// ==========================================================
// PETULAP SALES CRM - BACKGROUND SERVICE WORKER (MV3)
// Alarmas periódicas y notificaciones nativas de Windows/Chrome
// ==========================================================

const API_BASE = 'https://petulap.store/crm_ventas/api';

// Crear alarma cada 10 minutos
chrome.runtime.onInstalled.addListener(() => {
    console.log('🚀 [Petulap CRM] Background Service Worker instalado.');
    chrome.alarms.create('check_recordatorios', { periodInMinutes: 10 });
    verificarRecordatorios();
});

chrome.alarms.onAlarm.addListener((alarm) => {
    if (alarm.name === 'check_recordatorios') {
        verificarRecordatorios();
    }
});

async function verificarRecordatorios() {
    try {
        const res = await fetch(`${API_BASE}/leads.php?action=recordatorios_hoy`);
        const json = await res.json();
        
        if (!json.success) return;

        const citas = json.citas_hoy || [];
        const frios = json.clientes_frios || [];

        // 1. Notificar citas programadas de hoy
        if (citas.length > 0) {
            const proxima = citas[0];
            const hora = new Date(proxima.fecha_hora).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            chrome.notifications.create(`cita_${proxima.id}`, {
                type: 'basic',
                iconUrl: 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="%233b82f6"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"/></svg>',
                title: `🔔 Petulap: Cita Hoy ${hora}`,
                message: `Visita con ${proxima.cliente_nombre} (${proxima.modelo_laptop || 'Laptop'}) en Sede ${proxima.tipo.includes('CAYMA') ? 'Cayma' : 'Yanahuara'}.`,
                priority: 2
            });
        }

        // 2. Notificar si hay más de 3 clientes fríos (>24h sin respuesta)
        if (frios.length >= 3) {
            chrome.notifications.create('alerta_frios', {
                type: 'basic',
                iconUrl: 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="%23ef4444"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
                title: '🔥 Petulap: Clientes Fríos Pendientes',
                message: `Tienes ${frios.length} prospectos en visto (>24h). Envíales una oferta o regalo para reactivarlos.`,
                priority: 1
            });
        }
    } catch (err) {
        console.error('Error en background verificando recordatorios:', err);
    }
}
