<?php
session_start();
require_once "db.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Кіру керек']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$hint_level = isset($_POST['hint_level']) ? (int)$_POST['hint_level'] : 0;

if ($task_id === 0 || $hint_level < 1 || $hint_level > 3) {
    echo json_encode(['success' => false, 'message' => 'Қате параметрлер']);
    exit();
}

$task_stmt = $conn->prepare("SELECT id, points FROM lesson_tasks WHERE id = ?");
$task_stmt->bind_param("i", $task_id);
$task_stmt->execute();
$task_result = $task_stmt->get_result();

if ($task_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Тапсырма табылмады']);
    $task_stmt->close();
    exit();
}

$task = $task_result->fetch_assoc();
$task_stmt->close();

$progress_stmt = $conn->prepare("
    SELECT completed
    FROM user_task_progress
    WHERE user_id = ? AND task_id = ?
");
$progress_stmt->bind_param("ii", $user_id, $task_id);
$progress_stmt->execute();
$progress_result = $progress_stmt->get_result();
$progress = $progress_result->fetch_assoc();
$progress_stmt->close();

if ($progress && (int)$progress['completed'] === 1) {
    echo json_encode(['success' => false, 'message' => 'Тапсырма аяқталған']);
    exit();
}

$hint_stmt = $conn->prepare("
    SELECT hint_text, hint_code, penalty
    FROM hints
    WHERE task_id = ? AND level = ?
");
$hint_stmt->bind_param("ii", $task_id, $hint_level);
$hint_stmt->execute();
$hint_result = $hint_stmt->get_result();

if ($hint_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Хинт табылмады']);
    $hint_stmt->close();
    exit();
}

$hint = $hint_result->fetch_assoc();
$hint_stmt->close();

$insert_hint_stmt = $conn->prepare("
    INSERT IGNORE INTO user_task_hints (user_id, task_id, hint_level)
    VALUES (?, ?, ?)
");
$insert_hint_stmt->bind_param("iii", $user_id, $task_id, $hint_level);
$insert_hint_stmt->execute();
$insert_hint_stmt->close();

$total_penalty_stmt = $conn->prepare("
    SELECT COALESCE(MAX(h.penalty), 0) AS total_penalty
    FROM user_task_hints uh
    JOIN hints h
        ON h.task_id = uh.task_id
        AND h.level = uh.hint_level
    WHERE uh.user_id = ? AND uh.task_id = ?
");

$total_penalty_stmt->bind_param("ii", $user_id, $task_id);
$total_penalty_stmt->execute();
$total_penalty_result = $total_penalty_stmt->get_result();
$total_penalty_row = $total_penalty_result->fetch_assoc();
$total_penalty_stmt->close();

$total_penalty = (int)($total_penalty_row['total_penalty'] ?? 0);
$points = (int)$task['points'];
$score = max(0, $points - $total_penalty);

$used_hints_stmt = $conn->prepare("
    SELECT COUNT(*) AS used_count
    FROM user_task_hints
    WHERE user_id = ? AND task_id = ?
");
$used_hints_stmt->bind_param("ii", $user_id, $task_id);
$used_hints_stmt->execute();
$used_hints_result = $used_hints_stmt->get_result();
$used_hints_row = $used_hints_result->fetch_assoc();
$used_hints_stmt->close();

$used_count = (int)($used_hints_row['used_count'] ?? 0);

$progress_upsert_stmt = $conn->prepare("
    INSERT INTO user_task_progress (user_id, task_id, completed, score, hints_used)
    VALUES (?, ?, 0, ?, ?)
    ON DUPLICATE KEY UPDATE
        score = VALUES(score),
        hints_used = VALUES(hints_used)
");
$progress_upsert_stmt->bind_param("iiii", $user_id, $task_id, $score, $used_count);
$progress_upsert_stmt->execute();
$progress_upsert_stmt->close();

$used_levels_stmt = $conn->prepare("
    SELECT hint_level
    FROM user_task_hints
    WHERE user_id = ? AND task_id = ?
    ORDER BY hint_level ASC
");
$used_levels_stmt->bind_param("ii", $user_id, $task_id);
$used_levels_stmt->execute();
$used_levels_result = $used_levels_stmt->get_result();

$used_hints = [];
while ($row = $used_levels_result->fetch_assoc()) {
    $used_hints[] = (int)$row['hint_level'];
}
$used_levels_stmt->close();

echo json_encode([
    'success' => true,
    'hint_text' => $hint['hint_text'],
    'hint_code' => $hint['hint_code'] ?? '',
    'penalty' => (int)$hint['penalty'],
    'total_penalty' => $total_penalty,
    'score' => $score,
    'used_hints' => $used_hints
]);

$conn->close();
?>
