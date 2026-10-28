# Tasks: reminders

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: Enum, migraciones y modelos

**Acceptance criteria:**
- [x] `ReminderKind` (`maintenance|document`) con `label()` en español
- [x] Migración `reminders` (vehículo, kind, source morph, huella única, título/cuerpo, vencimientos, avisos)
- [x] Migración `reminder_dismissals` (único `reminder_id` + `user_id`, cascadas)
- [x] `Reminder` con `vehicle()`, `source()`, `dismissals()`; `ReminderDismissal` con relaciones
- [x] `Vehicle` expone `reminders()`

**Verification:** `php artisan migrate` · `php artisan test --filter=Reminder`

**Dependencies:** None · **Scope:** S

## Task 2: Recurso

**Acceptance criteria:**
- [x] `ReminderResource` con `kind_label`, `vehicle` resumido, `url`
- [x] `status` derivado (`overdue|upcoming`), `days_left`, `km_left`
- [x] Fechas en `Y-m-d`

**Verification:** `php artisan test --filter=ReminderApiTest`

**Dependencies:** Task 1 · **Scope:** S

## Task 3: Generador

**Acceptance criteria:**
- [x] Documentos con `days_left <= 30` (incluidos vencidos) generan candidato
- [x] Mantenimiento con estado `soon` o `due` de `MaintenanceSchedule` genera candidato
- [x] Huella `kind|source|due_on|due_km` única; regenerar no duplica
- [x] Se borran los recordatorios del vehículo que ya no son candidatos
- [x] `forVehicle`, `forUser` y `all()` (comando)

**Verification:** `php artisan test --filter=ReminderGenerationTest`

**Dependencies:** Task 1 · **Scope:** M

## Task 4: Avisos

**Acceptance criteria:**
- [x] Al crear: aviso "próximo" y `notified_at`
- [x] Al quedar vencido: aviso "vencido" y `overdue_notified_at` (una sola vez)
- [x] Categoría `reminders`, URL `/recordatorios`, payload con `reminder_id` y `vehicle_id`
- [x] Vehículo personal → dueño; de organización → todos los miembros
- [x] `NotifierTest`-style con `Mail::fake()` + `FakePushSender`

**Verification:** `php artisan test --filter=ReminderGenerationTest`

**Dependencies:** Tasks 3 · **Scope:** M

## Task 5: API

**Acceptance criteria:**
- [x] `GET /api/reminders` con ámbito (`organization_id`, `scope=personal`, todo) y orden por urgencia
- [x] Excluye los descartados por el usuario autenticado
- [x] `meta` con `overdue`, `upcoming`, `total`
- [x] `POST /api/reminders/{reminder}/dismiss` idempotente y por usuario
- [x] `POST /api/reminders/sync` genera para el ámbito y devuelve `created`/`total`
- [x] Vehículo ajeno → 404; `ReminderApiTest` cubre todo

**Verification:** `php artisan test --filter=ReminderApiTest`

**Dependencies:** Tasks 2, 4 · **Scope:** M

## Task 6: Comando y schedule

**Acceptance criteria:**
- [x] `php artisan reminders:generate` recorre todos los vehículos en bloques
- [x] Informa cuántos recordatorios se crearon
- [x] Programado a diario en `routes/console.php`
- [x] Test del comando

**Verification:** `php artisan reminders:generate` · `php artisan test --filter=GenerateReminders`

**Dependencies:** Task 4 · **Scope:** S

## Checkpoint: API y proceso
- [x] Lista, descarte y sincronización operables; generación diaria programada

## Task 7: Cliente + store

**Acceptance criteria:**
- [x] `types.ts`: `Reminder`, `ReminderKind`, `ReminderStatus`
- [x] `endpoints.ts`: listar, descartar y sync con ámbito
- [x] `stores/reminders.ts` con `load`, `dismiss`, `sync`, `reset`
- [x] Test del store

**Verification:** `npm run typecheck && npm run test`

**Dependencies:** Task 5 · **Scope:** M

## Task 8: Página `Reminders`

**Acceptance criteria:**
- [x] Secciones "Vencidos" y "Próximos" con vehículo, motivo y fecha/km
- [x] Botón "Comprobar ahora" (sync) y "Descartar" por recordatorio
- [x] Estado vacío claro en español
- [x] Ruta `/recordatorios` y enlace en la navegación
- [x] Se recarga al cambiar de organización

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 7 · **Scope:** M

## Task 9: E2E `reminders.spec.ts`

**Acceptance criteria:**
- [x] `registro → vehículo → documento a 10 días → recordatorios → comprobar → descartar`
- [x] `npx playwright test` en verde

**Verification:** `npx playwright test`

**Dependencies:** Tasks 5, 8 · **Scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-reminders.md` cumplidos
- [x] Listo para `reporting`
