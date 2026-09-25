<?php

namespace App\Filament\Pages;

use App\Models\GreenDirection;
use App\Models\User;
use App\Support\GreenDirections;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * The Club Secretary sets the direction of play (north-south or east-west) per green. It holds from the chosen
 * day until it is changed again.
 */
class PlayDirection extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $title = 'Direction of play';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.play-direction';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.event');
    }

    /** @return array<string, array<string, string|null>> date => green => direction, for the next 14 days */
    public function upcoming(): array
    {
        return app(GreenDirections::class)->forDays(Carbon::today(), Carbon::today()->addDays(13));
    }

    protected function getHeaderActions(): array
    {
        $greens = app(GreenDirections::class)->greens();

        return [
            Action::make('set')
                ->label('Set direction')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->schema([
                    DatePicker::make('date')
                        ->label('From')
                        ->helperText('Holds on the days after it until you change it again.')
                        ->native(false)
                        ->displayFormat('D j M Y')
                        ->default(today())
                        ->minDate(today())
                        ->required(),
                    CheckboxList::make('greens')
                        ->options(array_combine($greens, array_map(fn ($green) => "Green $green", $greens)))
                        ->default($greens)
                        ->columns(2)
                        ->required(),
                    ToggleButtons::make('direction')
                        ->options(GreenDirection::DIRECTIONS)
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data) {
                    foreach ($data['greens'] as $green) {
                        app(GreenDirections::class)->set($green, $data['date'], $data['direction']);
                    }

                    Notification::make()->title('Direction of play saved')->success()->send();
                }),
        ];
    }
}
