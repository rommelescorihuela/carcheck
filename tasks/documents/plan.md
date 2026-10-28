# Implementation Plan: documents

## Overview

Documentos legales del vehículo con vencimiento y archivo escaneado, más un estado derivado
(`valid`/`expiring`/`expired`) y un resumen por vehículo. Hereda la propiedad del vehículo.
Ver [SPEC-documents](../../SPEC-documents.md).

## Architecture Decisions

- **Estado derivado**: `DocumentStatus` calcula `status` y `days_left` desde `expires_on`; no hay
  columna que quede obsoleta al día siguiente.
- **Autorización delegada en el vehículo**: `DocumentPolicy` reutiliza `VehiclePolicy` (accesible vs
  gestionable), igual que `FuelEntryPolicy` y `MaintenanceRecordPolicy`.
- **Archivo**: medialibrary, colección `file` **single file**, disco `public`; se reemplaza al subir
  otro.
- **`kind` como enum cerrado** (`DocumentKind`): los tipos legales son fijos; el texto libre va en
  `name`.
- **Sin costo ni moneda**: `reporting` no depende de `documents`; añadirlo cambiaría ese contrato.

## Task List

### Fase 0: Fundación
- [x] Task 1: Enum `DocumentKind`, migración, modelo `Document` (medialibrary), factory y relación en `Vehicle`
- [x] Task 2: `DocumentStatus` (estado, `days_left`, resumen) + tests

### Fase 1: API
- [x] Task 3: `DocumentPolicy` + `DocumentResource` + `DocumentController` (CRUD) + rutas + tests
- [x] Task 4: Archivo (`POST/DELETE /documents/{document}/file`) + `documents-summary` + tests

### Fase 2: PWA
- [x] Task 5: Cliente (endpoints/types) + store `documents` + test del store
- [x] Task 6: Páginas `DocumentsList` (resumen + estado por color) y `DocumentForm` (con archivo)
- [x] Task 7: E2E `documents.spec.ts`

### Checkpoint: Módulo completo
- [x] Criterios de éxito de `SPEC-documents.md` cumplidos

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Estado desactualizado | Alto | Derivado en el recurso, nunca columna |
| Zona horaria desplaza `days_left` | Medio | Comparar fechas con `startOfDay()`; tests con fechas fijas |
| Archivo huérfano al borrar | Bajo | Medialibrary borra en cascada al eliminar el documento |
| Duplicar autorización | Medio | Delegar en `VehiclePolicy` |

## Task List Target

Tareas en [`todo.md`](./todo.md).
