<?php
session_start();
require_once "db.php";

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo "not_logged";
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$hints_used = isset($_POST['hints_used']) ? (int)$_POST['hints_used'] : 0;

if ($task_id === 0) {
    echo "invalid_task";
    exit();
}

$task_stmt = $conn->prepare("
    SELECT points
    FROM lesson_tasks
    WHERE id = ?
");
$task_stmt->bind_param("i", $task_id);
$task_stmt->execute();
$task_result = $task_stmt->get_result();

if ($task_result->num_rows === 0) {
    echo "task_not_found";
    $task_stmt->close();
    exit();
}

$task = $task_result->fetch_assoc();
$task_stmt->close();

$max_points = (int)$task['points'];

$penalty_stmt = $conn->prepare("
    SELECT COALESCE(SUM(penalty), 0) AS total_penalty
    FROM hints
    WHERE task_id = ? AND level <= ?
");
$penalty_stmt->bind_param("ii", $task_id, $hints_used);
$penalty_stmt->execute();
$penalty_result = $penalty_stmt->get_result();
$penalty_row = $penalty_result->fetch_assoc();
$penalty_stmt->close();

$total_penalty = (int)($penalty_row['total_penalty'] ?? 0);

$score = $max_points - $total_penalty;
if ($score < 0) {
    $score = 0;
}

$stmt = $conn->prepare("
    INSERT INTO user_task_progress (user_id, task_id, completed, score, hints_used, completed_at)
    VALUES (?, ?, 1, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        completed = 1,
        score = VALUES(score),
        hints_used = VALUES(hints_used),
        completed_at = NOW()
");
$stmt->bind_param("iiii", $user_id, $task_id, $score, $hints_used);

if ($stmt->execute()) {
    echo "saved";
} else {
    echo "error: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
