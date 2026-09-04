<?php
require_once "api/config.php";
$db = getDB();

// 1. Anadir columna password_hash si no existe
$db->query("ALTER TABLE personas ADD COLUMN password_hash VARCHAR(255) NULL AFTER telefono");

// 2. Hash por defecto '123456' para tecnicos existentes
$defaultHash = password_hash('123456', PASSWORD_DEFAULT);
$db->query("UPDATE personas SET password_hash = '$defaultHash' WHERE tipo = 'tecnico' AND password_hash IS NULL");

// 3. Crear el administrador maestro (si no existe)
$adminDni = 'ADMIN001';
$adminHash = password_hash('petulap2026', PASSWORD_DEFAULT);

$res = $db->query("SELECT id FROM personas WHERE tipo = 'admin' LIMIT 1");
if ($res->num_rows == 0) {
    $db->query("INSERT INTO personas (dni, nombre, apellido, tipo, password_hash, activo) VALUES ('$adminDni', 'Administrador', 'Maestro', 'admin', '$adminHash', 1)");
    echo "Admin maestro creado (user: ADMIN001, pass: petulap2026).\n";
} else {
    // Si ya existe, asegurar que la contrasena es correcta
    $db->query("UPDATE personas SET password_hash = '$adminHash' WHERE tipo = 'admin'");
    echo "Admin maestro actualizado.\n";
}

echo "Base de datos securizada correctamente.\n";
?>
