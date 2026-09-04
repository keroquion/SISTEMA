import os
import re

base_dir = r"c:\Users\Admin\Desktop\tdf\website_files"
css_dir = os.path.join(base_dir, "css")

def process_styles():
    styles_path = os.path.join(css_dir, "styles.css")
    with open(styles_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Remove Outfit import
    content = re.sub(
        r"@import url\('https://fonts.googleapis.com/css2\?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap'\);",
        r"@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');",
        content
    )

    # Remove :root block
    content = re.sub(r':root\s*\{[^}]*\}', '', content)

    # Replacements
    replacements = {
        'var(--font-headings)': 'var(--font-family)',
        'var(--font-body)': 'var(--font-family)',
        'var(--accent-main)': 'var(--color-brand)',
        'var(--accent-hover)': 'var(--color-brand-hover)',
        'var(--accent-light)': 'var(--bg-brand-subtle)',
        'var(--bg-body)': 'var(--bg-page)',
        'var(--border-color)': 'var(--border-default)',
        'var(--border-focus)': 'var(--border-strong)',
        'var(--transition-normal)': 'var(--transition)',
        'var(--text-tertiary)': 'var(--text-muted)'
    }
    for old, new in replacements.items():
        content = content.replace(old, new)

    # Remove !important
    content = content.replace(' !important', '')
    content = content.replace('!important', '')

    with open(styles_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("styles.css processed")

def process_dashboard():
    dash_path = os.path.join(css_dir, "dashboard.css")
    with open(dash_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Remove :root block (lines 4-28 typically, but regex is safer)
    content = re.sub(r':root\s*\{[^}]*\}', '', content)

    replacements = {
        'var(--bg-body)': 'var(--bg-page)',
        'var(--bg-card)': 'var(--bg-surface)',
        'var(--primary)': 'var(--color-brand)',
        'var(--primary-hover)': 'var(--color-brand-hover)',
        'var(--primary-light)': 'var(--bg-brand-subtle)',
        'var(--dark-text)': 'var(--text-primary)',
        'var(--gray-text)': 'var(--text-secondary)',
        'var(--gray-light)': 'var(--bg-surface-hover)',
        'var(--border-color)': 'var(--border-default)',
        'var(--card-shadow)': 'var(--shadow-card)',
        'var(--border-radius)': 'var(--radius-lg)',
        'var(--surface)': 'var(--bg-surface)',
        'var(--surface-light)': 'var(--bg-surface-hover)',
        'var(--info)': 'var(--color-info)',
        'var(--success)': 'var(--color-success)',
        'var(--warning)': 'var(--color-warning)',
        'var(--danger)': 'var(--color-danger)',
        'var(--danger-light)': 'var(--bg-danger-subtle)',
        'var(--warning-light)': 'var(--bg-warning-subtle)'
    }
    for old, new in replacements.items():
        content = content.replace(old, new)

    with open(dash_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("dashboard.css processed")

def process_html():
    html_files = [
        "admin_roles.html", "caja.html", "clientes.html", "consulta.html",
        "garantias.html", "historial_entregados.html", "importar.html",
        "index.html", "inventario.html", "inventario_soporte.html",
        "login.html", "lotes.html", "manual.html", "mis_ordenes.html",
        "recepcion_movil.html", "reportes.html", "repuestos.html",
        "soporte.html", "tecnicos.html", "turnos.html"
    ]
    link_tag = '<link rel="stylesheet" href="css/tokens.css?v=1">\n    '

    for html_file in html_files:
        file_path = os.path.join(base_dir, html_file)
        if os.path.exists(file_path):
            with open(file_path, 'r', encoding='utf-8') as f:
                content = f.read()
                
            if 'tokens.css' not in content:
                match = re.search(r'<link[^>]*rel="stylesheet"[^>]*>', content)
                if match:
                    pos = match.start()
                    content = content[:pos] + link_tag + content[pos:]
                    with open(file_path, 'w', encoding='utf-8') as f:
                        f.write(content)
                    print(f"Updated {html_file}")
        else:
            print(f"File not found: {html_file}")

if __name__ == "__main__":
    process_styles()
    process_dashboard()
    process_html()
