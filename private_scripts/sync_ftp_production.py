import ftplib, os

FTP_HOST = 'ftp.petulap.store'
FTP_USER = 'petumjvq'
FTP_PASS = 'HjBI32sh5kAb'

print('=' * 60)
print('INICIANDO SINCRONIZACION FTP COMPLETA')
print('=' * 60)

ftp = ftplib.FTP(FTP_HOST, timeout=30)
ftp.login(user=FTP_USER, passwd=FTP_PASS)
ftp.set_pasv(True)
print('Conectado exitosamente a', FTP_HOST)

# 1. Subir archivos a public_html/ (raiz)
root_uploads = [
    ('website_files/admin_roles.html', 'admin_roles.html'),
    ('website_files/desempeno_tecnicos.html', 'desempeno_tecnicos.html'),
    ('website_files/inventario.html', 'inventario.html'),
    ('website_files/lotes.html', 'lotes.html'),
    ('website_files/mis_ordenes.html', 'mis_ordenes.html'),
    ('website_files/pedidos_repuestos.html', 'pedidos_repuestos.html'),
    ('website_files/reportes.html', 'reportes.html'),
    ('website_files/sw.js', 'sw.js'),
    ('website_files/.htaccess', '.htaccess'),
]

ftp.cwd('public_html')
print('\n--- 1. Subiendo archivos principales a public_html/ ---')
for local_file, remote_file in root_uploads:
    if os.path.exists(local_file):
        with open(local_file, 'rb') as f:
            ftp.storbinary(f'STOR {remote_file}', f)
        size = os.path.getsize(local_file)
        print(f'  [SUBIDO OK] {remote_file} ({size} bytes)')
    else:
        print(f'  [ERROR] No existe localmente: {local_file}')

# 2. Subir dashboard.js a public_html/js/
print('\n--- 2. Subiendo js/dashboard.js a public_html/js/ ---')
ftp.cwd('js')
with open('website_files/js/dashboard.js', 'rb') as f:
    ftp.storbinary('STOR dashboard.js', f)
size_dash = os.path.getsize('website_files/js/dashboard.js')
print(f'  [SUBIDO OK] js/dashboard.js ({size_dash} bytes)')

# 3. Eliminar navbar.js de public_html/js/
print('\n--- 3. Eliminando js/navbar.js de public_html/js/ ---')
js_list = ftp.nlst()
if 'navbar.js' in js_list:
    try:
        ftp.delete('navbar.js')
        print('  [ELIMINADO REMOTO OK] js/navbar.js')
    except Exception as e:
        print('  [ERROR BORRANDO] js/navbar.js:', e)
else:
    print('  [NO ESTABA] js/navbar.js')

# 4. Subir api/soporte.php a public_html/api/
print('\n--- 4. Subiendo api/soporte.php a public_html/api/ ---')
ftp.cwd('../api')
with open('website_files/api/soporte.php', 'rb') as f:
    ftp.storbinary('STOR soporte.php', f)
size_sop = os.path.getsize('website_files/api/soporte.php')
print(f'  [SUBIDO OK] api/soporte.php ({size_sop} bytes)')

# 5. Eliminar schema_dump.php y update_roles.php de public_html/ (raiz)
print('\n--- 5. Eliminando scripts vulnerables de public_html/ ---')
ftp.cwd('..')
root_list = ftp.nlst()
for fname in ['schema_dump.php', 'update_roles.php']:
    if fname in root_list:
        try:
            ftp.delete(fname)
            print(f'  [ELIMINADO REMOTO OK] {fname}')
        except Exception as e:
            print(f'  [ERROR BORRANDO] {fname}: {e}')
    else:
        print(f'  [NO ESTABA] {fname}')

ftp.quit()
print('\n' + '=' * 60)
print('SINCRONIZACION Y LIMPIEZA FTP FINALIZADA')
print('=' * 60)
