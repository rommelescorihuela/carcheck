# Tasks: documents

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: Enum, migración, modelo y factory

**Acceptance criteria:**
- [x] `DocumentKind` (`insurance|inspection|tax|license|other`) con `label()` en español
- [x] Migración `documents` (`vehicle_id` cascade, `kind`, `name`, `issuer`, `reference`,
      `issued_on`, `expires_on`, `notes`) con índices `(vehicle_id, expires_on)` y
      `(vehicle_id, kind)`
- [x] `Document` con `#[Fillable]`, casts de fechas, medialibrary (`file` single file) y
      `Vehicle::documents()`
- [x] `DocumentFactory` con estados `expired()` / `expiring()` / `withoutExpiry()`

**Verification:** `php artisan migrate:fresh` · `php artisan test --filter=DocumentModelTest`

**Dependencies:** None · **Scope:** S

## Task 2: Cálculo de vencimiento

**Acceptance criteria:**
- [x] `DocumentStatus::for(?CarbonInterface $expiresOn): array{status, days_left}` con umbral 30 días
- [x] Fecha nula → `valid` + `days_left` nulo; pasada → `expired` con días negativos
- [x] `summary(Vehicle)` con `total`, `valid`, `expiring`, `expired` y `next_expiration`
- [x] `DocumentStatusTest` cubre los cuatro casos y el resumen

**Verification:** `php artisan test --filter=DocumentStatusTest`

**Dependencies:** Task 1 · **Scope:** S

## Task 3: CRUD de documentos (API)

**Acceptance criteria:**
- [x] `GET/POST /api/vehicles/{vehicle}/documents` y `GET/PATCH/DELETE /api/documents/{document}`
- [x] `DocumentPolicy` delega en el vehículo; ajeno → 403; miembro simple no gestiona
- [x] `DocumentResource` incluye `kind_label`, `status`, `days_left` y `file`
- [x] Orden por `expires_on` asc con nulos al final; `expires_on ≥ issued_on`
- [x] `DocumentTest` cubre CRUD, estados, permisos y validación

**Verification:** `php artisan test --filter=DocumentTest`

**Dependencies:** Tasks 1, 2 · **Scope:** M

## Task 4: Archivo y resumen

**Acceptance criteria:**
- [x] `POST /api/documents/{document}/file` (`multipart`, `jpeg|png|webp|pdf`, ≤ 10 MB), reemplaza el anterior
- [x] `DELETE /api/documents/{document}/file`
- [x] `GET /api/vehicles/{vehicle}/documents-summary` (accesible)
- [x] Tests de archivo (subida, reemplazo, borrado, tipo inválido) y de resumen

**Verification:** `php artisan test --filter=DocumentFileTest`

**Dependencies:** Task 3 · **Scope:** M

## Checkpoint: API
- [x] Documentos, estados, archivo y resumen funcionan; aislamiento y permisos verificados

## Task 5: Cliente + store `documents`

**Acceptance criteria:**
- [x] `types.ts`: `DocumentKind`, `DocumentStatus`, `VehicleDocument`, `DocumentsSummary`
- [x] `endpoints.ts`: CRUD + archivo + resumen
- [x] `stores/documents.ts`: documentos y resumen del vehículo activo + test del store

**Verification:** `npm run typecheck && npm run test`

**Dependencies:** Task 4 · **Scope:** M

## Task 6: Páginas

**Acceptance criteria:**
- [x] `DocumentsList` con tarjetas de resumen, lista con estado por color y días restantes
- [x] `DocumentForm` con errores por campo (`ApiError.errors`) y selector de tipo en español
- [x] Subida y borrado de archivo desde la lista
- [x] Ruta `/vehiculos/:vehicleId/documentos` y enlace desde el detalle del vehículo
- [x] Textos en español

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 5 · **Scope:** M

## Task 7: E2E `documents.spec.ts`

**Acceptance criteria:**
- [x] `registro → vehículo → agregar documento vencido → ver estado "Vencido"`
- [x] `npx playwright test` en verde

**Verification:** `npx playwright test`

**Dependencies:** Tasks 5, 6 · **Scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-documents.md` cumplidos
- [x] Listo para `billing` / `notifications`
