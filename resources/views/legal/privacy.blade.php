<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad — {{ $businessName }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f4f6f9;
            color: #1f2933;
            line-height: 1.6;
        }
        .privacy-hero {
            background: linear-gradient(160deg, #111b21 0%, #075e54 55%, #128c7e 100%);
            color: #fff;
            padding: 3rem 1.5rem 4rem;
            text-align: center;
        }
        .privacy-hero .icon-badge {
            width: 56px;
            height: 56px;
            margin: 0 auto 1rem;
            border-radius: 16px;
            background: rgba(255,255,255,.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #25d366;
        }
        .privacy-hero h1 {
            margin: 0 0 .5rem;
            font-size: 1.85rem;
            font-weight: 700;
        }
        .privacy-hero p {
            margin: 0;
            color: rgba(233,237,239,.82);
            font-size: .95rem;
        }
        .privacy-wrap {
            max-width: 760px;
            margin: -2.5rem auto 3rem;
            padding: 0 1.25rem;
        }
        .privacy-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(17,27,33,.1);
            border: 1px solid #e8ecf1;
            padding: 2rem 2rem 2.5rem;
        }
        .privacy-updated {
            font-size: .8rem;
            color: #667781;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .privacy-toc {
            display: grid;
            gap: .4rem;
            margin: 0 0 2rem;
            padding: 1rem 1.15rem;
            background: #f0fdf4;
            border: 1px solid #d1fae5;
            border-radius: 12px;
        }
        .privacy-toc a {
            color: #075e54;
            text-decoration: none;
            font-size: .85rem;
            font-weight: 600;
        }
        .privacy-toc a:hover { text-decoration: underline; }
        section.privacy-section {
            margin-bottom: 1.9rem;
            scroll-margin-top: 1.5rem;
        }
        section.privacy-section h2 {
            display: flex;
            align-items: center;
            gap: .55rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: #111b21;
            margin: 0 0 .6rem;
        }
        section.privacy-section h2 i {
            color: #128c7e;
            font-size: .95rem;
            width: 20px;
            text-align: center;
        }
        section.privacy-section p,
        section.privacy-section li {
            font-size: .92rem;
            color: #3b4a54;
        }
        section.privacy-section ul {
            margin: .5rem 0 0;
            padding-left: 1.3rem;
        }
        section.privacy-section li { margin-bottom: .35rem; }
        .privacy-rights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: .6rem;
            margin-top: .75rem;
        }
        .privacy-right-pill {
            background: #f8fafc;
            border: 1px solid #e8ecf1;
            border-radius: 10px;
            padding: .7rem .8rem;
            font-size: .82rem;
            font-weight: 600;
            color: #111b21;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .privacy-right-pill i { color: #25d366; }
        .privacy-note {
            margin-top: .5rem;
            padding: .85rem 1rem;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            font-size: .82rem;
            color: #92400e;
        }
        .privacy-contact-box {
            background: #f0fdf4;
            border: 1px solid #d1fae5;
            border-radius: 12px;
            padding: 1rem 1.15rem;
            font-size: .88rem;
            color: #166534;
        }
        .privacy-contact-box strong { color: #14532d; }
        .privacy-footer {
            text-align: center;
            font-size: .78rem;
            color: #8696a0;
            padding: 0 1.5rem 3rem;
        }
        .privacy-footer a { color: #128c7e; text-decoration: none; }
        .privacy-back {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-top: 1rem;
            font-size: .82rem;
            font-weight: 600;
            color: #128c7e;
            text-decoration: none;
        }
        .privacy-back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="privacy-hero">
        <div class="icon-badge"><i class="fas fa-user-shield"></i></div>
        <h1>Política de Privacidad y Protección de Datos</h1>
        <p>{{ $businessName }}</p>
    </header>

    <div class="privacy-wrap">
        <div class="privacy-card">
            <div class="privacy-updated">
                <i class="fas fa-clock"></i> Última actualización: {{ now()->translatedFormat('d \d\e F \d\e Y') }}
            </div>

            <nav class="privacy-toc">
                <a href="#responsable">1. ¿Quién es el responsable de tus datos?</a>
                <a href="#datos">2. ¿Qué datos recopilamos?</a>
                <a href="#finalidad">3. ¿Para qué usamos tus datos?</a>
                <a href="#terceros">4. ¿Con quién compartimos tus datos?</a>
                <a href="#almacenamiento">5. ¿Dónde y por cuánto tiempo se guardan?</a>
                <a href="#derechos">6. Tus derechos como titular de los datos</a>
                <a href="#seguridad">7. Seguridad de la información</a>
                <a href="#menores">8. Menores de edad</a>
                <a href="#cambios">9. Cambios a esta política</a>
                <a href="#contacto">10. Contacto</a>
            </nav>

            <p style="font-size:.92rem;color:#3b4a54">
                En {{ $businessName }} respetamos tu privacidad y cumplimos con la
                <strong>Ley Orgánica de Protección de Datos Personales del Ecuador (LOPDP)</strong>. Este documento
                explica de forma clara qué información recopilamos cuando nos escribes por WhatsApp o realizas un
                pedido, para qué la usamos y qué derechos tienes sobre ella.
            </p>

            <section class="privacy-section" id="responsable">
                <h2><i class="fas fa-building"></i> 1. ¿Quién es el responsable de tus datos?</h2>
                <p>
                    <strong>{{ $businessName }}</strong> es el responsable del tratamiento de tus datos personales
                    conforme al Art. 3 de la LOPDP.
                    @if($whatsappNumber)
                        Puedes contactarnos por WhatsApp al <strong>{{ $whatsappNumber }}</strong>
                    @endif
                    @if($contactEmail)
                        {{ $whatsappNumber ? ' o al correo ' : 'Puedes contactarnos al correo ' }}<strong>{{ $contactEmail }}</strong>
                    @endif
                    para cualquier consulta sobre el tratamiento de tu información.
                </p>
            </section>

            <section class="privacy-section" id="datos">
                <h2><i class="fas fa-database"></i> 2. ¿Qué datos recopilamos?</h2>
                <p>Cuando interactúas con nuestro chatbot de WhatsApp o realizas un pedido, podemos recopilar:</p>
                <ul>
                    <li>Nombre completo y número de teléfono (WhatsApp).</li>
                    <li>Dirección de entrega y referencias, cuando pides delivery.</li>
                    <li>Historial de pedidos, productos comprados y preferencias.</li>
                    <li>Método de pago elegido y comprobantes de pago que nos envíes (no almacenamos números de tarjeta).</li>
                    <li>Datos de facturación (cédula/RUC, razón social) si solicitas factura.</li>
                    <li>Mensajes de la conversación con el bot o con nuestro equipo, para dar seguimiento a tu pedido.</li>
                </ul>
            </section>

            <section class="privacy-section" id="finalidad">
                <h2><i class="fas fa-bullseye"></i> 3. ¿Para qué usamos tus datos?</h2>
                <ul>
                    <li>Procesar, confirmar y entregar tus pedidos.</li>
                    <li>Brindarte atención al cliente y responder tus consultas.</li>
                    <li>Enviarte promociones, ofertas y novedades de nuestros productos.</li>
                    <li>Mejorar nuestro catálogo, tiempos de entrega y calidad de servicio.</li>
                    <li>Cumplir obligaciones legales y tributarias (facturación).</li>
                </ul>
                <p>La base legal de este tratamiento es tu <strong>consentimiento</strong>, que otorgas al escribirnos
                    por WhatsApp y continuar usando nuestro servicio, así como la <strong>ejecución del pedido</strong>
                    que nos solicitas.</p>
            </section>

            <section class="privacy-section" id="terceros">
                <h2><i class="fas fa-people-arrows"></i> 4. ¿Con quién compartimos tus datos?</h2>
                <p>Tus datos pueden compartirse únicamente con:</p>
                <ul>
                    <li><strong>Proveedores aliados</strong> que participan en la ejecución de tu pedido (ej. servicios de delivery/mensajería, sucursales del negocio).</li>
                    <li><strong>Meta / WhatsApp Business Platform</strong>, como intermediario técnico necesario para enviar y recibir tus mensajes.</li>
                    <li><strong>Proveedores tecnológicos</strong> que nos dan soporte de hosting y plataforma (ver sección 5).</li>
                    <li>Autoridades competentes, cuando la ley lo exija.</li>
                </ul>
                <p>No vendemos tus datos personales a terceros.</p>
            </section>

            <section class="privacy-section" id="almacenamiento">
                <h2><i class="fas fa-server"></i> 5. ¿Dónde y por cuánto tiempo se guardan?</h2>
                <p>
                    Tu información se almacena en un hosting proporcionado por nuestro proveedor de servicios
                    tecnológicos <strong>Siglo Tecnológico</strong>, quien actúa como encargado del tratamiento bajo
                    las instrucciones de {{ $businessName }} y con las medidas de seguridad correspondientes.
                </p>
                <p>
                    Conservamos tus datos mientras mantengas una relación comercial activa con nosotros y, luego,
                    durante el plazo necesario para cumplir obligaciones legales, contables o tributarias. Puedes
                    solicitar la eliminación de tus datos en cualquier momento (ver sección 6).
                </p>
            </section>

            <section class="privacy-section" id="derechos">
                <h2><i class="fas fa-scale-balanced"></i> 6. Tus derechos como titular de los datos</h2>
                <p>Conforme a la LOPDP, tienes derecho a:</p>
                <div class="privacy-rights-grid">
                    <div class="privacy-right-pill"><i class="fas fa-eye"></i> Acceso</div>
                    <div class="privacy-right-pill"><i class="fas fa-pen"></i> Rectificación</div>
                    <div class="privacy-right-pill"><i class="fas fa-trash"></i> Eliminación</div>
                    <div class="privacy-right-pill"><i class="fas fa-ban"></i> Oposición</div>
                    <div class="privacy-right-pill"><i class="fas fa-file-export"></i> Portabilidad</div>
                    <div class="privacy-right-pill"><i class="fas fa-robot"></i> No decisiones automatizadas</div>
                </div>
                <p style="margin-top:.85rem">
                    Para ejercer cualquiera de estos derechos, escríbenos por WhatsApp o al correo indicado en la
                    sección de contacto. Responderemos tu solicitud dentro de los plazos establecidos por la ley.
                </p>
            </section>

            <section class="privacy-section" id="seguridad">
                <h2><i class="fas fa-lock"></i> 7. Seguridad de la información</h2>
                <p>
                    Aplicamos medidas técnicas y organizativas razonables (acceso restringido, cifrado en tránsito,
                    respaldo de información) para proteger tus datos contra pérdida, uso indebido o acceso no
                    autorizado.
                </p>
            </section>

            <section class="privacy-section" id="menores">
                <h2><i class="fas fa-child-reaching"></i> 8. Menores de edad</h2>
                <p>
                    Nuestros servicios están dirigidos a personas mayores de edad. Si eres menor de edad, te pedimos
                    utilizar este canal solo con la supervisión de un padre, madre o representante legal.
                </p>
            </section>

            <section class="privacy-section" id="cambios">
                <h2><i class="fas fa-rotate"></i> 9. Cambios a esta política</h2>
                <p>
                    Podemos actualizar esta política para reflejar cambios legales u operativos. La fecha de la
                    última actualización siempre estará visible al inicio de este documento.
                </p>
            </section>

            <section class="privacy-section" id="contacto">
                <h2><i class="fas fa-headset"></i> 10. Contacto</h2>
                <div class="privacy-contact-box">
                    @if($whatsappNumber)
                        <div><i class="fab fa-whatsapp"></i> WhatsApp: <strong>{{ $whatsappNumber }}</strong></div>
                    @endif
                    @if($contactEmail)
                        <div style="margin-top:.35rem"><i class="fas fa-envelope"></i> Correo: <strong>{{ $contactEmail }}</strong></div>
                    @endif
                </div>
            </section>

            <div class="privacy-note">
                <i class="fas fa-triangle-exclamation"></i>
                Este documento es una plantilla informativa basada en la Ley Orgánica de Protección de Datos
                Personales del Ecuador y no constituye asesoría legal. Recomendamos que sea revisado por un
                profesional del derecho antes de su publicación definitiva, y completar los datos legales
                (razón social, RUC, domicilio) del responsable del tratamiento.
            </div>

            <a href="{{ url('/') }}" class="privacy-back"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
        </div>
    </div>

    <div class="privacy-footer">
        &copy; {{ date('Y') }} {{ $businessName }} · Todos los derechos reservados
    </div>
</body>
</html>
