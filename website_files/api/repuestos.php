<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {
    case "list":
        $result = $db->query("SELECT * FROM repuestos ORDER BY nombre ASC");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "buscar":
        $raw_q = trim($_GET["q"] ?? "");
        $q = "%" . $raw_q . "%";
        $stmt = $db->prepare("SELECT * FROM repuestos WHERE nombre LIKE ? OR pn LIKE ? ORDER BY nombre ASC LIMIT 15");
        $stmt->bind_param("ss", $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("INSERT INTO repuestos (nombre, pn, stock, precio, notas) VALUES (?,?,?,?,?)");
        $n = $data["nombre"];
        $pn = $data["pn"] ?? "";
        $s = (int)($data["stock"] ?? 0);
        $p = (float)($data["precio"] ?? 0);
        $nt = $data["notas"] ?? "";
        $stmt->bind_param("ssids", $n, $pn, $s, $p, $nt);
        if ($stmt->execute()) echo json_encode(["ok" => true, "id" => $db->insert_id]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "actualizar":
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)$data["id"];
        $stmt = $db->prepare("UPDATE repuestos SET nombre=?, pn=?, stock=?, precio=?, notas=? WHERE id=?");
        $n = $data["nombre"];
        $pn = $data["pn"] ?? "";
        $s = (int)($data["stock"] ?? 0);
        $p = (float)($data["precio"] ?? 0);
        $nt = $data["notas"] ?? "";
        $stmt->bind_param("ssidsi", $n, $pn, $s, $p, $nt, $id);
        if ($stmt->execute()) echo json_encode(["ok" => true]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "eliminar":
        $id = (int)($_GET["id"] ?? 0);
        if ($db->query("DELETE FROM repuestos WHERE id=$id")) echo json_encode(["ok" => true]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion invalida"]);
}
$db->close();
?>
