<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * PasswordController — actualización de contraseña del usuario autenticado.
 *
 * Política de contraseñas (institucional):
 *  - Mínimo 8 caracteres
 *  - Al menos una mayúscula y una minúscula
 *  - Al menos un número
 *  - Al menos un símbolo
 *  - NO debe aparecer en brechas públicas (haveibeenpwned, k-anonymity)
 *
 * Por qué tan estricto: este sistema tiene UNA sola cuenta administradora con
 * acceso a TODA la data institucional (técnicos, reportes, ubicaciones, backups).
 * Si esa contraseña cae, cae todo. Vale la pena un poco más de fricción.
 */
class PasswordController extends Controller
{
    /**
     * Actualiza la contraseña del usuario autenticado.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            // Mensajes amistosos en español
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Debes ingresar la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return back()->with('success', 'Contraseña actualizada con éxito.');
    }
}
