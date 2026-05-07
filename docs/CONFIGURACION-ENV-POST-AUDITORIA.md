# Configuración del `.env` post-auditoría de seguridad

**Fecha**: 2026-05-06
**Aplica a**: tu `.env` local de desarrollo Y al `.env` de producción

---

## Por qué este documento existe

Tras la auditoría de seguridad, varias correcciones requieren variables de entorno NUEVAS en `.env`. El agente NO puede modificar `.env` directamente (por permisos del IDE — y es correcto que sea así, los secrets los manejas TÚ). Aquí están las instrucciones exactas.

---

## Variables a AGREGAR a tu `.env` LOCAL (desarrollo)

Abre `c:\laragon\www\CVAUP-Reportes-App-WEB\.env` y agrega al final:

```env
# ─────────────────────────────────────────────────────────
# CVAUP — Configuración post-auditoría 2026-05-06
# ─────────────────────────────────────────────────────────

# Admin inicial (usado por AdminUserSeeder con firstOrCreate)
# Si el admin ya existe en la BD, NO se sobrescribe.
ADMIN_EMAIL=admin@cvaup-tachira.com
ADMIN_INITIAL_PASSWORD=Cvaup-Local-Dev-2026!

# Backup cifrado obligatorio (HIGH #3)
# Para DEV puedes usar una password simple. Para PROD GENERA con: openssl rand -base64 32
BACKUP_ARCHIVE_PASSWORD=DevLocal-Backup-2026!
```

> **IMPORTANTE**:
> - `ADMIN_INITIAL_PASSWORD` solo se usa la PRIMERA vez que corras `php artisan db:seed --class=AdminUserSeeder`. Como tu BD ya tiene el admin con la contraseña fuerte que le pusiste vía `/perfil`, el `firstOrCreate` NO la sobrescribirá.
> - `BACKUP_ARCHIVE_PASSWORD` se requiere para que `BackupController::generar()` y `php artisan backup:run` funcionen. Si tratas de generar un backup sin esta var, recibirás un mensaje claro pidiendo configurarla.

## Variables a AGREGAR a tu `.env.example`

Esto documenta para futuros desarrolladores qué variables son obligatorias:

```env
# Admin inicial (consumido por AdminUserSeeder, OBLIGATORIO)
ADMIN_EMAIL=admin@cvaup-tachira.gob.ve
ADMIN_INITIAL_PASSWORD=

# Backup cifrado (OBLIGATORIO para BackupController y backup:run)
# Generar con: openssl rand -base64 32
BACKUP_ARCHIVE_PASSWORD=
```

(Solo el nombre y un valor de ejemplo. NO pongas el secreto real en `.env.example` porque ese SÍ se versiona en git.)

---

## Variables a CONFIGURAR para producción (instrucciones para TI institucional)

Cuando se entregue el sistema al equipo de TI, deben configurar el `.env` de PRODUCCIÓN así:

```env
APP_NAME="CVAUP Táchira"
APP_ENV=production
APP_KEY=base64:GENERAR_CON_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://cvaup-tachira.gob.ve

# Base de datos — usuario dedicado, NO root
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cvaup_reportes
DB_USERNAME=cvaup_app
DB_PASSWORD=<openssl rand -base64 32>

# Sesión endurecida
SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true     # OBLIGATORIO con HTTPS
SESSION_SAME_SITE=strict
SESSION_HTTP_ONLY=true

# Backup
BACKUP_ARCHIVE_PASSWORD=<openssl rand -base64 32>
BACKUP_NOTIFICATIONS_EMAIL=alertas-ti@cvaup-tachira.gob.ve

# Mail SMTP institucional
MAIL_MAILER=smtp
MAIL_HOST=smtp.cvaup-tachira.gob.ve
MAIL_PORT=587
MAIL_USERNAME=sistema
MAIL_PASSWORD=<secret-institucional>
MAIL_ENCRYPTION=tls

# Admin inicial (CAMBIAR EN PRIMER LOGIN)
ADMIN_EMAIL=admin@cvaup-tachira.gob.ve
ADMIN_INITIAL_PASSWORD=<openssl rand -base64 24>

# Logs
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

### Generación de secrets

Todos los `<openssl rand -base64 N>` deben generarse en el servidor de producción con:

```bash
# Para passwords de 32 bytes (256 bits)
openssl rand -base64 32

# Para password admin más legible (24 bytes)
openssl rand -base64 24

# APP_KEY se genera con artisan
php artisan key:generate --show
```

### Almacenamiento de los secrets

- **NO los guardes en el repo de git.** Solo en el `.env` del servidor.
- **NO los compartas por email.** Usa un gestor de secretos institucional (Bitwarden Enterprise, 1Password Teams, Hashicorp Vault).
- **Documenta quién los conoce.** Solo TI institucional + el administrador del sistema.

---

## Configuración de PHP en producción (recomendaciones de la auditoría)

En `php.ini` del servidor de producción:

```ini
; Ocultar versión de PHP (corrige el header X-Powered-By detectado en auditoría)
expose_php = Off

; Errores nunca al cliente
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/errors.log

; Sesiones más seguras
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Strict"
session.use_strict_mode = 1

; Limit body size (alineado con max upload del importer Excel)
upload_max_filesize = 5M
post_max_size = 20M

; Timezone explícita
date.timezone = America/Caracas
```

## Configuración de Apache/Nginx en producción

### Headers adicionales (defensa en profundidad — el middleware ya los pone, pero también el server)

**Apache (`httpd.conf` — NO `.htaccess`, estos van a nivel server):**
```apache
# Ocultar versión de Apache + OS + módulos
ServerTokens Prod
ServerSignature Off
TraceEnable Off

# Ocultar X-Powered-By que añade PHP-FPM
Header unset X-Powered-By
Header unset Server

# Headers de seguridad como respaldo (Laravel ya los envía, pero defensa en profundidad)
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" env=HTTPS
```

> **Validación post-deploy**: tras configurar lo anterior, `curl -I https://app.cvaup.gob.ve/login` NO debe devolver `Server:` con versión ni `X-Powered-By:`. Solo `Server: Apache` (sin versión) o nada.

**Nginx:**
```nginx
server_tokens off;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;

# Bloquear acceso directo a archivos sensibles
location ~ /\.env { deny all; return 404; }
location ~ /storage/.+\.key { deny all; return 404; }
```

### Forzar HTTPS

**Apache:**
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name cvaup-tachira.gob.ve;
    return 301 https://$host$request_uri;
}
```

---

## Comandos a ejecutar tras configurar `.env`

```bash
# Limpiar caches con la nueva config
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Verificar que la config carga sin errores
php artisan config:cache

# En producción, también:
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## Si algo falla

### "BACKUP_ARCHIVE_PASSWORD es obligatoria"
Agrega `BACKUP_ARCHIVE_PASSWORD=...` a tu `.env` y `php artisan config:clear`.

### "AdminUserSeeder requiere ADMIN_EMAIL y ADMIN_INITIAL_PASSWORD"
Agrega ambas variables a `.env`. Solo se usa al re-poblar; tu admin actual NO se afecta.

### "419 PAGE EXPIRED" tras agregar SecurityHeaders
Es porque CSP requiere recargar la sesión. Cierra sesión y vuelve a entrar.

### El navegador bloquea fuentes de Google
La CSP las permite explícitamente con `font-src https://fonts.gstatic.com`. Si igual falla, revisa la consola DevTools → si hay un dominio bloqueado, agrégalo al middleware `SecurityHeaders.php`.
