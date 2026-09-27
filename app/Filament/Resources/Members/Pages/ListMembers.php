<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\User;
use App\Services\MemberUsage;
use App\Services\RinkUtilisation;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    /** The period of the "Use of rinks" tab: week, month, quarter or year (RinkUtilisation::PERIODS). */
    #[Url]
    public string $usagePeriod = 'month';

    /** @var array{hours: float, bookings: int, members: int}|null */
    private ?array $usageTotals = null;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $waiting = User::query()->where('status', 'disabled')->count();

        return [
            'all' => Tab::make('All members'),
            'waiting' => Tab::make('Waiting for approval')
                ->badge($waiting ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'disabled')),
            'usage' => Tab::make('Use of rinks')
                ->modifyQueryUsing(fn (Builder $query) => app(MemberUsage::class)->applyTo($query, ...$this->usageRange())),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return User::query()->where('status', 'disabled')->exists() ? 'waiting' : 'all';
    }

    public function mount(): void
    {
        parent::mount();

        if (! array_key_exists($this->usagePeriod, RinkUtilisation::PERIODS)) {
            $this->usagePeriod = 'month';
        }
    }

    public function isShowingUsage(): bool
    {
        return $this->activeTab === 'usage';
    }

    public function setUsagePeriod(string $period): void
    {
        if (array_key_exists($period, RinkUtilisation::PERIODS)) {
            $this->usagePeriod = $period;
            $this->usageTotals = null;
            $this->resetPage();
        }
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function usageRange(): array
    {
        return RinkUtilisation::range($this->usagePeriod);
    }

    /** @return array{hours: float, bookings: int, members: int} */
    public function usageTotals(): array
    {
        return $this->usageTotals ??= app(MemberUsage::class)->totals(...$this->usageRange());
    }

    /** A member's hours as a percentage of everyone's hours in the period. */
    public function usageShare(float $hours): float
    {
        $total = $this->usageTotals()['hours'];

        return $total > 0 ? round($hours / $total * 100, 1) : 0.0;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
