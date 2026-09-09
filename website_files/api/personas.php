<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $tipo = $_GET["tipo"] ?? "cliente";
        $stmt = $db->prepare("SELECT id, dni, nombre, apellido, telefono, tipo, especialidad, activo, notas, fecha_registro FROM personas WHERE tipo = ? ORDER BY nombre");
        $stmt->bind_param("s", $tipo);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "todos":
        $result = $db->query("SELECT id, dni, nombre, apellido, telefono, tipo, especialidad, activo FROM personas ORDER BY tipo, nombre");
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "buscar_dni":
        $dni = trim($_GET["dni"] ?? "");
        $stmt = $db->prepare("SELECT id, dni, nombre, apellido, telefono, tipo, especialidad, activo, notas FROM personas WHERE dni = ? LIMIT 1");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) echo json_encode(["ok" => true, "data" => $row]);
        else echo json_encode(["ok" => false, "msg" => "DNI no encontrado"]);
        break;

    case "buscar":
        $raw_q = trim($_GET["q"] ?? "");
        $q = "%" . $raw_q . "%";
        $tipo = trim($_GET["tipo"] ?? "cliente");
        $stmt = $db->prepare("SELECT id, dni, nombre, apellido, telefono, tipo, especialidad FROM personas WHERE tipo = ? AND (nombre LIKE ? OR apellido LIKE ? OR dni LIKE ?) ORDER BY nombre LIMIT 20");
        $stmt->bind_param("ssss", $tipo, $q, $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("INSERT INTO personas (dni, nombre, apellido, telefono, tipo, especialidad, notas) VALUES (?,?,?,?,?,?,?)");
        $apellido = $data["apellido"] ?? "";
        $tel = $data["telefono"] ?? "936183039";
        $tipo = $data["tipo"] ?? "cliente";
        $espec = $data["especialidad"] ?? "";
        $notas = $data["notas"] ?? "";
        $stmt->bind_param("sssssss", $data["dni"], $data["nombre"], $apellido, $tel, $tipo, $espec, $notas);
        if ($stmt->execute()) echo json_encode(["ok" => true, "id" => $db->insert_id, "msg" => "Creado OK"]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "editar":
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)$data["id"];
        $dni = $data["dni"] ?? "";
        $nombre = $data["nombre"] ?? "";
        $apellido = $data["apellido"] ?? "";
        $tel = $data["telefono"] ?? "";
        $espec = $data["especialidad"] ?? "";
        $activo = (int)($data["activo"] ?? 1);
        $notas = $data["notas"] ?? "";
        
        $sql = "UPDATE personas SET nombre=?, apellido=?, telefono=?, especialidad=?, activo=?, notas=?";
        $types = "ssssis";
        $params = [$nombre, $apellido, $tel, $espec, $activo, $notas];

        if ($dni !== "") {
            $sql .= ", dni=?";
            $types .= "s";
            $params[] = $dni;
        }

        if (!empty($data["password"])) {
            $sql .= ", password_hash=?";
            $types .= "s";
            $params[] = password_hash($data["password"], PASSWORD_BCRYPT);
        }

        $sql .= " WHERE id=?";
        $types .= "i";
        $params[] = $id;

        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) echo json_encode(["ok" => true, "msg" => "Actualizado OK"]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "eliminar":
        $id = (int)($_GET["id"] ?? 0);
        $check = $db->query("SELECT COUNT(*) as c FROM soporte_tecnico WHERE cliente_id = $id OR tecnico_id = $id");
        $row = $check->fetch_assoc();
        if ($row["c"] > 0) {
            echo json_encode(["ok" => false, "msg" => "Tiene atenciones asociadas, no se puede eliminar"]);
        } else {
            $db->query("DELETE FROM turnos WHERE tecnico_id = $id");
            $db->query("DELETE FROM personas WHERE id = $id");
            echo json_encode(["ok" => true, "msg" => "Eliminado"]);
        }
        break;

    case "consultar_dni":
        $dni = preg_replace('/[^0-9]/', '', $_GET["dni"] ?? "");
        if (strlen($dni) !== 8) {
            echo json_encode(["ok" => false, "msg" => "DNI invalido (debe tener 8 digitos)"]);
            break;
        }
        $url = "https://api.apis.net.pe/v1/dni?numero=" . $dni;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Petulap/1.0");
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $http_code !== 200) {
            echo json_encode(["ok" => false, "msg" => "Error de conexion con el servidor publico de RENIEC/SUNAT"]);
            break;
        }
        
        $data = json_decode($response, true);
        if (isset($data["numeroDocumento"])) {
            echo json_encode(["ok" => true, "data" => [
                "nombre" => $data["nombres"],
                "apellido" => $data["apellidoPaterno"] . " " . $data["apellidoMaterno"]
            ]]);
        } else {
            echo json_encode(["ok" => false, "msg" => "DNI no encontrado en la base publica"]);
        }
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
