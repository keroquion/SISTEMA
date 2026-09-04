import ftplib
import urllib.request

FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"

ftp = ftplib.FTP(FTP_HOST)
ftp.login(user=FTP_USER, passwd=FTP_PASS)
ftp.set_pasv(True)
ftp.cwd('public_html/api')

with open('website_files/api/setup_roles.php', 'rb') as f:
    ftp.storbinary('STOR setup_roles.php', f)

ftp.quit()

try:
    with urllib.request.urlopen('https://petulap.store/api/setup_roles.php') as response:
        html = response.read()
        print(html.decode('utf-8'))
except Exception as e:
    print(f"Error accessing URL: {e}")
