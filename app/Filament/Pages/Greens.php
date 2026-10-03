<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use App\Services\GreenManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * Green management (some clubs have one green, others three): add a green with its rinks,
 * rename one everywhere, hide one from members, or delete an empty one. Individual rink
 * settings stay on the Rinks page.
 */
class Greens extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.greens';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.config');
    }

    /** @return array<string, array{rinks: string, hidden: bool, bookings: int}> */
    public function greens(): array
    {
        $rows = [];

        foreach (app(GreenManager::class)->all() as $green => $rinks) {
            $rows[$green] = [
                'rinks' => $rinks->pluck('name')->implode(', '),
                'hidden' => $rinks->every(fn (Rink $rink) => $rink->status === 'disabled'),
                'bookings' => Booking::query()->whereIn('sid', $rinks->pluck('sid'))->count(),
            ];
        }

        return $rows;
    }

    protected function getHeaderActions(): array
    {
        $greenOptions = fn (): array => array_combine(
            $names = array_keys(app(GreenManager::class)->all()),
            $names,
        );

        return [
            Action::make('addGreen')
                ->label('Add a green')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('name')->label('Green name')->required()->maxLength(10)
                        ->helperText('Letters and numbers, e.g. C. Rinks become C-1, C-2, ...'),
                    TextInput::make('rinks')->label('Number of rinks')->numeric()->minValue(1)->maxValue(20)
                        ->default(6)->required()
                        ->helperText('Playing times and capacity are copied from the existing rinks.'),
                ])
                ->action(fn (array $data) => $this->run(function () use ($data) {
                    app(GreenManager::class)->add((string) $data['name'], (int) $data['rinks']);

                    return 'Green added';
                })),

            Action::make('renameGreen')
                ->label('Rename a green')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Select::make('green')->options($greenOptions)->required(),
                    TextInput::make('name')->label('New name')->required()->maxLength(10)
                        ->helperText('The rinks, green events and closed days are renamed with it.'),
                ])
                ->action(fn (array $data) => $this->run(function () use ($data) {
                    app(GreenManager::class)->rename((string) $data['green'], (string) $data['name']);

                    return 'Green renamed';
                })),

            Action::make('hideGreen')
                ->label('Hide or show a green')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('gray')
                ->schema([
                    Select::make('green')->options($greenOptions)->required(),
                    Select::make('visibility')->options([
                        'hide' => 'Hide from members',
                        'show' => 'Show to members',
                    ])->required(),
                ])
                ->action(function (array $data) {
                    $this->run(function () use ($data) {
                        app(GreenManager::class)->setHidden((string) $data['green'], $data['visibility'] === 'hide');

                        return $data['visibility'] === 'hide' ? 'Green hidden from members' : 'Green shown to members';
                    });

                    // Upcoming bookings on a hidden green are cancelled, and the Secretary lets the members know.
                    if ($data['visibility'] === 'hide') {
                        AffectedBookings::cancelDisplaced((string) $data['green']);
                    }
                }),

            Action::make('deleteGreen')
                ->label('Delete a green')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Only a green without any bookings can be deleted. Its rinks, events and closed days go with it.')
                ->schema([
                    Select::make('green')->options($greenOptions)->required(),
                ])
                ->action(fn (array $data) => $this->run(function () use ($data) {
                    app(GreenManager::class)->delete((string) $data['green']);

                    return 'Green deleted';
                })),
        ];
    }

    /** Runs a green operation and turns its refusals into notifications. */
    private function run(callable $operation): void
    {
        try {
            $title = $operation();
        } catch (RuntimeException $refused) {
            Notification::make()->title($refused->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($title)->success()->send();
    }
}
