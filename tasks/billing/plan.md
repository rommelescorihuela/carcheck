# Implementation Plan: billing

## Overview

Planes, prueba gratuita, suscripción por propietario (usuario u organización) y pagos manuales con
aprobación de `super_admin`, más el panel Filament para revisarlos. Ver
[SPEC-billing](../../SPEC-billing.md).

## Architecture Decisions

- **Historial de suscripciones**: cada alta es un registro; la actual es la última del owner. No se
  sobrescribe, así queda auditoría.
- **Estado efectivo derivado** (`Billing::effectiveStatus`): el paso del tiempo no requiere jobs ni
  columnas que se desactualicen.
- **Owner polimórfico**: reutiliza la regla de propiedad de `identity` (usuario personal u
  organización con rol `owner`/`admin`).
- **Aprobación humana**: `super_admin` desde `/admin`; la API expone el mismo servicio que usa
  Filament para no duplicar reglas.
- **Multimoneda con tasa explícita**: `amount_base = amount / exchange_rate` cuando la moneda difiere
  de la del plan; se valida que cubra el precio antes de aprobar.
- **Sin prorrateo**: cambio de plan inmediato, documentado como límite del MVP.

## Task List

### Fase 0: Fundación
- [x] Task 1: Enums (`PlanInterval`, `SubscriptionStatus`, `PaymentMethod`, `PaymentStatus`), migraciones, modelos y factories
- [x] Task 2: `PlanSeeder` con los planes comerciales

### Fase 1: Dominio
- [x] Task 3: `Billing` (estado efectivo, acceso, suscribir, cambiar, cancelar) + tests
- [x] Task 4: Aprobación/rechazo de pagos + multimoneda + tests

### Fase 2: API
- [x] Task 5: `BillingController` (planes, suscripción) + recursos + rutas + tests
- [x] Task 6: `PaymentController` (reportar, comprobante, aprobar/rechazar) + policy + tests

### Fase 3: Admin
- [x] Task 7: `PaymentResource` en Filament (listar, ver comprobante, aprobar/rechazar) + test

### Fase 4: PWA
- [x] Task 8: Cliente + store `billing` + test del store
- [x] Task 9: Página `Billing` (plan actual, planes, pago con comprobante, historial)
- [x] Task 10: E2E `billing.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-billing.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Estado efectivo inconsistente entre API y panel | Alto | Un solo servicio `Billing`, ambos lo llaman |
| Aprobar pagos insuficientes | Alto | Validar `amount_base` contra el precio del plan; test explícito |
| Doble suscripción vigente | Medio | Guardar 422 si la actual da acceso; test |
| Reglas duplicadas en Filament | Medio | Acciones llaman a `Billing::approvePayment/rejectPayment` |
| Tasa mal interpretada | Medio | Documentar "unidades de moneda local por 1 USD" y test con VES |

## Task List Target

Tareas en [`todo.md`](./todo.md).
