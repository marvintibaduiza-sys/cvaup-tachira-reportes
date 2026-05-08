<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor estructural del modelo Tecnico:
 *  - Separa nombre_apellido en `nombre` y `apellido` (campos independientes)
 *  - Agrega `tipo_documento` (V/E/J/G/P) — extraído del prefijo de la cédula vieja
 *  - Limpia `cedula` (solo dígitos, sin guión ni puntos)
 *  - UNIQUE compuesto: (tipo_documento, cedula, deleted_at) — permite mismo número
 *    con distintos tipos (ej: V-12345678 y E-12345678 son personas distintas)
 *
 * Migración de data existente: heurística simple (primera palabra = nombre, resto = apellido).
 * El admin puede ajustar manualmente después si los splits no son perfectos.
 *
 * Mantiene SoftDeletes (deleted_at) intacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── PASO 1: Agregar columnas nuevas como NULLABLE primero ─────
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->string('nombre', 120)->nullable()->after('id');
            $table->string('apellido', 120)->nullable()->after('nombre');
            $table->string('tipo_documento', 1)->nullable()->after('apellido');
        });

        // ── PASO 2: Migrar data existente ─────────────────────────────
        // Heurística:
        //  - nombre = primera palabra de nombre_apellido
        //  - apellido = resto (puede tener varias palabras)
        //  - tipo_documento = primera letra de cedula (V/E/J/G/P)
        //  - cedula = solo dígitos (sin guion, sin puntos)
        $tecnicos = DB::table('tecnicos')->get(['id', 'nombre_apellido', 'cedula']);

        foreach ($tecnicos as $t) {
            // Split nombre/apellido
            $partes = preg_split('/\s+/', trim((string) $t->nombre_apellido), 2);
            $nombre = $partes[0] ?? 'SinNombre';
            $apellido = $partes[1] ?? 'SinApellido';

            // Extraer tipo_documento + cedula numérica
            $cedulaVieja = (string) $t->cedula;
            // Match: V/E/J/G/P al inicio (con o sin guion)
            if (preg_match('/^([VEJGP])-?(.+)$/i', $cedulaVieja, $m)) {
                $tipoDoc = strtoupper($m[1]);
                $numero = preg_replace('/\D/', '', $m[2]); // solo dígitos
            } else {
                // Caso raro: cédula sin prefijo. Asumimos V (venezolano).
                $tipoDoc = 'V';
                $numero = preg_replace('/\D/', '', $cedulaVieja);
            }

            DB::table('tecnicos')->where('id', $t->id)->update([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'tipo_documento' => $tipoDoc,
                'cedula' => $numero,
            ]);
        }

        // ── PASO 3: Hacer las nuevas columnas NOT NULL ────────────────
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->string('nombre', 120)->nullable(false)->change();
            $table->string('apellido', 120)->nullable(false)->change();
            $table->string('tipo_documento', 1)->nullable(false)->change();
        });

        // ── PASO 4: Eliminar UNIQUE viejo y crear nuevo compuesto ─────
        // El UNIQUE viejo era (cedula, deleted_at) — permitía recrear cedulas tras soft-delete.
        // El nuevo es (tipo_documento, cedula, deleted_at) — permite que V-12345678 y E-12345678
        // sean entidades distintas y mantiene la lógica de soft-delete.
        Schema::table('tecnicos', function (Blueprint $table) {
            try {
                $table->dropUnique('tecnicos_cedula_deleted_at_unique');
            } catch (\Throwable $e) {
                // Si no existe (migración fresca), continuar
            }
        });

        Schema::table('tecnicos', function (Blueprint $table) {
            $table->unique(['tipo_documento', 'cedula', 'deleted_at'], 'tecnicos_tipodoc_cedula_deleted_unique');
        });

        // ── PASO 5: Eliminar columna nombre_apellido (ya no se usa) ──
        // Nota: el modelo Tecnico tendrá un accessor que la compute de nombre+apellido
        // para mantener compat con código que la consulta.
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->dropColumn('nombre_apellido');
        });
    }

    public function down(): void
    {
        // Revierte todo. Útil si la migración rompe algo y queremos volver atrás.
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->string('nombre_apellido', 255)->nullable()->after('id');
        });

        // Reconstruir nombre_apellido desde nombre+apellido
        DB::statement("UPDATE tecnicos SET nombre_apellido = CONCAT(nombre, ' ', apellido)");

        Schema::table('tecnicos', function (Blueprint $table) {
            $table->string('nombre_apellido', 255)->nullable(false)->change();
        });

        // Reconstruir cédula con prefijo + puntos (formato viejo V-XX.XXX.XXX)
        $tecnicos = DB::table('tecnicos')->get(['id', 'tipo_documento', 'cedula']);
        foreach ($tecnicos as $t) {
            $num = (string) $t->cedula;
            // Insertar puntos cada 3 dígitos desde la derecha
            $reversed = strrev($num);
            $chunks = str_split($reversed, 3);
            $formateado = strrev(implode('.', $chunks));
            DB::table('tecnicos')->where('id', $t->id)->update([
                'cedula' => $t->tipo_documento . '-' . $formateado,
            ]);
        }

        Schema::table('tecnicos', function (Blueprint $table) {
            $table->dropUnique('tecnicos_tipodoc_cedula_deleted_unique');
            $table->unique(['cedula', 'deleted_at'], 'tecnicos_cedula_deleted_at_unique');
            $table->dropColumn(['nombre', 'apellido', 'tipo_documento']);
        });
    }
};
