import ftplib
import os
import sys

FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"

FILES_TO_SYNC = [
    "consulta.php",
    "equipos.php",
    "garantias.php",
    "historial.php",
    "importar.php",
    "lotes.php",
    "notificaciones.php",
    "personas.php",
    "push.php",
    "repuestos.php",
    "roles.php",
    "sesiones.php",
    "soporte.php",
    "turnos.php",
]

def sync():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    local_api_dir = os.path.join(base_dir, "website_files", "api")

    print(f"Iniciando conexion FTP a {FTP_HOST}...")
    ftp = ftplib.FTP(FTP_HOST, timeout=30)
    ftp.login(user=FTP_USER, passwd=FTP_PASS)
    ftp.set_pasv(True)
    print("Conectado exitosamente.")

    print("Cambiando directorio remoto a public_html/api...")
    ftp.cwd("public_html/api")

    success_count = 0
    for filename in FILES_TO_SYNC:
        local_path = os.path.join(local_api_dir, filename)
        if not os.path.exists(local_path):
            print(f"[ERROR] Archivo no existe localmente: {local_path}")
            continue

        size = os.path.getsize(local_path)
        with open(local_path, "rb") as f:
            ftp.storbinary(f"STOR {filename}", f)
        print(f"[OK] Subido: {filename} ({size} bytes)")
        success_count += 1

    ftp.quit()
    print(f"\nSincronizacion FTP completada: {success_count}/{len(FILES_TO_SYNC)} archivos subidos correctamente.")

if __name__ == "__main__":
    sync()
