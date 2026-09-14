<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código para recuperar tu cuenta</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f0f2f5; padding: 20px; line-height: 1.6; }
        .email-container { max-width: 480px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .email-header { background: linear-gradient(135deg, #25d366 0%, #128c7e 100%); color: #ffffff; padding: 28px 20px; text-align: center; }
        .email-header .icon { font-size: 40px; margin-bottom: 8px; }
        .email-header h1 { font-size: 20px; font-weight: 600; }
        .email-body { padding: 30px 24px; text-align: center; }
        .code-box { display: inline-block; margin: 12px 0 20px; padding: 16px 28px; border-radius: 8px; background-color: #f8f9fa; border: 1px dashed #25d366; font-size: 32px; font-weight: 700; letter-spacing: .3em; color: #128c7e; }
        .email-body p { color: #555555; font-size: 14px; margin-top: 4px; }
        .email-footer { background-color: #f8f9fa; padding: 18px; text-align: center; color: #888888; font-size: 12px; border-top: 1px solid #e5e5e5; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <div class="icon">🔐</div>
            <h1>{{ $businessName }}</h1>
        </div>
        <div class="email-body">
            <p>Usa este código para crear una nueva contraseña:</p>
            <div class="code-box">{{ $code }}</div>
            <p>Vence en 10 minutos. No lo compartas con nadie.</p>
        </div>
        <div class="email-footer">
            <p>Si no solicitaste este código, puedes ignorar este correo.</p>
        </div>
    </div>
</body>
</html>
