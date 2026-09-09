<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
$_SESSION['user_id'] = 1;

$_GET['action'] = 'actualizar';

$json = '{"id": 59, "estado": "LISTO_PARA_RECOGER", "usuario": "Prueba"}';
file_put_contents('php://input', $json);
// Wait, file_get_contents("php://input") only works once per request if it's actual HTTP.
// Let's just mock the HTTP request to soporte.php
$ch = curl_init('http://localhost/api/soporte.php?action=actualizar');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . session_id());
$res = curl_exec($ch);
echo "RESPONSE:\n" . $res;
?>
