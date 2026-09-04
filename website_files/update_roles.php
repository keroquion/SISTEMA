<?php
require_once "api/config.php";
$db = getDB();

$res = $db->query("SELECT * FROM roles_config");
while($row = $res->fetch_assoc()) {
    $modulos = json_decode($row['modulos_permitidos'], true);
    if (!in_array('historial_entregados.html', $modulos)) {
        $modulos[] = 'historial_entregados.html';
        $modulos_json = $db->real_escape_string(json_encode($modulos));
        $id = $row['id'];
        $db->query("UPDATE roles_config SET modulos_permitidos = '$modulos_json' WHERE id = $id");
    }
}
echo "Roles actualizados.";
?>
