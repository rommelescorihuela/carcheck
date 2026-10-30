<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cobros manuales: lo que espera revisión y lo aprobado en el mes.
 */
class BillingOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Facturación';

    protected function getStats(): array
    {
        $pending = Payment::query()->where('status', PaymentStatus::Pending)->count();

        $approvedThisMonth = Payment::query()
            ->where('status', PaymentStatus::Approved)
            ->whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->with('subscription.plan')
            ->get();

        // `amount_base` está en la moneda de cada plan: se agrupa para no sumar monedas distintas.
        $revenue = $approvedThisMonth
            ->groupBy(fn (Payment $payment): string => strtoupper($payment->subscription?->plan?->currency ?? 'USD'))
            ->map(fn ($payments, string $currency): string => number_format(
                $payments->sum(fn (Payment $payment): float => $payment->amount_base), 2,
            ).' '.$currency)
            ->join(' · ');

        $renewals = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereBetween('current_period_end', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->count();

        return [
            Stat::make('Pagos por revisar', (string) $pending)
                ->description($pending > 0 ? 'Requieren aprobación o rechazo' : 'Todo al día')
                ->descriptionIcon($pending > 0 ? 'heroicon-o-clock' : 'heroicon-o-check-circle')
                ->descriptionColor($pending > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-banknotes')
                ->color($pending > 0 ? 'warning' : 'success')
                ->url(PaymentResource::getUrl('index')),
            Stat::make('Pagos aprobados (mes)', (string) $approvedThisMonth->count())
                ->description($revenue !== '' ? $revenue : 'Sin pagos aprobados')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success'),
            Stat::make('Vencen esta semana', (string) $renewals)
                ->description('Suscripciones a renovar')
                ->icon('heroicon-o-calendar-days')
                ->color($renewals > 0 ? 'info' : 'gray'),
        ];
    }
}
