# Tasks: reporting

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: `VehicleScope`

**Acceptance criteria:**
- [x] `VehicleScope::for(Request)` resuelve `organization_id` (403 si no es miembro), `scope=personal` o todo lo accesible
- [x] `VehicleController::index` y `ReminderController` lo usan (sin cambiar su contrato)
- [x] Tests existentes de ambos módulos siguen en verde

**Verification:** `php artisan test --filter=Vehicle` · `php artisan test --filter=Reminder`

**Dependencies:** None · **Scope:** S

## Task 2: `ExpenseReport`

**Acceptance criteria:**
- [x] Mantenimiento del periodo: Σ cantidad × precio de los ítems, por moneda
- [x] Combustible del periodo: Σ litros × precio por litro, por moneda
- [x] Totales por moneda; filas por vehículo+moneda; filas por mes+moneda
- [x] Periodo vacío devuelve listas vacías
- [x] Redondeo a 2 decimales

**Verification:** `php artisan test --filter=ExpenseReportTest`

**Dependencies:** Task 1 · **Scope:** M

## Task 3: Bloque de suscripción

**Acceptance criteria:**
- [x] Solo pagos `approved` del propietario resuelto
- [x] Agrupados por moneda del plan; importe `amount_base`
- [x] Pagos pendientes/rechazados y de otros propietarios quedan fuera

**Verification:** `php artisan test --filter=ExpenseReportTest`

**Dependencies:** Task 2 · **Scope:** S

## Task 4: API

**Acceptance criteria:**
- [x] `GET /api/reports/expenses` con `from`/`to` (defaults: 1 de enero → hoy)
- [x] `to` anterior a `from` responde 422
- [x] Ámbito personal/organización; `vehicle_id` ajeno → 404
- [x] Filtro por vehículo limita todas las secciones
- [x] `ReportApiTest` cubre todo

**Verification:** `php artisan test --filter=ReportApiTest`

**Dependencies:** Tasks 2, 3 · **Scope:** M

## Task 5: Página `Reports`

**Acceptance criteria:**
- [x] Campos "Desde"/"Hasta" y botón "Aplicar"; se recarga al cambiar de organización
- [x] Tarjetas de total por moneda (mantenimiento, combustible, total)
- [x] Tabla por vehículo y tabla por mes
- [x] Bloque de suscripción y estado vacío en español
- [x] Ruta `/reportes` y enlace en la navegación

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 4 · **Scope:** M

## Task 6: E2E

**Acceptance criteria:**
- [x] `registro → vehículo → mantenimiento → combustible → informe` con totales correctos
- [x] `npx playwright test` en verde

**Verification:** `npx playwright test`

**Dependencies:** Tasks 4, 5 · **Scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-reporting.md` cumplidos
- [x] Último módulo del mapa: el producto queda completo
