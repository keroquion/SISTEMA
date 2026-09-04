<?php
require_once "config.php";
$db = getDB();
$sql = "ALTER TABLE roles_config ADD COLUMN acciones_permitidas TEXT AFTER modulos_permitidos";
if ($db->query($sql)) {
    echo "Columna anadida con exito.";
} else {
    echo "Error o la columna ya existe: " . $db->error;
}
?>
