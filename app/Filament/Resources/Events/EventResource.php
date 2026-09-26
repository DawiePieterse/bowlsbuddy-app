<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\Rink;
use App\Services\GreenService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Blocked time (PLAN.md section 7): an event blocks one rink, one green or all rinks. The name
 * and description live in bs_events_meta; the scope is the sid column plus the "green" meta.
 */
class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')->schema([
                TextInput::make('name')->required()->maxLength(100)
                    ->helperText('Shown purple on the greens overview and calendar.'),
                Textarea::make('description')->rows(2),
                DateTimePicker::make('datetime_start')->label('From')->seconds(false)->required(),
                DateTimePicker::make('datetime_end')->label('To')->seconds(false)->required()->after('datetime_start'),
            ])->columns(2),

            Section::make('What it blocks')->schema([
                Radio::make('scope')
                    ->hiddenLabel()
                    ->options([
                        'all' => 'All rinks',
                        'green' => 'One green',
                        'rink' => 'One rink',
                    ])
                    ->default('all')
                    ->live()
                    ->required(),
                Select::make('green')
                    ->options(fn (): array => array_map(
                        fn (string $green) => 'Green '.$green,
                        array_combine(array_keys(app(GreenService::class)->greens()), array_keys(app(GreenService::class)->greens())),
                    ))
                    ->visible(fn (Get $get): bool => $get('scope') === 'green')
                    ->requiredIf('scope', 'green'),
                Select::make('sid')
                    ->label('Rink')
                    ->options(Rink::query()->orderBy('priority')->pluck('name', 'sid'))
                    ->visible(fn (Get $get): bool => $get('scope') === 'rink')
                    ->requiredIf('scope', 'rink'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['rink', 'metaEntries']))
            ->columns([
                TextColumn::make('name')->state(fn (Event $record): string => (string) $record->meta('name', '-')),
                TextColumn::make('datetime_start')->label('From')->dateTime('D j M Y H:i')->sortable(),
                TextColumn::make('datetime_end')->label('To')->dateTime('D j M Y H:i'),
                TextColumn::make('blocks')->state(function (Event $record): string {
                    if ($record->sid !== null) {
                        return 'Rink '.$record->rink?->name;
                    }

                    $green = $record->meta('green');

                    return $green ? 'Green '.$green : 'All rinks';
                })->badge()->color('info'),
            ])
            ->defaultSort('datetime_start', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    /**
     * Turns the scope fields into the sid column; the meta fields are stripped.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function eventColumns(array $data): array
    {
        $data['sid'] = ($data['scope'] ?? 'all') === 'rink' ? $data['sid'] : null;
        $data['status'] ??= 'enabled';

        unset($data['scope'], $data['green'], $data['name'], $data['description']);

        return $data;
    }

    /** @param  array<string, mixed>  $state  the form's raw state */
    public static function saveMeta(Event $event, array $state): void
    {
        $event->setMeta('name', trim((string) ($state['name'] ?? '')) ?: null);
        $event->setMeta('description', trim((string) ($state['description'] ?? '')) ?: null);
        $event->setMeta(
            'green',
            ($state['scope'] ?? 'all') === 'green' ? ((string) ($state['green'] ?? '') ?: null) : null,
        );
    }
}
