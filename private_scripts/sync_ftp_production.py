import ftplib, os

FTP_HOST = 'ftp.petulap.store'
FTP_USER = 'petumjvq'
FTP_PASS = 'HjBI32sh5kAb'

print('=' * 60)
print('INICIANDO SINCRONIZACION FTP COMPLETA (PRODUCCION)')
print('=' * 60)

ftp = ftplib.FTP(FTP_HOST, timeout=30)
ftp.login(user=FTP_USER, passwd=FTP_PASS)
ftp.set_pasv(True)
print('Conectado exitosamente a', FTP_HOST)

# 1. Subir archivos a public_html/ (raiz)
html_files = [
    'admin_roles.html',
    'caja.html',
    'clientes.html',
    'desempeno_tecnicos.html',
    'garantias.html',
    'historial_entregados.html',
    'importar.html',
    'index.html',
    'inventario.html',
    'inventario_soporte.html',
    'lotes.html',
    'manual.html',
    'mis_ordenes.html',
    'pedidos_repuestos.html',
    'recepcion_movil.html',
    'reportes.html',
    'repuestos.html',
    'soporte.html',
    'tecnicos.html',
    'turnos.html',
    'sw.js',
    '.htaccess'
]
root_uploads = [(os.path.join('website_files', f), f) for f in html_files]

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

# 2. Subir dashboard.js a public_html/js/ y dashboard.css a public_html/css/
print('\n--- 2. Subiendo js/dashboard.js y css/dashboard.css ---')
ftp.cwd('js')
with open('website_files/js/dashboard.js', 'rb') as f:
    ftp.storbinary('STOR dashboard.js', f)
size_dash = os.path.getsize('website_files/js/dashboard.js')
print(f'  [SUBIDO OK] js/dashboard.js ({size_dash} bytes)')

ftp.cwd('../css')
with open('website_files/css/dashboard.css', 'rb') as f:
    ftp.storbinary('STOR dashboard.css', f)
size_css = os.path.getsize('website_files/css/dashboard.css')
print(f'  [SUBIDO OK] css/dashboard.css ({size_css} bytes)')
ftp.cwd('../js')

# 3. Eliminar navbar.js de public_html/js/ (si existiera)
print('\n--- 3. Verificando js/navbar.js en public_html/js/ ---')
js_list = ftp.nlst()
if 'navbar.js' in js_list:
    try:
        ftp.delete('navbar.js')
        print('  [ELIMINADO REMOTO OK] js/navbar.js')
    except Exception as e:
        print('  [ERROR BORRANDO] js/navbar.js:', e)
else:
    print('  [LIMPIO] js/navbar.js no está en el servidor')

# 4. Subir endpoints optimizados a public_html/api/
print('\n--- 4. Subiendo endpoints optimizados de escalabilidad a public_html/api/ ---')
ftp.cwd('../api')

api_files = [
    'desempeno.php',
    'equipos.php',
    'garantias.php',
    'historial.php',
    'lotes.php',
    'notificaciones.php',
    'personas.php',
    'repuestos.php',
    'sesiones.php',
    'soporte.php',
    'turnos.php',
]

for fname in api_files:
    local_path = os.path.join('website_files', 'api', fname)
    if os.path.exists(local_path):
        with open(local_path, 'rb') as f:
            ftp.storbinary(f'STOR {fname}', f)
        size = os.path.getsize(local_path)
        print(f'  [SUBIDO OK] api/{fname} ({size} bytes)')
    else:
        print(f'  [ERROR] No existe localmente: {local_path}')

# 5. Eliminar schema_dump.php y update_roles.php de public_html/ (raiz)
print('\n--- 5. Verificando scripts vulnerables en public_html/ ---')
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
    print('  [LIMPIO] Scripts vulnerables no residen en la raíz pública')

ftp.quit()
print('\n' + '=' * 60)
print('SINCRONIZACION Y LIMPIEZA FTP FINALIZADA EXITOSAMENTE')
print('=' * 60)
