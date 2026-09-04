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
