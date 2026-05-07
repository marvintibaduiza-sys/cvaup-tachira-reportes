<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $estado_id
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Estado $estado
 * @property-read Collection<int, Parroquia> $parroquias
 * @property-read Collection<int, Reporte> $reportes
 * @property-read Collection<int, Tecnico> $tecnicos
 * @property-read int|null $parroquias_count  Cuando se carga con ->withCount('parroquias')
 * @property-read int|null $reportes_count    Cuando se carga con ->withCount('reportes')
 */
class Municipio extends Model
{
    use HasFactory;

    protected $table = 'municipios';

    protected $fillable = ['estado_id', 'nombre'];

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function parroquias(): HasMany
    {
        return $this->hasMany(Parroquia::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    public function tecnicos(): BelongsToMany
    {
        return $this->belongsToMany(Tecnico::class, 'tecnico_municipio')->withTimestamps();
    }
}
