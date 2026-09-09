# 04 - PREVENCIÓN Y REMEDIACIÓN DE INYECCIÓN SQL (SQLi)

**Fecha:** 2026-09-09  
**Responsable:** Ingeniero de Seguridad Senior  
**Estado:** ✅ COMPLETADO Y VERIFICADO  
**Alcance:** Todos los endpoints del backend en `website_files/api/`  

---

## 1. Resumen Ejecutivo

Durante la auditoría de seguridad del sistema backend PHP/MySQLi, se identificaron múltiples endpoints vulnerables a Inyección SQL (SQLi) debido a la concatenación directa de entradas de usuario (`$_GET`, `$_POST`, cuerpos JSON de peticiones) en consultas SQL, apoyadas únicamente en `real_escape_string()`.

Se ejecutó un plan de remediación quirúrgico archivo por archivo, sustituyendo la concatenación y el escape débil por **sentencias preparadas nativas de MySQLi (`prepare()` + `bind_param()`)**, asegurando el tipado estricto de parámetros y preservando al 100% la lógica de negocio y las respuestas del sistema.

---

## 2. Registro Detallado de Remediación por Archivo

A continuación se detalla cada archivo intervenido, las consultas modificadas y los tipos de datos utilizados en `bind_param()`:

### 1. `website_files/api/consulta.php`
- **Consulta:** Búsqueda pública por ticket y DNI de cliente (`SELECT ... WHERE s.numero_atencion = ? AND (p.dni = ? OR ? = '')`).
- **Tipos `bind_param`:** `"ss"` (`$ticket`, `$dni`, `$dni`).
- **Resultado:** Búsqueda pública completamente inmunizada contra inyecciones sin requerir sesión activa.

### 2. `website_files/api/soporte.php`
- **`list`:** Filtros dinámicos combinados (`estado`, `tecnico_id`, `desde`, `hasta`, `search`).
  - **Tipos `bind_param`:** Tipado dinámico (`"s"`, `"i"`) según los filtros presentes.
- **`crear_tarea` / `crear_interno`:** Inserción de órdenes de soporte técnico.
  - **Tipos `bind_param`:** `"sisssss"` / `"iisssss"`.
- **`actualizar`:** Actualización de estados y notas de tareas.
  - **Tipos `bind_param`:** `"ssi"` (`$estado`, `$notas`, `$id`).
- **`buscar_equipo`:** Búsqueda por código o número de serie.
  - **Tipos `bind_param`:** `"ss"` (`$q`, `$q`).
- **`buscar_barcode`:** Búsqueda por código de barras exacto.
  - **Tipos `bind_param`:** `"s"` (`$code`).

### 3. `website_files/api/equipos.php`
- **`list`:** Filtro dinámico con búsqueda textual `LIKE ?`.
  - **Tipos `bind_param`:** `"sssss"` vinculando `$search_like` para cada campo filtrado.
- **`buscar`:** Búsqueda rápida por serie o código.
  - **Tipos `bind_param`:** `"ss"`.
- **`inventario_rapido`:** Búsqueda para inventariado por código/serie.
  - **Tipos `bind_param`:** `"ss"`.
- **`list_docs`:** Filtro por documento de compra/venta.
  - **Tipos `bind_param`:** `"ss"`.
- **`reporte_avanzado`:** Filtros multidimensionales de reportes.
  - **Tipos `bind_param`:** Tipos combinados dinámicos con placeholders `?`.

### 4. `website_files/api/sesiones.php`
- **`inventario_rapido` (verificación de equipo):**
  - **Tipos `bind_param`:** `"s"` (`$codigo`).
- **`inventario_rapido` (actualización de item):**
  - **Tipos `bind_param`:** `"sissi"` (`$estado`, `$user_id`, `$notas`, `$triaje`, `$item_id`).
- **`editar_sesion_nombre`:**
  - **Tipos `bind_param`:** `"si"` (`$nombre`, `$sesion_id`).

### 5. `website_files/api/personas.php`
- **`buscar_dni`:** Búsqueda de personas por número de documento exacto.
  - **Tipos `bind_param`:** `"s"` (`$dni`).
- **`buscar`:** Búsqueda por nombre, apellido, DNI o teléfono.
  - **Tipos `bind_param`:** `"ssss"` (`$q`, `$q`, `$q`, `$q`).

### 6. `website_files/api/repuestos.php`
- **`buscar`:** Búsqueda por nombre o número de parte (`LIKE ? OR LIKE ?`).
  - **Tipos `bind_param`:** `"ss"` (`$q`, `$q`).

### 7. `website_files/api/lotes.php`
- **`list`:** Filtro opcional por tipo de lote (`LOCAL`, `NACIONAL`, etc.).
  - **Tipos `bind_param`:** `"s"` (`$tipo`).
- **`ver`:** Obtención de lote por identificador.
  - **Tipos `bind_param`:** `"s"` (`$id`).
- **`crear` (auto-cargar equipos de triaje):**
  - **Tipos `bind_param`:** `"s"` (`$lid`).
- **`avanzar_estado`:** Cambio de fase en el ciclo de vida del lote.
  - **Tipos `bind_param`:** `"ss"` (`$nuevo_estado`, `$lid`).
- **`agregar_equipo`:** Sincronización de triaje y falla en tabla `equipos`.
  - **Tipos `bind_param`:** `"ssss"` (`$triaje`, `$triaje`, `$falla`, `$cod`).
- **`editar`:** Modificación de título y notas del lote.
  - **Tipos `bind_param`:** `"sss"` (`$titulo`, `$notas`, `$lid`).
- **`editar_repuestos`:** Seguimiento de repuestos, orden y costos.
  - **Tipos `bind_param`:** `"ssss"` (`$orden`, `$costo`, `$estado`, `$lid`).
- **`editar_item`:** Actualización de items individuales del lote y sincronización con equipos.
  - **Tipos `bind_param`:** `"ssssi"` (`$falla`, `$pieza`, `$pn`, `$triaje`, `$le_id`) y `"sss"` para equipos.
- **`mover_pendientes`:** Traslado de equipos entre lotes con recálculo de conteos.
  - **Tipos `bind_param`:** `"ssis"` (auditoría), `"ss"` (traslado), `"ss"` (recálculo origen y destino).
- **`eliminar`:** Limpieza referencial en cascada segura (reset triaje equipos, borrado de items y lote).
  - **Tipos `bind_param`:** `"s"` (`$lid`) en 3 sentencias preparadas consecutivas.

### 8. `website_files/api/garantias.php`
- **`list`:** Filtro por estado de garantía de proveedor.
  - **Tipos `bind_param`:** `"s"` (`$estado`).
- **`ver`:** Búsqueda de garantía e items asociados.
  - **Tipos `bind_param`:** `"i"` (`$id`).
- **`crear_desde_lote`:** Verificación de lote nacional.
  - **Tipos `bind_param`:** `"s"` (`$lote_id`).
- **`actualizar`:** Modificación de estado, tipo de resolución, notas y días de alerta.
  - **Tipos `bind_param`:** `"sssii"` (`$estado`, `$tipo_res`, `$notas`, `$alerta_dias`, `$id`).

### 9. `website_files/api/turnos.php`
- **`verificar_turno`:** Consulta de turno activo del técnico.
  - **Tipos `bind_param`:** `"i"` (`$tecnico_id`).
- **`crear` (verificación):** Detección de turnos preexistentes.
  - **Tipos `bind_param`:** `"i"` (`$tecnico_id`).

### 10. `website_files/api/historial.php`
- **`ver`:** Consulta de auditoría por tabla de origen e ID de registro.
  - **Tipos `bind_param`:** `"si"` (`$tabla`, `$id`).
- **`registrar`:** Corrección de la máscara de tipos del `bind_param` para coincidir exactamente con los 7 campos.
  - **Tipos `bind_param`:** `"sisssss"` (`$tabla`, `$reg_id`, `$num_ref`, `$campo`, `$ant`, `$nuevo`, `$usuario`).

### 11. `website_files/api/desempeno.php`
- **Auditoría completa:** Consultas internas con lista blanca estricta en PHP (`hoy`, `semana`, `mes`) y sin interpolación de entradas del usuario. **No requirió modificación de código**.

### 12. `website_files/api/notificaciones.php`
- **Cron de alarmas de garantías:**
  - Comprobación de duplicados: `bind_param("is", $uid, $like_gar)`.
  - Actualización de última fecha: `bind_param("i", $gar_id)`.
- **`list`:** `bind_param("i", $usuario_id)`.
- **`marcar_leida`:** `bind_param("ii", $id, $usuario_id)`.
- **`marcar_todas_leidas`:** `bind_param("i", $usuario_id)`.

### 13. `website_files/api/roles.php`
- **`guardar`:** Actualización de módulos permitidos y página por defecto para el rol.
  - **Tipos `bind_param`:** `"sss"` (`$pagina_defecto`, `$modulos_json`, `$rol`).

### 14. `website_files/api/importar.php`
- **Verificación de duplicado:** Búsqueda insensible a mayúsculas/espacios por código.
  - **Tipos `bind_param`:** `"s"` (`$codigo_clean`).
- **Inserción por lote:** Inserción parametrizada dinámica basada en la lista blanca `$allowed_columns`.
  - **Tipos `bind_param`:** `str_repeat('s', count($vals))` con desempaquetado de argumentos `...$vals`, vinculando `null` para campos vacíos.

### 15. `website_files/api/push.php`
- **`sendPushToUser`:** Consulta de credenciales Push por usuario.
  - **Tipos `bind_param`:** `"i"` (`$userId`).
- **Limpieza de suscripciones caducadas:**
  - **Tipos `bind_param`:** `"i"` (`$sub_id`).
- **`case "unsubscribe"`:** Eliminación de suscripción de la sesión.
  - **Tipos `bind_param`:** `"i"` (`$userId`).

---

## 3. Matriz de Tipos de Datos Utilizados en `bind_param`

| Letra | Tipo de Dato MySQLi | Uso en la Aplicación |
|:---:|:---|:---|
| `s` | **String** | Nombres, descripciones, notas, estados, códigos alfanuméricos, DNI, fechas formateadas, JSON serializados. |
| `i` | **Integer** | Identificadores (`id`), claves foráneas (`user_id`, `tecnico_id`, `sesion_id`), banderas booleanas (`activo`, `leido`), días de alerta. |
| `d` | **Double/Float** | Precios y costos monetarios (cuando aplique almacenamiento numérico con decimales). |

---

## 4. Verificación de Integridad

- **Compatibilidad sintáctica:** Todos los archivos modificados mantienen las variables originales, retornos JSON estándar (`ok`, `data`, `msg`) y códigos de estado HTTP correspondientes.
- **Auditoría Git:** 14 archivos modificados listos para commit en rama `feature/notificaciones-garantias`.
- **Cero fugas de credenciales:** El archivo `website_files/api/config.php` permanece protegido en el hosting, eliminado del almacenamiento local e ignorado por Git.
