<?php
header("Content-Type: application/json");
echo json_encode([
    "php_version" => phpversion(),
    "openssl" => extension_loaded('openssl'),
    "gmp" => extension_loaded('gmp'),
    "mbstring" => extension_loaded('mbstring'),
    "curl" => extension_loaded('curl'),
    "json" => extension_loaded('json'),
    "loaded_extensions" => get_loaded_extensions()
]);
