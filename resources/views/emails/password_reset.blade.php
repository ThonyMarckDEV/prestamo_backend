<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Cambio de Contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background-color: #dc2626; color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .button { display: inline-block; padding: 10px 20px; background-color: #dc2626; color: #ffffff; text-decoration: none; border-radius: 5px; }
        .button:hover { background-color: #b91c1c; }
        .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cambio de Contraseña</h1>
        </div>
        <div class="content">
            <p>Hola, {{ $user->datos->nombre }}</p>
            <p>Hemos recibido una solicitud para restablecer tu contraseña. Usa el siguiente enlace para cambiar tu contraseña:</p>
            <p style="text-align: center;">
                <a href="{{ $resetUrl }}" class="button">Cambiar Contraseña</a>
            </p>
            <p>Este enlace expirará en 10 minutos. Si no solicitaste este cambio, ignora este correo.</p>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} FicSullana. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>