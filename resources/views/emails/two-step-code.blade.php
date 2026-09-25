<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Código de verificação — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Código de verificação</h1>
    <p>Olá, {{ $name }}!</p>
    <p>Use o código abaixo para concluir seu acesso ao sistema {{ config('app.name') }}:</p>
    <p style="font-size:32px;font-weight:bold;letter-spacing:6px">{{ $code }}</p>
    <p>Este código expira em <strong>10 minutos</strong>. Se não foi você, ignore este e-mail.</p>
</body>
</html>