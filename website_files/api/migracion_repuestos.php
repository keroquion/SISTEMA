<?php
header('Content-Type: text/plain');
require_once "config.php";
$db = getDB();

$sql1 = "ALTER TABLE soporte_tecnico ADD COLUMN repuesto_pagado TINYINT(1) DEFAULT 0";
$sql2 = "ALTER TABLE soporte_tecnico ADD COLUMN repuesto_comprobante VARCHAR(100) DEFAULT NULL";
$sql3 = "ALTER TABLE soporte_tecnico ADD COLUMN repuesto_fecha_llegada_aprox DATE DEFAULT NULL";

echo "Iniciando migracion...\n";

if ($db->query($sql1)) echo "repuesto_pagado OK\n";
else echo "Error o ya existe: " . $db->error . "\n";

if ($db->query($sql2)) echo "repuesto_comprobante OK\n";
else echo "Error o ya existe: " . $db->error . "\n";

if ($db->query($sql3)) echo "repuesto_fecha_llegada_aprox OK\n";
else echo "Error o ya existe: " . $db->error . "\n";

echo "FIN MIGRACION.";
?>
