<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\PlanInterval;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->helperText('Identificador interno; no se muestra al usuario.')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Precio y vigencia')
                    ->schema([
                        TextInput::make('price')
                            ->label('Precio')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required(),
                        TextInput::make('currency')
                            ->label('Moneda')
                            ->default('USD')
                            ->minLength(3)
                            ->maxLength(3)
                            ->required(),
                        Select::make('interval')
                            ->label('Intervalo')
                            ->options(self::intervals())
                            ->default(PlanInterval::Monthly->value)
                            ->required(),
                        TextInput::make('trial_days')
                            ->label('Días de prueba')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Los planes inactivos no se ofrecen en la PWA.')
                            ->default(true),
                        TextInput::make('sort')
                            ->label('Orden')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->columns(3),
                Section::make('Límites')
                    ->schema([
                        TextInput::make('max_vehicles')
                            ->label('Vehículos')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Sin límite'),
                        TextInput::make('max_members')
                            ->label('Miembros')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Sin límite'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function intervals(): array
    {
        $options = [];

        foreach (PlanInterval::cases() as $interval) {
            $options[$interval->value] = $interval->label();
        }

        return $options;
    }
}
