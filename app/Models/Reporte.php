<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tecnico_id
 * @property Carbon $fecha
 * @property string $estado_reporte
 * @property int|null $municipio_id
 * @property int|null $parroquia_id
 * @property int|null $comuna_id
 * @property int|null $consejo_comunal_id
 * @property int|null $cantidad_comunas_atendidas
 * @property int|null $cantidad_consejos_comunales_atendidos
 * @property string|null $lugar
 * @property int|null $cantidad_personas_atendidas
 * @property int|null $cantidad_personas_a_beneficiar
 * @property string $tipo_actividad      Una de Reporte::TIPOS_ACTIVIDAD
 * @property string $descripcion_actividad  Descripción libre de la actividad ejecutada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tecnico|null $tecnico
 * @property-read Municipio|null $municipio
 * @property-read Parroquia|null $parroquia
 * @property-read Comuna|null $comuna
 * @property-read ConsejoComunal|null $consejoComunal
 * @property-read Collection<int, FotoReporte> $fotos
 * @property-read Collection<int, Comuna> $comunasAdicionales  BLOQUE 5: pivote reporte_comuna_atendida
 * @property-read Collection<int, ConsejoComunal> $consejosComunalesAdicionales  BLOQUE 5: pivote reporte_consejo_comunal_atendido
 */
class Reporte extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tipos de actividad permitidos en `tipo_actividad`.
     * Decisión del cliente (BLOQUE 8): dropdown corto en vez de texto libre — para que
     * los listados y reportes ejecutivos puedan agrupar/filtrar por tipo.
     */
    public const TIPOS_ACTIVIDAD = [
        'Capacitación',
        'Asesoría técnica',
        'Taller',
        'Visita técnica',
        'Otra',
    ];

    protected $table = 'reportes';

    protected $fillable = [
        'tecnico_id', 'fecha', 'estado_reporte',
        'municipio_id', 'parroquia_id', 'comuna_id', 'consejo_comunal_id',
        'cantidad_comunas_atendidas', 'cantidad_consejos_comunales_atendidos',
        'lugar', 'cantidad_personas_atendidas', 'cantidad_personas_a_beneficiar',
        'tipo_actividad', 'descripcion_actividad',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad_comunas_atendidas' => 'integer',
        'cantidad_consejos_comunales_atendidos' => 'integer',
        'cantidad_personas_atendidas' => 'integer',
        'cantidad_personas_a_beneficiar' => 'integer',
    ];

    // ── Relaciones ──

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function parroquia(): BelongsTo
    {
        return $this->belongsTo(Parroquia::class);
    }

    public function comuna(): BelongsTo
    {
        return $this->belongsTo(Comuna::class);
    }

    public function consejoComunal(): BelongsTo
    {
        return $this->belongsTo(ConsejoComunal::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoReporte::class)->orderBy('orden');
    }

    /**
     * BLOQUE 5: Comunas adicionales atendidas en la misma jornada.
     *
     * Pueden ser de CUALQUIER municipio (un técnico puede desplazarse a otro
     * municipio para una actividad especial). El modelo no restringe — la UI
     * y el FormRequest validan solo que existan en BD.
     *
     * NO incluye la comuna principal (`comuna_id`). Para obtener la lista
     * COMPLETA (principal + adicionales), usar `getTodasLasComunasAtendidas()`.
     */
    public function comunasAdicionales(): BelongsToMany
    {
        return $this->belongsToMany(Comuna::class, 'reporte_comuna_atendida')
            ->withTimestamps();
    }

    /**
     * BLOQUE 5: Consejos comunales adicionales atendidos.
     *
     * Pueden ser de CUALQUIER parroquia (no solo la del CC principal).
     */
    public function consejosComunalesAdicionales(): BelongsToMany
    {
        return $this->belongsToMany(
            ConsejoComunal::class,
            'reporte_consejo_comunal_atendido',
            'reporte_id',
            'consejo_comunal_id'
        )->withTimestamps();
    }

    /**
     * Cantidad TOTAL de comunas atendidas en este reporte.
     * = 1 (la principal, si existe) + adicionales del pivote.
     *
     * Si no hay comuna principal (raro), no contamos esa "1" — pero el FormRequest
     * obliga a tener mínimo 1 comuna principal según las reglas de negocio.
     */
    public function calcularCantidadComunasAtendidas(): int
    {
        $principal = $this->comuna_id ? 1 : 0;
        $adicionales = $this->comunasAdicionales()->count();
        return $principal + $adicionales;
    }

    /**
     * Cantidad TOTAL de consejos comunales atendidos.
     * = 1 (el principal) + adicionales del pivote.
     */
    public function calcularCantidadConsejosComunalesAtendidos(): int
    {
        $principal = $this->consejo_comunal_id ? 1 : 0;
        $adicionales = $this->consejosComunalesAdicionales()->count();
        return $principal + $adicionales;
    }

    /**
     * Recalcula y persiste las cantidades cacheadas. Llamar después de
     * sync() de los pivotes desde el controller.
     */
    public function recalcularCantidadesYGuardar(): void
    {
        $this->cantidad_comunas_atendidas = $this->calcularCantidadComunasAtendidas();
        $this->cantidad_consejos_comunales_atendidos = $this->calcularCantidadConsejosComunalesAtendidos();
        $this->save();
    }

    // ── Scopes ──

    public function scopeCompletos($q)
    {
        return $q->where('estado_reporte', 'completo');
    }

    public function scopeIncompletos($q)
    {
        return $q->where('estado_reporte', 'incompleto');
    }

    public function scopeBorradores($q)
    {
        return $q->where('estado_reporte', 'borrador');
    }

    public function scopeDelMes($q, $fecha = null)
    {
        $f = $fecha ? \Carbon\Carbon::parse($fecha) : now();
        return $q->whereBetween('fecha', [$f->copy()->startOfMonth(), $f->copy()->endOfMonth()]);
    }

    public function scopeDeLaSemana($q)
    {
        return $q->whereBetween('fecha', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Calcula el estado del reporte según los campos llenos.
     *
     * Reglas (post BLOQUE 8 — simplificación):
     *  - completo:   tecnico + fecha + ubicación completa (municipio→CC) +
     *                tipo_actividad + descripcion_actividad + al menos 1 foto
     *  - incompleto: tiene algunos datos pero falta algún campo requerido o foto
     *  - borrador:   recién creado, sin datos significativos
     */
    public function calcularEstado(): string
    {
        $tieneRequeridos =
            $this->tecnico_id &&
            $this->fecha &&
            $this->municipio_id &&
            $this->parroquia_id &&
            $this->comuna_id &&
            $this->consejo_comunal_id &&
            $this->tipo_actividad &&
            $this->descripcion_actividad;

        $tieneFotos = $this->fotos()->count() > 0;

        if ($tieneRequeridos && $tieneFotos) {
            return 'completo';
        }

        $algunosDatos = $this->tipo_actividad || $this->descripcion_actividad || $this->municipio_id;
        return $algunosDatos ? 'incompleto' : 'borrador';
    }

    public function recalcularYGuardarEstado(): void
    {
        $this->estado_reporte = $this->calcularEstado();
        $this->save();
    }
}
