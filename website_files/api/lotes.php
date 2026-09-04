<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
check_api_access('lotes.html'); // Seguridad de Backend
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $estado = isset($_GET["estado"]) ? $db->real_escape_string($_GET["estado"]) : "";
        $where = $estado ? "WHERE estado = '$estado'" : "";
        $result = $db->query("SELECT l.*, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as admin_nombre,
            (SELECT COUNT(*) FROM lote_equipos le WHERE le.lote_id = l.lote_id) as total_items,
            (SELECT COUNT(*) FROM lote_equipos le WHERE le.lote_id = l.lote_id AND le.estado_item = 'COMPLETADO') as items_completados
            FROM lotes l LEFT JOIN personas p ON l.admin_id = p.id $where ORDER BY l.fecha_creacion DESC");
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
        $lid = $db->real_escape_string($_GET["id"] ?? "");
        $result = $db->query("SELECT l.*, CONCAT(COALESCE(p.nombre,''),' ',COALESCE(p.apellido,'')) as admin_nombre FROM lotes l LEFT JOIN personas p ON l.admin_id = p.id WHERE l.lote_id = '$lid' LIMIT 1");
        $lote = $result->fetch_assoc();
        if (!$lote) { echo json_encode(["ok" => false, "msg" => "Lote no encontrado"]); break; }
        $result2 = $db->query("SELECT le.*, e.doc_compra, e.triaje as live_triaje, e.falla as live_falla, CONCAT(COALESCE(t.nombre,''),' ',COALESCE(t.apellido,'')) as tecnico_nombre FROM lote_equipos le LEFT JOIN personas t ON le.tecnico_id = t.id LEFT JOIN equipos e ON le.equipo_codigo = e.codigo WHERE le.lote_id = '$lid' ORDER BY le.fecha_scan DESC");
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
                $doc_compra_auto = $db->real_escape_string($data["autocargar_compra"]);
                $db->query("
                    INSERT INTO lote_equipos (lote_id, equipo_codigo, equipo_serie, marca, modelo, triaje, falla, estado_item)
                    SELECT '$lote_id', codigo, serie, marca, modelo, COALESCE(triaje, 'SIN_FALLA'), falla, 'PENDIENTE'
                    FROM equipos WHERE doc_compra = '$doc_compra_auto'
                ");
                $db->query("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id='$lote_id'), estado='LISTO_PISTOLEO' WHERE lote_id='$lote_id'");
                $db->query("UPDATE equipos SET triaje_inicial = 'SIN_FALLA' WHERE doc_compra = '$doc_compra_auto' AND (triaje_inicial IS NULL OR triaje_inicial = '')");
                $msg .= " y auto-cargado desde compra.";
            }
            echo json_encode(["ok" => true, "lote_id" => $lote_id, "msg" => $msg]);
        }
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "avanzar_estado":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = $db->real_escape_string($data["lote_id"]);
        $nuevo = $db->real_escape_string($data["estado"]);
        $db->query("UPDATE lotes SET estado='$nuevo'" . ($nuevo === 'CERRADO' ? ", fecha_cierre=NOW()" : "") . " WHERE lote_id='$lid'");
        echo json_encode(["ok" => true, "msg" => "Estado actualizado a $nuevo"]);
        break;

    case "agregar_equipo":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = $data["lote_id"];
        $cod = $db->real_escape_string($data["equipo_codigo"] ?? "");
        $ser = $data["equipo_serie"] ?? "";
        $mar = $data["marca"] ?? "";
        $mod = $data["modelo"] ?? "";
        $falla = $db->real_escape_string($data["falla"] ?? "");
        $pieza = $data["pieza"] ?? "";
        $pn = $data["pn"] ?? "";
        $triaje = $db->real_escape_string($data["triaje"] ?? "SIN_FALLA");
        $tec = !empty($data["tecnico_id"]) ? (int)$data["tecnico_id"] : "NULL";

        // Verificar si ya existe (ej. cargado por Lote Completo)
        if ($cod !== "") {
            $check = $db->query("SELECT id FROM lote_equipos WHERE lote_id='$lid' AND equipo_codigo='$cod' LIMIT 1");
            if ($check->num_rows > 0) {
                $id = $check->fetch_assoc()["id"];
                $db->query("UPDATE lote_equipos SET triaje='$triaje', falla='$falla', tecnico_id=$tec WHERE id=$id");
                $db->query("UPDATE equipos SET triaje='$triaje', triaje_inicial = IF(triaje_inicial IS NULL OR triaje_inicial = '' OR triaje_inicial = 'SIN_FALLA', '$triaje', triaje_inicial), falla='$falla' WHERE codigo='$cod'");
                echo json_encode(["ok" => true, "id" => $id, "msg" => "Actualizado (ya existia en el lote)"]);
                break;
            }
        }

        $stmt = $db->prepare("INSERT INTO lote_equipos (lote_id, equipo_codigo, equipo_serie, marca, modelo, falla, pieza, pn, triaje, tecnico_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $t_val = $tec === "NULL" ? null : $tec;
        $stmt->bind_param("sssssssssi", $lid, $cod, $ser, $mar, $mod, $data["falla"], $pieza, $pn, $data["triaje"], $t_val);
        if ($stmt->execute()) {
            $db->query("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id='$lid') WHERE lote_id='$lid'");
            if ($cod !== "") {
                $db->query("UPDATE equipos SET triaje='$triaje', triaje_inicial = IF(triaje_inicial IS NULL OR triaje_inicial = '' OR triaje_inicial = 'SIN_FALLA', '$triaje', triaje_inicial), falla='$falla' WHERE codigo='$cod'");
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
        $lid = $db->real_escape_string($data["lote_id"]);
        $titulo = $db->real_escape_string($data["titulo"]);
        $notas = $db->real_escape_string($data["notas"] ?? "");
        if ($db->query("UPDATE lotes SET titulo='$titulo', notas='$notas' WHERE lote_id='$lid'")) {
            echo json_encode(["ok" => true, "msg" => "Lote editado"]);
        } else {
            echo json_encode(["ok" => false, "msg" => $db->error]);
        }
        break;
    case "editar_repuestos":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = $db->real_escape_string($data["lote_id"]);
        $orden = $db->real_escape_string($data["repuestos_orden"] ?? "");
        $costo = $db->real_escape_string($data["repuestos_costo"] ?? "");
        $estado = $db->real_escape_string($data["repuestos_estado"] ?? "PENDIENTE");

        $db->query("UPDATE lotes SET repuestos_orden='$orden', repuestos_costo='$costo', repuestos_estado='$estado' WHERE lote_id='$lid'");
        echo json_encode(["ok" => true, "msg" => "Seguimiento de repuestos guardado"]);
        break;

    case "editar_item":
        $data = json_decode(file_get_contents("php://input"), true);
        $le_id = (int)$data["lote_equipo_id"];
        $falla = $db->real_escape_string($data["falla"] ?? "");
        $pieza = $db->real_escape_string($data["pieza"] ?? "");
        $pn = $db->real_escape_string($data["pn"] ?? "");
        $triaje = $db->real_escape_string($data["triaje"] ?? "");

        $db->query("UPDATE lote_equipos SET falla='$falla', pieza='$pieza', pn='$pn', triaje='$triaje' WHERE id=$le_id");
        
        // Sincronizar con equipos
        $res = $db->query("SELECT equipo_codigo FROM lote_equipos WHERE id=$le_id");
        if($row = $res->fetch_assoc()) {
            $cod = $row['equipo_codigo'];
            if($cod) $db->query("UPDATE equipos SET triaje='$triaje', falla='$falla' WHERE codigo='$cod'");
        }
        echo json_encode(["ok" => true, "msg" => "Item actualizado"]);
        break;
    case "mover_pendientes":
        $data = json_decode(file_get_contents("php://input"), true);
        $origen = $db->real_escape_string($data["lote_origen"]);
        $destino = $db->real_escape_string($data["lote_destino"]);
        $user_id = $_SESSION['user_id']; // Recuperar ID del usuario que hace el movimiento

        // Guardar auditoria ANTES de moverlos
        $db->query("INSERT INTO log_movimientos_lotes (lote_origen, lote_destino, equipo_codigo, usuario_id) 
                    SELECT '$origen', '$destino', equipo_codigo, $user_id 
                    FROM lote_equipos 
                    WHERE lote_id='$origen' AND triaje='NECESITA_REPUESTO'");

        // Mover los que tienen triaje NECESITA_REPUESTO
        $db->query("UPDATE lote_equipos SET lote_id='$destino' WHERE lote_id='$origen' AND triaje='NECESITA_REPUESTO'");
        
        // Recalcular cantidades
        $db->query("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id='$origen') WHERE lote_id='$origen'");
        $db->query("UPDATE lotes SET cantidad_real = (SELECT COUNT(*) FROM lote_equipos WHERE lote_id='$destino') WHERE lote_id='$destino'");
        
        echo json_encode(["ok" => true, "msg" => "Equipos trasladados con exito"]);
        break;

    case "eliminar":
        $data = json_decode(file_get_contents("php://input"), true);
        $lid = $db->real_escape_string($data["lote_id"]);
        // 1. Resetear triaje en equipos
        $db->query("UPDATE equipos e JOIN lote_equipos le ON e.codigo = le.equipo_codigo SET e.triaje='SIN_FALLA', e.falla=NULL WHERE le.lote_id = '$lid'");
        // 2. Borrar items
        $db->query("DELETE FROM lote_equipos WHERE lote_id = '$lid'");
        // 3. Borrar lote
        if ($db->query("DELETE FROM lotes WHERE lote_id = '$lid'")) {
            echo json_encode(["ok" => true, "msg" => "Lote eliminado"]);
        } else {
            echo json_encode(["ok" => false, "msg" => $db->error]);
        }
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
