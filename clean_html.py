import os
import re

dir_path = 'website_files'

def clean_inline_style(style_str):
    properties = [p.strip() for p in style_str.split(';') if p.strip()]
    cleaned = []
    for p in properties:
        if ':' not in p:
            continue
        key, val = p.split(':', 1)
        key = key.strip().lower()
        val = val.strip().lower()
        
        # Keep structural and layout styles
        structural_keys = [
            'display', 'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
            'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right',
            'width', 'max-width', 'min-width', 'flex', 'gap', 'justify-content', 'align-items',
            'flex-wrap', 'flex-direction', 'border-radius', 'border-collapse', 'cursor', 'z-index',
            'position', 'top', 'left', 'height', 'min-height', 'overflow', 'overflow-x', 'text-overflow',
            'white-space', 'grid-column', 'grid-template-columns', 'text-align'
        ]
        
        if key in structural_keys:
            cleaned.append(p)
            continue
            
        if key == 'border' or key.startswith('border-'):
            if val in ['none', '0'] or key in ['border-radius', 'border-collapse']:
                cleaned.append(p)
            continue
            
        if key.startswith('background'):
            if val in ['none', 'transparent']:
                cleaned.append(p)
            continue
            
        if key in ['color', 'font-size', 'font-family', 'font-weight', 'text-decoration']:
            # Strip these completely to let styles.css handle them
            continue
            
        cleaned.append(p)
        
    return '; '.join(cleaned)

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    def replacer(match):
        orig_style = match.group(1)
        new_style = clean_inline_style(orig_style)
        if new_style:
            return f'style="{new_style}"'
        return '' 
        
    new_content = re.sub(r'style="([^"]*)"', replacer, content)
    new_content = re.sub(r"style='([^']*)'", replacer, new_content)
    
    if new_content != content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Cleaned {filepath}")

for filename in os.listdir(dir_path):
    if filename.endswith('.html'):
        process_file(os.path.join(dir_path, filename))
