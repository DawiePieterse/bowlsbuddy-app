<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

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
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return User::query()->where('status', 'disabled')->exists() ? 'waiting' : 'all';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
