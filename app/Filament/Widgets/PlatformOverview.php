<?php

namespace App\Filament\Widgets;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Foto del negocio para el superadmin: quién entra, cuántas flotas hay y cuánto se mueve.
 */
class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Plataforma';

    protected function getStats(): array
    {
        $activeSubscriptions = Subscription::query()
            ->where(function ($query): void {
                $query
                    ->where(fn ($query) => $query
                        ->where('status', SubscriptionStatus::Trialing)
                        ->where('trial_ends_at', '>', now()))
                    ->orWhere(fn ($query) => $query
                        ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Canceled])
                        ->where('current_period_end', '>=', today()));
            })
            ->count();

        $pending = Subscription::query()
            ->where('status', SubscriptionStatus::PendingPayment)
            ->count();

        return [
            Stat::make('Usuarios', (string) User::query()->count())
                ->description(User::query()->where('created_at', '>=', now()->startOfMonth())->count().' este mes')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Organizaciones', (string) Organization::query()->count())
                ->description(Organization::query()->where('created_at', '>=', now()->startOfMonth())->count().' este mes')
                ->icon('heroicon-o-building-office-2'),
            Stat::make('Vehículos', (string) Vehicle::query()->count())
                ->description(Vehicle::query()->where('created_at', '>=', now()->startOfMonth())->count().' este mes')
                ->icon('heroicon-o-truck'),
            Stat::make('Suscripciones vigentes', (string) $activeSubscriptions)
                ->description($pending === 1 ? '1 pendiente de pago' : "{$pending} pendientes de pago")
                ->descriptionIcon($pending > 0 ? 'heroicon-o-exclamation-triangle' : null)
                ->descriptionColor($pending > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-credit-card'),
        ];
    }
}
