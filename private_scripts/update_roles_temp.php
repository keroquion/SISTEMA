<?php
require_once "config.php";
$db = getDB();

$roles = ['admin', 'gerencia'];
foreach ($roles as $rol) {
    $res = $db->query("SELECT modulos_permitidos FROM roles_config WHERE rol = '$rol'");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $modulos = json_decode($row['modulos_permitidos'], true);
        if (!in_array('desempeno_tecnicos.html', $modulos)) {
            $modulos[] = 'desempeno_tecnicos.html';
            $nuevoJson = json_encode($modulos);
            $db->query("UPDATE roles_config SET modulos_permitidos = '$nuevoJson' WHERE rol = '$rol'");
            echo "Agregado a $rol\n";
        } else {
            echo "Ya existia en $rol\n";
        }
    }
}
echo "Completado\n";
?>
