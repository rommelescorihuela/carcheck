<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),
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
                TextColumn::make('owned_organizations_count')
                    ->label('Org. propias')
                    ->counts('ownedOrganizations')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currency')
                    ->label('Moneda')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol de plataforma')
                    ->relationship('roles', 'name')
                    ->preload(),
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
