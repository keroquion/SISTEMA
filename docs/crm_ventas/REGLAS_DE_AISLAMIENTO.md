# Petulap Sales CRM — Reglas de Aislamiento e Integridad

> **Documento de Gobernanza:** Separación Estricta entre el Sistema Operativo de Taller (Petulap SST) y el CRM de Ventas WhatsApp.

---

## 1. Principio Fundamental de Aislamiento
El nuevo **Petulap Sales CRM** fue concebido y desarrollado bajo el principio de **cero interferencia**:
* **No altera** ninguna de las 20 pantallas operativas preexistentes (`soporte.html`, `garantias.html`, `pedidos_repuestos.html`, `caja.html`, etc.).
* **No modifica** las hojas de estilo globales (`tokens.css`, `styles.css`, `dashboard.css`).
* **No sobreescribe** scripts globales (`dashboard.js`, `check_auth.js`, `sw.js`).

---

## 2. Aislamiento en la Base de Datos (Prefijo `crm_`)
Para evitar colisiones con las 17 tablas operativas de soporte técnico (`soporte_tecnico`, `equipos`, `personas`, etc.), todas las tablas del CRM utilizan obligatoriamente el prefijo **`crm_`**:

1. **`crm_leads`**: Base de prospectos, temperaturas de seguimiento (Verde, Ámbar, Rojo) y etapas Kanban.
2. **`crm_promociones`**: Catálogo comercial de laptops i5, i7 ejecutivas, Workstations y ofertas de campaña.
3. **`crm_vendedores`**: Registro de asesores comerciales y supervisores de venta.
4. **`crm_seguimientos`**: Bitácora de mensajes, llamadas y cotizaciones enviadas.

> **Garantía:** Si en el futuro se desea purgar o migrar el CRM a un servidor o base de datos externa (`petumjvq_crm`), solo se deben exportar las tablas que comiencen con `crm_`.

---

## 3. Aislamiento en el Servidor y Hosting
* En el servidor de producción (`petulap.store`), todos los archivos residen exclusivamente dentro de:
  `/public_html/crm_ventas/`
* La raíz de la web (`/public_html/index.html`, etc.) permanece intacta.
* La ruta de acceso al CRM es:
  👉 `https://petulap.store/crm_ventas/`

---

## 4. Seguridad y Política Anti-Baneo de WhatsApp
1. La extensión de WhatsApp Web (`crm_ventas/extension/`) opera bajo modo de **solo lectura pasiva de DOM** en el navegador del asesor.
2. Está terminantemente prohibido incorporar bots de socket (como Baileys o Puppeteer automatizado en backend) que envíen ráfagas masivas de mensajes, ya que causan baneo automático del número por Meta.
3. El contacto siempre lo inicia el cliente o se envía mediante un clic manual del asesor abriendo `https://wa.me/...`.

---

## 5. Control de Versiones en Git
* Los cambios del CRM deben mantenerse en ramas dedicadas (`feature/crm-ventas-whatsapp`).
* No mezclar commits del CRM con remediaciones de taller a menos que se apruebe una fusión formal en `main`.

---

*Fecha de Entrada en Vigor: Septiembre 2026*  
*Petulap S.A.C. — Arquitectura y Calidad de Software*
