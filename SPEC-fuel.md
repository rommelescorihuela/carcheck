# Spec: fuel

Registra las cargas de combustible de un vehículo y calcula el **rendimiento** (km/l) y el **costo
por km**. Precede a `reporting`, que agrega estos montos. Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Permitir registrar cargas por vehículo y conocer cuánto consume y cuánto cuesta cada kilómetro.
Hereda la propiedad del vehículo (usuario u organización) y reutiliza `OdometerService`.

Historias / criterios de aceptación:

- Como usuario registro una carga: fecha, odómetro, litros, precio por litro y si fue **tanque lleno**.
- Veo el **total** de la carga calculado (litros × precio) y la moneda del ámbito.
- Veo la **distancia recorrida** desde la carga anterior y el **rendimiento** (km/l) de la carga.
- Veo un **resumen**: litros, gasto total, distancia, promedio km/l y costo por km.
- El odómetro del vehículo se sincroniza al alza con el de la carga.
- Un miembro de la organización ve las cargas de la org; solo `owner`/`admin` las gestionan.

## Tech Stack

- Igual que `vehicles`: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React.
- Montos: `decimal(12,2)`; precio por litro `decimal(12,3)`; litros `decimal(8,2)`.
- Moneda: la del **ámbito** (org/usuario), igual que `maintenance`.

## Autorización

- **Carga** (vía `VehiclePolicy`): `view` = accesible; `update`/`delete` = gestión
  (personal dueño; org `owner`/`admin`).
- `FuelEntryPolicy` **delega en el vehículo**: `view` → accesible; `update`/`delete` → gestionable.
- Crear una carga exige gestión del vehículo.

## Contrato de API

Todas bajo `auth:sanctum`.

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/vehicles/{vehicle}/fuel-entries` | accesible (paginado, orden por odómetro asc) |
| POST | `/api/vehicles/{vehicle}/fuel-entries` | gestión |
| GET | `/api/fuel-entries/{entry}` | accesible |
| PATCH | `/api/fuel-entries/{entry}` | gestión |
| DELETE | `/api/fuel-entries/{entry}` | gestión |
| GET | `/api/vehicles/{vehicle}/fuel-summary` | accesible |

### Entrada y validación

- **Carga (POST/PATCH):** `filled_on` (`required|date`), `odometer_km` (`required|integer|min:0`),
  `liters` (`required|numeric|min:0.01|max:9999`), `unit_price` (`required|numeric|min:0`),
  `full_tank` (`boolean`, por defecto `true`), `station` (`nullable|string|max:150`),
  `notes` (`nullable|string|max:2000`).

### Respuesta

- Con API Resources. Carga: `{ id, vehicle_id, filled_on, odometer_km, liters, unit_price, total,
  currency, full_tank, station, notes, distance_km, km_per_liter, cost_per_km, created_at,
  updated_at }`. Listados paginados `{ data, meta, links }`.
- `fuel-summary`: `{ entries, total_liters, total_cost, distance_km, km_per_liter, cost_per_km,
  currency, last_filled_on }`.
- Decimales como **string** en el JSON (comportamiento de Eloquent/PG); el cliente usa `Decimal`.

## Modelo de datos

- `fuel_entries`: `vehicle_id`, `filled_on` (date), `odometer_km` (int), `liters` (`decimal(8,2)`),
  `unit_price` (`decimal(12,3)`), `full_tank` (bool, default true), `station` (nullable),
  `notes` (nullable), `currency` (3), timestamps.
- Índices: `(vehicle_id, odometer_km)` y `(vehicle_id, filled_on)`.
- `total` es **derivado** (`liters × unit_price`), no se guarda: evita divergencia al editar.

### Contrato de rendimiento

`App\Services\FuelConsumption` calcula, ordenando las cargas por `odometer_km` ascendente:

- `distance_km` = `odometer_km` − odómetro de la carga inmediatamente anterior (`null` en la primera).
- `liters_since_full` = litros acumulados desde la última carga con `full_tank` **inclusive**.
  Las cargas parciales se acumulan; el rendimiento se reporta en la carga que cierra el tanque.
- `km_per_liter` = `distance_km / liters_since_full` cuando la carga es `full_tank`, `distance_km > 0`
  y `liters_since_full > 0`; en otro caso `null`.
- `cost_per_km` = `total / distance_km` cuando `distance_km > 0`; en otro caso `null`.

Reglas del resumen:

- `total_liters` y `total_cost`: suma de todas las cargas del vehículo.
- `distance_km`: `max(odometer_km) − min(odometer_km)`.
- `km_per_liter`: Σ `distance_km` / Σ `liters_since_full` **solo** de las cargas con rendimiento.
- `cost_per_km`: `total_cost / distance_km` del rango.
- Dos cargas con el mismo odómetro no producen rendimiento (división por cero evitada).

## Project Structure

```text
carcheck/
  app/Models/FuelEntry.php
  app/Http/Controllers/Api/FuelEntryController.php
  app/Http/Resources/FuelEntryResource.php
  app/Policies/FuelEntryPolicy.php
  app/Services/FuelConsumption.php
  database/migrations/*_create_fuel_entries_table.php
  database/factories/FuelEntryFactory.php
  tests/Feature/Fuel/*
carcheck-app/
  src/api/{endpoints,types}.ts, src/stores/fuel.ts
  src/pages/FuelList.tsx, src/pages/FuelForm.tsx
  e2e/fuel.spec.ts
```

## Commands

Backend (`carcheck/`): `composer run test`, `php artisan test --filter=Fuel`, `vendor/bin/pint`,
`php artisan migrate --seed`.
Frontend (`carcheck-app/`): `npm run typecheck`, `npm run test`, `npm run build`, `npx playwright test`.

## Code Style

Laravel Pint por defecto; strings en español. `#[Fillable]` + casts; decimales como string en el
cliente (tipo `Decimal`). En el cliente, `import type`.

## Testing Strategy

- **Backend:** `FuelEntryTest` (CRUD, total derivado, moneda del ámbito, odómetro al alza, ajeno →
  403, miembro simple no gestiona), `FuelConsumptionTest` (distancia, rendimiento, cargas parciales,
  mismo odómetro, resumen agregado).
- **Cliente:** store `fuel`; E2E `fuel.spec.ts` (registrar carga y ver total y km/l).
- Verificación: `php artisan test` + `pint`; `typecheck` + `test` + `build`.

## Boundaries

- **Siempre:** autorizar vía el vehículo; sincronizar odómetro al alza; validar litros > 0 y precios
  ≥ 0; aislar por organización en tests.
- **Preguntar antes:** cambiar el contrato de rendimiento; alterar el esquema de `fuel_entries` una
  vez mergeado; añadir dependencias.
- **Nunca:** exponer cargas de vehículos ajenos; guardar un `total` editable que pueda divergir;
  copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Un usuario registra una carga y ve su total, la distancia desde la anterior y el km/l.
- Dos cargas consecutivas producen un rendimiento correcto; una carga parcial no lo falsea.
- El resumen del vehículo cuadra con la suma de sus cargas.
- Un vehículo ajeno devuelve 403; un miembro simple no puede crear ni borrar.
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. **`total` derivado, no almacenado**: el precio y los litros mandan; `reporting` lo agrega en SQL.
2. **Tanque lleno** es la unidad de medida: sin él no hay rendimiento fiable. Las parciales se
   acumulan hasta la que cierra el tanque.
3. **Precio por litro con 3 decimales** porque en VES/litro los céntimos no alcanzan.
4. **Sin entidad "estación"**: campo de texto libre en el MVP, igual que `provider` en `maintenance`.
5. El odómetro de la carga **nunca baja** el del vehículo, pero sí puede ser menor que el anterior:
   se permite corregir una carga mal tecleada sin romper el histórico (produce `null` de rendimiento).
