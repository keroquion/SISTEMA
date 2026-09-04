import os
import glob

html_files = glob.glob('c:/Users/Admin/Desktop/tdf/website_files/*.html')

tags = """
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="icon.jpg">
"""

for file in html_files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    if '<meta name="apple-mobile-web-app-capable"' not in content:
        content = content.replace('</head>', tags + '</head>')
        with open(file, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'PWA tags injected in {os.path.basename(file)}')
