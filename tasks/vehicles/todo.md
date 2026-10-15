# Tasks: vehicles

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: Instalar `spatie/laravel-medialibrary`

**Description:** Añadir la dependencia, publicar config/migración y migrar.

**Acceptance criteria:**
- [x] `composer require spatie/laravel-medialibrary` (^11.23) instala sin conflictos con Laravel 13
- [x] `config/media-library.php` y la migración `media` publicadas; `php artisan migrate` crea la tabla
- [x] La suite existente sigue en verde

**Verification:** `php artisan migrate` · `php artisan test`

**Dependencies:** None

**Files likely touched:** `composer.json`, `config/media-library.php`, `database/migrations/*_create_media_table.php`

**Estimated scope:** S

## Task 2: Enums, migración `vehicles`, modelo `Vehicle`, factory

**Description:** Enums string-backed, tabla `vehicles` con morph + soft delete, modelo con trait de
propiedad y medialibrary, y factory.

**Acceptance criteria:**
- [x] `VehicleType` (`car|motorcycle|truck|other`) y `FuelType` (`gasoline|diesel|cng|electric|hybrid`)
- [x] Migración con los campos de `SPEC-vehicles.md` (morph, `year`, `plate`, `vin` único nullable, `type`, `fuel_type`, `odometer_km`, `color`, `notes`, `deleted_at`, índice `(owner_type,owner_id)`)
- [x] `Vehicle` usa `OwnedByUserOrOrganization`, `SoftDeletes`, `InteractsWithMedia`; colección `photo` `singleFile`; `photo_url` accessor; casts de enums/enteros
- [x] `VehicleFactory` crea vehículos personales y de organización

**Verification:** `php artisan migrate:fresh` · `php artisan test --filter=VehicleModelTest`

**Dependencies:** Task 1

**Files likely touched:** `app/Enums/VehicleType.php`, `app/Enums/FuelType.php`, `database/migrations/*_create_vehicles_table.php`, `app/Models/Vehicle.php`, `database/factories/VehicleFactory.php`, `tests/Feature/Vehicles/VehicleModelTest.php`

**Estimated scope:** M

## Task 3: `OdometerService` + test

**Description:** Servicio que sube el odómetro solo al alza.

**Acceptance criteria:**
- [x] `OdometerService::record(Vehicle, int $km)` sube `odometer_km` solo si `$km > odometer_km`
- [x] Valores menores o iguales son no-op; nunca baja
- [x] Test cubre subir, igual y bajar

**Verification:** `php artisan test --filter=OdometerServiceTest`

**Dependencies:** Task 2

**Files likely touched:** `app/Services/OdometerService.php`, `tests/Feature/Vehicles/OdometerServiceTest.php`

**Estimated scope:** S

## Checkpoint: Fundación
- [x] `php artisan migrate:fresh` sin errores; `php artisan test` y `pint` en verde

## Task 4: `VehiclePolicy` + CRUD `VehicleController` + rutas + `VehicleTest`

**Description:** Autorización de acceso/gestión y CRUD de vehículos con validación de ámbito.

**Acceptance criteria:**
- [x] `VehiclePolicy`: `view` = accesible (trait); `update`/`delete` = gestión (personal dueño; org `owner`/`admin`)
- [x] `POST /vehicles` valida `organization_id` (miembro `owner`/`admin`) y asigna el morph; nunca acepta `owner_*` del cliente
- [x] `GET /vehicles` lista solo accesibles, con `?organization_id=`/`?scope=personal`, orden `created_at` desc, paginado 15
- [x] `PATCH`/`DELETE` (soft) con validación de campos; no-acceso → 403
- [x] `VehicleTest` cubre CRUD, validación, aislamiento personal/org y no-acceso

**Verification:** `php artisan test --filter=VehicleTest`

**Dependencies:** Tasks 2, 3

**Files likely touched:** `app/Policies/VehiclePolicy.php`, `app/Http/Controllers/Api/VehicleController.php`, `routes/api.php`, `tests/Feature/Vehicles/VehicleTest.php`

**Estimated scope:** M

## Task 5: Foto (subir/borrar) + tests

**Description:** Endpoints de foto con medialibrary.

**Acceptance criteria:**
- [x] `POST /vehicles/{id}/photo` valida `image|mimes:jpeg,png,webp|max:5120`, guarda en colección `photo` `singleFile` (reemplaza)
- [x] `DELETE /vehicles/{id}/photo` elimina la media
- [x] Ambos requieren **gestión**; respuesta incluye `photo_url`
- [x] Test cubre subida válida, tipo inválido (422) y borrado

**Verification:** `php artisan test --filter=VehiclePhotoTest`

**Dependencies:** Task 4

**Files likely touched:** `app/Http/Controllers/Api/VehicleController.php`, `routes/api.php`, `tests/Feature/Vehicles/VehiclePhotoTest.php`

**Estimated scope:** M

## Task 6: Traspaso (`transfer`) + `VehicleTransferTest`

**Description:** Mover un vehículo entre personal y organización con reglas.

**Acceptance criteria:**
- [x] `POST /vehicles/{id}/transfer` con `{ owner_type, owner_id }`
- [x] Origen: gestión; destino `user` = el propio usuario; destino `organization` = `owner`/`admin`
- [x] **Org → personal** solo lo permite el `owner` de la org
- [x] Devuelve el vehículo actualizado; tests cubren los casos y 403/422

**Verification:** `php artisan test --filter=VehicleTransferTest`

**Dependencies:** Task 4

**Files likely touched:** `app/Policies/VehiclePolicy.php`, `app/Http/Controllers/Api/VehicleController.php`, `routes/api.php`, `tests/Feature/Vehicles/VehicleTransferTest.php`

**Estimated scope:** M

## Checkpoint: API
- [x] CRUD, foto y traspaso funcionan; aislamiento personal/org y reglas de destino verificados

## Task 7: Cliente (`FormData` + endpoints/types) + store `vehicle`

**Description:** Soporte de subida y capa de datos del cliente.

**Acceptance criteria:**
- [x] `request()` en `src/api/client.ts` no fija `Content-Type` ni serializa cuando el body es `FormData`
- [x] `endpoints.ts`: list/create/get/update/delete de vehículos, subir/borrar foto, transfer
- [x] `types.ts`: `Vehicle`, `VehicleType`, `FuelType`, `Paginated<T>`
- [x] `stores/vehicle.ts`: lista + vehículo activo, recarga al cambiar de ámbito; test del store

**Verification:** `npm run typecheck && npm run test`

**Dependencies:** None (contra la API de las Tasks 4–6)

**Files likely touched:** `src/api/client.ts`, `src/api/endpoints.ts`, `src/api/types.ts`, `src/stores/vehicle.ts`, `src/stores/vehicle.test.ts`

**Estimated scope:** M

## Task 8: Páginas lista/alta-edición/detalle

**Description:** UI de vehículos en la PWA.

**Acceptance criteria:**
- [x] `Vehicles` lista los del ámbito activo (usa `stores/organization.ts`), con alta rápida
- [x] `VehicleForm` crea/edita con validación y errores por campo (`ApiError.errors`)
- [x] `VehicleDetail` muestra foto (subir/borrar), editar odómetro y traspaso (visible según rol)
- [x] Rutas y enlace en `AppShell`; textos en español

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 7

**Files likely touched:** `src/pages/Vehicles.tsx`, `src/pages/VehicleForm.tsx`, `src/pages/VehicleDetail.tsx`, `src/router.tsx`, `src/components/AppShell.tsx`

**Estimated scope:** M

## Task 9: E2E Playwright `vehicles.spec.ts`

**Description:** Flujo de vehículo de punta a punta.

**Acceptance criteria:**
- [x] `registro → crear vehículo → verlo en la lista → editar odómetro`
- [x] Cubre al menos el aislamiento básico (el vehículo aparece en el ámbito personal)
- [x] `npx playwright test` en verde
- [x] Extra: `e2e/vehicle-organization.spec.ts` cubre ámbito de organización, subida de foto y traspaso org→personal

**Verification:** `npx playwright test`

**Dependencies:** Tasks 7, 8

**Files likely touched:** `e2e/vehicles.spec.ts`

**Estimated scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-vehicles.md` cumplidos
- [ ] Listo para revisión antes de `billing`/`maintenance`
