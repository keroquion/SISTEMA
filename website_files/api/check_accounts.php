<?php
require_once "config.php";
$db = getDB();

$res = $db->query("SELECT id, nombre, apellido, dni, tipo FROM personas WHERE tipo IN ('admin', 'tecnico', 'gerencia')");
$accounts = [];
while($row = $res->fetch_assoc()){
    $id = (int)$row['id'];
    
    // Check lotes (as admin)
    $q_lotes = $db->query("SELECT COUNT(*) as c FROM lotes WHERE admin_id = $id");
    $lotes = $q_lotes->fetch_assoc()['c'];
    
    // Check soporte (tickets)
    $q_sop = $db->query("SELECT COUNT(*) as c FROM soporte_tecnico WHERE tecnico_id = $id OR cliente_id = $id");
    if($q_sop) {
        $soporte = $q_sop->fetch_assoc()['c'];
    } else {
        $soporte = 0;
    }
    
    // Check lotes_equipos (as tecnico)
    $q_le = $db->query("SELECT COUNT(*) as c FROM lote_equipos WHERE tecnico_id = $id");
    if($q_le) {
        $le = $q_le->fetch_assoc()['c'];
    } else {
        $le = 0;
    }

    $row['lotes_admin'] = $lotes;
    $row['tickets_asignados'] = $soporte;
    $row['items_lote'] = $le;
    $row['total_relaciones'] = $lotes + $soporte + $le;
    
    $accounts[] = $row;
}

echo json_encode(["ok"=>true, "data"=>$accounts]);
?>
