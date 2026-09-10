<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header('HTTP/1.1 401 Unauthorized'); 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); 
    exit; 
}
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_nombre'] ?? 'Sistema';
session_write_close();

header('Content-Type: application/json; charset=utf-8');
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

// Helper: Garantizar existencia de la tabla pedidos_repuestos
$ensureTablePedidosRepuestos = function() use ($db) {
    $sql = "
        CREATE TABLE IF NOT EXISTS pedidos_repuestos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            origen_tipo ENUM('CLIENTE', 'GARANTIA', 'LOTE') NOT NULL DEFAULT 'CLIENTE',
            ticket_id INT NULL,
            lote_equipo_id INT NULL,
            numero_referencia VARCHAR(50) NOT NULL,
            cliente_nombre VARCHAR(150) NULL,
            cliente_telefono VARCHAR(50) NULL,
            equipo_descripcion VARCHAR(255) NULL,
            repuesto_nombre VARCHAR(255) NOT NULL,
            repuesto_pn VARCHAR(100) NULL,
            proveedor_nombre VARCHAR(100) NULL,
            courier VARCHAR(100) NULL,
            tracking_number VARCHAR(100) NULL,
            fecha_compra DATE NULL,
            fecha_estimada_llegada DATE NULL,
            costo_compra DECIMAL(10,2) DEFAULT 0.00,
            precio_cliente DECIMAL(10,2) DEFAULT 0.00,
            es_garantia TINYINT(1) DEFAULT 0,
            estado_envio ENUM('SOLICITADO', 'EN_TRANSITO', 'RECIBIDO_EN_TALLER', 'INSTALADO') DEFAULT 'SOLICITADO',
            notas_envio TEXT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (ticket_id),
            INDEX (lote_equipo_id),
            INDEX (numero_referencia),
            INDEX (estado_envio),
            INDEX (origen_tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $db->query($sql);
};

switch ($action) {
    // -------------------------------------------------------------
    // GESTIÓN Y TRAZABILIDAD DE COMPRAS Y ENVÍOS (v1.5.0)
    // -------------------------------------------------------------
    case "tracking_pedidos":
        $ensureTablePedidosRepuestos();

        // 1. Auto-sincronizar órdenes de soporte_tecnico pendientes de repuesto
        $sqlSyncSt = "
            SELECT st.id as ticket_id, st.numero_atencion, st.equipo_descripcion,
                   COALESCE(NULLIF(st.repuesto_nombre, ''), 'Repuesto no especificado') as repuesto_nombre,
                   st.repuesto_pn, st.repuesto_precio, st.en_garantia,
                   st.repuesto_fecha_llegada_aprox,
                   CONCAT(c.nombre, ' ', COALESCE(c.apellido, '')) as cliente_nombre,
                   c.telefono as cliente_telefono
            FROM soporte_tecnico st
            LEFT JOIN personas c ON st.cliente_id = c.id
            WHERE (st.estado = 'ESPERANDO_REPUESTO' OR (st.repuesto_nombre IS NOT NULL AND st.repuesto_nombre != '' AND st.estado NOT IN ('ENTREGADO', 'CANCELADO')))
            AND st.id NOT IN (SELECT ticket_id FROM pedidos_repuestos WHERE ticket_id IS NOT NULL)
        ";
        $resSyncSt = $db->query($sqlSyncSt);
        if ($resSyncSt) {
            $stmtInsSt = $db->prepare("
                INSERT INTO pedidos_repuestos 
                (origen_tipo, ticket_id, numero_referencia, cliente_nombre, cliente_telefono, equipo_descripcion, repuesto_nombre, repuesto_pn, fecha_estimada_llegada, precio_cliente, es_garantia, estado_envio)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SOLICITADO')
            ");
            while ($stRow = $resSyncSt->fetch_assoc()) {
                $origen = (!empty($stRow['en_garantia']) || (float)$stRow['repuesto_precio'] == 0) ? 'GARANTIA' : 'CLIENTE';
                $esGar = ($origen === 'GARANTIA') ? 1 : 0;
                $precioCli = $esGar ? 0.00 : (float)($stRow['repuesto_precio'] ?? 0);
                $fechaEst = !empty($stRow['repuesto_fecha_llegada_aprox']) ? $stRow['repuesto_fecha_llegada_aprox'] : null;
                $tkId = (int)$stRow['ticket_id'];
                $numRef = $stRow['numero_atencion'] ?? 'ST-PENDIENTE';
                $cliNom = trim($stRow['cliente_nombre'] ?? 'Cliente General');
                $cliTel = $stRow['cliente_telefono'] ?? '';
                $eqDesc = $stRow['equipo_descripcion'] ?? 'Laptop de Cliente';
                $repNom = $stRow['repuesto_nombre'];
                $repPn = $stRow['repuesto_pn'] ?? '';

                $stmtInsSt->bind_param("sissssssdss", $origen, $tkId, $numRef, $cliNom, $cliTel, $eqDesc, $repNom, $repPn, $fechaEst, $precioCli, $esGar);
                $stmtInsSt->execute();
            }
            $stmtInsSt->close();
        }

        // 2. Auto-sincronizar laptops de lotes masivos con triaje 'NECESITA_REPUESTO'
        $sqlSyncLe = "
            SELECT le.id as lote_equipo_id, le.lote_id, le.equipo_codigo, le.marca, le.modelo,
                   le.pieza, le.pn, le.falla, l.titulo as lote_titulo
            FROM lote_equipos le
            LEFT JOIN lotes l ON le.lote_id = l.lote_id
            WHERE le.triaje = 'NECESITA_REPUESTO'
            AND le.id NOT IN (SELECT lote_equipo_id FROM pedidos_repuestos WHERE lote_equipo_id IS NOT NULL)
        ";
        $resSyncLe = $db->query($sqlSyncLe);
        if ($resSyncLe) {
            $stmtInsLe = $db->prepare("
                INSERT INTO pedidos_repuestos 
                (origen_tipo, lote_equipo_id, numero_referencia, cliente_nombre, equipo_descripcion, repuesto_nombre, repuesto_pn, notas_envio, estado_envio)
                VALUES ('LOTE', ?, ?, 'Interno Petulap / Taller', ?, ?, ?, ?, 'SOLICITADO')
            ");
            while ($leRow = $resSyncLe->fetch_assoc()) {
                $leId = (int)$leRow['lote_equipo_id'];
                $numRef = ($leRow['lote_id'] ?? 'LOT') . ' / ' . ($leRow['equipo_codigo'] ?? 'S/C');
                $piezaNom = !empty($leRow['pieza']) ? trim($leRow['pieza']) : (!empty($leRow['falla']) ? 'Repuesto: ' . trim($leRow['falla']) : 'Pieza de Lote');
                $eqDesc = trim(($leRow['marca'] ?? '') . ' ' . ($leRow['modelo'] ?? ''));
                if (empty($eqDesc)) $eqDesc = 'Laptop de Lote ' . ($leRow['lote_id'] ?? '');
                $repPn = $leRow['pn'] ?? '';
                $notasLe = 'Lote: ' . ($leRow['lote_id'] ?? '') . ' | Falla: ' . ($leRow['falla'] ?? 'Requiere repuesto');

                $stmtInsLe->bind_param("isssss", $leId, $numRef, $eqDesc, $piezaNom, $repPn, $notasLe);
                $stmtInsLe->execute();
            }
            $stmtInsLe->close();
        }

        // 3. Consultar todos los pedidos de repuestos calculando SLA
        $sql = "
            SELECT pr.*,
                   DATEDIFF(pr.fecha_estimada_llegada, CURDATE()) as dias_restantes
            FROM pedidos_repuestos pr
            ORDER BY 
                FIELD(pr.estado_envio, 'SOLICITADO', 'EN_TRANSITO', 'RECIBIDO_EN_TALLER', 'INSTALADO'),
                CASE WHEN pr.fecha_estimada_llegada IS NULL THEN 1 ELSE 0 END,
                pr.fecha_estimada_llegada ASC,
                pr.id DESC
        ";
        $res = $db->query($sql);
        $pedidos = [];

        $kpis = [
            "total_activos" => 0,
            "en_transito" => 0,
            "garantias" => 0,
            "retrasados" => 0,
            "lotes_internos" => 0
        ];

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $estadoEnvio = $row['estado_envio'] ?? 'SOLICITADO';
                $dias = ($row['fecha_estimada_llegada'] !== null) ? (int)$row['dias_restantes'] : null;

                // Cálculo del semáforo SLA de envíos
                if ($estadoEnvio === 'RECIBIDO_EN_TALLER' || $estadoEnvio === 'INSTALADO') {
                    $slaStatus = 'RECIBIDO';
                    $slaLabel = ($estadoEnvio === 'INSTALADO') ? 'Instalado en Equipo' : 'En Taller / Recibido';
                    $slaColor = 'emerald';
                } elseif ($dias === null) {
                    $slaStatus = 'SIN_FECHA';
                    $slaLabel = 'Sin fecha asignada';
                    $slaColor = 'slate';
                } elseif ($dias < 0) {
                    $slaStatus = 'RETRASADO';
                    $diasVencidos = abs($dias);
                    $slaLabel = ($diasVencidos === 1) ? 'Retrasado hace 1 día' : "Retrasado hace {$diasVencidos} días";
                    $slaColor = 'red';
                } elseif ($dias === 0) {
                    $slaStatus = 'LLEGA_PRONTO';
                    $slaLabel = 'Llega Hoy';
                    $slaColor = 'amber';
                } elseif ($dias === 1) {
                    $slaStatus = 'LLEGA_PRONTO';
                    $slaLabel = 'Llega Mañana';
                    $slaColor = 'amber';
                } elseif ($dias === 2) {
                    $slaStatus = 'LLEGA_PRONTO';
                    $slaLabel = 'Llega en 2 días';
                    $slaColor = 'amber';
                } else {
                    $slaStatus = 'A_TIEMPO';
                    $fLegible = date('d/m', strtotime($row['fecha_estimada_llegada']));
                    $slaLabel = "Llega en {$dias} días ({$fLegible})";
                    $slaColor = 'green';
                }

                $row['sla_status'] = $slaStatus;
                $row['sla_label'] = $slaLabel;
                $row['sla_color'] = $slaColor;

                // Contadores KPI
                $esActivo = in_array($estadoEnvio, ['SOLICITADO', 'EN_TRANSITO']);
                if ($esActivo) {
                    $kpis['total_activos']++;
                }
                if ($estadoEnvio === 'EN_TRANSITO' || (!empty($row['tracking_number']) && $estadoEnvio === 'SOLICITADO')) {
                    $kpis['en_transito']++;
                }
                if (!empty($row['es_garantia']) || $row['origen_tipo'] === 'GARANTIA') {
                    $kpis['garantias']++;
                }
                if ($slaStatus === 'RETRASADO' && $esActivo) {
                    $kpis['retrasados']++;
                }
                if ($row['origen_tipo'] === 'LOTE') {
                    $kpis['lotes_internos']++;
                }

                $pedidos[] = $row;
            }
        }

        echo json_encode(["ok" => true, "kpis" => $kpis, "data" => $pedidos]);
        break;

    case "guardar_tracking":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $prov = trim($data["proveedor_nombre"] ?? "");
        $courier = trim($data["courier"] ?? "");
        $tracking = trim($data["tracking_number"] ?? "");
        $fCompra = !empty($data["fecha_compra"]) ? $data["fecha_compra"] : null;
        $fLlegada = !empty($data["fecha_estimada_llegada"]) ? $data["fecha_estimada_llegada"] : null;
        $costo = (float)($data["costo_compra"] ?? 0);
        $precio = (float)($data["precio_cliente"] ?? 0);
        $esGar = !empty($data["es_garantia"]) ? 1 : 0;
        if ($esGar) $precio = 0.00;

        $estadoEnvio = trim($data["estado_envio"] ?? "SOLICITADO");
        if (!in_array($estadoEnvio, ['SOLICITADO', 'EN_TRANSITO', 'RECIBIDO_EN_TALLER', 'INSTALADO'])) {
            $estadoEnvio = 'SOLICITADO';
        }
        // Si tiene número de seguimiento y sigue en solicitado, avanzar lógicamente a EN_TRANSITO
        if (!empty($tracking) && $estadoEnvio === 'SOLICITADO') {
            $estadoEnvio = 'EN_TRANSITO';
        }

        $notas = trim($data["notas_envio"] ?? "");

        $stmt = $db->prepare("
            UPDATE pedidos_repuestos SET
                proveedor_nombre = ?,
                courier = ?,
                tracking_number = ?,
                fecha_compra = ?,
                fecha_estimada_llegada = ?,
                costo_compra = ?,
                precio_cliente = ?,
                es_garantia = ?,
                estado_envio = ?,
                notas_envio = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssssddissi", $prov, $courier, $tracking, $fCompra, $fLlegada, $costo, $precio, $esGar, $estadoEnvio, $notas, $id);
        
        if ($stmt->execute()) {
            // Sincronizar ticket de soporte si aplica
            $stmtTk = $db->prepare("SELECT ticket_id FROM pedidos_repuestos WHERE id = ?");
            $stmtTk->bind_param("i", $id);
            $stmtTk->execute();
            $resTk = $stmtTk->get_result()->fetch_assoc();
            $stmtTk->close();

            if (!empty($resTk['ticket_id'])) {
                $tkId = (int)$resTk['ticket_id'];
                $stmtUpdSt = $db->prepare("
                    UPDATE soporte_tecnico SET
                        repuesto_fecha_llegada_aprox = ?,
                        repuesto_precio = ?,
                        en_garantia = ?
                    WHERE id = ?
                ");
                $stmtUpdSt->bind_param("sdii", $fLlegada, $precio, $esGar, $tkId);
                $stmtUpdSt->execute();
                $stmtUpdSt->close();
            }

            echo json_encode(["ok" => true, "msg" => "Tracking actualizado exitosamente"]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al actualizar: " . $db->error]);
        }
        $stmt->close();
        break;

    case "marcar_recibido":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $stmt = $db->prepare("
            UPDATE pedidos_repuestos 
            SET estado_envio = 'RECIBIDO_EN_TALLER' 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Consultar datos para sincronización con soporte_tecnico
            $stmtInfo = $db->prepare("SELECT ticket_id, lote_equipo_id, numero_referencia, repuesto_nombre FROM pedidos_repuestos WHERE id = ?");
            $stmtInfo->bind_param("i", $id);
            $stmtInfo->execute();
            $info = $stmtInfo->get_result()->fetch_assoc();
            $stmtInfo->close();

            if (!empty($info['ticket_id'])) {
                $tkId = (int)$info['ticket_id'];
                
                // Actualizar estado del ticket en soporte_tecnico
                $stmtUpdSt = $db->prepare("
                    UPDATE soporte_tecnico 
                    SET estado = 'EN_REPARACION', fecha_llegada_repuesto = NOW() 
                    WHERE id = ? AND estado = 'ESPERANDO_REPUESTO'
                ");
                $stmtUpdSt->bind_param("i", $tkId);
                $stmtUpdSt->execute();
                $stmtUpdSt->close();

                // Registrar en auditoría historial_cambios
                $stmtHist = $db->prepare("
                    INSERT INTO historial_cambios 
                    (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre)
                    VALUES ('soporte_tecnico', ?, ?, 'estado', 'ESPERANDO_REPUESTO', 'EN_REPARACION', ?)
                ");
                $numRef = $info['numero_referencia'] ?? "Ticket #$tkId";
                $stmtHist->bind_param("iss", $tkId, $numRef, $userName);
                $stmtHist->execute();
                $stmtHist->close();

                // Alerta en notificaciones internas
                $stmtNotif = $db->prepare("
                    INSERT INTO notificaciones (usuario_id, titulo, mensaje, link)
                    SELECT tecnico_id, ?, ?, 'soporte.html'
                    FROM soporte_tecnico WHERE id = ? AND tecnico_id IS NOT NULL
                ");
                if ($stmtNotif) {
                    $tituloNotif = "Pieza en Taller: " . ($info['repuesto_nombre'] ?? 'Repuesto');
                    $msgNotif = "El repuesto de la orden {$numRef} fue recibido en taller y pasó a EN REPARACIÓN.";
                    $stmtNotif->bind_param("ssi", $tituloNotif, $msgNotif, $tkId);
                    $stmtNotif->execute();
                    $stmtNotif->close();
                }
            } elseif (!empty($info['lote_equipo_id'])) {
                $leId = (int)$info['lote_equipo_id'];
                $stmtLe = $db->prepare("UPDATE lote_equipos SET triaje = 'FALLA_MENOR', estado_item = 'EN_PROCESO' WHERE id = ?");
                $stmtLe->bind_param("i", $leId);
                $stmtLe->execute();
                $stmtLe->close();
            }

            echo json_encode(["ok" => true, "msg" => "Repuesto recibido en taller. Orden actualizada."]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al marcar recepción: " . $db->error]);
        }
        $stmt->close();
        break;

    // -------------------------------------------------------------
    // ACCIONES PREVIAS DEL CATÁLOGO DE REPUESTOS
    // -------------------------------------------------------------
    case "list":
        $result = $db->query("SELECT * FROM repuestos ORDER BY nombre ASC");
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $rows[] = $row;
        }
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
        if ($result) {
            while ($row = $result->fetch_assoc()) $rows[] = $row;
        }
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $stmt = $db->prepare("INSERT INTO repuestos (nombre, pn, stock, precio, notas) VALUES (?,?,?,?,?)");
        $n = $data["nombre"] ?? "";
        $pn = $data["pn"] ?? "";
        $s = (int)($data["stock"] ?? 0);
        $p = (float)($data["precio"] ?? 0);
        $nt = $data["notas"] ?? "";
        $stmt->bind_param("ssids", $n, $pn, $s, $p, $nt);
        if ($stmt->execute()) echo json_encode(["ok" => true, "id" => $db->insert_id]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "actualizar":
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        $stmt = $db->prepare("UPDATE repuestos SET nombre=?, pn=?, stock=?, precio=?, notas=? WHERE id=?");
        $n = $data["nombre"] ?? "";
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
        $stmt = $db->prepare("DELETE FROM repuestos WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(["ok" => true]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion invalida"]);
}
$db->close();
?>
