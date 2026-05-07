<?php

namespace App\Models;

use App\Services\PhotoCompressor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FotoReporte extends Model
{
    use HasFactory;

    protected $table = 'fotos_reporte';

    protected $fillable = [
        'reporte_id', 'ruta', 'nombre_original', 'tamano_kb', 'orden',
    ];

    protected $casts = [
        'tamano_kb' => 'integer',
        'orden' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    /**
     * URL para acceder a la foto. Pasa por FotoController autenticado (CRITICAL #2 fix).
     *
     * Antes: `Storage::disk('public')->url(...)` que devolvía `/storage/...` (público).
     * Ahora: ruta nombrada `fotos.reporte` que requiere auth + valida filename.
     */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->ruta) || empty($this->reporte_id)) {
            return null;
        }
        // basename() extrae 'foto-1.webp' de 'fotos/reportes/123/foto-1.webp'
        return route('fotos.reporte', [
            'reporte' => $this->reporte_id,
            'filename' => basename($this->ruta),
        ]);
    }

    /**
     * Al eliminar el modelo, también eliminar el archivo del disco PRIVADO.
     */
    protected static function booted(): void
    {
        static::deleting(function (FotoReporte $foto) {
            if (Storage::disk(PhotoCompressor::DISK)->exists($foto->ruta)) {
                Storage::disk(PhotoCompressor::DISK)->delete($foto->ruta);
            }
        });
    }
}
