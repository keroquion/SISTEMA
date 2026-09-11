// Logica de UI para el Dashboard Moderno
document.addEventListener('DOMContentLoaded', () => {
    
    // Toggle Menu Movil
    const mobileMenuBtn = document.getElementById('mobile-menu-toggle');
    const mobileSidebar = document.getElementById('mobile-sidebar');

    // Optional Chaining para seguridad
    mobileMenuBtn?.addEventListener('click', () => {
        mobileSidebar?.classList.toggle('active');
    });

    // Minimizar Sidebar (Desktop)
    const btnMinimize = document.querySelector('.btn-minimize');
    const sidebar = document.querySelector('.sidebar');
    const mainWrapper = document.querySelector('.main-wrapper');
    
    btnMinimize?.addEventListener('click', () => {
        sidebar?.classList.toggle('collapsed');
        mainWrapper?.classList.toggle('expanded');
    });

    // Logica de Acordeon
    const accordions = document.querySelectorAll('.accordion-header');
    
    accordions?.forEach(acc => {
        acc.addEventListener('click', function() {
            const parent = this.parentElement;
            
            // Cerrar otros acordeones
            document.querySelectorAll('.accordion-item')?.forEach(item => {
                if (item !== parent) {
                    item.classList.remove('active');
                }
            });
            
            // Alternar el estado actual
            parent?.classList.toggle('active');
        });
    });
    
});

/* --- FUNCIONES GLOBALES (Limpiar Cache y Salir) --- */
async function logout(e) {
    if (e) e.preventDefault();
    try {
        await fetch('api/auth.php?action=logout');
    } catch(err) {}
    window.location.href = 'login.html';
}

async function clearCache(e) {
    if (e) e.preventDefault();
    
    // Unregister service workers
    if ('serviceWorker' in navigator) {
        try {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (let registration of registrations) {
                await registration.unregister();
            }
        } catch (err) {
            console.error('Error unregistering SW', err);
        }
    }
    
    // Clear caches API
    if ('caches' in window) {
        try {
            const keys = await caches.keys();
            for (let key of keys) {
                await caches.delete(key);
            }
        } catch (err) {
            console.error('Error clearing caches', err);
        }
    }
    
    alert('Cache limpiada correctamente. La pagina se recargara.');
    // Hard reload
    window.location.reload(true);
}

/* --- MODO OSCURO (Toggle y sincronizacion de iconos) --- */
function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('petulap-theme', 'light');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('petulap-theme', 'dark');
    }
    document.querySelectorAll('.theme-toggle-btn i').forEach(icon => {
        icon.className = isDark ? 'ph ph-moon' : 'ph ph-sun';
    });
}
window.toggleTheme = toggleTheme;

document.addEventListener('DOMContentLoaded', () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.querySelectorAll('.theme-toggle-btn i').forEach(icon => {
        icon.className = isDark ? 'ph ph-sun' : 'ph ph-moon';
    });
});


// ====== FAB CHAT LOGIC (DRAGGABLE & GLOBAL TASK CREATION) ======
document.addEventListener('DOMContentLoaded', () => {
    const fabBtn = document.querySelector('.fab-chat');
    if (fabBtn) {
        // Override the FAB for creating tasks globally
        fabBtn.title = "Crear Tarea Interna";
        fabBtn.innerHTML = '<i class="ph-bold ph-list-plus"></i>';
        
        // Remove existing inline onclick attributes
        fabBtn.removeAttribute('onclick');
        
        // Handle global click
        fabBtn.addEventListener('click', (e) => {
            if (window.isDraggingFab) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            
            if (typeof cambiarTab === 'function') cambiarTab('internos');
            if (typeof window.abrirModalTareaGlobal === 'function') {
                window.abrirModalTareaGlobal();
            } else {
                // Fallback in case script didn't load properly
                window.location.href = 'mis_ordenes.html?action=crear_tarea';
            }
        });
        
        // --- Draggable Logic ---
        window.isDraggingFab = false;
        var startX, startY, initialX, initialY;

        function onDragStart(e) {
            if (e.type === 'mousedown' && e.button !== 0) return;
            
            if (e.type === 'touchstart') {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            } else {
                startX = e.clientX;
                startY = e.clientY;
            }
            
            var rect = fabBtn.getBoundingClientRect();
            initialX = rect.left;
            initialY = rect.top;
            
            fabBtn.style.transition = 'none';
            
            document.addEventListener('mousemove', onDragMove);
            document.addEventListener('touchmove', onDragMove, {passive: false});
            document.addEventListener('mouseup', onDragEnd);
            document.addEventListener('touchend', onDragEnd);
        }

        function onDragMove(e) {
            var currentX, currentY;
            if (e.type === 'touchmove') {
                currentX = e.touches[0].clientX;
                currentY = e.touches[0].clientY;
            } else {
                currentX = e.clientX;
                currentY = e.clientY;
            }
            
            var dx = currentX - startX;
            var dy = currentY - startY;
            
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                window.isDraggingFab = true;
                if (e.cancelable) e.preventDefault();
            }
            
            var newX = initialX + dx;
            var newY = initialY + dy;
            
            newX = Math.max(0, Math.min(newX, window.innerWidth - fabBtn.offsetWidth));
            newY = Math.max(0, Math.min(newY, window.innerHeight - fabBtn.offsetHeight));
            
            fabBtn.style.right = 'auto';
            fabBtn.style.bottom = 'auto';
            fabBtn.style.left = newX + 'px';
            fabBtn.style.top = newY + 'px';
        }

        function onDragEnd() {
            fabBtn.style.transition = '';
            document.removeEventListener('mousemove', onDragMove);
            document.removeEventListener('touchmove', onDragMove);
            document.removeEventListener('mouseup', onDragEnd);
            document.removeEventListener('touchend', onDragEnd);
            
            setTimeout(function() { window.isDraggingFab = false; }, 50);
        }

        fabBtn.addEventListener('mousedown', onDragStart);
        fabBtn.addEventListener('touchstart', onDragStart, {passive: false});
    }
});

// ====== GLOBAL TAREA INTERNA MODAL INJECTION ======
document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('modal-tarea-global')) {
        var modalHtml = '<div class="modal-overlay" id="modal-tarea-global" style="display:none; z-index:9999;">'
          + '<div class="card" style="width:90%; max-width:400px; z-index:10000;">'
          + '<h2><i class="ph ph-plus"></i> Tarea Interna</h2>'
          + '<div class="form-group"><label class="form-label">Titulo</label><input type="text" id="g-t-titulo" class="form-control"></div>'
          + '<div class="form-group"><label class="form-label">Descripcion</label><textarea id="g-t-desc" class="form-control" rows="3"></textarea></div>'
          + '<div class="form-group"><label class="form-label">Tecnico</label><select id="g-t-tecnico" class="form-control"></select></div>'
          + '<div class="form-group"><label class="form-label">Prioridad</label><select id="g-t-prioridad" class="form-control"><option value="SIN PRIORIDAD">Sin Prioridad</option><option value="NORMAL">Normal</option><option value="ALTA">Alta</option><option value="URGENTE">Urgente</option></select></div>'
          + '<div class="form-group"><label class="form-label"><i class="ph ph-timer"></i> Tiempo Estimado / Asignado</label>'
          + '<select id="g-t-tiempo" class="form-control" onchange="window.toggleTiempoGlobalCustom()">'
          + '<option value="">— Sin estimar —</option>'
          + '<option value="30 min">30 min</option>'
          + '<option value="1 hora">1 hora</option>'
          + '<option value="2 horas">2 horas</option>'
          + '<option value="3 horas">3 horas</option>'
          + '<option value="4 horas">4 horas</option>'
          + '<option value="8 horas">8 horas (Jornada)</option>'
          + '<option value="otro">Personalizado...</option>'
          + '</select>'
          + '<input type="text" id="g-t-tiempo-custom" class="form-control" style="display:none; margin-top:8px;" placeholder="Ej: 1h 30m, 45 min...">'
          + '</div>'
          + '<div style="display:flex; gap:10px"><button class="btn btn-outline" onclick="document.getElementById(\'modal-tarea-global\').style.display=\'none\'">Cancelar</button><button class="btn btn-primary" onclick="guardarTareaGlobal()">Crear</button></div>'
          + '</div>'
          + '</div>';
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Fetch technicians globally
        fetch('api/personas.php?action=list&tipo=tecnico').then(function(r){return r.json()}).then(function(res) {
            if(res.ok) {
                var htmlTec = '<option value="">-- Sin Asignar (Cualquiera) --</option>';
                res.data.forEach(function(t) { htmlTec += '<option value="' + t.id + '">' + t.nombre + ' ' + (t.apellido||'') + '</option>'; });
                var sel = document.getElementById('g-t-tecnico');
                if(sel) sel.innerHTML = htmlTec;
            }
        });
    }
});

window.toggleTiempoGlobalCustom = function() {
    var sel = document.getElementById('g-t-tiempo');
    var custom = document.getElementById('g-t-tiempo-custom');
    if (sel && custom) {
        custom.style.display = (sel.value === 'otro') ? 'block' : 'none';
        if (sel.value === 'otro') custom.focus();
    }
};

window.abrirModalTareaGlobal = function() {
  document.getElementById('g-t-titulo').value = '';
  document.getElementById('g-t-desc').value = '';
  document.getElementById('g-t-prioridad').value = 'SIN PRIORIDAD';
  var selT = document.getElementById('g-t-tiempo');
  if (selT) selT.value = '';
  var custT = document.getElementById('g-t-tiempo-custom');
  if (custT) { custT.value = ''; custT.style.display = 'none'; }
  document.getElementById('modal-tarea-global').style.display = 'flex';
};

window.guardarTareaGlobal = function() {
  var tiempoEstimado = document.getElementById('g-t-tiempo') ? document.getElementById('g-t-tiempo').value : '';
  if (tiempoEstimado === 'otro') {
    tiempoEstimado = document.getElementById('g-t-tiempo-custom') ? document.getElementById('g-t-tiempo-custom').value.trim() : '';
  }

  var body = {
    titulo: document.getElementById('g-t-titulo').value,
    descripcion: document.getElementById('g-t-desc').value,
    tecnico_id: document.getElementById('g-t-tecnico').value,
    prioridad: document.getElementById('g-t-prioridad').value,
    tiempo_estimado: tiempoEstimado || null
  };
  if (!body.titulo) { alert('Pon un titulo'); return; }
  fetch('api/soporte.php?action=crear_tarea', { method:'POST', body:JSON.stringify(body) }).then(function(r){return r.json()}).then(function(res) {
    if (res.ok) {
      document.getElementById('modal-tarea-global').style.display = 'none';
      if (typeof cargarMisOrdenes === 'function') {
          cargarMisOrdenes();
      } else {
          alert("Tarea interna creada exitosamente.");
      }
    } else { alert("Error: " + res.msg); }
  });
};


// ====== NOTIFICATION BELL LOGIC (v1.6.2) ======
document.addEventListener('DOMContentLoaded', function() {
    const bellBtns = document.querySelectorAll('.notification-btn');
    if (!bellBtns || bellBtns.length === 0) return;
    
    // Inject dropdown container if not present
    if (!document.getElementById('notif-dropdown')) {
        const notifHtml = `
            <div id="notif-dropdown" class="card" style="display:none; position:fixed; right:16px; top:62px; width:360px; max-width:calc(100vw - 32px); max-height:480px; overflow-y:auto; z-index:10000; padding:0; box-shadow:0 14px 36px rgba(0,0,0,0.3); border:1px solid var(--border-default); border-radius:12px; background:var(--bg-surface);">
                <div style="padding:12px 15px; border-bottom:1px solid var(--border-default); display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface-hover);">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i class="ph-bold ph-bell" style="color:var(--color-brand); font-size:1.1rem;"></i>
                        <h3 style="margin:0; font-size:15px; font-weight:700; color:var(--text-primary);">Notificaciones</h3>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button onclick="marcarTodasLeidas()" style="background:none; border:none; color:var(--color-brand); cursor:pointer; font-size:12px; font-weight:600; padding:4px 8px; border-radius:4px;" title="Marcar todas como leídas">Marcar leídas</button>
                        <button onclick="cerrarDropdownNotif()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px; padding:2px 6px; line-height:1;" aria-label="Cerrar">&times;</button>
                    </div>
                </div>
                <div id="notif-push-banner" style="display:none; padding:8px 14px; background:rgba(37,99,235,0.08); border-bottom:1px solid rgba(37,99,235,0.15); font-size:11.5px; color:var(--text-secondary); display:flex; justify-content:space-between; align-items:center;">
                    <span style="display:flex; align-items:center; gap:6px;">
                        <i class="ph ph-broadcast" style="color:var(--color-brand)"></i> Alertas nativas en Chrome
                    </span>
                    <button onclick="activarPushManual(event)" class="btn btn-primary" style="padding:2px 8px; font-size:11px; border-radius:4px; font-weight:600;">Activar</button>
                </div>
                <div id="notif-list" style="padding:8px; max-height:380px; overflow-y:auto;">
                    <div style="padding:20px; text-align:center; color:var(--text-muted);">
                        <i class="ph ph-spinner ph-spin" style="font-size:1.4rem;"></i>
                        <p style="margin-top:6px; font-size:12px;">Cargando notificaciones...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', notifHtml);
    }
    
    // Inject badges on all notification bell buttons (desktop + mobile)
    bellBtns.forEach((btn, idx) => {
        btn.style.position = 'relative';
        if (!btn.querySelector('.notif-badge-item')) {
            const badgeHtml = `<span class="notif-badge-item" ${idx === 0 ? 'id="notif-badge"' : ''} style="display:none; position:absolute; top:-3px; right:-3px; background:var(--color-danger); color:white; border-radius:50%; font-size:10px; min-width:16px; height:16px; padding:0 3px; align-items:center; justify-content:center; font-weight:bold; box-shadow:0 0 0 2px var(--bg-surface); pointer-events:none;">0</span>`;
            btn.insertAdjacentHTML('beforeend', badgeHtml);
        }

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const dropdown = document.getElementById('notif-dropdown');
            if (!dropdown) return;
            
            if (dropdown.style.display === 'none' || !dropdown.style.display) {
                dropdown.style.display = 'block';
                cargarNotificaciones();
                verificarEstadoPushBanner();
            } else {
                dropdown.style.display = 'none';
            }
        });
    });

    // Clic fuera del dropdown lo cierra
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('notif-dropdown');
        if (!dropdown || dropdown.style.display !== 'block') return;
        
        let clickedOnBell = false;
        bellBtns.forEach(btn => {
            if (btn.contains(e.target)) clickedOnBell = true;
        });

        if (!dropdown.contains(e.target) && !clickedOnBell) {
            dropdown.style.display = 'none';
        }
    });

    // Cargar notificaciones al inicio
    cargarNotificaciones();

    // Smart Polling de Alta Escalabilidad (Page Visibility API + Jitter)
    let lastNotifCheck = Date.now();
    const BASE_POLL_INTERVAL = 4 * 60 * 1000; // 4 minutos

    function planificarSiguienteSondeo() {
        const jitter = (Math.random() - 0.5) * 30 * 1000;
        const delay = Math.max(60000, BASE_POLL_INTERVAL + jitter);
        setTimeout(() => {
            if (document.visibilityState === 'visible') {
                cargarNotificaciones();
                lastNotifCheck = Date.now();
            }
            planificarSiguienteSondeo();
        }, delay);
    }
    planificarSiguienteSondeo();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && (Date.now() - lastNotifCheck) >= BASE_POLL_INTERVAL) {
            cargarNotificaciones();
            lastNotifCheck = Date.now();
        }
    });
});

window.cerrarDropdownNotif = function() {
    const dropdown = document.getElementById('notif-dropdown');
    if (dropdown) dropdown.style.display = 'none';
};

window.verificarEstadoPushBanner = function() {
    const banner = document.getElementById('notif-push-banner');
    if (!banner) return;
    if (typeof petulap_checkPushStatus === 'function') {
        const st = petulap_checkPushStatus();
        banner.style.display = (st === 'granted') ? 'none' : 'flex';
    } else {
        banner.style.display = 'none';
    }
};

window.activarPushManual = async function(e) {
    if (e) e.stopPropagation();
    if (typeof petulap_subscribePush === 'function') {
        const ok = await petulap_subscribePush();
        if (ok) {
            alert('¡Notificaciones de Chrome activadas con éxito!');
            verificarEstadoPushBanner();
        } else {
            alert('No se pudo activar las notificaciones. Por favor revisa los permisos de tu navegador.');
        }
    }
};

window.cargarNotificaciones = async function() {
    try {
        const res = await fetch('api/notificaciones.php?action=list').then(r => r.json());
        if (res.ok) {
            let unread = 0;
            let html = '';
            const listContainer = document.getElementById('notif-list');
            if (!listContainer) return;

            if (!res.data || res.data.length === 0) {
                html = `
                    <div style="padding:30px 15px; text-align:center; color:var(--text-muted);">
                        <i class="ph ph-bell-simple-slash" style="font-size:2rem; opacity:0.6;"></i>
                        <p style="margin-top:8px; font-size:13px; font-weight:500;">No tienes notificaciones pendientes</p>
                    </div>
                `;
            } else {
                res.data.forEach(n => {
                    const isUnread = (parseInt(n.leido) === 0);
                    if (isUnread) unread++;

                    const bg = isUnread ? 'var(--bg-surface-hover)' : 'transparent';
                    const fw = isUnread ? '700' : '500';
                    const borderLeft = isUnread ? '3px solid var(--color-brand)' : '3px solid transparent';
                    
                    // Elegir ícono y color contextual según título / contenido
                    let iconHtml = '<i class="ph ph-bell-ringing" style="color:var(--color-brand)"></i>';
                    const tit = (n.titulo || '').toLowerCase();
                    const msg = (n.mensaje || '').toLowerCase();
                    
                    if (tit.includes('repuesto') || msg.includes('repuesto') || tit.includes('compra')) {
                        if (tit.includes('crítica') || tit.includes('urgente') || msg.includes('urgente')) {
                            iconHtml = '<i class="ph-bold ph-warning-circle" style="color:var(--color-danger); font-size:1.1rem;"></i>';
                        } else {
                            iconHtml = '<i class="ph-bold ph-package" style="color:#f59e0b; font-size:1.1rem;"></i>';
                        }
                    } else if (tit.includes('courier') || tit.includes('envío') || tit.includes('tracking') || tit.includes('encomienda')) {
                        iconHtml = '<i class="ph-bold ph-truck" style="color:#0284c7; font-size:1.1rem;"></i>';
                    } else if (tit.includes('garantía')) {
                        iconHtml = '<i class="ph-bold ph-shield-check" style="color:#8b5cf6; font-size:1.1rem;"></i>';
                    } else if (tit.includes('ticket') || tit.includes('orden') || tit.includes('tarea')) {
                        iconHtml = '<i class="ph-bold ph-wrench" style="color:#10b981; font-size:1.1rem;"></i>';
                    }

                    const safeLink = n.link ? n.link.replace(/"/g, '&quot;') : '';

                    html += `
                        <div style="padding:10px 12px; border-bottom:1px solid var(--border-default); background:${bg}; border-left:${borderLeft}; cursor:pointer; border-radius:8px; margin-bottom:5px; transition:all 0.15s ease;"
                             onclick="clickNotif(${n.id}, '${safeLink}')"
                             onmouseover="this.style.background='var(--bg-surface-hover)'"
                             onmouseout="this.style.background='${bg}'">
                            <div style="display:flex; align-items:center; gap:6px; font-weight:${fw}; font-size:13.5px; margin-bottom:4px; color:var(--text-primary);">
                                ${iconHtml}
                                <span>${n.titulo}</span>
                            </div>
                            <div style="font-size:12.5px; color:var(--text-secondary); line-height:1.35; padding-left:22px;">
                                ${n.mensaje}
                            </div>
                            <div style="font-size:10.5px; color:var(--text-muted); margin-top:5px; text-align:right;">
                                ${n.fecha || ''}
                            </div>
                        </div>
                    `;
                });
            }

            listContainer.innerHTML = html;

            // Actualizar todos los badges (.notif-badge-item)
            const badges = document.querySelectorAll('.notif-badge-item');
            badges.forEach(badge => {
                if (unread > 0) {
                    badge.style.display = 'flex';
                    badge.textContent = unread > 9 ? '9+' : unread;
                } else {
                    badge.style.display = 'none';
                }
            });
        }
    } catch (e) {
        console.warn('Error cargando notificaciones:', e);
    }
};

window.clickNotif = async function(id, link) {
    try {
        await fetch('api/notificaciones.php?action=marcar_leida', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        });
    } catch(e) {}

    if (link && link !== 'null' && link !== '') {
        window.location.href = link;
    } else {
        cargarNotificaciones();
    }
};

window.marcarTodasLeidas = async function() {
    try {
        await fetch('api/notificaciones.php?action=marcar_todas_leidas');
        cargarNotificaciones();
    } catch(e) {}
};
