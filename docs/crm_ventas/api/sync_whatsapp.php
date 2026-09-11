<?php
// ==========================================================
// PETULAP SALES CRM - SINCRONIZADOR PASIVO DE WHATSAPP WEB
// Recibe el estado del DOM leído por la extensión de Chrome
// ==========================================================

require_once __DIR__ . '/db.php';

$is_pdo = ($conn instanceof PDO);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_resp(['success' => false, 'error' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$telefono = preg_replace('/[^0-9]/', '', $input['telefono'] ?? '');
$nombre = trim($input['nombre'] ?? 'Contacto WhatsApp');
$emisor = ($input['ultimo_mensaje_emisor'] ?? 'CLIENTE') === 'PETULAP' ? 'PETULAP' : 'CLIENTE';
$texto_snippet = mb_substr(trim($input['ultimo_mensaje_texto'] ?? ''), 0, 255);
$timestamp = $input['ultimo_mensaje_hora'] ?? date('Y-m-d H:i:s');

if (empty($telefono)) {
    json_resp(['success' => false, 'error' => 'Teléfono es requerido'], 400);
}

// Normalizar formato Perú si es de 9 dígitos
if (strlen($telefono) === 9) {
    $telefono = '51' . $telefono;
}

// Buscar si el lead ya existe por teléfono
$sql = "SELECT id, nombre, etapa, temperatura, modelo_interes_texto, presupuesto_aprox, ultimo_mensaje_hora, ultimo_mensaje_emisor 
        FROM crm_leads 
        WHERE telefono = ? OR telefono LIKE ? LIMIT 1";

$search_like = "%" . substr($telefono, -9);
$lead = null;

if ($is_pdo) {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$telefono, $search_like]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $telefono, $search_like);
    $stmt->execute();
    $res = $stmt->get_result();
    $lead = $res->fetch_assoc();
}

if ($lead) {
    // Actualizar el lead con los datos frescos del DOM de WhatsApp Web
    $update_sql = "UPDATE crm_leads 
                   SET ultimo_mensaje_emisor = ?, 
                       ultimo_mensaje_texto = ?, 
                       ultimo_mensaje_hora = ?, 
                       fecha_actualizacion = CURRENT_TIMESTAMP 
                   WHERE id = ?";
    if ($is_pdo) {
        $u_stmt = $conn->prepare($update_sql);
        $u_stmt->execute([$emisor, $texto_snippet, $timestamp, $lead['id']]);
    } else {
        $u_stmt = $conn->prepare($update_sql);
        $u_stmt->bind_param("sssi", $emisor, $texto_snippet, $timestamp, $lead['id']);
        $u_stmt->execute();
    }

    // Calcular temperatura en caliente
    $lead['ultimo_mensaje_emisor'] = $emisor;
    $lead['ultimo_mensaje_hora'] = $timestamp;
    $horas_transcurridas = (time() - strtotime($timestamp)) / 3600;

    $temp = 'VERDE';
    if ($emisor === 'PETULAP') {
        if ($horas_transcurridas >= 24) $temp = 'ROJO';
        elseif ($horas_transcurridas >= 6) $temp = 'AMBAR';
    }
    $lead['temperatura'] = $temp;

    json_resp([
        'success' => true,
        'status' => 'actualizado',
        'lead' => $lead
    ]);
} else {
    // Si no existe, permitir al vendedor crearlo al instante
    json_resp([
        'success' => true,
        'status' => 'no_registrado',
        'telefono' => $telefono,
        'nombre_sugerido' => $nombre
    ]);
}
