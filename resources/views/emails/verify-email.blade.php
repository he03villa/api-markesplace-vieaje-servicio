<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 20px;
        }

        .container {
            background: white;
            max-width: 500px;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
        }

        .btn {
            display: inline-block;
            background: #6366f1;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }

        .footer {
            color: #888;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Verifica tu correo</h2>
        <p>Haz clic en el botón para verificar tu cuenta. El link expira en <strong>60 minutos</strong>.</p>

        <a href="{{ $verificationUrl }}" class="btn">Verificar mi cuenta</a>

        <p class="footer">Si no creaste una cuenta, ignora este correo.</p>
    </div>
</body>

</html>