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

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://maps.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://cdnjs.cloudflare.com",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net https://cdnjs.cloudflare.com",
            "connect-src 'self' https://maps.googleapis.com",
            "frame-src 'self' https://maps.google.com",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
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
