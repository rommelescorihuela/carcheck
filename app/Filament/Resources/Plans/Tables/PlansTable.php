<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Enums\PlanInterval;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                TextColumn::make('interval')
                    ->label('Intervalo')
                    ->badge()
                    ->formatStateUsing(fn (PlanInterval $state): string => $state->label()),
                TextColumn::make('trial_days')
                    ->label('Prueba')
                    ->suffix(' días')
                    ->sortable(),
                TextColumn::make('max_vehicles')
                    ->label('Vehículos')
                    ->placeholder('Sin límite'),
                TextColumn::make('max_members')
                    ->label('Miembros')
                    ->placeholder('Sin límite'),
                TextColumn::make('subscriptions_count')
                    ->label('Suscripciones')
                    ->counts('subscriptions')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('sort')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activo'),
                SelectFilter::make('interval')
                    ->label('Intervalo')
                    ->options([
                        PlanInterval::Monthly->value => PlanInterval::Monthly->label(),
                        PlanInterval::Yearly->value => PlanInterval::Yearly->label(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
