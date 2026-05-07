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
 * @property int $parroquia_id
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Parroquia $parroquia
 * @property-read Collection<int, ConsejoComunal> $consejosComunales
 * @property-read int|null $consejos_comunales_count  Cuando se carga con ->withCount('consejosComunales')
 */
class Comuna extends Model
{
    use HasFactory;

    protected $table = 'comunas';

    protected $fillable = ['parroquia_id', 'nombre'];

    public function parroquia(): BelongsTo
    {
        return $this->belongsTo(Parroquia::class);
    }

    public function consejosComunales(): HasMany
    {
        return $this->hasMany(ConsejoComunal::class);
    }
}
