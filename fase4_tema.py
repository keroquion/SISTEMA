import os
import glob

# 1. Update navbar.js with toggle logic
navbar_path = r'c:\Users\Admin\Desktop\tdf\website_files\js\navbar.js'
with open(navbar_path, 'r', encoding='utf-8') as f:
    navbar_content = f.read()

if 'function toggleTheme' not in navbar_content:
    toggle_script = '''
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
'''
    with open(navbar_path, 'a', encoding='utf-8') as f:
        f.write('\n' + toggle_script)

head_script = '''
    <script>
      (function(){
        if(localStorage.getItem('petulap-theme') === 'dark') {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      })();
    </script>
</head>'''

btn_html = '''
                <button class="theme-toggle-btn" aria-label="Cambiar tema" onclick="toggleTheme()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-secondary); margin-right: 15px; display: flex; align-items: center;">
                    <i class="ph ph-moon"></i>
                </button>
                <button class="notification-btn">'''

login_btn = '''<body>
  <button class="theme-toggle-btn" aria-label="Cambiar tema" onclick="toggleTheme()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-secondary);">
    <i class="ph ph-moon"></i>
  </button>'''

html_files = glob.glob(r'c:\Users\Admin\Desktop\tdf\website_files\*.html')
for fp in html_files:
    if 'imprimir_sticker.html' in fp:
        continue
    
    with open(fp, 'r', encoding='utf-8') as f:
        content = f.read()
        
    modified = False
    
    # Inject HEAD script
    if 'petulap-theme' not in content and '</head>' in content:
        content = content.replace('</head>', head_script, 1)
        modified = True
        
    # Inject button
    if 'theme-toggle-btn' not in content:
        if 'login.html' in fp:
            content = content.replace('<body>', login_btn, 1)
            modified = True
        elif '<button class="notification-btn">' in content:
            content = content.replace('<button class="notification-btn">', btn_html, 1)
            modified = True
            
    if modified:
        with open(fp, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'Updated {os.path.basename(fp)}')

print('Theme setup complete!')
