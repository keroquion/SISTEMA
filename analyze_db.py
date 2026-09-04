import re
from collections import defaultdict

equipos = []
with open('backup.sql', 'r', encoding='utf-8') as f:
    for line in f:
        if line.startswith("INSERT INTO `equipos`"):
            # values: (id, serie, codigo, tipo_equipo, marca, modelo, procesador, ram, hd_ssd, pantalla, case, res, pulg, sucursal, estado, obs, fcc, dc, fcv, dv, fr, triaje, falla, triaje_inicial)
            # This is hard to parse precisely with regex due to commas inside strings. 
            # We can just use a quick regex or ast.literal_eval if formatted right.
            pass

# Let's use a simpler approach. Read the whole file into sqlite memory to query it.
import sqlite3
import re

def to_sqlite():
    conn = sqlite3.connect(':memory:')
    c = conn.cursor()
    c.execute('''CREATE TABLE equipos (
      id INTEGER PRIMARY KEY,
      serie TEXT,
      codigo TEXT,
      triaje TEXT
    )''')
    
    with open('backup.sql', 'r', encoding='utf-8') as f:
        for line in f:
            if line.startswith("INSERT INTO `equipos`"):
                # Extract values between parentheses
                match = re.search(r'VALUES\((.*)\);$', line)
                if match:
                    val_str = match.group(1)
                    # Split by comma but respect quotes
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
                    
                    if len(parts) >= 22:
                        id_val = parts[0].strip('"')
                        serie = parts[1].strip('"')
                        codigo = parts[2].strip('"')
                        triaje = parts[21].strip('"')
                        c.execute("INSERT INTO equipos (id, serie, codigo, triaje) VALUES (?, ?, ?, ?)", (id_val, serie, codigo, triaje))
    
    conn.commit()
    
    c.execute("SELECT COUNT(*) FROM equipos")
    print(f"Total equipos: {c.fetchone()[0]}")
    
    c.execute("SELECT triaje, COUNT(*) FROM equipos GROUP BY triaje")
    print("\nPor triaje:")
    for row in c.fetchall():
        print(f"  {row[0]}: {row[1]}")
        
    c.execute('''
        SELECT codigo, COUNT(*) as c 
        FROM equipos 
        WHERE codigo != '' AND codigo != 'NULL'
        GROUP BY codigo 
        HAVING c > 1
    ''')
    dupes = c.fetchall()
    print(f"\nCodigos duplicados: {len(dupes)}")
    
    total_dupe_rows = sum([row[1] for row in dupes])
    print(f"Total filas implicadas en duplicados: {total_dupe_rows}")

to_sqlite()
