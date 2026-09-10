# PROMPT MAESTRO: Estandarización de Navegación de Escritorio y Módulos Faltantes (v1.5.4)

> **Documento de Gobernanza Técnica y Ejecución Asistida por IA**  
> **Basado estrictamente en:** `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md`  
> **Sistema:** Petulap SST (Soporte Técnico, Lotes e Inventario)  
> **Ubicación:** `docs/PROMPT-ESTANDARIZACION-MENU-DESKTOP-V1.5.4.md`  

---

ROL: Actúa como Ingeniero de Software Fullstack Senior para el proyecto Petulap SST.

CONTEXTO OBLIGATORIO DE ARQUITECTURA:
Antes de generar código o modificar cualquier archivo, consulta los documentos maestros en docs/:
- docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md (Estructura general y 7 reglas de oro)
- docs/01-ARQUITECTURA.md (Estructura de directorios y mapa de enlaces)
- docs/02-BACKEND.md (Endpoints de la API y modelo relacional)
- docs/03-FRONTEND.md (Sección 1: Inventario de 23 pantallas y Sección 7: 'Cosas Frágiles')
- docs/05-CHANGELOG.md (Versión actual 1.5.3 -> Este cambio será [1.5.4])
- docs/08-CHECKLIST-TESTING.md (Protocolo de pruebas de humo y validación cruzada)
- docs/10-REMEDIACION-NAVEGACION-MOVIL.md (Estandarización de menús móviles y acordeones)
- docs/12-SISTEMA-DE-DISENO-UI-UX.md (Tokens semánticos, badges y estilo ejecutivo SaaS)

REGLAS GENERALES INNEGOCIABLES:
1. Jerarquía CSS estricta: tokens.css -> styles.css -> dashboard.css.
2. Identificadores protegidos: NO renombrar ni borrar #mobile-menu-toggle, #mobile-sidebar, #lbl-nombre, #lbl-tecnico, .fab-chat, .notification-btn, #notif-dropdown, #notif-badge.
3. Hrefs exactos: Coincidencia milimétrica en minúsculas (ej: `pedidos_repuestos.html`, `repuestos.html`, `garantias.html`, `historial_entregados.html`, `clientes.html`, `turnos.html`, `importar.html`, `manual.html`), sin `./` ni mayúsculas para preservar el filtrado dinámico de `check_auth.js` con `roles_config`.
4. Modo oscuro: Preservar el script anti-flicker intacto al inicio del <head>.
5. Seguridad Backend: Validación de sesión y roles en api/.
6. Enfoque quirúrgico: Modificaciones focalizadas y limpias en las 20 pantallas internas. Cero frameworks ni dependencias externas.

=======================================================
DEFINICIÓN DEL CAMBIO SOLICITADO:
- Módulos o Pantallas a intervenir:
  * Las 20 pantallas operativas del sistema (`soporte.html`, `mis_ordenes.html`, `index.html`, `desempeno_tecnicos.html`, `inventario.html`, `inventario_soporte.html`, `caja.html`, `reportes.html`, `lotes.html`, `admin_roles.html`, `tecnicos.html`, `recepcion_movil.html`, `garantias.html`, `pedidos_repuestos.html`, `repuestos.html`, `historial_entregados.html`, `clientes.html`, `turnos.html`, `importar.html`, `manual.html`).
  * `website_files/sw.js` (Bump de versión a petulap-v24).
  * `CHANGELOG.md` y `docs/05-CHANGELOG.md` (Registro oficial v1.5.4).

- Tipo de Intervención: [Fixed] y [Changed]

- Causa Raíz y Requerimientos de Negocio:
  1. FALTA DE ACCESO A COMPRAS / PEDIDOS DE REPUESTOS EN ESCRITORIO:
     En versiones anteriores se implementó la plataforma ejecutiva de control de compras y trazabilidad de repuestos con motor de SLA (`pedidos_repuestos.html`) y el catálogo de repuestos (`repuestos.html`). En la versión 1.4.0 se añadieron enlaces en el menú acordeón móvil (`#mobile-sidebar`), pero la barra lateral de escritorio (`<aside class="sidebar">`) en 18 pantallas quedó congelada con una plantilla legada de solo 12 ítems.
  2. IDENTIFICACIÓN DE 8 MÓDULOS OPERACIONALES HUÉRFANOS EN DESKTOP:
     En computadoras de escritorio y laptops, los usuarios no pueden acceder desde la barra lateral a:
     - `pedidos_repuestos.html` (*Pedidos / Compras de Repuestos y SLA*)
     - `garantias.html` (*Garantías de Proveedor y Clientes*)
     - `repuestos.html` (*Catálogo de Repuestos*)
     - `historial_entregados.html` (*Historial de Órdenes Entregadas y Terminadas*)
     - `importar.html` (*Importación Masiva Excel/CSV*)
     - `clientes.html` (*Directorio CRM de Clientes*)
     - `turnos.html` (*Planificador de Turnos de Taller*)
     - `manual.html` (*Manual de Usuario y Operaciones*)
  3. HOMOGENEIZACIÓN CANÓNICA DE LA NAVEGACIÓN EN ESCRITORIO:
     El menú de escritorio debe reflejar con máxima exactitud los 20 módulos operacionales organizados en 4 secciones lógicas consistentes con el menú móvil y las categorías de `index.html`.

- Criterio de Aceptación:
  1. Al navegar desde una computadora de escritorio (viewport > 768px), la barra lateral izquierda muestra las 4 secciones estructuradas con los 20 accesos directos, incluyendo claramente "Pedidos de Repuestos" y "Repuestos".
  2. Los usuarios con privilegios limitados siguen viendo únicamente los módulos autorizados en su rol gracias al filtrado automático de `check_auth.js`.
  3. El sidebar colapsado (ancho 80px) oculta los textos y alinea los iconos Phosphor sin romper el diseño responsive.
  4. Los identificadores protegidos (`#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, etc.) se mantienen 100% intactos.
=======================================================

ESPECIFICACIÓN TÉCNICA DEL MENÚ DE ESCRITORIO CANÓNICO:

El bloque `<div class="sidebar-menu">` en `<aside class="sidebar" id="mobile-sidebar">` debe contener exactamente:

```html
<div class="sidebar-menu">
    <a href="index.html" class="menu-item">
        <div class="menu-item-left">
            <i class="ph ph-house"></i>
            <span>Panel de Control</span>
        </div>
    </a>

    <!-- SECCIÓN 1: SOPORTE Y TALLER -->
    <div class="menu-section">
        <span class="menu-title">Soporte y Taller</span>
        <a href="soporte.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-ticket"></i>
                <span>Tickets de Soporte</span>
            </div>
            <div class="status-dot dot-green"></div>
        </a>
        <a href="mis_ordenes.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-kanban"></i>
                <span>Panel Kanban</span>
            </div>
            <div class="status-dot dot-yellow"></div>
        </a>
        <a href="tecnicos.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-users"></i>
                <span>Técnicos</span>
            </div>
            <div class="status-dot dot-yellow"></div>
        </a>
        <a href="recepcion_movil.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-device-mobile"></i>
                <span>App Recepción</span>
            </div>
            <div class="status-dot dot-gray"></div>
        </a>
        <a href="garantias.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-shield-check"></i>
                <span>Garantías</span>
            </div>
            <div class="status-dot dot-blue"></div>
        </a>
        <a href="pedidos_repuestos.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-truck"></i>
                <span>Pedidos Repuestos</span>
            </div>
            <div class="status-dot dot-blue"></div>
        </a>
    </div>

    <!-- SECCIÓN 2: INVENTARIO Y OPERACIONES -->
    <div class="menu-section">
        <span class="menu-title">Inventario y Operaciones</span>
        <a href="inventario.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-archive-box"></i>
                <span>Inventario General</span>
            </div>
            <div class="status-dot dot-red"></div>
        </a>
        <a href="inventario_soporte.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-scan"></i>
                <span>Escaneo / Triaje</span>
            </div>
            <div class="status-dot dot-green"></div>
        </a>
        <a href="repuestos.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-wrench"></i>
                <span>Repuestos</span>
            </div>
            <div class="status-dot dot-orange"></div>
        </a>
        <a href="historial_entregados.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-clock-counter-clockwise"></i>
                <span>Historial Entregados</span>
            </div>
            <div class="status-dot dot-gray"></div>
        </a>
        <a href="importar.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-download-simple"></i>
                <span>Importar Equipos</span>
            </div>
            <div class="status-dot dot-blue"></div>
        </a>
        <a href="caja.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-money"></i>
                <span>Caja / Entregas</span>
            </div>
            <div class="status-dot dot-blue"></div>
        </a>
    </div>

    <!-- SECCIÓN 3: GERENCIA Y CLIENTES -->
    <div class="menu-section">
        <span class="menu-title">Gerencia y Clientes</span>
        <a href="lotes.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-stack"></i>
                <span>Lotes Masivos</span>
            </div>
            <div class="status-dot dot-blue"></div>
        </a>
        <a href="desempeno_tecnicos.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-chart-line-up"></i>
                <span>Desempeño Técnicos</span>
            </div>
            <div class="status-dot dot-blue"></div>
        </a>
        <a href="clientes.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-user-list"></i>
                <span>Clientes y Contactos</span>
            </div>
            <div class="status-dot dot-green"></div>
        </a>
    </div>

    <!-- SECCIÓN 4: REPORTES Y CONFIGURACIÓN -->
    <div class="menu-section">
        <span class="menu-title">Reportes y Configuración</span>
        <a href="reportes.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-chart-bar"></i>
                <span>Reportes y Métricas</span>
            </div>
            <div class="status-dot dot-blue"></div>
        </a>
        <a href="turnos.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-clock"></i>
                <span>Turnos de Taller</span>
            </div>
            <div class="status-dot dot-yellow"></div>
        </a>
        <a href="admin_roles.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-crown"></i>
                <span>Roles y Permisos</span>
            </div>
            <div class="status-dot dot-yellow"></div>
        </a>
        <a href="manual.html" class="menu-item">
            <div class="menu-item-left">
                <i class="ph ph-book-open"></i>
                <span>Manual de Usuario</span>
            </div>
            <div class="status-dot dot-gray"></div>
        </a>
    </div>

    <!-- SECCIÓN 5: ACCIONES DE SISTEMA -->
    <div class="menu-section">
        <div class="menu-title" style="color: var(--danger)">Sistema</div>
        <a href="#" class="menu-item" onclick="clearCache(event)">
            <div class="menu-item-left">
                <i class="ph ph-trash"></i>
                <span>Eliminar Caché</span>
            </div>
        </a>
        <a href="#" class="menu-item" onclick="logout(event)">
            <div class="menu-item-left">
                <i class="ph ph-sign-out"></i>
                <span>Cerrar Sesión</span>
            </div>
        </a>
    </div>
</div>
```

=======================================================
TAREAS DE EJECUCIÓN:
1. Reemplazar de forma quirúrgica y unificada el bloque `<div class="sidebar-menu">` en las 20 pantallas operativas del sistema con la plantilla canónica de 20 módulos.
2. Comprobar que en la versión móvil (`<header class="mobile-header">` y acordeón móvil) cada módulo conserve su icono y texto semántico sin romper la navegación de pantalla táctil.
3. Validar que la regla de `check_auth.js` (`document.querySelectorAll('a[href$=".html"]')`) filtre y oculte de forma reactiva los ítems según los permisos del rol del usuario logueado.
4. Incrementar la versión del Service Worker en `website_files/sw.js` de `'petulap-v23'` a `'petulap-v24'`.
5. Actualizar `CHANGELOG.md` y `docs/05-CHANGELOG.md` bajo la versión `[1.5.4]` con el "Por Qué" de la unificación.
6. Ejecutar el script `python private_scripts/sync_ftp_production.py` para subir los cambios a producción.
7. Realizar commit y push en Git:
   `git add .`
   `git commit -m "feat(navegacion): v1.5.4 unificacion de menu de escritorio con compras de repuestos y 20 modulos canonicos"`
   `git push origin feature/notificaciones-garantias`

ENTREGABLES:
- Las 20 pantallas actualizadas con el menú canónico completo.
- Validación de que `pedidos_repuestos.html` y los otros 7 módulos huérfanos se visualizan perfectamente en desktop y móvil.
- `sw.js` en versión v24 y bitácoras de changelog actualizadas.
