<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Mensaje - WhatsApp Bot</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .email-header .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .email-body {
            padding: 30px 20px;
        }
        .info-section {
            background-color: #f8f9fa;
            border-left: 4px solid #25d366;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-row {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            color: #333333;
            min-width: 140px;
            font-size: 14px;
        }
        .info-value {
            color: #666666;
            flex: 1;
            font-size: 14px;
        }
        .message-content {
            background-color: #dcf8c6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #25d366;
        }
        .message-content-text {
            color: #333333;
            font-size: 15px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background-color: #25d366;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #128c7e;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666666;
            font-size: 12px;
            border-top: 1px solid #e5e5e5;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-text {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .badge-image {
            background-color: #fff3e0;
            color: #f57c00;
        }
        .badge-audio {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        .badge-video {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .badge-document {
            background-color: #fce4ec;
            color: #c2185b;
        }
        .badge-location {
            background-color: #e0f2f1;
            color: #00796b;
        }
        .badge-interactive {
            background-color: #fff9c4;
            color: #f57f17;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="icon">📱</div>
            <h1>Nuevo Mensaje Recibido</h1>
            <p style="opacity: 0.9; font-size: 14px;">Bot de WhatsApp - Sistema de Monitoreo</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <!-- Información del Contacto -->
            <div class="info-section">
                <div class="info-row">
                    <span class="info-label">👤 Contacto:</span>
                    <span class="info-value">{{ $contactName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">📱 Teléfono:</span>
                    <span class="info-value">{{ $phoneNumber }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">📝 Tipo:</span>
                    <span class="info-value">
                        <span class="badge
                            @if($messageType === 'text') badge-text
                            @elseif($messageType === 'image') badge-image
                            @elseif($messageType === 'audio') badge-audio
                            @elseif($messageType === 'video') badge-video
                            @elseif($messageType === 'document') badge-document
                            @elseif($messageType === 'location') badge-location
                            @else badge-interactive
                            @endif">
                            {{ ucfirst($messageType) }}
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">🕐 Fecha/Hora:</span>
                    <span class="info-value">{{ $timestamp }}</span>
                </div>
            </div>

            <!-- Contenido del Mensaje -->
            <div class="message-content">
                <div style="font-weight: 600; color: #333; margin-bottom: 10px; font-size: 14px;">
                    Mensaje:
                </div>
                <div class="message-content-text">{{ $messageContent }}</div>
            </div>

            <!-- Botón de Acción -->
            <div class="button-container">
                <a href="{{ route('admin.chats') }}" class="button">
                    Ver Conversaciones →
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>Este es un mensaje automático del sistema de monitoreo de WhatsApp Bot.</p>
            <p style="margin-top: 8px;">© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
