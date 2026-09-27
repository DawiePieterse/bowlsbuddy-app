<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\GreenManager;
use App\Support\Licensing\InvalidLicence;
use App\Support\Licensing\Modules;
use App\Support\Licensing\ModuleState;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The club's Bowls Buddy licence: what it covers, until when, and where the Secretary pastes a new key.
 * Hosts without a command line install licences here (docs/MODULES.md section 6).
 *
 * @property-read Schema $form
 */
class Licence extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.licence';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.config');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('key')->label('Licence key')->required()->rows(3)
                ->helperText('Paste the whole key Bowls Buddy sent you. It replaces the current licence.'),
        ])->statePath('data');
    }

    public function install(Modules $modules): void
    {
        $key = (string) ($this->form->getState()['key'] ?? '');

        try {
            $licence = $modules->install($key);
        } catch (InvalidLicence $invalid) {
            Notification::make()->title('Licence not installed')->body($invalid->getMessage())->danger()->send();

            return;
        }

        $this->form->fill();

        Notification::make()->title('Licence installed')
            ->body('Valid until '.$licence->expires->format('j F Y').'.')
            ->success()->send();
    }

    /**
     * Every module with its state, the included ones first.
     *
     * @return list<array{key: string, name: string, description: string, state: ModuleState}>
     */
    public function moduleRows(): array
    {
        $modules = app(Modules::class);
        $rows = [];

        foreach ($modules->all() as $key => $module) {
            $rows[] = ['key' => $key, 'name' => $module['name'], 'description' => $module['description'], 'state' => $modules->state($key)];
        }

        usort($rows, fn (array $a, array $b) => ($a['state'] === ModuleState::Locked) <=> ($b['state'] === ModuleState::Locked));

        return $rows;
    }

    public function greensInUse(): int
    {
        return count(app(GreenManager::class)->all());
    }

    public static function badgeColour(ModuleState $state): string
    {
        return match ($state) {
            ModuleState::Active => 'success',
            ModuleState::Grace => 'warning',
            ModuleState::ReadOnly => 'danger',
            ModuleState::Locked => 'gray',
        };
    }
}
