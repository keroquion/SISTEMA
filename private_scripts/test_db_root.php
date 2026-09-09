<?php
$servername = "localhost";
$username = "petumjvq";
$password = "HjBI32sh5kAb";
$dbname = "petumjvq_pruebas";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully to the database!";
?>
