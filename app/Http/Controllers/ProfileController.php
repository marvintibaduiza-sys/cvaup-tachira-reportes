<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ProfileController — gestión de la cuenta del único administrador.
 *
 * IMPORTANTE: este sistema es MONO-USUARIO. Solo existe edit() y update().
 * NO incluye destroy() porque borrar al admin = perder acceso al sistema.
 * Si en el futuro se migra a multi-tenant, agregar destroy() con guardas
 * (super-admin no se puede auto-borrar, etc.).
 */
class ProfileController extends Controller
{
    /**
     * Muestra el formulario de la cuenta del usuario autenticado.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Actualiza la información del usuario autenticado (nombre + email).
     * Si cambia el email, fuerza re-verificación.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')
            ->with('success', 'Información de la cuenta actualizada.');
    }
}
