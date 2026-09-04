<?php
require_once "config.php";
$db = getDB();

// Eliminar cuentas de prueba (tecnico, tecnico de pruebas, etc)
// Assuming test accounts have specific names like "Prueba", "Test", or we can just list them.
$res = $db->query("SELECT id, nombre, apellido, usuario, tipo FROM personas");
$accounts = [];
while($row = $res->fetch_assoc()){
    $accounts[] = $row;
}

$deleted = 0;
foreach($accounts as $acc){
    $nom = strtolower($acc['nombre'] . ' ' . $acc['apellido']);
    if(strpos($nom, 'prueba') !== false || strpos($nom, 'test') !== false || strtolower($acc['usuario']) == 'tecnico'){
        $id = (int)$acc['id'];
        $db->query("DELETE FROM personas WHERE id=$id");
        $deleted++;
    }
}

// Volver a listar para mostrar al agente
$res = $db->query("SELECT id, nombre, apellido, usuario, tipo FROM personas");
$final_accounts = [];
while($row = $res->fetch_assoc()){
    $final_accounts[] = $row;
}

echo json_encode(["ok"=>true, "deleted"=>$deleted, "accounts"=>$final_accounts]);
?>
