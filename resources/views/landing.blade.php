<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CarCheck — El historial y los vencimientos de cada vehículo</title>
    <meta name="description" content="CarCheck guarda el mantenimiento, los documentos y el kilometraje de cada vehículo, y avisa antes de que algo venza. Para un auto o para una flota de 75.">
    <meta name="theme-color" content="#f4f1ea">
    <meta property="og:type" content="website">
    <meta property="og:title" content="CarCheck — El auto avisa antes de romperse">
    <meta property="og:description" content="Historial, documentos y próximo mantenimiento por vehículo. Avisos antes del vencimiento.">
    <meta property="og:locale" content="es_ES">
    <link rel="icon" href="{{ asset('brand/carcheck-mark.svg') }}" type="image/svg+xml">
    @vite('resources/css/app.css')
</head>
<body class="landing">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-navy focus:px-4 focus:py-2 focus:text-white">
        Saltar al contenido
    </a>

    <header class="lc-wrap flex items-center justify-between gap-6 py-5">
        <a href="/" class="flex items-center gap-2.5" aria-label="CarCheck, inicio">
            <svg width="34" height="34" viewBox="0 0 96 96" aria-hidden="true" focusable="false">
                <rect width="96" height="96" rx="22" fill="#1d4a70"/>
                <g fill="none" stroke="#f6f7f8" stroke-width="5" stroke-linecap="round">
                    <circle cx="46" cy="46" r="19"/>
                    <path d="M46 52v13"/>
                    <path d="M40 43 31 38"/>
                    <path d="M52 43 61 38"/>
                </g>
                <circle cx="46" cy="46" r="6" fill="#f6f7f8"/>
                <circle cx="70" cy="70" r="15" fill="#c03a2b" stroke="#1d4a70" stroke-width="4"/>
                <path d="m63 70 5 5 10-11" fill="none" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="text-[1.0625rem] font-bold tracking-[-0.02em]">CarCheck</span>
        </a>

        <nav class="hidden items-center gap-7 text-[0.9375rem] md:flex" aria-label="Secciones">
            <a href="#como-funciona" class="text-ink/70 transition-colors hover:text-ink">Cómo funciona</a>
            <a href="#flotas" class="text-ink/70 transition-colors hover:text-ink">Flotas</a>
            <a href="#planes" class="text-ink/70 transition-colors hover:text-ink">Planes</a>
            <a href="{{ url('/admin/login') }}" class="lc-btn lc-btn-primary !min-h-10 !px-4 !text-sm">Entrar</a>
        </nav>

        <a href="{{ url('/admin/login') }}" class="lc-btn lc-btn-primary !min-h-10 !px-4 !text-sm md:hidden">Entrar</a>
    </header>

    <main id="contenido">
        {{-- Primer viewport: la ficha del vehículo demuestra el mecanismo --}}
        <section class="lc-wrap grid items-start gap-10 pb-16 pt-6 lg:grid-cols-12 lg:gap-x-10 lg:pb-24 lg:pt-12">
            <div class="lg:col-span-7">
                <h1 class="lc-display max-w-[15ch] text-[clamp(2.5rem,5.4vw,4.5rem)]">
                    El auto avisa antes de romperse.
                </h1>

                <p class="mt-6 max-w-[46ch] text-[1.0625rem] leading-[1.65] text-ink/75">
                    CarCheck guarda el historial, los documentos y el próximo mantenimiento de cada
                    vehículo. Cuando algo está por vencer, llega el aviso: no el taller, no la multa.
                </p>

                <div class="lc-reveal mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ url('/admin/login') }}" class="lc-btn lc-btn-primary">Entrar al panel</a>
                    <a href="#planes" class="lc-btn lc-btn-ghost">
                        Ver planes
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>

                <dl class="lc-rule mt-10 grid gap-px pt-0 sm:grid-cols-3">
                    <div class="py-4 pr-4">
                        <dt class="lc-label">SOAT</dt>
                        <dd class="mt-1.5 text-[0.9375rem] font-semibold text-soon">Vence en 12 días</dd>
                    </div>
                    <div class="py-4 pr-4 sm:border-l sm:border-ink/12 sm:pl-4">
                        <dt class="lc-label">Aceite y filtros</dt>
                        <dd class="mt-1.5 text-[0.9375rem] font-semibold text-soon">Faltan 1.240 km</dd>
                    </div>
                    <div class="py-4 pr-4 sm:border-l sm:border-ink/12 sm:pl-4">
                        <dt class="lc-label">Revisión técnica</dt>
                        <dd class="mt-1.5 text-[0.9375rem] font-semibold text-navy">Al día</dd>
                    </div>
            </dl>
            </div>

            <aside class="lg:col-span-5 lg:col-start-8" aria-labelledby="ficha-titulo">
                <div class="lc-sheet lc-reveal lc-reveal-2 overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-ink/12 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="lc-plate">AA 123 BC</span>
                            <span id="ficha-titulo" class="text-sm font-semibold">Toyota Hilux · 2021</span>
                        </div>
                        <span class="text-[0.6875rem] font-semibold uppercase tracking-[0.14em] text-ink/70">Ficha</span>
                    </div>

                    <div class="px-5 pb-4 pt-5">
                        <p class="lc-label">Odómetro</p>
                        <p class="mt-1 text-[2.5rem] font-bold leading-none tracking-[-0.035em]">128.450<span class="ml-1.5 text-base font-semibold text-ink/70">km</span></p>
                    </div>

                    <div class="border-t border-ink/12 px-5 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-semibold">Aceite y filtros</p>
                            <p class="text-[0.8125rem] font-semibold text-soon">en 1.240 km</p>
                        </div>
                        <div class="lc-meter mt-2.5" role="img" aria-label="72 % del intervalo recorrido">
                            <span style="width: 72%"></span>
                        </div>
                        <p class="mt-2 text-[0.75rem] text-ink/70">Último service: 4 de septiembre · 127.210 km</p>
                    </div>

                    <ul class="border-t border-ink/12 px-5 py-2">
                        <li class="flex items-center justify-between gap-3 border-b border-ink/8 py-3 last:border-b-0">
                            <span class="text-sm">SOAT</span>
                            <span class="lc-chip lc-chip-soon">Vence 2 dic</span>
                        </li>
                        <li class="flex items-center justify-between gap-3 border-b border-ink/8 py-3 last:border-b-0">
                            <span class="text-sm">Seguro todo riesgo</span>
                            <span class="lc-chip lc-chip-overdue">Vencido hace 4 días</span>
                        </li>
                        <li class="flex items-center justify-between gap-3 py-3">
                            <span class="text-sm">Revisión técnica</span>
                            <span class="lc-chip lc-chip-ok">Al día</span>
                        </li>
                    </ul>

                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-ink/12 bg-ink/[0.03] px-5 py-3">
                        <p class="text-[0.75rem] text-ink/70">Actualizada por Marcos · hace 2 h</p>
                        <span class="lc-stamp ml-auto">Por vencer</span>
                    </div>
                </div>
                <p class="mt-3 text-right text-[0.75rem] text-ink/70">Ficha de ejemplo con datos ilustrativos.</p>
            </aside>

        </section>

        {{-- El problema, contado como registro y no como tarjetas --}}
        <section class="border-y border-ink/12 bg-paper-2/60 py-16 lg:py-20" aria-labelledby="carpeta-titulo">
            <div class="lc-wrap grid gap-10 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <h2 id="carpeta-titulo" class="lc-display text-[clamp(1.9rem,3.2vw,2.6rem)]">
                        Lo que se pierde en una carpeta
                    </h2>
                    <p class="mt-4 max-w-[38ch] text-[0.9375rem] leading-[1.65] text-ink/70">
                        No es falta de cuidado: es que un vencimiento no vive en un papel, vive en una fecha.
                    </p>
                </div>

                <div class="lg:col-span-8">
                    <table class="lc-table lc-table-stack">
                        <caption class="sr-only">Comparación entre llevar los vencimientos en una carpeta y en CarCheck</caption>
                        <thead>
                            <tr>
                                <th scope="col">Qué vence</th>
                                <th scope="col">En una carpeta</th>
                                <th scope="col">En CarCheck</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row" class="font-semibold">SOAT y seguro</th>
                                <td class="text-ink/70" data-label="En una carpeta">Se descubre cuando ya venció</td>
                                <td data-label="En CarCheck">Aviso antes de la fecha y marca roja si pasó</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Cambio de aceite</th>
                                <td class="text-ink/70" data-label="En una carpeta">“Creo que fue en marzo”</td>
                                <td data-label="En CarCheck">Calculado por kilometraje, no por memoria</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Revisión técnica</th>
                                <td class="text-ink/70" data-label="En una carpeta">Un papel en la guantera</td>
                                <td data-label="En CarCheck">Estado por vehículo y por flota entera</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Cómo funciona: secuencia real, numerada porque el orden importa --}}
        <section id="como-funciona" class="lc-wrap py-16 lg:py-24" aria-labelledby="pasos-titulo">
            <div class="grid gap-10 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <h2 id="pasos-titulo" class="lc-display text-[clamp(1.9rem,3.2vw,2.6rem)]">
                        Tres pasos, siempre los mismos
                    </h2>
                    <p class="mt-4 max-w-[40ch] text-[0.9375rem] leading-[1.65] text-ink/70">
                        La primera carga toma unos minutos. Después, el sistema hace el trabajo
                        aburrido: acordarse.
                    </p>
                </div>

                <ol class="lc-seq lg:col-span-7">
                    <li>
                        <span class="lc-num">01</span>
                        <div>
                            <h3 class="font-semibold">Carga el vehículo</h3>
                            <p class="mt-1 text-[0.9375rem] leading-[1.6] text-ink/70">
                                Patente, kilometraje y una foto del documento. Queda asociado a tu
                                cuenta o a la organización.
                            </p>
                        </div>
                    </li>
                    <li>
                        <span class="lc-num">02</span>
                        <div>
                            <h3 class="font-semibold">Registra servicios y combustible</h3>
                            <p class="mt-1 text-[0.9375rem] leading-[1.6] text-ink/70">
                                Cada carga suma al historial y mueve el próximo mantenimiento. Lo puede
                                hacer cualquiera del equipo, incluso el conductor.
                            </p>
                        </div>
                    </li>
                    <li>
                        <span class="lc-num">03</span>
                        <div>
                            <h3 class="font-semibold">Recibe el aviso</h3>
                            <p class="mt-1 text-[0.9375rem] leading-[1.6] text-ink/70">
                                Correo, notificación y aviso dentro del panel. Si ya venció, también
                                avisa: nadie se entera tarde por silencio.
                            </p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        {{-- Roles: la diferencia entre un garaje y una flota --}}
        <section id="flotas" class="border-y border-ink/12 bg-paper-2/60 py-16 lg:py-20" aria-labelledby="flotas-titulo">
            <div class="lc-wrap grid gap-10 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <h2 id="flotas-titulo" class="lc-display text-[clamp(1.9rem,3.2vw,2.6rem)]">
                        El conductor registra. El dueño decide.
                    </h2>
                    <p class="mt-4 max-w-[38ch] text-[0.9375rem] leading-[1.65] text-ink/70">
                        Los permisos se aplican por organización: el conductor registra, el dueño decide.
                    </p>
                </div>

                <div class="lg:col-span-8">
                    <table class="lc-table">
                        <caption class="sr-only">Roles y permisos por organización</caption>
                        <thead>
                            <tr>
                                <th scope="col">Rol</th>
                                <th scope="col">Puede hacer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row" class="font-semibold">Dueño</th>
                                <td class="text-ink/70">Gestiona el garaje, el plan y las personas. Transfiere la organización.</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Administrador</th>
                                <td class="text-ink/70">Agrega vehículos y personas, y ve todo el historial.</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Miembro</th>
                                <td class="text-ink/70">Registra mantenimiento y combustible de cualquier vehículo.</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Conductor</th>
                                <td class="text-ink/70">Solo registra sus cargas. No cambia la configuración ni los planes.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Planes: tarifario del taller, no tarjetas de SaaS --}}
        <section id="planes" class="lc-wrap py-16 lg:py-24" aria-labelledby="planes-titulo">
            <div class="grid gap-6 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-7">
                    <h2 id="planes-titulo" class="lc-display text-[clamp(1.9rem,3.2vw,2.6rem)]">
                        Precio por tamaño de garaje
                    </h2>
                    <p class="mt-4 max-w-[44ch] text-[0.9375rem] leading-[1.65] text-ink/70">
                        Todos los planes incluyen historial, documentos y recordatorios, con 14 días de prueba.
                    </p>
                </div>
                <p class="text-[0.8125rem] text-ink/70 lg:col-span-5 lg:text-right">USD · facturación mensual</p>
            </div>

            <div class="mt-8 border-t border-ink/16">
                <div class="grid gap-x-6 gap-y-2 border-b border-ink/12 py-5 sm:grid-cols-12 sm:items-baseline">
                    <div class="sm:col-span-5">
                        <h3 class="text-[1rem] font-semibold">Básico</h3>
                        <p class="mt-0.5 text-[0.875rem] leading-[1.5] text-ink/70">Un vehículo y su historial completo.</p>
                    </div>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-3">1 vehículo · 1 persona</p>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-2">Recordatorios por correo</p>
                    <p class="text-[1.375rem] font-bold tracking-[-0.03em] sm:col-span-2 sm:text-right">
                        3,99 <span class="text-[0.8125rem] font-semibold text-ink/70">USD/mes</span>
                    </p>
                </div>

                <div class="grid gap-x-6 gap-y-2 border-b border-ink/12 py-5 sm:grid-cols-12 sm:items-baseline">
                    <div class="sm:col-span-5">
                        <h3 class="text-[1rem] font-semibold">Familiar</h3>
                        <p class="mt-0.5 text-[0.875rem] leading-[1.5] text-ink/70">Hasta 5 vehículos compartidos.</p>
                    </div>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-3">5 vehículos · 5 personas</p>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-2">Todos los avisos</p>
                    <p class="text-[1.375rem] font-bold tracking-[-0.03em] sm:col-span-2 sm:text-right">
                        7,99 <span class="text-[0.8125rem] font-semibold text-ink/70">USD/mes</span>
                    </p>
                </div>

                <div class="grid gap-x-6 gap-y-2 border-b border-ink/12 py-5 sm:grid-cols-12 sm:items-baseline">
                    <div class="sm:col-span-5">
                        <h3 class="text-[1rem] font-semibold">Flota</h3>
                        <p class="mt-0.5 text-[0.875rem] leading-[1.5] text-ink/70">Conductores que registran sin tocar la configuración.</p>
                    </div>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-3">25 vehículos · 10 personas</p>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-2">Rol de conductor y reportes</p>
                    <p class="text-[1.375rem] font-bold tracking-[-0.03em] sm:col-span-2 sm:text-right">
                        29,99 <span class="text-[0.8125rem] font-semibold text-ink/70">USD/mes</span>
                    </p>
                </div>

                <div class="grid gap-x-6 gap-y-2 border-b border-ink/12 py-5 sm:grid-cols-12 sm:items-baseline">
                    <div class="sm:col-span-5">
                        <h3 class="text-[1rem] font-semibold">Empresa</h3>
                        <p class="mt-0.5 text-[0.875rem] leading-[1.5] text-ink/70">Para operaciones grandes.</p>
                    </div>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-3">75 vehículos · 25 personas</p>
                    <p class="text-[0.875rem] text-ink/70 sm:col-span-2">Reportes de la flota</p>
                    <p class="text-[1.375rem] font-bold tracking-[-0.03em] sm:col-span-2 sm:text-right">
                        59,99 <span class="text-[0.8125rem] font-semibold text-ink/70">USD/mes</span>
                    </p>
                </div>
            </div>

            <p class="mt-5 text-[0.8125rem] text-ink/70">
                Precios publicados en el sistema. El alta de la cuenta la hace el equipo de CarCheck.
            </p>
        </section>

        {{-- Cierre --}}
        <section class="lc-wrap pb-16 lg:pb-24" aria-labelledby="cierre-titulo">
            <div class="rounded-xl bg-navy px-6 py-12 text-white sm:px-10 lg:px-14 lg:py-16">
                <div class="grid items-end gap-8 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 id="cierre-titulo" class="lc-display max-w-[20ch] text-[clamp(1.9rem,3.6vw,3rem)]">
                            Empieza por el vehículo que más te preocupa.
                        </h2>
                        <p class="mt-4 max-w-[46ch] text-[0.9375rem] leading-[1.65] text-white/70">
                            En cinco minutos tienes el historial cargado y la primera fecha a la vista.
                        </p>
                    </div>
                    <div class="lg:col-span-4 lg:text-right">
                        <a href="{{ url('/admin/login') }}" class="lc-btn bg-white !text-navy hover:!bg-paper">
                            Entrar al panel
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-ink/12">
        <div class="lc-wrap flex flex-col gap-4 py-8 text-[0.8125rem] text-ink/70 sm:flex-row sm:items-center sm:justify-between">
            <p>CarCheck · mantenimiento y documentos por vehículo</p>
            <nav class="flex items-center gap-5" aria-label="Pie de página">
                <a href="#planes" class="transition-colors hover:text-ink">Planes</a>
                <a href="{{ url('/admin/login') }}" class="transition-colors hover:text-ink">Entrar</a>
                <span>{{ date('Y') }}</span>
            </nav>
        </div>
    </footer>
</body>
</html>
