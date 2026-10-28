# Tasks: notifications

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: Enum, migraciones y suscripciones push

**Acceptance criteria:**
- [x] `NotificationCategory` (`system|billing|maintenance|documents|reminders`) con `label()` en español
- [x] Migración estándar `notifications` (UUID, morphs, `data` JSON, `read_at`)
- [x] Migración `notification_preferences` (único `user_id` + `category`, tres booleanos)
- [x] Migración `push_subscriptions` del paquete (endpoint único, claves, codificación)
- [x] `User` usa `HasPushSubscriptions` y expone `notificationPreferences()`

**Verification:** `php artisan migrate:fresh` · `php artisan test --filter=Notification`

**Dependencies:** None · **Scope:** S

## Task 2: Preferencias

**Acceptance criteria:**
- [x] `NotificationPreferences::for(User)` devuelve las cinco categorías con etiqueta y canales
- [x] Sin fila guardada, todos los canales están activos
- [x] `allows($user, $category, $channel)` respeta lo guardado
- [x] `update()` hace upsert por categoría sin borrar las no enviadas
- [x] `NotificationPreferencesTest` cubre defaults, upsert y filtrado por canal

**Verification:** `php artisan test --filter=NotificationPreferencesTest`

**Dependencies:** Task 1 · **Scope:** S

## Task 3: Envío push

**Acceptance criteria:**
- [x] `PushSender` (interfaz) con `send(User, WebPushMessageInterface): int`
- [x] `WebPushSender` usa VAPID de `config/webpush.php` y `ContentEncoding::aes128gcm`
- [x] Sin claves VAPID registra la omisión y devuelve 0 (no lanza)
- [x] Elimina suscripciones con reporte de expirada
- [x] Binding en `AppServiceProvider`; `FakePushSender` en `tests/Support`
- [x] `WebPushSenderTest` cubre el caso sin claves

**Verification:** `php artisan test --filter=WebPushSenderTest`

**Dependencies:** Task 1 · **Scope:** M

## Task 4: `Notifier` y email

**Acceptance criteria:**
- [x] `NotificationMail` + vista `mail.notification` en español
- [x] `Notifier::send()` crea la fila in-app con categoría, título, cuerpo, URL y payload
- [x] Envía email y push solo si la preferencia lo permite
- [x] Un fallo de email/push no revierte el aviso in-app
- [x] `sendToMany()` para `reminders`
- [x] `NotifierTest` con `Mail::fake()` y `FakePushSender`

**Verification:** `php artisan test --filter=NotifierTest`

**Dependencies:** Tasks 2, 3 · **Scope:** M

## Task 5: API de bandeja

**Acceptance criteria:**
- [x] `GET /api/notifications` paginado, solo del usuario, más recientes primero
- [x] `GET /api/notifications/unread-count`
- [x] `POST /api/notifications/{notification}/read` y `read-all`
- [x] `DELETE /api/notifications/{notification}`
- [x] `NotificationResource` con `category_label`
- [x] Aviso ajeno → 404; `NotificationApiTest` cubre todo

**Verification:** `php artisan test --filter=NotificationApiTest`

**Dependencies:** Task 4 · **Scope:** M

## Task 6: Preferencias, push y prueba (API)

**Acceptance criteria:**
- [x] `GET/PUT /api/notifications/preferences` con validación y `push_configured`
- [x] `GET /api/notifications/push/public-key`
- [x] `POST/DELETE /api/notifications/push-subscriptions` (alta, reasignación y baja)
- [x] `POST /api/notifications/test` envía al usuario y devuelve el aviso
- [x] Tests de permisos y validación

**Verification:** `php artisan test --filter=NotificationApiTest`

**Dependencies:** Task 5 · **Scope:** M

## Checkpoint: API
- [x] Bandeja, preferencias y push operables por API; aislamiento verificado

## Task 7: Cliente + store

**Acceptance criteria:**
- [x] `types.ts`: `AppNotification`, `NotificationCategory`, `NotificationPreference`
- [x] `endpoints.ts`: bandeja, contador, leer, borrar, preferencias, clave, suscripción, prueba
- [x] `stores/notifications.ts` con `load`, `markRead`, `markAllRead`, `remove`, `savePreferences`, `sendTest`
- [x] Test del store

**Verification:** `npm run typecheck && npm run test`

**Dependencies:** Task 6 · **Scope:** M

## Task 8: Service worker y utilidades push

**Acceptance criteria:**
- [x] `public/push-sw.js` maneja `push` y `notificationclick` (abre/enfoca `/notificaciones`)
- [x] `workbox.importScripts: ['push-sw.js']` en `vite.config.ts`
- [x] `lib/push.ts`: soporte, conversión de clave y `subscribe()`/`unsubscribe()`
- [x] El build genera un `sw.js` que importa `push-sw.js`

**Verification:** `npm run build`

**Dependencies:** Task 7 · **Scope:** M

## Task 9: Página `Notifications`

**Acceptance criteria:**
- [x] Lista con título, cuerpo, categoría, fecha y estado leído/no leído
- [x] Acciones: marcar leído, marcar todo, eliminar
- [x] Preferencias por categoría (tres casillas) con guardado
- [x] Botón de push (activar/desactivar) y estado cuando no está configurado
- [x] Botón "Enviar notificación de prueba"
- [x] Ruta `/notificaciones`, enlace con contador de no leídos en la navegación
- [x] Textos en español

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 8 · **Scope:** M

## Task 10: E2E `notifications.spec.ts`

**Acceptance criteria:**
- [x] `registro → notificaciones → enviar prueba → ver aviso y no leído → marcar leído`
- [x] `npx playwright test` en verde

**Verification:** `npx playwright test`

**Dependencies:** Tasks 7, 9 · **Scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-notifications.md` cumplidos
- [x] Listo para `reminders`
