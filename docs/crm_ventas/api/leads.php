<?php
// ==========================================================
// PETULAP SALES CRM - GESTIÓN DE LEADS Y EMBUSO KANBAN
// Endpoints REST para tarjetas, cambios de etapa y cálculo SLA
// ==========================================================

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'list';
$is_pdo = ($conn instanceof PDO);

// ----------------------------------------------------------
// 1. LISTAR LEADS CON CÁLCULO DINÁMICO DE TEMPERATURA / SLA
// ----------------------------------------------------------
if ($action === 'list') {
    $filtro_etapa = $_GET['etapa'] ?? '';
    $filtro_vendedor = $_GET['vendedor_id'] ?? '';
    $filtro_search = $_GET['search'] ?? '';

    $query = "SELECT * FROM crm_leads WHERE 1=1";
    $params = [];

    if (!empty($filtro_etapa)) {
        $query .= " AND etapa = ?";
        $params[] = $filtro_etapa;
    }
    if (!empty($filtro_vendedor)) {
        $query .= " AND vendedor_id = ?";
        $params[] = (int)$filtro_vendedor;
    }
    if (!empty($filtro_search)) {
        $query .= " AND (nombre LIKE ? OR telefono LIKE ? OR modelo_interes_texto LIKE ?)";
        $search_term = "%$filtro_search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    $query .= " ORDER BY fecha_actualizacion DESC";

    $leads = [];
    if ($is_pdo) {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $leads[] = $row;
        }
    }

    // Calcular en caliente la temperatura según las horas transcurridas
    $now = new DateTime();
    foreach ($leads as &$lead) {
        $ultimo_dt = new DateTime($lead['ultimo_mensaje_hora'] ?? $lead['fecha_creacion']);
        $diff = $now->diff($ultimo_dt);
        $horas = ($diff->days * 24) + $diff->h;
        $lead['horas_sin_contacto'] = $horas;

        // Regla inteligente anti-olvido:
        // Si tiene cita agendada o está en VISITA_SEPARADO -> PURPURA
        // Si el cliente fue el último en escribir -> VERDE urgente (atenderlo ya!)
        // Si Petulap fue el último en escribir:
        //   < 6h -> VERDE
        //   6h a 24h -> AMBAR (esperando respuesta)
        //   > 24h -> ROJO (cliente frío, dejado en visto)
        if ($lead['etapa'] === 'VISITA_SEPARADO' || ($lead['temperatura'] ?? '') === 'PURPURA') {
            $lead['temperatura'] = 'PURPURA';
        } elseif ($lead['etapa'] === 'GANADO') {
            $lead['temperatura'] = 'VERDE';
        } elseif ($lead['etapa'] === 'PERDIDO') {
            $lead['temperatura'] = 'ROJO';
        } elseif (($lead['ultimo_mensaje_emisor'] ?? 'CLIENTE') === 'CLIENTE') {
            $lead['temperatura'] = 'VERDE';
        } else {
            if ($horas < 6) {
                $lead['temperatura'] = 'VERDE';
            } elseif ($horas < 24) {
                $lead['temperatura'] = 'AMBAR';
            } else {
                $lead['temperatura'] = 'ROJO';
            }
        }
    }

    json_resp(['success' => true, 'total' => count($leads), 'data' => $leads]);
}

// ----------------------------------------------------------
// 2. CREAR NUEVO LEAD
// ----------------------------------------------------------
if ($action === 'crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $nombre = trim($input['nombre'] ?? '');
    $telefono = preg_replace('/[^0-9]/', '', $input['telefono'] ?? '');
    $etapa = $input['etapa'] ?? 'NUEVO';
    $modelo = trim($input['modelo_interes_texto'] ?? '');
    $presupuesto = (float)($input['presupuesto_aprox'] ?? 0);
    $origen = $input['origen_lead'] ?? 'WHATSAPP';
    $sede = $input['sede_preferida'] ?? 'YANAHUARA';
    $notas = trim($input['notas'] ?? '');
    $vendedor_id = (int)($input['vendedor_id'] ?? 1);

    if (empty($nombre) || empty($telefono)) {
        json_resp(['success' => false, 'error' => 'Nombre y teléfono son obligatorios'], 400);
    }

    // Normalizar número a formato Perú si es de 9 dígitos
    if (strlen($telefono) === 9) {
        $telefono = '51' . $telefono;
    }

    $sql = "INSERT INTO crm_leads (nombre, telefono, etapa, temperatura, modelo_interes_texto, presupuesto_aprox, origen_lead, sede_preferida, notas, vendedor_id, ultimo_mensaje_emisor, ultimo_mensaje_hora) 
            VALUES (?, ?, ?, 'VERDE', ?, ?, ?, ?, ?, ?, 'CLIENTE', CURRENT_TIMESTAMP)";

    if ($is_pdo) {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nombre, $telefono, $etapa, $modelo, $presupuesto, $origen, $sede, $notas, $vendedor_id]);
        $new_id = $conn->lastInsertId();
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssdsssi", $nombre, $telefono, $etapa, $modelo, $presupuesto, $origen, $sede, $notas, $vendedor_id);
        $stmt->execute();
        $new_id = $conn->insert_id;
    }

    json_resp(['success' => true, 'message' => 'Lead registrado exitosamente', 'id' => $new_id]);
}

// ----------------------------------------------------------
// 3. CAMBIAR ETAPA EN EL KANBAN
// ----------------------------------------------------------
if ($action === 'cambiar_etapa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $lead_id = (int)($input['id'] ?? 0);
    $nueva_etapa = $input['etapa'] ?? '';

    $etapas_validas = ['NUEVO', 'ASESORIA', 'COTIZADO', 'VISITA_SEPARADO', 'GANADO', 'PERDIDO'];
    if (!in_array($nueva_etapa, $etapas_validas) || $lead_id <= 0) {
        json_resp(['success' => false, 'error' => 'Parámetros inválidos'], 400);
    }

    $sql = "UPDATE crm_leads SET etapa = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = ?";
    if ($is_pdo) {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nueva_etapa, $lead_id]);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nueva_etapa, $lead_id);
        $stmt->execute();
    }

    json_resp(['success' => true, 'message' => "Lead movido a $nueva_etapa"]);
}

// ----------------------------------------------------------
// 4. RESUMEN DE MÉTRICAS (KPIs COMERCIALES)
// ----------------------------------------------------------
if ($action === 'stats') {
    $sql = "SELECT etapa, temperatura, presupuesto_aprox FROM crm_leads";
    $rows = [];
    if ($is_pdo) {
        $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }

    $stats = [
        'total' => count($rows),
        'nuevos' => 0,
        'en_asesoria' => 0,
        'cotizados' => 0,
        'separados' => 0,
        'ganados' => 0,
        'perdidos' => 0,
        'rojos_frios' => 0,
        'monto_proyectado' => 0.0,
        'monto_cerrado' => 0.0
    ];

    foreach ($rows as $r) {
        $et = $r['etapa'] ?? 'NUEVO';
        $monto = (float)($r['presupuesto_aprox'] ?? 0);
        if ($et === 'NUEVO') $stats['nuevos']++;
        if ($et === 'ASESORIA') $stats['en_asesoria']++;
        if ($et === 'COTIZADO') { $stats['cotizados']++; $stats['monto_proyectado'] += $monto; }
        if ($et === 'VISITA_SEPARADO') { $stats['separados']++; $stats['monto_proyectado'] += $monto; }
        if ($et === 'GANADO') { $stats['ganados']++; $stats['monto_cerrado'] += $monto; }
        if ($et === 'PERDIDO') $stats['perdidos']++;
        if (($r['temperatura'] ?? '') === 'ROJO' && $et !== 'GANADO' && $et !== 'PERDIDO') {
            $stats['rojos_frios']++;
        }
    }

    json_resp(['success' => true, 'stats' => $stats]);
}

// ----------------------------------------------------------
// 5. AGENDAR CITA O LLAMADA (TEMPERATURA PÚRPURA)
// ----------------------------------------------------------
if ($action === 'agendar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $lead_id = (int)($input['lead_id'] ?? 0);
    $tipo = $input['tipo'] ?? 'VISITA_YANAHUARA';
    $fecha_hora = $input['fecha_hora'] ?? date('Y-m-d H:i:s');
    $modelo_laptop = trim($input['modelo_laptop'] ?? '');
    $notas = trim($input['notas'] ?? '');
    $vendedor_id = (int)($input['vendedor_id'] ?? 1);

    if ($lead_id <= 0) {
        json_resp(['success' => false, 'error' => 'Lead ID requerido'], 400);
    }

    // Insertar en crm_agendamientos
    $sql_ag = "INSERT INTO crm_agendamientos (lead_id, vendedor_id, tipo, fecha_hora, modelo_laptop, notas, estado) 
               VALUES (?, ?, ?, ?, ?, ?, 'PENDIENTE')";
    if ($is_pdo) {
        $stmt = $conn->prepare($sql_ag);
        $stmt->execute([$lead_id, $vendedor_id, $tipo, $fecha_hora, $modelo_laptop, $notas]);
        $ag_id = $conn->lastInsertId();
    } else {
        $stmt = $conn->prepare($sql_ag);
        $stmt->bind_param("iissss", $lead_id, $vendedor_id, $tipo, $fecha_hora, $modelo_laptop, $notas);
        $stmt->execute();
        $ag_id = $conn->insert_id;
    }

    // Actualizar etapa a VISITA_SEPARADO y temperatura a PURPURA
    $sql_lead = "UPDATE crm_leads 
                 SET etapa = 'VISITA_SEPARADO', 
                     temperatura = 'PURPURA', 
                     modelo_interes_texto = COALESCE(NULLIF(?, ''), modelo_interes_texto),
                     fecha_actualizacion = CURRENT_TIMESTAMP 
                 WHERE id = ?";
    if ($is_pdo) {
        $stmt_l = $conn->prepare($sql_lead);
        $stmt_l->execute([$modelo_laptop, $lead_id]);
    } else {
        $stmt_l = $conn->prepare($sql_lead);
        $stmt_l->bind_param("si", $modelo_laptop, $lead_id);
        $stmt_l->execute();
    }

    json_resp([
        'success' => true, 
        'message' => 'Cita agendada correctamente', 
        'agendamiento_id' => $ag_id,
        'temperatura' => 'PURPURA'
    ]);
}

// ----------------------------------------------------------
// 6. RECORDATORIOS DE HOY (CITAS PRÓXIMAS + LEADS FRÍOS)
// ----------------------------------------------------------
if ($action === 'recordatorios_hoy') {
    $hoy = date('Y-m-d');
    
    // 1. Citas pendientes para hoy o próximas
    $sql_citas = "SELECT a.*, l.nombre AS cliente_nombre, l.telefono, l.sede_preferida, l.presupuesto_aprox 
                  FROM crm_agendamientos a
                  JOIN crm_leads l ON a.lead_id = l.id
                  WHERE a.estado = 'PENDIENTE' AND DATE(a.fecha_hora) = ?
                  ORDER BY a.fecha_hora ASC";
    
    $citas = [];
    if ($is_pdo) {
        $stmt = $conn->prepare($sql_citas);
        $stmt->execute([$hoy]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare($sql_citas);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $citas[] = $row;
    }

    // 2. Clientes fríos que requieren llamada de reactivación
    $sql_frios = "SELECT id, nombre, telefono, modelo_interes_texto, presupuesto_aprox, ultimo_mensaje_hora 
                  FROM crm_leads 
                  WHERE etapa NOT IN ('GANADO', 'PERDIDO') 
                    AND (temperatura = 'ROJO' OR ultimo_mensaje_hora <= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                  ORDER BY ultimo_mensaje_hora ASC LIMIT 10";
    
    $frios = [];
    if ($is_pdo) {
        $frios = $conn->query($sql_frios)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $conn->query($sql_frios);
        while ($row = $res->fetch_assoc()) $frios[] = $row;
    }

    json_resp([
        'success' => true,
        'total_pendientes' => count($citas) + count($frios),
        'citas_hoy' => $citas,
        'clientes_frios' => $frios
    ]);
}

// ----------------------------------------------------------
// 7. COMPLETAR O CANCELAR AGENDAMIENTO
// ----------------------------------------------------------
if ($action === 'completar_agendamiento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $ag_id = (int)($input['id'] ?? 0);
    $nuevo_estado = $input['estado'] ?? 'COMPLETADO'; // COMPLETADO, NO_ASISTIO, CANCELADO
    $cerrar_venta = !empty($input['venta_cerrada']);

    $sql = "UPDATE crm_agendamientos SET estado = ? WHERE id = ?";
    if ($is_pdo) {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nuevo_estado, $ag_id]);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nuevo_estado, $ag_id);
        $stmt->execute();
    }

    if ($cerrar_venta) {
        $sql_lead = "UPDATE crm_leads SET etapa = 'GANADO', temperatura = 'VERDE', fecha_actualizacion = CURRENT_TIMESTAMP 
                     WHERE id = (SELECT lead_id FROM crm_agendamientos WHERE id = ?)";
        if ($is_pdo) {
            $stmt_l = $conn->prepare($sql_lead);
            $stmt_l->execute([$ag_id]);
        } else {
            $stmt_l = $conn->prepare($sql_lead);
            $stmt_l->bind_param("i", $ag_id);
            $stmt_l->execute();
        }
    }

    json_resp(['success' => true, 'message' => 'Agendamiento actualizado']);
}

// ----------------------------------------------------------
// 8. RANKING COMERCIAL Y COMPARATIVA DE SEDES
// ----------------------------------------------------------
if ($action === 'ranking_asesores') {
    $sql = "SELECT id, nombre, etapa, temperatura, sede_preferida, presupuesto_aprox, vendedor_id FROM crm_leads";
    $rows = [];
    if ($is_pdo) {
        $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }

    $sedes = [
        'YANAHUARA' => ['nombre' => 'Sede Yanahuara (Av. Ejército 314)', 'leads' => 0, 'ganados' => 0, 'facturado' => 0.0, 'citas' => 0],
        'CAYMA' => ['nombre' => 'Sede Cayma (León XIII Mza A-4)', 'leads' => 0, 'ganados' => 0, 'facturado' => 0.0, 'citas' => 0],
        'ENVIO_PROVINCIA' => ['nombre' => 'Envíos a Provincia', 'leads' => 0, 'ganados' => 0, 'facturado' => 0.0, 'citas' => 0]
    ];

    $vendedores = [
        1 => ['id' => 1, 'nombre' => 'Asesor General', 'leads' => 0, 'ganados' => 0, 'facturado' => 0.0, 'citas' => 0, 'frios' => 0],
        2 => ['id' => 2, 'nombre' => 'Ventas Yanahuara', 'leads' => 0, 'ganados' => 0, 'facturado' => 0.0, 'citas' => 0, 'frios' => 0],
        3 => ['id' => 3, 'nombre' => 'Ventas Cayma', 'leads' => 0, 'ganados' => 0, 'facturado' => 0.0, 'citas' => 0, 'frios' => 0]
    ];

    foreach ($rows as $r) {
        $sede = $r['sede_preferida'] ?? 'YANAHUARA';
        if (!isset($sedes[$sede])) $sede = 'YANAHUARA';
        
        $v_id = (int)($r['vendedor_id'] ?? 1);
        if (!isset($vendedores[$v_id])) $v_id = 1;

        $monto = (float)($r['presupuesto_aprox'] ?? 0);
        $et = $r['etapa'] ?? 'NUEVO';
        $temp = $r['temperatura'] ?? 'VERDE';

        // Sedes
        $sedes[$sede]['leads']++;
        if ($et === 'GANADO') {
            $sedes[$sede]['ganados']++;
            $sedes[$sede]['facturado'] += $monto;
        }
        if ($temp === 'PURPURA' || $et === 'VISITA_SEPARADO') {
            $sedes[$sede]['citas']++;
        }

        // Vendedores
        $vendedores[$v_id]['leads']++;
        if ($et === 'GANADO') {
            $vendedores[$v_id]['ganados']++;
            $vendedores[$v_id]['facturado'] += $monto;
        }
        if ($temp === 'PURPURA' || $et === 'VISITA_SEPARADO') {
            $vendedores[$v_id]['citas']++;
        }
        if ($temp === 'ROJO') {
            $vendedores[$v_id]['frios']++;
        }
    }

    // Calcular tasa de conversión
    $ranking = array_values($vendedores);
    foreach ($ranking as &$v) {
        $v['conversion_pct'] = $v['leads'] > 0 ? round(($v['ganados'] / $v['leads']) * 100, 1) : 0;
    }
    // Ordenar por facturación descendente
    usort($ranking, fn($a, $b) => $b['facturado'] <=> $a['facturado']);

    json_resp([
        'success' => true,
        'sedes' => $sedes,
        'ranking' => $ranking
    ]);
}

json_resp(['success' => false, 'error' => 'Acción no reconocida'], 400);
