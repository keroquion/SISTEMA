import re
import sqlite3

def to_sqlite():
    conn = sqlite3.connect(':memory:')
    c = conn.cursor()
    c.execute('''CREATE TABLE soporte_tecnico (
      id INTEGER PRIMARY KEY,
      numero_atencion TEXT,
      equipo_codigo TEXT,
      estado TEXT
    )''')
    
    with open('backup.sql', 'r', encoding='utf-8') as f:
        for line in f:
            if line.startswith("INSERT INTO `soporte_tecnico`"):
                # (id, num_atencion, eq_codigo, ...)
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
                        num = parts[1].strip('"')
                        codigo = parts[2].strip('"')
                        estado = parts[10].strip('"')
                        c.execute("INSERT INTO soporte_tecnico (id, numero_atencion, equipo_codigo, estado) VALUES (?, ?, ?, ?)", (id_val, num, codigo, estado))
    
    conn.commit()
    
    c.execute("SELECT COUNT(*) FROM soporte_tecnico")
    print(f"Total soporte: {c.fetchone()[0]}")
    
    c.execute("SELECT estado, COUNT(*) FROM soporte_tecnico GROUP BY estado")
    print("\nPor estado (soporte):")
    for row in c.fetchall():
        print(f"  {row[0]}: {row[1]}")

to_sqlite()
