// Verifica en cada pagina si la sesion de PHP sigue activa
document.addEventListener("DOMContentLoaded", async () => {
    const publicPages = ['login.html', 'consulta.html', 'imprimir_sticker.html', 'manual.html'];
    const path = window.location.pathname;
    const pageName = path.substring(path.lastIndexOf('/') + 1);

    if (publicPages.includes(pageName)) {
        return; // No requiere auth
    }

    // Load push.js dynamically for all authenticated pages
    const pushScript = document.createElement('script');
    pushScript.src = 'js/push.js';
    pushScript.onload = () => {
        // Suscripción silenciosa de Web Push en segundo plano si el permiso ya fue concedido
        if (typeof petulap_checkPushStatus === 'function' && petulap_checkPushStatus() === 'granted') {
            petulap_subscribePush().catch(e => console.log('Silent push update failed', e));
        }
    };
    document.head.appendChild(pushScript);

    try {
        const res = await fetch('api/auth.php?action=check').then(r => r.json());
        if (!res.ok) {
            // Sesion expirada o no iniciada
            window.location.href = 'login.html';
        } else {
            // Guardar modulos
            window.userModulos = res.data.modulos || [];
            const esAdmin = (res.data.tipo === 'admin');

            if (!esAdmin && window.userModulos.length > 0 && pageName !== 'login.html' && !publicPages.includes(pageName) && pageName !== '' && !window.userModulos.includes(pageName)) {
                window.location.href = res.data.start_url || 'index.html';
                return;
            }

            // Ocultar links no permitidos en el DOM (el rol admin siempre tiene acceso a todos los modulos)
            if (!esAdmin && window.userModulos.length > 0) {
                document.querySelectorAll('a[href$=".html"]').forEach(link => {
                    const href = link.getAttribute('href');
                    if (href && !publicPages.includes(href) && href !== 'index.html' && !window.userModulos.includes(href)) {
                        link.style.display = 'none';
                    }
                });
                
                // Limpiar categorias vacias en el index
                document.querySelectorAll('.dash-category').forEach(cat => {
                    const grid = cat.querySelector('.dashboard-grid');
                    if (grid) {
                        const visibleCards = Array.from(grid.querySelectorAll('.dash-card')).filter(c => c.style.display !== 'none');
                        if (visibleCards.length === 0) {
                            cat.style.display = 'none';
                        }
                    }
                });
            }

            // Sesion activa, configurar globales para compatibilidad legacy
            window.miId = (res.data.tipo === 'admin' || res.data.tipo === 'gerencia') ? res.data.tipo : res.data.id;
            
            // Personalizar boton central movil segun el rol
            const mobileNavs = document.querySelectorAll('.mobile-nav');
            mobileNavs.forEach(nav => {
                const middleBtn = nav.querySelectorAll('.nav-item')[1]; // Indice 1 es el boton central (Buscar)
                if (middleBtn) {
                    if (res.data.tipo === 'tecnico') {
                        middleBtn.href = 'mis_ordenes.html';
                        middleBtn.innerHTML = '<i class="ph ph-kanban"></i><span>Kanban</span>';
                    } else if (res.data.tipo === 'gerencia') {
                        middleBtn.href = 'reportes.html';
                        middleBtn.innerHTML = '<i class="ph ph-chart-bar"></i><span>Reportes</span>';
                    } else if (res.data.tipo === 'admin') {
                        middleBtn.href = '#';
                        middleBtn.onclick = (e) => { 
                            e.preventDefault(); 
                            document.getElementById('mobile-sidebar')?.classList.toggle('active'); 
                        };
                        middleBtn.innerHTML = '<i class="ph ph-list"></i><span>Menu</span>';
                    }
                }
            });
            
            // Actualizar etiquetas visuales si existen
            const lblNombre = document.getElementById('lbl-nombre');
            if (lblNombre) {
                let icon = '👨‍';
                let label = res.data.nombre.split(' ')[0];
                if (res.data.tipo === 'admin') { icon = '👑'; label = 'ADMIN'; }
                else if (res.data.tipo === 'gerencia') { icon = '👔'; label = 'GERENCIA'; }
                lblNombre.innerHTML = `${icon} ${label}`;
            }
            const lblTecnico = document.getElementById('lbl-tecnico');
            if (lblTecnico) {
                if (res.data.tipo === 'admin') lblTecnico.textContent = 'Admin';
                else if (res.data.tipo === 'gerencia') lblTecnico.textContent = 'Gerencia';
                else lblTecnico.textContent = 'Tec: ' + res.data.nombre.split(' ')[0];
            }

            // Ocultar el dropdown de login legacy en mis_ordenes.html si existe
            const loginBox = document.getElementById('login-box');
            if (loginBox) {
                loginBox.style.display = 'none';
            }
            
            // Mostrar panel principal si existe (mis_ordenes.html)
            const panelPrincipal = document.getElementById('panel-principal');
            if (panelPrincipal) {
                panelPrincipal.style.display = 'flex';
                if(document.getElementById('kanban-board')) {
                    document.getElementById('kanban-board').style.display = 'flex';
                }
                
                // Mostrar botones de admin si corresponde
                if (window.miId === 'admin') {
                    const btnCrear = document.getElementById('btn-crear-tarea');
                    const btnAsignar = document.getElementById('btn-asignar-rep');
                    if (btnCrear) btnCrear.style.display = 'block';
                    if (btnAsignar) btnAsignar.style.display = 'block';
                }

                if (typeof cargarMisOrdenes === 'function') {
                    cargarMisOrdenes();
                    if (typeof iniciarAutoRefresh === 'function') iniciarAutoRefresh();
                }
            }
        }
    } catch (e) {
        console.error("Auth check failed", e);
        window.location.href = 'login.html';
    }
});
