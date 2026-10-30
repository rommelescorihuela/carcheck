# Despliegue de CarCheck

CarCheck son dos artefactos independientes:

| Artefacto | Repo | Qué es |
|---|---|---|
| Backend | `carcheck/` | Laravel 13 + Filament 5: API `/api`, panel `/admin`, colas y scheduler |
| PWA | `carcheck-app/` | React 19 + Vite: sitio estático (`dist/`) que consume la API |

La PWA **no** se sirve desde Laravel: se publica como sitio estático (Nginx, Apache, CDN…)
apuntando a `VITE_API_URL`.

## Requisitos del servidor

- PHP 8.3+ con `pdo_pgsql` **o** `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` o
  `imagick`, `intl`, `zip`, `curl` y `dom`.
- PostgreSQL 14+ **o** MySQL 8 / MariaDB 10.6+. El esquema y la suite completa se prueban en
  ambos motores.
- Composer 2 y Node.js 22+ **en el servidor de build**. En hosting compartido sin SSH se usan
  los ZIP ya construidos (ver [Hosting compartido](#hosting-compartido-cpanel-sin-root)).
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

## Hosting compartido (cPanel básico, sin root)

No hace falta acceso a Apache ni a la configuración del servidor: todo se resuelve con
`.htaccess` (cPanel permite `AllowOverride` de serie) y con las herramientas del panel. Tampoco
hace falta Terminal, Composer ni Node en el servidor: el ZIP `carcheck-backend-shared.zip` ya
incluye `vendor/` de producción y los assets del panel compilados.

| En un VPS | En cPanel básico |
|---|---|
| Nginx/Apache con root | `.htaccess` incluido en el ZIP; no tocas Apache |
| Worker con systemd | **Cron Jobs** cada 5 min con `queue:work --stop-when-empty` |
| `crontab -e` / Terminal | **Cron Jobs** del panel (una tarea temporal para instalar) |
| Composer y npm en el servidor | ZIP con `vendor/` y assets ya construidos |
| PostgreSQL | MySQL/MariaDB de cPanel (soportado y probado) |
| `sudo` para permisos | File Manager: `775` en `storage` y `bootstrap/cache` |

### 1. Dónde subir cada cosa

La aplicación va **fuera** de `public_html`; solo su carpeta `public/` se publica:

```text
/home/USUARIO/
├── carcheck/                 # backend completo (NO accesible por HTTP)
│   ├── app/ config/ database/ vendor/ …
│   ├── public/               # se copia al subdominio, no se sirve desde aquí
│   ├── storage/              # escritura obligatoria
│   └── .env
└── public_html/
    ├── api/                  # ← contenido de carcheck/public (document root de la API)
    └── …                     # la PWA, en el dominio principal o en app.tudominio.com
```

**Opción A — el panel deja elegir document root** (lo ideal): crea `api.tudominio.com` apuntando a
`/home/USUARIO/carcheck/public`. No copies nada.

**Opción B — cPanel básico con carpeta fija** (lo habitual): crea el subdominio
`api.tudominio.com` (carpeta `public_html/api`) y copia **el contenido** de `carcheck/public`
dentro de `public_html/api`. El `index.php` del ZIP compartido localiza la app automáticamente en
`/home/USUARIO/carcheck`; si la subes a otra ruta, edita la primera línea de `$appPath`.

No subas `carcheck/` dentro de `public_html`: quedarían expuestos `.env`, `storage/` y el código.
El ZIP incluye un `.htaccess` en la raíz de la app que deniega el acceso directo por si acaso.

### 2. Base de datos MySQL

En **Bases de datos MySQL** de cPanel crea la base y el usuario (cPanel añade el prefijo de la
cuenta) y asigna el usuario con **todos los privilegios**. Después, en `carcheck/.env`:

```dotenv
APP_URL=https://api.tudominio.com
CORS_ALLOWED_ORIGINS=https://tudominio.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=usuario_carcheck
DB_USERNAME=usuario_carcheck
DB_PASSWORD=la_clave_de_cpanel

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

Copia `env.production` a `carcheck/.env` y cambia también `MAIL_*`, `APP_KEY` (se genera en el
paso siguiente) y `VAPID_*`. Si el plan ofrece PostgreSQL, usa el bloque comentado de la plantilla.

### 3. Instalar sin Terminal (Cron Jobs)

Añade temporalmente estas variables al `.env` para crear el primer `super_admin`:

```dotenv
ADMIN_EMAIL=admin@tudominio.com
ADMIN_PASSWORD=una_clave_larga_y_unica
ADMIN_NAME=Administrador
```

En **Cron Jobs** crea una tarea “una vez al día” (se ejecutará en el siguiente minuto) con esta
línea, cambiando `USUARIO` y la ruta de PHP si tu host usa otra versión:

```bash
cd /home/USUARIO/carcheck && /opt/cpanel/ea-php83/root/usr/bin/php artisan key:generate --force && /opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --seed --force && ln -sfn /home/USUARIO/carcheck/storage/app/public /home/USUARIO/public_html/api/storage
```

Qué hace: genera `APP_KEY`, crea las tablas, siembra roles/tipos/planes, crea el administrador y
publica `storage` en el subdominio. En producción `migrate --seed` **no** crea los usuarios demo
(`admin@carcheck.test` / `demo@carcheck.test`): solo existen en local y tests.

Cuando termine:

1. Revisa `carcheck/storage/logs/laravel.log` y entra a `https://api.tudominio.com/admin`.
2. **Borra la tarea de cron.**
3. **Borra `ADMIN_PASSWORD` del `.env`** (deja `ADMIN_EMAIL` si quieres; no se usa si no hay
   contraseña).
4. Cambia la contraseña del administrador desde el panel.

Si el plan no ofrece Cron Jobs ni Terminal, no hay forma de ejecutar migraciones: pide al hosting
que active una de las dos. No importes un dump a mano salvo que sepas que el esquema está al día.

La ruta real de PHP se ve en **Select PHP Version** o con un `phpinfo()` temporal; suele ser
`/opt/cpanel/ea-php83/root/usr/bin/php` o `/usr/local/bin/php`.

### 4. Cola y recordatorios con cron

Cola cada 5 minutos:

```bash
*/5 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/USUARIO/carcheck/artisan queue:work --stop-when-empty --tries=3 --max-time=280 >> /home/USUARIO/carcheck/storage/logs/queue.log 2>&1
```

Scheduler cada minuto (si el plan lo permite):

```bash
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/USUARIO/carcheck/artisan schedule:run >> /home/USUARIO/carcheck/storage/logs/schedule.log 2>&1
```

Si el mínimo es de 5 minutos, añade una tarea diaria a las 08:00 con
`artisan reminders:generate`. Si no hay cron, pon `QUEUE_CONNECTION=sync`: el correo y el push
salen en la misma petición (más lento y sin reintentos).

### 5. Permisos y límites de PHP

```bash
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Nunca `777`. Los documentos aceptan hasta 10 MB y las fotos 5 MB. El ZIP compartido ya trae
`public/.user.ini` (se copia a `public_html/api/.user.ini`) con:

```ini
upload_max_filesize = 12M
post_max_size = 16M
memory_limit = 256M
max_execution_time = 120
```

Si el host no respeta `.user.ini`, cámbialo en **MultiPHP INI Editor** o **Select PHP Version →
Options**, y comprueba el valor real con un `phpinfo()` temporal.

### 6. La PWA

Sube **el contenido de `carcheck-pwa.zip`** al document root del dominio o subdominio de la app
(`public_html` o `public_html/app`). Ya incluye `.htaccess` con:

- fallback a `index.html` para `/flota`, `/vehiculos/12`…;
- `sw.js`, `manifest.webmanifest` y `config.js` sin caché;
- assets con caché larga.

La URL de la API vive en **`config.js`**, junto a `index.html`. Edítalo en el servidor con File
Manager y recarga: no hace falta recompilar.

```js
window.__CARCHECK_CONFIG__ = { apiUrl: 'https://api.tudominio.com/api' }
```

Para generar un paquete nuevo con la URL ya incluida (opcional, requiere Node en tu equipo):

```bash
SHARED_HOSTING=1 VITE_API_URL=https://api.tudominio.com/api SKIP_INSTALL=1 ./build.sh
```

Activa **AutoSSL** en ambos dominios: sin HTTPS no hay service worker ni push.

### 7. Actualizar sin Terminal

1. Copia la base de datos y `storage/app/public` desde cPanel.
2. Sube y extrae `carcheck-backend-shared.zip` sobre `carcheck/`. Sustituye todo **menos**
   `storage/` y `.env`: el ZIP no los incluye precisamente para no pisarlos.
3. Vuelve a copiar el contenido de `carcheck/public` a `public_html/api` (así se actualizan
   `index.php`, `.htaccess` y `public/build`).
4. Vuelve a subir `carcheck-pwa.zip` si cambió la PWA; si solo cambió el dominio de la API basta
   con editar `config.js`.
5. Ejecuta una tarea de cron temporal:
   `php artisan migrate --force && php artisan optimize:clear && php artisan optimize`.
   Bórrala al terminar.
6. Revisa `/up`, el panel y una descarga de CSV.

### Limitaciones reales de un plan compartido

- Sin Redis: cola y caché en base de datos.
- Sin worker persistente: la cola avanza con el cron; un pico grande tarda.
- Límites de CPU, memoria y `max_execution_time`: las exportaciones muy grandes pueden cortarse.
- Sin Git: cada actualización es subir el ZIP y ejecutar migraciones.
- El correo depende del SMTP del host o de un proveedor externo; el push, de las claves VAPID.

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
