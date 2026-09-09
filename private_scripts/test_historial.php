<?php
require_once "api/config.php";
$db = getDB();
$res = $db->query("SELECT * FROM historial_cambios");
if (!$res) {
    echo "Error: " . $db->error;
} else {
    echo "Historial OK";
}
?>
