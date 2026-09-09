<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $raw_q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
        $q = "%" . $raw_q . "%";
        $estado = isset($_GET["estado"]) ? trim($_GET["estado"]) : "";
        $marca = isset($_GET["marca"]) ? trim($_GET["marca"]) : "";
        $doc = isset($_GET["doc_compra"]) ? trim($_GET["doc_compra"]) : "";

        $where_clauses = ["(codigo LIKE ? OR serie LIKE ? OR modelo LIKE ? OR marca LIKE ?)"];
        $params = [$q, $q, $q, $q];
        $types = "ssss";

        if ($estado !== "") {
            $where_clauses[] = "estado = ?";
            $params[] = $estado;
            $types .= "s";
        }
        if ($marca !== "") {
            $where_clauses[] = "marca = ?";
            $params[] = $marca;
            $types .= "s";
        }
        if ($doc !== "") {
            $where_clauses[] = "doc_compra = ?";
            $params[] = $doc;
            $types .= "s";
        }

        $where = "WHERE " . implode(" AND ", $where_clauses);
        $limit = (int)($_GET["limit"] ?? 100);

        // Query data
        $sql = "SELECT id, serie, codigo, tipo_equipo, marca, modelo, procesador, ram, hd_ssd, pulgadas, sucursal, estado, observacion, fec_compra, doc_compra, fec_venta FROM equipos $where ORDER BY codigo LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types . "i", ...array_merge($params, [$limit]));
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;

        // Query total count
        $sql_count = "SELECT COUNT(*) as c FROM equipos $where";
        $stmt_count = $db->prepare($sql_count);
        $stmt_count->bind_param($types, ...$params);
        $stmt_count->execute();
        $total = $stmt_count->get_result()->fetch_assoc()["c"];

        echo json_encode(["ok" => true, "data" => $rows, "total" => $total]);
        break;

    case "ver":
        $id = (int)($_GET["id"] ?? 0);
        $result = $db->query("SELECT * FROM equipos WHERE id=$id LIMIT 1");
        $row = $result->fetch_assoc();
        echo json_encode($row ? ["ok" => true, "data" => $row] : ["ok" => false, "msg" => "No encontrado"]);
        break;

    case "buscar":
        $q = trim($_GET["q"] ?? "");
        $stmt = $db->prepare("SELECT id, serie, codigo, marca, modelo, procesador, ram, hd_ssd, estado, observacion, doc_compra FROM equipos WHERE codigo=? OR serie=? LIMIT 10");
        $stmt->bind_param("ss", $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
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
        $codigo = trim($data["codigo"] ?? "");
        $triaje = trim($data["triaje"] ?? "SIN_FALLA");
        $falla = trim($data["falla"] ?? "");

        if (!$codigo) {
            echo json_encode(["ok" => false, "msg" => "Codigo vacio"]);
            break;
        }

        // Search for equipment
        $stmt_search = $db->prepare("SELECT * FROM equipos WHERE codigo=? OR serie=? LIMIT 1");
        $stmt_search->bind_param("ss", $codigo, $codigo);
        $stmt_search->execute();
        $res = $stmt_search->get_result();
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
        $fec_inicio = isset($_GET["fec_inicio"]) ? trim($_GET["fec_inicio"]) : date('Y-m-01');
        $fec_fin = isset($_GET["fec_fin"]) ? trim($_GET["fec_fin"]) : date('Y-m-t');

        $sql = "SELECT 
            doc_compra, 
            observacion, 
            COUNT(*) as cantidad, 
            MIN(fec_compra) as fecha,
            SUM(CASE WHEN (triaje IS NULL OR triaje = '' OR triaje = 'SIN_FALLA') THEN 1 ELSE 0 END) as operativos,
            SUM(CASE WHEN (triaje IS NOT NULL AND triaje != '' AND triaje != 'SIN_FALLA') THEN 1 ELSE 0 END) as danados,
            SUM(CASE WHEN (triaje_inicial IS NULL OR triaje_inicial = '' OR triaje_inicial = 'SIN_FALLA') THEN 1 ELSE 0 END) as operativos_inicial,
            SUM(CASE WHEN (triaje_inicial IS NOT NULL AND triaje_inicial != '' AND triaje_inicial != 'SIN_FALLA') THEN 1 ELSE 0 END) as danados_inicial
            FROM equipos 
            WHERE doc_compra IS NOT NULL AND doc_compra != '' 
            AND fec_compra >= ? AND fec_compra <= ? 
            GROUP BY doc_compra, observacion 
            ORDER BY fecha DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $fec_inicio, $fec_fin);
        $stmt->execute();
        $res = $stmt->get_result();
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
        $doc_items = [];
        $types = "";
        if ($doc !== "") {
            $doc_items = array_values(array_filter(array_map('trim', explode(',', $doc)), 'strlen'));
            if (!empty($doc_items)) {
                $placeholders = implode(',', array_fill(0, count($doc_items), '?'));
                $where = "WHERE doc_compra IN ($placeholders)";
                $types = str_repeat('s', count($doc_items));
            }
        }
        
        // Totales por triaje
        $sql1 = "SELECT IF(triaje IS NULL OR triaje = '', 'SIN_FALLA', triaje) as t, COUNT(*) as c FROM equipos $where GROUP BY IF(triaje IS NULL OR triaje = '', 'SIN_FALLA', triaje)";
        if (!empty($doc_items)) {
            $stmt1 = $db->prepare($sql1);
            $stmt1->bind_param($types, ...$doc_items);
            $stmt1->execute();
            $res = $stmt1->get_result();
        } else {
            $res = $db->query($sql1);
        }
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

        // Totales por triaje inicial (Historico)
        $sql2 = "SELECT IF(triaje_inicial IS NULL OR triaje_inicial = '', 'SIN_FALLA', triaje_inicial) as t, COUNT(*) as c FROM equipos $where GROUP BY IF(triaje_inicial IS NULL OR triaje_inicial = '', 'SIN_FALLA', triaje_inicial)";
        if (!empty($doc_items)) {
            $stmt2 = $db->prepare($sql2);
            $stmt2->bind_param($types, ...$doc_items);
            $stmt2->execute();
            $res_hist = $stmt2->get_result();
        } else {
            $res_hist = $db->query($sql2);
        }
        $stats_hist = ["SIN_FALLA"=>0, "FALLA_MENOR"=>0, "NECESITA_REPUESTO"=>0, "DANO_GRAVE"=>0];
        if ($res_hist) {
            while ($r = $res_hist->fetch_assoc()) {
                $stats_hist[$r["t"]] = (int)$r["c"];
            }
        }

        // Listado detallado
        $limit = (int)($_GET["limit"] ?? 20000);
        $sql3 = "SELECT observacion, doc_compra, codigo, serie, marca, modelo, estado, fec_venta, IF(triaje IS NULL OR triaje = '', 'SIN_FALLA', triaje) as triaje, IF(triaje_inicial IS NULL OR triaje_inicial = '', 'SIN_FALLA', triaje_inicial) as triaje_inicial, falla FROM equipos $where ORDER BY id DESC LIMIT ?";
        $stmt3 = $db->prepare($sql3);
        if (!empty($doc_items)) {
            $stmt3->bind_param($types . "i", ...array_merge($doc_items, [$limit]));
        } else {
            $stmt3->bind_param("i", $limit);
        }
        $stmt3->execute();
        $res_list = $stmt3->get_result();
        if (!$res_list) {
            echo json_encode(["ok" => false, "msg" => "Error BD list: " . $db->error]);
            break;
        }
        $list = [];
        while ($row = $res_list->fetch_assoc()) {
            $list[] = $row;
        }

        echo json_encode(["ok" => true, "total" => $total, "stats" => $stats, "stats_hist" => $stats_hist, "data" => $list]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
