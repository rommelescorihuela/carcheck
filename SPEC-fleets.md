# Spec: fleets

Operación de **flotas pequeñas** sobre el mismo garaje: rol `driver`, atribución de quién registra
combustible y mantenimiento, alta masiva de vehículos por CSV y una vista consolidada del gasto y
las alertas de toda la flota. Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Que una empresa con 5–75 vehículos pueda operar CarCheck sin cargar vehículos uno por uno y sin dar
permisos de administrador a quien solo conduce. La flota no es un módulo de datos nuevo: reutiliza
`identity`, `vehicles`, `fuel`, `maintenance`, `documents` y `billing`; añade la capa operativa que
faltaba.

Historias / criterios de aceptación:

- Como `owner`/`admin` invito a un **conductor** (`driver`): registra combustible y mantenimiento
  desde el teléfono, pero no crea vehículos, no invita gente y no toca la facturación.
- Cada carga y cada servicio guarda **quién lo registró** y la app lo muestra.
- Quien registró una entrada puede **corregirla**; el resto necesita gestionar el vehículo.
- Como `owner`/`admin` subo un **CSV** y se crean hasta 500 vehículos de una vez, respetando el
  límite del plan y explicando fila a fila qué falló.
- Como miembro de la flota veo una **vista consolidada**: gasto del periodo, combustible,
  documentos vencidos, servicios pendientes y el detalle por vehículo.
- Los planes **Flota** y **Empresa** existen en el catálogo con límites bloqueantes (ver
  [SPEC-billing](./SPEC-billing.md)).

## Tech Stack

- Igual que el resto: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React 19.
- El CSV se procesa en PHP (`fgetcsv` sobre un buffer temporal), sin paquetes nuevos.
- La vista consolidada reutiliza `ExpenseReport` y `MaintenanceSchedule`; no agrega en SQL para que
  SQLite (tests) y PostgreSQL (producción) den el mismo resultado.

## Roles y autorización

El pivot `organization_user` admite `owner|admin|member|driver` (y lo mismo en `invitations`).

| Acción | `owner` | `admin` | `member` | `driver` |
|---|---|---|---|---|
| Ver vehículos, historial, documentos y flota | ✅ | ✅ | ✅ | ✅ |
| Registrar combustible / mantenimiento | ✅ | ✅ | ✅ | ✅ |
| Editar/borrar **su propio** registro | ✅ | ✅ | ✅ | ✅ |
| Editar/borrar registros de otros | ✅ | ✅ | ❌ | ❌ |
| Crear, editar, eliminar y traspasar vehículos | ✅ | ✅ | ❌ | ❌ |
| Invitar, cambiar rol y quitar miembros | ✅ | ✅* | ❌ | ❌ |
| Importar vehículos por CSV | ✅ | ✅ | ❌ | ❌ |
| Gestionar la suscripción y los pagos | ✅ | ✅ | ❌ | ❌ |
| Traspasar la propiedad de la organización | ✅ | ❌ | ❌ | ❌ |

\* Un `admin` puede gestionar `member`/`driver`, pero solo el `owner` nombra o degrada
administradores y cambia el propietario.

Reglas en código:

- `VehiclePolicy`: `view` = accesible; `log` = miembro de la organización (o dueño personal);
  `update`/`delete`/`transfer` = `manage` (dueño personal u `owner`/`admin`).
- `FuelEntryPolicy` y `MaintenanceRecordPolicy`: `update`/`delete` = autor del registro con `log`
  sobre el vehículo **o** gestión del vehículo.
- `Billing` sigue siendo la única fuente de verdad de límites y estado de suscripción.

## Contrato de API

Todas bajo `auth:sanctum`.

| Método | Ruta | Autorización |
|---|---|---|
| POST | `/api/vehicles/import` | `owner`/`admin` de la organización, o cuenta personal |
| GET | `/api/fleet/summary` | miembro del ámbito pedido |
| PATCH | `/api/organizations/{org}/members/{user}` | `owner` (roles `admin`, `member`, `driver`) |
| POST | `/api/organizations/{org}/invitations` | `owner`/`admin` (roles `admin`, `member`, `driver`) |

### Alta masiva (`POST /api/vehicles/import`)

`multipart/form-data`:

- `file` (`required|file|mimes:csv,txt|max:2048`).
- `organization_id` (`nullable|integer|exists:organizations,id`). Sin él, importa a la cuenta
  personal.

Reglas del archivo:

- Delimitador detectado en la cabecera: `,`, `;` o tabulador.
- BOM UTF-8 tolerado; si el contenido no es UTF-8 se interpreta como ISO-8859-1 (Excel en español).
- Cabeceras sin acentos ni mayúsculas. Alias aceptados:

  | Campo | Cabeceras |
  |---|---|
  | `make` | `marca`, `make`, `fabricante` |
  | `model` | `modelo`, `model` |
  | `year` | `año`, `ano`, `anio`, `year` |
  | `plate` | `placa`, `plate`, `matrícula`, `patente` |
  | `vin` | `vin`, `chasis`, `serie` |
  | `type` | `tipo`, `type` |
  | `fuel_type` | `combustible`, `fuel` |
  | `odometer_km` | `kilometraje`, `km`, `odómetro` |
  | `color` | `color` |
  | `notes` | `notas`, `observaciones` |

- `marca` y `modelo` son obligatorias: si faltan las columnas, la petición falla con 422.
- Valores de `tipo`: `auto/carro/coche`, `moto/motocicleta`, `camión/camioneta`, `otro`.
- Valores de `combustible`: `gasolina`, `diésel`, `gnc`, `eléctrico`, `híbrido`.
- Máximo **500 filas** de datos.
- VIN repetido dentro del archivo o ya existente en la BD → error en esa fila, no en el archivo.
- El límite del plan se aplica **fila a fila**: se crea lo que cabe y el resto se informa con el
  mensaje de mejora de plan.

Respuesta `200`:

```json
{
  "data": {
    "created": 22,
    "failed": 2,
    "limit": 25,
    "remaining": 1,
    "errors": [
      { "line": 7, "message": "El VIN está repetido en el archivo." },
      { "line": 19, "message": "El plan permite 25 vehículos. Mejora el plan para importar más." }
    ]
  }
}
```

`line` es la línea real del CSV (la 1 es la cabecera) y siempre entero.

### Vista consolidada (`GET /api/fleet/summary`)

Parámetros: `organization_id` (opcional; sin él usa todo lo accesible por el usuario), `from` y `to`
(por defecto, del inicio del mes en curso a hoy). `to` no puede ser anterior a `from`.

Respuesta `200` (`data`):

- `period: { from, to }`.
- `vehicles: { total, with_expired_documents, with_due_maintenance, without_activity }`.
- `totals`: filas de gasto por moneda (misma forma que `reporting`: `currency`, `fuel`,
  `maintenance`, `total`, `fuel_entries`, `maintenance_entries`).
- `fuel: { entries, liters, costs: [{ currency, liters, cost, avg_price }] }`.
- `maintenance: { records, costs: [{ currency, cost }] }`.
- `documents: { total, expired, expiring }`.
- `alerts`: hasta 12 avisos ordenados por urgencia
  (`kind: document|maintenance`, `vehicle_id`, `vehicle`, `label`, `days_left`).
- `rows`: una fila por vehículo con `vehicle`, `currency`, `fuel`, `maintenance`, `total`,
  `documents: { expired, expiring }`, `maintenance_due`, `maintenance_soon`; ordenadas por
  documentos vencidos, mantenimiento vencido y gasto.

## Modelo de datos

Migración `2026_10_30_100000_add_driver_role_and_log_attribution`:

- `organization_user.role` e `invitations.role` aceptan `driver` (en PostgreSQL se recrea el CHECK).
- `fuel_entries.user_id` y `maintenance_records.user_id`: FK nullable a `users`, `nullOnDelete`.
  Nulo en registros anteriores a la atribución; la UI lo interpreta como "sin autor".

Migración `2026_10_30_100100_rebrand_taller_plan_as_fleet`:

- Renombra en sitio el plan `taller` → `flota` para conservar el `plan_id` de las suscripciones.
- Inserta `empresa` si no existe.

Catálogo resultante (ver `PlanSeeder`):

| Plan | Precio | Vehículos | Miembros |
|---|---|---|---|
| Básico | $3.99 | 1 | sin tope |
| Familiar | $7.99 | 5 | 5 |
| Flota | $29.99 | 25 | 10 |
| Empresa | $59.99 | 75 | 25 |

## PWA

- Ruta `/flota`: periodo editable, indicadores, combustible, avisos y detalle por vehículo. En
  móvil el detalle se muestra como tarjetas; en `sm+` como tabla.
- Ruta `/vehiculos`: importador CSV con selector de archivo nativo, resumen de creados/fallidos y
  errores por línea.
- `OrganizationMembers` y `Organizations`: roles en español (`Propietario`, `Administrador`,
  `Miembro`, `Conductor`), invitación con rol y cambio de rol solo para el propietario.
- `FuelList` y `MaintenanceList` muestran "Registrado por …".
- El enlace **Flota** aparece en la navegación principal.

## Decisiones y caveats

1. **`driver` no es un rol de plataforma**: es un rol de organización en el pivot; Spatie/Shield
   siguen reservados al panel `/admin`.
2. **Conductores y miembros registran**: obligar a ser `admin` para cargar combustible convertía
   cada carga en una tarea del dueño y hacía inútil la app en una flota.
3. **Atribución sin historial de cambios**: se guarda el autor de la entrada; no hay auditoría de
   ediciones (fuera de alcance hasta que exista un requisito legal).
4. **Importación parcial**: se prioriza no abortar todo el archivo por una fila mala. El usuario ve
   qué entró y qué no.
5. **Sin importación de documentos ni historial**: el CSV solo crea vehículos. Importar seguros o
   servicios exige otro formato y otra validación.
6. **La vista consolidada no convierte monedas**: agrupa por moneda, igual que `reporting`.
