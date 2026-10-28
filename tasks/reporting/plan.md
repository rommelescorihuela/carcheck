# Implementation Plan: reporting

## Overview

Informe de gastos (mantenimiento + combustible) por periodo, vehículo y moneda, más la suscripción.
Ver [SPEC-reporting](../../SPEC-reporting.md).

## Architecture Decisions

- **`ExpenseReport` sin HTTP**: recibe vehículos y periodo, devuelve el informe; lo reutilizan la
  API y, si hiciera falta, el panel.
- **`VehicleScope` compartido**: extrae la resolución de ámbito que hoy duplican `vehicles` y
  `reminders`; `reports` la usa también.
- **Sin conversión de moneda**: cada gasto conserva su moneda; se agrupa por moneda.
- **Agregación en PHP**: portable entre SQLite (tests) y PostgreSQL (producción).
- **Suscripción como bloque aparte**, solo pagos aprobados y en la moneda del plan.

## Task List

### Fase 0: Fundación
- [x] Task 1: `VehicleScope` y refactor de `VehicleController`/`ReminderController` para usarlo
- [x] Task 2: `ExpenseReport` (mantenimiento, combustible, vehículos, meses) + tests

### Fase 1: Suscripción y API
- [x] Task 3: Bloque `billing` (pagos aprobados por moneda del plan) + tests
- [x] Task 4: `ReportController` con validación del periodo, ámbito y filtro por vehículo + tests

### Fase 2: PWA
- [x] Task 5: Tipos, endpoint y página `/reportes` (periodo, totales, tablas, suscripción)
- [x] Task 6: Enlace de navegación y E2E `reports.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-reporting.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Sumar monedas distintas | Alto | Filas por moneda en totales, vehículos y meses |
| Recalcular importes con otra fórmula | Alto | Reutilizar `items` × precio y `litros` × precio; tests con los mismos números que los módulos |
| Informe lento con muchos registros | Medio | Filtrar por fechas y vehículos en la consulta; agregar en PHP |
| Ámbito distinto al de `vehicles` | Medio | `VehicleScope` único usado por los tres módulos |
| Fechas inválidas o invertidas | Bajo | `date` + `after_or_equal:from`; defaults documentados |

## Task List Target

Tareas en [`todo.md`](./todo.md).
