<?php

use App\Http\Controllers\Api\UbicacionLookupController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportarController;
use App\Http\Controllers\FotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TecnicoController;
use App\Http\Controllers\UbicacionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // Export PDF del dashboard — recibe los 3 charts como data:image/png;base64 desde el cliente
    // Rate limit 10/min: DomPDF + img base64 = operación pesada en memoria.
    Route::post('/dashboard/exportar/pdf', [DashboardController::class, 'exportarPdf'])
        ->middleware('throttle:10,1')
        ->name('dashboard.exportar.pdf');

    // Fotos privadas — sirven el contenido del disco fotos_privadas con auth (CRITICAL #2)
    // Patrón regex en {filename} es defensa en profundidad además del check en el controller.
    Route::get('/fotos/reportes/{reporte}/{filename}', [FotoController::class, 'reporte'])
        ->where('filename', '[A-Za-z0-9_-]+\.webp')
        ->name('fotos.reporte');
    Route::get('/fotos/tecnicos/{tecnico}/{filename}', [FotoController::class, 'tecnico'])
        ->where('filename', '[A-Za-z0-9_-]+\.webp')
        ->name('fotos.tecnico');

    // Técnicos (Fase 8)
    Route::get('/tecnicos', [TecnicoController::class, 'index'])->name('tecnicos.index');
    Route::get('/tecnicos/crear', [TecnicoController::class, 'create'])->name('tecnicos.create');
    Route::post('/tecnicos', [TecnicoController::class, 'store'])->name('tecnicos.store');
    Route::get('/tecnicos/{tecnico}', [TecnicoController::class, 'show'])->name('tecnicos.show');
    Route::get('/tecnicos/{tecnico}/editar', [TecnicoController::class, 'edit'])->name('tecnicos.edit');
    Route::match(['put', 'patch'], '/tecnicos/{tecnico}', [TecnicoController::class, 'update'])->name('tecnicos.update');
    Route::delete('/tecnicos/{tecnico}', [TecnicoController::class, 'destroy'])->name('tecnicos.destroy');
    Route::patch('/tecnicos/{tecnico}/toggle', [TecnicoController::class, 'toggleEstado'])->name('tecnicos.toggle');

    // Reportes (Fase 10)
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/crear', [ReporteController::class, 'create'])->name('reportes.create');
    Route::post('/reportes', [ReporteController::class, 'store'])->name('reportes.store');
    Route::get('/reportes/{reporte}', [ReporteController::class, 'show'])->name('reportes.show');
    Route::get('/reportes/{reporte}/editar', [ReporteController::class, 'edit'])->name('reportes.edit');
    Route::match(['put', 'patch'], '/reportes/{reporte}', [ReporteController::class, 'update'])->name('reportes.update');
    Route::delete('/reportes/{reporte}', [ReporteController::class, 'destroy'])->name('reportes.destroy');
    // Fase 11: Exportar reporte individual a PDF
    // Rate limit 10/min: DomPDF con cintillo + fotos = pesado.
    Route::get('/reportes/{reporte}/pdf', [ReporteController::class, 'pdf'])
        ->middleware('throttle:10,1')
        ->name('reportes.pdf');

    // API JSON para selects en cascada (consumido por el form de reportes)
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/parroquias', [UbicacionLookupController::class, 'parroquias'])->name('parroquias');
        Route::get('/comunas', [UbicacionLookupController::class, 'comunas'])->name('comunas');
        Route::get('/consejos', [UbicacionLookupController::class, 'consejos'])->name('consejos');
    });

    // Generar Reportes (antes "Exportar") — pantalla UNIFICADA con 2 botones (PDF/Excel)
    // Forms (GET) son ligeros — sin throttle. Downloads (POST) son pesados → throttle 10/min.
    Route::get('/generar-reportes', [ExportarController::class, 'formUnificado'])->name('generar-reportes.index');
    Route::post('/generar-reportes/pdf', [ExportarController::class, 'pdfDownload'])
        ->middleware('throttle:10,1')
        ->name('generar-reportes.pdf');
    Route::post('/generar-reportes/excel', [ExportarController::class, 'excelDownload'])
        ->middleware('throttle:10,1')
        ->name('generar-reportes.excel');
    Route::get('/generar-reportes/preview', [ExportarController::class, 'preview'])->name('generar-reportes.preview');

    // Backup del sistema (Fase 13)
    // Backup = mysqldump + zip + cifrado AES-256 = pesado pero no abusivo en uso normal.
    // Throttle 10/hora — suficiente para uso institucional (en cron solo se hace 1/día).
    Route::get('/backups', [BackupController::class, 'index'])->name('backup.index');
    Route::get('/backups/generar', [BackupController::class, 'generarForm'])->name('backup.generar.form');
    Route::post('/backups/generar', [BackupController::class, 'generar'])
        ->middleware('throttle:10,60')
        ->name('backup.generar');
    Route::get('/backups/{filename}/descargar', [BackupController::class, 'descargar'])
        ->where('filename', '[A-Za-z0-9_\-\.]+\.zip')
        ->name('backup.descargar');
    Route::delete('/backups/{filename}', [BackupController::class, 'eliminar'])
        ->where('filename', '[A-Za-z0-9_\-\.]+\.zip')
        ->name('backup.eliminar');

    // Ubicaciones (Fase 9) — árbol jerárquico + CRUD por nivel + import Excel
    Route::get('/ubicaciones', [UbicacionController::class, 'index'])->name('ubicaciones.index');
    Route::get('/ubicaciones/children', [UbicacionController::class, 'children'])->name('ubicaciones.children');
    Route::get('/ubicaciones/buscar', [UbicacionController::class, 'buscar'])->name('ubicaciones.buscar');
    Route::get('/ubicaciones/flat-list', [UbicacionController::class, 'flatList'])->name('ubicaciones.flat-list');
    // Solo Excel: el dataset de ubicaciones (>2k filas) excede lo que DomPDF maneja de forma estable.
    // PDF queda reservado para reportes individuales (1 reporte = 1 documento oficial).
    // Rate limit 10/min: PhpSpreadsheet con 2k filas + cintillo es pesado.
    Route::match(['get', 'post'], '/ubicaciones/exportar/excel', [UbicacionController::class, 'exportarExcel'])
        ->middleware('throttle:10,1')
        ->name('ubicaciones.exportar.excel');

    Route::post('/ubicaciones/{tipo}', [UbicacionController::class, 'store'])
        ->where('tipo', 'municipio|parroquia|comuna|consejo')
        ->name('ubicaciones.store');
    Route::put('/ubicaciones/{tipo}/{id}', [UbicacionController::class, 'update'])
        ->where(['tipo' => 'municipio|parroquia|comuna|consejo', 'id' => '[0-9]+'])
        ->name('ubicaciones.update');
    Route::delete('/ubicaciones/{tipo}/{id}', [UbicacionController::class, 'destroy'])
        ->where(['tipo' => 'municipio|parroquia|comuna|consejo', 'id' => '[0-9]+'])
        ->name('ubicaciones.destroy');

    Route::get('/ubicaciones/importar', [UbicacionController::class, 'importarForm'])->name('ubicaciones.importar.form');
    // Import = parseo XLSX completo + 2 pasadas (preview + confirm). Throttle 5/min.
    Route::post('/ubicaciones/importar/preview', [UbicacionController::class, 'importarPreview'])
        ->middleware('throttle:5,1')
        ->name('ubicaciones.importar.preview');
    Route::post('/ubicaciones/importar/confirmar', [UbicacionController::class, 'importarConfirmar'])
        ->middleware('throttle:5,1')
        ->name('ubicaciones.importar.confirmar');
    Route::get('/ubicaciones/plantilla', [UbicacionController::class, 'plantillaDescargar'])->name('ubicaciones.plantilla');

    // Perfil (Breeze)
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');

    // ──────────────────────────────────────────────────────────────────────
    // Endpoint de setup en servidor: ejecuta migraciones pendientes desde el navegador.
    //
    // SEGURIDAD:
    //   - Requiere usuario autenticado (middleware 'auth')
    //   - Solo el email definido en ADMIN_EMAIL puede ejecutarlo
    //   - Requiere token = sha256(APP_KEY).substr(0,32) — el dueño del servidor lo conoce
    //     porque solo él tiene acceso al .env
    //   - Log de toda la operación en storage/logs/laravel.log
    //
    // USO:
    //   1. Genera tu token localmente:
    //      php artisan tinker --execute="echo substr(hash('sha256', config('app.key')), 0, 32);"
    //   2. Logueate en producción como admin
    //   3. Visita: https://servidor.com/admin/db-setup/{tu-token-de-32-chars}
    //   4. Verás el output de migrate + optimize:clear
    //
    // Tras usarlo en producción, conviene eliminarlo. No es para uso recurrente.
    // ──────────────────────────────────────────────────────────────────────
    Route::get('/admin/db-setup/{token}', function (string $token) {
        // 1) Solo el admin definido en .env
        if (auth()->user()->email !== env('ADMIN_EMAIL')) {
            abort(403, 'No autorizado. Solo el admin del sistema puede ejecutar este endpoint.');
        }

        // 2) Token = hash del APP_KEY (32 chars). Solo el dueño del servidor lo conoce.
        $tokenEsperado = substr(hash('sha256', config('app.key')), 0, 32);
        if (!hash_equals($tokenEsperado, $token)) {
            \Log::warning('admin/db-setup intento con token inválido', [
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);
            abort(403, 'Token inválido.');
        }

        // 3) Ejecutar migrate y optimize:clear
        $outputMigrate = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitMigrate = \Artisan::call('migrate', ['--force' => true], $outputMigrate);

        $outputClear = new \Symfony\Component\Console\Output\BufferedOutput();
        \Artisan::call('optimize:clear', [], $outputClear);

        $resultado = "[migrate exit code: {$exitMigrate}]\n\n"
            . "=== MIGRATE OUTPUT ===\n" . $outputMigrate->fetch()
            . "\n=== OPTIMIZE:CLEAR OUTPUT ===\n" . $outputClear->fetch();

        \Log::info('admin/db-setup ejecutado', [
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'exit_code' => $exitMigrate,
        ]);

        return response(
            "<!doctype html><html><head><meta charset='utf-8'><title>DB Setup — CVAUP</title>"
            . "<style>body{font-family:monospace;padding:24px;background:#0f172a;color:#e2e8f0}"
            . "h1{color:#22c55e}.ok{color:#22c55e}.err{color:#ef4444}</style></head><body>"
            . "<h1>✓ DB Setup ejecutado</h1>"
            . "<p class='" . ($exitMigrate === 0 ? 'ok' : 'err') . "'>"
            . "Exit code: {$exitMigrate} (" . ($exitMigrate === 0 ? 'OK' : 'ERROR') . ")</p>"
            . "<pre>" . htmlspecialchars($resultado) . "</pre>"
            . "<p><a href='/dashboard' style='color:#22c55e'>← Volver al dashboard</a></p>"
            . "</body></html>",
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    })->name('admin.db-setup');

    // ──────────────────────────────────────────────────────────────────────
    // Endpoint de deploy completo desde el navegador:
    //   1. git pull origin main
    //   2. php artisan migrate --force
    //   3. php artisan optimize:clear
    //
    // SEGURIDAD: solo el admin (verificado por email == ADMIN_EMAIL) puede ejecutarlo.
    // No requiere token — el sistema es mono-usuario, el email es la barrera suficiente.
    //
    // USO:
    //   https://servidor.com/admin/deploy
    //
    // NOTA: requiere que `exec()` esté habilitada en PHP. Algunos hostings
    // compartidos la bloquean. Si falla, usa el `.cpanel.yml` + "Deploy HEAD Commit".
    // ──────────────────────────────────────────────────────────────────────
    Route::get('/admin/deploy', function () {
        // Solo admin (verificado por email del .env)
        if (auth()->user()->email !== env('ADMIN_EMAIL')) {
            \Log::warning('admin/deploy intento de no-admin', [
                'user_id' => auth()->id(),
                'email' => auth()->user()->email,
                'ip' => request()->ip(),
            ]);
            abort(403, 'No autorizado. Solo el admin del sistema puede ejecutar deploy.');
        }

        // NOTA: este hosting bloquea exec() — no podemos hacer `git pull` desde PHP.
        // El usuario debe hacer "Update from Remote" en cPanel para traer código nuevo.
        // Este endpoint solo aplica migraciones pendientes y limpia caches —
        // usando Artisan API (que sí está permitida).

        // 1) php artisan migrate --force
        $bufferMigrate = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitMigrate = \Artisan::call('migrate', ['--force' => true], $bufferMigrate);
        $outputMigrate = $bufferMigrate->fetch();

        // 2) php artisan optimize:clear
        $bufferClear = new \Symfony\Component\Console\Output\BufferedOutput();
        \Artisan::call('optimize:clear', [], $bufferClear);
        $outputClear = $bufferClear->fetch();

        \Log::info('admin/deploy ejecutado', [
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'migrate_exit' => $exitMigrate,
        ]);

        $okMigrate = $exitMigrate === 0;

        $bg = $okMigrate ? '#0f172a' : '#7f1d1d';
        $titleColor = $okMigrate ? '#22c55e' : '#fca5a5';
        $titleText = $okMigrate ? '✓ Migraciones aplicadas' : '✗ Falló migrate';

        return response(
            "<!doctype html><html><head><meta charset='utf-8'><title>Deploy — CVAUP</title>"
            . "<style>body{font-family:monospace;padding:24px;background:{$bg};color:#e2e8f0;line-height:1.5;max-width:900px;margin:0 auto}"
            . "h1{color:{$titleColor};margin-top:0}"
            . "h2{color:#38bdf8;border-bottom:1px solid #334155;padding-bottom:4px;margin-top:24px}"
            . "pre{background:#1e293b;border:1px solid #334155;border-radius:4px;padding:12px;overflow-x:auto;white-space:pre-wrap}"
            . ".ok{color:#22c55e}.err{color:#ef4444}"
            . ".info{background:#1e3a8a;border:1px solid #3b82f6;padding:12px;border-radius:4px;margin-top:16px}"
            . "a{color:#22c55e}</style></head><body>"
            . "<h1>" . htmlspecialchars($titleText) . "</h1>"
            . "<h2>1. php artisan migrate --force</h2>"
            . "<p class='" . ($okMigrate ? 'ok' : 'err') . "'>Exit code: {$exitMigrate} (" . ($okMigrate ? 'OK' : 'ERROR') . ")</p>"
            . "<pre>" . htmlspecialchars($outputMigrate ?: '(sin migraciones pendientes)') . "</pre>"
            . "<h2>2. php artisan optimize:clear</h2>"
            . "<pre>" . htmlspecialchars($outputClear ?: '(sin output)') . "</pre>"
            . "<div class='info'>"
            . "<strong>ℹ️ Si pusheaste código nuevo a GitHub:</strong> primero haz <strong>\"Update from Remote\"</strong> en cPanel para traer el código, "
            . "y después vuelve a abrir esta URL para aplicar las nuevas migraciones. Este hosting bloquea exec() así que no podemos hacer git pull desde PHP."
            . "</div>"
            . "<p><a href='/dashboard'>← Volver al dashboard</a></p>"
            . "</body></html>",
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    })->name('admin.deploy');

    // ──────────────────────────────────────────────────────────────────────
    // Endpoint de reset completo de ubicaciones desde el navegador.
    //
    // QUÉ HACE (en orden, dentro de una transacción):
    //   1. DELETE fotos_reporte (FK desde reportes)
    //   2. DELETE reportes
    //   3. TRUNCATE tecnico_municipio (zonas asignadas se pierden)
    //   4. TRUNCATE consejos_comunales → comunas → parroquias → municipios → estados
    //   5. Reimporta TODO desde docs/CVAUP_TACHIRA.xlsx (debe estar en el repo)
    //
    // CUÁNDO USAR: cuando los datos territoriales en BD están mal y quieres
    // resetearlos a la verdad del Excel del repo. Útil cuando se actualiza
    // el Excel del repo y se quiere propagar el cambio a producción.
    //
    // ⚠️ DESTRUCTIVO: borra TODOS los reportes, ubicaciones y zonas asignadas.
    // Los técnicos y el admin se preservan.
    //
    // SEGURIDAD: solo admin (verificado por ADMIN_EMAIL del .env).
    //
    // USO:
    //   https://servidor.com/admin/reset-ubicaciones
    // ──────────────────────────────────────────────────────────────────────
    Route::get('/admin/reset-ubicaciones', function () {
        if (auth()->user()->email !== env('ADMIN_EMAIL')) {
            \Log::warning('admin/reset-ubicaciones intento de no-admin', [
                'user_id' => auth()->id(),
                'email' => auth()->user()->email,
                'ip' => request()->ip(),
            ]);
            abort(403, 'No autorizado.');
        }

        $excelPath = base_path('docs/CVAUP_TACHIRA.xlsx');

        if (!file_exists($excelPath)) {
            return response(
                "<!doctype html><html><head><meta charset='utf-8'><style>body{font-family:monospace;padding:24px;background:#7f1d1d;color:#fff}</style></head><body>"
                . "<h1>✗ Excel no encontrado</h1>"
                . "<p>No existe el archivo <code>docs/CVAUP_TACHIRA.xlsx</code> en el servidor.</p>"
                . "<p>Asegúrate de que esté en el repo y haz \"Update from Remote\" en cPanel antes de ejecutar este endpoint.</p>"
                . "</body></html>",
                500,
                ['Content-Type' => 'text/html; charset=utf-8']
            );
        }

        $errores = [];
        $stats = null;
        $reportesBorrados = 0;

        try {
            \DB::transaction(function () use (&$reportesBorrados) {
                // Quitar verificación de FK para poder truncar en orden libre
                \DB::statement('SET FOREIGN_KEY_CHECKS = 0');

                // 1) Borrar fotos primero (FK desde reportes)
                \DB::table('fotos_reporte')->delete();

                // 2) Borrar reportes (incluyendo soft-deleted)
                $reportesBorrados = \DB::table('reportes')->delete();

                // 3) Limpiar pivote técnico-municipio (las zonas asignadas se pierden)
                \DB::table('tecnico_municipio')->truncate();

                // 4) Truncar jerarquía territorial completa (hijos → padres)
                \DB::table('consejos_comunales')->truncate();
                \DB::table('comunas')->truncate();
                \DB::table('parroquias')->truncate();
                \DB::table('municipios')->truncate();
                \DB::table('estados')->truncate();

                \DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            });

            // 5) Re-importar desde el Excel del repo
            $importer = app(\App\Services\UbicacionesImporter::class);
            $stats = $importer->import($excelPath, dryRun: false, onProgress: null);

            \Log::info('admin/reset-ubicaciones ejecutado OK', [
                'user_id' => auth()->id(),
                'reportes_borrados' => $reportesBorrados,
                'stats' => $stats,
            ]);

        } catch (\Throwable $e) {
            $errores[] = $e->getMessage();
            \Log::error('admin/reset-ubicaciones FALLÓ', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $ok = empty($errores) && $stats !== null;
        $bg = $ok ? '#0f172a' : '#7f1d1d';
        $titleColor = $ok ? '#22c55e' : '#fca5a5';
        $titleText = $ok ? '✓ Ubicaciones reseteadas e importadas' : '✗ Reset falló';

        $html = "<!doctype html><html><head><meta charset='utf-8'><title>Reset Ubicaciones — CVAUP</title>"
            . "<style>body{font-family:monospace;padding:24px;background:{$bg};color:#e2e8f0;line-height:1.6;max-width:900px;margin:0 auto}"
            . "h1{color:{$titleColor};margin-top:0}"
            . "h2{color:#38bdf8;border-bottom:1px solid #334155;padding-bottom:4px;margin-top:24px}"
            . "pre{background:#1e293b;border:1px solid #334155;border-radius:4px;padding:12px;overflow-x:auto;white-space:pre-wrap}"
            . ".ok{color:#22c55e}.err{color:#ef4444}"
            . ".warn{background:#7f1d1d;border:2px solid #ef4444;padding:12px;border-radius:4px;margin-top:24px}"
            . "a{color:#22c55e}</style></head><body>"
            . "<h1>" . htmlspecialchars($titleText) . "</h1>";

        if ($ok && $stats) {
            $html .= "<h2>📊 Resultado del reset</h2>"
                . "<pre>"
                . "Reportes borrados:       {$reportesBorrados}\n"
                . "\n"
                . "Importación desde Excel: " . htmlspecialchars(basename($excelPath)) . "\n"
                . "  Estados creados:       " . $stats['creados']['estados'] . "\n"
                . "  Municipios creados:    " . $stats['creados']['municipios'] . "\n"
                . "  Parroquias creadas:    " . $stats['creados']['parroquias'] . "\n"
                . "  Comunas creadas:       " . $stats['creados']['comunas'] . "\n"
                . "  Consejos comunales:    " . $stats['creados']['consejos'] . "\n"
                . "\n"
                . "  Filas procesadas:      " . $stats['filas_procesadas'] . "\n"
                . "  Filas vacías saltadas: " . $stats['filas_saltadas_vacias'] . "\n"
                . "</pre>"
                . "<div class='warn'><strong>⚠️ ZONAS DE TÉCNICOS BORRADAS:</strong> Las zonas asignadas a los técnicos se perdieron. Hay que reasignarlas manualmente desde <a href='/tecnicos'>/tecnicos</a> → Editar cada uno → marcar municipios.</div>";
        } else {
            $html .= "<h2 class='err'>Errores</h2><pre>";
            foreach ($errores as $err) {
                $html .= htmlspecialchars($err) . "\n";
            }
            $html .= "</pre>";
        }

        $html .= "<p><a href='/dashboard'>← Volver al dashboard</a> | <a href='/tecnicos'>→ Ir a Técnicos a reasignar zonas</a></p>"
            . "</body></html>";

        return response($html, $ok ? 200 : 500, ['Content-Type' => 'text/html; charset=utf-8']);
    })->name('admin.reset-ubicaciones');
});

require __DIR__.'/auth.php';
