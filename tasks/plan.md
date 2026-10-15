# Implementation Plan: identity (CarCheck)

## Overview

Primer módulo del [SPEC-MAP](../SPEC-MAP.md): cuentas, sesiones, organizaciones con miembros,
roles, invitaciones y la regla de propiedad de recursos (usuario u organización). Se entrega en
dos repos — `carcheck/` (Laravel 13 + Filament + API) y `carcheck-app/` (PWA React) — siguiendo
el patrón `app-gym` / `app-gym-member`. El resultado deja el resto de módulos construibles.

## Architecture Decisions

- **Sanctum Bearer + refresh**: mismos endpoints, throttles y forma de respuesta que `app-gym`; el cliente reintenta el 401 una sola vez.
- **Propiedad por morph `owner_type`/`owner_id`** con un trait `OwnedByUserOrOrganization` (equivalente a `BelongsToTenant` de `app-gym`), en lugar de DB por tenant.
- **Roles de organización = enum en el pivot `organization_user`** (`owner|admin|member`); Spatie + Shield se reservan a los roles de plataforma del panel `/admin`.
- **Sin seed en `composer run setup`**; los datos demo van en `migrate --seed`, con textos en español.
- **PWA** con `vite-plugin-pwa`; web-push queda **fuera** de este módulo (pertenece a `notifications`).

## Task List

### Fase 0: Bootstrap
- [x] Task 1: Bootstrap del backend `carcheck/`
- [x] Task 2: Bootstrap de la PWA `carcheck-app/`

### Fase 1: Autenticación (vertical)
- [x] Task 3: Esquema de datos de identity
- [x] Task 4: Modelos, factories y trait de propiedad
- [x] Task 5: API de auth (register/login/logout/refresh/user)
- [x] Task 6: Cliente API + store de sesión
- [x] Task 7: Pantallas de login y registro

### Checkpoint: Autenticación
- [x] Registro y login end-to-end contra el backend real
- [x] `composer run test`, `npm run test`, `composer run pint`

### Fase 2: Organizaciones
- [x] Task 8: API de organizaciones y miembros
- [x] Task 9: UI de organizaciones (crear/listar, selector activo)
- [x] Task 10: Invitaciones por email

### Checkpoint: Organizaciones
- [x] Crear org → invitar → aceptar, con aislamiento entre orgs verificado

### Fase 3: Panel y PWA
- [x] Task 11: Panel Filament `/admin` + roles de plataforma
- [x] Task 12: PWA instalable (manifest + service worker)
- [x] Task 13: E2E Playwright del flujo completo

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-identity.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| El "modo ambos" (personal + org) complica la autorización | Alto | Trait + scope únicos desde el inicio; tests de aislamiento cruzado obligatorios |
| Desincronización del contrato API/cliente | Medio | Tipos del contrato en `carcheck-app/src/api/types.ts`; test de `client.ts` con el refresh 401 |
| `vite-plugin-pwa` + push trae ruido prematuro | Bajo | Push fuera de alcance; solo manifest + service worker offline |
| Bootstrap con Postgres no disponible en el entorno | Medio | Dev con Postgres; tests con SQLite `:memory:` (como `app-gym`) |

## Open Questions

Pendientes de `SPEC-identity.md` §Open Questions (owner múltiple, expiración de tokens Sanctum,
`currency` org vs usuario, borrado/traspaso de cuenta). No bloquean las Fases 0–1.

## Task List Target

Las tareas viven en `tasks/todo.md` (checklist markdown, convención por defecto).
