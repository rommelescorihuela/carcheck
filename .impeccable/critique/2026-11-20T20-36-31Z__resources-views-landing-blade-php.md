---
target: landing de producto CarCheck (v3)
total_score: 29
max_score: 32
na_heuristics: 7,10
p0_count: 1
p1_count: 0
target_identity: "file:/var/www/html/extra/carcheck/resources/views/landing.blade.php"
target_fingerprint: "sha256:e8df7abf03513bf91923f7dea978df92e0fe2c7a069f3b3eabe57ce006917e16"
target_path: /var/www/html/extra/carcheck/resources/views/landing.blade.php
timestamp: 2026-11-20T20-36-31Z
slug: resources-views-landing-blade-php
---
## CarCheck — crítica v3

**Total: 29/32.** Sube 4 puntos. Los tres que faltan son de recuperación de errores y control, no de superficie.

### Problemas

**[P0] "Entrar a la app" está muerto.** Header, menú y pie apuntan a `FRONTEND_URL`, que por defecto es `http://localhost:5174` y devuelve `ERR_CONNECTION_REFUSED`. Es el único camino del cliente que ya paga.
→ Configurar la URL de la PWA o no renderizar el enlace mientras no exista.

**[P2] Los números del hero no cuadran.** Odómetro 128.450, último service 127.210 (1.240 km), "en 1.240 km" y barra al 72 %. Con esos datos el intervalo es 2.480 km (barra al 50 %), y un intervalo de aceite de 2.480 km no es creíble.
→ Service a 123.000, próximo en 1.550, intervalo 7.000, barra al 78 %.

**[P2] La audiencia de flota no ve una flota.** El único artefacto es un vehículo; la tabla promete "estado por vehículo y por flota entera" y no hay evidencia de esa vista.
→ Tira de 3 filas (patente · vence · responsable) o pestaña "Flota" en la ficha.

**[P2] El error no tiene estado de campo ni foco.** `aria-invalid` no cambia borde; tras enviar, `activeElement` es `BODY`.
→ Borde y fondo de error + foco al primer campo inválido + resumen "Faltan 2 datos".

**[P3]** El panel de éxito queda vacío y no repite el correo. El rail de permisos desaparece en móvil. Sin legales en el pie.

### Personas
Jordan cree que ya debería tener cuenta y el enlace lo lleva a un error. Riley no ve la flota ni los números cuadrando. Casey tiene menú y ficha en el primer scroll, pero 5.478 px sin CTA persistente.
