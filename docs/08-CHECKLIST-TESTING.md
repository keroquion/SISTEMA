# 08 - CHECKLIST DE PRUEBAS DE HUMO (SMOKE TEST PROTOCOL)

> **Documento de Calidad y Control Pre-Despliegue**  
> **Sistema:** Petulap SST — Gestión de Taller y Reacondicionamiento de Laptops  
> **Responsable:** QA Lead & Software Tester Senior  
> **Tiempo Estimado de Ejecución:** $\le$ 15 minutos  
> **Frecuencia:** Obligatorio antes de cada sincronización FTP/GitHub a producción o tras modificaciones de backend/frontend.  

---

## 1. Protocolo de Ejecución y Regla de Oro

> [!CAUTION]
> ### 🛑 REGLA DE ORO DE DESPLIEGUE (ZERO REGRESSION POLICY)
> Si cualquiera de las pruebas clasificadas como **CRÍTICA** falla, **EL DESPLIEGUE SE CANCELA DE INMEDIATO**.  
> Se debe ejecutar rollback de los archivos involucrados o congelar la sincronización FTP hasta que el equipo de desarrollo entregue la corrección y la checklist sea completada al 100% de forma exitosa.

### Perfiles de Usuario Requeridos para la Prueba:
Para cubrir todas las rutas del sistema en menos de 15 minutos, quien ejecute la prueba debe contar con 3 sesiones de prueba configuradas en el entorno:
1. **Administrador (`admin`):** Acceso irrestricto a todos los módulos, reportes y configuración de roles.
2. **Técnico de Taller (`tecnico`):** Perfil asignado a reparaciones, con acceso centrado en Kanban (`mis_ordenes.html`), recepción e inventario.
3. **Recepción / Caja (`cajero`):** Perfil enfocado en mostrador (`soporte.html`, `caja.html`, `clientes.html`).

---

## 2. Bloques de Pruebas de Humo

### Bloque 1: Acceso, Sesión y Seguridad (Tiempo estimado: 3 min)

| Estado | ID | Acción (Paso a Paso) | Resultado Esperado | ¿Por Qué es Crítica? (Riesgo Mitigado) | Componente Técnico |
| :---: | :---: | :--- | :--- | :--- | :--- |
| [ ] | **SMK-SEC-01** | 1. Ir a `login.html`<br>2. Ingresar DNI y contraseña válida de Admin.<br>3. Pulsar "Iniciar Sesión". | El sistema autentica correctamente, almacena sesión y redirige al panel principal (`index.html`). | **Operación:** Si el inicio de sesión legítimo falla, ningún colaborador de la empresa puede operar el sistema. | `website_files/login.html`<br>`api/auth.php?action=login` |
| [ ] | **SMK-SEC-02** | 1. En `login.html`, ingresar un DNI de técnico sin clave en BD.<br>2. Probar ingresar con la clave universal `123456`. | El sistema rechaza el ingreso con mensaje explícito: *"Usuario sin contraseña configurada..."*. | **Seguridad:** Evita la reapertura de la puerta trasera que permitía secuestrar cuentas de técnicos mediante la clave default `123456`. | `api/auth.php`<br>(docs/02-REMEDIACION-EMERGENCIA.md) |
| [ ] | **SMK-SEC-03** | 1. Abrir una ventana de incógnito sin sesión activa.<br>2. Escribir directamente en la URL: `/soporte.html` o `/caja.html`. | La página expulsa de inmediato al usuario no autenticado y lo redirige automáticamente a `login.html`. | **Seguridad:** Previene la fuga pública de órdenes de reparación, clientes y registros contables ante accesos no autorizados. | `website_files/js/check_auth.js`<br>`api/auth.php?action=check` |
| [ ] | **SMK-SEC-04** | 1. Iniciar sesión como `técnico`.<br>2. Intentar ingresar manualmente por URL a `admin_roles.html`. | `check_auth.js` intercepta la carga, valida la matriz de permisos y redirige al técnico a su página permitida (`mis_ordenes.html`). | **Seguridad:** Impide el escalamiento de privilegios vertical donde un colaborador modifique permisos del sistema. | `website_files/admin_roles.html`<br>`website_files/js/check_auth.js` |
| [ ] | **SMK-SEC-05** | 1. Estando como usuario `técnico` o anónimo, invocar en navegador `api/cleanup_dupes.php` o `api/manage_accounts.php`. | Devuelve código `HTTP 401` o `HTTP 403 Forbidden` (`{"ok":false,"msg":"Acceso denegado. Solo administradores."}`). | **Seguridad Crítica:** Bloquea la invocación de scripts destructivos que ejecutan `DELETE FROM equipos` o eliminan personal sin autorización. | `api/cleanup_dupes.php`<br>`api/manage_accounts.php` |

---

### Bloque 2: Flujo Operativo de Taller (Recepción a Kanban) (Tiempo estimado: 4 min)

| Estado | ID | Acción (Paso a Paso) | Resultado Esperado | ¿Por Qué es Crítica? (Riesgo Mitigado) | Componente Técnico |
| :---: | :---: | :--- | :--- | :--- | :--- |
| [ ] | **SMK-OPS-01** | 1. Con rol Admin o Recepción, ir a `soporte.html`.<br>2. Ingresar DNI de cliente existente o nuevo.<br>3. Seleccionar equipo, detallar falla y pulsar **"Crear Ticket"**. | Retorna confirmación JSON exitosa, reproduce sonido de confirmación y genera correlativo `ST-AAAAMMDD-XXX`. | **Negocio / Operación:** Es la puerta de entrada de dinero del taller; si falla, no se pueden registrar laptops de clientes externos. | `website_files/soporte.html`<br>`api/soporte.php?action=crear` |
| [ ] | **SMK-OPS-02** | 1. Observar la ventana emergente generada tras crear el ticket en `soporte.html`. | Abre `imprimir_sticker.html` con el código de barras y datos del equipo listos para enviar a la rotuladora térmica. | **Trazabilidad:** Sin la etiqueta adhesiva con código de barras, las laptops se confunden físicamente en las mesas del taller. | `website_files/imprimir_sticker.html`<br>`website_files/js/sonidos.js` |
| [ ] | **SMK-OPS-03** | 1. Iniciar sesión como Técnico o ir a `mis_ordenes.html`.<br>2. Verificar la columna **"PENDIENTES"**. | La orden recién generada aparece listada en la columna de pendientes con su código, cliente y falla descrita. | **Operación:** Garantiza que los técnicos reciban el trabajo en cola en tiempo real sin desfase de base de datos. | `website_files/mis_ordenes.html`<br>`api/soporte.php?action=mis_ordenes` |
| [ ] | **SMK-OPS-04** | 1. Cambiar el estado del ticket (ej. de "Pendiente" a "En Diagnóstico" o "Reparado").<br>2. Recargar pantalla. | La tarjeta se mueve a la columna correspondiente y el estado persiste en la base de datos tras la recarga. | **Integridad de Datos:** Previene la pérdida de diagnósticos y asegura que recepcionistas y clientes sepan el estado real. | `api/soporte.php?action=actualizar`<br>`website_files/mis_ordenes.html` |
| [ ] | **SMK-OPS-05** | 1. Abrir `mis_ordenes.html` en celular o emular 375px en DevTools.<br>2. Observar la carga inicial.<br>3. Tocar el encabezado de otra columna. | La columna **PENDIENTES** carga expandida por defecto. Al tocar otra columna, se contrae la actual y se expande la seleccionada suavemente sin scroll horizontal. | **Usabilidad Móvil:** Valida la remediación de acordeones móviles eliminando más de 1600px de scroll horizontal involuntario. | `website_files/mis_ordenes.html`<br>(docs/07-REMEDIACION-FRONTEND-PARTE2.md) |

---

### Bloque 3: Caja, Cobro y Entrega (Tiempo estimado: 3 min)

| Estado | ID | Acción (Paso a Paso) | Resultado Esperado | ¿Por Qué es Crítica? (Riesgo Mitigado) | Componente Técnico |
| :---: | :---: | :--- | :--- | :--- | :--- |
| [ ] | **SMK-FIN-01** | 1. Ir a `caja.html`.<br>2. Buscar la orden creada en el Bloque 2 por número de ticket o DNI. | Carga la orden, mostrando el costo del servicio, repuestos añadidos, anticipos previos y saldo pendiente a cobrar. | **Finanzas:** Previene cobrar de más o de menos a los clientes debido a lecturas desactualizadas de adelantos. | `website_files/caja.html`<br>`api/soporte.php?action=ver` |
| [ ] | **SMK-FIN-02** | 1. Seleccionar método de pago (Efectivo / Yape / Transferencia).<br>2. Registrar el cobro del saldo.<br>3. Confirmar entrega del equipo. | La orden se marca como cobrada, emite sonido de éxito y actualiza el estado del ticket a `ENTREGADO`. | **Caja:** Asegura el cuadre diario de caja chica e ingresos bancarios del taller. | `website_files/caja.html`<br>`api/soporte.php?action=actualizar` |
| [ ] | **SMK-FIN-03** | 1. Ir a `historial_entregados.html`.<br>2. Buscar la orden recién cobrada. | La orden aparece en el libro histórico con fecha de entrega, cajero responsable y monto total liquidado. | **Auditoría Legal:** Es el respaldo de descargo ante reclamos posteriores de clientes sobre entrega física del equipo. | `website_files/historial_entregados.html`<br>`api/soporte.php?action=entregados` |

---

### Bloque 4: Inventario y Lotes de Compra (Tiempo estimado: 2.5 min)

| Estado | ID | Acción (Paso a Paso) | Resultado Esperado | ¿Por Qué es Crítica? (Riesgo Mitigado) | Componente Técnico |
| :---: | :---: | :--- | :--- | :--- | :--- |
| [ ] | **SMK-INV-01** | 1. Ir a `inventario.html`.<br>2. Escribir en la barra de búsqueda una serie o marca conocida.<br>3. Presionar Enter. | Filtra instantáneamente la lista devolviendo solo los equipos coincidentes sin congelar la interfaz. | **Operación de Almacén:** Si el buscador falla, los técnicos pierden hasta 10 minutos buscando manualmente entre cientos de laptops. | `website_files/inventario.html`<br>`api/equipos.php?action=buscar` |
| [ ] | **SMK-INV-02** | 1. En `inventario.html`, activar modo celular (375px de ancho).<br>2. Revisar la tabla de laptops. | La tabla se transforma en tarjetas apiladas verticales; las columnas densas (`Procesador`, `RAM`, `Disco`) se ocultan para mantener legibilidad sin desbordamiento. | **Usabilidad Móvil:** Valida la remediación de 12 columnas que antes generaba más de 900px de scroll horizontal destructivo. | `website_files/inventario.html`<br>`attr(data-label)` |
| [ ] | **SMK-INV-03** | 1. Ir a `lotes.html`.<br>2. En vista móvil (375px), verificar la lista de lotes y pulsar en un lote para ver sus laptops asociadas. | El formulario se apila verticalmente, los lotes se muestran como tarjetas clave-valor limpias y el botón de acciones es fácilmente pulsable con el pulgar. | **Compras:** Permite al área comercial registrar y auditar lotes de laptops reacondicionadas directamente desde su celular en almacén. | `website_files/lotes.html`<br>`api/lotes.php?action=list` |

---

### Bloque 5: PWA, Responsive y Modo Oscuro (Tiempo estimado: 2.5 min)

| Estado | ID | Acción (Paso a Paso) | Resultado Esperado | ¿Por Qué es Crítica? (Riesgo Mitigado) | Componente Técnico |
| :---: | :---: | :--- | :--- | :--- | :--- |
| [ ] | **SMK-UI-01** | 1. En cualquier pantalla (ej. `desempeno_tecnicos.html` o `pedidos_repuestos.html`), reducir ancho a $\le 768\text{px}$.<br>2. Tocar el botón hamburguesa (`#mobile-menu-toggle`). | El menú lateral deslizante (`#mobile-sidebar`) abre suavemente; al tocar fuera o presionar la cruz, se cierra sin trabarse. | **Navegación Móvil:** Previene que pantallas con scripts omitidos dejen a los usuarios atrapados sin poder navegar en teléfonos. | `website_files/js/dashboard.js`<br>(docs/10-REMEDIACION-NAVEGACION-MOVIL.md) |
| [ ] | **SMK-UI-02** | 1. En la barra superior, verificar la cabecera.<br>2. Pulsar la campana de notificaciones (`.notification-btn`). | El elemento `#lbl-nombre` muestra el usuario y rol activo (`👑 ADMIN` o nombre de técnico). El dropdown de alertas se despliega mostrando los avisos no leídos. | **UX / Notificaciones:** Garantiza la identificación de sesión y la entrega oportuna de alarmas de garantías por vencer. | `website_files/js/dashboard.js`<br>`api/notificaciones.php` |
| [ ] | **SMK-UI-03** | 1. Alternar a **Modo Oscuro**.<br>2. Navegar entre 3 pantallas distintas (`index.html`, `soporte.html`, `mis_ordenes.html`).<br>3. Presionar F5 en cada una. | El tema oscuro persiste en todas las vistas; no ocurre ningún parpadeo blanco (*flicker*) durante la carga del `<head>`. | **Salud Visual / Ergonomía:** Evita la ceguera por destellos blancos intensos para técnicos que trabajan en entornos oscuros de laboratorio. | `tokens.css`<br>`localStorage.getItem('petulap-theme')` |
| [ ] | **SMK-UI-04** | 1. Abrir DevTools (`F12`) $\rightarrow$ pestaña **Application** $\rightarrow$ **Service Workers**.<br>2. Verificar el nombre de caché en `sw.js`. | El Service Worker está registrado, activo y corriendo sobre la versión esperada (`petulap-v10`). | **Caché de Cliente:** Asegura que los navegadores de los colaboradores no se queden atascados con CSS/JS viejos en producción. | `website_files/sw.js` |
| [ ] | **SMK-UI-05** | 1. En vista móvil (375px), abrir `#mobile-sidebar`.<br>2. Expandir los 3 primeros acordeones.<br>3. Verificar presencia de: `pedidos_repuestos.html`, `repuestos.html`, `historial_entregados.html`, `desempeno_tecnicos.html` y `clientes.html`. | Los 4 módulos críticos son visibles, tienen sus iconos asignados (`.acc-icon-*`), y al tocarlos cargan la página correspondiente sin bloqueos. | **Estandarización Móvil:** Garantiza que los técnicos y gerentes puedan acceder al 100% de los módulos desde smartphones sin quedar incomunicados. | `website_files/*.html`<br>(docs/10-REMEDIACION-NAVEGACION-MOVIL.md) |

---

## 3. Matriz de Resultados de Ejecución

Al terminar la prueba de 15 minutos, complete este registro:

* **Fecha y Hora de Ejecución:** `____-__-__ __:__`
* **Versión Evaluada:** `[   ]` (ej. v1.2.0)
* **Entorno de Prueba:** `[   ]` Servidor de Pruebas / `[   ]` Staging / `[   ]` Producción Post-Despliegue
* **Responsable de la Prueba:** `_________________________`
* **Total Pruebas Ejecutadas:** 20 / 20
* **Dictamen Final:**
  - `[   ]` **APROBADO (GO TO PRODUCTION):** 100% de pruebas pasadas.
  - `[   ]` **RECHAZADO (NO-GO / ROLLBACK):** Al menos 1 prueba crítica falló.

---

## 4. Plantilla Rápida de Reporte de Incidencias (Bug Report)

Si se detecta cualquier falla durante la ejecución de la checklist, copie, complete y envíe este bloque de 4 líneas al canal de desarrollo de inmediato:

```text
🐛 REPORTE DE INCIDENCIA DE HUMO (PETULAP SST)
1. Pantalla / Endpoint: [Ej: website_files/caja.html | api/soporte.php]
2. Pasos para Reproducir: [Ej: 1. Abrir caja -> 2. Buscar ticket ST-20260909-001 -> 3. Pulsar Cobrar]
3. Error Observado vs Esperado: [Ej: La ventana muestra 'NaN' en saldo en lugar del monto pendiente]
4. Severidad: [CRÍTICA (Bloquea despliegue) / ALTA / MEDIA / BAJA]
```
