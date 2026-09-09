<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header('HTTP/1.1 401 Unauthorized'); 
    echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); 
    exit; 
}
session_write_close();

require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "resumen";

if ($action === "resumen") {
    // Keep existing logic or replace it? 
    // We can replace the whole logic to return the 'horario' data immediately.
    
    $rango = $_GET['rango'] ?? 'hoy';
    $whereFecha = "1=1";
    if ($rango === 'hoy') {
        $whereFecha = "DATE(h.fecha_cambio) = CURDATE()";
    } elseif ($rango === 'semana') {
        $whereFecha = "YEARWEEK(h.fecha_cambio, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($rango === 'mes') {
        $whereFecha = "MONTH(h.fecha_cambio) = MONTH(CURDATE()) AND YEAR(h.fecha_cambio) = YEAR(CURDATE())";
    }

    // List of active technicians
    $sqlTec = "SELECT id, nombre, apellido FROM personas WHERE tipo = 'tecnico' AND activo = 1 ORDER BY nombre";
    $resTec = $db->query($sqlTec);
    $tecnicos = [];
    if ($resTec) {
        while ($tec = $resTec->fetch_assoc()) {
            $tec['actividades'] = [];
            $tec['horas_trabajadas'] = 0; // en segundos
            $tecnicos[$tec['id']] = $tec;
        }
    }

    // Extract ALL history events for the period for these technicians
    // We want to know when a ticket enters 'EN_DIAGNOSTICO' or 'EN_REPARACION' (START)
    // and when it changes to anything else like 'ESPERANDO_REPUESTO', 'LISTO_PARA_RECOGER', 'ENTREGADO' (END)
    
    // Simplest approach: Query all tickets that had ANY activity by the technician in the date range.
    // For each ticket, find the chronological sequence of states.
    
    $sqlHist = "
        SELECT h.registro_id, h.fecha_cambio, h.valor_nuevo, st.tecnico_id, st.numero_atencion, st.equipo_descripcion, st.estado as estado_actual
        FROM historial_cambios h
        INNER JOIN soporte_tecnico st ON h.registro_id = st.id
        WHERE h.tabla_origen = 'soporte_tecnico' 
        AND h.campo_cambiado = 'estado'
        AND ($whereFecha OR DATE(st.fecha_ingreso) = CURDATE()) 
        ORDER BY h.fecha_cambio ASC
    ";
    $resHist = $db->query($sqlHist);
    
    $tickets = [];
    if ($resHist) {
        while ($row = $resHist->fetch_assoc()) {
            $tid = $row['tecnico_id'];
            if (!isset($tecnicos[$tid])) continue; // Not an active tech
            
            $tk_id = $row['registro_id'];
            if (!isset($tickets[$tk_id])) {
                $tickets[$tk_id] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $row['equipo_descripcion'],
                    'tecnico_id' => $tid,
                    'eventos' => []
                ];
            }
            $tickets[$tk_id]['eventos'][] = [
                'fecha' => $row['fecha_cambio'],
                'estado' => $row['valor_nuevo']
            ];
        }
    }

    // Now construct blocks for each ticket
    foreach ($tickets as $tk_id => $tk) {
        $tid = $tk['tecnico_id'];
        $inicio = null;
        
        foreach ($tk['eventos'] as $ev) {
            $est = $ev['estado'];
            $fech = $ev['fecha'];
            
            if (in_array($est, ['EN_DIAGNOSTICO', 'EN_REPARACION'])) {
                if ($inicio === null) {
                    $inicio = $fech;
                }
            } else {
                // If it was started, and now it changed to something else, close the block
                if ($inicio !== null) {
                    $tecnicos[$tid]['actividades'][] = [
                        'ticket' => $tk['numero'],
                        'equipo' => $tk['equipo'],
                        'inicio' => $inicio,
                        'fin' => $fech,
                        'estado_fin' => $est
                    ];
                    // Add duration
                    $dur = strtotime($fech) - strtotime($inicio);
                    if ($dur > 0) $tecnicos[$tid]['horas_trabajadas'] += $dur;
                    $inicio = null;
                }
            }
        }
        // If it never closed, it means it's currently working on it
        if ($inicio !== null) {
            $tecnicos[$tid]['actividades'][] = [
                'ticket' => $tk['numero'],
                'equipo' => $tk['equipo'],
                'inicio' => $inicio,
                'fin' => null, // En proceso
                'estado_fin' => 'EN_PROCESO'
            ];
            $dur = time() - strtotime($inicio);
            if ($dur > 0) $tecnicos[$tid]['horas_trabajadas'] += $dur;
        }
    }

    // Sort actividades cronológicamente para cada técnico
    $data = [];
    foreach ($tecnicos as $tid => $tec) {
        usort($tec['actividades'], function($a, $b) {
            return strtotime($a['inicio']) - strtotime($b['inicio']);
        });
        $data[] = $tec;
    }

    // Resumen Global
    $resumen = [
        "total_tecnicos" => count($data),
        "tecnicos_ocupados" => 0,
        "total_horas" => 0
    ];
    foreach ($data as $t) {
        $resumen['total_horas'] += $t['horas_trabajadas'];
        // Check if last activity is EN_PROCESO
        if (count($t['actividades']) > 0) {
            $last = end($t['actividades']);
            if ($last['fin'] === null) {
                $resumen['tecnicos_ocupados']++;
            }
        }
    }

    echo json_encode(["ok" => true, "resumen" => $resumen, "data" => $data]);
    exit;
}
?>