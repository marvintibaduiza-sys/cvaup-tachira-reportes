<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * AdminUserSeeder — crea la ÚNICA cuenta administradora del sistema mono-usuario.
 *
 * SEGURIDAD CRÍTICA (CWE-798 / OWASP A07:2021):
 *  - NO hardcodea contraseñas. Lee `ADMIN_EMAIL` y `ADMIN_INITIAL_PASSWORD` del .env.
 *  - Usa `firstOrCreate` (NO `updateOrCreate`) para que NO sobrescriba la contraseña
 *    si el admin ya la cambió. Cada `db:seed` posterior es idempotente y seguro.
 *  - Lanza RuntimeException si las variables no están definidas — falla rápido y ruidoso.
 *
 * Para producción: TI institucional debe poner en .env:
 *
 *   ADMIN_EMAIL=admin@cvaup-tachira.gob.ve
 *   ADMIN_INITIAL_PASSWORD=$(openssl rand -base64 24)   # >= 16 chars, fuerte
 *
 * Para desarrollo local: define ambas en tu .env. NO uses la contraseña de producción.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_INITIAL_PASSWORD');

        if (empty($email) || empty($password)) {
            throw new RuntimeException(
                'AdminUserSeeder requiere ADMIN_EMAIL y ADMIN_INITIAL_PASSWORD en .env. '
                . 'Generar con: openssl rand -base64 24. '
                . 'NUNCA hardcodear contraseñas. '
                . 'Ver docs/AUDITORIA-SEGURIDAD.md (CRITICAL #1).'
            );
        }

        // firstOrCreate: si el admin ya existe, NO toca su password (puede que el
        // admin la haya cambiado vía /perfil — respetamos esa decisión).
        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador CVAUP Táchira',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
    }
}
