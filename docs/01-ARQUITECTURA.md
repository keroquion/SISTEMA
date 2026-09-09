# 01 - AUDITORÍA DE ARQUITECTURA DEL SISTEMA (PETULAP SST)

> **Documento de Auditoría Técnica y Diagnóstico de Arquitectura**  
> **Fecha de Elaboración:** Septiembre 2026  
> **Perfil del Auditor:** Arquitecto de Software Senior  
> **Destinatario:** Propietario del Proyecto / Dirección de Negocio  
> **Estado del Código Auditado:** Modo Solo Lectura (sin modificaciones realizadas)

---

## a) Resumen Ejecutivo en una Página

### ¿Qué hace la aplicación?
**Petulap SST** es un sistema integral de gestión operativa para un taller de servicio técnico y reacondicionamiento de computadoras portátiles (*laptops*) de la empresa **PETULAP S.A.C.**  
El sistema cubre el ciclo de vida completo de los equipos y la atención:
1. **Recepción y Triaje Inicial:** Registro rápido de laptops que ingresan (tanto de clientes externos como de compras por lote), asignación de estado de falla y generación de tickets con código de barras para etiquetas adhesivas.
2. **Taller y Asignación Técnica:** Tablero visual tipo tarjeta (*Kanban*) para que los técnicos cambien el estado de las reparaciones (diagnóstico, en espera de repuesto, reparado, listo para entrega).
3. **Control de Lotes y Garantías:** Clasificación de lotes de laptops compradas a proveedores (nacionales o importadas), separación de equipos fallados y gestión de reclamos de garantía ante proveedores.
4. **Caja y Entrega:** Liquidación de pagos (efectivo, transferencias, billeteras digitales) y confirmación de entrega al cliente.
5. **Inventario de Equipos y Repuestos:** Catálogo de máquinas en stock con número de serie y catálogo de repuestos disponibles.
6. **Medición de Desempeño y Roles:** Control de horarios/turnos de técnicos, medición de horas dedicadas a reparaciones y permisos por tipo de usuario (administrador, técnico, gerencia).
7. **Consulta Pública para Clientes:** Página donde el cliente final puede ver cómo va su reparación ingresando su número de ticket y su DNI.

---

### ¿En qué tecnologías está construida?

* **Frontend (La interfaz visual que ve e interactúa el usuario):**
  * **HTML5:** 19 páginas individuales e independientes (arquitectura *Multi-Page Application*, es decir, cada opción del menú carga un archivo `.html` distinto).
  * **CSS Vanilla (Estilos puros sin librerías):** No utiliza marcos pesados como Tailwind o Bootstrap. Emplea variables de diseño (*CSS Custom Properties*) en archivos como `tokens.css` y `dashboard.css`, con soporte para modo claro y modo oscuro (*dark mode*).
  * **JavaScript Vanilla (Código nativo en el navegador):** No utiliza *frameworks* modernos (como React, Angular o Vue). La interactividad se maneja con funciones nativas de JavaScript que capturan eventos (clics, teclas, lector de código de barras) y actualizan el texto en pantalla mediante manipulación directa del documento (*DOM*).
  * **PWA (Aplicación Web Progresiva):** Cuenta con un archivo `manifest.json` y un trabajador en segundo plano (`sw.js` o *Service Worker*) que permite instalar la web como una app en teléfonos Android/iOS o en computadoras.

* **Backend (El motor interno y cerebro del servidor):**
  * **PHP Vanilla (Nativo, versión 7.x/8.x):** No utiliza ningún *framework* estructurado del mercado (como Laravel, Symfony o CodeIgniter).
  * La lógica está repartida en **36 scripts individuales de PHP** dentro de la carpeta `api/`. Cada archivo responde a peticiones específicas (por ejemplo, `equipos.php`, `soporte.php`, `auth.php`).
  * La comunicación con la base de datos se realiza mediante la extensión nativa `mysqli` de PHP.
  * La sesión del usuario se gestiona mediante las cookies nativas de PHP (`PHPSESSID`).

* **Base de Datos (El archivador de información):**
  * **Motor:** MySQL / MariaDB sobre servidor Linux.
  * **Base de datos configurada:** `petumjvq_pruebas` (almacena 17 tablas identificadas).

---

### ¿Dónde y cómo se despliega (Hosting)?
* **Servidor de Producción:** Alojamiento compartido (*shared hosting*) con panel cPanel/Apache en el dominio `https://petulap.store`.
* **Proveedor de Servidor:** Se detectaron rastros previos de migración desde InfinityFree y actual alojamiento en Namecheap Hosting.
* **Mecanismo de Despliegue Actual:** **FTP Tradicional Manual** (Protocolo de Transferencia de Archivos). No existe un conducto automático (*CI/CD Pipeline*) que pruebe y suba el código cuando se envía a GitHub. La sincronización se hace manualmente ejecutando scripts de subida/bajada por FTP desde la computadora del programador.

---

## b) Flujo de Datos Explicado Paso a Paso

Para comprender cómo viaja la información dentro del sistema, tomaremos como ejemplo el proceso más crítico: **El registro de una nueva orden de servicio técnico (Ticket de reparación)**.

```
[Usuario en el Navegador]
       │
       ▼ (1. Rellena formulario y presiona "Guardar")
[JavaScript en el Frontend]
       │
       ▼ (2. Empaqueta datos en formato JSON y envía petición fetch POST)
[Red Internet / HTTPS]
       │
       ▼ (3. Servidor Web Apache recibe la petición y llama a PHP)
[Backend: api/soporte.php]
       │
       ▼ (4. Valida sesión, genera correlativo ST-YYYYMMDD-XXX y prepara consulta SQL)
[Base de Datos: MySQL]
       │
       ▼ (5. Guarda en tablas: soporte_tecnico, secuencias_tickets, notificaciones)
[Retorno de Confirmación JSON]
       │
       ▼ (6. Frontend recibe confirmación, reproduce sonido de éxito y abre ventana de impresión de sticker)
[Pantalla del Usuario]
```

### Paso a paso detallado:

1. **Acción del Usuario en la Pantalla:**  
   El recepcionista o técnico ingresa a `soporte.html` o `recepcion_movil.html`. Ingresa el DNI del cliente, selecciona o escanea el código de la laptop, describe el problema reportado y pulsa el botón **"Crear Ticket"**.

2. **Procesamiento en el Navegador (Frontend):**  
   El código JavaScript de la página captura los valores de los campos de texto, valida que los datos indispensables no estén vacíos y construye un objeto de datos en formato **JSON** (un formato estándar de texto ligero para transportar datos).

3. **Envío por la Red (Petición HTTP):**  
   JavaScript ejecuta una función nativa llamada `fetch()` que envía una solicitud por internet vía método `POST` hacia la dirección `https://petulap.store/api/soporte.php?action=crear`, viajando con la cookie de sesión del usuario logueado.

4. **Recepción en el Servidor (Backend):**  
   El servidor web Apache recibe la solicitud, verifica el certificado de seguridad HTTPS y le entrega el paquete al intérprete de PHP.  
   El archivo `api/soporte.php` ejecuta:
   - `session_start()`: Revisa si el usuario tiene una sesión activa y autorizada.
   - Lee la conexión en `api/config.php` y se conecta a MySQL.
   - Consulta la tabla `secuencias_tickets` para obtener el número siguiente del día (por ejemplo: `ST-20260909-005`).

5. **Guardado en la Base de Datos:**  
   PHP ejecuta una instrucción de inserción (`INSERT INTO soporte_tecnico ...`) con los datos del equipo y del cliente.  
   Simultáneamente, inserta una alerta en la tabla `notificaciones` para avisar a los administradores que ha entrado un nuevo ticket.

6. **Respuesta al Usuario:**  
   La base de datos confirma que la fila fue guardada exitosamente. PHP devuelve al navegador una respuesta en JSON: `{"ok": true, "id": 142, "numero": "ST-20260909-005"}`.  
   El navegador recibe la confirmación, dispara un sonido de confirmación (`js/sonidos.js`), limpia el formulario, actualiza la lista visual sin recargar toda la página y abre automáticamente la ventana `imprimir_sticker.html` para imprimir la etiqueta con código de barras en la rotuladora.

---

## c) Módulos y Carpetas Principales

| Carpeta / Archivo Clave | ¿Qué hace en una frase simple? |
| :--- | :--- |
| **`website_files/`** | Carpeta raíz que contiene todo el sistema que se publica en internet. |
| **`website_files/api/`** | Contiene los 36 programas en PHP que leen, guardan, borran y procesan datos en la base de datos. |
| **`website_files/js/`** | Programas en JavaScript que controlan la interactividad, el menú lateral, la autenticación, los sonidos y las notificaciones. |
| **`website_files/css/`** | Hojas de diseño visual que definen la paleta de colores, tipografías, tarjetas y modos claro/oscuro. |
| **`TAREAS/`** | Carpeta secundaria con transcripciones de reuniones y scripts en Python de apoyo (no forma parte del sistema web). |
| **`index.html`** | Tablero principal (*Dashboard*) que resume el estado del taller y muestra los accesos según el rol del usuario. |
| **`soporte.html`** | Pantalla de control de tickets de reparación, búsqueda avanzada y asignación de técnicos. |
| **`mis_ordenes.html`** | Tablero interactivo con columnas (*Kanban*) para que los técnicos muevan sus reparaciones según su estado. |
| **`recepcion_movil.html`** | Versión compacta y adaptada para pantallas de teléfonos celulares, pensada para recibir laptops en mostrador rápidamente. |
| **`lotes.html`** | Módulo de compras al por mayor; permite registrar lotes de computadoras y hacer el triaje masivo de cada máquina. |
| **`garantias.html`** | Control de reclamos a proveedores por laptops o piezas que llegaron con defectos de fábrica. |
| **`caja.html`** | Punto de entrega y cobro final de equipos que ya fueron reparados y están listos para el cliente. |
| **`inventario.html` e `inventario_soporte.html`** | Catálogo general de computadoras en stock y control de repuestos/partes disponibles en taller. |
| **`tecnicos.html` y `turnos.html`** | Registro del personal del taller y configuración de sus días y horas de trabajo. |
| **`reportes.html` y `desempeno_tecnicos.html`** | Pantallas de analítica con gráficos interactivos y cálculo de productividad por técnico. |
| **`consulta.html`** | Portal público donde el cliente final consulta el avance de su computadora usando su Ticket y DNI. |
| **`login.html`** | Pantalla de inicio de sesión con validación de credenciales y redirección según rol. |

---

## d) Base de Datos

* **Motor:** MySQL / MariaDB (utiliza tablas con motor de almacenamiento `InnoDB` y juego de caracteres `utf8mb4`).
* **Nombre de la Base de Datos:** `petumjvq_pruebas`.

### Tablas Existentes (17 identificadas):

1. **`personas`:** Directorio unificado de seres humanos. Guarda clientes, técnicos, personal de gerencia y administradores. Contiene DNI, nombre, teléfono, rol (`tipo`), estado de activación y contraseña cifrada (*password_hash*).
2. **`equipos`:** Inventario maestro de computadoras/laptops. Almacena número de serie, código interno de barras, marca, modelo, procesador, memoria RAM, disco duro, estado general, triaje (`SIN_FALLA`, `FALLA_MENOR`, `NECESITA_REPUESTO`, `DANO_GRAVE`, `SOPORTE`) y detalle de fallas.
3. **`soporte_tecnico`:** Registro central de órdenes de servicio (tickets). Vincula qué cliente trajo qué equipo, qué problema tiene, qué técnico lo atiende, su estado de reparación (`PENDIENTE`, `EN_DIAGNOSTICO`, `ESPERANDO_REPUESTO`, `EN_REPARACION`, `LISTO_PARA_RECOGER`, `ENTREGADO`, `CANCELADO`), prioridad, costos y fecha de entrega.
4. **`lotes`:** Compras de computadoras por paquetes grandes (nacionales o importadas). Contiene nombre del proveedor, RUC, factura/guía de compra, cantidad de laptops declaradas y cargadores buenos.
5. **`lote_equipos`:** Tabla de unión que detalla qué equipos físicos individuales pertenecen a cada lote y el resultado de su inspección.
6. **`garantias_proveedor`:** Documento de reclamo formal que se le envía al proveedor cuando laptops o partes de un lote vienen con fallas.
7. **`garantia_items`:** Lista específica de laptops o repuestos individuales incluidos dentro de un reclamo de garantía al proveedor.
8. **`repuestos`:** Inventario de piezas físicas (pantallas, teclados, baterías, cargadores, discos) con número de parte (*P/N*), existencias (*stock*) y precio unitario.
9. **`turnos`:** Horarios laborales asignados a cada técnico (hora de entrada, hora de salida y días de la semana activos).
10. **`sesiones_inventario`:** Agrupa sesiones de conteo rápido o triaje masivo realizadas por un operario en un día específico.
11. **`sesiones_items`:** Equipos escaneados dentro de una sesión de inventario rápida con su clasificación de falla asignada.
12. **`historial_cambios`:** Bitácora de auditoría interna. Registra automáticamente cada vez que un ticket o equipo cambia de estado, guardando quién lo hizo, fecha exacta, valor anterior y valor nuevo.
13. **`secuencias_tickets`:** Tabla de control numérico para asegurar que los números de ticket diarios no se repitan ni colisionen si dos usuarios guardan al mismo tiempo.
14. **`log_movimientos_lotes`:** Registro histórico que documenta cuándo una laptop fue movida de un lote a otro.
15. **`notificaciones`:** Alertas internas dentro de la aplicación para el usuario (por ejemplo: "Ticket asignado", "Equipo listo").
16. **`push_subscriptions`:** Registros de suscripción del navegador para enviar notificaciones automáticas al celular o computadora del personal mediante Web Push.
17. **`roles_config`:** Tabla de configuración de perfiles que define a qué pantallas HTML tiene acceso cada rol (`admin`, `tecnico`, `gerencia`) y cuál es su página de inicio.

### Mapa de Relaciones Lógicas:

```
           ┌──────────────┐
           │   personas   │ (Clientes, Técnicos, Admins)
           └──────┬───────┘
                  │ 1
                  ├───────────────────────────────┐
                  │ 1:N (cliente_id / tecnico_id) │ 1:N (tecnico_id)
                  ▼                               ▼
       ┌────────────────────┐              ┌──────────────┐
       │  soporte_tecnico   │              │    turnos    │
       └──────────┬─────────┘              └──────────────┘
                  │ 1:N
                  ▼
       ┌────────────────────┐
       │ historial_cambios  │
       └────────────────────┘

           ┌──────────────┐
           │    lotes     │
           └──────┬───────┘
                  │ 1:N
         ┌────────┴────────┐
         ▼                 ▼
┌─────────────────┐ ┌────────────────────────┐
│  lote_equipos   │ │  garantias_proveedor   │
└────────┬────────┘ └───────────┬────────────┘
         │                      │ 1:N
         ▼                      ▼
┌─────────────────┐ ┌────────────────────────┐
│     equipos     │ │     garantia_items     │
└─────────────────┘ └────────────────────────┘
```

---

## e) Dependencias y Servicios Externos

1. **Librerías de Interfaz y Gráficos (Vía enlace web / CDN):**
   * **Phosphor Icons (`unpkg.com/@phosphor-icons/web`):** Proporciona todos los iconos visuales de la aplicación. *(Riesgo: Si el servicio unpkg tiene cortes, los iconos se vuelven invisibles).*
   * **Google Fonts (`fonts.googleapis.com`):** Descarga las fuentes tipográficas *Inter* y *Outfit*.
   * **Chart.js (`cdn.jsdelivr.net/npm/chart.js`):** Genera los gráficos interactivos de barras y pastel en la sección de reportes.
   * **SheetJS (`cdnjs.cloudflare.com/ajax/libs/xlsx`):** Permite arrastrar un archivo Excel en `importar.html` para que el navegador lo lea y suba los equipos en bloque.
   * **JsBarcode (`cdn.jsdelivr.net/npm/jsbarcode`):** Dibuja los códigos de barras en pantalla para imprimir los stickers en las computadoras.

2. **Protocolo Web Push (Notificaciones al navegador):**
   * **Implementación:** La aplicación implementa de forma manual en PHP nativo (`api/push.php`) los estándares de cifrado criptográfico RFC 8291 y autenticación VAPID para enviar avisos a navegadores Chrome/Edge sin pagar a servicios intermediarios como OneSignal o Firebase.
   * **Sujeto VAPID configurado:** `mailto:admin@petulap.store`.

3. **Servicios de Pago y Correo (Estado real):**
   * **Pasarelas de Pago:** **NO EXISTEN.** En `caja.html` se muestran opciones como Yape, Plin, Transferencia o Tarjeta, pero son únicamente etiquetas de texto para registrar cómo pagó el cliente. No hay integración con bancos, Culqi, Izipay ni MercadoPago.
   * **Servicio de Correo Electrónico:** **NO EXISTE.** No hay integración con servidores SMTP ni servicios de envío masivo de correos (como SendGrid o Amazon SES).
   * **Servicio de Mensajería SMS / WhatsApp:** **NO EXISTE.** No hay pasarelas de WhatsApp Business API activas en el código auditado.

---

## f) Control de Versiones (Git y GitHub)

* **¿Está conectado a un repositorio Git?:** **SÍ, CONFIRMADO.**
* **Plataforma:** GitHub.
* **Dirección remota (*Remote URL*):** `https://github.com/keroquion/SISTEMA.git`.
* **Rama de trabajo activa (*Branch*):** `feature/notificaciones-garantias`.
* **Rama principal registrada:** `main`.
* **Último punto de control (*Commit* sincronizado):** `cbe6cae` (*"Sync: descarga fresca del servidor FTP - cambios desde otra PC, nuevos archivos (desempeno, roles, fix_mojibake), limpieza de basura hosting"*).
* **Diagnóstico de versionado:** El proyecto cuenta con repositorio activo y ramas bien estructuradas, lo cual evita pérdidas catastróficas de código ante incidentes locales.

---

## g) Puntos Ciegos (Inconsistencias y Cabos Sueltos)

1. **Acumulación de "Archivos Basura" y Scripts Temporales en Producción:**  
   En la carpeta pública del servidor web residen scripts que debieron ser de un solo uso o de mantenimiento local, pero están expuestos a internet:
   - `refactor.py`: Script en Python utilizado para reemplazar estilos en los archivos HTML. No tiene ninguna función dentro de la web en producción.
   - `fix_mojibake.php`: Script que repara caracteres con tildes y emojis dañados en archivos de texto. Si alguien lo ejecuta, reescribe archivos en vivo.
   - `index2.html`: Página de bienvenida original del servicio de hosting gratuito InfinityFree. Confunde a los motores de búsqueda y revela el historial de hosting.
   - `test_db.php`, `test_api.php`, `test_proxy.php`, `test_historial.php`: Scripts de prueba que revelan errores técnicos y credenciales.
   - `update_roles_temp.php`, `update_schema_tmp.php`, `setup_auth.php`, `setup_roles.php`: Scripts de migración que quedaron abandonados en el servidor.

2. **Lógica de Roles Desconectada entre la Pantalla y el Servidor:**  
   En la pantalla (`js/check_auth.js`), el sistema oculta los botones y menús que no corresponden al rol del usuario. Sin embargo, en el servidor (`api/`), de los 36 archivos PHP existentes, **solo 3 archivos (`turnos.php`, `roles.php`, `lotes.php`) verifican si el usuario realmente tiene permiso para esa acción**. Si un técnico o persona no autorizada envía una orden directa al resto de los archivos PHP, el servidor la procesará sin rechazarla.

3. **Múltiples Conceptos de "Triaje" y "Falla" Duplicados:**  
   El estado de una computadora con fallas está disperso en al menos cuatro lugares distintos sin una única fuente de verdad:
   - Campo `triaje` y `falla` dentro de la tabla `equipos`.
   - Campo `triaje` y `falla` dentro de la tabla `lote_equipos`.
   - Campo `triaje_asignado` y `falla_asignada` dentro de `sesiones_items`.
   - Campos `diagnostico` y `solucion` en `soporte_tecnico`.  
   Esto genera el riesgo de que una computadora figure como "Reparada" en una pantalla pero siga figurando como "Dañada" en otra.

4. **Flujo de Trabajo Invertido (Peligro de Sobreescritura):**  
   El flujo habitual en desarrollo profesional es:  
   `Computadora de desarrollo` ➔ `Git/GitHub` ➔ `Servidor de Producción`.  
   En este proyecto, se hicieron cambios en una máquina, se subieron directo al servidor por FTP, y luego otra máquina tuvo que descargar del servidor para enviar a GitHub. Este método es propenso a que dos personas sobreescriban el trabajo mutuo sin darse cuenta.

---

## h) Alertas Encontradas (Sin Modificar el Código)

> [!CAUTION]
> ### 1. Copia de Base de Datos Pública sin Contraseña (`export_db.php`)
> **Ubicación:** `website_files/export_db.php`  
> **Riesgo:** Cualquier persona que abra en un navegador la dirección `https://petulap.store/export_db.php` descargará inmediatamente un archivo `.sql` con **TODA la base de datos de la empresa**: datos personales de clientes, DNI, teléfonos, montos cobrados y contraseñas de los usuarios. No pide usuario ni contraseña.

> [!CAUTION]
> ### 2. Credenciales Maestras en Archivos Públicos
> **Ubicación:** `website_files/setup_auth.php` y `website_files/test_db.php`  
> **Riesgo:** El archivo `setup_auth.php` contiene en texto plano el usuario y la contraseña maestra del administrador:
> ```php
> $adminDni = 'ADMIN001';
> $adminHash = password_hash('petulap2026', PASSWORD_DEFAULT);
> ```
> Además, `test_db.php` expone el usuario y contraseña de la base de datos MySQL en texto legible.

> [!WARNING]
> ### 3. Scripts con Borrado Destructivo sin Autenticación
> **Ubicación:** `website_files/api/manage_accounts.php` y `website_files/api/cleanup_dupes.php`  
> **Riesgo:** Si un tercero o un rastreador de Google accede a la dirección web `https://petulap.store/api/manage_accounts.php`, el archivo ejecuta directamente sentencias `DELETE FROM personas` en la base de datos sin solicitar autenticación previa.

> [!WARNING]
> ### 4. Contraseña por Defecto Insegura para Técnicos
> **Ubicación:** `website_files/api/auth.php` (Línea 25) y `setup_auth.php` (Línea 10)  
> **Riesgo:** Si un técnico no tiene contraseña personalizada o se crea un técnico nuevo, el sistema permite que ingrese escribiendo la clave universal `123456`.

> [!WARNING]
> ### 5. Consultas SQL sin Parámetros Preparados (Riesgo de Inyección SQL)
> **Ubicación:** Múltiples archivos en `api/` (por ejemplo: `api/sesiones.php`, `api/equipos.php`, `api/soporte.php`, `api/repuestos.php`)  
> **Riesgo:** Muchos valores recibidos del usuario se colocan dentro de las consultas SQL mediante concatenación directa (usando comillas simples) o usando únicamente `real_escape_string`. Un usuario con intenciones maliciosas podría ingresar caracteres especiales en los campos de búsqueda para alterar o extraer información indebida de la base de datos (*Inyección SQL*).

> [!NOTE]
> ### 6. Base de Datos Nombrada como "Pruebas" en Entorno Real
> **Ubicación:** `website_files/api/config.php`  
> **Riesgo:** La base de datos activa se llama `petumjvq_pruebas`. Si algún script de mantenimiento o desarrollador asume por su nombre que es una base de datos descartable y corre pruebas en ella, se afectarán datos reales de la operación diaria.

---

### Conclusión del Auditor
La aplicación cuenta con una **lógica de negocio muy valiosa, completa y orientada exactamente al día a día del taller** (cubre recepción, triaje, kanban, lotes y caja). Sin embargo, al haber sido construida con velocidad mediante herramientas de IA, carece de blindaje de seguridad elemental (scripts de prueba expuestos, falta de validación de roles en el backend y endpoints de exportación abiertos).  
El siguiente paso estratégico sugerido **no es rehacer el sistema**, sino **sanearlo**: eliminar los scripts expuestos, proteger los endpoints con candados de sesión estrictos y estandarizar el despliegue a través de Git.
