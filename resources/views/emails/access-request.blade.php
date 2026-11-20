<p style="font-family: system-ui, sans-serif; font-size: 15px; line-height: 1.6;">
    Nueva solicitud de acceso desde la landing de CarCheck.
</p>

<table cellpadding="6" style="font-family: system-ui, sans-serif; font-size: 15px; border-collapse: collapse;">
    <tr><td><strong>Nombre</strong></td><td>{{ $request['name'] }}</td></tr>
    <tr><td><strong>Correo</strong></td><td>{{ $request['email'] }}</td></tr>
    <tr><td><strong>Empresa</strong></td><td>{{ $request['company'] }}</td></tr>
    <tr><td><strong>Flota</strong></td><td>{{ $request['fleet'] ?: 'Sin indicar' }}</td></tr>
</table>

@if ($request['message'])
    <p style="font-family: system-ui, sans-serif; font-size: 15px; line-height: 1.6; white-space: pre-line;">
        {{ $request['message'] }}
    </p>
@endif
