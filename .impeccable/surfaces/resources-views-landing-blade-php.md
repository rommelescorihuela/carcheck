---
version: 1
slug: "resources-views-landing-blade-php"
primary_target: "resources/views/landing.blade.php"
related_targets: []
---

# Landing de producto — CarCheck

- **Modo:** Persuade. Visitante: dueño de flota pequeña o particular ordenado.
- **Trabajo:** que entienda en 10 segundos que el sistema avisa antes de que algo venza, y
  que vea el precio real sin hablar con nadie.
- **Acción primaria:** entrar al panel (`/admin/login`). Secundaria: ver planes (ancla).

## Direction contract

**THESIS:** la landing es una bitácora de taller, no un hero de SaaS: una ficha de vehículo
con sus vencimientos ocupa el primer viewport y demuestra el mecanismo antes de explicarlo.
Se rechaza el hero centrado con tres tarjetas de icono.

**OWN-WORLD:** papel hueso (#f4f1ea) y tinta azul marino (#1d4a70) con líneas de 1 px y
ámbar de tablero (#c98a12) solo para lo que vence. Archivo en 400/600/700, cifras tabulares,
etiquetas en mayúsculas pequeñas con tracking. Sin sombras difusas: elevación por línea.

**STORY:** el visitante reconoce su propio problema (documentos y services que se pasan de
fecha), ve cómo la ficha lo resuelve, entiende roles y planes con precios reales, y entra.

**FIRST VIEWPORT:** dos columnas desiguales (7/5). Izquierda: titular de dos líneas, bajada
corta, CTA navy + enlace a planes, y una tira fina con tres vencimientos. Derecha: ficha
"Toyota Hilux · AA123BC" con odómetro, próximo service y documentos; un sello "EN 12 DÍAS".
En móvil la ficha va debajo del titular, nunca antes.

**FORM:** séptima de siete estructuras derivadas del mundo del taller (bitácora, orden de
trabajo, tablero, manual, checklist, calendario de mantenimiento, ficha de vehículo).
Elegida por ser la que contiene el mecanismo completo. `seed: none (unavailable)`.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.
