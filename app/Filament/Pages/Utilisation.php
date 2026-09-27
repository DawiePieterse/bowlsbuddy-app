<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\RinkUtilisation;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * The utilisation heatmap: how many hours each rink was booked over the last week, month, quarter or
 * year, one heatmap per direction of play so the greenkeeper can see wear both ways.
 */
class Utilisation extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 60;

    protected string $view = 'filament.pages.utilisation';

    /** Sequential ramp for the cells: the panel's primary green, 100 to 700. */
    public const RAMP = ['#dcebdd', '#bcd7bd', '#93bd96', '#62996a', '#2f7a38', '#1b5e20'];

    #[Url]
    public string $period = 'month';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.booking');
    }

    public function mount(): void
    {
        if (! array_key_exists($this->period, RinkUtilisation::PERIODS)) {
            $this->period = 'month';
        }
    }

    public function setPeriod(string $period): void
    {
        if (array_key_exists($period, RinkUtilisation::PERIODS)) {
            $this->period = $period;
        }
    }

    /** @return array<string, string> */
    public function periods(): array
    {
        return RinkUtilisation::PERIODS;
    }

    /** @return array<string, mixed> see RinkUtilisation::for() */
    public function heatmap(): array
    {
        return app(RinkUtilisation::class)->for($this->period);
    }

    public function directionLabel(string $direction): string
    {
        return RinkUtilisation::directionLabel($direction);
    }

    /**
     * The cell's shade: the club's green from light to dark by hours relative to the busiest cell of
     * the green, and ink that stays readable on it. Empty cells stay on the surface.
     *
     * @return array{background: string|null, ink: string}
     */
    public function shade(float $hours, float $max): array
    {
        if ($hours <= 0 || $max <= 0) {
            return ['background' => null, 'ink' => 'inherit'];
        }

        $step = min(count(self::RAMP) - 1, (int) floor($hours / $max * count(self::RAMP)));

        return ['background' => self::RAMP[$step], 'ink' => $step >= 3 ? '#ffffff' : '#0e3413'];
    }

    public static function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }
}
