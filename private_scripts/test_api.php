<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
session_start();
$_SESSION['user_id'] = 1; // Fake login

$data = ["id" => 59, "estado" => "LISTO_PARA_RECOGER", "usuario" => "TEST"];
$id = 59;

$ant_res = $db->query("SELECT estado, tecnico_id, prioridad, numero_atencion, equipo_descripcion, motivo_ingreso, es_externo FROM soporte_tecnico WHERE id=$id LIMIT 1");
$ant = $ant_res->fetch_assoc();

echo "JSON_ENCODE: \n";
echo json_encode(["ok" => true, "msg" => "Actualizado"]);
echo "\n\nAFTER JSON ENCODE:\n";

try {
    require_once 'push.php';
    $num = $ant['numero_atencion'] ?? '';
    $es_tarea = ($ant['es_externo'] == 2 || strpos($num, 'TAR') === 0);
    $titulo_tk = $es_tarea ? ($ant['motivo_ingreso'] ?: 'Tarea Interna') : ($ant['equipo_descripcion'] ?: 'Equipo en Soporte');
    
    $tecNombre = $data['usuario'] ?? 'Tecnico';
    $emoji = $data['estado'] === 'ENTREGADO' ? "\xF0\x9F\x93\xA6" : "\xE2\x9C\x85";
    
    // sendPushToAdmins($db, "$emoji $titulo_tk", "Ticket: $num\nMarcada como " . str_replace('_', ' ', $data['estado']) . " por $tecNombre", '/mis_ordenes.html');
    echo "Push would have been sent: $titulo_tk \n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Throwable $t) {
    echo "Fatal Error: " . $t->getMessage();
}
?>
