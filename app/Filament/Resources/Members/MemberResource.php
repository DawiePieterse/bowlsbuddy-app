<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\User;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Secretary's member management (PLAN.md section 7): search, create, edit, activate, set a
 * temporary password, privileges. Privileges live in bs_users_meta as "allow.<privilege>"; the
 * Edit/Create pages move them in and out of the form's "privileges" field.
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
        return $table
            ->columns([
                TextColumn::make('alias')->label('Name')->searchable()->sortable(),
                TextColumn::make('phone')->label('Cellphone')->searchable()
                    ->formatStateUsing(fn (?string $state) => Phone::pretty($state)),
                TextColumn::make('email')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()
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
                TextColumn::make('last_activity')->dateTime('j M Y H:i')->label('Last active')->sortable(),
            ])
            ->defaultSort('alias')
            ->filters([
                SelectFilter::make('status')->options(User::STATUSES),
            ])
            ->recordActions([
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
            ])
            ->toolbarActions([]);
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

        return $data;
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

        $granted = (array) ($state['privileges'] ?? []);

        foreach (array_keys(User::PRIVILEGES) as $privilege) {
            $user->setMeta('allow.'.$privilege, in_array($privilege, $granted, true) ? 'true' : null);
        }
    }
}
