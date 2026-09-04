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
