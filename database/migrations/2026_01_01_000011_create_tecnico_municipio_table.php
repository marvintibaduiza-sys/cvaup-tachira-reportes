<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: tecnico_municipio
 *
 * Modela "zona asignada" como N:M entre técnicos y municipios.
 * Aclaración del usuario en el chat de specs:
 *  "la zona asignada sea editable, que se pueda agregar eliminar
 *   porque las personas cambian de residencia"
 *
 * Asignar un municipio implica todas sus parroquias/comunas/CC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tecnico_municipio', function (Blueprint $table) {
            $table->foreignId('tecnico_id')->constrained('tecnicos')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['tecnico_id', 'municipio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tecnico_municipio');
    }
};
