<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre_apellido
 * @property string $cedula
 * @property string|null $telefono
 * @property string|null $especialidad
 * @property string|null $foto_perfil  Path relativo en disco fotos_privadas (ej: "fotos/tecnicos/5/perfil.webp")
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $es_activo
 * @property-read string|null $foto_perfil_url  URL autenticada para servir la foto (vía FotoController)
 * @property-read Reporte|null $ultimo_reporte
 * @property-read Collection<int, Reporte> $reportes
 * @property-read Collection<int, Municipio> $municipios
 */
class Tecnico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tecnicos';

    protected $fillable = [
        'nombre_apellido',
        'cedula',
        'telefono',
        'especialidad',
        'foto_perfil',
        'estado',
    ];

    protected $casts = [
        'estado' => 'string',
    ];

    /**
     * URL para acceder a la foto de perfil. Pasa por FotoController autenticado (CRITICAL #2).
     *
     * Devuelve null si el técnico no tiene foto. Llamar como `$tecnico->foto_perfil_url`.
     */
    public function getFotoPerfilUrlAttribute(): ?string
    {
        if (empty($this->foto_perfil)) {
            return null;
        }
        return route('fotos.tecnico', [
            'tecnico' => $this->id,
            'filename' => basename($this->foto_perfil),
        ]);
    }

    // ── Relaciones ──

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    public function municipios(): BelongsToMany
    {
        return $this->belongsToMany(Municipio::class, 'tecnico_municipio')->withTimestamps();
    }

    // ── Scopes ──

    public function scopeActivos($q)
    {
        return $q->where('estado', 'activo');
    }

    public function scopeInactivos($q)
    {
        return $q->where('estado', 'inactivo');
    }

    // ── Atributos derivados ──

    public function getEsActivoAttribute(): bool
    {
        return $this->estado === 'activo';
    }

    public function getUltimoReporteAttribute()
    {
        return $this->reportes()->latest('fecha')->first();
    }

    /**
     * Tasa de cumplimiento del mes actual:
     * (reportes completos del mes ÷ días laborables transcurridos del mes) * 100
     */
    public function tasaCumplimientoMesActual(): float
    {
        $hoy = now();
        $diasLaborables = collect();
        for ($d = $hoy->copy()->startOfMonth(); $d->lte($hoy); $d->addDay()) {
            if ($d->isWeekday()) {
                $diasLaborables->push($d->copy());
            }
        }

        if ($diasLaborables->isEmpty()) {
            return 0.0;
        }

        $reportesCompletosMes = $this->reportes()
            ->where('estado_reporte', 'completo')
            ->whereBetween('fecha', [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()])
            ->count();

        return round(($reportesCompletosMes / $diasLaborables->count()) * 100, 1);
    }
}
