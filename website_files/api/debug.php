<?php
require_once "config.php";
$db = getDB();

echo "=== FIXING TRIAJE ENUM ===\n";
$db->query("ALTER TABLE equipos MODIFY COLUMN triaje ENUM('SIN_FALLA','FALLA_MENOR','NECESITA_REPUESTO','DANO_GRAVE','SOPORTE') DEFAULT 'SIN_FALLA'");
echo "Alter table result: " . ($db->error ? $db->error : "OK") . "\n";

echo "=== FIXING 564 RECORDS ===\n";
$db->query("UPDATE equipos SET triaje='SOPORTE' WHERE triaje=''");
echo "Rows updated: " . $db->affected_rows . "\n";

?>
