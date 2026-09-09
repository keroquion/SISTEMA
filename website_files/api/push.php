<?php
/**
 * Push Notifications API - Petulap SST
 * Pure PHP implementation of Web Push protocol (VAPID + RFC 8291 encryption)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config.php";

// ============================================================
// VAPID KEYS (generated once, permanent)
// ============================================================
define('VAPID_PUBLIC_KEY',  'BDAEUCfKgYVhTkBiPexU5d6s9uT44BgFpKdZA8F7k4GBDu62PrNZH41GDBlJ1_DVQAWh3QeM2ojZelKtd2UI2Bw');
define('VAPID_PRIVATE_KEY', '81JXmlQV2X5r8c_bb6yWiBMmoCIEfV38w6wRDidZruU');
define('VAPID_SUBJECT',     'mailto:admin@petulap.store');

// ============================================================
// HELPERS: Base64URL encoding
// ============================================================
function b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function b64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

// ============================================================
// CRYPTO: EC Key format conversions
// ============================================================

/** Convert raw 65-byte EC public key to PEM (SubjectPublicKeyInfo) */
function ecPubToPem($raw65) {
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($prefix . $raw65), 64) . "-----END PUBLIC KEY-----";
}

/** Convert raw 32-byte private + 65-byte public to EC PEM (SEC 1) */
function ecPrivToPem($priv32, $pub65) {
    $der = "\x02\x01\x01"                          // version = 1
         . "\x04\x20" . $priv32                    // privateKey OCTET STRING (32 bytes)
         . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"  // [0] OID prime256v1
         . "\xa1\x44\x03\x42\x00" . $pub65;        // [1] publicKey BIT STRING
    $len = strlen($der);
    $seq = "\x30" . ($len > 127 ? "\x81" . chr($len) : chr($len)) . $der;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($seq), 64) . "-----END EC PRIVATE KEY-----";
}

/** Convert DER ECDSA signature to raw 64-byte (r||s) for JWT */
function derSigToRaw($der) {
    $pos = 2;
    if (ord($der[1]) > 127) $pos += (ord($der[1]) & 0x7f);
    
    if (ord($der[$pos]) !== 0x02) return false;
    $pos++;
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen);
    $pos += $rLen;
    
    if (ord($der[$pos]) !== 0x02) return false;
    $pos++;
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);
    
    return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
         . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
}

// ============================================================
// VAPID: Create signed JWT for push service authentication
// ============================================================
function createVapidJwt($audience) {
    $header  = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => VAPID_SUBJECT
    ]));
    $input = "$header.$payload";

    // Build PEM from raw VAPID keys
    $privRaw = b64url_decode(VAPID_PRIVATE_KEY);
    $pubRaw  = b64url_decode(VAPID_PUBLIC_KEY);
    $pem = ecPrivToPem($privRaw, $pubRaw);

    $key = openssl_pkey_get_private($pem);
    if (!$key) return false;
    
    openssl_sign($input, $derSig, $key, OPENSSL_ALGO_SHA256);
    $rawSig = derSigToRaw($derSig);
    
    return "$input." . b64url_encode($rawSig);
}

// ============================================================
// WEB PUSH: Encrypt payload per RFC 8291 (aes128gcm)
// ============================================================
function encryptPushPayload($payload, $userPubKeyB64, $userAuthB64) {
    $uaPub  = base64_decode($userPubKeyB64);   // 65 bytes (standard base64 from JS)
    $uaAuth = base64_decode($userAuthB64);      // 16 bytes

    // 1. Generate ephemeral ECDH key pair
    $localKey = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC
    ]);
    $det = openssl_pkey_get_details($localKey);
    $localPub = "\x04" . str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                       . str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    // 2. ECDH shared secret
    $uaPem = ecPubToPem($uaPub);
    $uaKeyRes = openssl_pkey_get_public($uaPem);
    if (!$uaKeyRes) return false;
    
    $sharedSecret = openssl_pkey_derive($uaKeyRes, $localKey);
    if ($sharedSecret === false) return false;

    // 3. Key derivation (RFC 8291 Section 3.4)
    //    IKM = HKDF(auth_secret, ecdh_secret, "WebPush: info\0" || ua_pub || as_pub, 32)
    $keyInfo = "WebPush: info\x00" . $uaPub . $localPub;
    $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $uaAuth);

    //    salt = random 16 bytes
    $salt = random_bytes(16);

    //    CEK = HKDF(salt, IKM, "Content-Encoding: aes128gcm\0", 16)
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);

    //    nonce = HKDF(salt, IKM, "Content-Encoding: nonce\0", 12)
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

    // 4. Pad plaintext: payload || 0x02 (delimiter for last record)
    $padded = $payload . "\x02";

    // 5. AES-128-GCM encrypt
    $tag = '';
    $encrypted = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($encrypted === false) return false;
    $ciphertext = $encrypted . $tag;

    // 6. Build aes128gcm content coding header:
    //    salt(16) || rs(4, uint32 = 4096) || idlen(1, = 65) || keyid(65, local public key)
    $rs = pack('N', 4096);
    $header = $salt . $rs . chr(65) . $localPub;

    return $header . $ciphertext;
}

// ============================================================
// SEND: Push notification to a single subscription
// ============================================================
function sendWebPush($endpoint, $p256dh, $auth, $payloadJson) {
    $encrypted = encryptPushPayload($payloadJson, $p256dh, $auth);
    if (!$encrypted) return ['ok' => false, 'msg' => 'Encryption failed'];

    // Extract origin from endpoint for VAPID audience
    $parsed = parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];
    
    $jwt = createVapidJwt($audience);
    if (!$jwt) return ['ok' => false, 'msg' => 'JWT creation failed'];

    $headers = [
        'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Content-Length: ' . strlen($encrypted),
        'TTL: 86400',
        'Urgency: high'
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 201 || $httpCode === 200) {
        return ['ok' => true];
    } elseif ($httpCode === 404 || $httpCode === 410) {
        // Subscription expired or invalid — clean up
        return ['ok' => false, 'expired' => true, 'code' => $httpCode];
    } else {
        return ['ok' => false, 'code' => $httpCode, 'error' => $error, 'response' => $response];
    }
}

// ============================================================
// HIGH-LEVEL: Send push to a user (all their subscriptions)
// ============================================================
function sendPushToUser($db, $userId, $title, $body, $url = '/index.html') {
    $userId = (int)$userId;
    $stmt = $db->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) return;
    
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);
    
    while ($sub = $res->fetch_assoc()) {
        $result = sendWebPush($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
        // Clean up expired subscriptions
        if (!empty($result['expired'])) {
            $sub_id = (int)$sub['id'];
            $stmt_del = $db->prepare("DELETE FROM push_subscriptions WHERE id = ?");
            $stmt_del->bind_param("i", $sub_id);
            $stmt_del->execute();
        }
    }
}

/** Send push to all admin users */
function sendPushToAdmins($db, $title, $body, $url = '/index.html') {
    $res = $db->query("SELECT ps.id, ps.endpoint, ps.p256dh, ps.auth 
                        FROM push_subscriptions ps 
                        JOIN personas p ON ps.user_id = p.id 
                        WHERE p.tipo = 'admin'");
    if (!$res) return;
    
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);
    
    while ($sub = $res->fetch_assoc()) {
        $result = sendWebPush($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
        if (!empty($result['expired'])) {
            $sub_id = (int)$sub['id'];
            $stmt_del = $db->prepare("DELETE FROM push_subscriptions WHERE id = ?");
            $stmt_del->bind_param("i", $sub_id);
            $stmt_del->execute();
        }
    }
}

// ============================================================
// API ENDPOINTS
// ============================================================
$action = $_GET["action"] ?? "";

if (basename($_SERVER['SCRIPT_FILENAME']) === 'push.php') {
    switch ($action) {
    
        case "vapid_key":
            // Public endpoint - return the VAPID public key for client subscription
            echo json_encode(["ok" => true, "key" => VAPID_PUBLIC_KEY]);
            break;
    
        case "subscribe":
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(["ok" => false, "msg" => "No autorizado"]);
                break;
            }
            $data = json_decode(file_get_contents("php://input"), true);
            $endpoint = $data["endpoint"] ?? "";
            $p256dh   = $data["p256dh"] ?? "";
            $authKey  = $data["auth"] ?? "";
            
            if (!$endpoint || !$p256dh || !$authKey) {
                echo json_encode(["ok" => false, "msg" => "Faltan datos de suscripcion"]);
                break;
            }
            
            $db = getDB();
            $userId = (int)$_SESSION['user_id'];
            
            // Upsert: insert or update if endpoint already exists
            $stmt = $db->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), created_at = NOW()");
            $stmt->bind_param("isss", $userId, $endpoint, $p256dh, $authKey);
            
            if ($stmt->execute()) {
                echo json_encode(["ok" => true, "msg" => "Suscrito a notificaciones"]);
            } else {
                echo json_encode(["ok" => false, "msg" => "Error DB: " . $db->error]);
            }
            $db->close();
            break;
    
        case "unsubscribe":
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(["ok" => true]);
                break;
            }
            $db = getDB();
            $userId = (int)$_SESSION['user_id'];
            $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            echo json_encode(["ok" => true, "msg" => "Suscripciones eliminadas"]);
            $db->close();
            break;
    
        case "test":
            // Test endpoint: send a test push to the logged-in user
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(["ok" => false, "msg" => "No autorizado"]);
                break;
            }
            $db = getDB();
            sendPushToUser($db, $_SESSION['user_id'], '🔔 Test Petulap', 'Las notificaciones funcionan correctamente!', '/index.html');
            echo json_encode(["ok" => true, "msg" => "Push de prueba enviado"]);
            $db->close();
            break;
    
        case "setup":
            // One-time: create the push_subscriptions table
            $db = getDB();
            $sql = "CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh VARCHAR(255) NOT NULL,
                auth VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_sub (user_id, endpoint(255))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($db->query($sql)) {
                echo json_encode(["ok" => true, "msg" => "Tabla push_subscriptions creada"]);
            } else {
                echo json_encode(["ok" => false, "msg" => $db->error]);
            }
            $db->close();
            break;
    
        default:
            echo json_encode(["ok" => false, "msg" => "Accion no valida"]);
    }
}
?>
