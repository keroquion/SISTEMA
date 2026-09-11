import ftplib
import os
import sys

# Configuración FTP de Petulap
FTP_HOST = 'ftp.petulap.store'
FTP_USER = 'petumjvq'
FTP_PASS = 'HjBI32sh5kAb'

LOCAL_DIR = os.path.dirname(os.path.abspath(__file__))
REMOTE_BASE = 'public_html/crm_ventas'

print('=' * 65)
print('🚀 INICIANDO SUBIDA AISLADA DE CRM VENTAS A PRODUCCIÓN')
print(f'📍 Local:  {LOCAL_DIR}')
print(f'🌐 Remoto: {REMOTE_BASE}')
print('=' * 65)

try:
    ftp = ftplib.FTP(FTP_HOST, timeout=30)
    ftp.login(user=FTP_USER, passwd=FTP_PASS)
    ftp.set_pasv(True)
    print(f'✅ Conexión establecida exitosamente con {FTP_HOST}')
except Exception as e:
    print(f'❌ Error conectando al FTP: {e}')
    sys.exit(1)

def ensure_remote_dir(path):
    parts = path.strip('/').split('/')
    cur = ''
    for p in parts:
        cur += '/' + p
        try:
            ftp.cwd(cur)
        except ftplib.error_perm:
            try:
                ftp.mkd(cur)
                print(f'📁 Creado directorio remoto: {cur}')
                ftp.cwd(cur)
            except Exception as ex:
                print(f'Aviso creando {cur}: {ex}')

ensure_remote_dir(REMOTE_BASE)

# Archivos a ignorar durante la subida al hosting
IGNORE_EXTENSIONS = ['.py', '.sqlite']

subidos = 0
for root, dirs, files in os.walk(LOCAL_DIR):
    # Calcular ruta relativa
    rel_path = os.path.relpath(root, LOCAL_DIR)
    if rel_path == '.':
        remote_dir = REMOTE_BASE
    else:
        remote_dir = REMOTE_BASE + '/' + rel_path.replace('\\', '/')
    
    ensure_remote_dir(remote_dir)
    ftp.cwd('/' + remote_dir)

    for file in files:
        if any(file.endswith(ext) for ext in IGNORE_EXTENSIONS):
            continue
        
        local_file_path = os.path.join(root, file)
        print(f'⬆️ Subiendo: {file} a /{remote_dir}/...')
        try:
            with open(local_file_path, 'rb') as f:
                ftp.storbinary(f'STOR {file}', f)
            subidos += 1
        except Exception as fe:
            print(f'❌ Error subiendo {file}: {fe}')

ftp.quit()
print('=' * 65)
print(f'🎉 ¡SUBIDA COMPLETADA CON ÉXITO! {subidos} archivos transferidos.')
print(f'👉 Acceso en producción: https://petulap.store/crm_ventas/index.html')
print('=' * 65)
