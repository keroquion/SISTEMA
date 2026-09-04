<?php
require_once "config.php";
$db = getDB();

$sql1 = "ALTER TABLE lotes ADD COLUMN repuestos_orden VARCHAR(100) DEFAULT NULL";
$sql2 = "ALTER TABLE lotes ADD COLUMN repuestos_costo DECIMAL(10,2) DEFAULT NULL";
$sql3 = "ALTER TABLE lotes ADD COLUMN repuestos_estado VARCHAR(50) DEFAULT 'PENDIENTE'";

$db->query($sql1);
$db->query($sql2);
$db->query($sql3);

echo "Columnas anadidas con exito o ya existian.";
?>
