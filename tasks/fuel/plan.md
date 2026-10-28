# Implementation Plan: fuel

## Overview

Registra cargas de combustible por vehículo y calcula rendimiento (km/l) y costo por km, incluido un
resumen agregado del vehículo. Hereda la propiedad del vehículo y reutiliza `OdometerService`.
Ver [SPEC-fuel](../../SPEC-fuel.md).

## Architecture Decisions

- **Total derivado**: `FuelEntry::total` es un accessor (`liters × unit_price`); no hay columna
  `total` que pueda divergir al editar. `reporting` lo agrega en SQL.
- **Autorización delegada en el vehículo**: `FuelEntryPolicy` reutiliza `VehiclePolicy` (accesible
  vs gestionable), igual que `MaintenanceRecordPolicy`.
- **Odómetro**: `OdometerService::record()` al crear/editar la carga (solo sube).
- **Rendimiento**: `FuelConsumption` aislado y probado; acumula cargas parciales hasta la que cierra
  el tanque. Sin esa regla el km/l se falsea.
- **Moneda**: la del ámbito (`owner->currency`), igual que `maintenance`.

## Task List

### Fase 0: Fundación
- [x] Task 1: Migración, modelo `FuelEntry`, factory y relaciones en `Vehicle`
- [x] Task 2: `FuelConsumption` (distancia, km/l, costo/km, resumen) + tests

### Fase 1: API
- [x] Task 3: `FuelEntryPolicy` + `FuelEntryResource` + `FuelEntryController` (CRUD) + rutas + tests
- [x] Task 4: `GET /vehicles/{vehicle}/fuel-summary` + tests

### Fase 2: PWA
- [x] Task 5: Cliente (endpoints/types) + store `fuel` + test del store
- [x] Task 6: Páginas `FuelList` (resumen + historial) y `FuelForm`
- [x] Task 7: E2E `fuel.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-fuel.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Rendimiento mal calculado | Alto | `FuelConsumption` aislado, con tests de carga parcial y mismo odómetro |
| Total divergente | Medio | Derivado en el modelo, nunca columna |
| Duplicar autorización | Medio | Delegar en `VehiclePolicy`, no reimplementar |

## Task List Target

Tareas en [`todo.md`](./todo.md).
