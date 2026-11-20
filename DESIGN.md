# DESIGN — CarCheck

Sistema visual del producto. El panel (`/admin`) usa Filament con la marca CarCheck; la
landing (`/`) tiene su propia capa, documentada aquí.

## Principio

La landing es una **bitácora de taller**: papel, reglas de 1 px y cifras que se leen como
un odómetro. Nada flota; la jerarquía se construye con líneas y escala tipográfica, no con
sombras. El color solo aparece cuando algo vence.

## Tokens

| Token | Valor | Uso |
|---|---|---|
| `--color-paper` | `#f4f1ea` | Fondo de página |
| `--color-paper-2` | `#fbf9f4` | Superficies (ficha, bandas) |
| `--color-ink` | `#10202e` | Texto y líneas base |
| `--color-navy` | `#1d4a70` | Marca, acciones, "al día" |
| `--color-navy-deep` | `#163a58` | Hover de acción |
| `--color-signal` | `#c03a2b` | Vencido (marca de la insignia del logo) |
| `--color-soon` | `#a86a10` | Por vencer |

Líneas: `color-mix(in srgb, var(--color-ink) 14%, transparent)`. Sin `box-shadow` en
superficies; la ficha se separa con borde de 1 px.

## Tipografía

- **Archivo** (variable 100–900, self-hosted en `public/fonts/archivo.woff2`, licencia OFL).
  Es la única familia de la landing: display y texto.
- Display: `letter-spacing: -0.035em`, `line-height: 0.98`, `text-wrap: balance`.
- Cuerpo: 17 px / 1.65; máximo 46–50 ch.
- Etiquetas: 11 px, 600, `letter-spacing: .14em`, mayúsculas.
- Cifras tabulares siempre (`font-variant-numeric: tabular-nums`).

## Componentes

- `.lc-sheet` — ficha: borde 1 px, radio 14 px, fondo `paper-2`.
- `.lc-plate` — placa de matrícula: fondo blanco, borde 30 % ink, tracking `.16em`.
- `.lc-meter` — barra de intervalo de service; se llena una vez al cargar.
- `.lc-chip-{overdue,soon,ok}` — estado de un vencimiento.
- `.lc-stamp` — sello girado −3°, doble filete, solo para "por vencer".
- `.lc-table` / `.lc-table-stack` — registro y tarifario; en móvil cada fila se apila con
  su etiqueta (`data-label`).
- `.lc-btn-primary` (navy) y `.lc-btn-ghost` (borde 22 % ink).

## Composición

Primer viewport en 12 columnas: 7 para titular, bajada, CTAs y rail de vencimientos; 5 para
la ficha. El rail desaparece en móvil (< 640 px) para que la ficha entre en la primera
pantalla. Secciones alternan `paper` y `paper-2/60` con `border-y` de 1 px.

## Movimiento

Un solo momento: la barra del intervalo se llena (`scaleX` 0→1, 900 ms, expo-out) y la ficha
entra con 10 px de desplazamiento. Todo se anula bajo `prefers-reduced-motion`.

## Accesibilidad

Foco visible de 2 px navy con offset 3 px; skip link; contraste mínimo 4.5:1 (secundarios a
70 % de ink sobre papel ≈ 5.9:1); tablas con `caption` y `scope`; la barra de intervalo lleva etiqueta
accesible y el sello es `aria-hidden` porque duplica el texto del estado.

## Assets

| Asset | Origen |
|---|---|
| `public/fonts/archivo.woff2` | Google Fonts, subset latin, OFL |
| Marca inline del header | `docs/brand/carcheck-mark.svg` (autoría del proyecto) |
| `public/brand/carcheck-mark.svg` | copia del anterior para favicon |
