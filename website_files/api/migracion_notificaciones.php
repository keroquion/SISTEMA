<?php
header('Content-Type: text/plain');
require_once "config.php";
$db = getDB();

$sql1 = "CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensaje TEXT,
    link VARCHAR(255),
    leido TINYINT(1) DEFAULT 0,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$sql2 = "ALTER TABLE garantias_proveedor ADD COLUMN sesion_triaje_id INT DEFAULT NULL";
$sql3 = "ALTER TABLE garantias_proveedor ADD COLUMN alerta_dias INT DEFAULT 10";
$sql4 = "ALTER TABLE garantias_proveedor ADD COLUMN ultima_alerta_fecha DATE DEFAULT NULL";

echo "Iniciando migracion notificaciones y garantias...\n";

if ($db->query($sql1)) echo "Tabla notificaciones OK\n";
else echo "Error: " . $db->error . "\n";

if ($db->query($sql2)) echo "sesion_triaje_id OK\n";
else echo "Error o ya existe: " . $db->error . "\n";

if ($db->query($sql3)) echo "alerta_dias OK\n";
else echo "Error o ya existe: " . $db->error . "\n";

if ($db->query($sql4)) {
    echo "ultima_alerta_fecha OK\n";
    // Si acaba de crearse, igualar ultima_alerta_fecha a fecha_creacion para garantias antiguas
    $db->query("UPDATE garantias_proveedor SET ultima_alerta_fecha = DATE(fecha_creacion) WHERE ultima_alerta_fecha IS NULL");
}
else echo "Error o ya existe: " . $db->error . "\n";

echo "FIN MIGRACION.";
?>
