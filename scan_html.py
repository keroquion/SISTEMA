import glob, re

files = glob.glob('c:/Users/Admin/Desktop/tdf/website_files/*.html')
issues = {}

for f in files:
    with open(f, 'r', encoding='utf-8') as file:
        content = file.read()
        
    filename = f.split('\\')[-1].split('/')[-1]
    file_issues = []
    
    # Check for empty onClick
    if 'onclick=""' in content or "onclick=''" in content:
        file_issues.append('Empty or broken onclick')
        
    # Check if dashboard.js is included
    if 'dashboard.js' not in content:
        file_issues.append('Missing dashboard.js')
        
    if file_issues:
        issues[filename] = set(file_issues)

for k, v in issues.items():
    print(k, v)
print('Done scanning HTML files.')
