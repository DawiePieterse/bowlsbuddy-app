<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\MemberPayment;
use App\Models\User;
use App\Services\Membership;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A member's payment history on their page: membership fees the Secretary records by hand. Recording a
 * payment suggests this membership year and the fee of the member's membership type.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    protected static ?string $modelLabel = 'payment';

    /** The history shows with the page instead of waiting to be scrolled into view. */
    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        $membership = app(Membership::class);

        return $schema->components([
            DatePicker::make('paid_on')->label('Paid on')->default(now())->maxDate(now())->required(),
            Select::make('year')->label('For the membership year')
                ->options($membership->yearOptions())
                ->default($membership->currentYear())
                ->required(),
            TextInput::make('amount')->label('Amount (R)')
                ->numeric()->minValue(0)->maxValue(100000)->step(0.01)
                ->default(fn (): ?float => $membership->feeFor($this->member()->meta(Membership::TYPE)))
                ->required(),
            Select::make('method')->options(MemberPayment::METHODS)->default('eft')->required(),
            TextInput::make('reference')->maxLength(100)->placeholder('Receipt number or EFT reference'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $membership = app(Membership::class);
        $year = $membership->currentYear();

        return $table
            ->description(fn (): string => $membership->hasPaid($this->member(), $year)
                ? 'Paid for '.$membership->yearLabel($year).'.'
                : 'Nothing paid for '.$membership->yearLabel($year).' yet.')
            ->columns([
                TextColumn::make('paid_on')->label('Paid on')->date('j M Y')->sortable(),
                TextColumn::make('year')->label('For')
                    ->formatStateUsing(fn (int $state): string => $membership->yearLabel($state)),
                TextColumn::make('amount')->alignEnd()
                    ->formatStateUsing(fn (string $state): string => Membership::rand($state)),
                TextColumn::make('method')
                    ->formatStateUsing(fn (string $state): string => MemberPayment::methodLabel($state)),
                TextColumn::make('reference')->placeholder('—')->visibleFrom('md'),
            ])
            ->defaultSort('paid_on', 'desc')
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Record a membership fee when the member pays.')
            ->headerActions([
                CreateAction::make()
                    ->label('Record payment')
                    ->modalHeading('Record a payment')
                    ->modalSubmitActionLabel('Record payment')
                    ->createAnother(false)
                    ->successNotificationTitle('Payment recorded')
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'recorded_by' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private function member(): User
    {
        /** @var User $member */
        $member = $this->getOwnerRecord();

        return $member;
    }
}
