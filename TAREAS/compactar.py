"""
Compacta transcripciones pre-limpiadas:
1. Elimina muletillas y sonidos de asentimiento sueltos (ok, ya, hm, mhm, eh, yeah) cuando no aportan contenido.
2. Une frases cortadas de un mismo hablante que quedaron separadas por interjecciones vacías.
3. Limpia tartamudeos o repeticiones de palabras accidentales ("de de", "la la").
4. Mantiene 100% el significado original sin resumir ni interpretar.
"""

import re
import sys


PURE_FILLERS = {
    'ok', 'ok.', 'okay', 'okay.', 'ya', 'ya.', 'eso', 'eso.', 'eh', 'eh.',
    'hm', 'hm.', 'mhm', 'mhm.', 'mm', 'mm.', 'm', 'm.', 'ajá', 'ajá.',
    'yeah', 'yeah.', 'de'
}


def compact_transcript(text: str) -> str:
    lines = text.splitlines()
    legend = []
    dialogue = []
    is_legend = True

    for l in lines:
        if l.strip() == '# Transcripción':
            is_legend = False
            legend.append(l)
            continue
        if is_legend:
            legend.append(l)
        else:
            dialogue.append(l)

    # 1. Parsear turnos de diálogo
    turns = []
    for l in dialogue:
        m = re.match(r'^(S\d+):\s*(.*)$', l.strip())
        if m:
            turns.append([m.group(1), m.group(2).strip()])
        elif l.strip() and turns:
            turns[-1][1] += ' ' + l.strip()

    # 2. Filtrar turnos que consisten únicamente de muletillas vacías
    filtered_turns = []
    for spk, ut in turns:
        norm = ut.strip().lower()
        if norm in PURE_FILLERS:
            continue
        filtered_turns.append([spk, ut])

    # 3. Fusionar turnos consecutivos que quedaron juntos tras quitar las muletillas intermedias
    merged_turns = []
    for spk, ut in filtered_turns:
        if merged_turns and merged_turns[-1][0] == spk:
            merged_turns[-1][1] += ' ' + ut
        else:
            merged_turns.append([spk, ut])

    # 4. Limpiar repeticiones accidentales de palabras (tartamudeo: "de de", "la la")
    def remove_stutters(s):
        return re.sub(r'\b([A-Za-záéíóúñÁÉÍÓÚÑ]{2,})\s+\1\b', r'\1', s, flags=re.IGNORECASE)

    out_dialogue = []
    for spk, ut in merged_turns:
        clean_ut = remove_stutters(ut)
        clean_ut = re.sub(r'\s{2,}', ' ', clean_ut).strip()
        out_dialogue.append(f"{spk}: {clean_ut}")

    return '\n'.join(legend) + '\n\n' + '\n'.join(out_dialogue)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Uso: python compactar.py entrada_limpia.txt [salida_compactada.txt]")
        sys.exit(1)

    input_path = sys.argv[1]
    output_path = sys.argv[2] if len(sys.argv) > 2 else "transcripcion_compactada.txt"

    with open(input_path, "r", encoding="utf-8") as f:
        raw = f.read()

    compacted = compact_transcript(raw)

    with open(output_path, "w", encoding="utf-8") as f:
        f.write(compacted)

    red = 100 - (len(compacted) / len(raw) * 100) if raw else 0
    print(f"Listo -> {output_path}")
    print(f"Entrada: {len(raw):,} caracteres | Compactado: {len(compacted):,} caracteres ({red:.1f}% reducción adicional)")
