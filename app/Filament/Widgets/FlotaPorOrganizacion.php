<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use Filament\Widgets\ChartWidget;

/**
 * Dónde está la flota: vehículos registrados por organización.
 */
class FlotaPorOrganizacion extends ChartWidget
{
    protected ?string $heading = 'Vehículos por organización';

    protected ?string $description = 'Las ocho flotas con más vehículos registrados';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '18rem';

    protected function getData(): array
    {
        $filas = Organization::query()
            ->withCount('vehicles')
            ->orderByDesc('vehicles_count')
            ->take(8)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Vehículos',
                'data' => $filas->pluck('vehicles_count')->map(fn ($n): int => (int) $n)->all(),
                'backgroundColor' => 'rgba(74, 124, 169, 0.85)',
                'hoverBackgroundColor' => '#77a2c8',
                'borderColor' => '#77a2c8',
                'borderWidth' => 1,
                'borderRadius' => 6,
            ]],
            'labels' => $filas->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
