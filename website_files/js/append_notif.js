
// ====== NOTIFICATION BELL LOGIC ======
document.addEventListener('DOMContentLoaded', function() {
    const bellBtn = document.querySelector('.notification-btn');
    if (!bellBtn) return;
    
    // Inject dropdown container
    const notifHtml = '<div id="notif-dropdown" class="card" style="display:none; position:absolute; right:20px; top:60px; width:320px; max-height:400px; overflow-y:auto; z-index:9999; padding:0; box-shadow:0 10px 25px rgba(0,0,0,0.2);">'
      + '<div style="padding:15px; border-bottom:1px solid var(--border-default); display:flex; justify-content:space-between; align-items:center;">'
      + '<h3 style="margin:0; font-size:16px;">Notificaciones</h3>'
      + '<button onclick="marcarTodasLeidas()" style="background:none; border:none; color:var(--color-brand); cursor:pointer; font-size:12px; font-weight:bold;">Marcar leídas</button>'
      + '</div>'
      + '<div id="notif-list" style="padding:10px;">Cargando...</div>'
      + '</div>';
    document.body.insertAdjacentHTML('beforeend', notifHtml);
    
    // Inject badge (hide by default)
    const badgeHtml = '<span id="notif-badge" style="display:none; position:absolute; top:-2px; right:-2px; background:var(--color-danger); color:white; border-radius:50%; font-size:10px; width:16px; height:16px; align-items:center; justify-content:center; font-weight:bold;">0</span>';
    bellBtn.style.position = 'relative';
    bellBtn.insertAdjacentHTML('beforeend', badgeHtml);
    
    const dropdown = document.getElementById('notif-dropdown');
    
    bellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if(dropdown.style.display === 'none') {
            dropdown.style.display = 'block';
            cargarNotificaciones();
        } else {
            dropdown.style.display = 'none';
        }
    });
    
    // Clic fuera del dropdown lo cierra
    document.addEventListener('click', (e) => {
        if (dropdown.style.display === 'block' && !dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
    
    // Auto load on start
    cargarNotificaciones();
    // Poll every 5 minutes
    setInterval(cargarNotificaciones, 5 * 60 * 1000);
});

window.cargarNotificaciones = async function() {
    try {
        const res = await fetch('api/notificaciones.php?action=list').then(r=>r.json());
        if(res.ok) {
            let unread = 0;
            let html = '';
            res.data.forEach(n => {
                if(n.leido == 0) unread++;
                const bg = n.leido == 0 ? 'var(--bg-surface-hover)' : 'transparent';
                const fw = n.leido == 0 ? 'bold' : 'normal';
                html += `<div style="padding:12px; border-bottom:1px solid var(--border-default); background:${bg}; cursor:pointer; border-radius:8px; margin-bottom:5px; transition: background 0.2s;" onclick="clickNotif(${n.id}, '${n.link}')" onmouseover="this.style.background='var(--bg-surface-hover)'" onmouseout="this.style.background='${bg}'">
                    <div style="font-weight:${fw}; font-size:14px; margin-bottom:5px; color:var(--text-primary);"><i class="ph ph-bell-ringing" style="margin-right:5px; color:var(--color-brand)"></i>${n.titulo}</div>
                    <div style="font-size:13px; color:var(--text-secondary); line-height: 1.3;">${n.mensaje}</div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:6px; text-align:right;">${n.fecha}</div>
                </div>`;
            });
            if(res.data.length === 0) html = '<div style="padding:20px; text-align:center; color:var(--text-secondary);">No hay notificaciones nuevas</div>';
            document.getElementById('notif-list').innerHTML = html;
            
            const badge = document.getElementById('notif-badge');
            if(unread > 0) {
                badge.style.display = 'flex';
                badge.innerText = unread > 9 ? '9+' : unread;
            } else {
                badge.style.display = 'none';
            }
        }
    } catch(e) {}
};

window.clickNotif = async function(id, link) {
    await fetch('api/notificaciones.php?action=marcar_leida', {method:'POST', body:JSON.stringify({id: id})});
    if(link && link !== 'null') window.location.href = link;
    else cargarNotificaciones();
};

window.marcarTodasLeidas = async function() {
    await fetch('api/notificaciones.php?action=marcar_todas_leidas');
    cargarNotificaciones();
};
