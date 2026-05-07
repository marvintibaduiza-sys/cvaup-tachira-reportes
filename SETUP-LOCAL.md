# Setup Local — CVAUP Táchira

> Este archivo describe los pasos manuales mínimos que el agente NO puede hacer por restricciones de permisos del entorno (acceso a `.env` bloqueado por seguridad).

## 1) Crear la base de datos MySQL

Abrir **HeidiSQL** desde Laragon (o terminal MySQL) y ejecutar:

```sql
CREATE DATABASE cvaup_tachira CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 2) Editar el archivo `.env` (en la raíz del proyecto)

Reemplazar/verificar las siguientes claves:

```env
APP_NAME="CVAUP Tachira"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://cvaup-reportes-app-web.test
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_VE

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cvaup_tachira
DB_USERNAME=root
DB_PASSWORD=
```

Guarda el archivo.

## 3) Reiniciar Laragon

Para que reconozca el nuevo virtual host `cvaup-reportes-app-web.test` y limpie la cache de configuración. Luego ejecutar (en la terminal del proyecto):

```bash
php artisan config:clear
```

## 4) Avísale al agente para continuar

Cuando termines los pasos 1-3, escribe `listo` o `continuar` y el agente seguirá con la instalación de Breeze + Vue + Tailwind, las migraciones y los seeders.

---

## Credenciales de la app (después del seeder)

- **Email:** `admin@cvaup-tachira.com`
- **Password:** `cvaup2026`
