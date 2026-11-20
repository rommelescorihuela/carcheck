<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Altas recientes: qué entró al sistema y a qué flota pertenece.
 */
class UltimosVehiculos extends TableWidget
{
    protected static ?string $heading = 'Últimos vehículos registrados';

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Vehicle::query()->with('owner')->latest())
            ->columns([
                TextColumn::make('plate')
                    ->label('Placa')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('make')
                    ->label('Vehículo')
                    ->state(fn (Vehicle $record): string => trim("{$record->make} {$record->model}") ?: '—')
                    ->description(fn (Vehicle $record): string => $record->year ? (string) $record->year : ''),
                TextColumn::make('owner.name')
                    ->label('Flota')
                    ->placeholder('Particular')
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->since()
                    ->alignEnd(),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Sin vehículos todavía');
    }
}
