# PROMPT DE EJECUCION - AJUSTE DE HORARIO TALLER Y EXTRAS (MATRIZ DESEMPENO)

## ROL
Actua como Ingeniero de Software Fullstack Senior para el proyecto Petulap SST.

## CONTEXTO OBLIGATORIO DE ARQUITECTURA
- Consulta docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md, docs/05-CHANGELOG.md y docs/12-SISTEMA-DE-DISENO-UI-UX.md.
- Reglas innegociables:
  1. Jerarquia CSS estricta: tokens.css -> styles.css -> dashboard.css.
  2. Identificadores protegidos: NO renombrar ni borrar #mobile-menu-toggle, #mobile-sidebar, #lbl-nombre, #lbl-tecnico, etc.
  3. Hrefs exactos y sin dependencias externas.
  4. Preservar intactas las mejoras de tareas multi-tecnico y tiempo real recien aplicadas.

---

## DEFINICION DEL CAMBIO SOLICITADO
- Modulos a intervenir:
  * website_files/api/desempeno.php
  * website_files/desempeno_tecnicos.html
  * website_files/sw.js
- Tipo de Intervencion: [Changed] y [Fixed]

### Causa Raiz y Requerimientos de Negocio:
1. AJUSTE AL HORARIO REAL DE TALLER (10:00 AM - 08:00 PM):
   - El horario de atencion y trabajo presencial en taller es de 10:00 a 20:00 (10 hrs).
   - En desempeno.php, el bucle de horas de matriz semanal estaba fijo de 8 a 18 (h=8 a 18), dejando sin reflejar actividades realizadas a partir de las 18:00 (6:00 PM) o antes de las 10:00 AM.
   - Ajustar el rango principal a h = 10 hasta 20 (11 slots horarios).
2. COLUMNA / SLOT ESPECIAL 'EXTRAS':
   - Anadir una columna especial o slot indexado (indice 11, hora representativa 99 o 'Extras') para capturar y contabilizar visualmente cualquier actividad realizada fuera del rango 10:00-20:00 (ej. tareas nocturnas a las 10:00 PM, 12:00 AM, etc.).
   - Mapear cualquier actividad fuera del horario de taller al slot Extras para que nunca quede en blanco si el tecnico trabajo fuera de hora.
3. FRONTEND (desempeno_tecnicos.html):
   - Actualizar los titulos de cabecera: Matriz de Actividad Semanal (Lunes a Sabado - 10:00 a 20:00 + Extras).
   - Modificar la cuadricula CSS .heatmap-grid de 11 columnas a 12 columnas:
     grid-template-columns: 125px repeat(12, minmax(36px, 1fr));
   - Actualizar el array horasHeaders a: [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 99].
   - Renderizar el header 99 con el label 'Extras' y estilo distintivo.
4. SERVICE WORKER (sw.js):
   - Incrementar la version de cache para invalidar versiones antiguas en navegador movil y de escritorio.

---

## CRITERIO DE ACEPTACION
1. La matriz semanal de desempeno muestra columnas de 10:00 a 20:00 mas la columna Extras.
2. Tareas creadas o ejecutadas en la noche/madrugada o fuera de las 10:00-20:00 se pintan en la columna Extras con su respectivo tooltip y detalle modal.
3. No hay desalineacion horizontal en la tabla ni en desktop ni en responsive.
