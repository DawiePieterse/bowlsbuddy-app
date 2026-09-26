<?php

namespace App\Filament\Resources\Rinks;

use App\Filament\Resources\Rinks\Pages\EditRink;
use App\Filament\Resources\Rinks\Pages\ListRinks;
use App\Models\Rink;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rink settings (PLAN.md section 7). The seconds columns are edited as minutes, hours or days;
 * the Edit page converts them. Rinks are configured, not created or deleted, from the panel.
 */
class RinkResource extends Resource
{
    protected static ?string $model = Rink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rink')->schema([
                TextInput::make('name')->required()->maxLength(32)
                    ->helperText('The part before the dash is the green: A-1 is on green A.'),
                Select::make('status')->options([
                    'enabled' => 'Open for booking',
                    'readonly' => 'Visible, not bookable',
                    'disabled' => 'Hidden',
                ])->required(),
                TextInput::make('capacity')->numeric()->minValue(1)->maxValue(8)->required()
                    ->label('Players per rink'),
                TextInput::make('priority')->numeric()->required()
                    ->helperText('Sort order on the calendar.'),
            ])->columns(2),

            Section::make('Times')->schema([
                TextInput::make('time_start')->label('First slot starts')->placeholder('12:00')->required()
                    ->regex('/^\d{1,2}:\d{2}(:\d{2})?$/'),
                TextInput::make('time_end')->label('Last slot ends')->placeholder('17:00')->required()
                    ->regex('/^\d{1,2}:\d{2}(:\d{2})?$/'),
                TextInput::make('slot_minutes')->label('Slot length (minutes)')->numeric()->minValue(15)->maxValue(240)->required(),
                TextInput::make('booking_range_days')->label('Bookable ahead (days)')->numeric()->minValue(1)->maxValue(60)->required(),
                TextInput::make('cancel_range_hours')->label('Cancel cut-off (hours)')->numeric()->minValue(0)->maxValue(168)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'enabled' => 'success',
                    'readonly' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('capacity')->label('Players'),
                TextColumn::make('time_start')->label('From')->formatStateUsing(fn (string $state) => substr($state, 0, 5)),
                TextColumn::make('time_end')->label('To')->formatStateUsing(fn (string $state) => substr($state, 0, 5)),
                TextColumn::make('time_block')->label('Slot')->formatStateUsing(fn (int $state) => ($state / 60).' min'),
            ])
            ->defaultSort('priority')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRinks::route('/'),
            'edit' => EditRink::route('/{record}/edit'),
        ];
    }
}
