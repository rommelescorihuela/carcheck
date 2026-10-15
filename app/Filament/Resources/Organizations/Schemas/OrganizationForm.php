<?php

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Select::make('owner_id')
                    ->label('Propietario')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('currency')
                    ->label('Moneda')
                    ->default('USD')
                    ->maxLength(3)
                    ->required(),
                TextInput::make('timezone')
                    ->label('Zona horaria')
                    ->default('America/Caracas')
                    ->maxLength(64)
                    ->required(),
            ])->columns(2);
    }
}
