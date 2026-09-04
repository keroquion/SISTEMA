import os
import re

base_dir = r"c:\Users\Admin\Desktop\tdf\website_files"

emoji_map = {
    '📦': '<i class="ph ph-package"></i>',
    '🔄': '<i class="ph ph-arrow-clockwise"></i>',
    '✅': '<i class="ph ph-check-circle"></i>',
    '🖨️': '<i class="ph ph-printer"></i>',
    '➕': '<i class="ph ph-plus"></i>',
    '⚠️': '<i class="ph ph-warning"></i>',
    '❌': '<i class="ph ph-x"></i>',
    '👥': '<i class="ph ph-users"></i>',
    '⚙️': '<i class="ph ph-gear"></i>',
    '📊': '<i class="ph ph-chart-bar"></i>',
    '💰': '<i class="ph ph-money"></i>',
    '📱': '<i class="ph ph-device-mobile"></i>',
    '🔧': '<i class="ph ph-wrench"></i>',
    '📋': '<i class="ph ph-clipboard"></i>',
    '🏠': '<i class="ph ph-house"></i>',
    '🔍': '<i class="ph ph-magnifying-glass"></i>',
    '📅': '<i class="ph ph-calendar"></i>',
    '🏢': '<i class="ph ph-buildings"></i>',
    '🛒': '<i class="ph ph-shopping-cart"></i>',
    '💵': '<i class="ph ph-currency-dollar"></i>',
    '🏷️': '<i class="ph ph-tag"></i>',
    '💳': '<i class="ph ph-credit-card"></i>',
    '🛡️': '<i class="ph ph-shield-check"></i>',
    '🕰️': '<i class="ph ph-clock"></i>',
    '💻': '<i class="ph ph-laptop"></i>',
    '🖥️': '<i class="ph ph-desktop"></i>'
}

html_files = [f for f in os.listdir(base_dir) if f.endswith('.html')]

script_tag = '<script src="https://unpkg.com/@phosphor-icons/web"></script>'

for file in html_files:
    path = os.path.join(base_dir, file)
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Inject Phosphor script if not present
    if 'unpkg.com/@phosphor-icons' not in content:
        # Find </head>
        content = re.sub(r'</head>', f'    {script_tag}\n</head>', content, flags=re.IGNORECASE)

    # Replace Emojis
    for emoji, icon in emoji_map.items():
        content = content.replace(emoji, icon)

    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
        
print("Fase 4 (Icon migration) completed locally")
