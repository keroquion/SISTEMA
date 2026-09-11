<?php
/**
 * Modulo Aislado: CourierTrackingService.php
 * Sandbox de integracion y rastreo automatizado de couriers para Petulap SST.
 * 
 * COURIERS SOPORTADOS:
 * 1. Cruz del Sur Cargo (API v4 REST - 100% Automatizable sin CAPTCHA)
 * 2. Shalom Express (API v1 REST con HMAC Bearer + AES-256-CBC Decryption + reCAPTCHA v3)
 * 
 * NOTA: Este archivo es 100% independiente y NO modifica pantallas ni base de datos del sistema.
 */

class CourierTrackingService {
    
    // ==========================================
    // 1. CRUZ DEL SUR CARGO
    // ==========================================
    private const CDS_API_URL = "https://api.cruzdelsurcargo.com.pe/cargoweb/home/tracking/v4/";
    private const CDS_AUTH_BASIC = "Basic MjAxMDAyMjc0NjE6NWUyNjFjYTEyOWNhMw=="; // 20100227461:5e261ca129ca3

    /**
     * Rastrea una guia en Cruz del Sur Cargo
     * @param string $trackingCode Codigo alfanumerico de 10 caracteres (ej: 7D2092CC54)
     * @return array Resultado estandarizado
     */
    public static function trackCruzDelSur(string $trackingCode): array {
        $trackingCode = strtoupper(trim($trackingCode));
        if (empty($trackingCode)) {
            return [
                'success' => false,
                'courier' => 'CRUZ_DEL_SUR',
                'message' => 'Codigo de seguimiento requerido.'
            ];
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
            return [
                'success' => false,
                'courier' => 'CRUZ_DEL_SUR',
                'message' => 'Error de conexion con Cruz del Sur: ' . $curlErr
            ];
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || !$data) {
            return [
                'success' => false,
                'courier' => 'CRUZ_DEL_SUR',
                'http_code' => $httpCode,
                'message' => 'No se encontro informacion para el codigo proporcionado.',
                'raw' => $data ?? $response
            ];
        }

        // Parsear y estandarizar datos de Cruz del Sur
        $respInfo = !empty($data['data']['respuesta'][0]) ? $data['data']['respuesta'][0] : [];
        $doc = !empty($respInfo['documento']) ? $respInfo['documento'] : [];
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

        return [
            'success' => true,
            'courier' => 'CRUZ_DEL_SUR',
            'tracking_code' => $trackingCode,
            'estado_actual' => $respInfo['status'] ?? ($data['estado'] ?? 'DESCONOCIDO'),
            'mensaje_estado' => $respInfo['mensaje'] ?? ($data['mensaje'] ?? ''),
            'origen' => $doc['DESLOCORI'] ?? ($doc['DESAGEEMI'] ?? ''),
            'destino' => $doc['DESLOCDES'] ?? ($doc['DESAGEDES'] ?? ''),
            'agencia_emision' => $doc['DESAGEEMI'] ?? '',
            'agencia_destino' => $doc['DESAGEDES'] ?? '',
            'remitente' => $doc['NOMREM'] ?? '',
            'destinatario' => $doc['NOMCON'] ?? '',
            'guia_numero' => ($doc['PREDOCEQU'] ?? '') . '-' . ($doc['NUMDOCEQU'] ?? ''),
            'fecha_emision' => $doc['FECDOC'] ?? '',
            'importe' => $doc['TOT'] ?? 0,
            'peso_kg' => $doc['TOTPES'] ?? 0,
            'moneda' => $doc['CODMONSUN'] ?? 'PEN',
            'eventos_cronologicos' => $eventos,
            'raw' => $data
        ];
    }


    // ==========================================
    // 2. SHALOM EXPRESS (AUTOMATIZACION TOTAL SIN CAPTCHA)
    // ==========================================
    private const SHALOM_URL_BASE = "https://serviceswebapi.shalomcontrol.com/api/v1/web";
    private const SHALOM_HMAC_SECRET = ".Ov3rsku112024l4r43l.";
    private const SHALOM_AES_KEY_B64 = "uQn/bQ94PXBEfId70zjN+VE1hSU7kh9VBXTOUd68Ssc=";

    /**
     * Genera el token Bearer HMAC-SHA256 requerido por la API de Shalom
     */
    public static function generateShalomBearerToken(): string {
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

    /**
     * Descifra la respuesta cifrada en AES-256-CBC enviada por Shalom
     */
    public static function decryptShalomPayload(string $encryptedBase64): ?array {
        $key = base64_decode(self::SHALOM_AES_KEY_B64);
        $rawBytes = base64_decode($encryptedBase64);
        if ($rawBytes === false || strlen($rawBytes) < 17) {
            return null;
        }

        // El IV son los primeros 16 bytes, el resto es el ciphertext
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

    /**
     * Ejecuta una peticion POST cifrada a la API de Shalom
     */
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

    /**
     * Rastreo automatizado de guia en Shalom Express (SIN CAPTCHA)
     * Utiliza el flujo de dos pasos:
     * 1. Consulta /reclamo/orden/buscar con el numero de orden -> Obtiene datos de envio, remitente, destinatario y ose_id.
     * 2. Consulta /rastrea/estados con el ose_id -> Obtiene el progreso cronologico detallado y entrega.
     * 
     * @param string $numeroOrden Numero de orden (ej: 94833476)
     * @param string|null $codigoSeguridad Codigo opcional de 4 caracteres
     * @return array Resultado unificado y estandarizado
     */
    public static function trackShalom(string $numeroOrden, ?string $codigoSeguridad = null): array {
        $numeroOrden = trim($numeroOrden);
        if (empty($numeroOrden)) {
            return [
                'success' => false,
                'courier' => 'SHALOM',
                'message' => 'Numero de orden requerido.'
            ];
        }

        // Paso 1: Obtener detalles de la orden mediante el endpoint de consulta rapida
        $guiaResp = self::requestShalom('/reclamo/orden/buscar', ['numero' => $numeroOrden]);

        if (!$guiaResp || empty($guiaResp['success']) || empty($guiaResp['data'])) {
            return [
                'success' => false,
                'courier' => 'SHALOM',
                'message' => $guiaResp['message'] ?? 'No se encontro informacion para la orden especificada en Shalom.',
                'raw' => $guiaResp
            ];
        }

        $guiaData = $guiaResp['data'];
        $oseId = $guiaData['ose_id'] ?? null;

        // Paso 2: Obtener estados cronologicos con el ose_id
        $estadosData = null;
        if ($oseId) {
            $estadosResp = self::requestShalom('/rastrea/estados', ['ose_id' => $oseId]);
            if ($estadosResp && !empty($estadosResp['success'])) {
                $estadosData = $estadosResp['data'] ?? null;
            }
        }

        // Construir cronograma de estados
        $cronologia = [];
        if (!empty($estadosData)) {
            if (!empty($estadosData['registrado']['fecha'])) {
                $cronologia[] = [
                    'estado' => 'REGISTRADO',
                    'fecha_hora' => $estadosData['registrado']['fecha'],
                    'detalle' => 'Orden registrada en sistema'
                ];
            }
            if (!empty($estadosData['origen']['fecha'])) {
                $cronologia[] = [
                    'estado' => 'ORIGEN',
                    'fecha_hora' => $estadosData['origen']['fecha'],
                    'detalle' => 'Recepción en agencia de origen'
                ];
            }
            if (!empty($estadosData['transito']['fecha'])) {
                $cronologia[] = [
                    'estado' => 'TRANSITO',
                    'fecha_hora' => $estadosData['transito']['fecha'],
                    'detalle' => 'En ruta a destino' . (!empty($estadosData['transito']['carguero']) ? " (Carguero: {$estadosData['transito']['carguero']})" : '')
                ];
            }
            if (!empty($estadosData['destino']['fecha'])) {
                $cronologia[] = [
                    'estado' => 'DESTINO',
                    'fecha_hora' => $estadosData['destino']['fecha'],
                    'detalle' => 'Llegada a agencia de destino (Lista para recojo)'
                ];
            }
            if (!empty($estadosData['entregado']['fecha'])) {
                $recibidoPor = !empty($estadosData['entregado']['cliente']['nombre']) ? $estadosData['entregado']['cliente']['nombre'] : '';
                $docRecibido = !empty($estadosData['entregado']['cliente']['documento']) ? $estadosData['entregado']['cliente']['documento'] : '';
                $cronologia[] = [
                    'estado' => 'ENTREGADO',
                    'fecha_hora' => $estadosData['entregado']['fecha'],
                    'detalle' => 'Entregado a: ' . trim("$recibidoPor ($docRecibido)")
                ];
            }
        }

        // Determinar estado actual
        $estadoActual = 'EN_PROCESO';
        if (!empty($guiaData['entregado'])) {
            $estadoActual = 'ENTREGADO';
        } elseif (!empty($estadosData['destino']['completo'])) {
            $estadoActual = 'LISTO_PARA_RECOJO';
        } elseif (!empty($estadosData['transito']['completo'])) {
            $estadoActual = 'EN_TRANSITO';
        }

        return [
            'success' => true,
            'courier' => 'SHALOM',
            'numero_orden' => $numeroOrden,
            'codigo_seguridad' => $guiaData['codigo_orden'] ?? $codigoSeguridad,
            'ose_id' => $oseId,
            'estado_actual' => $estadoActual,
            'entregado' => (bool)($guiaData['entregado'] ?? false),
            'origen' => $guiaData['origen']['nombre'] ?? '',
            'departamento_origen' => $guiaData['origen']['departamento'] ?? '',
            'destino' => $guiaData['destino']['nombre'] ?? '',
            'departamento_destino' => $guiaData['destino']['departamento'] ?? '',
            'direccion_entrega' => $guiaData['direccion_entrega'] ?? '',
            'remitente' => $guiaData['remitente']['nombre'] ?? '',
            'remitente_doc' => $guiaData['remitente']['documento'] ?? '',
            'destinatario' => $guiaData['destinatario']['nombre'] ?? '',
            'destinatario_doc' => $guiaData['destinatario']['documento'] ?? '',
            'contenido' => $guiaData['contenido'] ?? '',
            'monto' => $guiaData['monto'] ?? 0,
            'fecha_emision' => $guiaData['fecha_emision'] ?? '',
            'comprobante' => !empty($guiaData['comprobante']) ? ($guiaData['comprobante']['serie'] . '-' . $guiaData['comprobante']['numero']) : '',
            'eventos_cronologicos' => $cronologia,
            'raw_guia' => $guiaData,
            'raw_estados' => $estadosData
        ];
    }
}
