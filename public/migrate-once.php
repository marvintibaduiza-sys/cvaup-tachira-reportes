<?php

/**
 * Script standalone para ejecutar migrate en el servidor SIN necesitar SSH.
 *
 * USO:
 *  1. Subir este archivo a la carpeta `public/` del servidor productivo (via FTP/cPanel)
 *  2. Abrir en el navegador:
 *     https://TU-DOMINIO/migrate-once.php?token=b3dfdf07f30042319e6d797ef49d247b672d3e92ee03957c
 *  3. Verás el output del migrate (HTML simple)
 *  4. ⚠️ ELIMINAR este archivo del servidor inmediatamente después de usarlo
 *
 * SEGURIDAD:
 *  - Requiere token hardcodeado (cualquiera que conozca la URL+token puede correrlo)
 *  - Por eso es "once": después de usarlo, BÓRRALO del servidor (cPanel > File Manager > Delete)
 *  - No tiene auth Laravel porque el bootstrap es manual y nos importa que funcione SIEMPRE,
 *    incluso si los modelos están desfasados respecto al schema
 */

// ── 1. Validación de token ──────────────────────────────────────────────
$TOKEN_VALIDO = 'b3dfdf07f30042319e6d797ef49d247b672d3e92ee03957c';

$tokenRecibido = $_GET['token'] ?? '';

if (!hash_equals($TOKEN_VALIDO, $tokenRecibido)) {
    http_response_code(403);
    echo "<h1>403 — Token inválido</h1>";
    exit;
}

// ── 2. Bootstrap Laravel (manual, no como request HTTP normal) ──────────
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ── 3. Ejecutar artisan migrate ─────────────────────────────────────────
$bufferMigrate = new \Symfony\Component\Console\Output\BufferedOutput();
$exitMigrate = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true], $bufferMigrate);

// ── 4. Limpieza de caches ───────────────────────────────────────────────
$bufferClear = new \Symfony\Component\Console\Output\BufferedOutput();
\Illuminate\Support\Facades\Artisan::call('optimize:clear', [], $bufferClear);

// ── 5. Render del resultado ─────────────────────────────────────────────
$outputMigrate = $bufferMigrate->fetch();
$outputClear = $bufferClear->fetch();

$ok = ($exitMigrate === 0);
$bgColor = $ok ? '#0f172a' : '#7f1d1d';
$titleColor = $ok ? '#22c55e' : '#fca5a5';
$titleText = $ok ? '✓ Migrate ejecutado correctamente' : '✗ Migrate falló';

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Migrate Once — CVAUP</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: <?= $bgColor ?>;
            color: #e2e8f0;
            padding: 24px;
            line-height: 1.5;
        }
        h1 { color: <?= $titleColor ?>; margin-top: 0; }
        h2 { color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 4px; margin-top: 24px; }
        pre {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 4px;
            padding: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .warn {
            background: #7f1d1d;
            border: 2px solid #ef4444;
            padding: 12px;
            border-radius: 4px;
            margin-top: 24px;
        }
        .warn strong { color: #fef2f2; }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($titleText) ?></h1>
    <p>Exit code: <strong><?= $exitMigrate ?></strong></p>

    <h2>📋 Output de `php artisan migrate --force`</h2>
    <pre><?= htmlspecialchars($outputMigrate ?: '(sin output)') ?></pre>

    <h2>🧹 Output de `php artisan optimize:clear`</h2>
    <pre><?= htmlspecialchars($outputClear ?: '(sin output)') ?></pre>

    <div class="warn">
        <strong>⚠️ SEGURIDAD CRÍTICA:</strong> Este archivo (`public/migrate-once.php`)
        debe ser <strong>ELIMINADO del servidor</strong> inmediatamente después de usarlo.
        Si alguien más descubre la URL+token, podría re-ejecutar migrate.
        <br><br>
        Para eliminarlo: cPanel → File Manager → public/ → migrate-once.php → Delete
        <br>
        (o vía FTP: borra el archivo en la carpeta `public/`)
    </div>
</body>
</html>
