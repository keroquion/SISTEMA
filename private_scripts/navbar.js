// js/navbar.js - Mantenido por retrocompatibilidad de las funciones logout y clearCache
// La inyeccion de HTML fue eliminada porque ahora usamos el layout moderno centralizado.

async function logout(e) {
    e.preventDefault();
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

window.addEventListener('DOMContentLoaded', () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.querySelectorAll('.theme-toggle-btn i').forEach(icon => {
        icon.className = isDark ? 'ph ph-sun' : 'ph ph-moon';
    });
});
