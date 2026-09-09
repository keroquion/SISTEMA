<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    // Registrar cambio en historial (llamado internamente desde otras APIs)
    case "registrar":
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("INSERT INTO historial_cambios (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre) VALUES (?,?,?,?,?,?,?)");
        $tabla = $data["tabla"] ?? "";
        $reg_id = (int)($data["registro_id"] ?? 0);
        $num_ref = $data["numero_referencia"] ?? "";
        $campo = $data["campo"] ?? "";
        $ant = $data["valor_anterior"] ?? null;
        $nuevo = $data["valor_nuevo"] ?? null;
        $usuario = $data["usuario"] ?? "Sistema";
        $stmt->bind_param("sisssss", $tabla, $reg_id, $num_ref, $campo, $ant, $nuevo, $usuario);
        if ($stmt->execute()) echo json_encode(["ok" => true]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    // Ver historial de un registro especifico
    case "ver":
        $tabla = trim($_GET["tabla"] ?? "soporte_tecnico");
        $id = (int)($_GET["id"] ?? 0);
        $stmt = $db->prepare("SELECT * FROM historial_cambios WHERE tabla_origen=? AND registro_id=? ORDER BY fecha_cambio DESC");
        $stmt->bind_param("si", $tabla, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    // Ver historial reciente global
    case "reciente":
        $limit = (int)($_GET["limit"] ?? 50);
        $result = $db->query("SELECT * FROM historial_cambios ORDER BY fecha_cambio DESC LIMIT $limit");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
