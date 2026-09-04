import re

with open('website_files/index.html', 'r', encoding='utf-8') as f:
    html = f.read()

# Remove the old categories, we need to extract from <div class="dash-category">...</div>... until <div class="status-bar"
match = re.search(r'<div class="dash-category">.*?</div>\s*<div class="dash-category">.*?</div>\s*<div class="dash-category">.*?</div>\s*<div class="status-bar"', html, re.DOTALL)

if not match:
    # maybe it wasn't exactly 3, fallback to a broader regex
    match = re.search(r'<div class="dash-category">.*<div class="status-bar"', html, re.DOTALL)

new_dashboard_html = """
<div class="dash-category">
  <h3>🛠️ Soporte y Taller</h3>
  <div class="dashboard-grid">
    <a href="soporte.html" class="dash-card primary op-gerencial" id="btn-buscador">
      <div class="dash-icon">🔍</div>
      <div class="dash-title">Tickets de Soporte</div>
    </a>
    <a href="mis_ordenes.html" class="dash-card primary op-operativa">
      <div class="dash-icon">🎛️</div>
      <div class="dash-title">Panel Kanban (Taller)</div>
    </a>
    <a href="tecnicos.html" class="dash-card primary op-operativa">
      <div class="dash-icon">🔧</div>
      <div class="dash-title">Técnicos</div>
    </a>
    <a href="recepcion_movil.html" class="dash-card primary op-operativa">
      <div class="dash-icon">📱</div>
      <div class="dash-title">App Recepción (Móvil)</div>
    </a>
    <a href="garantias.html" class="dash-card primary op-operativa">
      <div class="dash-icon">🛡️</div>
      <div class="dash-title">Garantías</div>
    </a>
  </div>
</div>

<div class="dash-category">
  <h3>📦 Inventario y Operaciones</h3>
  <div class="dashboard-grid">
    <a href="inventario.html" class="dash-card primary op-operativa">
      <div class="dash-icon">🗃️</div>
      <div class="dash-title">Inventario General</div>
    </a>
    <a href="inventario_soporte.html" class="dash-card warning op-operativa">
      <div class="dash-icon">🔫</div>
      <div class="dash-title">Escaneo / Triaje Masivo</div>
    </a>
    <a href="importar.html" class="dash-card success op-operativa">
      <div class="dash-icon">📥</div>
      <div class="dash-title">Importar Excel</div>
    </a>
    <a href="caja.html" class="dash-card success op-operativa">
      <div class="dash-icon">📦</div>
      <div class="dash-title">Caja / Entregas</div>
    </a>
  </div>
</div>

<div class="dash-category">
  <h3>📊 Reportes</h3>
  <div class="dashboard-grid">
    <a href="reportes.html" class="dash-card purple op-gerencial" id="btn-reportes-main">
      <div class="dash-icon">📊</div>
      <div class="dash-title">Lotes y Equipos (Reportes)</div>
    </a>
  </div>
</div>

<div class="dash-category">
  <h3>💼 Gerencia</h3>
  <div class="dashboard-grid">
    <a href="lotes.html" class="dash-card primary op-gerencial">
      <div class="dash-icon">📦</div>
      <div class="dash-title">Lotes Masivos</div>
    </a>
    <a href="clientes.html" class="dash-card primary op-gerencial">
      <div class="dash-icon">👤</div>
      <div class="dash-title">Actividades y Clientes</div>
    </a>
  </div>
</div>

<div class="dash-category">
  <h3>👥 Administración y Utilidades</h3>
  <div class="dashboard-grid">
    <a href="turnos.html" class="dash-card primary op-operativa">
      <div class="dash-icon">🕐</div>
      <div class="dash-title">Asignación Turnos</div>
    </a>
    <a href="manual.html" class="dash-card warning op-operativa">
      <div class="dash-icon">📖</div>
      <div class="dash-title">Manual de Usuario</div>
    </a>
    
    <!-- Botón para ver más opciones (Gerencia) - Movido aquí abajo -->
    <a href="#" class="dash-card secondary" id="btn-ver-mas" style="display: none;" onclick="event.preventDefault(); document.querySelectorAll('.op-operativa').forEach(e => e.style.display='flex'); this.style.display='none';">
      <div class="dash-icon">⚙️</div>
      <div class="dash-title">Ver Más Opciones...</div>
    </a>
  </div>
</div>
"""

new_html = html[:match.start()] + new_dashboard_html + '\n  <div class="status-bar"'

with open('website_files/index.html', 'w', encoding='utf-8') as f:
    f.write(new_html)
print("Dashboard rebuilt with 5 categories successfully.")
