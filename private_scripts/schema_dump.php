<?php
require_once "api/config.php";
$db = getDB();
$tables = [];
$res = $db->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

$schema = [];
foreach ($tables as $t) {
    $cRes = $db->query("SHOW COLUMNS FROM `$t`");
    $cols = [];
    while ($c = $cRes->fetch_assoc()) {
        $cols[] = $c;
    }
    $schema[$t] = $cols;
}
echo json_encode(["ok" => true, "schema" => $schema]);
?>
