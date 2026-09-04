<?php
define("DB_HOST", "localhost");
define("DB_USER", "petumjvq_petumjvq");
define("DB_PASS", "HjBI32sh5kAb");
define("DB_NAME", "petumjvq_pruebas");
define("DB_CHARSET", "utf8mb4");

function getDB() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        http_response_code(500);
        die(json_encode(["error" => "DB Error: " . $mysqli->connect_error]));
    }
    $mysqli->set_charset(DB_CHARSET);
    return $mysqli;
}

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit(); }

function check_api_access($modulo_requerido) {
    if (!isset($_SESSION['user_modulos'])) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['ok'=>false, 'msg'=>'Acceso denegado. No hay permisos definidos.']);
        exit;
    }
    
    // Si el usuario tiene permisos, lo dejamos pasar. 
    // Si el modulo requerido es un rol exacto (ej. 'admin_only'), validamos tipo
    if ($modulo_requerido === 'admin_only' && $_SESSION['user_tipo'] !== 'admin') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['ok'=>false, 'msg'=>'Acceso denegado. Solo administradores.']);
        exit;
    }

    if ($modulo_requerido !== 'admin_only' && !in_array($modulo_requerido, $_SESSION['user_modulos'])) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['ok'=>false, 'msg'=>"Acceso denegado. Se requiere modulo: $modulo_requerido"]);
        exit;
    }
}
