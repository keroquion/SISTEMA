
if ("serviceWorker" in navigator) { window.addEventListener("load", () => { navigator.serviceWorker.register("sw.js").then(r => console.log("SW ok")).catch(e => console.log("SW fail", e)); }); }


      (function(){
        if(localStorage.getItem('petulap-theme') === 'dark') {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      })();
    

let miId = localStorage.getItem('tecnico_id');
let miTipo = localStorage.getItem('tecnico_tipo');

if (miTipo === 'admin') {
    miId = 'admin';
    const btnCrear = document.getElementById('btn-crear-tarea');
    if (btnCrear) btnCrear.style.display = 'block';
    const btnAsignar = document.getElementById('btn-asignar-rep');
    if (btnAsignar) btnAsignar.style.display = 'block';
}

if (!miId) {
    window.location.href = 'login.html';
}

document.getElementById('lbl-nombre').textContent = localStorage.getItem('tecnico_nombre') || 'Usuario';

let ultimosTickets = [];
let autoRefreshInterval = null;
let modoVista = 'clientes'; // 'clientes' o 'internos'
let listaTecnicos = [];
let ticketActual = null;
let accionActual = '';

// Pre-cargar tecnicos para admin
fetch('api/personas.php?action=list&tipo=tecnico').then(r=>r.json()).then(res => {
    if(res.ok) {
        listaTecnicos = res.data;
        let htmlTec = '<option value="">-- Sin Asignar (Cualquiera) --</option>';
        listaTecnicos.forEach(t => htmlTec += `<option value="${t.id}">${t.nombre} ${t.apellido||''}</option>`);
        document.getElementById('t-tecnico').innerHTML = htmlTec;
        if(document.getElementById('a-tecnico')) document.getElementById('a-tecnico').innerHTML = htmlTec;
    }
});

function cambiarTab(tab) {
  modoVista = tab;
  if (tab === 'clientes') {
    document.getElementById('tab-clientes').className = 'tab-btn active';
    document.getElementById('tab-internos').className = 'tab-btn inactive';
  } else {
    document.getElementById('tab-internos').className = 'tab-btn active';
    document.getElementById('tab-clientes').className = 'tab-btn inactive';
  }
  cargarMisOrdenes();
}

async function cargarMisOrdenes() {
  const res = await fetch('api/soporte.php?action=list').then(r=>r.json());
  if (!res.ok) return;
  
  let misTickets = res.data;
  if (miId !== 'admin') {
    misTickets = res.data.filter(t => t.tecnico_id == miId);
  }
  
  misTickets = misTickets.filter(t => t.estado !== 'ENTREGADO');

  if (modoVista === 'clientes') {
    misTickets = misTickets.filter(t => t.es_externo != 2 && t.cliente_id != 1);
  } else {
    misTickets = misTickets.filter(t => t.es_externo == 2 || t.cliente_id == 1);
  }
  
  const pendientesActuales = misTickets.filter(t => t.estado === 'PENDIENTE');
  if (ultimosTickets.length > 0) {
    const idsViejos = ultimosTickets.map(t => t.id);
    const hayNuevos = pendientesActuales.some(t => !idsViejos.includes(t.id));
    if (hayNuevos && typeof playSound === 'function') playSound('notification');
  }
  ultimosTickets = pendientesActuales;
  
  const cols = { pend:[], diag:[], rep:[], listo:[] };
  misTickets.forEach(t => {
    if (t.estado === 'PENDIENTE') cols.pend.push(t);
    else if (t.estado === 'EN_DIAGNOSTICO' || t.estado === 'EN_REPARACION') cols.diag.push(t);
    else if (t.estado === 'ESPERANDO_REPUESTO') cols.rep.push(t);
    else if (t.estado === 'LISTO_PARA_RECOGER' || t.estado === 'ENTREGADO') cols.listo.push(t);
  });
  
  renderCol('pend', cols.pend);
  renderCol('diag', cols.diag);
  renderCol('rep', cols.rep);
  renderCol('listo', cols.listo);
  
  const btn = document.getElementById('btn-refresh');
  if (btn) btn.innerHTML = '<i class="ph ph-arrow-clockwise"></i> ' + new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
}

function iniciarAutoRefresh() {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);
  autoRefreshInterval = setInterval(cargarMisOrdenes, 60000);
}

function renderCol(colId, tickets) {
  document.getElementById(`c-${colId}`).textContent = tickets.length;
  let html = '';
  
  tickets.forEach(t => {
    const esTarea = (t.es_externo == 2 || t.numero_atencion.startsWith('TAR'));
    const urg = t.prioridad === 'URGENTE' ? '<span class="urgente">URGENTE</span>' : '';
    const baseUrl = window.location.origin + window.location.pathname.replace('mis_ordenes.html','');
    const linkSeguimiento = baseUrl + 'consulta.html?ticket=' + encodeURIComponent(t.numero_atencion);
    const msgWP = encodeURIComponent(`Hola ${t.cliente_nombre} <i class="ph ph-wrench"></i>

Tu equipo (*${t.equipo_descripcion || t.equipo_codigo}*) ya estÃ¡ LISTO para recoger.

Te esperamos en nuestros horarios de atenciÃ³n.

<i class="ph ph-clipboard"></i> Ticket: ${t.numero_atencion}
Rastrear: ${linkSeguimiento}

Â¡Gracias por confiar en PETULAP! ðŸ™Œ`);
    const msgInitWP = encodeURIComponent(`Hola ${t.cliente_nombre}, somos PETULAP <i class="ph ph-wrench"></i>

Tu equipo (*${t.equipo_descripcion || t.equipo_codigo}*) ha ingresado correctamente.

<i class="ph ph-clipboard"></i> *Ticket:* ${t.numero_atencion}

Puedes rastrear tu equipo aquÃ­:
${linkSeguimiento}

Â¡Gracias por confiar en nosotros! ðŸ™Œ`);
    
    const d1 = new Date(t.fecha_ingreso);
    const d2 = new Date();
    
    // Difference in hours
    const ageHours = (d2 - d1) / (1000 * 60 * 60);
    
    // Check next calendar day
    const nextDay = new Date(d1);
    nextDay.setDate(nextDay.getDate() + 1);
    const isNextDay = nextDay.getDate() === d2.getDate() && nextDay.getMonth() === d2.getMonth() && nextDay.getFullYear() === d2.getFullYear();

    let ageColor = '';
    let ageText = '';
    let badgeColor = '';

    if (ageHours >= 72) {
        ageColor = 'border-left: 8px solid var(--color-danger); background: var(--bg-danger-subtle);';
        badgeColor = 'background: var(--color-danger); color: white;';
        ageText = '<i class="ph-bold ph-warning-circle"></i> > 72h';
    } else if (ageHours >= 24) {
        ageColor = 'border-left: 8px solid var(--color-warning); background: var(--bg-warning-subtle);';
        badgeColor = 'background: var(--color-warning); color: #000;';
        ageText = '<i class="ph-bold ph-hourglass-high"></i> > 24h';
    } else if (isNextDay) {
        ageColor = 'border-left: 8px solid var(--color-brand); background: var(--bg-brand-subtle);';
        badgeColor = 'background: var(--color-brand); color: white;';
        ageText = '<i class="ph-bold ph-clock"></i> Ayer';
    } else {
        ageColor = 'border-left: 8px solid var(--color-success); background: var(--bg-success-subtle);';
        badgeColor = 'background: var(--color-success); color: white;';
        ageText = '<i class="ph-bold ph-check-circle"></i> Hoy';
    }

    if (esTarea) {
        ageColor = 'border-left: 8px solid #b5179e; background: rgba(181, 23, 158, 0.15);';
        badgeColor = 'background: #b5179e; color: white;';
    }

    html += `<div class="ticket" style="${ageColor}">
      ${urg}
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px">
        <div class="tkt-id" style="word-break: break-all; margin-right: 5px;">${t.numero_atencion}</div>
        ${!esTarea ? `<div style="${badgeColor} padding:4px 10px; border-radius:12px; letter-spacing:0.5px; display:flex; gap:4px; align-items:center; font-size:11px; font-weight:700; white-space:nowrap; flex-shrink:0;">${ageText}</div>` : `<div style="${badgeColor} padding:4px 10px; border-radius:12px; letter-spacing:0.5px; display:flex; gap:4px; align-items:center; font-size:11px; font-weight:700; white-space:nowrap; flex-shrink:0;"><i class="ph-bold ph-wrench"></i> TAREA</div>`}
      </div>`;
      
      let equipoTitulo = esTarea ? (t.motivo_ingreso||'') : (t.equipo_descripcion||'');
      equipoTitulo = equipoTitulo.replace(/\s*\(S\/N:[^)]+\)/i, '').trim();
      const motivoTexto = esTarea ? (t.diagnostico||'') : (t.motivo_ingreso||'');
      const clienteTexto = esTarea ? (t.tecnico_nombre||'No asignado') : (t.cliente_nombre||'');

      html += `
      <div class="tkt-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;" title="${equipoTitulo}">${equipoTitulo}</div>
      <p style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;" title="${motivoTexto}"><b>${esTarea?'Desc:':'Motivo:'}</b> ${motivoTexto}</p>
      <p style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;" title="${clienteTexto}"><b>${esTarea?'Asignado a:':'Cliente:'}</b> ${clienteTexto}</p>
      <p style="display:flex; align-items:center; gap:5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;"><i class="ph-bold ph-user" style="color:var(--text-secondary); flex-shrink:0;"></i> <b>TÃ©cnico:</b> <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${t.tecnico_nombre||'Sin asignar'}">${t.tecnico_nombre||'Sin asignar'}</span></p>
      
      <div class="acciones" style="display:flex; gap:8px; margin-top: 12px; align-items: stretch; flex-wrap: wrap;">`;
      
      if (t.estado === 'PENDIENTE') {
        html += `<button class="btn-primary" style="flex:1" onclick="cambiarEstadoRapido(${t.id}, 'EN_DIAGNOSTICO')"><i class="ph-bold ph-play-circle"></i> Iniciar</button>`;
      }
      else if (t.estado === 'EN_DIAGNOSTICO' || t.estado === 'EN_REPARACION') {
        if (!esTarea) html += `<button class="btn-secondary" style="flex:1" onclick="abrirModal(${t.id}, 'repuesto')"><i class="ph-bold ph-package"></i> Repuesto</button>`;
        html += `<button class="btn-primary" style="flex:1" onclick="abrirModal(${t.id}, 'listo')"><i class="ph-bold ph-check-circle"></i> Terminar</button>`;
      }
      else if (t.estado === 'ESPERANDO_REPUESTO') {
        html += `<button class="btn-primary" style="flex:1" onclick="cambiarEstadoRapido(${t.id}, 'EN_REPARACION')"><i class="ph ph-wrench"></i> Continuar</button>`;
      }
      else if (t.estado === 'LISTO_PARA_RECOGER') {
        if (miId === 'admin' && !esTarea) {
          html += `<button class="btn-primary" style="flex:1" onclick="abrirModalWhatsApp(${t.id}, '${t.cliente_tel}', \`${msgWP}\`)"><i class="ph ph-whatsapp-logo"></i> Entregar</button>`;
        } else {
          html += `<button class="btn-secondary" style="flex:1" onclick="cambiarEstadoRapido(${t.id}, 'ENTREGADO')"><i class="ph ph-handshake"></i> ${esTarea?'Archivar':'Entregado'}</button>`;
        }
      }

      if (miId === 'admin') {
          html += `<button class="btn-secondary" style="padding:0 10px;" onclick="const el = document.getElementById('more-actions-${t.id}'); el.style.display = el.style.display==='none'?'flex':'none';"><i class="ph ph-dots-three"></i></button>`;
      }

    html += `</div>`; 

    if (miId === 'admin') {
      html += `<div id="more-actions-${t.id}" style="display:none; margin-top:12px; padding-top:12px; border-top:1px solid var(--border-default); flex-direction:column; gap:8px;">`;
      html += `<button class="btn-secondary" style="width:100%" onclick="abrirModalAdmin(${t.id}, '${t.estado}', ${t.tecnico_id || 'null'})"><i class="ph ph-crown"></i> Administrar Ticket</button>`;
      
      if (!esTarea) {
        html += `<a href="https://wa.me/51${t.cliente_tel}?text=${msgInitWP}" target="_blank" style="text-decoration:none">
                   <button class="btn-secondary" style="width:100%"><i class="ph ph-whatsapp-logo"></i> Recibo Inicial (WP)</button>
                 </a>`;
        html += `<button class="btn-secondary" style="width:100%" onclick="window.open('imprimir_sticker.html?numero=${t.numero_atencion}&cliente=${encodeURIComponent(t.cliente_nombre)}', '_blank', 'width=300,height=200')"><i class="ph ph-printer"></i> Imprimir Sticker</button>`;
      }
      html += `</div>`;
    }

    html += `</div>`;
  });

  document.getElementById(`col-${colId}`).innerHTML = html;
}

async function cambiarEstadoRapido(id, nuevoEstado) {
  const body = { id: id, estado: nuevoEstado, usuario: document.getElementById('lbl-nombre').textContent };
  const res = await fetch(`api/soporte.php?action=actualizar`, { method:'POST', body:JSON.stringify(body) }).then(r=>r.json());
  if (res.ok) cargarMisOrdenes();
  else alert("Error: " + res.msg);
}

function abrirModal(id, accion) {
  ticketActual = id;
  accionActual = accion;
  
  document.getElementById('m-texto').value = '';
  document.getElementById('m-repuesto').style.display = 'none';
  document.getElementById('m-repuesto').value = '';
  
  if (accion === 'repuesto') {
    document.getElementById('m-titulo').textContent = '<i class="ph ph-package"></i> Esperando Repuesto';
    document.getElementById('lbl-input').textContent = 'Â¿QuÃ© pieza falta?';
    
    fetch('api/repuestos.php?action=list').then(r=>r.json()).then(res => {
      if(res.ok) {
        let h = '<option value="">-- Selecciona del catÃ¡logo (Opcional) --</option>';
        res.data.forEach(r => h += `<option value="${r.nombre}" data-id="${r.id}">[Stock: ${r.stock}] ${r.nombre}</option>`);
        document.getElementById('m-repuesto').innerHTML = h;
        document.getElementById('m-repuesto').style.display = 'block';
      }
    });

    document.getElementById('btn-guardar-modal').onclick = () => guardarModal('ESPERANDO_REPUESTO', 'diagnostico');
  } else if (accion === 'listo') {
    document.getElementById('m-titulo').textContent = '<i class="ph ph-check-circle"></i> Listo para Recoger';
    document.getElementById('lbl-input').textContent = 'Â¿CuÃ¡l fue la soluciÃ³n aplicada?';
    document.getElementById('btn-guardar-modal').onclick = () => guardarModal('LISTO_PARA_RECOGER', 'solucion');
  }
  
  document.getElementById('modal').style.display = 'flex';
}

function cerrarModal() {
  document.getElementById('modal').style.display = 'none';
}

function abrirModalAdmin(id, estadoActual, tecnicoId) {
    ticketActual = id;
    document.getElementById('admin-estado').value = estadoActual;
    
    let htmlTec = '<option value="">-- Sin Asignar --</option>';
    listaTecnicos.forEach(t => {
        htmlTec += `<option value="${t.id}" ${t.id == tecnicoId ? 'selected' : ''}>${t.nombre} ${t.apellido||''}</option>`;
    });
    document.getElementById('admin-tecnico').innerHTML = htmlTec;
    
    document.getElementById('modal-admin').style.display = 'flex';
}

async function guardarAdmin() {
    const estado = document.getElementById('admin-estado').value;
    const tec = document.getElementById('admin-tecnico').value;
    
    const body = { 
      id: ticketActual, 
      estado: estado, 
      tecnico_id: tec ? parseInt(tec) : null,
      usuario: document.getElementById('lbl-nombre').textContent 
    };
    
    const res = await fetch(`api/soporte.php?action=actualizar`, { method:'POST', body:JSON.stringify(body) }).then(r=>r.json());
    if (res.ok) {
        document.getElementById('modal-admin').style.display = 'none';
        cargarMisOrdenes();
    } else {
        alert("Error al actualizar: " + res.msg);
    }
}

async function guardarModal(estado, campoTexto) {
  let texto = document.getElementById('m-texto').value.trim();
  const repuestoSel = document.getElementById('m-repuesto');
  
  if (estado === 'ESPERANDO_REPUESTO' && repuestoSel.value) {
    texto = repuestoSel.value + (texto ? " - " + texto : "");
  }
  if (!texto) { alert('Debes escribir un mensaje o seleccionar un repuesto.'); return; }
  
  const body = { id: ticketActual, estado: estado, usuario: document.getElementById('lbl-nombre').textContent };
  body[campoTexto] = texto; 
  
  if (estado === 'ESPERANDO_REPUESTO' && repuestoSel.value) {
     body['repuesto_nombre'] = repuestoSel.value;
  }
  
  const res = await fetch(`api/soporte.php?action=actualizar`, { method:'POST', body:JSON.stringify(body) }).then(r=>r.json());
  if (res.ok) {
    cerrarModal();
    cargarMisOrdenes();
  } else {
    alert("Error: " + res.msg);
  }
}

function abrirModalWhatsApp(id, tel, msg) {
  document.getElementById('wp-ticket-id').value = id;
  document.getElementById('wp-tel').value = tel;
  document.getElementById('wp-texto').value = decodeURIComponent(msg);
  document.getElementById('modal-wp').style.display = 'flex';
}

function enviarWhatsApp() {
  const id = document.getElementById('wp-ticket-id').value;
  const tel = document.getElementById('wp-tel').value;
  const texto = encodeURIComponent(document.getElementById('wp-texto').value);
  window.open(`https://wa.me/51${tel}?text=${texto}`, '_blank');
  document.getElementById('modal-wp').style.display = 'none';
  cambiarEstadoRapido(id, 'ENTREGADO');
}

function abrirModalTarea() {
  document.getElementById('t-titulo').value = '';
  document.getElementById('t-desc').value = '';
  document.getElementById('modal-tarea').style.display = 'flex';
}

async function guardarTarea() {
  const body = {
    titulo: document.getElementById('t-titulo').value,
    descripcion: document.getElementById('t-desc').value,
    tecnico_id: document.getElementById('t-tecnico').value
  };
  if (!body.titulo) return alert('Pon un titulo');
  const res = await fetch(`api/soporte.php?action=crear_tarea`, { method:'POST', body:JSON.stringify(body) }).then(r=>r.json());
  if (res.ok) {
    document.getElementById('modal-tarea').style.display = 'none';
    cargarMisOrdenes();
  } else { alert("Error: " + res.msg); }
}

cargarMisOrdenes();
iniciarAutoRefresh();


