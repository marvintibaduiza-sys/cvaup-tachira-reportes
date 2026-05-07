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
 * @property int $municipio_id
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Municipio $municipio
 * @property-read Collection<int, Comuna> $comunas
 * @property-read int|null $comunas_count  Cuando se carga con ->withCount('comunas')
 */
class Parroquia extends Model
{
    use HasFactory;

    protected $table = 'parroquias';

    protected $fillable = ['municipio_id', 'nombre'];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function comunas(): HasMany
    {
        return $this->hasMany(Comuna::class);
    }
}
