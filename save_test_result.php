<?php
session_start();

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'algolab';

$conn = new mysqli($host, $user, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo 'db_connection_error';
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo 'not_logged_in';
    exit();
}

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$section_id = (int)($_POST['section_id'] ?? 0);
$score      = (int)($_POST['score'] ?? 0);
$max_score  = (int)($_POST['max_score'] ?? 0);
$results    = $_POST['results'] ?? '[]';

if (!$user_id || !$section_id) {
    echo 'invalid_data';
    exit();
}

$conn->query("
    CREATE TABLE IF NOT EXISTS test_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        section_id INT NOT NULL,
        score INT NOT NULL DEFAULT 0,
        max_score INT NOT NULL DEFAULT 0,
        results LONGTEXT NULL,
        taken_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_section (user_id, section_id)
    )
");

$stmt = $conn->prepare("
    INSERT INTO test_results (user_id, section_id, score, max_score, results, taken_at)
    VALUES (?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        score = VALUES(score),
        max_score = VALUES(max_score),
        results = VALUES(results),
        taken_at = NOW()
");

$stmt->bind_param("iiiis", $user_id, $section_id, $score, $max_score, $results);

if ($stmt->execute()) {
    echo 'ok';
} else {
    echo 'error: ' . $stmt->error;
}

$stmt->close();
$conn->close();
?>
