<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(["success" => false, "message" => "Қате сұраныс."], 405);
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === '1';

if ($email === '' || $password === '') {
    respond(["success" => false, "message" => "Email және құпия сөзді енгізіңіз."], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(["success" => false, "message" => "Жарамсыз email."], 400);
}


$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, password, is_verified, role
    FROM users
    WHERE email = ?
    LIMIT 1
");

if (!$stmt) {
    respond(["success" => false, "message" => "Дерекқор қатесі."], 500);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    respond(["success" => false, "message" => "Email немесе құпия сөз қате."], 401);
}

$user = $result->fetch_assoc();


if ((int)$user['is_verified'] !== 1) {
    respond([
        "success" => false,
        "message" => "Email расталмаған. Поштаңызды тексеріңіз."
    ], 403);
}


if (!password_verify($password, $user['password'])) {
    respond(["success" => false, "message" => "Email немесе құпия сөз қате."], 401);
}


session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name'] = $user['last_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['logged_in'] = true;

$redirect = 'profile.php';

if ($user['role'] === 'student') {
    $redirect = 'student_profile.php';
} elseif ($user['role'] === 'teacher') {
    $redirect = 'teacher_dashboard.php';
}
respond([
    "success" => true,
    "message" => "Сәтті кірдіңіз!",
    "name" => $user['first_name'] . ' ' . $user['last_name'],
    "email" => $user['email'],
    "role" => $user['role'],
    "redirect" => $redirect
]);

$conn->close();