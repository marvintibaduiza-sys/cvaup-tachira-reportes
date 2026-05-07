# Playbook de despliegue: Sistema de Backup en Producción

> **Para:** técnico de TI de CVAUP que despliega el sistema en el servidor institucional
> **Cuándo aplicar:** UNA SOLA VEZ, después del primer despliegue a producción
> **Pre-requisito:** sistema funcionando manualmente (probado en dev)

## 🎯 Resumen de lo que vas a configurar

```
1. Activar el cron del servidor para que dispare Laravel Scheduler cada minuto
2. Configurar SMTP para que lleguen alertas por email cuando algo falle
3. (Opcional pero recomendado) Configurar destino remoto (Drive/SFTP/NAS)
   para tener copia off-site de los backups
```

Tiempo estimado: **30-60 minutos** según la infraestructura disponible.

---

## 📦 Lo que YA está construido en el código (no requiere acción)

✅ Cronograma definido en `routes/console.php`:
```
02:00 AM diario  → backup:clean (limpia antiguos)
02:30 AM diario  → backup:run (genera backup nuevo)
Lunes 03:00 AM  → backup:monitor (verifica salud)
```

✅ Política de retención: mantiene últimos **10 backups**, elimina automáticamente los más antiguos.

✅ Contenido del backup: dump completo de BD `cvaup_tachira` + carpeta `storage/app/public/fotos` + archivo `.env`.

✅ UI manual disponible en `/backups` — generar/descargar/eliminar manualmente.

✅ Notificaciones por email definidas (faltan credenciales SMTP).

---

## 🔧 Paso 1 — Activar el cron del servidor

### 🐧 Si es servidor Linux (Ubuntu/Debian/CentOS)

Editar el crontab del usuario que ejecuta PHP/Apache (típicamente `www-data` o el usuario de la app):

```bash
sudo crontab -u www-data -e
```

Agregar UNA SOLA LÍNEA:

```cron
* * * * * cd /var/www/cvaup-reportes && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Donde:
- `/var/www/cvaup-reportes` es la ruta donde clonaste el repo
- `/usr/bin/php` es la ruta del binario PHP (verifícala con `which php`)

Verificar que está activo:
```bash
sudo systemctl status cron
sudo crontab -u www-data -l
```

### 🪟 Si es Windows Server

Igual que en una laptop normal con Task Scheduler — solo que el server está encendido 24/7:

1. **Win+R** → `taskschd.msc`
2. **Crear tarea** (NO básica — la avanzada con permisos elevados)
3. **General:** marcar "Ejecutar tanto si el usuario inició sesión como si no" + "Ejecutar con privilegios más altos"
4. **Desencadenadores:** Iniciar diario, repetir cada **1 minuto**, indefinidamente
5. **Acciones:** Iniciar programa
   - Programa: `C:\path\to\php.exe`
   - Argumentos: `artisan schedule:run`
   - Iniciar en: `C:\path\to\cvaup-reportes`
6. **Condiciones:** desmarcar "solo con AC" si es server con UPS
7. **Configuración:** "no iniciar nueva instancia" (evita solapamiento)

### Verificar que funciona

Después de configurar el cron, espera 2 minutos y ejecuta:

```bash
cd /var/www/cvaup-reportes  # o C:\path\... en Windows
php artisan schedule:list
```

Debe listar las 3 tareas con "Next Due".

---

## 📧 Paso 2 — Configurar SMTP para alertas

Las notificaciones por email solo funcionan si hay un servidor SMTP configurado en `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com           # o el SMTP institucional
MAIL_PORT=587
MAIL_USERNAME=sistema@cvaup.gob.ve  # email institucional
MAIL_PASSWORD=xxxxxxxxxxxxxxxx      # contraseña o app password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=sistema@cvaup.gob.ve
MAIL_FROM_NAME="CVAUP Táchira — Sistema"

# Email destino de las alertas críticas:
BACKUP_NOTIFICATIONS_EMAIL=admin-sistemas@cvaup.gob.ve
```

### Opciones de SMTP gratuitas/institucionales

| Servicio | Limitación | Notas |
|----------|------------|-------|
| **Gmail SMTP** | 500 emails/día | Requiere [App Password](https://support.google.com/accounts/answer/185833) si tiene 2FA |
| **Microsoft 365** | Si CVAUP usa Office 365, ya tienen SMTP institucional incluido | Ideal — corporativo |
| **SMTP institucional propio** | Sin límite | Si CVAUP tiene Postfix/Exim configurado |
| **Mailgun / SendGrid** | 100 emails/día gratis | Si todo lo anterior falla |

### Probar que funciona

```bash
php artisan tinker
> Mail::raw('Test desde CVAUP', fn($m) => $m->to('admin-sistemas@cvaup.gob.ve')->subject('Test SMTP'));
```

Si llega el email, está OK. Si no, revisa `.env` y `storage/logs/laravel.log`.

---

## ☁️ Paso 3 — Destino off-site (CRÍTICO institucionalmente)

Sin esto, el backup queda **solo en el mismo servidor que respalda**. Si el servidor se daña/incendia, los backups se pierden con él. La regla 3-2-1 dice: **2 copias en medios distintos, 1 fuera del sitio**.

### Opciones según infraestructura disponible

#### Opción A — Otro servidor del Estado (vía SFTP)

Si CVAUP o un Ministerio relacionado tiene otro servidor (ej: VPS gubernamental, server en otra ciudad):

```bash
composer require league/flysystem-sftp-v3
```

Configurar disco SFTP en `config/filesystems.php`:
```php
'sftp_offsite' => [
    'driver' => 'sftp',
    'host' => env('SFTP_HOST'),
    'username' => env('SFTP_USER'),
    'privateKey' => env('SFTP_KEY_PATH'),  // o password
    'port' => env('SFTP_PORT', 22),
    'root' => '/backups/cvaup-tachira/',
],
```

Agregar a `config/backup.php`:
```php
'disks' => ['local', 'sftp_offsite'],
```

**Pro:** sin USD, sin servicios externos, control total
**Contra:** depende de tener acceso a otro servidor del Estado

#### Opción B — Google Drive (cuenta institucional)

Si CVAUP tiene cuenta `@cvaup.gob.ve` en Google Workspace:

```bash
composer require masbug/flysystem-google-drive-ext
```

Setup OAuth (~30 min, hay que hacerlo manualmente desde Google Cloud Console):
1. Ir a https://console.cloud.google.com/
2. Crear proyecto "CVAUP Backups"
3. Habilitar Google Drive API
4. Crear credenciales OAuth (tipo "Desktop")
5. Descargar `client_id` + `client_secret`
6. Generar `refresh_token` con un script auxiliar (puedes pedírselo al desarrollador)

Configurar disco en `config/filesystems.php`:
```php
'google' => [
    'driver' => 'google',
    'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
    'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
    'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
    'folder' => 'CVAUP-Backups',
],
```

Agregar a `config/backup.php`:
```php
'disks' => ['local', 'google'],
```

**Pro:** 15 GB gratis, sin USD, accesible desde cualquier computadora con la cuenta CVAUP
**Contra:** setup OAuth tiene curva inicial, depende de internet

#### Opción C — Disco externo / NAS de la institución

Si CVAUP tiene un NAS Synology/QNAP o disco externo con SMB:
1. Montar el NAS como disco de red en el servidor
2. Configurar Spatie disco `local` apuntando al path del NAS
3. Listo — el backup se escribe directo al NAS

**Pro:** infraestructura del Estado, sin servicios externos
**Contra:** si el desastre es físico (incendio, inundación), pierdes ambos servidor + NAS si están en el mismo edificio

#### Opción D — AWS S3 / DigitalOcean Spaces / Backblaze B2

Si CVAUP puede pagar USD (por terceros o tarjeta institucional internacional):

```bash
composer require league/flysystem-aws-s3-v3
```

Setup standard de credenciales en `.env`. Costo: $3-15 USD/año típicamente.

**Pro:** confiabilidad enterprise, sin gestionar infraestructura
**Contra:** requiere USD

### 🎯 Recomendación de orden a evaluar

```
1. ¿Hay otro servidor del Estado accesible? → SFTP (Opción A)
2. ¿CVAUP tiene Google Workspace? → Google Drive (Opción B)
3. ¿Hay NAS institucional? → Disco montado (Opción C) + Drive como tercera capa
4. ¿Hay presupuesto USD? → S3/B2 (Opción D)
```

Lo importante: **al menos UNA opción off-site activada antes de que el sistema entre en uso real**.

---

## 🧪 Paso 4 — Validación post-despliegue

Después de configurar TODO, hacer este checklist:

- [ ] `php artisan schedule:list` muestra las 3 tareas
- [ ] `php artisan backup:run` se ejecuta sin errores y genera un .zip en `storage/app/private/cvaup-tachira/`
- [ ] (Si configuraste SMTP) Forzar un fallo (ej: poner BD inaccesible) y verificar que llega email
- [ ] (Si configuraste destino remoto) Verificar que el .zip aparece también en el remoto
- [ ] Esperar al primer 02:30 AM y verificar al día siguiente que el backup automático ocurrió
- [ ] Acceder a `/backups` y ver el nuevo backup con timestamp 02:30 + indicador "Generado automáticamente"

---

## 🆘 Troubleshooting común en producción

| Síntoma | Causa probable | Fix |
|---------|----------------|-----|
| Schedule no se ejecuta | Cron no instalado/sin permisos | `sudo systemctl restart cron` y verificar crontab |
| `backup:run` falla con "mysqldump not found" | PATH del system no incluye mysql | Configurar `dump_binary_path` en `config/database.php` |
| Permission denied al escribir | Usuario del cron sin permisos en `storage/` | `sudo chown -R www-data:www-data storage/` |
| Email no llega | SMTP mal configurado | `php artisan tinker` + Mail::raw() de prueba |
| Backup tarda >10 min | BD muy grande o disco lento | Considerar `--only-db` para backup más frecuente solo de la BD |
| Disco lleno | Backups acumulados | Verificar que `backup:clean` está ejecutándose, ajustar política de retención |

---

## 📞 Contacto desarrollador

Si después de seguir este playbook hay algo que no funciona, contactar al desarrollador con:
- Output de `php artisan schedule:list`
- Últimas 100 líneas de `storage/logs/laravel.log`
- Versión de PHP (`php -v`) y SO (`uname -a` o `systeminfo`)
- Qué destino remoto eligieron (A, B, C, D)
