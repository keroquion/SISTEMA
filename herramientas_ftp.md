# Herramientas de Conexión FTP y Sincronización (Scripts Python)

Estos scripts se utilizan para sincronizar localmente los archivos y conectarse al servidor FTP de producción. Puedes usarlos para subir o descargar archivos rápidamente.

## Credenciales FTP Comunes:
- **Host:** `ftp.petulap.store`
- **Usuario:** `petumjvq`
- **Contraseña:** `HjBI32sh5kAb`
- **Ruta Web Principal:** `public_html/`

---

## 1. Subir archivos modificados (`ftp_sync.py`)
Este script se encarga de subir los archivos editados localmente a sus carpetas correspondientes en producción (`css/`, `js/`, `api/`, y la raíz).

```python
import ftplib
import os

FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"

def upload_files():
    try:
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(user=FTP_USER, passwd=FTP_PASS)
        ftp.set_pasv(True)
        
        # Subir CSS
        ftp.cwd('public_html/css')
        for filename in os.listdir('website_files/css'):
            if filename.endswith('.css'):
                with open(os.path.join('website_files/css', filename), 'rb') as f:
                    ftp.storbinary(f'STOR {filename}', f)
                print(f"Subido: css/{filename}")
        
        # Subir HTMLs
        ftp.cwd('../')
        for filename in os.listdir('website_files'):
            if filename.endswith('.html'):
                with open(os.path.join('website_files', filename), 'rb') as f:
                    ftp.storbinary(f'STOR {filename}', f)
                print(f"Subido: {filename}")
                
        # Subir JS
        ftp.cwd('js')
        for filename in os.listdir('website_files/js'):
            if filename.endswith('.js'):
                with open(os.path.join('website_files/js', filename), 'rb') as f:
                    ftp.storbinary(f'STOR {filename}', f)
                print(f"Subido: js/{filename}")
                
        # Subir API
        try:
            ftp.cwd('../api')
        except:
            ftp.mkd('../api')
            ftp.cwd('../api')
            
        for filename in os.listdir('website_files/api'):
            if filename.endswith('.php') or filename.endswith('.json'):
                with open(os.path.join('website_files/api', filename), 'rb') as f:
                    ftp.storbinary(f'STOR {filename}', f)
                print(f"Subido: api/{filename}")

        # Subir root JS, PHP y JSON
        ftp.cwd('../')
        for filename in os.listdir('website_files'):
            if filename.endswith(('.js', '.php', '.json')):
                with open(os.path.join('website_files', filename), 'rb') as f:
                    ftp.storbinary(f'STOR {filename}', f)
                print(f"Subido: {filename}")
                
        ftp.quit()
        print("Sincronización completada exitosamente.")
    except Exception as e:
        print(f"Error de FTP: {e}")

if __name__ == "__main__":
    upload_files()
```

---

## 2. Descargar Todo el Sitio (`download_all.py`)
Script recursivo para descargar una copia completa del entorno de producción.

```python
import ftplib
import os

FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"
LOCAL_DIR = "website_files"

def download_dir(ftp, remote_dir, local_dir):
    try:
        os.makedirs(local_dir, exist_ok=True)
        ftp.cwd(remote_dir)
        items = ftp.nlst()
        for item in items:
            if item in ('.', '..'):
                continue
            
            # Check if it's a directory
            try:
                ftp.cwd(item)
                ftp.cwd('..')
                is_dir = True
            except ftplib.error_perm:
                is_dir = False
                
            local_path = os.path.join(local_dir, item)
            
            if is_dir:
                print(f"Directory: {item}")
                download_dir(ftp, item, local_path)
                ftp.cwd('..')  # go back to current directory after returning
            else:
                print(f"Downloading: {item} to {local_path}")
                with open(local_path, 'wb') as f:
                    ftp.retrbinary(f'RETR {item}', f.write)
    except Exception as e:
        print(f"Error in {remote_dir}: {e}")

def main():
    print("Connecting to FTP...")
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(user=FTP_USER, passwd=FTP_PASS)
    ftp.set_pasv(True)
    
    print("Starting download from public_html...")
    download_dir(ftp, 'public_html', LOCAL_DIR)
    
    ftp.quit()
    print("Download completed.")

if __name__ == "__main__":
    main()
```

---

## 3. Ejemplo de Script Único (`upload_and_clean.py`)
Muestra cómo subir un archivo específico e invocar un webhook para ejecutar limpieza post-subida en el servidor.

```python
import ftplib
import urllib.request
import time

FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"

print("Uploading cleanup script...")
ftp = ftplib.FTP(FTP_HOST)
ftp.login(user=FTP_USER, passwd=FTP_PASS)
ftp.set_pasv(True)
ftp.cwd('public_html/api')

with open('website_files/api/cleanup_dupes.php', 'rb') as f:
    ftp.storbinary('STOR cleanup_dupes.php', f)

# Also update importar.php while we are at it
with open('website_files/api/importar.php', 'rb') as f:
    ftp.storbinary('STOR importar.php', f)

ftp.quit()
print("Uploaded. Triggering cleanup...")

time.sleep(2)
try:
    with urllib.request.urlopen('https://petulap.store/api/cleanup_dupes.php') as response:
        html = response.read()
        print(html.decode('utf-8'))
except Exception as e:
    print(f"Error accessing URL: {e}")
```
