<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "get_activa";
$usuario_id = $_SESSION['user_id'];
$usuario_nombre = $_SESSION['user_nombre'];
$es_admin = ($_SESSION['user_tipo'] === 'admin');

switch ($action) {
    case "get_activa":
        $hoy = date('Y-m-d');
        $res = $db->query("SELECT * FROM sesiones_inventario WHERE usuario_id = $usuario_id AND estado = 'ACTIVA' AND DATE(fecha_creacion) = '$hoy' ORDER BY id DESC LIMIT 1");
        if ($res->num_rows > 0) {
            $sesion = $res->fetch_assoc();
        } else {
            $nombre = "Lista de $usuario_nombre - " . date('d/m/Y');
            $stmt = $db->prepare("INSERT INTO sesiones_inventario (nombre, usuario_id) VALUES (?, ?)");
            $stmt->bind_param("si", $nombre, $usuario_id);
            $stmt->execute();
            $sesion_id = $stmt->insert_id;
            $res = $db->query("SELECT * FROM sesiones_inventario WHERE id = $sesion_id");
            $sesion = $res->fetch_assoc();
        }
        
        $items = [];
        $res2 = $db->query("SELECT s.id as sesion_item_id, e.*, e.triaje as triaje_asignado, e.falla as falla_asignada FROM sesiones_items s JOIN equipos e ON s.equipo_id = e.id WHERE s.sesion_id = " . $sesion['id'] . " ORDER BY s.id ASC");
        while($r = $res2->fetch_assoc()) {
            $items[] = $r;
        }
        $sesion['items'] = $items;
        echo json_encode(["ok" => true, "data" => $sesion]);
        break;

    case "inventario_rapido":
        $data = json_decode(file_get_contents("php://input"), true);
        $sesion_id = (int)($data["sesion_id"] ?? 0);
        $codigo = $db->real_escape_string($data["codigo"] ?? "");
        $triaje = $db->real_escape_string($data["triaje"] ?? "SIN_FALLA");
        $falla = $db->real_escape_string($data["falla"] ?? "");

        if (!$codigo || !$sesion_id) {
            echo json_encode(["ok" => false, "msg" => "Faltan datos (codigo o sesion)"]);
            break;
        }

        $res = $db->query("SELECT * FROM equipos WHERE codigo='$codigo' OR serie='$codigo' LIMIT 1");
        $equipo = $res->fetch_assoc();
        
        if (!$equipo) {
            echo json_encode(["ok" => false, "msg" => "Equipo no encontrado en BD"]);
            break;
        }

        $equipo_id = $equipo["id"];
        
        $chk = $db->query("SELECT id FROM sesiones_items WHERE sesion_id=$sesion_id AND equipo_id=$equipo_id");
        if ($chk->num_rows > 0) {
            $si_id = $chk->fetch_assoc()['id'];
            $db->query("UPDATE sesiones_items SET triaje_asignado='$triaje', falla_asignada='$falla' WHERE id=$si_id");
            $sesion_item_id = $si_id;
        } else {
            $stmt = $db->prepare("INSERT INTO sesiones_items (sesion_id, equipo_id, triaje_asignado, falla_asignada) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $sesion_id, $equipo_id, $triaje, $falla);
            $stmt->execute();
            $sesion_item_id = $stmt->insert_id;
        }

        $stmt = $db->prepare("UPDATE equipos SET triaje=?, falla=? WHERE id=?");
        $stmt->bind_param("ssi", $triaje, $falla, $equipo_id);
        if ($stmt->execute()) {
            $equipo['sesion_item_id'] = $sesion_item_id;
            $equipo['triaje_asignado'] = $triaje;
            $equipo['falla_asignada'] = $falla;
            echo json_encode(["ok" => true, "data" => $equipo]);
        } else {
            echo json_encode(["ok" => false, "msg" => $db->error]);
        }
        break;

    case "deshacer_item":
        $data = json_decode(file_get_contents("php://input"), true);
        $sesion_item_id = (int)($data["sesion_item_id"] ?? 0);
        
        $chk = $db->query("SELECT equipo_id FROM sesiones_items WHERE id=$sesion_item_id");
        if ($chk->num_rows > 0) {
            $equipo_id = $chk->fetch_assoc()['equipo_id'];
            $db->query("DELETE FROM sesiones_items WHERE id=$sesion_item_id");
            $db->query("UPDATE equipos SET triaje=NULL, falla=NULL WHERE id=$equipo_id");
            echo json_encode(["ok" => true]);
        } else {
            echo json_encode(["ok" => false, "msg" => "No encontrado"]);
        }
        break;

    case "listar_sesiones":
        if (!$es_admin) { echo json_encode(["ok"=>false, "msg"=>"Solo admin"]); break; }
        $res = $db->query("SELECT s.*, p.nombre as usuario, (SELECT COUNT(*) FROM sesiones_items WHERE sesion_id = s.id) as total_items FROM sesiones_inventario s JOIN personas p ON s.usuario_id = p.id ORDER BY s.id DESC");
        $list = [];
        while($r = $res->fetch_assoc()) $list[] = $r;
        echo json_encode(["ok" => true, "data" => $list]);
        break;

    case "eliminar_sesion":
        if (!$es_admin) { echo json_encode(["ok"=>false, "msg"=>"Solo admin"]); break; }
        $data = json_decode(file_get_contents("php://input"), true);
        $sesion_id = (int)($data["sesion_id"] ?? 0);
        
        $db->query("UPDATE equipos SET triaje=NULL, falla=NULL WHERE id IN (SELECT equipo_id FROM sesiones_items WHERE sesion_id=$sesion_id)");
        $db->query("DELETE FROM sesiones_inventario WHERE id=$sesion_id");
        echo json_encode(["ok" => true]);
        break;

    case "terminar_sesion":
        $data = json_decode(file_get_contents("php://input"), true);
        $sesion_id = (int)($data["sesion_id"] ?? 0);
        if ($es_admin) {
            $db->query("UPDATE sesiones_inventario SET estado='CERRADA' WHERE id=$sesion_id");
        } else {
            $db->query("UPDATE sesiones_inventario SET estado='CERRADA' WHERE id=$sesion_id AND usuario_id=$usuario_id");
        }
        echo json_encode(["ok" => true]);
        break;

    case "reabrir_sesion":
        if (!$es_admin) { echo json_encode(["ok"=>false, "msg"=>"Solo admin"]); break; }
        $data = json_decode(file_get_contents("php://input"), true);
        $sesion_id = (int)($data["sesion_id"] ?? 0);
        $db->query("UPDATE sesiones_inventario SET estado='ACTIVA' WHERE id=$sesion_id");
        echo json_encode(["ok" => true]);
        break;

    case "get_sesion_items":
        if (!$es_admin) { echo json_encode(["ok"=>false, "msg"=>"Solo admin"]); break; }
        $sesion_id = (int)($_GET["id"] ?? 0);
        $res = $db->query("SELECT s.id as sesion_item_id, e.*, e.triaje as triaje_asignado, e.falla as falla_asignada FROM sesiones_items s JOIN equipos e ON s.equipo_id = e.id WHERE s.sesion_id = $sesion_id ORDER BY s.id ASC");
        $items = [];
        if ($res) {
            while($r = $res->fetch_assoc()) $items[] = $r;
        }
        echo json_encode(["ok" => true, "data" => $items]);
        break;

    case "editar_sesion_nombre":
        if (!$es_admin) { echo json_encode(["ok"=>false, "msg"=>"Solo admin"]); break; }
        $data = json_decode(file_get_contents("php://input"), true);
        $sesion_id = (int)($data["sesion_id"] ?? 0);
        $nombre = $db->real_escape_string($data["nombre"] ?? "");
        $db->query("UPDATE sesiones_inventario SET nombre='$nombre' WHERE id=$sesion_id");
        echo json_encode(["ok" => true]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
?>
