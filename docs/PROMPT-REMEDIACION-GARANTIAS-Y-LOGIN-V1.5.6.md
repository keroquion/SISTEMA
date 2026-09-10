# PROMPT MAESTRO: Remediación de Bug de Desenfoque (Blur), Rediseño de Garantías de Proveedor y Elevación Corporativa del Login (v1.5.6)

> **Documento de Gobernanza Técnica y Control de Calidad Asistido por IA**  
> **Basado en:** `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` y `docs/12-SISTEMA-DE-DISENO-UI-UX.md`  
> **Versión del Sistema:** Petulap SST v1.5.5 $\rightarrow$ **v1.5.6**  
> **Objetivo:** Erradicar el bug visual de pantalla borrosa (blur overlay inicial) en `garantias.html`, modernizar integralmente la gestión de garantías de taller bajo estándar SaaS y transformar `login.html` en un portal de acceso corporativo de alto nivel con identidad de marca oficial, badges institucionales y usabilidad avanzada.

---

## 1. ROL
Actúa como **Ingeniero de Software Fullstack Senior y Diseñador de Producto UI/UX** para el proyecto **Petulap SST**.

---

## 2. CONTEXTO OBLIGATORIO DE ARQUITECTURA
Antes de generar o modificar cualquier archivo, consulta los documentos de gobernanza técnica:
- `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` (Las 7 Reglas de Oro y ciclo de 5 pasos).
- `docs/02-BACKEND.md` (Catálogo de endpoints y base de datos relacional).
- `docs/03-FRONTEND.md` (Catálogo de pantallas y Sección 7: *Cosas Frágiles* con identificadores protegidos).
- `docs/05-CHANGELOG.md` (Historial oficial; la versión actual es **1.5.5**, este cambio será **[1.5.6]**).
- `docs/08-CHECKLIST-TESTING.md` (Protocolo de Smoke Testing).
- `docs/12-SISTEMA-DE-DISENO-UI-UX.md` (Tokens semánticos, componentes en vivo, heatmaps, badges `.live-chip` y microinteracciones).

---

## 3. LAS 7 REGLAS DE ORO INNEGOCIABLES (COSAS FRÁGILES)
1. **Jerarquía CSS estricta:** `<link>` en `<head>` en orden:  
   `tokens.css` $\rightarrow$ `styles.css` $\rightarrow$ `dashboard.css`.
2. **Identificadores protegidos intactos:**  
   No alterar ni renombrar: `#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, `#lbl-tecnico`, `.fab-chat`, `.notification-btn`, `#notif-dropdown`, `#notif-badge`.
3. **Hrefs exactos en minúsculas:**  
   Enlaces limpios sin `./` ni mayúsculas (ej: `href="garantias.html"`, `href="login.html"`).
4. **Anti-flicker de modo oscuro:**  
   El primer `<script>` en `<head>` debe ser el bloque síncrono que lee `localStorage.getItem('petulap-theme')`.
5. **Consultas SQL 100% preparadas:**  
   Toda interacción en `api/garantias.php` debe emplear `$stmt = $db->prepare(...)` y `bind_param()`.
6. **Seguridad Backend y Sesiones:**  
   Validar sesión activa en endpoints y respetar niveles de rol.
7. **Zero dependencias externas:**  
   Vanilla JavaScript ES6+, CSS nativo con variables de diseño, Phosphor Icons y Google Fonts Inter.

---

## 4. ANÁLISIS DE CAUSA RAÍZ: ¿POR QUÉ APARECE LA PANTALLA BORROSA (BLUR) EN `garantias.html`?

Tras la inspección del código fuente de `website_files/garantias.html` y las hojas de estilo:

1. **Causa Raíz del Blur en `garantias.html` (Línea 688):**
   - En `website_files/garantias.html` (línea 688) se encuentra declarado el modal de triaje:
     ```html
     <!-- MODAL TRIAJE -->
     <div class="modal-overlay" id="modal-triaje-overlay" onclick="cerrarModalTriaje()"></div>
     <div class="modal card" id="modal-triaje">
     ```
   - **El Error Crítico:** El elemento `<div class="modal-overlay" id="modal-triaje-overlay">` **NO TIENE** `style="display:none;"` ni clase que lo oculte por defecto en la carga inicial del documento.
   - En `website_files/css/styles.css` (línea 557), la clase `.modal-overlay` tiene las siguientes propiedades:
     ```css
     .modal-overlay {
         position: fixed;
         top: 0; left: 0; width: 100%; height: 100%;
         background-color: rgba(15, 23, 42, 0.4);
         backdrop-filter: blur(8px);
         -webkit-backdrop-filter: blur(8px);
         display: flex;
         z-index: 1000;
         animation: fadeIn 0.3s forwards;
     }
     ```
   - **Comportamiento Anómalo:** Tan pronto el usuario entra a `https://petulap.store/garantias.html`, el navegador renderiza este `div` en pantalla completa con filtro `backdrop-filter: blur(8px)`.
   - **Por qué desaparece al hacer clic:** Porque el overlay tiene el atributo `onclick="cerrarModalTriaje()"`, cuya función de JavaScript ejecuta:
     ```javascript
     function cerrarModalTriaje() {
         document.getElementById('modal-triaje').style.display = 'none';
         document.getElementById('modal-triaje-overlay').style.display = 'none';
     }
     ```
   - Al hacer clic en cualquier parte de la pantalla, se activa esta función y se oculta el overlay, revelando la página subyacente.

2. **Deficiencias Actuales en `garantias.html`:**
   - La pantalla muestra formularios planos desplegados en todo el ancho, ocupando espacio vertical innecesario.
   - Carece de tarjetas métricas (KPIs) ejecutivas para auditar garantías en trámite, aprobadas o rechazadas.
   - Botón de cierre en modal con icono de casa (`<i class="ph ph-house"></i>`) en lugar de `ph-x`.
   - No tiene vista responsive adaptativa en tarjetas para dispositivos móviles (`@media <= 768px`).

3. **Deficiencias Actuales en `login.html`:**
   - Emplea un icono genérico de candado (`<div class="login-logo"><i class="ph ph-lock-key"></i></div>`) en lugar del logotipo oficial de la empresa (`img/logo-petulap.png`).
   - Falta de contexto institucional: no menciona a **Petulap S.A.C.**, su trayectoria en importación desde EE.UU. y Europa, sus sedes en Arequipa (Yanahuara y Cayma), ni sus políticas de garantía (6 meses en laptops + 3 años de soporte técnico).
   - El campo de contraseña carece de botón interactivo para mostrar/ocultar contraseña (`<i class="ph ph-eye"></i>` / `ph-eye-slash`).
   - El diseño es básico y no transmite la solidez ejecutiva de una plataforma corporativa.

---

## 5. DEFINICIÓN DEL CAMBIO SOLICITADO (v1.5.6)

- **Módulos a intervenir:**
  1. `website_files/garantias.html` (Corrección inmediata del overlay con blur, rediseño completo de la interfaz de garantías bajo el sistema de diseño v1.5, 4 KPIs, filtros de estado, búsqueda debounced y modales limpios).
  2. `website_files/api/garantias.php` (Incorporación de endpoint de métricas/resumen `action=resumen` y optimización de filtros por estado).
  3. `website_files/login.html` (Rediseño visual integral con logo corporativo oficial `img/logo-petulap.png`, badges institucionales, selector mostrar/ocultar contraseña, microanimaciones y link de alta visibilidad para clientes a `consulta.html`).
  4. `website_files/sw.js` (Incremento de versión de Service Worker: de `v25` a `v26`).
  5. `CHANGELOG.md` y `docs/05-CHANGELOG.md` (Registro oficial v1.5.6).
  6. `docs/02-BACKEND.md` (Actualización de documentación de `api/garantias.php`).

- **Tipo de Intervención:** `[Fixed]`, `[Changed]`, `[Added]`.

---

## 6. PLAN DETALLADO DE EJECUCIÓN PASO A PASO

### Paso 1: Remediación y Rediseño de `garantias.html`
1. **Corrección de Bug de Desenfoque:**
   - Asegurar `style="display:none;"` explícito en `#modal-triaje-overlay` y `#modal-triaje`, así como en `#modal-overlay` y `#modal`.
2. **Cabecera Ejecutiva:**
   - Título: `Garantías con Proveedores` con icono `ph-shield-check`.
   - Subtítulo: `Control de devoluciones, notas de crédito, piezas de recambio y reemplazos de lotes de importación.`
   - Botones de acción en cabecera:
     - `+ Nueva Garantía (Desde Triaje)` (abre modal de triaje asistido).
     - `+ Nueva Garantía Manual` (abre modal de registro manual ordenado).
     - `Actualizar` (recarga rápida de datos).
3. **4 Tarjetas KPI Ejecutivas:**
   - KPI 1: **Total Garantías** (Icono: `ph-shield-check`, Azul).
   - KPI 2: **En Trámite / Enviadas** (Icono: `ph-paper-plane-tilt`, Pulso Ámbar).
   - KPI 3: **Aceptadas / Resueltas** (Icono: `ph-check-circle`, Pulso Verde).
   - KPI 4: **Rechazadas / Observadas** (Icono: `ph-x-circle`, Pulso Rojo).
4. **Filtros por Chips y Búsqueda Debounced:**
   - Barra de búsqueda reactiva (250 ms) por número de garantía, nombre de proveedor, RUC, lote o notas.
   - Chips interactivos con contadores: `[Todas]`, `[Enviadas / En Trámite]`, `[Aceptadas]`, `[Reemplazo Recibido]`, `[Rechazadas]`.
5. **Tabla Desktop y Tarjetas Móviles:**
   - Desktop: Columnas claras para N° Garantía, Lote / Origen, Proveedor & RUC, Cantidad de Equipos/Piezas, Estado con Badges Vivos (`.badge-warning`, `.badge-success`, `.badge-danger`, `.badge-info`), Fecha de Registro y Acciones.
   - Mobile (`@media <= 768px`): Tarjetas apiladas con toda la información clave, badges de estado y botones táctiles de 44px de altura.
6. **Modales Rediseñados:**
   - Modal de Detalle y Trazabilidad con historial de cambios, notas de resolución y formulario para actualizar estado (Aceptado, Rechazado, etc.).
   - Modal de Creación (Manual / Desde Triaje) compacto, de 2 columnas, con validación de campos y cierre limpio (tecla ESC o clic en overlay).

---

### Paso 2: Backend `api/garantias.php`
1. **Endpoint `action=resumen` / `action=metricas`:**
   - Retornar conteos consolidados:
     - `total_garantias`
     - `en_tramite` (estado `ENVIADO` o `PENDIENTE`)
     - `aceptadas` (estado `ACEPTADO` o `REEMPLAZO_RECIBIDO`)
     - `rechazadas` (estado `RECHAZADO`)
2. **Consultas 100% Preparadas (`bind_param`):**
   - Asegurar que todas las consultas SQL sigan el estándar de blindaje de `04-PREVENCION-SQL-INJECTION.md`.

---

### Paso 3: Elevación Visual del Portal de Login (`login.html`)
1. **Identidad de Marca Oficial:**
   - Reemplazar el icono genérico `<i class="ph ph-lock-key"></i>` por el logotipo oficial horizontal:
     `<img src="img/logo-petulap.png" alt="Petulap SST" class="login-brand-logo">`
   - Título: `Sistema de Soporte Técnico (SST)`
   - Subtítulo Institucional: `Petulap S.A.C. · Importación Directa & Laboratorio Especializado`
2. **Insignias Institucionales de Confianza:**
   - Badges visuales sutiles en la parte inferior o tarjeta lateral:
     - *Sedes en Arequipa:* Yanahuara & Cayma.
     - *Garantía Petulap:* 6 meses en laptops + 3 años de soporte técnico.
3. **Usabilidad del Formulario:**
   - Campo Usuario/DNI con icono `ph-user`.
   - Campo Contraseña con icono `ph-lock-key` y botón de alternancia mostrar/ocultar (`togglePassword` con icono `ph-eye` / `ph-eye-slash`).
   - Botón CTA principal "Ingresar al Sistema" con animación de spinner durante la validación.
   - Enlace destacado para clientes externos:
     *"¿Eres cliente y buscas tu orden? **Rastrear equipo aquí →**"* hacia `consulta.html`.
4. **Anti-flicker y Soporte de Modo Oscuro:**
   - Mantener el botón flotante superior de cambio de tema (Sol / Luna) y el script anti-flicker al inicio del `<head>`.

---

### Paso 4: Service Worker y Cache Busting
1. Actualizar `website_files/sw.js` a la versión `petulap-v26`:
   `const CACHE_NAME = 'petulap-v26';`
2. Asegurar que tanto `garantias.html` como `login.html` se sirvan con la versión más reciente sin retención en caché viejo.

---

### Paso 5: Despliegue y Pruebas en Vivo
1. Ejecutar el script FTP de sincronización a producción:
   `python private_scripts/sync_ftp_production.py`
2. Comprobar en vivo:
   - `https://petulap.store/garantias.html`: Carga directa y nítida **SIN ningún desenfoque ni overlay fantasma**.
   - `https://petulap.store/login.html`: Presentación del logotipo oficial, badges institucionales, alternador de visibilidad de contraseña y acceso fluido.
3. Actualizar `CHANGELOG.md` y `docs/05-CHANGELOG.md` registrando `[1.5.6]`.

---

## 7. ENTREGABLES ESPERADOS
- Código corregido y modernizado de `website_files/garantias.html`.
- Código optimizado de `website_files/api/garantias.php`.
- Código elevado de `website_files/login.html`.
- Incremento a `petulap-v26` en `website_files/sw.js`.
- Registro formal en la bitácora `CHANGELOG.md`.
- Despliegue confirmado vía FTP y reporte de pruebas en vivo.
