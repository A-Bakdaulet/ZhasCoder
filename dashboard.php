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

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id === 0 && isset($_SESSION['email'])) {
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $row['role'];
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit();
}

$role = $user['role'] ?? 'self';

switch ($role) {
    case 'teacher':
        header("Location: teacher_dashboard.php");
        break;
    case 'student':
        header("Location: student_profile.php");
        break;
    case 'self':
    default:
        header("Location: profile.php");
        break;
}
$conn->close();
exit();
?>