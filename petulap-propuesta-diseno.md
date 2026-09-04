# Petulap S.A.C. — Propuesta de rediseño UX/UI
### Auditoría, sistema de diseño unificado y plan de ejecución (con modo claro/oscuro)

Documento de referencia único. Está escrito para que un desarrollador o **otra IA implementadora** pueda ejecutar el rediseño sin tener que interpretar ni adivinar decisiones. No contiene código: contiene la especificación exacta que el código debe cumplir.

---

## 0. Hallazgo raíz: hay tres sistemas de diseño mezclados

Al revisar `todas_las_vistas.md` y `estilos_originales.md` confirmo el origen real de la inconsistencia visual: **no es un solo sistema con errores, son tres sistemas de tokens distintos conviviendo en el mismo proyecto**, cada uno con su propio vocabulario de variables para el mismo concepto:

| Concepto | Sistema A (`dashboard.css`) | Sistema B (bloque kanban) | Sistema C (`styles.css`) |
|---|---|---|---|
| Color de marca | `--primary` | `--primary` (reutilizada, pero pensada para fondo oscuro) | `--accent-main` |
| Texto principal | `--dark-text` | `--text-primary` / `--text-secondary` | `--text-primary` |
| Fondo de tarjeta | `--bg-card` | `--surface` / `--surface-light` | `--bg-surface` |
| Radio de borde | `--border-radius` (16px) | valores sueltos (10px, 12px) | `--radius-sm/md/lg` |
| Sombra | `--card-shadow` | `rgba(0,0,0,0.2-0.3)` (asume fondo oscuro) | `--shadow-sm/lg` |

Esto explica por qué Reportes y Lotes "se ven feo": sus componentes (`.filtros`, `.stats`, `.stat-card`, `.lote-label`) fueron escritos contra un tercer vocabulario de tokens que no siempre coincide con el que usa el sidebar o el kanban. Además, el bloque de estilos del kanban usa sombras y hovers pensados para **fondo oscuro** (`rgba(255,255,255,0.1)` en hover, sombras muy negras) mientras el resto del sistema es un tema claro — es probable que ese módulo se haya copiado de un experimento de modo oscuro anterior y haya quedado a medias.

**Esto es lo primero que hay que resolver, antes que cualquier ajuste visual.** Si el modo claro/oscuro se construye sobre esta base fragmentada, el resultado será inconsistente en la mitad de las pantallas. Los pasos 1 y 2 de este documento existen para resolverlo de raíz.

---

## 1. Sistema de tokens unificado (fuente única de verdad)

Un solo archivo de variables, un solo vocabulario. Se listan por rol (qué significa cada token), no por color literal — así el modo oscuro es un segundo mapa de los mismos nombres, no un sistema paralelo.

### 1.1 Colores de superficie

| Token | Rol | Valor claro | Valor oscuro |
|---|---|---|---|
| `--bg-page` | Fondo general de la app | `#F4F7FE` | `#0F1420` |
| `--bg-surface` | Fondo de tarjetas, modales, inputs | `#FFFFFF` | `#1A2130` |
| `--bg-surface-hover` | Hover sobre filas/ítems | `#F1F5F9` | `#232B3D` |
| `--bg-sidebar` | Fondo del sidebar (se mantiene oscuro en ambos modos, es una franja de marca) | `#1E293B` | `#141A28` |

### 1.2 Colores de texto

| Token | Rol | Valor claro | Valor oscuro |
|---|---|---|---|
| `--text-primary` | Texto principal | `#0F172A` | `#F1F5F9` |
| `--text-secondary` | Texto de apoyo, etiquetas | `#64748B` | `#94A3B8` |
| `--text-muted` | Placeholders, metadatos | `#94A3B8` | `#64748B` |
| `--text-on-brand` | Texto sobre fondo de color sólido (botones, sidebar) | `#FFFFFF` | `#FFFFFF` |

### 1.3 Bordes y sombras

| Token | Rol | Valor claro | Valor oscuro |
|---|---|---|---|
| `--border-default` | Borde estándar | `#E2E8F0` | `#2A3448` |
| `--border-strong` | Borde de énfasis / foco | `#CBD5E1` | `#3D4A63` |
| `--shadow-card` | Sombra de tarjetas | `0 4px 20px rgba(0,0,0,0.04)` | `0 4px 20px rgba(0,0,0,0.35)` |

### 1.4 Colores semánticos (estado — un solo significado cada uno, en todo el sistema)

| Token | Significado | Uso permitido | Valor claro | Valor oscuro |
|---|---|---|---|---|
| `--color-success` | Completado, sin fallas, operativo | Badges, texto de KPI, borde superior de columna kanban "Listos" | `#10B981` | `#34D399` |
| `--color-warning` | Atención, en proceso, esperando | Badges, columna "En revisión", falla menor | `#F59E0B` | `#FBBF24` |
| `--color-danger` | Bloqueado, daño grave, urgente real | Badges, botones destructivos, solo para atrasos reales | `#EF4444` | `#F87171` |
| `--color-info` | Informativo neutro | Columna "Esperando repuesto", mensajes informativos | `#3B82F6` | `#60A5FA` |
| `--color-brand` | Acción primaria de marca | Botón primario único por vista, elementos activos de navegación | `#2563EB` | `#3B82F6` |

Cada color semántico debe tener también su versión "clara" (`--bg-success-subtle`, `--bg-warning-subtle`, etc.) para fondos de badge — hoy estos existen como hex sueltos (`#FEF3C7`, `#D1FAE5`, `#FEE2E2`) que **no van a verse bien en fondo oscuro**; deben pasar a ser tokens con su propio valor por modo, igual que los demás.

**Regla de aplicación:** un color semántico = un significado, siempre. Hoy `--danger` se usa tanto para "más de 48h" como para "botón cancelar" como para "equipo con daño grave" — son tres urgencias distintas con el mismo rojo, lo que hace que el rojo pierda fuerza de alerta. A partir de este sistema, rojo se reserva para bloqueos y atrasos reales; acciones destructivas usan el mismo rojo pero solo tras confirmación.

### 1.5 Espaciado, tipografía y forma

| Token | Valor | Nota |
|---|---|---|
| `--space-1` a `--space-8` | 4, 8, 12, 16, 24, 32, 48, 64px | Reemplaza los valores sueltos (`15px`, `20px`, `30px`) repartidos hoy por el CSS |
| `--radius-sm` | 8px | Inputs, badges pequeños |
| `--radius-md` | 12px | Tarjetas, botones |
| `--radius-lg` | 16px | Modales, contenedores grandes |
| `--font-family` | Inter (ya está importada, se mantiene) | Una sola familia en todo el sistema — hoy `styles.css` también importa "Outfit" sin usarla de forma consistente; eliminar esa importación si no se usa |
| Escala tipográfica | 12 / 13 / 14 / 16 / 20 / 24 / 32px | Consolidar los tamaños dispersos (`13px`, `14px`, `15px`, `24px`, `32px` ya existen — solo formalizarlos como escala) |

---

## 2. Modo claro / oscuro — mecanismo de funcionamiento

**Cómo se activa:** un atributo `data-theme="light"` o `data-theme="dark"` en la etiqueta `<html>` (no en `<body>`, para evitar parpadeo al cargar). Cada token de la sección 1 se define dos veces en el CSS: una vez bajo `:root` (valores claros, modo por defecto) y una vez bajo `[data-theme="dark"]` (sobrescribe con los valores oscuros). Ningún componente vuelve a declarar color directamente — todos leen el token, así que cambiar el atributo cambia toda la interfaz de una vez.

**Detección inicial (modo 100% manual, sin autodetección del sistema operativo):**
1. Si el usuario ya eligió un modo antes (guardado en `localStorage`, clave `petulap-theme`) → usar ese.
2. Si no ha elegido nunca → modo claro por defecto, sin excepción.

Deliberadamente **no** se lee `prefers-color-scheme` del sistema operativo — el modo oscuro solo se activa si el usuario lo eligió explícitamente con el botón. Esto evita que alguien con su sistema operativo en oscuro (algo común y muchas veces no relacionado con su preferencia para esta app en particular) reciba un tema que nunca pidió.

Este chequeo debe ejecutarse en un script pequeño **antes** de que se pinte el `<body>`, para que no haya un parpadeo de claro-a-oscuro al cargar.

**Dónde vive el interruptor:** un botón de sol/luna (ícono Phosphor `ph-sun` / `ph-moon`) en dos lugares — junto al ícono de notificaciones en el `.topbar` de escritorio, y dentro del menú de cuenta en `.mobile-header` en móvil (no ocupar espacio permanente en la barra móvil, que ya está justa). Al tocarlo, cambia el atributo en `<html>` y guarda la elección en `localStorage` para que persista entre sesiones y entre las ~20 páginas del sistema (todas deben leer la misma clave).

**Qué NO cambia entre modos:** el `--bg-sidebar` se mantiene oscuro en ambos modos (es una franja de identidad de marca, no contenido que deba adaptarse) — solo se ajusta unos puntos de luminosidad entre modos para que no choque. Los logos y el ícono de marca tampoco cambian.

**Componentes que necesitan atención especial en modo oscuro** (porque hoy usan colores hardcodeados en vez de tokens, según lo visto en `estilos_originales.md`):
- Los badges de estado (`.badge-P`, `.badge-C`, `.badge-M`, `.badge-V`, las clases `.triaje-*`) — hoy son hex fijos tipo `#FEF3C7` / `#D97706`, deben pasar a usar los tokens `--bg-{semantic}-subtle` / `--text-{semantic}` de la sección 1.4.
- Las tarjetas de ticket del kanban (`.ticket`, `.tkt-id`, `.tkt-title`) — hoy usan `var(--surface)`, `var(--text-secondary)`, que no están definidos en el mismo `:root` que el resto (ver hallazgo raíz).
- El overlay de modales (`rgba(15, 23, 42, 0.4-0.6)`) — funciona en ambos modos sin cambios, no requiere token nuevo.

---

## 3. Iconografía — completar la migración, no sumar una librería nueva

El sistema ya carga Phosphor Icons y lo usa correctamente en `.mobile-nav` (`ph-house`, `ph-magnifying-glass`, `ph-user`) y en el FAB (`ph-fill ph-plus-circle`). El resto de la interfaz sigue usando emoji directamente en el texto de los botones (🔍 💾 📋 💬 👑 ▶️ 📦 🔄 ✅).

**Instrucción de ejecución:** reemplazar cada emoji por su equivalente Phosphor outline, manteniendo el texto del botón en español junto al ícono (nunca ícono solo, salvo los botones de acción rápida ya identificados como solo-ícono, que **deben** llevar `aria-label`).

| Emoji actual | Ícono Phosphor equivalente | Usado en |
|---|---|---|
| 🔍 | `ph-magnifying-glass` | Buscar, Consultar, RENIEC |
| 💾 | `ph-floppy-disk` | Guardar, Crear |
| 📋 | `ph-clipboard-text` | Detalle, Atenciones |
| 💬 | `ph-whatsapp-logo` | Avisar / Enviar por WhatsApp |
| 👑 | `ph-crown-simple` | Administrar (rol admin) |
| ▶️ | `ph-play` | Iniciar, Siguiente fase |
| 📦 | `ph-package` | Repuesto, Inventario |
| 🔄 | `ph-arrow-clockwise` | Refrescar |
| ✅ | `ph-check-circle` | Terminar, Confirmar, Entregado |
| 🖨️ | `ph-printer` | Imprimir sticker |
| ➕ | `ph-plus` | Nueva tarea, Nueva orden |
| ⚠️ | `ph-warning` | Dañados, alertas |
| ❌ | `ph-x` | Deshacer, cancelar |

Sol/luna para el interruptor de tema: `ph-sun` (mostrar cuando está en modo oscuro, para invitar a volver a claro) y `ph-moon` (mostrar en modo claro).

---

## 4. Componentes a normalizar (uno solo por tipo, reutilizado en todas las pantallas)

Basado en las clases reales que ya existen en el HTML, cada una de estas se redefine **una sola vez** contra los tokens de la sección 1, y todas las pantallas la reutilizan tal cual — nadie vuelve a escribir su propio `.card` o su propio `.btn`:

- **`.card`** — contenedor base: fondo `--bg-surface`, borde `--border-default`, radio `--radius-md`, sombra `--shadow-card`. Hoy existen al menos 3 variantes (`.card`, `.dash-card`, `.dash-card-modern`) que hacen lo mismo con distinto padding y sombra — consolidar en una sola con un modificador de tamaño si hace falta.
- **`.btn` / `.btn-primary` / `.btn-outline` / `.btn-secondary` / `.btn-danger`** — ya están bien pensados en `styles.css`; el trabajo aquí es que **todas** las páginas los usen (hoy `caja.html` y `reportes.html` mezclan `btn-primary`/`btn-secondary` con estilos inline distintos).
- **`.form-group` / `.form-control`** — ya existen y están bien definidos; extender su uso a los inputs de fecha nativos de Reportes, que hoy rompen el estilo visual del resto del formulario.
- **`.badge` (con variantes semánticas)** — unificar `.badge-P/C/M/V` y `.triaje-*` en un solo patrón `badge--{semantic}` que herede de los tokens 1.4, en vez de hex sueltos.
- **`.stat-card`** — ya definida; usarla también en el dashboard en vez de `.dash-card-modern`, que duplica el mismo propósito.
- **Tarjeta de ticket (`.ticket`)** — reescribir contra los tokens unificados (ver 2, "componentes que necesitan atención especial").
- **Columna kanban (`.k-col`, `.k-header`, `.k-body`)** — mantener la estructura, solo migrar sus colores a tokens.
- **`.modal-overlay` + tarjeta interna** — ya está bien resuelto (overlay + animación); reutilizar tal cual en todos los módulos que hoy tienen su propio modal (Lotes, Soporte, Kanban, Caja).

---

## 5. Accesibilidad — checklist de cumplimiento

- Contraste mínimo 4.5:1 entre texto y fondo en ambos modos — verificar en particular `--text-secondary` sobre `--bg-surface` y los textos de badge sobre su fondo subtle.
- Todo botón solo-ícono (la lupa 🔍 sin texto en Recepción móvil, Clientes, Técnicos) lleva `aria-label` describiendo la acción ("Buscar en RENIEC", no "Buscar").
- Todo objetivo táctil mide al menos 44×44px en la versión móvil — revisar especialmente los checkboxes de días en Turnos y los botones `.btn-sm`.
- Ningún estado se comunica solo por color: el borde superior de columna kanban (color) debe acompañarse del texto ya presente en `.k-header` (que ya existe, mantenerlo); los badges de triaje llevan texto, no solo color (ya lo hacen, mantenerlo).
- El interruptor de tema debe ser operable por teclado y anunciar su estado actual a lectores de pantalla (`aria-pressed` o equivalente).
- El toggle de contraste entre modo claro/oscuro no debe depender únicamente de la percepción de color para saber en qué modo se está — el ícono (sol/luna) ya resuelve esto.

---

## 6. Rediseño específico por pantalla prioritaria

*(Reportes, Lotes y Kanban ya se validaron contigo con mockups visuales en la respuesta anterior — aquí se documenta la especificación funcional que sustenta esos mockups, ahora que se conoce el HTML real.)*

### 6.1 `reportes.html`
Reestructurar en tres bloques verticales claros, cada uno como un `.card` independiente en vez de la mezcla actual de `.filtros` + `.stats` sueltos:
1. **Filtros** (`#fecha-inicio`, `#fecha-fin`, selección de lotes) — un solo `.card`, inputs usando `.form-control` estándar.
2. **KPIs** (`#stats`) — usar `.stat-card` en grilla de 2 columnas en móvil / 4 en escritorio, con color semántico aplicado al número según corresponda (verde si es "operativos", ámbar si es "dañados").
3. **Detalle tabular** (`#tabla-reporte`) — tabla envuelta en `.table-container` con scroll horizontal en móvil (ya definido en el CSS, `overflow-x: auto`).

Los botones de acción (`Generar Reporte`, `Reporte Global`, `Marcar Todos`, `Imprimir`) deben distinguirse por jerarquía: "Generar Reporte" es `.btn-primary` (la acción esperada), el resto son `.btn-outline` o `.btn-sm`.

### 6.2 `lotes.html`
Separar la tabla de lotes de la vista de detalle — hoy conviven en el mismo scroll. Recomendación: al hacer clic en "Abrir", la tabla colapsa (o se oculta en móvil) y el detalle ocupa la pantalla completa con un botón "← Volver" fijo arriba (el HTML ya tiene ese patrón, solo falta que la tabla se oculte al mismo tiempo).

Dentro del detalle, agrupar los controles de "Seguimiento de Repuestos/Compra" en un solo `.card` con estructura de formulario vertical en móvil (hoy son 5 controles en una fila que se aprieta). El bloque "Pistolear/Agregar equipo" pasa a ser el elemento visualmente más grande de la pantalla (fondo con `--bg-accent-subtle`, input de altura 44px) porque es la acción más repetida del flujo, según confirma el documento técnico.

### 6.3 `mis_ordenes.html` (Kanban)
Cada tarjeta `.ticket` muestra un solo botón de acción primaria visible (el que corresponde al estado de la columna: "Iniciar" en Pendientes, "Terminar" en Revisión) más un botón de "más acciones" (ícono `ph-dots-three`) que despliega Administrar Ticket, Enviar WhatsApp e Imprimir Sticker — hoy estas 3-4 acciones están todas visibles y compiten entre sí.

El indicador `.urgente` (">48h") deja de aplicarse automáticamente a todo ticket con más de 48 horas y pasa a reservarse para los que superan un umbral configurable mayor (ej. 72h) o que un supervisor marca manualmente — si aparece en la mayoría de las tarjetas pierde su función de alerta, como se ve en la captura actual.

### 6.4 `index.html` (Dashboard)
Buena noticia al revisar el HTML: esta pantalla ya usa `.dash-card-modern` con íconos Phosphor reales (`ph-ticket`, `ph-package`, `ph-wrench`) — es la más avanzada de las 20. El trabajo aquí no es reconstruir, es limpiar y unificar:
- Eliminar el componente duplicado `.dash-card` (usado en otras vistas) y dejar `.dash-card-modern` como el único componente de tarjeta de KPI del sistema — hoy son dos implementaciones distintas del mismo concepto.
- Retirar los datos de prueba visibles en "Tareas Críticas" ("Tareas de conemanga", "Tareas de pernaciones", "Tareas criticas criticas") antes de producción — son claramente placeholder.
- El `.date-picker` ("Hoy, 16 de Mayo 2025") y el gráfico SVG vacío en `.chart-container` deben quedar funcionales o, si no hay datos aún, mostrar un estado vacío explícito en vez de un contenedor sin contenido.
- Los colores `icon-red / icon-yellow / icon-blue` de cada tarjeta deben pasar a usar los tokens semánticos de la sección 1.4 en vez de las clases sueltas actuales (`--danger-light`, `--warning-light`, `--primary-light`), que hoy no tienen contraparte en modo oscuro.

### 6.5 `recepcion_movil.html`
Es, junto al Dashboard, la mejor lograda estructuralmente (flujo de 3 pasos con pantalla de éxito). Ajustes puntuales:
- Agregar `aria-label` a los dos botones solo-ícono de lupa (`f-dni`, `f-eq-codigo`) — ej. "Buscar cliente por DNI en RENIEC", "Buscar equipo por código".
- El paso 3 y su botón "GENERAR ORDEN" deben quedar alcanzables sin scroll largo en móvil: considerar que ese botón se vuelva fijo (sticky) al fondo de la pantalla mientras el usuario completa el formulario, siguiendo el patrón ya usado en apps de checkout.
- La pantalla de éxito (`#step-exito`) ya tiene buena jerarquía (ticket grande, 3 acciones claras); solo migrar sus botones de emoji a Phosphor.

### 6.6 `soporte.html` (Tickets de Soporte)
Formulario largo de una sola columna con secciones (`.seccion`) ya bien delimitadas por líneas punteadas — mantener ese patrón, solo:
- Unificar el bloque "Equipo interno vs. externo" (`#bloque-interno` / `#bloque-externo`) para que el cambio entre ambos sea un toggle visualmente claro (pestañas o switch), no solo un checkbox "Equipo externo" que revela/oculta contenido sin indicación visual de qué bloque está activo.
- La tabla de "Atenciones de Soporte" con sus filtros (`#filtro-estado`, `#filtro-buscar`) sigue el mismo patrón que Inventario y Reportes — usar el mismo componente `.filtros` + `.table-container` en las tres.
- El modal de detalle/edición reutiliza el modal genérico ya definido en la sección 4.

### 6.7 `lotes.html` — nota adicional sobre el listado de lotes
Más allá de lo ya especificado en 6.2, el formulario "Nuevo Lote" con su opción "Autocargar Lote Completo desde Compra Previa" (`#chk-autocargar`) debe tratarse como un modo alterno del formulario, no como un campo más: al marcar el checkbox, los campos manuales (cantidad, notas) deberían atenuarse visualmente para indicar que el autocargado los reemplaza. La barra de acciones del detalle de lote (`#barra-acciones-lote`: Siguiente Fase, Soporte Técnico, Editar, Borrar, Cerrar) debe aplicar la jerarquía de botones ya definida — "Siguiente Fase" primario, "Editar" secundario, "Borrar Lote" y "Cerrar Lote" en rojo (`.btn-danger`) con confirmación obligatoria antes de ejecutar.

### 6.8 `garantias.html`
Mismo patrón que Lotes/Soporte: formulario de creación arriba (`.card`), filtro + tabla abajo. Sin hallazgos nuevos más allá de aplicar los componentes base — es de las pantallas más simples y ya sigue una estructura sana.

### 6.9 `historial_entregados.html`
Pantalla de solo lectura (una tabla). Asegurar que la tabla use `.table-container` con scroll horizontal en móvil y que cada fila permita ver el detalle del ticket sin necesitar abrir Soporte por separado — hoy no queda claro si es clicable.

### 6.10 `importar.html`
El flujo (subir Excel → previsualizar → confirmar) es claro y ya usa `.upload-card` con mensaje de advertencia bien redactado ("Regla de Oro"). Ajustes:
- El emoji 📊 en `.upload-icon` pasa a `ph-file-xls`.
- La tabla de previsualización (`#preview-table`) debe limitarse visualmente a 5 filas con scroll, tal como indica el título "Primeros 5 registros" — verificar que el CSS no la deje crecer sin límite en pantallas grandes.
- El botón "🚀 Confirmar e Importar Seguramente" es la única acción primaria de la pantalla — debe ser el único `.btn-primary` visible mientras el preview está activo.

### 6.11 `imprimir_sticker.html`
Pantalla especial: no lleva sidebar ni navegación, es una vista de impresión directa (código de barras + datos del ticket). No requiere el sistema de tema (no tiene sentido un sticker impreso "en modo oscuro") — se excluye explícitamente del mecanismo de la sección 2. Sí debe verificarse que el contraste del código de barras (`#barcode`) sea siempre negro sobre blanco, sin depender de ningún token de color, para garantizar que se lea al imprimir.

### 6.12 `inventario.html` e `inventario_soporte.html`
Ambas comparten el mismo patrón que Reportes: bloque de estadísticas (`.stats`) + filtros (`.filtros`) + tabla. Aplicar exactamente la misma reestructuración de tres niveles verticales de la sección 6.1. En `inventario_soporte.html` en particular, el bloque de escaneo (`.scan-box`) debe recibir el mismo tratamiento visual destacado que el pistoleo de Lotes (sección 6.2) — es la misma acción crítica repetida en dos pantallas distintas, y ambas deben verse igual de prioritarias.

### 6.13 `repuestos.html`
Catálogo simple tipo tabla con modal de alta/edición — ya sigue el patrón correcto. Único ajuste: el botón "➕ Nuevo Repuesto" usa hoy una clase `.btn-green` que no existe en el sistema de tokens (es un color suelto) — debe pasar a `.btn-primary` o a una variante `.btn-success` definida formalmente en la sección 1.4, no a un verde inventado ad hoc.

### 6.14 `tecnicos.html` y `turnos.html`
Formularios de alta/edición ya con buena estructura (`.form-row`, secciones). Ajustes:
- Los checkboxes de día en Turnos ("Lun" a "Dom") deben crecer a un objetivo táctil mínimo de 44×44px en móvil — hoy son checkboxes nativos pequeños con etiqueta corta al lado.
- El reloj en tiempo real (`.reloj`) y el bloque "Estado de Turnos Ahora" deben usar los colores semánticos de disponibilidad (verde = disponible ahora, gris = fuera de turno) en vez de texto plano.
- El botón "Activar/Desactivar" técnico (mencionado en el documento técnico original) debe usar un componente de switch/toggle visual, no un botón de texto — comunica mejor un estado binario.

### 6.15 `admin_roles.html`
Pantalla administrativa de configuración — mantiene su estructura de selector + checkboxes de módulos. Aplicar `.form-control` a los selects y agrupar visualmente los checkboxes de "Módulos Permitidos" en una grilla de 2 columnas en escritorio / 1 columna en móvil, en vez de una lista corrida.

### 6.16 `login.html`
Ya está resuelto de forma limpia (`.login-container`, `.login-card`) y es de las pocas pantallas sin sidebar. Único cambio: el emoji 🔒 en `.login-logo` pasa a `ph-lock-key` en tamaño grande, y esta pantalla también incluye el botón de tema (sol/luna) ya que es la puerta de entrada al sistema — buen lugar para que el usuario configure su preferencia desde el primer momento.

### 6.17 `consulta.html` (portal público del cliente)
Es la única pantalla orientada al cliente final, no al staff — el stepper visual (`.stepper`, pasos "Recibido → Revisión → Reparación → …") ya es un buen patrón de comunicación de estado. Mantenerlo, solo migrar sus colores a los tokens semánticos y asegurar que sea completamente legible sin necesitar contexto del sistema interno (lenguaje simple, sin jerga técnica de taller).

### 6.18 `manual.html`
Pantalla de documentación/ayuda interna. Sin cambios estructurales necesarios — solo debe heredar tipografía y tokens de color como el resto del sistema para no sentirse "fuera" de la aplicación.

---

## 7. Plan de ejecución para la IA/desarrollador implementador

Seguir este orden exacto — cada fase depende de que la anterior esté cerrada, para no reintroducir la fragmentación de tokens:

1. **Crear el archivo de tokens único** con todas las variables de la sección 1, en `:root` y en `[data-theme="dark"]`. No tocar ninguna pantalla todavía.
2. **Eliminar o vaciar los bloques `:root` duplicados** que hoy existen en `dashboard.css`, en el bloque de estilos del kanban y en `styles.css` — todos deben apuntar al archivo de tokens del paso 1. Verificar que ningún componente quede sin variable definida (buscar cualquier `var(--nombre)` que no exista en el nuevo archivo).
3. **Reescribir los componentes base** listados en la sección 4 contra los tokens nuevos, uno por uno, verificando visualmente cada uno en modo claro y oscuro antes de pasar al siguiente.
4. **Implementar el mecanismo de tema** (atributo, detección inicial, botón, persistencia) descrito en la sección 2, y verificar que funcione en al menos 3 páginas distintas antes de propagarlo a las 20.
5. **Migrar los emojis a íconos Phosphor** según la tabla de la sección 3, página por página.
6. **Aplicar el rediseño específico** de Reportes, Lotes y Kanban (sección 6.1–6.3).
7. **Pasada de accesibilidad** completa (sección 5) sobre las 20 páginas.
8. **QA final** (sección 8) antes de dar por cerrado el trabajo.

**Reglas de no-regresión para quien ejecute:** no introducir ningún color hexadecimal nuevo fuera del archivo de tokens; no crear una segunda variable para un concepto que ya tiene token (si algo nuevo hace falta, se agrega a la sección 1 de este documento, no se inventa localmente); mantener siempre `--sidebar-width` y `--header-height` sin cambios, ya que otras reglas de layout dependen de ellos.

---

## 8. Checklist de QA final

- [ ] Las 20 páginas leen el mismo archivo de tokens (cero `:root` duplicados).
- [ ] El botón de modo oscuro funciona igual en las 19 páginas aplicables (todas menos `imprimir_sticker.html`) y la elección persiste al navegar entre ellas.
- [ ] El modo oscuro nunca se activa solo, sin que el usuario lo haya elegido con el botón (no se lee `prefers-color-scheme`).
- [ ] Ningún texto queda con contraste insuficiente en modo oscuro (revisar especialmente badges y tarjetas de ticket).
- [ ] Cero emojis restantes en botones de producción (solo íconos Phosphor + texto).
- [ ] Reportes, Lotes y Kanban siguen la nueva estructura de la sección 6.
- [ ] Todo botón solo-ícono tiene `aria-label`.
- [ ] Objetivos táctiles ≥44px en vista móvil.
- [ ] No quedan datos de prueba visibles (tickets `ST-TEST-*`, texto "Tareas de conemanga").
