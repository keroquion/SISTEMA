<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('HTTP/1.1 401 Unauthorized'); echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); exit; }
session_write_close();
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

switch ($action) {

    case "list":
        $estado = isset($_GET["estado"]) ? trim($_GET["estado"]) : "";
        $origen = isset($_GET["origen"]) ? trim($_GET["origen"]) : ""; // 'clientes', 'internos'
        $whereClauses = [];
        $params = [];
        $types = "";

        if ($estado !== "") {
            if ($estado === "TERMINADOS" || $estado === "HISTORIAL") {
                $whereClauses[] = "st.estado IN ('ENTREGADO', 'LISTO_PARA_RECOGER')";
            } elseif (strpos($estado, ',') !== false) {
                $estadosArr = array_map('trim', explode(',', $estado));
                $placeholders = implode(',', array_fill(0, count($estadosArr), '?'));
                $whereClauses[] = "st.estado IN ($placeholders)";
                foreach ($estadosArr as $est) {
                    $types .= "s";
                    $params[] = $est;
                }
            } else {
                $whereClauses[] = "st.estado = ?";
                $types .= "s";
                $params[] = $estado;
            }
        } else {
            $whereClauses[] = "st.estado != 'ELIMINADO'";
        }

        if ($origen === "clientes") {
            $whereClauses[] = "st.es_externo = 1";
        } elseif ($origen === "internos") {
            $whereClauses[] = "st.es_externo IN (0, 2)";
        }

        $where = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        $sql = "SELECT st.id, st.numero_atencion, st.equipo_codigo, st.equipo_serie, st.equipo_descripcion,
                st.es_externo, st.motivo_ingreso, st.diagnostico, st.solucion, st.notas_internas, st.tiempo_estimado,
                st.estado, st.prioridad, st.en_garantia, st.meses_garantia_restantes,
                st.fecha_ingreso, st.fecha_estimada, st.fecha_entrega, st.monto_cobrado, st.metodo_pago,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido,''))), ''), IF(st.es_externo=2, 'Tarea Interna', 'Stock Propio / Taller')) as cliente_nombre,
                COALESCE(c.dni, '-') as cliente_dni, 
                COALESCE(c.telefono, '-') as cliente_tel,
                CONCAT(COALESCE(t.nombre,''),IF(t.apellido IS NOT NULL AND t.apellido != '',' ',''),COALESCE(t.apellido,'')) as tecnico_nombre,
                st.cliente_id, st.tecnico_id, st.tecnicos_adicionales
                FROM soporte_tecnico st
                LEFT JOIN personas c ON st.cliente_id = c.id
                LEFT JOIN personas t ON st.tecnico_id = t.id
                $where
                ORDER BY FIELD(st.estado,'PENDIENTE','EN_DIAGNOSTICO','ESPERANDO_REPUESTO','EN_REPARACION','LISTO_PARA_RECOGER','ENTREGADO','CANCELADO'), st.prioridad DESC, st.fecha_ingreso DESC";

        if (!empty($params)) {
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $db->query($sql);
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    case "ver":
        $id = (int)($_GET["id"] ?? 0);
        $stmt = $db->prepare("SELECT st.*, 
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido,''))), ''), IF(st.es_externo=2, 'Tarea Interna', 'Stock Propio / Taller')) as cliente_nombre,
                COALESCE(c.dni, '-') as cliente_dni, 
                COALESCE(c.telefono, '-') as cliente_tel,
                CONCAT(COALESCE(t.nombre,''),' ',COALESCE(t.apellido,'')) as tecnico_nombre
                FROM soporte_tecnico st
                LEFT JOIN personas c ON st.cliente_id = c.id
                LEFT JOIN personas t ON st.tecnico_id = t.id
                WHERE st.id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) echo json_encode(["ok" => true, "data" => $row]);
        else echo json_encode(["ok" => false, "msg" => "No encontrado"]);
        break;

    case "crear":
        $data = json_decode(file_get_contents("php://input"), true);
        $fecha = date("Ymd");
        
        // --- INICIO GENERACION SEGURA CONCURRENTE ---
        $db->query("INSERT INTO secuencias_tickets (fecha_str, ultimo_valor) VALUES ('$fecha', 1) ON DUPLICATE KEY UPDATE ultimo_valor = ultimo_valor + 1");
        $res = $db->query("SELECT ultimo_valor FROM secuencias_tickets WHERE fecha_str = '$fecha'");
        $row = $res->fetch_assoc();
        $seq = str_pad((int)$row["ultimo_valor"], 3, "0", STR_PAD_LEFT);
        $numero = "ST-$fecha-$seq";
        // --- FIN GENERACION SEGURA CONCURRENTE ---

        $stmt = $db->prepare("INSERT INTO soporte_tecnico (numero_atencion, cliente_id, equipo_codigo, equipo_serie, equipo_descripcion, es_externo, motivo_ingreso, prioridad, fecha_estimada, tiempo_estimado, tecnico_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $cliente_id = (int)$data["cliente_id"];
        $eq_cod = $data["equipo_codigo"] ?? "";
        $eq_ser = $data["equipo_serie"] ?? "";
        $eq_desc = $data["equipo_descripcion"] ?? "";
        $es_ext = (int)($data["es_externo"] ?? 0);
        $motivo = $data["motivo_ingreso"] ?? "";
        $prior = $data["prioridad"] ?? "SIN PRIORIDAD";
        $fecha_est = !empty($data["fecha_estimada"]) ? $data["fecha_estimada"] : null;
        $tiempo_est = !empty($data["tiempo_estimado"]) ? trim($data["tiempo_estimado"]) : null;
        
        // Segun peticion: Por defecto se deja sin asignar
        $tec_id = !empty($data["tecnico_id"]) ? (int)$data["tecnico_id"] : null;

        $stmt->bind_param("sisssissssi", $numero, $cliente_id, $eq_cod, $eq_ser, $eq_desc, $es_ext, $motivo, $prior, $fecha_est, $tiempo_est, $tec_id);
        if ($stmt->execute()) {
            $insert_id = $db->insert_id;
            
            // Trazabilidad inicial en historial_cambios
            $usuario_creador = $data["usuario"] ?? ($_SESSION['user_nombre'] ?? 'Recepción');
            $stmt_h = $db->prepare("INSERT INTO historial_cambios (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre) VALUES ('soporte_tecnico', ?, ?, 'estado', NULL, 'PENDIENTE', ?)");
            if ($stmt_h) {
                $stmt_h->bind_param("iss", $insert_id, $numero, $usuario_creador);
                $stmt_h->execute();
            }

            echo json_encode(["ok" => true, "id" => $insert_id, "numero" => $numero, "msg" => "Atencion $numero creada" . ($tec_id ? "" : " (Sin asignar)")]);
            // Push notification to assigned technician
            if ($tec_id) {
                try {
                    require_once 'push.php';
                    sendPushToUser($db, $tec_id, "\xF0\x9F\x93\x8B Nueva Orden Asignada", "Orden $numero te fue asignada", '/mis_ordenes.html');
                } catch (Exception $e) { /* silent */ }
                $db->query("INSERT INTO notificaciones (usuario_id, titulo, mensaje, link) VALUES ($tec_id, 'Nueva Orden', 'La orden $numero te fue asignada', 'mis_ordenes.html')");
            }
            // Notify admins
            try {
                require_once 'push.php';
                sendPushToAdmins($db, "\xF0\x9F\x93\x8B Nueva Orden", "Orden $numero ingresada: $eq_desc", '/soporte.html');
            } catch (Exception $e) { /* silent */ }
            $db->query("INSERT INTO notificaciones (usuario_id, titulo, mensaje, link) SELECT id, 'Nuevo Ticket', 'Ticket $numero ha ingresado', 'soporte.html' FROM personas WHERE tipo='admin' AND estado='ACTIVO'");
        }
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "crear_tarea":
        $data = json_decode(file_get_contents("php://input"), true);
        $fecha = date("Y");
        $res = $db->query("SELECT COUNT(*) as c FROM soporte_tecnico WHERE numero_atencion LIKE 'TAR-$fecha-%'");
        $row = $res->fetch_assoc();
        $seq = str_pad((int)$row["c"] + 1, 3, "0", STR_PAD_LEFT);
        $numero = "TAR-$fecha-$seq";

        $cliente_id = (int)($_SESSION['user_id'] ?? 1);
        $motivo = $data["titulo"] ?? "";
        $diag = $data["descripcion"] ?? "";
        $es_ext = 2; // Indicador de Tarea Interna
        $prior = !empty($data["prioridad"]) ? $db->real_escape_string($data["prioridad"]) : "SIN PRIORIDAD";
        $tiempo_est = !empty($data["tiempo_estimado"]) ? trim($data["tiempo_estimado"]) : null;
        $notas_internas = !empty($tiempo_est) ? "[Tiempo Estimado: $tiempo_est]" : null;
        $tec_id = !empty($data["tecnico_id"]) ? (int)$data["tecnico_id"] : null;
        
        $stmt = $db->prepare("INSERT INTO soporte_tecnico (numero_atencion, cliente_id, es_externo, motivo_ingreso, diagnostico, prioridad, tiempo_estimado, notas_internas, tecnico_id) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("siisssssi", $numero, $cliente_id, $es_ext, $motivo, $diag, $prior, $tiempo_est, $notas_internas, $tec_id);
        
        if ($stmt->execute()) {
            $nuevo_id = $db->insert_id;
            
            // Trazabilidad inicial en historial_cambios
            $usuario_creador = $data["usuario"] ?? ($_SESSION['user_nombre'] ?? 'Administrador');
            $stmt_h = $db->prepare("INSERT INTO historial_cambios (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre) VALUES ('soporte_tecnico', ?, ?, 'estado', NULL, 'PENDIENTE', ?)");
            if ($stmt_h) {
                $stmt_h->bind_param("iss", $nuevo_id, $numero, $usuario_creador);
                $stmt_h->execute();
            }

            echo json_encode(["ok" => true, "id" => $nuevo_id, "numero" => $numero, "msg" => "Tarea $numero asignada"]);
            // Push notification to assigned technician
            if ($tec_id) {
                try {
                    require_once 'push.php';
                    sendPushToUser($db, $tec_id, "\xF0\x9F\x94\xA7 Nueva Tarea", "Tarea $numero asignada a ti: $motivo", '/mis_ordenes.html');
                } catch (Exception $e) { /* silent */ }
            }
            // Also notify admins about new task creation
            try {
                require_once 'push.php';
                sendPushToAdmins($db, "\xF0\x9F\x93\x8C Tarea Creada", "$numero: $motivo", '/mis_ordenes.html');
            } catch (Exception $e) { /* silent */ }
            $notif_msg = "$numero: $motivo";
            $stmt_notif = $db->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, link) SELECT id, 'Nueva Tarea', ?, 'mis_ordenes.html' FROM personas WHERE tipo='admin' AND estado='ACTIVO'");
            $stmt_notif->bind_param("s", $notif_msg);
            $stmt_notif->execute();
        }
        else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "crear_interno":
        $data = json_decode(file_get_contents("php://input"), true);
        $cod = trim($data["equipo_codigo"] ?? "");
        $tec_id = !empty($data["tecnico_id"]) ? (int)$data["tecnico_id"] : null;
        
        // Buscar el equipo y su falla en la BD
        $stmtEq = $db->prepare("SELECT id, serie, descripcion, falla FROM equipos WHERE codigo=? LIMIT 1");
        $stmtEq->bind_param("s", $cod);
        $stmtEq->execute();
        $resEq = $stmtEq->get_result();
        if ($resEq->num_rows == 0) {
            echo json_encode(["ok" => false, "msg" => "Equipo no existe en inventario"]);
            break;
        }
        $eq = $resEq->fetch_assoc();
        $falla = $eq["falla"] ?? "Asignado para revision interna";
        
        // Auto-asignacion si no hay tecnico (reusando logica)
        if ($tec_id === null) {
            $dia_actual = date('l'); 
            $dias_map = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miercoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sabado','Sunday'=>'Domingo'];
            $dia_es = $dias_map[$dia_actual] ?? $dia_actual;
            $hora_actual = date('H:i:s');
            
            $turnos = $db->query("SELECT p.id, COUNT(st.id) as tickets_pendientes
                                  FROM personas p 
                                  JOIN turnos t ON p.id = t.tecnico_id AND t.activo = 1 AND FIND_IN_SET('$dia_es', t.dias_trabajo) AND '$hora_actual' BETWEEN t.hora_inicio AND t.hora_fin
                                  LEFT JOIN soporte_tecnico st ON p.id = st.tecnico_id AND st.estado IN ('PENDIENTE', 'EN_DIAGNOSTICO', 'EN_REPARACION')
                                  WHERE p.tipo = 'tecnico' AND p.activo = 1
                                  GROUP BY p.id ORDER BY tickets_pendientes ASC LIMIT 1");
            if ($turnos->num_rows > 0) $tec_id = (int)$turnos->fetch_assoc()["id"];
        }

        $fecha = date("Ymd");
        $res = $db->query("SELECT COUNT(*) as c FROM soporte_tecnico WHERE numero_atencion LIKE 'ST-INT-$fecha-%'");
        $row = $res->fetch_assoc();
        $seq = str_pad((int)$row["c"] + 1, 3, "0", STR_PAD_LEFT);
        $numero = "ST-INT-$fecha-$seq";

        $cliente_id = 1; // Petulap
        $es_ext = 0; // Interno / Propio
        $motivo = "Revision interna por falla en Lote/Inventario";
        $prior = "SIN PRIORIDAD";
        
        $stmt = $db->prepare("INSERT INTO soporte_tecnico (numero_atencion, cliente_id, equipo_codigo, equipo_serie, equipo_descripcion, es_externo, motivo_ingreso, diagnostico, prioridad, tecnico_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $eq_ser = $eq["serie"] ?? "";
        $eq_desc = $eq["descripcion"] ?? "Equipo Interno";
        $stmt->bind_param("sisssisssi", $numero, $cliente_id, $cod, $eq_ser, $eq_desc, $es_ext, $motivo, $falla, $prior, $tec_id);
        
        if ($stmt->execute()) {
            $nuevo_id = $db->insert_id;
            
            // Trazabilidad inicial en historial_cambios
            $usuario_creador = $data["usuario"] ?? ($_SESSION['user_nombre'] ?? 'Sistema');
            $stmt_h = $db->prepare("INSERT INTO historial_cambios (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre) VALUES ('soporte_tecnico', ?, ?, 'estado', NULL, 'PENDIENTE', ?)");
            if ($stmt_h) {
                $stmt_h->bind_param("iss", $nuevo_id, $numero, $usuario_creador);
                $stmt_h->execute();
            }

            echo json_encode(["ok" => true, "id" => $nuevo_id, "numero" => $numero, "msg" => "Asignado correctamente"]);
        } else {
            echo json_encode(["ok" => false, "msg" => $db->error]);
        }
        break;

    case "actualizar":
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int)($data["id"] ?? 0);

        // Obtener valores anteriores para historial
        $ant_res = $db->query("SELECT estado, tecnico_id, prioridad, numero_atencion, equipo_descripcion, motivo_ingreso, es_externo FROM soporte_tecnico WHERE id=$id LIMIT 1");
        $ant = $ant_res->fetch_assoc();

        $fields = []; $values = []; $types = "";
        $allowed = ["estado","prioridad","diagnostico","solucion","notas_internas","tecnico_id","tecnicos_adicionales",
                    "equipo_codigo","equipo_serie","equipo_descripcion","es_externo","motivo_ingreso",
                    "fecha_estimada","en_garantia",
                    "repuesto_nombre","repuesto_pn","repuesto_precio",
                    "repuesto_pagado","repuesto_comprobante","repuesto_fecha_llegada_aprox",
                    "monto_cobrado","metodo_pago"];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f=?"; $types .= "s"; $values[] = $data[$f]; }
        }
        // Fechas automaticas por cambio de estado
        if (isset($data["estado"])) {
            $nuevoEstado = trim($data["estado"]);
            if (in_array($nuevoEstado, ["ENTREGADO", "LISTO_PARA_RECOGER", "COMPLETADO", "LISTO_PARA_ENTREGA"])) {
                $fields[] = "fecha_entrega=NOW()";
                if ($nuevoEstado === "ENTREGADO") {
                    // Registrar quien cobro
                    $cobrado_por = (int)($_SESSION['user_id'] ?? 0);
                    if ($cobrado_por) { $fields[] = "cobrado_por=$cobrado_por"; }
                }
            }
            if ($nuevoEstado === "ESPERANDO_REPUESTO" && ($ant["estado"] ?? '') !== "ESPERANDO_REPUESTO") {
                $fields[] = "fecha_pedido_repuesto=NOW()";
            }
        }
        // Confirmar llegada del repuesto
        if (!empty($data["confirmar_llegada"])) {
            $fields[] = "fecha_llegada_repuesto=NOW()";
        }
        if (!$fields) { echo json_encode(["ok" => false, "msg" => "Nada que actualizar"]); break; }
        $types .= "i"; $values[] = $id;
        $stmt = $db->prepare("UPDATE soporte_tecnico SET " . implode(",", $fields) . " WHERE id=?");
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) {
            // Registrar historial de cambios relevantes
            $num_ref = $ant["numero_atencion"] ?? "";
            $usuario = $data["usuario"] ?? "Sistema";
            $campos_loggear = ["estado","prioridad","tecnico_id"];
            foreach ($campos_loggear as $campo) {
                if (isset($data[$campo]) && $data[$campo] != ($ant[$campo] ?? null)) {
                    $stmt_h = $db->prepare("INSERT INTO historial_cambios (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre) VALUES ('soporte_tecnico',?,?,?,?,?,?)");
                    $v_ant = $ant[$campo] ?? null;
                    $v_nuevo = $data[$campo];
                    $stmt_h->bind_param("isssss", $id, $num_ref, $campo, $v_ant, $v_nuevo, $usuario);
                    $stmt_h->execute();
                }
            }
            echo json_encode(["ok" => true, "msg" => "Actualizado"]);
            // Push notification when task/order completed
            if (isset($data['estado']) && ($data['estado'] === 'LISTO_PARA_RECOGER' || $data['estado'] === 'ENTREGADO')) {
                try {
                    require_once 'push.php';
                    $num = $ant['numero_atencion'] ?? '';
                    $es_tarea = ($ant['es_externo'] == 2 || strpos($num, 'TAR') === 0);
                    $titulo_tk = $es_tarea ? ($ant['motivo_ingreso'] ?: 'Tarea Interna') : ($ant['equipo_descripcion'] ?: 'Equipo en Soporte');
                    
                    $tecNombre = $data['usuario'] ?? 'Tecnico';
                    $emoji = $data['estado'] === 'ENTREGADO' ? "\xF0\x9F\x93\xA6" : "\xE2\x9C\x85";
                    sendPushToAdmins($db, "$emoji $titulo_tk", "Ticket: $num\nMarcada como " . str_replace('_', ' ', $data['estado']) . " por $tecNombre", '/mis_ordenes.html');
                } catch (Exception $e) { /* silent */ }
                $notif_msg = "El ticket $num cambió a " . ($data['estado'] ?? '');
                $stmt_upd_notif = $db->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, link) SELECT id, 'Ticket Actualizado', ?, 'mis_ordenes.html' FROM personas WHERE tipo='admin' AND estado='ACTIVO'");
                $stmt_upd_notif->bind_param("s", $notif_msg);
                $stmt_upd_notif->execute();
            }
            // Notify technician when a ticket is assigned/reassigned to them
            if (isset($data['tecnico_id']) && $data['tecnico_id'] != ($ant['tecnico_id'] ?? null) && $data['tecnico_id']) {
                try {
                    require_once 'push.php';
                    $num = $ant['numero_atencion'] ?? '';
                    $es_tarea = ($ant['es_externo'] == 2 || strpos($num, 'TAR') === 0);
                    $titulo_tk = $es_tarea ? ($ant['motivo_ingreso'] ?: 'Tarea Interna') : ($ant['equipo_descripcion'] ?: 'Equipo en Soporte');
                    
                    sendPushToUser($db, (int)$data['tecnico_id'], "\xF0\x9F\x93\x8B $titulo_tk", "Ticket: $num te fue asignado.", '/mis_ordenes.html');
                } catch (Exception $e) { /* silent */ }
                $tid = (int)$data['tecnico_id'];
                $db->query("INSERT INTO notificaciones (usuario_id, titulo, mensaje, link) VALUES ($tid, 'Ticket Asignado', 'El ticket $num te fue asignado', 'mis_ordenes.html')");
            }
        } else echo json_encode(["ok" => false, "msg" => $db->error]);
        break;

    case "eliminar":
        $id = (int)($_GET["id"] ?? 0);
        $usuario = $db->real_escape_string($_GET["usuario"] ?? 'Admin');
        
        $res_num = $db->query("SELECT numero_atencion FROM soporte_tecnico WHERE id=$id");
        $num_ref = $res_num->fetch_assoc()["numero_atencion"] ?? "";

        // Soft delete: cambiar estado a ELIMINADO
        $db->query("UPDATE soporte_tecnico SET estado='ELIMINADO' WHERE id=$id");
        
        // Registrar en historial
        $stmt_h = $db->prepare("INSERT INTO historial_cambios (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre) VALUES ('soporte_tecnico',?,?,?,?,?,?)");
        $campo = 'estado';
        $v_ant = 'ACTIVO';
        $v_nuevo = 'ELIMINADO';
        $stmt_h->bind_param("isssss", $id, $num_ref, $campo, $v_ant, $v_nuevo, $usuario);
        $stmt_h->execute();

        echo json_encode(["ok" => true, "msg" => "Eliminado (Cancelado) correctamente"]);
        break;

    case "buscar_equipo":
        $q = trim($_GET["q"] ?? "");
        $stmt = $db->prepare("SELECT id, codigo, serie, marca, modelo, procesador, ram, hd_ssd, estado, observacion, doc_compra, fec_venta FROM equipos WHERE codigo=? OR serie=? LIMIT 5");
        $stmt->bind_param("ss", $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            // Detectar garantia: fec_venta dentro de los ultimos 6 meses
            $en_garantia = false;
            $meses_restantes = null;
            if (!empty($row["fec_venta"])) {
                $venta = new DateTime($row["fec_venta"]);
                $hoy = new DateTime();
                $diff_dias = $hoy->diff($venta)->days;
                $meses_transcurridos = $diff_dias / 30.44;
                if ($meses_transcurridos <= 6 && $venta <= $hoy) {
                    $en_garantia = true;
                    $meses_restantes = round(6 - $meses_transcurridos, 1);
                }
            }
            $row["en_garantia"] = $en_garantia;
            $row["meses_garantia_restantes"] = $meses_restantes;
            $rows[] = $row;
        }
        echo json_encode(["ok" => true, "data" => $rows, "count" => count($rows)]);
        break;

    case "buscar_barcode":
        // Para la caja: buscar ticket por numero o codigo de equipo
        $q = trim($_GET["q"] ?? "");
        $sql = "SELECT st.*, 
                       CONCAT(p.nombre,' ',IFNULL(p.apellido,'')) AS cliente_nombre,
                       p.telefono AS cliente_tel, p.dni AS cliente_dni,
                       CONCAT(IFNULL(tp.nombre,''),' ',IFNULL(tp.apellido,'')) AS tecnico_nombre
                FROM soporte_tecnico st
                JOIN personas p ON st.cliente_id = p.id
                LEFT JOIN personas tp ON st.tecnico_id = tp.id
                WHERE (st.numero_atencion=? OR st.equipo_codigo=?)
                  AND st.estado = 'LISTO_PARA_RECOGER'
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $q, $q);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            echo json_encode(["ok" => true, "data" => $res->fetch_assoc()]);
        } else {
            // Buscar en cualquier estado para dar info
            $sql2 = "SELECT st.estado, st.numero_atencion, CONCAT(p.nombre,' ',IFNULL(p.apellido,'')) AS cliente_nombre
                     FROM soporte_tecnico st JOIN personas p ON st.cliente_id = p.id
                     WHERE st.numero_atencion=? OR st.equipo_codigo=? LIMIT 1";
            $stmt2 = $db->prepare($sql2);
            $stmt2->bind_param("ss", $q, $q);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($res2->num_rows > 0) {
                $row = $res2->fetch_assoc();
                echo json_encode(["ok" => false, "msg" => "Equipo encontrado pero estado: " . $row['estado'] . " (no esta LISTO_PARA_RECOGER)", "data" => $row]);
            } else {
                echo json_encode(["ok" => false, "msg" => "Ticket o codigo de equipo no encontrado"]);
            }
        }
        break;

    case "repuestos_pendientes":
        // Vista compacta de todos los tickets en ESPERANDO_REPUESTO
        $sql = "SELECT st.id, st.numero_atencion, st.repuesto_nombre, st.repuesto_pn, st.repuesto_precio,
                       st.fecha_pedido_repuesto, st.fecha_llegada_repuesto, st.equipo_codigo, st.equipo_descripcion,
                       st.repuesto_pagado, st.repuesto_comprobante, st.repuesto_fecha_llegada_aprox,
                       CONCAT(p.nombre,' ',IFNULL(p.apellido,'')) AS cliente_nombre,
                       CONCAT(tp.nombre,' ',IFNULL(tp.apellido,'')) AS tecnico_nombre
                FROM soporte_tecnico st
                JOIN personas p ON st.cliente_id = p.id
                LEFT JOIN personas tp ON st.tecnico_id = tp.id
                WHERE st.estado = 'ESPERANDO_REPUESTO'
                ORDER BY st.fecha_pedido_repuesto ASC";
        $res = $db->query($sql);
        $rows = [];
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        echo json_encode(["ok" => true, "data" => $rows]);
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
}
$db->close();
