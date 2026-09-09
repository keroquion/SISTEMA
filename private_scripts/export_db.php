<?php
require_once __DIR__ . "/api/config.php";

$mysqli = getDB();
$tables = array();
$result = $mysqli->query("SHOW TABLES");
if(!$result) {
    die("Error showing tables: " . $mysqli->error);
}
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $result = $mysqli->query("SELECT * FROM `$table`");
    $numFields = $result->field_count;

    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $row2 = $mysqli->query("SHOW CREATE TABLE `$table`")->fetch_row();
    $sql .= $row2[1] . ";\n\n";

    while ($row = $result->fetch_row()) {
        $sql .= "INSERT INTO `$table` VALUES(";
        for ($i = 0; $i < $numFields; $i++) {
            if (isset($row[$i])) {
                $escaped = $mysqli->real_escape_string($row[$i]);
                $sql .= '"' . $escaped . '"';
            } else {
                $sql .= 'NULL';
            }
            if ($i < ($numFields - 1)) {
                $sql .= ',';
            }
        }
        $sql .= ");\n";
    }
    $sql .= "\n\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="petulap_backup_' . date('Y-m-d_H-i-s') . '.sql"');
echo $sql;
?>
