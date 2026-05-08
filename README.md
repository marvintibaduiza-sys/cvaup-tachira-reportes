# Sistema de Reportes Técnicos — CVAUP Táchira

> Plataforma institucional para el registro y consolidación digital de las actividades técnicas de la **Corporación Venezolana para la Agricultura Urbana y Periurbana del Estado Táchira**.

[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js)](https://vuejs.org)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql)](https://mysql.com)
[![Tests](https://img.shields.io/badge/E2E_tests-84%2F84_passed-brightgreen)](docs/FASE-15-SMOKE-TESTS.md)
[![Security](https://img.shields.io/badge/security_audit-passed-brightgreen)](docs/AUDITORIA-SEGURIDAD.md)

---

## 🎯 Propósito

Este sistema responde al mandato del Ejecutivo Nacional sobre **simplificación de trámites administrativos** y **digitalización del Estado venezolano**, materializando los principios constitucionales de **eficacia, eficiencia, transparencia y rendición de cuentas** (Art. 141 CRBV).

### Reducción de tiempos operativos

| Tarea | Antes (manual) | Ahora | Reducción |
|-------|----------------|-------|-----------|
| Registrar reporte técnico | 30-45 min | 3-5 min | **~90 %** |
| Consolidar 100 reportes | 2-3 días | < 1 min | **~99 %** |
| Responder a entes contralores | 1 semana | 2 min | **~99 %** |
| Localizar reportes por municipio | Manual / imposible | < 5 seg | **inmediato** |

---

## 📦 Capacidades del sistema

### 7 módulos funcionales

| Módulo | Descripción |
|--------|-------------|
| 📊 **Dashboard** | Panel ejecutivo con indicadores en tiempo real, semáforo de cumplimiento, alertas y gráficos de tendencia. Exportable como informe ejecutivo PDF. |
| 📝 **Reportes** | Registro de actividades técnicas con geo-referenciación, fotografías y resumen narrativo. Genera PDF oficial firmable. |
| 👤 **Técnicos** | Catálogo del personal de campo con cédula, especialidad, foto institucional y estado activo/inactivo. |
| 📍 **Ubicaciones** | Árbol jerárquico Estado → Municipio → Parroquia → Comuna → Consejo Comunal con los **2.207 consejos comunales** del Estado Táchira. |
| 📤 **Exportación** | Generación masiva de reportes en PDF (entrega oficial) y Excel (análisis posterior). |
| 💾 **Respaldo** | Generación cifrada (AES-256) automática diaria + manual bajo demanda. Política FIFO de retención (últimos 10). |
| ⚙ **Configuración** | Gestión del perfil del administrador y cambio seguro de contraseña. |

### Cobertura territorial

- 1 estado: **Táchira**
- 29 municipios
- 59 parroquias
- 147 comunas
- **2.207 consejos comunales**

---

## 🔒 Seguridad institucional

- 🛡 **Auditoría de seguridad ejecutada** — 24 hallazgos identificados y corregidos
- 🔐 **Cifrado AES-256** en todos los respaldos
- 🚧 **Política de contraseñas robusta** (anti-fuga, anti-diccionario via haveibeenpwned)
- 📋 **Headers HTTP completos**: CSP, HSTS, X-Frame-Options, Cache-Control no-store, Referrer-Policy, Permissions-Policy
- 🚦 **Rate limiting** anti-DoS en operaciones pesadas
- 🌐 **100 % on-premise** — sin dependencia de servicios cloud externos (soberanía de datos)
- ✅ **0 vulnerabilidades** en `composer audit` y `npm audit`

Reporte completo de auditoría: [docs/AUDITORIA-SEGURIDAD.md](docs/AUDITORIA-SEGURIDAD.md)

---

## 🧪 Calidad

- ✅ **84 / 84** tests E2E funcionales pasados (100 %)
- ✅ **8 bugs detectados y arreglados** durante el QA
- ✅ **Documentación profesional completa** (89 páginas en 9 documentos)

Plan de smoke tests con resultados: [docs/FASE-15-SMOKE-TESTS.md](docs/FASE-15-SMOKE-TESTS.md)

---

## 📚 Documentación

Toda la documentación oficial está organizada por audiencia en [`docs/`](docs/):

| Documento | Audiencia |
|-----------|-----------|
| [00-INDICE.md](docs/00-INDICE.md) | Índice maestro |
| [01-DESCRIPCION-DEL-SISTEMA.md](docs/01-DESCRIPCION-DEL-SISTEMA.md) | **Autoridades institucionales** |
| [02-MANUAL-DE-USO.md](docs/02-MANUAL-DE-USO.md) | **Administrador del sistema** |
| [03-GUIA-DESPLIEGUE-TI.md](docs/03-GUIA-DESPLIEGUE-TI.md) | **Equipo de TI institucional** |
| [04-GUION-PRESENTACION-EJECUTIVA.md](docs/04-GUION-PRESENTACION-EJECUTIVA.md) | Presentación oral |
| [AUDITORIA-SEGURIDAD.md](docs/AUDITORIA-SEGURIDAD.md) | Auditores / contralorías |
| [FASE-15-SMOKE-TESTS.md](docs/FASE-15-SMOKE-TESTS.md) | Evidencia de QA |
| [CONFIGURACION-ENV-POST-AUDITORIA.md](docs/CONFIGURACION-ENV-POST-AUDITORIA.md) | TI técnico |
| [DEPLOYMENT-BACKUP-PRODUCCION.md](docs/DEPLOYMENT-BACKUP-PRODUCCION.md) | TI ops |

---

## 🛠 Stack técnico

```
Backend:   Laravel 11 (PHP 8.3+)
Frontend:  Vue 3 + Inertia.js + Tailwind CSS
DB:        MySQL 8 (también compatible con MariaDB 10.6+)
PDF:       DomPDF
Excel:     Maatwebsite/Excel + PhpSpreadsheet
Charts:    Chart.js v4 + vue-chartjs
Backup:    spatie/laravel-backup (con cifrado AES-256)
Auth:      Laravel Breeze (mono-usuario)
Build:     Vite
```

**100 % software libre · cero costos de licenciamiento · cero dependencias cloud externas.**

---

## 🚀 Despliegue rápido

> Para despliegue en producción institucional, ver [docs/03-GUIA-DESPLIEGUE-TI.md](docs/03-GUIA-DESPLIEGUE-TI.md) (guía paso a paso completa).

### Setup local de desarrollo

```bash
# 1. Clonar el repositorio
git clone https://github.com/<tu-usuario>/cvaup-reportes-app-web.git
cd cvaup-reportes-app-web

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Editar .env con tus credenciales locales:
#    - DB_DATABASE, DB_USERNAME, DB_PASSWORD
#    - ADMIN_EMAIL, ADMIN_INITIAL_PASSWORD
#    - BACKUP_ARCHIVE_PASSWORD

# 5. Crear BD y migrar
php artisan migrate
php artisan db:seed --class=UbicacionesSeeder   # Carga 27 mun + 54 parr + 147 com + 2207 CCs desde docs/CVAUP_TACHIRA.xlsx
php artisan db:seed --class=AdminUserSeeder     # Crea el admin con ADMIN_EMAIL + ADMIN_INITIAL_PASSWORD del .env

# 6. Compilar assets y arrancar Vite
npm run build
npm run dev    # mantener corriendo en una terminal

# 7. Acceder al sistema en tu dominio local (Laragon)
```

### Variables de entorno mínimas

```env
APP_NAME="CVAUP Táchira"
APP_ENV=local
APP_KEY=                            # php artisan key:generate
APP_DEBUG=true
APP_URL=http://cvaup-reportes-app-web.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cvaup_tachira
DB_USERNAME=root
DB_PASSWORD=

ADMIN_EMAIL=admin@cvaup-tachira.com
ADMIN_INITIAL_PASSWORD=DevLocal-2026!
BACKUP_ARCHIVE_PASSWORD=DevBackup-2026!
```

---

## 📋 Marco normativo

Esta plataforma materializa lineamientos del Estado venezolano:

- **Constitución de la República Bolivariana de Venezuela**, Art. 110 (ciencia y tecnología como interés público) y Art. 141 (eficacia y eficiencia administrativa)
- **Decreto-Ley de Simplificación de Trámites Administrativos** (celeridad, oportunidad, desburocratización)
- **Plan de la Patria 2025-2031**, Objetivo Nacional 2.5.5 (digitalización progresiva de la gestión pública)
- **Decreto N° 825** (uso prioritario de internet en la Administración Pública)

---

## 📜 Licencia

Sistema desarrollado para uso institucional de la **Corporación Venezolana para la Agricultura Urbana y Periurbana del Estado Táchira**.

Todos los derechos reservados — CVAUP Táchira, 2026.

---

## 📞 Contacto

Para soporte técnico institucional o solicitudes de mejora, contactar al equipo de TI de CVAUP Táchira.

---

**Versión 1.0.0 — Mayo 2026**
