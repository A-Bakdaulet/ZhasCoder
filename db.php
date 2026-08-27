<?php
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'algolab';

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB connection failed");
}

$conn->set_charset("utf8mb4");
?>