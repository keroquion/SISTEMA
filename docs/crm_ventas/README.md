# Petulap Sales CRM — WhatsApp Pro & Pipeline de Laptops

Sistema independiente de gestión comercial, seguimiento de prospectos por WhatsApp y embudo Kanban para **Petulap Arequipa**, integrado con un **Inspector Pasivo de DOM de WhatsApp Web** 100% seguro (cero riesgo de baneo).

---

## 1. Características Principales

1. **Tablero Kanban Ejecutivo de 6 Fases:**
   - `1. Nuevos Leads` $\rightarrow$ `2. En Asesoría` $\rightarrow$ `3. Cotizados` $\rightarrow$ `4. Visita / Separado` $\rightarrow$ `5. Ganados 🎉` $\rightarrow$ `6. Perdidos`.
2. **Semáforo Inteligente de Seguimiento (SLA en Tiempo Real):**
   - 🟢 **Verde (Al día):** Conversación activa o el cliente respondió recientemente (< 6h).
   - 🟡 **Ámbar (Esperando):** Petulap envió cotización hace 6h - 24h y el cliente aún no responde.
   - 🔴 **Rojo (¡Cliente Frío!):** Más de 24 horas sin respuesta $\rightarrow$ Notificación visual con pulso de alarma para re-enganche inmediato.
3. **Cotizador Express de WhatsApp (1 Clic):**
   - Catálogo de laptops integrado basado en la lista de promociones (`PLAN DE ACCION _ LEAD - PROMO JUNIO`):
     * *Lenovo ThinkPad T14 (S/ 1,190)*
     * *Dell Latitude 7490 (S/ 1,200)*
     * *Dell 5410 Táctil (S/ 1,190)*
     * *ThinkPad L14 i5 10th (S/ 1,390)*
     * *Dell 3420 i5 11th - Solo 2 unidades (S/ 1,390)*
     * *ThinkPad Carbon X1 Gen 10 / Gen 11 (S/ 2,550 - S/ 2,990)*
     * *Workstations para Ingeniería y Render (S/ 1,999 a S/ 3,799)*
   - Genera automáticamente un mensaje de WhatsApp seductor con especificaciones, garantía de 6 meses, 3 años de soporte oficial y direcciones de las sedes de **Yanahuara** y **Cayma**.
4. **Inspector Pasivo de DOM para WhatsApp Web:**
   - Funciona como una extensión local de Chrome (Manifest V3).
   - **Cero riesgo de baneo**: no utiliza bots automatizados ni librerías no oficiales de Meta. Solo lee la pantalla activa (`document.querySelector`) tal como lo haría un humano.
   - Inyecta un widget flotante en `web.whatsapp.com` con el estado del lead y botón para copiar la ficha de laptops al instante.

---

## 2. Estructura de la Carpeta `crm_ventas/`

```text
crm_ventas/
├── index.html              # Tablero Kanban y Dashboard Comercial
├── css/
│   └── crm.css             # Diseño SaaS ejecutivo (modo oscuro y claro)
├── js/
│   └── crm.js              # Lógica Kanban, SLA y cotizador de WhatsApp
├── api/
│   ├── db.php              # Conexión modular MySQL / SQLite
│   ├── schema.sql          # Esquema de base de datos
│   ├── leads.php           # Endpoints de leads, KPIs y cambios de etapa
│   ├── promos.php          # Catálogo estructurado de laptops del CSV
│   └── sync_whatsapp.php   # Sincronizador de DOM recibido de WhatsApp Web
└── extension/              # Extensión de Chrome para WhatsApp Web
    ├── manifest.json       # Manifiesto V3
    ├── content.js          # Lector pasivo de mensajes y cabecera
    └── panel.css           # Estilos del panel inyectado en WhatsApp
```

---

## 3. Cómo Usar el CRM de Ventas

1. **Abrir el Tablero Kanban:**
   - Abre `crm_ventas/index.html` en cualquier navegador web o mediante tu servidor local Apache (`http://localhost/.../crm_ventas/index.html`).
   - Podrás registrar nuevos clientes con el botón **"+ Nuevo Lead"**, filtrarlos por clientes fríos y moverlos entre columnas con un solo clic.
   - En cualquier tarjeta, presiona **"Cotizar"** para seleccionar una laptop y enviar la propuesta con precio especial directo al WhatsApp del cliente.

2. **Cómo Instalar la Extensión de WhatsApp Web (30 Segundos):**
   - En Google Chrome, Edge o Brave, ve a: `chrome://extensions/`.
   - Activa el interruptor **"Modo de desarrollador"** (arriba a la derecha).
   - Haz clic en **"Cargar descomprimida"** (*Load unpacked*).
   - Selecciona la subcarpeta: `docs/crm_ventas/extension/`.
   - ¡Listo! Abre `https://web.whatsapp.com/` y verás el panel de Petulap CRM en la esquina superior derecha sincronizando cada chat en tiempo real.

---

© 2026 Petulap S.A.C. — Arequipa, Perú.
