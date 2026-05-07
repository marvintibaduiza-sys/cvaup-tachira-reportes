<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotos_reporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('reportes')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('nombre_original');
            $table->unsignedInteger('tamano_kb'); // tamaño DESPUÉS de compresión
            $table->unsignedTinyInteger('orden')->default(1); // 1, 2 o 3
            $table->timestamps();

            $table->index(['reporte_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_reporte');
    }
};
