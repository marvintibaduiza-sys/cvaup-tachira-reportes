# Variables `.env` para producción (referencia)

> **Para:** técnico de TI de CVAUP que configura el `.env` del servidor de producción
> **NO copiar tal cual:** valores son ejemplos. Cada institución pone los suyos.

## ⚙️ Variables base de Laravel (cambiar de `local` a `production`)

```env
APP_NAME="CVAUP Tachira"
APP_ENV=production              # ← CRÍTICO: cambia el comportamiento del sistema
APP_KEY=base64:GENERAR_NUEVA_AL_DESPLEGAR
APP_DEBUG=false                 # ← CRÍTICO: false en producción para no exponer errores
APP_URL=https://reportes.cvaup-tachira.gob.ve

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_VE
```

## 🗄️ Base de datos (servidor de producción)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1                              # o IP del servidor MySQL si es separado
DB_PORT=3306
DB_DATABASE=cvaup_tachira
DB_USERNAME=cvaup_app                          # usuario dedicado, NO root
DB_PASSWORD=GENERAR_PASSWORD_FUERTE_AQUI       # nunca usar password vacía en prod
```

## 📧 SMTP para notificaciones de backup (Paso 2 del playbook)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com                       # o el SMTP institucional
MAIL_PORT=587
MAIL_USERNAME=sistema@cvaup-tachira.gob.ve
MAIL_PASSWORD=APP_PASSWORD_DE_GMAIL_O_PROPIO
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=sistema@cvaup-tachira.gob.ve
MAIL_FROM_NAME="CVAUP Táchira — Sistema"

# Destino de las alertas críticas:
BACKUP_NOTIFICATIONS_EMAIL=admin-sistemas@cvaup-tachira.gob.ve
```

## 💾 Configuración del nombre del backup (opcional)

```env
# Carpeta donde Spatie guarda los backups locales.
# Default: 'cvaup-tachira'. Cambiar solo si hay multi-tenancy.
BACKUP_NAME=cvaup-tachira
```

## ☁️ Destino off-site — Opción A: Google Drive (Paso 3 del playbook)

Si eligen Google Drive como destino off-site:

```env
GOOGLE_DRIVE_CLIENT_ID=tu-client-id.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=tu-client-secret
GOOGLE_DRIVE_REFRESH_TOKEN=el-token-generado-via-oauth
GOOGLE_DRIVE_FOLDER=CVAUP-Backups
```

## ☁️ Destino off-site — Opción B: SFTP a otro servidor

Si eligen SFTP a otro servidor del Estado:

```env
SFTP_HOST=backup-server.cvaup.gob.ve
SFTP_PORT=22
SFTP_USER=cvaup-backup
SFTP_KEY_PATH=/var/www/cvaup/.ssh/id_rsa_backup
# o usar password en lugar de key:
# SFTP_PASSWORD=password-del-usuario-sftp
```

## ☁️ Destino off-site — Opción C: AWS S3 / DigitalOcean Spaces

Si eligen S3 (requiere USD):

```env
AWS_ACCESS_KEY_ID=AKIAXXXXXXXX
AWS_SECRET_ACCESS_KEY=secretXXXXXXXXXX
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=cvaup-tachira-backups
AWS_ENDPOINT=                                  # vacío para AWS, lleno para Spaces
AWS_USE_PATH_STYLE_ENDPOINT=false
```

## 🔐 Sesiones y caché (production-grade)

```env
# En producción usar Redis si está disponible (más rápido + permite multi-server).
# Si no hay Redis, dejar 'database' que es lo que tiene CVAUP por default.
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

## ✅ Checklist post-configuración del `.env` en producción

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` generado con `php artisan key:generate` (NO copiar el de dev)
- [ ] DB con usuario dedicado (NO root) y password fuerte
- [ ] `php artisan config:cache` (cachea config para mejor performance)
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] SMTP probado con `php artisan tinker` + `Mail::raw(...)`
- [ ] Cron del sistema activo (Paso 1 del playbook)
- [ ] Permisos correctos: `chmod -R 775 storage/ bootstrap/cache/`
- [ ] HTTPS configurado (Let's Encrypt o cert institucional)

> Cuando tengas todo listo, ejecuta `php artisan backup:run` UNA VEZ manualmente para verificar que el sistema completo funciona end-to-end antes de dejarlo en automático.
