document.addEventListener("DOMContentLoaded", function() {
    // Inject the navbar at the top of the body
    const navbarHTML = `
        <nav class="navbar">
            <a href="index.html" class="navbar-brand">
                ⚙️ <span>Petulap</span> SST
            </a>
            <button class="navbar-toggle" id="navbarToggle" aria-label="Toggle navigation">
                ☰
            </button>
            <div class="navbar-menu" id="navbarMenu">
                <a href="index.html" class="navbar-link">🏠 Inicio</a>
                <a href="recepcion_movil.html" class="navbar-link">📱 Recepción</a>
                <a href="inventario.html" class="navbar-link">🗃️ Inventario</a>
                <a href="reportes.html" class="navbar-link">📊 Reportes</a>
                <a href="login.html" class="navbar-link" id="navLogout" style="color: var(--danger);" onclick="logout(event)">🚪 Salir</a>
            </div>
        </nav>
    `;

    document.body.insertAdjacentHTML('afterbegin', navbarHTML);

    const toggleBtn = document.getElementById('navbarToggle');
    const menu = document.getElementById('navbarMenu');

    toggleBtn.addEventListener('click', () => {
        menu.classList.toggle('active');
        toggleBtn.innerText = menu.classList.contains('active') ? '✖' : '☰';
    });
});

async function logout(e) {
    e.preventDefault();
    try {
        await fetch('api/auth.php?action=logout');
    } catch(err) {}
    window.location.href = 'login.html';
}