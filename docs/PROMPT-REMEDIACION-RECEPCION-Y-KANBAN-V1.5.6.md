# PROMPT MAESTRO: Remediación Integral de Creación de Órdenes en Recepción y Sincronización de Estados en Tablero Kanban (v1.5.6)

> **Documento de Gobernanza Técnica y Ejecución Asistida por IA**  
> **Basado en:** `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` y `docs/00-INICIALIZACION-EJECUTOR-DESARROLLADOR.MD`  
> **Sistema:** Petulap SST (Soporte Técnico, Taller, Lotes e Inventario)  
> **Versión del Sistema:** Petulap SST v1.5.5 $\rightarrow$ **v1.5.6**  
> **Caché Service Worker:** `petulap-v25` $\rightarrow$ `petulap-v26`  
> **Objetivo:** Resolver el bloqueo crítico del botón "Guardando..." en `recepcion_movil.html` (desajuste fatal de parámetros en `api/soporte.php` y TypeError en frontend) y corregir la discrepancia de tickets fantasma en el Kanban de `mis_ordenes.html` (contador "3 órdenes" vs 1 visible).

---

## 1. ROL
Actúa como **Ingeniero de Software Fullstack Senior y Desarrollador Ejecutor** para el proyecto **Petulap SST**, bajo las directrices y estándares de `docs/00-INICIALIZACION-EJECUTOR-DESARROLLADOR.MD`.

---

## 2. CONTEXTO OBLIGATORIO DE ARQUITECTURA
Antes de generar o modificar cualquier línea de código, consulta obligatoriamente los documentos rectores en `docs/`:
- `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` (Ciclo de 5 pasos y 7 Reglas Innegociables).
- `docs/01-ARQUITECTURA.md` (Flujo de datos general y modelo cliente-servidor).
- `docs/02-BACKEND.md` (Endpoints de la API y modelo relacional de las 17 tablas MySQL).
- `docs/03-FRONTEND.md` (Catálogo de pantallas y Sección 7: *Cosas Frágiles* con identificadores protegidos).
- `docs/04-PREVENCION-SQL-INJECTION.md` (Inmunización contra SQLi y tipado en `bind_param`).
- `docs/05-CHANGELOG.md` (Historial oficial bajo formato SemVer 2.0.0 y *Keep a Changelog*).
- `docs/08-CHECKLIST-TESTING.md` (Protocolo de Smoke Tests pre-despliegue).
- `docs/12-SISTEMA-DE-DISENO-UI-UX.md` (Tokens semánticos, `.live-chip`, `.pulse-dot` y componentes SaaS).

---

## 3. LAS 7 REGLAS DE ORO INNEGOCIABLES (COSAS FRÁGILES)
Cualquier alteración a estas reglas causará el rechazo inmediato de la entrega:

1. **Jerarquía CSS estricta en `<head>`:**
   `<link>` en orden inalterable: `tokens.css` $\rightarrow$ `styles.css` $\rightarrow$ `dashboard.css`.
2. **Identificadores DOM protegidos intactos:**
   Prohibido renombrar o borrar: `#mobile-menu-toggle`, `#mobile-sidebar`, `#lbl-nombre`, `#lbl-tecnico`, `.fab-chat`, `.notification-btn`, `#notif-dropdown`, `#notif-badge`.
3. **Coincidencia milimétrica de `href`:**
   Enlaces en minúsculas, sin `./` ni mayúsculas (ej: `href="recepcion_movil.html"`, `href="mis_ordenes.html"`).
4. **Anti-flicker de modo oscuro intacto:**
   Primer script síncrono al inicio del `<head>` leyendo `localStorage.getItem('petulap-theme')`.
5. **Consultas SQL 100% preparadas:**
   Uso exclusivo de `$stmt = $db->prepare(...)` y `bind_param()` con tipado riguroso. Cero concatenación de variables en sentencias SQL.
6. **Seguridad Backend Obligatoria:**
   Validación estricta de sesión activa (`$_SESSION['user_id']`) y control de acceso en `website_files/api/*.php`.
7. **Zero Dependencias Externas:**
   Vanilla JavaScript ES6+ puro, CSS nativo con `@media (max-width: 768px)`, Phosphor Icons y Google Fonts Inter. Prohibido agregar frameworks (Tailwind, Bootstrap, jQuery, React) ni herramientas de compilación (`npm build`).

---

## 4. ANÁLISIS DE CAUSA RAÍZ: ¿POR QUÉ OCURRIERON LOS FALLOS?

### Fallo A: Botón colgado en "Guardando..." en `https://petulap.store/recepcion_movil.html`
Al pulsar el botón `GENERAR ORDEN`, la interfaz se congela con:
`<button class="btn btn-primary" ... disabled=""><i class="ph ph-hourglass"></i> Guardando...</button>`
sin registrar el ticket ni retornar feedback al usuario.

**Causas Raíz Detectadas:**
1. **Desincronización Fatal en Sentencia Preparada Backend (`api/soporte.php`, `case "crear"`):**
   - En la consulta `INSERT INTO soporte_tecnico`:
     ```php
     $stmt = $db->prepare("INSERT INTO soporte_tecnico (numero_atencion, cliente_id, equipo_codigo, equipo_serie, equipo_descripcion, es_externo, motivo_ingreso, prioridad, fecha_estimada, tecnico_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
     ```
     La sentencia tiene **10 columnas** y **10 placeholders `?`**.
   - Sin embargo, en el `bind_param`:
     ```php
     $stmt->bind_param("sisssissssi", $numero, $cliente_id, $eq_cod, $eq_ser, $eq_desc, $es_ext, $motivo, $prior, $fecha_est, $tiempo_est, $tec_id);
     ```
     El string de tipos contiene **11 caracteres** (`"sisssissssi"`) y se pasan **11 variables** (incluyendo `$tiempo_est`, agregado en v1.4.6).
   - **Efecto catastrófico:** PHP / MySQLi arroja un error fatal no capturado:
     `mysqli_sql_exception: Number of elements in type string doesn't match number of parameters in prepared statement`
     La API responde con código HTTP 500 o HTML con traza de error PHP.
2. **Ausencia de `try...catch` en `fetch` de `crearOrden()` en `recepcion_movil.html`:**
   - La respuesta no-JSON hace que `.then(r => r.json())` lance un `SyntaxError: Unexpected token < in JSON at position 0`.
   - Al no existir captura de excepciones, el hilo de ejecución de JavaScript se detiene abruptamente: el botón queda permanentemente `disabled = true`, el texto congelado en `Guardando...` y jamás se restaura ni muestra mensaje de error.
3. **TypeError Fatal en Renderizado de Pantalla de Éxito (`recepcion_movil.html`):**
   - En caso de respuesta exitosa, el código ejecutaba:
     ```javascript
     document.querySelectorAll('.step-card').forEach(c => c.classList.remove('active'));
     document.getElementById('step-exito').classList.add('active');
     document.querySelector('.stepper').style.display = 'none';
     ```
   - Las tarjetas en `recepcion_movil.html` tienen la clase `.card` con IDs `#step-1`, `#step-2`, `#step-3` (no `.step-card`).
   - `#step-exito` tiene la regla inline `style="display:none;"`, por lo que añadirle `.active` no la hacía visible sin alterar `display = 'block'`.
   - El selector `document.querySelector('.stepper')` es `null`, provocando `Uncaught TypeError: Cannot read properties of null (reading 'style')`, congelando la UI incluso ante peticiones válidas.

---

### Fallo B: Discrepancia de Conteo en Tablero Kanban (`https://petulap.store/mis_ordenes.html`)
El usuario observa en la pestaña *Clientes* el chip: `"3 órdenes en taller"`, pero en las 4 columnas del tablero solo se visualiza 1 tarjeta en la columna *ESPERANDO REPUESTO*, mientras las demás columnas muestran `(0)`.

**Causas Raíz Detectadas:**
1. **Filtro Incompleto en Carga de Tablero (`mis_ordenes.html`, función `cargarMisOrdenes`):**
   - El filtrado de tickets activos se implementó de la siguiente forma:
     ```javascript
     misTickets = misTickets.filter(t => t.estado !== 'ENTREGADO');
     ```
     Solo excluye `ENTREGADO`. Si existen tickets con estado `ELIMINADO` (borrado lógico de la acción `eliminar` en `api/soporte.php`), `CANCELADO` o estados no estándar, permanecen dentro de `misTickets` y elevan el conteo general `#txt-total-active` (`${misTickets.length} órdenes en taller`).
2. **Falta de Manejo en el Reparto de Columnas (`mis_ordenes.html`):**
   - Las columnas activas solo clasifican cuatro estados exactos:
     ```javascript
     const cols = { pend:[], diag:[], rep:[], listo:[] };
     misTickets.forEach(t => {
       if (t.estado === 'PENDIENTE') cols.pend.push(t);
       else if (t.estado === 'EN_DIAGNOSTICO' || t.estado === 'EN_REPARACION') cols.diag.push(t);
       else if (t.estado === 'ESPERANDO_REPUESTO') cols.rep.push(t);
       else if (t.estado === 'LISTO_PARA_RECOGER' || t.estado === 'ENTREGADO') cols.listo.push(t);
     });
     ```
   - Los 2 tickets con estados `ELIMINADO`, `CANCELADO` o con inconsistencias no coinciden con ningún `if`, quedando como **órdenes fantasma**: se cuentan en el total superior, pero no se dibujan en ninguna columna.
3. **Backend sin Exclusión de Borrados Lógicos (`api/soporte.php`, `case "list"`):**
   - Cuando se llama a `api/soporte.php?action=list` sin parámetro de estado, la cláusula SQL no excluía `st.estado != 'ELIMINADO'`, enviando registros eliminados a los clientes.

---

## 5. DEFINICIÓN DEL CAMBIO SOLICITADO (v1.5.6)

- **Módulos a intervenir quirúrgicamente:**
  1. `website_files/api/soporte.php` (Sincronización de columnas en `INSERT`, exclusión de `ELIMINADO` en `action=list`).
  2. `website_files/recepcion_movil.html` (Manejo robusto con `try...catch` en `crearOrden`, restauración de botón ante fallos, transición visual limpia a `#step-exito`).
  3. `website_files/mis_ordenes.html` (Filtrado estricto de tickets activos excluyendo `ENTREGADO`, `CANCELADO` y `ELIMINADO`, fallback de normalización de estados y consistencia del contador).
  4. `website_files/sw.js` (Incremento de versión de Service Worker: de `petulap-v25` a `petulap-v26`).
  5. `CHANGELOG.md` y `docs/05-CHANGELOG.md` (Registro oficial bajo estándar *Keep a Changelog* v1.5.6).
  6. `docs/02-BACKEND.md` (Documentación del campo `tiempo_estimado` en `soporte_tecnico` y exclusión de `ELIMINADO`).

- **Tipo de Intervención:** `[Fixed]`, `[Security]`.

- **Criterios de Aceptación y Reglas de Negocio:**
  1. **Generación de Ticket Ininterrumpida:** Al presionar `GENERAR ORDEN` en `recepcion_movil.html`, el ticket debe guardarse exitosamente en la base de datos sin errores 500 de MySQLi.
  2. **Transición a Pantalla de Éxito:** Tras guardar, deben ocultarse los pasos del formulario (`#step-1`, `#step-2`, `#step-3`), mostrarse `#step-exito` con el número correlativo generado (ej. `ST-20260910-001`), el enlace directo a WhatsApp y el botón para imprimir sticker.
  3. **Resiliencia ante Errores de Red/Backend:** Si ocurre cualquier error, el botón de generar orden debe re-habilitarse de inmediato, volver a su estado original (`💾 GENERAR ORDEN`) y disparar un `toast` explicativo. Cero bloqueos infinitos con "Guardando...".
  4. **Consistencia Absoluta de Contadores en Kanban:** El número reportado en el chip ejecutivo `#txt-total-active` debe coincidir exactamente con la suma de las tarjetas visibles en las 4 columnas (`c-pend + c-diag + c-rep + c-listo`).
  5. **Exclusión de Tickets Borrados o Cancelados:** Ni tickets `ELIMINADO` ni `CANCELADO` deben figurar en el tablero activo ni alterar el total de órdenes en taller.

---

## 6. PLAN DETALLADO DE EJECUCIÓN PASO A PASO

### Paso 1: Backend `website_files/api/soporte.php`
1. **Corregir la sentencia `INSERT INTO soporte_tecnico` en `case "crear"`:**
   - Incorporar explícitamente el campo `tiempo_estimado` en la lista de columnas y su correspondiente placeholder `?`:
     ```php
     $stmt = $db->prepare("INSERT INTO soporte_tecnico (numero_atencion, cliente_id, equipo_codigo, equipo_serie, equipo_descripcion, es_externo, motivo_ingreso, prioridad, fecha_estimada, tiempo_estimado, tecnico_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
     ```
   - Validar que coincida exactamente con `$stmt->bind_param("sisssissssi", $numero, $cliente_id, $eq_cod, $eq_ser, $eq_desc, $es_ext, $motivo, $prior, $fecha_est, $tiempo_est, $tec_id);` (11 columnas, 11 `?`, 11 variables).
2. **Excluir por defecto registros con estado `ELIMINADO` en `case "list"`:**
   - En el filtrado de `api/soporte.php?action=list`:
     ```php
     if ($estado === "") {
         $whereClauses[] = "st.estado != 'ELIMINADO'";
     }
     ```
   - Previene que registros borrados con soft-delete sean enviados al frontend.

---

### Paso 2: Frontend `website_files/recepcion_movil.html`
1. **Blindar `crearOrden()` con manejo de errores robusto:**
   ```javascript
   async function crearOrden() {
     const btn = document.querySelector('#step-3 button.btn-primary');
     if (!btn) return;
     
     const textoOriginal = btn.innerHTML;
     btn.disabled = true;
     btn.innerHTML = '<i class="ph ph-hourglass"></i> Guardando...';

     try {
       // 1. Procesar / Crear Cliente
       const clienteBody = {
         dni: document.getElementById('f-dni').value.trim(),
         nombre: document.getElementById('f-nombre').value.trim(),
         telefono: document.getElementById('f-tel').value.trim(),
         tipo: 'cliente'
       };

       let clienteId = null;
       try {
         const cRes = await fetch('api/personas.php?action=crear', { 
           method: 'POST', 
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify(clienteBody) 
         }).then(r => r.json());
         
         if (cRes.ok && cRes.id) {
           clienteId = cRes.id;
         } else {
           const bRes = await fetch(`api/personas.php?action=buscar_dni&dni=${encodeURIComponent(clienteBody.dni)}`).then(r => r.json());
           if (bRes.ok && bRes.data) clienteId = bRes.data.id;
         }
       } catch(errCli) {
         console.warn("Fallo al crear cliente, intentando buscar por DNI:", errCli);
       }

       if (!clienteId) {
         showToast('Error al procesar el cliente. Verifica DNI o Nombre.');
         btn.disabled = false;
         btn.innerHTML = textoOriginal;
         return;
       }

       // 2. Preparar Orden de Soporte
       const eqCodigo = document.getElementById('f-eq-codigo').value.trim();
       const eqNombre = document.getElementById('f-eq-nombre').value.trim();
       const falla = document.getElementById('f-falla').value.trim() || 'Revision General';
       let idTecnicoFinal = isAdmin ? document.getElementById('sel-admin-tecnico').value : tecnicoId;

       const sopBody = {
         cliente_id: parseInt(clienteId),
         es_externo: eqCodigo ? 0 : 1,
         equipo_codigo: eqCodigo,
         equipo_serie: equipoSerie || '',
         equipo_descripcion: eqNombre,
         motivo_ingreso: falla,
         prioridad: 'NORMAL',
         fecha_estimada: null,
         tecnico_id: idTecnicoFinal ? parseInt(idTecnicoFinal) : null
       };

       // 3. Enviar al Backend con Control de Fallos
       const sRes = await fetch('api/soporte.php?action=crear', { 
         method: 'POST', 
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify(sopBody) 
       }).then(r => {
         if (!r.ok) throw new Error('Error HTTP en servidor (' + r.status + ')');
         return r.json();
       });

       if (sRes.ok) {
         if (typeof playSound === 'function') playSound('notification');

         const numero = sRes.numero || 'ST-OK';
         const nombreCliente = document.getElementById('f-nombre').value.trim();
         const telCliente = document.getElementById('f-tel').value.trim();
         const eqDesc = document.getElementById('f-eq-nombre').value.trim();

         document.getElementById('exito-numero').textContent = numero;
         document.getElementById('exito-cliente').textContent = nombreCliente + ' - ' + eqDesc;

         const baseUrl = window.location.origin + window.location.pathname.replace('recepcion_movil.html', '');
         const msgWP = `Hola ${nombreCliente}, somos PETULAP 🔧\n\nTu equipo (*${eqDesc}*) ha ingresado correctamente.\n\n📋 *Ticket:* ${numero}\n\nPuedes rastrear tu equipo aqui:\nhttps://petulap.store/consulta.html?ticket=${encodeURIComponent(numero)}\n\nGracias por confiar en nosotros! 🙌`;
         document.getElementById('btn-wp-exito').href = `https://wa.me/51${telCliente}?text=${encodeURIComponent(msgWP)}`;

         document.getElementById('btn-sticker-exito').dataset.numero = numero;
         document.getElementById('btn-sticker-exito').dataset.cliente = nombreCliente;

         // Transición limpia de pasos
         ['step-1', 'step-2', 'step-3'].forEach(id => {
           const el = document.getElementById(id);
           if (el) el.style.display = 'none';
         });
         const stepExito = document.getElementById('step-exito');
         if (stepExito) {
           stepExito.style.display = 'block';
           stepExito.scrollIntoView({ behavior: 'smooth' });
         }
       } else {
         showToast('Error al crear orden: ' + (sRes.msg || 'Respuesta no exitosa'));
         btn.disabled = false;
         btn.innerHTML = textoOriginal;
       }
     } catch (err) {
       console.error("Error crítico en crearOrden:", err);
       showToast('Error de conexión o fallo en servidor al guardar orden.');
       btn.disabled = false;
       btn.innerHTML = textoOriginal;
     }
   }
   ```

---

### Paso 3: Frontend `website_files/mis_ordenes.html`
1. **Depurar y blindar `cargarMisOrdenes()`:**
   - Excluir estados no activos de forma contundente:
     ```javascript
     misTickets = misTickets.filter(t => !['ENTREGADO', 'CANCELADO', 'ELIMINADO'].includes(t.estado));
     ```
   - Normalizar la distribución en columnas asegurando que estados análogos caigan en su columna correspondiente:
     ```javascript
     const cols = { pend:[], diag:[], rep:[], listo:[] };
     misTickets.forEach(t => {
       const est = (t.estado || '').toUpperCase().trim();
       if (est === 'PENDIENTE') {
         cols.pend.push(t);
       } else if (est === 'EN_DIAGNOSTICO' || est === 'EN_REPARACION') {
         cols.diag.push(t);
       } else if (est === 'ESPERANDO_REPUESTO') {
         cols.rep.push(t);
       } else if (est === 'LISTO_PARA_RECOGER' || est === 'LISTO_PARA_ENTREGA' || est === 'COMPLETADO') {
         cols.listo.push(t);
       } else {
         // Fallback seguro: si tiene un estado desconocido pero está activo, colocarlo en revisión para visibilidad
         cols.diag.push(t);
       }
     });
     ```
   - Actualizar el contador `#txt-total-active` con el total real de tarjetas clasificadas:
     ```javascript
     const totalActivos = cols.pend.length + cols.diag.length + cols.rep.length + cols.listo.length;
     if (countSpan) {
       countSpan.textContent = `${totalActivos} ${totalActivos === 1 ? 'orden' : 'órdenes'} en taller`;
     }
     ```
2. **Mejora de UX Móvil en Acordeones:**
   - Si la columna `PENDIENTES` está vacía al cargar pero `ESPERANDO REPUESTO` o `EN REVISION` tienen tarjetas, abrir automáticamente la primera columna que contenga órdenes para que el técnico no encuentre un acordeón vacío a primera vista.

---

### Paso 4: Cache Busting y Service Worker
- Actualizar la versión de caché en `website_files/sw.js`:
  ```javascript
  // Service Worker - Petulap PWA v26 (Remediación recepción móvil y sincronización de Kanban v1.5.6)
  var CACHE_NAME = 'petulap-v26';
  ```

---

### Paso 5: Cierre Documental Obligatorio
1. **Actualizar `CHANGELOG.md` y `docs/05-CHANGELOG.md` bajo `[1.5.6]`:**
   - Registrar la causa raíz técnica ("El Por Qué"): desajuste en sentencia preparada MySQLi y condición de filtrado incompleta en Kanban.
   - Categorizar bajo `[Fixed]` y `[Changed]`.
2. **Actualizar `docs/02-BACKEND.md`:**
   - Ratificar la firma de parámetros del endpoint `api/soporte.php?action=crear` documentando los 11 campos obligatorios y opcionales.

---

## 7. MATRIZ DE TESTING Y VERIFICACIÓN (SMOKE TESTS)
Validar exhaustivamente antes de dar por cerrada la tarea:

| Caso de Prueba | Procedimiento | Resultado Esperado |
| :--- | :--- | :--- |
| **ST-01: Creación de Ticket en Móvil** | Completar DNI, Nombre y Equipo en `recepcion_movil.html` y pulsar `GENERAR ORDEN`. | El botón muestra "Guardando...", se genera el correlativo `ST-YYYYMMDD-XXX` y transiciona limpiamente a la pantalla de éxito con link de WhatsApp y sticker. Cero bloqueos. |
| **ST-02: Resiliencia ante Fallos** | Desconectar red temporalmente o simular fallo y pulsar `GENERAR ORDEN`. | Se muestra un toast de error y el botón vuelve a quedar activo con su texto original. |
| **ST-03: Conteo Consistente en Kanban** | Acceder a `mis_ordenes.html` en pestaña Clientes. | El número del chip (`X órdenes en taller`) coincide con la suma aritmética exacta de los contadores `c-pend + c-diag + c-rep + c-listo`. |
| **ST-04: Acordeones Móviles** | Abrir `mis_ordenes.html` en viewport $\le 768\text{px}$. | Los acordeones se despliegan fluidamente al pulsar el encabezado; las tarjetas se leen completas sin desbordamientos horizontales. |
| **ST-05: PWA Service Worker** | Inspeccionar pestaña Application en DevTools. | `sw.js` activo con caché `petulap-v26`. |

---

## 8. ENTREGABLES EXIGIDOS AL EJECUTOR
1. Archivos de código modificados con diffs limpios y quirúrgicos.
2. Entrada completa agregada a `CHANGELOG.md` y `docs/05-CHANGELOG.md` para la versión `1.5.6`.
3. Verificación de no regresión en las 7 Reglas de Oro ("Cosas Frágiles").
