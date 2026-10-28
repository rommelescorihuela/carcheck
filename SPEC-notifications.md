# Spec: notifications

Infraestructura de avisos del producto: **email**, **push PWA (web-push)** y **bandeja in-app**,
con preferencias por usuario y por categoría. Es la base que consumirá `reminders` (y cualquier
módulo que necesite avisar). Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Que el usuario se entere de lo que pasa con sus vehículos y su cuenta sin depender de mirar la app.
Cada aviso se emite una vez y se entrega por los canales que el usuario tenga activos.

Historias / criterios de aceptación:

- Recibo avisos en una **bandeja dentro de la PWA**, con contador de no leídos.
- Puedo **marcar como leído** uno o todos, y **eliminar** avisos.
- Activo/desactivo **push** en mi dispositivo; el navegador se suscribe y la suscripción se guarda.
- Elijo por **categoría** (`sistema`, `facturación`, `mantenimiento`, `documentos`, `recordatorios`)
  qué canales quiero: `in_app`, `email` y `push`.
- Puedo enviarme una **notificación de prueba** para comprobar que todo funciona.
- Si el push no está configurado en el servidor, la app lo indica y no falla: el aviso queda
  igualmente en la bandeja.
- Las notificaciones son **privadas**: nunca veo las de otro usuario.
- Las suscripciones push caducadas (404/410) se **eliminan solas**.

## Tech Stack

- Igual que `identity`: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React 19.
- Tabla `notifications` estándar de Laravel (UUID + morphs).
- Push con `laravel-notification-channels/webpush` (usa `minishlink/web-push` v11); claves VAPID
  por entorno.
- Service worker propio (`public/push-sw.js`) inyectado en el SW de Workbox.

## Autorización

- **Todo** el módulo es del usuario autenticado: la bandeja y las preferencias se consultan
  siempre desde `$request->user()`.
- Un aviso ajeno devuelve **404**, no 403 (no se revela su existencia).
- Las suscripciones push se guardan contra el usuario autenticado; registrar el mismo endpoint
  desde otra cuenta lo reasigna a la cuenta actual.
- El envío de prueba solo se manda a uno mismo.

## Contrato de API

Todas bajo `auth:sanctum`.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/notifications` | Bandeja del usuario, paginada, más recientes primero |
| GET | `/api/notifications/unread-count` | `{ count }` de no leídos |
| POST | `/api/notifications/{notification}/read` | Marca uno como leído |
| POST | `/api/notifications/read-all` | Marca todos como leídos |
| DELETE | `/api/notifications/{notification}` | Elimina un aviso |
| GET | `/api/notifications/preferences` | Preferencias por categoría |
| PUT | `/api/notifications/preferences` | Reemplaza las preferencias enviadas |
| GET | `/api/notifications/push/public-key` | Clave pública VAPID (`null` si no hay push) |
| POST | `/api/notifications/push-subscriptions` | Alta/actualización de suscripción push |
| DELETE | `/api/notifications/push-subscriptions` | Baja por `endpoint` |
| POST | `/api/notifications/test` | Envía una notificación de prueba al usuario |

### Entrada y validación

- **Preferencias (PUT):** `preferences` (`required|array|min:1`), cada elemento con
  `category` (`required|in:system,billing,maintenance,documents,reminders`),
  `in_app` / `email` / `push` (`required|boolean`). Se hace **upsert** por
  `(user_id, category)`; las categorías no enviadas conservan su valor.
- **Suscripción push (POST):** `endpoint` (`required|string|max:1024|url`),
  `keys.p256dh` (`required|string|max:255`), `keys.auth` (`required|string|max:255`),
  `content_encoding` (`nullable|in:aes128gcm,aesgcm`; por defecto `aes128gcm`).
- **Baja push (DELETE):** `endpoint` (`required|string|max:1024`).

### Respuesta

- Aviso: `{ id, category, category_label, title, body, url, payload, read_at, created_at }`.
  `payload` es la información libre que añade el módulo emisor (por ejemplo `vehicle_id`).
- Preferencias: `{ data: { preferences: [{ category, category_label, in_app, email, push }],
  push_configured } }`.
- `unread-count`: `{ data: { count } }`.
- `push/public-key`: `{ data: { public_key } }` (`null` sin claves VAPID).
- `test`: `{ message, data: <aviso>|null }`; `data` es `null` si el usuario desactivó `in_app`.

## Modelo de datos

- `notifications` (estándar Laravel): `id` UUID, `type`, `notifiable_type`/`notifiable_id`,
  `data` (JSON), `read_at`, timestamps. Índice por `(notifiable_type, notifiable_id)`.
- `notification_preferences`: `user_id` (cascade), `category` (30), `in_app`, `email`, `push`
  (booleanos, por defecto `true`), timestamps; único `(user_id, category)`.
- `push_subscriptions`: tabla del paquete (`subscribable` morph, `endpoint` único,
  `public_key`, `auth_token`, `content_encoding`, timestamps).

## Project Structure

```text
carcheck/
  app/Enums/NotificationCategory.php
  app/Contracts/PushSender.php
  app/Services/{Notifier,NotificationPreferences,WebPushSender}.php
  app/Mail/NotificationMail.php
  app/Http/Controllers/Api/NotificationController.php
  app/Http/Resources/NotificationResource.php
  database/migrations/*_create_notifications_table.php, *_create_notification_preferences_table.php,
    *_create_push_subscriptions_table.php
  resources/views/mail/notification.blade.php
  tests/Feature/Notifications/*
  tests/Support/FakePushSender.php
carcheck-app/
  public/push-sw.js
  src/api/{endpoints,types}.ts, src/stores/notifications.ts, src/lib/push.ts
  src/pages/Notifications.tsx
  e2e/notifications.spec.ts
```

## Commands

Backend (`carcheck/`): `php artisan test --filter=Notification`, `vendor/bin/pint`,
`php artisan migrate --seed`, `php artisan webpush:vapid` (genera claves).
Frontend (`carcheck-app/`): `npm run typecheck`, `npm run test`, `npm run build`,
`npx playwright test`.

## Code Style

Laravel Pint por defecto; strings en español. Enums con `label()`. En la PWA, `import type` y
errores de campo con `ApiError.errors`. Los canales se muestran como casillas por categoría.

## Testing Strategy

- **Backend:**
  - `NotifierTest`: crea el aviso in-app; manda email (`Mail::fake`) y push (fake de
    `PushSender`); respeta cada preferencia; una categoría desactivada no genera aviso.
  - `NotificationPreferencesTest`: valores por defecto, upsert y `allows()` por canal.
  - `NotificationApiTest`: bandeja propia, contador, leer uno/todos, borrar, aviso ajeno → 404,
    preferencias (GET/PUT), clave pública, alta/baja de suscripción push, envío de prueba.
  - `WebPushSenderTest`: sin claves VAPID no envía ni falla.
- **Cliente:** store `notifications`; E2E `notifications.spec.ts` (registro → aviso de prueba →
  bandeja con no leído → marcar leído).
- Verificación: `php artisan test` + `pint`; `typecheck` + `test` + `build`.

## Boundaries

- **Siempre:** leer la bandeja desde el usuario autenticado; comprobar preferencias antes de cada
  canal; enviar de forma síncrona (no hay worker en el MVP); podar suscripciones caducadas.
- **Preguntar antes:** añadir otro canal (SMS/WhatsApp), programar envíos diferidos, agrupar avisos
  (digest), plantillas editables desde el panel, sonido/vibración configurables.
- **Nunca:** exponer avisos de otro usuario; enviar push con claves VAPID en el repositorio; fallar
  el alta de un aviso porque el push no esté configurado; copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Un aviso generado por el backend aparece en la bandeja con su categoría y contador.
- Desactivar `in_app` para una categoría deja de crear avisos de esa categoría.
- Desactivar `email` o `push` evita ese canal, sin afectar a los demás.
- La app muestra el estado del push y permite suscribirse o darse de baja.
- La notificación de prueba llega a la bandeja y el E2E la ve.
- Un usuario no puede leer ni borrar avisos ajenos (404).
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. **Envío síncrono**: no se configura cola ni worker; con `QUEUE_CONNECTION=database` un job
   quedaría sin procesar. Los tres canales se intentan en la misma petición.
2. **Preferencias por categoría, no por tipo**: cinco categorías estables y comprensibles; añadir
   tipos nuevos no obliga a migrar las preferencias del usuario.
3. **`in_app` es la fuente de verdad**: el aviso se guarda primero; email y push son adicionales y
   un fallo suyo no revierte la bandeja.
4. **Push opcional**: sin claves VAPID el sistema registra la omisión y sigue; la app lo indica con
   `push_configured`.
5. **Suscripción reasignable**: un endpoint es del dispositivo; si otra cuenta inicia sesión en él,
   la suscripción pasa a esa cuenta (evita avisos cruzados).
6. **Sin cola de reintentos**: si un push falla por red, se pierde; el aviso sigue en la bandeja.
