<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     *
     * SEGURIDAD (post-test 1.5):
     *  Tras logout, enviamos el header `Clear-Site-Data` que ordena al navegador
     *  borrar TODA la data del sitio: cookies, localStorage, sessionStorage,
     *  cache (incluyendo bfcache de Chrome), IndexedDB, etc.
     *
     *  Esto cierra el agujero de "presionar atrás tras logout muestra dashboard
     *  cacheado". Al borrarse el cache del sitio, no hay nada que el navegador
     *  pueda mostrar — debe pedir todo al servidor de cero.
     *
     *  Ref: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Clear-Site-Data
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->withHeaders([
            // "*" borra TODA la data: cache, cookies, storage, executionContexts.
            // Es la forma más completa y segura de hacer logout.
            'Clear-Site-Data' => '"cache", "cookies", "storage"',
        ]);
    }
}
