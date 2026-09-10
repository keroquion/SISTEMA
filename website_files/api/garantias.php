<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
session_write_close();
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $estado = trim($_GET["estado"] ?? "");
        $baseSql = "SELECT gp.*, l.titulo as lote_titulo, l.tipo as lote_tipo,
                (SELECT COUNT(*) FROM garantia_items gi WHERE gi.garantia_id = gp.id) as total_items
                FROM garantias_proveedor gp
                LEFT JOIN lotes l ON gp.lote_id = l.lote_id";
        if ($estado !== "") {
            $stmt = $db->prepare("$baseSql WHERE gp.estado = ? ORDER BY gp.fecha_creacion DESC");
            $stmt->bind_param("s", $estado);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $db->query("$baseSql ORDER BY gp.fecha_creacion DESC");
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "ver":
        $id = (int)($_GET["id"] ?? 0);
        $stmt_gar = $db->prepare("SELECT gp.*, l.titulo as lote_titulo FROM garantias_proveedor gp LEFT JOIN lotes l ON gp.lote_id = l.lote_id WHERE gp.id=? LIMIT 1");
        $stmt_gar->bind_param("i", $id);
        $stmt_gar->execute();
        $gar = $stmt_gar->get_result()->fetch_assoc();
        if (!$gar) { echo json_encode(["ok" => false, "msg" => "No encontrado"]); break; }
        $stmt_items = $db->prepare("SELECT * FROM garantia_items WHERE garantia_id=? ORDER BY tipo_item, marca");
        $stmt_items->bind_param("i", $id);
        $stmt_items->execute();
        $items = $stmt_items->get_result();
        $gar["items"] = [];
        while ($row = $items->fetch_assoc()) $gar["items"][] = $row;
        echo json_encode(["ok" => true, "data" => $gar]);
        break;

    // Crear garantia desde lote NACIONAL (con sus items fallados)
    case "crear_desde_lote":
        $data = json_decode(file_get_contents("php://input"), true);
        $lote_id = trim($data["lote_id"] ?? "");

        // Verificar que sea lote NACIONAL
        $stmt_lote = $db->prepare("SELECT * FROM lotes WHERE lote_id=? LIMIT 1");
        $stmt_lote->bind_param("s", $lote_id);
        $stmt_lote->execute();
        $lote = $stmt_lote->get_result()->fetch_assoc();
        if (!$lote) { echo json_encode(["ok" => false, "msg" => "Lote no encontrado"]); break; }
        if ($lote["tipo"] !== "NACIONAL") { echo json_encode(["ok" => false, "msg" => "Solo lotes NACIONAL pueden generar garantia de proveedor"]); break; }

        // Generar numero de garantia
        $fecha = date("Ymd");
        $res = $db->query("SELECT COUNT(*) as c FROM garantias_proveedor WHERE numero_garantia LIKE 'GAR-$fecha-%'");
        $seq = str_pad((int)$res->fetch_assoc()["c"] + 1, 3, "0", STR_PAD_LEFT);
        $numero = "GAR-$fecha-$seq";

        $stmt = $db->prepare("INSERT INTO garantias_proveedor (numero_garantia, lote_id, proveedor_nombre, proveedor_ruc, total_equipos, total_repuestos, notas) VALUES (?,?,?,?,?,?,?)");
        $prov = $data["proveedor_nombre"] ?? $lote["proveedor_nombre"];
        $ruc = $data["proveedor_ruc"] ?? $lote["proveedor_ruc"];
        $items_data = $data["items"] ?? [];
        $total_eq = count(array_filter($items_data, fn($i) => ($i["tipo_item"] ?? "EQUIPO") === "EQUIPO"));
        $total_rep = count($items_data) - $total_eq;
        $notas = $data["notas"] ?? "";
        $stmt->bind_param("ssssiis", $numero, $lote_id, $prov, $ruc, $total_eq, $total_rep, $notas);

        if (!$stmt->execute()) { echo json_encode(["ok" => false, "msg" => $db->error]); break; }
        $gar_id = $db->insert_id;

        // Insertar items
        foreach ($items_data as $item) {
            $stmt2 = $db->prepare("INSERT INTO garantia_items (garantia_id, equipo_codigo, equipo_serie, marca, modelo, falla, tipo_item, pieza) VALUES (?,?,?,?,?,?,?,?)");
            $cod = $item["equipo_codigo"] ?? "";
            $ser = $item["equipo_serie"] ?? "";
            $mar = $item["marca"] ?? "";
            $mod = $item["modelo"] ?? "";
            $fal = $item["falla"] ?? "";
            $tip = $item["tipo_item"] ?? "EQUIPO";
            $pie = $item["pieza"] ?? "";
            $stmt2->bind_param("isssssss", $gar_id, $cod, $ser, $mar, $mod, $fal, $tip, $pie);
            $stmt2->execute();
        }

        echo json_encode(["ok" => true, "id" => $gar_id, "numero" => $numero, "msg" => "Garantia $numero creada con " . count($items_data) . " items"]);
        break;

    // Crear garantia desde sesion de triaje masivo
    case "crear_desde_triaje":
        $data = json_decode(file_get_contents("php://input"), true);
        $sesion_id = (int)($data["sesion_id"] ?? 0);
        $prov = $db->real_escape_string($data["proveedor_nombre"] ?? "");
        $ruc = $db->real_escape_string($data["proveedor_ruc"] ?? "");
        $notas = $db->real_escape_string($data["notas"] ?? "");

        $chk = $db->query("SELECT nombre FROM sesiones_inventario WHERE id=$sesion_id AND estado='CERRADA'");
        if ($chk->num_rows == 0) { echo json_encode(["ok" => false, "msg" => "Sesión no existe o no está cerrada"]); break; }
        
        $fecha = date("Ymd");
        $res = $db->query("SELECT COUNT(*) as c FROM garantias_proveedor WHERE numero_garantia LIKE 'GAR-$fecha-%'");
        $seq = str_pad((int)$res->fetch_assoc()["c"] + 1, 3, "0", STR_PAD_LEFT);
        $numero = "GAR-$fecha-$seq";

        // Obtener items con falla
        $items_fallados = $db->query("SELECT e.codigo, e.serie, e.marca, e.modelo, e.falla FROM sesiones_items si JOIN equipos e ON si.equipo_id = e.id WHERE si.sesion_id=$sesion_id AND si.triaje_asignado='CON_FALLA'");
        $total_eq = $items_fallados->num_rows;

        if ($total_eq == 0) { echo json_encode(["ok" => false, "msg" => "No hay equipos CON_FALLA en esta sesión"]); break; }

        $stmt = $db->prepare("INSERT INTO garantias_proveedor (numero_garantia, sesion_triaje_id, proveedor_nombre, proveedor_ruc, total_equipos, total_repuestos, notas, ultima_alerta_fecha) VALUES (?,?,?,?,?,?,? , CURDATE())");
        $tot_rep = 0;
        $stmt->bind_param("sisssis", $numero, $sesion_id, $prov, $ruc, $total_eq, $tot_rep, $notas);
        if (!$stmt->execute()) { echo json_encode(["ok" => false, "msg" => $db->error]); break; }
        $gar_id = $db->insert_id;

        while ($it = $items_fallados->fetch_assoc()) {
            $st2 = $db->prepare("INSERT INTO garantia_items (garantia_id, equipo_codigo, equipo_serie, marca, modelo, falla, tipo_item) VALUES (?,?,?,?,?,?,'EQUIPO')");
            $st2->bind_param("isssss", $gar_id, $it['codigo'], $it['serie'], $it['marca'], $it['modelo'], $it['falla']);
            $st2->execute();
        }

        echo json_encode(["ok" => true, "id" => $gar_id, "numero" => $numero, "msg" => "Garantía $numero creada con $total_eq equipos"]);
        break;

    // Crear garantia manual
    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        $fecha = date("Ymd");
        $res = $db->query("SELECT COUNT(*) as c FROM garantias_proveedor WHERE numero_garantia LIKE 'GAR-$fecha-%'");
        $seq = str_pad((int)$res->fetch_assoc()["c"] + 1, 3, "0", STR_PAD_LEFT);
        $numero = "GAR-$fecha-$seq";
        $lote_id = !empty($data["lote_id"]) ? $data["lote_id"] : null;
        $prov = $data["proveedor_nombre"] ?? "";
        $ruc = $data["proveedor_ruc"] ?? "";
        $notas = $data["notas"] ?? "";
        $stmt = $db->prepare("INSERT INTO garantias_proveedor (numero_garantia, lote_id, proveedor_nombre, proveedor_ruc, notas) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss", $numero, $lote_id, $prov, $ruc, $notas);
        if ($stmt->execute()) echo json_encode(["ok" => true, "id" => $db->insert_id, "numero" => $numero, "msg" => "Garantia $numero creada"]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    // Actualizar estado
    case "actualizar":
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data["id"] ?? 0);
        $estado = trim($data["estado"] ?? "");
        $tipo_res = isset($data["tipo_resolucion"]) ? $data["tipo_resolucion"] : null;
        $notas = trim($data["notas"] ?? "");
        $alerta_dias = isset($data["alerta_dias"]) ? (int)$data["alerta_dias"] : 10;
        
        $fecha_envio = $estado === "ENVIADO" ? ", fecha_envio=NOW()" : "";
        $fecha_res = in_array($estado, ["RESUELTO","RECHAZADO"]) ? ", fecha_resolucion=NOW()" : "";
        $stmt = $db->prepare("UPDATE garantias_proveedor SET estado=?, tipo_resolucion=?, notas=?, alerta_dias=? $fecha_envio $fecha_res WHERE id=?");
        $stmt->bind_param("sssii", $estado, $tipo_res, $notas, $alerta_dias, $id);
        $stmt->execute();
        echo json_encode(["ok" => true, "msg" => "Garantía actualizada a $estado"]);
        break;


    // KPI resumen por estado
    case "resumen":
        $sql = "SELECT
            COUNT(*) as total,
            SUM(estado='PREPARANDO') as preparando,
            SUM(estado='ENVIADO') as enviado,
            SUM(estado='EN_PROCESO_PROVEEDOR') as en_proceso,
            SUM(estado='RESUELTO') as resuelto,
            SUM(estado='RECHAZADO') as rechazado
            FROM garantias_proveedor";
        $row = $db->query($sql)->fetch_assoc();
        echo json_encode(["ok" => true, "data" => $row]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
