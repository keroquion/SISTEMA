<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header('HTTP/1.1 401 Unauthorized'); 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); 
    exit; 
}
session_write_close();

header('Content-Type: application/json; charset=utf-8');
require_once "config.php";
$db = getDB();
$action = $_GET["action"] ?? "resumen";

if ($action === "resumen") {
    $rango = $_GET['rango'] ?? 'mes';
    $whereFecha = "1=1";
    if ($rango === 'hoy') {
        $whereFecha = "DATE(h.fecha_cambio) = CURDATE()";
    } elseif ($rango === 'semana') {
        $whereFecha = "YEARWEEK(h.fecha_cambio, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($rango === 'mes') {
        $whereFecha = "MONTH(h.fecha_cambio) = MONTH(CURDATE()) AND YEAR(h.fecha_cambio) = YEAR(CURDATE())";
    }

    // 1. Obtener técnicos activos
    $sqlTec = "SELECT id, nombre, apellido FROM personas WHERE tipo = 'tecnico' AND activo = 1 ORDER BY nombre";
    $resTec = $db->query($sqlTec);
    $tecnicos = [];
    if ($resTec) {
        while ($tec = $resTec->fetch_assoc()) {
            $tec['actividades'] = [];
            $tec['horas_trabajadas'] = 0; // en segundos para el rango
            $tec['horas_hoy'] = 0;        // en segundos hoy
            $tec['estado_en_vivo'] = 'INACTIVO';
            $tec['ticket_actual'] = null;
            $tec['heatmap_semanal'] = [];
            $tec['timeline_hoy'] = [];
            $tecnicos[$tec['id']] = $tec;
        }
    }

    // 2. Extraer historial de cambios de estado relevantes
    $sqlHist = "
        SELECT h.registro_id, h.fecha_cambio, h.valor_nuevo, st.tecnico_id, st.numero_atencion, st.equipo_descripcion, st.estado as estado_actual
        FROM historial_cambios h
        INNER JOIN soporte_tecnico st ON h.registro_id = st.id
        WHERE h.tabla_origen = 'soporte_tecnico' 
        AND h.campo_cambiado = 'estado'
        AND ($whereFecha OR YEARWEEK(h.fecha_cambio, 1) = YEARWEEK(CURDATE(), 1) OR DATE(h.fecha_cambio) = CURDATE())
        ORDER BY h.fecha_cambio ASC
    ";
    $resHist = $db->query($sqlHist);

    $tickets = [];
    if ($resHist) {
        while ($row = $resHist->fetch_assoc()) {
            $tid = $row['tecnico_id'];
            if (!isset($tecnicos[$tid])) continue;
            
            $tk_id = $row['registro_id'];
            if (!isset($tickets[$tk_id])) {
                $tickets[$tk_id] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $row['equipo_descripcion'],
                    'tecnico_id' => $tid,
                    'estado_actual' => $row['estado_actual'],
                    'eventos' => []
                ];
            }
            $tickets[$tk_id]['eventos'][] = [
                'fecha' => $row['fecha_cambio'],
                'estado' => $row['valor_nuevo']
            ];
        }
    }

    // 3. Procesar bloques de actividad por ticket
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
                if ($inicio !== null) {
                    $dur = strtotime($fech) - strtotime($inicio);
                    $tecnicos[$tid]['actividades'][] = [
                        'ticket' => $tk['numero'],
                        'equipo' => $tk['equipo'],
                        'inicio' => $inicio,
                        'fin' => $fech,
                        'estado_fin' => $est,
                        'duracion_seg' => max(0, $dur)
                    ];
                    if ($dur > 0) $tecnicos[$tid]['horas_trabajadas'] += $dur;
                    $inicio = null;
                }
            }
        }

        // Si el ticket nunca se cerró y sigue activo
        if ($inicio !== null) {
            $dur = time() - strtotime($inicio);
            $tecnicos[$tid]['actividades'][] = [
                'ticket' => $tk['numero'],
                'equipo' => $tk['equipo'],
                'inicio' => $inicio,
                'fin' => null,
                'estado_fin' => 'EN_PROCESO',
                'duracion_seg' => max(0, $dur)
            ];
            if ($dur > 0) $tecnicos[$tid]['horas_trabajadas'] += $dur;
        }
    }

    // 4. Determinar estado en vivo desde soporte_tecnico para máxima fidelidad
    $sqlLive = "
        SELECT st.id, st.numero_atencion, st.equipo_descripcion, st.estado, st.tecnico_id, st.fecha_modificacion, st.fecha_ingreso
        FROM soporte_tecnico st
        WHERE st.tecnico_id IS NOT NULL
        AND (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO') OR DATE(st.fecha_modificacion) = CURDATE())
        ORDER BY st.fecha_modificacion DESC, st.id DESC
    ";
    $resLive = $db->query($sqlLive);
    $liveVisto = [];
    if ($resLive) {
        while ($row = $resLive->fetch_assoc()) {
            $tid = $row['tecnico_id'];
            if (!isset($tecnicos[$tid]) || isset($liveVisto[$tid])) continue;

            $est = $row['estado'];
            if (in_array($est, ['EN_DIAGNOSTICO', 'EN_REPARACION'])) {
                $tecnicos[$tid]['estado_en_vivo'] = 'TRABAJANDO';
                $tecnicos[$tid]['ticket_actual'] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $row['equipo_descripcion'],
                    'inicio' => $row['fecha_modificacion'] ?? $row['fecha_ingreso']
                ];
                $liveVisto[$tid] = true;
            } elseif ($est === 'ESPERANDO_REPUESTO') {
                $tecnicos[$tid]['estado_en_vivo'] = 'ESPERANDO';
                $tecnicos[$tid]['ticket_actual'] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $row['equipo_descripcion'],
                    'inicio' => $row['fecha_modificacion'] ?? $row['fecha_ingreso']
                ];
                $liveVisto[$tid] = true;
            }
        }
    }

    // 5. Calcular matriz semanal para Heatmap estilo GitHub y Timeline Hoy
    // Lunes de la semana actual
    $dow = (int)date('w'); // 0=Domingo, 1=Lunes..6=Sabado
    $diasDesdeLunes = ($dow === 0) ? 6 : ($dow - 1);
    $hoyYmd = date('Y-m-d');
    $lunesTs = strtotime("-$diasDesdeLunes days", strtotime($hoyYmd));
    $nombresDias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    foreach ($tecnicos as $tid => &$tec) {
        // Inicializar matriz de 6 días (Lunes a Sábado) y 11 horas (8 a 18)
        $heatmap = [];
        for ($d = 0; $d <= 5; $d++) {
            $fechaDia = date('Y-m-d', strtotime("+$d days", $lunesTs));
            $horasDia = [];
            for ($h = 8; $h <= 18; $h++) {
                $horasDia[] = [
                    'hora' => $h,
                    'hora_label' => sprintf('%02d:00', $h),
                    'minutos' => 0,
                    'ticket' => null,
                    'equipo' => null,
                    'actividad' => null
                ];
            }
            $heatmap[] = [
                'dia_indice' => $d,
                'dia_nombre' => $nombresDias[$d],
                'fecha' => $fechaDia,
                'es_hoy' => ($fechaDia === $hoyYmd),
                'horas' => $horasDia
            ];
        }

        // Acumular minutos de cada actividad en la matriz semanal y calcular horas hoy
        $horasHoySeg = 0;
        foreach ($tec['actividades'] as $act) {
            $actStart = strtotime($act['inicio']);
            $actEnd = !empty($act['fin']) ? strtotime($act['fin']) : time();
            if ($actEnd <= $actStart) continue;

            // Si la actividad ocurrió hoy, acumular segundos hoy
            $hoyStart = strtotime("$hoyYmd 00:00:00");
            $hoyEnd = strtotime("$hoyYmd 23:59:59");
            $overlapHoy = min($actEnd, $hoyEnd) - max($actStart, $hoyStart);
            if ($overlapHoy > 0) {
                $horasHoySeg += $overlapHoy;
            }

            // Distribuir en el heatmap semanal
            for ($d = 0; $d <= 5; $d++) {
                $fechaDia = $heatmap[$d]['fecha'];
                for ($hi = 0; $hi < 11; $hi++) {
                    $h = $heatmap[$d]['horas'][$hi]['hora'];
                    $slotStart = strtotime("$fechaDia $h:00:00");
                    $slotEnd = strtotime("$fechaDia $h:59:59");

                    if ($actEnd > $slotStart && $actStart < $slotEnd) {
                        $ovStart = max($actStart, $slotStart);
                        $ovEnd = min($actEnd, $slotEnd);
                        $mins = (int)round(($ovEnd - $ovStart) / 60);
                        if ($mins > 0) {
                            $prevMins = $heatmap[$d]['horas'][$hi]['minutos'];
                            $newMins = min(60, $prevMins + $mins);
                            $heatmap[$d]['horas'][$hi]['minutos'] = $newMins;
                            if (!$heatmap[$d]['horas'][$hi]['ticket']) {
                                $heatmap[$d]['horas'][$hi]['ticket'] = $act['ticket'];
                                $heatmap[$d]['horas'][$hi]['equipo'] = $act['equipo'];
                            }
                        }
                    }
                }
            }
        }

        $tec['horas_hoy'] = $horasHoySeg;
        $tec['heatmap_semanal'] = $heatmap;

        // Construir timeline de hoy (11 franjas de 8am a 6pm)
        $timelineHoy = [];
        $diaHoyObj = null;
        foreach ($heatmap as $dia) {
            if ($dia['es_hoy']) {
                $diaHoyObj = $dia;
                break;
            }
        }
        for ($h = 8; $h <= 18; $h++) {
            $idx = $h - 8;
            $minsSlot = ($diaHoyObj && isset($diaHoyObj['horas'][$idx])) ? $diaHoyObj['horas'][$idx]['minutos'] : 0;
            $timelineHoy[] = [
                'hora' => $h,
                'hora_label' => sprintf('%02d:00', $h),
                'minutos' => $minsSlot,
                'activo' => ($minsSlot > 0)
            ];
        }
        $tec['timeline_hoy'] = $timelineHoy;

        // Ordenar actividades cronológicamente descendente para el historial detallado
        usort($tec['actividades'], function($a, $b) {
            return strtotime($b['inicio']) - strtotime($a['inicio']);
        });
    }
    unset($tec);

    $data = array_values($tecnicos);

    // Resumen Global
    $resumen = [
        "total_tecnicos" => count($data),
        "tecnicos_trabajando" => 0,
        "tecnicos_esperando" => 0,
        "tecnicos_inactivos" => 0,
        "total_horas" => 0,
        "total_horas_hoy" => 0
    ];
    foreach ($data as $t) {
        $resumen['total_horas'] += $t['horas_trabajadas'];
        $resumen['total_horas_hoy'] += $t['horas_hoy'];
        if ($t['estado_en_vivo'] === 'TRABAJANDO') {
            $resumen['tecnicos_trabajando']++;
        } elseif ($t['estado_en_vivo'] === 'ESPERANDO') {
            $resumen['tecnicos_esperando']++;
        } else {
            $resumen['tecnicos_inactivos']++;
        }
    }

    echo json_encode(["ok" => true, "resumen" => $resumen, "data" => $data]);
    exit;
}
?>