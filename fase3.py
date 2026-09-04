import os
import re

base_dir = r"c:\Users\Admin\Desktop\tdf\website_files\css"

with open(os.path.join(base_dir, "styles.css"), "r", encoding="utf-8") as f:
    styles = f.read()

styles = re.sub(r'\.card\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.card:hover\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn,\s*button:not\(\.navbar-toggle\)\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn:hover,\s*button:not\(\.navbar-toggle\):hover\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn-primary,\s*button\.btn-primary\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn-primary:hover\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn-secondary,\s*button\.btn-secondary\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn-secondary:hover\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn-danger,\s*button\.btn-danger\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn-danger:hover\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.btn-sm,\s*button\.btn-sm\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.form-control,\s*input\[type="text"\],\s*input\[type="password"\],\s*input\[type="number"\],\s*input\[type="date"\],\s*select,\s*textarea\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.form-control:focus,\s*input:focus,\s*select:focus,\s*textarea:focus\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.badge\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.badge-[A-Z]\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.triaje-[A-Z_]+\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.badge-activo\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.badge-inactivo\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.badge-primary\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.modal\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.modal-content\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.stat-card\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.stat-card\s*\.num\s*\{[^}]*\}', '', styles)
styles = re.sub(r'\.stat-card\s*\.lbl\s*\{[^}]*\}', '', styles)

new_components = """
/* ================== COMPONENTS BASE (FASE 3) ================== */
.card, .dash-card, .dash-card-modern {
    background: var(--bg-surface);
    border: 1px solid var(--border-default);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-card);
    padding: 24px;
    transition: var(--transition);
    color: var(--text-primary);
    text-decoration: none;
}
.card:hover, .dash-card:hover, .dash-card-modern:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    transform: translateY(-3px);
}
.btn, button:not(.navbar-toggle) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px; /* Accessible */
    padding: 0 20px;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    font-family: var(--font-family);
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition-fast);
    text-decoration: none;
    background-color: var(--bg-surface);
    color: var(--text-primary);
    box-shadow: var(--shadow-sm);
}
.btn:hover, button:not(.navbar-toggle):hover {
    background-color: var(--bg-surface-hover);
}
.btn-primary, button.btn-primary {
    background-color: var(--color-brand);
    color: var(--text-on-brand);
    box-shadow: 0 4px 12px var(--bg-brand-subtle);
}
.btn-primary:hover {
    background-color: var(--color-brand-hover);
}
.btn-secondary, button.btn-secondary {
    background-color: var(--bg-surface);
    color: var(--text-secondary);
    border: 1px solid var(--border-default);
}
.btn-secondary:hover {
    border-color: var(--text-secondary);
    color: var(--text-primary);
}
.btn-danger, button.btn-danger {
    background-color: var(--bg-danger-subtle);
    color: var(--color-danger);
    border: 1px solid transparent;
}
.btn-danger:hover {
    background-color: var(--color-danger);
    color: var(--text-on-brand);
}
.btn-outline {
    background: transparent;
    border: 1px solid var(--border-default);
    color: var(--text-primary);
}
.btn-outline:hover {
    background: var(--bg-surface-hover);
}
.btn-sm, button.btn-sm {
    min-height: 44px; /* Fixed accessibility */
    padding: 6px 12px;
    font-size: 13px;
}
.form-control, input[type="text"], input[type="password"], input[type="number"], input[type="date"], select, textarea {
    width: 100%;
    min-height: 44px;
    background-color: var(--bg-surface-hover);
    border: 1px solid var(--border-default);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    color: var(--text-primary);
    font-family: var(--font-family);
    font-size: 14px;
    transition: var(--transition-fast);
}
.form-control:focus, input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--color-brand);
    background-color: var(--bg-surface);
    box-shadow: 0 0 0 4px var(--bg-brand-subtle);
}
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    font-family: var(--font-family);
}
.badge--success, .badge-C, .triaje-SIN_FALLA { background: var(--bg-success-subtle); color: var(--text-success); }
.badge--warning, .badge-P, .triaje-FALLA_MENOR { background: var(--bg-warning-subtle); color: var(--text-warning); }
.badge--danger, .badge-M, .triaje-DANO_GRAVE { background: var(--bg-danger-subtle); color: var(--text-danger); }
.badge--info, .badge-V, .triaje-SOPORTE, .triaje-NECESITA_REPUESTO { background: var(--bg-info-subtle); color: var(--text-info); }
.badge-activo { background: var(--bg-success-subtle); color: var(--text-success); }
.badge-inactivo { background: var(--bg-danger-subtle); color: var(--text-danger); }

.stat-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-default);
    border-radius: var(--radius-md);
    padding: 20px;
    text-align: center;
    flex: 1;
    min-width: 150px;
    box-shadow: var(--shadow-sm);
}
.stat-card .num { font-size: 32px; color: var(--text-primary); font-weight: 700; font-family: var(--font-family); }
.stat-card .lbl { font-size: 12px; color: var(--text-secondary); margin-top: 5px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;}

.modal-overlay, .modal {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background-color: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    animation: fadeIn 0.3s forwards;
}
.modal-content, .modal-overlay .card {
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    padding: 30px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    transform: scale(0.95) translateY(10px);
    animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeIn { to { opacity: 1; } }
@keyframes popIn { to { transform: scale(1) translateY(0); } }
"""
styles += new_components
with open(os.path.join(base_dir, "styles.css"), "w", encoding="utf-8") as f:
    f.write(styles)


with open(os.path.join(base_dir, "dashboard.css"), "r", encoding="utf-8") as f:
    dash = f.read()

dash = re.sub(r'\.dash-card\s*\{[^}]*\}', '', dash)
dash = re.sub(r'\.dash-card:hover\s*\{[^}]*\}', '', dash)
dash = re.sub(r'\.dash-card-modern\s*\{[^}]*\}', '', dash)
dash = re.sub(r'\.dash-card-modern:hover\s*\{[^}]*\}', '', dash)
dash = re.sub(r'\.btn-outline\s*\{[^}]*\}', '', dash)
dash = re.sub(r'\.btn-outline:hover\s*\{[^}]*\}', '', dash)
dash = re.sub(r'\.modal-overlay\s*\{[^}]*\}', '', dash)
dash = re.sub(r'\.modal-overlay \.card\s*\{[^}]*\}', '', dash)
dash = re.sub(r'@keyframes fadeIn\s*\{[^}]*\}', '', dash)
dash = re.sub(r'@keyframes popIn\s*\{[^}]*\}', '', dash)

kanban_replacements = {
    'rgba(0,0,0,0.2)': 'var(--shadow-card)',
    '0 4px 10px rgba(0,0,0,0.3)': 'var(--shadow-card)'
}
for old, new in kanban_replacements.items():
    dash = dash.replace(old, new)

with open(os.path.join(base_dir, "dashboard.css"), "w", encoding="utf-8") as f:
    f.write(dash)
print("Fase 3 completed locally")
