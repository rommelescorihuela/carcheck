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

Orden de construcción (ver [`../SPEC-MAP.md`](../SPEC-MAP.md)): `identity` → `vehicles`, `billing` →
`maintenance`, `fuel`, `documents` → `notifications` → `reminders` → `reporting`.
