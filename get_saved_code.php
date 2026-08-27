<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '';
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

if ($user_id === 0 || $task_id === 0) {
    echo '';
    exit();
}

$stmt = $conn->prepare("
    SELECT code
    FROM user_saved_codes
    WHERE user_id = ? AND task_id = ?
");
$stmt->bind_param("ii", $user_id, $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo $row['code'];
} else {
    echo '';
}

$stmt->close();
$conn->close();
?>
