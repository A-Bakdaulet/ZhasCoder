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

if (isset($_GET['test'])) {
    echo "save_code.php жұмыс істейді!";
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo 'not_logged_in';
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$code = $_POST['code'] ?? '';

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

$check = $conn->prepare("
    SELECT id
    FROM user_saved_codes
    WHERE user_id = ? AND task_id = ?
");
$check->bind_param("ii", $user_id, $task_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $stmt = $conn->prepare("
        UPDATE user_saved_codes
        SET code = ?, updated_at = NOW()
        WHERE user_id = ? AND task_id = ?
    ");
    $stmt->bind_param("sii", $code, $user_id, $task_id);
} else {
    $stmt = $conn->prepare("
        INSERT INTO user_saved_codes (user_id, task_id, code)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $user_id, $task_id, $code);
}

if ($stmt->execute()) {
    echo 'saved';
} else {
    echo 'error: ' . $stmt->error;
}

$stmt->close();
$check->close();
$conn->close();
?>
