# 02 - MANUAL Y ARQUITECTURA TÉCNICA DEL BACKEND (PHP / MySQL)

> **Documento de Referencia Técnica y Mantenimiento del Servidor**  
> **Destinatario:** Propietarios del sistema, administradores y futuros desarrolladores  
> **Enfoque:** Lenguaje claro, estructurado y accesible para personas sin formación técnica  
> **Estado del Sistema:** Auditado, protegido contra inyección SQL y credenciales aisladas  

---

## 1. Catálogo Completo de Endpoints (`api/`)

En el desarrollo web, un **endpoint** (punto de acceso) es como la ventanilla de un banco: una dirección web específica a la que el navegador envía una carta pidiendo o entregando información.  
En este sistema, todos los endpoints están en la carpeta `api/` y responden en formato **JSON** (un formato de texto estándar y ligero que las computadoras usan para intercambiar datos estructurados).

A continuación se detallan todas las ventanillas disponibles, la acción que realizan y el **método HTTP** que esperan:
- **GET:** Método para *pedir o consultar* información sin alterar nada en el servidor.
- **POST:** Método para *enviar o guardar* datos nuevos o modificar registros existentes.

---

### 1.1. Autenticación y Sesiones: `api/auth.php`
Controla quién puede entrar al sistema y mantiene abierta la sesión del usuario en el servidor.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `login` | **POST** | Recibe el DNI y la contraseña, verifica que coincidan con la base de datos y abre la sesión de trabajo. |
| `check` | **GET** | Pregunta al servidor si el usuario sigue conectado y devuelve su nombre, rol y módulos permitidos. |
| `logout` | **GET / POST** | Cierra la sesión activa en el servidor y borra los datos temporales del usuario. |

---

### 1.2. Consulta Pública de Clientes: `api/consulta.php`
Permite a los clientes consultar el estado de su reparación por internet sin tener cuenta de usuario.

| Parámetros | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `?ticket=...&dni=...` | **GET** | Busca en la base de datos una orden de reparación que coincida con el número de ticket y el DNI ingresados. |

---

### 1.3. Soporte Técnico y Reparaciones: `api/soporte.php`
El núcleo operativo del taller: administra las órdenes de servicio, diagnósticos y tareas de reparación.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `list` | **GET** | Consulta y filtra la lista de órdenes de trabajo por estado, fechas, técnico o texto libre (excluye registros con estado `ELIMINADO` por defecto). |
| `ver` | **GET** | Devuelve toda la información detallada de una sola orden a partir de su identificador numérico (`id`). |
| `crear` | **POST** | Registra el ingreso de una laptop de un cliente externo y genera un número de ticket con formato `ST-AAAAMMDD-XXX` (recibe 11 parámetros incluyendo `tiempo_estimado` y asignación de técnico). |
| `crear_tarea` | **POST** | Registra una tarea de trabajo interna y genera un número de ticket con formato `TAR-AAAA-XXX`. |
| `crear_interno` | **POST** | Registra una reparación sobre un equipo propio de la empresa con formato `ST-INT-AAAA-XXX`. |
| `actualizar` | **POST** | Cambia el estado de un ticket (ej. de diagnóstico a listo), guarda notas técnicas y registra la fecha. |
| `eliminar` | **POST** | Borra una orden de soporte del sistema (acción protegida solo para administradores). |
| `buscar_equipo` | **GET** | Busca en el inventario laptops por código o serie para autocompletar la recepción. |
| `buscar_barcode` | **GET** | Busca de forma instantánea una laptop cuando se escanea su código de barras físico. |
| `mis_ordenes` | **GET** | Lista únicamente los tickets asignados al técnico que tiene la sesión iniciada en ese momento. |
| `stats` | **GET** | Cuenta cuántos tickets hay en cada estado para alimentar los contadores del panel de control. |
| `entregados` | **GET** | Consulta el archivo histórico de todas las laptops que ya fueron devueltas a sus dueños. |

---

### 1.4. Inventario de Equipos: `api/equipos.php`
Administra el catálogo de computadoras, laptops y servidores que posee la empresa.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `list` | **GET** | Muestra el listado de equipos con paginación (bloques de 50 en 50) y filtros de búsqueda. |
| `ver` | **GET** | Muestra todas las características de un equipo (procesador, memoria RAM, disco duro, pantalla, etc.). |
| `crear` | **POST** | Añade manualmente una laptop o computadora nueva al inventario general. |
| `editar` | **POST** | Modifica los datos técnicos, sucursal, estado u observaciones de un equipo existente. |
| `eliminar` | **POST** | Retira definitivamente un equipo de la base de datos (restringido a administradores). |
| `buscar` | **GET** | Busca coincidencias rápidas de equipos por número de serie o código interno. |
| `inventario_rapido` | **POST** | Permite escanear un equipo y asignarle de inmediato si funciona o si tiene alguna falla detectada. |
| `list_docs` | **GET** | Lista equipos agrupados según la factura o guía de compra o venta asociada. |
| `reporte_avanzado` | **GET** | Filtra equipos combinando múltiples condiciones complejas para generar hojas de reporte. |

---

### 1.5. Lotes de Compra al por Mayor: `api/lotes.php`
Permite recibir cargamentos masivos de laptops (nacionales o locales), pistolearlas y clasificarlas.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `list` | **GET** | Lista todos los lotes de compra registrados indicando cuántas máquinas contiene cada uno. |
| `ver` | **GET** | Muestra el detalle de un lote y cada una de las laptops que fueron escaneadas dentro de él. |
| `crear` | **POST** | Registra un lote nuevo (generando un código `LOT-AAAA-XXX`) y calcula sus cantidades. |
| `editar` | **POST** | Modifica el título, descripción o notas comerciales del lote. |
| `avanzar_estado` | **POST** | Cambia la etapa del lote (de "Borrador" a "Listo para escaneo", "En proceso" o "Completado"). |
| `agregar_equipo` | **POST** | Añade una máquina al lote tras ser escaneada con la lectora y le asigna su estado de triaje. |
| `editar_item` | **POST** | Corrige la falla, repuesto necesario o clasificación de una laptop individual dentro del lote. |
| `editar_repuestos` | **POST** | Registra el costo y número de orden de los repuestos que se mandaron a pedir para este lote. |
| `mover_pendientes` | **POST** | Pasa automáticamente todas las máquinas que necesitan repuesto de un lote viejo a un lote nuevo. |
| `eliminar` | **POST** | Elimina un lote y restaura el estado limpio de los equipos que estaban dentro de él. |

---

### 1.6. Garantías con Proveedores: `api/garantias.php`
Controla las computadoras o repuestos fallados que se envían a los mayoristas para cambio o devolución.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `list` | **GET** | Muestra todas las órdenes de garantía abiertas y su cantidad de productos en reclamo. |
| `ver` | **GET** | Muestra qué máquinas específicas y qué piezas están incluidas en un reclamo a un proveedor. |
| `crear` | **POST** | Abre una garantía manual ingresando el nombre y RUC del proveedor (código `GAR-AAAAMMDD-XXX`). |
| `crear_desde_lote` | **POST** | Junta en un solo clic todas las máquinas falladas de un lote nacional y arma la orden de garantía. |
| `crear_desde_triaje` | **POST** | Arma una orden de garantía con todos los equipos fallados de una sesión masiva de inventario. |
| `actualizar` | **POST** | Cambia el estado de la garantía (Enviado, Aprobado, Rechazado) y programa días de alarma de seguimiento. |
| `resumen` | **GET** | Retorna conteo de KPI ejecutivos en tiempo real (total, enviadas, en proceso, resueltas, rechazadas). |

---

### 1.7. Turnos y Horarios de Técnicos: `api/turnos.php`
Supervisa los días y horas de trabajo de cada técnico del taller.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `list` | **GET** | Muestra el listado de técnicos junto con sus días y horas configuradas de trabajo. |
| `verificar_turno` | **GET** | Responde si un técnico en particular está o no en horario de trabajo justo en el segundo actual. |
| `estado_actual` | **GET** | Evalúa a toda la plantilla y muestra en vivo quiénes deberían estar trabajando en este instante. |
| `crear` | **POST** | Configura el horario laboral inicial de un técnico (solo administradores). |
| `editar` | **POST** | Cambia los días de descanso o las horas de entrada/salida de un técnico (solo administradores). |

---

### 1.8. Rendimiento y Desempeño: `api/desempeno.php`
Calcula la productividad laboral midiendo las horas efectivas que cada técnico pasa reparando laptops.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `resumen` | **GET** | Suma las horas trabajadas y lista las actividades terminadas por cada técnico en el día, semana o mes. |

---

### 1.9. Personas y Clientes: `api/personas.php`
Directorio central de clientes, recepcionistas, técnicos y administradores.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `list` | **GET** | Lista personas filtrando por rol (ej. solo técnicos o solo administradores). |
| `todos` | **GET** | Devuelve el directorio completo de personas registradas sin distinción. |
| `buscar_dni` | **GET** | Busca de manera exacta a un cliente por su número de documento de identidad (DNI). |
| `buscar` | **GET** | Busca clientes escribiendo parte de su nombre, apellido o teléfono. |
| `crear` | **POST** | Registra a un cliente nuevo en el sistema. |
| `editar` | **POST** | Actualiza el teléfono, dirección o notas de una persona registrada. |
| `eliminar` | **POST** | Da de baja a un usuario o cliente en la base de datos. |

---

### 1.10. Catálogo de Repuestos y Compras de Taller: `api/repuestos.php`
Controla el stock de piezas de recambio (pantallas, teclados, cargadores, baterías, flex) y la trazabilidad de compras y couriers.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `resumen` / `metricas` | **GET** | Devuelve métricas KPI (total catálogo, stock físico, piezas críticas/agotadas, valorización en S/. y conteos por categoría). |
| `list` | **GET** | Lista piezas del catálogo con soporte de filtros por categoría (`?categoria=...`) y stock crítico (`?critico=1`). |
| `buscar` | **GET** | Búsqueda reactiva por texto libre en nombre, número de parte (P/N), categoría o ubicación en taller. |
| `crear` | **POST** | Registra un nuevo repuesto con precio referencial, stock actual, stock mínimo y ubicación (restringido a admin/gerencia). |
| `actualizar` | **POST** | Modifica datos técnicos, existencias físicas, precios o ubicación de una pieza existente. |
| `eliminar` | **GET / POST** | Da de baja definitivamente una pieza del catálogo (restringido exclusivamente a administradores). |
| `tracking_pedidos` | **GET** | Auto-sincroniza y lista las compras de repuestos en tránsito, couriers y fechas estimadas de llegada. |
| `guardar_tracking` | **POST** | Actualiza proveedor, courier, número de tracking, costo y estado de envío de un pedido de repuesto. |
| `marcar_recibido` | **POST** | Cambia el estado de la pieza a "RECIBIDO_EN_TALLER", actualiza el ticket de soporte a "EN_REPARACION" y notifica al técnico. |
| `escanear_voucher_pedido` | **POST** | Procesa la fotografía de un voucher físico con IA Gemini 2.5 Flash, extrae datos de la encomienda y flete (`costo_envio`), consulta el rastreo oficial en vivo y actualiza la orden y la fecha estimada de llegada en `soporte_tecnico`. |
| `actualizar_tracking_en_vivo` | **POST** | Reconsulta en vivo la API de Cruz del Sur Cargo o Shalom Express y refresca el estado en carretera, destino y eventos de transporte de una orden de repuesto. |
| `registrar_pago_proveedor` | **POST** | Registra el pago por transferencia de Gerencia al proveedor (banco, CCI/cuenta, número de operación y voucher digital) y actualiza el balance financiero. |
| `marcar_compra_local` | **POST** | Registra en 1 clic compras locales en Arequipa sin flete courier (`costo_envio = 0.00`), pasando la orden a tránsito inmediato. |
| `marcar_instalado` | **POST** | Marca el repuesto como instalado y probado por el técnico, actualiza `soporte_tecnico` a `REPARADO` (Listo para entrega) y notifica a recepción. |
| `finalizar_entrega` | **POST** | Registra la entrega final al cliente, calcula el balance neto de rentabilidad deduciendo el flete (`Cobro - Costo - Flete = Margen`), transiciona a `ENTREGADO` y activa la garantía técnica. |

---

### 1.11. Configuración de Roles: `api/roles.php`
Permite a la gerencia definir qué pantallas puede abrir cada puesto de trabajo.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `listar` | **GET** | Devuelve la lista de roles (`admin`, `gerencia`, `tecnico`) y los módulos a los que tienen acceso. |
| `guardar` | **POST** | Guarda en la base de datos la lista de pantallas permitidas y la página de inicio para un rol. |

---

### 1.12. Escaneo y Triaje Masivo: `api/sesiones.php`
Utilizado para revisar rápidamente lotes grandes de computadoras mediante pistoleo continuo.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `activa` | **GET** | Comprueba si el usuario tiene una mesa de trabajo o sesión de escaneo abierta hoy. |
| `crear` | **POST** | Abre una sesión de escaneo nueva (ej. "Revisión Lote Dell Mañana"). |
| `inventario_rapido`| **POST** | Escanea una máquina, le asocia su triaje y la suma a la sesión abierta. |
| `remover_item` | **POST** | Quita una laptop de la lista escaneada en caso de haberse equivocado. |
| `cerrar` | **POST** | Cierra la sesión de trabajo y consolida el conteo total de laptops operativas y falladas. |
| `listar` | **GET** | Muestra el archivo de sesiones de triaje realizadas en días anteriores. |
| `ver` | **GET** | Permite revisar qué laptops se pistolearon en una sesión cerrada del pasado. |
| `eliminar` | **POST** | Borra una sesión y restaura el estado previo de las máquinas involucradas. |
| `editar_sesion_nombre` | **POST** | Cambia el nombre descriptivo de la sesión de inventario. |

---

### 1.13. Registro Histórico de Auditoría: `api/historial.php`
Caja negra del sistema: anota quién cambió qué y a qué hora.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `registrar` | **POST** | Llamado internamente por el sistema para guardar un cambio de estado en la auditoría. |
| `ver` | **GET** | Muestra toda la historia de cambios por la que ha pasado una orden de trabajo. |
| `reciente` | **GET** | Muestra los últimos 50 movimientos o modificaciones ocurridas en todo el sistema. |

---

### 1.14. Importador de Hojas de Cálculo: `api/importar.php`
Permite cargar miles de laptops desde Excel al inventario en segundos.

| Método | ¿Qué hace en una frase simple? |
| :---: | :--- |
| **POST** | Recibe las filas leídas de un Excel, verifica que los códigos no existan ya y guarda los equipos nuevos ignorando duplicados. |

---

### 1.15. Notificaciones Web Push (VAPID): `api/push.php`
Hace sonar y vibrar los teléfonos y computadoras del personal cuando entra una orden nueva.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `vapid_key` | **GET** | Entrega la clave pública del servidor para que el navegador del usuario solicite permiso de alertas. |
| `subscribe` | **POST** | Guarda el teléfono o navegador del usuario en la base de datos para poder enviarle avisos. |
| `unsubscribe` | **POST** | Da de baja el dispositivo para que deje de recibir notificaciones push. |
| `test` | **GET / POST** | Envía una alerta de prueba inmediata al dispositivo del usuario que la pide. |

---

### 1.16. Notificaciones Internas y Motor de SLA: `api/notificaciones.php`
Controla el centro de notificaciones de la campanita en pantalla, coordina alertas en tiempo real y ejecuta el motor de SLA para compras críticas de repuestos y vencimiento de garantías.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `list` | **GET** | Ejecuta en segundo plano la evaluación de SLA (24h preventivo / 48h recurrente horaria en repuestos sin comprar y garantías demoradas) y entrega las últimas 50 alertas dirigidas al usuario. |
| `marcar_leida` | **POST** | Marca una notificación individual como leída (apagando su borde activo) al hacer clic sobre ella antes de redirigir al módulo destino. |
| `marcar_todas_leidas`| **POST** | Marca como leídas todas las alertas acumuladas del usuario y resetea el contador del badge a cero. |

---

### 1.17. Auditoría y Rendimiento Técnico: `api/desempeno.php`
Calcula métricas de productividad, estado en vivo y auditoría histórica de tiempos de reparación para técnicos individuales y equipos colaborativos.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `resumen` | **GET** | Devuelve el consolidado de KPIs globales (eficiencia, tickets cerrados, tiempos promedio y ranking de técnicos). |
| `detalle_tecnico` | **GET** | Desglosa la actividad de un técnico específico (`tecnico_id`), tiempos reales por ticket y roles (Titular/Colaborador/Equipo). |
| `tecnicos_en_vivo` | **GET** | Provee el estado en tiempo real (`TRABAJANDO` o `LIBRE`), el ticket actual y la duración activa para cada técnico. |
| `historial_semanal` | **GET** | Alimenta el heatmap de intensidad de trabajo y zonas calientes estilo GitHub. |

---

### 1.18. Rastreo de Couriers y Encomiendas: `api/courier_tracking.php`
Permite conectar de forma automatizada e instantánea con las empresas de transporte interprovincial Cruz del Sur Cargo y Shalom Express.

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `asociar` | **POST** | Valida una guía en vivo contra la API del courier y la vincula de forma segura a una orden de trabajo. |
| `consultar` | **GET / POST** | Consulta el estado en tiempo real contra el servidor del courier y refresca la base de datos local. |
| `ver` | **GET** | Devuelve los datos de la guía vinculada a un ticket, su estado actual y su línea de tiempo de eventos. |
| `desvincular` | **POST** | Remueve la asociación de transporte de un ticket en caso de equivocación de número. |
| `test_en_vivo` | **GET** | Ejecuta una verificación diagnóstica directa con Cruz del Sur o Shalom (solo administración). |

---

### 1.19. Extracción Multimodal de Vouchers con Inteligencia Artificial: `api/courier_ia.php`
Servicio de visión artificial basado en Google Gemini 2.5 Flash con arquitectura resiliente de fallback escalonado (`gemini-2.5-flash` → `gemini-2.5-flash-lite` → `gemini-2.0-flash`).

| Acción (`action`) | Método | ¿Qué hace en una frase simple? |
| :--- | :---: | :--- |
| `escanear_voucher` | **POST** | Recibe una imagen en Base64 o archivo multipart, identifica la empresa transportista (Cruz del Sur Cargo o Shalom Express), y extrae número de guía, serie, orden y código de seguridad en formato JSON limpio. |
| `analizar_y_rastrear` | **POST** | Escanea el voucher físico con IA y encadena de inmediato la consulta a los servidores del transportista, entregando la encomienda parseada junto con su estado oficial en vivo. |

---

## 2. Matriz de Seguridad y Control de Acceso

No todas las ventanillas están abiertas para todo el mundo. El sistema aplica tres niveles de candado:
1. **Público:** Accesible para cualquier visitante de internet.
2. **Requiere Sesión:** El usuario debe haberse identificado con DNI y contraseña válida.
3. **Requiere Rol o Módulo:** El usuario no solo debe estar conectado, sino que su puesto de trabajo debe tener permiso explícito para esa función.

| Endpoint (`api/*.php`) | ¿Requiere Sesión? | Nivel de Permiso Requerido | ¿Qué pasa si no tengo permiso? |
| :--- | :---: | :--- | :--- |
| `consulta.php` | ❌ No (Público) | Libre | Muestra el resultado de la búsqueda pública. |
| `push.php?action=vapid_key` | ❌ No (Público) | Libre | Devuelve la clave pública de cifrado. |
| `auth.php?action=login` | ❌ No (Público) | Credenciales válidas | Si la clave falla, devuelve error `401`. |
| `auth.php?action=check` | ⚠️ Opcional | Libre | Si no hay sesión, indica que está desconectado. |
| `roles.php` | ✅ Sí | **Solo Administrador** (`admin_only`) | Bloqueo HTTP `403 Forbidden` (Acceso denegado). |
| `turnos.php?action=crear/editar` | ✅ Sí | **Solo Administrador** (`admin_only`) | Bloqueo HTTP `403 Forbidden` (Acceso denegado). |
| `lotes.php` | ✅ Sí | **Módulo `lotes.html`** | Bloqueo HTTP `403 Forbidden` si no está en sus permisos. |
| `garantias.php` | ✅ Sí | Sesión de Técnico, Gerencia o Admin | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `soporte.php` | ✅ Sí | Sesión de Técnico, Recepción o Admin | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `equipos.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `desempeno.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `sesiones.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `personas.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `repuestos.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `historial.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `importar.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `notificaciones.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `cleanup_dupes.php` | ✅ Sí | **Solo Administrador** (`admin_only`) | Bloqueo HTTP `403 Forbidden` (Acceso denegado). |
| `manage_accounts.php` | ✅ Sí | **Solo Administrador** (`admin_only`) | Bloqueo HTTP `403 Forbidden` (Acceso denegado). |
| `courier_tracking.php?action=asociar/desvincular` | ✅ Sí | Sesión de Técnico, Recepción o Admin | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |
| `courier_tracking.php?action=ver/consultar` | ⚠️ Opcional | Libre / Sesión activa | Consulta el estado del transporte y devuelve timeline. |
| `courier_ia.php` | ✅ Sí | Sesión activa en el sistema | Bloqueo HTTP `401 Unauthorized` si no inició sesión. |

---

## 3. Esquema Completo de la Base de Datos

La base de datos MySQL se llama `petumjvq_sst` y funciona como un archivero con **18 cajones principales (tablas)**.  
Una **clave foránea** (o relación) es simplemente un número que apunta a otra tabla para evitar repetir información (por ejemplo, en vez de escribir todo el nombre del cliente en cada ticket, solo se anota su `cliente_id`).

A continuación se lista cada cajón y su función:

### 1. `personas` (Directorio de Usuarios y Clientes)
- **Columnas clave:** `id` (código único), `dni` (documento), `nombre`, `apellido`, `telefono`, `tipo` (`admin`, `tecnico`, `gerencia`, `cliente`), `especialidad`, `password_hash` (contraseña encriptada), `activo`.
- **Relaciones:** Se conecta con casi todas las tablas del sistema: es el técnico que repara, el cliente que encarga, o el administrador que supervisa.

### 2. `equipos` (Inventario General de Computadoras)
- **Columnas clave:** `id`, `codigo` (código interno de barra), `serie` (número de serie de fábrica), `tipo_equipo` (Laptop, PC, Servidor), `marca`, `modelo`, `procesador`, `ram`, `hd_ssd`, `pantalla`, `estado` (P=Pendiente, V=Vendido), `triaje` (`SIN_FALLA`, `FALLA_MENOR`, `NECESITA_REPUESTO`, `DANO_GRAVE`), `falla` (descripción del defecto).
- **Relaciones:** Se vincula con las órdenes de servicio en `soporte_tecnico` y con los lotes en `lote_equipos`.

### 3. `soporte_tecnico` (Órdenes de Trabajo y Tickets)
- **Columnas clave:** `id`, `numero_atencion` (`ST-...` o `TAR-...`), `cliente_id` (quién la trajo), `equipo_codigo`, `equipo_serie`, `equipo_descripcion`, `motivo_ingreso`, `diagnostico`, `solucion`, `tiempo_estimado`, `prioridad`, `estado` (`PENDIENTE`, `EN_DIAGNOSTICO`, `EN_REPARACION`, `ESPERANDO_REPUESTO`, `LISTO_PARA_RECOGER`, `ENTREGADO`, `CANCELADO`, `ELIMINADO`), `tecnico_id` (técnico titular), `tecnicos_adicionales` (IDs de técnicos colaboradores separados por coma), `fecha_ingreso`, `fecha_entrega`.
- **Relaciones:**  
  - `cliente_id` apunta a `personas.id` (el cliente dueño o nulo en órdenes internas).  
  - `tecnico_id` apunta a `personas.id` (el técnico asignado titular).
  - `tecnicos_adicionales` referencia múltiples IDs de `personas.id` para trabajo colaborativo o en equipo.

### 4. `secuencias_tickets` (Contador de Tickets Diarios)
- **Columnas clave:** `fecha_str` (fecha en formato `AAAAMMDD`), `ultimo_valor` (número correlativo actual del día).
- **Función:** Garantiza que dos recepcionistas guardando al mismo milisegundo nunca reciban el mismo número de ticket.

### 5. `lotes` (Cargamentos de Compra Masiva)
- **Columnas clave:** `id`, `lote_id` (código alfanumérico ej. `LOT-2026-001`), `titulo`, `tipo` (`LOCAL`, `NACIONAL`), `proveedor_nombre`, `proveedor_ruc`, `cantidad_estimada`, `cantidad_real`, `estado` (`LISTO_PISTOLEO`, `EN_PROCESO`, `COMPLETADO`), `admin_id`.
- **Relaciones:** `admin_id` apunta al administrador que creó el lote en `personas.id`.

### 6. `lote_equipos` (Laptops dentro de un Lote)
- **Columnas clave:** `id`, `lote_id`, `equipo_codigo`, `falla`, `pieza`, `pn` (número de parte), `triaje`, `estado_item`, `tecnico_id`.
- **Relaciones:**  
  - `lote_id` apunta a `lotes.lote_id`.  
  - `equipo_codigo` apunta a `equipos.codigo`.

### 7. `log_movimientos_lotes` (Auditoría de Traslados de Lotes)
- **Columnas clave:** `id`, `lote_origen`, `lote_destino`, `equipo_codigo`, `usuario_id`, `fecha`.
- **Función:** Anota el historial cuando una laptop que necesita repuesto se mueve de un lote a otro.

### 8. `garantias_proveedor` (Reclamos a Mayoristas)
- **Columnas clave:** `id`, `numero_garantia` (`GAR-...`), `lote_id`, `proveedor_nombre`, `proveedor_ruc`, `total_equipos`, `estado` (`PENDIENTE`, `ENVIADO`, `RESUELTO`), `tipo_resolucion` (`CAMBIO`, `NOTA_CREDITO`), `alerta_dias`, `ultima_alerta_fecha`.
- **Relaciones:** Puede apuntar al lote de origen en `lotes.lote_id`.

### 9. `garantia_items` (Productos dentro de una Garantía)
- **Columnas clave:** `id`, `garantia_id`, `equipo_codigo`, `equipo_serie`, `marca`, `modelo`, `falla`, `tipo_item` (`EQUIPO` o `REPUESTO`).
- **Relaciones:** `garantia_id` apunta a `garantias_proveedor.id`.

### 10. `turnos` (Horarios Laborales)
- **Columnas clave:** `id`, `tecnico_id` (técnico), `dias_trabajo` (ej. "Lunes,Martes,Miercoles"), `hora_inicio`, `hora_fin`, `activo` (1 o 0).
- **Relaciones:** `tecnico_id` apunta a `personas.id` de manera única (un horario por técnico).

### 11. `historial_cambios` (Auditoría de Acciones y Trazabilidad)
- **Columnas clave:** `id`, `tabla_origen` (ej. `soporte_tecnico`), `registro_id`, `numero_referencia`, `campo_cambiado` (`estado`), `valor_anterior`, `valor_nuevo`, `usuario_nombre`, `fecha_cambio`.
- **Función:** Guarda cada vez que alguien cambia un estado. Alimenta el cálculo de horas y desempeño de los técnicos.

### 12. `notificaciones` (Campanita de Avisos en Pantalla)
- **Columnas clave:** `id`, `usuario_id`, `titulo`, `mensaje`, `link` (a qué pantalla ir al hacer clic), `leido` (0=no, 1=sí), `fecha`.
- **Relaciones:** `usuario_id` apunta a `personas.id`.

### 13. `push_subscriptions` (Dispositivos Suscritos a Alertas Push)
- **Columnas clave:** `id`, `user_id`, `endpoint` (dirección web del navegador en Google/Mozilla/Apple), `p256dh` (clave criptográfica), `auth` (secreto de cifrado).
- **Relaciones:** `user_id` apunta a `personas.id`.

### 14. `roles_config` (Permisos y Módulos por Rol)
- **Columnas clave:** `id`, `rol` (`admin`, `gerencia`, `tecnico`), `pagina_defecto` (pantalla que abre al loguearse), `modulos_permitidos` (lista de pantallas autorizadas guardada en texto JSON).

### 15. `repuestos` (Inventario de Piezas)
- **Columnas clave:** `id`, `nombre`, `pn` (código de parte), `stock` (cantidad disponible), `precio`, `notas`.

### 16. `sesiones_inventario` (Mesas de Escaneo Masivo)
- **Columnas clave:** `id`, `usuario_id`, `nombre`, `estado` (`ACTIVA` o `CERRADA`), `fecha_creacion`, `fecha_cierre`.
- **Relaciones:** `usuario_id` apunta al recepcionista/técnico en `personas.id`.

### 17. `sesiones_items` (Laptops Pistoleadas en una Sesión)
- **Columnas clave:** `id`, `sesion_id`, `equipo_id`, `triaje_asignado`, `falla_asignada`.
- **Relaciones:**  
  - `sesion_id` apunta a `sesiones_inventario.id`.  
  - `equipo_id` apunta a `equipos.id`.

### 18. `guias_envio` (Rastreo de Encomiendas de Couriers)
- **Columnas clave:** `id`, `ticket_id` (orden de soporte asociada), `numero_referencia` (`ST-...`), `courier` (`CRUZ_DEL_SUR`, `SHALOM`), `numero_guia_orden`, `codigo_seguridad`, `ose_id`, `estado_courier` (`REGISTRADO`, `EN_TRANSITO`, `LISTO_PARA_RECOJO`, `ENTREGADO`), `ultimo_mensaje`, `origen`, `destino`, `agencia_destino`, `remitente`, `destinatario`, `fecha_emision`, `fecha_entrega_courier`, `importe`, `peso_kg`, `raw_data_json`.
- **Relaciones:** `ticket_id` apunta a `soporte_tecnico.id`.

---

## 4. El Sistema de Roles y Módulos en Detalle

Uno de los pilares del sistema es que **cada empleado solo vea los botones que corresponden a su trabajo**.

```
[Base de Datos: roles_config]
        │
        ▼ (Al iniciar sesión, api/auth.php empaqueta los módulos en la sesión del servidor)
[$_SESSION['user_modulos']]
        │
        ▼ (El navegador consulta api/auth.php?action=check)
[Frontend: window.userModulos en js/check_auth.js]
        │
        ▼ (Recorre cada botón <a> del menú lateral)
¿El enlace href está en la lista permitida?
        ├── SÍ ──► Muestra el botón normalmente.
        └── NO ──► Aplica estilo: display: none (desaparece de la vista).
```

### ¿Dónde se configura?
En la pantalla `admin_roles.html`, el administrador marca casillas con los módulos permitidos y presiona "Guardar Cambios". La API `api/roles.php?action=guardar` guarda la lista en la tabla `roles_config` en una sola columna en formato JSON (ejemplo: `["index.html", "soporte.html", "inventario.html"]`).

### El Caso Real del Bug que Solucionamos:
**¿Por qué desapareció el botón de "Desempeño Técnicos"?**
1. La pantalla del reporte se llama físicamente `desempeno_tecnicos.html`.
2. En la pantalla visual de administrar roles, el programador anterior **olvidó poner la casilla de `desempeno_tecnicos.html`**. Solo existía una casilla llamada `Lotes y Equipos (Reportes)` que en realidad controlaba `reportes.html`.
3. El usuario entró a revisar los roles, vio marcado `Reportes` y le dio a **"Guardar Cambios"**.
4. Al guardar, el formulario leyó únicamente las casillas visibles en pantalla y envió esa lista a la base de datos. Como no había casilla para `desempeno_tecnicos.html`, **se borró silenciosamente de la base de datos**.
5. Al día siguiente, cuando el administrador abrió el sistema, `check_auth.js` revisó la lista, no encontró `desempeno_tecnicos.html` y **ocultó el botón en todos los menús**.

### Regla de Oro para el Futuro:
El nombre del archivo físico (`.html`), el valor de la casilla en `admin_roles.html` y el enlace `href` en el menú **DEBEN LLAMARSE EXACTAMENTE IGUAL**. Una sola letra de diferencia o una tilde provocará que el botón desaparezca.

> **Blindaje especial añadido:** Ahora el rol `admin` tiene una regla de protección en `admin_roles.html` y en `check_auth.js` que garantiza que **el administrador siempre tenga acceso a todas las pantallas existentes**, evitando que se vuelva a quedar sin acceso aunque desmarque casillas por error.

---

## 5. El Flujo de Notificaciones (Web e Hiperconectadas)

El sistema cuenta con dos mecanismos complementarios de aviso:

```
                  ┌─────────────────────────────────────────┐
                  │     Evento: Entra un nuevo ticket        │
                  └────────────────────┬────────────────────┘
                                       │
                ┌──────────────────────┴──────────────────────┐
                ▼                                             ▼
   [Notificación Interna en BD]                  [Notificación Web Push VAPID]
 (api/notificaciones.php)                       (api/push.php)
                │                                             │
                ▼                                             ▼
  Se guarda en tabla `notificaciones`            Se cifra el mensaje con llaves
  con usuario_id y link.                         matemáticas privadas del servidor.
                │                                             │
                ▼                                             ▼
  El navegador consulta cada X segundos          Se envía a los servidores de Google/Apple
  y enciende la campanita roja en pantalla.      y hace sonar el celular aunque esté bloqueado.
```

1. **Notificación en Pantalla (Campanita):**  
   Cuando un recepcionista crea un ticket o avanza un estado, PHP inserta una fila en la tabla `notificaciones`. El archivo `js/dashboard.js` o `js/sonidos.js` lee esta tabla cada pocos segundos y muestra el globito rojo y un sonido de campana.
2. **Notificación Web Push (Móvil y Escritorio):**  
   Basado en el estándar oficial **VAPID** (un protocolo criptográfico para que un servidor web pueda enviar mensajes directamente al sistema operativo Android, iOS o Windows sin pagar servicios de terceros).  
   - El empleado entra por primera vez y presiona la campanita: el navegador pide permiso al usuario.
   - El navegador genera una dirección secreta (`endpoint`) y claves criptográficas (`p256dh`, `auth`).
   - `api/push.php` guarda estos datos en la tabla `push_subscriptions`.
   - Cuando se asigna una laptop a un técnico, `soporte.php` llama a `sendPushToUser()`: el servidor cifra el mensaje y lo manda a Google/Apple, despertando la pantalla del teléfono del técnico con el mensaje: *"Orden ST-20260909-002 te fue asignada"*.
3. **Simulador de Alarmas Automáticas (Garantías por vencer):**  
   Como los hostings compartidos a menudo no permiten ejecutar tareas en segundo plano (llamadas *Cron Jobs*), cada vez que un administrador hace cualquier clic en la campanita, `api/notificaciones.php` revisa si hay garantías enviadas a proveedores que lleven más de 10 días sin resolverse y genera alertas automáticas para que nadie olvide reclamarlas.

---

## 6. Sección de "Cosas Frágiles" (Advertencias para No Romper Nada)

Esta es la sección más importante para el mantenimiento a largo plazo. Son cosas que **no dan error visual inmediato**, pero si se tocan sin cuidado desarmarán partes del sistema:

### ⚠️ 1. Nombres exactos de archivos `.html` en los roles
- **Por qué es frágil:** La base de datos no valida si el texto escrito en `modulos_permitidos` es un archivo real. Si alguien agrega un módulo y escribe `reporte.html` en vez de `reportes.html`, el botón desaparecerá sin dejar ningún mensaje de error en pantalla.

### ⚠️ 2. El formato de los códigos correlativos
- **Por qué es frágil:** Diversos módulos dependen de que los tickets comiencen estrictamente con:
  - `ST-` para recepción normal de clientes.
  - `TAR-` para tareas de taller.
  - `ST-INT-` para soporte interno.
  - `LOT-` para lotes masivos.
  - `GAR-` para garantías de proveedores.  
  Si un programador cambia el formato a `TICKET-001`, las búsquedas rápidas, los reportes de desempeño y la consulta pública dejarán de encontrar las reparaciones.

### ⚠️ 3. Los tipos de dato en las consultas preparadas (`bind_param`)
- **Por qué es frágil:** Toda la API fue saneada para usar sentencias preparadas de MySQLi. Cada parámetro requiere indicar su tipo:
  - `"s"` para texto y fechas (`string`).
  - `"i"` para números enteros y claves de identificación (`integer`).  
  Si en el futuro se añade una consulta y se colocan 3 letras `"sss"` pero se pasan 4 variables, PHP arrojará un error fatal y la pantalla se quedará en blanco.

### ⚠️ 4. La exclusión del archivo `api/config.php`
- **Por qué es frágil:** `api/config.php` contiene la contraseña maestra de la base de datos y fue retirado de Git mediante `.gitignore` para evitar filtraciones de seguridad.  
  Si alguien clona este proyecto en una computadora nueva, **la web no abrirá** hasta que cree manualmente `website_files/api/config.php` con los datos de conexión correspondientes.

### ⚠️ 5. Formato de columnas al importar Excel (`api/importar.php`)
- **Por qué es frágil:** El importador busca nombres de columnas insensibles a mayúsculas, pero requiere que las columnas estándar (`codigo`, `serie`, `marca`, `modelo`, etc.) coincidan con la lista permitida `$allowed_columns`. Si un proveedor entrega un Excel con una columna llamada `NRO_SERIE` en vez de `serie`, esa información se omitirá en la importación.

---

## 7. Glosario Rápido para No Técnicos

- **API (*Application Programming Interface*):** Es el mensajero que lleva las órdenes desde la pantalla del usuario hasta la base de datos y trae de vuelta las respuestas.
- **Backend:** Todo lo que ocurre "detrás de escena" en el servidor (cálculos, contraseñas, base de datos) y que el usuario común no puede ver ni manipular.
- **Frontend:** La parte visual de la página web que se dibuja en la pantalla (botones, colores, tablas, formularios).
- **JSON (*JavaScript Object Notation*):** Formato de texto universal utilizado para transportar datos entre el frontend y el backend en forma de paquetes ordenados.
- **MySQLi / Sentencias Preparadas (*Prepared Statements*):** Técnica de seguridad donde el servidor separa la orden SQL de los datos del usuario, haciendo matemáticamente imposible que un hacker inyecte código malicioso.
- **VAPID (*Voluntary Application Server Identification*):** Estándar de seguridad que permite enviar alertas a celulares sin depender de intermediarios de pago.
