# Guía de Procesamiento y Limpieza de Transcripciones

Esta guía documenta el flujo de trabajo utilizado para transformar transcripciones crudas de clases (formato Google Meet / Teams) en textos compactos de bajo consumo de tokens, listos para ser analizados por una IA sin pérdida de información ni alteración del significado.

---

## 📌 Resumen del Flujo de Trabajo

El procesamiento consta de **dos etapas**:

```
[Transcripción Cruda]
        │
        ▼ (Paso 1: limpiar_transcripcion.py)
[Transcripción Pre-limpiada]  --> Fusión de turnos consecutivos + Códigos S1, S2 + Quita timestamps
        │
        ▼ (Paso 2: compactar.py)
[Transcripción Compactada]    --> Elimina muletillas vacías aisladas + Une frases interrumpidas
```

* **Reducción de tamaño promedio:** entre **43% y 45%**.
* **Integridad del mensaje:** 100% preservada (sin resúmenes ni interpretaciones).

---

## 🛠️ Herramientas Disponibles

En la carpeta `TAREAS/` tienes los dos scripts listos para usar:

1. **`limpiar_transcripcion.py`** (o `herramienta.py`):
   * Filtra metadatos iniciales (lista de asistentes, encabezados).
   * Identifica marcas de tiempo periódicas (`00:05:00`, `00:10:00`, etc.) y las retira para no contaminar el texto.
   * Asigna identificadores cortos por orden de aparición (`S1`, `S2`, `S3`...) y genera la `# Leyenda de hablantes` arriba.
   * Fusiona líneas consecutivas emitidas por el mismo hablante.

2. **`compactar.py`**:
   * Descarta turnos que solo contienen ruidos de asentimiento o muletillas sin contenido (`Hm.`, `Yeah.`, `Mhm.`, `Ok.`, etc.).
   * Une frases que quedaron cortadas cuando el hablante fue interrumpido por una muletilla o pausa.
   * Corrige duplicaciones de palabras accidentales por tartamudeo o audio (`"de de" -> "de"`, `"la la" -> "la"`).

---

## 🚀 Cómo Aplicarlo a Nuevos Documentos (Paso a Paso)

Abre la terminal de PowerShell en la carpeta `TAREAS`:

```powershell
cd c:\Users\Admin\Desktop\tdf\TAREAS
```

### 1. Procesar un solo archivo

Supongamos que subes un nuevo archivo llamado `nueva_clase.txt`:

```powershell
# Paso 1: Pre-limpiar (códigos de hablante y fusión inicial)
python limpiar_transcripcion.py "nueva_clase.txt" "limpio_nueva_clase.txt"

# Paso 2: Compactar (remover muletillas sueltas y unir frases cortadas)
python compactar.py "limpio_nueva_clase.txt" "compactado_nueva_clase.txt"
```

El archivo final optimizado para la IA será `compactado_nueva_clase.txt`.

---

### 2. Procesar varios archivos en lote (Batch en PowerShell)

Si subes varias transcripciones a la vez, puedes procesarlas automáticamente con este comando:

```powershell
Get-ChildItem -Filter "*Transcript.txt" | ForEach-Object {
    $baseName = $_.BaseName
    $limpio = "limpio_$baseName.txt"
    $compactado = "compactado_$baseName.txt"

    Write-Host "Procesando: $($_.Name)" -ForegroundColor Cyan
    python limpiar_transcripcion.py $_.Name $limpio
    python compactar.py $limpio $compactado
}
```

---

## 📋 Reglas de Calidad Aplicadas

1. **Sin Alucinaciones ni Pérdida de Datos:** Ninguna frase explicativa, concepto técnico, pregunta o respuesta es eliminada.
2. **Muletillas Sueltas:** Solo se eliminan si ocupan un turno completo como sonido pasivo (`S1: Hm.`, `S3: Yeah.`). Si una palabra como *"ok"* o *"ya"* forma parte de una orden o explicación (*"Ya, entonces abran la plataforma..."*), se mantiene intacta.
3. **Unión de Ideas:** Las oraciones separadas por la transcripción automática se reconectan para dar fluidez y contexto al modelo de lenguaje que las lea después.
4. **Codificación:** Todo se procesa y guarda en `UTF-8` para asegurar que las tildes, la letra `ñ` y los caracteres especiales se conserven correctamente.
