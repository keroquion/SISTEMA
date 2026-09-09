# 02 - INFORME DE REMEDIACIÓN DE EMERGENCIA DE SEGURIDAD

> **Documento de Control de Cambios y Mitigación de Vulnerabilidades**  
> **Fecha de Aplicación:** Septiembre 2026  
> **Responsable:** Ingeniero de Seguridad Senior  
> **Tipo de Intervención:** Quirúrgica / Emergencia (Cambios mínimos controlados)  
> **Estado:** Completado y Verificado  

---

## 1. Resumen Ejecutivo de la Intervención

Se ejecutó un plan de contención inmediata sobre las vulnerabilidades críticas detectadas en la auditoría inicial de **Petulap SST**. El objetivo principal fue **cerrar la fuga pública de datos y bloquear vectores de ejecución no autorizada** sin alterar la arquitectura operativa ni romper las funciones legítimas que utiliza el taller en su día a día.

### Objetivos Alcanzados:
1. **Eliminación de fugas de base de datos:** Se retiraron del acceso web público los scripts que permitían descargar copias de la base de datos o exponían credenciales maestras.
2. **Protección de scripts destructivos:** Se colocaron candados estrictos de sesión y rol de administrador en los endpoints que realizan borrados masivos.
3. **Eliminación de la puerta trasera de contraseña por defecto:** Se suprimió la lógica que permitía el ingreso universal con la contraseña `123456`.

---

## 2. Inventario de Archivos Reubicados a Zona Segura (`private_scripts/`)

Se creó el directorio `private_scripts/` en la raíz del proyecto (situado **fuera** de la carpeta pública `website_files/`, impidiendo cualquier invocación o descarga a través de un navegador web).  
Se trasladaron allí **14 archivos** que no tienen ninguna dependencia activa en el sistema de producción:

| Archivo Original | Destino Seguro | Motivo de la Reubicación |
| :--- | :--- | :--- |
| `website_files/export_db.php` | `private_scripts/export_db.php` | **Crítico:** Volcaba toda la base de datos en archivo `.sql` sin pedir usuario ni clave. |
| `website_files/test_db.php` | `private_scripts/test_db_root.php` | **Crítico:** Exponía credenciales de MySQL en texto plano. |
| `website_files/setup_auth.php` | `private_scripts/setup_auth.php` | **Crítico:** Contenía la contraseña maestra del administrador (`petulap2026`). |
| `website_files/iniciar.php` | `private_scripts/iniciar.php` | **Crítico:** Clon idéntico de `setup_auth.php` con credenciales de administrador expuestas. |
| `website_files/api/test_db.php` | `private_scripts/test_db_api.php` | Script de prueba de conexión a BD redundante. |
| `website_files/api/test_api.php` | `private_scripts/test_api.php` | Script de prueba con simulación forzada de sesión de usuario ID 1. |
| `website_files/api/test_proxy.php` | `private_scripts/test_proxy.php` | Script de prueba local de peticiones cURL. |
| `website_files/test_historial.php` | `private_scripts/test_historial.php` | Script de prueba de lectura sobre `historial_cambios`. |
| `website_files/api/setup_roles.php` | `private_scripts/setup_roles.php` | Script de inicialización de roles (migración de un solo uso ya ejecutada). |
| `website_files/api/update_roles_temp.php` | `private_scripts/update_roles_temp.php` | Script temporal de asignación de módulo de desempeño ya completado. |
| `website_files/api/update_schema_tmp.php` | `private_scripts/update_schema_tmp.php` | Script de migración de columna `tecnicos_adicionales` ya ejecutado. |
| `website_files/refactor.py` | `private_scripts/refactor.py` | Script utilitario en Python para reemplazo masivo de estilos CSS. |
| `website_files/fix_mojibake.php` | `private_scripts/fix_mojibake.php` | Script de corrección de tildes/emojis que reescribe archivos en vivo. |
| `website_files/index2.html` | `private_scripts/index2.html` | Página de plantilla residual del servicio de hosting InfinityFree. |

---

## 3. Modificaciones Quirúrgicas de Código Aplicadas

Solo se editaron **3 archivos preexistentes**, alterando exclusivamente las líneas indispensables:

### 3.1. Protección de `website_files/api/manage_accounts.php`
* **Vulnerabilidad previa:** Borraba registros de la tabla `personas` si cualquier persona o robot accedía vía web.
* **Cambio aplicado:** Se añadieron las validaciones de sesión activa (`session_start()`, chequeo de `$_SESSION['user_id']`) y verificación obligatoria de rol administrador (`check_api_access('admin_only')`).
* **Comportamiento actual:**
  - Sin sesión iniciada: Devuelve código de estado `HTTP 401 Unauthorized` (`{"ok":false,"msg":"No autorizado"}`).
  - Con sesión de técnico o usuario sin rol admin: Devuelve `HTTP 403 Forbidden` (`{"ok":false,"msg":"Acceso denegado. Solo administradores."}`).
  - Con sesión de administrador legítimo: Procede con la gestión controlada.

### 3.2. Protección de `website_files/api/cleanup_dupes.php`
* **Vulnerabilidad previa:** Ejecutaba sentencias `DELETE FROM equipos` de forma masiva sin autenticación previa.
* **Cambio aplicado:** Mismo candado estricto: inicio de sesión obligatorio y control de rol `admin_only`.
* **Comportamiento actual:** La URL ya no es un vector de denegación de servicio o pérdida de datos. Solo un administrador autenticado puede ejecutar el limpiador de duplicados.

### 3.3. Supresión de Contraseña Universal en `website_files/api/auth.php`
* **Vulnerabilidad previa:**
  ```php
  // CÓDIGO ANTERIOR (ELIMINADO):
  if ($user['password_hash'] === null) {
      if ($pass === '123456') {
          $auth_ok = true;
      } else {
          $auth_ok = false;
      }
  } else {
      $auth_ok = password_verify($pass, $user["password_hash"]);
  }
  ```
* **Cambio aplicado:**
  ```php
  // CÓDIGO ACTUAL (SEGURO):
  if (empty($user['password_hash'])) {
      echo json_encode([
          "ok" => false, 
          "msg" => "Usuario sin contraseña configurada. Solicite a un administrador que le asigne una contraseña."
      ]);
      exit;
  }
  $auth_ok = password_verify($pass, $user["password_hash"]);
  ```
* **Comportamiento actual:** Ningún usuario o técnico puede autenticarse con `123456` a menos que esa sea explícitamente su clave asignada y encriptada por el administrador. Si la cuenta carece de contraseña cifrada en la base de datos, el sistema detiene el proceso y emite el mensaje correspondiente.

---

## 4. Confirmación de Funciones Legítimas del Sistema

Se verificó el impacto de los cambios sobre las operaciones cotidianas de la empresa:

| Función del Sistema | Estado Post-Remediación | Detalle de Verificación |
| :--- | :---: | :--- |
| **Inicio de Sesión Normal (`login.html`)** | **Operativa** | Administradores, técnicos y gerencia con contraseña configurada se autentican con normalidad mediante `password_verify`. |
| **Bloqueo de Clave Débil no Autorizada** | **Operativa** | Intentos de acceso a cuentas huérfanas con `123456` son rechazados con aviso explícito. |
| **Creación y Edición de Personal (`tecnicos.html`, `personas.php`)** | **Operativa** | La creación y edición de técnicos y clientes permanece intacta. |
| **Control de Reparaciones y Kanban (`soporte.html`, `mis_ordenes.html`)** | **Operativa** | El flujo de tickets, diagnóstico, cambio de estados e impresión de stickers opera sin interferencias. |
| **Ventas, Caja y Entrega (`caja.html`)** | **Operativa** | El proceso de cobro, selección de método de pago y confirmación de entrega sigue respondiendo a través de `api/soporte.php`. |
| **Lotes, Garantías e Inventario (`lotes.html`, `garantias.html`)** | **Operativa** | Las compras por lote y reclamos a proveedores no fueron tocados y mantienen su funcionamiento normal. |
| **Borrados y Limpiezas Autorizadas** | **Operativa** | Los administradores debidamente identificados conservan la facultad de invocar las herramientas de mantenimiento. |

---

## 5. Próximos Pasos Recomendados (Fase No Urgente)

Habiendo neutralizado los vectores de riesgo inmediato, las tareas a planificar para una sesión posterior son:
1. **Saneamiento de Inyección SQL:** Parametrizar con *Prepared Statements* (`bind_param`) las consultas dinámicas en `equipos.php`, `sesiones.php`, `soporte.php` y `repuestos.php`.
2. **Renombrar Base de Datos en Servidor:** Coordinar en cPanel el cambio de nombre de `petumjvq_pruebas` a un nombre de producción formal (ej. `petumjvq_sistema`), actualizando `api/config.php`.
3. **Automatización de Despliegues:** Establecer un mecanismo de despliegue directo desde GitHub hacia el servidor para prescindir del traspaso manual por FTP.
