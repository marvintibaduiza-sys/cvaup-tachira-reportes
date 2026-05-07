<?php

namespace App\Http\Controllers;

use App\Models\Reporte;
use App\Models\Tecnico;
use App\Services\PhotoCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FotoController — sirve fotos privadas con autenticación.
 *
 * Las fotos viven en `storage/app/fotos-privadas/...` (disco privado).
 * Apache/Nginx NO las sirve directamente. Solo se acceden vía estos endpoints,
 * todos protegidos por el middleware `auth + verified` (registrado en routes/web.php).
 *
 * SEGURIDAD (CRITICAL #2 post-auditoría):
 *  - Validación regex estricta del filename para anti path-traversal
 *    (impide ../../etc/passwd y similares).
 *  - El controller verifica que el reporte/técnico exista en BD antes de servir
 *    (evita que IDs falsos descubran fotos huérfanas si quedaron tras un delete).
 *  - Cabeceras de cache `private, max-age=3600` — cachean SOLO en navegador del admin,
 *    NO en proxies intermedios.
 *  - X-Content-Type-Options nosniff por header explícito (defensa en profundidad,
 *    el middleware SecurityHeaders ya lo añade globalmente).
 *
 * Path en disco: fotos-privadas/fotos/{tecnicos|reportes}/{ID}/{filename}
 */
class FotoController extends Controller
{
    /**
     * Regex para validar filenames de fotos.
     * Solo aceptamos: caracteres alfanuméricos, guiones y guiones bajos + .webp final.
     * Esto IMPIDE explícitamente: '..', '/', '\\', ':', '..\\', '%2e%2e', etc.
     */
    private const FILENAME_PATTERN = '/^[A-Za-z0-9_-]+\.webp$/';

    /**
     * Sirve una foto de reporte.
     *
     * Ruta: GET /fotos/reportes/{reporte}/{filename}
     */
    public function reporte(Reporte $reporte, string $filename): StreamedResponse
    {
        $this->validarFilename($filename);

        $path = "fotos/reportes/{$reporte->id}/{$filename}";

        abort_unless(
            Storage::disk(PhotoCompressor::DISK)->exists($path),
            404,
            'Foto no encontrada.'
        );

        return Storage::disk(PhotoCompressor::DISK)->response(
            $path,
            $filename,
            [
                'Content-Type' => 'image/webp',
                'Cache-Control' => 'private, max-age=3600, no-transform',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Sirve la foto de perfil de un técnico.
     *
     * Ruta: GET /fotos/tecnicos/{tecnico}/{filename}
     */
    public function tecnico(Tecnico $tecnico, string $filename): StreamedResponse
    {
        $this->validarFilename($filename);

        $path = "fotos/tecnicos/{$tecnico->id}/{$filename}";

        abort_unless(
            Storage::disk(PhotoCompressor::DISK)->exists($path),
            404,
            'Foto no encontrada.'
        );

        return Storage::disk(PhotoCompressor::DISK)->response(
            $path,
            $filename,
            [
                'Content-Type' => 'image/webp',
                'Cache-Control' => 'private, max-age=3600, no-transform',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Aborta con 404 si el filename contiene caracteres ilegales o secuencias de traversal.
     * Devolvemos 404 (no 400) para no filtrar info al atacante: "no existe" es más opaco que
     * "tu pattern es inválido — sigue intentando".
     */
    private function validarFilename(string $filename): void
    {
        if (!preg_match(self::FILENAME_PATTERN, $filename)) {
            abort(404, 'Foto no encontrada.');
        }
    }
}
