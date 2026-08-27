<?php
session_start();

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'algolab';

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection error: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo 'not_logged_in';
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$task_id = (int)($_POST['task_id'] ?? 0);

if ($user_id === 0 || $task_id === 0) {
    echo 'invalid_data';
    exit();
}

$task_check = $conn->prepare("SELECT id FROM lesson_tasks WHERE id = ?");
$task_check->bind_param("i", $task_id);
$task_check->execute();
$task_result = $task_check->get_result();

if ($task_result->num_rows === 0) {
    echo 'task_not_found';
    $task_check->close();
    $conn->close();
    exit();
}
$task_check->close();

$progress_stmt = $conn->prepare("
    DELETE FROM user_task_progress
    WHERE user_id = ? AND task_id = ?
");
$progress_stmt->bind_param("ii", $user_id, $task_id);

if (!$progress_stmt->execute()) {
    echo 'error: ' . $progress_stmt->error;
    $progress_stmt->close();
    $conn->close();
    exit();
}
$progress_stmt->close();

$code_stmt = $conn->prepare("
    DELETE FROM user_saved_codes
    WHERE user_id = ? AND task_id = ?
");
$code_stmt->bind_param("ii", $user_id, $task_id);

if (!$code_stmt->execute()) {
    echo 'error: ' . $code_stmt->error;
    $code_stmt->close();
    $conn->close();
    exit();
}
$code_stmt->close();

echo 'ok';
$conn->close();
?>
