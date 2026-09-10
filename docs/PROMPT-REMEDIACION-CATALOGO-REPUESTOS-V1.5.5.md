# PROMPT MAESTRO: Remediación Integral del Catálogo de Repuestos y Stock de Taller (v1.5.5)

> **Documento de Gobernanza y Ejecución Técnica**  
> **Basado en:** `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` y `docs/12-SISTEMA-DE-DISENO-UI-UX.md`  
> **Versión del Sistema:** Petulap SST v1.5.4 $\rightarrow$ **v1.5.5**  
> **Objetivo:** Resolver el problema crítico de visualización ("NO HAY NADA"), erradicar la condición de carrera en la autenticación, dotar de auto-inicialización a la base de datos y elevar la interfaz a estándar SaaS ejecutivo con KPIs, filtros vivos y responsividad móvil.

---

## 1. ROL
Actúa como **Ingeniero de Software Fullstack Senior y Diseñador de Producto UI/UX** para el proyecto **Petulap SST**.

---

## 2. CONTEXTO OBLIGATORIO DE ARQUITECTURA
Antes de generar o modificar cualquier archivo, consulta obligatoriamente:
- `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` (Ciclo de 5 pasos y 7 Reglas Innegociables).
- `docs/02-BACKEND.md` (Endpoints, control de sesiones y esquema relacional de base de datos).
- `docs/03-FRONTEND.md` (Catálogo de pantallas y Sección 7: *Cosas Frágiles* con identificadores protegidos).
- `docs/05-CHANGELOG.md` (Versión actual `1.5.4` $\rightarrow$ este cambio será registrado como `[1.5.5]`).
- `docs/08-CHECKLIST-TESTING.md` (Matriz de Smoke Testing pre-despliegue).
- `docs/12-SISTEMA-DE-DISENO-UI-UX.md` (Estándar ejecutivo: tokens semánticos, badges vivos `.live-chip`, KPIs de alto contraste y microinteracciones).

---

## 3. LAS 7 REGLAS DE ORO INNEGOCIABLES (COSAS FRÁGILES)
1. **Jerarquía CSS estricta:** `<link>` en `<head>` en orden inalterable:
   `tokens.css` $\rightarrow$ `styles.css` $\rightarrow$ `dashboard.css`.
2. **Identificadores protegidos intactos:**
   No modificar, ocultar ni renombrar: `#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, `#lbl-tecnico`, `.fab-chat`, `.notification-btn`, `#notif-dropdown`, `#notif-badge`.
3. **Hrefs exactos en minúsculas:**
   Enlaces sin `./`, sin mayúsculas y coincidentes con `roles_config` (ej: `href="repuestos.html"`, `href="pedidos_repuestos.html"`).
4. **Anti-flicker de modo oscuro:**
   El primer `<script>` en `<head>` debe ser el bloque síncrono que lee `localStorage.getItem('petulap-theme')`.
5. **Consultas SQL 100% preparadas:**
   Toda interacción en `api/repuestos.php` debe usar `$stmt = $db->prepare(...)` y `bind_param()`. Cero concatenaciones en sentencias SQL.
6. **Seguridad y permisos de rol:**
   Todo llamado en backend debe validar sesión activa. Permitir visualización/consulta a técnicos y gerencia (lectura de stock disponible en taller), y restringir mutaciones (crear, editar, eliminar) a roles autorizados (`admin`, `gerencia`).
7. **Zero dependencias externas:**
   Vanilla JavaScript ES6+, CSS nativo, Phosphor Icons y Google Fonts Inter. Prohibido frameworks o librerías externas.

---

## 4. ANÁLISIS DE CAUSA RAÍZ: ¿POR QUÉ "NO HAY NADA"?

Tras la inspección profunda de `website_files/repuestos.html` y `website_files/api/repuestos.php`, se determinaron 4 factores críticos que causan el fallo:

1. **Condición de Carrera en Autenticación (`checkAdmin` vs `check_auth.js`):**
   - En `repuestos.html`:
     ```javascript
     async function checkAdmin() {
       if (window.miId !== 'admin') {
         alert("Solo el Administrador puede gestionar el catalogo de repuestos.");
         window.location.href = "index.html";
       }
     }
     setTimeout(checkAdmin, 500);
     ```
   - `check_auth.js` consulta de manera asíncrona `api/auth.php?action=check`. En conexiones móviles, 3G/4G o con latencia de red > 500 ms, `window.miId` es aún `undefined` al cumplirse el `setTimeout`.
   - **Efecto:** El navegador dispara un `alert()` y redirige de inmediato al usuario a `index.html`, impidiendo que cargue la pantalla.
   - Además, bloquea por completo a los técnicos de taller, impidiéndoles consultar si existe una pantalla o teclado disponible en stock físico.

2. **Ausencia de Auto-Creación de Tabla y Datos en Backend:**
   - A diferencia de `pedidos_repuestos` (que cuenta con `$ensureTablePedidosRepuestos`), el endpoint de catálogo `repuestos` en `api/repuestos.php` **no cuenta con función de auto-creación ni verificación de columnas**.
   - Si la tabla `repuestos` en la base de datos de producción está vacía (0 filas), `SELECT * FROM repuestos` devuelve un arreglo vacío `[]`.

3. **Frontend Obsoleto y Desprovisto de Estado Vacío (Empty State):**
   - Cuando la API responde con `[]`, `repuestos.html` simplemente inyecta:
     `<p>No hay repuestos registrados.</p>`.
   - No hay métricas, no hay categorías, no hay orientación para el usuario ni botón de inicialización.
   - Los botones de la tabla tienen íconos de casas (`<i class="ph ph-house"></i>`) por error en lugar de lápiz y papelera.
   - No hay adaptabilidad móvil para pantallas estrechas.

4. **Desconexión con `pedidos_repuestos.html`:**
   - El sistema cuenta con dos módulos complementarios:
     - `pedidos_repuestos.html`: Compras en tránsito, couriers, proveedores y tracking de piezas solicitadas.
     - `repuestos.html`: Catálogo e inventario físico de piezas en estantería del taller.
   - Actualmente ambos módulos no tienen navegación cruzada ni visibilidad compartida.

---

## 5. DEFINICIÓN DEL CAMBIO SOLICITADO (v1.5.5)

- **Módulos a intervenir:**
  1. `website_files/repuestos.html` (Rediseño visual integral, UX moderna, KPIs, filtros, modal y responsividad).
  2. `website_files/api/repuestos.php` (Auto-creación `$ensureTableRepuestos`, auto-semilla de catálogo base si está vacío, endpoint de métricas y sanitización de permisos).
  3. `website_files/sw.js` (Incremento de versión de Service Worker: de `v24` a `v25`).
  4. `CHANGELOG.md` y `docs/05-CHANGELOG.md` (Registro oficial v1.5.5).
  5. `docs/02-BACKEND.md` (Actualización de documentación de endpoints de repuestos).

- **Tipo de Intervención:** `[Fixed]`, `[Changed]`, `[Added]`.

- **Criterios de Aceptación y Reglas de Negocio:**
  1. **Acceso Ininterrumpido:** La pantalla no debe expulsar al usuario con falsos positivos de timeout. Se debe verificar rol una vez resuelto `check_auth.js`.
  2. **Permisos Asimétricos:** Técnicos pueden ver y buscar repuestos (para saber si hay piezas disponibles para una reparación). Administradores y Gerencia pueden además crear, editar stock, fijar precios y eliminar.
  3. **Auto-inicialización:** Si la tabla `repuestos` no existe o tiene 0 registros, el backend asegura la estructura de tabla e inserta un catálogo base con categorías realistas de servicio técnico de laptops (Pantallas LED, Teclados, Cargadores, Baterías, Pasta Térmica, Flex, etc.).
  4. **Panel de Métricas (KPIs):** Mostrar 4 tarjetas métricas ejecutivas:
     - Total de Repuestos en Catálogo.
     - Unidades Físicas en Stock.
     - Repuestos con Stock Crítico (≤ 1 unidad) o Agotados.
     - Valor Total Estimado del Inventario (S/.).
  5. **Filtros Dinámicos (Chips):** Filtros por categoría instantáneos (`Todos`, `Pantallas`, `Teclados`, `Cargadores`, `Baterías`, `Flex / Cables`, `Stock Crítico / Agotado`).
  6. **Búsqueda Instantánea con Debounce:** Búsqueda reactiva mientras el usuario escribe por Nombre o Número de Parte (PN), sin necesidad de recargar.
  7. **Empty State de Alto Nivel:** Si no hay resultados o la búsqueda no coincide, mostrar tarjeta con ícono, mensaje instructivo y botón de restablecer filtros o registrar nuevo ítem.
  8. **Acciones Claras:** Botones con íconos correctos: `<i class="ph ph-pencil-simple"></i>` para Editar y `<i class="ph ph-trash"></i>` para Eliminar, con confirmación segura.
  9. **Navegación Cruzada:** Banner/pestaña superior con enlace directo a `pedidos_repuestos.html` (*"¿Buscando repuestos pedidos a proveedores o couriers? Ir a Seguimiento de Compras y Envíos $\rightarrow$ "*).
  10. **Diseño Móvil Adaptativo:** En pantallas `≤ 768px`, las filas de la tabla se transforman en tarjetas compactas con información clara de stock, precio y acciones táctiles.

---

## 6. PLAN DETALLADO DE EJECUCIÓN PASO A PASO

### Paso 1: Backend `website_files/api/repuestos.php`
1. **Implementar función `$ensureTableRepuestos`:**
   ```sql
   CREATE TABLE IF NOT EXISTS repuestos (
       id INT AUTO_INCREMENT PRIMARY KEY,
       nombre VARCHAR(255) NOT NULL,
       pn VARCHAR(100) DEFAULT NULL,
       categoria VARCHAR(100) DEFAULT 'GENERAL',
       stock INT DEFAULT 0,
       stock_minimo INT DEFAULT 1,
       precio DECIMAL(10,2) DEFAULT 0.00,
       ubicacion VARCHAR(100) DEFAULT 'TALLER',
       notas TEXT DEFAULT NULL,
       fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
       fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       INDEX (categoria),
       INDEX (pn),
       INDEX (stock)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
2. **Auto-seeding de seguridad:**
   Si `SELECT COUNT(*) FROM repuestos` es 0, insertar lote inicial de repuestos comunes de taller para que la pantalla nunca esté vacía:
   - *Pantalla 15.6 LED Slim 30 Pines (FHD)* (Categoría: Pantallas, Stock: 2, Precio: 220.00)
   - *Pantalla 14.0 LED Slim 30 Pines (HD/FHD)* (Categoría: Pantallas, Stock: 1, Precio: 195.00)
   - *Teclado Lenovo ThinkPad E14 / T480 Español* (Categoría: Teclados, Stock: 3, Precio: 85.00)
   - *Teclado HP 15-DY / 15-EF Español con Marco* (Categoría: Teclados, Stock: 1, Precio: 75.00)
   - *Cargador Universal Laptop 65W Tipo-C 20V* (Categoría: Cargadores, Stock: 5, Precio: 65.00)
   - *Cargador HP Punta Azul 19.5V 3.33A 65W Original* (Categoría: Cargadores, Stock: 4, Precio: 60.00)
   - *Batería Interna Dell Inspiron 5570 / 3580 (WDX0R)* (Categoría: Baterías, Stock: 2, Precio: 130.00)
   - *Pasta Térmica Alto Rendimiento Arctic MX-4 (4g)* (Categoría: Insumos, Stock: 6, Precio: 35.00)
   - *Cable Flex Video eDP 30 Pines Universal* (Categoría: Flex / Cables, Stock: 0, Precio: 45.00)
3. **Endpoint de Resumen y KPIs (`action=resumen` o `action=metricas`):**
   Retornar en una sola consulta ligera:
   - `total_repuestos`: Conteo de ítems.
   - `total_stock`: Suma de unidades físicas.
   - `stock_critico`: Cantidad de ítems con `stock <= stock_minimo`.
   - `valor_inventario`: Suma de `stock * precio`.
   - `categorias`: Lista de categorías únicas con conteo.
4. **Acciones CRUD con Bind Param:**
   Asegurar que `crear`, `actualizar`, `eliminar`, `buscar` y `list` reciban y validen los campos: `nombre`, `pn`, `categoria`, `stock`, `stock_minimo`, `precio`, `ubicacion`, `notas`.
   Validar que `crear`, `actualizar` y `eliminar` requieran rol con permisos de edición (`admin` o `gerencia`). Si un técnico intenta guardar, retornar `403 Forbidden` en JSON estructurado.

---

### Paso 2: Frontend `website_files/repuestos.html`
1. **Limpieza y Estructura `<head>`:**
   - Mantener orden estricto de estilos:
     `tokens.css?v=1` $\rightarrow$ `styles.css?v=3` $\rightarrow$ `dashboard.css?v=3`.
   - Preservar script anti-flicker de tema al inicio del `<head>`.
   - Favicon oficial y viewport correctos.
2. **Header y Navegación Cruzada:**
   - Título ejecutivo: "Catálogo de Repuestos y Stock de Taller".
   - Subtítulo descriptivo: "Control de inventario físico, disponibilidad de piezas y compatibilidad para reparaciones".
   - Banner de enlace rápido a `pedidos_repuestos.html`:
     *"¿Buscando compras de repuestos en tránsito por courier? **Ir a Pedidos de Repuestos** $\rightarrow$"*
3. **Sección de KPIs Superiores (4 Cards):**
   - Card 1: **Total Catálogo** (Ícono: `ph-package`, Color: Azul).
   - Card 2: **Unidades en Stock** (Ícono: `ph-check-circle`, Color: Verde).
   - Card 3: **Stock Crítico / Agotado** (Ícono: `ph-warning-circle`, Color: Ámbar/Rojo).
   - Card 4: **Valoración Estimada** (Ícono: `ph-currency-dollar`, Color: Púrpura).
4. **Barra de Herramientas y Filtros:**
   - Buscador en vivo con debounce de 250ms (por nombre o PN).
   - Chips interactivos de categoría: `[Todos]`, `[Pantallas]`, `[Teclados]`, `[Cargadores]`, `[Baterías]`, `[Flex / Cables]`, `[Insumos]`, `[Stock Bajo]`.
   - Botón de acción: `+ Nuevo Repuesto` (visible sólo si tiene permisos de edición, o deshabilitado con tooltip si es modo solo lectura).
5. **Tabla y Tarjetas Responsive:**
   - Desktop: Tabla moderna alineada con `dashboard.css`, badges de stock (`.badge-success` si > 2, `.badge-warning` si == 1, `.badge-danger` si == 0).
   - Columnas: Repuesto / Descripción, P/N, Categoría, Ubicación, Stock Físico, Precio Ref., Acciones.
   - Acciones: Botón Editar (`ph-pencil-simple`) y Eliminar (`ph-trash`).
   - Mobile (`@media (max-width: 768px)`): Contenedor de tarjetas individuales con bordes suaves, badges compactos y botones táctiles de 44px de altura mínima.
6. **Empty State Premium:**
   - Si la búsqueda no encuentra elementos o la lista está vacía:
     Contenedor centrado con ícono grande `ph-magnifying-glass` o `ph-package-open`, texto explicativo amigable y botón `Limpiar Búsqueda` o `Crear Repuesto`.
7. **Modal de Gestión (Nuevo / Editar):**
   - Campos estructurados: Nombre, P/N, Categoría (select con opciones predeterminadas + opción libre), Ubicación en taller, Stock actual, Stock mínimo de alerta, Precio referencial, Notas adicionales.
   - Validación reactiva y cierre suave (ESC, clic en overlay, o botón cancelar).
8. **Manejo Seguro de Autenticación en JS:**
   - Reemplazar el frágil `setTimeout(checkAdmin, 500)` por una función de inicialización que espere a que `check_auth.js` defina `window.userRole` o consulte `/api/auth.php?action=check`.
   - Si el usuario es técnico, mostrar la pantalla en modo **Consulta y Reserva de Stock** (ocultando botones de borrado o deshabilitando edición no autorizada sin expulsar al técnico).

---

### Paso 3: Service Worker y Cache Busting
1. Actualizar `website_files/sw.js` incrementando la versión del cache a `petulap-v25`:
   `const CACHE_NAME = 'petulap-v25';`
2. Asegurar que `repuestos.html` se cargue siempre fresco.

---

### Paso 4: Despliegue y Pruebas
1. Ejecutar script FTP de sincronización a producción:
   `python private_scripts/sync_ftp_production.py`
2. Verificar subida exitosa de:
   - `repuestos.html`
   - `api/repuestos.php`
   - `sw.js`
3. Probar en vivo en `https://petulap.store/repuestos.html`:
   - Carga limpia sin redirección forzada a `index.html`.
   - Carga de KPIs y visualización inmediata del catálogo inicial.
   - Filtrado por chips y búsqueda por texto.
   - Apertura y guardado desde el modal.

---

### Paso 5: Documentación y Versionado
1. Actualizar `CHANGELOG.md` y `docs/05-CHANGELOG.md` registrando `[1.5.5]` con fecha y descripción detallada del cambio bajo `[Fixed]`, `[Changed]` y `[Added]`.
2. Actualizar `docs/02-BACKEND.md` con las nuevas acciones y parámetros de `api/repuestos.php`.
3. Confirmar preservación estricta de las 7 Cosas Frágiles.

---

## 7. ENTREGABLES ESPERADOS
- Código completo de `website_files/repuestos.html` modernizado y blindado.
- Código completo de `website_files/api/repuestos.php` con `$ensureTableRepuestos`, auto-seeding y KPIs.
- Incremento de versión en `website_files/sw.js` a `petulap-v25`.
- Registro formal en `CHANGELOG.md` y `docs/05-CHANGELOG.md`.
- Verificación en vivo en producción reportando el estado del catálogo en `https://petulap.store/repuestos.html`.
