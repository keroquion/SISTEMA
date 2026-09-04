<?php
require_once "config.php";
$db = getDB();
$db->query("UPDATE equipos SET triaje='SIN_FALLA', falla=NULL WHERE falla LIKE '%Falla de prueba automatica%'");
echo "OK Limpiado. " . $db->affected_rows . " filas afectadas.";
$db->close();
?>
