<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="color: #0f766e;">¡Hola {{ $user->name }}!</h1>

    <p>
        Tu cuenta de <strong>CarCheck</strong> está lista. Desde aquí podrás llevar el
        mantenimiento de tus vehículos, registrar servicios y costos, y recibir recordatorios
        de los próximos vencimientos.
    </p>

    <p>
        <a href="{{ config('app.frontend_url') }}"
           style="display: inline-block; padding: 10px 18px; background: #0f766e; color: #ffffff; border-radius: 6px; text-decoration: none;">
            Entrar a CarCheck
        </a>
    </p>

    <p>Gracias por confiar en nosotros,<br>{{ config('app.name') }}</p>
</body>
</html>
