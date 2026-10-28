<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Billing;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('owner_name')
                    ->label('Propietario')
                    ->getStateUsing(fn (Payment $record): string => $record->subscription->owner->name ?? '—'),
                TextColumn::make('subscription.plan.name')
                    ->label('Plan'),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn ($state, Payment $record): string => number_format((float) $state, 2).' '.$record->currency)
                    ->sortable(),
                TextColumn::make('amount_base')
                    ->label('Equivalente')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' USD'),
                TextColumn::make('method')
                    ->label('Método')
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Pending => 'warning',
                        PaymentStatus::Approved => 'success',
                        PaymentStatus::Rejected => 'danger',
                    }),
                TextColumn::make('paid_on')
                    ->label('Pagado el')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Reportado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ]),
            ])
            ->recordActions([
                Action::make('proof')
                    ->label('Comprobante')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (Payment $record): ?string => $record->getFirstMediaUrl('proof') ?: null)
                    ->openUrlInNewTab()
                    ->visible(fn (Payment $record): bool => $record->getFirstMedia('proof') !== null),
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Pending)
                    ->action(function (Payment $record): void {
                        try {
                            app(Billing::class)->approvePayment($record, auth()->user());
                        } catch (ValidationException $exception) {
                            self::notifyError(collect($exception->errors())->flatten()->first());

                            return;
                        }

                        Notification::make()->title('Pago aprobado')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Pending)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo (opcional)')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        try {
                            app(Billing::class)->rejectPayment($record, auth()->user(), $data['reason'] ?? null);
                        } catch (ValidationException $exception) {
                            self::notifyError(collect($exception->errors())->flatten()->first());

                            return;
                        }

                        Notification::make()->title('Pago rechazado')->success()->send();
                    }),
            ]);
    }

    private static function notifyError(?string $message): void
    {
        Notification::make()
            ->title('No se pudo revisar el pago')
            ->body($message ?? 'Inténtalo de nuevo.')
            ->danger()
            ->send();
    }
}
