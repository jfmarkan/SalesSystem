<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación OTP</title>
</head>
<body>
    <h2>Tu código de verificación OTP</h2>
    <p>{{ $user->first_name }}, usa este código para verificar tu cuenta:</p>
    <h3>{{ $user->otp }}</h3>
    <p>Este código expira en 10 minutos.</p>
</body>
</html>