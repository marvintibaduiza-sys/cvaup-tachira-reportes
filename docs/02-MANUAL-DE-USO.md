# Manual de Uso — Sistema de Reportes Técnicos CVAUP Táchira

**Documento operativo dirigido al administrador del sistema.**
**Versión 1.0.0 — Mayo 2026**

---

## Índice

1. [Acceso al sistema](#1-acceso-al-sistema)
2. [Estructura general de la plataforma](#2-estructura-general-de-la-plataforma)
3. [Dashboard — Panel de control](#3-dashboard--panel-de-control)
4. [Gestión de Técnicos](#4-gestión-de-técnicos)
5. [Registro de Reportes](#5-registro-de-reportes)
6. [Gestión de Ubicaciones](#6-gestión-de-ubicaciones)
7. [Exportación de información](#7-exportación-de-información)
8. [Respaldos del sistema](#8-respaldos-del-sistema)
9. [Mi cuenta — Perfil del administrador](#9-mi-cuenta--perfil-del-administrador)
10. [Casos de uso típicos](#10-casos-de-uso-típicos)
11. [Resolución de problemas comunes](#11-resolución-de-problemas-comunes)
12. [Glosario](#12-glosario)

---

## 1. Acceso al sistema

### 1.1 Ingreso inicial

1. Abrir el navegador web (Chrome, Edge o Firefox actualizados).
2. Escribir la dirección institucional del sistema (la entrega TI):
   ```
   https://cvaup-tachira.gob.ve
   ```
3. Aparece la pantalla de inicio de sesión.
4. Ingresar:
   - **Correo electrónico**: el asignado por TI institucional (ej. `admin@cvaup-tachira.gob.ve`)
   - **Contraseña**: la entregada en sobre cerrado por TI
5. Hacer click en **"Iniciar sesión"**.

### 1.2 Cambio obligatorio de contraseña inicial

Al primer ingreso, **cambiar inmediatamente la contraseña inicial**:

1. Hacer click en el círculo verde con su inicial (esquina superior derecha).
2. Se abre la página **"Mi cuenta"**.
3. En la sección **"Cambiar contraseña"**:
   - Ingresar la contraseña actual (la entregada por TI)
   - Crear una nueva contraseña que cumpla TODOS los requisitos del checklist:
     - ✓ Mínimo 8 caracteres
     - ✓ Al menos una mayúscula y una minúscula
     - ✓ Al menos un número
     - ✓ Al menos un símbolo (!?.@#$ ...)
   - Confirmar la nueva contraseña
4. Hacer click en **"Actualizar contraseña"**.
5. Aparece un banner verde de confirmación.

> **Importante**: anote su nueva contraseña en un lugar seguro (gestor de contraseñas institucional). Si la olvida, solo TI institucional puede recuperarla con acceso al servidor.

### 1.3 Cierre de sesión

Hacer click en el botón **"Salir"** (esquina superior derecha junto al avatar). El sistema cierra la sesión y redirige al login.

---

## 2. Estructura general de la plataforma

### 2.1 Áreas de la pantalla

```
┌─────────────────────────────────────────────────────────────────────┐
│  [LOGO CVAUP]    [Título]                  [Avatar]  [Salir]        │ ← Encabezado
├──────────────┬──────────────────────────────────────────────────────┤
│  📊 Dashboard │                                                     │
│  📄 Reportes  │                                                     │
│  👤 Técnicos  │            CONTENIDO PRINCIPAL                      │
│  📍 Ubicacion │                                                     │
│  ⬇  Exportar │                                                     │
│  ☁  Respaldo │                                                     │
│  ⚙  Config   │                                                     │
│              │                                                     │
└──────────────┴──────────────────────────────────────────────────────┘
   ↑ Menú lateral                ↑ Área de trabajo
```

### 2.2 Navegación

- **Menú lateral**: contiene los 6 módulos principales más Configuración.
- **Hacer click en el nombre del módulo** (ej. "Técnicos") → lleva directamente al listado.
- **Hacer click en la flecha (▶)** del módulo → expande las sub-opciones (Listado, Registrar nuevo, etc.).
- **Botón hamburguesa (≡)**: contrae/expande el menú lateral para tener más espacio de trabajo.

### 2.3 Mensajes del sistema

- 🟢 **Banner verde**: operación exitosa (creación, edición, eliminación).
- 🔴 **Banner rojo / cuadros rojos**: error o validación fallida.
- 🟡 **Banner ámbar**: advertencia que requiere atención.
- ✅ Todos los mensajes son auto-descriptivos en español.

---

## 3. Dashboard — Panel de control

El Dashboard es la primera pantalla que ve al ingresar. Ofrece una **vista panorámica** del estado operativo de CVAUP Táchira.

### 3.1 Componentes del Dashboard

#### Tarjetas de indicadores (parte superior)

| Indicador | Significado |
|-----------|-------------|
| **Técnicos activos** | Cantidad de personal de campo registrado y operativo |
| **Reportes del mes** | Total de actividades registradas en el mes en curso |
| **Reportes de la semana** | Total de actividades de la semana actual |
| **Personas atendidas (mes)** | Suma de personas atendidas en el mes |

#### Semáforo de cumplimiento

Lista los técnicos con un círculo de color que indica su estado del día:

- 🟢 **Verde**: el técnico reportó hoy y el reporte está completo.
- 🟡 **Amarillo**: reportó hoy pero el reporte tiene campos incompletos.
- 🔴 **Rojo**: día laborable pero aún no ha reportado.
- ⚪ **Gris**: fin de semana, no se evalúa.

> **Click sobre cualquier técnico** del semáforo → lleva directamente a su perfil.

#### Panel de alertas

Lista los técnicos con problemas de cumplimiento:

- **Aviso (ámbar)**: 3 o más días laborables sin reportar.
- **Crítico (rojo)**: 5 o más días laborables sin reportar.

> **Click sobre cualquier alerta** → lleva al perfil del técnico para tomar acción.

#### Gráficos estadísticos

1. **Tendencia de reportes — últimos 30 días**: gráfica de línea con la evolución diaria.
2. **Top técnicos del mes**: gráfica de barras con los 10 técnicos con más reportes.
3. **Distribución por municipio**: gráfica circular con reportes agrupados por municipio del Estado Táchira.

### 3.2 Exportar resumen ejecutivo en PDF

En la parte superior del Dashboard:

1. Hacer click en **"Exportar resumen PDF"**.
2. El sistema genera un PDF profesional con:
   - Cintillo institucional CVAUP
   - Indicadores numéricos del momento
   - Estado del semáforo
   - Las 3 gráficas embebidas
   - Lista de alertas activas
   - Pie de página con fecha y hora exacta de generación
3. El PDF descarga automáticamente al equipo del usuario.

> **Uso recomendado**: enviar este PDF en correos institucionales como "estado del momento" cuando se solicite información ejecutiva. Es 1 hoja, escaneable en 10 segundos por la autoridad superior.

---

## 4. Gestión de Técnicos

### 4.1 Listado de técnicos

Menú lateral → **Técnicos**.

Aparece la lista con:
- Foto de perfil
- Nombre completo
- Cédula
- Especialidad
- Estado (Activo / Inactivo)
- Total de reportes registrados

#### Funciones disponibles

- **Buscar**: campo de texto que filtra por nombre o cédula.
- **Ver perfil**: click sobre el nombre → muestra detalle del técnico con su historial de reportes.
- **Editar**: botón "Editar" en el detalle.
- **Eliminar**: botón "Eliminar" (solo posible si no tiene reportes asociados).
- **Cambiar estado**: toggle Activo/Inactivo (los inactivos no aparecen en el semáforo del Dashboard).

### 4.2 Registrar un nuevo técnico

1. Click en **"Nuevo Técnico"** (botón verde arriba a la derecha) o en el sub-menú lateral.
2. Llenar el formulario:
   - **Nombre completo** *(obligatorio)*
   - **Cédula** *(obligatorio, formato `V-12.345.678`)*
   - **Teléfono** *(opcional, formato venezolano `0414-1234567`)*
   - **Especialidad** *(opcional, ej. "Agronomía urbana")*
   - **Estado** (Activo por defecto)
   - **Foto de perfil** *(opcional, JPG/PNG, se comprime automáticamente)*
3. Click en **"Guardar técnico"**.
4. El sistema valida y muestra banner verde de éxito.

### 4.3 Reglas de cédula

El sistema valida el formato de cédula venezolana:
- ✅ `V-12.345.678` (con prefijo V- y puntos separadores)
- ✅ `E-12.345.678` (extranjero)
- ✅ `J-12.345.678` (jurídico)
- ❌ `12345678` (sin prefijo)
- ❌ `V-12345678` (sin puntos)

**No puede haber dos técnicos activos con la misma cédula**. Si se elimina un técnico (soft-delete), su cédula queda liberada y puede registrarse otro técnico nuevo con esa cédula.

---

## 5. Registro de Reportes

Este es el módulo **más importante** del sistema. Cada reporte representa **una actividad técnica realizada en campo**.

### 5.1 Listado de reportes

Menú lateral → **Reportes**.

Muestra todos los reportes en orden cronológico (más recientes primero) con:
- Fecha
- Técnico responsable
- Lugar (consejo comunal donde se realizó la actividad)
- Personas atendidas
- Estado (Completo / Incompleto)
- Indicador de fotos adjuntas

#### Filtros disponibles

- Búsqueda por título de actividad, lugar o resumen
- Filtro por técnico específico
- Filtro por municipio
- Filtro por estado del reporte
- Rango de fechas (desde / hasta)

### 5.2 Registrar un nuevo reporte

> **Regla institucional**: 1 reporte = 1 consejo comunal atendido. Si el técnico atendió 3 consejos comunales en un día, debe registrar 3 reportes separados (cada uno con su propia foto y resumen). **Nota**: 1 técnico solo puede tener 1 reporte por fecha; si en un día atendió varios consejos comunales, ajustar las fechas correspondientes.

#### Pasos para crear un reporte

1. Click en **"Nuevo Reporte"** (botón verde) o sub-menú lateral.
2. Sección **Datos básicos**:
   - **Técnico responsable**: seleccionar de la lista (solo aparecen los activos)
   - **Fecha**: día en que se realizó la actividad (debe ser día laborable, lunes a viernes)
   - **Estado**: Completo / Incompleto / Borrador
3. Sección **Ubicación geográfica** (cascada):
   - Seleccionar **Municipio** → se cargan las parroquias automáticamente
   - Seleccionar **Parroquia** → se cargan las comunas
   - Seleccionar **Comuna** → se cargan los consejos comunales
   - Seleccionar **Consejo Comunal** específico
   - Llenar el campo **Lugar** (sector, dirección puntual, ej. "Sector Las Flores, calle principal")
4. Sección **Métricas de impacto**:
   - **Cantidad de personas atendidas** (número entero)
   - **Cantidad de personas a beneficiar** (proyección)
5. Sección **Descripción de la actividad**:
   - **Título de actividad** (ej. "Capacitación huerto familiar")
   - **Rubro científico** (ej. "Solanum lycopersicum (tomate)")
   - **Fecha de ejecución** (puede coincidir con fecha del reporte o ser distinta)
   - **Ponencia / Responsable** (opcional)
   - **Material de apoyo entregado** (opcional, máximo 5.000 caracteres)
   - **Organizado por** (opcional)
   - **Aval de** (institución que avala)
   - **Certificación** (opcional, máximo 5.000 caracteres)
6. Sección **Impacto**:
   - **Participantes acreditados** (número)
   - **Alcance del grupo** (número)
   - **Resultado** (descripción narrativa, máximo 5.000 caracteres)
7. Sección **Resumen temático**:
   - Texto narrativo del reporte (máximo 10.000 caracteres)
8. Sección **Constancia fotográfica**:
   - Adjuntar **hasta 3 fotos** (JPG / PNG)
   - Las fotos se comprimen automáticamente a WebP de 1.200 px máximo
9. Click en **"Guardar reporte"**.
10. Aparece banner verde y se redirige al detalle del reporte recién creado.

### 5.3 Ver detalle de un reporte

Click sobre cualquier reporte de la lista. Muestra:
- Toda la información estructurada
- Las fotos en grilla (click para verlas en lightbox con zoom)
- Botones de acción: Editar, Eliminar, **Descargar PDF**

### 5.4 Descargar PDF individual del reporte

Botón **"Descargar PDF"** en la vista detalle. Genera un documento profesional con:
- Cintillo institucional CVAUP arriba
- Datos del técnico y ubicación
- Métricas y descripción
- Las fotografías embebidas
- Pie de página con timestamp de generación

> **Este PDF es el documento oficial firmable** que se entrega a las autoridades superiores o se archiva en expedientes institucionales.

### 5.5 Editar un reporte

1. En el detalle, click en **"Editar"**.
2. Modificar los campos necesarios.
3. Para **agregar fotos nuevas**: usar el campo de upload (respeta el máximo de 3 totales).
4. Para **eliminar fotos existentes**: click en el botón ✕ sobre cada foto.
5. Click en **"Guardar cambios"**.

### 5.6 Eliminar un reporte

1. En el detalle, click en **"Eliminar"**.
2. Confirmar en el diálogo que aparece.
3. El reporte y sus fotos se eliminan permanentemente.

> **Cuidado**: la eliminación es definitiva. Si se elimina por error, solo se puede recuperar restaurando un respaldo.

---

## 6. Gestión de Ubicaciones

El sistema viene con la **geografía oficial del Estado Táchira pre-cargada**: 1 estado, 29 municipios, 59 parroquias, 147 comunas y 2.207 consejos comunales.

### 6.1 Vista del módulo

Menú lateral → **Ubicaciones**.

Tiene dos vistas:

#### Vista de árbol

Estructura jerárquica navegable. Click en cualquier nodo para expandirlo:

```
📍 Estado Táchira (29 municipios)
    └─ Municipio San Cristóbal (5 parroquias)
        └─ Parroquia Pedro María Morantes (12 comunas)
            └─ Comuna Cordillera Andina (8 consejos comunales)
                └─ CC Las Flores I
                └─ CC La Pradera
                └─ ...
```

#### Vista de tabla

Listado tabular con:
- Estado, Municipio, Parroquia, Comuna, Consejo Comunal
- Cantidad de reportes asociados a cada CC
- Filtros en cascada por nivel
- Búsqueda global

### 6.2 Crear una ubicación nueva

> Solo es necesario si el Estado Táchira incorpora un nuevo Consejo Comunal o se crea una nueva parroquia/comuna. La data oficial pre-cargada cubre el 100 % del estado actual.

1. En la vista árbol, expandir hasta el nodo padre (ej. una comuna específica).
2. Click en el botón **"+ Nuevo consejo comunal"** del nodo.
3. Aparece un modal con:
   - Indicador de bajo qué nodo se va a crear
   - Campo de nombre con ejemplo
4. Escribir el nombre y click en **"Crear"**.
5. Aparece banner verde y el nuevo nodo aparece bajo el padre.

### 6.3 Editar nombre

1. Click en el ícono de lápiz (✎) sobre cualquier nodo.
2. Modificar el nombre en el modal.
3. Click en **"Guardar cambios"**.

### 6.4 Eliminar una ubicación

1. Click en el ícono de papelera (🗑) sobre el nodo.
2. Confirmar en el diálogo.
3. **Restricción**: no se puede eliminar un nodo que tenga **reportes asociados** o **hijos**. Si tiene hijos, eliminarlos primero.

### 6.5 Importación masiva desde Excel

Útil cuando se incorporan grandes lotes de consejos comunales nuevos.

1. Menú lateral → **Ubicaciones → Importar Excel**.
2. Click en **"Descargar plantilla"** para obtener el formato Excel correcto.
3. Llenar la plantilla con las nuevas ubicaciones (columnas: Estado, Municipio, Parroquia, Comuna, Consejo Comunal).
4. Volver a la pantalla de importación.
5. Subir el archivo (debe ser `.xlsx`, máximo 5 MB).
6. Click en **"Vista previa"**: el sistema muestra cuántas filas se van a insertar, actualizar o saltar.
7. Si todo está correcto, click en **"Confirmar importación"**.
8. Aparece banner verde con estadísticas finales.

> **Restricción institucional**: el sistema solo acepta el Estado **Táchira**. Si en el Excel hay filas con otro estado, se rechazan con error claro y NO se importan.

### 6.6 Exportar listado a Excel

Vista tabla → click en **"Exportar Excel"**. Descarga un archivo con:
- Cintillo institucional en filas 1-3
- Headers en fila 4
- Todos los consejos comunales filtrados según los filtros activos

> **Por qué solo Excel y no PDF en este módulo**: los 2.207 consejos comunales son **data masiva para análisis**, no un documento oficial. Excel es la herramienta correcta. Para entrega oficial firmable, los reportes individuales sí tienen PDF.

---

## 7. Exportación de información

Cuando entes contralores o autoridades superiores soliciten información consolidada, este módulo es la herramienta.

### 7.1 Generar PDF masivo de reportes

1. Menú lateral → **Exportar → Generar PDF**.
2. Aplicar filtros:
   - Rango de fechas (ej. "del 1 al 31 de mayo")
   - Técnico específico (opcional)
   - Municipio (opcional)
   - Estado del reporte (Completo / Incompleto / Todos)
3. El sistema muestra un **preview en vivo** del número de reportes que coinciden.
4. Click en **"Generar PDF"**.
5. Descarga un PDF con:
   - Cintillo institucional
   - Filtros aplicados (visible en el documento)
   - Tabla con todos los reportes
   - Pie de página con timestamp

### 7.2 Generar Excel masivo

Mismos filtros que PDF. La diferencia:
- **PDF** → entrega oficial, formato fijo, firmable.
- **Excel** → análisis posterior, cruces de datos, importación a otros sistemas (Power BI, Sheets).

### 7.3 Recomendación de uso

| Situación | Formato |
|-----------|---------|
| "Necesitamos el reporte oficial del mes para el ministerio" | **PDF** |
| "Vamos a cruzar datos con otra dependencia" | **Excel** |
| "El gobernador pidió un informe firmable" | **PDF** |
| "Vamos a hacer una proyección estadística" | **Excel** |

---

## 8. Respaldos del sistema

### 8.1 Vista del módulo

Menú lateral → **Respaldo**.

Muestra:
- Lista de respaldos existentes (los últimos 10 que retiene el sistema)
- Tamaño y fecha de creación de cada uno
- Información del cronograma automático
- Botón para generar respaldo manual

### 8.2 Generar un respaldo manual

1. Click en **"Generar Backup"**.
2. Confirmar en el diálogo.
3. Esperar 5-15 segundos (mientras el sistema empaca BD + fotos + cifra).
4. Aparece banner verde y el nuevo `.zip` aparece en la lista.

### 8.3 Descargar un respaldo

Click en **"Descargar"** sobre cualquier respaldo de la lista. El `.zip` se descarga al equipo del usuario.

> **Importante**: el `.zip` está cifrado con AES-256. Para abrirlo se requiere la contraseña que TI institucional configuró en el archivo de configuración. Sin esa contraseña, el respaldo es **inutilizable** (esto es deliberado, garantiza confidencialidad).

### 8.4 Eliminar un respaldo

Click en el botón de papelera (🗑). Útil si se quiere liberar espacio antes de generar uno nuevo.

### 8.5 Política de retención

El sistema mantiene **automáticamente los últimos 10 respaldos**. Cuando se genera el #11, el más antiguo se elimina automáticamente (lógica FIFO simple).

### 8.6 Cronograma automático (en producción)

Una vez desplegado en el servidor institucional, el sistema genera respaldos automáticamente:

| Hora | Tarea |
|------|-------|
| **02:00 AM** | Limpieza de respaldos antiguos (mantiene últimos 10) |
| **02:30 AM** | Generación del respaldo del día |
| **03:00 AM (lunes)** | Verificación de salud (alerta por correo si pasaron días sin respaldo) |

---

## 9. Mi cuenta — Perfil del administrador

Click en el avatar verde (esquina superior derecha) → **Mi cuenta**.

### 9.1 Información de la cuenta

Permite cambiar:
- Nombre completo (cómo se muestra en el sistema)
- Correo electrónico (usado para login)

> Si se cambia el correo, **se requiere re-login**.

### 9.2 Cambio de contraseña

Sección dedicada con:
- Campo de contraseña actual
- Campo de nueva contraseña con **checklist en vivo** que muestra los requisitos cumplidos
- Confirmación de la nueva contraseña
- Botones de mostrar/ocultar (👁) en cada campo

> **El sistema rechaza contraseñas débiles** o que aparezcan en bases de datos públicas de filtración (haveibeenpwned).

---

## 10. Casos de uso típicos

### 10.1 Inicio del día — revisión rápida

1. Login al sistema.
2. Vista del Dashboard:
   - Revisar el semáforo: ¿quién no ha reportado hoy?
   - Revisar alertas: ¿hay técnicos en estado crítico?
   - Click sobre cualquier alerta para ver detalle del técnico.

### 10.2 Solicitud urgente de información del ministerio

> **"Solicitamos un consolidado de todas las actividades realizadas en el municipio Junín durante mayo"**

1. Menú → **Exportar → Generar PDF**.
2. Filtros:
   - Desde: 01/05/2026
   - Hasta: 31/05/2026
   - Municipio: Junín
3. Preview muestra 47 reportes.
4. Click "Generar PDF".
5. Adjuntar el PDF al correo de respuesta al ministerio.

**Tiempo total: < 2 minutos.** (Antes: 1 semana de búsqueda en archivos.)

### 10.3 Reunión con autoridad superior

> **"Llegó el secretario sin previo aviso y pide un informe del estado actual"**

1. Menú → **Dashboard**.
2. Click en **"Exportar resumen PDF"** (esquina superior derecha).
3. Imprimir el PDF descargado o mostrarlo en pantalla.

**Tiempo: 30 segundos.** El secretario tiene en sus manos:
- Indicadores numéricos del momento
- Estado de los técnicos
- Tendencia de reportes del último mes
- Distribución geográfica de la actividad
- Alertas pendientes

### 10.4 Llegada de nuevo técnico al equipo

1. Menú → **Técnicos → Nuevo Técnico**.
2. Llenar el formulario con sus datos y foto institucional.
3. Estado: Activo.
4. Guardar.
5. **Listo**: el técnico ya aparece en el semáforo del Dashboard y puede empezar a registrar reportes.

**Tiempo: 2 minutos.**

### 10.5 Auditoría externa

> **"La Contraloría pide acceso a todos los reportes del 2026"**

1. Menú → **Exportar → Excel**.
2. Filtros: Desde 01/01/2026 hasta hoy.
3. Click "Generar Excel".
4. Entregar el archivo a la Contraloría.

**Tiempo: 1 minuto.** El Excel incluye TODOS los campos de cada reporte para análisis exhaustivo.

### 10.6 Antes de salir de viaje / vacaciones

1. Menú → **Respaldo → Generar Backup**.
2. Esperar 15 segundos.
3. **Descargar** el respaldo recién creado al equipo personal o pen drive.
4. Listo: si algo pasa con el servidor mientras está fuera, hay un respaldo independiente.

---

## 11. Resolución de problemas comunes

### 11.1 "No puedo iniciar sesión"

| Síntoma | Causa probable | Solución |
|---------|----------------|----------|
| "Las credenciales no coinciden" | Correo o contraseña mal escritos | Verificar que no haya espacios extra; revisar mayúsculas/minúsculas |
| "Demasiados intentos. Vuelve a intentar en X segundos" | Sistema activó protección anti brute-force | Esperar 1 minuto y reintentar |
| Pantalla blanca o error 500 | Servidor caído o problema de red | Contactar a TI institucional |

### 11.2 "El sistema dice que la cédula ya existe"

- Si el técnico anterior con esa cédula fue eliminado, el sistema permite registrarlo nuevamente. Verificar primero en la lista de técnicos.
- Si el técnico está activo, no se puede crear otro con la misma cédula (regla institucional).

### 11.3 "No puedo subir más de 3 fotos al reporte"

Es la regla institucional: **máximo 3 fotos por reporte**. Si necesitan más, considerar separar en dos reportes (ambos del mismo técnico pero en fechas distintas).

### 11.4 "La fecha del reporte no se acepta"

- Solo se permiten **días laborables (lunes a viernes)**.
- No se permite **dos reportes del mismo técnico en la misma fecha** (1 técnico = 1 reporte por día).

### 11.5 "El backup no abre / pide contraseña"

Esto es **comportamiento esperado**: los respaldos están cifrados con AES-256. La contraseña la configuró TI institucional al desplegar el sistema. Solicitar a TI la contraseña del respaldo si se necesita restaurarlo.

### 11.6 "Olvidé mi contraseña"

El sistema **NO** tiene función de "recuperar contraseña por email" (es un sistema cerrado mono-usuario). En este caso:
1. Contactar a TI institucional.
2. TI restablece la contraseña directamente en el servidor con un comando de Laravel.
3. Recibirás la nueva contraseña por canal seguro.

### 11.7 "El sistema responde lento"

- **Si es la primera carga del día**: normal, el caché se está calentando. La segunda navegación es rápida.
- **Si persiste**: contactar a TI institucional, puede ser tema del servidor.

---

## 12. Glosario

| Término | Significado |
|---------|-------------|
| **Reporte técnico** | Documento que registra una actividad realizada por un técnico de campo en un consejo comunal específico, en una fecha determinada. |
| **Consejo Comunal** | Unidad organizativa básica del Estado Táchira a nivel comunidad. Es la entidad geográfica más granular del sistema. |
| **Cintillo institucional** | Imagen oficial de CVAUP que aparece como encabezado en todos los documentos PDF generados. |
| **Soft-delete** | Eliminación reversible: el registro queda marcado como eliminado pero no se borra físicamente. Se usa con técnicos para preservar el histórico. |
| **Lazy-load** | Carga bajo demanda: el árbol de ubicaciones no carga los 2.207 consejos comunales de golpe, sino solo los que el usuario expande. |
| **Cifrado AES-256** | Estándar de cifrado de grado militar usado para los respaldos. Sin la contraseña correcta, el archivo es indescifrable. |
| **Throttle / Rate limit** | Mecanismo de protección que limita cuántas operaciones pesadas se pueden hacer por minuto. Protege el servidor. |
| **CSP / Content Security Policy** | Cabecera HTTP de seguridad que evita ataques de scripts maliciosos. |
| **HSTS** | Cabecera HTTP que fuerza el uso de HTTPS, evitando ataques de degradación. |
| **Master data** | Información de referencia estable: en este sistema, las ubicaciones (estados, municipios, parroquias, comunas, consejos comunales). No se cambian con frecuencia. |

---

## 13. Soporte y contacto

Para problemas técnicos del sistema:
- Contactar a TI institucional de CVAUP Táchira.

Para mejoras o nuevas funcionalidades:
- Documentar la solicitud y enviarla al equipo de desarrollo a través de TI.

---

**Documento elaborado para el administrador operativo del sistema.**
**CVAUP Táchira — Mayo 2026 · Versión 1.0.0**
