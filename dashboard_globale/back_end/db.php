<?php
$host = "localhost";
$username = "root";
$password = "";
$conn_dw = new mysqli($host, $username, $password, "dw_labo");
if ($conn_dw->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "DW connection failed: " . $conn_dw->connect_error
    ]));
}
$conn_dw->set_charset("utf8mb4");
$conn_labo = new mysqli($host, $username, $password, "labo_db1");
if ($conn_labo->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "Labo connection failed: " . $conn_labo->connect_error
    ]));
}
$conn_labo->set_charset("utf8mb4");
?>