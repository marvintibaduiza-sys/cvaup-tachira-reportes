<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $comuna_id
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Comuna $comuna
 * @property-read Collection<int, Reporte> $reportes
 * @property-read int|null $reportes_count  Cuando se carga con ->withCount('reportes')
 */
class ConsejoComunal extends Model
{
    use HasFactory;

    protected $table = 'consejos_comunales';

    protected $fillable = ['comuna_id', 'nombre'];

    public function comuna(): BelongsTo
    {
        return $this->belongsTo(Comuna::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }
}
