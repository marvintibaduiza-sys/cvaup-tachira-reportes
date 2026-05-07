<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tecnicos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_apellido');
            $table->string('cedula')->unique();
            $table->string('telefono')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('foto_perfil')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
            $table->softDeletes();

            $table->index('cedula');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tecnicos');
    }
};
