---
target: landing de producto CarCheck
total_score: 27
max_score: 32
na_heuristics: 7,10
p0_count: 1
p1_count: 1
target_identity: "file:/var/www/html/extra/carcheck/resources/views/landing.blade.php"
target_fingerprint: "sha256:6d3f9b92ebc834d103a8eaf778874af3639f54bb35478978b0907e5dc80c25a2"
target_path: /var/www/html/extra/carcheck/resources/views/landing.blade.php
timestamp: 2026-11-20T19-14-36Z
slug: resources-views-landing-blade-php
---
## CarCheck — landing de producto (Persuade)

### Especificidad

**Autorada para este producto (8,5/10).** El artefacto del hero *es* el mecanismo: placa, odómetro con cifras tabulares, barra de intervalo al 72 %, chips de vencimiento y sello. Ninguna pieza es transportable a otro SaaS. Lo intercambiable: el cierre navy con botón blanco, la banda de planes sin recomendación y el molde "h2 + bajada" repetido cuatro veces.

### Puntuación heurística

| # | Heurístico | Punt. | Evidencia |
|---|---|---|---|
| 1 | Visibilidad del estado | 4 | Chip + sello + barra + "hace 2 h" |
| 2 | Mundo real | 4 | SOAT, revisión técnica, odómetro, guantera |
| 3 | Control y libertad | 3 | Anclas y foco visibles; la única acción real es un login |
| 4 | Consistencia | 3 | El sello dice "Por vencer" con un documento "Vencido hace 4 días" |
| 5 | Prevención de errores | 3 | Avisa que el alta la hace el equipo, pero el CTA no lo refleja |
| 6 | Reconocer antes que recordar | 4 | Tabla carpeta vs CarCheck; estados con palabra |
| 7 | Flexibilidad | n/a | — |
| 8 | Diseño minimalista | 4 | Cero sombras, densidad justa |
| 9 | Recuperación de errores | 2 | Nada sobre datos mal cargados |
| 10 | Ayuda y documentación | n/a | — |
| **Total** | | **27/32** | **84 % · Bien (alto)** |

### Problemas prioritarios

**[P0] Todos los CTAs llevan a un login; no hay camino para un visitante nuevo.**
Un dueño de flota sin cuenta solo puede "Entrar al panel". El cierre promete "en cinco minutos tienes el historial cargado" y el destino es un formulario de login. El alta la hace el equipo, pero solo se dice en una nota de 13 px bajo los planes.
*Arreglo:* CTA de cierre "Solicitar acceso" que ancle a un bloque con el proceso real y un canal de contacto; "Ya tengo cuenta" solo en header y pie.

**[P1] El ámbar de vencimiento no cumple contraste AA.**
`--color-soon: #a86a10` sobre papel da 3,93:1 ("Vence en 12 días", "Faltan 1.240 km"), 4,22:1 ("en 1.240 km") y 3,97:1 (sello). Cuatro nodos en 1440, dos en 390.
*Arreglo:* oscurecer el token a ~#8a5606 y verificar ≥4,5:1 sobre `paper` y `paper-2`.

**[P2] El sello "Por vencer" contradice la ficha.**
El elemento más de marca se contradice con un dato de su propia lista.
*Arreglo:* sello dinámico con el estado más urgente o contador "1 vencido · 1 por vencer".

**[P2] Los planes no se pueden comparar.**
Los deltas mezclan canal, rol y capacidad ("Recordatorios por correo" → "Todos los avisos" → "Rol de conductor y reportes"); en móvil las filas se apilan sin etiqueta de columna.
*Arreglo:* tres columnas estables (Vehículos, Personas, Qué incluye) con vocabulario homogéneo.

**[P2] Enlaces de navegación y pie bajo 24×24 px.**
Nav 22,5 px de alto y pie 19,5 px: incumplen WCAG 2.2 SC 2.5.8 (mínimo 24×24). Además el header móvil mide 40 px y no hay menú: las secciones solo se alcanzan por el pie.
*Arreglo:* padding vertical hasta 24 px mínimo; añadir menú móvil.

**[P3]** El rail de tres hechos duplica la ficha en el mismo viewport. La placa se parte en tres líneas con formatos largos (falta `white-space: nowrap`). "FICHA" es decorativa.

### Personas

- **Jordan:** entiende el producto, llega al cierre, hace clic en "Entrar al panel" y no sabe si debe registrarse. Se pierde en el único paso que importa.
- **Riley:** `AB 123 CDE` parte la placa en columna; un nombre largo empuja la etiqueta; la tabla de roles queda justa a 320 px.
- **Casey:** el mejor caso de los tres: a 390 ve titular, CTA y ficha completa (aside en y=437, 527 px). Pero no tiene navegación a secciones.

### Observaciones menores

Botón "Entrar" 40 px vs 46 del hero. `og:locale` es_ES para contenido LATAM; sin `og:image`. Sin enlaces legales. Cuatro secciones con el mismo molde.
