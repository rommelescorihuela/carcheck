# Tasks

Planes y tareas por módulo. Todos los módulos del [mapa](../SPEC-MAP.md) están cerrados.

| Módulo | Estado | Plan |
|---|---|---|
| `identity` | ✅ cerrado | [`identity/plan.md`](./identity/plan.md) |
| `vehicles` | ✅ cerrado | [`vehicles/plan.md`](./vehicles/plan.md) |
| `maintenance` | ✅ cerrado | [`maintenance/plan.md`](./maintenance/plan.md) |
| `fuel` | ✅ cerrado | [`fuel/plan.md`](./fuel/plan.md) |
| `documents` | ✅ cerrado | [`documents/plan.md`](./documents/plan.md) |
| `billing` | ✅ cerrado | [`billing/plan.md`](./billing/plan.md) |
| `notifications` | ✅ cerrado | [`notifications/plan.md`](./notifications/plan.md) |
| `reminders` | ✅ cerrado | [`reminders/plan.md`](./reminders/plan.md) |
| `reporting` | ✅ cerrado | [`reporting/plan.md`](./reporting/plan.md) |
| `fleets` | ✅ cerrado | [`fleets/plan.md`](./fleets/plan.md) |

Orden de construcción (ver [`../SPEC-MAP.md`](../SPEC-MAP.md)): `identity` → `vehicles`, `billing` →
`maintenance`, `fuel`, `documents` → `notifications` → `reminders` → `reporting` → `fleets`.

## Extras de cierre (post-mapa)

- Catálogo de planes editable en `/admin` (`PlanResource`, sin borrado: se desactiva).
- **Flotas**: rol `driver` con atribución, alta masiva por CSV (`POST /api/vehicles/import`) y vista
  consolidada (`GET /api/fleet/summary`). Ver [`../SPEC-fleets.md`](../SPEC-fleets.md).
- Exportación CSV del informe (`GET /api/reports/expenses/export`) con descarga autenticada en la PWA.
- Email y push encolados (`SendPushNotification`, `Mail::queue`); el aviso in-app sigue inmediato.
- `admin:create` para el primer `super_admin` de producción.
- Despliegue documentado en [`../DEPLOY.md`](../DEPLOY.md) con `.env.production.example`.
