# Spec: maintenance

Módulo núcleo de CarCheck. Registra los servicios de mantenimiento de un vehículo, sus costos y
adjuntos, y calcula el **próximo servicio** (base de `reminders`). Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Permitir registrar mantenimientos por vehículo y planificar los próximos. Hereda la propiedad del
vehículo (usuario u organización) y reutiliza `OdometerService`.

Historias / criterios de aceptación:

- Como usuario registro un mantenimiento de un vehículo (tipo, fecha, odómetro, notas).
- Añado **ítems con costo** (repuestos/mano de obra) y el sistema calcula el total.
- Indico el **taller/proveedor** y adjunto **facturas/fotos**.
- Defino un **plan de intervalos por vehículo** (cada X km / cada Y meses por tipo) y/o fijo el
  **próximo servicio** manualmente en un registro.
- Veo los **próximos servicios** del vehículo (km/fecha/estado).
- El odómetro del vehículo se sincroniza al alza con el del registro.
- Un miembro de la organización ve los mantenimientos de la org; cualquier miembro (incluido
  `driver`) los registra y quien lo registró puede corregirlos.

## Tech Stack

- Igual que `vehicles`: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React.
- Adjuntos con `spatie/laravel-medialibrary` (colección `attachments`, múltiple, disco `public`).
- Montos: `decimal(12,2)` + `currency` del **ámbito** (org/usuario); la conversión es de `reporting`.
- Enum PHP `MaintenanceItemKind` (`labor|part|other`).

## Autorización

- **Vehículo** (vía `VehiclePolicy`): `view` = accesible; `update`/`delete` = gestión
  (personal dueño; org `owner`/`admin`).
- **Tipos personalizados**: se gestionan según el ámbito dueño (personal dueño; org `owner`/`admin`).
  Los tipos **globales** (seed) no se editan.
- `MaintenanceRecordPolicy` **delega en el vehículo**: `view` → accesible; crear exige `log`
  (cualquier miembro); `update`/`delete` → autor del registro con `log` o gestión del vehículo.

## Contrato de API

Todas bajo `auth:sanctum`.

### Tipos de servicio

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/maintenance-types` | autenticado (globales + propios del ámbito) |
| POST | `/api/maintenance-types` | autenticado (crea uno propio en el ámbito activo) |
| PATCH | `/api/maintenance-types/{type}` | dueño del tipo (no global) |
| DELETE | `/api/maintenance-types/{type}` | dueño del tipo; bloquea si está en uso (422) |

### Plan por vehículo

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/vehicles/{vehicle}/maintenance-plans` | accesible |
| PUT | `/api/vehicles/{vehicle}/maintenance-plans/{type}` | gestión (upsert `interval_km`/`interval_months`) |
| DELETE | `/api/vehicles/{vehicle}/maintenance-plans/{type}` | gestión |

### Registros

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/vehicles/{vehicle}/maintenance-records` | accesible (paginado) |
| POST | `/api/vehicles/{vehicle}/maintenance-records` | gestión |
| GET | `/api/maintenance-records/{record}` | accesible |
| PATCH | `/api/maintenance-records/{record}` | gestión |
| DELETE | `/api/maintenance-records/{record}` | gestión |
| POST | `/api/maintenance-records/{record}/attachments` | gestión (multipart `file`) |
| DELETE | `/api/maintenance-records/{record}/attachments/{media}` | gestión |

### Próximos servicios

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/vehicles/{vehicle}/maintenance-due` | accesible |

### Entrada y validación

- **Registro (POST/PATCH):** `maintenance_type_id` (`nullable|exists`), `performed_on`
  (`required|date`), `odometer_km` (`required|integer|min:0`), `provider`
  (`nullable|string|max:150`), `notes` (`nullable|string|max:2000`), `next_due_km`
  (`nullable|integer|min:0`), `next_due_date` (`nullable|date`), `items` (`array` de
  `{ kind: in:labor,part,other, description, quantity: numeric|min:0, unit_price: numeric|min:0 }`).
- **Tipo (POST):** `name` (`required|string|max:100`), `interval_km` (`nullable|integer|min:1`),
  `interval_months` (`nullable|integer|min:1`).
- **Plan (PUT):** `interval_km` (`nullable|integer|min:1`), `interval_months` (`nullable|integer|min:1`).
- **Adjunto (POST):** multipart `file` (`file|mimes:jpeg,png,webp,pdf|max:10240`).

### Respuesta

- Con API Resources. Registro: `{ id, vehicle_id, recorded_by, maintenance_type, performed_on,
  odometer_km, provider, notes, next_due_km, next_due_date, currency, total, items[], attachments[],
  created_at, updated_at }` (`total` = Σ `quantity × unit_price`). Listados paginados
  `{ data, meta, links }`.
- `maintenance-due`: `[{ maintenance_type, due_km, due_date, status: "ok"|"soon"|"due", source:
  "plan"|"manual" }]`.

## Modelo de datos

- `maintenance_types`: `owner_type`/`owner_id` (**nullable = tipo global**), `name`,
  `slug` (nullable), `interval_km` (nullable), `interval_months` (nullable), timestamps.
- `maintenance_plans`: `vehicle_id`, `maintenance_type_id`, `interval_km` (nullable),
  `interval_months` (nullable), timestamps; único `(vehicle_id, maintenance_type_id)`.
- `maintenance_records`: `vehicle_id`, `maintenance_type_id` (nullable), `performed_on` (date),
  `odometer_km` (int), `provider` (nullable), `notes` (nullable), `next_due_km` (nullable),
  `next_due_date` (nullable), `currency` (3), timestamps. Media `attachments` (múltiple).
- `maintenance_items`: `maintenance_record_id`, `kind` (`maintenance_item_kind`), `description`,
  `quantity` (`decimal(10,2)`), `unit_price` (`decimal(12,2)`), timestamps.

### Contrato de odómetro y próximo servicio

- Al crear/editar un registro: `OdometerService::record($vehicle, $odometer_km)` (solo sube).
- `App\Services\MaintenanceSchedule::dueFor(Vehicle $vehicle): array` calcula por tipo:
  - `due_km` = `record.next_due_km` **o** `record.odometer_km + plan.interval_km`.
  - `due_date` = `record.next_due_date` **o** `record.performed_on + plan.interval_months`.
  - Usa el **último registro** de cada tipo con plan definido. `status` = `due` si
    `vehicle.odometer_km ≥ due_km` o `hoy ≥ due_date`; `soon` si falta ≤ 500 km o ≤ 15 días.
  - Lo consume `reminders` (módulo posterior).

## Project Structure

```text
carcheck/
  app/Enums/MaintenanceItemKind.php
  app/Models/MaintenanceType.php, MaintenancePlan.php, MaintenanceRecord.php, MaintenanceItem.php
  app/Http/Controllers/Api/MaintenanceTypeController.php, MaintenancePlanController.php, MaintenanceRecordController.php
  app/Http/Resources/MaintenanceRecordResource.php, MaintenanceTypeResource.php
  app/Policies/MaintenanceRecordPolicy.php, MaintenanceTypePolicy.php
  app/Services/MaintenanceSchedule.php
  database/migrations/*_create_maintenance_*.php
  database/factories/* + database/seeders/MaintenanceTypeSeeder.php
  tests/Feature/Maintenance/*
carcheck-app/
  src/api/{endpoints,types}.ts, src/stores/maintenance.ts
  src/pages/MaintenanceList.tsx, MaintenanceForm.tsx, MaintenanceDetail.tsx
```

## Commands

Backend (`carcheck/`): `composer run test`, `php artisan test --filter=Maintenance`,
`vendor/bin/pint`, `php artisan migrate --seed`.
Frontend (`carcheck-app/`): `npm run typecheck`, `npm run test`, `npm run build`, `npx playwright test`.

## Code Style

Laravel Pint por defecto; strings en español. `#[Fillable]` + casts de enums/decimales; decimales
como string en el cliente (tipo `Decimal`). En el cliente, `import type`.

## Testing Strategy

- **Backend:** `MaintenanceTypeTest` (catálogo global + custom, permisos), `MaintenancePlanTest`
  (upsert, permisos), `MaintenanceRecordTest` (CRUD, ítems/total, odómetro sube, aislamiento
  personal/org, adjuntos), `MaintenanceScheduleTest` (cálculo de próximos por km/fecha y overrides).
- **Cliente:** store `maintenance`; E2E `maintenance.spec.ts` (registrar servicio con ítem y ver el
  próximo servicio).
- Verificación: `php artisan test` + `pint`; `typecheck` + `test` + `build`.

## Boundaries

- **Siempre:** autorizar vía el vehículo; sincronizar odómetro al alza; validar montos ≥ 0;
  aislar por organización en tests.
- **Preguntar antes:** cambiar el contrato de propiedad; alterar el esquema de `maintenance_*`
  una vez mergeado; añadir dependencias.
- **Nunca:** permitir editar/borrar tipos globales; exponer mantenimientos de vehículos ajenos;
  copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Un usuario registra un mantenimiento con ítems y ve el total; el odómetro del vehículo sube.
- El plan por vehículo y/o el override manual producen un próximo servicio correcto.
- Un miembro de la organización ve los registros; los registra y quien los creó los corrige.
- Un vehículo ajeno devuelve 403; los tipos globales no se pueden editar.
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. **Taller/proveedor** = campo de texto libre en el registro (no entidad) en el MVP.
2. **Catálogo**: tipos globales por seed + tipos personalizados por ámbito; los globales son de solo lectura.
3. **Próximo servicio**: "ambos" — el plan calcula, el registro puede sobreescribir.
4. **Adjuntos** (facturas/fotos) viven aquí; `documents` (módulo posterior) cubre seguro/revisión/impuestos.
5. Módulo grande: el plan lo divide en sub-fases (tipos → plan → registros/ítems → adjuntos → schedule).
