# PROMPT PROFESIONAL: Logo Oficial, Favicon y Rediseño del Portal de Seguimiento (v1.5.3)

---

## ROL
Actúa como Ingeniero de Software Fullstack Senior y Diseñador de Producto para el proyecto **Petulap SST**.

---

## CONTEXTO OBLIGATORIO DE ARQUITECTURA
Antes de modificar cualquier archivo, revisa:
- `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` (7 reglas de oro)
- `docs/03-FRONTEND.md` (Sección 7: Cosas Frágiles — identificadores protegidos)
- `docs/12-SISTEMA-DE-DISENO-UI-UX.md` (Sistema de diseño, tokens, paleta)
- `docs/05-CHANGELOG.md` (Versión actual: **1.5.2** — este cambio será **[1.5.3]**)

---

## REGLAS INNEGOCIABLES
1. **Jerarquía CSS**: `tokens.css` -> `styles.css` -> `dashboard.css` — jamás TailwindCSS ni CSS externo no aprobado.
2. **Identificadores protegidos** (no renombrar ni borrar): `#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, `#lbl-tecnico`, `.fab-chat`, `.notification-btn`.
3. **Hrefs exactos** sin dependencias externas no aprobadas (Google Fonts y Phosphor Icons SI son aprobados).
4. **Anti-flicker**: El script de detección de `petulap-theme` debe ser el primer `<script>` dentro del `<head>`.
5. **Sin backend nuevo**: Los cambios de esta tarea son 100% frontend + assets estáticos.
6. La página `consulta.html` es **pública** (sin sesión), accedida principalmente desde **WhatsApp en móviles**. Optimizar para carga rápida.

---

## ASSETS DISPONIBLES (ya descargados en el repo)
- **Logo:** `website_files/img/logo-petulap.png` — Logotipo horizontal azul "PetuLap" con ícono geométrico.
- **Favicon:** `website_files/img/favicon-petulap.png` — Ícono cuadrado azul (símbolo solo).

Ambos deben subirse al servidor FTP en `public_html/img/`.

---

## INFORMACIÓN DE LA EMPRESA (para el portal público)

**Petulap S.A.C.** — Arequipa, Perú
- Importación y venta de laptops seminuevos de EE.UU. y Europa
- **Garantía:** 6 meses en equipos + 3 años de soporte técnico
- **Eslogan:** "Laptops Importadas desde EE.UU. & Europa. Con garantía de 6 meses y servicio técnico por 3 años."
- **WhatsApp:** +51 983 396 137
- **Instagram:** @petulap_computadoras | **Facebook:** PetulapComputadoras
- **Oficina 1:** Av. Ejército 314, 2do piso, Yanahuara — Lun-Vie 10:30am–7:30pm, Sáb 10:30am–6pm
- **Oficina 2:** León XIII Mza A-4, 1er piso, Cayma — mismo horario

---

## MÓDULOS A INTERVENIR

1. `website_files/consulta.html` — [REDESIGN MAYOR: portal público de clientes]
2. Todos los `website_files/*.html` del sistema interno — [BATCH: favicon + logo en sidebar]
3. `website_files/sw.js` — [Bump caché a petulap-v23 + precaché de assets img/]
4. `docs/05-CHANGELOG.md` y `CHANGELOG.md` — [Registro versión 1.5.3]

---

## DETALLE DE CAMBIOS

---

### CAMBIO 1: `consulta.html` — Rediseño integral del portal público de clientes

Esta pantalla es la **cara pública de Petulap**. Los clientes llegan desde el enlace de WhatsApp. El diseño actual es básico y no genera confianza. Rediseñar íntegramente manteniendo toda la lógica JS existente.

#### 1.1. HEAD — Favicon y título
```html
<link rel="icon" type="image/png" href="img/favicon-petulap.png">
<link rel="apple-touch-icon" href="img/favicon-petulap.png">
<title>Seguimiento de Servicio — PetuLap</title>
```

#### 1.2. HEADER con logo oficial
Reemplazar el texto/ícono actual por el logo real:
```html
<header class="portal-header">
  <img src="img/logo-petulap.png" alt="PetuLap" class="portal-logo">
  <span class="portal-header-tag">Portal de Clientes</span>
</header>
```
CSS: header fijo, fondo blanco con sombra sutil, logo de 38px de alto.

#### 1.3. SECCIÓN HERO (encima del formulario)
```
[Logo grande centrado]
H1: "Seguimiento de tu Servicio Técnico"
Subtexto: "Ingresa tu número de ticket para ver el estado de tu reparación en tiempo real."
Badges de confianza:
  [icono ph-shield-check] 6 meses de garantía
  [icono ph-wrench] 3 años de soporte técnico
  [icono ph-seal-check] Empresa formal con boleta/factura
```

#### 1.4. FORMULARIO DE BÚSQUEDA (UX mejorada)
- Input ticket: ícono `ph-ticket`, placeholder `"Ej: ST-20260901-001 o TAR-2026-010"`
- Input DNI: ícono `ph-identification-card`, placeholder `"Tu DNI (opcional)"`
- Botón: fondo `#3B82F6`, `ph-magnifying-glass`, texto "Consultar Estado"
- Nota de ayuda: "¿No tienes tu ticket? Escríbenos al WhatsApp y te lo enviamos."

#### 1.5. STEPPER DE 5 PASOS (reemplaza el actual de 4)

| Paso | Ícono Phosphor | Etiqueta pública | Estados internos |
|------|---------------|-----------------|-----------------|
| 1 | `ph-download-simple` | Recibido | `PENDIENTE` |
| 2 | `ph-magnifying-glass` | En Diagnóstico | `EN_DIAGNOSTICO` |
| 3 | `ph-wrench` | En Reparación | `EN_REPARACION`, `ESPERANDO_REPUESTO` |
| 4 | `ph-check-circle` | Listo para Recoger | `LISTO_PARA_RECOGER`, `COMPLETADO`, `LISTO_PARA_ENTREGA` |
| 5 | `ph-package` | Entregado | `ENTREGADO` |

Estilo: Pasos completados en verde, paso activo en azul de marca con efecto de pulso CSS. Línea conectora animada. En móvil: disposición vertical (columna).

Actualizar la función `mostrarResultados(data)` en JS para mapear los estados correctamente a los 5 pasos.

#### 1.6. TARJETA DE RESULTADO (premium)
Reemplazar `info-box` por una tarjeta con:
- **Header de tarjeta**: número de ticket en grande (color de marca) + badge de estado (colores semánticos: amarillo=pendiente, azul=diagnóstico, naranja=reparación, verde=listo/entregado)
- **Grilla 2col / 1col en móvil**:
  - Equipo: `[icono ph-laptop]` + texto
  - Cliente: `[icono ph-user]` + nombre
  - Ingreso: `[icono ph-calendar]` + fecha
  - Motivo: `[icono ph-note]` + texto
- **Sección técnica** (solo si existen): Diagnóstico y Solución en bloque destacado
- **Badge garantía**: `[icono ph-shield-check]` "Atención por Garantía" en verde si `en_garantia == 1`
- **CTA "Listo para recoger"**: Botón verde WhatsApp: "¡Mi equipo está listo! Escribir al taller" -> `https://wa.me/51983396137`

#### 1.7. SECCIÓN INFORMATIVA DEL TALLER (siempre visible)
Debajo del resultado (o del formulario si no hay resultado), mostrar tarjetas de información:

**Tarjeta 1 — Horarios:**
- `[ph-clock]` Lun–Vie: 10:30 am – 7:30 pm
- `[ph-clock]` Sábados: 10:30 am – 6:00 pm

**Tarjeta 2 — Oficinas:**
- `[ph-map-pin]` Av. Ejército 314, 2do piso, Yanahuara
- `[ph-map-pin]` León XIII Mza A-4, 1er piso, Cayma

**Tarjeta 3 — Garantía y Pagos:**
- `[ph-shield-check]` 6 meses de garantía en equipos
- `[ph-wrench]` 3 años de soporte técnico
- `[ph-credit-card]` Efectivo, transferencia y tarjetas
- `[ph-file-text]` Boleta y factura

**Tarjeta 4 — Contacto:**
- `[ph-whatsapp-logo]` +51 983 396 137 (enlace `wa.me`)
- `[ph-instagram-logo]` @petulap_computadoras
- `[ph-facebook-logo]` PetulapComputadoras

#### 1.8. BOTÓN FLOTANTE DE WHATSAPP
```html
<a href="https://wa.me/51983396137" target="_blank" class="fab-whatsapp" aria-label="Escribir al WhatsApp de Petulap">
    <i class="ph-bold ph-whatsapp-logo"></i>
</a>
```
CSS: posición `fixed`, bottom `24px`, right `24px`, z-index `9999`, fondo `#25D366`, color blanco, border-radius `50%`, size `56px`, sombra y efecto hover scale.

#### 1.9. FOOTER
```html
<footer class="portal-footer">
  <img src="img/logo-petulap.png" alt="PetuLap" style="height:28px; filter: grayscale(1); opacity:.7">
  <p>Dudas: <a href="https://wa.me/51983396137">+51 983 396 137</a> | 
     <a href="https://instagram.com/petulap_computadoras" target="_blank"><i class="ph ph-instagram-logo"></i></a>
     <a href="https://facebook.com/PetulapComputadoras" target="_blank"><i class="ph ph-facebook-logo"></i></a>
  </p>
  <p>© 2026 Petulap S.A.C. | Arequipa, Perú</p>
</footer>
```

---

### CAMBIO 2: Favicon + Logo en TODAS las páginas del sistema interno

En TODOS los archivos `website_files/*.html` del sistema (excluyendo `consulta.html` que ya se trata arriba y `login.html`):

**2.1. En el `<head>` — Reemplazar favicon antiguo:**
```html
<!-- ELIMINAR -->
<link rel="apple-touch-icon" href="icon.jpg">

<!-- AGREGAR -->
<link rel="icon" type="image/png" href="img/favicon-petulap.png">
<link rel="apple-touch-icon" href="img/favicon-petulap.png">
```

**2.2. En el sidebar — Reemplazar logo en texto por imagen:**
```html
<!-- ANTES (en cada página) -->
<div class="logo-container">
    <div class="logo-icon">P</div>
    <span>PETULAP S.A.C.</span>
</div>

<!-- DESPUÉS -->
<div class="logo-container">
    <img src="img/logo-petulap.png" alt="PetuLap SST" class="sidebar-logo">
</div>
```

**2.3. CSS en `css/dashboard.css` — Agregar al final:**
```css
/* Logo oficial en sidebar */
.sidebar-logo {
    height: 30px;
    width: auto;
    max-width: 140px;
    object-fit: contain;
    filter: brightness(0) invert(1);
}
[data-theme="light"] .sidebar-logo {
    filter: none;
}
```

**Páginas del sistema interno a actualizar (batch):**
`soporte.html`, `mis_ordenes.html`, `desempeno_tecnicos.html`, `historial_entregados.html`, `lotes.html`, `inventario_soporte.html`, `pedidos_repuestos.html`, `garantias.html`, `clientes.html`, `repuestos.html`, `tecnicos.html`, `turnos.html`, `caja.html`, `reportes.html`, `importar.html`, `admin_roles.html`, `manual.html`, `recepcion_movil.html`, `inventario.html`, `index.html`

---

### CAMBIO 3: `sw.js` — Bump a petulap-v23 + precaché de imágenes

```js
// Service Worker - Petulap PWA v23 (Logo oficial, favicon y redesign portal consulta.html)
var CACHE_NAME = 'petulap-v23';
var ASSETS = [
    // ... assets existentes ...
    'img/logo-petulap.png',
    'img/favicon-petulap.png',
    'consulta.html'
];
```

---

### CAMBIO 4: Changelog `[1.5.3]`

Registrar en `docs/05-CHANGELOG.md` y `CHANGELOG.md` bajo `[1.5.3] - Septiembre 2026`:

**Causa Raíz:** La plataforma no mostraba el logo oficial de Petulap en ninguna pantalla (favicon, sidebar ni portal público), lo que generaba desconfianza en clientes externos que acceden al portal de consulta desde WhatsApp. El portal `consulta.html` tenía un diseño básico sin información del taller, sin stepper de 5 pasos acorde a los estados reales del sistema, sin CTA de contacto y sin la identidad visual de la marca.

**Added:**
- Logo oficial `img/logo-petulap.png` y favicon `img/favicon-petulap.png` en toda la plataforma.
- Sección informativa del taller (horarios, oficinas, garantía, pagos, contacto) en `consulta.html`.
- Botón flotante WhatsApp en `consulta.html`.
- Footer de marca con links a redes sociales en `consulta.html`.
- Precaché de assets `img/` en Service Worker `petulap-v23`.

**Changed:**
- `consulta.html`: Rediseño integral del portal público de clientes con identidad de marca, stepper de 5 pasos, tarjeta de resultado premium y sección de confianza.
- `css/dashboard.css`: Agregada clase `.sidebar-logo` con filtro CSS para modo oscuro/claro.
- Todos los `.html` del sistema: Favicon actualizado a `img/favicon-petulap.png`.
- Logo en sidebar de todas las pantallas internas cambiado de `<div class="logo-icon">P</div>` a `<img class="sidebar-logo">`.

---

## DESPLIEGUE

1. Subir `website_files/img/` completa por FTP a `public_html/img/`
2. Ejecutar `python private_scripts/sync_ftp_production.py`
3. Git commit y push:
```
git add .
git commit -m "feat(marca): v1.5.3 logo oficial, favicon y rediseno portal consulta.html para clientes"
git push origin feature/notificaciones-garantias
```

---

## VERIFICACIÓN (Pruebas de Humo)

1. `https://petulap.store/consulta.html` en móvil sin sesión → carga correctamente, sin redirección a login.
2. Ingresar ticket real → stepper muestra el paso correcto (verificar los 5 pasos).
3. Cuando estado es `LISTO_PARA_RECOGER` → aparece el botón verde de WhatsApp.
4. Favicon visible en pestaña del navegador en `consulta.html` y en `soporte.html`.
5. Logo en sidebar visible en `mis_ordenes.html` (blanco en modo oscuro, azul en modo claro).
6. Botón flotante WhatsApp visible sin obstruir el formulario en iPhone SE (375px).
7. `#mobile-menu-toggle`, `#mobile-sidebar`, `.fab-chat`, `.notification-btn` intactos en páginas internas.

---

*Prompt generado por Antigravity para el proyecto Petulap SST — Septiembre 2026.*
ROL: Actúa como Ingeniero de Software Fullstack Senior para el proyecto Petulap SST.

CONTEXTO OBLIGATORIO DE ARQUITECTURA:
Consulta docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md, docs/05-CHANGELOG.md (versión 1.5.2) y docs/12-SISTEMA-DE-DISENO-UI-UX.md.

REGLAS GENERALES INNEGOCIABLES:
1. Jerarquía CSS estricta: tokens.css -> styles.css -> dashboard.css.
2. Identificadores protegidos: NO renombrar ni borrar #mobile-menu-toggle, #mobile-sidebar, #lbl-nombre, #lbl-tecnico, etc.
3. Hrefs exactos y sin dependencias externas.
4. Preservar intactas las mejoras de tareas multi-técnico y tiempo real recién aplicadas.

=======================================================
DEFINICIÓN DEL CAMBIO SOLICITADO:
- Módulos a intervenir:
  * website_files/api/desempeno.php
  * website_files/desempeno_tecnicos.html
  * website_files/sw.js
- Tipo de Intervención: [Changed] y [Fixed]

- Causa Raíz y Requerimientos de Negocio:
  1. AJUSTE AL HORARIO REAL DE TALLER (10:00 AM A 08:00 PM):
     - La cuadrícula anterior estaba desfasada con horario de oficina antigua (08:00 a 18:00).
     - El horario operativo real de Petulap SST es de 10:00 AM a 08:00 PM (20:00).
     - Las 11 columnas horarias de la matriz deben configurarse exactamente para los slots:
       [ 10:00 ] [ 11:00 ] [ 12:00 ] [ 13:00 ] [ 14:00 ] [ 15:00 ] [ 16:00 ] [ 17:00 ] [ 18:00 ] [ 19:00 ] [ 20:00 ]
  
  2. RANURA DE HORAS EXTRAS / FUERA DE TURNO (🌙 EXTRAS):
     - Al final de las 11 horas comerciales (después de las 20:00), incorporar la columna "🌙 Extras" (o "Ext").
     - En api/desempeno.php (get_weekly_heatmap):
       * Si una tarea o actividad se registra fuera del turno regular (antes de las 10:00 AM o después de las 20:00, como la tarea TAR-2026-038 a las 00:27), NO debe descartarse.
       * Debe computarse en la ranura de 'extras' del día correspondiente, clasificando con nivel verde o violeta (.level-instant si es <1m).
     - En desempeno_tecnicos.html:
       * Renderizar la cabecera con las columnas: 10h, 11h, 12h, 13h, 14h, 15h, 16h, 17h, 18h, 19h, 20h y 🌙 Extras.
       * El tooltip contextual (#heatmap-tooltip) sobre la celda de Extras debe indicar: "🌙 Actividad fuera de horario (ej. 00:27) | Ticket: ...".

  3. ZONA HORARIA PERÚ OBLIGATORIA (AMERICA/LIMA):
     - En api/desempeno.php (y endpoints vinculados):
       * Fijar date_default_timezone_set('America/Lima'); en PHP.
       * Ejecutar $conn->query("SET time_zone = '-05:00'"); en la conexión MySQL.
       * Previene que a altas horas de la noche el servidor de hosting salte al día siguiente por desfase horario.

  4. SERVICE WORKER:
     - Incrementar versión de caché en sw.js a 'petulap-v23'.

- Criterio de Aceptación:
  - La matriz semanal muestra la franja horaria de 10:00 a 20:00 más la columna de "🌙 Extras".
  - Tareas registradas a la medianoche (como TAR-2026-038 a las 00:27) o fuera de turno se pintan en la columna "🌙 Extras" con su color y tooltip correspondiente, sin dejar el día en blanco.
=======================================================

TAREAS DE EJECUCIÓN:
1. En api/desempeno.php:
   - Configurar zona horaria America/Lima (UTC-5).
   - Actualizar el rango de slots a 10..20 en get_weekly_heatmap, canalizando actividades <10:00 o >20:00 hacia la clave 'extras'.
2. En desempeno_tecnicos.html:
   - Actualizar las cabeceras de horas de la matriz (.heatmap-header): 10:00 a 20:00 y "🌙 Extras".
   - Ajustar el grid CSS para acomodar los 11 slots regulares + 1 slot de extras.
3. En sw.js: Incrementar a 'petulap-v23'.
4. Actualizar docs/05-CHANGELOG.md y CHANGELOG.md bajo [1.5.3].

ENTREGABLES:
- Código de api/desempeno.php, desempeno_tecnicos.html y sw.js.
- Entrada en CHANGELOG.md versión [1.5.3].
