# Implementation Plan: fleets

## Overview

Capa operativa para flotas pequeñas: rol `driver` con atribución de registros, alta masiva de
vehículos por CSV y vista consolidada de la flota. Reutiliza los módulos existentes; no crea un
modelo de datos nuevo. Ver [SPEC-fleets](../../SPEC-fleets.md).

## Architecture Decisions

- **`driver` como rol del pivot**: se añade al enum `organization_user`/`invitations` en lugar de
  crear permisos nuevos. `Organization::isManager()` (owner/admin) y `canLog()` (cualquier miembro)
  concentran la regla.
- **`VehiclePolicy::log`**: crear una carga o un servicio exige `log`, no `update`. Así el conductor
  registra sin administrar. `manage` sigue siendo owner/admin.
- **Autoría en la fila**: `fuel_entries.user_id` y `maintenance_records.user_id` (nullable,
  `nullOnDelete`). La política permite corregir lo propio; los registros viejos sin autor solo los
  toca quien gestiona el vehículo.
- **Importación en PHP con `fgetcsv`**: detección de delimitador, BOM y ISO-8859-1 sin dependencias.
  Se procesa fila a fila para poder informar errores y respetar el límite del plan sin abortar.
- **`remainingVehicles()` en `Billing`**: el importador no reimplementa límites ni el gate de
  suscripción; pregunta a la única fuente de verdad.
- **`FleetSummary` sobre `ExpenseReport`**: los totales de la flota coinciden con Reportes por
  construcción. La agregación es en PHP por compatibilidad SQLite/PostgreSQL.
- **Catálogo de 4 planes**: `taller` se renombra en sitio a `flota` (conserva suscripciones) y se
  añade `empresa`.

## Task List

### Fase 0: Fundación
- [x] Task 1: Migración del rol `driver` y de `user_id` en combustible/mantenimiento
- [x] Task 2: `Organization::isManager()/canLog()`, `VehiclePolicy::log`, políticas de autor
- [x] Task 3: `Billing::remainingVehicles()` y límites bloqueantes

### Fase 1: Catálogo y planes
- [x] Task 4: Migración `taller` → `flota`, alta de `empresa`, `PlanSeeder` y test de catálogo

### Fase 2: API
- [x] Task 5: `VehicleImport` + `VehicleImportController` + `POST /api/vehicles/import` + tests
- [x] Task 6: `FleetSummary` + `FleetController` + `GET /api/fleet/summary` + tests
- [x] Task 7: Roles `driver` en invitaciones y miembros + tests

### Fase 3: PWA
- [x] Task 8: Tipos, endpoints y store `fleet` (+ test)
- [x] Task 9: Página `/flota` (indicadores, avisos, tarjetas/tabla)
- [x] Task 10: Importador CSV en `/vehiculos`
- [x] Task 11: Selector de rol, etiquetas en español y atribución visible

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-fleets.md` cumplidos
- [x] `composer run test` y `vendor/bin/pint` en verde
- [x] `npm run typecheck && npm run test && npm run build` en verde

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Un `driver` con permisos de más | Alto | `log` separado de `manage`; tests negativos por rol |
| CSV enorme o mal codificado | Medio | Límite de 500 filas, detección de delimitador y fallback ISO-8859-1 |
| Importar por encima del plan | Alto | `Billing::remainingVehicles()` corta fila a fila y explica el límite |
| Totales de flota distintos a Reportes | Medio | `FleetSummary` reutiliza `ExpenseReport` |
| Migración del enum en PostgreSQL | Medio | `DROP/ADD CONSTRAINT` explícito; SQLite se recrea desde la migración original |
