<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";
$usuario_id = $_SESSION['user_id'];
$es_admin = ($_SESSION['user_tipo'] === 'admin');

// SIMULADOR DE CRON PARA ALARMAS DE GARANTIAS (Sólo si es admin)
if ($es_admin) {
    // Buscar garantias que esten ENVIADO o EN_PROCESO_PROVEEDOR y que haya pasado el tiempo de alerta_dias
    $sql_alarmas = "SELECT id, numero_garantia, proveedor_nombre, alerta_dias 
                    FROM garantias_proveedor 
                    WHERE estado IN ('ENVIADO', 'EN_PROCESO_PROVEEDOR') 
                    AND DATEDIFF(CURDATE(), ultima_alerta_fecha) >= alerta_dias";
    $res_alarmas = $db->query($sql_alarmas);
    if ($res_alarmas && $res_alarmas->num_rows > 0) {
        while ($gar = $res_alarmas->fetch_assoc()) {
            $msg = "La garantía {$gar['numero_garantia']} ({$gar['proveedor_nombre']}) lleva {$gar['alerta_dias']} días o más sin resolverse.";
            
            // Obtener todos los administradores para enviarles la notificación
            $admins = $db->query("SELECT id FROM personas WHERE tipo = 'admin' AND estado = 'ACTIVO'");
            while ($adm = $admins->fetch_assoc()) {
                $uid = $adm['id'];
                // Evitar duplicados exactos el mismo día
                $like_gar = "%" . $gar['numero_garantia'] . "%";
                $stmt_chk = $db->prepare("SELECT id FROM notificaciones WHERE usuario_id=? AND link='garantias.html' AND titulo LIKE ? AND DATE(fecha) = CURDATE()");
                $stmt_chk->bind_param("is", $uid, $like_gar);
                $stmt_chk->execute();
                $chk = $stmt_chk->get_result();
                if ($chk->num_rows == 0) {
                    $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, link) VALUES (?, ?, ?, ?)");
                    $tit = "Garantía Pendiente: " . $gar['numero_garantia'];
                    $link = "garantias.html";
                    $stmt->bind_param("isss", $uid, $tit, $msg, $link);
                    $stmt->execute();
                }
            }
            // Actualizar la última fecha de alerta para que vuelva a sonar en X días más
            $gar_id = (int)$gar['id'];
            $stmt_upd = $db->prepare("UPDATE garantias_proveedor SET ultima_alerta_fecha = CURDATE() WHERE id = ?");
            $stmt_upd->bind_param("i", $gar_id);
            $stmt_upd->execute();
        }
    }
}

switch ($action) {
    case "list":
        $stmt = $db->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $notis = [];
        while ($row = $res->fetch_assoc()) $notis[] = $row;
        echo json_encode(["ok" => true, "data" => $notis]);
        break;

    case "marcar_leida":
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data["id"] ?? 0);
        $stmt = $db->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $id, $usuario_id);
        $stmt->execute();
        echo json_encode(["ok" => true]);
        break;

    case "marcar_todas_leidas":
        $stmt = $db->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ?");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        echo json_encode(["ok" => true]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Acción no válida"]);
}
$db->close();
?>
