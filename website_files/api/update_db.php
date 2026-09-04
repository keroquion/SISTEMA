<?php
require_once "config.php";
$db = getDB();

$queries = [
    "ALTER TABLE `equipos` ADD COLUMN `triaje` ENUM('SIN_FALLA','FALLA_MENOR','NECESITA_REPUESTO','DANO_GRAVE') DEFAULT 'SIN_FALLA'",
    "ALTER TABLE `equipos` ADD COLUMN `falla` TEXT DEFAULT NULL",
    "UPDATE `personas` SET `tipo` = 'admin' WHERE `dni` = '99999999'",
    "CREATE TABLE IF NOT EXISTS `repuestos` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `nombre` VARCHAR(255) NOT NULL,
      `pn` VARCHAR(100) DEFAULT NULL,
      `stock` INT DEFAULT 0,
      `precio` DECIMAL(10,2) DEFAULT 0.00,
      `notas` TEXT DEFAULT NULL,
      `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `secuencias_tickets` (
      `fecha_str` VARCHAR(10) PRIMARY KEY,
      `ultimo_valor` INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `log_movimientos_lotes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `lote_origen` VARCHAR(50) NOT NULL,
      `lote_destino` VARCHAR(50) NOT NULL,
      `equipo_codigo` VARCHAR(50) NOT NULL,
      `usuario_id` INT DEFAULT NULL,
      `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$results = [];
foreach ($queries as $q) {
    if ($db->query($q)) {
        $results[] = "OK: $q";
    } else {
        $results[] = "ERROR/ALREADY_EXISTS: " . $db->error . " -> $q";
    }
}

echo "<h3>Actualizacion de BD</h3>";
foreach ($results as $r) {
    echo "<p>$r</p>";
}
?>
