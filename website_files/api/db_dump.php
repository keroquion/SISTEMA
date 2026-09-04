<?php
require_once "config.php";
$db = getDB();
$res = $db->query("SELECT id, nombre, apellido, dni, tipo FROM personas");
$data = [];
while($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode(["ok"=>true, "data"=>$data]);
?>
