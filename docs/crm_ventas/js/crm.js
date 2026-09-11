// ==========================================================
// PETULAP SALES CRM - LÓGICA DE NEGOCIO Y KANBAN
// Control de etapas, cotizador WhatsApp y cálculo de SLA en vivo
// ==========================================================

const API_BASE = 'api';
let leadsData = [];
let promosData = [];
let activeFilter = 'TODOS';
let activeLeadForQuote = null;

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    cargarLeads();
    cargarStats();
    cargarPromociones();
    initEventos();

    // Auto-refrescar cada 60 segundos para mantener el semáforo al día
    setInterval(() => {
        cargarLeads(false);
        cargarStats();
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
// 2. CARGAR Y RENDERIZAR LEADS EN EL KANBAN
// ----------------------------------------------------------
async function cargarLeads(mostrarLoading = true) {
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=list`);
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
    div.className = 'lead-card';
    div.draggable = true;
    div.dataset.id = lead.id;

    // Semáforo SLA
    const tempClass = `temp-${lead.temperatura.toLowerCase()}`;
    const pulseClass = `pulse-${lead.temperatura.toLowerCase()}`;
    const tempLabel = lead.temperatura === 'VERDE' ? 'Al día' 
                    : lead.temperatura === 'AMBAR' ? 'Esperando resp.' 
                    : '¡Cliente Frío!';

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
            <span class="temp-badge ${tempClass}">
                <span class="pulse-dot ${pulseClass}"></span>
                ${tempLabel}
            </span>
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
                <button class="btn-card-move" onclick="abrirCotizador(${lead.id})" title="Enviar Ficha y Cotización">
                    <i class="ph ph-file-text"></i> Cotizar
                </button>
                <a href="${waUrl}" target="_blank" class="btn-card-wa" title="Abrir Chat WhatsApp">
                    <i class="ph-bold ph-whatsapp-logo"></i> Chat
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
        const item = document.createElement('div');
        item.className = 'promo-item';
        item.innerHTML = `
            <div>
                <strong>${p.marca} ${p.modelo}</strong>
                <div style="font-size: 0.78rem; color: var(--text-secondary);">
                    ${p.procesador} | ${p.ram} | ${p.almacenamiento} | ${p.pantalla}
                </div>
                ${p.nota_stock ? `<span style="font-size: 0.7rem; color: var(--warning); font-weight: 700;">⚠️ ${p.nota_stock}</span>` : ''}
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
