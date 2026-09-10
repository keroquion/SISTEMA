# PROMPT MAESTRO DE INGENIERÍA: Remediación de Bug de Desenfoque (Blur), Rediseño de Garantías de Proveedor y Elevación Corporativa del Login (v1.5.7)

> **Documento de Gobernanza Técnica y Ejecución Asistida por IA de Nivel Élite (1 en 1 Millón)**  
> **Sistema:** Petulap SST (Plataforma de Soporte Técnico, Taller, Lotes e Inventario)  
> **Ubicación:** `docs/PROMPT-REMEDIACION-GARANTIAS-Y-LOGIN-V1.5.7.md`  
> **Basado estrictamente en:** `docs/00-INICIALIZACION-EJECUTOR-DESARROLLADOR.MD`, `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` y `docs/12-SISTEMA-DE-DISENO-UI-UX.md`  
> **Versión del Sistema:** Petulap SST v1.5.6 $\rightarrow$ **v1.5.7**

---

```markdown
================================================================================
BLOQUE DE ACTIVACIÓN OBLIGATORIO PARA EL AGENTE DE IA (COPIAR Y EJECUTAR)
================================================================================

ROL DEL AGENTE:
Actúa como Ingeniero de Software Fullstack Senior y Desarrollador Ejecutor Quirúrgico para el proyecto Petulap SST, bajo las directrices estrictas de docs/00-INICIALIZACION-EJECUTOR-DESARROLLADOR.MD y docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md.

DIRECTIVA PRIMARIA:
Tu misión es resolver con precisión milimétrica y quirúrgica el bug de pantalla borrosa (blur) en garantias.html, modernizar la interfaz de gestión de garantías bajo el sistema de diseño ejecutivo SaaS (v1.5), y transformar login.html en un portal corporativo con la identidad visual oficial de Petulap S.A.C., sin introducir regresiones ni alterar código no solicitado.

================================================================================
GUARDRAILS Y RESTRICCIONES NEGATIVAS EXPLÍCITAS (HARD NEGATIVE CONSTRAINTS)
================================================================================
Bajo ninguna circunstancia debes violar las siguientes 8 restricciones. Cualquier infracción anulará la entrega:

1. ESTÁ ESTRICTAMENTE PROHIBIDO modificar o tocar archivos fuera de esta lista blanca:
   - website_files/garantias.html
   - website_files/login.html
   - website_files/api/garantias.php
   - website_files/sw.js
   - CHANGELOG.md
   - docs/05-CHANGELOG.md
   - docs/02-BACKEND.md
   - docs/PROMPT-REMEDIACION-GARANTIAS-Y-LOGIN-V1.5.7.md
   (No toques mis_ordenes.html, recepcion_movil.html, soporte.html, inventario.html, repuestos.html ni ningún otro archivo).

2. ESTÁ ESTRICTAMENTE PROHIBIDO renombrar, eliminar, desplazar u ocultar los IDENTIFICADORES PROTEGIDOS del DOM:
   #mobile-menu-toggle, #mobile-sidebar, #lbl-nombre, #lbl-tecnico, .fab-chat, .notification-btn, #notif-dropdown, #notif-badge.
   Son consumidos por dashboard.js y check_auth.js. Si los tocas, romperás la navegación global.

3. ESTÁ ESTRICTAMENTE PROHIBIDO alterar la jerarquía inalterable de CSS en el <head>:
   1º tokens.css -> 2º styles.css -> 3º dashboard.css.

4. ESTÁ ESTRICTAMENTE PROHIBIDO incorporar dependencias o librerías externas:
   Cero Bootstrap, cero TailwindCSS, cero jQuery, cero paquetes npm. Exclusivamente Vanilla JavaScript ES6+, CSS nativo con variables de diseño, Phosphor Icons y Google Fonts Inter.

5. ESTÁ ESTRICTAMENTE PROHIBIDO usar consultas SQL concatenadas en PHP:
   100% de las consultas en api/garantias.php deben usar sentencias preparadas nativas ($stmt = $db->prepare(...) y bind_param()). Tolerancia cero a Inyección SQL.

6. ESTÁ ESTRICTAMENTE PROHIBIDO alterar enlaces (href) o inventar rutas:
   Mantener coincidencia milimétrica en minúsculas sin ./ (ej. href="garantias.html", href="pedidos_repuestos.html", href="consulta.html") para compatibilidad con check_auth.js y roles_config.

7. ESTÁ ESTRICTAMENTE PROHIBIDO omitir el script anti-flicker de modo oscuro:
   El bloque síncrono que lee localStorage.getItem('petulap-theme') debe permanecer como el primer <script> dentro de <head>.

8. ESTÁ ESTRICTAMENTE PROHIBIDO inventar datos de la empresa:
   Usar única y exclusivamente la información institucional oficial de Petulap S.A.C. estipulada en este documento.

================================================================================
CONTEXTO CORPORATIVO OFICIAL DE LA EMPRESA (PETULAP S.A.C.)
================================================================================
Usa estos datos exactos para enriquecer y elevar el portal de login.html:
- Razón Social: Petulap S.A.C.
- Eslogan / Especialidad: "Importación Directa de Laptops desde EE.UU. & Europa · Laboratorio Especializado de Servicio Técnico".
- Ubicación Central: Arequipa, Perú.
- Sedes Físicas Oficiales:
  * Sede Yanahuara: Centro Comercial Cayma / Av. Ejército.
  * Sede Cayma: Taller Central de Diagnóstico y Laboratorio Técnico.
- Política de Garantía Oficial:
  * 6 meses de garantía directa en laptops importadas.
  * 3 años de cobertura en soporte técnico especializado.
- Canales de Atención al Cliente: WhatsApp Oficial (+51 983 396 137).
- Activos Visuales Oficiales:
  * Logotipo Horizontal Oficial: img/logo-petulap.png
  * Favicon e Icono de Aplicación: img/favicon-petulap.png

================================================================================
DIAGNÓSTICO TÉCNICO DE CAUSA RAÍZ: EL BUG DEL BLUR EN garantias.html
================================================================================
- Ubicación del Error: website_files/garantias.html, línea 688.
- Código Responsable:
  <!-- MODAL TRIAJE -->
  <div class="modal-overlay" id="modal-triaje-overlay" onclick="cerrarModalTriaje()"></div>
  <div class="modal card" id="modal-triaje">
- Mecanismo del Fallo:
  El elemento <div class="modal-overlay" id="modal-triaje-overlay"> carece del atributo style="display:none;".
  En website_files/css/styles.css (línea 557), la regla .modal-overlay tiene:
  {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background-color: rgba(15, 23, 42, 0.4);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      display: flex;
      z-index: 1000;
  }
  Por tanto, al cargar la página, el navegador aplica de inmediato una capa fija a pantalla completa con desenfoque de 8px (blur).
  Al hacer clic en cualquier lugar, se dispara onclick="cerrarModalTriaje()", el cual ejecuta document.getElementById('modal-triaje-overlay').style.display = 'none', haciendo que el blur desaparezca.
- Solución Obligatoria:
  Asignar explícitamente style="display:none;" tanto a #modal-triaje-overlay como a #modal-triaje, y asegurar que todos los modales permanezcan ocultos hasta que se invoque su función de apertura.

================================================================================
ESPECIFICACIÓN TÉCNICA Y TAREAS PASO A PASO
================================================================================

--------------------------------------------------------------------------------
PASO 1: REMEDIACIÓN Y REDISEÑO EJECUTIVO DE website_files/garantias.html
--------------------------------------------------------------------------------
1.1. Corrección del Desenfoque (Blur):
     Garantizar que los overlays y modales inicien con style="display:none;".

1.2. Cabecera Ejecutiva & Barra de Acciones:
     - Título: "Garantías con Proveedores" con icono <i class="ph-bold ph-shield-check">.
     - Subtítulo: "Trazabilidad de equipos y repuestos enviados a garantía con mayoristas y proveedores de lotes."
     - Botones de acción:
       * <button class="btn-primary" onclick="abrirModalTriaje()"><i class="ph-bold ph-magic-wand"></i> Nueva Garantía (Desde Triaje)</button>
       * <button class="btn-cross-nav" onclick="abrirModalManual()"><i class="ph-bold ph-plus-circle"></i> Nueva Garantía Manual</button>
       * <button class="btn-refresh" onclick="cargarGarantias()"><i class="ph-bold ph-arrows-clockwise"></i> Actualizar</button>

1.3. Fila de 4 Tarjetas KPI Ejecutivas (.kpis-row):
     - KPI 1: Total Garantías Registradas (Azul, icono ph-shield-check, id="kpi-total").
     - KPI 2: En Trámite / Enviadas (Pulso Ámbar, icono ph-paper-plane-tilt, id="kpi-enviadas").
     - KPI 3: Aceptadas / Resueltas (Pulso Verde, icono ph-check-circle, id="kpi-aceptadas").
     - KPI 4: Rechazadas / Observadas (Pulso Rojo, icono ph-x-circle, id="kpi-rechazadas").

1.4. Filtros por Chips de Estado y Buscador con Debounce:
     - Buscador instantáneo con input reactivo (250 ms debounce): filtra por N° Garantía, Proveedor, RUC, Lote de Origen o Notas.
     - Chips interactivos con contadores vivos:
       * [Todas]
       * [Enviadas / En Trámite] (con punto pulsante ámbar .pulse-amber)
       * [Aceptadas] (con punto pulsante verde .pulse-green)
       * [Reemplazo Recibido]
       * [Rechazadas] (con punto pulsante rojo .pulse-red)

1.5. Vista Dual: Tabla Desktop y Tarjetas Móviles:
     - Desktop: Tabla moderna con diseño alineado a dashboard.css.
       Columnas: N° Garantía & Fecha, Lote / Origen, Proveedor & RUC, Equipos / Ítems, Estado con Badges Vivos (.badge-warning, .badge-success, .badge-danger, .badge-info), Acciones.
       Acciones: Botón Ver/Gestionar (<i class="ph-bold ph-eye">), Botón Imprimir Guía (<i class="ph-bold ph-printer">).
     - Mobile (@media max-width: 768px):
       Grid de tarjetas apiladas con badges táctiles, resumen de lote y botones de acción de 44px de altura mínima.

1.6. Modales Optimizados:
     - Modal Detalle de Garantía: Muestra la información completa del proveedor, lote de origen, lista de laptops/repuestos incluidos, notas técnicas, y formulario para cambiar el estado (Aceptado, Rechazado, Reemplazo) con registro de nota de resolución.
     - Modal Nueva Garantía Manual: Formulario compacto de 2 columnas para registrar proveedor, RUC, lote y observaciones sin invadir la pantalla principal.

--------------------------------------------------------------------------------
PASO 2: BACKEND EN website_files/api/garantias.php
--------------------------------------------------------------------------------
2.1. Incorporar el endpoint action=resumen o action=metricas:
     Retornar en formato JSON estructurado:
     {
         "ok": true,
         "data": {
             "total": N,
             "enviadas": N,
             "aceptadas": N,
             "rechazadas": N
         }
     }
2.2. Asegurar que las consultas de listado (case "list") soporten filtrado seguro por estado (?estado=...) usando sentencias preparadas ($stmt->prepare y bind_param).

--------------------------------------------------------------------------------
PASO 3: ELEVACIÓN CORPORATIVA DE website_files/login.html
--------------------------------------------------------------------------------
3.1. Identidad Visual Oficial de Marca:
     - Reemplazar el icono genérico <i class="ph ph-lock-key"> por el logotipo horizontal oficial:
       <img src="img/logo-petulap.png" alt="Petulap SST" class="login-brand-logo" style="max-width: 220px; height: auto; margin-bottom: 16px;">
     - Título: "Sistema de Soporte Técnico (SST)"
     - Subtítulo: "Petulap S.A.C. · Laboratorio Técnico & Control Operativo"

3.2. Formulario de Acceso de Alto Nivel:
     - Campo DNI / Usuario con icono <i class="ph-bold ph-user">.
     - Campo Contraseña con icono <i class="ph-bold ph-lock-key"> y botón interactivo para Mostrar / Ocultar contraseña:
       <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility()" title="Mostrar u ocultar contraseña">
           <i class="ph-bold ph-eye" id="icon-toggle-pass"></i>
       </button>
     - Botón principal de login con estado interactivo ("Validando..." + spinner Phosphor) al enviar credenciales.
     - Manejo de tema claro/oscuro respetando anti-flicker y botón superior de tema (Sol/Luna).

3.3. Sección de Confianza Institucional:
     - Badges semánticos debajo del formulario:
       * <span class="trust-badge"><i class="ph-bold ph-map-pin"></i> Sedes Yanahuara & Cayma (Arequipa)</span>
       * <span class="trust-badge"><i class="ph-bold ph-shield-check"></i> Garantía 6 Meses + 3 Años Soporte</span>
     - Enlace prominente para clientes externos:
       <div class="client-portal-card">
           <span>¿Eres cliente de servicio técnico?</span>
           <a href="consulta.html" class="client-track-link">
               <strong>Rastrear el estado de tu equipo aquí</strong> <i class="ph-bold ph-arrow-right"></i>
           </a>
       </div>

--------------------------------------------------------------------------------
PASO 4: ACTUALIZACIÓN DE SERVICE WORKER (website_files/sw.js)
--------------------------------------------------------------------------------
- Incrementar la versión de la caché PWA a:
  const CACHE_NAME = 'petulap-v27';
- Garantizar la invalidación instantánea de caché para garantias.html y login.html.

--------------------------------------------------------------------------------
PASO 5: REGISTRO DOCUMENTAL Y DESPLIEGUE A PRODUCCIÓN
--------------------------------------------------------------------------------
- Actualizar CHANGELOG.md y docs/05-CHANGELOG.md registrando la versión [1.5.7] bajo el estándar Keep a Changelog.
- Actualizar docs/02-BACKEND.md con el nuevo endpoint de api/garantias.php.
- Ejecutar el script FTP oficial:
  python private_scripts/sync_ftp_production.py
- Verificar en vivo en https://petulap.store/garantias.html y https://petulap.store/login.html.

================================================================================
CRITERIOS DE ACEPTACIÓN INNEGOCIABLES (DEFINITION OF DONE)
================================================================================
[ ] 1. Al acceder a https://petulap.store/garantias.html, la página carga limpia, nítida y SIN NINGÚN DESENFOQUE (blur) ni overlay visible.
[ ] 2. Al hacer clic en "Nueva Garantía (Desde Triaje)" o "Nueva Garantía Manual", se abre el modal correspondiente suavemente y se cierra con botón X, cancelar o ESC.
[ ] 3. Las 4 tarjetas KPI muestran métricas en tiempo real con pulsos ámbar/verde/rojo.
[ ] 4. El buscador por texto filtra dinámicamente con debounce sin recargar la página.
[ ] 5. En vista móvil (<= 768px), las garantías se muestran como tarjetas apiladas táctiles legibles sin desbordamiento horizontal.
[ ] 6. En https://petulap.store/login.html se exhibe el logotipo oficial img/logo-petulap.png nítido en lugar de un icono genérico.
[ ] 7. El botón de alternancia de contraseña en login permite alternar entre tipo "password" y "text" con cambio de icono (ph-eye / ph-eye-slash).
[ ] 8. El enlace a consulta.html es visible y funcional para clientes que acceden desde WhatsApp.
[ ] 9. sw.js está en la versión petulap-v27 y la transferencia FTP culmina sin errores.
[ ] 10. Las 7 Cosas Frágiles están intactas y validadas contra docs/08-CHECKLIST-TESTING.md.
```
