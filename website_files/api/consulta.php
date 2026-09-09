<?php
require_once "config.php";
$db = getDB();

$action = $_GET["action"] ?? "estado";

if ($action === "estado") {
    $ticket = $db->real_escape_string($_GET["ticket"] ?? "");
    $dni = $db->real_escape_string($_GET["dni"] ?? "");

    if (!$ticket || !$dni) {
        echo json_encode(["ok" => false, "msg" => "Faltan datos de consulta."]);
        exit;
    }

    // Consulta ultra segura: Solo cruza si el DNI pertenece al cliente de esa atencion
    $stmt = $db->prepare("
        SELECT st.numero_atencion, st.estado, st.fecha_ingreso, st.fecha_entrega, st.en_garantia, 
               st.diagnostico, st.solucion, st.equipo_descripcion, st.motivo_ingreso,
               p.nombre as cliente_nombre
        FROM soporte_tecnico st
        JOIN personas p ON st.cliente_id = p.id
        WHERE st.numero_atencion = ? AND p.dni = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $ticket, $dni);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        // Anti-bots generico
        echo json_encode(["ok" => false, "msg" => "No se encontro ninguna orden de servicio con esos datos. Verifique su numero de Ticket y su DNI."]);
    } else {
        $data = $res->fetch_assoc();
        echo json_encode(["ok" => true, "data" => $data]);
    }
} else {
    echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}

$db->close();
