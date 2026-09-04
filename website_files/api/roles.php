<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }

require_once "config.php";
check_api_access('admin_only'); // Solo Admin
$db = getDB();
$action = $_GET["action"] ?? "listar";

switch ($action) {
    case "listar":
        $res = $db->query("SELECT * FROM roles_config ORDER BY rol ASC");
        $roles = [];
        while ($row = $res->fetch_assoc()) {
            $row['modulos_permitidos'] = json_decode($row['modulos_permitidos'], true);
            $roles[] = $row;
        }
        echo json_encode(["ok" => true, "data" => $roles]);
        break;

    case "guardar":
        $data = json_decode(file_get_contents("php://input"), true);
        $rol = $db->real_escape_string($data['rol'] ?? '');
        $pagina_defecto = $db->real_escape_string($data['pagina_defecto'] ?? 'index.html');
        
        $modulos = $data['modulos_permitidos'] ?? [];
        if (!is_array($modulos)) $modulos = [];
        $modulos_json = $db->real_escape_string(json_encode($modulos));

        if (!$rol) {
            echo json_encode(["ok" => false, "msg" => "Falta rol"]);
            exit;
        }

        $sql = "UPDATE roles_config SET pagina_defecto = '$pagina_defecto', modulos_permitidos = '$modulos_json' WHERE rol = '$rol'";
        if ($db->query($sql)) {
            echo json_encode(["ok" => true, "msg" => "Rol guardado correctamente"]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error guardando: " . $db->error]);
        }
        break;
}
?>
