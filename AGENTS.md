# AGENTS.md — CarCheck (backend)

SaaS multiusuario de mantenimiento de vehículos. **Laravel 13 + Filament 5 + PostgreSQL**;
API con Sanctum para la PWA `../carcheck-app` (patrón `app-gym` / `app-gym-member`). Textos de
UI, comentarios y seed data **en español**.

## Documentación del proyecto

- `SPEC-MAP.md` — mapa de capacidades (índice de specs y orden de construcción).
- `SPEC-<módulo>.md` — contrato de cada módulo (todos los del mapa están cerrados).
- `tasks/<módulo>/plan.md` + `todo.md` — plan y tareas del módulo. Índice en `tasks/README.md`.
- `DEPLOY.md` — despliegue en producción, cola, scheduler y PWA. Plantilla: `.env.production.example`.

## Comandos

```bash
composer run setup         # install + .env + key:generate + migrate --force + npm build (sin seed)
composer run dev           # serve + queue:listen + pail + vite (concurrently)
composer run test          # config:clear + php artisan test
php artisan test --filter=AuthTest            # una clase
php artisan test --filter=test_user_can_login # un método
vendor/bin/pint            # Laravel Pint, preset por defecto (sin pint.json)
php artisan migrate --seed                                     # datos demo
php artisan reminders:generate                                 # recordatorios de documentos y mantenimiento
php artisan admin:create admin@dominio.com --name="Admin"      # super_admin de producción (pide contraseña)
php artisan shield:generate --all --panel=admin                # tras añadir recursos Filament
php artisan optimize:clear                                     # caché de Filament
```

## Base de datos

- **Dev y prod: PostgreSQL** (db `carcheck`, `postgres`/`postgres` en local). `.env.example` trae `DB_CONNECTION=pgsql`.
- **Tests: SQLite `:memory:`** y `BCRYPT_ROUNDS=4` (fijado en `phpunit.xml`).
- `php artisan migrate --seed` crea `admin@carcheck.test` / `password` (super_admin) y `demo@carcheck.test`.
- **Correo y push**: dev usa `MAIL_MAILER=log` (no envía de verdad). En producción, email y push se **encolan** (`QUEUE_CONNECTION=database`): hace falta un `queue:work` supervisado y el scheduler (`DEPLOY.md`). El aviso in-app se guarda de inmediato.
- **Usuarios con soft delete**: `DELETE /api/account` borra la cuenta salvo que el usuario sea `owner` de alguna organización (422 hasta traspasarla). Los tokens expiran a los 30 días (`config/sanctum.php`).

## CI

GitHub Actions (`.github/workflows/tests.yml`): `composer install` → `php artisan test` → `vendor/bin/pint --test`.


## Arquitectura

- **Auth API:** Sanctum Bearer. `POST /api/register|login|logout`, `POST /api/token/refresh`,
  `GET /api/user`; throttles en register/login/refresh. El cliente reintenta el 401 una sola vez.
- **Tenancy:** no hay DB por tenant. La propiedad se resuelve por `owner_type`/`owner_id`
  (morph a `User` u `Organization`) con el trait `OwnedByUserOrOrganization`. Los roles de
  organización son un enum en el pivot `organization_user` (`owner|admin|member`); Spatie +
  Shield se reservan a los roles de plataforma del panel `/admin`.
- **Panel:** Filament 5 en `/admin` (`app/Filament/`, autodescubierto por `AdminPanelProvider`).

## Convenciones de Filament 5

- Usar `Schema` (no `Form`) en `form()`; `Schemas\Components\Section` (no `Forms\...`).
- Acciones desde `Filament\Actions` (no `Filament\Tables\Actions`).
- `getNavigationGroup()`, `getNavigationIcon()`, `getView()` como **métodos**, no propiedades.

## Gotchas

- **No copiar código de `openGym/`** (AGPL-3.0); solo referencia de UX/algoritmos.
- `public/build/`, `public/css/filament/` y `public/js/filament/` son **generados** — no editar.
  Tras tocar `resources/css|js` ejecutar `npm run build` (o tener `npm run dev`).
- Las rutas con `{param}` van **después** de las estáticas.
- Tras añadir recursos de Filament: `php artisan shield:generate --all --panel=admin` y `optimize:clear`.
- `shield:generate` **no debe regenerar** `app/Policies/OrganizationPolicy.php`: es de dominio (roles de
  organización) y `OrganizationResource` está en `resources.exclude` de `config/filament-shield.php`.
  Si la sobrescribe, se rompe la autorización de la API (`OrganizationMembershipTest` falla).
- Solo `super_admin` entra a `/admin` (`User::canAccessPanel`); Shield define el bypass por Gate
  (`super_admin.define_via_gate = true`). El seeder crea `super_admin` y `panel_user`.
