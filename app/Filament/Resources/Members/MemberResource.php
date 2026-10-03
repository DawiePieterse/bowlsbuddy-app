<?php

namespace App\Filament\Resources\Members;

use App\Filament\Pages\MessageMembers;
use App\Filament\Pages\Utilisation;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\RelationManagers\PaymentsRelationManager;
use App\Models\User;
use App\Services\Membership;
use App\Support\Phone;
use App\Support\WhatsApp;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Secretary's member management (PLAN.md section 7): search, create, edit, activate, set a
 * temporary password, privileges, membership details and payments, and WhatsApp messages. Privileges
 * live in bs_users_meta as "allow.<privilege>", the membership details as meta too (Membership::TYPE...);
 * the Edit/Create pages move them in and out of the form's fields.
 */
class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'member';

    protected static ?string $recordTitleAttribute = 'alias';

    /** New registrations waiting for the Secretary, shown as a count on the Members menu item. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = User::query()->where('status', 'disabled')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for approval';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Member')->schema([
                TextInput::make('firstname')->label('First name')->required()->maxLength(100),
                TextInput::make('lastname')->label('Surname')->required()->maxLength(100),
                TextInput::make('phone')->label('Cellphone (WhatsApp)')->tel()
                    ->placeholder('082 123 4567')
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                        if (filled($value) && Phone::normalize((string) $value) === null) {
                            $fail('Please give a South African cellphone number, like 082 123 4567.');
                        }
                    })
                    ->requiredWithout('email'),
                TextInput::make('email')->email()->unique('bs_users', 'email', ignoreRecord: true)
                    ->requiredWithout('phone')
                    ->helperText('Only needed for accounts without a cellphone number.'),
                Select::make('status')->options([
                    'disabled' => 'Waiting for approval / not active',
                    'enabled' => 'Member',
                    'assist' => 'Assistant (privileges below)',
                    'admin' => 'Admin (all privileges)',
                ])->default('enabled')->required(),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText('Leave empty to keep the current password.'),
            ])->columns(2),

            Section::make('Membership')->schema([
                Select::make(Membership::TYPE)->label('Membership type')
                    ->options(fn (): array => app(Membership::class)->typeOptions())
                    ->placeholder('None'),
                DatePicker::make(Membership::JOINED)->label('Member of the club since')->maxDate(now()),
                Select::make(Membership::GENDER)->label('Gender')->options(Membership::GENDERS)->placeholder('Not given'),
                DatePicker::make(Membership::BIRTHDAY)->label('Birthday')->maxDate(now())
                    ->helperText('For the birthday wishes on the dashboard.'),
            ])->columns(2),

            Section::make('Privileges')
                ->description('Only used for assistants. Admins can do everything.')
                ->schema([
                    CheckboxList::make('privileges')
                        ->hiddenLabel()
                        ->options(User::PRIVILEGES)
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $membership = app(Membership::class);
        $year = $membership->currentYear();
        $paidLabel = 'Paid for '.$membership->currentYearLabel();
        $types = array_keys($membership->types());

        return $table
            ->columns([
                // The cellphone number (or email) sits under the name, so the list fits on a phone.
                TextColumn::make('alias')->label('Name')
                    ->description(fn (User $record): ?string => $record->phone ? Phone::pretty($record->phone) : $record->email)
                    ->searchable(['alias', 'phone', 'email'])
                    ->sortable(),
                TextColumn::make('membership')->label('Membership')
                    ->state(fn (User $record): ?string => $record->meta(Membership::TYPE))
                    ->placeholder('—')
                    ->visibleFrom('md')
                    ->hidden(fn ($livewire): bool => self::showingUsage($livewire)),
                IconColumn::make('paid_current')
                    ->label('Paid')
                    ->tooltip($paidLabel)
                    ->boolean()
                    ->alignCenter()
                    ->hidden(fn ($livewire): bool => self::showingUsage($livewire)),
                TextColumn::make('usage_hours')->label('Hours')
                    ->formatStateUsing(fn ($state): string => Utilisation::formatHours((float) $state))
                    ->alignEnd()
                    ->sortable()
                    ->visible(fn ($livewire): bool => self::showingUsage($livewire)),
                TextColumn::make('usage_bookings')->label('Bookings')
                    ->alignEnd()
                    ->sortable()
                    ->visibleFrom('md')
                    ->visible(fn ($livewire): bool => self::showingUsage($livewire)),
                TextColumn::make('usage_share')->label('Share of total')
                    ->state(fn (User $record, $livewire): float => $livewire->usageShare((float) $record->getAttribute('usage_hours')))
                    ->formatStateUsing(fn (float $state): string => number_format($state, 1).'%')
                    ->alignEnd()
                    ->visible(fn ($livewire): bool => self::showingUsage($livewire)),
                // On a phone the "Waiting for approval" tab stands in for the status column.
                TextColumn::make('status')->badge()
                    ->visibleFrom('md')
                    ->hidden(fn ($livewire): bool => self::showingUsage($livewire))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'disabled' => 'Waiting for approval',
                        'enabled' => 'Member',
                        'assist' => 'Assistant',
                        'admin' => 'Admin',
                        default => User::STATUSES[$state] ?? $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'assist' => 'warning',
                        'enabled' => 'success',
                        'disabled' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('last_activity')->dateTime('j M Y, H:i')->label('Last active')->sortable()->visibleFrom('xl')
                    ->hidden(fn ($livewire): bool => self::showingUsage($livewire)),
            ])
            // Whether each member has paid for the current membership year, and their details for the columns.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('metaEntries')
                ->withExists(['payments as paid_current' => fn (Builder $payments) => $payments->where('year', $year)]))
            ->header(fn ($livewire): ?View => self::showingUsage($livewire)
                ? view('filament.members.usage-header', ['page' => $livewire])
                : null)
            // The use of rinks tab ranks members from most to fewest hours; the others go by name.
            ->defaultSort(fn (Builder $query, $livewire): Builder => self::showingUsage($livewire)
                ? $query->orderByDesc('usage_hours')->orderBy('alias')
                : $query->orderBy('alias'))
            ->filters([
                SelectFilter::make('status')->options(User::STATUSES),
                SelectFilter::make('membership')->label('Membership type')
                    ->options(array_combine($types, $types))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereMeta(Membership::TYPE, $data['value'])
                        : $query),
                TernaryFilter::make('paid')
                    ->label($paidLabel)
                    ->queries(
                        true: fn (Builder $query) => $query->wherePaidFor($year),
                        false: fn (Builder $query) => $query->wherePaidFor($year, false),
                    ),
                SelectFilter::make('gender')
                    ->options(Membership::GENDERS)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereMeta(Membership::GENDER, $data['value'])
                        : $query),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->tooltip('Open a WhatsApp chat')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->iconButton()
                    ->color('gray')
                    ->visible(fn (User $record): bool => filled($record->phone))
                    ->url(fn (User $record): ?string => WhatsApp::to($record->phone), shouldOpenInNewTab: true),
                Action::make('activate')
                    ->label('Approve')
                    ->button()
                    ->color('primary')
                    ->visible(fn (User $record): bool => $record->status === 'disabled')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->modalIcon(Heroicon::OutlinedCheckCircle)
                    ->modalHeading(fn (User $record): string => 'Approve '.$record->alias.'?')
                    ->modalDescription('They can log in and book rinks straight away.')
                    ->modalSubmitActionLabel('Approve')
                    ->action(function (User $record): void {
                        $record->update(['status' => 'enabled']);
                    })
                    ->successNotificationTitle('Member approved')
                    ->successRedirectUrl(fn (): string => static::getUrl()),
                ActionGroup::make([
                    Action::make('temporaryPassword')
                        ->label('Set temporary password')
                        ->visible(fn (User $record): bool => $record->uid !== auth()->id())
                        ->icon(Heroicon::OutlinedKey)
                        ->requiresConfirmation()
                        ->modalHeading('Set a temporary password?')
                        ->modalDescription('The member logs in with it and picks a new one under My account.')
                        ->action(function (User $record): void {
                            $password = Str::password(10, symbols: false);

                            $record->update(['pw' => $password]);

                            Notification::make()
                                ->title('Temporary password set')
                                ->body("Give the member this password: {$password}")
                                ->success()
                                ->persistent()
                                ->send();
                        }),
                    EditAction::make(),
                ])->label('Password and edit')->tooltip('Password and edit'),
            ])
            ->toolbarActions([
                BulkAction::make('message')
                    ->label('Send a WhatsApp message')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->action(fn (Collection $records) => redirect(MessageMembers::getUrl(['members' => $records->pluck('uid')->sort()->join(',')]))),
            ]);
    }

    /** Whether the table shows the "Use of rinks" tab of the Members list. */
    private static function showingUsage(mixed $livewire): bool
    {
        return $livewire instanceof ListMembers && $livewire->isShowingUsage();
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    /**
     * Turns the form's virtual fields into bs_users columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mapFormData(array $data, ?int $ignoreUid = null): array
    {
        $data['alias'] = trim(($data['firstname'] ?? '').' '.($data['lastname'] ?? ''));

        $data['phone'] = filled($data['phone'] ?? null) ? Phone::normalize((string) $data['phone']) : null;

        if ($data['phone'] !== null && User::query()
            ->where('phone', $data['phone'])
            ->when($ignoreUid !== null, fn ($query) => $query->where('uid', '!=', $ignoreUid))
            ->exists()) {
            throw ValidationException::withMessages([
                'data.phone' => 'An account with this cellphone number already exists.',
            ]);
        }

        if (blank($data['email'] ?? null)) {
            $data['email'] = null;
        }

        if (filled($data['password'] ?? null)) {
            $data['pw'] = $data['password'];
        }

        unset($data['firstname'], $data['lastname'], $data['privileges'], $data['password']);

        return Arr::except($data, Membership::DETAILS);
    }

    /**
     * Stores the name and privilege fields in bs_users_meta.
     *
     * @param  array<string, mixed>  $state  the form's raw state
     */
    public static function saveMeta(User $user, array $state): void
    {
        $user->setMeta('firstname', trim((string) ($state['firstname'] ?? '')) ?: null);
        $user->setMeta('lastname', trim((string) ($state['lastname'] ?? '')) ?: null);

        foreach (Membership::DETAILS as $key) {
            $user->setMeta($key, filled($state[$key] ?? null) ? substr((string) $state[$key], 0, 100) : null);
        }

        $granted = (array) ($state['privileges'] ?? []);

        foreach (array_keys(User::PRIVILEGES) as $privilege) {
            $user->setMeta('allow.'.$privilege, in_array($privilege, $granted, true) ? 'true' : null);
        }
    }
}
