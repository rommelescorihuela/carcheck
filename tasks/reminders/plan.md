# Implementation Plan: reminders

## Overview

Recordatorios de mantenimiento y documentos derivados de los datos existentes, deduplicados por
huella y entregados por `Notifier`. Ver [SPEC-reminders](../../SPEC-reminders.md).

## Architecture Decisions

- **Derivar y persistir**: la condición se recalcula en cada pasada; la fila conserva `notified_at`
  y el descarte del usuario.
- **Huella determinista** (`kind|source|due_on|due_km`) con índice único: sustituye a un estado que
  habría que ir actualizando y hace idempotente la generación.
- **`ReminderGenerator` sin HTTP**: lo usan el comando diario y el endpoint `sync`.
- **Umbrales compartidos** con `DocumentStatus` y `MaintenanceSchedule` para que la lista y el
  detalle del vehículo nunca se contradigan.
- **Descarte por usuario** en tabla aparte; el recordatorio sigue existiendo para el resto.

## Task List

### Fase 0: Fundación
- [x] Task 1: `ReminderKind`, migraciones (`reminders`, `reminder_dismissals`) y modelos
- [x] Task 2: `ReminderResource` con estado derivado (`overdue|upcoming`) y `days_left`/`km_left`

### Fase 1: Generación
- [x] Task 3: `ReminderGenerator` (candidatos de documentos y mantenimiento, huella, limpieza) + tests
- [x] Task 4: Avisos por `Notifier` (próximo/vencido, destinatarios según propiedad) + tests

### Fase 2: API y proceso
- [x] Task 5: `ReminderController` (lista, descartar, sync) + rutas + tests
- [x] Task 6: Comando `reminders:generate` y schedule diario + test

### Fase 3: PWA
- [x] Task 7: Tipos, endpoints y store `reminders` + test del store
- [x] Task 8: Página `Reminders` (vencidos/próximos, comprobar, descartar) + ruta y navegación
- [x] Task 9: E2E `reminders.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-reminders.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Avisos repetidos en cada pasada | Alto | Huella única + `notified_at`/`overdue_notified_at` |
| Recordatorio obsoleto tras renovar | Medio | Borrar por vehículo lo que ya no es candidato |
| Descarte de un miembro oculta al resto | Medio | `reminder_dismissals` por usuario |
| Muchos vehículos en el comando | Medio | `chunkById` y avisos solo al crear |
| Umbrales divergentes con los otros módulos | Medio | Reutilizar `DocumentStatus` y `MaintenanceSchedule` |
| Fechas con zona horaria | Bajo | Comparar por día (`startOfDay`) como `DocumentStatus` |

## Task List Target

Tareas en [`todo.md`](./todo.md).
