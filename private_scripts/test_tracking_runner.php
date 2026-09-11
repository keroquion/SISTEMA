<?php
/**
 * Test Runner Aislado para CourierTrackingService
 * Ejecutable via CLI o servidor web local.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/CourierTrackingService.php';

$courier = $_GET['courier'] ?? $argv[1] ?? 'ALL';
$results = [];

// Prueba 1: Cruz del Sur con la guia real del usuario
if ($courier === 'ALL' || strtoupper($courier) === 'CDS') {
    $trackingCode = $_GET['code'] ?? '7D2092CC54';
    $results['cruz_del_sur'] = CourierTrackingService::trackCruzDelSur($trackingCode);
}

// Prueba 2: Shalom con la orden y codigo real del usuario
if ($courier === 'ALL' || strtoupper($courier) === 'SHALOM') {
    $numeroOrden = $_GET['orden'] ?? '94833476';
    $codigo = $_GET['codigo'] ?? '7CJC';
    $results['shalom'] = CourierTrackingService::trackShalom($numeroOrden, $codigo);
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
