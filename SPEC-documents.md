# Spec: documents

Guarda los documentos legales del vehículo (seguro, revisión, impuestos, licencia) con su
**vencimiento** y su archivo escaneado. Es la fuente de datos que consumirá `reminders` para avisar
antes de que caduquen. Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Saber qué documentos tiene el vehículo, cuándo vencen y tener el archivo a mano. Hereda la
propiedad del vehículo (usuario u organización).

Historias / criterios de aceptación:

- Como usuario registro un documento con tipo, nombre, emisor, número de referencia, fecha de
  emisión y **fecha de vencimiento**.
- Adjunto una **foto o PDF** del documento (opcional, uno por documento).
- Veo el **estado** de cada documento: `valid`, `expiring` (vence en ≤ 30 días) o `expired`.
- Veo cuántos días le quedan (`days_left`) y los documentos ordenados por vencimiento.
- Veo un **resumen** del vehículo: totales por estado y el **próximo vencimiento**.
- Un documento puede no vencer (`expires_on` nulo): estado siempre `valid`, `days_left` nulo.
- Un miembro de la organización ve los documentos de la org; solo `owner`/`admin` los gestionan.

## Tech Stack

- Igual que `vehicles`: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React.
- Archivos con `spatie/laravel-medialibrary`, colección `file` **single file**, disco `public`.
- Fechas: `date` (sin hora); se comparan contra `now()->startOfDay()`.

## Autorización

- **Documento** (vía `VehiclePolicy`): `view` = accesible; `update`/`delete` = gestionable
  (personal dueño; org `owner`/`admin`).
- `DocumentPolicy` **delega en el vehículo**, igual que `FuelEntryPolicy`.
- Crear un documento exige gestión del vehículo.

## Contrato de API

Todas bajo `auth:sanctum`.

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/vehicles/{vehicle}/documents` | accesible (paginado, orden por `expires_on` asc, nulos al final) |
| POST | `/api/vehicles/{vehicle}/documents` | gestión |
| GET | `/api/vehicles/{vehicle}/documents-summary` | accesible |
| GET | `/api/documents/{document}` | accesible |
| PATCH | `/api/documents/{document}` | gestión |
| DELETE | `/api/documents/{document}` | gestión |
| POST | `/api/documents/{document}/file` | gestión (`multipart`, campo `file`) |
| DELETE | `/api/documents/{document}/file` | gestión |

### Entrada y validación

- **Documento (POST/PATCH):** `kind` (`required|in:insurance,inspection,tax,license,other`),
  `name` (`required|string|max:150`), `issuer` (`nullable|string|max:150`), `reference`
  (`nullable|string|max:100`), `issued_on` (`nullable|date`), `expires_on`
  (`nullable|date|after_or_equal:issued_on`), `notes` (`nullable|string|max:2000`).
- **Archivo:** `file` (`required|file|mimes:jpeg,png,webp,pdf|max:10240`).

### Respuesta

- Con API Resources. Documento: `{ id, vehicle_id, kind, kind_label, name, issuer, reference,
  issued_on, expires_on, notes, status, days_left, file: {id, file_name, url, mime_type,
  size}|null, created_at, updated_at }`. Listados paginados `{ data, meta, links }`.
- `documents-summary`: `{ total, valid, expiring, expired, next_expiration: {id, name, kind,
  kind_label, expires_on, days_left}|null }`.
- `kind_label` en español (`Seguro`, `Revisión`, `Impuesto`, `Licencia`, `Otro`).

## Modelo de datos

- `documents`: `vehicle_id` (cascade), `kind` (string 20), `name` (150), `issuer` (150, nullable),
  `reference` (100, nullable), `issued_on` (date, nullable), `expires_on` (date, nullable),
  `notes` (text, nullable), timestamps.
- Índices: `(vehicle_id, expires_on)` y `(vehicle_id, kind)`.
- El estado y `days_left` son **derivados**, no columnas: cambian solos con el paso del tiempo.

### Contrato de vencimiento

`App\Services\DocumentStatus` (constante `WARNING_DAYS = 30`):

- `expires_on` nulo → `status = valid`, `days_left = null`.
- `days_left = expires_on − hoy` (en días, redondeado por fecha, puede ser negativo).
- `days_left < 0` → `expired`; `0 ≤ days_left ≤ 30` → `expiring`; `> 30` → `valid`.
- El resumen cuenta por estado y `next_expiration` es el documento no vencido con `expires_on` más
  próxima (incluye los que ya están `expiring`).

## Project Structure

```text
carcheck/
  app/Enums/DocumentKind.php
  app/Models/Document.php
  app/Http/Controllers/Api/DocumentController.php
  app/Http/Resources/DocumentResource.php
  app/Policies/DocumentPolicy.php
  app/Services/DocumentStatus.php
  database/migrations/*_create_documents_table.php
  database/factories/DocumentFactory.php
  tests/Feature/Documents/*
carcheck-app/
  src/api/{endpoints,types}.ts, src/stores/documents.ts
  src/pages/DocumentsList.tsx, src/pages/DocumentForm.tsx
  e2e/documents.spec.ts
```

## Commands

Backend (`carcheck/`): `composer run test`, `php artisan test --filter=Document`, `vendor/bin/pint`,
`php artisan migrate --seed`.
Frontend (`carcheck-app/`): `npm run typecheck`, `npm run test`, `npm run build`, `npx playwright test`.

## Code Style

Laravel Pint por defecto; strings en español. `#[Fillable]` + casts; `import type` en el cliente.
El estado se muestra con color: `valid` verde, `expiring` ámbar, `expired` rojo.

## Testing Strategy

- **Backend:** `DocumentTest` (CRUD, estado/`days_left` con fecha pasada, futura y nula, resumen,
  archivo adjunto, ajeno → 403, miembro simple no gestiona, validación `expires_on ≥ issued_on`).
- **Cliente:** store `documents`; E2E `documents.spec.ts` (registrar documento vencido y ver el
  estado `Vencido`).
- Verificación: `php artisan test` + `pint`; `typecheck` + `test` + `build`.

## Boundaries

- **Siempre:** autorizar vía el vehículo; derivar estado y `days_left` de `expires_on`; un solo
  archivo por documento; validar tipo de archivo y tamaño.
- **Preguntar antes:** añadir costo/moneda al documento (hoy `reporting` no lo agrega); cambiar el
  umbral de 30 días; soportar varios archivos por documento.
- **Nunca:** exponer documentos de vehículos ajenos; guardar `status` en la BD (se desactualiza);
  copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Registro un documento con vencimiento y veo su estado y días restantes correctos.
- Un documento vencido aparece como `expired`; uno que vence en 10 días, como `expiring`.
- El resumen cuadra con los documentos del vehículo y señala el próximo vencimiento.
- Adjunto y elimino el archivo sin afectar al documento.
- Un vehículo ajeno devuelve 403; un miembro simple no puede crear ni borrar.
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. **Estado derivado, no almacenado**: una columna `status` quedaría obsoleta al día siguiente.
2. **Un archivo por documento**: es un escaneo del documento, no un expediente. Si hace falta más,
   se pregunta antes.
3. **`expires_on` opcional**: el título de propiedad no vence; forzar fecha inventaría datos.
4. **Sin costo ni moneda**: `reporting` agrega mantenimiento, combustible y facturación; el costo del
   seguro exigiría cambiar ese contrato, así que queda fuera hasta decidirlo.
5. **`kind` es un enum cerrado** (los 4 del mapa + `other`) en vez de un catálogo editable como
   `maintenance_types`: aquí el valor legal está en el tipo, no en el nombre libre (`name`).
