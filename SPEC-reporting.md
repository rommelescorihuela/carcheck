# Spec: reporting

Gastos de los vehículos por periodo, vehículo y moneda, más el gasto en la suscripción de CarCheck.
Consume `maintenance`, `fuel` y `billing`. Ver [SPEC-MAP](./SPEC-MAP.md).

## Objective

Que el usuario sepa **cuánto le cuesta su flota** sin exportar nada a mano: cuánto gastó en
mantenimiento y combustible en un periodo, desglosado por vehículo y por mes, y cuánto paga por
CarCheck. Sirve para decidir (¿conviene este carro?, ¿subió el combustible?) y para rendir cuentas.

Historias / criterios de aceptación:

- Elijo un periodo (desde/hasta) y veo el **total gastado** en mantenimiento y combustible.
- Si hay gastos en varias monedas, veo un total **por moneda**; nunca se suman monedas distintas.
- Veo el desglose **por vehículo** y **por mes** del periodo.
- Veo aparte lo pagado por la **suscripción** de CarCheck (solo pagos aprobados).
- Puedo limitar el informe a un vehículo concreto.
- El ámbito sigue la organización activa, igual que en `vehicles` y `reminders`.

## Tech Stack

- Laravel 13 + PostgreSQL; API Sanctum; PWA React 19 + TS.
- Sin librería de gráficos: tablas y tarjetas (el informe se lee, no se explora).

## Autorización

- Mismo criterio de ámbito que `vehicles`: `organization_id` (debe ser miembro; si no, 403) o
  `scope=personal`; sin parámetros, todo lo accesible (propio + organizaciones del usuario).
- `vehicle_id` debe ser accesible; si no, **404** (no se revela su existencia).
- La suscripción que se informa es la del propietario resuelto (usuario u organización), no la de
  otros.

## Contrato de API

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/reports/expenses` | Informe de gastos del periodo |

Parámetros:

- `from`, `to`: fechas `Y-m-d`. Por defecto, del **1 de enero del año actual** a **hoy**. `to` no
  puede ser anterior a `from`.
- `organization_id` o `scope=personal` (ámbito).
- `vehicle_id` (opcional): limita los gastos a ese vehículo. Los pagos de suscripción son del
  propietario, no del vehículo, así que el bloque `billing` no se filtra por vehículo.

### Respuesta

```json
{
  "data": {
    "period": { "from": "2026-01-01", "to": "2026-12-31" },
    "totals": [
      {
        "currency": "USD",
        "maintenance": 20.0,
        "fuel": 80.0,
        "total": 100.0,
        "maintenance_entries": 1,
        "fuel_entries": 2
      }
    ],
    "vehicles": [
      {
        "vehicle": { "id": 1, "make": "Toyota", "model": "Corolla", "plate": null },
        "currency": "USD",
        "maintenance": 20.0,
        "fuel": 80.0,
        "total": 100.0,
        "maintenance_entries": 1,
        "fuel_entries": 2
      }
    ],
    "monthly": [
      {
        "month": "2026-06",
        "currency": "USD",
        "maintenance": 20.0,
        "fuel": 80.0,
        "total": 100.0,
        "maintenance_entries": 1,
        "fuel_entries": 2
      }
    ],
    "billing": [{ "currency": "USD", "payments": 1, "total": 29.0 }]
  }
}
```

- `totals`: una fila por moneda, ordenadas por moneda.
- `vehicles`: una fila por vehículo y moneda (un vehículo puede tener gastos en dos monedas), de
  mayor a menor total.
- `monthly`: una fila por mes (`YYYY-MM`) y moneda, en orden cronológico.
- `billing`: pagos **aprobados** del propietario en el periodo, agrupados por moneda del plan;
  `amount_base` es el monto ya convertido a esa moneda.
- Periodo sin gastos: listas vacías (`totals: []`, etc.), nunca `null`.

## Reglas de cálculo

- **Mantenimiento**: registros con `performed_on` dentro del periodo; importe = Σ cantidad × precio
  unitario de sus ítems (el mismo `total` que muestra el módulo). Moneda del registro.
- **Combustible**: cargas con `filled_on` dentro del periodo; importe = litros × precio por litro.
  Moneda de la carga.
- **Suscripción**: pagos del propietario resuelto con `status = approved` y `paid_on` en el
  periodo; importe = `amount_base` (monto del pago / tasa), agrupado por la moneda del plan.
- **Sin conversión**: no se inventan tasas para mantenimiento/combustible; cada gasto conserva su
  moneda y los totales se agrupan por ella.
- Importes redondeados a 2 decimales.

## Modelo de datos

No hay tablas nuevas: el informe se calcula sobre `maintenance_records`, `maintenance_items`,
`fuel_entries` y `payments`. La agregación se hace en PHP para no depender de SQL específico de
PostgreSQL (los tests corren en SQLite).

## Project Structure

```text
carcheck/
  app/Services/ExpenseReport.php
  app/Services/VehicleScope.php
  app/Http/Controllers/Api/ReportController.php
  routes/api.php
  tests/Feature/Reports/*
carcheck-app/
  src/api/{types,endpoints}.ts, src/pages/Reports.tsx
  e2e/reports.spec.ts
```

## Commands

Backend: `php artisan test --filter=Report`, `vendor/bin/pint`.
Frontend: `npm run typecheck`, `npm run test`, `npm run build`, `npx playwright test`.

## Code Style

Pint por defecto; textos en español. El servicio no conoce HTTP; el controlador solo valida el
periodo, resuelve el ámbito y delega. Fechas `Y-m-d` en la API; el cliente formatea.

## Testing Strategy

- **Backend (`ExpenseReportTest`)**: suma mantenimiento y combustible por moneda; separa monedas
  distintas; excluye registros fuera del periodo; agrupa por vehículo y por mes; `billing` solo
  cuenta pagos aprobados del propietario; periodo vacío devuelve listas vacías.
- **Backend (`ReportApiTest`)**: ámbito personal/organización (403 para no miembro), aislamiento de
  vehículos ajenos (404 con `vehicle_id`), filtro por vehículo, validación de fechas y defaults.
- **Cliente**: E2E `reports.spec.ts` (registro → vehículo → mantenimiento → combustible → informe).

## Boundaries

- **Siempre:** agrupar por moneda, reutilizar los importes de los módulos (no recalcular con otra
  fórmula), respetar el ámbito de `vehicles`, calcular en PHP.
- **Preguntar antes:** exportar a CSV/PDF, gráficos, comparación entre periodos, presupuestos,
  conversión de moneda con tasas históricas, informe por conductor/sucursal.
- **Nunca:** sumar monedas distintas en un solo número, incluir pagos pendientes o rechazados,
  mostrar vehículos ajenos, copiar código de `openGym` (AGPL-3.0).

## Success Criteria

- Un mantenimiento de 20 USD y una carga de 40 USD en el periodo dan `USD 60` de total.
- Dos monedas dan dos filas de total, sin mezclarse.
- El desglose por vehículo y por mes cuadra con los totales.
- Los pagos pendientes no aparecen en `billing`.
- `php artisan test` y `npm run test` en verde; `pint` y `typecheck` limpios.

## Decisiones y caveats

1. **Totales por moneda, no convertidos**: no hay tasas históricas para mantenimiento/combustible;
   convertirlos con la tasa del último pago sería inventar. Se agrupa y se informa.
2. **Suscripción aparte**: es un gasto del propietario, no del vehículo; mezclarlo con el desglose
   por vehículo ensuciaría el informe. Se muestra como bloque propio.
3. **Agregación en PHP**: los volúmenes por usuario/periodo son pequeños y así el informe es
   idéntico en SQLite (tests) y PostgreSQL (producción).
4. **Periodo por defecto = año en curso**: es lo que se consulta casi siempre y evita un informe
   gigantesco al abrir la página.
5. **`VehicleScope` compartido**: `vehicles`, `reminders` y `reports` resuelven el ámbito igual; una
   sola implementación evita que uno de los tres se desincronice.
