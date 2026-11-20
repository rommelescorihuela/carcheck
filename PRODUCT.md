# PRODUCT — CarCheck

## Qué es

SaaS de mantenimiento de vehículos para particulares y flotas pequeñas. Cada vehículo
tiene su historial completo —servicios, documentos, combustible y kilometraje— y el sistema
avisa antes de que algo venza.

## Para quién

- **Particular ordenado**: uno o dos autos, quiere dejar de perder papeles y fechas.
- **Familia / equipo chico**: hasta 5 vehículos compartidos, varias personas cargando datos.
- **Flota operativa**: reparto, construção, servicios; 25–75 vehículos y conductores
  que solo registran, sin tocar la configuración.

Escena real: oficina de galpón con luz de tubo, Tablet o notebook; y el móvil en la calle
cuando se carga combustible o se saca una foto del documento.

## Mecanismo único

El vehículo deja de ser una carpeta: **cada vencimiento tiene fecha y responsable**, y la
flota entera se ve en una sola tabla. Documentos y mantenimiento se avisan solos por
correo y notificación; el conductor registra sin poder cambiar la configuración.

## Superficies

| Superficie | Ruta | Estado |
|---|---|---|
| Panel de gestión (staff de plataforma) | `/admin` | Filament 5 |
| API para la PWA | `/api/*` (Sanctum) | activa |
| PWA del miembro | repo `carcheck-app` | React 19 |
| Landing pública | `/` | **esta entrega** |

## Hechos verificados (no inventar otros)

- Planes reales (`plans`): Básico 3,99 USD — 1 vehículo; Familiar 7,99 USD — 5 vehículos y 5
  personas; Flota 29,99 USD — 25 vehículos y 10 personas; Empresa 59,99 USD — 75 vehículos y
  25 personas. Todos mensuales, 14 días de prueba.
- Roles de organización: `owner`, `admin`, `member`, `driver`. Cualquier miembro registra
  combustible y mantenimiento; `owner`/`admin` gestionan el garaje.
- Documentos y mantenimiento generan recordatorios (`reminders:generate`).
- Avisos por correo y push (encolados) + aviso in-app inmediato.
- Registro web público: **no existe**. El alta la hace el equipo; la landing no debe prometer
  autoservicio.

## Lo que la landing no puede decir

- Que hay registro instantáneo o prueba sin intervención.
- Cifras de clientes, ahorros, reseñas o logos de empresas.
- Integraciones que no existen.
