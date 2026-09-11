// ==========================================================
// PETULAP SALES CRM - LÓGICA DE NEGOCIO Y KANBAN
// Control de etapas, cotizador WhatsApp y cálculo de SLA en vivo
// ==========================================================

const API_BASE = 'api';
let leadsData = [];
let promosData = [];
let activeFilter = 'TODOS';
let activeLeadForQuote = null;
let activeVendedorId = localStorage.getItem('petulap-crm-vendedor') || '0';
let focoHoyActivo = false;
let activeLeadForDisparos = null;
let activeLeadForLlamada = null;

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initAdvisorSelector();
    cargarLeads();
    cargarStats();
    cargarPromociones();
    cargarRecordatoriosHoy();
    cargarBolsaRescate();
    initEventos();

    // Auto-refrescar cada 60 segundos para mantener el semáforo y la bolsa al día
    setInterval(() => {
        cargarLeads(false);
        cargarStats();
        cargarRecordatoriosHoy();
        cargarBolsaRescate();
    }, 60000);
});

// ----------------------------------------------------------
// 1. GESTIÓN DEL TEMA OSCURO / CLARO
// ----------------------------------------------------------
function initTheme() {
    const saved = localStorage.getItem('petulap-crm-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    actualizarIconoTema(saved);
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('petulap-crm-theme', next);
    actualizarIconoTema(next);
}

function actualizarIconoTema(theme) {
    const btn = document.getElementById('theme-toggle-btn');
    if (btn) {
        btn.innerHTML = theme === 'dark' 
            ? '<i class="ph ph-sun"></i> Modo Claro' 
            : '<i class="ph ph-moon"></i> Modo Oscuro';
    }
}

// ----------------------------------------------------------
// 1.1 SINTETIZADOR DE AUDIO WEBAUDIO (Cero dependencias externas)
// ----------------------------------------------------------
function reproducirAlarmaAudio(tipo = 'cita') {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        if (ctx.state === 'suspended') {
            ctx.resume();
        }
        
        if (tipo === 'ganado') {
            // Fanfarria triunfal de 4 notas ascendentes (C5, E5, G5, C6)
            const notas = [523.25, 659.25, 783.99, 1046.50];
            notas.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, ctx.currentTime + (i * 0.12));
                gain.gain.setValueAtTime(0.25, ctx.currentTime + (i * 0.12));
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + (i * 0.12) + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + (i * 0.12));
                osc.stop(ctx.currentTime + (i * 0.12) + 0.36);
            });
        } else {
            // Chime armónico de doble campana para citas y alertas urgentes
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const gainNode = ctx.createGain();

            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(880, ctx.currentTime); // A5
            osc2.type = 'triangle';
            osc2.frequency.setValueAtTime(1760, ctx.currentTime); // A6

            gainNode.gain.setValueAtTime(0.25, ctx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);

            osc1.connect(gainNode);
            osc2.connect(gainNode);
            gainNode.connect(ctx.destination);

            osc1.start();
            osc2.start();
            osc1.stop(ctx.currentTime + 0.8);
            osc2.stop(ctx.currentTime + 0.8);
        }
    } catch (e) {
        console.warn('AudioContext no disponible o bloqueado:', e);
    }
}

// ----------------------------------------------------------
// 2. CARGAR Y RENDERIZAR LEADS EN EL KANBAN
// ----------------------------------------------------------
async function cargarLeads(mostrarLoading = true) {
    try {
        const url = `${API_BASE}/leads.php?action=list&vendedor_id=${activeVendedorId}&foco_hoy=${focoHoyActivo ? '1' : '0'}`;
        const res = await fetch(url);
        const json = await res.json();
        if (json.success) {
            leadsData = json.data;
            // Si está vacío en la primera carga, insertar datos de muestra demostrativos
            if (leadsData.length === 0) {
                leadsData = [
                    {
                        id: 1,
                        nombre: "Kevin Quicaño",
                        telefono: "51983396137",
                        etapa: "COTIZADO",
                        temperatura: "ROJO",
                        horas_sin_contacto: 26,
                        modelo_interes_texto: "ThinkPad T14 (i5 10th)",
                        presupuesto_aprox: 1200,
                        ultimo_mensaje_texto: "Te pasé la cotización de la T14 a S/ 1,190. ¿La pudiste revisar?",
                        ultimo_mensaje_emisor: "PETULAP"
                    },
                    {
                        id: 2,
                        nombre: "Mariana Delgado",
                        telefono: "51942770228",
                        etapa: "ASESORIA",
                        temperatura: "VERDE",
                        horas_sin_contacto: 1,
                        modelo_interes_texto: "Dell 5410 Táctil o Latitude 7490",
                        presupuesto_aprox: 1300,
                        ultimo_mensaje_texto: "¿La Dell táctil tiene garantía y cargador original?",
                        ultimo_mensaje_emisor: "CLIENTE"
                    },
                    {
                        id: 3,
                        nombre: "Ing. Roberto Alarcón",
                        telefono: "51959123456",
                        etapa: "VISITA_SEPARADO",
                        temperatura: "AMBAR",
                        horas_sin_contacto: 8,
                        modelo_interes_texto: "ThinkPad Carbon X1 Gen 10",
                        presupuesto_aprox: 2550,
                        ultimo_mensaje_texto: "Perfecto, pasaré por la sede de Yanahuara hoy a las 5pm para verla.",
                        ultimo_mensaje_emisor: "CLIENTE"
                    },
                    {
                        id: 4,
                        nombre: "Claudia Zúñiga",
                        telefono: "51958765432",
                        etapa: "GANADO",
                        temperatura: "VERDE",
                        horas_sin_contacto: 3,
                        modelo_interes_texto: "HP ProBook 440 G8 (i7 11th)",
                        presupuesto_aprox: 1790,
                        ultimo_mensaje_texto: "¡Muchas gracias! Ya recogí mi laptop en Cayma con su boleta.",
                        ultimo_mensaje_emisor: "CLIENTE"
                    }
                ];
            }
            renderizarKanban();
        }
    } catch (err) {
        console.error('Error cargando leads:', err);
    }
}

async function cargarStats() {
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=stats`);
        const json = await res.json();
        if (json.success && json.stats) {
            const s = json.stats;
            document.getElementById('kpi-total').textContent = s.total;
            document.getElementById('kpi-cotizados').textContent = s.cotizados;
            document.getElementById('kpi-ganados').textContent = s.ganados;
            document.getElementById('kpi-rojos').textContent = s.rojos_frios;
            document.getElementById('kpi-monto').textContent = `S/ ${s.monto_proyectado.toLocaleString('es-PE', {minimumFractionDigits: 0})}`;
        }
    } catch (err) {
        console.error('Error cargando stats:', err);
    }
}

function renderizarKanban() {
    const columnas = {
        'NUEVO': document.getElementById('cards-NUEVO'),
        'ASESORIA': document.getElementById('cards-ASESORIA'),
        'COTIZADO': document.getElementById('cards-COTIZADO'),
        'VISITA_SEPARADO': document.getElementById('cards-VISITA_SEPARADO'),
        'GANADO': document.getElementById('cards-GANADO'),
        'PERDIDO': document.getElementById('cards-PERDIDO')
    };

    // Limpiar columnas
    Object.values(columnas).forEach(col => { if (col) col.innerHTML = ''; });
    const conteos = { NUEVO: 0, ASESORIA: 0, COTIZADO: 0, VISITA_SEPARADO: 0, GANADO: 0, PERDIDO: 0 };

    const searchTerm = (document.getElementById('search-input')?.value || '').toLowerCase();

    leadsData.forEach(lead => {
        // Filtro por búsqueda
        const coincide = lead.nombre.toLowerCase().includes(searchTerm) ||
                         lead.telefono.includes(searchTerm) ||
                         (lead.modelo_interes_texto || '').toLowerCase().includes(searchTerm);
        if (!coincide) return;

        // Filtro por temperatura / frío si aplica
        if (activeFilter === 'ROJO' && lead.temperatura !== 'ROJO') return;
        if (activeFilter === 'SEPARADOS' && lead.etapa !== 'VISITA_SEPARADO') return;
        if (activeFilter === 'INMINENTE' && lead.prioridad_compra !== 'INMINENTE') return;

        const etapa = columnas[lead.etapa] ? lead.etapa : 'NUEVO';
        conteos[etapa]++;

        const card = crearTarjetaLead(lead);
        columnas[etapa].appendChild(card);
    });

    // Actualizar contadores de cabecera
    Object.keys(conteos).forEach(et => {
        const counter = document.getElementById(`count-${et}`);
        if (counter) counter.textContent = conteos[et];
    });
}

// ----------------------------------------------------------
// 3. GENERADOR DE TARJETA HTML DE LEAD
// ----------------------------------------------------------
function crearTarjetaLead(lead) {
    const div = document.createElement('div');
    const esInminente = lead.prioridad_compra === 'INMINENTE';
    div.className = `lead-card ${esInminente ? 'inminente' : ''}`;
    div.draggable = true;
    div.dataset.id = lead.id;

    // Semáforo SLA (4 Temperaturas)
    const tempClass = `temp-${lead.temperatura.toLowerCase()}`;
    const pulseClass = `pulse-${lead.temperatura.toLowerCase()}`;
    const tempLabel = lead.temperatura === 'PURPURA' ? '🟣 Cita Agendada'
                    : lead.temperatura === 'VERDE' ? '🟢 Al día' 
                    : lead.temperatura === 'AMBAR' ? '🟡 Esperando resp.' 
                    : '🔴 ¡Cliente Frío!';

    // Formatear tiempo transcurrido
    let tiempoTexto = 'Hace instantes';
    if (lead.horas_sin_contacto !== undefined) {
        if (lead.horas_sin_contacto >= 24) {
            const dias = Math.floor(lead.horas_sin_contacto / 24);
            tiempoTexto = `Hace ${dias} día${dias > 1 ? 's' : ''}`;
        } else if (lead.horas_sin_contacto > 0) {
            tiempoTexto = `Hace ${lead.horas_sin_contacto}h`;
        }
    }

    const telLimpio = lead.telefono.replace(/\D/g, '');
    const waUrl = `https://wa.me/${telLimpio}`;

    div.innerHTML = `
        <div class="card-top">
            <span class="lead-name">${escapar(lead.nombre)}</span>
            <div style="display: flex; gap: 4px; align-items: center;">
                ${esInminente ? `
                    <span class="temp-badge temp-inminente" title="Alerta: Cliente en fase decisiva de compra">
                        <span class="pulse-dot pulse-inminente"></span> 🔥 INMINENTE
                    </span>
                ` : ''}
                <span class="temp-badge ${tempClass}">
                    <span class="pulse-dot ${pulseClass}"></span>
                    ${tempLabel}
                </span>
            </div>
        </div>

        <div class="lead-model">
            <i class="ph-bold ph-laptop"></i>
            <span>${escapar(lead.modelo_interes_texto || 'Buscando laptop')}</span>
        </div>

        ${lead.presupuesto_aprox > 0 ? `
            <div class="lead-price">Presupuesto: S/ ${Number(lead.presupuesto_aprox).toFixed(2)}</div>
        ` : ''}

        ${lead.ultimo_mensaje_texto ? `
            <div class="lead-msg-snippet" title="${escapar(lead.ultimo_mensaje_texto)}">
                "${escapar(lead.ultimo_mensaje_texto)}"
            </div>
        ` : ''}

        <div class="card-footer">
            <span class="lead-time"><i class="ph ph-clock"></i> ${tiempoTexto}</span>
            <div class="card-actions">
                <button class="btn-card-move" style="color: #fb923c; border-color: ${esInminente ? '#f97316' : 'var(--border-color)'};" onclick="togglePrioridadInminente(${lead.id}, '${lead.prioridad_compra || 'NORMAL'}')" title="${esInminente ? 'Quitar Compra Inminente' : 'Marcar como Compra Inminente / VIP'}">
                    <i class="ph-bold ph-fire"></i>
                </button>
                <button class="btn-card-move" style="color: #60a5fa; border-color: #3b82f6;" onclick="abrirModalDisparos(${lead.id})" title="Cadencia de 4 Disparos WhatsApp para Laptops Usadas">
                    <i class="ph-bold ph-chat-circle-dots"></i>
                </button>
                <button class="btn-card-move" style="color: var(--success); border-color: var(--success);" onclick="abrirModalLlamada(${lead.id})" title="Registrar Llamada Telefónica">
                    <i class="ph-bold ph-phone-call"></i>
                </button>
                <button class="btn-card-move" onclick="abrirCotizador(${lead.id})" title="Enviar Ficha y Cotización">
                    <i class="ph ph-file-text"></i>
                </button>
                <button class="btn-card-move" style="color: #c084fc; border-color: #a855f7;" onclick="abrirModalAgendar(${lead.id})" title="Agendar Cita en Tienda / Llamada">
                    <i class="ph-bold ph-calendar"></i>
                </button>
                <a href="${waUrl}" target="_blank" class="btn-card-wa" title="Abrir Chat WhatsApp">
                    <i class="ph-bold ph-whatsapp-logo"></i>
                </a>
                <button class="btn-card-move" onclick="avanzarEtapa(${lead.id}, '${lead.etapa}')" title="Avanzar etapa">
                    <i class="ph-bold ph-arrow-right"></i>
                </button>
            </div>
        </div>
    `;

    return div;
}

// ----------------------------------------------------------
// 4. AVANZAR ETAPA DE FORMA QUIRÚRGICA
// ----------------------------------------------------------
async function avanzarEtapa(leadId, etapaActual) {
    const orden = ['NUEVO', 'ASESORIA', 'COTIZADO', 'VISITA_SEPARADO', 'GANADO'];
    const idx = orden.indexOf(etapaActual);
    if (idx === -1 || idx === orden.length - 1) return;

    const siguienteEtapa = orden[idx + 1];
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=cambiar_etapa`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: leadId, etapa: siguienteEtapa })
        });
        const json = await res.json();
        if (json.success) {
            if (siguienteEtapa === 'GANADO') {
                reproducirAlarmaAudio('ganado');
            }
            cargarLeads(false);
            cargarStats();
        }
    } catch (err) {
        console.error('Error avanzando etapa:', err);
    }
}

// ----------------------------------------------------------
// 5. COTIZADOR EXPRESS DE WHATSAPP CON CATÁLOGO
// ----------------------------------------------------------
async function cargarPromociones() {
    try {
        const res = await fetch(`${API_BASE}/promos.php?action=list`);
        const json = await res.json();
        if (json.success) {
            promosData = json.data;
            renderizarListaPromos();
        }
    } catch (err) {
        console.error('Error cargando promos:', err);
    }
}

function renderizarListaPromos() {
    const container = document.getElementById('promo-list-container');
    if (!container) return;
    container.innerHTML = '';

    promosData.forEach(p => {
        const stockReal = p.stock_real !== undefined ? parseInt(p.stock_real, 10) : Math.max(0, (parseInt(p.stock_disponible, 10) || 0) - (parseInt(p.unidades_reservadas, 10) || 0));
        const reservadas = parseInt(p.unidades_reservadas, 10) || 0;
        const sinStock = stockReal <= 0;

        const item = document.createElement('div');
        item.className = 'promo-item';
        item.innerHTML = `
            <div>
                <strong>${escapar(p.marca)} ${escapar(p.modelo)}</strong>
                <div style="font-size: 0.78rem; color: var(--text-secondary);">
                    ${escapar(p.procesador)} | ${escapar(p.ram)} | ${escapar(p.almacenamiento)} | ${escapar(p.pantalla)}
                </div>
                <div style="display: flex; gap: 6px; align-items: center; margin-top: 3px; flex-wrap: wrap;">
                    <span style="font-size: 0.72rem; color: ${sinStock ? 'var(--danger)' : 'var(--success)'}; font-weight: 700;">
                        ${sinStock ? '⛔ Sin stock libre' : `📦 ${stockReal} disponible${stockReal > 1 ? 's' : ''}`}
                    </span>
                    ${reservadas > 0 ? `
                        <span style="font-size: 0.68rem; background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.4); padding: 1px 6px; border-radius: 4px;" title="Bloqueo temporal de stock por cita agendada">
                            🔒 ${reservadas} reservada${reservadas > 1 ? 's' : ''} (24h)
                        </span>
                    ` : ''}
                    ${p.nota_stock ? `<span style="font-size: 0.7rem; color: var(--warning); font-weight: 700;">⚠️ ${escapar(p.nota_stock)}</span>` : ''}
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1rem; font-weight: 800; color: var(--primary);">S/ ${p.precio_promo}</div>
                ${p.precio_regular ? `<div style="font-size: 0.75rem; text-decoration: line-through; color: var(--text-muted);">S/ ${p.precio_regular}</div>` : ''}
                <button class="btn btn-wa" style="padding: 4px 10px; font-size: 0.75rem; margin-top: 4px;" onclick="enviarFichaWhatsApp(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                    <i class="ph-bold ph-whatsapp-logo"></i> Enviar
                </button>
            </div>
        `;
        container.appendChild(item);
    });
}

function abrirCotizador(leadId) {
    activeLeadForQuote = leadsData.find(l => l.id == leadId);
    if (!activeLeadForQuote) return;

    document.getElementById('modal-quote-cliente').textContent = activeLeadForQuote.nombre;
    document.getElementById('modal-quote-tel').textContent = activeLeadForQuote.telefono;
    document.getElementById('modal-cotizador').style.display = 'flex';
}

function enviarFichaWhatsApp(promo) {
    if (!activeLeadForQuote) return;

    const nombre = activeLeadForQuote.nombre.split(' ')[0];
    const mensaje = 
`¡Hola, *${nombre}*! 👋 Te saluda el equipo de *Petulap Arequipa* 💻

Revisando lo que buscas, esta opción en promoción es perfecta para ti:

✨ *${promo.marca} ${promo.modelo}*
⚙️ *Procesador:* ${promo.procesador}
🧠 *Memoria RAM:* ${promo.ram}
💾 *Almacenamiento:* ${promo.almacenamiento}
🖥️ *Pantalla:* ${promo.pantalla}

🏷️ *Precio Especial de Promo:* S/ ${promo.precio_promo} ${promo.precio_regular ? `_(Precio regular: S/ ${promo.precio_regular})_` : ''}
${promo.nota_stock ? `🔥 *Disponibilidad:* ${promo.nota_stock}\n` : ''}
🛡️ *Garantía Oficial:* 6 meses en el equipo + 3 años de servicio técnico oficial Petulap.

📍 *Puedes verla y probarla hoy mismo en nuestras sedes:*
• *Yanahuara:* Av. Ejército 314, 2do piso
• *Cayma:* León XIII Mza A-4, 1er piso
⏰ Lun-Vie: 10:30am - 7:30pm | Sáb: 10:30am - 6:00pm

¿Deseas que te la separe mientras te acercas a la tienda? 🙌`;

    const telLimpio = activeLeadForQuote.telefono.replace(/\D/g, '');
    const url = `https://wa.me/${telLimpio}?text=${encodeURIComponent(mensaje)}`;
    window.open(url, '_blank');

    // Mover automáticamente el lead a 'COTIZADO'
    avanzarEtapa(activeLeadForQuote.id, 'ASESORIA');
    cerrarModal('modal-cotizador');
}

// ----------------------------------------------------------
// 6. REGISTRAR NUEVO LEAD
// ----------------------------------------------------------
async function guardarNuevoLead(e) {
    e.preventDefault();
    const form = e.target;
    const datos = {
        nombre: form.nombre.value.trim(),
        telefono: form.telefono.value.trim(),
        modelo_interes_texto: form.modelo.value.trim(),
        presupuesto_aprox: parseFloat(form.presupuesto.value) || 0,
        sede_preferida: form.sede.value,
        origen_lead: form.origen.value,
        notas: form.notas.value.trim()
    };

    try {
        const res = await fetch(`${API_BASE}/leads.php?action=crear`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        const json = await res.json();
        if (json.success) {
            form.reset();
            cerrarModal('modal-nuevo-lead');
            cargarLeads();
            cargarStats();
        } else {
            alert(json.error || 'Error al guardar');
        }
    } catch (err) {
        console.error('Error guardando lead:', err);
    }
}

// ----------------------------------------------------------
// 7. EVENTOS Y MODALES
// ----------------------------------------------------------
function initEventos() {
    document.getElementById('search-input')?.addEventListener('input', () => {
        renderizarKanban();
    });

    document.getElementById('form-nuevo-lead')?.addEventListener('submit', guardarNuevoLead);
    document.getElementById('form-agendar-lead')?.addEventListener('submit', guardarAgendamientoLead);

    // Filtros rápidos
    document.querySelectorAll('.chip').forEach(chip => {
        chip.addEventListener('click', (e) => {
            document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
            e.currentTarget.classList.add('active');
            activeFilter = e.currentTarget.dataset.filter;
            renderizarKanban();
        });
    });
}

// ----------------------------------------------------------
// 8. RECORDATORIOS Y CITAS DE HOY (DRAWER Y CAMPANA)
// ----------------------------------------------------------
async function cargarRecordatoriosHoy() {
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=recordatorios_hoy`);
        const json = await res.json();
        if (!json.success) return;

        const total = json.total_pendientes || 0;
        const badge = document.getElementById('badge-notif-count');
        if (badge) {
            badge.textContent = total;
            badge.style.display = total > 0 ? 'inline-block' : 'none';
        }

        renderizarDrawerRecordatorios(json.citas_hoy || [], json.clientes_frios || []);
    } catch (err) {
        console.error('Error cargando recordatorios:', err);
    }
}

function toggleDrawerRecordatorios() {
    const drawer = document.getElementById('drawer-recordatorios');
    if (drawer) drawer.classList.toggle('open');
}

function renderizarDrawerRecordatorios(citas, frios) {
    const list = document.getElementById('drawer-recordatorios-list');
    if (!list) return;
    list.innerHTML = '';

    if (citas.length === 0 && frios.length === 0) {
        list.innerHTML = `
            <div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 0.9rem;">
                <i class="ph-bold ph-check-circle" style="font-size: 2rem; color: var(--success); display: block; margin-bottom: 8px;"></i>
                ¡Todo al día! No hay citas pendientes ni clientes fríos urgentes.
            </div>
        `;
        return;
    }

    // 1. Renderizar citas de hoy
    if (citas.length > 0) {
        const seccionCitas = document.createElement('div');
        seccionCitas.innerHTML = `<div style="font-size: 0.8rem; font-weight: 800; color: #c084fc; margin-bottom: 8px; text-transform: uppercase;">📅 Citas Programadas para Hoy (${citas.length})</div>`;
        
        citas.forEach(c => {
            const hora = new Date(c.fecha_hora).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const item = document.createElement('div');
            item.className = 'reminder-item purpura';
            item.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong style="font-size: 0.95rem; color: #fff;">${escapar(c.cliente_nombre)}</strong>
                    <span style="font-size: 0.8rem; font-weight: 800; color: #c084fc;">⏰ ${hora}</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                    💻 ${escapar(c.modelo_laptop || 'Laptop por definir')} • 📍 ${c.tipo.includes('CAYMA') ? 'Cayma' : 'Yanahuara'}
                </div>
                ${c.notas ? `<div style="font-size: 0.75rem; color: var(--text-muted); font-style: italic;">"${escapar(c.notas)}"</div>` : ''}
                <div style="display: flex; gap: 6px; margin-top: 4px;">
                    <a href="https://wa.me/${c.telefono.replace(/\D/g, '')}" target="_blank" class="btn btn-wa" style="padding: 4px 8px; font-size: 0.72rem;">
                        <i class="ph-bold ph-whatsapp-logo"></i> WhatsApp
                    </a>
                    <button class="btn btn-primary" style="padding: 4px 8px; font-size: 0.72rem; background: var(--success);" onclick="completarCita(${c.id}, true)">
                        ✅ Venta Ganada
                    </button>
                    <button class="btn btn-ghost" style="padding: 4px 8px; font-size: 0.72rem;" onclick="completarCita(${c.id}, false)">
                        ❌ No asistió
                    </button>
                </div>
            `;
            seccionCitas.appendChild(item);
        });
        list.appendChild(seccionCitas);
    }

    // 2. Renderizar clientes fríos (>24h)
    if (frios.length > 0) {
        const seccionFrios = document.createElement('div');
        seccionFrios.style.marginTop = '14px';
        seccionFrios.innerHTML = `<div style="font-size: 0.8rem; font-weight: 800; color: var(--danger); margin-bottom: 8px; text-transform: uppercase;">🔥 Clientes Fríos / Dejados en Visto (${frios.length})</div>`;
        
        frios.forEach(f => {
            const item = document.createElement('div');
            item.className = 'reminder-item rojo';
            item.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong style="font-size: 0.95rem; color: #fff;">${escapar(f.nombre)}</strong>
                    <span style="font-size: 0.75rem; color: var(--danger); font-weight: 700;">>24h sin respuesta</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                    Interés: ${escapar(f.modelo_interes_texto || 'Laptop')}
                </div>
                <div style="display: flex; gap: 6px; margin-top: 4px;">
                    <button class="btn btn-wa" style="padding: 4px 8px; font-size: 0.72rem;" onclick="enviarReengancheWhatsApp('${f.telefono}', '${escapar(f.nombre)}')">
                        🔥 Enviar Re-enganche
                    </button>
                    <button class="btn btn-ghost" style="padding: 4px 8px; font-size: 0.72rem;" onclick="abrirModalAgendar(${f.id})">
                        📅 Agendar Cita
                    </button>
                </div>
            `;
            seccionFrios.appendChild(item);
        });
        list.appendChild(seccionFrios);
    }
}

function enviarReengancheWhatsApp(telefono, nombre) {
    const primerNombre = nombre.split(' ')[0];
    const mensaje = 
`¡Hola, *${primerNombre}*! 👋 Te saluda nuevamente el equipo de *Petulap Arequipa* 💻

¿Pudiste revisar la información de la laptop que conversamos? 
Te comento que nos van quedando *pocas unidades disponibles en promoción*. Si te animas a pasar hoy por nuestra sede de Yanahuara o Cayma, te podemos incluir *mouse inalámbrico o funda de regalo* con tu compra 🎁✨

¿Te gustaría que te reserve una para probarla hoy? 🙌`;

    const telLimpio = telefono.replace(/\D/g, '');
    window.open(`https://wa.me/${telLimpio}?text=${encodeURIComponent(mensaje)}`, '_blank');
}

// ----------------------------------------------------------
// 9. MODAL Y ACCIÓN DE AGENDAR DESDE EL KANBAN
// ----------------------------------------------------------
function abrirModalAgendar(leadId) {
    const lead = leadsData.find(l => l.id == leadId);
    if (!lead) return;

    document.getElementById('agenda-lead-id').value = lead.id;
    document.getElementById('agenda-cliente-nombre').textContent = `${lead.nombre} (${lead.telefono})`;
    document.getElementById('agenda-modelo').value = lead.modelo_interes_texto || '';
    
    // Sugerir fecha: hoy + 2 horas
    const d = new Date();
    d.setHours(d.getHours() + 2);
    d.setMinutes(0);
    document.getElementById('agenda-fecha-hora').value = d.toISOString().substring(0, 16);

    abrirModal('modal-agendar-lead');
}

async function guardarAgendamientoLead(e) {
    e.preventDefault();
    const form = e.target;
    const datos = {
        lead_id: form.lead_id.value,
        tipo: form.tipo.value,
        fecha_hora: form.fecha_hora.value.replace('T', ' ') + ':00',
        modelo_laptop: form.modelo_laptop.value.trim(),
        notas: form.notas.value.trim()
    };

    try {
        const res = await fetch(`${API_BASE}/leads.php?action=agendar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        const json = await res.json();
        if (json.success) {
            cerrarModal('modal-agendar-lead');
            reproducirAlarmaAudio('cita');
            cargarLeads(false);
            cargarStats();
            cargarRecordatoriosHoy();
            cargarPromociones();
            alert('🎉 ¡Cita agendada con éxito! El lead avanzó a Visita/Separado con temperatura PÚRPURA y el equipo quedó reservado por 24h.');
        } else {
            alert(json.error || 'Error al agendar');
        }
    } catch (err) {
        console.error('Error guardando agendamiento:', err);
    }
}

async function completarCita(agId, cerrarVenta) {
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=completar_agendamiento`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: agId,
                estado: cerrarVenta ? 'COMPLETADO' : 'NO_ASISTIO',
                venta_cerrada: cerrarVenta
            })
        });
        const json = await res.json();
        if (json.success) {
            if (cerrarVenta) {
                reproducirAlarmaAudio('ganado');
            }
            cargarLeads(false);
            cargarStats();
            cargarRecordatoriosHoy();
            cargarPromociones();
        }
    } catch (err) {
        console.error('Error completando cita:', err);
    }
}

// ----------------------------------------------------------
// 10. MODAL RANKING COMERCIAL Y COMPARATIVA DE SEDES
// ----------------------------------------------------------
async function abrirModalRanking() {
    const container = document.getElementById('ranking-modal-body');
    if (!container) return;
    abrirModal('modal-ranking-ventas');
    container.innerHTML = `
        <div style="text-align: center; padding: 30px; color: var(--text-muted);">
            <i class="ph-bold ph-spinner ph-spin" style="font-size: 2rem; color: var(--primary);"></i>
            <p style="margin-top: 8px;">Cargando métricas comerciales de sedes...</p>
        </div>
    `;

    try {
        const res = await fetch(`${API_BASE}/leads.php?action=ranking_asesores`);
        const json = await res.json();
        if (!json.success) {
            container.innerHTML = `<p style="color: var(--danger); text-align: center;">Error al cargar ranking comercial.</p>`;
            return;
        }

        const sedes = json.sedes || {};
        const ranking = json.ranking || [];

        const yana = sedes.YANAHUARA || { nombre: 'Yanahuara', leads: 0, ganados: 0, facturado: 0, citas: 0 };
        const cayma = sedes.CAYMA || { nombre: 'Cayma', leads: 0, ganados: 0, facturado: 0, citas: 0 };
        const envios = sedes.ENVIO_PROVINCIA || { nombre: 'Envíos', leads: 0, ganados: 0, facturado: 0, citas: 0 };

        const totalFacturado = (yana.facturado || 0) + (cayma.facturado || 0) + (envios.facturado || 0);
        const yanaPct = totalFacturado > 0 ? Math.round((yana.facturado / totalFacturado) * 100) : 50;
        const caymaPct = totalFacturado > 0 ? Math.round((cayma.facturado / totalFacturado) * 100) : 50;

        const medallas = ['🥇', '🥈', '🥉'];

        container.innerHTML = `
            <!-- Comparativa Sedes Yanahuara vs Cayma -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary); display: flex; align-items: center; gap: 6px;">
                        <i class="ph-bold ph-buildings" style="color: var(--primary);"></i> Batalla de Sedes Arequipa
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">Facturación Total: <strong>S/ ${totalFacturado.toFixed(2)}</strong></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <!-- Yanahuara -->
                    <div style="background: rgba(37, 99, 235, 0.08); border: 1px solid rgba(37, 99, 235, 0.3); border-radius: 8px; padding: 12px;">
                        <div style="font-weight: 800; color: #60a5fa; font-size: 0.85rem;">📍 SEDE YANAHUARA</div>
                        <div style="font-size: 1.2rem; font-weight: 900; color: #fff; margin: 4px 0;">S/ ${Number(yana.facturado).toFixed(2)}</div>
                        <div style="display: flex; gap: 8px; font-size: 0.75rem; color: var(--text-secondary);">
                            <span>👥 ${yana.leads} leads</span>
                            <span>🎉 ${yana.ganados} ventas</span>
                            <span>📅 ${yana.citas} citas</span>
                        </div>
                    </div>

                    <!-- Cayma -->
                    <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 12px;">
                        <div style="font-weight: 800; color: #34d399; font-size: 0.85rem;">📍 SEDE CAYMA</div>
                        <div style="font-size: 1.2rem; font-weight: 900; color: #fff; margin: 4px 0;">S/ ${Number(cayma.facturado).toFixed(2)}</div>
                        <div style="display: flex; gap: 8px; font-size: 0.75rem; color: var(--text-secondary);">
                            <span>👥 ${cayma.leads} leads</span>
                            <span>🎉 ${cayma.ganados} ventas</span>
                            <span>📅 ${cayma.citas} citas</span>
                        </div>
                    </div>
                </div>

                <!-- Barra comparativa porcentual -->
                <div style="margin-top: 14px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.72rem; color: var(--text-secondary); margin-bottom: 4px;">
                        <span>Yanahuara (${yanaPct}%)</span>
                        <span>Cayma (${caymaPct}%)</span>
                    </div>
                    <div style="display: flex; height: 8px; border-radius: 4px; overflow: hidden; background: #334155;">
                        <div style="width: ${yanaPct}%; background: #3b82f6; transition: width 0.4s ease;"></div>
                        <div style="width: ${caymaPct}%; background: #10b981; transition: width 0.4s ease;"></div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard de Asesores de Venta -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px;">
                <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="ph-bold ph-medal" style="color: #f59e0b;"></i> Podio de Asesores Comerciales
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    ${ranking.map((asesor, index) => {
                        const medalla = medallas[index] || `#${index + 1}`;
                        const isTop = index === 0;
                        return `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: 8px; background: ${isTop ? 'rgba(245, 158, 11, 0.1)' : 'rgba(255, 255, 255, 0.02)'}; border: 1px solid ${isTop ? 'rgba(245, 158, 11, 0.3)' : 'var(--border-color)'};">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span style="font-size: 1.3rem;">${medalla}</span>
                                    <div>
                                        <div style="font-weight: 800; font-size: 0.9rem; color: ${isTop ? '#fbbf24' : 'var(--text-primary)'};">
                                            ${escapar(asesor.nombre)}
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--text-secondary); display: flex; gap: 8px;">
                                            <span>📥 ${asesor.leads} atendidos</span>
                                            <span>📅 ${asesor.citas} citas</span>
                                            <span style="color: ${asesor.frios > 0 ? 'var(--danger)' : 'var(--text-muted)'};">🔴 ${asesor.frios} fríos</span>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.95rem; font-weight: 800; color: var(--success);">
                                        S/ ${Number(asesor.facturado).toFixed(2)}
                                    </div>
                                    <div style="font-size: 0.72rem; color: var(--primary); font-weight: 700;">
                                        Conv: ${asesor.conversion_pct}% (${asesor.ganados} ganados)
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    } catch (err) {
        console.error('Error cargando modal ranking:', err);
        container.innerHTML = `<p style="color: var(--danger); text-align: center;">Ocurrió un error al cargar el ranking.</p>`;
    }
}

function abrirModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
}

function cerrarModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

function escapar(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ----------------------------------------------------------
// 11. GESTIÓN DE ASESOR ACTIVO Y FILTRO MI FOCO DE HOY
// ----------------------------------------------------------
function initAdvisorSelector() {
    const sel = document.getElementById('selector-asesor-activo');
    if (sel) {
        sel.value = activeVendedorId;
    }
}

function cambiarAsesorActivo(id) {
    activeVendedorId = id;
    localStorage.setItem('petulap-crm-vendedor', id);
    cargarLeads(true);
    cargarStats();
    cargarRecordatoriosHoy();
    cargarBolsaRescate();
}

function toggleFocoHoy() {
    focoHoyActivo = !focoHoyActivo;
    const btn = document.getElementById('chip-foco-hoy');
    if (btn) {
        if (focoHoyActivo) {
            btn.style.background = '#f59e0b';
            btn.style.color = '#000';
            btn.innerHTML = '<i class="ph-bold ph-target"></i> 🎯 Mi Foco ACTIVO';
        } else {
            btn.style.background = 'rgba(245, 158, 11, 0.1)';
            btn.style.color = '#fbbf24';
            btn.innerHTML = '<i class="ph-bold ph-target"></i> 🎯 Mi Foco de Hoy';
        }
    }
    cargarLeads(true);
}

// ----------------------------------------------------------
// 12. COMPRA INMINENTE & BOLSA DE RESCATE
// ----------------------------------------------------------
async function togglePrioridadInminente(leadId, prioridadActual) {
    const nueva = prioridadActual === 'INMINENTE' ? 'NORMAL' : 'INMINENTE';
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=marcar_compra_inminente`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: leadId, prioridad: nueva })
        });
        const json = await res.json();
        if (json.success) {
            if (nueva === 'INMINENTE') {
                reproducirAlarmaAudio('cita');
            }
            cargarLeads(false);
            cargarStats();
        }
    } catch (err) {
        console.error('Error toggling prioridad inminente:', err);
    }
}

async function cargarBolsaRescate() {
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=bolsa_rescate`);
        const json = await res.json();
        if (!json.success) return;

        const total = json.total || 0;
        const badge = document.getElementById('badge-rescate-count');
        if (badge) {
            badge.textContent = total;
            badge.style.display = total > 0 ? 'inline-block' : 'none';
        }
    } catch (err) {
        console.error('Error cargando bolsa de rescate:', err);
    }
}

async function abrirModalBolsaRescate() {
    const container = document.getElementById('lista-bolsa-rescate-container');
    if (!container) return;
    abrirModal('modal-bolsa-rescate');

    container.innerHTML = `
        <div style="text-align: center; padding: 25px; color: var(--text-muted);">
            <i class="ph-bold ph-spinner ph-spin" style="font-size: 1.8rem; color: #ef4444;"></i>
            <p style="margin-top: 8px;">Buscando prospectos abandonados...</p>
        </div>
    `;

    try {
        const res = await fetch(`${API_BASE}/leads.php?action=bolsa_rescate`);
        const json = await res.json();
        if (!json.success || !json.data || json.data.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                    <i class="ph-bold ph-shield-check" style="font-size: 2.2rem; color: var(--success); display: block; margin-bottom: 8px;"></i>
                    ¡Excelente! No hay ningún lead caliente abandonado. Todo el equipo está al día.
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 10px;">
                ${json.data.map(lead => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px;">
                        <div>
                            <div style="font-weight: 800; font-size: 0.95rem; color: #fff;">
                                ${escapar(lead.nombre)}
                                <span style="font-size: 0.72rem; background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">
                                    ${escapar(lead.motivo_rescate || '>4h sin respuesta')}
                                </span>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px;">
                                💻 ${escapar(lead.modelo_interes_texto || 'Laptop')} • S/ ${lead.presupuesto_aprox || 0} • Asesor anterior: ${escapar(lead.vendedor_nombre || 'Sin asignar')}
                            </div>
                        </div>
                        <button class="btn btn-primary" style="background: #ef4444; padding: 6px 12px; font-size: 0.8rem;" onclick="ejecutarRescateLead(${lead.id})">
                            <i class="ph-bold ph-lightning"></i> Rescatar Lead
                        </button>
                    </div>
                `).join('')}
            </div>
        `;
    } catch (err) {
        console.error('Error cargando modal bolsa rescate:', err);
        container.innerHTML = `<p style="color: var(--danger); text-align: center;">Error al cargar prospectos rescatables.</p>`;
    }
}

async function ejecutarRescateLead(leadId) {
    try {
        const nuevoVendedor = activeVendedorId > 0 ? activeVendedorId : 2;
        const res = await fetch(`${API_BASE}/leads.php?action=rescatar_lead`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lead_id: leadId, nuevo_vendedor_id: nuevoVendedor })
        });
        const json = await res.json();
        if (json.success) {
            reproducirAlarmaAudio('cita');
            cerrarModal('modal-bolsa-rescate');
            cargarLeads(false);
            cargarStats();
            cargarBolsaRescate();
            alert('🚀 ¡Lead rescatado con éxito! Ahora está asignado a ti en estado de Compra Inminente.');
        } else {
            alert(json.error || 'No se pudo rescatar');
        }
    } catch (err) {
        console.error('Error rescatando lead:', err);
    }
}

// ----------------------------------------------------------
// 13. REGISTRO RÁPIDO DE LLAMADAS TELEFÓNICAS
// ----------------------------------------------------------
function abrirModalLlamada(leadId) {
    const lead = leadsData.find(l => l.id == leadId);
    if (!lead) return;
    activeLeadForLlamada = lead;

    document.getElementById('llamada-lead-id').value = lead.id;
    document.getElementById('llamada-cliente-nombre').textContent = `${lead.nombre} (${lead.telefono})`;
    document.getElementById('llamada-notas').value = '';

    abrirModal('modal-registro-llamada');
}

async function guardarLlamadaResultado(resultado) {
    if (!activeLeadForLlamada) return;
    const leadId = activeLeadForLlamada.id;
    const notas = document.getElementById('llamada-notas').value.trim();

    try {
        const res = await fetch(`${API_BASE}/leads.php?action=registrar_llamada`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lead_id: leadId,
                vendedor_id: activeVendedorId > 0 ? activeVendedorId : 1,
                resultado: resultado,
                notas: notas
            })
        });
        const json = await res.json();
        if (json.success) {
            cerrarModal('modal-registro-llamada');
            cargarLeads(false);
            cargarStats();
            if (resultado === 'VIENE_TIENDA') {
                reproducirAlarmaAudio('cita');
                alert('🎉 ¡Cliente confirmó visita a tienda! Se avanzó a Visita/Separado con temperatura PÚRPURA.');
            } else if (resultado === 'VOLVER_A_LLAMAR') {
                alert('⏰ Llamada registrada: Volver a llamar en 2 horas.');
            } else if (resultado === 'NO_CONTESTO') {
                const tel = activeLeadForLlamada.telefono.replace(/\D/g, '');
                const nombre = activeLeadForLlamada.nombre.split(' ')[0];
                const msg = `Hola ${nombre} 👋, te intenté llamar de Petulap por la laptop que consultaste. Avísame cuando estés disponible para coordinar o si prefieres que te responda por aquí 🙌`;
                window.open(`https://wa.me/${tel}?text=${encodeURIComponent(msg)}`, '_blank');
            }
        }
    } catch (err) {
        console.error('Error guardando llamada:', err);
    }
}

// ----------------------------------------------------------
// 14. CADENCIA DE 4 DISPAROS PARA LAPTOPS USADAS
// ----------------------------------------------------------
function abrirModalDisparos(leadId) {
    const lead = leadsData.find(l => l.id == leadId);
    if (!lead) return;
    activeLeadForDisparos = lead;

    const nombre = lead.nombre ? lead.nombre.split(' ')[0] : 'amigo';
    const laptop = lead.modelo_interes_texto || 'la laptop que vimos';
    const tel = lead.telefono.replace(/\D/g, '');

    const disparos = [
        {
            num: 1,
            titulo: "📹 Disparo 1: Confianza en Video (Estética y Batería)",
            momento: "A las 2 horas de la cotización",
            texto: `¡Hola, ${nombre}! 👋 Te grabé un video rápido de 15 segundos mostrando el estado estético impecable grado A de *${laptop}* y la salud de batería probada al 100%. ¿Deseas que te lo mande por aquí para que la veas antes de que se venda? 💻✨`
        },
        {
            num: 2,
            titulo: "⚡ Disparo 2: Escasez Real (Pregunta en Tienda)",
            momento: "A las 6 horas o al día siguiente",
            texto: `Hola, ${nombre}! Un cliente acaba de venir a consultar por *${laptop}*. Como tú me hablaste primero por WhatsApp, quería consultarte: ¿te la aparto hasta las 6:00 PM o la dejamos en vitrina para venta libre? Me avisas para no quedarte mal 🙌`
        },
        {
            num: 3,
            titulo: "🎁 Disparo 3: Gancho de Cierre con Regalo (Anti-Frío)",
            momento: "A las 24 horas (Lead dejado en visto)",
            texto: `¡${nombre}! Conversé con el encargado: Si pasas hoy a probar *${laptop}* a nuestra sede (Yanahuara o Cayma), te incluiré totalmente de cortesía un *Mouse inalámbrico nuevo + Funda acolchada* de regalo 🎁. ¿A qué hora te quedaría bien pasar?`
        },
        {
            num: 4,
            titulo: "🚪 Disparo 4: Ruptura Elegante / Despedida (FOMO)",
            momento: "A las 48 horas sin respuesta",
            texto: `Hola ${nombre}, una consulta rápida: ¿pudiste conseguir laptop o sigues buscando? Te pregunto para saber si libero la reserva de tu cotización en nuestro sistema o te sigo guardando la opción. ¡Un saludo de Petulap! 🙌`
        }
    ];

    const container = document.getElementById('disparos-modal-container');
    if (!container) return;

    container.innerHTML = `
        <div style="margin-bottom: 12px; font-size: 0.88rem; color: var(--text-secondary);">
            Prospecto: <strong style="color: #fff;">${escapar(lead.nombre)}</strong> | Laptop: <strong style="color: var(--primary);">${escapar(laptop)}</strong>
        </div>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            ${disparos.map(d => `
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <strong style="font-size: 0.88rem; color: #60a5fa;">${d.titulo}</strong>
                        <span style="font-size: 0.72rem; color: var(--text-muted);">${d.momento}</span>
                    </div>
                    <div style="font-size: 0.82rem; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px; color: var(--text-primary); margin-bottom: 8px; white-space: pre-wrap; font-family: inherit;">${d.texto}</div>
                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                        <button class="btn btn-ghost" style="padding: 4px 10px; font-size: 0.75rem;" onclick="copiarTextoPortapapeles(${JSON.stringify(d.texto).replace(/"/g, '&quot;')})">
                            <i class="ph ph-copy"></i> Copiar
                        </button>
                        <a href="https://wa.me/${tel}?text=${encodeURIComponent(d.texto)}" target="_blank" class="btn btn-wa" style="padding: 4px 12px; font-size: 0.75rem;">
                            <i class="ph-bold ph-whatsapp-logo"></i> Enviar a WhatsApp
                        </a>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    abrirModal('modal-disparos-whatsapp');
}

function copiarTextoPortapapeles(texto) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(() => {
            alert('📋 Texto copiado al portapapeles.');
        });
    } else {
        const t = document.createElement('textarea');
        t.value = texto;
        document.body.appendChild(t);
        t.select();
        document.execCommand('copy');
        document.body.removeChild(t);
        alert('📋 Texto copiado al portapapeles.');
    }
}
