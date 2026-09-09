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
    $semanaOffset = isset($_GET['semana_offset']) ? (int)$_GET['semana_offset'] : 0;

    // Calcular lunes de la semana según el offset solicitado
    $dow = (int)date('w'); // 0=Domingo, 1=Lunes..6=Sabado
    $diasDesdeLunes = ($dow === 0) ? 6 : ($dow - 1);
    $hoyYmd = date('Y-m-d');
    $lunesBaseTs = strtotime("-$diasDesdeLunes days", strtotime($hoyYmd));
    $lunesTs = strtotime(($semanaOffset >= 0 ? "+$semanaOffset weeks" : "$semanaOffset weeks"), $lunesBaseTs);
    $sabadoTs = strtotime("+5 days", $lunesTs);
    $lunesSql = date('Y-m-d 00:00:00', $lunesTs);
    $sabadoSql = date('Y-m-d 23:59:59', $sabadoTs);

    $whereFechaHist = "1=1";
    $whereFechaSt = "1=1";

    if ($semanaOffset !== 0) {
        $whereFechaHist = "h.fecha_cambio BETWEEN '$lunesSql' AND '$sabadoSql'";
        $whereFechaSt = "((st.fecha_ingreso BETWEEN '$lunesSql' AND '$sabadoSql') OR (st.fecha_entrega BETWEEN '$lunesSql' AND '$sabadoSql') OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE')))";
    } else {
        if ($rango === 'hoy') {
            $whereFechaHist = "DATE(h.fecha_cambio) = CURDATE()";
            $whereFechaSt = "(DATE(st.fecha_ingreso) = CURDATE() OR DATE(st.fecha_entrega) = CURDATE() OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO') AND DATE(st.fecha_ingreso) <= CURDATE()))";
        } elseif ($rango === 'semana') {
            $whereFechaHist = "YEARWEEK(h.fecha_cambio, 1) = YEARWEEK(CURDATE(), 1)";
            $whereFechaSt = "(YEARWEEK(st.fecha_ingreso, 1) = YEARWEEK(CURDATE(), 1) OR YEARWEEK(st.fecha_entrega, 1) = YEARWEEK(CURDATE(), 1) OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO')))";
        } elseif ($rango === 'mes') {
            $whereFechaHist = "MONTH(h.fecha_cambio) = MONTH(CURDATE()) AND YEAR(h.fecha_cambio) = YEAR(CURDATE())";
            $whereFechaSt = "((MONTH(st.fecha_ingreso) = MONTH(CURDATE()) AND YEAR(st.fecha_ingreso) = YEAR(CURDATE())) OR (MONTH(st.fecha_entrega) = MONTH(CURDATE()) AND YEAR(st.fecha_entrega) = YEAR(CURDATE())) OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO')))";
        }
    }

    // 1. Obtener técnicos y personal activo con órdenes o tareas asignadas
    $sqlTec = "
        SELECT DISTINCT p.id, p.nombre, p.apellido, p.tipo 
        FROM personas p 
        WHERE p.activo = 1 AND (p.tipo = 'tecnico' OR p.id IN (SELECT DISTINCT tecnico_id FROM soporte_tecnico WHERE tecnico_id IS NOT NULL))
        ORDER BY p.nombre ASC
    ";
    $resTec = $db->query($sqlTec);
    $tecnicos = [];
    if ($resTec) {
        while ($tec = $resTec->fetch_assoc()) {
            $tec['actividades'] = [];
            $tec['horas_trabajadas'] = 0; // en segundos para el rango seleccionado
            $tec['horas_hoy'] = 0;        // en segundos trabajados hoy
            $tec['estado_en_vivo'] = 'INACTIVO';
            $tec['ticket_actual'] = null;
            $tec['heatmap_semanal'] = [];
            $tec['timeline_hoy'] = [];
            $tecnicos[$tec['id']] = $tec;
        }
    }

    // 2. Extraer historial de cambios de estado relevantes
    $sqlHist = "
        SELECT h.registro_id, h.fecha_cambio, h.valor_nuevo, st.tecnico_id, st.numero_atencion,
               COALESCE(NULLIF(st.equipo_descripcion, ''), NULLIF(st.motivo_ingreso, ''), 'Tarea Interna') as equipo_descripcion,
               st.estado as estado_actual,
               st.tiempo_estimado
        FROM historial_cambios h
        INNER JOIN soporte_tecnico st ON h.registro_id = st.id
        WHERE h.tabla_origen = 'soporte_tecnico' 
        AND h.campo_cambiado = 'estado'
        AND ($whereFechaHist OR (h.fecha_cambio BETWEEN '$lunesSql' AND '$sabadoSql') OR DATE(h.fecha_cambio) = CURDATE())
        ORDER BY h.fecha_cambio ASC
    ";
    $resHist = $db->query($sqlHist);

    $tickets = [];
    $ticketsConActividad = [];

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
                    'tiempo_estimado' => $row['tiempo_estimado'] ?? null,
                    'eventos' => []
                ];
            }
            $tickets[$tk_id]['eventos'][] = [
                'fecha' => $row['fecha_cambio'],
                'estado' => $row['valor_nuevo']
            ];
        }
    }

    // 3. Procesar bloques de actividad a partir de eventos de historial
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
                    if ($dur > 0) {
                        $tecnicos[$tid]['actividades'][] = [
                            'ticket' => $tk['numero'],
                            'equipo' => $tk['equipo'],
                            'inicio' => $inicio,
                            'fin' => $fech,
                            'estado_fin' => $est,
                            'duracion_seg' => max(0, $dur),
                            'tiempo_estimado' => $tk['tiempo_estimado']
                        ];
                        $tecnicos[$tid]['horas_trabajadas'] += $dur;
                        $ticketsConActividad[$tk_id] = true;
                    }
                    $inicio = null;
                }
            }
        }

        // Si el ticket nunca se cerró y sigue activo en progreso
        if ($inicio !== null) {
            $dur = time() - strtotime($inicio);
            if ($dur > 0) {
                $tecnicos[$tid]['actividades'][] = [
                    'ticket' => $tk['numero'],
                    'equipo' => $tk['equipo'],
                    'inicio' => $inicio,
                    'fin' => null,
                    'estado_fin' => 'EN_PROCESO',
                    'duracion_seg' => max(0, $dur),
                    'tiempo_estimado' => $tk['tiempo_estimado']
                ];
                $tecnicos[$tid]['horas_trabajadas'] += $dur;
                $ticketsConActividad[$tk_id] = true;
            }
        }
    }

    // 4. Respaldo y Fallback Robusto desde soporte_tecnico para tickets sin eventos suficientes
    $sqlFallback = "
        SELECT st.id, st.numero_atencion,
               COALESCE(NULLIF(st.equipo_descripcion, ''), NULLIF(st.motivo_ingreso, ''), 'Tarea Interna') as equipo_descripcion,
               st.estado, st.tecnico_id, st.fecha_ingreso, st.fecha_entrega, st.tiempo_estimado,
               COALESCE(st.fecha_entrega, st.fecha_ingreso) as fecha_referencia
        FROM soporte_tecnico st
        WHERE st.tecnico_id IS NOT NULL
        AND ($whereFechaSt OR (COALESCE(st.fecha_entrega, st.fecha_ingreso) BETWEEN '$lunesSql' AND '$sabadoSql') OR st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE'))
        ORDER BY fecha_referencia ASC
    ";
    $resFallback = $db->query($sqlFallback);
    if ($resFallback) {
        while ($row = $resFallback->fetch_assoc()) {
            $tid = $row['tecnico_id'];
            if (!isset($tecnicos[$tid])) continue;
            $tk_id = $row['id'];
            if (isset($ticketsConActividad[$tk_id])) continue; // Ya tiene eventos registrados

            $est = $row['estado'];
            $num = $row['numero_atencion'];
            $eq = $row['equipo_descripcion'];
            $ingreso = $row['fecha_ingreso'];
            $entrega = $row['fecha_entrega'];
            $tiempoEst = $row['tiempo_estimado'] ?? null;

            if (in_array($est, ['EN_DIAGNOSTICO', 'EN_REPARACION'])) {
                // Trabajo activo: si ingresó hoy, desde ingreso; si ingresó antes, desde las 08:00 de hoy
                $ingresoYmd = date('Y-m-d', strtotime($ingreso));
                $inicio = ($ingresoYmd < $hoyYmd) ? date('Y-m-d 08:00:00') : $ingreso;
                $dur = time() - strtotime($inicio);
                if ($dur > 0) {
                    $tecnicos[$tid]['actividades'][] = [
                        'ticket' => $num,
                        'equipo' => $eq,
                        'inicio' => $inicio,
                        'fin' => null,
                        'estado_fin' => 'EN_PROCESO',
                        'duracion_seg' => max(0, $dur),
                        'tiempo_estimado' => $tiempoEst
                    ];
                    $tecnicos[$tid]['horas_trabajadas'] += $dur;
                    $ticketsConActividad[$tk_id] = true;
                }
            } elseif ($est === 'ESPERANDO_REPUESTO') {
                $ingresoYmd = date('Y-m-d', strtotime($ingreso));
                $inicio = ($ingresoYmd < $hoyYmd) ? date('Y-m-d 08:00:00') : $ingreso;
                $dur = 2700; // 45 min de triaje previo
                $fin = date('Y-m-d H:i:s', strtotime($inicio) + $dur);
                $tecnicos[$tid]['actividades'][] = [
                    'ticket' => $num,
                    'equipo' => $eq,
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'estado_fin' => 'ESPERANDO_REPUESTO',
                    'duracion_seg' => $dur,
                    'tiempo_estimado' => $tiempoEst
                ];
                $tecnicos[$tid]['horas_trabajadas'] += $dur;
                $ticketsConActividad[$tk_id] = true;
            } elseif ($est === 'LISTO_PARA_RECOGER' || $est === 'ENTREGADO') {
                $fin = !empty($entrega) ? $entrega : $ingreso;
                $dur = 5400; // 90 min promedio de reparación
                $inicio = date('Y-m-d H:i:s', strtotime($fin) - $dur);
                $tecnicos[$tid]['actividades'][] = [
                    'ticket' => $num,
                    'equipo' => $eq,
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'estado_fin' => 'COMPLETADO',
                    'duracion_seg' => $dur,
                    'tiempo_estimado' => $tiempoEst
                ];
                $tecnicos[$tid]['horas_trabajadas'] += $dur;
                $ticketsConActividad[$tk_id] = true;
            }
        }
    }

    // 5. Determinar estado en vivo desde soporte_tecnico para máxima fidelidad
    $sqlLive = "
        SELECT st.id, st.numero_atencion,
               COALESCE(NULLIF(st.equipo_descripcion, ''), NULLIF(st.motivo_ingreso, ''), 'Tarea Interna') as equipo_descripcion,
               st.estado, st.tecnico_id,
               COALESCE(st.fecha_entrega, st.fecha_ingreso) as fecha_referencia,
               st.fecha_ingreso
        FROM soporte_tecnico st
        WHERE st.tecnico_id IS NOT NULL
        AND (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE') OR DATE(COALESCE(st.fecha_entrega, st.fecha_ingreso)) = CURDATE())
        ORDER BY 
            FIELD(st.estado, 'EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE', 'LISTO_PARA_RECOGER', 'ENTREGADO'),
            fecha_referencia DESC, st.id DESC
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
                    'inicio' => $row['fecha_referencia']
                ];
                $liveVisto[$tid] = true;
            } elseif ($est === 'ESPERANDO_REPUESTO') {
                $tecnicos[$tid]['estado_en_vivo'] = 'ESPERANDO';
                $tecnicos[$tid]['ticket_actual'] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $row['equipo_descripcion'],
                    'inicio' => $row['fecha_referencia']
                ];
                $liveVisto[$tid] = true;
            } elseif ($est === 'PENDIENTE') {
                $tecnicos[$tid]['estado_en_vivo'] = 'EN_ESPERA';
                $tecnicos[$tid]['ticket_actual'] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $row['equipo_descripcion'],
                    'inicio' => $row['fecha_referencia'],
                    'es_pendiente' => true
                ];
                $liveVisto[$tid] = true;
            }
        }
    }

    // 6. Calcular matriz semanal para Heatmap estilo GitHub y Timeline Hoy
    $nombresDias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    foreach ($tecnicos as $tid => &$tec) {
        // Inicializar matriz de 6 días (Lunes a Sábado) y 11 horas (8 a 18)
        $heatmap = [];
        for ($d = 0; $d <= 5; $d++) {
            $fechaDia = date('Y-m-d', strtotime("+$d days", $lunesTs));
            $diaMes = date('d/m', strtotime($fechaDia));
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
                'dia_mes' => $diaMes,
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
        } elseif ($t['estado_en_vivo'] === 'ESPERANDO' || $t['estado_en_vivo'] === 'EN_ESPERA' || $t['estado_en_vivo'] === 'ASIGNADO') {
            $resumen['tecnicos_esperando']++;
        } else {
            $resumen['tecnicos_inactivos']++;
        }
    }

    $semanaInfo = [
        'offset' => $semanaOffset,
        'lunes_fecha' => date('Y-m-d', $lunesTs),
        'sabado_fecha' => date('Y-m-d', $sabadoTs),
        'lunes_label' => date('d/m', $lunesTs),
        'sabado_label' => date('d/m', $sabadoTs),
        'ano' => date('Y', $lunesTs),
        'rango_texto' => 'Semana del ' . date('d/m', $lunesTs) . ' al ' . date('d/m', $sabadoTs) . ', ' . date('Y', $lunesTs)
    ];

    echo json_encode(["ok" => true, "semana_info" => $semanaInfo, "resumen" => $resumen, "data" => $data]);
    exit;
}
?>