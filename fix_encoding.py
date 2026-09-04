"""
Fix UTF-8 mojibake in all HTML files under website_files/
Then upload the corrected files to FTP.
Uses re.sub with byte-level patterns to avoid encoding issues in the script itself.
"""
import os
import glob
import ftplib
import re


FTP_HOST = "ftp.petulap.store"
FTP_USER = "petumjvq"
FTP_PASS = "HjBI32sh5kAb"
LOCAL_DIR = "website_files"


def fix_mojibake(content):
    """
    Fix double-encoded UTF-8 (mojibake) by re-encoding.
    The files have UTF-8 bytes that were wrongly decoded as Latin-1,
    producing sequences like C3 A1 -> \u00c3\u00a1 (should be \u00e1).
    """
    # Strategy: encode back to latin-1 to recover original bytes, then decode as UTF-8
    # We do this per-segment to handle mixed content
    
    # Common mojibake patterns (latin-1 interpretation -> correct UTF-8 char)
    replacements = [
        # Lowercase accented vowels  
        ('\u00c3\u00a1', '\u00e1'),   # á
        ('\u00c3\u00a9', '\u00e9'),   # é
        ('\u00c3\u00ad', '\u00ed'),   # í
        ('\u00c3\u00b3', '\u00f3'),   # ó
        ('\u00c3\u00ba', '\u00fa'),   # ú
        # Uppercase accented vowels
        ('\u00c3\u0081', '\u00c1'),   # Á
        ('\u00c3\u0089', '\u00c9'),   # É
        ('\u00c3\u008d', '\u00cd'),   # Í
        ('\u00c3\u0093', '\u00d3'),   # Ó
        ('\u00c3\u009a', '\u00da'),   # Ú
        # Ñ / ñ
        ('\u00c3\u00b1', '\u00f1'),   # ñ
        ('\u00c3\u0091', '\u00d1'),   # Ñ
        # Ü / ü
        ('\u00c3\u00bc', '\u00fc'),   # ü
        ('\u00c3\u009c', '\u00dc'),   # Ü
        # Special punctuation
        ('\u00c2\u00a1', '\u00a1'),   # ¡
        ('\u00c2\u00bf', '\u00bf'),   # ¿
        ('\u00c2\u00ae', '\u00ae'),   # ®
        ('\u00c2\u00a9', '\u00a9'),   # ©
        ('\u00c2\u00aa', '\u00aa'),   # ª
        ('\u00c2\u00ba', '\u00ba'),   # º
        # Dashes and quotes (triple-byte sequences)
        ('\u00e2\u0080\u0094', '\u2014'),  # em dash —
        ('\u00e2\u0080\u0093', '\u2013'),  # en dash –
        ('\u00e2\u0080\u0099', '\u2019'),  # right single quote '
        ('\u00e2\u0080\u009c', '\u201c'),  # left double quote "
        ('\u00e2\u0080\u009d', '\u201d'),  # right double quote "
        ('\u00e2\u0080\u00a6', '\u2026'),  # ellipsis …
        ('\u00e2\u0080\u00a2', '\u2022'),  # bullet •
        ('\u00e2\u0086\u00b3', '\u21b3'),  # ↳
        ('\u00e2\u009c\u0085', '\u2705'),  # ✅ 
        # Stray Â before whitespace (NBSP double-encoding artifact)
        ('\u00c2 ', ' '),
    ]
    
    for bad, good in replacements:
        content = content.replace(bad, good)
    
    return content


def fix_file(filepath):
    """Fix mojibake in a single file. Returns True if changes were made."""
    try:
        with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
            content = f.read()
        
        original = content
        content = fix_mojibake(content)
        
        if content != original:
            with open(filepath, 'w', encoding='utf-8', newline='') as f:
                f.write(content)
            return True
        return False
    except Exception as e:
        print(f"  ERROR processing {filepath}: {e}")
        return False


def upload_to_ftp(local_files):
    """Upload corrected files to FTP."""
    print(f"\n{'='*60}")
    print(f"Uploading {len(local_files)} corrected files to FTP...")
    print(f"{'='*60}")
    
    try:
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(user=FTP_USER, passwd=FTP_PASS)
        ftp.set_pasv(True)
        ftp.cwd('public_html')
        
        for local_path in local_files:
            rel_path = os.path.relpath(local_path, LOCAL_DIR).replace('\\', '/')
            remote_dir = os.path.dirname(rel_path)
            filename = os.path.basename(rel_path)
            
            # Navigate to correct remote directory
            if remote_dir:
                try:
                    ftp.cwd(remote_dir)
                except ftplib.error_perm:
                    ftp.mkd(remote_dir)
                    ftp.cwd(remote_dir)
            
            print(f"  Uploading: {rel_path}")
            with open(local_path, 'rb') as f:
                ftp.storbinary(f'STOR {filename}', f)
            
            # Navigate back to public_html
            if remote_dir:
                depth = len(remote_dir.split('/'))
                for _ in range(depth):
                    ftp.cwd('..')
        
        ftp.quit()
        print("\nUpload completed successfully!")
    except Exception as e:
        print(f"\nFTP Error: {e}")


def main():
    html_files = glob.glob(os.path.join(LOCAL_DIR, '**', '*.html'), recursive=True)
    
    print(f"Scanning {len(html_files)} HTML files for encoding issues...")
    print(f"{'='*60}")
    
    fixed_files = []
    
    for filepath in sorted(html_files):
        changed = fix_file(filepath)
        rel = os.path.relpath(filepath, LOCAL_DIR)
        if changed:
            print(f"  FIXED: {rel}")
            fixed_files.append(filepath)
        else:
            print(f"  OK:    {rel}")
    
    print(f"\n{'='*60}")
    print(f"Results: {len(fixed_files)} files fixed out of {len(html_files)} scanned")
    
    if fixed_files:
        upload_to_ftp(fixed_files)
    else:
        print("No files needed fixing.")


if __name__ == '__main__':
    main()
