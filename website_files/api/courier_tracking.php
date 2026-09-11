<?php
/**
 * API de Rastreo Automatizado de Couriers - Petulap SST v1.6.0
 * Soporta: Cruz del Sur Cargo (v4 REST) y Shalom Express (Microservicios REST + AES)
 * Cero dependencias externas - 100% PHP cURL + OpenSSL nativo
 */
require_once "config.php";
header("Content-Type: application/json; charset=utf-8");

$db = getDB();

// Helper: Asegurar existencia de la tabla guias_envio (Patrón de Resiliencia)
$ensureTableGuiasEnvio = function() use ($db) {
    $sql = "
        CREATE TABLE IF NOT EXISTS guias_envio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NULL,
            numero_referencia VARCHAR(50) NOT NULL,
            courier ENUM('CRUZ_DEL_SUR', 'SHALOM') NOT NULL,
            numero_guia_orden VARCHAR(50) NOT NULL,
            codigo_seguridad VARCHAR(20) NULL,
            ose_id VARCHAR(50) NULL,
            estado_courier VARCHAR(50) NOT NULL DEFAULT 'REGISTRADO',
            ultimo_mensaje TEXT NULL,
            origen VARCHAR(150) NULL,
            destino VARCHAR(150) NULL,
            agencia_destino VARCHAR(150) NULL,
            remitente VARCHAR(150) NULL,
            remitente_doc VARCHAR(50) NULL,
            destinatario VARCHAR(150) NULL,
            destinatario_doc VARCHAR(50) NULL,
            fecha_emision VARCHAR(50) NULL,
            fecha_entrega_courier VARCHAR(50) NULL,
            importe DECIMAL(10,2) DEFAULT 0.00,
            peso_kg DECIMAL(10,2) DEFAULT 0.00,
            raw_data_json MEDIUMTEXT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (ticket_id),
            INDEX (numero_referencia),
            INDEX (courier),
            INDEX (numero_guia_orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->query($sql);
};

// ============================================================================
// MOTOR DE INTEGRACIÓN DE COURIERS
// ============================================================================
class CourierEngine {
    
    // --- CRUZ DEL SUR CARGO ---
    private const CDS_API_URL = "https://api.cruzdelsurcargo.com.pe/cargoweb/home/tracking/v4/";
    private const CDS_AUTH_BASIC = "Basic MjAxMDAyMjc0NjE6NWUyNjFjYTEyOWNhMw==";

    public static function trackCruzDelSur(string $trackingCode): array {
        $trackingCode = strtoupper(trim($trackingCode));
        if (empty($trackingCode)) {
            return ['success' => false, 'message' => 'Código de seguimiento requerido.'];
        }

        $url = self::CDS_API_URL . urlencode($trackingCode);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . self::CDS_AUTH_BASIC,
                'Accept: application/json, text/plain, */*',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Error de conexión con Cruz del Sur: ' . $curlErr];
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || !$data || empty($data['data']['respuesta'][0])) {
            return [
                'success' => false,
                'message' => 'No se encontró información en Cruz del Sur para el código ' . $trackingCode,
                'raw' => $data
            ];
        }

        $respInfo = $data['data']['respuesta'][0];
        $doc = $respInfo['documento'] ?? [];
        $trackingEvents = !empty($respInfo['tracking']) && is_array($respInfo['tracking']) ? $respInfo['tracking'] : [];

        $eventos = [];
        foreach ($trackingEvents as $ev) {
            $eventos[] = [
                'orden' => $ev['ORDEN'] ?? '',
                'fecha_hora' => $ev['FECHA'] ?? '',
                'evento' => $ev['EVENTO'] ?? '',
                'agencia' => $ev['DESC_AGENCIA'] ?? '',
                'localidad' => $ev['DESC_LOCALIDAD'] ?? '',
                'detalle' => $ev['DETALLE'] ?? '',
                'manifiesto' => $ev['MANIFIESTO'] ?? ''
            ];
        }

        $rawEstado = strtolower($respInfo['status'] ?? '');
        $estadoEstandar = 'EN_TRANSITO';
        if (strpos($rawEstado, 'entrega') !== false || strpos($rawEstado, 'entregado') !== false) {
            $estadoEstandar = 'ENTREGADO';
        } elseif (strpos($rawEstado, 'agencia') !== false || strpos($rawEstado, 'llegada') !== false || strpos($rawEstado, 'recibido') !== false) {
            $estadoEstandar = 'LISTO_PARA_RECOJO';
        }

        return [
            'success' => true,
            'courier' => 'CRUZ_DEL_SUR',
            'tracking_code' => $trackingCode,
            'numero_guia' => ($doc['PREDOCEQU'] ?? '') . '-' . ($doc['NUMDOCEQU'] ?? ''),
            'estado_courier' => $estadoEstandar,
            'ultimo_mensaje' => $respInfo['mensaje'] ?? 'En tránsito hacia destino',
            'origen' => $doc['DESLOCORI'] ?? ($doc['DESAGEEMI'] ?? ''),
            'destino' => $doc['DESLOCDES'] ?? ($doc['DESAGEDES'] ?? ''),
            'agencia_destino' => $doc['DESAGEDES'] ?? '',
            'remitente' => $doc['NOMREM'] ?? '',
            'destinatario' => $doc['NOMCON'] ?? '',
            'fecha_emision' => $doc['FECDOC'] ?? '',
            'importe' => $doc['TOT'] ?? 0,
            'peso_kg' => $doc['TOTPES'] ?? 0,
            'eventos_cronologicos' => $eventos,
            'raw' => $data
        ];
    }

    // --- SHALOM EXPRESS ---
    private const SHALOM_URL_BASE = "https://serviceswebapi.shalomcontrol.com/api/v1/web";
    private const SHALOM_HMAC_SECRET = ".Ov3rsku112024l4r43l.";
    private const SHALOM_AES_KEY_B64 = "uQn/bQ94PXBEfId70zjN+VE1hSU7kh9VBXTOUd68Ssc=";

    private static function generateShalomBearerToken(): string {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $n = "web-" . $uuid;
        $t = time() + 300;
        $o = $n . "@" . $t;
        $a = hash_hmac('sha256', $o, self::SHALOM_HMAC_SECRET);
        return $o . "@" . $a;
    }

    private static function decryptShalomPayload(string $encryptedBase64): ?array {
        $key = base64_decode(self::SHALOM_AES_KEY_B64);
        $rawBytes = base64_decode($encryptedBase64);
        if ($rawBytes === false || strlen($rawBytes) < 17) {
            return null;
        }

        $iv = substr($rawBytes, 0, 16);
        $ciphertext = substr($rawBytes, 16);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            return null;
        }

        $json = json_decode($decrypted, true);
        return $json !== null ? $json : ['raw_decrypted' => $decrypted];
    }

    private static function requestShalom(string $endpoint, array $postData): ?array {
        $url = self::SHALOM_URL_BASE . $endpoint;
        $bearer = self::generateShalomBearerToken();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $bearer,
                'Accept: application/json, text/plain, */*',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $rawJson = json_decode($response, true);
        if (isset($rawJson['encrypted']) && $rawJson['encrypted'] === true && !empty($rawJson['data'])) {
            return self::decryptShalomPayload($rawJson['data']);
        }

        return $rawJson;
    }

    public static function trackShalom(string $numeroOrden): array {
        $numeroOrden = trim($numeroOrden);
        if (empty($numeroOrden)) {
            return ['success' => false, 'message' => 'Número de orden requerido.'];
        }

        // Paso 1: Obtener detalles de la orden mediante el microservicio operativo sin CAPTCHA
        $guiaResp = self::requestShalom('/reclamo/orden/buscar', ['numero' => $numeroOrden]);

        if (!$guiaResp || empty($guiaResp['success']) || empty($guiaResp['data'])) {
            return [
                'success' => false,
                'message' => $guiaResp['message'] ?? 'No se encontró la orden ' . $numeroOrden . ' en Shalom.',
                'raw' => $guiaResp
            ];
        }

        $guiaData = $guiaResp['data'];
        $oseId = $guiaData['ose_id'] ?? null;

        // Paso 2: Obtener estados cronológicos
        $estadosData = null;
        if ($oseId) {
            $estadosResp = self::requestShalom('/rastrea/estados', ['ose_id' => $oseId]);
            if ($estadosResp && !empty($estadosResp['success'])) {
                $estadosData = $estadosResp['data'] ?? null;
            }
        }

        $cronologia = [];
        if (!empty($estadosData)) {
            if (!empty($estadosData['registrado']['fecha'])) {
                $cronologia[] = [
                    'orden' => 1,
                    'evento' => 'REGISTRADO',
                    'fecha_hora' => $estadosData['registrado']['fecha'],
                    'detalle' => 'Orden registrada en sistema'
                ];
            }
            if (!empty($estadosData['origen']['fecha'])) {
                $cronologia[] = [
                    'orden' => 2,
                    'evento' => 'ORIGEN',
                    'fecha_hora' => $estadosData['origen']['fecha'],
                    'detalle' => 'Recepción en agencia: ' . ($guiaData['origen']['nombre'] ?? '')
                ];
            }
            if (!empty($estadosData['transito']['fecha'])) {
                $cronologia[] = [
                    'orden' => 3,
                    'evento' => 'TRANSITO',
                    'fecha_hora' => $estadosData['transito']['fecha'],
                    'detalle' => 'En ruta a destino' . (!empty($estadosData['transito']['carguero']) ? " (Carguero: {$estadosData['transito']['carguero']})" : '')
                ];
            }
            if (!empty($estadosData['destino']['fecha'])) {
                $cronologia[] = [
                    'orden' => 4,
                    'evento' => 'DESTINO',
                    'fecha_hora' => $estadosData['destino']['fecha'],
                    'detalle' => 'Llegó a agencia de destino: ' . ($guiaData['destino']['nombre'] ?? '') . ' (Listo para recojo)'
                ];
            }
            if (!empty($estadosData['entregado']['fecha'])) {
                $recibidoPor = $estadosData['entregado']['cliente']['nombre'] ?? '';
                $docRecibido = $estadosData['entregado']['cliente']['documento'] ?? '';
                $cronologia[] = [
                    'orden' => 5,
                    'evento' => 'ENTREGADO',
                    'fecha_hora' => $estadosData['entregado']['fecha'],
                    'detalle' => 'Entregado a: ' . trim("$recibidoPor ($docRecibido)")
                ];
            }
        }

        $estadoEstandar = 'EN_TRANSITO';
        $ultimoMsg = 'Mercadería en traslado a destino';
        if (!empty($guiaData['entregado'])) {
            $estadoEstandar = 'ENTREGADO';
            $ultimoMsg = 'Encomienda entregada con éxito al destinatario';
        } elseif (!empty($estadosData['destino']['completo'])) {
            $estadoEstandar = 'LISTO_PARA_RECOJO';
            $ultimoMsg = 'Encomienda en agencia de destino, lista para recojo';
        }

        return [
            'success' => true,
            'courier' => 'SHALOM',
            'tracking_code' => $numeroOrden,
            'numero_guia' => $numeroOrden,
            'codigo_seguridad' => $guiaData['codigo_orden'] ?? '',
            'ose_id' => (string)$oseId,
            'estado_courier' => $estadoEstandar,
            'ultimo_mensaje' => $ultimoMsg,
            'origen' => $guiaData['origen']['nombre'] ?? '',
            'destino' => $guiaData['destino']['nombre'] ?? '',
            'agencia_destino' => $guiaData['destino']['nombre'] ?? '',
            'remitente' => $guiaData['remitente']['nombre'] ?? '',
            'remitente_doc' => $guiaData['remitente']['documento'] ?? '',
            'destinatario' => $guiaData['destinatario']['nombre'] ?? '',
            'destinatario_doc' => $guiaData['destinatario']['documento'] ?? '',
            'fecha_emision' => $guiaData['fecha_emision'] ?? '',
            'fecha_entrega_courier' => $estadosData['entregado']['fecha'] ?? null,
            'importe' => $guiaData['monto'] ?? 0,
            'peso_kg' => 0,
            'eventos_cronologicos' => $cronologia,
            'raw' => ['guia' => $guiaData, 'estados' => $estadosData]
        ];
    }
}

// ============================================================================
// ENRUTAMIENTO DE ACCIONES API
// ============================================================================
$action = $_GET['action'] ?? $_POST['action'] ?? 'ver';

switch ($action) {
    
    // -------------------------------------------------------------
    // ACCIÓN: asociar (Vincular guía de transporte a un ticket)
    // -------------------------------------------------------------
    case 'asociar':
        check_api_access(); // Requiere sesión autenticada
        $ensureTableGuiasEnvio();

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $ticketId = intval($input['ticket_id'] ?? 0);
        $courier = strtoupper(trim($input['courier'] ?? ''));
        $numeroGuia = trim($input['numero_guia'] ?? '');

        if (!$ticketId || !in_array($courier, ['CRUZ_DEL_SUR', 'SHALOM']) || empty($numeroGuia)) {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos. Se requiere ticket_id, courier y número de guía.']);
            exit;
        }

        // Obtener número de atención del ticket
        $stmtTk = $db->prepare("SELECT numero_atencion FROM soporte_tecnico WHERE id = ?");
        $stmtTk->bind_param("i", $ticketId);
        $stmtTk->execute();
        $resTk = $stmtTk->get_result();
        if ($resTk->num_rows === 0) {
            echo json_encode(['ok' => false, 'msg' => 'El ticket especificado no existe.']);
            exit;
        }
        $rowTk = $resTk->fetch_assoc();
        $numAtencion = $rowTk['numero_atencion'];
        $stmtTk->close();

        // 1. Validar en vivo con la API oficial del courier
        $trackingInfo = ($courier === 'CRUZ_DEL_SUR')
            ? CourierEngine::trackCruzDelSur($numeroGuia)
            : CourierEngine::trackShalom($numeroGuia);

        if (!$trackingInfo['success']) {
            echo json_encode([
                'ok' => false,
                'msg' => 'No se pudo verificar la guía en ' . ($courier === 'CRUZ_DEL_SUR' ? 'Cruz del Sur' : 'Shalom') . ': ' . $trackingInfo['message']
            ]);
            exit;
        }

        // 2. Guardar o actualizar en guias_envio
        $codigoSeg = $trackingInfo['codigo_seguridad'] ?? '';
        $oseId = $trackingInfo['ose_id'] ?? '';
        $estadoCourier = $trackingInfo['estado_courier'] ?? 'EN_TRANSITO';
        $ultimoMsg = $trackingInfo['ultimo_mensaje'] ?? '';
        $origen = $trackingInfo['origen'] ?? '';
        $destino = $trackingInfo['destino'] ?? '';
        $agenciaDestino = $trackingInfo['agencia_destino'] ?? '';
        $remitente = $trackingInfo['remitente'] ?? '';
        $remitenteDoc = $trackingInfo['remitente_doc'] ?? '';
        $destinatario = $trackingInfo['destinatario'] ?? '';
        $destinatarioDoc = $trackingInfo['destinatario_doc'] ?? '';
        $fechaEmision = $trackingInfo['fecha_emision'] ?? '';
        $fechaEntrega = $trackingInfo['fecha_entrega_courier'] ?? null;
        $importe = floatval($trackingInfo['importe'] ?? 0);
        $pesoKg = floatval($trackingInfo['peso_kg'] ?? 0);
        $rawJson = json_encode($trackingInfo, JSON_UNESCAPED_UNICODE);

        // Verificar si ya existía un registro para este ticket
        $stmtChk = $db->prepare("SELECT id FROM guias_envio WHERE ticket_id = ?");
        $stmtChk->bind_param("i", $ticketId);
        $stmtChk->execute();
        $resChk = $stmtChk->get_result();

        if ($resChk->num_rows > 0) {
            $rowOld = $resChk->fetch_assoc();
            $stmtUpd = $db->prepare("
                UPDATE guias_envio SET
                    courier = ?, numero_guia_orden = ?, codigo_seguridad = ?, ose_id = ?,
                    estado_courier = ?, ultimo_mensaje = ?, origen = ?, destino = ?,
                    agencia_destino = ?, remitente = ?, remitente_doc = ?, destinatario = ?,
                    destinatario_doc = ?, fecha_emision = ?, fecha_entrega_courier = ?,
                    importe = ?, peso_kg = ?, raw_data_json = ?
                WHERE id = ?
            ");
            $stmtUpd->bind_param(
                "sssssssssssssssddsi",
                $courier, $numeroGuia, $codigoSeg, $oseId,
                $estadoCourier, $ultimoMsg, $origen, $destino,
                $agenciaDestino, $remitente, $remitenteDoc, $destinatario,
                $destinatarioDoc, $fechaEmision, $fechaEntrega,
                $importe, $pesoKg, $rawJson, $rowOld['id']
            );
            $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            $stmtIns = $db->prepare("
                INSERT INTO guias_envio (
                    ticket_id, numero_referencia, courier, numero_guia_orden, codigo_seguridad, ose_id,
                    estado_courier, ultimo_mensaje, origen, destino, agencia_destino,
                    remitente, remitente_doc, destinatario, destinatario_doc, fecha_emision,
                    fecha_entrega_courier, importe, peso_kg, raw_data_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->bind_param(
                "isssssssssssssssddss",
                $ticketId, $numAtencion, $courier, $numeroGuia, $codigoSeg, $oseId,
                $estadoCourier, $ultimoMsg, $origen, $destino, $agenciaDestino,
                $remitente, $remitenteDoc, $destinatario, $destinatarioDoc, $fechaEmision,
                $fechaEntrega, $importe, $pesoKg, $rawJson
            );
            $stmtIns->execute();
            $stmtIns->close();
        }
        $stmtChk->close();

        echo json_encode([
            'ok' => true,
            'msg' => 'Guía de ' . ($courier === 'CRUZ_DEL_SUR' ? 'Cruz del Sur' : 'Shalom') . ' vinculada y verificada con éxito.',
            'data' => $trackingInfo
        ]);
        break;

    // -------------------------------------------------------------
    // ACCIÓN: consultar (Rastrear en tiempo real y refrescar BD)
    // -------------------------------------------------------------
    case 'consultar':
        $ensureTableGuiasEnvio();
        $ticketId = intval($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
        $guiaId = intval($_GET['guia_id'] ?? $_POST['guia_id'] ?? 0);

        if (!$ticketId && !$guiaId) {
            echo json_encode(['ok' => false, 'msg' => 'ticket_id o guia_id requerido.']);
            exit;
        }

        $stmt = $db->prepare($ticketId
            ? "SELECT * FROM guias_envio WHERE ticket_id = ? LIMIT 1"
            : "SELECT * FROM guias_envio WHERE id = ? LIMIT 1"
        );
        $idParam = $ticketId ?: $guiaId;
        $stmt->bind_param("i", $idParam);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(['ok' => false, 'msg' => 'No hay guía de courier registrada para este ticket.']);
            exit;
        }

        $guiaRow = $res->fetch_assoc();
        $stmt->close();

        // Consulta en vivo
        $liveInfo = ($guiaRow['courier'] === 'CRUZ_DEL_SUR')
            ? CourierEngine::trackCruzDelSur($guiaRow['numero_guia_orden'])
            : CourierEngine::trackShalom($guiaRow['numero_guia_orden']);

        if ($liveInfo['success']) {
            $nuevoEstado = $liveInfo['estado_courier'];
            $nuevoMsg = $liveInfo['ultimo_mensaje'];
            $fechaEnt = $liveInfo['fecha_entrega_courier'] ?? $guiaRow['fecha_entrega_courier'];
            $rawJson = json_encode($liveInfo, JSON_UNESCAPED_UNICODE);

            $stmtUpd = $db->prepare("
                UPDATE guias_envio SET
                    estado_courier = ?, ultimo_mensaje = ?, fecha_entrega_courier = ?, raw_data_json = ?
                WHERE id = ?
            ");
            $stmtUpd->bind_param("ssssi", $nuevoEstado, $nuevoMsg, $fechaEnt, $rawJson, $guiaRow['id']);
            $stmtUpd->execute();
            $stmtUpd->close();

            echo json_encode(['ok' => true, 'actualizado' => true, 'data' => $liveInfo]);
        } else {
            // Devolver lo que teníamos en caché si el courier falló
            $parsedRaw = json_decode($guiaRow['raw_data_json'], true);
            echo json_encode([
                'ok' => true,
                'actualizado' => false,
                'msg' => 'No se pudo refrescar con el courier; mostrando datos en caché.',
                'data' => $parsedRaw ?: $guiaRow
            ]);
        }
        break;

    // -------------------------------------------------------------
    // ACCIÓN: ver (Obtener información de la guía guardada)
    // -------------------------------------------------------------
    case 'ver':
        $ensureTableGuiasEnvio();
        $ticketId = intval($_GET['ticket_id'] ?? 0);
        $numAtencion = trim($_GET['ticket'] ?? '');

        if (!$ticketId && empty($numAtencion)) {
            echo json_encode(['ok' => false, 'msg' => 'Identificador de orden requerido.']);
            exit;
        }

        $stmt = $db->prepare($ticketId
            ? "SELECT * FROM guias_envio WHERE ticket_id = ? LIMIT 1"
            : "SELECT * FROM guias_envio WHERE numero_referencia = ? LIMIT 1"
        );
        if ($ticketId) {
            $stmt->bind_param("i", $ticketId);
        } else {
            $stmt->bind_param("s", $numAtencion);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(['ok' => false, 'tiene_guia' => false, 'msg' => 'Sin guía asociada.']);
            exit;
        }

        $data = $res->fetch_assoc();
        $stmt->close();
        $data['raw'] = json_decode($data['raw_data_json'], true);

        echo json_encode(['ok' => true, 'tiene_guia' => true, 'data' => $data]);
        break;

    // -------------------------------------------------------------
    // ACCIÓN: desvincular (Remover guía de transporte de un ticket)
    // -------------------------------------------------------------
    case 'desvincular':
        check_api_access(); // Requiere sesión autenticada
        $ensureTableGuiasEnvio();
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $ticketId = intval($input['ticket_id'] ?? 0);

        if (!$ticketId) {
            echo json_encode(['ok' => false, 'msg' => 'ticket_id requerido.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM guias_envio WHERE ticket_id = ?");
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['ok' => true, 'msg' => 'Guía de transporte desvinculada con éxito.']);
        break;

    // -------------------------------------------------------------
    // ACCIÓN: test_en_vivo (Prueba diagnóstica)
    // -------------------------------------------------------------
    case 'test_en_vivo':
        $courier = strtoupper(trim($_GET['courier'] ?? 'ALL'));
        $results = [];

        if ($courier === 'ALL' || $courier === 'CRUZ_DEL_SUR' || $courier === 'CDS') {
            $code = trim($_GET['code'] ?? '7D2092CC54');
            $results['cruz_del_sur'] = CourierEngine::trackCruzDelSur($code);
        }

        if ($courier === 'ALL' || $courier === 'SHALOM') {
            $orden = trim($_GET['orden'] ?? '94833476');
            $results['shalom'] = CourierEngine::trackShalom($orden);
        }

        echo json_encode(['ok' => true, 'results' => $results]);
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']);
        break;
}

$db->close();
