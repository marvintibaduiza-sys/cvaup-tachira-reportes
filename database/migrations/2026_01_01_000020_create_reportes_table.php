<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tecnico_id')->constrained('tecnicos')->cascadeOnDelete();
            $table->date('fecha');
            $table->enum('estado_reporte', ['completo', 'incompleto', 'borrador'])->default('borrador');

            // Ubicación jerárquica (1 reporte = 1 consejo comunal — confirmado por usuario)
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->foreignId('parroquia_id')->nullable()->constrained('parroquias')->nullOnDelete();
            $table->foreignId('comuna_id')->nullable()->constrained('comunas')->nullOnDelete();
            $table->foreignId('consejo_comunal_id')->nullable()->constrained('consejos_comunales')->nullOnDelete();

            // Ámbito y métricas
            $table->unsignedInteger('cantidad_comunas_atendidas')->nullable();
            $table->unsignedInteger('cantidad_consejos_comunales_atendidos')->nullable();
            $table->string('lugar')->nullable();
            $table->unsignedInteger('cantidad_personas_atendidas')->nullable();
            $table->unsignedInteger('cantidad_personas_a_beneficiar')->nullable();

            // Descripción de la actividad
            $table->string('titulo_actividad');
            $table->string('nombre_cientifico_rubro')->nullable();
            $table->date('fecha_ejecucion');
            $table->string('ponencia_responsable')->nullable();
            $table->text('material_apoyo')->nullable();
            $table->string('organizado_por')->nullable();
            $table->string('aval_de')->nullable();
            $table->text('certificacion')->nullable();

            // Impacto y participación
            $table->unsignedInteger('participantes_acreditados')->nullable();
            $table->unsignedInteger('alcance_grupo')->nullable();
            $table->text('resultado')->nullable();

            // Resumen temático
            $table->text('resumen_tematico')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Validación: 1 técnico = 1 reporte por fecha (constraint a nivel BD)
            $table->unique(['tecnico_id', 'fecha'], 'unique_tecnico_fecha');

            // Índices para filtros del listado
            $table->index('fecha');
            $table->index('estado_reporte');
            $table->index('municipio_id');
            $table->index('parroquia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
