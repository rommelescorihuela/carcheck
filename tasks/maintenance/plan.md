# Implementation Plan: maintenance

## Overview

Registra mantenimientos por vehículo (servicio, ítems con costo, taller, adjuntos, próximo servicio)
y calcula los próximos servicios. Hereda la propiedad del vehículo. Ver
[SPEC-maintenance](../../SPEC-maintenance.md).

## Architecture Decisions

- **Autorización delegada en el vehículo**: `MaintenanceRecordPolicy` reutiliza la lógica de
  `VehiclePolicy` (accesible vs gestionable). Tipos personalizados por ámbito; globales de solo lectura.
- **Odómetro**: `OdometerService::record()` al crear/editar el registro.
- **Próximo servicio**: `MaintenanceSchedule` combina el plan por vehículo con el override del registro.
- **Adjuntos**: medialibrary, colección `attachments` (múltiple, disco `public`).
- **Montos**: `decimal(12,2)` + `currency` del ámbito; la conversión es de `reporting`.

## Task List

### Fase 0: Fundación
- [x] Task 1: Enum, migraciones, modelos y factories
- [x] Task 2: Seeder del catálogo global de tipos

### Fase 1: Tipos de servicio
- [x] Task 3: `MaintenanceTypePolicy` + `MaintenanceTypeController` + rutas + tests

### Fase 2: Plan por vehículo
- [x] Task 4: `MaintenancePlanController` (upsert) + rutas + tests

### Fase 3: Registros
- [x] Task 5: `MaintenanceRecordPolicy` + CRUD + ítems + odómetro + tests
- [x] Task 6: Adjuntos (subir/borrar) + tests

### Fase 4: Próximos servicios
- [x] Task 7: `MaintenanceSchedule` + `/maintenance-due` + tests

### Fase 5: PWA
- [x] Task 8: Cliente (endpoints/types) + store `maintenance`
- [x] Task 9: Páginas (lista, formulario con ítems, detalle con adjuntos y próximos)
- [x] Task 10: E2E `maintenance.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-maintenance.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Módulo grande | Medio | 5 sub-fases con checkpoint; cada tarea ≤ ~5 archivos |
| Cálculo de próximo servicio confuso | Medio | `MaintenanceSchedule` aislado y probado (plan + override) |
| Autorización duplicada | Medio | Delegar en `VehiclePolicy`/trait, no reimplementar |

## Task List Target

Tareas en [`todo.md`](./todo.md).
