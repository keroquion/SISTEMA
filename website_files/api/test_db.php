<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
$db = getDB();
$id = 59; // Any valid ID
$ant_res = $db->query("SELECT estado, tecnico_id, prioridad, numero_atencion, equipo_descripcion, motivo_ingreso, es_externo FROM soporte_tecnico WHERE id=$id LIMIT 1");
if (!$ant_res) {
    echo "DB Error: " . $db->error;
} else {
    $ant = $ant_res->fetch_assoc();
    var_dump($ant);
}
?>
