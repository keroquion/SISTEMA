import re
import sqlite3

def to_sqlite():
    conn = sqlite3.connect(':memory:')
    c = conn.cursor()
    c.execute('''CREATE TABLE sesiones_items (
      id INTEGER PRIMARY KEY,
      codigo TEXT,
      triaje_asignado TEXT
    )''')
    
    with open('backup.sql', 'r', encoding='utf-8') as f:
        for line in f:
            if line.startswith("INSERT INTO `sesiones_items`"):
                match = re.search(r'VALUES\((.*)\);$', line)
                if match:
                    val_str = match.group(1)
                    parts = []
                    current = []
                    in_quote = False
                    for char in val_str:
                        if char == '"':
                            in_quote = not in_quote
                        elif char == ',' and not in_quote:
                            parts.append(''.join(current))
                            current = []
                            continue
                        current.append(char)
                    if current:
                        parts.append(''.join(current))
                    
                    if len(parts) >= 8:
                        id_val = parts[0].strip('"')
                        codigo = parts[2].strip('"')
                        triaje = parts[7].strip('"')
                        c.execute("INSERT INTO sesiones_items (id, codigo, triaje_asignado) VALUES (?, ?, ?)", (id_val, codigo, triaje))
    
    conn.commit()
    
    c.execute("SELECT COUNT(*) FROM sesiones_items")
    print(f"Total sesiones_items: {c.fetchone()[0]}")
    
    c.execute("SELECT triaje_asignado, COUNT(*) FROM sesiones_items GROUP BY triaje_asignado")
    print("\nPor triaje (sesiones_items):")
    for row in c.fetchall():
        print(f"  {row[0]}: {row[1]}")

to_sqlite()
