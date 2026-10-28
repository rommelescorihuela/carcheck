# Implementation Plan: notifications

## Overview

Bandeja in-app + email + push PWA, con preferencias por usuario y categoría y un servicio
`Notifier` que será la puerta de entrada para `reminders`. Ver
[SPEC-notifications](../../SPEC-notifications.md).

## Architecture Decisions

- **Un solo punto de emisión (`Notifier`)**: los módulos no conocen canales ni preferencias; solo
  llaman a `Notifier::send()`.
- **`PushSender` como interfaz**: `WebPushSender` usa `minishlink/web-push`; en tests se sustituye
  por un fake que registra los envíos, sin tocar la red.
- **Bandeja sobre la tabla estándar de Laravel**: `$user->notifications()` ya existe por
  `Notifiable`; se crea la fila directamente (sin `Notification::fake` ni colas).
- **Preferencias con defaults en código**: si no hay fila, todos los canales están activos; se
  guarda solo lo que el usuario cambia.
- **Push sin VAPID no es error**: se registra en el log y se devuelve 0; el alta del aviso sigue.
- **Service worker propio inyectado**: `push-sw.js` gestiona `push` y `notificationclick`;
  Workbox lo importa con `workbox.importScripts`.

## Task List

### Fase 0: Fundación
- [x] Task 1: `NotificationCategory`, migraciones (`notifications`, `notification_preferences`, `push_subscriptions`), `HasPushSubscriptions` en `User`
- [x] Task 2: `NotificationPreferences` (defaults, `for`, `allows`, `update`) + tests

### Fase 1: Envío
- [x] Task 3: `PushSender` + `WebPushSender` (VAPID, poda de caducadas) + test sin claves
- [x] Task 4: `NotificationMail` + `Notifier` (in-app, email, push; respeta preferencias) + tests

### Fase 2: API
- [x] Task 5: `NotificationController` (bandeja, contador, leer, leer todo, borrar) + recurso + rutas + tests
- [x] Task 6: Preferencias, clave pública, suscripciones push y envío de prueba + tests

### Fase 3: PWA
- [x] Task 7: Cliente + store `notifications` + test del store
- [x] Task 8: `push-sw.js`, `lib/push.ts` y `workbox.importScripts`
- [x] Task 9: Página `Notifications` (bandeja, preferencias, push, prueba) + ruta y contador en la navegación
- [x] Task 10: E2E `notifications.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-notifications.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Push falla y tumba el aviso | Alto | Guardar in-app primero y capturar errores de canales externos |
| Suscripciones basura acumuladas | Medio | Borrar en el reporte 404/410 de `flush()` |
| Preferencias sin fila | Medio | Defaults en código; nunca "sin canales" por olvido |
| SW con dos listeners de `push` | Medio | Un solo `push-sw.js` importado por Workbox; no duplicar |
| Claves VAPID en el repo | Alto | Solo `.env`; `.env.example` con placeholders |
| Tests dependientes de red | Alto | `FakePushSender` y `Mail::fake()`; sin llamadas reales |

## Task List Target

Tareas en [`todo.md`](./todo.md).
