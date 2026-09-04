import re

with open('website_files/index.html', 'r', encoding='utf-8') as f:
    html = f.read()

# The cards are inside <div class="dashboard-grid">
cards_match = re.search(r'<div class="dashboard-grid">(.*?)</div>\s*<div class="status-bar"', html, re.DOTALL)
if not cards_match:
    print("Could not find dashboard-grid")
    exit(1)

cards_html = cards_match.group(1)

# Extract individual cards
# A card usually looks like <a href="..." class="..." id="...">...</a>
# We can just split by <a href=...
# Actually it's easier to manually map the links to categories and just write the new HTML.

new_dashboard_html = """
<style>
  .dash-category { margin-top: 30px; }
  .dash-category h3 { color: var(--text-secondary); font-size: 14px; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; }
</style>

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
  <h3>📦 Inventario y Almacén</h3>
  <div class="dashboard-grid">
    <a href="inventario.html" class="dash-card primary op-operativa">
      <div class="dash-icon">🗃️</div>
      <div class="dash-title">Inventario General</div>
    </a>
    <a href="lotes.html" class="dash-card primary op-gerencial">
      <div class="dash-icon">📦</div>
      <div class="dash-title">Lotes Masivos</div>
    </a>
    <a href="reportes.html" class="dash-card purple op-gerencial" id="btn-reportes-main">
      <div class="dash-icon">📊</div>
      <div class="dash-title">Lotes y Equipos (Reportes)</div>
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
  <h3>👥 Administración y Clientes</h3>
  <div class="dashboard-grid">
    <a href="clientes.html" class="dash-card primary op-gerencial">
      <div class="dash-icon">👤</div>
      <div class="dash-title">Actividades y Clientes</div>
    </a>
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

new_html = html[:cards_match.start()] + new_dashboard_html + "\n  <div class=\"status-bar\"" + html[cards_match.end()-len('</div>'):]

# Fix the script at the bottom that moves btn-ver-mas
# "const grid = document.querySelector('.dashboard-grid');"
# "grid.appendChild(document.getElementById('btn-ver-mas'));"
new_html = new_html.replace(
    "const grid = document.querySelector('.dashboard-grid');",
    "// Boton ver mas ya esta ubicado"
).replace(
    "grid.appendChild(document.getElementById('btn-ver-mas'));",
    ""
)

with open('website_files/index.html', 'w', encoding='utf-8') as f:
    f.write(new_html)
print("Dashboard rebuilt successfully.")
