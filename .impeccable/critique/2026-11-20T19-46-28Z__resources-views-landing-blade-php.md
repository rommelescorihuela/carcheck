---
target: landing de producto CarCheck (v2)
total_score: 25
max_score: 32
na_heuristics: 7,10
p0_count: 0
p1_count: 3
target_identity: "file:/var/www/html/extra/carcheck/resources/views/landing.blade.php"
target_fingerprint: "sha256:f4eefd05c30f534f4ecdb40f334bbce1feae0e700fcd3747ccec96160b60dcdc"
target_path: /var/www/html/extra/carcheck/resources/views/landing.blade.php
timestamp: 2026-11-20T19-46-28Z
slug: resources-views-landing-blade-php
---
## CarCheck — crítica v2

**Especificidad:** autorada. La ficha demuestra el mecanismo y los números son coherentes (127.210 + 1.240 = 128.450, barra al 72 %). El molde de página (hero + rail + tabla + tabla) es lo único genérico.

**Total: 25/32 (78 %) — Bueno.** Baja 2 puntos respecto a la ronda anterior por los estados nuevos del formulario y la duplicación de CTA, no por el arte.

### Problemas

**[P1] "Ya tengo cuenta" lleva a la puerta equivocada.** Apunta a `/admin/login`, donde solo entra `super_admin`; los clientes usan la PWA. Un dueño de flota que ya paga aterriza en un login que lo rechaza. → apuntar a la URL de la app o quitar el enlace.

**[P1] Inputs a 15 px: iOS hace zoom al enfocar.** Los 5 campos de `.lc-field`. → 16 px mínimo.

**[P1] Validación en inglés y sin asociación.** Globo nativo "Please fill out this field" tapando la etiqueta; `aria-invalid` sin `aria-describedby`; tras un error el foco queda arriba y el mensaje fuera de pantalla. → `novalidate`, mensajes propios en español, `aria-describedby` y foco al primer campo inválido.

**[P2] El formulario no encaja con el comprador de un auto.** "Empresa o garaje" obligatorio y "Vehículos" opcional, al revés de lo que decide el plan.

**[P2] Sin privacidad ni términos** en el pie, con un formulario que recoge correo y empresa.

**[P3]** CTA duplicado en cabecera y hero en móvil; el menú `<details>` no cierra con Esc ni al tocar fuera; "Personas —" en Básico es ambiguo; el sello ámbar resume un vencido en color de "por vencer".

### Personas
Jordan quiere crear cuenta y encuentra un formulario de acceso; "Empresa" obligatorio lo frena. Riley no tiene respuesta para 80 vehículos ("Más de 75" sin plan). Casey ve la ficha en la primera pantalla, pero el CTA está a 5.400 px y el zoom de iOS le rompe el formulario.
