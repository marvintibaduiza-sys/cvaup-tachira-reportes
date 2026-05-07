<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consejos_comunales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comuna_id')->constrained('comunas')->cascadeOnDelete();
            $table->string('nombre');
            $table->timestamps();
            $table->unique(['comuna_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consejos_comunales');
    }
};
