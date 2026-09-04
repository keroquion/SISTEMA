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
