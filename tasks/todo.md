# Tasks: identity (CarCheck)

Detalle de las tareas de `tasks/plan.md`. Convención: `[ ]` pendiente, `[x]` hecha.

## Task 1: Bootstrap del backend `carcheck/`

**Description:** Crear el proyecto Laravel 13 con Postgres, Sanctum, spatie/laravel-permission,
filament-shield y Filament 5, y definir los scripts de composer del workspace.

**Acceptance criteria:**
- [x] `composer run setup` instala, copia `.env`, genera key y migra (sin seed)
- [x] `composer run dev` y `composer run test` definidos (serve + queue + pail + vite → `test` = `config:clear + artisan test`)
- [x] `/up` responde 200; `/admin` muestra login de Filament
- [x] Test por defecto en verde con SQLite `:memory:`

**Verification:** `composer run test` · `php artisan about` · visitar `/admin`

**Dependencies:** None

**Files likely touched:** `composer.json`, `.env.example`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/sanctum.php`, `config/database.php`, `routes/api.php`, `routes/web.php`, `phpunit.xml`

**Estimated scope:** M

## Task 2: Bootstrap de la PWA `carcheck-app/`

**Description:** Scaffold Vite + React 19 + TS + Tailwind 4 + React Router + Zustand, Vitest,
Playwright y `vite-plugin-pwa`.

**Acceptance criteria:**
- [x] `npm run dev`, `npm run typecheck`, `npm run test`, `npm run build` funcionan
- [x] `.env.example` define `VITE_API_URL` (base sin barra final)
- [x] TS estricto con `verbatimModuleSyntax`, `noUnusedLocals`, `noUnusedParameters`
- [x] Rutas estáticas declaradas antes que las de parámetro en `src/router.tsx`

**Verification:** `npm run typecheck && npm run test && npm run build`

**Dependencies:** None

**Files likely touched:** `package.json`, `vite.config.ts`, `tsconfig.json`, `.env.example`, `index.html`, `src/main.tsx`, `src/router.tsx`, `playwright.config.ts`

**Estimated scope:** M

## Task 3: Esquema de datos de identity

**Description:** Migraciones de `organizations`, `organization_user`, `invitations` y columna
`currency` en `users`.

**Acceptance criteria:**
- [x] `organizations`: `name`, `slug` único, `owner_id` FK a `users`, `currency`, `timezone`, timestamps
- [x] `organization_user`: FK a organization y user, `role` enum (`owner|admin|member`), `joined_at`, único `(organization_id, user_id)`
- [x] `invitations`: `organization_id`, `email`, `role`, `token` único (hasheado), `expires_at`, `accepted_at`
- [x] `php artisan migrate:fresh` corre sin errores en Postgres y en SQLite

**Verification:** `php artisan migrate:fresh` · `php artisan test --filter=Migration`

**Dependencies:** Task 1

**Files likely touched:** `database/migrations/*_create_organizations_table.php`, `*_create_organization_user_table.php`, `*_create_invitations_table.php`, `*_add_currency_to_users_table.php`

**Estimated scope:** S

## Task 4: Modelos, factories y trait de propiedad

**Description:** `Organization`, `Invitation`, relaciones en `User`, `OrganizationPolicy` y el
trait/scope `OwnedByUserOrOrganization`.

**Acceptance criteria:**
- [x] `User` tiene `organizations()`, `ownedOrganizations()`, `invitations()`
- [x] `Organization` tiene `members()`, `owner()`, `invitations()`
- [x] El trait `OwnedByUserOrOrganization` expone un scope que filtra por usuario propietario o por org del usuario
- [x] Factories `OrganizationFactory`, `InvitationFactory` y helper para agregar miembros con rol
- [x] `OrganizationPolicy` permite ver/editar según rol (`owner`/`admin`/`member`)

**Verification:** `php artisan test --filter=OrganizationModelTest`

**Dependencies:** Task 3

**Files likely touched:** `app/Models/User.php`, `app/Models/Organization.php`, `app/Models/Invitation.php`, `app/Traits/OwnedByUserOrOrganization.php`, `app/Policies/OrganizationPolicy.php`, `database/factories/OrganizationFactory.php`, `database/factories/InvitationFactory.php`

**Estimated scope:** M

## Task 5: API de auth (register/login/logout/refresh/user)

**Description:** `AuthController` con el contrato de `app-gym`, rutas con throttles, `WelcomeEmail`
y tests.

**Acceptance criteria:**
- [x] `POST /api/register` (throttle `10,1`) crea usuario y devuelve `{ user, access_token, token_type: "Bearer" }`
- [x] `POST /api/login` (throttle `6,1`) valida credenciales y responde igual
- [x] `POST /api/logout` revoca el token actual; `POST /api/token/refresh` (throttle `30,1`) emite token nuevo
- [x] `GET /api/user` devuelve el usuario autenticado; `401` sin token
- [x] Credenciales inválidas → `422`; el email de bienvenida no rompe el registro si el SMTP falla (`rescue`)
- [x] `AuthTest` cubre los 5 endpoints

**Verification:** `php artisan test --filter=AuthTest`

**Dependencies:** Tasks 1, 3, 4

**Files likely touched:** `app/Http/Controllers/Api/AuthController.php`, `app/Mail/WelcomeEmail.php`, `routes/api.php`, `tests/Feature/Auth/AuthTest.php`

**Estimated scope:** M

## Task 6: Cliente API + store de sesión

**Description:** `client.ts` (Bearer + refresh en 401), `token.ts`, `endpoints.ts`, `types.ts` y
`stores/auth.ts` en `carcheck-app/`.

**Acceptance criteria:**
- [x] `client.ts` adjunta `Authorization: Bearer` y ante 401 llama a `/token/refresh` **una vez** y reintenta
- [x] `token.ts` guarda el token fuera del store persistido (memoria + `localStorage`)
- [x] `bootstrap()` valida sesión con `GET /user`; falla limpia y vuelve a `/login`
- [x] Los decimales del backend se tipan como `Decimal` (string), no `number`
- [x] Test de `client.ts` que simula el 401 → refresh → reintento

**Verification:** `npm run test`

**Dependencies:** Task 2

**Files likely touched:** `src/api/client.ts`, `src/api/token.ts`, `src/api/endpoints.ts`, `src/api/types.ts`, `src/stores/auth.ts`, `src/api/client.test.ts`

**Estimated scope:** M

## Task 7: Pantallas de login y registro

**Description:** Páginas de login y registro, `ProtectedRoute` y `AppShell` mínimo.

**Acceptance criteria:**
- [x] Registro y login funcionan contra el backend y persisten sesión
- [x] Errores por campo se muestran desde `ApiError.errors`
- [x] Ruta protegida redirige a `/login` sin token
- [x] Textos en español

**Verification:** `npm run typecheck && npm run test && npm run build` · prueba manual end-to-end

**Dependencies:** Task 6

**Files likely touched:** `src/pages/Login.tsx`, `src/pages/Register.tsx`, `src/components/ProtectedRoute.tsx`, `src/components/AppShell.tsx`, `src/router.tsx`

**Estimated scope:** M

## Checkpoint: Autenticación
- [x] Registro y login reales contra `composer run dev`
- [x] `composer run test`, `vendor/bin/pint`, `npm run test`, `npm run typecheck` en verde
- [ ] Revisar con la persona antes de seguir

## Task 8: API de organizaciones y miembros

**Description:** `OrganizationController` y `MemberController` con autorización por rol y tests de
aislamiento.

**Acceptance criteria:**
- [x] `POST /api/organizations` crea la org y al creador como `owner`
- [x] `GET /api/organizations` solo lista las del usuario; `GET .../{id}` requiere ser miembro
- [x] `GET/PATCH/DELETE .../{id}/members[/{userId}]` respetan `owner`/`admin`; no-miembro → `403`
- [x] `OrganizationMembershipTest` prueba que un miembro de dos orgs **no** ve datos cruzados

**Verification:** `php artisan test --filter=OrganizationMembershipTest`

**Dependencies:** Tasks 4, 5

**Files likely touched:** `app/Http/Controllers/Api/OrganizationController.php`, `app/Http/Controllers/Api/MemberController.php`, `routes/api.php`, `tests/Feature/Auth/OrganizationMembershipTest.php`

**Estimated scope:** M

## Task 9: UI de organizaciones

**Description:** Crear/listar organizaciones, selector de organización activa y pantalla de
miembros.

**Acceptance criteria:**
- [x] Onboarding permite crear la primera org tras el registro
- [x] Selector cambia la org activa y la UI refleja el ámbito personal vs organización
- [x] Pantalla de miembros lista roles; acciones visibles según rol

**Verification:** `npm run typecheck && npm run test && npm run build` · prueba manual

**Dependencies:** Task 7, Task 8

**Files likely touched:** `src/pages/Organizations.tsx`, `src/pages/OrganizationMembers.tsx`, `src/stores/organization.ts`, `src/router.tsx`

**Estimated scope:** M

## Task 10: Invitaciones por email

**Description:** `InvitationController` (crear/aceptar), `OrganizationInvitation` mail y tests de
expiración/reuso.

**Acceptance criteria:**
- [x] `POST /api/organizations/{id}/invitations` (rol ≥ `admin`) crea invitación y encola email
- [x] El `token` se guarda hasheado y es de un solo uso; expira a los 7 días
- [x] `POST /api/invitations/{token}/accept` agrega al miembro con el rol invitado
- [x] Token expirado/reutilizado → error; `InvitationTest` lo cubre

**Verification:** `php artisan test --filter=InvitationTest`

**Dependencies:** Task 8

**Files likely touched:** `app/Http/Controllers/Api/InvitationController.php`, `app/Mail/OrganizationInvitation.php`, `routes/api.php`, `tests/Feature/Auth/InvitationTest.php`

**Estimated scope:** M

## Checkpoint: Organizaciones
- [x] Flujo crear org → invitar → aceptar funcional end-to-end
- [x] Aislamiento entre organizaciones verificado en tests

## Task 11: Panel Filament `/admin` + roles de plataforma

**Description:** `AdminPanelProvider`, recursos `Organization`/`User`, roles de plataforma vía
Shield y seeder.

**Acceptance criteria:**
- [x] `/admin` accesible solo para `super_admin`; recursos listan orgs/usuarios sin exponer datos sensibles
- [x] `php artisan shield:generate --all --panel=admin` genera las policies sin errores
- [x] Seeder crea `super_admin` y `panel_user` (textos en español)

**Verification:** `php artisan test --filter=AdminAccessTest` · visita manual a `/admin`

**Dependencies:** Tasks 3, 4

**Files likely touched:** `app/Filament/Providers/AdminPanelProvider.php`, `app/Filament/Resources/OrganizationResource.php`, `app/Filament/Resources/UserResource.php`, `database/seeders/RolesSeeder.php`

**Estimated scope:** M

## Task 12: PWA instalable

**Description:** Manifest, service worker y prompt de instalación (sin push).

**Acceptance criteria:**
- [x] `vite-plugin-pwa` genera manifest y service worker en `npm run build`
- [x] La app es instalable y arranca offline con un shell cacheado
- [x] Íconos en `public/`; nombre y colores en español

**Verification:** `npm run build` · build servido localmente e instalación manual

**Dependencies:** Task 2

**Files likely touched:** `vite.config.ts`, `public/manifest.webmanifest`, `public/icons/*`, `src/main.tsx`

**Estimated scope:** S

## Task 13: E2E Playwright del flujo completo

**Description:** Un spec que cubre registro → crear org → invitar → aceptar.

**Acceptance criteria:**
- [x] `npx playwright test` levanta backend y frontend y pasa en verde
- [x] El spec cubre los criterios de éxito de `SPEC-identity.md`
- [x] Se documenta cómo ejecutarlo (`playwright.config.ts`) si requiere Postgres sembrado

**Verification:** `npx playwright test`

**Dependencies:** Tasks 8, 9, 10

**Files likely touched:** `e2e/identity.spec.ts`, `playwright.config.ts`

**Estimated scope:** M

## Checkpoint: Módulo completo
- [x] Todos los criterios de éxito de `SPEC-identity.md` cumplidos
- [ ] Listo para revisión antes de empezar `billing`/`vehicles`
