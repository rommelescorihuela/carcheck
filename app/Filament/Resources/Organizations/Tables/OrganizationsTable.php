<?php

namespace App\Filament\Resources\Organizations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Propietario')
                    ->description(fn ($record): ?string => $record->owner?->email)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currentSubscription.plan.name')
                    ->label('Plan')
                    ->placeholder('Sin plan')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('currentSubscription.effective_status')
                    ->label('Suscripción')
                    ->placeholder('—')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'trialing' => 'Prueba gratuita',
                        'active' => 'Activa',
                        'pending_payment' => 'Pendiente de pago',
                        'canceled' => 'Cancelada',
                        'expired' => 'Vencida',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'trialing' => 'info',
                        'active' => 'success',
                        'pending_payment' => 'warning',
                        'canceled', 'expired' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('vehicles_count')
                    ->label('Vehículos')
                    ->counts('vehicles')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('members_count')
                    ->label('Miembros')
                    ->counts('members')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Moneda')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
