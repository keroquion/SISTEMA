"""
Limpia transcripciones de clase/chat (formato "NOMBRE: mensaje", con o sin
timestamps tipo "00:00:00.000,00:00:03.000") para reducir tokens antes de
pasarlas a una IA.

Qué hace:
1. Fusiona líneas consecutivas del mismo hablante en un solo bloque.
2. Reemplaza los nombres completos por códigos cortos (S1, S2, ...) y
   pone la leyenda una sola vez al inicio.
3. Quita las marcas de tiempo (opcional conservarlas, solo el inicio de
   cada bloque).
4. No resume ni cambia el contenido — solo compacta el formato.

Uso:
    python limpiar_transcripcion.py entrada.txt [salida.txt] [--timestamps]
"""

import re
import sys

TIMESTAMP_RE = re.compile(r'^\d{1,2}:\d{2}(:\d{2})?(\.\d+)?(,\d{1,2}:\d{2}(:\d{2})?(\.\d+)?)?$')
# Nombre en mayúsculas (o Nombre Apellido) seguido de ": mensaje"
SPEAKER_RE = re.compile(r'^([A-ZÁÉÍÓÚÑÜa-záéíóúñü][A-ZÁÉÍÓÚÑÜa-záéíóúñü .]{2,60}?):\s*(.*)$')


def clean_transcript(raw_text: str, keep_timestamps: bool = False) -> str:
    lines = raw_text.splitlines()
    entries = []  # [speaker, texto, timestamp_inicio]
    current_ts = None

    for line in lines:
        line = line.strip()
        if not line:
            continue

        if TIMESTAMP_RE.match(line):
            current_ts = line.split(',')[0]
            continue

        m = SPEAKER_RE.match(line)
        if m:
            speaker, text = m.group(1).strip(), m.group(2).strip()
            entries.append([speaker, text, current_ts])
        else:
            # Línea de continuación (sin "NOMBRE:") -> se pega al mensaje anterior
            if entries:
                entries[-1][1] += ' ' + line

    # Fusionar líneas consecutivas del mismo hablante
    merged = []
    for speaker, text, ts in entries:
        if merged and merged[-1][0] == speaker:
            merged[-1][1] += ' ' + text
        else:
            merged.append([speaker, text, ts])

    # Códigos cortos por orden de aparición
    codes = {}
    for speaker, _, _ in merged:
        if speaker not in codes:
            codes[speaker] = f"S{len(codes) + 1}"

    out = ["# Leyenda de hablantes", ""]
    for speaker, code in codes.items():
        out.append(f"{code} = {speaker}")
    out += ["", "# Transcripción", ""]

    for speaker, text, ts in merged:
        code = codes[speaker]
        prefix = f"[{ts}] " if keep_timestamps and ts else ""
        out.append(f"{prefix}{code}: {text}")

    return "\n".join(out)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Uso: python limpiar_transcripcion.py entrada.txt [salida.txt] [--timestamps]")
        sys.exit(1)

    input_path = sys.argv[1]
    keep_ts = "--timestamps" in sys.argv
    positional = [a for a in sys.argv[2:] if not a.startswith("--")]
    output_path = positional[0] if positional else "transcripcion_limpia.txt"

    with open(input_path, "r", encoding="utf-8") as f:
        raw = f.read()

    cleaned = clean_transcript(raw, keep_timestamps=keep_ts)

    with open(output_path, "w", encoding="utf-8") as f:
        f.write(cleaned)

    reduccion = 100 - (len(cleaned) / len(raw) * 100) if raw else 0
    print(f"Listo -> {output_path}")
    print(f"Original: {len(raw):,} caracteres | Limpio: {len(cleaned):,} caracteres "
          f"({reduccion:.1f}% de reducción)")