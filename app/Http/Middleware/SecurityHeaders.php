<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad HTTP que faltaban (hallazgo de QA, sección 2.1):
 * CSP, HSTS, Referrer-Policy y Permissions-Policy. X-Frame-Options y
 * X-Content-Type-Options ya las pone el servidor web en producción, pero se
 * agregan aquí también para que existan sin depender de esa configuración
 * externa (por ejemplo, en un entorno local sin ese nginx delante).
 *
 * El sitio usa scripts/estilos inline en casi todas las vistas -- quitar
 * 'unsafe-inline' de golpe rompería el panel y el micrositio completos, así
 * que la política se enfoca en lo que sí se puede endurecer sin ese riesgo:
 * restringir a qué dominios externos se puede cargar contenido, bloquear
 * plugins/objetos, e impedir que la página se enmarque desde otro origen.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // connect.facebook.net/*.facebook.com: SDK de "Conectar WhatsApp"
        // (Embedded Signup, admin/empresas/whatsapp.blade.php) -- se había
        // quedado fuera de una primera versión de esta política y hubiera
        // roto ese botón en silencio (el script ni siquiera se carga, sin
        // error visible más que en la consola del navegador).
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://maps.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://connect.facebook.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "connect-src 'self' https://maps.googleapis.com https://graph.facebook.com https://connect.facebook.net https://cdn.jsdelivr.net",
            "frame-src 'self' https://maps.google.com https://www.facebook.com https://web.facebook.com",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        // Modo "solo reporte" temporal: dos veces ya se detectaron dominios
        // externos legítimos (connect.facebook.net, cdn.jsdelivr.net en
        // style-src) que faltaban en la lista blanca sin poder probar en un
        // navegador real desde aquí. En este modo el navegador AVISA en la
        // consola/DevTools cuál regla se violaría, pero nunca bloquea nada
        // -- cero riesgo de romper el panel mientras se termina de navegar
        // por todas las pantallas. Cuando se confirme que ya no aparecen más
        // avisos, cambiar el nombre de esta cabecera a
        // 'Content-Security-Policy' para que empiece a bloquear de verdad.
        $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // Nunca sobre HTTP: forzar HTTPS en un entorno local (o si la app
        // corriera temporalmente sin certificado) dejaría el navegador sin
        // poder volver a entrar hasta que expire la marca.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
