<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;

/**
 * Database updates and cache clearing from the browser, for hosts without a command line (InfinityFree).
 */
class Maintenance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.maintenance';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.config');
    }

    /** @return list<string> names of migrations that have not run yet */
    public function pendingMigrations(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('migrate')
                ->label('Run database updates')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Applies any pending database updates. Download a backup first.')
                ->disabled(fn () => $this->pendingMigrations() === [])
                ->action(function () {
                    Artisan::call('migrate', ['--force' => true]);
                    Artisan::call('optimize:clear');

                    Notification::make()->title('Database is up to date')->success()->send();
                }),

            Action::make('clearCaches')
                ->label('Clear caches')
                ->color('gray')
                ->action(function () {
                    Artisan::call('optimize:clear');

                    Notification::make()->title('Caches cleared')->success()->send();
                }),
        ];
    }
}
