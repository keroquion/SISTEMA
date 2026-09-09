<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['rows']) || !is_array($data['rows'])) {
    echo json_encode(['ok'=>false, 'msg'=>'No hay datos para importar.']);
    exit;
}

$rows = $data['rows'];
$inserted = 0;
$ignored = 0;
$errors = 0;

// Lista de columnas permitidas en la BD
$allowed_columns = [
    'serie', 'codigo', 'tipo_equipo', 'marca', 'modelo', 'procesador', 'ram', 'hd_ssd', 
    'pantalla', 'case_equipo', 'resolucion', 'pulgadas', 'sucursal', 'estado', 
    'observacion', 'fec_compra', 'doc_compra', 'fec_venta', 'doc_venta'
];

foreach ($rows as $row) {
    // 1. Buscar si ya existe el codigo
    $codigo = trim($row['codigo'] ?? $row['Codigo'] ?? $row['CODIGO'] ?? '');
    
    // Si no tiene codigo, intentamos usar la serie
    if (!$codigo) {
        $codigo = trim($row['serie'] ?? $row['Serie'] ?? $row['SERIE'] ?? '');
    }

    if (!$codigo) {
        $errors++; // Fila invalida sin identificador
        continue;
    }

    // 2. Verificar duplicidad ignorando espacios y mayusculas
    $codigo_clean = trim(strtolower($codigo));
    $stmt_chk = $db->prepare("SELECT id FROM equipos WHERE LOWER(TRIM(codigo)) = ?");
    $stmt_chk->bind_param("s", $codigo_clean);
    $stmt_chk->execute();
    $res = $stmt_chk->get_result();
    if ($res && $res->num_rows > 0) {
        $ignored++; // REGLA DE ORO: Si ya existe, se ignora por completo
        continue; 
    }

    // 3. Preparar Insercion
    $cols = [];
    $vals = [];
    foreach ($allowed_columns as $col) {
        // Buscamos ignorando mayusculas/minusculas
        $found_val = null;
        foreach ($row as $k => $v) {
            if (strtolower(trim($k)) === strtolower(trim($col))) {
                $found_val = $v;
                break;
            }
        }

        if ($found_val !== null) {
            $cols[] = $col;
            // Manejar fechas vacias en Excel
            if (($col === 'fec_compra' || $col === 'fec_venta') && empty(trim((string)$found_val))) {
                $vals[] = null;
            } else {
                $vals[] = (string)$found_val;
            }
        }
    }
    
    // Forzamos el codigo si no lo encontro por mapeo exacto
    if (!in_array('codigo', $cols)) {
        $cols[] = 'codigo';
        $vals[] = (string)$codigo;
    }

    if (count($cols) > 0) {
        $cols_str = implode(',', $cols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO equipos ($cols_str) VALUES ($placeholders)";
        $stmt_ins = $db->prepare($sql);
        if ($stmt_ins) {
            $types = str_repeat('s', count($vals));
            $stmt_ins->bind_param($types, ...$vals);
            if ($stmt_ins->execute()) {
                $inserted++;
            } else {
                error_log("Error importando fila $codigo: " . $stmt_ins->error);
                $errors++;
            }
        } else {
            error_log("Error preparando insercion fila $codigo: " . $db->error);
            $errors++;
        }
    } else {
        $errors++;
    }
}

echo json_encode([
    'ok' => true,
    'inserted' => $inserted,
    'ignored' => $ignored,
    'errors' => $errors
]);
$db->close();
