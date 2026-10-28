# Tasks: maintenance

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: Enum, migraciones, modelos y factories

**Acceptance criteria:**
- [x] `MaintenanceItemKind` (`labor|part|other`)
- [x] Migraciones `maintenance_types` (owner morph nullable = global), `maintenance_plans`, `maintenance_records`, `maintenance_items`
- [x] Modelos con relaciones y casts; `MaintenanceRecord` con medialibrary (`attachments` múltiple) y `total` accessor
- [x] `Vehicle` tiene `maintenanceRecords()`; factories para los 4 modelos

**Verification:** `php artisan migrate:fresh` · `php artisan test --filter=MaintenanceModelTest`

**Dependencies:** None · **Scope:** M

## Task 2: Seeder del catálogo global de tipos

**Acceptance criteria:**
- [x] `MaintenanceTypeSeeder` crea tipos globales (aceite, filtros, frenos, neumáticos, batería, revisión…) con intervalos por defecto
- [x] Idempotente (`firstOrCreate`); registrado en `DatabaseSeeder`

**Verification:** `php artisan migrate:fresh --seed` · test de que existen tipos globales

**Dependencies:** Task 1 · **Scope:** S

## Task 3: Tipos de servicio (API)

**Acceptance criteria:**
- [x] `GET /api/maintenance-types` devuelve globales + propios del ámbito
- [x] `POST` crea tipo propio en el ámbito activo (`organization_id` validado); `PATCH`/`DELETE` solo tipos propios; `DELETE` bloquea si está en uso (422)
- [x] `MaintenanceTypePolicy`; no se editan globales
- [x] `MaintenanceTypeTest` cubre todo

**Verification:** `php artisan test --filter=MaintenanceTypeTest`

**Dependencies:** Tasks 1, 2 · **Scope:** M

## Task 4: Plan por vehículo (API)

**Acceptance criteria:**
- [x] `GET /api/vehicles/{vehicle}/maintenance-plans` (accesible)
- [x] `PUT .../maintenance-plans/{type}` upsert `interval_km`/`interval_months` (gestión)
- [x] `DELETE .../maintenance-plans/{type}` (gestión)
- [x] `MaintenancePlanTest` cubre upsert, validación y 403

**Verification:** `php artisan test --filter=MaintenancePlanTest`

**Dependencies:** Task 1 · **Scope:** M

## Task 5: Registros (API) + ítems + odómetro

**Acceptance criteria:**
- [x] CRUD `/api/vehicles/{vehicle}/maintenance-records` y `/api/maintenance-records/{record}`
- [x] `items[]` con `kind/description/quantity/unit_price`; `total` calculado; `currency` del ámbito
- [x] `OdometerService::record()` al crear/editar (solo sube)
- [x] `MaintenanceRecordPolicy` delega en el vehículo; ajeno → 403
- [x] `MaintenanceRecordTest` cubre CRUD, total, odómetro y aislamiento

**Verification:** `php artisan test --filter=MaintenanceRecordTest`

**Dependencies:** Tasks 1, 3, 4 · **Scope:** M/L

## Task 6: Adjuntos

**Acceptance criteria:**
- [x] `POST /api/maintenance-records/{record}/attachments` (multipart `file`, `file|mimes:jpeg,png,webp,pdf|max:10240`)
- [x] `DELETE /api/maintenance-records/{record}/attachments/{media}` (gestión)
- [x] Respuesta incluye `attachments[]`; test de subida/borrado y 403

**Verification:** `php artisan test --filter=MaintenanceAttachmentTest`

**Dependencies:** Task 5 · **Scope:** S/M

## Task 7: Próximos servicios

**Acceptance criteria:**
- [x] `MaintenanceSchedule::dueFor(Vehicle)` combina plan + override (`next_due_km`/`next_due_date`)
- [x] `GET /api/vehicles/{vehicle}/maintenance-due` con `status` (`ok|soon|due`) y `source`
- [x] `MaintenanceScheduleTest` cubre por km, por fecha, override y `soon`

**Verification:** `php artisan test --filter=MaintenanceScheduleTest`

**Dependencies:** Tasks 4, 5 · **Scope:** M

## Checkpoint: API
- [x] Tipos, plan, registros, adjuntos y próximos funcionan; aislamiento y permisos verificados

## Task 8: Cliente (endpoints/types) + store `maintenance`

**Acceptance criteria:**
- [x] `endpoints.ts`: tipos, plan, registros, adjuntos, `maintenance-due`
- [x] `types.ts`: `MaintenanceType`, `MaintenanceRecord`, `MaintenanceItem`, `MaintenanceDue`, `Paginated`
- [x] `stores/maintenance.ts`: tipos + registros + due del vehículo activo; test del store

**Verification:** `npm run typecheck && npm run test`

**Dependencies:** Tasks 3–7 · **Scope:** M

## Task 9: Páginas

**Acceptance criteria:**
- [x] `MaintenanceList` (por vehículo), `MaintenanceForm` (servicio + ítems + próximo), `MaintenanceDetail` (adjuntos + próximos)
- [x] Errores por campo (`ApiError.errors`); decimales como string; textos en español
- [x] Rutas y navegación desde el detalle del vehículo

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 8 · **Scope:** M

## Task 10: E2E `maintenance.spec.ts`

**Acceptance criteria:**
- [x] `registro → vehículo → agregar mantenimiento con ítem → ver total y próximo servicio`
- [x] `npx playwright test` en verde

**Verification:** `npx playwright test`

**Dependencies:** Tasks 8, 9 · **Scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-maintenance.md` cumplidos
- [ ] Listo para revisión antes de `fuel`/`documents`/`billing`
