<?php
$host = "localhost";
$dbname = "dw_labo";
$username = "root";
$password = "";
$conn_dw = new mysqli($host, $username, $password, $dbname);
if ($conn_dw->connect_error) {
    die("Connexion DW échouée : " . $conn_dw->connect_error);
}
$conn_dw->set_charset("utf8mb4");
?>