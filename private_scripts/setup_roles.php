<?php
require_once "config.php";
$db = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS `roles_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rol` varchar(50) NOT NULL UNIQUE,
  `pagina_defecto` varchar(100) NOT NULL,
  `modulos_permitidos` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$db->query($sql);

$default_roles = [
    [
        'rol' => 'admin',
        'defecto' => 'index.html',
        'modulos' => '["index.html","recepcion_movil.html","inventario.html","reportes.html","soporte.html","mis_ordenes.html","tecnicos.html","garantias.html","lotes.html","inventario_soporte.html","importar.html","caja.html","clientes.html","turnos.html","manual.html","admin_roles.html","historial_entregados.html"]'
    ],
    [
        'rol' => 'gerencia',
        'defecto' => 'reportes.html',
        'modulos' => '["index.html","reportes.html","lotes.html","clientes.html","soporte.html","historial_entregados.html"]'
    ],
    [
        'rol' => 'tecnico',
        'defecto' => 'mis_ordenes.html',
        'modulos' => '["index.html","mis_ordenes.html","soporte.html","recepcion_movil.html","tecnicos.html","garantias.html","inventario.html","inventario_soporte.html","importar.html","caja.html","turnos.html","manual.html","historial_entregados.html"]'
    ]
];

foreach ($default_roles as $r) {
    $rol = $db->real_escape_string($r['rol']);
    $defecto = $db->real_escape_string($r['defecto']);
    $modulos = $db->real_escape_string($r['modulos']);
    $db->query("INSERT IGNORE INTO roles_config (rol, pagina_defecto, modulos_permitidos) VALUES ('$rol', '$defecto', '$modulos')");
}

echo "Roles configurados correctamente.";
