// ==========================================================
// PETULAP SALES CRM - INSPECTOR PASIVO DE DOM PARA WHATSAPP WEB
// 100% SEGURO: Solo lectura de pantalla, cero riesgo de baneo
// ==========================================================

const CRM_API_URL = 'https://petulap.store/crm_ventas/api'; // Producción oficial
let ultimoTelefonoAnalizado = '';
let widgetElement = null;

console.log('🚀 [Petulap CRM] Inspector pasivo de WhatsApp Web iniciado.');

// Inicializar widget en la página
function initWidget() {
    if (document.getElementById('petulap-crm-sidebar')) return;

    widgetElement = document.createElement('div');
    widgetElement.id = 'petulap-crm-sidebar';
    widgetElement.innerHTML = `
        <div class="petulap-min-icon" style="display: none;">💻</div>
        <div class="petulap-content">
            <div class="petulap-header">
                <div class="petulap-title">
                    <span>⚡ Petulap Sales CRM</span>
                </div>
                <button class="petulap-close-btn" id="petulap-btn-toggle" title="Minimizar / Expandir">_</button>
            </div>
            <div class="petulap-body" id="petulap-widget-body">
                <div style="color: #94a3b8; font-size: 11px;">Abre un chat para sincronizar con el CRM...</div>
            </div>
        </div>
    `;

    document.body.appendChild(widgetElement);

    document.getElementById('petulap-btn-toggle')?.addEventListener('click', (e) => {
        e.stopPropagation();
        widgetElement.classList.toggle('minimized');
    });

    widgetElement.addEventListener('click', () => {
        if (widgetElement.classList.contains('minimized')) {
            widgetElement.classList.remove('minimized');
        }
    });
}

// Escaneo pasivo cada 2.5 segundos
setInterval(() => {
    initWidget();
    analizarChatActivo();
}, 2500);

// Función de análisis de DOM
async function analizarChatActivo() {
    const mainChat = document.querySelector('#main');
    if (!mainChat) return;

    // 1. Obtener cabecera del chat actual
    const header = mainChat.querySelector('header');
    if (!header) return;

    // Extraer título / nombre / teléfono
    const titleEl = header.querySelector('span[dir="auto"], [title]');
    const nombreOtelefono = titleEl ? (titleEl.getAttribute('title') || titleEl.textContent || '').trim() : '';

    if (!nombreOtelefono) return;

    // Intentar extraer dígitos si es número
    const digitos = nombreOtelefono.replace(/\D/g, '');
    let telefono = digitos;
    let nombre = nombreOtelefono;

    // Si tiene 9 dígitos peruanos, anteponer 51
    if (telefono.length === 9) {
        telefono = '51' + telefono;
    } else if (telefono.length < 8) {
        // Es un nombre guardado en agenda (ej: "Juan Pérez")
        telefono = '51_' + encodeURIComponent(nombreOtelefono);
    }

    // 2. Extraer el último mensaje del chat
    const bubbles = mainChat.querySelectorAll('div[data-id], .message-in, .message-out');
    let ultimoEmisor = 'CLIENTE';
    let ultimoTexto = '';

    if (bubbles.length > 0) {
        const lastBubble = bubbles[bubbles.length - 1];
        
        // Detectar si fue saliente (nosotros) o entrante (el cliente)
        const isOut = lastBubble.classList.contains('message-out') || 
                      lastBubble.querySelector('[data-icon="msg-dblcheck"], [data-icon="msg-check"]') !== null ||
                      (lastBubble.getAttribute('data-id') || '').includes('true_');

        ultimoEmisor = isOut ? 'PETULAP' : 'CLIENTE';

        // Extraer texto del mensaje
        const textSpan = lastBubble.querySelector('.selectable-text, span[dir="ltr"], span[dir="auto"]');
        if (textSpan) {
            ultimoTexto = textSpan.textContent.trim();
        }
    }

    // Si no ha cambiado el teléfono ni el estado, evitar llamadas repetitivas
    const firmaActual = `${telefono}_${ultimoEmisor}_${ultimoTexto.substring(0, 20)}`;
    if (firmaActual === ultimoTelefonoAnalizado) return;
    ultimoTelefonoAnalizado = firmaActual;

    // 3. Notificar al backend del CRM de forma silenciosa
    try {
        const res = await fetch(`${CRM_API_URL}/sync_whatsapp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                telefono: telefono,
                nombre: nombre,
                ultimo_mensaje_emisor: ultimoEmisor,
                ultimo_mensaje_texto: ultimoTexto,
                ultimo_mensaje_hora: new Date().toISOString()
            })
        });

        const json = await res.json();
        actualizarWidget(json, nombre, telefono, ultimoEmisor);
    } catch (err) {
        // Fallback visual si el servidor local está apagado
        renderizarWidgetOffline(nombre, telefono, ultimoEmisor, ultimoTexto);
    }
}

// Actualizar el panel flotante con la respuesta del CRM
function actualizarWidget(data, nombre, telefono, ultimoEmisor) {
    const body = document.getElementById('petulap-widget-body');
    if (!body) return;

    if (data.status === 'actualizado' && data.lead) {
        const l = data.lead;
        const tempClass = `petulap-badge-${(l.temperatura || 'VERDE').toLowerCase()}`;
        const tempLabel = l.temperatura === 'VERDE' ? '🟢 Al día' 
                        : l.temperatura === 'AMBAR' ? '🟡 Esperando (>6h)' 
                        : '🔴 ¡Cliente Frío (>24h)!';

        body.innerHTML = `
            <div class="petulap-lead-name">${escapar(l.nombre || nombre)}</div>
            <div style="color: #94a3b8; font-size: 11px;">Etapa: <strong>${l.etapa}</strong></div>
            
            <div class="petulap-status-badge ${tempClass}">
                ${tempLabel}
            </div>

            ${l.modelo_interes_texto ? `
                <div style="font-size: 11px; color: #38bdf8;">
                    💻 Interés: ${escapar(l.modelo_interes_texto)}
                </div>
            ` : ''}

            <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 6px;">
                <button class="petulap-btn-action" onclick="window.open('${CRM_API_URL}/../index.html', '_blank')">
                    📋 Ver en Tablero Kanban
                </button>
                <button class="petulap-btn-action petulap-btn-secondary" onclick="copiarFichaThinkPad()">
                    ⚡ Copiar Ficha ThinkPad T14
                </button>
            </div>
        `;
    } else {
        // Prospecto no registrado aún
        body.innerHTML = `
            <div class="petulap-lead-name">${escapar(nombre)}</div>
            <div style="color: #f59e0b; font-size: 11px;">⚠️ Prospecto nuevo no registrado</div>
            
            <button class="petulap-btn-action" style="margin-top: 6px;" onclick="window.open('${CRM_API_URL}/../index.html', '_blank')">
                ➕ Registrar en Petulap CRM
            </button>
        `;
    }
}

function renderizarWidgetOffline(nombre, telefono, ultimoEmisor, ultimoTexto) {
    const body = document.getElementById('petulap-widget-body');
    if (!body) return;

    body.innerHTML = `
        <div class="petulap-lead-name">${escapar(nombre)}</div>
        <div style="color: #94a3b8; font-size: 11px;">Último emisor: <strong>${ultimoEmisor}</strong></div>
        <div style="font-size: 10px; color: #64748b; font-style: italic; margin-top: 4px;">
            "${escapar(ultimoTexto.substring(0, 40))}..."
        </div>
        <div style="margin-top: 8px;">
            <button class="petulap-btn-action" onclick="window.open('${CRM_API_URL}/../index.html', '_blank')">
                🚀 Abrir Petulap CRM
            </button>
        </div>
    `;
}

// Helper para copiar plantilla de venta directa de laptop ThinkPad T14
window.copiarFichaThinkPad = function() {
    const texto = 
`¡Hola! 👋 Te comparto nuestra opción más recomendada en promoción:

💻 *Lenovo ThinkPad T14*
⚙️ Procesador Intel Core i5 | 16 GB RAM | 512 GB SSD
🏷️ *Precio Especial Promo:* S/ 1,190 (Precio regular: S/ 1,350)
🛡️ 6 meses de garantía oficial Petulap + 3 años de servicio técnico en Arequipa.

📍 Puedes probarla hoy mismo en:
• Sede Yanahuara: Av. Ejército 314, 2do piso
• Sede Cayma: León XIII Mza A-4
¿Deseas que te la separe mientras vienes a la tienda? 🙌`;

    navigator.clipboard.writeText(texto).then(() => {
        alert('✅ Ficha de ThinkPad T14 copiada al portapapeles. Solo pega con Ctrl+V en el chat.');
    });
};

function escapar(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
