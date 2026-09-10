# PROMPT MAESTRO: Humanización de Mensajería WhatsApp y Optimización del Portal de Seguimiento (v1.5.8)

**ROL:** Actúa como Ingeniero de Software Fullstack Senior, Diseñador de Producto y Especialista en Customer Experience (CX) para el sistema **Petulap SST** (Arequipa, Perú).

---

## 1. CONTEXTO OBLIGATORIO Y REFERENCIAS TÉCNICAS
Antes de escribir o modificar una sola línea de código, debes consultar y respetar estrictamente:
- `docs/00-INICIALIZACION-EJECUTOR-DESARROLLADOR.MD` (Protocolos de ejecución segura y pruebas)
- `docs/00-PROTOCOLO-DE-CAMBIOS-Y-PROMPTS.md` (Las 7 Reglas Innegociables y ciclo de 5 pasos)
- `docs/petulap-info-y-recomendaciones.md` (Identidad de marca oficial de Petulap S.A.C.)
- `docs/01-ARQUITECTURA.md` y `docs/03-FRONTEND.md` (Estructura de pantallas e identificadores protegidos)
- `docs/12-SISTEMA-DE-DISENO-UI-UX.md` (Tokens semánticos, jerarquía tipográfica y componentes)
- `docs/05-CHANGELOG.md` (Registro oficial de versiones)

---

## 2. DIAGNÓSTICO DEL PROBLEMA

### A. La Mensajería Actual (Fría, Robótica y con Fricción de UX)
El mensaje actual enviado tras registrar un equipo o notificar al cliente es:
```text
Hola KEVIN ROOSEVELT QUICAÑO CONDORI, somos PETULAP 🔧

Tu equipo (LENOVO T2054p (S/N: vna528wr)) ha ingresado correctamente.

📋 Ticket: ST-20260910-002

Puedes rastrear tu equipo aqui:
https://petulap.store/consulta.html?ticket=ST-20260910-002

Gracias por confiar en nosotros! 🙌
```

#### Defectos Críticos Detectados:
1. **Tratamiento Agresivo e Impersonal:** Utiliza el nombre completo en mayúsculas sostenidas proveniente de la consulta RENIEC (`KEVIN ROOSEVELT QUICAÑO CONDORI`), generando una sensación de notificación legal o bancaria fría en lugar de una bienvenida cálida y cordial.
2. **Fricción Técnica en el Enlace de Rastreo:** El enlace actual solo envía el parámetro `ticket`, pero el backend (`api/consulta.php`) y el portal (`consulta.html`) exigen **Ticket + DNI** para validar la consulta. Al no incluir el `&dni=...` capturado en recepción, el cliente hace clic, se le muestra un error o formulario en blanco y debe volver a escribir manualmente su DNI de 8 dígitos en el móvil.
3. **Ausencia Total de Identidad y Respaldo:** No comunica los pilares de Petulap (empresa formal en Arequipa, importación de EE.UU./Europa, garantía de 6 meses, 3 años de soporte técnico, sedes en Yanahuara y Cayma con horarios claros).
4. **Falta de Variación Contextual:** Se usa el mismo tono rígido para ingreso de orden y para notificación de entrega.

### B. El Portal de Seguimiento (`consulta.html`)
El portal de consulta ya cuenta con un stepper de 5 pasos y diseño estructurado, pero requiere:
1. **Recepción 1-Click Silenciosa:** Si la URL viene con `ticket` y `dni` desde el WhatsApp mejorado, debe ejecutar la consulta de inmediato sin mostrar pantallas intermedias vacías.
2. **Formateo Estético de Identidad:** Capitalizar el nombre del cliente (`Title Case`), evitando desplegar textos en mayúsculas crudas en la tarjeta de resultados.
3. **Refuerzo de Canales de Atención Arequipa:** Asegurar que los botones de contacto y las direcciones de las sedes (Yanahuara y Cayma) ofrezcan enlaces directos a Google Maps o chat directo con un asesor con mensaje contextual pre-rellenado.

---

## 3. IDENTIDAD CORPORATIVA PETULAP (FUENTE DE VERDAD)

- **Razón Social:** Petulap S.A.C. (Arequipa, Perú).
- **Eslogan:** *"Laptops Importadas desde EE.UU. & Europa. Con garantía de 6 meses y servicio técnico por 3 años."*
- **Sedes y Horarios:**
  - **Sede 1 (Yanahuara):** Av. Ejército 314, 2do piso (frente a la comisaría de Yanahuara, costado de Inkafarma).
  - **Sede 2 (Cayma):** León XIII Mza A-4, 1er piso (cerca al cruce Av. Ejército con Av. Cayma).
  - **Horarios:** Lunes a Viernes 10:30 am – 7:30 pm | Sábados 10:30 am – 6:00 pm.
- **Canal Oficial WhatsApp:** +51 983 396 137.
- **Tono de Comunicación:** Cercano, profesional, tecnológico, transparente y de absoluta confianza.

---

## 4. ESPECIFICACIÓN TÉCNICA DE CAMBIOS REQUERIDOS

### MÓDULO 1: Función Helper Universal para Nombres y Enlaces (`js/utils.js` o embebido)
Implementar una función utilitaria compartida o estandarizada para:
1. **Capitalizar Nombres:**
   ```javascript
   function formatearNombreCliente(nombreCompleto) {
     if (!nombreCompleto) return 'Estimado/a cliente';
     // Convierte "KEVIN ROOSEVELT QUICAÑO CONDORI" en "Kevin Quicaño" o "Kevin Roosevelt"
     const partes = nombreCompleto.trim().toLowerCase().split(/\s+/);
     const capitalizadas = partes.map(p => p.charAt(0).toUpperCase() + p.slice(1));
     // Retornar Primer Nombre + Primer Apellido o los 2 primeros nombres
     return capitalizadas.length >= 3 
       ? `${capitalizadas[0]} ${capitalizadas[2]}` 
       : capitalizadas.join(' ');
   }
   ```
2. **Construcción de URL de Rastreo 1-Click:**
   `https://petulap.store/consulta.html?ticket=${encodeURIComponent(ticket)}&dni=${encodeURIComponent(dni)}`

---

### MÓDULO 2: Nuevas Plantillas de WhatsApp Humanizadas

#### Plantilla 1: Recepción e Ingreso al Taller (`recepcion_movil.html` y `mis_ordenes.html`)
```text
¡Hola, *{NombreAmigable}*! 👋 En *PETULAP* ya tenemos en nuestras manos tu equipo:

💻 *Equipo:* {EquipoDescripcion}
📋 *N° de Ticket:* {NumeroTicket}
🏢 *Sede de Ingreso:* Petulap Arequipa

🛠️ Nuestro equipo de especialistas técnicos iniciará la revisión y diagnóstico correspondiente para cuidar de tu equipo con los mejores estándares.

🔍 *Sigue el avance de tu servicio en vivo (1 clic):*
{UrlSeguimientoConTicketYDni}

📍 *Nuestras Sedes:*
• Yanahuara: Av. Ejército 314, 2do piso
• Cayma: León XIII Mza A-4, 1er piso
⏰ Lun-Vie: 10:30am a 7:30pm | Sáb: 10:30am a 6:00pm

¡Gracias por confiar en el servicio técnico oficial de Petulap! 🤝
```

#### Plantilla 2: Equipo Listo para Recojo (`mis_ordenes.html` y `soporte.html`)
```text
¡Excelentes noticias, *{NombreAmigable}*! 🎉

Tu equipo *{EquipoDescripcion}* ha completado satisfactoriamente su servicio técnico y se encuentra *LISTO PARA ENTREGA* 💻✨

📋 *Ticket:* {NumeroTicket}

Puedes acercarte a nuestra oficina dentro de nuestro horario de atención:
⏰ *Lunes a Viernes:* 10:30 am – 7:30 pm
⏰ *Sábados:* 10:30 am – 6:00 pm

📍 *Sedes en Arequipa:*
• Yanahuara: Av. Ejército 314, 2do piso
• Cayma: León XIII Mza A-4, 1er piso

📌 *Importante:* Recuerda traer tu DNI o el número de ticket para retirar tu equipo. 

¡Te esperamos en Petulap! 🙌
```

---

### MÓDULO 3: Refactorización y Auditoría de `consulta.html` (Portal de Clientes)
1. **Limpieza y Formateo del Nombre:** En `mostrarResultados(data)`, procesar `data.cliente_nombre` para que se muestre en formato Capitalizado (evitando mayúsculas agresivas).
2. **Soporte Completo de Query Parameters:** Garantizar que al entrar con `?ticket=...&dni=...` se ejecute inmediatamente la búsqueda con un indicador visual fluido ("Cargando el estado de tu equipo...").
3. **Enlaces Interactivos de Ubicación:** Agregar enlaces que abran Google Maps para la sede de Yanahuara y Cayma.
4. **Garantía y Confianza Visible:** Asegurar que los badges de garantía (6 meses / 3 años de soporte) se muestren de forma destacada en la tarjeta del resultado.

---

### MÓDULO 4: Archivos a Modificar
1. `website_files/recepcion_movil.html`: Integrar la plantilla humanizada y pasar `dni` en el enlace de WhatsApp generado en pantalla de éxito.
2. `website_files/mis_ordenes.html`: Actualizar las plantillas de `msgInitWP` y `msgWP` (listo para recoger) con las nuevas estructuras humanizadas y enlace 1-click.
3. `website_files/soporte.html`: Actualizar el generador de mensajes de WhatsApp del modal de detalle técnico.
4. `website_files/consulta.html`: Formateo de cliente, ejecución instantánea, pulido de badges y accesos a mapas de sedes.
5. `website_files/sw.js`: Incrementar versión de caché a `petulap-v27` para forzar la actualización de los scripts PWA en clientes y técnicos.
6. `CHANGELOG.md` y `docs/05-CHANGELOG.md`: Registrar versión **[1.5.8] - Humanización de Mensajería WhatsApp y Portal 1-Click**.

---

## 5. REGLAS INNEGOCIABLES DE EJECUCIÓN
1. **Zero Regresiones:** No alterar identificadores HTML ni llamadas de APIs existentes (`api/soporte.php`, `api/consulta.php`).
2. **Seguridad y Encoding:** Todos los enlaces WhatsApp (`https://wa.me/51...`) deben utilizar `encodeURIComponent()` completo para evitar truncamientos de caracteres con tildes o saltos de línea.
3. **Despliegue Completo:** Subir los cambios a GitHub y sincronizar a producción vía script `private_scripts/sync_ftp_production.py`.
4. **Verificación:** Probar la generación de enlace tanto en móvil como en escritorio y certificar que la consulta cargue sin errores.
