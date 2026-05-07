<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

/**
 * Sistema MONO-USUARIO: solo se exponen rutas de login, logout y cambio de contraseña.
 *
 * Rutas DESHABILITADAS (no aplican a un sistema con un solo administrador):
 *  - register / register store
 *  - forgot-password / reset-password (no hay flujo de recuperación email)
 *  - email verification (no hay registro público)
 */

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Cambio de contraseña (Fase 14 — UI dedicada en /perfil/password)
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
