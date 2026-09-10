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
    $rango = in_array($_GET['rango'] ?? '', ['hoy', 'semana', 'mes']) ? $_GET['rango'] : 'mes';
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
        $whereFechaHist = "h.fecha_cambio BETWEEN ? AND ?";
        $whereFechaSt = "((st.fecha_ingreso BETWEEN ? AND ?) OR (st.fecha_entrega BETWEEN ? AND ?) OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE')))";
    } else {
        if ($rango === 'hoy') {
            $whereFechaHist = "(DATE(h.fecha_cambio) = CURDATE() OR (h.fecha_cambio BETWEEN ? AND ?))";
            $whereFechaSt = "(DATE(st.fecha_ingreso) = CURDATE() OR DATE(st.fecha_entrega) = CURDATE() OR (COALESCE(st.fecha_entrega, st.fecha_ingreso) BETWEEN ? AND ?) OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO') AND DATE(st.fecha_ingreso) <= CURDATE()))";
        } elseif ($rango === 'semana') {
            $whereFechaHist = "(YEARWEEK(h.fecha_cambio, 1) = YEARWEEK(CURDATE(), 1) OR (h.fecha_cambio BETWEEN ? AND ?))";
            $whereFechaSt = "(YEARWEEK(st.fecha_ingreso, 1) = YEARWEEK(CURDATE(), 1) OR YEARWEEK(st.fecha_entrega, 1) = YEARWEEK(CURDATE(), 1) OR (COALESCE(st.fecha_entrega, st.fecha_ingreso) BETWEEN ? AND ?) OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO')))";
        } else { // mes
            $whereFechaHist = "((MONTH(h.fecha_cambio) = MONTH(CURDATE()) AND YEAR(h.fecha_cambio) = YEAR(CURDATE())) OR (h.fecha_cambio BETWEEN ? AND ?))";
            $whereFechaSt = "((MONTH(st.fecha_ingreso) = MONTH(CURDATE()) AND YEAR(st.fecha_ingreso) = YEAR(CURDATE())) OR (MONTH(st.fecha_entrega) = MONTH(CURDATE()) AND YEAR(st.fecha_entrega) = YEAR(CURDATE())) OR (COALESCE(st.fecha_entrega, st.fecha_ingreso) BETWEEN ? AND ?) OR (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO')))";
        }
    }

    // 1. Obtener técnicos y personal activo con órdenes o tareas asignadas
    $sqlTec = "
        SELECT DISTINCT p.id, p.nombre, p.apellido, p.tipo 
        FROM personas p 
        WHERE p.activo = 1 AND (
            p.tipo IN ('tecnico', 'admin') 
            OR p.id IN (SELECT DISTINCT tecnico_id FROM soporte_tecnico WHERE tecnico_id IS NOT NULL)
        )
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
            $tecnicos[(int)$tec['id']] = $tec;
        }
    }

    // Helper: Matching flexible de usuario_nombre hacia ID de técnico
    $resolverTecnicoIdPorUsuario = function($usuarioNombre) use ($tecnicos) {
        if (empty($usuarioNombre)) return null;
        $un = trim($usuarioNombre);
        if (strcasecmp($un, 'sistema') === 0 || strcasecmp($un, 'usuario') === 0 || strcasecmp($un, 'null') === 0) {
            return null;
        }

        $normalizar = function($str) {
            $str = mb_strtolower(trim($str), 'UTF-8');
            $trans = [
                'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
                'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'
            ];
            return strtr($str, $trans);
        };

        $unNorm = $normalizar($un);

        // 1. Coincidencia exacta con nombre completo
        foreach ($tecnicos as $tid => $tec) {
            $completo = $normalizar($tec['nombre'] . ' ' . ($tec['apellido'] ?? ''));
            if ($unNorm === $completo) {
                return $tid;
            }
        }

        // 2. Coincidencia exacta con nombre o apellido
        foreach ($tecnicos as $tid => $tec) {
            $nom = $normalizar($tec['nombre']);
            $ape = $normalizar($tec['apellido'] ?? '');
            if ($unNorm === $nom || (!empty($ape) && $unNorm === $ape)) {
                return $tid;
            }
        }

        // 3. Coincidencia por subcadena / prefijo
        foreach ($tecnicos as $tid => $tec) {
            $nom = $normalizar($tec['nombre']);
            $ape = $normalizar($tec['apellido'] ?? '');
            if (!empty($nom) && (strpos($unNorm, $nom) !== false || strpos($nom, $unNorm) !== false)) {
                return $tid;
            }
            if (!empty($ape) && (strpos($unNorm, $ape) !== false || strpos($ape, $unNorm) !== false)) {
                return $tid;
            }
        }

        return null;
    };

    // Helper: Normalización de actividades (Clientes ST, Soporte Interno ST-INT y Tareas de Taller TAR)
    $normalizarTicketInfo = function($num, $st_equipo, $motivo, $diag, $clienteRaw) {
        $numUpper = strtoupper(trim($num ?? ''));
        if (strpos($numUpper, 'TAR-') === 0) {
            $tipoOrigen = 'TAREA';
            $desc = !empty(trim($motivo ?? '')) ? trim($motivo) : (!empty(trim($diag ?? '')) ? trim($diag) : (!empty(trim($st_equipo ?? '')) ? trim($st_equipo) : 'Tarea de Taller'));
            $cliente = 'Interno / Taller';
        } elseif (strpos($numUpper, 'ST-INT-') === 0) {
            $tipoOrigen = 'INTERNO';
            $desc = !empty(trim($st_equipo ?? '')) ? trim($st_equipo) : (!empty(trim($motivo ?? '')) ? trim($motivo) : 'Equipo Propio Empresa');
            $cliente = 'Stock Propio Empresa';
        } else {
            $tipoOrigen = 'CLIENTE';
            $desc = !empty(trim($st_equipo ?? '')) ? trim($st_equipo) : (!empty(trim($motivo ?? '')) ? trim($motivo) : 'Equipo Laptop');
            $cliente = !empty(trim($clienteRaw ?? '')) ? trim($clienteRaw) : 'Cliente General';
        }
        return [
            'tipo_origen' => $tipoOrigen,
            'equipo_descripcion' => $desc,
            'cliente_nombre' => $cliente
        ];
    };

    // 2. Fuente Intervención: Extraer historial de cambios de estado relevantes
    $sqlHist = "
        SELECT h.registro_id, h.fecha_cambio, h.valor_nuevo, h.valor_anterior, h.usuario_nombre,
               st.tecnico_id as titular_tecnico_id, st.numero_atencion,
               st.motivo_ingreso, st.diagnostico, st.equipo_descripcion as st_equipo,
               CONCAT(c.nombre, ' ', COALESCE(c.apellido, '')) as cliente_nombre_raw,
               st.estado as estado_actual,
               st.tiempo_estimado
        FROM historial_cambios h
        INNER JOIN soporte_tecnico st ON h.registro_id = st.id
        LEFT JOIN personas c ON st.cliente_id = c.id
        WHERE h.tabla_origen = 'soporte_tecnico' 
        AND h.campo_cambiado = 'estado'
        AND ($whereFechaHist OR DATE(h.fecha_cambio) = CURDATE())
        ORDER BY h.fecha_cambio ASC
    ";

    $stmtHist = $db->prepare($sqlHist);
    $stmtHist->bind_param("ss", $lunesSql, $sabadoSql);
    $stmtHist->execute();
    $resHist = $stmtHist->get_result();

    $eventosPorTecnicoTicket = [];
    $actividadesUnicas = []; // [$tid][$claveAct] = true
    $ticketsConActividad = []; // [$tid][$tk_id] = true

    if ($resHist) {
        while ($row = $resHist->fetch_assoc()) {
            $usuarioNombre = $row['usuario_nombre'] ?? '';
            $titularId = !empty($row['titular_tecnico_id']) ? (int)$row['titular_tecnico_id'] : null;

            // Atribuir al técnico que realizó la transición en historial_cambios
            $ejecutorId = $resolverTecnicoIdPorUsuario($usuarioNombre);
            if ($ejecutorId === null && $titularId && isset($tecnicos[$titularId])) {
                // Si el cambio fue por 'Sistema' o usuario no reconocido, se asigna al titular
                $ejecutorId = $titularId;
            }

            if (!$ejecutorId || !isset($tecnicos[$ejecutorId])) {
                continue;
            }

            $tk_id = (int)$row['registro_id'];
            $esColab = ($titularId && $ejecutorId !== $titularId);

            $infoNorm = $normalizarTicketInfo(
                $row['numero_atencion'],
                $row['st_equipo'],
                $row['motivo_ingreso'],
                $row['diagnostico'],
                $row['cliente_nombre_raw']
            );

            if (!isset($eventosPorTecnicoTicket[$ejecutorId][$tk_id])) {
                $eventosPorTecnicoTicket[$ejecutorId][$tk_id] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $infoNorm['equipo_descripcion'],
                    'cliente' => $infoNorm['cliente_nombre'],
                    'tipo_origen' => $infoNorm['tipo_origen'],
                    'titular_id' => $titularId,
                    'estado_actual' => $row['estado_actual'],
                    'tiempo_estimado' => $row['tiempo_estimado'] ?? null,
                    'eventos' => []
                ];
            }

            $eventosPorTecnicoTicket[$ejecutorId][$tk_id]['eventos'][] = [
                'fecha' => $row['fecha_cambio'],
                'estado' => $row['valor_nuevo'],
                'es_colaboracion' => $esColab
            ];
        }
    }

    // 3. Procesar bloques de actividad colaborativos e individuales
    foreach ($eventosPorTecnicoTicket as $tid => $ticketsDeTecnico) {
        foreach ($ticketsDeTecnico as $tk_id => $tk) {
            $inicio = null;
            $esColabAct = false;

            foreach ($tk['eventos'] as $ev) {
                $est = $ev['estado'];
                $fech = $ev['fecha'];
                $esColab = $ev['es_colaboracion'];

                if (in_array($est, ['EN_DIAGNOSTICO', 'EN_REPARACION'])) {
                    if ($inicio === null) {
                        $inicio = $fech;
                        $esColabAct = $esColab;
                    }
                } else {
                    if ($inicio !== null) {
                        $dur = strtotime($fech) - strtotime($inicio);
                        if ($dur >= 0) {
                            $esInstant = ($dur < 60);
                            $durSeg = max(0, $dur);
                            $claveAct = $tk['numero'] . '_' . date('Y-m-d_H', strtotime($inicio));
                            if (!isset($actividadesUnicas[$tid][$claveAct])) {
                                $actividadesUnicas[$tid][$claveAct] = true;
                                $tecnicos[$tid]['actividades'][] = [
                                    'ticket' => $tk['numero'],
                                    'equipo' => $tk['equipo'],
                                    'cliente' => $tk['cliente'],
                                    'tipo_origen' => $tk['tipo_origen'],
                                    'inicio' => $inicio,
                                    'fin' => $fech,
                                    'estado_fin' => $est,
                                    'duracion_seg' => $durSeg,
                                    'minutos_reales' => (int)round($durSeg / 60),
                                    'es_instantaneo' => $esInstant,
                                    'tiempo_estimado' => $tk['tiempo_estimado'],
                                    'es_colaboracion' => $esColabAct,
                                    'rol_intervencion' => $esColabAct ? 'Colaborador' : 'Titular'
                                ];
                                $tecnicos[$tid]['horas_trabajadas'] += $durSeg;
                                $ticketsConActividad[$tid][$tk_id] = true;
                            }
                        }
                        $inicio = null;
                    } else {
                        // Cierre directo o pase a repuesto por este técnico
                        if (in_array($est, ['LISTO_PARA_RECOGER', 'ENTREGADO', 'ESPERANDO_REPUESTO', 'COMPLETADO', 'ENTREGADA'])) {
                            $dur = ($est === 'ESPERANDO_REPUESTO') ? 2700 : 3600; // 45m o 60m estándar
                            $inicioEst = date('Y-m-d H:i:s', strtotime($fech) - $dur);
                            $claveAct = $tk['numero'] . '_' . date('Y-m-d_H', strtotime($inicioEst));
                            if (!isset($actividadesUnicas[$tid][$claveAct])) {
                                $actividadesUnicas[$tid][$claveAct] = true;
                                $tecnicos[$tid]['actividades'][] = [
                                    'ticket' => $tk['numero'],
                                    'equipo' => $tk['equipo'],
                                    'cliente' => $tk['cliente'],
                                    'tipo_origen' => $tk['tipo_origen'],
                                    'inicio' => $inicioEst,
                                    'fin' => $fech,
                                    'estado_fin' => ($est === 'ESPERANDO_REPUESTO') ? 'ESPERANDO_REPUESTO' : 'COMPLETADO',
                                    'duracion_seg' => $dur,
                                    'minutos_reales' => (int)round($dur / 60),
                                    'es_instantaneo' => false,
                                    'tiempo_estimado' => $tk['tiempo_estimado'],
                                    'es_colaboracion' => $esColab,
                                    'rol_intervencion' => $esColab ? 'Colaborador' : 'Titular'
                                ];
                                $tecnicos[$tid]['horas_trabajadas'] += $dur;
                                $ticketsConActividad[$tid][$tk_id] = true;
                            }
                        }
                    }
                }
            }

            // Si el trabajo sigue en progreso abierto
            if ($inicio !== null) {
                $dur = time() - strtotime($inicio);
                if ($dur >= 0) {
                    $esInstant = ($dur < 60);
                    $durSeg = max(0, $dur);
                    $claveAct = $tk['numero'] . '_' . date('Y-m-d_H', strtotime($inicio));
                    if (!isset($actividadesUnicas[$tid][$claveAct])) {
                        $actividadesUnicas[$tid][$claveAct] = true;
                        $tecnicos[$tid]['actividades'][] = [
                            'ticket' => $tk['numero'],
                            'equipo' => $tk['equipo'],
                            'cliente' => $tk['cliente'],
                            'tipo_origen' => $tk['tipo_origen'],
                            'inicio' => $inicio,
                            'fin' => null,
                            'estado_fin' => 'EN_PROCESO',
                            'duracion_seg' => $durSeg,
                            'minutos_reales' => (int)round($durSeg / 60),
                            'es_instantaneo' => $esInstant,
                            'tiempo_estimado' => $tk['tiempo_estimado'],
                            'es_colaboracion' => $esColabAct,
                            'rol_intervencion' => $esColabAct ? 'Colaborador' : 'Titular'
                        ];
                        $tecnicos[$tid]['horas_trabajadas'] += $durSeg;
                        $ticketsConActividad[$tid][$tk_id] = true;
                    }
                }
            }
        }
    }

    // 4. Fuente Titular: Respaldo para órdenes asignadas en soporte_tecnico sin transiciones suficientes en historial
    $sqlFallback = "
        SELECT st.id, st.numero_atencion,
               st.motivo_ingreso, st.diagnostico, st.equipo_descripcion as st_equipo,
               CONCAT(c.nombre, ' ', COALESCE(c.apellido, '')) as cliente_nombre_raw,
               st.estado, st.tecnico_id, st.fecha_ingreso, st.fecha_entrega, st.tiempo_estimado,
               COALESCE(st.fecha_entrega, st.fecha_ingreso) as fecha_referencia
        FROM soporte_tecnico st
        LEFT JOIN personas c ON st.cliente_id = c.id
        WHERE st.tecnico_id IS NOT NULL
        AND ($whereFechaSt OR st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE'))
        ORDER BY fecha_referencia ASC
    ";
    $stmtFallback = $db->prepare($sqlFallback);
    if ($semanaOffset !== 0) {
        $stmtFallback->bind_param("ssss", $lunesSql, $sabadoSql, $lunesSql, $sabadoSql);
    } else {
        $stmtFallback->bind_param("ss", $lunesSql, $sabadoSql);
    }
    $stmtFallback->execute();
    $resFallback = $stmtFallback->get_result();

    if ($resFallback) {
        while ($row = $resFallback->fetch_assoc()) {
            $tid = (int)$row['tecnico_id'];
            if (!isset($tecnicos[$tid])) continue;
            $tk_id = (int)$row['id'];

            // Si el técnico titular ya registró actividad para este ticket, no duplicar
            if (isset($ticketsConActividad[$tid][$tk_id])) continue;

            $est = $row['estado'];
            $num = $row['numero_atencion'];
            $infoNorm = $normalizarTicketInfo(
                $num,
                $row['st_equipo'],
                $row['motivo_ingreso'],
                $row['diagnostico'],
                $row['cliente_nombre_raw']
            );
            $eq = $infoNorm['equipo_descripcion'];
            $cli = $infoNorm['cliente_nombre'];
            $tipoOrigen = $infoNorm['tipo_origen'];
            $ingreso = $row['fecha_ingreso'];
            $entrega = $row['fecha_entrega'];
            $tiempoEst = $row['tiempo_estimado'] ?? null;

            if (in_array($est, ['EN_DIAGNOSTICO', 'EN_REPARACION'])) {
                $ingresoYmd = date('Y-m-d', strtotime($ingreso));
                $inicio = ($ingresoYmd < $hoyYmd) ? date('Y-m-d 08:00:00') : $ingreso;
                $dur = time() - strtotime($inicio);
                if ($dur >= 0) {
                    $esInstant = ($dur < 60);
                    $durSeg = max(0, $dur);
                    $claveAct = $num . '_' . date('Y-m-d_H', strtotime($inicio));
                    if (!isset($actividadesUnicas[$tid][$claveAct])) {
                        $actividadesUnicas[$tid][$claveAct] = true;
                        $tecnicos[$tid]['actividades'][] = [
                            'ticket' => $num,
                            'equipo' => $eq,
                            'cliente' => $cli,
                            'tipo_origen' => $tipoOrigen,
                            'inicio' => $inicio,
                            'fin' => null,
                            'estado_fin' => 'EN_PROCESO',
                            'duracion_seg' => $durSeg,
                            'minutos_reales' => (int)round($durSeg / 60),
                            'es_instantaneo' => $esInstant,
                            'tiempo_estimado' => $tiempoEst,
                            'es_colaboracion' => false,
                            'rol_intervencion' => 'Titular'
                        ];
                        $tecnicos[$tid]['horas_trabajadas'] += $durSeg;
                        $ticketsConActividad[$tid][$tk_id] = true;
                    }
                }
            } elseif ($est === 'ESPERANDO_REPUESTO') {
                $ingresoYmd = date('Y-m-d', strtotime($ingreso));
                $inicio = ($ingresoYmd < $hoyYmd) ? date('Y-m-d 08:00:00') : $ingreso;
                $dur = 2700; // 45 min de triaje previo
                $fin = date('Y-m-d H:i:s', strtotime($inicio) + $dur);
                $claveAct = $num . '_' . date('Y-m-d_H', strtotime($inicio));
                if (!isset($actividadesUnicas[$tid][$claveAct])) {
                    $actividadesUnicas[$tid][$claveAct] = true;
                    $tecnicos[$tid]['actividades'][] = [
                        'ticket' => $num,
                        'equipo' => $eq,
                        'cliente' => $cli,
                        'tipo_origen' => $tipoOrigen,
                        'inicio' => $inicio,
                        'fin' => $fin,
                        'estado_fin' => 'ESPERANDO_REPUESTO',
                        'duracion_seg' => $dur,
                        'minutos_reales' => (int)round($dur / 60),
                        'es_instantaneo' => false,
                        'tiempo_estimado' => $tiempoEst,
                        'es_colaboracion' => false,
                        'rol_intervencion' => 'Titular'
                    ];
                    $tecnicos[$tid]['horas_trabajadas'] += $dur;
                    $ticketsConActividad[$tid][$tk_id] = true;
                }
            } elseif (in_array($est, ['LISTO_PARA_RECOGER', 'ENTREGADO', 'COMPLETADO', 'ENTREGADA'])) {
                $fin = !empty($entrega) ? $entrega : $ingreso;
                $diffFechas = (!empty($entrega) && !empty($ingreso)) ? (strtotime($entrega) - strtotime($ingreso)) : 5400;
                $esInstant = ($diffFechas >= 0 && $diffFechas < 60);
                if ($esInstant) {
                    $dur = max(0, $diffFechas);
                    $inicio = $ingreso;
                } else {
                    $dur = ($diffFechas > 0 && $diffFechas < 86400) ? $diffFechas : 5400;
                    $inicio = date('Y-m-d H:i:s', strtotime($fin) - $dur);
                }
                $claveAct = $num . '_' . date('Y-m-d_H', strtotime($inicio));
                if (!isset($actividadesUnicas[$tid][$claveAct])) {
                    $actividadesUnicas[$tid][$claveAct] = true;
                    $tecnicos[$tid]['actividades'][] = [
                        'ticket' => $num,
                        'equipo' => $eq,
                        'cliente' => $cli,
                        'tipo_origen' => $tipoOrigen,
                        'inicio' => $inicio,
                        'fin' => $fin,
                        'estado_fin' => 'COMPLETADO',
                        'duracion_seg' => $dur,
                        'minutos_reales' => (int)round($dur / 60),
                        'es_instantaneo' => $esInstant,
                        'tiempo_estimado' => $tiempoEst,
                        'es_colaboracion' => false,
                        'rol_intervencion' => 'Titular'
                    ];
                    $tecnicos[$tid]['horas_trabajadas'] += $dur;
                    $ticketsConActividad[$tid][$tk_id] = true;
                }
            }
        }
    }

    // 5. Determinar estado en vivo combinando actividad en curso y soporte_tecnico
    foreach ($tecnicos as $tid => &$tec) {
        // Revisar si el técnico tiene una actividad en curso hoy
        foreach ($tec['actividades'] as $act) {
            if ($act['fin'] === null && $act['estado_fin'] === 'EN_PROCESO') {
                $actYmd = date('Y-m-d', strtotime($act['inicio']));
                if ($actYmd === $hoyYmd) {
                    $tec['estado_en_vivo'] = 'TRABAJANDO';
                    $tec['ticket_actual'] = [
                        'numero' => $act['ticket'],
                        'equipo' => $act['equipo'],
                        'cliente' => $act['cliente'] ?? '',
                        'tipo_origen' => $act['tipo_origen'] ?? 'CLIENTE',
                        'inicio' => $act['inicio'],
                        'es_colaboracion' => !empty($act['es_colaboracion']),
                        'rol_intervencion' => !empty($act['es_colaboracion']) ? 'Colaborador' : 'Titular'
                    ];
                    break;
                }
            }
        }
    }
    unset($tec);

    $sqlLive = "
        SELECT st.id, st.numero_atencion,
               st.motivo_ingreso, st.diagnostico, st.equipo_descripcion as st_equipo,
               CONCAT(c.nombre, ' ', COALESCE(c.apellido, '')) as cliente_nombre_raw,
               st.estado, st.tecnico_id,
               COALESCE(st.fecha_entrega, st.fecha_ingreso) as fecha_referencia,
               st.fecha_ingreso
        FROM soporte_tecnico st
        LEFT JOIN personas c ON st.cliente_id = c.id
        WHERE st.tecnico_id IS NOT NULL
        AND (st.estado IN ('EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE') OR DATE(COALESCE(st.fecha_entrega, st.fecha_ingreso)) = CURDATE())
        ORDER BY 
            FIELD(st.estado, 'EN_DIAGNOSTICO', 'EN_REPARACION', 'ESPERANDO_REPUESTO', 'PENDIENTE', 'LISTO_PARA_RECOGER', 'ENTREGADO'),
            fecha_referencia DESC, st.id DESC
    ";
    $resLive = $db->query($sqlLive);
    if ($resLive) {
        while ($row = $resLive->fetch_assoc()) {
            $tid = (int)$row['tecnico_id'];
            if (!isset($tecnicos[$tid])) continue;
            if ($tecnicos[$tid]['estado_en_vivo'] === 'TRABAJANDO') continue; // Ya detectado por actividad

            $infoNorm = $normalizarTicketInfo(
                $row['numero_atencion'],
                $row['st_equipo'],
                $row['motivo_ingreso'],
                $row['diagnostico'],
                $row['cliente_nombre_raw']
            );

            $est = $row['estado'];
            if (in_array($est, ['EN_DIAGNOSTICO', 'EN_REPARACION'])) {
                $tecnicos[$tid]['estado_en_vivo'] = 'TRABAJANDO';
                $tecnicos[$tid]['ticket_actual'] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $infoNorm['equipo_descripcion'],
                    'cliente' => $infoNorm['cliente_nombre'],
                    'tipo_origen' => $infoNorm['tipo_origen'],
                    'inicio' => $row['fecha_referencia'],
                    'es_colaboracion' => false,
                    'rol_intervencion' => 'Titular'
                ];
            } elseif ($est === 'ESPERANDO_REPUESTO' && $tecnicos[$tid]['estado_en_vivo'] === 'INACTIVO') {
                $tecnicos[$tid]['estado_en_vivo'] = 'ESPERANDO';
                $tecnicos[$tid]['ticket_actual'] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $infoNorm['equipo_descripcion'],
                    'cliente' => $infoNorm['cliente_nombre'],
                    'tipo_origen' => $infoNorm['tipo_origen'],
                    'inicio' => $row['fecha_referencia'],
                    'es_colaboracion' => false,
                    'rol_intervencion' => 'Titular'
                ];
            } elseif ($est === 'PENDIENTE' && $tecnicos[$tid]['estado_en_vivo'] === 'INACTIVO') {
                $tecnicos[$tid]['estado_en_vivo'] = 'EN_ESPERA';
                $tecnicos[$tid]['ticket_actual'] = [
                    'numero' => $row['numero_atencion'],
                    'equipo' => $infoNorm['equipo_descripcion'],
                    'cliente' => $infoNorm['cliente_nombre'],
                    'tipo_origen' => $infoNorm['tipo_origen'],
                    'inicio' => $row['fecha_referencia'],
                    'es_pendiente' => true,
                    'es_colaboracion' => false,
                    'rol_intervencion' => 'Titular'
                ];
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
                    'tipo_celda' => 'vacio',
                    'nivel_visual' => '0',
                    'es_instantaneo' => false,
                    'ticket' => null,
                    'equipo' => null,
                    'cliente' => null,
                    'tipo_origen' => null,
                    'actividad' => null,
                    'actividades_detalle' => [],
                    'es_colaboracion' => false,
                    'rol' => null
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
            $esInstantAct = !empty($act['es_instantaneo']);

            // Si es instantáneo (actEnd == actStart o duración < 60)
            if ($esInstantAct || ($actEnd - $actStart < 60)) {
                $refTs = !empty($act['fin']) ? strtotime($act['fin']) : $actStart;
                $refDiaYmd = date('Y-m-d', $refTs);
                $refHora = (int)date('G', $refTs);

                for ($d = 0; $d <= 5; $d++) {
                    if ($heatmap[$d]['fecha'] === $refDiaYmd) {
                        if ($refHora >= 8 && $refHora <= 18) {
                            $hi = $refHora - 8;
                            $cell = &$heatmap[$d]['horas'][$hi];
                            $cell['actividades_detalle'][] = [
                                'ticket' => $act['ticket'],
                                'equipo' => $act['equipo'],
                                'cliente' => $act['cliente'] ?? '',
                                'tipo_origen' => $act['tipo_origen'] ?? 'CLIENTE',
                                'es_instantaneo' => true,
                                'es_colaboracion' => !empty($act['es_colaboracion']),
                                'rol' => !empty($act['es_colaboracion']) ? 'Colaborador' : 'Titular'
                            ];
                            // Si la celda no tiene minutos acumulados (> 0), marcar visualmente como instantánea
                            if ($cell['minutos'] === 0) {
                                $cell['tipo_celda'] = 'instantaneo';
                                $cell['nivel_visual'] = 'instant';
                                $cell['es_instantaneo'] = true;
                                if (!$cell['ticket']) {
                                    $cell['ticket'] = $act['ticket'];
                                    $cell['equipo'] = $act['equipo'];
                                    $cell['cliente'] = $act['cliente'] ?? '';
                                    $cell['tipo_origen'] = $act['tipo_origen'] ?? 'CLIENTE';
                                    $cell['es_colaboracion'] = !empty($act['es_colaboracion']);
                                    $cell['rol'] = !empty($act['es_colaboracion']) ? 'Colaborador' : 'Titular';
                                }
                            }
                            unset($cell);
                        }
                        break;
                    }
                }
                continue;
            }

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
                            $heatmap[$d]['horas'][$hi]['tipo_celda'] = 'regular';
                            $heatmap[$d]['horas'][$hi]['nivel_visual'] = ($newMins >= 46) ? '3' : (($newMins >= 26) ? '2' : '1');
                            $heatmap[$d]['horas'][$hi]['es_instantaneo'] = false;
                            $heatmap[$d]['horas'][$hi]['actividades_detalle'][] = [
                                'ticket' => $act['ticket'],
                                'equipo' => $act['equipo'],
                                'cliente' => $act['cliente'] ?? '',
                                'tipo_origen' => $act['tipo_origen'] ?? 'CLIENTE',
                                'minutos' => $mins,
                                'es_instantaneo' => false,
                                'es_colaboracion' => !empty($act['es_colaboracion']),
                                'rol' => !empty($act['es_colaboracion']) ? 'Colaborador' : 'Titular'
                            ];
                            if (!$heatmap[$d]['horas'][$hi]['ticket']) {
                                $heatmap[$d]['horas'][$hi]['ticket'] = $act['ticket'];
                                $heatmap[$d]['horas'][$hi]['equipo'] = $act['equipo'];
                                $heatmap[$d]['horas'][$hi]['cliente'] = $act['cliente'] ?? '';
                                $heatmap[$d]['horas'][$hi]['tipo_origen'] = $act['tipo_origen'] ?? 'CLIENTE';
                                $heatmap[$d]['horas'][$hi]['es_colaboracion'] = !empty($act['es_colaboracion']);
                                $heatmap[$d]['horas'][$hi]['rol'] = !empty($act['es_colaboracion']) ? 'Colaborador' : 'Titular';
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