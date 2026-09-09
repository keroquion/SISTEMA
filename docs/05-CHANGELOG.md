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
- **`[Changed]`**: Actualización programada de la versión de caché del Service Worker a `'petulap-v9'` en [website_files/sw.js](website_files/sw.js) al momento de publicar a producción para forzar la recarga transparente de las hojas de estilo y componentes responsivos en todos los navegadores de los clientes.
- **`[Security]`**: Parametrización con sentencias preparadas nativas de MySQLi (`prepare()` + `bind_param()`) en endpoints y subconsultas SQL dinámicas restantes del backend (módulos analíticos y reportes complejos) para completar la cobertura de blindaje contra Inyección SQL.
- **`[Security]`**: Implementación de un pipeline de Integración y Despliegue Continuo (CI/CD) automatizado desde GitHub hacia el hosting de producción (cPanel/Apache) para reemplazar el traspaso manual por FTP y mitigar el riesgo de desincronización o error humano en despliegues.
- **`[Changed]`**: Coordinación en el panel de control del servidor (cPanel) para renombrar la base de datos de `petumjvq_pruebas` a un identificador formal de producción (ej. `petumjvq_sistema`), actualizando la variable de entorno en el servidor de forma segura.
- **`[Added]`**: Implementación de inyección dinámica de atributos `data-label` en la función `renderTabla()` de [website_files/reportes.html](website_files/reportes.html) una vez congelado el esquema final de columnas de reportes, permitiendo el despliegue responsivo completo en tablas de auditoría dinámica.
- **`[Added]`**: Incorporación de un elemento colapsable nativo `<details><summary>` en las tarjetas móviles de [website_files/inventario.html](website_files/inventario.html) para permitir consultar bajo demanda las especificaciones técnicas secundarias (`Procesador`, `RAM`, `HD/SSD`, `Observacion`) sin saturar la vista vertical en smartphones.

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
