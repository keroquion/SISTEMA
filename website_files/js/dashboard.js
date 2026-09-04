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

window.abrirModalTareaGlobal = function() {
  document.getElementById('g-t-titulo').value = '';
  document.getElementById('g-t-desc').value = '';
  document.getElementById('g-t-prioridad').value = 'SIN PRIORIDAD';
  document.getElementById('modal-tarea-global').style.display = 'flex';
};

window.guardarTareaGlobal = function() {
  var body = {
    titulo: document.getElementById('g-t-titulo').value,
    descripcion: document.getElementById('g-t-desc').value,
    tecnico_id: document.getElementById('g-t-tecnico').value,
    prioridad: document.getElementById('g-t-prioridad').value
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
