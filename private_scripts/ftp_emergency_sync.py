import ftplib
import os
import sys

FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"

FILES_TO_UPLOAD_API = [
    ("website_files/api/auth.php", "auth.php"),
    ("website_files/api/cleanup_dupes.php", "cleanup_dupes.php"),
    ("website_files/api/manage_accounts.php", "manage_accounts.php"),
]

FILES_TO_DELETE_ROOT = [
    "export_db.php",
    "test_db.php",
    "test_historial.php",
    "setup_auth.php",
    "iniciar.php",
    "refactor.py",
    "index2.html",
    "fix_mojibake.php",
]

FILES_TO_DELETE_API = [
    "test_db.php",
    "test_api.php",
    "test_proxy.php",
    "update_roles_temp.php",
    "update_schema_tmp.php",
    "setup_roles.php",
]

def main():
    print("=" * 60)
    print("SINCRONIZACIÓN DE REMEDIACIÓN FTP (PRODUCCIÓN)")
    print("=" * 60)
    
    print(f"Conectando a {FTP_HOST}...")
    ftp = ftplib.FTP(FTP_HOST, timeout=30)
    ftp.login(user=FTP_USER, passwd=FTP_PASS)
    ftp.set_pasv(True)
    print("Conectado exitosamente.\n")

    # 1. Subir archivos corregidos en public_html/api
    print("--- 1. Subiendo archivos modificados en api/ ---")
    ftp.cwd("public_html/api")
    for local_rel, remote_name in FILES_TO_UPLOAD_API:
        full_local = os.path.join(os.path.dirname(os.path.dirname(__file__)), local_rel)
        if os.path.exists(full_local):
            with open(full_local, "rb") as f:
                ftp.storbinary(f"STOR {remote_name}", f)
            print(f"  [SUBIDO OK] api/{remote_name}")
        else:
            print(f"  [ERROR] No existe archivo local: {full_local}")

    # 2. Eliminar archivos vulnerables en public_html/api
    print("\n--- 2. Eliminando scripts vulnerables en public_html/api/ ---")
    api_items = ftp.nlst()
    for fname in FILES_TO_DELETE_API:
        if fname in api_items:
            try:
                ftp.delete(fname)
                print(f"  [ELIMINADO REMOTO] api/{fname}")
            except Exception as e:
                print(f"  [ERROR BORRANDO] api/{fname}: {e}")
        else:
            print(f"  [NO ESTABA] api/{fname}")

    # 3. Eliminar archivos vulnerables en public_html/ (raíz)
    print("\n--- 3. Eliminando scripts vulnerables en public_html/ (raíz) ---")
    ftp.cwd("../")
    root_items = ftp.nlst()
    for fname in FILES_TO_DELETE_ROOT:
        if fname in root_items:
            try:
                ftp.delete(fname)
                print(f"  [ELIMINADO REMOTO] {fname}")
            except Exception as e:
                print(f"  [ERROR BORRANDO] {fname}: {e}")
        else:
            print(f"  [NO ESTABA] {fname}")

    ftp.quit()
    print("\n" + "=" * 60)
    print("SINCRONIZACIÓN FTP COMPLETADA CON ÉXITO")
    print("=" * 60)

if __name__ == "__main__":
    main()
