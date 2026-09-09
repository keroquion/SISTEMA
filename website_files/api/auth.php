<?php
session_start();
require_once "config.php";

$db = getDB();
$action = $_GET["action"] ?? "check";

switch ($action) {
    case "login":
        $data = json_decode(file_get_contents("php://input"), true);
        $dni = $db->real_escape_string($data["dni"] ?? "");
        $pass = $data["password"] ?? "";

        if (!$dni || !$pass) {
            echo json_encode(["ok" => false, "msg" => "Faltan credenciales"]);
            exit;
        }

        $res = $db->query("SELECT id, dni, nombre, apellido, tipo, password_hash FROM personas WHERE dni = '$dni' AND (tipo = 'admin' OR tipo = 'tecnico' OR tipo = 'gerencia') AND activo = 1 LIMIT 1");
        
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            
            if (empty($user['password_hash'])) {
                echo json_encode([
                    "ok" => false, 
                    "msg" => "Usuario sin contraseña configurada. Solicite a un administrador que le asigne una contraseña."
                ]);
                exit;
            }
            $auth_ok = password_verify($pass, $user["password_hash"]);

            if ($auth_ok) {
                $tipo = $user['tipo'];
                $config_res = $db->query("SELECT pagina_defecto, modulos_permitidos FROM roles_config WHERE rol = '$tipo'");
                if ($config_res && $config_res->num_rows > 0) {
                    $cfg = $config_res->fetch_assoc();
                    $pagina_defecto = $cfg['pagina_defecto'];
                    $modulos = json_decode($cfg['modulos_permitidos'], true);
                } else {
                    $pagina_defecto = 'index.html';
                    $modulos = [];
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_dni'] = $user['dni'];
                $_SESSION['user_nombre'] = $user['nombre'] . ' ' . $user['apellido'];
                $_SESSION['user_tipo'] = $tipo;
                $_SESSION['user_start_url'] = $pagina_defecto;
                $_SESSION['user_modulos'] = $modulos;
                
                echo json_encode([
                    "ok" => true,
                    "data" => [
                        "id" => $user['id'],
                        "tipo" => $tipo,
                        "nombre" => $_SESSION['user_nombre'],
                        "start_url" => $pagina_defecto,
                        "modulos" => $modulos
                    ]
                ]);
            } else {
                echo json_encode(["ok" => false, "msg" => "Contrasena incorrecta"]);
            }
        } else {
            echo json_encode(["ok" => false, "msg" => "Usuario no encontrado o no autorizado"]);
        }
        break;

    case "logout":
        // Clean up push subscriptions
        if (isset($_SESSION['user_id'])) {
            try {
                $userId = (int)$_SESSION['user_id'];
                $db->query("DELETE FROM push_subscriptions WHERE user_id = $userId");
            } catch (Exception $e) { /* silent */ }
        }
        session_destroy();
        echo json_encode(["ok" => true, "msg" => "Sesion cerrada"]);
        break;

    case "check":
        if (isset($_SESSION['user_id'])) {
            // Refrescar roles por si el admin los cambio en caliente
            $tipo = $_SESSION['user_tipo'];
            $config_res = $db->query("SELECT pagina_defecto, modulos_permitidos FROM roles_config WHERE rol = '$tipo'");
            if ($config_res && $config_res->num_rows > 0) {
                $cfg = $config_res->fetch_assoc();
                $_SESSION['user_start_url'] = $cfg['pagina_defecto'];
                $_SESSION['user_modulos'] = json_decode($cfg['modulos_permitidos'], true);
            }

            echo json_encode([
                "ok" => true,
                "data" => [
                    "id" => $_SESSION['user_id'],
                    "tipo" => $_SESSION['user_tipo'],
                    "nombre" => $_SESSION['user_nombre'],
                    "start_url" => $_SESSION['user_start_url'] ?? 'index.html',
                    "modulos" => $_SESSION['user_modulos'] ?? []
                ]
            ]);
        } else {
            echo json_encode(["ok" => false, "msg" => "No session"]);
        }
        break;
}
?>
