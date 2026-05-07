# Fase 15 — Smoke Tests E2E (Pre-Deploy)

**Sistema**: CVAUP Reportes — Sistema de Reportes Técnicos
**Fecha**: 2026-05-06
**Tester**: Guillermo (admin)
**Versión auditada**: post-hardening 14.5

---

## ¿Cómo usar este documento?

1. Recórrelo de **arriba hacia abajo** sin saltarte secciones.
2. Para cada punto:
   - **Lee la "Acción"** y ejecútala literalmente en el navegador.
   - **Lee el "Resultado esperado"** y compáralo con lo que ves.
   - **Marca el checkbox**: ✅ pasa / ❌ falla.
3. Si algo falla:
   - Anota en la sección **"Hallazgos"** al final.
   - Continúa con el resto (no te trabes).
4. Al terminar, lee el **Veredicto final**.

**Si una prueba falla**, NO entres en pánico — anótala y seguimos. Yo arreglo todo lo que falle al final, luego retesteamos solo lo arreglado.

---

## 0 · Pre-requisitos

Antes de empezar, confirma estos puntos:

- [ ] Vite dev server corriendo en una PowerShell (output: `➜ Local: http://127.0.0.1:5173/`)
- [ ] Laragon/Apache arriba (puedes acceder a `http://cvaup-reportes-app-web.test`)
- [ ] BD `cvaup_tachira` con: 0 técnicos · 0 reportes · 1 estado · 29 municipios · 2207 consejos comunales · 1 admin
- [ ] Navegador con caché limpio (hard-refresh ya hecho)
- [ ] Estás logueado como `admin@cvaup-tachira.com`

> Si alguno NO está cumplido, dímelo antes de avanzar.

---

## 1 · Autenticación y sesión

### 1.1 Login feliz path
**Acción**: cierra sesión (botón "Salir" arriba a la derecha) → ingresa email + tu contraseña actual → Submit

**Esperado**:
- Redirige a `/dashboard`
- Header muestra tu inicial verde + nombre

- [ ] Pasa

### 1.2 Login con contraseña incorrecta
**Acción**: cierra sesión → email correcto + contraseña inventada → Submit

**Esperado**:
- Mensaje rojo: "Estas credenciales no coinciden con nuestros registros."
- NO accede al dashboard

- [ ] Pasa

### 1.3 Login con email inválido
**Acción**: cierra sesión → email = `noexiste@example.com` + cualquier contraseña → Submit

**Esperado**:
- Mensaje de error genérico (NO debe decir "el email no existe" — eso filtraría info)

- [ ] Pasa

### 1.4 Brute force protection (rate limiting)
**Acción**: cierra sesión → intenta login con contraseña incorrecta **6 veces seguidas**

**Esperado**:
- Después del 5to intento falla con un mensaje tipo: "Demasiados intentos. Vuelve a intentar en X segundos"

- [ ] Pasa

### 1.5 Logout invalida sesión

**⚠️ Este test depende de HTTPS — solo se valida COMPLETAMENTE en producción.**

**Acción**: login normal → click "Salir" → intenta volver con flecha del navegador (atrás)

**Esperado en LOCAL (HTTP)**:
- Chrome puede mostrar el dashboard cacheado por bfcache (el header `Clear-Site-Data` se ignora en HTTP por decisión de seguridad de Chrome). Esto es comportamiento NORMAL en HTTP local. La sesión server-side SÍ está invalidada — si intentas hacer cualquier acción, el server te redirige.

**Esperado en PRODUCCIÓN (HTTPS)**:
- Redirige a `/login` (Clear-Site-Data borra todo + headers no-store + listener pageshow trabajan en conjunto)

**En local, validar manualmente que la sesión está invalidada**:
- Logout → en URL escribe `/dashboard` directamente (sin usar atrás) → debe redirigir a `/login`

- [ ] Pasa en LOCAL (test alternativo: URL directa redirige a /login)
- [ ] Documentado para re-validar en producción HTTPS

### 1.6 Acceso sin auth a ruta protegida
**Acción**: cierra sesión → escribe en URL: `cvaup-reportes-app-web.test/dashboard`

**Esperado**:
- Redirige a `/login`

- [ ] Pasa

---

## 2 · Perfil de usuario (`/perfil`)

### 2.1 Vista del perfil
**Acción**: login → click en tu avatar verde (esquina superior derecha)

**Esperado**:
- Carga `/perfil` con:
  - Header con tu inicial gigante + nombre + email + badge verde "Cuenta administradora"
  - Sección "Información de la cuenta"
  - Sección "Cambiar contraseña" con checklist verde de requisitos
  - Banner ámbar de seguridad abajo

- [ ] Pasa

### 2.2 Cambio de nombre
**Acción**: cambia tu nombre por "Administrador CVAUP Táchira (Test)" → click "Guardar cambios"

**Esperado**:
- Banner verde "Información de la cuenta actualizada."
- El nombre se actualiza en el header

- [ ] Pasa
- [ ] **Vuelve a cambiarlo** a "Administrador CVAUP Táchira" para limpiar el test.

### 2.3 Cambio de contraseña — caso feliz
**Acción**: en sección "Cambiar contraseña":
- Contraseña actual: tu password actual
- Nueva: `TestNuevaPwd-2026!`
- Confirmar: `TestNuevaPwd-2026!`
- Click "Actualizar contraseña"

**Esperado**:
- Banner verde "Contraseña actualizada con éxito."
- Los 4 checks del checklist están todos en verde mientras escribes

- [ ] Pasa

### 2.4 Cambio de contraseña — débil rechazada
**Acción**: contraseña actual correcta, nueva = `1234567` → Submit

**Esperado**:
- Error: "La contraseña debe tener al menos 8 caracteres" (o similar)

- [ ] Pasa

### 2.5 Cambio de contraseña — sin mayúscula
**Acción**: nueva = `solominusculas2026!`

**Esperado**:
- Error sobre mixedCase requerido

- [ ] Pasa

### 2.6 Cambio de contraseña — sin símbolo
**Acción**: nueva = `Cvaup2026sinsimbolos`

**Esperado**:
- Error sobre símbolo requerido

- [ ] Pasa

### 2.7 Cambio de contraseña — confirmación no coincide
**Acción**: nueva = `Pwd-A-2026!`, confirmar = `Pwd-B-2026!`

**Esperado**:
- Error: "La confirmación no coincide con la nueva contraseña."

- [ ] Pasa

### 2.8 Cambio de contraseña — contraseña actual incorrecta
**Acción**: contraseña actual = `xxxxxxxxx`, nueva = `Pwd-Valida-2026!`

**Esperado**:
- Error: "La contraseña actual no es correcta."

- [ ] Pasa
- [ ] **DEJA tu contraseña en `TestNuevaPwd-2026!`** o cámbiala por la que prefieras (ANÓTALA en gestor).

### 2.9 Toggle mostrar/ocultar contraseña
**Acción**: en el form de cambio, click en el ícono del ojo en cualquier campo

**Esperado**:
- El input cambia de `password` (puntitos) a `text` (letras visibles), y viceversa

- [ ] Pasa

---

## 3 · Técnicos (CRUD completo)

### 3.1 Lista vacía
**Acción**: menú lateral → "Técnicos"

**Esperado**:
- Tabla vacía (BD limpia) con mensaje "No hay técnicos registrados" o similar
- Botón "Nuevo técnico" visible

- [ ] Pasa

### 3.2 Crear técnico — feliz path
**Acción**: click "Nuevo técnico" → llena:
- Nombre: `Juan Pérez Test`
- Cédula: `V-12.345.678`
- Teléfono: `0414-1234567`
- Especialidad: `Agronomía urbana`
- Estado: Activo
- (Opcional) sube una foto JPG/PNG
- Submit

**Esperado**:
- Banner verde "Técnico creado correctamente."
- Aparece en la tabla

- [ ] Pasa

### 3.3 Crear técnico — cédula duplicada
**Acción**: crear OTRO técnico con cédula `V-12.345.678`

**Esperado**:
- Error rojo: "Ya existe un técnico con esa cédula" (o similar)

- [ ] Pasa

### 3.4 Crear técnico — cédula con formato inválido
**Acción**: crear con cédula = `12345678` (sin prefijo V-)

**Esperado**:
- Error: la cédula debe tener formato `V-12.345.678`

- [ ] Pasa

### 3.5 Ver técnico
**Acción**: click en el técnico de la lista

**Esperado**:
- Vista detalle con foto (si subiste), datos, contador de reportes (0)
- La foto se sirve desde `/fotos/tecnicos/...` (NO `/storage/fotos/...`)

- [ ] Pasa
- [ ] **Verifica en F12 → Network**: la URL de la foto debe contener `fotos/tecnicos/{id}` y NO `storage/fotos`

### 3.6 Editar técnico
**Acción**: click "Editar" → cambia teléfono → Guardar

**Esperado**:
- Banner verde de éxito + cambio reflejado

- [ ] Pasa

### 3.7 Eliminar técnico (soft-delete)
**Acción**: click "Eliminar" → confirma

**Esperado**:
- Banner verde, técnico desaparece de la lista
- (BD: registro queda con `deleted_at != null`)

- [ ] Pasa

### 3.8 Recrear técnico con misma cédula tras delete
**Acción**: crea de nuevo un técnico con cédula `V-12.345.678` (ya borrado en 3.7)

**Esperado**:
- Se crea exitosamente (la corrección del UNIQUE compuesto funciona)
- Banner verde

- [ ] Pasa

### 3.9 Búsqueda de técnico
**Acción**: en barra de búsqueda escribe "Juan"

**Esperado**:
- Filtra técnicos que contengan "Juan"

- [ ] Pasa

### 3.10 Toggle estado activo/inactivo
**Acción**: si hay UI para cambiar estado, ciclalo activo→inactivo→activo

**Esperado**:
- Cambia visualmente el estado, persiste tras refresh

- [ ] Pasa

> **Limpia**: borra todos los técnicos de prueba creados antes de seguir, dejando 0.

---

## 4 · Reportes (módulo más crítico)

### 4.1 Pre-condición
**Acción**: crea **1 técnico** llamado `Carlos Test` con cédula `V-99.999.999` (lo necesitas para los reportes).

- [ ] Hecho

### 4.2 Lista vacía
**Acción**: menú → "Reportes"

**Esperado**:
- Tabla vacía con CTA para crear primero

- [ ] Pasa

### 4.3 Crear reporte — caso feliz completo
**Acción**: click "Nuevo reporte" → llena TODO:
- Técnico: Carlos Test
- Fecha: HOY (debe ser día laborable)
- Estado: completo
- Municipio → Parroquia → Comuna → Consejo Comunal (selecciona en cascada)
- Lugar: `Sector Las Flores`
- Personas atendidas: 25
- Personas a beneficiar: 50
- Título actividad: `Capacitación huerto familiar`
- Rubro: `Solanum lycopersicum (tomate)`
- Fecha ejecución: HOY
- Resumen: `Asesoría técnica brindada al consejo comunal.`
- Sube 2 fotos (JPG/PNG)
- Submit

**Esperado**:
- Banner verde "Reporte creado correctamente."
- Aparece en la tabla
- Las fotos se comprimieron a WebP

- [ ] Pasa

### 4.4 Validación: 1 técnico = 1 reporte por fecha
**Acción**: intenta crear OTRO reporte con Carlos Test + misma fecha de hoy

**Esperado**:
- Error: ya existe un reporte para ese técnico en esa fecha

- [ ] Pasa

### 4.5 Validación: día no laborable rechazado
**Acción**: nuevo reporte con fecha = sábado o domingo más cercano

**Esperado**:
- Error: "La fecha debe ser un día laborable (lunes a viernes)" o similar

- [ ] Pasa

### 4.6 Validación: máximo 3 fotos
**Acción**: nuevo reporte con técnico distinto + intenta subir 4+ fotos

**Esperado**:
- Error: máximo 3 fotos permitidas

- [ ] Pasa

### 4.7 Ver reporte
**Acción**: click en el reporte creado en 4.3

**Esperado**:
- Vista detalle con todos los campos
- Las 2 fotos se ven (lightbox al click)
- URLs de fotos: `/fotos/reportes/{id}/{filename}.webp` (NO `/storage/...`)

- [ ] Pasa
- [ ] **F12 → Network**: confirma URLs de fotos

### 4.8 Editar reporte
**Acción**: click "Editar" → cambia "Personas atendidas" a 30 → Guardar

**Esperado**:
- Banner verde + cambio reflejado

- [ ] Pasa

### 4.9 Eliminar foto del reporte
**Acción**: editar reporte → quita 1 de las 2 fotos → Guardar

**Esperado**:
- Banner verde, queda 1 foto solamente

- [ ] Pasa

### 4.10 PDF individual del reporte
**Acción**: en vista de reporte, click "Descargar PDF"

**Esperado**:
- Descarga `reporte-{id}.pdf`
- Al abrirlo: cintillo institucional CVAUP arriba + datos del reporte + foto embebida + footer
- **NO** debe romperse por las URLs de fotos privadas (DomPDF usa path absoluto, no URL)

- [ ] Pasa

### 4.11 Eliminar reporte
**Acción**: click "Eliminar" → confirma

**Esperado**:
- Reporte desaparece + sus fotos se borran del disco

- [ ] Pasa

### 4.12 Búsqueda de reportes
**Acción**: crea 2 reportes con técnicos distintos → en barra de búsqueda escribe nombre del técnico

**Esperado**:
- Filtra los reportes correspondientes

- [ ] Pasa

### 4.13 Filtros
**Acción**: aplica filtros de Municipio + rango de fechas

**Esperado**:
- La lista se actualiza correctamente

- [ ] Pasa

> **Limpia**: borra todos los reportes y técnicos de prueba antes de seguir.

---

## 5 · Ubicaciones (árbol + tabla + import)

### 5.1 Vista árbol
**Acción**: menú → "Ubicaciones"

**Esperado**:
- Estado Táchira → 29 municipios visibles
- Click en un municipio → expande sus parroquias (lazy load)
- Click en parroquia → expande sus comunas
- Click en comuna → expande sus consejos comunales

- [ ] Pasa

### 5.2 Vista tabla (paginada)
**Acción**: cambia a vista "Tabla" con el toggle/botón

**Esperado**:
- Tabla con: Estado · Municipio · Parroquia · Comuna · Consejo Comunal · Reportes asociados
- Paginación abajo (probablemente 50/100 por página)
- Total: 2207 consejos comunales

- [ ] Pasa

### 5.3 Filtros en tabla
**Acción**: filtra por Municipio "San Cristóbal"

**Esperado**:
- Solo muestra consejos comunales de San Cristóbal

- [ ] Pasa

### 5.4 Búsqueda
**Acción**: barra de búsqueda → escribe "Bolívar" (o algo común)

**Esperado**:
- Resultados con breadcrumb completo (Estado › Municipio › Parroquia › ...)

- [ ] Pasa

### 5.5 Búsqueda con wildcards (anti-DoS)
**Acción**: barra de búsqueda → escribe `_` (solo guion bajo)

**Esperado**:
- Búsqueda funciona sin colapsar el server (el escape de SqlLike protege)

- [ ] Pasa

### 5.6 Crear nuevo consejo comunal
**Acción**: en una comuna existente, click "+ Nuevo consejo comunal" → ingresa nombre `CC Test 12345` → Submit

**Esperado**:
- Aparece bajo la comuna padre

- [ ] Pasa

### 5.7 Editar nombre
**Acción**: edita el CC creado → cambia a `CC Test 12345 (Editado)`

**Esperado**:
- Cambio reflejado

- [ ] Pasa

### 5.8 Eliminar
**Acción**: elimina el CC creado

**Esperado**:
- Desaparece del árbol/tabla

- [ ] Pasa

### 5.9 Exportar Excel (filtrado)
**Acción**: en vista tabla, aplica filtro de un municipio → click "Exportar Excel"

**Esperado**:
- Descarga `ubicaciones-{fecha}.xlsx`
- Al abrirlo: cintillo institucional + filas filtradas + headers

- [ ] Pasa
- [ ] **NO** existe botón PDF (eliminado en Fase 9 por decisión arquitectónica)

### 5.10 Importar plantilla
**Acción**: descargar plantilla desde el módulo de importar → abrirla en Excel

**Esperado**:
- Plantilla descarga con headers correctos

- [ ] Pasa

### 5.11 Importar archivo `.xls` (legacy) — debe rechazar
**Acción**: intenta subir un archivo `.xls` (no `.xlsx`)

**Esperado**:
- Error: "El archivo debe ser un .xlsx"

- [ ] Pasa

### 5.12 Importar archivo válido (preview)
**Acción**: usa la plantilla descargada con 2-3 filas dummy → "Vista previa"

**Esperado**:
- Muestra preview con cuántas filas se van a insertar/actualizar/saltar

- [ ] Pasa

### 5.13 Confirmar importación
**Acción**: en preview → "Confirmar"

**Esperado**:
- Banner verde con stats finales
- Las filas dummy aparecen en el árbol

- [ ] Pasa
- [ ] **Limpia**: borra las filas dummy si las creaste.

---

## 6 · Dashboard + Charts

### 6.1 Vista del Dashboard con BD vacía
**Acción**: ve a `/dashboard`

**Esperado**:
- 4 stat cards en 0
- Semáforo: "No hay técnicos activos para evaluar."
- Alertas: "Sin alertas. Todos los técnicos están al día."
- 3 charts con mensajes "Sin reportes este mes para mostrar"

- [ ] Pasa

### 6.2 Crear data para validar charts
**Acción**: ejecuta:
```bash
php artisan demo:poblar --tecnicos=5 --reportes=40 --force
```

**Esperado**:
- Mensaje "✓ Técnicos demo disponibles: 5" + "✓ Reportes demo creados: 40"

- [ ] Hecho

### 6.3 Ctrl+F5 al dashboard
**Acción**: refresh

**Esperado**:
- Stats > 0
- Semáforo con técnicos demo (rojos porque no reportaron HOY)
- Charts con barras/curvas/dountnut populadas

- [ ] Pasa

### 6.4 Tooltips de charts
**Acción**: hover sobre cualquier punto del chart de tendencia

**Esperado**:
- Tooltip aparece con fecha completa DD/MM/YYYY + número de reportes

- [ ] Pasa

### 6.5 Exportar resumen PDF
**Acción**: click "Exportar resumen PDF" arriba a la derecha

**Esperado**:
- Botón cambia a "Generando PDF..."
- Después de 1-2s descarga `dashboard-resumen-{fecha}.pdf`
- Al abrirlo:
  - Cintillo institucional arriba
  - Stats actuales
  - Semáforo en pills
  - 3 charts embebidos como imágenes
  - Lista de alertas si las hay

- [ ] Pasa

### 6.6 Click en alerta lleva al técnico
**Acción**: si hay alertas, click en una

**Esperado**:
- Lleva a la vista del técnico correspondiente

- [ ] Pasa

### 6.7 Limpieza después del test
**Acción**: ejecuta:
```bash
php artisan demo:limpiar --force
```

**Esperado**:
- "✓ Reportes [DEMO] eliminados: 40" + "✓ Técnicos [DEMO] eliminados: 5"

- [ ] Hecho

---

## 7 · Exportación masiva (módulo Exportar)

### 7.1 Pre-condición
**Acción**: crea data ficticia para tener algo que exportar:
```bash
php artisan demo:poblar --tecnicos=3 --reportes=20 --force
```

- [ ] Hecho

### 7.2 Vista del módulo
**Acción**: menú → "Exportar"

**Esperado**:
- 2 tarjetas/botones: PDF y Excel

- [ ] Pasa

### 7.3 Export PDF — feliz path
**Acción**: PDF → llena filtros (rango de fechas, técnicos opcional) → "Generar PDF"

**Esperado**:
- Descarga `reportes-{fecha}.pdf` con cintillo + tabla + footer

- [ ] Pasa

### 7.4 Export Excel — feliz path
**Acción**: Excel → mismos filtros → "Generar Excel"

**Esperado**:
- Descarga `reportes-{fecha}.xlsx`
- Al abrir: cintillo en filas 1-3 + tabla con datos desde fila 4

- [ ] Pasa

### 7.5 Excel formula injection neutralizada
**Acción**: edita un reporte → cambia "título actividad" a `=SUM(1+2+3)` → guarda → exporta Excel

**Esperado**:
- Al abrir el Excel, la celda muestra **literal `=SUM(1+2+3)`**, NO ejecuta la fórmula (NO muestra `6`)

- [ ] Pasa
- [ ] Restaura el título original del reporte después.

### 7.6 Rate limiting export
**Acción**: click "Generar PDF" 11 veces seguidas en menos de 1 minuto

**Esperado**:
- A partir del 11vo intento: error 429 "Too Many Requests"

- [ ] Pasa (puede no llegar a esto si el browser tarda en re-clickear)

### 7.7 Limpieza
```bash
php artisan demo:limpiar --force
```

- [ ] Hecho

---

## 8 · Backup

### 8.1 Vista del módulo
**Acción**: menú → "Backup"

**Esperado**:
- Lista de backups (probablemente vacía)
- Botón "Generar backup"

- [ ] Pasa

### 8.2 Generar backup
**Acción**: "Generar backup" → confirmar

**Esperado**:
- Spinner durante 5-15s
- Banner verde "Respaldo generado exitosamente"
- Aparece un .zip en la lista con tamaño y fecha

- [ ] Pasa

### 8.3 Backup cifrado (validación)
**Acción**: navega a `c:/laragon/www/CVAUP-Reportes-App-WEB/storage/app/private/cvaup-tachira/` → intenta abrir el .zip

**Esperado**:
- Windows/7-Zip te pide CONTRASEÑA al intentar entrar al zip
- Si pones la `BACKUP_ARCHIVE_PASSWORD` que configuraste → abre
- Si pones cualquier otra → falla

- [ ] Pasa

### 8.4 Backup NO incluye .env (HIGH #3 fix)
**Acción**: dentro del .zip (con la password), busca el archivo `.env`

**Esperado**:
- **NO** existe `.env` en el zip (corregimos esto en HIGH #3)

- [ ] Pasa

### 8.5 Descargar backup
**Acción**: en módulo Backup → click descargar el .zip

**Esperado**:
- Descarga el archivo correctamente

- [ ] Pasa

### 8.6 Path traversal bloqueado
**Acción**: en URL escribe: `cvaup-reportes-app-web.test/backups/../../etc/passwd/descargar`

**Esperado**:
- 404 o 403 (NO debe servir nada fuera del directorio de backups)

- [ ] Pasa

### 8.7 Eliminar backup
**Acción**: click eliminar el backup → confirmar

**Esperado**:
- .zip desaparece de la lista y del disco

- [ ] Pasa

---

## 9 · Verificaciones de seguridad post-hardening

### 9.1 Headers HTTP de seguridad
**Acción**: F12 → Network → click en la primera request del dashboard → tab "Headers" → "Response Headers"

**Esperado**: ver al menos estos headers:
- [ ] `Content-Security-Policy: default-src 'self'; ...`
- [ ] `X-Content-Type-Options: nosniff`
- [ ] `X-Frame-Options: DENY`
- [ ] `Referrer-Policy: strict-origin-when-cross-origin`
- [ ] `Permissions-Policy: camera=(), microphone=(), ...`

### 9.2 Console del navegador limpia
**Acción**: F12 → Console → recorre todas las páginas del sistema (dashboard, técnicos, reportes, ubicaciones, perfil)

**Esperado**:
- **CERO errores rojos** especialmente nada que diga "blocked by Content Security Policy"

- [ ] Pasa

### 9.3 Fotos de reportes con URL autenticada
**Acción**: F12 → Network → ve un reporte con foto

**Esperado**:
- URL de la foto: `/fotos/reportes/{id}/{filename}.webp`
- **NO** debe ser `/storage/fotos/...`

- [ ] Pasa

### 9.4 Foto de reporte requiere auth
**Acción**: copia la URL de una foto del network tab → cierra sesión → pega la URL en otra pestaña

**Esperado**:
- Redirige a `/login` (la foto NO se sirve sin auth)

- [ ] Pasa

### 9.5 Path traversal en fotos bloqueado
**Acción**: con sesión activa, ve a:
`cvaup-reportes-app-web.test/fotos/reportes/1/..%2F..%2Fetc%2Fpasswd`

**Esperado**:
- 404 (regex anti-traversal lo bloquea)

- [ ] Pasa

### 9.6 robots.txt restrictivo
**Acción**: ve a `cvaup-reportes-app-web.test/robots.txt`

**Esperado**:
```
User-agent: *
Disallow: /
```

- [ ] Pasa

### 9.7 X-Powered-By NO presente (en producción será removido por TI)
**Acción**: F12 → Network → cualquier request → Response Headers

**Esperado en local**: puede estar (PHP-FPM lo añade)
**En producción**: TI debe configurar `expose_php = Off`

- [ ] Anotado

---

## 10 · Edge cases adicionales

### 10.1 CSRF protección
**Acción**: F12 → Console → ejecuta:
```javascript
fetch('/tecnicos', { method: 'POST', body: JSON.stringify({nombre:'hack'}) })
  .then(r => console.log('Status:', r.status))
```

**Esperado**:
- Status 419 (CSRF token mismatch)

- [ ] Pasa

### 10.2 Backup sin password (debe fallar)
**Acción**: en `.env`, comenta temporalmente la línea `BACKUP_ARCHIVE_PASSWORD=...` con un `#` al inicio → `php artisan config:clear` → intenta generar backup desde la UI

**Esperado**:
- Banner rojo: "No se puede generar el backup: falta configurar BACKUP_ARCHIVE_PASSWORD"

- [ ] Pasa
- [ ] **DESCOMENTA la línea de `BACKUP_ARCHIVE_PASSWORD` y `php artisan config:clear` después del test**

### 10.3 Comando demo en producción (debe fallar)
**Acción**: en PowerShell:
```bash
$env:APP_ENV="production"
php artisan demo:poblar --force
```

**Esperado**:
- "❌ Este comando NO se puede ejecutar en producción."

- [ ] Pasa
- [ ] Restaura: `Remove-Item Env:APP_ENV` (o cierra y reabre PowerShell)

### 10.4 Sesión persiste tras cerrar/abrir navegador
**Acción**: cierra Chrome completamente → vuelve a abrirlo → ve al sistema

**Esperado**:
- Si configuraste "remember me" estás logueado
- Si no, redirige a login (correcto)

- [ ] Pasa

### 10.5 Lazy load del árbol de ubicaciones
**Acción**: F12 → Network → en `/ubicaciones` (vista árbol), click en un municipio que no hayas expandido

**Esperado**:
- Se hace una request AJAX a `/ubicaciones/children` (NO se cargaron los 2207 al inicio)

- [ ] Pasa

---

## Hallazgos

> Anota aquí cualquier prueba que falle. Formato:

| # | Sección | Qué falló | Severidad |
|---|---------|-----------|-----------|
| 1 |  |  |  |
| 2 |  |  |  |

**Ejemplo**:
| 1 | 4.10 | El PDF descarga pero la foto no aparece | HIGH |

---

## Veredicto final — EJECUTADO 2026-05-06

- ✅ **Total pasados**: **84 / 84**
- ❌ **Total fallidos**: 0
- 🐛 **Bugs descubiertos durante el QA y arreglados in-situ**: 8

### Bugs encontrados y corregidos durante el QA

| # | Bug | Sección | Severidad | Estado |
|---|-----|---------|-----------|--------|
| 1 | CSP local bloqueaba Vite (IPv6 corchetes) | Pre-test | HIGH | ✅ Fixed |
| 2 | bfcache muestra dashboard tras logout (HTTP local) | 1.5 | INFO | ⚠️ Limitación HTTP — funciona en HTTPS prod |
| 3 | Búsqueda con `_` y `%` no escapadas (DoS leve) | 5.6 | MEDIUM | ✅ Fixed (SqlLike global) |
| 4 | Modal de creación de ubicaciones mal diseñado | 5.7 | LOW | ✅ Fixed (rediseño completo) |
| 5 | Sin banner verde al eliminar ubicaciones | 5.9 | LOW | ✅ Fixed |
| 6 | Importer permitía crear estados duplicados | 5.13 | HIGH | ✅ Fixed (mono-estado) |
| 7 | Alertas y semáforo del Dashboard no clickeables | 6.6 | MEDIUM | ✅ Fixed (Inertia Link) |
| 8 | `demo:limpiar` no borraba (FK restrict + SoftDeletes) | 6.7 | HIGH | ✅ Fixed (forceDelete + cascade) |

### Mejoras adicionales aplicadas durante el QA

- Throttle backup: 3/h → 10/h (operativamente más realista)
- `KeepLastNStrategy` custom para Spatie cleanup (FIFO simple "últimos 10")
- Documentación Apache `ServerTokens Prod` agregada al deploy doc
- Limpieza de backups viejos sin cifrar (legacy pre-fix)

### Decisión

- [x] ✅ **APROBADO** — sistema listo para entrega a TI institucional.

### Notas para TI institucional (NO bloqueantes pero requeridas en deploy)

1. Configurar `.env` de producción según `docs/CONFIGURACION-ENV-POST-AUDITORIA.md`
2. PHP: `expose_php = Off` en `php.ini`
3. Apache: `ServerTokens Prod` + `ServerSignature Off` en `httpd.conf`
4. Cron de backup automático según `docs/DEPLOYMENT-BACKUP-PRODUCCION.md`
5. SMTP institucional para alertas de backup
6. Backup off-site (sFTP, Drive, NAS o S3 — opciones documentadas)
7. Forzar HTTPS (con HSTS funcionando)
8. Borrar backups locales generados durante QA antes del primer backup oficial de producción

### Mejoras cosméticas pendientes (no bloqueantes)

- Click en grupo "Técnicos" del sidebar debería ir directo a la lista (UX)
- `APP_NAME` en `.env` está como `Laravel` — cambiar a `CVAUP Táchira` para que aparezca en el `<title>`

---

## Para entregar a TI institucional

Junto con el código, entregar:
- `docs/AUDITORIA-SEGURIDAD.md` — reporte de auditoría completo
- `docs/CONFIGURACION-ENV-POST-AUDITORIA.md` — guía de configuración `.env` + Apache/Nginx
- `docs/FASE-15-SMOKE-TESTS.md` (este archivo, completado) — evidencia de QA
- `docs/DEPLOYMENT-BACKUP-PRODUCCION.md` — playbook de cron + SMTP + off-site
