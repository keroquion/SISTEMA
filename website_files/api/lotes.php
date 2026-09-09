<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
check_api_access('lotes.html'); // Seguridad de Backend
session_write_close();
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $estado = isset($_GET["estado"]) ? trim($_GET["estado"]) : "";
        $where = $estado !== "" ? "WHERE estado = ?" : "";
        $sql = "SELECT l.*, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as admin_nombre,
            (SELECT COUNT(*) FROM lote_equipos le WHERE le.lote_id = l.lote_id) as total_items,
            (SELECT COUNT(*) FROM lote_equipos le WHERE le.lote_id = l.lote_id AND le.estado_item = 'COMPLETADO') as items_completados
            FROM lotes l LEFT JOIN personas p ON l.admin_id = p.id $where ORDER BY l.fecha_creacion DESC";
        if ($estado !== "") {
            $stmt = $db->prepare($sql);
            $stmt->bind_param("s", $estado);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $db->query($sql);
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;
    case "compras_disponibles":
        $dias = isset($_GET["dias"]) ? (int)$_GET["dias"] : 60;
        $result = $db->query("
            SELECT doc_compra, DATE(fec_compra) as fecha, COUNT(*) as cantidad, MAX(observacion) as obs
            FROM equipos 
            WHERE doc_compra IS NOT NULL AND doc_compra != '' AND fec_compra >= DATE_SUB(CURDATE(), INTERVAL $dias DAY)
            GROUP BY doc_compra, DATE(fec_compra) 
            ORDER BY fecha DESC
        ");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "ver":
        $lid = trim($_GET["id"] ?? "");
        $stmt1 = $db->prepare("SELECT l.*, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as admin_nombre FROM lotes l LEFT JOIN personas p ON l.admin_id = p.id WHERE l.lote_id = ? LIMIT 1");
        $stmt1->bind_param("s", $lid);
        $stmt1->execute();
        $result = $stmt1->get_result();
        $lote = $result->fetch_assoc();
        if (!$lote) { echo json_encode(["ok" => false, "msg" => "Lote no encontrado"]); break; }
        $stmt2 = $db->prepare("SELECT le.*, e.doc_compra, e.triaje as live_triaje, e.falla as live_falla, CONCAT(COALESCE(t.nombre,''),' ',COALESCE(t.apellido,'')) as tecnico_nombre FROM lote_equipos le LEFT JOIN personas t ON le.tecnico_id = t.id LEFT JOIN equipos e ON le.equipo_codigo = e.codigo WHERE le.lote_id = ? ORDER BY le.fecha_scan DESC");
        $stmt2->bind_param("s", $lid);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $items = [];
        while ($row = $result2->fetch_assoc()) {
            if (isset($row['live_triaje'])) $row['triaje'] = $row['live_triaje'];
            if (isset($row['live_falla'])) $row['falla'] = $row['live_falla'];
            $items[] = $row;
        }
        $lote["items"] = $items;
        // Agregar valores por defecto para repuestos si son null
        $lote["repuestos_orden"] = $lote["repuestos_orden"] ?? "";
        $lote["repuestos_costo"] = $lote["repuestos_costo"] ?? "";
        $lote["repuestos_estado"] = $lote["repuestos_estado"] ?? "PENDIENTE";
        echo json_encode(["ok" => true, "data" => $lote]);
        break;

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        $year = date("Y");
        $res = $db->query("SELECT COUNT(*) as c FROM lotes WHERE lote_id LIKE 'LOT-$year-%'");
        $row = $res->fetch_assoc();
        $seq = str_pad((int)$row["c"] + 1, 3, "0", STR_PAD_LEFT);
        $lote_id = "LOT-$year-$seq";

        $stmt = $db->prepare("INSERT INTO lotes (lote_id, tipo, titulo, proveedor_nombre, proveedor_ruc, doc_compra, cantidad_declarada, cargadores_funcionales, admin_id, notas) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $tipo = $data["tipo"];
        $titulo = $data["titulo"];
        $prov = $data["proveedor_nombre"] ?? "";
        $ruc = $data["proveedor_ruc"] ?? "";
        $doc = $data["doc_compra"] ?? "";
        $cant = (int)($data["cantidad_declarada"] ?? 0);
        $carg = (int)($data["cargadores_funcionales"] ?? 0);
        $admin = !empty($data["admin_id"]) ? (int)$data["admin_id"] : null;
        $notas = $data["notas"] ?? "";
        $stmt->bind_param("ssssssiisi", $lote_id, $tipo, $titulo, $prov, $ruc, $doc, $cant, $carg, $admin, $notas);
        if ($stmt->execute()) {
            $msg = "Lote $lote_id creado";
            if (!empty($data["autocargar_compra"])) {
                $doc_compra_auto = trim($data["autocargar_compra"]);
                $stmt_auto1 = $db->prepare("
                    INSERT INTO lote_equipos (lote_id, equipo_codigo, equipo_serie, marca, modelo, triaje, falla, estado_item)
                    SELECT ?, codigo, serie, marca, modelo, COALESCE(triaje, 'SIN_FALLA'), falla, 'PENDIENTE'
                    FROM equipos WHERE doc_compra = ?
                ");
                $stmt_auto1->bind_param("ss", $lote_id, $doc_compra_auto);
                $stmt_auto1->execute();

                $stmt_auto2 = $db->prepare("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id=?), estado='LISTO_PISTOLEO' WHERE lote_id=?");
                $stmt_auto2->bind_param("ss", $lote_id, $lote_id);
                $stmt_auto2->execute();

                $stmt_auto3 = $db->prepare("UPDATE equipos SET triaje_inicial = 'SIN_FALLA' WHERE doc_compra = ? AND (triaje_inicial IS NULL OR triaje_inicial = '')");
                $stmt_auto3->bind_param("s", $doc_compra_auto);
                $stmt_auto3->execute();
                $msg .= " y auto-cargado desde compra.";
            }
            echo json_encode(["ok" => true, "lote_id" => $lote_id, "msg" => $msg]);
        }
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "avanzar_estado":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = trim($data["lote_id"] ?? "");
        $nuevo = trim($data["estado"] ?? "");
        $sql_st = "UPDATE lotes SET estado=?" . ($nuevo === 'CERRADO' ? ", fecha_cierre=NOW()" : "") . " WHERE lote_id=?";
        $stmt_st = $db->prepare($sql_st);
        $stmt_st->bind_param("ss", $nuevo, $lid);
        $stmt_st->execute();
        echo json_encode(["ok" => true, "msg" => "Estado actualizado a $nuevo"]);
        break;

    case "agregar_equipo":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = trim($data["lote_id"] ?? "");
        $cod = trim($data["equipo_codigo"] ?? "");
        $ser = $data["equipo_serie"] ?? "";
        $mar = $data["marca"] ?? "";
        $mod = $data["modelo"] ?? "";
        $falla = $data["falla"] ?? "";
        $pieza = $data["pieza"] ?? "";
        $pn = $data["pn"] ?? "";
        $triaje = trim($data["triaje"] ?? "SIN_FALLA");
        $tec = !empty($data["tecnico_id"]) ? (int)$data["tecnico_id"] : null;

        // Verificar si ya existe (ej. cargado por Lote Completo)
        if ($cod !== "") {
            $stmt_chk = $db->prepare("SELECT id FROM lote_equipos WHERE lote_id=? AND equipo_codigo=? LIMIT 1");
            $stmt_chk->bind_param("ss", $lid, $cod);
            $stmt_chk->execute();
            $check = $stmt_chk->get_result();
            if ($check->num_rows > 0) {
                $id = (int)$check->fetch_assoc()["id"];
                $stmt_upd_le = $db->prepare("UPDATE lote_equipos SET triaje=?, falla=?, tecnico_id=? WHERE id=?");
                $stmt_upd_le->bind_param("ssii", $triaje, $falla, $tec, $id);
                $stmt_upd_le->execute();

                $stmt_upd_eq = $db->prepare("UPDATE equipos SET triaje=?, triaje_inicial = IF(triaje_inicial IS NULL OR triaje_inicial = '' OR triaje_inicial = 'SIN_FALLA', ?, triaje_inicial), falla=? WHERE codigo=?");
                $stmt_upd_eq->bind_param("ssss", $triaje, $triaje, $falla, $cod);
                $stmt_upd_eq->execute();

                echo json_encode(["ok" => true, "id" => $id, "msg" => "Actualizado (ya existia en el lote)"]);
                break;
            }
        }

        $stmt = $db->prepare("INSERT INTO lote_equipos (lote_id, equipo_codigo, equipo_serie, marca, modelo, falla, pieza, pn, triaje, tecnico_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssssi", $lid, $cod, $ser, $mar, $mod, $falla, $pieza, $pn, $triaje, $tec);
        if ($stmt->execute()) {
            $stmt_cnt = $db->prepare("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id=?) WHERE lote_id=?");
            $stmt_cnt->bind_param("ss", $lid, $lid);
            $stmt_cnt->execute();

            if ($cod !== "") {
                $stmt_eq_sync = $db->prepare("UPDATE equipos SET triaje=?, triaje_inicial = IF(triaje_inicial IS NULL OR triaje_inicial = '' OR triaje_inicial = 'SIN_FALLA', ?, triaje_inicial), falla=? WHERE codigo=?");
                $stmt_eq_sync->bind_param("ssss", $triaje, $triaje, $falla, $cod);
                $stmt_eq_sync->execute();
            }
            echo json_encode(["ok" => true, "id" => $db->insert_id, "msg" => "Equipo agregado"]);
        } else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "actualizar_item":
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)$data["id"];
        $fields = []; $values = []; $types = "";
        $allowed = ["estado_item","triaje","falla","pieza","pn","tecnico_id","notas"];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f=?"; $types .= "s"; $values[] = $data[$f]; }
        }
        if (!$fields) { echo json_encode(["ok" => false, "msg" => "Nada"]); break; }
        $types .= "i"; $values[] = $id;
        $stmt = $db->prepare("UPDATE lote_equipos SET " . implode(",", $fields) . " WHERE id=?");
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) echo json_encode(["ok" => true]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "editar":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = trim($data["lote_id"] ?? "");
        $titulo = trim($data["titulo"] ?? "");
        $notas = trim($data["notas"] ?? "");
        $stmt_ed = $db->prepare("UPDATE lotes SET titulo=?, notas=? WHERE lote_id=?");
        $stmt_ed->bind_param("sss", $titulo, $notas, $lid);
        if ($stmt_ed->execute()) {
            echo json_encode(["ok" => true, "msg" => "Lote editado"]);
        } else {
            echo json_encode(["ok" => false, "msg" => $db->error]);
        }
        break;
    case "editar_repuestos":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = trim($data["lote_id"] ?? "");
        $orden = trim($data["repuestos_orden"] ?? "");
        $costo = trim($data["repuestos_costo"] ?? "");
        $estado = trim($data["repuestos_estado"] ?? "PENDIENTE");

        $stmt_rep = $db->prepare("UPDATE lotes SET repuestos_orden=?, repuestos_costo=?, repuestos_estado=? WHERE lote_id=?");
        $stmt_rep->bind_param("ssss", $orden, $costo, $estado, $lid);
        $stmt_rep->execute();
        echo json_encode(["ok" => true, "msg" => "Seguimiento de repuestos guardado"]);
        break;

    case "editar_item":
        $data = json_decode(file_get_contents("php://input"), true);
        $le_id = (int)($data["lote_equipo_id"] ?? 0);
        $falla = trim($data["falla"] ?? "");
        $pieza = trim($data["pieza"] ?? "");
        $pn = trim($data["pn"] ?? "");
        $triaje = trim($data["triaje"] ?? "");

        $stmt_ei = $db->prepare("UPDATE lote_equipos SET falla=?, pieza=?, pn=?, triaje=? WHERE id=?");
        $stmt_ei->bind_param("ssssi", $falla, $pieza, $pn, $triaje, $le_id);
        $stmt_ei->execute();
        
        // Sincronizar con equipos
        $stmt_gc = $db->prepare("SELECT equipo_codigo FROM lote_equipos WHERE id=?");
        $stmt_gc->bind_param("i", $le_id);
        $stmt_gc->execute();
        $res = $stmt_gc->get_result();
        if ($row = $res->fetch_assoc()) {
            $cod = $row['equipo_codigo'];
            if ($cod) {
                $stmt_ue = $db->prepare("UPDATE equipos SET triaje=?, falla=? WHERE codigo=?");
                $stmt_ue->bind_param("sss", $triaje, $falla, $cod);
                $stmt_ue->execute();
            }
        }
        echo json_encode(["ok" => true, "msg" => "Item actualizado"]);
        break;
    case "mover_pendientes":
        $data = json_decode(file_get_contents("php://input"), true);
        $origen = trim($data["lote_origen"] ?? "");
        $destino = trim($data["lote_destino"] ?? "");
        $user_id = (int)($_SESSION['user_id'] ?? 0);

        // Guardar auditoria ANTES de moverlos
        $stmt_log = $db->prepare("INSERT INTO log_movimientos_lotes (lote_origen, lote_destino, equipo_codigo, usuario_id) 
                    SELECT ?, ?, equipo_codigo, ? 
                    FROM lote_equipos 
                    WHERE lote_id=? AND triaje='NECESITA_REPUESTO'");
        $stmt_log->bind_param("ssis", $origen, $destino, $user_id, $origen);
        $stmt_log->execute();

        // Mover los que tienen triaje NECESITA_REPUESTO
        $stmt_mov = $db->prepare("UPDATE lote_equipos SET lote_id=? WHERE lote_id=? AND triaje='NECESITA_REPUESTO'");
        $stmt_mov->bind_param("ss", $destino, $origen);
        $stmt_mov->execute();
        
        // Recalcular cantidades
        $stmt_r1 = $db->prepare("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id=?) WHERE lote_id=?");
        $stmt_r1->bind_param("ss", $origen, $origen);
        $stmt_r1->execute();

        $stmt_r2 = $db->prepare("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id=?) WHERE lote_id=?");
        $stmt_r2->bind_param("ss", $destino, $destino);
        $stmt_r2->execute();
        
        echo json_encode(["ok" => true, "msg" => "Equipos trasladados con exito"]);
        break;

    case "eliminar":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = trim($data["lote_id"] ?? "");
        // 1. Resetear triaje en equipos
        $stmt_del1 = $db->prepare("UPDATE equipos e JOIN lote_equipos le ON e.codigo = le.equipo_codigo SET e.triaje='SIN_FALLA', e.falla=NULL WHERE le.lote_id = ?");
        $stmt_del1->bind_param("s", $lid);
        $stmt_del1->execute();

        // 2. Borrar items
        $stmt_del2 = $db->prepare("DELETE FROM lote_equipos WHERE lote_id = ?");
        $stmt_del2->bind_param("s", $lid);
        $stmt_del2->execute();

        // 3. Borrar lote
        $stmt_del3 = $db->prepare("DELETE FROM lotes WHERE lote_id = ?");
        $stmt_del3->bind_param("s", $lid);
        if ($stmt_del3->execute()) {
            echo json_encode(["ok" => true, "msg" => "Lote eliminado"]);
        } else {
            echo json_encode(["ok" => false, "msg" => $db->error]);
        }
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
