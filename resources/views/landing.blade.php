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
    <meta property="og:locale" content="es_LA">
    <meta property="og:image" content="{{ asset('og.png') }}">
    <link rel="icon" href="{{ asset('brand/carcheck-mark.svg') }}" type="image/svg+xml">
    @vite('resources/css/app.css')
</head>
<body class="landing">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-navy focus:px-4 focus:py-2 focus:text-white">
        Saltar al contenido
    </a>

    <header class="lc-wrap flex items-center justify-between gap-6 py-5">
        <a href="/" class="flex min-h-9 items-center gap-2.5" aria-label="CarCheck, inicio">
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

        <nav class="hidden items-center gap-6 text-[0.9375rem] md:flex" aria-label="Secciones">
            <a href="#como-funciona" class="lc-navlink text-ink/75 transition-colors hover:text-ink">Cómo funciona</a>
            <a href="#flotas" class="lc-navlink text-ink/75 transition-colors hover:text-ink">Flotas</a>
            <a href="#planes" class="lc-navlink text-ink/75 transition-colors hover:text-ink">Planes</a>
            <a href="{{ url('/admin/login') }}" class="lc-btn lc-btn-ghost !min-h-10 !px-4 !text-sm">Ya tengo cuenta</a>
            <a href="#acceso" class="lc-btn lc-btn-primary !px-4 !text-sm">Solicitar acceso</a>
        </nav>

        <div class="flex items-center gap-2 md:hidden">
            <a href="#acceso" class="lc-btn lc-btn-primary !px-3.5 !text-sm">Solicitar acceso</a>
            <details class="lc-menu">
                <summary class="lc-menu-toggle" aria-label="Abrir menú">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M3 5h12M3 9h12M3 13h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </summary>
                <nav class="lc-menu-panel" aria-label="Secciones">
                    <a href="#como-funciona">Cómo funciona</a>
                    <a href="#flotas">Flotas</a>
                    <a href="#planes">Planes</a>
                    <a href="{{ url('/admin/login') }}">Ya tengo cuenta</a>
                </nav>
            </details>
        </div>
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
                    <a href="#acceso" class="lc-btn lc-btn-primary">Solicitar acceso</a>
                    <a href="#planes" class="lc-btn lc-btn-ghost">
                        Ver planes
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>

                <dl class="lc-rule mt-10 hidden gap-px pt-0 sm:grid sm:grid-cols-3">
                    <div class="py-4 pr-4">
                        <dt class="lc-label">Un solo historial</dt>
                        <dd class="mt-1.5 text-[0.9375rem] text-ink/85">Documentos y services juntos</dd>
                    </div>
                    <div class="py-4 pr-4 sm:border-l sm:border-ink/12 sm:pl-4">
                        <dt class="lc-label">Permisos por rol</dt>
                        <dd class="mt-1.5 text-[0.9375rem] text-ink/85">El conductor solo registra</dd>
                    </div>
                    <div class="py-4 pr-4 sm:border-l sm:border-ink/12 sm:pl-4">
                        <dt class="lc-label">Avisos</dt>
                        <dd class="mt-1.5 text-[0.9375rem] text-ink/85">Correo, push y panel</dd>
                    </div>
                </dl>
            </div>

            <aside class="lg:col-span-5 lg:col-start-8" aria-labelledby="ficha-titulo">
                <div class="lc-sheet lc-reveal lc-reveal-2 overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-ink/12 px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="lc-plate">AA 123 BC</span>
                            <span id="ficha-titulo" class="truncate text-sm font-semibold">Toyota Hilux · 2021</span>
                        </div>
                    </div>

                    <div class="px-5 pb-4 pt-5">
                        <p class="lc-label">Odómetro</p>
                        <p class="mt-1 text-[2.5rem] font-bold leading-none tracking-[-0.035em]">128.450<span class="ml-1.5 text-base font-semibold text-ink/75">km</span></p>
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

                    <div class="flex flex-col items-start gap-2 border-t border-ink/12 bg-ink/[0.03] px-5 py-3">
                        <p class="text-[0.75rem] text-ink/70">Actualizada por Marcos · hace 2 h</p>
                        <span class="lc-stamp self-end" aria-hidden="true">1 vencido · 1 por vencer</span>
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
                    <table class="lc-table lc-table-stack">
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
                                <td class="text-ink/70" data-label="Puede hacer">Gestiona el garaje, el plan y las personas. Transfiere la organización.</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Administrador</th>
                                <td class="text-ink/70" data-label="Puede hacer">Agrega vehículos y personas, y ve todo el historial.</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Miembro</th>
                                <td class="text-ink/70" data-label="Puede hacer">Registra mantenimiento y combustible de cualquier vehículo.</td>
                            </tr>
                            <tr>
                                <th scope="row" class="font-semibold">Conductor</th>
                                <td class="text-ink/70" data-label="Puede hacer">Solo registra sus cargas. No cambia la configuración ni los planes.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Planes: mismo vocabulario y mismas columnas para poder comparar --}}
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

            <table class="lc-table lc-table-stack lc-table-spec mt-8">
                <caption class="sr-only">Planes de CarCheck con precio, vehículos y personas</caption>
                <thead>
                    <tr>
                        <th scope="col">Plan</th>
                        <th scope="col">Vehículos</th>
                        <th scope="col">Personas</th>
                        <th scope="col" class="text-right">Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row" class="font-semibold">
                            Básico
                            <span class="mt-0.5 block text-[0.8125rem] font-normal text-ink/70">Un vehículo y su historial completo.</span>
                        </th>
                        <td data-label="Vehículos">1</td>
                        <td data-label="Personas">—</td>
                        <td data-label="Precio" class="text-left font-semibold sm:text-right">3,99 USD/mes</td>
                    </tr>
                    <tr>
                        <th scope="row" class="font-semibold">
                            Familiar
                            <span class="mt-0.5 block text-[0.8125rem] font-normal text-ink/70">Hasta 5 vehículos compartidos.</span>
                        </th>
                        <td data-label="Vehículos">5</td>
                        <td data-label="Personas">5</td>
                        <td data-label="Precio" class="text-left font-semibold sm:text-right">7,99 USD/mes</td>
                    </tr>
                    <tr>
                        <th scope="row" class="font-semibold">
                            Flota
                            <span class="mt-0.5 block text-[0.8125rem] font-normal text-ink/70">Conductores que registran sin tocar la configuración.</span>
                        </th>
                        <td data-label="Vehículos">25</td>
                        <td data-label="Personas">10</td>
                        <td data-label="Precio" class="text-left font-semibold sm:text-right">29,99 USD/mes</td>
                    </tr>
                    <tr>
                        <th scope="row" class="font-semibold">
                            Empresa
                            <span class="mt-0.5 block text-[0.8125rem] font-normal text-ink/70">Para operaciones grandes.</span>
                        </th>
                        <td data-label="Vehículos">75</td>
                        <td data-label="Personas">25</td>
                        <td data-label="Precio" class="text-left font-semibold sm:text-right">59,99 USD/mes</td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-5 text-[0.8125rem] text-ink/70">
                Precios publicados en el sistema. El alta de la cuenta la hace el equipo de CarCheck.
            </p>
        </section>

        {{-- Acceso: el camino real para un visitante sin cuenta --}}
        <section id="acceso" class="lc-wrap pb-16 lg:pb-24" aria-labelledby="acceso-titulo">
            <div class="grid overflow-hidden rounded-xl border border-ink/15 lg:grid-cols-12">
                <div class="flex flex-col justify-between gap-8 bg-navy px-6 py-10 text-white sm:px-10 lg:col-span-5 lg:py-12">
                    <div>
                        <h2 id="acceso-titulo" class="lc-display max-w-[18ch] text-[clamp(1.8rem,3.2vw,2.6rem)]">
                            Empieza por el vehículo que más te preocupa.
                        </h2>
                        <p class="mt-4 max-w-[38ch] text-[0.9375rem] leading-[1.65] text-white/75">
                            El alta la hace el equipo: cuéntanos cuántos vehículos tienes y te
                            escribimos para dejarlo cargado.
                        </p>
                    </div>
                    <ul class="space-y-2 text-[0.875rem] text-white/80">
                        <li>14 días de prueba en todos los planes</li>
                        <li>Sin tarjeta para empezar</li>
                        <li>Respondemos de lunes a viernes</li>
                    </ul>
                </div>

                <div class="bg-paper-2 px-6 py-10 sm:px-10 lg:col-span-7 lg:py-12">
                    @if (session('access_request_sent'))
                        <div role="status" class="flex h-full flex-col justify-center">
                            <p class="lc-label">Solicitud recibida</p>
                            <p class="mt-2 max-w-[40ch] text-[1.125rem] leading-[1.5]">
                                Gracias. Te escribimos al correo que dejaste para darte de alta y
                                cargar el primer vehículo contigo.
                            </p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('access-request.store') }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                            <div class="sm:col-span-2">
                                <label for="name" class="lc-label">Nombre</label>
                                <input id="name" name="name" type="text" required autocomplete="name"
                                       value="{{ old('name') }}" class="lc-field mt-1.5" @error('name') aria-invalid="true" @enderror>
                                @error('name') <p class="lc-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="email" class="lc-label">Correo</label>
                                <input id="email" name="email" type="email" required autocomplete="email"
                                       value="{{ old('email') }}" class="lc-field mt-1.5" @error('email') aria-invalid="true" @enderror>
                                @error('email') <p class="lc-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="company" class="lc-label">Empresa o garaje</label>
                                <input id="company" name="company" type="text" required autocomplete="organization"
                                       value="{{ old('company') }}" class="lc-field mt-1.5" @error('company') aria-invalid="true" @enderror>
                                @error('company') <p class="lc-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="fleet" class="lc-label">Vehículos</label>
                                <select id="fleet" name="fleet" class="lc-field mt-1.5">
                                    <option value="">Elegir…</option>
                                    @foreach (['1', '2 a 5', '6 a 25', '26 a 75', 'Más de 75'] as $opcion)
                                        <option value="{{ $opcion }}" @selected(old('fleet') === $opcion)>{{ $opcion }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="message" class="lc-label">Qué necesitas (opcional)</label>
                                <textarea id="message" name="message" rows="3" class="lc-field mt-1.5">{{ old('message') }}</textarea>
                                @error('message') <p class="lc-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
                                <button type="submit" class="lc-btn lc-btn-primary">Enviar solicitud</button>
                                <p class="text-[0.75rem] text-ink/70">Sin tarjeta. Te escribimos antes de dar de alta nada.</p>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-ink/12">
        <div class="lc-wrap flex flex-col gap-4 py-8 text-[0.8125rem] text-ink/70 sm:flex-row sm:items-center sm:justify-between">
            <p>CarCheck · mantenimiento y documentos por vehículo</p>
            <nav class="flex items-center gap-5" aria-label="Pie de página">
                <a href="#planes" class="lc-footerlink transition-colors hover:text-ink">Planes</a>
                <a href="{{ url('/admin/login') }}" class="lc-footerlink transition-colors hover:text-ink">Ya tengo cuenta</a>
                <span>{{ date('Y') }}</span>
            </nav>
        </div>
    </footer>
</body>
</html>
