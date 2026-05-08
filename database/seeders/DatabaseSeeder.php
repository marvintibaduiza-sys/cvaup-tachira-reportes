<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder — punto de entrada de `php artisan db:seed`.
 *
 * SOLO carga lo MÍNIMO indispensable para que el sistema arranque en producción:
 *  - El usuario administrador (lee credenciales de .env)
 *  - La jerarquía territorial completa de Táchira (lee de docs/CVAUP_TACHIRA.xlsx)
 *
 * Los técnicos y reportes son DATA OPERATIVA del cliente — el sistema NO debe
 * crear técnicos ficticios al desplegar en servidor. El admin los registrará
 * vía la UI conforme el equipo real comience a usar el sistema.
 *
 * Si necesitas data DE MUESTRA en desarrollo local (técnicos y reportes
 * ficticios para probar listados, charts, exports), corre los seeders
 * opcionales explícitamente:
 *
 *   php artisan db:seed --class=TecnicosSeeder
 *   php artisan db:seed --class=ReportesSeeder
 *
 * O usa el comando demo (con guards anti-producción):
 *
 *   php artisan demo:poblar
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            UbicacionesSeeder::class,
            // TecnicosSeeder y ReportesSeeder están deliberadamente OMITIDOS aquí.
            // Son data ficticia que NO debe cargarse en producción.
            // Para data de muestra en desarrollo, ver el docblock de esta clase.
        ]);
    }
}
