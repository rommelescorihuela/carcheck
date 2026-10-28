# Spec: reminders

Recordatorios de **mantenimiento próximo** y **documentos por vencer**, derivados de los datos que el
usuario ya carga. El sistema los detecta, los guarda para no avisar dos veces y los entrega por
`Notifier` (bandeja + email + push). Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Que el usuario no tenga que acordarse de mirar fechas ni kilómetros: CarCheck le avisa **antes** de
que toque el servicio o venza el seguro, y le deja una lista de lo que viene.

Historias / criterios de aceptación:

- Veo una lista de **recordatorios vigentes** de mis vehículos (personales y de mis organizaciones),
  con vehículo, motivo y cuándo toca.
- Los recordatorios se agrupan en **vencidos** y **próximos**; el que antes vence aparece primero.
- Puedo **descartar** un recordatorio para mí; deja de aparecer y no vuelve a generarse mientras la
  condición sea la misma.
- El sistema avisa **una sola vez** al crear el recordatorio y **una sola vez** cuando pasa a vencido.
- Un documento renovado o un servicio hecho **borran** el recordatorio obsoleto.
- Puedo forzar la comprobación desde la app ("Comprobar ahora") sin esperar al proceso diario.
- Un proceso diario (`reminders:generate`) recorre todos los vehículos y emite los avisos.

## Tech Stack

- Igual que `notifications`: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React 19.
- Emisión exclusivamente vía `App\Services\Notifier` (categoría `reminders`).
- Umbrales reutilizados de los módulos existentes: `DocumentStatus::WARNING_DAYS` (30 días) y los
  estados `soon`/`due` de `MaintenanceSchedule` (500 km / 15 días).

## Autorización

- La lista se resuelve con el mismo criterio que `vehicles`: `organization_id` (debe ser miembro) o
  `scope=personal`; sin parámetros, todo lo accesible.
- Descartar exige poder **ver** el vehículo; el descarte es **por usuario** (no oculta el
  recordatorio a los demás miembros).
- Un recordatorio de un vehículo ajeno responde **404** (no se revela su existencia).
- `sync` genera solo para los vehículos del ámbito pedido; nunca para vehículos ajenos.

## Contrato de API

Todas bajo `auth:sanctum`.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/reminders` | Recordatorios vigentes no descartados, ordenados por urgencia |
| POST | `/api/reminders/{reminder}/dismiss` | Descarta el recordatorio para el usuario actual |
| POST | `/api/reminders/sync` | Regenera los recordatorios del ámbito y devuelve cuántos se crearon |

Parámetros de ámbito (los tres endpoints): `organization_id` (organización concreta) o
`scope=personal`. Sin parámetros: todo lo accesible.

### Respuesta

- Recordatorio: `{ id, kind, kind_label, title, body, due_on, due_km, km_left, days_left, status,
  vehicle: { id, make, model, plate }, url, created_at }`.
  `status` es `overdue` o `upcoming`; `days_left`/`km_left` son `null` cuando no aplican.
- Lista: `{ data: [...], meta: { overdue, upcoming, total } }`.
- `dismiss`: `{ message }`.
- `sync`: `{ message, data: { created, total } }`.

## Reglas de generación

- **Documentos**: con `expires_on` no nulo y `days_left <= 30`. `due_on = expires_on`.
  Título: `«<Tipo> por vencer»` o `«<Tipo> vencido»`; cuerpo con nombre del documento, vehículo y
  días restantes/transcurridos.
- **Mantenimiento**: cada fila de `MaintenanceSchedule::dueFor()` con estado `soon` o `due`.
  `due_km`/`due_on` tal como los calcula el servicio (plan o valores manuales del último registro).
  Título: `«<Tipo de mantenimiento> próximo»` / `«… vencido»`.
- **Huella (`fingerprint`)**: identifica la condición concreta (`kind|source|due_on|due_km`).
  Si ya existe un recordatorio con esa huella no se crea otro ni se vuelve a avisar.
- **Limpieza**: al regenerar un vehículo se eliminan sus recordatorios cuya huella ya no está entre
  las condiciones vigentes (documento renovado, servicio hecho, plan borrado).
- **Avisos**: al crear el recordatorio se envía un aviso "próximo" (`notified_at`); cuando queda
  vencido y aún no se había avisado como tal, se envía el aviso "vencido" (`overdue_notified_at`).
  Destinatarios: el dueño si el vehículo es personal; **todos los miembros** si es de organización.

## Modelo de datos

- `reminders`: `vehicle_id` (cascade), `kind` (`maintenance|document`), `source` (morph:
  `MaintenanceType` o `Document`), `fingerprint` único, `title`, `body`, `due_on`, `due_km`,
  `notified_at`, `overdue_notified_at`, timestamps.
- `reminder_dismissals`: `reminder_id` (cascade), `user_id` (cascade), timestamps;
  único `(reminder_id, user_id)`.

## Project Structure

```text
carcheck/
  app/Enums/ReminderKind.php
  app/Models/{Reminder,ReminderDismissal}.php
  app/Services/ReminderGenerator.php
  app/Http/Controllers/Api/ReminderController.php
  app/Http/Resources/ReminderResource.php
  app/Console/Commands/GenerateReminders.php
  database/migrations/*_create_reminders_table.php, *_create_reminder_dismissals_table.php
  routes/api.php, routes/console.php
  tests/Feature/Reminders/*
carcheck-app/
  src/api/{types,endpoints}.ts, src/stores/reminders.ts
  src/pages/Reminders.tsx
  e2e/reminders.spec.ts
```

## Commands

Backend: `php artisan test --filter=Reminder`, `vendor/bin/pint`, `php artisan reminders:generate`.
Frontend: `npm run typecheck`, `npm run test`, `npm run build`, `npx playwright test`.

## Code Style

Pint por defecto; textos en español. Enums con `label()`. Fechas `Y-m-d` en la API; el cliente
formatea. El generador no conoce HTTP ni la PWA.

## Testing Strategy

- **Backend:**
  - `ReminderGenerationTest`: documento a 10 días → recordatorio + aviso; documento a 60 días →
    nada; documento vencido → estado `overdue` y aviso de vencido; mantenimiento `soon` por km;
    ejecutar dos veces no duplica ni reavisa; documento renovado borra el obsoleto; descartado no
    reaparece; vehículo de organización avisa a todos los miembros.
  - `ReminderApiTest`: lista con ámbito personal/organización, aislamiento (404), descarte por
    usuario (otro miembro sigue viéndolo), `sync` crea y cuenta, contadores del `meta`.
  - `GenerateRemindersCommandTest`: el comando recorre todos los vehículos e informa el total.
- **Cliente:** store `reminders`; E2E `reminders.spec.ts` (registro → vehículo → documento a 10 días
  → recordatorios → comprobar → ver y descartar).

## Boundaries

- **Siempre:** derivar de los datos existentes (documentos y planes), no pedir fechas nuevas;
  deduplicar por huella; avisar por `Notifier`; borrar lo obsoleto al regenerar.
- **Preguntar antes:** recordatorios configurables por el usuario (días de antelación, canales por
  tipo), notificaciones por SMS/WhatsApp, reprogramar citas, integración con calendario, envío en
  cola.
- **Nunca:** duplicar avisos en cada ejecución; mostrar recordatorios de vehículos ajenos; generar
  recordatorios para documentos sin vencimiento; copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Un documento que vence en ≤30 días genera un recordatorio y un aviso, una sola vez.
- Un servicio a ≤500 km o ≤15 días genera su recordatorio de mantenimiento.
- Renovar el documento o registrar el servicio hace desaparecer el recordatorio anterior.
- Descartar oculta el recordatorio solo para quien lo descarta.
- El comando diario deja el sistema al día sin intervención.
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. **Recordatorio persistido además de derivado**: la condición se recalcula siempre, pero la fila
   guarda `notified_at` y el descarte; sin ella no habría forma de no repetir avisos.
2. **Huella en lugar de estado**: si cambia el vencimiento (documento renovado, nuevo servicio) la
   huella cambia y nace un recordatorio nuevo; el viejo se borra. No hay `status` que actualizar.
3. **Descarte por usuario**: el recordatorio es del vehículo, pero "no me lo muestres" es personal;
   `reminder_dismissals` evita que un miembro silencie al resto.
4. **Un solo aviso por fase**: "próximo" al crearse y "vencido" al cruzar el umbral. Sin digest ni
   repeticiones semanales (se preguntará antes de añadirlas).
5. **`sync` manual**: permite ver el resultado al instante y hace el E2E determinista sin depender
   del reloj del scheduler.
6. **Vehículos sin historial no avisan**: sin un registro previo no hay base para calcular el
   próximo servicio; el plan solo no basta.
