<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="color: #0f766e;">{{ $title }}</h1>

    <p>{!! nl2br(e($body)) !!}</p>

    @if ($url)
        <p>
            <a href="{{ rtrim(config('app.url'), '/').'/'.ltrim($url, '/') }}"
               style="display: inline-block; padding: 10px 18px; background: #0f766e; color: #ffffff; border-radius: 6px; text-decoration: none;">
                Ver en CarCheck
            </a>
        </p>
    @endif

    <p style="font-size: 12px; color: #64748b;">
        Recibes este correo porque tienes los avisos por email activos en CarCheck. Puedes
        cambiarlo desde la sección Notificaciones de la app.
    </p>
</body>
</html>
