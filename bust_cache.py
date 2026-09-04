import os
import glob

html_files = glob.glob('c:/Users/Admin/Desktop/tdf/website_files/*.html')

for file in html_files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Remove navbar.js
    if 'js/navbar.js' in content:
        content = content.replace('<script src="js/navbar.js"></script>', '')
        content = content.replace('<script src="js/navbar.js?v=2"></script>', '')
        
    # Bust cache for dashboard.js
    content = content.replace('<script src="js/dashboard.js"></script>', '<script src="js/dashboard.js?v=2"></script>')
    content = content.replace('<script src="js/dashboard.js?v=2"></script>', '<script src="js/dashboard.js?v=3"></script>')
        
    # Bust cache for styles.css
    content = content.replace('<link rel="stylesheet" href="css/styles.css">', '<link rel="stylesheet" href="css/styles.css?v=2">')
    content = content.replace('<link rel="stylesheet" href="css/styles.css?v=2">', '<link rel="stylesheet" href="css/styles.css?v=3">')

    # Bust cache for dashboard.css
    content = content.replace('<link rel="stylesheet" href="css/dashboard.css">', '<link rel="stylesheet" href="css/dashboard.css?v=2">')
    content = content.replace('<link rel="stylesheet" href="css/dashboard.css?v=2">', '<link rel="stylesheet" href="css/dashboard.css?v=3">')
        
    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)

print("Cache busting tags added successfully.")
