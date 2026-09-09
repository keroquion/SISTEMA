import os
import re

dir_path = '.'

replacements = [
    (r'body\s*\{\s*font-family:\s*Arial,\s*sans-serif;\s*background:\s*#1a1a2e;\s*color:\s*#e0e0e0;\s*margin:\s*0;\s*padding:\s*15px;\s*\}',
     "body { font-family: 'Outfit', Arial, sans-serif; background:var(--bg-primary, #F8FAFC); color:var(--text-primary, #111827); margin:0; padding:15px; }"),
    (r'h2\s*\{\s*color:\s*#00c6ff;\s*margin:\s*0\s+0\s+10px;\s*\}',
     "h2 { color:var(--accent-hover, #00193A); margin:0 0 10px; font-weight: 600; }"),
    (r'\.nav\s*\{\s*margin-bottom:15px;\s*\}\s*\.nav a\s*\{\s*color:#00c6ff;\s*margin-right:15px;\s*\}',
     ".nav { margin-bottom:15px; background: #FFFFFF; padding: 10px 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid var(--border-color, #E5E7EB); } \n  .nav a { color:var(--accent-main, #1D4ED8); margin-right:15px; text-decoration:none; font-weight: 500; }"),
    (r'input,select\s*\{\s*background:#0f1117;\s*color:#e0e0e0;\s*border:1px solid #333;\s*padding:7px;\s*border-radius:4px;\s*\}',
     "input,select { background:var(--bg-card, #FFFFFF); color:var(--text-primary, #111827); border:1px solid var(--border-color, #E5E7EB); padding:10px 12px; border-radius:6px; font-family: 'Outfit', sans-serif; font-size: 15px;}\n  input:focus, select:focus { outline:none; border-color:var(--accent-main, #1D4ED8); box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15); }"),
    (r'button\s*\{\s*padding:8px 18px;\s*border:none;\s*border-radius:4px;\s*cursor:pointer;\s*font-size:14px;\s*\}',
     "button { padding:10px 18px; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-weight:600; transition: all 0.2s; font-family: 'Outfit', sans-serif;}"),
    (r'\.btn-primary\s*\{\s*background:#00c6ff;\s*color:#0f1117;\s*font-weight:bold;\s*\}',
     ".btn-primary { background:var(--accent-main, #1D4ED8); color:#FFFFFF; }\n  .btn-primary:hover { background:var(--accent-hover, #00193A); }"),
    (r'\.btn-sm\s*\{\s*padding:4px 10px;\s*font-size:12px;\s*\}',
     ".btn-sm { padding:6px 12px; font-size:12px; }"),
    (r'table\s*\{\s*width:100%;\s*border-collapse:collapse;\s*margin-top:10px;\s*\}',
     "table { width:100%; border-collapse:collapse; margin-top:10px; background: #FFFFFF; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }"),
    (r'th\s*\{\s*background:#16213e;\s*padding:8px;\s*text-align:left;\s*border-bottom:2px solid #00c6ff;\s*font-size:12px;\s*\}',
     "th { background:#F8FAFC; color:var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size:12px; padding:12px; border-bottom:1px solid var(--border-color);}"),
    (r'td\s*\{\s*padding:6px 8px;\s*border-bottom:1px solid #222;\s*font-size:12px;\s*\}',
     "td { border-bottom:1px solid var(--border-color); padding:12px; text-align:left; font-size:14px; }"),
    (r'tr:hover td\s*\{\s*background:#1a2a3e;\s*\}',
     "tr:hover td { background:#F8FAFC; }"),
    (r'\.filtros\s*\{\s*display:flex;\s*gap:10px;\s*margin-bottom:10px;\s*flex-wrap:wrap;\s*\}',
     ".filtros { display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap; background:#FFFFFF; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }"),
    (r'<meta name="theme-color" content="#1a1a2e">', '<meta name="theme-color" content="#F8FAFC">')
]

for filename in os.listdir(dir_path):
    if filename.endswith(".html"):
        with open(filename, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original = content
        for pattern, replacement in replacements:
            content = re.sub(pattern, replacement, content)
            
        if content != original:
            with open(filename, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Updated {filename}")
