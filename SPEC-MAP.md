# SPEC-MAP — CarCheck

SaaS multiusuario de mantenimiento de vehículos. Dos repos hermanos (patrón `app-gym`):

- `carcheck/` — backend Laravel 13 + panel Filament 5 (admin) + API.
- `carcheck-app/` — PWA React 19 + TS del usuario final (cliente de la API).

Este mapa es el índice de specs. Cada módulo tiene su `SPEC-<id>.md` en la raíz de `carcheck/`.

## Mapa de capacidades

| Módulo | Responsabilidad | Depende de |
|---|---|---|
| `identity` | Cuentas, sesiones, organizaciones y miembros, roles, invitaciones; propiedad de recursos por usuario u organización | — |
| `billing` | Planes, prueba gratuita, suscripción y pagos manuales (transferencia/Pago móvil/Zelle/Binance), multimoneda | `identity` |
| `vehicles` | Vehículos, odómetro, datos base (marca, modelo, año, placa, combustible) | `identity` |
| `maintenance` | Servicios, repuestos, costos, historial y periodicidad (km/tiempo) | `vehicles` |
| `fuel` | Cargas de combustible, consumo (km/l), costo por km | `vehicles` |
| `documents` | Documentos y vencimientos (seguro, revisión, impuestos, licencia) | `vehicles` |
| `notifications` | Infra de envío: email, push PWA (web-push) y bandeja in-app | `identity` |
| `reminders` | Recordatorios por km y/o fecha; usa `notifications` | `maintenance`, `documents`, `vehicles`, `notifications` |
| `reporting` | Gastos por periodo/vehículo, multimoneda | `maintenance`, `fuel`, `billing` |

**Orden de construcción:** `identity` → `billing`, `vehicles` → `maintenance`, `fuel`, `documents` → `notifications` → `reminders` → `reporting`.

## Decisiones fijadas

- **Modelo multiusuario:** ambos — cuenta individual con vehículos propios **y** organizaciones con miembros que comparten vehículos.
- **Producto:** SaaS público con registro y pago. **Cobro manual** (sin pasarela) + **multimoneda**.
- **Plataforma:** web responsive + PWA instalable.
- **Recordatorios:** email + push PWA (web-push) + bandeja in-app.
- **Tenancy:** no hay base por tenant; la propiedad se resuelve por `owner` (usuario u organización), al estilo `gym_id` de `app-gym`.
- **UI y seed data en español.**
