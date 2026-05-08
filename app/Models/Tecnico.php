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
 * @property string $nombre
 * @property string $apellido
 * @property string $tipo_documento  V (venezolano), E (extranjero), J (jurídico), G (gobierno), P (pasaporte)
 * @property string $cedula  Solo dígitos, sin guion ni puntos
 * @property string|null $telefono
 * @property array|null $especialidades  Lista de especialidades (BLOQUE 4.5: multi-select). Cada item: opción de ESPECIALIDADES o texto libre.
 * @property string|null $foto_perfil  Path relativo en disco fotos_privadas (ej: "fotos/tecnicos/5/perfil.webp")
 * @property string $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $nombre_apellido  Computado: "{nombre} {apellido}"
 * @property-read string $documento_completo  Computado: "{tipo_documento}-{cedula}" (ej: "V-12345678")
 * @property-read string $especialidad  BACKWARD COMPAT: implode(', ', especialidades) — lectura solamente.
 * @property-read bool $es_activo
 * @property-read string|null $foto_perfil_url  URL autenticada para servir la foto (vía FotoController)
 * @property-read Reporte|null $ultimo_reporte
 * @property-read Collection<int, Reporte> $reportes
 * @property-read int|null $reportes_count  Cuando se carga con withCount('reportes')
 * @property-read string|null $ultimo_reporte_fecha  Cuando se carga via addSelect subquery (TecnicoController)
 * @property-read Collection<int, Municipio> $municipios
 */
class Tecnico extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Lista oficial de especialidades disponibles en CVAUP Táchira.
     *
     * BLOQUE 4.5: el campo es multi-select (ver $casts['especialidades']).
     * El formulario presenta estas opciones como checkboxes + opción de agregar
     * texto libre para casos no contemplados.
     */
    public const ESPECIALIDADES = [
        'Agronomía urbana',
        'Hidroponía',
        'Compostaje y aprovechamiento orgánico',
        'Sanidad vegetal',
        'Riego y manejo del agua',
        'Apicultura',
        'Cultivos protegidos (invernaderos)',
        'Acuaponía',
        'Educación ambiental comunitaria',
        'Producción animal urbana',
        'Semilleros y viveros',
    ];

    /**
     * Tipos de documento válidos en Venezuela.
     *
     * La administración pública venezolana entiende las iniciales sin descripción:
     *  V (Venezolano), E (Extranjero), J (Jurídico), G (Gobierno), P (Pasaporte).
     */
    public const TIPOS_DOCUMENTO = ['V', 'E', 'J', 'G', 'P'];

    protected $table = 'tecnicos';

    protected $fillable = [
        'nombre',
        'apellido',
        'tipo_documento',
        'cedula',
        'telefono',
        'especialidades',
        'foto_perfil',
        'estado',
    ];

    protected $casts = [
        'estado' => 'string',
        // BLOQUE 4.5: array de strings (mezcla de ESPECIALIDADES predefinidas y custom)
        'especialidades' => 'array',
    ];

    // ── Atributos derivados (accessors) ──────────────────────────────

    /**
     * Nombre completo concatenado: "{nombre} {apellido}".
     * Reemplaza el campo viejo `nombre_apellido` que ya no existe en BD.
     * Mantenido para compat con código que lo consulta.
     */
    public function getNombreApellidoAttribute(): string
    {
        return trim(($this->nombre ?? '') . ' ' . ($this->apellido ?? ''));
    }

    /**
     * Documento completo en formato institucional: "V-12345678" (sin puntos).
     * Útil para mostrar en listados, PDFs y exportaciones.
     */
    public function getDocumentoCompletoAttribute(): string
    {
        return ($this->tipo_documento ?? '') . '-' . ($this->cedula ?? '');
    }

    /**
     * BACKWARD COMPAT: devuelve las especialidades concatenadas con coma.
     * BLOQUE 4.5: la columna `especialidad` (string) ya no existe; este accessor
     * mantiene compatibilidad con código que aún consulta `$tecnico->especialidad`.
     * Para nuevo código, usar `$tecnico->especialidades` (array).
     */
    public function getEspecialidadAttribute(): ?string
    {
        $arr = $this->especialidades;
        if (!\is_array($arr) || \count($arr) === 0) {
            return null;
        }
        return implode(', ', $arr);
    }

    public function getEsActivoAttribute(): bool
    {
        return $this->estado === 'activo';
    }

    public function getUltimoReporteAttribute()
    {
        return $this->reportes()->latest('fecha')->first();
    }

    /**
     * URL para acceder a la foto de perfil. Pasa por FotoController autenticado (CRITICAL #2).
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

    /**
     * Búsqueda flexible: combina nombre, apellido o cédula.
     * Útil para filtros del listado y autocompletes.
     */
    public function scopeBuscar($q, string $termino)
    {
        $t = trim($termino);
        if ($t === '') return $q;

        return $q->where(function ($w) use ($t) {
            $w->where('nombre', 'like', "%{$t}%")
              ->orWhere('apellido', 'like', "%{$t}%")
              ->orWhere('cedula', 'like', "%{$t}%")
              ->orWhereRaw("CONCAT(nombre, ' ', apellido) LIKE ?", ["%{$t}%"]);
        });
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
