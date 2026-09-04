import re
import sqlite3

def to_sqlite():
    conn = sqlite3.connect(':memory:')
    c = conn.cursor()
    c.execute('''CREATE TABLE equipos (
      id INTEGER PRIMARY KEY,
      serie TEXT,
      codigo TEXT,
      estado TEXT
    )''')
    
    with open('backup.sql', 'r', encoding='utf-8') as f:
        for line in f:
            if line.startswith("INSERT INTO `equipos`"):
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
                    
                    if len(parts) >= 15:
                        id_val = parts[0].strip('"')
                        codigo = parts[2].strip('"')
                        estado = parts[14].strip('"')
                        c.execute("INSERT INTO equipos (id, codigo, estado) VALUES (?, ?, ?)", (id_val, codigo, estado))
    
    conn.commit()
    
    c.execute("SELECT estado, COUNT(*) FROM equipos GROUP BY estado")
    print("\nPor estado (equipos):")
    for row in c.fetchall():
        print(f"  {row[0]}: {row[1]}")

to_sqlite()
