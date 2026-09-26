<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Rink;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Secretary's booking management (PLAN.md section 7): list, create for a member, edit,
 * cancel, delete. The date and times live in the booking's reservation; the Create/Edit pages
 * move them in and out of the form.
 */
class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking')->schema([
                Select::make('uid')
                    ->label('Member')
                    ->relationship('user', 'alias')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('sid')
                    ->label('Rink')
                    ->options(Rink::query()->orderBy('priority')->pluck('name', 'sid'))
                    ->required(),
                DatePicker::make('date')->required()->native(false)->displayFormat('D j M Y'),
                TimePicker::make('time_start')->label('From')->seconds(false)->required(),
                TimePicker::make('time_end')->label('To')->seconds(false)->required()->after('time_start'),
                Select::make('quantity')->label('Players')->options([1 => '1', 2 => '2'])->default(1)->required(),
                Select::make('status')->options(['single' => 'Booked', 'cancelled' => 'Cancelled'])
                    ->default('single')->required(),
            ])->columns(2),

            Section::make('Details')->schema([
                TagsInput::make('player_names')
                    ->label('Other players')
                    ->placeholder('Full first and last names'),
                Textarea::make('notes')->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'rink', 'reservations']))
            ->columns([
                TextColumn::make('firstReservation')
                    ->label('When')
                    ->state(function (Booking $record): string {
                        $reservation = $record->reservations->first();

                        if (! $reservation) {
                            return '-';
                        }

                        return $reservation->date->format('D j M Y').', '
                            .substr($reservation->time_start, 0, 5).'-'.substr($reservation->time_end, 0, 5);
                    }),
                TextColumn::make('rink.name')->label('Rink')->sortable(),
                TextColumn::make('user.alias')->label('Member')->searchable()->sortable(),
                TextColumn::make('quantity')->label('Players'),
                TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'cancelled' ? 'gray' : 'success'),
            ])
            ->defaultSort('bid', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['single' => 'Booked', 'cancelled' => 'Cancelled']),
                SelectFilter::make('sid')->label('Rink')
                    ->options(Rink::query()->orderBy('priority')->pluck('name', 'sid')),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->visible(fn (Booking $record): bool => $record->status === 'single')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => $record->update(['status' => 'cancelled']))
                    ->successNotificationTitle('Booking cancelled'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }

    /**
     * Splits the virtual reservation and meta fields off the booking columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function bookingColumns(array $data): array
    {
        unset($data['date'], $data['time_start'], $data['time_end'], $data['player_names'], $data['notes']);

        $data['visibility'] ??= 'public';

        return $data;
    }

    /** @param  array<string, mixed>  $state  the form's raw state */
    public static function saveReservationAndMeta(Booking $booking, array $state): void
    {
        $values = [
            'date' => $state['date'],
            'time_start' => $state['time_start'],
            'time_end' => $state['time_end'],
        ];

        $reservation = $booking->reservations()->first();

        if ($reservation instanceof Reservation) {
            $reservation->update($values);
        } else {
            $booking->reservations()->create($values);
        }

        $booking->setPlayerNames(array_map(strval(...), (array) ($state['player_names'] ?? [])));
        $booking->setMeta('notes', trim((string) ($state['notes'] ?? '')) ?: null);
    }
}
