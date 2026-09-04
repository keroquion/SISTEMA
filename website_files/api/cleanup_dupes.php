<?php
require_once "config.php";
$db = getDB();

$res = $db->query("SELECT codigo, COUNT(*) as c FROM equipos GROUP BY codigo HAVING c > 1");
$deleted = 0;
$protected = 0;

while ($row = $res->fetch_assoc()) {
    $codigo = $db->real_escape_string($row['codigo']);
    if (empty($codigo)) continue;

    // Priorizar mantener el que tiene triaje != 'SIN_FALLA' o el mas antiguo (MIN id)
    // Usamos ORDER BY para que el primero que nos devuelva sea el que "salvamos"
    $q = $db->query("SELECT id, triaje FROM equipos WHERE codigo = '$codigo' ORDER BY IF(triaje != 'SIN_FALLA', 0, 1), id ASC");
    
    $first = true;
    while ($item = $q->fetch_assoc()) {
        if ($first) {
            $first = false; // Salvamos este
            if ($item['triaje'] !== 'SIN_FALLA') {
                $protected++;
            }
        } else {
            // Borramos los demas (clones)
            $id = $item['id'];
            $db->query("DELETE FROM equipos WHERE id = $id");
            $deleted++;
        }
    }
}

echo json_encode([
    'ok' => true,
    'msg' => "Se eliminaron $deleted equipos duplicados. Se protegieron $protected equipos con triaje activo."
]);
