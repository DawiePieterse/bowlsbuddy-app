<?php

namespace App\Models;

use App\Models\Concerns\HasMeta;
use App\Models\Meta\UserMeta;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A member or staff account (bs_users).
 *
 * @property int $uid
 * @property string $alias
 * @property string $status
 * @property string|null $email
 * @property string|null $pw
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasMeta;

    public const CREATED_AT = 'created';

    public const UPDATED_AT = null;

    /** Status => label, as in the original app. */
    public const STATUSES = [
        'placeholder' => 'Placeholder',
        'deleted' => 'Deleted user',
        'blocked' => 'Blocked user',
        'disabled' => 'Waiting for activation',
        'enabled' => 'Enabled user',
        'assist' => 'Assist',
        'admin' => 'Admin',
    ];

    /** Statuses that may log in. */
    public const LOGIN_STATUSES = ['enabled', 'assist', 'admin'];

    /** Privilege => description. Admins have all; assists have those set as meta "allow.<privilege>" = "true". */
    public const PRIVILEGES = [
        'admin.user' => 'Can manage users',
        'admin.booking' => 'Can manage bookings',
        'admin.event' => 'Can manage events',
        'admin.config' => 'Can change configuration',
        'admin.see-menu' => 'Sees the admin menu',
        'calendar.see-past' => 'Sees past bookings',
        'calendar.see-data' => 'Sees names and data in calendar',
        'calendar.create-single-bookings' => 'Can create single bookings',
        'calendar.cancel-single-bookings' => 'Can cancel single bookings',
        'calendar.delete-single-bookings' => 'Can delete single bookings',
    ];

    protected $table = 'bs_users';

    protected $primaryKey = 'uid';

    protected $authPasswordName = 'pw';

    protected $fillable = ['alias', 'status', 'email', 'pw'];

    protected $attributes = ['remember_token' => null];

    protected $hidden = ['pw', 'remember_token'];

    public static function metaModel(): string
    {
        return UserMeta::class;
    }

    protected function casts(): array
    {
        return [
            'pw' => 'hashed',
            'login_detent' => 'datetime',
            'last_activity' => 'datetime',
            'created' => 'datetime',
        ];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'uid', 'uid');
    }

    public function canLogIn(): bool
    {
        return in_array($this->status, self::LOGIN_STATUSES, true);
    }

    public function hasPrivilege(string $privilege): bool
    {
        return match ($this->status) {
            'admin' => true,
            'assist' => $this->meta('allow.'.$privilege) === 'true',
            default => false,
        };
    }

    public function firstName(): string
    {
        return (string) $this->meta('firstname');
    }

    public function lastName(): string
    {
        return (string) $this->meta('lastname');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canLogIn() && $this->hasPrivilege('admin.see-menu');
    }

    public function getFilamentName(): string
    {
        return $this->alias;
    }
}
