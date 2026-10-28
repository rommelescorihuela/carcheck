# Tasks: fuel

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: Migración, modelo y factory

**Acceptance criteria:**
- [x] Migración `fuel_entries` (`vehicle_id` cascade, `filled_on`, `odometer_km`, `liters` 8,2,
      `unit_price` 12,3, `full_tank` bool, `station`, `notes`, `currency` 3) con índices
      `(vehicle_id, odometer_km)` y `(vehicle_id, filled_on)`
- [x] `FuelEntry` con `#[Fillable]`, casts (`filled_on` date, `liters`/`unit_price` decimal,
      `full_tank` bool, `odometer_km` int) y accessor `total`
- [x] `Vehicle::fuelEntries()`
- [x] `FuelEntryFactory`

**Verification:** `php artisan migrate:fresh` · `php artisan test --filter=FuelModelTest`

**Dependencies:** None · **Scope:** S

## Task 2: Cálculo de rendimiento

**Acceptance criteria:**
- [x] `FuelConsumption::forVehicle(Vehicle): array` devuelve por carga `distance_km`,
      `liters_since_full`, `km_per_liter`, `cost_per_km`
- [x] Acumula parciales hasta la carga que cierra el tanque; `null` cuando no es calculable
- [x] `summary()` con `entries`, `total_liters`, `total_cost`, `distance_km`, `km_per_liter`,
      `cost_per_km`, `currency`, `last_filled_on`
- [x] `FuelConsumptionTest` cubre distancia, km/l, parciales, mismo odómetro y resumen

**Verification:** `php artisan test --filter=FuelConsumptionTest`

**Dependencies:** Task 1 · **Scope:** M

## Task 3: CRUD de cargas (API)

**Acceptance criteria:**
- [x] `GET/POST /api/vehicles/{vehicle}/fuel-entries` y `GET/PATCH/DELETE /api/fuel-entries/{entry}`
- [x] `FuelEntryPolicy` delega en el vehículo; ajeno → 403; miembro simple no gestiona
- [x] `currency` del ámbito; `OdometerService::record()` al crear/editar (solo sube)
- [x] `FuelEntryResource` incluye `total` y los campos de rendimiento
- [x] `FuelEntryTest` cubre CRUD, total, moneda, odómetro y aislamiento

**Verification:** `php artisan test --filter=FuelEntryTest`

**Dependencies:** Tasks 1, 2 · **Scope:** M

## Task 4: Resumen del vehículo

**Acceptance criteria:**
- [x] `GET /api/vehicles/{vehicle}/fuel-summary` (accesible) devuelve el resumen agregado
- [x] Test de permisos y de cuadre con las cargas

**Verification:** `php artisan test --filter=FuelSummaryTest`

**Dependencies:** Tasks 2, 3 · **Scope:** S

## Checkpoint: API
- [x] Cargas, rendimiento y resumen funcionan; aislamiento y permisos verificados

## Task 5: Cliente + store `fuel`

**Acceptance criteria:**
- [x] `types.ts`: `FuelEntry`, `FuelSummary`; `Decimal` para litros/precio/total
- [x] `endpoints.ts`: CRUD de cargas + `fuel-summary`
- [x] `stores/fuel.ts`: cargas y resumen del vehículo activo + test del store

**Verification:** `npm run typecheck && npm run test`

**Dependencies:** Tasks 3, 4 · **Scope:** M

## Task 6: Páginas

**Acceptance criteria:**
- [x] `FuelList` con tarjetas de resumen, historial (km/l y costo/km) y formulario de alta
- [x] `FuelForm` con errores por campo (`ApiError.errors`), decimales como string
- [x] Ruta `/vehiculos/:vehicleId/combustible` y enlace desde el detalle del vehículo
- [x] Textos en español

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 5 · **Scope:** M

## Task 7: E2E `fuel.spec.ts`

**Acceptance criteria:**
- [x] `registro → vehículo → agregar carga → ver total y km/l en el historial`
- [x] `npx playwright test` en verde

**Verification:** `npx playwright test`

**Dependencies:** Tasks 5, 6 · **Scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-fuel.md` cumplidos
- [x] Listo para revisión antes de `documents`/`billing`
