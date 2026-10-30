# Spec: identity

Módulo base de CarCheck. Es el primer módulo del [SPEC-MAP](./SPEC-MAP.md) y del que
dependen todos los demás.

## Objective

Permitir que una persona se registre y se autentique, y definir la **propiedad** de los
recursos del SaaS: cada vehículo pertenece a un **usuario** (uso personal) o a una
**organización** (familia, taller o empresa) con varios miembros. Cubre el modelo "ambos":
cuentas individuales y organizaciones que comparten vehículos.

Historias / criterios de aceptación:

- Como visitante me registro con email/contraseña y quedo autenticado (Bearer) en backend y PWA.
- Como usuario inicio y cierro sesión, y renuevo mi token sin volver a escribir credenciales.
- Como usuario creo una organización y quedo como `owner`.
- Como `owner`/`admin` invito por email y asigno un rol al nuevo miembro (`admin`, `member` o
  `driver`); solo el `owner` nombra administradores y cambia el propietario.
- Un usuario puede pertenecer a **varias** organizaciones sin mezclar datos.
- El sistema resuelve la pregunta "¿puede este usuario acceder a este recurso?" tanto por
  propiedad personal como por pertenencia a la organización dueña.

## Tech Stack

- **Backend:** Laravel 13 (PHP ^8.3), PostgreSQL (dev y prod). Tests con SQLite `:memory:`.
- **Auth API:** Laravel Sanctum ^4 — tokens personales Bearer + refresh (mismo contrato que `app-gym`).
- **Admin:** Filament 5 en `/admin`; RBAC de panel con `spatie/laravel-permission` ^6 + `bezhansalleh/filament-shield` ^4.
- **Cliente:** `carcheck-app/` — React 19 + TS + Vite + Zustand, PWA instalable; consume la API.
- **Sin** DB por tenant: la propiedad se resuelve por `owner` (usuario u organización).

## Contrato de autenticación (replicar `app-gym`)

| Método | Ruta | Auth | Throttle |
|---|---|---|---|
| POST | `/api/register` | — | `10,1` |
| POST | `/api/login` | — | `6,1` |
| POST | `/api/logout` | Bearer | — |
| POST | `/api/token/refresh` | Bearer | `30,1` |
| GET | `/api/user` | Bearer | — |
| DELETE | `/api/account` | Bearer | — |

- Login/registro devuelven `{ user, access_token, token_type: "Bearer" }`.
- El cliente envía `Authorization: Bearer <token>`; ante `401` llama **una vez** a
  `POST /api/token/refresh` y reintenta (ver `app-gym-member` `src/api/client.ts`).
- El token se guarda fuera del store persistido (memoria + `localStorage`).

### Organizaciones y miembros

| Método | Ruta | Rol mínimo |
|---|---|---|
| GET | `/api/organizations` | autenticado (las propias) |
| POST | `/api/organizations` | autenticado (crea y pasa a `owner`) |
| GET | `/api/organizations/{id}` | miembro |
| GET | `/api/organizations/{id}/members` | miembro |
| POST | `/api/organizations/{id}/invitations` | `owner`/`admin` |
| POST | `/api/invitations/{token}/accept` | autenticado |
| PATCH | `/api/organizations/{id}/members/{userId}` | `owner` (cambia rol) |
| DELETE | `/api/organizations/{id}/members/{userId}` | `owner`/`admin` |

## Modelo de datos

- `User` — `name`, `email` (único), `password`; `currency` (preferencia, para `reporting`).
- `Organization` — `name`, `slug`, `owner_id` → `users.id`, `currency`, `timezone`.
- `organization_user` (pivot) — `organization_id`, `user_id`, `role` enum (`owner|admin|member|driver`), `joined_at`. Índice único `(organization_id, user_id)`.
- `Invitation` — `organization_id`, `email`, `role`, `token` (único, hasheado), `expires_at`, `accepted_at`.
- **Roles de plataforma** (Filament, vía Shield): `super_admin`, `panel_user`. **Roles de organización** = enum en el pivot (NO usar equipos de Spatie; evita duplicar la tenancy).

### Propiedad de recursos (contrato para `vehicles` y siguientes)

- Todo recurso con dueño lleva `owner_type` + `owner_id` (morph a `User` u `Organization`).
- Acceso permitido si: `owner_type = User && owner_id = auth()->id()` **o** `owner_type = Organization` y el usuario es miembro.
- Implementar con un trait `OwnedByUserOrOrganization` + un scope reutilizable (equivalente a `BelongsToTenant` de `app-gym`). El módulo `vehicles` es el primero en consumirlo.
- `vehicles` spec decide el detalle de almacenamiento; esta spec fija la **regla de acceso**.

## Commands

Backend (workdir `carcheck/`):

```bash
composer run setup            # install + .env + key:generate + migrate (sin seed)
composer run dev              # serve + queue:listen + pail + vite (concurrently)
composer run test             # config:clear + php artisan test
php artisan test --filter=AuthTest                 # una clase
php artisan test --filter=test_user_can_login      # un método
vendor/bin/pint                                    # formatter (preset Laravel por defecto)
php artisan migrate --seed                         # datos demo
php artisan shield:generate --all --panel=admin    # tras añadir recursos al panel
php artisan optimize:clear                         # limpiar caché de Filament
```

Cliente (workdir `carcheck-app/`):

```bash
cp .env.example .env          # VITE_API_URL (base sin barra final)
npm install
npm run dev                   # Vite (requiere backend en marcha)
npm run typecheck             # tsc -b --noEmit
npm run test                  # Vitest en modo run
npm run build                 # tsc -b && vite build
```

## Project Structure

```text
carcheck/                      # repo backend (Laravel 13)
  app/Http/Controllers/Api/    # AuthController, OrganizationController, MemberController, InvitationController
  app/Models/                  # User, Organization, Invitation
  app/Traits/                  # OwnedByUserOrOrganization (propiedad)
  app/Policies/                # OrganizationPolicy, (resto vía Shield)
  app/Filament/                # panel /admin (Organizations, Users)
  database/migrations/         # users + organizations + organization_user + invitations
  database/factories/          # User, Organization (+ helper de miembro)
  routes/api.php               # rutas de identity
  routes/web.php               # landing + registro web
  tests/Feature/Auth/          # AuthTest, OrganizationMembershipTest, InvitationTest

carcheck-app/                  # repo PWA (React 19 + TS)
  src/api/                     # client.ts, token.ts, endpoints.ts, types.ts
  src/stores/auth.ts           # Zustand + persist
  src/pages/                   # login, registro, onboarding de organización
  e2e/                         # Playwright
```

## Code Style

Laravel Pint, preset por defecto (sin `pint.json`). Strings de cara al usuario **en español**.
En el cliente, TS estricto con `verbatimModuleSyntax` (`import type`), `noUnusedLocals`,
`noUnusedParameters`.

```php
// PHP: controladores delgados, validación inline, respuesta JSON explícita.
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $organization = $request->user()->ownedOrganizations()->create($validated);

    return response()->json(['organization' => $organization], 201);
}
```

```ts
// TS: tipos del contrato en src/api/types.ts; no reimplementar el refresh en pantallas.
import type { ApiError } from './types';
```

## Testing Strategy

- **Backend:** `php artisan test` (PHPUnit), SQLite `:memory:`. Feature tests por endpoint con
  `Sanctum::actingAs()` o token real; `RefreshDatabase`.
  - `AuthTest` — registro, login, logout, refresh, `GET /user`.
  - `OrganizationMembershipTest` — crear org, roles, aislamiento entre orgs, no-miembro → 403.
  - `InvitationTest` — invitar, aceptar, expirada/reutilizada → error.
- **Cliente:** Vitest (happy-dom) para `client.ts`/`endpoints.ts`; Playwright en `e2e/` para
  login y onboarding.
- **Antes de dar por terminado:** `composer run test && vendor/bin/pint` (backend);
  `npm run typecheck && npm run test && npm run build` (cliente).

## Boundaries

- **Siempre:** validar y autorizar cada ruta; hashear tokens de invitación; throttlear
  login/register/refresh; respetar el aislamiento por organización en los tests.
- **Preguntar antes:** cambiar el contrato de auth; añadir dependencias; tocar migraciones ya
  mergeadas; definir la política de expiración de tokens.
- **Nunca:** guardar tokens en claro; devolver `password`/tokens de otras cuentas; usar equipos
  de Spatie para roles de organización; copiar código de `openGym/` (AGPL-3.0).

## Success Criteria

- Un visitante puede registrarse y recibir un Bearer funcional contra `GET /api/user`.
- Un usuario autenticado cierra sesión (token revocado) y renueva sesión vía `/api/token/refresh`.
- Un usuario crea una organización y queda como `owner`; invita a otro; el invitado acepta y
  accede solo a los recursos de esa organización.
- Un usuario miembro de dos organizaciones **no** ve datos cruzados.
- `composer run test` y `npm run test` en verde; `vendor/bin/pint` y `npm run typecheck` sin errores.

## Decisiones resueltas

1. Un usuario **puede ser `owner` de varias organizaciones**.
2. **Tokens Sanctum expiran a los 30 días** (`config/sanctum.php` `expiration = 43200` minutos); el refresh emite uno nuevo y revoca el anterior.
3. **`Organization.currency` manda** en el ámbito organización; `User.currency` es la preferencia personal. `reporting`/`billing` convierten a la moneda del ámbito.
4. **Eliminar cuenta = soft delete** (`users.deleted_at`) + revocación de tokens y baja de membresías. Si el usuario es `owner` de alguna organización, se **bloquea** (422) hasta traspasarla o eliminarla. La `email` sigue siendo única: un correo dado de baja no puede re-registrarse (revisar si se necesita permitirlo).
