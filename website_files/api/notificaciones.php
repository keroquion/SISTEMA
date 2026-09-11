<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";
$usuario_id = $_SESSION['user_id'];
$es_admin = ($_SESSION['user_tipo'] === 'admin');
session_write_close();

// SIMULADOR DE CRON PARA ALARMAS (Garantías + SLA de Repuestos)
if ($es_admin) {
    // 1. ALARMAS DE GARANTÍAS DE PROVEEDORES
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
                    $stmt->close();
                }
                $stmt_chk->close();
            }
            $gar_id = (int)$gar['id'];
            $stmt_upd = $db->prepare("UPDATE garantias_proveedor SET ultima_alerta_fecha = CURDATE() WHERE id = ?");
            $stmt_upd->bind_param("i", $gar_id);
            $stmt_upd->execute();
            $stmt_upd->close();
        }
    }

    // 2. MOTOR DE SLA DE REPUESTOS (v1.6.2)
    // Regla: 24h = Recordatorio preventivo | >= 48h = Alerta Crítica recurrente cada HORA
    // Auto-asegurar columna ultima_alerta_sla en pedidos_repuestos
    $chkColSla = $db->query("SHOW COLUMNS FROM pedidos_repuestos LIKE 'ultima_alerta_sla'");
    if ($chkColSla && $chkColSla->num_rows === 0) {
        @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN ultima_alerta_sla DATETIME DEFAULT NULL");
    }

    $sql_sla = "
        SELECT p.id, p.numero_referencia, p.repuesto_nombre, p.estado_envio, p.tracking_number,
               p.fecha_registro, p.ultima_alerta_sla,
               TIMESTAMPDIFF(HOUR, p.fecha_registro, NOW()) as horas_transcurridas,
               TIMESTAMPDIFF(MINUTE, COALESCE(p.ultima_alerta_sla, '2000-01-01'), NOW()) as mins_desde_ultima_alerta
        FROM pedidos_repuestos p
        WHERE p.estado_envio IN ('SOLICITADO') 
           OR (p.estado_envio = 'EN_TRANSITO' AND (p.tracking_number IS NULL OR p.tracking_number = ''))
    ";
    $res_sla = $db->query($sql_sla);

    if ($res_sla && $res_sla->num_rows > 0) {
        require_once "push.php";

        // Obtener lista de administradores destinatarios
        $adminsList = [];
        $resAdm = $db->query("SELECT id FROM personas WHERE tipo = 'admin' AND estado = 'ACTIVO'");
        if ($resAdm) {
            while ($a = $resAdm->fetch_assoc()) $adminsList[] = (int)$a['id'];
        }

        while ($ped = $res_sla->fetch_assoc()) {
            $pedId = (int)$ped['id'];
            $numRef = $ped['numero_referencia'] ?? "Pedido #$pedId";
            $pieza = $ped['repuesto_nombre'] ?? "Repuesto";
            $horas = (int)$ped['horas_transcurridas'];
            $minsUltima = (int)$ped['mins_desde_ultima_alerta'];
            $linkDestino = "pedidos_repuestos.html?pedido_id={$pedId}";

            $enviarAlerta = false;
            $esCritica = false;
            $titAlerta = "";
            $msgAlerta = "";

            // Caso A: Alerta Crítica (>= 48 Horas) -> RECURRENTE CADA HORA
            if ($horas >= 48) {
                if ($minsUltima >= 55) { // Cada ~60 minutos
                    $enviarAlerta = true;
                    $esCritica = true;
                    $titAlerta = "🚨 ALERTA CRÍTICA: Compra urgente de mercancía ({$numRef})";
                    $msgAlerta = "URGENTE: La orden {$numRef} ({$pieza}) lleva {$horas}h sin pedir repuesto. Procede con la compra y sube la foto del comprobante para regularizar el tracking.";
                }
            } 
            // Caso B: Recordatorio Preventivo (24h a 47h) -> DIARIO
            elseif ($horas >= 24) {
                if ($minsUltima >= 1400) { // Una vez al día (~24 horas)
                    $enviarAlerta = true;
                    $esCritica = false;
                    $titAlerta = "⚠️ Recordatorio: Repuesto pendiente ({$numRef})";
                    $msgAlerta = "La orden {$numRef} ({$pieza}) lleva {$horas}h en espera. Ingresa modelo y precio o registra el voucher físico con la cámara móvil.";
                }
            }

            if ($enviarAlerta) {
                // 1. Insertar notificación interna en la campanita para todos los administradores
                foreach ($adminsList as $admId) {
                    $stmtInsNotif = $db->prepare("
                        INSERT INTO notificaciones (usuario_id, titulo, mensaje, link, leido, fecha) 
                        VALUES (?, ?, ?, ?, 0, NOW())
                    ");
                    $stmtInsNotif->bind_param("isss", $admId, $titAlerta, $msgAlerta, $linkDestino);
                    $stmtInsNotif->execute();
                    $stmtInsNotif->close();
                }

                // 2. Disparar Web Push nativo en Chrome / Android si es crítica o primer aviso
                if ($esCritica) {
                    sendPushToAdmins($db, $titAlerta, $msgAlerta, "/{$linkDestino}");
                }

                // 3. Actualizar timestamp de última alerta SLA
                $stmtUpdPed = $db->prepare("UPDATE pedidos_repuestos SET ultima_alerta_sla = NOW() WHERE id = ?");
                $stmtUpdPed->bind_param("i", $pedId);
                $stmtUpdPed->execute();
                $stmtUpdPed->close();
            }
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
