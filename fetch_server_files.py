import ftplib
import os

FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"

try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(user=FTP_USER, passwd=FTP_PASS)
    ftp.set_pasv(True)
    
    ftp.cwd('public_html')
    
    files = ftp.nlst()
    print("Files in public_html:", files)
    
    if 'error_log' in files:
        with open('error_log_server.txt', 'wb') as f:
            ftp.retrbinary('RETR error_log', f.write)
        print("Downloaded error_log.")
        
    ftp.cwd('api')
    
    api_files = ftp.nlst()
    if 'error_log' in api_files:
        with open('error_log_server_api.txt', 'wb') as f:
            ftp.retrbinary('RETR error_log', f.write)
        print("Downloaded API error_log.")
        
    with open('equipos_server.php', 'wb') as f:
        ftp.retrbinary('RETR equipos.php', f.write)
    print("Downloaded equipos.php")
    
    ftp.quit()
except Exception as e:
    print(f"Error FTP: {e}")
