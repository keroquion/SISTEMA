# Guía de Rediseño UX/UI: Plataforma de Control de Horarios y Tareas

Esta guía reúne los **prompts optimizados** para generadores de imágenes por IA (como Midjourney o DALL-E 3) y las **recomendaciones clave de UX/UI** basadas en la fusión de vistas individuales (mapas de calor por bloques de colores) y vistas generales (monitoreo de equipos en tiempo real).

---

## 🎨 Prompts para Generación de Interfaces (AI Prompts)

### 1. Dashboard Individual (Control de Horarios con Bloques de Actividad)
*Enfoque: Perfil individual del trabajador con línea de tiempo visual y cuadrícula de rendimiento coloreada.*

> **Prompt:**
> Modern SaaS HRMS and Time-Tracking dashboard UI/UX design, individual employee view, clean minimalist light mode layout. The centerpiece is a visual timeline and daily grid schedule. On top of the schedule, there is an overlay of vibrant, color-coded block indicators (heat-map style): vibrant green blocks for productive/focused work, soft blue blocks for active tasks/meetings, and bright red blocks for unregistered time or long breaks. Includes clean widgets for 'Working Hours' showing 9h 55m total, a 'Start Tracking' green CTA button, and a visual 'Time Breakdown' donut chart. Sidebar navigation on the left with icons for Dashboard, Tasks, and Reports. High-end digital product design, soft shadows, rounded corners, Figma style, ultra-detailed UI --ar 4:3 --v 6.0

### 2. Dashboard General (Monitoreo de Todo el Equipo en Tiempo Real)
*Enfoque: Vista de administrador para supervisar a todos los empleados de forma simultánea a través de barras cromáticas de actividad.*

> **Prompt:**
> Modern SaaS Employee Monitoring and Team Management dashboard UI/UX design, admin general view, clean layout in light mode. Features a comprehensive table/grid listing all remote employees. Each row displays the employee's profile picture, name, assigned project, current active task, and a compact, horizontal visual timeline strip using the same color-coded heat-map indicators (vibrant green for working, blue for task-specific activity, and red for away/no activity). Includes columns for priority badges (Urgent, Medium, Low) and interactive status dropdowns (To-Do, In-Progress, Done). Top header features a 'Search anything' bar and an overall team statistics widget. Professional corporate tool, clean typography, highly scannable interface, Figma design system --ar 4:3 --v 6.0

---

## 💡 Recomendaciones Clave de UX/UI para el Menú de Reportes y Actividades

Para lograr que tu versión rediseñada supere a las referencias visuales del mercado, implementa los siguientes tres pilares interactivos:

*   **Efecto "Hover" Contextual (Detalle Flotante):** Al pasar el cursor por encima de cualquier bloque de color (especialmente los rojos de inactividad o azules de tareas), la plataforma debe desplegar un *tooltip* flotante. Este debe indicar la aplicación activa (ej. Jira, Figma, Slack) o el motivo del estado sin registro.
*   **Filtros Globales de Estado Clínico/Laboral:** En la vista general de trabajadores, añade accesos directos superiores para segmentar al equipo al instante. Por ejemplo: *"¿Quién está inactivo (rojo) ahora mismo?"*, o filtrar directamente por departamentos (Diseño, Desarrollo, Ventas).
*   **Leyenda de Colores Persistente:** Sitúa una barra de referencia rápida en una zona fija de la pantalla. Ejemplo: 🟩 **Productivo** | 🟦 **En Tarea** | 🟥 **Inactivo / Descanso**. Esto garantiza que la curva de aprendizaje de los administradores sea inmediata.

---

## 📋 Tabla de Buenas Prácticas de Componentes

| Elemento de Interfaz | Criterio de Usabilidad (UX) |
| :--- | :--- |
| **Acción Principal (Fichaje)** | Debe requerir **un solo clic** en la pantalla de inicio ("Iniciar jornada" / "Pausa"). Evita submenús complejos. |
| **Líneas de Tiempo (Timeline)** | Usa **código de colores claro** para diferenciar rápidamente horas trabajadas, retrasos, reuniones y ausencias. |
| **Vistas de Tareas** | Integra las tareas directamente con el reloj de control horario para que el empleado asigne su tiempo a un proyecto específico sin cambiar de sección. |
