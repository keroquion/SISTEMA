<?php
require_once "config.php";
$db = getDB();

$query = "ALTER TABLE soporte_tecnico ADD COLUMN tecnicos_adicionales VARCHAR(255) NULL AFTER tecnico_id;";
if ($db->query($query)) {
    echo "Columna tecnicos_adicionales anadida exitosamente.";
} else {
    echo "Error o la columna ya existe: " . $db->error;
}
$db->close();
