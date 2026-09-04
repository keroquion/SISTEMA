
## PROMPT (copiar todo lo de abajo)

Actúa como un ingeniero frontend senior especializado en sistemas de diseño y en migraciones de CSS a gran escala. Vas a implementar, sobre un sistema real ya en producción llamado Petulap S.A.C. (taller de reparación de laptops, Perú), el rediseño especificado en el archivo adjunto `petulap-propuesta-diseno.md`. Ese documento es tu única fuente de verdad para decisiones de diseño — no inventes valores, nombres de variable, colores ni espaciados que no estén en él. Los archivos `todas_las_vistas.md` y `estilos_originales.md` son el código real actual, tu punto de partida.

### Reglas no negociables (rómpelas y el trabajo se descarta)

1. **No inventes tokens.** Todo color, espaciado o radio que uses debe existir en la sección 1 del documento de especificación. Si durante la ejecución detectas que falta un token para un caso real que encuentres en el HTML, DETENTE, dime exactamente qué caso es y qué token propones agregar — no lo agregues por tu cuenta y sigas.
2. **No mezcles los tres sistemas de variables viejos.** El documento explica en su sección 0 que hoy conviven `--primary`/`--dark-text` (sistema A), `--surface`/`--text-secondary` (sistema B) y `--accent-main`/`--bg-surface` (sistema C). Tu primera tarea es eliminarlos TODOS y dejar un único árbol de variables. Si en algún archivo CSS que no te mostré aparece una variable que no reconoces, pregúntame antes de asumir a qué sistema pertenece.
3. **Trabaja por fases, en el orden exacto de la sección 7 del documento, y espera mi confirmación antes de pasar a la siguiente fase.** No adelantes trabajo de una fase futura "para ir más rápido" — cada fase depende de que la anterior esté cerrada sin errores. Al final de cada fase, entrégame:
   - Los archivos modificados completos (no fragmentos, no diffs parciales — el archivo entero listo para reemplazar el original).
   - Un resumen de 3-5 líneas de qué cambiaste y por qué.
   - Cualquier duda o ambigüedad que hayas encontrado, sin resolverla por tu cuenta.
4. **El modo oscuro es 100% manual.** Nunca leas `prefers-color-scheme` del sistema operativo. Solo se activa si el usuario toca el botón. Revisa la sección 2 del documento para el mecanismo exacto (atributo `data-theme` en `<html>`, `localStorage` con la clave `petulap-theme`, sin parpadeo al cargar).
5. **`imprimir_sticker.html` no lleva el sistema de tema.** Es una vista de impresión, se queda fija en claro con fondo blanco puro. No le agregues el botón de tema ni tokens de color oscuro.
6. **No toques `--sidebar-width` ni `--header-height`.** Otras reglas de layout dependen de esos dos valores exactos; cambiarlos rompe la estructura general aunque tú no lo veas en el archivo que estés tocando en ese momento.
7. **Cuando reemplaces un emoji por un ícono, usa exactamente la tabla de la sección 3 del documento.** Si un botón usa un emoji que no está en esa tabla, pregúntame el equivalente antes de inventar uno.
8. **No elimines ni renombres ningún `id` o clase usada por JavaScript** (por ejemplo `#f-dni`, `#tabla-reporte`, `.k-col`, `#modal-overlay`). Puedes cambiar estilos y estructura visual alrededor, pero los identificadores que el HTML original ya trae deben seguir existiendo con el mismo nombre, o el sistema deja de funcionar aunque se vea bien.
9. **Si en cualquier momento no tienes suficiente información para decidir algo con certeza, no adivines.** Detente y pregúntame. Prefiero una pausa a un error que se propague a las 20 páginas.

### Orden de ejecución (fases — una por turno, con mi confirmación entre cada una)

**FASE 1 — Archivo de tokens único.** Crea el archivo de variables completo (sección 1 del documento), en `:root` y en `[data-theme="dark"]`. No toques ninguna otra página todavía. Muéstramelo completo.

**FASE 2 — Limpieza de los sistemas viejos.** Ve a `dashboard.css`, al bloque de estilos del kanban y a `styles.css` (los que te adjunté en `estilos_originales.md`) y elimina sus bloques `:root` duplicados, dejando que todo apunte al archivo de la Fase 1. Lista explícitamente cualquier `var(--algo)` que hayas encontrado en el CSS original y que no tenga equivalente en el nuevo archivo de tokens, antes de decidir a qué token unificado lo mapeas.

**FASE 3 — Componentes base.** Reescribe, uno por uno, los componentes listados en la sección 4 del documento (`.card`, `.btn` y variantes, `.form-group`/`.form-control`, `.badge` con sus variantes semánticas, `.stat-card`, `.ticket`, `.k-col`/`.k-header`/`.k-body`, `.modal-overlay`). Antes de pasar al siguiente componente, dime cuál acabas de terminar.

**FASE 4 — Mecanismo de tema.** Implementa el atributo `data-theme`, el script de detección inicial (sin parpadeo, sin leer el sistema operativo), el botón sol/luna con `aria-label` y la persistencia en `localStorage`. Impleméntalo primero solo en `login.html`, `index.html` y `reportes.html` como prueba piloto, y muéstramelo funcionando antes de propagarlo a las 17 páginas restantes.

**FASE 5 — Migración de íconos.** Reemplaza los emojis por Phosphor Icons según la tabla de la sección 3, página por página, en este orden: `index.html`, `reportes.html`, `lotes.html`, `mis_ordenes.html`, `recepcion_movil.html`, y luego el resto en el orden que prefieras. Dime después de cada 3-4 páginas cuántas llevas y cuántas faltan.

**FASE 6 — Rediseño específico de pantallas.** Aplica, en este orden, las secciones 6.1 (Reportes), 6.2 (Lotes), 6.3 (Kanban), y después el resto de las subsecciones 6.4 a 6.18 en el orden en que aparecen en el documento. Una pantalla por turno para las tres primeras (son las más complejas); puedes agrupar de 2 en 2 las pantallas simples (Garantías, Historial, Repuestos) si son estructuralmente parecidas, mencionándolo explícitamente.

**FASE 7 — Accesibilidad.** Pasa la sección 5 del documento como checklist sobre las 20 páginas ya migradas. Repórtame cualquier caso donde no puedas verificar contraste o tamaño de objetivo táctil sin verlo renderizado.

**FASE 8 — QA final.** Recorre el checklist de la sección 8 del documento punto por punto y dime el resultado de cada ítem (cumple / no cumple / no se puede verificar sin ejecutar el sitio).

### Formato de tus respuestas durante todo el proceso

- Nunca resumas "ya está todo listo" sin haberme mostrado el contenido real de los archivos modificados.
- Si un archivo no cambia en una fase determinada, dilo explícitamente ("`login.html` no requiere cambios en esta fase") en vez de omitirlo en silencio.
- Si te quedas sin espacio para mostrar un archivo completo en una sola respuesta, dímelo y continúa en el siguiente mensaje — no entregues un archivo truncado sin advertirlo.
- Al cerrar cada fase, pregúntame explícitamente: "¿Confirmas que avance a la Fase [N]?" y espera mi respuesta antes de continuar.

Empieza por la FASE 1 ahora.
