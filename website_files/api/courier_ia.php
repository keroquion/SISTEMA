<?php
/**
 * Modulo Separado: API de Analisis con IA (Gemini Vision) y Rastreo de Couriers
 * Petulap SST - Sandbox Aislado
 * 
 * Acciones:
 * 1. action=analizar_voucher (Sube imagen -> Gemini Vision extrae courier y codigos)
 * 2. action=rastrear (Ejecuta rastreo oficial en vivo con Cruz del Sur o Shalom)
 * 3. action=analizar_y_rastrear (Flujo completo 1-clic: Analiza foto con IA y rastrea inmediatamente)
 */
require_once "config.php";
if (file_exists(__DIR__ . "/secrets.php")) {
    require_once __DIR__ . "/secrets.php";
}
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Gemini-Key");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Clave de Gemini y Modelo por defecto (definidos de forma segura en secrets.php)
define("DEFAULT_GEMINI_API_KEY", defined("GEMINI_API_KEY") ? GEMINI_API_KEY : "");
define("DEFAULT_GEMINI_MODEL", defined("GEMINI_MODEL") ? GEMINI_MODEL : "gemini-2.5-flash");

class CourierIAService {

    // ========================================================================
    // 1. ANALIZADOR DE IMÁGENES CON GEMINI VISION
    // ========================================================================
    public static function analizarVoucherConIA(string $base64Image, string $mimeType = 'image/jpeg', string $apiKey = ''): array {
        $key = !empty($apiKey) ? $apiKey : DEFAULT_GEMINI_API_KEY;
        if (empty($key)) {
            return [
                'success' => false,
                'message' => 'API Key de Google Gemini no configurada. Por favor define GEMINI_API_KEY en secrets.php o en el cliente.'
            ];
        }

        // Endpoint oficial de Google Gemini (gemini-2.5-flash optimizado para OCR multimodal 2026)
        $model = DEFAULT_GEMINI_MODEL;
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($key);

        $prompt = <<<PROMPT
Eres un especialista en logística peruana y sistemas de transporte.
Analiza detenidamente la fotografía de este comprobante, voucher o boleta física de transporte.
Identifica si corresponde a "CRUZ DEL SUR CARGO" o a "SHALOM" (o SHALOM EMPRESARIAL).

Reglas de extracción estrictas:
1. Si es CRUZ DEL SUR:
   - "courier": "CRUZ_DEL_SUR"
   - "codigo_seguimiento": El código alfanumérico de 10 caracteres (letras mayúsculas y números, ej: "7D2092CC54", suele estar cerca del código de barras o titulado "Cód. Seguimiento" / "Seguimiento").
   - "numero_guia": El número de guía remitente o factura (ej: "0246-00081631").
   - "codigo_seguridad": null

2. Si es SHALOM:
   - "courier": "SHALOM"
   - "codigo_seguimiento": El "Nro. Orden" o número de orden de servicio (número de 8 dígitos, ej: "94833476").
   - "codigo_seguridad": El código o clave de seguridad de 4 caracteres (ej: "7CJC", titulado "Código de seguridad" o "Clave").
   - "numero_guia": El número de boleta/factura electrónica si aparece (ej: "F325-0046732").

3. Campos comunes:
   - "origen": Ciudad o agencia de origen detectada (ej: "LIMA", "AREQUIPA").
   - "destino": Ciudad o agencia de destino detectada (ej: "AREQUIPA", "CUSCO").
   - "remitente": Nombre completo o documento del remitente si es visible.
   - "destinatario": Nombre completo o documento del destinatario si es visible.
   - "monto": Monto pagado o por pagar en soles si es visible.

RESPONDE ÚNICA Y EXCLUSIVAMENTE CON UN OBJETO JSON VÁLIDO (sin markdown, sin bloques ```json, solo el JSON crudo):
{
  "courier": "CRUZ_DEL_SUR" o "SHALOM" o "DESCONOCIDO",
  "codigo_seguimiento": "string o null",
  "codigo_seguridad": "string o null",
  "numero_guia": "string o null",
  "origen": "string o null",
  "destino": "string o null",
  "remitente": "string o null",
  "destinatario": "string o null",
  "monto": 0.00,
  "confianza": "ALTA" o "MEDIA" o "BAJA",
  "notas": "observación breve de la lectura"
}
PROMPT;

        $requestBody = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'response_mime_type' => 'application/json'
            ]
        ];

        // Modelos con fallback automático en caso de saturación temporal (HTTP 503/429)
        $modelsToTry = array_unique([DEFAULT_GEMINI_MODEL, 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash', 'gemini-1.5-flash']);
        $lastErrMsg = '';
        $lastResJson = null;

        foreach ($modelsToTry as $model) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($key);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($requestBody),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                $lastErrMsg = 'Error de conexión con Gemini API (' . $model . '): ' . $curlErr;
                continue;
            }

            $resJson = json_decode($response, true);
            if ($httpCode === 200) {
                $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $rawText = trim(str_replace(['```json', '```'], '', $rawText));
                $parsedData = json_decode($rawText, true);

                if (!$parsedData) {
                    return [
                        'success' => false,
                        'message' => 'La IA no devolvió un formato JSON estructurado válido.',
                        'raw_text' => $rawText
                    ];
                }

                return [
                    'success' => true,
                    'modelo_usado' => $model,
                    'data' => [
                        'courier' => $parsedData['courier'] ?? 'DESCONOCIDO',
                        'codigo_seguimiento' => !empty($parsedData['codigo_seguimiento']) ? strtoupper(trim($parsedData['codigo_seguimiento'])) : null,
                        'codigo_seguridad' => !empty($parsedData['codigo_seguridad']) ? strtoupper(trim($parsedData['codigo_seguridad'])) : null,
                        'numero_guia' => $parsedData['numero_guia'] ?? null,
                        'origen' => $parsedData['origen'] ?? null,
                        'destino' => $parsedData['destino'] ?? null,
                        'remitente' => $parsedData['remitente'] ?? null,
                        'destinatario' => $parsedData['destinatario'] ?? null,
                        'monto' => floatval($parsedData['monto'] ?? 0.0),
                        'confianza' => $parsedData['confianza'] ?? 'ALTA',
                        'notas' => $parsedData['notas'] ?? ''
                    ],
                    'raw_ai_response' => $parsedData
                ];
            }

            // Registrar error de demanda o cuota e intentar con el siguiente modelo de respaldo
            $lastErrMsg = $resJson['error']['message'] ?? ('HTTP ' . $httpCode);
            $lastResJson = $resJson;
        }

        return [
            'success' => false,
            'message' => 'Servidores de IA temporalmente ocupados: ' . $lastErrMsg,
            'raw' => $lastResJson
        ];
    }


    // ========================================================================
    // 2. RASTREO OFICIAL CRUZ DEL SUR CARGO (v4 REST)
    // ========================================================================
    public static function trackCruzDelSur(string $trackingCode): array {
        $trackingCode = strtoupper(trim($trackingCode));
        if (empty($trackingCode)) {
            return ['success' => false, 'message' => 'Código de seguimiento requerido.'];
        }

        $url = "https://api.cruzdelsurcargo.com.pe/cargoweb/home/tracking/v4/" . urlencode($trackingCode);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic MjAxMDAyMjc0NjE6NWUyNjFjYTEyOWNhMw==',
                'Accept: application/json, text/plain, */*',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Error de conexión con Cruz del Sur.'];
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['data']['respuesta'][0])) {
            return ['success' => false, 'message' => 'No se encontró la guía en Cruz del Sur.', 'raw' => $data];
        }

        $respInfo = $data['data']['respuesta'][0];
        $doc = $respInfo['documento'] ?? [];
        $events = $respInfo['tracking'] ?? [];

        $cronologia = [];
        foreach ($events as $ev) {
            $cronologia[] = [
                'orden' => $ev['ORDEN'] ?? '',
                'fecha_hora' => $ev['FECHA'] ?? '',
                'evento' => $ev['EVENTO'] ?? '',
                'agencia' => $ev['DESC_AGENCIA'] ?? '',
                'localidad' => $ev['DESC_LOCALIDAD'] ?? '',
                'detalle' => $ev['DETALLE'] ?? '',
                'manifiesto' => $ev['MANIFIESTO'] ?? ''
            ];
        }

        return [
            'success' => true,
            'courier' => 'CRUZ_DEL_SUR',
            'courier_nombre' => 'Cruz del Sur Cargo',
            'tracking_code' => $trackingCode,
            'numero_guia' => ($doc['PREDOCEQU'] ?? '') . '-' . ($doc['NUMDOCEQU'] ?? ''),
            'estado_actual' => $respInfo['status'] ?? 'EN_TRANSITO',
            'mensaje_estado' => $respInfo['mensaje'] ?? '',
            'origen' => $doc['DESLOCORI'] ?? ($doc['DESAGEEMI'] ?? ''),
            'destino' => $doc['DESLOCDES'] ?? ($doc['DESAGEDES'] ?? ''),
            'agencia_destino' => $doc['DESAGEDES'] ?? '',
            'remitente' => $doc['NOMREM'] ?? '',
            'destinatario' => $doc['NOMCON'] ?? '',
            'fecha_emision' => $doc['FECDOC'] ?? '',
            'importe' => $doc['TOT'] ?? 0,
            'peso_kg' => $doc['TOTPES'] ?? 0,
            'eventos_cronologicos' => $cronologia,
            'raw' => $data
        ];
    }


    // ========================================================================
    // 3. RASTREO OFICIAL SHALOM EXPRESS (MICROSERVICIOS + AES SIN CAPTCHA)
    // ========================================================================
    private static function generateShalomBearer(): string {
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
        $a = hash_hmac('sha256', $o, ".Ov3rsku112024l4r43l.");
        return $o . "@" . $a;
    }

    private static function decryptShalom(string $b64): ?array {
        $raw = base64_decode($b64);
        if ($raw === false || strlen($raw) < 17) return null;
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = base64_decode("uQn/bQ94PXBEfId70zjN+VE1hSU7kh9VBXTOUd68Ssc=");
        $dec = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $dec !== false ? json_decode($dec, true) : null;
    }

    private static function callShalom(string $endpoint, array $data): ?array {
        $url = "https://serviceswebapi.shalomcontrol.com/api/v1/web" . $endpoint;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . self::generateShalomBearer(),
                'Accept: application/json, text/plain, */*',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        if (!$resp) return null;
        $json = json_decode($resp, true);
        if (isset($json['encrypted']) && $json['encrypted'] === true && !empty($json['data'])) {
            return self::decryptShalom($json['data']);
        }
        return $json;
    }

    public static function trackShalom(string $ordenNumero): array {
        $ordenNumero = trim($ordenNumero);
        if (empty($ordenNumero)) return ['success' => false, 'message' => 'Número de orden requerido.'];

        // Paso 1: Datos de la orden
        $resOrden = self::callShalom('/reclamo/orden/buscar', ['numero' => $ordenNumero]);
        if (!$resOrden || empty($resOrden['success']) || empty($resOrden['data'])) {
            return ['success' => false, 'message' => $resOrden['message'] ?? 'Orden no encontrada en Shalom.', 'raw' => $resOrden];
        }

        $ord = $resOrden['data'];
        $oseId = $ord['ose_id'] ?? null;

        // Paso 2: Estados cronologicos con ose_id
        $resEst = $oseId ? self::callShalom('/rastrea/estados', ['ose_id' => $oseId]) : null;
        $est = ($resEst && !empty($resEst['success'])) ? ($resEst['data'] ?? []) : [];

        $cronologia = [];
        if (!empty($est['registrado']['fecha'])) $cronologia[] = ['orden' => 1, 'evento' => 'REGISTRADO', 'fecha_hora' => $est['registrado']['fecha'], 'detalle' => 'Orden registrada en sistema'];
        if (!empty($est['origen']['fecha'])) $cronologia[] = ['orden' => 2, 'evento' => 'ORIGEN', 'fecha_hora' => $est['origen']['fecha'], 'detalle' => 'Recepción en agencia: ' . ($ord['origen']['nombre'] ?? '')];
        if (!empty($est['transito']['fecha'])) $cronologia[] = ['orden' => 3, 'evento' => 'TRANSITO', 'fecha_hora' => $est['transito']['fecha'], 'detalle' => 'En ruta a destino' . (!empty($est['transito']['carguero']) ? " (Carguero: {$est['transito']['carguero']})" : '')];
        if (!empty($est['destino']['fecha'])) $cronologia[] = ['orden' => 4, 'evento' => 'DESTINO', 'fecha_hora' => $est['destino']['fecha'], 'detalle' => 'Llegó a agencia destino: ' . ($ord['destino']['nombre'] ?? '') . ' (Listo para recojo)'];
        if (!empty($est['entregado']['fecha'])) {
            $cli = $est['entregado']['cliente']['nombre'] ?? '';
            $doc = $est['entregado']['cliente']['documento'] ?? '';
            $cronologia[] = ['orden' => 5, 'evento' => 'ENTREGADO', 'fecha_hora' => $est['entregado']['fecha'], 'detalle' => "Entregado a: $cli ($doc)"];
        }

        $estadoActual = 'EN_TRANSITO';
        $msg = 'Encomienda en tránsito a destino';
        if (!empty($ord['entregado'])) {
            $estadoActual = 'ENTREGADO';
            $msg = 'Encomienda entregada con éxito al destinatario';
        } elseif (!empty($est['destino']['completo'])) {
            $estadoActual = 'LISTO_PARA_RECOJO';
            $msg = 'Encomienda lista para recojo en agencia de destino';
        }

        return [
            'success' => true,
            'courier' => 'SHALOM',
            'courier_nombre' => 'Shalom Express',
            'tracking_code' => $ordenNumero,
            'numero_guia' => $ordenNumero,
            'codigo_seguridad' => $ord['codigo_orden'] ?? '',
            'ose_id' => (string)$oseId,
            'estado_actual' => $estadoActual,
            'mensaje_estado' => $msg,
            'origen' => $ord['origen']['nombre'] ?? '',
            'destino' => $ord['destino']['nombre'] ?? '',
            'agencia_destino' => $ord['destino']['nombre'] ?? '',
            'remitente' => $ord['remitente']['nombre'] ?? '',
            'destinatario' => $ord['destinatario']['nombre'] ?? '',
            'fecha_emision' => $ord['fecha_emision'] ?? '',
            'importe' => $ord['monto'] ?? 0,
            'eventos_cronologicos' => $cronologia,
            'raw' => ['orden' => $ord, 'estados' => $est]
        ];
    }
}

// ============================================================================
// ENRUTADOR (Solo se ejecuta si es invocado directamente como API)
// ============================================================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'rastrear';
    $apiKeyCustom = $_SERVER['HTTP_X_GEMINI_KEY'] ?? $_POST['gemini_api_key'] ?? $_GET['gemini_api_key'] ?? '';

    switch ($action) {

    // -------------------------------------------------------------
    // ACCIÓN 1: analizar_voucher (IA Gemini Vision)
    // -------------------------------------------------------------
    case 'analizar_voucher':
        $base64 = '';
        $mimeType = 'image/jpeg';

        // Manejar subida vía archivo directo (multipart/form-data)
        if (!empty($_FILES['imagen']['tmp_name'])) {
            $fileTmp = $_FILES['imagen']['tmp_name'];
            $mimeType = mime_content_type($fileTmp) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($fileTmp));
        } else {
            // Manejar vía JSON / raw post
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $rawB64 = $input['imagen_base64'] ?? '';
            if (preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $rawB64, $matches)) {
                $mimeType = $matches[1];
                $base64 = $matches[2];
            } else {
                $base64 = $rawB64;
            }
        }

        if (empty($base64)) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió ninguna imagen para analizar.']);
            exit;
        }

        $iaResult = CourierIAService::analizarVoucherConIA($base64, $mimeType, $apiKeyCustom);
        if (!$iaResult['success']) {
            echo json_encode(['ok' => false, 'msg' => $iaResult['message'], 'raw' => $iaResult['raw'] ?? null]);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $iaResult['data'], 'modelo' => $iaResult['gemini_model']]);
        break;

    // -------------------------------------------------------------
    // ACCIÓN 2: rastrear (Consulta oficial en vivo)
    // -------------------------------------------------------------
    case 'rastrear':
        $courier = strtoupper(trim($_GET['courier'] ?? $_POST['courier'] ?? ''));
        $codigo = trim($_GET['codigo'] ?? $_POST['codigo'] ?? '');

        if (empty($codigo)) {
            echo json_encode(['ok' => false, 'msg' => 'Ingresa el código de seguimiento o número de orden.']);
            exit;
        }

        if ($courier === 'CRUZ_DEL_SUR' || $courier === 'CDS') {
            $res = CourierIAService::trackCruzDelSur($codigo);
        } elseif ($courier === 'SHALOM') {
            $res = CourierIAService::trackShalom($codigo);
        } else {
            // Detección automática según formato:
            // Si tiene 8 dígitos numéricos -> Shalom; si tiene 10 caracteres alfanuméricos -> Cruz del Sur
            if (preg_match('/^\d{8}$/', $codigo)) {
                $res = CourierIAService::trackShalom($codigo);
            } else {
                $res = CourierIAService::trackCruzDelSur($codigo);
            }
        }

        echo json_encode(['ok' => $res['success'], 'msg' => $res['message'] ?? '', 'data' => $res]);
        break;

    // -------------------------------------------------------------
    // ACCIÓN 3: analizar_y_rastrear (Todo en 1 clic)
    // -------------------------------------------------------------
    case 'analizar_y_rastrear':
        $base64 = '';
        $mimeType = 'image/jpeg';

        if (!empty($_FILES['imagen']['tmp_name'])) {
            $fileTmp = $_FILES['imagen']['tmp_name'];
            $mimeType = mime_content_type($fileTmp) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($fileTmp));
        } else {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $rawB64 = $input['imagen_base64'] ?? '';
            if (preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $rawB64, $matches)) {
                $mimeType = $matches[1];
                $base64 = $matches[2];
            } else {
                $base64 = $rawB64;
            }
        }

        if (empty($base64)) {
            echo json_encode(['ok' => false, 'msg' => 'Imagen requerida.']);
            exit;
        }

        // Paso A: Analizar con Gemini
        $iaResult = CourierIAService::analizarVoucherConIA($base64, $mimeType, $apiKeyCustom);
        if (!$iaResult['success']) {
            echo json_encode(['ok' => false, 'msg' => $iaResult['message']]);
            exit;
        }

        $detected = $iaResult['data'];
        $cType = $detected['courier'] ?? '';
        $cCode = $detected['codigo_seguimiento'] ?? '';

        if (empty($cCode) || $cType === 'DESCONOCIDO') {
            echo json_encode([
                'ok' => false,
                'msg' => 'La IA no pudo detectar un código de seguimiento válido en la imagen.',
                'ia_data' => $detected
            ]);
            exit;
        }

        // Paso B: Rastrear inmediatamente
        $trackResult = ($cType === 'CRUZ_DEL_SUR')
            ? CourierIAService::trackCruzDelSur($cCode)
            : CourierIAService::trackShalom($cCode);

        echo json_encode([
            'ok' => true,
            'ia_data' => $detected,
            'tracking' => $trackResult
        ]);
        break;

    // -------------------------------------------------------------
    // ACCIÓN 4: status (Verifica si el backend tiene API Key configurada)
    // -------------------------------------------------------------
    case 'status':
        $hasKey = !empty(DEFAULT_GEMINI_API_KEY);
        $masked = $hasKey ? substr(DEFAULT_GEMINI_API_KEY, 0, 5) . '...' . substr(DEFAULT_GEMINI_API_KEY, -4) : '';
        echo json_encode([
            'ok' => true,
            'configured' => $hasKey,
            'model' => DEFAULT_GEMINI_MODEL,
            'masked_key' => $masked
        ]);
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Acción no válida.']);
        break;
    }
}

