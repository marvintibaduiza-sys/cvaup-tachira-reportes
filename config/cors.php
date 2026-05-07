<?php

/**
 * CORS — Cross-Origin Resource Sharing
 *
 * ESTADO ACTUAL: este sistema NO expone API cross-origin.
 * Inertia.js + las rutas web están en MISMO origen — el navegador ya las
 * permite por Same-Origin Policy sin necesidad de CORS.
 *
 * Esta configuración es RESTRICTIVA por defecto: deniega TODO cross-origin
 * salvo que se modifique deliberadamente. Es defensa en profundidad:
 *  - Si en el futuro alguien crea una ruta API en /api/* sin pensar,
 *    el navegador externo NO podrá hacer fetch ni preflight desde otro origen.
 *  - El día que se necesite exponer una API, el dev TIENE QUE editar este archivo
 *    explícitamente — eso es bueno, evita exponer accidentalmente.
 *
 * Cuándo modificar:
 *  - Si vas a crear un endpoint público (ej: webhook, integración terceros).
 *  - Si vas a hacer una app móvil que consume tu API directamente.
 *  - Si vas a exponer un Swagger/OpenAPI.
 *
 * Cómo modificar (cuando aplique):
 *  - 'paths' => ['api/*'] para que aplique a las rutas API.
 *  - 'allowed_origins' => ['https://tu-app-movil.gob.ve'] (URLs específicas, NO '*').
 *  - 'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'] (los que necesites).
 *  - NUNCA pongas '*' en producción si supports_credentials es true.
 *
 * Referencias:
 *  - https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 *  - https://owasp.org/www-community/attacks/CORS_OriginHeaderScrutiny
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS Options
    |--------------------------------------------------------------------------
    |
    | The allowed_methods and allowed_headers options are case-insensitive.
    |
    | You don't need to provide both allowed_origins and allowed_origins_patterns.
    | If one of the strings passed matches, it is considered a valid origin.
    |
    */

    // Paths a los que SE APLICA HandleCors. Vacío = NO aplica a NADA.
    // Cuando agregues APIs, mete 'api/*' aquí.
    'paths' => [],

    // Métodos HTTP permitidos cross-origin. Vacío = NINGUNO.
    'allowed_methods' => [],

    // Orígenes permitidos. Vacío = NINGUNO. NUNCA usar '*' con credentials=true.
    'allowed_origins' => [],

    // Patrones regex de orígenes permitidos. Vacío = NINGUNO.
    'allowed_origins_patterns' => [],

    // Headers permitidos en requests cross-origin. Vacío = NINGUNO.
    'allowed_headers' => [],

    // Headers que el navegador puede leer en respuestas cross-origin.
    'exposed_headers' => [],

    // Tiempo de cache del preflight OPTIONS en segundos. 0 = sin cache.
    'max_age' => 0,

    // Si la API usará cookies/sesión cross-origin. FALSE en este sistema mono-origen.
    'supports_credentials' => false,

];
