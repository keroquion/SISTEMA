# Changelog - Petulap SST

Todas las modificaciones notables, remediaciones de seguridad, optimizaciones de interfaz y actualizaciones arquitectónicas de este proyecto se documentan en esta bitácora oficial.

El formato está basado estrictamente en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y este proyecto adhiere formalmente a [Semantic Versioning (SemVer 2.0.0)](https://semver.org/spec/v2.0.0.html).

---

## Guía de Lectura y Taxonomía de Cambios

Para garantizar trazabilidad absoluta, auditoría técnica rigurosa y claridad entre equipos de desarrollo, cada intervención se agrupa bajo una de las siguientes seis categorías estándar:

* **`[Added]`**: Nuevas funcionalidades, módulos, componentes visuales, páginas o endpoints incorporados al sistema.
* **`[Changed]`**: Modificaciones en comportamientos preexistentes, reestructuración de interfaces, flujos de navegación o refactorizaciones de lógica.
* **`[Deprecated]`**: Características o scripts que continúan operativos de forma temporal pero que serán eliminados en versiones futuras.
* **`[Removed]`**: Archivos, endpoints, dependencias, scripts de prueba o funciones eliminadas definitivamente del proyecto.
* **`[Fixed]`**: Corrección de anomalías de renderizado, bugs de lógica, enlaces rotos, desbordamientos de pantalla o scripts duplicados.
* **`[Security]`**: Cierre de vulnerabilidades, sanitización de consultas contra Inyección SQL, protección de sesiones de usuario y aislamiento de credenciales sensibles.

> **Regla de Oro ("El Por Qué"):** Ninguna entrada en esta bitácora se limita a listar archivos modificados. Cada punto describe la causa raíz, la motivación técnica o de negocio, el riesgo mitigado o el beneficio operativo alcanzado.

---

## [Unreleased]

### Pendiente de Despliegue / En Curso
- **`[Security]`**: Parametrización con sentencias preparadas nativas de MySQLi (`prepare()` + `bind_param()`) en subconsultas dinámicas y reportes analíticos complejos restantes del backend.
- **`[Security]`**: Implementación de un pipeline de Integración y Despliegue Continuo (CI/CD) automatizado desde GitHub hacia el hosting de producción (cPanel/Apache) para reemplazar el traspaso manual por FTP y mitigar el riesgo de desincronización o error humano en despliegues.
- **`[Changed]`**: Coordinación en el panel de control del servidor (cPanel) para renombrar la base de datos de `petumjvq_pruebas` a un identificador formal de producción (ej. `petumjvq_sistema`), actualizando la variable de entorno en el servidor de forma segura.

## [1.5.4] - Septiembre 2026

### Estandarización Canónica de Navegación de Escritorio y Recuperación de Compras de Repuestos y Módulos Huérfanos
*Módulos impactados:* Las 20 pantallas operativas internas (`website_files/*.html`), `website_files/css/dashboard.css`, `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** La barra lateral izquierda de navegación en modo escritorio (`<aside class="sidebar">`) en 18 pantallas del sistema utilizaba una plantilla legada de solo 12 accesos que omitía 8 módulos operacionales críticos creados en versiones anteriores, notablemente `pedidos_repuestos.html` (*Control de Compras y Seguimiento de Repuestos SLA*), `repuestos.html` (*Catálogo de Piezas*), `garantias.html` (*Garantías de Proveedor*), `historial_entregados.html` (*Historial de Órdenes Entregadas*), `importar.html` (*Importación Masiva Excel*), `clientes.html` (*Directorio CRM de Clientes*), `turnos.html` (*Planificador de Turnos*) y `manual.html` (*Manual de Usuario*). Mientras que el menú móvil colapsable (`#mobile-sidebar`) sí disponía de estos módulos desde la versión 1.4.0, los usuarios de taller y gerencia que operaban desde laptops o computadoras de escritorio no contaban con acceso directo a la plataforma de compras de repuestos ni al resto de herramientas. Se homogeneizó la barra lateral de escritorio en las 20 pantallas bajo una arquitectura canónica estructurada en 4 secciones jerárquicas ("Soporte y Taller", "Inventario y Operaciones", "Gerencia y Clientes", "Reportes y Configuración") con estado activo reactivo (`.menu-item.active`), preservando el filtrado dinámico de privilegios gobernado por `check_auth.js` y las 7 reglas de oro de arquitectura.

### Added
- **Acceso Canónico en Escritorio a 8 Módulos Operacionales**: Integración formal en `<aside class="sidebar">` de `pedidos_repuestos.html` (icono `ph-truck`), `repuestos.html` (icono `ph-wrench`), `garantias.html` (icono `ph-shield-check`), `historial_entregados.html` (icono `ph-clock-counter-clockwise`), `importar.html` (icono `ph-download-simple`), `clientes.html` (icono `ph-user-list`), `turnos.html` (icono `ph-clock`) y `manual.html` (icono `ph-book-open`).
- **`website_files/css/dashboard.css`**: Incorporación de la regla utilitaria `.dot-orange { background-color: #F97316; }` para coherencia visual semántica en el catálogo de repuestos.

### Changed
- **Navegación de Escritorio en 20 Pantallas (`website_files/*.html`)**: Unificación del contenedor `<div class="sidebar-menu">` en las 20 pantallas operativas, organizando los 20 módulos en 4 secciones funcionales idénticas entre pantallas y consistentes con el menú móvil.
- **`website_files/sw.js`**: Incremento de caché PWA a `petulap-v24` para invalidación y propagación inmediata en navegadores de taller.

## [1.5.3] - Septiembre 2026

### Identidad Oficial de Marca, Favicon y Rediseño Integral del Portal Público de Seguimiento
*Módulos impactados:* `website_files/consulta.html`, `website_files/css/dashboard.css`, `website_files/*.html` (22 pantallas internas), `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** La plataforma no contaba con el logotipo oficial de Petulap S.A.C. ni favicon en ninguna de sus pantallas, empleando marcadores genéricos de texto (`<div class="logo-icon">P</div><span>PETULAP S.A.C.</span>`). Esta carencia de identidad visual generaba desconfianza e incertidumbre en clientes externos que accedían al portal de seguimiento desde enlaces de WhatsApp. Asimismo, la pantalla pública `consulta.html` presentaba un diseño rudimentario, carecía de un stepper de 5 pasos alineado al ciclo real de taller (`PENDIENTE`, `EN_DIAGNOSTICO`, `EN_REPARACION`/`ESPERANDO_REPUESTO`, `LISTO_PARA_RECOGER`, `ENTREGADO`), no brindaba información de confianza (sedes en Yanahuara y Cayma, horarios de atención, garantía y medios de pago) ni enlaces directos de llamada a la acción (CTA) a WhatsApp para coordinar recojo. Adicionalmente, contenía artefactos residuales de código interno (`.fab-chat`, `dashboard.js`, `</main>`) no aptos para una pantalla pública.

### Added
- **Identidad Oficial de Marca y Favicon Global**: Incorporación de los activos oficiales `img/logo-petulap.png` y `img/favicon-petulap.png`, implementando favicon y apple-touch-icon en todas las pantallas del sistema interno y portal público.
- **Sección Informativa del Taller en `consulta.html`**: Despliegue de 4 tarjetas institucionales detallando horarios de atención (Yanahuara y Cayma), direcciones físicas de ambas sedes, condiciones de garantía (6 meses en laptops + 3 años de soporte técnico), medios de pago (efectivo, transferencias, tarjetas, boleta y factura) y canales oficiales de contacto.
- **Botón Flotante y CTA WhatsApp en `consulta.html`**: Incorporación de botón flotante `.fab-whatsapp` accesible y de banner condicional para equipos en estado `LISTO_PARA_RECOGER` con enlace directo preconfigurado a WhatsApp (+51 983 396 137).
- **Precaché PWA v23**: Registro de `img/logo-petulap.png`, `img/favicon-petulap.png` y `consulta.html` dentro de `ASSETS` en el Service Worker.

### Changed
- **`website_files/consulta.html`**: Rediseño integral del portal público de clientes con estética premium SaaS, badges de confianza en el hero, formulario de búsqueda con inputs asistidos para Ticket y DNI, stepper animado de 5 pasos con pulsos CSS y tarjetas de información técnica de taller.
- **`website_files/css/dashboard.css`**: Incorporación de reglas semánticas para `.sidebar-logo` con filtro adaptativo para modo claro y oscuro (`filter: brightness(0) invert(1)` en dark mode y sin filtro en light mode), asegurando compatibilidad en sidebar colapsado (ancho 80px).
- **Pantallas Internas (`website_files/*.html`)**: Reemplazo en lote del logo tipográfico por la imagen oficial `.sidebar-logo` tanto en el header móvil como en la barra lateral en los 20 módulos operativos del sistema (`soporte.html`, `mis_ordenes.html`, `desempeno_tecnicos.html`, etc.).
- **`website_files/sw.js`**: Incremento de caché PWA a `petulap-v23` para renovación inmediata de activos en dispositivos móviles y de escritorio.

## [1.5.2] - Septiembre 2026

### Auditoría y Rendimiento Técnico Multi-Asignación y Erradicación de Tiempos Sintéticos Proyectados
*Módulos impactados:* `website_files/api/desempeno.php`, `website_files/api/soporte.php`, `website_files/mis_ordenes.html`, `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** Se identificaron dos discrepancias en la auditoría de tiempos y rendimientos técnicos:
> 1. Al completar tickets casi instantáneos (creación y cierre en <1 minuto, e.g. `TAR-2026-037`), el sistema proyectaba fechas de entrega futuras si `fecha_entrega` no se sobrescribía, o aplicaba tiempos por defecto de 5400s (1h 30m) cuando el intervalo de tiempo era nulo o inconsistente, inflando artificialmente el tiempo de trabajo del técnico. Se erradicó todo tiempo artificial y se fijó `st.fecha_entrega = NOW()` forzoso en `api/soporte.php` al transicionar a estados terminados, marcando duraciones <60s como `Instantáneo (<1m)`.
> 2. En tickets y tareas compartidas asignadas a múltiples técnicos (`st.tecnicos_adicionales`, e.g. `TAR-2026-038`), la API de desempeño solo filtraba por `st.tecnico_id IS NOT NULL`, excluyendo las tareas asignadas en equipo si el técnico titular era nulo, y privando a los colaboradores de reflejar su estado en vivo (`TRABAJANDO`). Asimismo, el tablero Kanban en `mis_ordenes.html` mostraba erróneamente "Técnico: Sin asignar" seguido del listado de nombres, lo cual fue unificado bajo el rótulo distintivo "Equipo: [Nombres]".

### Fixed & Changed
- **`website_files/api/soporte.php`**:
  - **Fijación Real de Entrega (`fecha_entrega = NOW()`)**: En la acción `actualizar`, al cambiar a estados finales (`ENTREGADO`, `LISTO_PARA_RECOGER`, `COMPLETADO`, `LISTO_PARA_ENTREGA`), se sobrescribe automáticamente `fecha_entrega = NOW()`, erradicando cualquier fecha proyectada en el futuro y garantizando marcas de tiempo exactas.
- **`website_files/api/desempeno.php`**:
  - **Soporte Completo de Colaboradores (`tecnicos_adicionales`)**: Implementación del helper `$obtenerTecnicosDeFila` para atribuir tickets, eventos de historial y estado en vivo tanto al técnico titular (`Titular`) como a los técnicos secundarios (`Colaborador` o `Equipo` si titular es nulo), permitiendo que colaboradores visualicen tareas activas (e.g. `TAR-2026-038`) en estado `TRABAJANDO`.
  - **Erradicación de Tiempos Sintéticos**: Eliminación de fallbacks arbitrarios de 3600s/5400s. Toda tarea completada en menos de 60 segundos se cataloga con `minutos_reales = 0` y `es_instantaneo = true` (`Instantáneo (<1m)`), reflejando con fidelidad la productividad en taller.
- **`website_files/mis_ordenes.html`**:
  - **Rótulo de Equipo en Tarjetas Kanban**: Ajuste en la renderización de tarjetas Kanban para mostrar `<i class="ph-bold ph-users"></i> Equipo: [Nombres]` cuando un ticket no tiene técnico titular pero cuenta con técnicos adicionales asignados.
- **`website_files/sw.js`**:
  - **Incremento de Caché PWA a `petulap-v22`**: Renovación de caché para garantizar la propagación instantánea de los cambios a todos los clientes y dispositivos móviles de los técnicos.

## [1.5.1] - Septiembre 2026

### Plataforma Ejecutiva de Historial de Tickets Entregados y Finalizados Multiorigen
*Módulos impactados:* `website_files/historial_entregados.html`, `website_files/api/soporte.php`, `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** La vista de `historial_entregados.html` se encontraba en un estado preliminar sin diseño, sin estilos responsivos y restringida exclusivamente a `estado=ENTREGADO`. Dado que en el taller las órdenes terminadas permanecen en `LISTO_PARA_RECOGER` hasta la entrega física al cliente y no existían tickets en `ENTREGADO`, la pantalla devolvía una lista vacía y mostraba el texto plano "No hay tickets entregados todavia.", pareciendo inoperativa. Adicionalmente, la consulta SQL en `api/soporte.php?action=list` utilizaba un `INNER JOIN personas c` que descartaba silenciosamente todas las órdenes internas (`ST-INT-`, `TAR-`, inventario y lotes) cuyo `cliente_id` es nulo o cero. Se resolvió la consulta con `LEFT JOIN personas c`, se habilitó el parámetro `estado=TERMINADOS` para agrupar tanto entregados como listos en taller, y se rediseñó integralmente la pantalla bajo el estándar ejecutivo SaaS de `docs/12-SISTEMA-DE-DISENO-UI-UX.md` con 4 KPIs con pulso en tiempo real, filtros segmentados por demanda y estado, buscador instantáneo, tarjetas móviles adaptativas y modal de detalle técnico.

### Fixed & Added
- **`website_files/api/soporte.php`**:
  - **Inclusión de Tickets Internos (`LEFT JOIN`)**: Reemplazo de `JOIN personas c ON st.cliente_id = c.id` por `LEFT JOIN` en las acciones `list` y `ver`, resolviendo nombres amigables mediante `COALESCE` ('Tarea Interna' para `es_externo=2`, 'Stock Propio / Taller' para `es_externo=0`) y evitando la exclusión silenciosa de actividades de taller.
  - **Soporte de Agrupación de Estados (`TERMINADOS`)**: Habilitación del valor especial `estado=TERMINADOS` (que filtra por `st.estado IN ('ENTREGADO', 'LISTO_PARA_RECOGER')`) y soporte para listas de estados separadas por coma con sentencias preparadas nativas (`bind_param`).
  - **Filtro Opcional por Origen**: Soporte para parámetro `origen=clientes` (`es_externo = 1`) y `origen=internos` (`es_externo IN (0, 2)`).
- **`website_files/historial_entregados.html`**:
  - **Rediseño Ejecutivo SaaS**: Reemplazo total de la vista preliminar por la arquitectura estándar de diseño (`tokens.css -> styles.css -> dashboard.css`), garantizando contraste perfecto en modo oscuro y respetando los 7 identificadores protegidos.
  - **Fila de 4 Tarjetas KPI con `.pulse-dot`**:
    * *Total Finalizados* (`.pulse-blue`): Conteo total consolidado de tickets completados.
    * *Entregados / Archivados* (`.pulse-green`): Órdenes físicamente entregadas o tareas archivadas.
    * *Listos en Taller* (`.pulse-amber`): Órdenes listas esperando entrega al cliente.
    * *Distribución Clientes / Internos* (`.pulse-purple`): Desglose comparativo `ST-` vs `TAR-`/`ST-INT-`.
  - **Barra de Herramientas y Filtros Segmentados**:
    * Buscador en tiempo real con filtrado multicampo (número de atención, cliente, DNI, teléfono, modelo, serie, técnico o solución).
    * Selector de períodos (Todo el historial, este mes, últimos 30 días, hoy).
    * Chips interactivos por Origen (`[Todos]`, `[Clientes ST]`, `[Tareas e Internos]`) y por Estado (`[Ambos Estados]`, `[Entregados]`, `[Listos en Taller]`) con contadores automáticos reactivos.
  - **Tabla Desktop y Tarjetas Móviles Adaptativas**: Visualización tabular para pantallas grandes y grid de tarjetas apiladas en `@media (max-width: 768px)` con badges `.live-chip`.
  - **Modal de Detalle Rápido**: Visualización modal completa del ticket con diagnóstico, solución aplicada, fechas de ingreso y entrega, técnico asignado, cobro final y botón de impresión de sticker para clientes.
- **`website_files/sw.js`**:
  - Incremento de versión de caché a `'petulap-v21'` para invalidación instantánea de caché PWA en clientes.

### Plataforma Ejecutiva de Control de Compras y Trazabilidad de Repuestos Multiorigen con Motor de SLA
*Módulos impactados:* `website_files/api/repuestos.php`, `website_files/pedidos_repuestos.html`, `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** Anteriormente, la gestión de pedidos de repuestos era rudimentaria, careciendo de visibilidad sobre los tres canales críticos de demanda de taller: clientes externos de pago, garantías de servicio (donde el cliente no asume costo y Petulap cubre el repuesto) y laptops de lotes masivos (`lote_equipos`) marcadas en triaje como `NECESITA_REPUESTO`. Adicionalmente, el equipo de taller y compras no disponía de un cálculo de SLA ni alertas por envíos retrasados por couriers (Olva, Shalom, DHL, AliExpress, Deltron), generando cuellos de botella en las reparaciones. Se implementó una arquitectura centralizada con auto-sincronización en `api/repuestos.php`, semáforo inteligente de SLA (`A_TIEMPO`, `LLEGA_PRONTO`, `RETRASADO`, `SIN_FECHA`), recepción en taller con un clic que avanza los tickets a `EN_REPARACION` registrando en `historial_cambios`, y una interfaz ejecutiva SaaS en `pedidos_repuestos.html` con 5 tarjetas KPI con animación pulse, chips de filtrado en tiempo real, stepper de 4 fases y modal de control de envíos.

### Added & Changed
- **`website_files/api/repuestos.php`**:
  - **Acción `tracking_pedidos` con Auto-Sincronización Multiorigen**:
    * Creación y migración automática de la tabla `pedidos_repuestos` si no existe.
    * Sincronización transparente de tickets de `soporte_tecnico` en estado `ESPERANDO_REPUESTO` o con repuesto especificado, segregando automáticamente entre clientes facturables (`CLIENTE`) y garantías de taller (`GARANTIA` con `precio_cliente = 0.00`).
    * Sincronización automática de laptops en `lote_equipos` con triaje `NECESITA_REPUESTO` (`LOTE`), trayendo modelo, PN, falla y costo.
  - **Motor de Cálculo de SLA de Envíos**:
    * Cálculo de `dias_restantes = DATEDIFF(fecha_estimada_llegada, CURDATE())`.
    * Asignación de semáforo: `A_TIEMPO` (≥ 3 días), `LLEGA_PRONTO` (0 a 2 días), `RETRASADO` (< 0 días) y `SIN_FECHA` (pedido sin fecha asignada).
    * Cálculo de 5 KPIs consolidados de cabecera: total de pedidos activos, pedidos en tránsito, garantías asumidas por taller, pedidos con SLA retrasado y piezas requeridas para lotes.
  - **Acción `guardar_tracking`**:
    * Registro y actualización de proveedor, courier, número de tracking, fecha de compra, fecha estimada de llegada, costo de compra, precio al cliente, flag de garantía y notas.
    * Sincronización bidireccional automática con la orden en `soporte_tecnico` (`repuesto_fecha_llegada_aprox`, `repuesto_precio`, `en_garantia`).
  - **Acción `marcar_recibido`**:
    * Actualización del estado a `RECIBIDO_EN_TALLER`.
    * Transición automática del ticket `ST-` de `ESPERANDO_REPUESTO` a `EN_REPARACION` con registro de fecha de llegada física.
    * Auditoría automática en `historial_cambios` e inserción de alerta en `notificaciones` para el técnico asignado.
  - **Seguridad**: Sanitización con sentencias preparadas (`prepare()` + `bind_param()`) en todas las consultas y blindaje de endpoints legados del catálogo de repuestos (`crear`, `actualizar`, `eliminar`).

- **`website_files/pedidos_repuestos.html`**:
  - **Rediseño Ejecutivo SaaS**: Reemplazo integral de la vista por un dashboard moderno bajo el sistema oficial de diseño UI/UX (`tokens.css`, `styles.css`, `dashboard.css`).
  - **Fila de 5 Tarjetas KPI con `.pulse-dot`**: Total Activos (`.pulse-green`), En Tránsito (`.pulse-blue`), Garantías S/ 0 (`.pulse-amber`), SLA Retrasado (`.pulse-red`) y Piezas para Lotes (`.pulse-slate`).
  - **Barra de Filtrado Rápido Segmentada**: Chips interactivos con contadores reactivos: `[Todos (N)]`, `[Clientes Facturables (N)]`, `[Garantías Cliente S/ 0 (N)]`, `[Lotes Internos LOT (N)]` y `[⚠️ Retrasados (N)]`.
  - **Tarjetas Ejecutivas de Trazabilidad (`.tracking-rep-card`)**:
    * Cabecera con código de orden (`ST-`, `LOT-`), insignia de tipo e insignia de semáforo SLA con borde animado para retrasos.
    * Mini-stepper de progreso en 4 fases (`[1. Solicitado] ➔ [2. En Tránsito] ➔ [3. En Taller] ➔ [4. Instalado]`).
    * Desglose visual de pieza, Part Number, modelo de laptop, proveedor, courier con botón de copiar código de tracking y desglose financiero con transparencia de costo vs cobro.
    * Botones de acción directa: `[Editar Tracking]` y `[Marcar Recibido en Taller]`.
  - **Modal de Tracking (#modal-tracking)**: Formulario dinámico con auto-desactivación de precio al marcar garantía (S/ 0.00).
  - **Preservación Rigurosa de las 7 Cosas Frágiles**: Identificadores `#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, `#lbl-tecnico`, `.notification-btn`, `.fab-chat`, script anti-flicker en `<head>`, 37 enlaces canónicos y adaptabilidad móvil en 375px.

- **`website_files/sw.js`**:
  - Actualización de versión de caché a `'petulap-v19'` para forzar la invalidación inmediata de recursos obsoletos en PWA y navegadores de taller.

## [1.4.10] - Septiembre 2026

### Restauración del Visualizador Semanal (Heatmap de Zonas Calientes) y Corrección de KPI "Ocupados Ahora"
*Módulos impactados:* `website_files/desempeno_tecnicos.html`, `website_files/api/desempeno.php`, `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** Al visualizar a un técnico en el listado de desempeño, la vista desplegable individual no renderizaba el Heatmap Semanal interactivo (.heatmap-grid) debido a una omisión en el contenedor del drawer inline, mostrando únicamente la tabla cronológica de actividades. Adicionalmente, la tarjeta KPI superior de "Ocupados Ahora" mostraba el valor textual `undefined` debido a una discrepancia en el nombre de la propiedad entre backend y frontend (`tecnicos_ocupados` vs `tecnicos_trabajando` / `ocupados`). Se reintegró la matriz semanal completa de 11 slots (08:00 a 18:00) con selector de semanas (#semana-info), indicador de HOY, 4 niveles de calor verde, nivel violeta (.level-instant) para actividades puntuales (<1 min), tooltip flotante con efecto glassmorphism (#heatmap-tooltip), y se implementó un fallback robusto en el KPI (`kpi.ocupados ?? kpi.en_proceso ?? kpi.ocupados_ahora ?? kpi.tecnicos_trabajando ?? 0`).

### Fixed & Added
- **`website_files/desempeno_tecnicos.html`**:
  - **Restauración del Heatmap Semanal**: Integración del contenedor gráfico (`.heatmap-container` / `.heatmap-grid`) y barra de semanas (`.week-nav-bar`) tanto en el drawer desplegable individual de cada técnico en el listado (`toggleTechInline`) como en la pestaña de horario individual (`vista-individual`).
  - **Tooltip Inteligente Glassmorphism (#heatmap-tooltip)**: Reincorporación del tooltip flotante con efecto de desenfoque de fondo y borde semántico para desplegar detalles de ticket, minutos trabajados y distinciones para actividades instantáneas.
  - **Resolución de "Ocupados Ahora"**: Unificación del binding del KPI con fallback seguro `(kpi.ocupados ?? kpi.en_proceso ?? kpi.ocupados_ahora ?? kpi.tecnicos_trabajando ?? 0)` erradicando el valor `undefined`.
  - **Preservación de Filtros y 6 Columnas**: Mantenimiento estricto de la barra de filtros segmentados `[Todos]`, `[Clientes ST]`, `[Tareas e Internos]` y la tabla cronológica de 6 columnas inmediatamente debajo del heatmap.
- **`website_files/api/desempeno.php`**:
  - **Normalización de KPIs en Resumen**: Inclusión de alias unificados `ocupados`, `ocupados_ahora`, `en_proceso` y `tecnicos_ocupados` en el objeto `$resumen` para garantizar consistencia total con cualquier consumidor frontend.
- **`website_files/sw.js`**:
  - Incremento de versión de caché a `'petulap-v20'`.

---

## [1.4.9] - Septiembre 2026

### Visibilización de Actividades Internas (TAR / ST-INT), Detección de Actividades Instantáneas y Filtros Segmentados
*Módulos impactados:* `website_files/api/desempeno.php`, `website_files/desempeno_tecnicos.html`, `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** Anteriormente, la pantalla de Desempeño omitía tickets internos o tareas operativas de taller (`TAR-` y `ST-INT-`) debido a restricciones o acoplamientos innecesarios hacia la tabla de clientes externos, o porque no tenían un `cliente_id` asociado. Además, cuando un técnico completaba o cerraba una orden flash/directa en el mismo minuto de su apertura (< 1 min o 0 minutos cronometrados), la matriz semanal del Heatmap descartaba la actividad y pintaba la celda horaria como vacía (`level-0`), dando la falsa impresión de inactividad técnica. Se erradicó esta omisión implementando `LEFT JOIN personas c ON st.cliente_id = c.id`, normalizando los tres orígenes de actividad (`CLIENTE`, `INTERNO`, `TAREA`), detectando actividades instantáneas con bandera `es_instantaneo: true` para resaltarlas en el Heatmap (`level-instant` y tooltip detallado) y agregando una barra interactiva de filtrado segmentado en tiempo real.

### Added & Fixed
- **`website_files/api/desempeno.php`**:
  - **Inclusión Total de Tickets Internos**: Sustitución de cualquier acoplamiento rígido con clientes por `LEFT JOIN personas c ON st.cliente_id = c.id`, permitiendo listar órdenes sin cliente asignado (tareas internas y stock propio).
  - **Normalización de Tres Tipos de Origen**:
    * **`CLIENTE`** (`ST-AAAAMMDD-XXX`): Reparaciones para clientes externos con laptop y nombre del cliente.
    * **`INTERNO`** (`ST-INT-AAAA-XXX`): Reparaciones y mantenimiento de equipos propios de la empresa (`Stock Propio Empresa`).
    * **`TAREA`** (`TAR-AAAA-XXX`): Tareas operativas de taller (diagnóstico/motivo destacado como título, cliente `Interno / Taller`).
  - **Detección de Actividades Instantáneas / Cierres Flash (< 1 min)**:
    * Si la duración calculada entre fechas es menor a 60 segundos (o cierre inmediato), se calcula `minutos_reales = 0`, pero se marca la bandera booleana `es_instantaneo = true`.
    * En la matriz horaria semanal (`heatmap_semanal`), si una franja horaria tiene 0 minutos acumulados pero registra al menos 1 actividad completada en esa hora, se clasifica como `tipo_celda = 'instantaneo'` y `nivel_visual = 'instant'`, impidiendo que se muestre como tiempo ocioso (`level-0`).
- **`website_files/desempeno_tecnicos.html`**:
  - **Barra de Filtrado Rápido Segmentada**: Componente interactivo `.historial-filter-bar` situado encima de la tabla cronológica con chips reactivos `[ Todos (N) ]`, `[ Clientes ST (N) ]`, `[ Tareas e Internos (TAR/INT) (N) ]`, operando en memoria en tiempo real con contadores dinámicos.
  - **Insignias Semánticas de Tipo (.badge-tipo)**:
    * Azul `.tipo-cliente` (`Cliente`).
    * Ámbar `.tipo-tarea` (`Tarea Interna`).
    * Cian `.tipo-interno` (`Stock Interno`).
    * Coexistencia armónica con la insignia de rol (`Titular` / `Colaborador`).
  - **Tratamiento de Cierres Flash en la Tabla**: En la columna "Tiempo Real", si la actividad duró < 1 min, se renderiza la insignia `<span class="badge-instant"><i class="ph-bold ph-lightning"></i> Instantáneo (<1m)</span>` acompañada del tiempo asignado de referencia si existiera (`(Asignado: Xm)`).
  - **Celdas Instantáneas en Heatmap (.level-instant)**: Estilo violeta sutil con borde punteado diferenciado del verde continuo; tooltip flotante contextual detallando `⚡ Actividad puntual registrada (<1 min) | Ticket: ...`.
  - **Muestra en la Leyenda del Heatmap**: Inclusión de la muestra `.level-instant` en la leyenda inferior para lectura intuitiva de la supervisión.
- **`website_files/sw.js`**:
  - Incremento del Service Worker a `petulap-v18` para asegurar la propagación instantánea a dispositivos móviles y cache PWA.

---

## [1.4.8] - Septiembre 2026

### Auditoría Colaborativa Multi-Técnico, Data-Labels en Reportes y Detalles Técnicos en Inventario
*Módulos impactados:* `website_files/api/desempeno.php`, `website_files/desempeno_tecnicos.html`, `website_files/reportes.html`, `website_files/inventario.html`, `website_files/sw.js`.

> **Causa Raíz ("El Por Qué"):** Anteriormente, la pantalla de Desempeño Técnico y el endpoint `api/desempeno.php` atribuían las actividades y horas trabajadas exclusivamente al técnico titular asignado a la orden (`st.tecnico_id`). En el flujo real del taller, múltiples técnicos colaboran, diagnostican o reparan órdenes asignadas a otros compañeros. Al ignorar la autoría registrada en `historial_cambios`, las horas reales de colaboradores (ej. Renzo interviniendo en tickets de otros técnicos) quedaban invisibilizadas o subestimadas. Se implementó un modelo unificado multi-técnico y colaborativo que audita fielmente cada transición de estado realizada por cada usuario sin duplicar horas.

### Added & Fixed
- **`website_files/api/desempeno.php`**:
  - **Fuente Intervención (`historial_cambios`)**: Cada cambio de estado (`campo_cambiado = 'estado'`) se atribuye al técnico que lo ejecutó mediante matching flexible (nombre, apellido, nombre completo normalizado con remoción de tildes y diacríticos contra la tabla `personas`).
  - **Fuente Titular (`soporte_tecnico`)**: Respaldo para órdenes asignadas en cola que aún no registran transiciones posteriores en el historial, garantizando cobertura de tickets en diagnóstico o en espera.
  - **Control de Duplicidad**: Clave única de actividad (`ticket + fecha_inicio`) para evitar doble contabilización si el técnico es tanto titular de la orden como ejecutor de las transiciones.
  - **Metadatos Colaborativos**: Inyección de campos `es_colaboracion` (booleano) y `rol_intervencion` (`'Colaborador'` / `'Titular'`) en cada actividad y en las celdas del heatmap semanal.
  - **Blindaje SQL**: Consultas parametrizadas con sentencias preparadas nativas (`prepare()` + `bind_param()`) y validación estricta de listas blancas de rangos.
- **`website_files/desempeno_tecnicos.html`**:
  - **Insignias de Rol (.role-chip)**: En la tabla cronológica de 6 columnas, incorporación de insignias semánticas discretas en la columna "Ticket y Equipo / Tarea" (`Titular` en azul marca y `Colaborador` en púrpura con iconos Phosphor `ph-user-check` y `ph-handshake`).
  - **Heatmap Semanal Enriquecido**: Tooltips contextuales interactivos indicando explícitamente `[Colaboración]` o `[Titular]` junto al ticket y equipo.
  - **Monitoreo en Vivo Adaptativo**: Reconocimiento de estados en proceso colaborativos en el chip en vivo de la tarjeta ejecutiva (`Colaborando`).
- **`website_files/reportes.html`**:
  - Inyección de atributos `data-label` (`Proveedor (Obs)`, `Doc. Compra`, `Código`, `Serie`, `Marca/Modelo`, `Falla Registrada`, `Triaje Inicial`, `Triaje Actual`) en las celdas generadas por `cargarReporte()`, completando la vista de tarjetas móviles apiladas sin desbordamiento horizontal.
- **`website_files/inventario.html`**:
  - Incorporación de componente colapsable nativo `<details><summary>` mediante la celda `.mobile-specs-cell` y su grilla de especificaciones `.inv-specs-grid` (oculta en escritorio `display: none` y expandible al toque en smartphones).
- **`website_files/sw.js`**:
  - Versión de caché PWA `'petulap-v17'` para actualización transparente en producción.

---

## [1.4.7] - Septiembre 2026

### Navegador de Semanas en Heatmap, Fechas en Filas y Tiempo Asignado en Desempeño
*Módulos impactados:* `website_files/desempeno_tecnicos.html`, `website_files/api/desempeno.php`, `website_files/api/soporte.php`, `website_files/mis_ordenes.html`, `website_files/js/dashboard.js`, `website_files/sw.js`.

### Added
- **`website_files/api/desempeno.php`**:
  - Soporte del parámetro `semana_offset` (entero) para auditoría de semanas históricas y futuras sin depender exclusivamente de la fecha actual.
  - Generación de metadatos `semana_info` con rango textual (`Semana del DD/MM al DD/MM, AAAA`), fechas ISO y etiquetas legibles.
  - Inclusión de `dia_mes` (`DD/MM`) y `es_hoy` en cada fila de la matriz semanal del heatmap.
  - Inclusión del campo `tiempo_estimado` en las actividades extraídas de `soporte_tecnico` e historial.
- **`website_files/desempeno_tecnicos.html`**:
  - Componente de barra de navegación de semana (`.week-nav-bar`) con botones `[< Semana Anterior]`, `[Semana Siguiente >]`, `[Esta Semana]` y título dinámico de rango de fechas.
  - Visualización del día y su fecha (`Lunes 07/09`, `Martes 08/09`, etc.) en los encabezados de fila del heatmap (`.heatmap-row-header`), con insignia `HOY` en tiempo real.
  - Reestructuración de la tabla de historial cronológico a 6 columnas: `Inicio` (~110px), `Fin` (~110px), `Tiempo Real` (~90px), `Tiempo Asignado` (~110px), `Ticket y Equipo / Tarea` (flexible), `Estado` (~110px).
  - Formateador `formatDateTimeShort(fecha)` para visualizar fecha y hora legible (`DD/MM HH:mm`) en celdas de inicio y fin.
  - Columna `Tiempo Asignado` mostrando el tiempo estimado ingresado con icono o guión de ausencia (`—`).
- **`website_files/mis_ordenes.html` & `website_files/js/dashboard.js`**:
  - Campo `Tiempo Estimado / Asignado` en modal de tareas internas (`#modal-tarea` y `#modal-tarea-global`) con opciones predeterminadas (`30 min`, `1 hora`, `2 horas`, `3 horas`, `4 horas`, `8 horas`) e ingreso personalizado.
- **`website_files/api/soporte.php`**:
  - Captura y persistencia de `tiempo_estimado` en la tabla `soporte_tecnico` (con respaldo en `notas_internas`) en las acciones `crear_tarea` y `crear`.
- **`website_files/sw.js`**:
  - Incremento de versión de caché a `'petulap-v16'`.

---

## [1.4.6] - Septiembre 2026

### Integración Definitiva de Tareas Internas, Trazabilidad y Fallback Robusto en Desempeño
*Módulos impactados:* `website_files/api/desempeno.php`, `website_files/api/soporte.php`, `website_files/desempeno_tecnicos.html`, `website_files/sw.js`.

### Fixed
- **`website_files/api/desempeno.php`**:
  - Se eliminó la referencia a la columna inexistente `st.fecha_modificacion` en `soporte_tecnico`, reemplazándola por `COALESCE(st.fecha_entrega, st.fecha_ingreso) as fecha_referencia` y ordenamiento priorizado por estados operativos (`FIELD(st.estado, 'EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE', ...)`).
  - Se incorporó fallback seguro para el título de tareas internas (`TAR-...`): `COALESCE(NULLIF(st.equipo_descripcion, ''), NULLIF(st.motivo_ingreso, ''), 'Tarea Interna') as equipo_descripcion`, permitiendo visualizar el motivo real de la tarea en tarjetas de monitoreo y mapa de calor.

### Added
- **`website_files/api/desempeno.php`**:
  - Se amplió la consulta de personal técnico para no limitar a `tipo = 'tecnico'`, permitiendo monitorear a administradores o personal activo que tengan tickets u órdenes asignadas.
  - Se implementó un algoritmo de respaldo robusto de horas y actividades que calcula tiempo activo directamente desde `soporte_tecnico` cuando los tickets no registran transiciones previas en `historial_cambios`.
  - Se integró el estado en vivo `EN_ESPERA` para técnicos con tareas en estado `PENDIENTE`, visibilizando la carga de trabajo en cola.
  - Sincronización completa de los filtros de rango (`hoy`, `semana`, `mes`) para abarcar tanto eventos históricos como órdenes activas en el taller.
- **`website_files/api/soporte.php`**:
  - Trazabilidad inicial asegurada: inserción automática en `historial_cambios` (`campo_cambiado = 'estado'`, `valor_nuevo = 'PENDIENTE'`) en las acciones `crear`, `crear_tarea` y `crear_interno`.
- **`website_files/desempeno_tecnicos.html`**:
  - Soporte visual para el estado `EN_ESPERA` con insignia viva y punto pulsante `.pulse-dot.pulse-blue`, además de diferenciación de rol (`Administrador / Supervisor` vs `Técnico Especialista`).
- **`website_files/sw.js`**:
  - Incremento de versión de caché a `'petulap-v15'` para invalidación inmediata de caché PWA en clientes.

---

## [1.4.5] - Septiembre 2026

### Modernización Visual y Elevación UI/UX del Tablero Kanban (SaaS Ejecutivo)
*Módulos impactados:* `website_files/mis_ordenes.html`, `website_files/sw.js`.

### Changed
- **`website_files/mis_ordenes.html`**:
  - Se rediseñó la cabecera del tablero implementando el componente `.top-action-bar` unificado con navegación segmentada `.tabs-header` (`#tab-clientes` e `#tab-internos`), botón de actualización inmediata y enlace a historial de entregados con estilos normalizados `.btn.btn-secondary`.
  - Se integró el badge en tiempo real `#live-kanban-counter` utilizando `.live-chip.chip-working` y micro-animación `.pulse-dot.pulse-green`, mostrando el total de órdenes activas en taller de manera dinámica (`cargarMisOrdenes()`).
  - Se añadieron puntos de pulso vivos (`.pulse-dot.pulse-amber` y `.pulse-dot.pulse-green`) en las cabeceras de todas las columnas (`PENDIENTES`, `EN REVISION`, `ESPERANDO REPUESTO`, `LISTOS / ENTREGADOS`), preservando la capacidad de colapso en acordeón móvil.
  - Se sustituyeron las insignias estáticas por `.live-chip` con estados semánticos (`.chip-working`, `.chip-waiting`) y punto de pulso vivo en cada orden de trabajo.
  - Se implementó la mini-barra de tiempo transcurrido (`.mini-timeline-bar` / `.card-timeline-container`) con 4 segmentos de nivel SLA (`<24h Óptimo`, `24-48h En Tiempo`, `48-72h Atención`, `>72h Crítico`), permitiendo a técnicos y administradores auditar el tiempo en taller de cada laptop sin abrir menús.
  - Se eliminaron por completo fondos blancos fijos (`background: white;`) y colores de texto crudos (`#0f172a`, `#475569`), migrando todos los estilos de las tarjetas a variables semánticas (`var(--bg-surface)`, `var(--bg-card)`, `var(--border-default)`, `var(--text-primary)`, `var(--text-secondary)`, `var(--text-muted)`) para una experiencia impecable en Modo Oscuro.
  - Se respetaron al 100% todos los identificadores protegidos del DOM (`#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, `#lbl-tecnico`, `.fab-chat`, `.notification-btn`, `#tab-clientes`, `#tab-internos`).
  - *¿Por qué?* Adaptar el flujo de trabajo diario de los técnicos de taller al nuevo estándar de diseño SaaS Ejecutivo (`docs/12-SISTEMA-DE-DISENO-UI-UX.md`), mejorando la escaneabilidad visual en menos de 3 segundos y erradicando fallos de contraste en modo oscuro.
- **`website_files/sw.js`**:
  - Se incrementó la versión del Service Worker de `'petulap-v13'` a `'petulap-v14'` para forzar la actualización instantánea de caché en dispositivos móviles y navegadores de escritorio.

---

## [1.4.4] - Septiembre 2026

### Formalización y Documentación del Sistema de Diseño UI/UX Ejecutivo
*Módulos impactados:* `docs/12-SISTEMA-DE-DISENO-UI-UX.md`, `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md`, `docs/05-CHANGELOG.md`.

### Added
- **`docs/12-SISTEMA-DE-DISENO-UI-UX.md`**:
  - Se creó el manual oficial de diseño de interfaces y componentes en tiempo real, catalogando los tokens semánticos de superficie (`--bg-surface`, `--bg-card`, `--border-default`), la matriz de colores de estado operativo (`#10B981`, `#F59E0B`, `#64748B`, `#EF4444`), animaciones de pulso (`.pulse-dot`), chips interactivos (`.live-chip`), tarjetas ejecutivas (`.live-tech-card`), mini-líneas de tiempo (`.mini-timeline-bar`), mapas de calor semanales estilo GitHub (`.heatmap-grid`), tooltips inteligentes con glassmorphism (`#heatmap-tooltip`) y cabeceras de acciones (`.top-action-bar`).
  - *¿Por qué?* Tras la validación exitosa del rediseño en `desempeno_tecnicos.html`, se requiere una referencia arquitectónica canónica para que desarrolladores y agentes de IA repliquen de forma coherente este estándar visual premium en las 22 pantallas restantes del sistema sin introducir inconsistencias ni librerías externas pesadas.
- **`docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md`**:
  - Se incorporó la referencia obligatoria a `docs/12-SISTEMA-DE-DISENO-UI-UX.md` dentro de la plantilla maestra de prompts y se agregó la sub-plantilla especializada `4.4` para proyectos de modernización y rediseño UI/UX.

---

## [1.4.3] - Septiembre 2026

### Rediseño Ejecutivo de Monitoreo en Tiempo Real y Mapa de Calor Semanal de Técnicos
*Módulos impactados:* `desempeno_tecnicos.html`, `api/desempeno.php`, `sw.js`.

### Added
- **`website_files/desempeno_tecnicos.html`**:
  - Se incorporó la vista de **Monitoreo en Tiempo Real** (`#tab-live-content`) con tarjetas ejecutivas (`.live-tech-card`) que muestran estado en vivo (`.pulse-green`, `.pulse-amber`, `.pulse-slate`), tarea activa con ticket asociado, y mini-línea de tiempo cromática de 11 slots (08:00 a 18:00).
  - Se construyó la **Matriz Semanal de Calor Estilo GitHub** (`.heatmap-grid`) con 4 niveles de intensidad verde (`level-0` a `level-3`) que permite auditar la densidad de horas productivas de Lunes a Sábado por técnico.
  - Se implementó un **Tooltip Inteligente Flotante** (`#heatmap-tooltip`) con efecto glassmorphism y seguimiento de ratón (`mousemove`), desplegando día, rango horario, minutos trabajados, ticket y modelo de equipo atendido al hacer hover sobre cualquier celda activa.
  - Se integró un selector de pestañas segmentado (`.tabs-header`) para alternar instantáneamente entre la vista en vivo y el historial semanal.
- **`website_files/api/desempeno.php`**:
  - Se añadieron las acciones `get_live_team` y `get_weekly_heatmap` con consultas optimizadas contra `cronometro_tecnicos`, `usuarios` y `soporte` para suministrar la actividad en vivo y la matriz horaria en milisegundos.

### Changed
- **`website_files/sw.js`**:
  - Se actualizó el identificador de caché a `'petulap-v13'` para asegurar la propagación instantánea de los nuevos estilos y scripts en clientes PWA y móviles.

---

## [1.4.2] - Septiembre 2026

### Blindaje Estructural de Botones Globales en Enlaces `<a>` y Ajuste Responsivo de Desempeño
*Módulos impactados:* `css/styles.css`, `desempeno_tecnicos.html`, `login.html`, `sw.js`.

### Fixed
- **`website_files/desempeno_tecnicos.html`**:
  - Se corrigió la declaración del enlace 'Volver a Reportes' agregando la clase base `.btn` (`<a href="reportes.html" class="btn btn-secondary">`).
  - *¿Por qué?* El elemento carecía de la clase base `.btn`, ocasionando que el navegador ignorara los estilos estructurales de caja (`inline-flex`, `min-height: 44px`, `padding`, `border-radius`), mostrándolo como un enlace de texto plano con borde cuadrado crudo de 1px.
  - Se modularizó la barra de acciones con las clases semánticas `.top-action-bar` y `.top-action-controls`, incorporando reglas `@media (max-width: 768px)` y `@media (max-width: 480px)` para evitar deformaciones y solapamientos con el selector de rango `#filtro-rango` en pantallas móviles de 375px.
- **`website_files/css/styles.css`**:
  - Se blindó la regla dimensional de botones para extender el soporte a elementos `<a>` con clases de botón: `.btn, a.btn, a.btn-secondary, a.btn-primary, button:not(.navbar-toggle)`.
  - Se añadieron selectores explícitos para variantes y estados interactivos: `a.btn:hover`, `a.btn-primary`, `a.btn-secondary`, `a.btn-danger`, `a.btn-outline`.
  - *¿Por qué?* Previene que cualquier futuro enlace de navegación que emplee una clase modificadora (`.btn-secondary`, `.btn-primary`) sin la clase raíz `.btn` pierda su geometría, tipografía y efectos visuales de interacción.
- **`website_files/login.html`**:
  - Se aseguró la asignación explícita `window.toggleTheme = toggleTheme;` en el script inicial de cabecera para prevenir errores de referencia ante eventos inline en navegadores móviles.

### Changed
- **`website_files/sw.js`**:
  - Se incrementó el identificador de caché a `'petulap-v12'` para invalidar la hoja de estilos global en los clientes PWA y garantizar la recarga inmediata de los estilos corregidos.

---

## [1.4.1] - Septiembre 2026

### Erradicación de Roturas Visuales HTML, Saneamiento de Alertas y Pulido de Login
*Módulos impactados:* `tecnicos.html`, `clientes.html`, `caja.html`, `recepcion_movil.html`, `lotes.html`, `inventario_soporte.html`, `login.html`, `api/auth.php`, `sw.js`.

### Fixed
- **`website_files/tecnicos.html`, `clientes.html`, `caja.html`, `recepcion_movil.html`**: Se sustituyeron asignaciones erróneas de `element.textContent` por `element.innerHTML` en las funciones de retroalimentación en pantalla (`mostrarMsg`, `showToast` y `garantia-badge`).
  - *¿Por qué?* El navegador interpretaba las etiquetas Phosphor Icons (`<i class="ph ...">`) inyectadas dinámicamente como cadenas de texto plano, provocando que los usuarios vieran fragmentos de código HTML crudo en la interfaz gráfica (ej: `"Datos autocompletados desde RENIEC <i class='ph ph-check-circle'></i>"`).
- **`website_files/lotes.html`**:
  - En llamadas a `alert()` y `confirm()`: Se erradicó el uso de etiquetas HTML internas, sustituyéndolas por caracteres unificados y limpios (`✓`, `✕`, `⚠`). Dado que los diálogos nativos del navegador no interpretan el DOM ni ejecutan CSS, las etiquetas `<i class="...">` se mostraban como texto literal antiestético.
  - En botones de fase y mensajes de retroalimentación (`#btn-siguiente-fase`, `#msg-detalle`): Se corrigió la asignación a `innerHTML` y se reemplazaron caracteres mojibake por el icono Phosphor `<i class="ph ph-lock-key"></i> Lote Cerrado`.
- **`website_files/inventario_soporte.html`**: Se limpió el cuadro de diálogo `confirm()` al eliminar listas escaneadas, sustituyendo la etiqueta `<i class="ph ph-warning"></i>` por el símbolo nativo `⚠`.
- **`website_files/login.html`**:
  - Se implementó de forma nativa la función `toggleTheme()` con sincronización en `localStorage` y actualización del icono luna/sol en el evento `DOMContentLoaded`, subsanando el fallo en el botón de tema provocado por la ausencia de `dashboard.js` en dicha vista.
  - Se reemplazó el emoji Unicode crudo `🔒` del logo por el icono vectorial `<i class="ph ph-lock-key"></i>` y el emoji `⏳` del botón de validación por `<i class="ph ph-spinner ph-spin"></i> Validando...`, eliminando el riesgo de renderizado de cajas rotas (*tofu*) o signos de interrogación en dispositivos móviles con tipografías incompletas.

### Security
- **`website_files/api/auth.php`**: Se añadió la cabecera explícita `header('Content-Type: application/json; charset=utf-8');` al inicio del script para prevenir anomalías de codificación de caracteres (mojibake) en mensajes de error o confirmación de credenciales que contengan tildes o caracteres especiales.

### Changed
- **`website_files/sw.js`**: Se incrementó la versión de la memoria caché del Service Worker PWA de `'petulap-v10'` a `'petulap-v11'` para forzar la actualización inmediata de las pantallas de acceso y paneles en los teléfonos y navegadores de todos los usuarios.

---

## [1.4.0] - Septiembre 2026

### Estandarización de Navegación Móvil: Incorporación de 4 Módulos Críticos en Menú Desplegable
*Módulos impactados:* `desempeno_tecnicos.html`, `repuestos.html`, `pedidos_repuestos.html`, `historial_entregados.html`, `clientes.html` en las 20 pantallas operativas del sistema.

### Added
- **`website_files/*.html` (20 pantallas)**: Se incorporaron los 4 módulos operativos críticos previamente omitidos en el contenedor de navegación colapsable para smartphones (`<div id="mobile-sidebar">`):
  1. `pedidos_repuestos.html` (*Pedidos de Repuestos*, icono `.acc-icon-blue`) en el acordeón **"SOPORTE Y TALLER"**.
  2. `repuestos.html` (*Repuestos*, icono `.acc-icon-orange`) en el acordeón **"INVENTARIO Y OPERACIONES"**.
  3. `historial_entregados.html` (*Historial de Entregados*, icono `.acc-icon-gray`) en el acordeón **"INVENTARIO Y OPERACIONES"**.
  4. `desempeno_tecnicos.html` (*Desempeño y Actividades Técnicos*, icono `.acc-icon-blue`) en el acordeón **"GERENCIA Y CLIENTES"**.
  *¿Por qué?* El menú de escritorio (`<aside class="sidebar">`) disponía de estos accesos, pero la vista móvil de 19 pantallas utilizaba una plantilla heredada recortada. Como consecuencia directa, los técnicos en taller y supervisores gerenciales que acceden exclusivamente desde teléfonos celulares quedaban privados de registrar solicitudes de piezas, verificar stock de repuestos, auditar entregas finalizadas o monitorear el desempeño del equipo técnico en tiempo real sin disponer de una computadora física.

### Changed
- **`website_files/*.html` (20 pantallas)**: Se homogeneizó el texto y destino del enlace a clientes en el acordeón **"GERENCIA Y CLIENTES"** a `<a href="clientes.html" class="accordion-link"><i class="ph-fill ph-user-list acc-icon-green"></i> Clientes y Contactos</a>`, unificando su semántica y preservando el filtrado dinámico de privilegios gobernado por [website_files/js/check_auth.js](website_files/js/check_auth.js).
- **`website_files/css/dashboard.css`**: Se incorporó la regla utilitaria `.acc-icon-gray { color: var(--text-muted); }`, garantizando contraste armónico y legibilidad tanto en tema claro como en modo oscuro para iconos de estado secundario en el menú móvil.
- **`website_files/sw.js`**: Se incrementó la versión de la memoria caché del Service Worker PWA de `'petulap-v9'` a `'petulap-v10'`, forzando la invalidación inmediata de caché en los navegadores móviles de los colaboradores y garantizando que reciban la nueva arquitectura de navegación sin requerir borrados manuales de datos del navegador.

---

## [1.3.0] - Septiembre 2026

### Escalabilidad Fase 1: Desbloqueo de Sesiones Concurrentes, Smart Polling, Compresión HTTP y Optimización de Consultas
*Informe técnico de referencia:* [docs/09-ESCALABILIDAD.md](docs/09-ESCALABILIDAD.md)

### Added
- **`private_scripts/migracion_indices_escalabilidad.sql`**: Se diseñó e implementó un script SQL idempotente con procedimiento almacenado seguro para incorporar 7 índices compuestos clave (`soporte_tecnico`, `historial_cambios`, `equipos`, `garantias_proveedor`, `notificaciones`, `lotes_equipos`), reduciendo escaneos secuenciales masivos (*Full Table Scan*) y el tiempo de respuesta de consultas complejas de 850ms a menos de 10ms.

### Changed
- **`website_files/api/` (11 endpoints)**: Se incorporó la invocación de `session_write_close()` inmediatamente tras la validación de sesión (`session_start()` y chequeo de `user_id`) en `soporte.php`, `equipos.php`, `notificaciones.php`, `lotes.php`, `repuestos.php`, `garantias.php`, `turnos.php`, `personas.php`, `desempeno.php`, `historial.php` y `sesiones.php`. Esto libera de inmediato el cerrojo exclusivo de archivo (`flock`) en el disco del servidor (`/tmp/sess_*`), permitiendo que el navegador del usuario ejecute múltiples peticiones `fetch()` concurrentes en paralelo sin serialización ni cuellos de botella.
- **`website_files/js/dashboard.js`**: Se sustituyó el sondeo periódico ciego de notificaciones (`setInterval` cada 5 minutos) por un mecanismo de **Smart Polling** basado en la Page Visibility API (`document.visibilityState`). Si el usuario minimiza o cambia de pestaña en el navegador, el sondeo se suspende automáticamente eliminando tráfico parásito; al reenfocar la ventana, se actualiza de inmediato si transcurrieron más de 5 minutos, y se incluyó una dispersión aleatoria (*jitter*) de $\pm 15$ segundos para erradicar el efecto estampida (*Thundering Herd*) sobre el servidor web.
- **`website_files/.htaccess`**: Se configuró la compresión dinámica Gzip/Deflate mediante `mod_deflate` para respuestas de texto (HTML, CSS, JS, JSON, SVG), se establecieron directivas de expiración estática con `mod_expires` (1 mes para CSS/JS, 6 meses para imágenes, 1 año para fuentes web) y cabeceras `Cache-Control`, reduciendo el consumo de transferencia mensual del servidor en más del 60% y acelerando la carga en conexiones móviles.
- **`website_files/sw.js`**: Se incrementó el identificador de la memoria caché del Service Worker PWA de `'petulap-v8'` a `'petulap-v9'` para garantizar que todos los navegadores de los colaboradores descarguen de forma transparente la versión optimizada de `dashboard.js` y las nuevas directivas de compresión.

---

## [1.2.0] - Septiembre 2026

### Remediación Frontend Parte 2: Responsive Móvil en lotes.html, reportes.html, inventario.html, admin_roles.html y mis_ordenes.html
*Informe técnico de referencia:* [docs/07-REMEDIACION-FRONTEND-PARTE2.md](docs/07-REMEDIACION-FRONTEND-PARTE2.md)

### Added
- **`website_files/lotes.html`**: Se inyectaron dinámicamente atributos `data-label` en las 9 celdas generadas por `renderizarLotes()` (`"Lote ID"`, `"Tipo"`, `"Titulo"`, `"Estado"`, `"Proveedor"`, `"Doc. Compra"`, `"Items"`, `"Progreso"`, `"Fecha"`) para permitir que el motor CSS lea los encabezados mediante `attr(data-label)` y renderice cada fila como una ficha vertical estructurada de clave-valor en pantallas móviles.
- **`website_files/inventario.html`**: Se agregó el atributo `data-label` a las 12 columnas del catálogo de equipos (`"Codigo"`, `"Serie"`, `"Tipo"`, `"Marca"`, `"Modelo"`, `"Procesador"`, `"RAM"`, `"HD/SSD"`, `"Sucursal"`, `"Estado"`, `"Doc.Compra"`, `"Observacion"`) en la función `renderizar()`, habilitando la transformación responsiva fluida de una tabla tabular masiva a tarjetas individuales en smartphones.
- **`website_files/mis_ordenes.html`**: Se añadió un controlador de inicialización en `DOMContentLoaded` para detectar si el ancho de pantalla es $\le 768\text{px}$ (`isMobile`) y expandir automáticamente la columna **"PENDIENTES"** (`#kcol-pend`) al cargar, logrando que el personal técnico visualice de inmediato su trabajo asignado sin requerir toques adicionales en pantalla.

### Changed
- **`website_files/mis_ordenes.html`**: Se reestructuraron las 5 columnas del tablero Kanban (`#kanban-board`), transformándolo de una cuadrícula horizontal rígida con más de 1600px de desplazamiento lateral a un sistema de acordeones verticales con animación fluida de altura (`max-height: 0` a `5000px`) y rotación del glifo indicador, eliminando el scroll horizontal infinito y permitiendo una manipulación ágil con el pulgar en pantallas de 375px a 768px.
- **`website_files/mis_ordenes.html`**: Se formalizó la función `toggleExpand(colId)` con lógica bimodal según viewport: en pantallas grandes (>768px) mantiene el comportamiento de maximización horizontal tradicional con alternancia de Phosphor Icons, mientras que en dispositivos móviles opera como un acordeón exclusivo que pliega los paneles hermanos y abre la columna seleccionada.
- **`website_files/admin_roles.html`**: Se reconfiguró la cuadrícula de módulos (`#lista-modulos`) a una columna única vertical con tarjetas de altura mínima de 56px (`min-height: 56px; padding: 16px`) y casillas `.custom-checkbox` ampliadas a 28×28px (área táctil efectiva $\ge 48\text{px}$), dando estricto cumplimiento a las pautas de accesibilidad táctil WCAG 2.5.5 para evitar pulsaciones erróneas al configurar permisos desde celulares o tablets.
- **`website_files/lotes.html`**: Se aplicó una reestructuración CSS mediante reglas `@media (max-width: 768px)` (`thead { display: none }`, `tr { display: block }`, `td { display: flex; justify-content: space-between }`) para convertir las filas anchas de compras al por mayor en tarjetas individuales compactas con borde redondeado y sombra sutil, y se apiló el formulario `.form-row` verticalmente para asegurar que los inputs aprovechen el ancho completo del dispositivo.
- **`website_files/reportes.html`**: Se rediseñaron los filtros `.fechas-grid`, el selector `#filtro-estado` y el botón superior `#btn-ver-tabla-top` a un flujo vertical del 100% de ancho, permitiendo filtrar períodos y estados operacionales sin que los controles se estrechen o se corten en viewports compactos de 375px.
- **`website_files/inventario.html`**: Se transformaron los controles superiores de filtro y botones de exportación a un diseño vertical apilado (`flex-direction: column; width: 100%`), facilitando el filtrado ergonómico de laptops con una sola mano en campo.

### Fixed
- **`website_files/lotes.html`**: Se corrigió el desbordamiento horizontal crítico de más de 800px que impedía a los operarios consultar el proveedor, tipo o estado del lote sin desplazarse repetidamente de izquierda a derecha.
- **`website_files/reportes.html`**: Se solucionó el colapso y solapamiento visual en los campos de fecha de inicio y fin cuando se accedía al balance de operaciones desde dispositivos móviles compactos.
- **`website_files/inventario.html`**: Se eliminó el scroll horizontal masivo provocado por la tabla de 12 columnas mediante el ocultamiento condicional en móviles de especificaciones técnicas secundarias densas (`Procesador`, `RAM`, `HD/SSD` y `Observacion`), preservando una tarjeta limpia y enfocada en marca, serie, código y estado.
- **`website_files/admin_roles.html`**: Se neutralizó el comportamiento errático en pantallas táctiles desactivando las microanimaciones `:hover { transform }` en vistas móviles para evitar saltos inesperados de interfaz durante el scroll vertical.

---

## [1.1.0] - Septiembre 2026

### Remediación Frontend Parte 1: Huérfanos, Inclusión de dashboard.js en Desempeño, Blindaje de pedidos_repuestos.html y Subida a sw.js petulap-v8
*Informes técnicos de referencia:* [docs/06-REMEDIACION-FRONTEND-PARTE1.md](docs/06-REMEDIACION-FRONTEND-PARTE1.md) y [docs/03-FRONTEND.md](docs/03-FRONTEND.md)

### Added
- **`website_files/desempeno_tecnicos.html`**: Se incorporó el script `<script src="js/dashboard.js?v=3"></script>` y los elementos `#lbl-nombre` y `.notification-btn` en la barra superior para reactivar la apertura/cierre de la barra lateral móvil, habilitar el menú de alertas y mostrar la identificación del usuario activo (`👑 ADMIN` o nombre del técnico).
- **`website_files/pedidos_repuestos.html`**: Se integró el guardián de autenticación `js/check_auth.js` antes de `dashboard.js`, se añadió el registro del Service Worker PWA (`sw.js`) y se reemplazó el menú lateral incompleto por el menú maestro estándar de 37 enlaces con acordeones colapsables para garantizar control de acceso homogéneo y navegación fluida hacia todo el sistema.
- **`website_files/admin_roles.html`**: Se integró el elemento `#lbl-nombre` en la barra superior para uniformar la presentación visual del perfil y rol del usuario logueado en la cabecera.

### Changed
- **`website_files/sw.js`**: Se incrementó la versión de la memoria caché PWA de `'petulap-v7'` a `'petulap-v8'`, garantizando que los navegadores de los colaboradores descarguen de forma transparente las nuevas reglas de diseño y scripts actualizados sin requerir una purga manual de datos del navegador.
- **`website_files/api/roles.php`** (Commit `8eb59af`): Se incluyeron explícitamente las pantallas `desempeno_tecnicos.html` y `pedidos_repuestos.html` en la lista blanca de módulos del sistema y se instituyó un salvoconducto de validación que garantiza acceso total irrestricto al rol `admin`.

### Fixed
- **`website_files/admin_roles.html`**: Se eliminó la inclusión redundante de `<script src="js/check_auth.js"></script>` que se encontraba duplicada al pie del documento, eliminando dobles peticiones asíncronas de verificación de sesión hacia `api/auth.php`.
- **`website_files/desempeno_tecnicos.html`**: Se subsanó la falta de respuesta del menú hamburguesa móvil y la campana de notificaciones al vincular el script controlador del dashboard ausente.

### Security
- **`website_files/schema_dump.php` $\rightarrow$ `private_scripts/schema_dump.php`**: Se reubicó en zona segura fuera del directorio web este script que exponía públicamente y sin autenticación la totalidad de la estructura de tablas, tipos de datos y columnas de la base de datos MySQL.
- **`website_files/update_roles.php` $\rightarrow$ `private_scripts/update_roles.php`**: Se retiró del alcance público el script de migración de roles para impedir la ejecución no autorizada de sentencias de escritura sobre la base de datos a través de peticiones directas en el navegador.

### Removed
- **`website_files/js/navbar.js` $\rightarrow$ `private_scripts/navbar.js`**: Se desincorporó del frontend activo este archivo huérfano tras verificar 0 referencias en todo el proyecto, dado que sus funciones (`logout`, `clearCache`, `toggleTheme`) fueron absorbidas previamente por `dashboard.js`.

---

## [1.0.1] - Septiembre 2026

### Remediación de Emergencia de Seguridad: Aislamiento de 14 Scripts a private_scripts/, Eliminación de Contraseña Universal '123456' en auth.php, Candado Admin en cleanup_dupes.php y manage_accounts.php
*Informes técnicos de referencia:* [docs/02-REMEDIACION-EMERGENCIA.md](docs/02-REMEDIACION-EMERGENCIA.md), [docs/03-GESTION-SEGURA-CREDENCIALES.md](docs/03-GESTION-SEGURA-CREDENCIALES.md) y [docs/04-PREVENCION-SQL-INJECTION.md](docs/04-PREVENCION-SQL-INJECTION.md)

### Security
- **`website_files/api/auth.php`**: Se suprimió la lógica que permitía el inicio de sesión universal con la clave predeterminada `'123456'` en cuentas cuyo `password_hash` fuera nulo o vacío; ahora el endpoint rechaza la autenticación y exige que un administrador configure una contraseña cifrada mediante `password_hash()`, neutralizando el vector de secuestro de cuentas huérfanas o técnicas.
- **`website_files/api/manage_accounts.php`**: Se implementaron validaciones de sesión obligatoria (`session_start()`, chequeo de `$_SESSION['user_id']`) y control estricto de privilegios (`check_api_access('admin_only')`), bloqueando el borrado no autenticado de registros de la tabla `personas` desde internet.
- **`website_files/api/cleanup_dupes.php`**: Se colocaron candados estrictos de autenticación y verificación de rol administrador (`admin_only`), mitigando un vector de denegación de servicio que permitía la invocación pública de sentencias destructivas masivas `DELETE FROM equipos`.
- **Aislamiento en `private_scripts/`**: Se retiraron del acceso web público en `website_files/` y se reubicaron en la carpeta no accesible vía web `private_scripts/` un total de 14 archivos críticos y de diagnóstico que exponían datos o credenciales:
  - `export_db.php`: Volcaba la totalidad de la base de datos MySQL en texto `.sql` sin requerir credenciales ni sesión.
  - `test_db.php` y `api/test_db.php`: Exponían contraseñas de conexión a MySQL en texto plano.
  - `setup_auth.php` e `iniciar.php`: Contenían en texto plano la contraseña maestra del administrador (`petulap2026`).
  - `api/test_api.php`: Simulaba de forma forzada una sesión de usuario con ID 1 sin validación real.
  - `api/test_proxy.php`, `test_historial.php`: Scripts de pruebas locales de cURL y lectura directa de auditoría.
  - `api/setup_roles.php`, `api/update_roles_temp.php`, `api/update_schema_tmp.php`: Scripts temporales de migración de base de datos cuyo ciclo útil ya había concluido.
  - `refactor.py`, `fix_mojibake.php`, `index2.html`: Scripts utilitarios de mantenimiento y plantilla residual de hosting InfinityFree.
- **Blindaje contra Inyección SQL (SQLi) en 14 Endpoints**: Se reemplazó la concatenación de entradas y el escape débil por **sentencias preparadas nativas de MySQLi (`prepare()` + `bind_param()`)** con tipado estricto (`s`, `i`, `d`) en los endpoints clave de la API:
  - `api/consulta.php`: Búsqueda pública por ticket y DNI protegida contra inyecciones SQL ciegas o basadas en uniones.
  - `api/soporte.php`: Filtros combinados de listado, registro de órdenes (`crear`, `crear_tarea`, `crear_interno`), cambios de estado y búsquedas por lector de código de barras.
  - `api/equipos.php`: Búsquedas por texto (`LIKE ?`), inventario rápido, filtrado por documentos de compra y reportes avanzados.
  - `api/sesiones.php`: Comprobación de laptops escaneadas en triaje, actualización de items y edición de sesiones.
  - `api/personas.php`: Consultas de clientes y técnicos por DNI exacto o búsqueda textual multidimensional.
  - `api/repuestos.php`: Búsquedas por descripción o número de parte parametrizadas.
  - `api/lotes.php`: Flujo completo de compras al por mayor: creación, avance de estados, asociación de máquinas y borrado en cascada seguro.
  - `api/garantias.php`: Consultas de reclamos a mayoristas, actualización de estados y cálculo de alertas.
  - `api/turnos.php`: Consultas de horarios laborales y detección de solapamiento de turnos de guardia.
  - `api/historial.php`: Consultas de trazabilidad y corrección de la máscara de tipos en la inserción de registros de auditoría (7 campos tipados).
  - `api/notificaciones.php`: Tarea cron de alarmas de garantías y marcado individual/masivo de avisos leídos.
  - `api/roles.php`: Guardado seguro de matrices de permisos y asignación de pantallas iniciales.
  - `api/importar.php`: Comprobación insensible a mayúsculas/espacios e inserción masiva parametrizada con lista blanca de columnas.
  - `api/push.php`: Gestión parametrizada de suscripciones Web Push por identificador de usuario.
- **Gestión Segura de Credenciales y Exclusión de Git**: Se desvinculó definitivamente el archivo `website_files/api/config.php` del control de versiones mediante `git rm` y se incorporaron reglas en `.gitignore`, impidiendo que las contraseñas de producción de MySQL se sincronicen hacia GitHub.

---

## [1.0.0] - Septiembre 2026

### Línea Base Auditada: Monolito PHP/JS Vanilla con Base de Datos MySQL Original
*Informes técnicos de referencia:* [docs/01-ARQUITECTURA.md](docs/01-ARQUITECTURA.md), [docs/02-BACKEND.md](docs/02-BACKEND.md) y [docs/03-FRONTEND.md](docs/03-FRONTEND.md)

### Added
- **Arquitectura Frontend Multi-Página (MPA)**: Suite base integrada por 23 interfaces HTML interactivas desarrolladas en Vanilla HTML5, hojas de estilo CSS3 nativas y JavaScript Vanilla estructurado, sin dependencias de frameworks externos pesados:
  - `index.html`: Tablero de mando principal con indicadores operativos del negocio.
  - `login.html`: Pantalla pública de acceso y validación de credenciales.
  - `consulta.html`: Portal público de autoservicio para consulta del estado de laptops por parte de clientes finales.
  - `imprimir_sticker.html`: Módulo emergente optimizado para rotuladoras térmicas de etiquetas con código de barras.
  - `soporte.html` y `mis_ordenes.html`: Centro de tickets y tablero Kanban interactivo para el taller técnico.
  - `recepcion_movil.html`: Interfaz compacta optimizada para recepción expedita de laptops en mostrador.
  - `tecnicos.html` y `turnos.html`: Directorio de presencia del personal técnico y planificador de turnos laborales.
  - `garantias.html`: Módulo de seguimiento de garantías y devoluciones de piezas ante proveedores mayoristas.
  - `inventario.html`, `inventario_soporte.html` e `importar.html`: Gestión de catálogo general, estación de triaje masivo e importador masivo desde Excel.
  - `caja.html`: Módulo de cobro, medios de pago (efectivo, transferencia, billeteras digitales) y confirmación de entrega.
  - `reportes.html`: Centro de balances gerenciales, rentabilidad de lotes y costos operativos de repuestos.
  - `desempeno_tecnicos.html`: Medición de productividad y tiempos dedicados a reparaciones por colaborador.
  - `lotes.html`: Recepción masiva y costeo de lotes corporativos de laptops importadas o locales.
  - `clientes.html`: Libreta central de contactos y trazabilidad histórica de visitas de clientes.
  - `historial_entregados.html`: Archivo histórico definitivo de laptops reparadas y retiradas del taller.
  - `repuestos.html` y `pedidos_repuestos.html`: Catálogo de piezas de recambio y bandeja de pedidos a distribuidores externos.
  - `admin_roles.html`: Panel de seguridad para asignación granular de pantallas permitidas por rol.
  - `manual.html`: Manual interactivo de operaciones y procedimientos integrado en la aplicación.
- **Sistema de Diseño y Modo Oscuro**: Variables CSS (*Custom Properties*) organizadas en `tokens.css` y `dashboard.css`, con soporte para modo claro y modo oscuro persistente en `localStorage` y script anti-parpadeo en la cabecera `<head>`.
- **Soporte PWA (Progressive Web App)**: Manifiesto `manifest.json` y Service Worker `sw.js` (versión base `'petulap-v7'`) para permitir la instalación en dispositivos móviles y almacenamiento local en caché.
- **Capa Backend en PHP Vanilla**: 36 scripts en la carpeta `api/` para atención de operaciones CRUD vía peticiones asíncronas `fetch()` en formato JSON y sesiones nativas basadas en cookies `PHPSESSID`.
- **Base de Datos Relacional MySQL**: Esquema base (`petumjvq_pruebas`) conformado por 17 tablas operativas: `personas`, `usuarios`, `equipos`, `soporte_tecnico`, `lotes`, `lotes_equipos`, `garantias_proveedor`, `garantias_items`, `repuestos`, `pedidos_repuestos`, `notificaciones`, `push_subscriptions`, `roles_config`, `turnos_trabajo`, `secuencias_tickets`, `sesiones_inventario` e `historial_cambios`.
- **Infraestructura de Alojamiento y Despliegue**: Despliegue inicial en hosting compartido Apache/cPanel sobre dominio `https://petulap.store`, gestionado mediante subida manual de archivos por protocolo FTP.

---

## Protocolo de Registro de Cambios para Desarrolladores e Inteligencias Artificiales

Para asegurar la trazabilidad, seguridad y estabilidad continua de Petulap SST a lo largo de futuras iteraciones, cualquier desarrollador humano o agente de Inteligencia Artificial que intervenga en el código fuente debe acatar de manera obligatoria las siguientes tres reglas operativas:

### 1. Registro Obligatorio en `[Unreleased]` con Justificación ("El Por Qué")
Ninguna modificación al código fuente puede considerarse completada ni enviarse al repositorio sin haber registrado previamente su impacto en la sección `[Unreleased]` de este documento.  
* Cada entrada debe clasificarse rigurosamente bajo una de las seis etiquetas oficiales (`[Added]`, `[Changed]`, `[Deprecated]`, `[Removed]`, `[Fixed]`, `[Security]`).
* Queda terminantemente prohibido redactar resúmenes mecánicos que solo indiquen el archivo alterado (ej. *"Se modificó archivo X"*). Toda entrada debe incluir obligatoriamente el **POR QUÉ** técnico o de negocio: la causa raíz del fallo, la vulnerabilidad neutralizada, el caso límite resuelto o el beneficio operativo obtenido.
* Al momento de preparar una publicación formal a producción, los puntos acumulados bajo `[Unreleased]` se congelarán bajo una nueva versión semántica (`[MAJOR.MINOR.PATCH]`) acompañada del mes y año correspondiente.

### 2. Preservación Estricta de Jerarquía CSS, Identificadores DOM y No Regresión en Escritorio
Cualquier ajuste visual o interactivo en el frontend debe cumplir los principios de no regresión y orden estructural:
* **Jerarquía de estilos obligatoria:** Se debe preservar estrictamente el orden de carga en el `<head>`: `tokens.css` $\rightarrow$ `styles.css` $\rightarrow$ `dashboard.css`. Las reglas nuevas deben añadirse en etiquetas `<style>` al pie del `<head>` o dentro de las hojas de estilo correspondientes, nunca antes de los tokens globales.
* **Intocabilidad de selectores del DOM:** Jamás se deben renombrar, suprimir ni duplicar selectores funcionales consumidos por JavaScript (`#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, `#lbl-tecnico`, `.fab-chat`, `.notification-btn`, `#notif-dropdown`, `#notif-badge`).
* **Encapsulamiento móvil estricto:** Toda regla responsive diseñada para teléfonos celulares debe residir obligatoriamente dentro de un bloque `@media (max-width: 768px)`. Queda prohibido alterar la disposición nativa de tablas (`display: table`), la cuadrícula de tarjetas o los flujos de pantalla completa para computadoras de escritorio (>1024px) salvo directiva explícita de diseño.

### 3. Política de Cero Secretos en Git y Renovación Obligatoria de Caché PWA (`sw.js`)
La integridad del control de versiones y la entrega transparente a los colaboradores del taller exige dos candados permanentes:
* **Prohibición absoluta de credenciales en Git:** Jamás debe versionarse ni sincronizarse hacia GitHub ningún archivo que contenga contraseñas de bases de datos, llaves maestras o configuraciones sensibles (`api/config.php`). Cualquier script temporal de diagnóstico, migración puntual o corrección de datos debe colocarse exclusivamente en el directorio seguro `private_scripts/`.
* **Invalidación automática de caché de usuario:** Cada vez que se modifiquen hojas de estilo CSS, scripts JavaScript o la estructura de pantallas HTML existentes, es mandatorio incrementar la versión de caché dentro de [website_files/sw.js](website_files/sw.js) (por ejemplo, `'petulap-v8'` $\rightarrow$ `'petulap-v9'`). Este incremento garantiza que los navegadores y teléfonos de los colaboradores purguen los recursos obsoletos y descarguen las correcciones de forma inmediata y transparente, sin requerir soporte técnico ni borrados manuales de caché.
