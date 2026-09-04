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
    $codigo = $db->real_escape_string($row['codigo'] ?? $row['Codigo'] ?? $row['CODIGO'] ?? '');
    
    // Si no tiene codigo, intentamos usar la serie
    if (!$codigo) {
        $codigo = $db->real_escape_string($row['serie'] ?? $row['Serie'] ?? $row['SERIE'] ?? '');
    }

    if (!$codigo) {
        $errors++; // Fila invalida sin identificador
        continue;
    }

    // 2. Verificar duplicidad ignorando espacios y mayusculas
    $codigo_clean = trim(strtolower($codigo));
    $res = $db->query("SELECT id FROM equipos WHERE LOWER(TRIM(codigo)) = '$codigo_clean'");
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
            if (($col === 'fec_compra' || $col === 'fec_venta') && empty(trim($found_val))) {
                $vals[] = "NULL";
            } else {
                $vals[] = "'" . $db->real_escape_string($found_val) . "'";
            }
        }
    }
    
    // Forzamos el codigo si no lo encontro por mapeo exacto
    if (!in_array('codigo', $cols)) {
        $cols[] = 'codigo';
        $vals[] = "'" . $codigo . "'";
    }

    if (count($cols) > 0) {
        $cols_str = implode(',', $cols);
        $vals_str = implode(',', $vals);
        $sql = "INSERT INTO equipos ($cols_str) VALUES ($vals_str)";
        if ($db->query($sql)) {
            $inserted++;
        } else {
            error_log("Error importando fila $codigo: " . $db->error);
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
