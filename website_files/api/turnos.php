<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
session_write_close();
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $sql = "SELECT t.*, CONCAT(p.nombre,' ',p.apellido) as tecnico_nombre, p.dni, p.especialidad, p.activo as persona_activa
                FROM turnos t JOIN personas p ON t.tecnico_id = p.id
                ORDER BY p.nombre";
        $result = $db->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    // Verificar si un tecnico tiene turno activo AHORA MISMO
    case "verificar_turno":
        $tecnico_id = (int)($_GET["tecnico_id"] ?? 0);
        $ahora = date("H:i:s");
        $dia_hoy = date("l"); // En ingles: Monday, Tuesday...
        $dias_es = ["Monday"=>"Lunes","Tuesday"=>"Martes","Wednesday"=>"Miercoles",
                    "Thursday"=>"Jueves","Friday"=>"Viernes","Saturday"=>"Sabado","Sunday"=>"Domingo"];
        $dia_hoy_es = $dias_es[$dia_hoy] ?? $dia_hoy;

        $stmt = $db->prepare("SELECT t.*, CONCAT(p.nombre,' ',p.apellido) as tecnico_nombre
            FROM turnos t JOIN personas p ON t.tecnico_id = p.id
            WHERE t.tecnico_id = ? AND t.activo = 1 LIMIT 1");
        $stmt->bind_param("i", $tecnico_id);
        $stmt->execute();
        $turno = $stmt->get_result()->fetch_assoc();

        if (!$turno) {
            echo json_encode(["ok" => false, "activo" => false, "msg" => "Tecnico no tiene turno configurado"]);
            break;
        }

        $en_dia = strpos($turno["dias_trabajo"], $dia_hoy_es) !== false;
        $en_hora = $ahora >= $turno["hora_inicio"] && $ahora <= $turno["hora_fin"];
        $activo = $en_dia && $en_hora;

        echo json_encode([
            "ok" => true,
            "activo" => $activo,
            "tecnico_nombre" => $turno["tecnico_nombre"],
            "hora_inicio" => $turno["hora_inicio"],
            "hora_fin" => $turno["hora_fin"],
            "dias" => $turno["dias_trabajo"],
            "dia_actual" => $dia_hoy_es,
            "hora_actual" => $ahora,
            "msg" => $activo ? "Tecnico en turno activo" : "Tecnico fuera de turno ($dia_hoy_es $ahora, turno: {$turno['hora_inicio']}-{$turno['hora_fin']})"
        ]);
        break;

    // Todos los tecnicos con estado de turno actual
    case "estado_actual":
        $ahora = date("H:i:s");
        $dia_hoy = date("l");
        $dias_es = ["Monday"=>"Lunes","Tuesday"=>"Martes","Wednesday"=>"Miercoles",
                    "Thursday"=>"Jueves","Friday"=>"Viernes","Saturday"=>"Sabado","Sunday"=>"Domingo"];
        $dia_hoy_es = $dias_es[$dia_hoy] ?? $dia_hoy;

        $sql = "SELECT t.*, CONCAT(p.nombre,' ',p.apellido) as tecnico_nombre, p.dni, p.especialidad
                FROM turnos t JOIN personas p ON t.tecnico_id = p.id
                WHERE t.activo = 1 ORDER BY p.nombre";
        $result = $db->query($sql);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $en_dia = strpos($row["dias_trabajo"], $dia_hoy_es) !== false;
            $en_hora = $ahora >= $row["hora_inicio"] && $ahora <= $row["hora_fin"];
            $row["en_turno_ahora"] = $en_dia && $en_hora;
            $row["dia_actual"] = $dia_hoy_es;
            $row["hora_actual"] = $ahora;
            $rows[] = $row;
        }
        echo json_encode(["ok" => true, "data" => $rows, "hora_servidor" => $ahora, "dia" => $dia_hoy_es]);
        break;

    case "crear":
        check_api_access('admin_only');
        $data = json_decode(file_get_contents("php://input"), true);
        $tecnico_id = (int)($data["tecnico_id"] ?? 0);
        // Verificar que no tenga ya un turno
        $stmt_chk = $db->prepare("SELECT id FROM turnos WHERE tecnico_id=? LIMIT 1");
        $stmt_chk->bind_param("i", $tecnico_id);
        $stmt_chk->execute();
        $check = $stmt_chk->get_result();
        if ($check->num_rows > 0) {
            echo json_encode(["ok" => false, "msg" => "Este tecnico ya tiene turno. Use editar."]);
            break;
        }
        $stmt = $db->prepare("INSERT INTO turnos (tecnico_id, dias_trabajo, hora_inicio, hora_fin, activo) VALUES (?,?,?,?,?)");
        $dias = $data["dias_trabajo"] ?? "Lunes,Martes,Miercoles,Jueves,Viernes";
        $ini = $data["hora_inicio"] ?? "08:00:00";
        $fin = $data["hora_fin"] ?? "18:00:00";
        $activo = (int)($data["activo"] ?? 1);
        $stmt->bind_param("isssi", $tecnico_id, $dias, $ini, $fin, $activo);
        if ($stmt->execute()) echo json_encode(["ok" => true, "msg" => "Turno creado"]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "editar":
        check_api_access('admin_only');
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data["id"] ?? 0);
        $stmt = $db->prepare("UPDATE turnos SET dias_trabajo=?, hora_inicio=?, hora_fin=?, activo=? WHERE id=?");
        $dias = $data["dias_trabajo"] ?? "Lunes,Martes,Miercoles,Jueves,Viernes";
        $ini = $data["hora_inicio"] ?? "08:00:00";
        $fin = $data["hora_fin"] ?? "18:00:00";
        $activo = (int)($data["activo"] ?? 1);
        $stmt->bind_param("sssii", $dias, $ini, $fin, $activo, $id);
        if ($stmt->execute()) echo json_encode(["ok" => true, "msg" => "Turno actualizado"]);
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
