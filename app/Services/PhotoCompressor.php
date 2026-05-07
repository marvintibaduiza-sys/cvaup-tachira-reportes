<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * PhotoCompressor
 *
 * Servicio reutilizable para comprimir y guardar fotos.
 * Convierte a WebP (calidad 80) con ancho máx. 1200px.
 *
 * Reglas (del chat de specs):
 *  - Max 1200px ancho
 *  - Calidad 80
 *  - Formato WebP
 *  - Storage: storage/app/fotos-privadas/{folder}/... (disco PRIVADO)
 *
 * SEGURIDAD (post-auditoría 2026-05-06, CRITICAL #2):
 *  - Las fotos NO se sirven como archivos estáticos. Vive en disco privado.
 *  - Para servir, usar `route('fotos.reporte', [$reporteId, $filename])`
 *    o `route('fotos.tecnico', [$tecnicoId])` que pasan por FotoController autenticado.
 *  - NUNCA escribir al disco 'public' (eso las expondría en /storage/...).
 *
 * Lo usan:
 *  - TecnicoController (foto_perfil)
 *  - ReporteController (fotos_reporte)
 *
 * Compatible con Intervention Image v4 (API: decode + encode con WebpEncoder).
 */
class PhotoCompressor
{
    public const MAX_WIDTH = 1200;
    public const QUALITY = 80;

    /**
     * Disk donde se guardan TODAS las fotos. Centralizado en una constante para
     * que cualquier cambio futuro (migración a S3, etc.) sea quirúrgico.
     */
    public const DISK = 'fotos_privadas';

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Comprime y guarda un archivo subido.
     *
     * @param  UploadedFile  $file  Archivo del request
     * @param  string  $folder  Subcarpeta dentro de storage/app/fotos-privadas/ (ej: "fotos/tecnicos/5")
     * @param  string|null  $filename  Nombre sin extensión (default: random); siempre se guarda como .webp
     * @return array{ruta: string, nombre_original: string, tamano_kb: int}  Info para persistir en BD
     */
    public function compressAndStore(UploadedFile $file, string $folder, ?string $filename = null): array
    {
        $filename = ($filename ?: Str::random(20)) . '.webp';
        $relativePath = trim($folder, '/') . '/' . $filename;

        // Decode source (Intervention v4 API)
        $image = $this->manager->decode($file->getRealPath());

        // Solo escala hacia abajo si excede MAX_WIDTH (no agranda imágenes pequeñas)
        if ($image->width() > self::MAX_WIDTH) {
            $image = $image->scaleDown(width: self::MAX_WIDTH);
        }

        // Encode a WebP con encoder explícito (v4 API)
        $encoded = $image->encode(new WebpEncoder(quality: self::QUALITY));

        Storage::disk(self::DISK)->put($relativePath, (string) $encoded);

        return [
            'ruta' => $relativePath,
            'nombre_original' => $file->getClientOriginalName(),
            // Namespace global explícito (\) permite que el compilador PHP optimice las llamadas
            // sin tener que resolver primero en App\Services y luego caer al global.
            'tamano_kb' => (int) \ceil(\strlen((string) $encoded) / 1024),
        ];
    }

    /**
     * Elimina una foto del disco si existe.
     */
    public function delete(string $relativePath): void
    {
        if (Storage::disk(self::DISK)->exists($relativePath)) {
            Storage::disk(self::DISK)->delete($relativePath);
        }
    }
}
