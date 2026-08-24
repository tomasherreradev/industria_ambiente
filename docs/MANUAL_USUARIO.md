# Manual de Usuario — Sistema de Muestreo

Este documento describe los flujos principales del sistema, paso a paso, indicando en cada caso **qué rol puede realizar cada tarea**.

---

## Índice

1. [Roles del sistema](#1-roles-del-sistema)
2. [Visión general del ciclo operativo](#2-visión-general-del-ciclo-operativo)
3. [Crear un cliente](#3-crear-un-cliente)
4. [Crear una cotización](#4-crear-una-cotización)
5. [Coordinar un muestreo](#5-coordinar-un-muestreo)
6. [Completar un muestreo (campo)](#6-completar-un-muestreo-campo)
7. [Pasar una muestra a laboratorio](#7-pasar-una-muestra-a-laboratorio)
8. [Coordinar una orden de trabajo (laboratorio)](#8-coordinar-una-orden-de-trabajo-laboratorio)
9. [Completar un análisis](#9-completar-un-análisis)
10. [Pasar a informes](#10-pasar-a-informes)
11. [Firmar un informe](#11-firmar-un-informe)
12. [Facturar](#12-facturar)
13. [Consultoría, ASP y Clarke Fire](#13-consultoría-asp-y-clarke-fire)
14. [Mediciones (documentación de campo)](#14-mediciones-documentación-de-campo)
15. [Importar determinaciones (cotio_items)](#15-importar-determinaciones-cotio_items)
16. [Leyes y normativas y su vínculo con determinaciones](#16-leyes-y-normativas-y-su-vínculo-con-determinaciones)

---

## 1. Roles del sistema

| Rol | Nombre en pantalla | Área principal |
|-----|-------------------|----------------|
| **Administrador** | Admin (`usu_nivel ≥ 900`) | Acceso total a todos los módulos |
| `ventas` | Vendedor | Cotizaciones, clientes |
| `facturador` | Facturador | Facturación (clientes solo lectura) |
| `coordinador_muestreo` | Coordinador Muestreo | Planificación y cierre de muestreos |
| `muestreador` | Muestreador | Trabajo de campo (Mis muestras) |
| `cadena_custodia` | Cadena de custodia | Módulo de muestras |
| `coordinador_lab` | Coordinador Laboratorio | Órdenes de trabajo y cierre de análisis |
| `laboratorio` | Analista | Carga de resultados (Mis análisis) |
| `firmador` | Firmador | Informes y firma digital |
| `coordinador_consul` | Coordinador Consultoría | Portal Consultoría |
| `asp` | ASP | Portal ASP |
| `clarke_fire` | Clarke Fire | Portal Clarke Fire |
| `coordinador_mediciones` | Coordinador Mediciones | Portal Documentación / Mediciones |
| `cliente` | Usuario Cliente | Portal de clientes |

> **Nota:** Un usuario puede tener un rol principal y roles adicionales. Los administradores pueden realizar cualquier tarea sin restricción de rol.

---

## 2. Visión general del ciclo operativo

El flujo estándar de un ensayo de laboratorio (con muestreo en campo) es:

```
Cotización → Aprobación → Coordinación de muestreo → Campo (muestreador)
→ Pasar a laboratorio → Coordinación de OT → Análisis → Informes → Firma → Facturación
```

Existen **caminos alternativos** según el tipo de ensayo:

| Tipo de ensayo | Flujo resumido |
|----------------|----------------|
| **Laboratorio estándar** (con muestreo) | Muestreo → Lab → Informes → Facturación |
| **Sin muestreo** (`lleva_muestreo = false`) | Directo a Órdenes de Trabajo → Informes → Facturación |
| **Consultoría / ASP / Clarke Fire** | Portal del canal → Pasar a informes (sin lab) → Facturación |
| **Mediciones** | Muestreo → Documentación (PDF) → Informes → Facturación |

---

## 3. Crear un cliente

**Módulo:** Clientes (`/clientes`)

### Pasos

1. Ir a **Clientes** → **Nuevo cliente**.
2. Completar los datos obligatorios: **Razón social** y estado **Activo**.
3. Opcionalmente completar: fantasía, dirección, CUIT, condición IVA, condición de pago, listas de precios, descuentos por sector.
4. Marcar **Es consultor** si el cliente gestiona cotizaciones para otras empresas (habilita empresas relacionadas al cotizar).
5. Agregar **contactos** (nombre, teléfono, email). El primer contacto se replica en los datos principales del cliente.
6. Si es consultor, agregar **empresas relacionadas**.
7. Configurar **razones sociales de facturación** si corresponde.
8. Guardar. El sistema asigna un código de cliente automáticamente si no se ingresa uno manualmente.

### Importación masiva

Desde el listado de clientes se puede importar un archivo Excel con datos de clientes y contactos.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Ver listado y detalle | Admin, `ventas`, `facturador` |
| Crear / editar / eliminar / importar | Admin, `ventas` |
| Solo lectura (sin crear ni editar) | `facturador` |

---

## 4. Crear una cotización

**Módulo:** Ventas / Cotizaciones (`/ventas`)

### Pasos

1. Ir a **Cotizaciones** → **Nueva cotización**.
2. **Seleccionar cliente** mediante el buscador. El cliente debe estar activo.
   - Si el cliente es consultor, elegir la **empresa destino** (`coti_para`) en la pestaña de empresas relacionadas.
3. Completar datos comerciales: fecha, condición de pago, lista de precios, divisas, descuentos, referencias de facturación.
4. **Agregar ensayos** desde el catálogo de determinaciones:
   - Cada ensayo principal (agrupador/muestra) puede incluir componentes o analitos.
   - El sistema clasifica automáticamente el **canal especial** del ensayo (consultoría, ASP, Clarke Fire, mediciones) según matriz y descripción.
5. Asignar **ley de aplicación** por ensayo si corresponde (ver sección 16).
6. Definir el **estado inicial**:
   - **En espera** (predeterminado): no entra en operaciones hasta ser aprobada.
   - **Aprobado**: queda disponible de inmediato para muestreo, laboratorio o portales de canal.
7. Guardar. El sistema genera el número de cotización (`coti_num`) y la versión 1.

### Estados de cotización

| Estado | Significado |
|--------|-------------|
| En espera (`E`) | Borrador / pendiente de aprobación comercial |
| Aprobado (`A`) | Habilitada para operaciones |
| En proceso (`P`) | En gestión comercial |
| Rechazado (`R`) | No operativa |
| Suspendida (`S`) | Pausada |

### Edición posterior

- Desde **Cotizaciones** (`/ventas/{id}/edit`) se pueden modificar ensayos, precios y datos comerciales.
- Solo el **administrador** puede bajar precios por debajo del tarifario.
- Existe bloqueo de edición concurrente cuando otro usuario está editando la misma cotización.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Crear / editar / eliminar cotizaciones | Admin, `ventas` |
| Ver listado filtrado por canal | Admin, `coordinador_consul`, `asp`, `clarke_fire`, `coordinador_mediciones` (solo lectura) |
| Ver detalle operativo de cotización | Admin, `coordinador_muestreo`, `coordinador_lab` |
| Aprobar cotización | Admin, `ventas` (al crear o editar con estado Aprobado) |

---

## 5. Coordinar un muestreo

**Módulo:** Muestras (`/muestras`)

**Requisito previo:** Cotización en estado **Aprobado** con ensayos que llevan muestreo (`lleva_muestreo = true`).

### Pasos

1. Ir a **Muestras** y localizar la cotización.
2. Entrar al **detalle de la cotización** para ver las categorías (matrices) e instancias de muestra.
3. **Activar instancias para muestreo** (opcional desde detalle de cotización):
   - Acción "Pasar a muestreo" crea o activa instancias con fecha y coordinador asignado.
4. **Asignación masiva** (flujo principal):
   - Seleccionar las muestras a coordinar.
   - Definir: fechas de inicio y fin de muestreo, responsables (muestreadores), vehículo, herramientas de muestreo, variables requeridas de campo.
   - Los análisis asociados (componentes del ensayo) heredan fechas y coordinación.
5. **Asignación puntual** (desde la vista por categoría):
   - Modal para ajustar fechas, vehículo, responsable u observaciones de una muestra individual.

### Estado resultante

Las muestras quedan en estado **`coordinado muestreo`**. Aparecen en:
- El listado de **Muestras** (coordinador).
- **Mis muestras** del muestreador asignado (`/mis-tareas`).

### Acciones adicionales del coordinador

- **Suspender** una muestra → estado `suspension`.
- **Recoordinar** una muestra suspendida → vuelve a `coordinado muestreo` (no permitido si ya pasó a laboratorio).
- **Marcar como prioritaria** para destacarla en el listado.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Ver módulo Muestras | Admin, `coordinador_muestreo`, `cadena_custodia` |
| Coordinar / asignar / suspender / recoordinar | Admin, `coordinador_muestreo` |
| Pasar a muestreo desde detalle de cotización | Admin, `coordinador_muestreo` |

---

## 6. Completar un muestreo (campo)

**Módulo:** Mis muestras (`/mis-tareas`) — rol muestreador

### Pasos del muestreador

1. Ingresar a **Mis muestras** y abrir la tarea asignada (estado `coordinado muestreo`).
2. **Completar identificación de la muestra:**
   - GPS / ubicación, precinto, datos de cadena de custodia, identificación OT.
   - Al guardar, la muestra pasa a **`en revision muestreo`**.
3. **Registrar mediciones de campo** (variables requeridas) y observaciones.
4. Guardar identificación y mediciones.

> Una vez que la muestra está en estado **`muestreado`**, la identificación y las mediciones quedan en solo lectura para el muestreador.

### Cierre formal del muestreo (coordinador)

Desde **Muestras → categoría → Finalizar todas**:
- Todas las instancias activas de la categoría pasan a estado **`muestreado`**.

También puede forzarse el estado manualmente desde el modal de cambio de estado.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Completar identificación y mediciones de campo | Admin, `muestreador` (asignado como responsable) |
| Finalizar muestreo (cerrar categoría) | Admin, `coordinador_muestreo` |
| Cambiar estado manualmente | Admin, `coordinador_muestreo` |

---

## 7. Pasar una muestra a laboratorio

**Importante:** Este paso **habilita** la muestra en el módulo de Órdenes de Trabajo. No es lo mismo que coordinar la OT (paso 8).

**Requisito:** Muestra en estado **`muestreado`**.

### Pasos

1. Ir a **Muestras → detalle de cotización → categoría**.
2. Seleccionar las muestras en estado `muestreado`.
3. Pulsar **"Pasar a Laboratorio"**.
4. El sistema:
   - Marca `enable_ot = true` (visible en Órdenes de Trabajo).
   - Marca `complete_muestreo = true`.
   - Genera número de OT (`otn`) si no existía.
   - Deja el análisis en estado pendiente de coordinar (`cotio_estado_analisis = null`).

### Caminos alternativos

| Situación | Acción |
|-----------|--------|
| Ensayo **sin muestreo** (`lleva_muestreo = false`) | Aparece directamente en Órdenes de Trabajo al aprobar la cotización |
| **Pasar directo a OT** (sin pasar por campo) | Botón "Pasar directo a OT" en detalle de muestra — crea instancias con `enable_ot = true` |
| Ensayo de **mediciones** | No usa "Pasar a Laboratorio"; usa "Pasar a documentación" (ver sección 14) |
| Ensayo de **consultoría / ASP / Clarke Fire** | No pasa por laboratorio; va directo al portal del canal |

### Reversión

Se puede **quitar de OT** mientras `active_ot = false` (aún no coordinada). Si la OT ya está activa, no se puede revertir desde este paso.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Pasar a laboratorio / pasar directo a OT | Admin, `coordinador_muestreo` |
| Quitar de OT (si no está activa) | Admin, `coordinador_muestreo` |

---

## 8. Coordinar una orden de trabajo (laboratorio)

**Módulo:** Órdenes de Trabajo (`/ordenes`)

**Requisito:** Muestra con `enable_ot = true` (ya pasó a laboratorio o ensayo sin muestreo).

### Pasos

1. Ir a **Órdenes de Trabajo** y localizar la cotización.
   - Filtro útil: **"Pendiente por coordinar"** (`enable_ot = true` y sin estado de análisis).
2. Entrar al **detalle** y seleccionar la categoría.
3. **Asignación masiva de OT:**
   - Seleccionar muestras/análisis.
   - Definir: coordinador de laboratorio, fechas de inicio/fin de OT, responsables de análisis (analistas por sector), herramientas de laboratorio.
   - El sistema marca `active_ot = true` y estado **`coordinado analisis`**.
4. **Asignación puntual:** modal para ajustar fechas, responsable o herramientas de una OT individual.

### Estado resultante

- **`coordinado analisis`**: OT activa, visible en **Mis análisis** del analista asignado (`/mis-ordenes`).
- Los ensayos de canales especiales (consultoría, ASP, Clarke Fire, mediciones) **no aparecen** en este módulo.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Ver Órdenes de Trabajo | Admin, `coordinador_lab` |
| Coordinar OT / asignación masiva o puntual | Admin, `coordinador_lab` |
| Cambiar estado de análisis | Admin, `coordinador_lab` |
| Solicitar revisión a analistas | Admin, `coordinador_lab` |

---

## 9. Completar un análisis

### Parte A — Carga de resultados (analista)

**Módulo:** Mis análisis (`/mis-ordenes`) — rol `laboratorio`

1. Ingresar a **Mis análisis** y abrir la OT asignada (estado `coordinado analisis`).
2. Para cada determinación/análito, **cargar el resultado** obtenido en laboratorio.
3. Al guardar un resultado, el análisis pasa a **`en revision analisis`** (también se actualiza la muestra padre).

### Parte B — Cierre de análisis (coordinador de laboratorio)

Desde **Órdenes de Trabajo → categoría**:

| Acción | Efecto |
|--------|--------|
| **Finalizar análisis seleccionados** | Solo los análisis marcados → `analizado` |
| **Finalizar todas** | Muestra + todos los análisis pendientes → `analizado` |
| **Cambio manual de estado** | Modal para forzar cualquier estado de análisis |

### Estado resultante

Análisis en **`analizado`**, listo para pasar a informes.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Cargar resultados | Admin, `laboratorio` (asignado como responsable de análisis) |
| Finalizar análisis / cambiar estado | Admin, `coordinador_lab` |
| Ver informe preliminar | Admin, `coordinador_lab` |

---

## 10. Pasar a informes

**Módulo:** Informes (`/informes`)

Para que una muestra aparezca en Informes necesita:
- `enable_inform = true`
- `aprobado_informe = true`

Existen **cuatro caminos** según el tipo de ensayo:

### Camino A — Laboratorio estándar (más habitual)

**Quién lo hace:** Coordinador de laboratorio

1. Con análisis en estado **`analizado`**, pulsar **"Pasar a Informe"** en la categoría de Órdenes de Trabajo.
   - Setea `enable_inform = true`.
2. Revisar el **informe preliminar** (HTML con resultados, límites normativos, mapa).
3. Pulsar **"Aprobar informe"**.
   - Setea `aprobado_informe = true`.
4. La muestra aparece en el listado de **Informes**.

### Camino B — Consultoría / ASP / Clarke Fire (facturación directa)

**Quién lo hace:** Coordinador del canal correspondiente

1. Desde el portal del canal o el detalle de muestra (`/show/{cotización}?canal=...`).
2. Seleccionar ensayos del canal y pulsar **"Pasar a informes"**.
3. Opcionalmente adjuntar PDF del informe externo.
4. El sistema marca directamente: `completado`, `enable_inform = true`, `aprobado_informe = true`.

### Camino C — Mediciones (documentación)

**Quién lo hace:** Coordinador de mediciones o coordinador de muestreo

1. Subir el PDF del informe de mediciones.
2. **Aprobar** el informe desde el portal Documentación.
3. El sistema habilita informes en la muestra y todos sus análisis.

### Camino D — Desde muestreo (API interna)

Existe una acción directa "Pasar a informes" desde muestreo (con PDF opcional) que marca completado y habilita informes. No aplica a ensayos de mediciones.

### Gestión en el módulo Informes

Una vez en Informes se puede:
- Ver detalle, generar PDF del protocolo.
- Editar resultados, observaciones y variables de muestreo.
- Editar cabecera del protocolo PDF (roles autorizados en sección de roles).
- Clasificar como informe **final**, **parcial** o **sin análisis** según completitud de resultados.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Pasar a informe / aprobar (lab estándar) | Admin, `coordinador_lab` |
| Pasar a informes (consultoría / ASP / Clarke Fire) | Admin, `coordinador_consul`, `asp`, `clarke_fire` |
| Subir y aprobar informe (mediciones) | Admin, `coordinador_mediciones`, `coordinador_muestreo` |
| Ver listado de informes | Admin, `coordinador_lab`, `coordinador_muestreo`, `firmador` |
| Editar datos del informe | Admin, `coordinador_lab`, `firmador` |
| Editar cabecera del protocolo PDF | Admin, `firmador`, `coordinador_lab`, `coordinador_muestreo`, `ventas`, `facturador`, `cadena_custodia`, coordinadores de canal |

---

## 11. Firmar un informe

**Módulo:** Informes (`/informes`)

**Requisito:** Muestra con `enable_inform = true` y `aprobado_informe = true`, aún sin firmar.

### Pasos

1. Desde el listado de **Informes**, abrir la muestra y pulsar **Firmar**.
2. El sistema prepara el PDF:
   - Si existe un PDF externo cargado (`archivo_informe`), lo usa.
   - Si no, genera el protocolo automáticamente.
3. Envía el documento al **servicio de firma digital** (Alpha2000 / Digilogix).
4. El firmador es redirigido al portal del proveedor para autorizar la firma.
5. Al completarse exitosamente:
   - La instancia queda marcada como **`firmado = true`** con fecha de firma.
   - El informe firmado puede descargarse desde Informes.

### Callbacks de error

Si la firma falla o es rechazada, el sistema muestra una pantalla de error y limpia la sesión pendiente. El informe queda disponible para reintentar la firma.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Iniciar firma digital | Admin, `firmador` |
| Descargar informe firmado | Admin, `firmador`, `coordinador_lab`, `coordinador_muestreo` |

---

## 12. Facturar

**Módulo:** Facturación (`/facturacion`)

**Requisito:** Muestras con `enable_inform = true`. En la pantalla de emisión se exige además `aprobado_informe = true`.

### Pasos

1. Ir a **Facturación** → bandeja de pendientes.
   - Muestra cotizaciones con muestras listas para facturar (`facturado = false`).
2. Seleccionar una cotización → **Facturar**.
3. En la pantalla de facturación:
   - Revisar resumen económico (precios netos/brutos, descuentos globales y sectoriales).
   - Seleccionar **muestras completas** y/o **análisis individuales** a incluir.
   - Configurar **cuotas** si la cotización tiene plan de cuotas.
   - Verificar referencias de facturación obligatorias.
   - Indicar emails de envío (contactos del cliente).
4. Confirmar emisión.
5. El sistema:
   - Genera la factura electrónica vía **AFIP/ARCA** (CAE, número de comprobante).
   - Marca las instancias seleccionadas como **`facturado = true`**.
   - Envía email con la factura a los contactos indicados.

### Consultas posteriores

- **Listado de facturas** emitidas.
- **Detalle** de cada factura.
- **Descarga** del comprobante.
- **Exportación IVA** para contabilidad.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Ver bandeja y emitir facturas | Admin, `facturador` |
| Ver listado / detalle / descargar | Admin, `facturador` |
| Exportar IVA | Admin, `facturador` |

---

## 13. Consultoría, ASP y Clarke Fire

Estos tres canales comparten un flujo especial: **no pasan por muestreo ni laboratorio**. Van directo de cotización aprobada → portal del canal → informes → facturación.

### Clasificación automática

Al crear la cotización, el sistema detecta el canal según la matriz y descripción del ensayo:

| Canal | Ejemplos de ensayos |
|-------|---------------------|
| **Consultoría** | Potabilidad, agua potable/recreacional, análisis fisicoquímico |
| **ASP** | Control bacteriológico de aire |
| **Clarke Fire** | Ensayo de agua de red de incendio |

Los ensayos de estos canales se guardan con `lleva_muestreo = false` y un código de canal especial.

### Portales

Cada canal tiene su portal de listado:

| Portal | URL | Rol requerido |
|--------|-----|---------------|
| Consultoría | `/consultoria` | `coordinador_consul` |
| ASP | `/asp` | `asp` |
| Clarke Fire | `/clarke-fire` | `clarke_fire` |

El administrador ve los tres portales en el menú de navegación.

### Flujo operativo

1. **Vendedor** crea la cotización con ensayos del canal y la **aprueba**.
2. El ensayo aparece en el **portal del canal** correspondiente (solo cotizaciones aprobadas).
3. El **coordinador del canal** abre el detalle de la cotización.
4. Selecciona los ensayos y pulsa **"Pasar a informes"**.
   - Opcionalmente adjunta un PDF del informe externo.
5. Las instancias quedan completadas y habilitadas para Informes.
6. Continúa el flujo normal: **Firma** → **Facturación**.

### Consultoría con ensayos mixtos

Una cotización puede tener ensayos de consultoría (facturación directa) **y** ensayos de laboratorio estándar (con muestreo) en paralelo. Cada ensayo sigue su propio camino según su canal.

### Restricciones de roles de canal

Los coordinadores de canal (`coordinador_consul`, `asp`, `clarke_fire`):
- **No pueden** crear ni editar cotizaciones en Ventas.
- **Pueden** ver el listado de cotizaciones filtrado por su canal (solo lectura).
- **Pueden** gestionar el paso a informes de sus ensayos.

### Roles — resumen

| Acción | Consultoría | ASP | Clarke Fire |
|--------|-------------|-----|-------------|
| Ver portal | `coordinador_consul` | `asp` | `clarke_fire` |
| Pasar a informes | ✓ | ✓ | ✓ |
| Crear cotización | ✗ (solo `ventas`) | ✗ | ✗ |
| Admin | Acceso a los tres portales | Acceso a los tres portales | Acceso a los tres portales |

---

## 14. Mediciones (documentación de campo)

Canal especial para ensayos de **mediciones** (`cotio_canal_especial = 'mediciones'`). Combina muestreo en campo con documentación PDF, **sin pasar por el laboratorio de análisis**.

### Flujo

1. **Vendedor** crea cotización con ensayo de mediciones y la aprueba.
2. **Coordinador de muestreo** coordina el muestreo (igual que flujo estándar, secciones 5 y 6).
3. Al completar el muestreo (`muestreado`), pulsar **"Pasar a documentación"** (no "Pasar a Laboratorio").
   - Marca `enable_modulo_mediciones = true`.
4. El ensayo aparece en el portal **Documentación / Mediciones** (`/mediciones`).
5. **Coordinador de mediciones** (o coordinador de muestreo):
   - Sube el **PDF del informe** de mediciones.
   - **Aprueba** el informe.
6. Al aprobar: `aprobado_informe = true`, `enable_inform = true` → pasa a **Informes**.
7. Continúa: Firma → Facturación.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Ver portal Mediciones | Admin, `coordinador_mediciones` |
| Coordinar muestreo | Admin, `coordinador_muestreo` |
| Pasar a documentación | Admin, `coordinador_muestreo` |
| Subir PDF y aprobar informe | Admin, `coordinador_mediciones`, `coordinador_muestreo` |

---

## 15. Importar determinaciones (cotio_items)

**Módulo:** Determinaciones (`/items`) — solo administradores

El catálogo de determinaciones define qué ensayos y analitos están disponibles al crear cotizaciones.

### Conceptos

| Concepto | Descripción |
|----------|-------------|
| **Agrupador** | Ensayo o muestra principal (`es_muestra = true`). Ej: "Agua superficial — paquete completo" |
| **Componente / Parámetro** | Determinación o analito individual (`es_muestra = false`). Ej: "pH", "Coliformes totales" |
| **Matriz** | Tipo de muestra (agua, suelo, aire, etc.) |
| **Método** | Método de muestreo y/o análisis asociado |

Los agrupadores se vinculan a componentes, y ambos se vinculan a matrices.

### Pasos para importar

1. Ir a **Determinaciones** → **Importar**.
2. Descargar la **plantilla Excel** (`/items/importar/plantilla`).
3. Completar el Excel con las columnas del formato nuevo:

   | Columna | Descripción |
   |---------|-------------|
   | Tipo | Matriz (ej: "Agua superficial") |
   | Agrupador | Nombre del ensayo/muestra |
   | Parámetro | Nombre de la determinación |
   | Método muestreo | Método de toma de muestra |
   | Método análisis | Método de laboratorio |
   | Precio | Precio unitario |
   | Unidad | Unidad de medida del resultado |
   | Límite detección / cuantificación | Opcional |

4. Subir el archivo → **Procesar importación**.
5. El sistema:
   - Crea o actualiza matrices, métodos, agrupadores y componentes.
   - Vincula agrupadores con componentes.
   - Asocia ítems a matrices.
   - Muestra resumen de éxitos y errores por fila.

### Exportación

Desde el mismo módulo se puede **exportar** el catálogo actual a Excel (formato re-importable).

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| Ver catálogo de determinaciones | Admin, `ventas`, `coordinador_lab` |
| Importar / exportar / crear / editar | Solo **Admin** |

---

## 16. Leyes y normativas y su vínculo con determinaciones

### Qué son

Las **leyes y normativas** definen los límites permitidos para cada determinación (variable/analito). Se usan en cotizaciones (campo "ley de aplicación") y en los informes PDF (columna de límite normativo).

### Estructura de datos

```
Ley/Normativa
  └── Variables (analitos)
        ├── Vinculada a una determinación del catálogo (cotio_items)
        ├── Valor límite
        └── Unidad de medida
```

Al cotizar, cada ensayo puede tener asignada una **ley de aplicación** (`ley_aplicacion`). En el informe, el sistema busca el límite correspondiente a cada análisis según esa ley.

### Alta manual

**Módulo:** Leyes y Normativas (`/leyes-normativas`)

1. Ir a **Leyes y Normativas** → **Nueva**.
2. Ingresar nombre y código de la norma.
3. Agregar **variables**: seleccionar determinación del catálogo, valor límite y unidad.
4. Guardar.

**Roles:** Admin (CRUD completo). `ventas` y `coordinador_lab` pueden ver el listado.

### Importación masiva

1. Descargar **plantilla Excel** desde Leyes y Normativas.
2. Completar columnas:

   | Columna | Descripción |
   |---------|-------------|
   | Analito | Nombre de la determinación (debe existir en catálogo) |
   | Nombre ley | Nombre de la normativa |
   | Valor límite | Límite numérico o textual |
   | Unidad | Unidad de medida |
   | Matriz / Método | Opcional, para filtrar determinaciones homónimas |

3. Subir y procesar. El sistema agrupa filas por ley, crea variables vinculadas a determinaciones y asigna límites.

### Conexión con cotizaciones

Al crear o editar una cotización, cada ensayo puede seleccionar una **ley de aplicación**. Este valor se guarda en la línea de cotización (`cotio.ley_aplicacion`).

### Conexión con informes

Al generar el PDF del protocolo:
1. Se carga la ley asignada a la muestra.
2. Para cada análisis, el sistema busca la variable correspondiente (por ID de catálogo o descripción).
3. Muestra el **valor límite + unidad** en la columna normativa del informe.

### Cadena de dependencias

```
1. Importar determinaciones (catálogo cotio_items)
       ↓
2. Crear/importar leyes con variables vinculadas a esas determinaciones
       ↓
3. Al cotizar, asignar ley de aplicación por ensayo
       ↓
4. En informes PDF, mostrar límites normativos automáticamente
```

> **Importante:** Para que un límite aparezca en el informe, la determinación debe existir primero en el catálogo y luego vincularse a una variable dentro de la ley correspondiente.

### Roles

| Acción | Roles autorizados |
|--------|-------------------|
| CRUD leyes y normativas | Solo **Admin** |
| Importar leyes desde Excel | Solo **Admin** |
| Ver listado | Admin, `ventas`, `coordinador_lab` |
| Asignar ley al cotizar | Admin, `ventas` |

---

## Apéndice A — Tabla resumen de estados

### Estados de muestreo (`cotio_estado`)

| Estado | Significado |
|--------|-------------|
| *(vacío / sin instancia)* | Cotización aprobada, aún no coordinada |
| `coordinado muestreo` | Asignada a muestreador con fechas |
| `en revision muestreo` | Muestreador completó identificación/datos |
| `muestreado` | Muestreo cerrado por coordinador |
| `completado` | Flujo directo a informes (canales especiales) |
| `suspension` | Muestreo suspendido |

### Estados de análisis (`cotio_estado_analisis`)

| Estado | Significado |
|--------|-------------|
| *(null)* | Pendiente de coordinar OT |
| `coordinado analisis` | OT activa, analista asignado |
| `en revision analisis` | Analista cargó resultados |
| `analizado` | Análisis cerrado por coordinador |
| `suspension` | Análisis suspendido |

### Banderas operativas clave

| Bandera | Significado |
|---------|-------------|
| `enable_ot` | Muestra visible en Órdenes de Trabajo |
| `active_ot` | OT coordinada y activa para analistas |
| `complete_muestreo` | Muestreo cerrado |
| `enable_inform` | Habilitada para módulo Informes |
| `aprobado_informe` | Informe aprobado, visible en listado |
| `enable_modulo_mediciones` | En portal Documentación/Mediciones |
| `firmado` | Informe firmado digitalmente |
| `facturado` | Incluido en factura emitida |

---

## Apéndice B — Diagrama del flujo completo

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        FLUJO ESTÁNDAR (CON MUESTREO)                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  [Vendedor] Crear cotización → Aprobar                                  │
│       ↓                                                                 │
│  [Coord. Muestreo] Coordinar muestreo (asignar fechas, muestreador)   │
│       ↓                                                                 │
│  [Muestreador] Identificación + mediciones de campo                     │
│       ↓                                                                 │
│  [Coord. Muestreo] Finalizar muestreo → Pasar a Laboratorio            │
│       ↓                                                                 │
│  [Coord. Lab] Coordinar OT (asignar analistas, fechas)                  │
│       ↓                                                                 │
│  [Analista] Cargar resultados                                           │
│       ↓                                                                 │
│  [Coord. Lab] Finalizar análisis → Pasar a Informe → Aprobar          │
│       ↓                                                                 │
│  [Firmador] Firmar informe                                              │
│       ↓                                                                 │
│  [Facturador] Emitir factura                                            │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│              FLUJO CONSULTORÍA / ASP / CLARKE FIRE                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  [Vendedor] Crear cotización (canal especial) → Aprobar                 │
│       ↓                                                                 │
│  [Coord. Canal] Portal → Pasar a informes (+ PDF opcional)             │
│       ↓                                                                 │
│  [Firmador] Firmar → [Facturador] Facturar                              │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                        FLUJO MEDICIONES                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  [Vendedor] Crear cotización → Aprobar                                  │
│       ↓                                                                 │
│  [Coord. Muestreo] Coordinar + completar muestreo                       │
│       ↓                                                                 │
│  [Coord. Muestreo] Pasar a documentación                                │
│       ↓                                                                 │
│  [Coord. Mediciones] Subir PDF → Aprobar informe                        │
│       ↓                                                                 │
│  [Firmador] Firmar → [Facturador] Facturar                              │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

*Documento generado a partir del análisis del código fuente del sistema. Última actualización: julio 2026.*
