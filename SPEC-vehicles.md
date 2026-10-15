# Spec: vehicles

Segundo módulo de CarCheck (tras `identity`). Dueño de los vehículos y del contrato de propiedad
que consumen `maintenance`, `fuel` y `documents`. Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Permitir registrar y gestionar vehículos. Cada vehículo pertenece a un **usuario** (uso personal)
o a una **organización** (miembros que lo comparten), reutilizando el trait
`OwnedByUserOrOrganization` y la regla de acceso definidas en `identity`.

Historias / criterios de aceptación:

- Como usuario creo un vehículo; queda en el **ámbito activo** (personal o la organización activa).
- Como usuario veo solo los vehículos accesibles: los míos propios y los de mis organizaciones.
- Como `owner`/`admin` de una organización puedo crear/editar/eliminar los vehículos de esa organización.
- Un miembro de dos organizaciones **no** ve vehículos cruzados.
- Puedo **traspasar** un vehículo entre mi cuenta personal y una organización a la que pertenezco.
- Puedo subir una foto y actualizar el odómetro; el odómetro se sincroniza al alza desde
  mantenimientos/cargas.

## Tech Stack

- Igual que `identity`: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React.
- **Foto:** `spatie/laravel-medialibrary` (colección `photo`, `singleFile`). Dependencia nueva de este
  módulo; **precedente en el workspace**: `restaurant/restaurant-app` la usa con Laravel 13 (`^11.23`).
- Unidades: **kilómetros** siempre (`odometer_km`).
- Enums PHP (string-backed): `VehicleType` (`car|motorcycle|truck|other`), `FuelType`
  (`gasoline|diesel|cng|electric|hybrid`) en `app/Enums/` (patrón ya usado en `dentalflowSaas`).

## Autorización de gestión (definida aquí, la usan los módulos siguientes)

La **accesibilidad** la da el trait `OwnedByUserOrOrganization` (dueño personal o miembro de la org
dueña). Sobre eso, **gestión** (editar/eliminar/traspasar/foto) es:

- Vehículo **personal** (`owner_type = User`): solo su dueño.
- Vehículo de **organización**: cualquier miembro puede **ver**; solo `owner`/`admin` pueden **gestionar**.

`VehiclePolicy` implementa `view` (accesible) y `update`/`delete`/`transfer` (gestión).

## Contrato de API

Todas bajo `auth:sanctum`. "Accesible" = el usuario es dueño o miembro de la org dueña; "gestión"
según la sección *Autorización de gestión*.

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/vehicles` | autenticado (solo accesibles; filtro opcional `?organization_id=`; paginado) |
| POST | `/api/vehicles` | autenticado (dueño = ámbito activo, con validación) |
| GET | `/api/vehicles/{vehicle}` | accesible |
| PATCH | `/api/vehicles/{vehicle}` | gestión |
| DELETE | `/api/vehicles/{vehicle}` | gestión |
| POST | `/api/vehicles/{vehicle}/photo` | gestión (multipart `photo`) |
| DELETE | `/api/vehicles/{vehicle}/photo` | gestión |
| POST | `/api/vehicles/{vehicle}/transfer` | gestión en origen **y** destino |

### Entrada y validación

- **Ámbito (POST):** el cliente envía `organization_id` (nullable). `null` → dueño personal (el propio
  usuario). Con id → el backend **valida que el usuario sea `owner`/`admin`** de esa organización
  (403 si no) y asigna `owner_type`/`owner_id`. **El cliente nunca envía `owner_type`/`owner_id`.**
- **Campos (POST/PATCH):** `make` `required|string|max:100`, `model` `required|string|max:100`,
  `year` `nullable|integer|between:1900,{año+1}`, `plate` `nullable|string|max:16`,
  `vin` `nullable|string|max:32|unique:vehicles,vin`, `type` `in:car,motorcycle,truck,other`,
  `fuel_type` `in:gasoline,diesel,cng,electric,hybrid`, `odometer_km` `integer|min:0`,
  `color` `nullable|string|max:40`, `notes` `nullable|string|max:2000`.
- `PATCH` **no** acepta `owner_type`/`owner_id` (el dueño solo cambia con `transfer`).

### Listado

- `GET /vehicles`: por defecto devuelve todos los accesibles (personales + de todas mis
  organizaciones). `?organization_id={id}` filtra a una organización; `?scope=personal` solo los
  personales. Orden `created_at` desc; paginado (15/página). Excluye los soft-deleted.

### Foto

- `POST /photo`: multipart `photo` con validación `image|mimes:jpeg,png,webp|max:5120` (5 MB), disco
  `public`, colección `photo` (`singleFile`: reemplaza la anterior). Devuelve el vehículo con
  `photo_url`.
- `DELETE /photo`: elimina la media. `photo_url` = `getFirstMediaUrl('photo')` o `null`.

### Traspaso

- `transfer` body: `{ owner_type: "user"|"organization", owner_id }`. Reglas:
  - Origen: el usuario debe poder **gestionar** el vehículo.
  - Destino `user`: debe ser **el propio usuario** (no se traspasa a terceros en el MVP).
  - Destino `organization`: el usuario debe ser `owner`/`admin` de esa organización.
  - **Org → personal**: solo el `owner` de la organización puede sacar el vehículo a su cuenta
    personal (un `admin` no).
  - Devuelve el vehículo actualizado.

### Respuesta

- Con **API Resources** (`VehicleResource`): un vehículo → `{ data: Vehicle }`; el listado →
  `{ data: Vehicle[], meta: {…paginación}, links: {…} }`.
- `Vehicle` = `{ id, owner_type, owner_id, make, model, year, plate, vin, type, fuel_type,
  odometer_km, color, notes, photo_url, created_at, updated_at }` (los enums se serializan a su
  valor string).

## Modelo de datos

`vehicles`:

- `owner_type` / `owner_id` (morph a `User` u `Organization`) — **obligatorio**.
- `make` (string), `model` (string) — requeridos.
- `year` (unsignedSmallInteger, nullable; rango 1900…año+1).
- `plate` (string, nullable; índice), `vin` (string, nullable; **único** cuando no es nulo).
- `type` (`vehicle_type`, default `car`), `fuel_type` (`fuel_type`, default `gasoline`).
- `odometer_km` (unsignedInteger, default 0).
- `color` (string, nullable), `notes` (text, nullable).
- `deleted_at` (soft delete; el modelo usa `SoftDeletes`).
- timestamps.
- Índice `(owner_type, owner_id)`; índice en `vin` (único, nullable).

Foto: colección `photo` (medialibrary), `singleFile`, disco `public`.

### Contrato de odómetro (para módulos siguientes)

- `App\Services\OdometerService::record(Vehicle $vehicle, int $km)`: sube `odometer_km` **solo si**
  `$km > odometer_km`. Lo usan `maintenance` y `fuel` al registrar con odómetro.
- El `PATCH` manual puede fijar cualquier valor ≥ 0 (no baja por sincronización, pero el usuario sí
  puede corregirlo).

## Project Structure

```text
carcheck/
  app/Enums/VehicleType.php, FuelType.php
  app/Models/Vehicle.php                 # usa OwnedByUserOrOrganization + InteractsWithMedia
  app/Http/Controllers/Api/VehicleController.php
  app/Policies/VehiclePolicy.php         # view/update/delete/transfer
  app/Services/OdometerService.php
  database/migrations/*_create_vehicles_table.php
  database/factories/VehicleFactory.php
  tests/Feature/Vehicles/VehicleTest.php, VehicleTransferTest.php
carcheck-app/
  src/api/endpoints.ts, types.ts         # + Vehicle, VehicleType, FuelType
  src/pages/Vehicles.tsx, VehicleForm.tsx, VehicleDetail.tsx
  src/stores/vehicle.ts                  # lista + vehículo activo
```

**Nota de cliente:** `src/api/client.ts` hoy siempre serializa con `JSON.stringify` y fija
`Content-Type: application/json`, por lo que **no soporta `FormData`**. Para `/photo` hay que
extender `request()` (si el body es `FormData`, no fijar `Content-Type` ni serializar) o añadir un
helper de subida. Va incluido en este módulo. El `organization_id` que envía el cliente sale de
`src/stores/organization.ts` (`activeId`; `null` = personal).

## Commands

Backend (`carcheck/`): `composer run test`, `php artisan test --filter=VehicleTest`,
`vendor/bin/pint`, `php artisan migrate --seed`, `php artisan shield:generate --all --panel=admin`.
Frontend (`carcheck-app/`): `npm run typecheck`, `npm run test`, `npm run build`, `npx playwright test`.

## Code Style

Laravel Pint por defecto; strings en español. `Vehicle` con `#[Fillable([...])]` (atributo) y casts
de enums. En el cliente, `import type` y `Decimal` para decimales (aquí `odometer_km` es entero).

## Testing Strategy

- **Backend:** `VehicleTest` (CRUD, validación, foto, aislamiento personal vs organización, no-acceso
  → 403), `VehicleTransferTest` (personal↔org, reglas de destino, 403/422), `OdometerServiceTest`
  (solo sube, nunca baja).
- **Cliente:** tests del store `vehicle`; E2E Playwright `vehicles.spec.ts` (crear vehículo, verlo,
  editar odómetro, traspaso básico).
- Verificación: `php artisan test` + `pint` (backend); `typecheck` + `test` + `build` (cliente).

## Boundaries

- **Siempre:** autorizar por el contrato de propiedad (trait/policy); validar enums; subir el
  odómetro nunca lo baja; aislar por organización en tests.
- **Preguntar antes:** cambiar el contrato de propiedad; añadir dependencias; alterar el esquema de
  `vehicles` una vez mergeado.
- **Nunca:** confiar en `owner_type`/`owner_id` del cliente sin validar destino; exponer vehículos
  de organizaciones ajenas; copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Un usuario crea un vehículo y lo ve en su lista; otro usuario no lo ve (403/ausente).
- Un miembro de una organización ve los vehículos de la organización y no los de otra.
- El traspaso personal→organización y organización→personal respeta las reglas de destino.
- Subir/eliminar foto funciona; `odometer_km` solo sube por sincronización.
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. `vin` **único global** cuando no es nulo; `plate` solo indexado. **Caveat:** con soft delete, un
   `vin` dado de baja sigue ocupando el índice único (no se puede re-registrar hasta purgar).
2. Borrado de vehículo = **soft delete** (`deleted_at`) para no romper mantenimientos/cargas futuros.
3. Límite de vehículos por plan: se decide en `billing`.
4. **Filament:** `/admin` **no** gestiona vehículos en el MVP (son datos de usuario, no de
   plataforma); no se crea `VehicleResource`. Si se necesita soporte, será un recurso de solo lectura
   en un módulo posterior.
