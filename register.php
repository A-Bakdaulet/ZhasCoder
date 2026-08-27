<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'db.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';
$role       = $_POST['role'] ?? 'self';
$teacher_email = trim($_POST['teacher_email'] ?? '');

if (!$first_name || !$last_name || !$email || !$password) {
    echo json_encode(["success" => false, "message" => "Барлық өрістерді толтырыңыз"]);
    exit;
}

if (!in_array($role, ['self', 'student', 'teacher'])) {
    echo json_encode(["success" => false, "message" => "Жарамсыз рөл"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Бұл email тіркелген"]);
    exit;
}
$check->close();

$teacher_id = null;
if ($role === 'student') {
    if (empty($teacher_email)) {
        echo json_encode(["success" => false, "message" => "Мұғалімнің email-ін енгізіңіз"]);
        exit;
    }
    
    $teacher_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher'");
    $teacher_check->bind_param("s", $teacher_email);
    $teacher_check->execute();
    $teacher_result = $teacher_check->get_result();
    
    if ($teacher_result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Бұл email-мен тіркелген мұғалім жоқ"]);
        exit;
    }
    
    $teacher_row = $teacher_result->fetch_assoc();
    $teacher_id = $teacher_row['id'];
    $teacher_check->close();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$verification_code = bin2hex(random_bytes(16));

$stmt = $conn->prepare("
    INSERT INTO users 
    (first_name, last_name, email, password, verification_code, is_verified, role, teacher_id) 
    VALUES (?, ?, ?, ?, ?, 0, ?, ?)
");

$stmt->bind_param("ssssssi", $first_name, $last_name, $email, $hashed_password, $verification_code, $role, $teacher_id);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Дерекқор қатесі: " . $conn->error]);
    exit;
}

$user_id = $stmt->insert_id;
$stmt->close();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'zhascoderpython@gmail.com';
    $mail->Password   = 'vqifzwsxkwaqfuha';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('zhascoderpython@gmail.com', 'ZhasCoder');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Email растау - ZhasCoder';

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $verification_link = $protocol . $host . "/algolab/verify_email.php?code=" . $verification_code;

    $mail->Body = "
        <h2>ZhasCoder</h2>
        <p>Тіркелуді аяқтау үшін email растаңыз:</p>
        <p><a href='$verification_link' style='padding:12px 20px; background:#6366f1; color:white; text-decoration:none; border-radius:20px;'>Email растау</a></p>
        <p>$verification_link</p>
    ";

    $mail->send();

    echo json_encode([
        "success" => true,
        "message" => "Растау хаты жіберілді!"
    ]);

} catch (Exception $e) {
    $conn->query("DELETE FROM users WHERE id = $user_id");
    echo json_encode([
        "success" => false,
        "message" => "Email жіберілмеді: " . $mail->ErrorInfo
    ]);
}

$conn->close();
?>