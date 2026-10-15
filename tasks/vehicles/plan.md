# Implementation Plan: vehicles

## Overview

Módulo que registra y gestiona vehículos con propiedad por `User` u `Organization` (trait
`OwnedByUserOrOrganization`), foto (medialibrary) y odómetro en km. Entrega en los dos repos
(`carcheck/` + `carcheck-app/`). Ver [SPEC-vehicles](../../SPEC-vehicles.md).

## Architecture Decisions

- **Propiedad**: reutiliza `OwnedByUserOrOrganization`; la **gestión** la decide `VehiclePolicy`
  (personal → dueño; organización → `owner`/`admin`). El cliente solo envía `organization_id`; el
  backend valida y asigna el morph.
- **Foto**: `spatie/laravel-medialibrary` (`^11.23`, mismo uso que `restaurant` con Laravel 13),
  colección `photo` `singleFile` en disco `public`.
- **Odómetro**: entero en km; `OdometerService::record()` solo sube. El `PATCH` manual puede
  corregir a cualquier valor ≥ 0.
- **Borrado**: soft delete.
- **Cliente**: extender `src/api/client.ts` para soportar `FormData` (hoy solo JSON).

## Task List

### Fase 0: Fundación
- [x] Task 1: Instalar `spatie/laravel-medialibrary`
- [x] Task 2: Enums, migración `vehicles`, modelo `Vehicle`, factory
- [x] Task 3: `OdometerService` + test

### Checkpoint: Fundación
- [x] `php artisan migrate` y `php artisan test` en verde; `pint` limpio

### Fase 1: API
- [x] Task 4: `VehiclePolicy` + CRUD `VehicleController` + rutas + `VehicleTest`
- [x] Task 5: Foto (subir/borrar) + tests
- [x] Task 6: Traspaso (`transfer`) + `VehicleTransferTest`

### Checkpoint: API
- [x] CRUD, foto y traspaso funcionan; aislamiento personal/org verificado

### Fase 2: PWA
- [x] Task 7: Cliente (`FormData` en `request()`, endpoints/types) + store `vehicle`
- [x] Task 8: Páginas lista/alta-edición/detalle (foto, odómetro, traspaso)
- [x] Task 9: E2E Playwright `vehicles.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-vehicles.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| `medialibrary` incompatible con Laravel 13 | Medio | Precedente en `restaurant` (`^11.23` + L13); si falla, caer a un `photo_path` simple |
| `client.ts` no soporta `FormData` | Medio | Extender `request()` en la Task 7 antes de la UI de foto |
| Asignar vehículo a una org ajena | Alto | Validar `owner`/`admin` en el backend (nunca confiar en el cliente) |
| `vin` único + soft delete | Bajo | Documentado; purge manual si se necesita re-registrar |

## Task List Target

Tareas en [`todo.md`](./todo.md).
