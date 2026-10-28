# Spec: billing

Planes, prueba gratuita, suscripción y **pagos manuales** (sin pasarela) con multimoneda. La
suscripción pertenece a un `owner` (usuario personal u organización), igual que los vehículos.
Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Permitir cobrar el SaaS sin integración de pasarela: el usuario elige plan, arranca su prueba
gratuita y, cuando toca pagar, **reporta un pago** (transferencia, Pago móvil, Zelle, Binance,
efectivo) con su comprobante; un `super_admin` lo aprueba y la suscripción se extiende.

Historias / criterios de aceptación:

- Veo los **planes activos** con precio, moneda, intervalo, días de prueba y límites.
- Arranco una **prueba gratuita** una sola vez por propietario (la primera suscripción).
- Sin prueba disponible, la suscripción queda **pendiente de pago** hasta que se apruebe un pago.
- Reporto un pago con monto, moneda, método, referencia, fecha y **comprobante** (foto/PDF).
- Si pago en una moneda distinta a la del plan, indico la **tasa** (unidades de mi moneda por 1 USD).
- Un `super_admin` **aprueba** o **rechaza** el pago desde el panel `/admin`.
- Al aprobar, la suscripción pasa a `active` y su periodo se extiende según el intervalo del plan.
- Veo mi suscripción actual, sus días restantes y el **uso** frente a los límites del plan.
- Puedo **cambiar de plan** o **cancelar**; la cancelación mantiene el acceso hasta el fin del periodo.
- La suscripción de una organización la gestionan solo `owner`/`admin`; un miembro simple solo la ve.

## Tech Stack

- Igual que `identity`: Laravel 13 + Filament 5 + PostgreSQL; API Sanctum; PWA React.
- Montos `decimal(12,2)`; tasa de cambio `decimal(14,4)`.
- Estados y permisos en español en la UI; código y rutas en inglés.

## Autorización

- **Owner de la suscripción**: el propio `User` o una `Organization` de la que el usuario es
  miembro. `organization_id` en la petición selecciona la organización (por defecto, ámbito
  personal).
- **Gestionar** (crear/cambiar/cancelar suscripción, reportar pago): propietario personal, u
  `owner`/`admin` de la organización.
- **Ver**: cualquier miembro de la organización.
- **Aprobar/rechazar pagos**: solo `super_admin` (rol de plataforma).

## Contrato de API

Todas bajo `auth:sanctum`.

| Método | Ruta | Autorización |
|---|---|---|
| GET | `/api/billing/plans` | autenticado |
| GET | `/api/billing/subscription` | ver (query `organization_id`) |
| POST | `/api/billing/subscription` | gestionar |
| PATCH | `/api/billing/subscription` | gestionar |
| DELETE | `/api/billing/subscription` | gestionar |
| GET | `/api/billing/payments` | ver (paginado) |
| POST | `/api/billing/payments` | gestionar |
| POST | `/api/billing/payments/{payment}/proof` | gestionar |
| DELETE | `/api/billing/payments/{payment}/proof` | gestionar |
| POST | `/api/billing/payments/{payment}/approve` | `super_admin` |
| POST | `/api/billing/payments/{payment}/reject` | `super_admin` |

### Entrada y validación

- **Suscripción (POST/PATCH):** `plan_id` (`required|integer|exists:plans,id`, plan activo),
  `organization_id` (`nullable|integer|exists:organizations,id`).
- **Pago (POST):** `amount` (`required|numeric|min:0.01`), `currency` (`required|string|size:3`),
  `exchange_rate` (`nullable|numeric|min:0.0001`, por defecto `1`), `method`
  (`required|in:transfer,mobile_payment,zelle,binance,cash,other`), `reference`
  (`nullable|string|max:100`), `paid_on` (`required|date`), `notes` (`nullable|string|max:2000`).
- **Comprobante:** `file` (`required|file|mimes:jpeg,png,webp,pdf|max:10240`).
- **Rechazo:** `reason` (`nullable|string|max:500`).

### Respuesta

- `plans`: `{ data: [{ id, code, name, description, price, currency, interval, interval_label,
  trial_days, max_vehicles, max_members, sort }] }`.
- `subscription`: `{ data: { subscription|null, usage: { vehicles, members }, can_manage } }`.
  Suscripción: `{ id, plan, status, effective_status, trial_ends_at, current_period_start,
  current_period_end, days_left, trial_days_left, canceled_at, payment_method, can_manage }`.
- `payments`: paginado; pago `{ id, subscription_id, amount, currency, exchange_rate, amount_base,
  method, method_label, reference, paid_on, status, notes, proof: {id, file_name, url}|null,
  reviewed_at, created_at }`.
- Montos como **string** (decimales de Eloquent).

### Reglas de negocio

- **Prueba gratuita**: solo si el propietario nunca tuvo suscripción (`trial_ends_at` nulo en toda
  su historia) y el plan tiene `trial_days > 0`. Si no, la suscripción nace `pending_payment`.
- **Una suscripción vigente**: si la actual da acceso (`trialing`/`active`), crear otra devuelve 422.
  Cancelada o vencida → se crea una nueva y queda como actual (la anterior se conserva).
- **Aprobar pago**: debe estar `pending`; `amount_base = amount / exchange_rate` (la tasa es 1
  cuando la moneda del pago coincide con la del plan); si `amount_base < price` → 422 (el pago no
  cubre el plan).
  El periodo empieza en `paid_on` (o al fin del periodo actual si aún es futuro) y termina según
  `interval` (`monthly` +1 mes, `yearly` +1 año). La suscripción pasa a `active`.
- **Rechazar pago**: pasa a `rejected` y se guarda el motivo en `notes`.
- **Cancelar**: `status = canceled`, `canceled_at = now`; conserva acceso hasta `current_period_end`.
- **Estado efectivo** (derivado, no almacenado): `trialing` vencida → `expired`; `active` con
  `current_period_end` pasado → `expired`; `canceled` se informa como `canceled` con `has_access`
  hasta el fin del periodo.

## Modelo de datos

- `plans`: `code` (único), `name`, `description`, `price` `decimal(12,2)`, `currency` (3),
  `interval` (`monthly|yearly`), `trial_days` (int, default 0), `max_vehicles` (int, nullable),
  `max_members` (int, nullable), `is_active` (bool), `sort` (int), timestamps.
- `subscriptions`: `owner_type`/`owner_id` (morph), `plan_id`, `status`
  (`trialing|pending_payment|active|canceled`), `trial_ends_at`, `current_period_start`,
  `current_period_end`, `canceled_at`, `payment_method` (nullable), timestamps.
  Índice `(owner_type, owner_id, id)`.
- `payments`: `subscription_id`, `amount`, `currency`, `exchange_rate` `decimal(14,4)`,
  `method`, `reference`, `paid_on` (date), `status` (`pending|approved|rejected`), `notes`,
  `reviewed_at`, `reviewed_by` (nullable → `users.id`), timestamps. Índice
  `(subscription_id, status)`.
- `amount_base` es **derivado** (`amount / exchange_rate`), no se guarda.

## Project Structure

```text
carcheck/
  app/Enums/{PlanInterval,PaymentMethod,PaymentStatus,SubscriptionStatus}.php
  app/Models/{Plan,Subscription,Payment}.php
  app/Services/Billing.php
  app/Http/Controllers/Api/BillingController.php, PaymentController.php
  app/Http/Resources/{PlanResource,SubscriptionResource,PaymentResource}.php
  app/Policies/PaymentPolicy.php
  app/Filament/Resources/Payments/*          # aprobar/rechazar en /admin
  database/migrations/*_create_plans_*, *_create_subscriptions_*, *_create_payments_*
  database/factories/{Plan,Subscription,Payment}Factory.php
  database/seeders/PlanSeeder.php
  tests/Feature/Billing/*
carcheck-app/
  src/api/{endpoints,types}.ts, src/stores/billing.ts
  src/pages/Billing.tsx
  e2e/billing.spec.ts
```

## Commands

Backend (`carcheck/`): `composer run test`, `php artisan test --filter=Billing`,
`vendor/bin/pint`, `php artisan migrate --seed`.
Frontend (`carcheck-app/`): `npm run typecheck`, `npm run test`, `npm run build`,
`npx playwright test`.

## Code Style

Laravel Pint por defecto; strings en español. Enums con `label()`. Enums de dominio persistidos como
string + cast a enum. En el cliente, `import type` y `Decimal` para montos.

## Testing Strategy

- **Backend:**
  - `SubscriptionTest`: primera suscripción con trial, segunda sin trial, plan inactivo, suscripción
    vigente duplicada → 422, cambio de plan, cancelación conserva acceso hasta el fin del periodo,
    permisos por rol de organización.
  - `PaymentTest`: reportar pago, comprobante, aprobación activa y extiende periodo, pago que no
    cubre el plan → 422, aprobación solo `super_admin`, rechazo con motivo, multimoneda con tasa.
  - `BillingStatusTest`: estado efectivo (prueba vencida, periodo vencido, cancelada con acceso).
- **Cliente:** store `billing`; E2E `billing.spec.ts` (registrar → plan → ver estado de prueba).
- Verificación: `php artisan test` + `pint`; `typecheck` + `test` + `build`.

## Boundaries

- **Siempre:** resolver el owner desde la petición y autorizar sobre él; derivar el estado efectivo;
  validar que el pago cubre el plan antes de aprobar; registrar quién y cuándo revisó.
- **Preguntar antes:** integrar pasarela real; prorrateo en cambios de plan; facturas fiscales;
  cobros automáticos por pasarela.
- **Nunca:** borrar el histórico de pagos/suscripciones; aprobar pagos sin rol `super_admin`;
  guardar `effective_status` en la BD; copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Un usuario nuevo arranca su prueba gratuita una vez y ve los días restantes.
- Al vencer la prueba, la suscripción figura vencida y sin acceso hasta que se apruebe un pago.
- Un pago aprobado activa la suscripción y extiende el periodo según el plan.
- Un pago insuficiente o sin rol `super_admin` no se aprueba.
- Un pago en VES con tasa correcta cubre un plan en USD; sin tasa, se rechaza.
- Un miembro simple ve la suscripción de su organización pero no puede cambiarla.
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. **Sin pasarela**: el cobro es manual por diseño; la aprobación humana es el "gateway".
2. **Historial, no fila única**: cada suscripción es un registro; la "actual" es la última. Permite
   auditar sin sobrescribir.
3. **Tasa explícita**: el pago guarda su moneda y la tasa usada; `amount_base` se deriva. Si la
   moneda coincide con la del plan, la tasa es 1.
4. **Sin prorrateo**: un cambio de plan aplica desde ya, sin devolución ni cobro proporcional.
5. **Límites informados, no bloqueantes en el MVP**: `max_vehicles`/`max_members` se exponen como
   uso vs límite; bloquear la creación de vehículos se decide cuando exista gating global.
