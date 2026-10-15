<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: system-ui, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="color: #0f766e;">Te invitaron a {{ $invitation->organization->name }}</h1>

    <p>
        Te han invitado a unirte a <strong>{{ $invitation->organization->name }}</strong> en
        CarCheck con el rol <strong>{{ $invitation->role }}</strong>.
    </p>

    <p>
        <a href="{{ $acceptUrl }}"
           style="display: inline-block; padding: 10px 18px; background: #0f766e; color: #ffffff; border-radius: 6px; text-decoration: none;">
            Aceptar invitación
        </a>
    </p>

    <p style="color: #64748b; font-size: 14px;">
        La invitación vence el {{ $invitation->expires_at->format('d/m/Y') }}. Si no esperabas este
        correo, puedes ignorarlo.
    </p>
</body>
</html>
