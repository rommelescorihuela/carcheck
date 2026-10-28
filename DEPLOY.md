# Despliegue de CarCheck

CarCheck son dos artefactos independientes:

| Artefacto | Repo | Qué es |
|---|---|---|
| Backend | `carcheck/` | Laravel 13 + Filament 5: API `/api`, panel `/admin`, colas y scheduler |
| PWA | `carcheck-app/` | React 19 + Vite: sitio estático (`dist/`) que consume la API |

La PWA **no** se sirve desde Laravel: se publica como sitio estático (Nginx, Apache, CDN…)
apuntando a `VITE_API_URL`.

## Requisitos del servidor

- PHP 8.3+ con `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `gd` o `imagick`, `intl`, `zip`.
- PostgreSQL 14+.
- Composer 2 y Node.js 22+ **en el servidor de build** (o construir el frontend en CI).
- HTTPS en ambos dominios: la PWA, el service worker y el push lo exigen.

## Backend: primera instalación

```bash
cd /var/www/carcheck

composer install --no-dev --optimize-autoloader

cp .env.production.example .env
# Completar DB_*, APP_URL, CORS_ALLOWED_ORIGINS, MAIL_*, VAPID_* y las claves.
php artisan key:generate --force

php artisan migrate --force

# Datos mínimos de plataforma (NO usar `db:seed`, que crea usuarios demo).
php artisan db:seed --class=RolesSeeder --force
php artisan db:seed --class=MaintenanceTypeSeeder --force
php artisan db:seed --class=PlanSeeder --force

# Primer super administrador del panel.
php artisan admin:create admin@tudominio.com --name="Administrador"

php artisan storage:link
```

### Assets del panel

El panel necesita los assets publicados de Filament y el build de Vite:

```bash
npm ci
npm run build
php artisan filament:assets   # solo si faltan public/css|js/filament
```

### Cachés de producción

```bash
php artisan optimize
```

Tras cada despliegue que cambie configuración, rutas o vistas: `php artisan optimize:clear && php artisan optimize`.
Si se edita `.env`: `php artisan config:cache`.

## Backend: actualizaciones

```bash
cd /var/www/carcheck

php artisan down --retry=30        # opcional; mantiene la API en 503 unos segundos
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

**Antes de migrar, haz copia de la base de datos.** Las migraciones no se revierten solas y
pueden borrar columnas.

## Cola y scheduler (obligatorio)

Los avisos guardan siempre la fila in-app, pero **email y push se encolan**. Sin worker, los
correos y push no salen (quedan en la tabla `jobs`). El recordatorio diario tampoco se genera sin
el scheduler.

Worker con systemd (`/etc/systemd/system/carcheck-queue.service`):

```ini
[Unit]
Description=CarCheck queue worker
After=network.target postgresql.service

[Service]
User=www-data
WorkingDirectory=/var/www/carcheck
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
KillSignal=SIGTERM

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now carcheck-queue
```

Scheduler (crontab del usuario de la app):

```cron
* * * * * cd /var/www/carcheck && php artisan schedule:run >> /dev/null 2>&1
```

El schedule ejecuta `reminders:generate` a las 08:00 (`routes/console.php`).

Revisa fallos con:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush        # solo tras revisar
```

## PWA: build y publicación

```bash
cd /var/www/carcheck-app

cp .env.production.example .env.production
# Ajustar VITE_API_URL (https, sin barra final), p. ej. https://api.tudominio.com/api

npm ci
npm run build          # Vite carga .env.production y genera dist/
```

Publica **el contenido de `dist/`** en el dominio de la PWA. Nginx de referencia:

```nginx
server {
    listen 443 ssl;
    server_name app.tudominio.com;
    root /var/www/carcheck-app/dist;
    index index.html;

    # SPA: cualquier ruta desconocida vuelve al index.
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Assets con hash: caché larga.
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # El service worker debe revalidarse para recibir actualizaciones.
    location = /sw.js {
        add_header Cache-Control "no-cache";
    }

    location = /manifest.webmanifest {
        types { } default_type application/manifest+json;
        add_header Cache-Control "no-cache";
    }
}
```

La PWA se actualiza sola (`registerType: 'autoUpdate'`), pero un `sw.js` cacheado por el
navegador retrasa la actualización: mantén el `no-cache` de arriba.

## Variables que no deben fallar

| Variable | Valor en producción |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | URL pública de la API, `https` |
| `CORS_ALLOWED_ORIGINS` | origen exacto de la PWA (varios separados por coma) |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `database` (o Redis si se añade) |
| `VAPID_SUBJECT` | `https://…` o `mailto:…` del dominio |
| `MEDIA_DISK` | `public` (los documentos se sirven por `/storage`) |
| `VITE_API_URL` (PWA) | `https://api…/api`, sin barra final |

Genera las claves push una sola vez y guárdalas:

```bash
php artisan webpush:vapid
```

Si cambian las claves VAPID, los navegadores deben volver a suscribirse.

## Verificación después de desplegar

```bash
curl -fsS https://api.tudominio.com/up
php artisan about
php artisan queue:monitor database:default --max=100
```

En la PWA: registrar una cuenta, crear un vehículo, abrir `/reportes`, descargar el CSV y comprobar
que el panel `/admin` carga con el usuario `super_admin`.

## Rollback

1. `php artisan down`.
2. Volver al release anterior (`git checkout <tag>` o restaurar el directorio).
3. `composer install --no-dev --optimize-autoloader` y `npm ci && npm run build`.
4. Restaurar la copia de la base si la migración no es compatible hacia atrás.
5. `php artisan optimize && php artisan up`.

Para releases grandes, publica en directorios versionados (`releases/2026-10-28/`) y cambia el
symlink `current` de forma atómica.
