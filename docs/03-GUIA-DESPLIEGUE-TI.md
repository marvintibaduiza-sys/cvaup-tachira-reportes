# Guía de Despliegue — Sistema de Reportes Técnicos CVAUP Táchira

**Documento técnico dirigido al equipo de Tecnologías de Información de la institución.**
**Versión 1.0.0 — Mayo 2026**

---

## 1. Propósito

Este documento provee instrucciones paso a paso para que el equipo de TI institucional despliegue el **Sistema de Reportes Técnicos CVAUP Táchira** en producción de manera **segura, eficiente y conforme a las mejores prácticas industriales**.

Está diseñado para ser ejecutado por personal con conocimientos básicos de:
- Linux (preferentemente) o Windows Server
- Apache/Nginx
- PHP 8.3+
- MySQL/MariaDB
- Comandos de shell

---

## 2. Requerimientos del servidor institucional

### 2.1 Hardware mínimo recomendado

| Recurso | Mínimo | Recomendado |
|---------|--------|-------------|
| **CPU** | 2 vCPUs | 4 vCPUs |
| **RAM** | 4 GB | 8 GB |
| **Almacenamiento** | 50 GB | 200 GB (con espacio para backups locales) |
| **Red** | 100 Mbps | 1 Gbps (en LAN institucional) |

### 2.2 Software requerido

| Componente | Versión mínima |
|------------|----------------|
| **PHP** | 8.3 |
| **MySQL** | 8.0 (o MariaDB 10.6+) |
| **Apache** | 2.4 (con `mod_rewrite` y `mod_headers`) |
| **Composer** | 2.6+ |
| **Node.js** | 18 LTS o superior |
| **OpenSSL** | 3.0+ |
| **mysqldump** | (incluido con MySQL) |

### 2.3 Extensiones PHP requeridas

```
php-bcmath
php-curl
php-gd          (procesamiento de imágenes)
php-intl
php-mbstring
php-mysql
php-xml
php-zip         (cifrado AES-256 de respaldos)
php-fileinfo
php-tokenizer
```

### 2.4 Sistema operativo

**Recomendado**: Ubuntu Server 22.04 LTS o Debian 12.
**Alternativo**: Windows Server 2019/2022 con IIS o Apache para Windows.

---

## 3. Despliegue paso a paso (Linux Ubuntu/Debian)

### 3.1 Preparación del servidor

```bash
# Actualizar paquetes
sudo apt update && sudo apt upgrade -y

# Instalar PHP 8.3 + extensiones
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-mbstring \
                    php8.3-xml php8.3-curl php8.3-zip php8.3-gd \
                    php8.3-bcmath php8.3-intl

# Instalar Apache + MySQL
sudo apt install -y apache2 mysql-server

# Instalar Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Instalar Node.js 18 LTS (para build de assets)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 3.2 Configurar MySQL

```bash
sudo mysql_secure_installation
```

Crear base de datos y usuario dedicado:

```sql
sudo mysql

CREATE DATABASE cvaup_reportes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cvaup_app'@'localhost' IDENTIFIED BY 'PASSWORD_FUERTE_AQUI';
GRANT ALL PRIVILEGES ON cvaup_reportes.* TO 'cvaup_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Generar contraseña fuerte**: `openssl rand -base64 32`

### 3.3 Desplegar el código

```bash
# Crear directorio
sudo mkdir -p /var/www/cvaup
sudo chown $USER:www-data /var/www/cvaup

# Copiar el código (entregado por el equipo de desarrollo)
# Opción A: vía git (si está en repositorio institucional)
cd /var/www
sudo git clone https://repo-institucional.gob.ve/cvaup-reportes.git cvaup
cd cvaup

# Opción B: vía archivo zip
unzip cvaup-reportes-v1.0.0.zip -d /var/www/cvaup
cd /var/www/cvaup

# Instalar dependencias PHP (sin dev, optimizado)
composer install --no-dev --optimize-autoloader

# Instalar y compilar assets frontend
npm install
npm run build

# Permisos
sudo chown -R www-data:www-data /var/www/cvaup
sudo chmod -R 755 /var/www/cvaup
sudo chmod -R 775 /var/www/cvaup/storage /var/www/cvaup/bootstrap/cache
```

### 3.4 Configurar el archivo `.env` de producción

```bash
cp .env.example .env
nano .env
```

Configuración OBLIGATORIA — cambiar todas las variables marcadas:

```env
# ─── Aplicación ───────────────────────────────────────────────────
APP_NAME="CVAUP Táchira"
APP_ENV=production
APP_KEY=                                    # Se genera con php artisan key:generate
APP_DEBUG=false
APP_URL=https://cvaup-tachira.gob.ve        # Dominio institucional con HTTPS

LOG_CHANNEL=daily
LOG_LEVEL=warning

# ─── Base de datos ────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cvaup_reportes
DB_USERNAME=cvaup_app
DB_PASSWORD=<la-contraseña-de-MySQL-creada-en-3.2>

# ─── Sesión (endurecida) ──────────────────────────────────────────
SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true                  # OBLIGATORIO con HTTPS
SESSION_SAME_SITE=strict
SESSION_HTTP_ONLY=true

# ─── Mail SMTP institucional ──────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.cvaup-tachira.gob.ve
MAIL_PORT=587
MAIL_USERNAME=sistema
MAIL_PASSWORD=<contraseña-SMTP-institucional>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=alertas@cvaup-tachira.gob.ve
MAIL_FROM_NAME="Sistema CVAUP Táchira"

# ─── Backup ───────────────────────────────────────────────────────
BACKUP_ARCHIVE_PASSWORD=<openssl rand -base64 32>
BACKUP_NOTIFICATIONS_EMAIL=alertas-ti@cvaup-tachira.gob.ve

# ─── Admin inicial (consumido por seeder) ─────────────────────────
ADMIN_EMAIL=admin@cvaup-tachira.gob.ve
ADMIN_INITIAL_PASSWORD=<openssl rand -base64 24>
```

> **CRÍTICO**: la `BACKUP_ARCHIVE_PASSWORD` y la `ADMIN_INITIAL_PASSWORD` deben **almacenarse en el gestor de secretos institucional** (NO en el mismo servidor). Sin la primera, los respaldos no se pueden abrir.

### 3.5 Generar APP_KEY y migrar BD

```bash
cd /var/www/cvaup

# Generar la clave de cifrado de la aplicación
php artisan key:generate

# Crear las tablas
php artisan migrate --force

# Cargar la geografía oficial del Estado Táchira (29 municipios + 2207 CC)
php artisan db:seed --class=UbicacionesSeeder --force

# Crear el usuario administrador
php artisan db:seed --class=AdminUserSeeder --force
```

### 3.6 Optimización de producción

```bash
# Cachear configuración, rutas y vistas (mejora rendimiento)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Crear el symlink storage (NOTA: solo si se necesitan assets públicos;
# las fotos del sistema ya viven en disco privado, esto es solo para logos/cintillo)
php artisan storage:link
```

### 3.7 Permisos finales

```bash
sudo chown -R www-data:www-data /var/www/cvaup
sudo chmod -R 755 /var/www/cvaup
sudo chmod -R 775 /var/www/cvaup/storage /var/www/cvaup/bootstrap/cache
sudo chmod 600 /var/www/cvaup/.env                # Solo el dueño puede leer
```

---

## 4. Configuración de Apache

### 4.1 VirtualHost HTTPS

Crear `/etc/apache2/sites-available/cvaup.conf`:

```apache
<VirtualHost *:80>
    ServerName cvaup-tachira.gob.ve

    # Redirigir todo HTTP a HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName cvaup-tachira.gob.ve
    DocumentRoot /var/www/cvaup/public

    SSLEngine on
    SSLCertificateFile      /etc/ssl/certs/cvaup-tachira.gob.ve.crt
    SSLCertificateKeyFile   /etc/ssl/private/cvaup-tachira.gob.ve.key
    SSLProtocol             TLSv1.2 TLSv1.3

    # ─── Hardening Apache ───────────────────────────────
    ServerTokens Prod
    ServerSignature Off
    TraceEnable Off

    # Ocultar X-Powered-By que añade PHP-FPM
    Header unset X-Powered-By
    Header unset Server

    # Headers de seguridad como respaldo (Laravel también los envía)
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" env=HTTPS

    # Bloquear acceso directo a archivos sensibles
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    <Directory /var/www/cvaup/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/cvaup-error.log
    CustomLog ${APACHE_LOG_DIR}/cvaup-access.log combined
</VirtualHost>
```

### 4.2 Activar sitio y módulos

```bash
sudo a2ensite cvaup.conf
sudo a2enmod rewrite headers ssl
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### 4.3 Configurar PHP

Editar `/etc/php/8.3/fpm/php.ini`:

```ini
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/errors.log

upload_max_filesize = 5M
post_max_size = 20M
memory_limit = 512M

date.timezone = America/Caracas
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Strict"
session.use_strict_mode = 1
```

```bash
sudo systemctl restart php8.3-fpm
```

---

## 5. Programar respaldos automáticos (cron)

### 5.1 Configurar el cron de Laravel

```bash
sudo crontab -u www-data -e
```

Agregar:

```cron
* * * * * cd /var/www/cvaup && php artisan schedule:run >> /dev/null 2>&1
```

Esto activa las tareas programadas en `routes/console.php`:

| Hora | Tarea |
|------|-------|
| **02:00 AM diario** | `backup:clean` — limpia respaldos antiguos (mantiene últimos 10) |
| **02:30 AM diario** | `backup:run` — genera respaldo del día |
| **03:00 AM lunes** | `backup:monitor` — verifica salud del sistema de respaldos |
| **Cada hora** | Cleanup de archivos temporales del importer Excel |

### 5.2 Verificar funcionamiento del cron

```bash
# Ejecutar manualmente las tareas programadas para validar
sudo -u www-data php /var/www/cvaup/artisan schedule:run

# Ver el listado de tareas
sudo -u www-data php /var/www/cvaup/artisan schedule:list

# Ejecutar un backup manualmente para probar
sudo -u www-data php /var/www/cvaup/artisan backup:run
```

Verificar que se generó el `.zip` en:
```
/var/www/cvaup/storage/app/private/cvaup-tachira/
```

---

## 6. Configuración de respaldo off-site

> **Política institucional**: los respaldos NO deben quedar solo en el servidor. Si el servidor falla, los respaldos también se pierden. Hay 4 opciones (elegir una):

### Opción A — sFTP a NAS institucional (recomendada)

Configurar disco SFTP en `config/filesystems.php` y agregar al `BACKUP_DESTINATION_DISKS`:

```php
'sftp_institucional' => [
    'driver' => 'sftp',
    'host' => 'nas.cvaup-tachira.gob.ve',
    'username' => env('SFTP_USERNAME'),
    'password' => env('SFTP_PASSWORD'),
    'root' => '/backups/cvaup',
    'port' => 22,
],
```

En `config/backup.php`, sección `disks`:
```php
'disks' => ['local', 'sftp_institucional'],
```

### Opción B — Google Drive institucional

Requiere paquete adicional `masbug/flysystem-google-drive-ext`. Documentación específica disponible bajo solicitud al equipo de desarrollo.

### Opción C — Amazon S3 (o compatible: MinIO, Wasabi)

Laravel/Spatie soporta S3 nativamente:
```php
'disks' => ['local', 's3'],
```
Configurar credenciales AWS en `.env`.

### Opción D — Sincronización con `rsync` programado

```bash
# Ejecutar 30 min después del backup automático
05 03 * * * rsync -avz /var/www/cvaup/storage/app/private/cvaup-tachira/ \
            usuario@nas.cvaup.gob.ve:/backups/cvaup-tachira/
```

---

## 7. Configuración SMTP institucional para alertas

El sistema envía notificaciones por correo cuando:
- El backup falla durante 1 día
- Se detecta error crítico en el sistema

Configuración mínima en `.env` (ya documentada en sección 3.4).

**Validar funcionamiento**:

```bash
sudo -u www-data php /var/www/cvaup/artisan tinker
>>> Mail::raw('Prueba SMTP CVAUP', fn($m) => $m->to('alertas-ti@cvaup-tachira.gob.ve')->subject('Test'));
```

Si llega el correo, SMTP está bien configurado.

---

## 8. Validación post-deploy

Tras completar todo el despliegue, validar:

### 8.1 Acceso al sistema

```bash
curl -I https://cvaup-tachira.gob.ve/up
```

Debe responder `HTTP 200` y NO debe incluir headers `X-Powered-By` ni `Server: Apache/x.x.x` con versión.

### 8.2 Headers de seguridad activos

```bash
curl -sI https://cvaup-tachira.gob.ve/login | grep -iE "csp|hsts|x-frame|x-content|referrer|permissions"
```

Debe mostrar:
- `Content-Security-Policy: ...`
- `Strict-Transport-Security: max-age=63072000; ...`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), ...`

### 8.3 Login funcional

1. Acceder a `https://cvaup-tachira.gob.ve`.
2. Login con `ADMIN_EMAIL` y `ADMIN_INITIAL_PASSWORD` del `.env`.
3. Debe entrar al Dashboard.
4. **Cambiar la contraseña inmediatamente** vía Mi cuenta → Cambiar contraseña.

### 8.4 Backup automatizado

Esperar 24 horas tras el deploy (o adelantar la hora del cron para prueba):
1. Verificar que existe un nuevo `.zip` en `storage/app/private/cvaup-tachira/`.
2. Intentar abrirlo: debe pedir contraseña (la del `.env`).
3. Verificar que adentro NO hay `.env` (sí hay `db-dumps/` + `fotos-privadas/`).

### 8.5 Limpieza de backups legacy

Antes de declarar producción operativa, **borrar los respaldos generados durante QA**:

```bash
# Listar
ls -la /var/www/cvaup/storage/app/private/cvaup-tachira/

# Si todos son de QA, limpiarlos
sudo rm /var/www/cvaup/storage/app/private/cvaup-tachira/*.zip
```

El primer respaldo oficial será el del cron a las 02:30 AM siguiente.

---

## 9. Comandos de mantenimiento útiles

| Comando | Función |
|---------|---------|
| `php artisan schedule:list` | Ver tareas programadas |
| `php artisan backup:run` | Generar respaldo manualmente |
| `php artisan backup:list` | Listar respaldos |
| `php artisan backup:clean` | Aplicar política de retención (10 últimos) |
| `php artisan queue:work` | Procesar cola (si en el futuro se agrega) |
| `php artisan cache:clear` | Limpiar caché de aplicación |
| `php artisan config:cache` | Recachear configuración (post-cambios en `.env`) |
| `php artisan route:cache` | Recachear rutas |
| `php artisan optimize` | Optimización general post-deploy |

---

## 10. Operaciones críticas

### 10.1 Restaurar un respaldo

> Solo en caso de pérdida o corrupción de datos.

```bash
# 1. Detener el servicio (opcional, evita escrituras concurrentes)
sudo systemctl stop apache2

# 2. Descomprimir el .zip con la BACKUP_ARCHIVE_PASSWORD
cd /tmp
unzip -P "<password-del-env>" /var/www/cvaup/storage/app/private/cvaup-tachira/2026-XX-XX.zip

# 3. Restaurar la base de datos
mysql -u cvaup_app -p cvaup_reportes < db-dumps/mysql-cvaup_reportes.sql

# 4. Restaurar las fotos
sudo cp -r /tmp/storage/app/fotos-privadas/* /var/www/cvaup/storage/app/fotos-privadas/

# 5. Permisos
sudo chown -R www-data:www-data /var/www/cvaup/storage

# 6. Reanudar servicio
sudo systemctl start apache2
```

### 10.2 Reiniciar contraseña del admin (si se olvida)

```bash
cd /var/www/cvaup
sudo -u www-data php artisan tinker

>>> $u = App\Models\User::first();
>>> $u->password = Hash::make('NuevaContraseñaTemp-2026!');
>>> $u->save();
>>> exit
```

Entregar la nueva contraseña al admin por canal seguro. Solicitarle cambio inmediato al primer login.

### 10.3 Escalar capacidad si el sistema crece

| Síntoma | Solución |
|---------|----------|
| Carga lenta del Dashboard con muchos reportes | Aumentar `memory_limit` PHP a 1024M |
| Backup tarda más de 5 min | Optimizar mysqldump con `--single-transaction --quick` |
| Servidor saturado | Considerar separar BD en servidor dedicado |

---

## 11. Soporte técnico

### 11.1 Logs útiles para diagnóstico

| Log | Ubicación |
|-----|-----------|
| Aplicación Laravel | `/var/www/cvaup/storage/logs/laravel-YYYY-MM-DD.log` |
| Apache errores | `/var/log/apache2/cvaup-error.log` |
| Apache accesos | `/var/log/apache2/cvaup-access.log` |
| PHP errores | `/var/log/php/errors.log` |
| MySQL | `/var/log/mysql/error.log` |

### 11.2 Escalación

Si TI institucional encuentra problemas que no puede resolver con esta guía:
1. Capturar el log relevante.
2. Capturar la URL/acción que generó el problema.
3. Capturar la salida de `php artisan about` (información del entorno).
4. Contactar al equipo de desarrollo.

---

## 12. Checklist de deploy

Marcar cada item al completarlo:

- [ ] Servidor con PHP 8.3 + extensiones
- [ ] MySQL 8 con BD `cvaup_reportes` y usuario dedicado
- [ ] Apache con `mod_rewrite`, `mod_headers`, `mod_ssl` activos
- [ ] Código copiado a `/var/www/cvaup`
- [ ] `composer install --no-dev --optimize-autoloader` ejecutado
- [ ] `npm install && npm run build` ejecutado
- [ ] `.env` configurado con TODAS las variables (especialmente las marcadas críticas)
- [ ] `php artisan key:generate` ejecutado
- [ ] `php artisan migrate --force` ejecutado
- [ ] `php artisan db:seed --class=UbicacionesSeeder --force` ejecutado (carga 2207 CC)
- [ ] `php artisan db:seed --class=AdminUserSeeder --force` ejecutado
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache` ejecutados
- [ ] Permisos correctos: storage y bootstrap/cache writable por www-data
- [ ] VirtualHost Apache HTTPS configurado y activo
- [ ] Certificado SSL/TLS válido instalado
- [ ] Redirect HTTP→HTTPS funcionando
- [ ] PHP `expose_php = Off` y `display_errors = Off`
- [ ] Cron Laravel programado en crontab de www-data
- [ ] SMTP institucional configurado y validado
- [ ] Backup off-site configurado (sFTP / Drive / NAS / S3)
- [ ] Validación: `curl -I https://...` muestra todos los headers de seguridad
- [ ] Validación: login con admin funciona
- [ ] Admin cambió su contraseña inicial
- [ ] Backup automático del primer día generado y validado (cifrado, sin `.env`)
- [ ] Backup legacy del QA eliminado
- [ ] Documentación entregada al admin operativo (manual de uso)
- [ ] Acceso a logs documentado para futuros diagnósticos

---

**Documento elaborado para el equipo de TI institucional.**
**CVAUP Táchira — Mayo 2026**
