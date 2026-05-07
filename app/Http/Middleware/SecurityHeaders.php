<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — añade headers HTTP de seguridad a TODAS las respuestas.
 *
 * Implementa defensa en profundidad contra:
 *  - XSS (CSP restrictiva)
 *  - Clickjacking (X-Frame-Options DENY + frame-ancestors 'none')
 *  - MIME sniffing (X-Content-Type-Options nosniff)
 *  - Downgrade HTTPS→HTTP (HSTS en producción)
 *  - Leak de Referrer cross-origin (Referrer-Policy)
 *  - Acceso a APIs sensibles del navegador (Permissions-Policy)
 *  - Embedding cross-domain por Flash/PDF legacy (X-Permitted-Cross-Domain-Policies)
 *
 * IMPORTANTE — CSP por entorno:
 *  En desarrollo local, Vite dev server corre en `[::1]:5173` o `localhost:5173`
 *  (origen distinto al dominio Laragon). Para que HMR funcione, debemos permitir
 *  conexiones script/style/connect al dev server. En PRODUCCIÓN, los assets
 *  se sirven desde `public/build/` (mismo origen) y NO necesitan esta excepción.
 *
 *  Por eso `buildCsp()` recibe `$isProd` y construye la cabecera distinta:
 *   - DEV:  permite Vite dev server (localhost:5173, [::1]:5173, ws://...)
 *   - PROD: 'self' estricto
 *
 * NOTA sobre Inertia.js:
 *  Inertia inyecta la respuesta inicial como JSON dentro de un atributo HTML
 *  vía data-attributes — no requiere `unsafe-inline` para scripts. Los scripts
 *  son cargados por el bundle de Vite, no inline.
 *  Sin embargo, Tailwind y algunos componentes Vue inyectan estilos inline durante
 *  desarrollo, por eso `style-src 'unsafe-inline'`. En un futuro endurecimiento
 *  se pueden usar nonces si se elimina toda inline-style.
 *
 * Referencias:
 *  - OWASP Secure Headers Project
 *  - https://content-security-policy.com/
 *  - https://owasp.org/www-project-secure-headers/
 */
class SecurityHeaders
{
    /**
     * Orígenes adicionales permitidos cuando estamos en LOCAL para que Vite dev server
     * (HMR + WebSocket + asset loading) funcione.
     *
     * IMPORTANTE: NO incluimos `[::1]:5173` (IPv6 loopback) porque el parser de CSP
     * de Chrome NO procesa correctamente los corchetes IPv6 en host-source — es un
     * edge-case conocido. Por eso `vite.config.js` está configurado con
     * `server.host: '127.0.0.1'` para forzar a Vite a escuchar SOLO en IPv4.
     */
    private const VITE_DEV_HTTP = 'http://localhost:5173 http://127.0.0.1:5173';
    private const VITE_DEV_WS = 'ws://localhost:5173 ws://127.0.0.1:5173';

    /**
     * Construye la cabecera Content-Security-Policy.
     *
     * Estrategia:
     *  - PRODUCCIÓN: empezar restrictivo, solo 'self' + Google Fonts + data/blob para imágenes.
     *  - LOCAL/DEV: agregar Vite dev server a script/style/connect/font sources para que HMR funcione.
     */
    private function buildCsp(bool $isProd): string
    {
        // Suffix con orígenes de Vite — vacío en producción, lleno en dev
        $viteHttp = $isProd ? '' : ' ' . self::VITE_DEV_HTTP;
        $viteWs = $isProd ? '' : ' ' . self::VITE_DEV_WS;

        return implode('; ', [
            // Default fallback: solo recursos del propio origen
            "default-src 'self'",
            // Scripts: propio origen + 'unsafe-inline' para Inertia (data-page).
            // En DEV agregamos Vite dev server. En un futuro endurecimiento usar nonces.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'{$viteHttp}",
            // Estilos: propio origen + inline (Tailwind JIT) + Google Fonts + (DEV) Vite
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$viteHttp}",
            // Fonts: propio origen + Google Fonts CDN + data: URIs + (DEV) Vite
            "font-src 'self' https://fonts.gstatic.com data:{$viteHttp}",
            // Imágenes: propio origen + data: (charts toBase64) + blob: (URL.createObjectURL para download)
            "img-src 'self' data: blob:{$viteHttp}",
            // Conexiones AJAX/fetch: propio origen + (DEV) Vite HTTP y WebSocket para HMR
            "connect-src 'self'{$viteHttp}{$viteWs}",
            // Anti-clickjacking: nadie puede embebernos en iframe
            "frame-ancestors 'none'",
            // Forms solo pueden enviar al propio origen
            "form-action 'self'",
            // <base href> solo puede ser propio
            "base-uri 'self'",
            // Bloquear plugins legacy (Flash, Java, etc.)
            "object-src 'none'",
            // Workers solo desde propio origen
            "worker-src 'self' blob:",
        ]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isProd = app()->isProduction();

        $response->headers->set('Content-Security-Policy', $this->buildCsp($isProd));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), interest-cohort=(), payment=(), usb=()'
        );
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // HSTS solo en producción HTTPS — en HTTP local rompería el navegador.
        // 2 años + includeSubDomains + preload (calidad para hsts.preload.org)
        if ($isProd) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload'
            );
        }

        // ── Cache-Control: no-store en TODAS las respuestas web ──────────
        //
        // PROBLEMA QUE ARREGLA: tras logout, presionar "atrás" en el navegador
        // mostraba el HTML cacheado del dashboard (test 1.5 fallaba).
        //
        // POR QUÉ EN TODAS (no solo autenticadas):
        //  Chrome usa bfcache (back/forward cache) que guarda el estado COMPLETO
        //  de la página, incluso ignorando algunos headers. La forma más confiable
        //  de deshabilitar bfcache es enviar `no-store` SIEMPRE en las respuestas web.
        //  Los assets estáticos (CSS/JS compilados, imágenes públicas) NO pasan
        //  por este middleware — los sirve Apache/Nginx directamente con cache normal.
        //
        // POR QUÉ NO IMPACTA UX:
        //  - HTML de páginas tiene <1s de TTFB en este sistema
        //  - Los assets reales (JS bundle, CSS) sí se cachean normalmente
        //  - El "ahorro" de cachear HTML es mínimo (~50ms) y no vale el riesgo
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Quitar header Server/X-Powered-By si los settings lo añaden.
        // (Apache/Nginx también deben configurarse para esto en producción.)
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
