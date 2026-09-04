<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $q = isset($_GET["q"]) ? "%" . $db->real_escape_string($_GET["q"]) . "%" : "%";
        $estado = isset($_GET["estado"]) ? $db->real_escape_string($_GET["estado"]) : "";
        $marca = isset($_GET["marca"]) ? $db->real_escape_string($_GET["marca"]) : "";
        $doc = isset($_GET["doc_compra"]) ? $db->real_escape_string($_GET["doc_compra"]) : "";
        $where = "WHERE (codigo LIKE '$q' OR serie LIKE '$q' OR modelo LIKE '$q' OR marca LIKE '$q')";
        if ($estado) $where .= " AND estado = '$estado'";
        if ($marca) $where .= " AND marca = '$marca'";
        if ($doc) $where .= " AND doc_compra = '$doc'";
        $limit = (int)($_GET["limit"] ?? 100);
        $result = $db->query("SELECT id, serie, codigo, tipo_equipo, marca, modelo, procesador, ram, hd_ssd, pulgadas, sucursal, estado, observacion, fec_compra, doc_compra, fec_venta FROM equipos $where ORDER BY codigo LIMIT $limit");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $total = $db->query("SELECT COUNT(*) as c FROM equipos $where")->fetch_assoc()["c"];
        echo json_encode(["ok" => true, "data" => $rows, "total" => $total]);
        break;

    case "ver":
        $id = (int)($_GET["id"] ?? 0);
        $result = $db->query("SELECT * FROM equipos WHERE id=$id LIMIT 1");
        $row = $result->fetch_assoc();
        echo json_encode($row ? ["ok" => true, "data" => $row] : ["ok" => false, "msg" => "No encontrado"]);
        break;

    case "buscar":
        $q = $db->real_escape_string($_GET["q"] ?? "");
        $result = $db->query("SELECT id, serie, codigo, marca, modelo, procesador, ram, hd_ssd, estado, observacion, doc_compra FROM equipos WHERE codigo='$q' OR serie='$q' LIMIT 10");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows, "found" => count($rows) > 0]);
        break;

    case "stats":
        $total = $db->query("SELECT COUNT(*) as c FROM equipos")->fetch_assoc()["c"];
        $por_estado = $db->query("SELECT estado, COUNT(*) as c FROM equipos GROUP BY estado");
        $estados = [];
        while ($r = $por_estado->fetch_assoc()) $estados[$r["estado"]] = $r["c"];
        $por_marca = $db->query("SELECT marca, COUNT(*) as c FROM equipos GROUP BY marca ORDER BY c DESC LIMIT 10");
        $marcas = [];
        while ($r = $por_marca->fetch_assoc()) $marcas[] = $r;
        echo json_encode(["ok" => true, "total" => $total, "por_estado" => $estados, "top_marcas" => $marcas]);
        break;

    case "inventario_rapido":
        $data = json_decode(file_get_contents("php://input"), true);
        $codigo = $db->real_escape_string($data["codigo"] ?? "");
        $triaje = $db->real_escape_string($data["triaje"] ?? "SIN_FALLA");
        $falla = $db->real_escape_string($data["falla"] ?? "");

        if (!$codigo) {
            echo json_encode(["ok" => false, "msg" => "Codigo vacío"]);
            break;
        }

        // Search for equipment
        $res = $db->query("SELECT * FROM equipos WHERE codigo='$codigo' OR serie='$codigo' LIMIT 1");
        $equipo = $res->fetch_assoc();
        
        if (!$equipo) {
            echo json_encode(["ok" => false, "msg" => "Equipo no encontrado en BD"]);
            break;
        }

        // Update triaje and falla
        $id = $equipo["id"];
        $stmt = $db->prepare("UPDATE equipos SET triaje=?, falla=? WHERE id=?");
        $stmt->bind_param("ssi", $triaje, $falla, $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "data" => $equipo]);
        } else {
            echo json_encode(["ok" => false, "msg" => $db->error]);
        }
        break;

    case "list_docs":
        $res = $db->query("SELECT doc_compra, observacion, COUNT(*) as cantidad, MIN(fec_compra) as fecha FROM equipos WHERE doc_compra IS NOT NULL AND doc_compra != '' GROUP BY doc_compra, observacion ORDER BY fecha DESC");
        if (!$res) {
            echo json_encode(["ok" => false, "msg" => "Error BD: " . $db->error]);
            break;
        }
        $docs = [];
        while ($row = $res->fetch_assoc()) {
            $docs[] = $row;
        }
        echo json_encode(["ok" => true, "data" => $docs]);
        break;

    case "reporte_avanzado":
        $doc = isset($_GET["doc_compra"]) ? trim($_GET["doc_compra"]) : "";
        $where = "";
        if ($doc) {
            $docs_array = array_map(function($d) use ($db) { return "'" . $db->real_escape_string(trim($d)) . "'"; }, explode(',', $doc));
            $docs_list = implode(',', $docs_array);
            $where = "WHERE doc_compra IN ($docs_list)";
        }
        
        // Totales por triaje
        $res = $db->query("SELECT COALESCE(triaje, 'SIN_FALLA') as t, COUNT(*) as c FROM equipos $where GROUP BY COALESCE(triaje, 'SIN_FALLA')");
        if (!$res) {
            echo json_encode(["ok" => false, "msg" => "Error BD stats: " . $db->error]);
            break;
        }
        $stats = ["SIN_FALLA"=>0, "FALLA_MENOR"=>0, "NECESITA_REPUESTO"=>0, "DANO_GRAVE"=>0];
        $total = 0;
        while ($r = $res->fetch_assoc()) {
            $stats[$r["t"]] = (int)$r["c"];
            $total += (int)$r["c"];
        }

        // Listado detallado
        $limit = (int)($_GET["limit"] ?? 500);
        $res_list = $db->query("SELECT observacion, doc_compra, codigo, serie, marca, modelo, estado, fec_venta, COALESCE(triaje, 'SIN_FALLA') as triaje, falla FROM equipos $where ORDER BY id DESC LIMIT $limit");
        if (!$res_list) {
            echo json_encode(["ok" => false, "msg" => "Error BD list: " . $db->error]);
            break;
        }
        $list = [];
        while ($row = $res_list->fetch_assoc()) {
            $list[] = $row;
        }

        echo json_encode(["ok" => true, "total" => $total, "stats" => $stats, "data" => $list]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
