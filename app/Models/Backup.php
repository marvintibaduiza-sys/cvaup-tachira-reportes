<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    protected $table = 'backups';

    protected $fillable = ['nombre_archivo', 'ruta', 'tamano_mb', 'tipo'];

    protected $casts = [
        'tamano_mb' => 'decimal:2',
    ];
}
