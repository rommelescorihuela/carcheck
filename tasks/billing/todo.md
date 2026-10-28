# Tasks: billing

Detalle de [`plan.md`](./plan.md). `[ ]` pendiente, `[x]` hecha.

## Task 1: Enums, migraciones, modelos y factories

**Acceptance criteria:**
- [x] `PlanInterval` (`monthly|yearly`) con `label()`; `SubscriptionStatus`
      (`trialing|pending_payment|active|canceled`); `PaymentMethod`
      (`transfer|mobile_payment|zelle|binance|cash|other`) con `label()`; `PaymentStatus`
      (`pending|approved|rejected`)
- [x] Migraciones `plans`, `subscriptions` (owner morph + índice), `payments`
- [x] Modelos con casts de enum/fecha/decimal y relaciones; `Payment::amount_base` accessor
- [x] Factories `PlanFactory` (con `yearly()`, `inactive()`, `withoutTrial()`),
      `SubscriptionFactory` (con `trialing()`, `active()`, `canceled()`), `PaymentFactory`

**Verification:** `php artisan migrate:fresh` · `php artisan test --filter=BillingModelTest`

**Dependencies:** None · **Scope:** M

## Task 2: Seeder de planes

**Acceptance criteria:**
- [x] `PlanSeeder` idempotente (`updateOrCreate` por `code`) con Básico, Familiar y Taller en USD
- [x] Registrado en `DatabaseSeeder`
- [x] Test de que existen y están activos

**Verification:** `php artisan migrate:fresh --seed` · `php artisan test --filter=PlanSeederTest`

**Dependencies:** Task 1 · **Scope:** S

## Task 3: Dominio de suscripción

**Acceptance criteria:**
- [x] `Billing::effectiveStatus()` (prueba vencida, periodo vencido, cancelada)
- [x] `Billing::hasAccess()` incluye cancelada hasta `current_period_end`
- [x] `Billing::subscribe()`: trial solo la primera vez; 422 si ya hay una vigente; sin trial nace
      `pending_payment`
- [x] `Billing::changePlan()` y `cancel()`
- [x] `BillingStatusTest` + `SubscriptionTest` (dominio)

**Verification:** `php artisan test --filter=Billing`

**Dependencies:** Tasks 1, 2 · **Scope:** M

## Task 4: Pagos y multimoneda

**Acceptance criteria:**
- [x] `Payment::amount_base` = `amount` si la moneda es la del plan, si no `amount / exchange_rate`
- [x] `Billing::approvePayment()`: solo `pending`, valida cobertura, activa y extiende periodo
      (mensual/anual), registra `reviewed_at`/`reviewed_by`
- [x] `Billing::rejectPayment()` guarda motivo y `reviewed_at`
- [x] Tests: VES con tasa, pago insuficiente, pago en plan anual, doble aprobación

**Verification:** `php artisan test --filter=Payment`

**Dependencies:** Task 3 · **Scope:** M

## Task 5: API de planes y suscripción

**Acceptance criteria:**
- [x] `GET /api/billing/plans` (solo activos, ordenados)
- [x] `GET/POST/PATCH/DELETE /api/billing/subscription` con `organization_id` opcional
- [x] `PlanResource`, `SubscriptionResource` con estado efectivo, días y uso
- [x] Permisos: miembro ve, `owner`/`admin` gestiona; ámbito personal por defecto
- [x] `SubscriptionTest` (API): trial, duplicada, cambio, cancelación, organización

**Verification:** `php artisan test --filter=SubscriptionTest`

**Dependencies:** Tasks 3, 4 · **Scope:** M

## Task 6: API de pagos

**Acceptance criteria:**
- [x] `GET/POST /api/billing/payments`, `POST/DELETE /api/billing/payments/{payment}/proof`
- [x] `POST .../approve` y `.../reject` solo `super_admin` (403 al resto)
- [x] `PaymentPolicy` para ver/gestionar según el owner
- [x] `PaymentTest` cubre todo, incluido comprobante y rechazo con motivo

**Verification:** `php artisan test --filter=PaymentTest`

**Dependencies:** Task 5 · **Scope:** M

## Task 7: Panel de pagos (Filament)

**Acceptance criteria:**
- [x] `PaymentResource` en `/admin`: listado con owner, plan, monto, método, estado y fecha
- [x] Acciones `Aprobar` y `Rechazar` (con motivo) que llaman a `Billing`
- [x] Enlace al comprobante; sin crear/editar pagos (los crea el usuario)
- [x] `AdminAccessTest` comprueba `/admin/payments`

**Verification:** `php artisan test --filter=AdminAccessTest`

**Dependencies:** Task 6 · **Scope:** M

## Checkpoint: API + admin
- [x] Suscripción y pagos operables de punta a punta; aprobación solo de `super_admin`

## Task 8: Cliente + store `billing`

**Acceptance criteria:**
- [x] `types.ts`: `Plan`, `Subscription`, `SubscriptionOverview`, `Payment`
- [x] `endpoints.ts`: planes, suscripción (GET/POST/PATCH/DELETE), pagos, comprobante
- [x] `stores/billing.ts` con `load`, `subscribe`, `changePlan`, `cancel`, `reportPayment`
- [x] Test del store

**Verification:** `npm run typecheck && npm run test`

**Dependencies:** Task 6 · **Scope:** M

## Task 9: Página `Billing`

**Acceptance criteria:**
- [x] Plan actual con estado, días restantes y uso vs límites
- [x] Lista de planes con botón para elegir/cambiar
- [x] Formulario de pago (monto, moneda, tasa, método, referencia, fecha, comprobante)
- [x] Historial de pagos con estado y comprobante
- [x] Ruta `/facturacion` y enlace en la navegación; textos en español

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** Task 8 · **Scope:** M

## Task 10: E2E `billing.spec.ts`

**Acceptance criteria:**
- [x] `registro → facturación → elegir plan → ver prueba gratuita con días restantes`
- [x] `npx playwright test` en verde

**Verification:** `npx playwright test`

**Dependencies:** Tasks 8, 9 · **Scope:** M

## Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-billing.md` cumplidos
- [x] Listo para `notifications`
