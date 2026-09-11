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
        const tempLabel = l.temperatura === 'PURPURA' ? '🟣 Cita Agendada'
                        : l.temperatura === 'VERDE' ? '🟢 Al día' 
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
                <button class="petulap-btn-action" style="background: #a855f7;" onclick="mostrarFormAgendamiento(${l.id}, '${escapar(l.nombre || nombre)}', '${escapar(l.modelo_interes_texto || '')}')">
                    📅 Agendar Cita en Tienda
                </button>
                <button class="petulap-btn-action" onclick="window.open('${CRM_API_URL}/../index.html', '_blank')">
                    📋 Ver en Tablero Kanban
                </button>
                <button class="petulap-btn-action petulap-btn-secondary" onclick="copiarFichaThinkPad()">
                    ⚡ Copiar Ficha ThinkPad T14
                </button>
            </div>

            <div id="petulap-mini-form-agenda" style="display: none; margin-top: 8px; border-top: 1px solid #334155; padding-top: 8px;">
                <div style="font-size: 11px; font-weight: 700; color: #c084fc; margin-bottom: 4px;">📅 Agendar Visita / Cita</div>
                <select id="p-sede-select" style="width: 100%; padding: 5px; background: #0f172a; color: #fff; border: 1px solid #334155; border-radius: 4px; font-size: 11px; margin-bottom: 4px;">
                    <option value="VISITA_YANAHUARA">Sede Yanahuara (Av. Ejército 314)</option>
                    <option value="VISITA_CAYMA">Sede Cayma (León XIII Mza A-4)</option>
                    <option value="LLAMADA_CIERRE">Llamada de Cierre</option>
                </select>
                <input type="datetime-local" id="p-fecha-input" style="width: 100%; padding: 5px; background: #0f172a; color: #fff; border: 1px solid #334155; border-radius: 4px; font-size: 11px; margin-bottom: 6px;">
                <button class="petulap-btn-action" style="background: #10b981;" onclick="guardarCita(${l.id}, '${escapar(l.nombre || nombre)}')">
                    ✅ Confirmar y Enviar WhatsApp
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

window.mostrarFormAgendamiento = function(leadId, nombre, modelo) {
    const el = document.getElementById('petulap-mini-form-agenda');
    if (el) {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
        // Poner fecha sugerida (hoy + 2 horas)
        const d = new Date();
        d.setHours(d.getHours() + 2);
        d.setMinutes(0);
        const iso = d.toISOString().substring(0, 16);
        const input = document.getElementById('p-fecha-input');
        if (input && !input.value) input.value = iso;
    }
};

window.guardarCita = async function(leadId, nombre) {
    const sede = document.getElementById('p-sede-select')?.value || 'VISITA_YANAHUARA';
    const fechaHora = document.getElementById('p-fecha-input')?.value;

    if (!fechaHora) {
        alert('Por favor selecciona una fecha y hora para la cita.');
        return;
    }

    try {
        const res = await fetch(`${CRM_API_URL}/leads.php?action=agendar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lead_id: leadId,
                tipo: sede,
                fecha_hora: fechaHora.replace('T', ' ') + ':00'
            })
        });

        const json = await res.json();
        if (json.success) {
            // Formatear texto de WhatsApp
            const fechaObj = new Date(fechaHora);
            const fechaStr = fechaObj.toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long' });
            const horaStr = fechaObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            const sedeTexto = sede === 'VISITA_CAYMA' 
                ? 'Sede Cayma (León XIII Mza A-4, 1er piso)' 
                : sede === 'LLAMADA_CIERRE'
                ? 'Llamada Telefónica'
                : 'Sede Yanahuara (Av. Ejército 314, 2do piso)';

            const confirmMsg = 
`¡Confirmado, *${nombre.split(' ')[0]}*! 🤝

📅 Te esperamos el *${fechaStr}* a las *${horaStr}* en nuestra *${sedeTexto}*.

Tendremos la laptop lista y configurada para que la pruebes con total tranquilidad. ¡Nos vemos pronto en Petulap! 🙌`;

            navigator.clipboard.writeText(confirmMsg).then(() => {
                alert('🎉 ¡Cita agendada y registrada en el CRM con temperatura PÚRPURA!\n\n✅ El mensaje de confirmación se copió a tu portapapeles. Solo presiona Ctrl+V para enviárselo al cliente.');
            });

            document.getElementById('petulap-mini-form-agenda').style.display = 'none';
        }
    } catch (err) {
        alert('Error conectando al CRM: ' + err.message);
    }
};

function escapar(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
