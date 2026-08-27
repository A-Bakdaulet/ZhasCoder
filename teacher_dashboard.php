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

if (!isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['user_id'] = $row['id'];
    }
    $stmt->close();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id === 0) {
    session_destroy();
    header("Location: login.html");
    exit();
}

$role_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
$role_check->bind_param("i", $user_id);
$role_check->execute();
$user_role = $role_check->get_result()->fetch_assoc()['role'] ?? 'self';
$role_check->close();

if ($user_role !== 'teacher') {
    header("Location: profile.php");
    exit();
}

$profile_message = '';
$profile_message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $errors = [];
    if (empty($first_name)) $errors[] = "Атыңызды енгізіңіз";
    if (empty($last_name))  $errors[] = "Тегіңізді енгізіңіз";
    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
        $upd->bind_param("ssi", $first_name, $last_name, $user_id);
        if ($upd->execute()) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name']  = $last_name;
            $_SESSION['profile_message'] = "✅ Жеке мәліметтер жаңартылды!";
            $_SESSION['profile_message_type'] = "success";
            header("Location: teacher_dashboard.php");
            exit();
        } else {
            $profile_message = "❌ Мәліметтерді жаңарту кезінде қате кетті";
            $profile_message_type = "error";
        }
        $upd->close();
    } else {
        $profile_message = implode("<br>", $errors);
        $profile_message_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $stmt_pw = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt_pw->bind_param("i", $user_id);
    $stmt_pw->execute();
    $stmt_pw->bind_result($hashed_password);
    $stmt_pw->fetch();
    $stmt_pw->close();
    if (!password_verify($current_password, $hashed_password)) {
        $profile_message = "❌ Ағымдағы құпия сөз қате";
        $profile_message_type = "error";
    } elseif (strlen($new_password) < 8) {
        $profile_message = "❌ Жаңа құпия сөз кемінде 8 символ болуы керек";
        $profile_message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $profile_message = "❌ Құпия сөздер сәйкес келмейді";
        $profile_message_type = "error";
    } else {
        $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $new_hashed, $user_id);
        if ($upd->execute()) {
            $profile_message = "✅ Құпия сөз сәтті өзгертілді!";
            $profile_message_type = "success";
        } else {
            $profile_message = "❌ Құпия сөзді өзгерту кезінде қате кетті";
            $profile_message_type = "error";
        }
        $upd->close();
    }
}

if (isset($_SESSION['profile_message'])) {
    $profile_message = $_SESSION['profile_message'];
    $profile_message_type = $_SESSION['profile_message_type'];
    unset($_SESSION['profile_message'], $_SESSION['profile_message_type']);
}

if (isset($_POST['remove_avatar'])) {
    $upd = $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    $upd->bind_param("i", $user_id);
    if ($upd->execute()) {
        if (!empty($teacher['avatar']) && file_exists($teacher['avatar'])) {
            unlink($teacher['avatar']);
        }
        header("Location: teacher_dashboard.php?removed=success");
        exit();
    }
    $upd->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && !isset($_POST['remove_avatar']) && !isset($_POST['action'])) {
    $target_dir = "uploads/avatars/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    $file_extension = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($file_extension, $allowed_extensions)) {
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            $upd = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $upd->bind_param("si", $target_file, $user_id);
            $upd->execute();
            $upd->close();
            header("Location: teacher_dashboard.php?success=1");
            exit();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$initials = '?';
if (!empty($teacher['first_name']) && !empty($teacher['last_name'])) {
    $initials = mb_strtoupper(mb_substr($teacher['first_name'],0,1) . mb_substr($teacher['last_name'],0,1));
}

$avatar_path = '';
if (!empty($teacher['avatar']) && file_exists($teacher['avatar'])) {
    $avatar_path = $teacher['avatar'] . '?v=' . time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];

    if ($action === 'create_class') {
        $class_name = trim($_POST['class_name'] ?? '');
        if (empty($class_name)) {
            echo json_encode(['success' => false, 'message' => 'Класс атауын енгізіңіз']);
            exit();
        }
        $ins = $conn->prepare("INSERT INTO classes (name, teacher_id) VALUES (?, ?)");
        $ins->bind_param("si", $class_name, $user_id);
        if ($ins->execute()) {
            echo json_encode(['success' => true, 'class_id' => $ins->insert_id, 'class_name' => $class_name]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Қате: ' . $conn->error]);
        }
        $ins->close();
        exit();
    }

    if ($action === 'delete_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $del_m = $conn->prepare("DELETE FROM class_members WHERE class_id = ?");
        $del_m->bind_param("i", $class_id);
        $del_m->execute();
        $del_m->close();
        $del_c = $conn->prepare("DELETE FROM classes WHERE id = ? AND teacher_id = ?");
        $del_c->bind_param("ii", $class_id, $user_id);
        if ($del_c->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Қате']);
        }
        $del_c->close();
        exit();
    }

    if ($action === 'add_student_to_class') {
        $class_id  = (int)($_POST['class_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        
        $chk = $conn->prepare("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?");
        $chk->bind_param("ii", $class_id, $student_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Оқушы бұл класста бар']);
            $chk->close();
            exit();
        }
        $chk->close();
        $ins = $conn->prepare("INSERT INTO class_members (class_id, student_id) VALUES (?, ?)");
        $ins->bind_param("ii", $class_id, $student_id);
        if ($ins->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Қате']);
        }
        $ins->close();
        exit();
    }

    if ($action === 'remove_student_from_class') {
    $class_id   = (int)($_POST['class_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);

    if ($class_id <= 0 || $student_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Қате параметрлер']);
        exit();
    }

    $check = $conn->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $check->bind_param("ii", $class_id, $user_id);
    $check->execute();
    $check_result = $check->get_result();
    $class_exists = $check_result->fetch_assoc();
    $check->close();

    if (!$class_exists) {
        echo json_encode(['success' => false, 'message' => 'Класс табылмады']);
        exit();
    }

    $del = $conn->prepare("DELETE FROM class_members WHERE class_id = ? AND student_id = ?");
    $del->bind_param("ii", $class_id, $student_id);
    $del->execute();

    if ($del->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Оқушы бұл класта табылмады']);
    }

    $del->close();
    exit();
}


    echo json_encode(['success' => false, 'message' => 'Белгісіз әрекет']);
    exit();
}
$students_query = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE teacher_id = ? AND role = 'student' ORDER BY first_name");
$students_query->bind_param("i", $user_id);
$students_query->execute();
$all_students_result = $students_query->get_result();
$all_students = [];
while ($s = $all_students_result->fetch_assoc()) {
    $all_students[] = $s;
}
$students_query->close();

$classes_query = $conn->prepare("SELECT * FROM classes WHERE teacher_id = ? ORDER BY created_at");
$classes_query->bind_param("i", $user_id);
$classes_query->execute();
$classes_result = $classes_query->get_result();
$classes = [];
while ($c = $classes_result->fetch_assoc()) {
    $classes[] = $c;
}
$classes_query->close();

$class_members = [];
foreach ($classes as $cls) {
    $mem_q = $conn->prepare("SELECT student_id FROM class_members WHERE class_id = ?");
    $mem_q->bind_param("i", $cls['id']);
    $mem_q->execute();
    $mem_r = $mem_q->get_result();
    while ($m = $mem_r->fetch_assoc()) {
        $class_members[$cls['id']][] = $m['student_id'];
    }
    $mem_q->close();
}

$assigned_ids = [];
foreach ($class_members as $cid => $members) {
    foreach ($members as $sid) {
        $assigned_ids[] = $sid;
    }
}
$assigned_ids = array_unique($assigned_ids);

$unassigned_students = array_filter($all_students, fn($s) => !in_array($s['id'], $assigned_ids));

$sections_result = $conn->query("SELECT * FROM sections ORDER BY order_number");
$sections = [];
while ($sec = $sections_result->fetch_assoc()) {
    $sections[] = $sec;
}

$test_sections = [];
$student_section_progress = [];
foreach ($all_students as $student) {
    $sid = $student['id'];
    foreach ($sections as $section) {
        $sec_id = $section['id'];
        $prog_q = $conn->prepare("
    SELECT 
        COUNT(DISTINCT l.id) as total,
        COUNT(DISTINCT CASE 
            WHEN lesson_stats.total_tasks = lesson_stats.completed_tasks THEN l.id
        END) as done
    FROM lessons l
    LEFT JOIN (
        SELECT 
            lt.lesson_id,
            COUNT(lt.id) as total_tasks,
            COUNT(CASE WHEN utp.completed = 1 THEN 1 END) as completed_tasks
        FROM lesson_tasks lt
        LEFT JOIN user_task_progress utp 
            ON utp.task_id = lt.id 
            AND utp.user_id = ?
        GROUP BY lt.lesson_id
    ) as lesson_stats ON lesson_stats.lesson_id = l.id
    WHERE l.section_id = ?
");
$prog_q->bind_param("ii", $sid, $sec_id);
$prog_q->execute();
$prog_data = $prog_q->get_result()->fetch_assoc();
        $prog_q->bind_param("ii", $sid, $sec_id);
        $prog_q->execute();
        $prog_data = $prog_q->get_result()->fetch_assoc();
        $prog_q->close();

        $student_section_progress[$sid][$sec_id] = [
            'total' => (int)$prog_data['total'],
            'done'  => (int)($prog_data['done'] ?? 0),
        ];
    }
}

$test_sections = [];
$test_results_matrix = [];

for ($i = 1; $i <= 12; $i++) {
    $test_sections[$i] = [
        'order_number' => $i,
        'title' => 'Тест ' . $i
    ];
}

$table_check = $conn->query("SHOW TABLES LIKE 'test_results'")->num_rows > 0;
if ($table_check) {
    $tr_stmt = $conn->prepare("
        SELECT 
            tr.user_id,
            tr.section_id,
            tr.score,
            tr.max_score,
            tr.retake_count,
            tr.taken_at,
            s.order_number
        FROM test_results tr
        JOIN users u ON u.id = tr.user_id
        JOIN sections s ON s.id = tr.section_id
        WHERE u.teacher_id = ? AND u.role = 'student'
        ORDER BY tr.taken_at DESC
    ");
    $tr_stmt->bind_param("i", $user_id);
    $tr_stmt->execute();
    $tr_result = $tr_stmt->get_result();

    while ($row = $tr_result->fetch_assoc()) {
        $sid = (int)$row['user_id'];
        $testNo = (int)$row['order_number'];

        if ($testNo < 1 || $testNo > 12) {
            continue;
        }

        if (!isset($test_results_matrix[$sid][$testNo])) {
            $test_results_matrix[$sid][$testNo] = $row;
        }
    }

    $tr_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мұғалім кабинеті - ZhasCoder</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="website icon" type="icon" href="img/ava.png">
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --primary-pastel: #e0e7ff;
            --secondary: #8b5cf6;
            --secondary-light: #a78bfa;
            --secondary-pastel: #ede9fe;
            --accent: #2dd4bf;
            --white: #ffffff;
            --white-smoke: #f8fafc;
            --gray-light: #f1f5f9;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --shadow-light: rgba(99, 102, 241, 0.08);
            --shadow-medium: rgba(99, 102, 241, 0.15);
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--white-smoke) 0%, #f1f4ff 100%);
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(99,102,241,0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 30%, rgba(139,92,246,0.05) 0%, transparent 20%),
                radial-gradient(circle at 30% 70%, rgba(45,212,191,0.05) 0%, transparent 30%);
            pointer-events: none;
            z-index: -1;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 40px;
            background: rgba(255,255,255,0.85);
            box-shadow: 0 8px 30px var(--shadow-light);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(99,102,241,0.15);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 20px;
            border-radius: 50px;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            box-shadow: 0 8px 20px var(--shadow-light);
            border: 1px solid rgba(255,255,255,0.6);
            transition: all 0.4s ease;
        }

        .logo:hover { transform: translateY(-3px); box-shadow: 0 12px 30px var(--shadow-medium); }

        .logo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 26px; font-weight: 700;
            box-shadow: 0 6px 15px rgba(99,102,241,0.3);
        }

        .logo-text {
            font-size: 28px; font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 60%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Montserrat', sans-serif;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 6px;
            box-shadow: 0 8px 25px rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.15);
        }

        .nav-button {
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 28px;
            border-radius: 30px;
            color: var(--text-light);
            background: transparent;
            border: none;
            transition: all 0.3s ease;
        }

        .nav-button:hover { color: var(--primary-dark); background: var(--primary-pastel); }
        .nav-button.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(99,102,241,0.3);
        }

        .avatar-dropdown { position: relative; }

        .avatar-btn {
            display: flex; align-items: center; gap: 10px;
            border: none;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            cursor: pointer;
            padding: 8px 16px 8px 12px;
            border-radius: 30px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px var(--shadow-light);
            border: 1px solid rgba(255,255,255,0.5);
        }

        .avatar-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px var(--shadow-medium); }

        .avatar-fallback {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 18px;
            border: 3px solid white;
        }

        .avatar-img {
            width: 42px; height: 42px; border-radius: 50%;
            object-fit: cover; border: 3px solid white;
        }

        .avatar-caret { font-size: 14px; color: var(--primary); transition: transform 0.3s ease; }
        .avatar-btn[aria-expanded="true"] .avatar-caret { transform: rotate(180deg); }

        .avatar-menu {
            position: absolute; right: 0; top: calc(100% + 12px);
            width: 280px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(99,102,241,0.15);
            overflow: hidden;
            transform: translateY(10px); opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(99,102,241,0.15);
            z-index: 1000;
        }

        .avatar-menu.open { opacity: 1; transform: translateY(0); pointer-events: auto; }

        .avatar-menu-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            display: flex; align-items: center; gap: 15px;
            border-bottom: 1px solid rgba(99,102,241,0.2);
        }

        .avatar-mini {
            width: 48px; height: 48px; border-radius: 16px;
            overflow: hidden; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(99,102,241,0.25);
            border: 2px solid white;
        }

        .avatar-mini-fallback {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 20px;
        }

        .avatar-name { font-weight: 700; color: var(--primary-dark); font-size: 16px; font-family: 'Montserrat', sans-serif; }
        .avatar-email { font-size: 13px; color: var(--text-light); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; }

        .avatar-menu-divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(99,102,241,0.3), transparent); margin: 6px 0; }

        .avatar-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 20px;
            text-decoration: none; color: var(--text-dark);
            font-weight: 500; font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .avatar-item:hover { background: var(--primary-pastel); padding-left: 25px; border-left-color: var(--primary); }
        .avatar-item .icon { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 16px; background: rgba(99,102,241,0.1); border-radius: 8px; }

        .avatar-item-danger { color: #ef4444; }
        .avatar-item-danger:hover { background: #fee2e2; border-left-color: #ef4444; }
        .avatar-item-danger .icon { background: rgba(239,68,68,0.1); }

        .main-container { max-width: 1300px; margin: 40px auto; padding: 0 20px; }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            background: white;
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.1);
            flex-wrap: wrap;
        }

        .profile-avatar {
            width: 120px; height: 120px; position: relative;
        }

        .profile-avatar img {
            width: 100%; height: 100%; border-radius: 50%;
            object-fit: cover; border: 4px solid var(--primary-pastel);
            box-shadow: 0 10px 20px rgba(99,102,241,0.2);
        }

        .avatar-initials-big {
            width: 120px; height: 120px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; display: flex; align-items: center; justify-content: center;
            font-size: 48px; font-weight: 700;
            border: 4px solid var(--primary-pastel);
            box-shadow: 0 10px 20px rgba(99,102,241,0.2);
        }

        .teacher-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, var(--primary-pastel), var(--secondary-pastel));
            color: var(--primary-dark);
            font-size: 13px; font-weight: 600;
            padding: 5px 14px; border-radius: 20px;
            margin-top: 8px;
            font-family: 'Montserrat', sans-serif;
        }

        .profile-name { font-size: 32px; font-weight: 700; font-family: 'Montserrat', sans-serif; }
        .profile-email { font-size: 16px; color: var(--text-light); display: flex; align-items: center; gap: 8px; margin-top: 5px; }
        .profile-email i { color: var(--primary); }

        .profile-stats { display: flex; gap: 30px; margin-left: auto; flex-wrap: wrap; }
        .stat-item { text-align: center; }
        .stat-value {
            font-size: 28px; font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .stat-label { font-size: 14px; color: var(--text-light); margin-top: 5px; }

        .tabs {
            display: flex; gap: 10px;
            background: white;
            border-radius: 20px;
            padding: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.1);
            flex-wrap: wrap;
        }

        .tab-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 14px;
            background: transparent;
            color: var(--text-light);
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            white-space: nowrap;
        }

        .tab-btn:hover { background: var(--primary-pastel); color: var(--primary); }
        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 6px 15px rgba(99,102,241,0.3);
        }

        .tab-badge {
            background: rgba(255,255,255,0.3);
            color: white;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 700;
        }

        .tab-btn:not(.active) .tab-badge {
            background: var(--primary-pastel);
            color: var(--primary);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .card {
            background: white;
            border-radius: 30px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.1);
            margin-bottom: 25px;
        }

        .card-title {
            font-size: 18px; font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
            font-family: 'Montserrat', sans-serif;
        }

        .card-title i { color: var(--primary); font-size: 20px; }

        .student-list { display: flex; flex-direction: column; gap: 12px; }

        .student-item {
            display: flex; align-items: center; gap: 15px;
            padding: 14px 18px;
            background: var(--gray-light);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .student-item:hover { background: var(--primary-pastel); transform: translateX(4px); }

        .student-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700; flex-shrink: 0;
        }

        .student-info { flex: 1; }
        .student-name { font-weight: 600; font-size: 15px; }
        .student-email { font-size: 13px; color: var(--text-light); }

        .btn-icon {
            width: 36px; height: 36px; border-radius: 10px;
            border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; transition: all 0.3s ease;
        }

        .btn-add { background: #dcfce7; color: #16a34a; }
        .btn-add:hover { background: #16a34a; color: white; transform: scale(1.1); }
        .btn-remove { background: #fee2e2; color: #dc2626; }
        .btn-remove:hover { background: #dc2626; color: white; transform: scale(1.1); }

        .classes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }

        .class-card {
            background: white;
            border-radius: 24px;
            padding: 22px;
            box-shadow: 0 6px 20px rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.12);
            transition: all 0.3s ease;
        }

        .class-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(99,102,241,0.15); }

        .class-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px;
        }

        .class-name {
            font-size: 18px; font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-dark);
            display: flex; align-items: center; gap: 8px;
        }

        .class-name i { font-size: 16px; }

        .btn-delete-class {
            background: #fee2e2; color: #dc2626;
            border: none; border-radius: 10px;
            padding: 8px 12px; cursor: pointer;
            font-size: 13px; font-weight: 600;
            transition: all 0.3s ease;
            display: flex; align-items: center; gap: 6px;
        }

        .btn-delete-class:hover { background: #dc2626; color: white; }

        .class-members { margin-top: 12px; }

        .class-member-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-pastel); color: var(--primary-dark);
            border-radius: 20px; padding: 5px 12px;
            font-size: 13px; font-weight: 500;
            margin: 4px; transition: all 0.2s ease;
        }

        .chip-remove {
            cursor: pointer; color: var(--error); font-size: 12px;
            width: 16px; height: 16px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; transition: all 0.2s ease;
        }

        .chip-remove:hover { background: var(--error); color: white; }

        .add-student-to-class {
            margin-top: 14px;
            display: flex; gap: 10px;
        }

        .add-student-to-class select {
            flex: 1; padding: 10px 14px;
            border: 2px solid var(--border, #e2e8f0);
            border-radius: 12px; font-family: 'Poppins', sans-serif;
            font-size: 14px; color: var(--text-dark);
            background: var(--gray-light);
            outline: none; transition: border 0.3s ease;
        }

        .add-student-to-class select:focus { border-color: var(--primary); background: white; }

        .btn-add-to-class {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border: none; border-radius: 12px;
            padding: 10px 16px; cursor: pointer; font-size: 18px;
            transition: all 0.3s ease;
        }

        .btn-add-to-class:hover { transform: scale(1.08); box-shadow: 0 6px 15px rgba(99,102,241,0.4); }

        .create-class-form {
            display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap;
        }

        .create-class-form input {
            flex: 1; padding: 14px 18px;
            border: 2px solid #e2e8f0; border-radius: 16px;
            font-family: 'Poppins', sans-serif; font-size: 15px;
            color: var(--text-dark); background: var(--gray-light);
            outline: none; transition: all 0.3s ease;
            min-width: 200px;
        }

        .create-class-form input:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px var(--primary-pastel); }

        .btn-create {
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border: none; border-radius: 16px;
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: 15px; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 15px rgba(99,102,241,0.3);
        }

        .btn-create:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(99,102,241,0.4); }

        .progress-table-wrap {
            overflow-x: auto;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(99,102,241,0.08);
        }

        .progress-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 900px;
        }

        .progress-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 12px 10px;
            text-align: center;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 12px;
            white-space: nowrap;
        }

        .progress-table th:first-child {
            text-align: left;
            padding-left: 18px;
            border-radius: 20px 0 0 0;
            min-width: 160px;
        }

        .progress-table th:last-child { border-radius: 0 20px 0 0; }

        .progress-table tr:nth-child(even) td { background: #f8fafc; }

        .progress-table td {
            padding: 11px 10px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s ease;
        }

        .progress-table td:first-child {
            text-align: left;
            padding-left: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .progress-table tr:hover td { background: var(--primary-pastel) !important; }

        .progress-table tr:last-child td { border-bottom: none; }

        .cell-done {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; font-size: 12px;
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }
        .cell-low {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        }


        .cell-partial {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white; font-size: 12px; font-weight: 700;
            box-shadow: 0 2px 8px rgba(245,158,11,0.3);
        }

        .cell-empty {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 50%;
            background: #e2e8f0; color: #94a3b8; font-size: 12px;
        }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: var(--text-dark); }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--primary); font-size: 16px; pointer-events: none; }
        .form-control {
            width: 100%; padding: 14px 16px 14px 48px;
            border: 2px solid var(--gray-light); border-radius: 30px;
            font-size: 15px; transition: all 0.2s;
            background: #fafbfc; font-family: 'Poppins', sans-serif;
        }
        .form-control:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .form-control:disabled { background: var(--gray-light); cursor: not-allowed; opacity: 0.7; }
        .btn-primary-form {
            padding: 12px 24px; border-radius: 30px; font-weight: 600;
            cursor: pointer; border: none; font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; width: 100%; transition: all 0.2s;
        }
        .btn-primary-form:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(99,102,241,0.4); }
        .message {
            padding: 14px 18px; border-radius: 20px;
            margin-bottom: 24px; font-weight: 500; animation: fadeIn 0.3s;
        }
        .message.success { background: #f0fff4; color: #38a169; border-left: 3px solid #38a169; }
        .message.error   { background: #fff5f5; color: #e53e3e; border-left: 3px solid #e53e3e; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
        small { font-size: 12px; color: var(--text-light); margin-top: 8px; display: block; }

        .filter-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 20px;
            border: 2px solid var(--border, #e2e8f0);
            background: white; color: var(--text-light);
            font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: 13px;
            cursor: pointer; transition: all 0.25s ease;
        }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-pastel); }
        .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border-color: transparent;
            box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }
        .filter-count {
            background: rgba(255,255,255,0.25);
            padding: 1px 7px; border-radius: 10px;
            font-size: 11px; font-weight: 700;
        }
        .filter-btn:not(.active) .filter-count {
            background: var(--primary-pastel); color: var(--primary);
        }

        .avatar-upload-form {
            display: none; position: fixed;
            top: 50%; left: 50%; transform: translate(-50%, -50%);
            background: white; padding: 30px; border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            z-index: 2000; width: 90%; max-width: 400px;
        }
        .avatar-upload-form.show { display: block; }
        .modal-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; color: var(--text-dark); font-family: 'Montserrat', sans-serif; }
        .file-input { width: 100%; padding: 10px; border: 2px dashed var(--primary-pastel); border-radius: 15px; margin-bottom: 20px; cursor: pointer; }
        .form-actions { display: flex; gap: 15px; }
        .btn { padding: 12px 24px; border-radius: 50px; border: none; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-family: 'Montserrat', sans-serif; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; flex: 1; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(99,102,241,0.3); }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); flex: 1; }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-danger {
            width: 100%; padding: 12px; border-radius: 50px;
            border: 2px solid #ef4444; background: #fee2e2; color: #ef4444;
            font-weight: 600; cursor: pointer; display: flex; align-items: center;
            justify-content: center; gap: 10px;
            font-family: 'Montserrat', sans-serif; font-size: 14px; transition: all 0.3s ease;
        }
        .btn-danger:hover { background: #ef4444; color: white; transform: translateY(-2px); }

        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1999; }
        .overlay.show { display: block; }

        .alert-fixed {
            padding: 15px 25px; border-radius: 50px; color: white; font-weight: 600;
            position: fixed; top: 100px; right: 20px; z-index: 3000;
            box-shadow: 0 10px 20px rgba(16,185,129,0.3);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .avatar-edit-btn {
            position: absolute; bottom: 5px; right: 5px;
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; cursor: pointer; border: 2px solid white;
            transition: all 0.3s ease;
        }
        .avatar-edit-btn:hover { transform: scale(1.1); box-shadow: 0 5px 15px rgba(99,102,241,0.4); }

        .empty-state {
            text-align: center; padding: 40px 20px; color: var(--text-light);
        }

        .empty-state i { font-size: 48px; margin-bottom: 15px; color: var(--primary-light); }
        .empty-state p { font-size: 16px; }

        .toast {
            position: fixed; bottom: 30px; right: 30px;
            padding: 14px 22px; border-radius: 16px;
            color: white; font-weight: 600; font-size: 15px;
            display: flex; align-items: center; gap: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            z-index: 9999;
            opacity: 0; transform: translateY(20px);
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: linear-gradient(135deg, #10b981, #059669); }
        .toast.error { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .mobile-menu-btn {
            display: none;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none; color: white; font-size: 24px;
            cursor: pointer; padding: 14px 18px; border-radius: 16px;
            box-shadow: 0 8px 20px rgba(99,102,241,0.3);
        }

        @media (max-width: 850px) {
            .mobile-menu-btn { display: block; }
            .nav-menu {
                display: none; position: absolute;
                top: 100%; left: 20px; right: 20px;
                flex-direction: column;
                background: rgba(255,255,255,0.97);
                padding: 20px; border-radius: 24px;
                box-shadow: 0 20px 40px rgba(99,102,241,0.15);
                border: 1px solid rgba(99,102,241,0.2);
                margin-top: 10px;
            }
            .nav-menu.show { display: flex; }
            .nav-button { width: 100%; text-align: center; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-stats { margin-left: 0; justify-content: center; }
        }

        @media (max-width: 600px) {
            header { padding: 15px 20px; }
            .main-container { padding: 0 12px; }
            .tabs { gap: 6px; }
            .tab-btn { font-size: 12px; padding: 10px 12px; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">
        <div class="logo-icon">📚</div>
        <span class="logo-text">ZhasCoder</span>
    </div>

    <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

    <div class="nav-menu" id="navMenu">
        <span class="nav-button" onclick="window.location.href='index.php#about'">Біз туралы</span>
        <span class="nav-button" onclick="window.location.href='index.php'">Басты бет</span>
        <span class="nav-button" onclick="window.location.href='index.php#contact'">Байланыс</span>
        <span class="nav-button" onclick="window.location.href='topics.php'">Тапсырмалар</span>
    </div>

    <div class="avatar-dropdown">
        <button class="avatar-btn" id="avatarBtn" type="button" aria-expanded="false">
            <?php if (!empty($avatar_path)): ?>
                <img src="<?= htmlspecialchars($avatar_path) ?>" class="avatar-img" alt="avatar">
            <?php else: ?>
                <div class="avatar-fallback"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <span class="avatar-caret">▾</span>
        </button>
        <div class="avatar-menu" id="avatarMenu">
            <div class="avatar-menu-header">
                <div class="avatar-mini">
                    <?php if (!empty($avatar_path)): ?>
                        <img src="<?= htmlspecialchars($avatar_path) ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <div class="avatar-mini-fallback"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="avatar-name"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></div>
                    <div class="avatar-email"><?= htmlspecialchars($teacher['email']) ?></div>
                </div>
            </div>
            <div class="avatar-menu-divider"></div>
            <a class="avatar-item" href="teacher_dashboard.php">
                <span class="icon">👤</span><span>Жеке кабинет</span>
            </a>
            <div class="avatar-menu-divider"></div>
            <a class="avatar-item avatar-item-danger" href="logout.php">
                <span class="icon">⎋</span><span>Шығу</span>
            </a>
        </div>
    </div>
</header>

<div class="main-container">

    <?php if (isset($_GET['removed']) || isset($_GET['success'])): ?>
    <div class="alert-fixed" id="statusAlert" style="background: <?= isset($_GET['removed']) ? '#f59e0b' : '#10b981' ?>;">
        <?= isset($_GET['removed']) ? '🗑️ Аватар өшірілді!' : '✅ Аватар жаңартылды!' ?>
    </div>
    <script>
        setTimeout(() => {
            const a = document.getElementById('statusAlert');
            if (a) { a.style.opacity = '0'; a.style.transition = 'opacity 0.3s'; setTimeout(() => a.style.display='none', 300); }
        }, 3000);
        setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.delete('removed'); url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url);
        }, 100);
    </script>
    <?php endif; ?>
    <div class="profile-header">
        <div class="profile-avatar" style="position:relative; width:120px; height:120px;">
            <?php if (!empty($avatar_path)): ?>
                <img src="<?= htmlspecialchars($avatar_path) ?>" alt="avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover;border:4px solid var(--primary-pastel);box-shadow:0 10px 20px rgba(99,102,241,0.2);">
            <?php else: ?>
                <div class="avatar-initials-big"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <div class="avatar-edit-btn" onclick="showAvatarUpload()">
                <i class="fas fa-camera"></i>
            </div>
        </div>
        <div>
            <h1 class="profile-name"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h1>
            <div class="profile-email">
                <i class="fas fa-envelope"></i>
                <?= htmlspecialchars($teacher['email']) ?>
            </div>
            <div class="teacher-badge">
                <i class="fas fa-chalkboard-user"></i> Мұғалім
            </div>
        </div>
        <div class="profile-stats">
            <div class="stat-item">
                <div class="stat-value"><?= count($all_students) ?></div>
                <div class="stat-label">Барлық оқушылар</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= count($classes) ?></div>
                <div class="stat-label">Класстар</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= count($unassigned_students) ?></div>
                <div class="stat-label">Бөлінбегендер</div>
            </div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('all-students', this)">
            <i class="fas fa-users"></i> Барлық оқушылар
            <span class="tab-badge"><?= count($all_students) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('unassigned', this)">
            <i class="fas fa-user-clock"></i> Класқа қосылмаған
            <span class="tab-badge"><?= count($unassigned_students) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('classes', this)">
            <i class="fas fa-school"></i> Класстар
            <span class="tab-badge"><?= count($classes) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('progress', this)">
            <i class="fas fa-chart-bar"></i> Прогресс кестесі
        </button>
        <button class="tab-btn" onclick="switchTab('tests', this)">
    <i class="fas fa-clipboard-check"></i> Тесттер
</button>
    </div>
    <div class="tab-content active" id="tab-all-students">
        <div class="card">
            <div class="card-title"><i class="fas fa-users"></i> Барлық оқушылар</div>
            <?php if (empty($all_students)): ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <p>Әзірге оқушылар жоқ</p>
            </div>
            <?php else: ?>
            <div class="student-list">
                <?php foreach ($all_students as $s): ?>
                <?php
                    $si = mb_strtoupper(mb_substr($s['first_name'],0,1) . mb_substr($s['last_name'],0,1));
                    
                    $in_classes = [];
                    foreach ($classes as $cls) {
                        if (isset($class_members[$cls['id']]) && in_array($s['id'], $class_members[$cls['id']])) {
                            $in_classes[] = $cls['name'];
                        }
                    }
                ?>
                <div class="student-item">
                    <div class="student-avatar"><?= htmlspecialchars($si) ?></div>
                    <div class="student-info">
                        <div class="student-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                        <div class="student-email"><?= htmlspecialchars($s['email']) ?></div>
                        <?php if (!empty($in_classes)): ?>
                        <div style="margin-top:4px; font-size:12px; color:var(--primary);">
                            <i class="fas fa-school"></i> <?= htmlspecialchars(implode(', ', $in_classes)) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-content" id="tab-unassigned">
        <div class="card">
            <div class="card-title"><i class="fas fa-user-clock"></i> Класқа қосылмаған оқушылар</div>
            <?php if (empty($unassigned_students)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color:var(--success)"></i>
                <p>Барлық оқушылар класқа бөлінген!</p>
            </div>
            <?php else: ?>
            <div class="student-list">
                <?php foreach ($unassigned_students as $s): ?>
                <?php $si = mb_strtoupper(mb_substr($s['first_name'],0,1) . mb_substr($s['last_name'],0,1)); ?>
                <div class="student-item" id="unassigned-<?= $s['id'] ?>">
                    <div class="student-avatar"><?= htmlspecialchars($si) ?></div>
                    <div class="student-info">
                        <div class="student-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                        <div class="student-email"><?= htmlspecialchars($s['email']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-content" id="tab-classes">
        <div class="card">
            <div class="card-title"><i class="fas fa-plus-circle"></i> Жаңа класс жасау</div>
            <div class="create-class-form">
                <input type="text" id="newClassName" placeholder="Класс атауын енгізіңіз (мысалы: 7А)">
                <button class="btn-create" onclick="createClass()">
                    <i class="fas fa-plus"></i> Жасау
                </button>
            </div>
        </div>

        <div class="classes-grid" id="classesGrid">
            <?php foreach ($classes as $cls): ?>
            <?php
                $cls_members = [];
                if (isset($class_members[$cls['id']])) {
                    foreach ($all_students as $s) {
                        if (in_array($s['id'], $class_members[$cls['id']])) {
                            $cls_members[] = $s;
                        }
                    }
                }
            ?>
            <div class="class-card" id="class-card-<?= $cls['id'] ?>">
                <div class="class-header">
                    <div class="class-name">
                        <i class="fas fa-school"></i>
                        <?= htmlspecialchars($cls['name']) ?>
                    </div>
                    <button class="btn-delete-class" onclick="deleteClass(<?= $cls['id'] ?>, '<?= htmlspecialchars($cls['name']) ?>')">
                        <i class="fas fa-trash"></i> Өшіру
                    </button>
                </div>

                <div class="class-members" id="members-<?= $cls['id'] ?>">
                    <?php if (empty($cls_members)): ?>
                    <div style="color:var(--text-light); font-size:13px;" id="empty-msg-<?= $cls['id'] ?>">
                        <i class="fas fa-info-circle"></i> Оқушылар жоқ
                    </div>
                    <?php else: ?>
                    <?php foreach ($cls_members as $m): ?>
                    <span class="class-member-chip" id="chip-<?= $cls['id'] ?>-<?= $m['id'] ?>">
                        <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                        <span class="chip-remove" onclick="removeFromClass(<?= $cls['id'] ?>, <?= $m['id'] ?>, '<?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>')">✕</span>
                    </span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="add-student-to-class">
                    <select id="select-<?= $cls['id'] ?>">
                        <option value="">— Оқушы таңдаңыз —</option>
                        <?php foreach ($all_students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-add-to-class" onclick="addToClass(<?= $cls['id'] ?>)" title="Қосу">+</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($classes)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-school"></i>
                <p>Әзірге класс жоқ. Жоғарыдан жаңа класс жасаңыз.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="tab-content" id="tab-progress">
        <div class="card">
            <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-chart-bar"></i>
                    <span id="progress-title">Барлық оқушылардың прогресі</span>
                </div>
                <?php if (!empty($classes)): ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <button class="filter-btn active" data-class="all" onclick="filterProgress('all', this)">
                        Барлығы <span class="filter-count"><?= count($all_students) ?></span>
                    </button>
                    <?php foreach ($classes as $cls): ?>
                    <?php $cnt = count($class_members[$cls['id']] ?? []); ?>
                    <button class="filter-btn" data-class="<?= $cls['id'] ?>" onclick="filterProgress(<?= $cls['id'] ?>, this)">
                        <i class="fas fa-school" style="font-size:11px;"></i>
                        <?= htmlspecialchars($cls['name']) ?>
                        <span class="filter-count"><?= $cnt ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($all_students)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>Оқушылар жоқ</p>
            </div>
            <?php else: ?>
            <div class="progress-table-wrap">
                <table class="progress-table" id="progressTable">
                    <thead>
                        <tr>
                            <th>Оқушы</th>
                            <?php foreach ($sections as $sec): ?>
                            <th title="<?= htmlspecialchars($sec['title']) ?>"><?= $sec['order_number'] ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_students as $s): ?>
                        <?php
                            $s_class_ids = [];
                            foreach ($class_members as $cid => $members) {
                                if (in_array($s['id'], $members)) $s_class_ids[] = $cid;
                            }
                        ?>
                        <tr data-student-id="<?= $s['id'] ?>"
                            data-classes="<?= htmlspecialchars(json_encode($s_class_ids)) ?>">
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-light)"><?= htmlspecialchars($s['email']) ?></div>
                            </td>
                            <?php foreach ($sections as $sec): ?>
                            <?php
                                $prog = $student_section_progress[$s['id']][$sec['id']] ?? ['total'=>0,'done'=>0];
                                $done = $prog['done'];
                                $total = $prog['total'];
                            ?>
                            <td>
                                <?php if ($total === 0): ?>
                                    <span class="cell-empty">—</span>
                                <?php elseif ($done === $total): ?>
                                    <span class="cell-done" title="<?= $done ?>/<?= $total ?>">✓</span>
                                <?php elseif ($done > 0): ?>
                                    <span class="cell-partial" title="<?= $done ?>/<?= $total ?>"><?= $done ?></span>
                                <?php else: ?>
                                    <span class="cell-empty" title="0/<?= $total ?>">○</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="no-students-msg" style="display:none; text-align:center; padding:30px; color:var(--text-light);">
                <i class="fas fa-users-slash" style="font-size:32px; margin-bottom:10px; display:block; color:var(--primary-light);"></i>
                <p>Бұл класта оқушылар жоқ</p>
            </div>
            <div style="display:flex; gap:20px; margin-top:16px; flex-wrap:wrap; font-size:13px; color:var(--text-light);">
                <div style="display:flex;align-items:center;gap:6px"><span class="cell-done">✓</span> Толық аяқталды</div>
                <div style="display:flex;align-items:center;gap:6px"><span class="cell-partial" style="font-size:12px;">N</span> Жасау процесінде</div>
                <div style="display:flex;align-items:center;gap:6px"><span class="cell-empty">○</span> Басталмаған</div>
            </div>
            <div style="margin-top:16px;">
                <div style="font-size:12px; color:var(--text-light); margin-bottom:8px; font-weight:600;">Бөлімдер:</div>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <?php foreach ($sections as $sec): ?>
                <span style="background:var(--primary-pastel); color:var(--primary-dark); border-radius:20px; padding:4px 12px; font-size:12px;">
                    <?= $sec['order_number'] ?> — <?= htmlspecialchars($sec['title']) ?>
                </span>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-content" id="tab-tests">
        <div class="card">
            <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-clipboard-check"></i>
                    <span id="tests-title">Оқушылардың бөлім бойынша тест нәтижелері</span>
                </div>

                <?php if (!empty($classes)): ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <button class="filter-btn active test-filter-btn" data-class="all" onclick="filterTests('all', this)">
                        Барлығы <span class="filter-count"><?= count($all_students) ?></span>
                    </button>
                    <?php foreach ($classes as $cls): ?>
                    <?php $cnt = count($class_members[$cls['id']] ?? []); ?>
                    <button class="filter-btn test-filter-btn" data-class="<?= $cls['id'] ?>" onclick="filterTests(<?= $cls['id'] ?>, this)">
                        <i class="fas fa-school" style="font-size:11px;"></i>
                        <?= htmlspecialchars($cls['name']) ?>
                        <span class="filter-count"><?= $cnt ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($all_students)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>Оқушылар жоқ</p>
            </div>
            <?php else: ?>
            <div class="progress-table-wrap">
                <table class="progress-table" id="testsTable">
                    <thead>
                        <tr>
                            <th>Оқушы</th>
                            <?php for ($i = 1; $i <= 11; $i++): ?>
                            <th>Тест <?= $i ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_students as $s): ?>
                        <?php
                            $s_class_ids = [];
                            foreach ($class_members as $cid => $members) {
                                if (in_array($s['id'], $members)) {
                                    $s_class_ids[] = $cid;
                                }
                            }
                        ?>
                        <tr data-student-id="<?= $s['id'] ?>" data-classes="<?= htmlspecialchars(json_encode($s_class_ids)) ?>">
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-light)"><?= htmlspecialchars($s['email']) ?></div>
                            </td>

                            <?php for ($i = 1; $i <= 11; $i++): ?>
                            <?php $tr = $test_results_matrix[$s['id']][$i] ?? null; ?>
                            <td>
                                <?php if (!$tr): ?>
                                    <span class="cell-empty">—</span>
                                <?php else: ?>
                                    <?php
                                        $pct = $tr['max_score'] > 0 ? round(($tr['score'] / $tr['max_score']) * 100) : 0;
                                        if ($pct >= 80) {
                                            $class = 'cell-done';
                                            $text = $pct . '%';
                                        } elseif ($pct >= 15) {
                                            $class = 'cell-partial';
                                            $text = $pct . '%';
                                        } else {
                                            $class = 'cell-low';
                                            $text = $pct . '%';
                                        }
                                    ?>
                                    <span class="<?= $class ?>" title="<?= $tr['score'] ?>/<?= $tr['max_score'] ?> | <?= date('d.m.Y', strtotime($tr['taken_at'])) ?>">
                                        <?= $text ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="no-tests-msg" style="display:none; text-align:center; padding:30px; color:var(--text-light);">
                <i class="fas fa-users-slash" style="font-size:32px; margin-bottom:10px; display:block; color:var(--primary-light);"></i>
                <p>Бұл класта оқушылар жоқ</p>
            </div>

            <div style="display:flex; gap:20px; margin-top:16px; flex-wrap:wrap; font-size:13px; color:var(--text-light);">
                <div style="display:flex;align-items:center;gap:6px"><span class="cell-done">100</span> 80% - 100%</div>
                <div style="display:flex;align-items:center;gap:6px"><span class="cell-partial">50</span> 15% - 79%</div>
                <div style="display:flex;align-items:center;gap:6px"><span class="cell-low">0</span> 0% - 14%</div>
                <div style="display:flex;align-items:center;gap:6px"><span class="cell-empty">—</span> Тапсырмаған</div>
            </div>
            <?php endif; ?>
        </div>
    </div>


    <?php if (!empty($profile_message)): ?>
    <div class="message <?= $profile_message_type ?>" style="margin-bottom:20px;">
        <i class="fas <?= $profile_message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= $profile_message ?>
    </div>
    <?php endif; ?>

    <div class="card" style="margin:25px 0;">
        <div class="card-title"><i class="fas fa-user-edit"></i><span>Жеке мәліметтер</span></div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Аты</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($teacher['first_name']) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Тегі</label>
                <div class="input-wrapper">
                    <i class="fas fa-user-tag"></i>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($teacher['last_name']) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($teacher['email']) ?>" disabled>
                </div>
                <small><i class="fas fa-info-circle"></i> Email өзгерту мүмкін емес</small>
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary-form">
                <i class="fas fa-save"></i> Сақтау
            </button>
        </form>
    </div>

    <div class="card" style="margin-bottom:40px;">
        <div class="card-title"><i class="fas fa-lock"></i><span>Құпия сөзді өзгерту</span></div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Ағымдағы құпия сөз</label>
                <div class="input-wrapper">
                    <i class="fas fa-key"></i>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Жаңа құпия сөз</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <small><i class="fas fa-shield-alt"></i> Кемінде 8 символ</small>
            </div>
            <div class="form-group">
                <label class="form-label">Жаңа құпия сөзді растау</label>
                <div class="input-wrapper">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary-form">
                <i class="fas fa-sync-alt"></i> Өзгерту
            </button>
        </form>
    </div>
</div>

<div class="overlay" id="overlay" onclick="hideAvatarUpload()"></div>
<div class="avatar-upload-form" id="avatarForm">
    <h2 class="modal-title">Аватарды өзгерту</h2>
    <form method="POST" enctype="multipart/form-data" style="margin-bottom:20px;">
        <input type="file" name="avatar" class="file-input" accept="image/*" required>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Жүктеу</button>
            <button type="button" class="btn btn-outline" onclick="hideAvatarUpload()">Болдырмау</button>
        </div>
    </form>
    <?php if (!empty($avatar_path)): ?>
    <div style="border-top:1px solid var(--gray-light); padding-top:20px;">
        <form method="POST" onsubmit="return confirm('Аватарды өшіруге сенімдісіз бе?')">
            <button type="submit" name="remove_avatar" class="btn btn-danger">
                🗑️ Аватарды өшіру
            </button>
        </form>
        <p style="font-size:12px; color:var(--text-light); margin-top:10px; text-align:center;">
            Аватар өшіріліп, орнына бастапқы әріптеріңіз көрсетіледі.
        </p>
    </div>
    <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');
}
function filterTests(classId, btn) {
    document.querySelectorAll('#tab-tests .test-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const title = document.getElementById('tests-title');
    if (classId === 'all') {
        title.textContent = 'Оқушылардың тест нәтижелері';
    } else {
        title.textContent = (classNames[classId] || '') + ' — тест нәтижелері';
    }

    const rows = document.querySelectorAll('#testsTable tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        if (classId === 'all') {
            row.style.display = '';
            visibleCount++;
        } else {
            const rowClasses = JSON.parse(row.getAttribute('data-classes') || '[]');
            if (rowClasses.includes(parseInt(classId))) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
    });

    const noMsg = document.getElementById('no-tests-msg');
    const tableWrap = document.querySelector('#tab-tests .progress-table-wrap');

    if (noMsg && tableWrap) {
        if (visibleCount === 0) {
            tableWrap.style.display = 'none';
            noMsg.style.display = 'block';
        } else {
            tableWrap.style.display = '';
            noMsg.style.display = 'none';
        }
    }
}

const avatarBtn = document.getElementById('avatarBtn');
const avatarMenu = document.getElementById('avatarMenu');
avatarBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = avatarMenu.classList.toggle('open');
    avatarBtn.setAttribute('aria-expanded', open);
});
document.addEventListener('click', () => {
    avatarMenu.classList.remove('open');
    avatarBtn.setAttribute('aria-expanded', false);
});

document.getElementById('mobileMenuBtn').addEventListener('click', () => {
    document.getElementById('navMenu').classList.toggle('show');
});

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = (type === 'success' ? '✅ ' : '❌ ') + msg;
    t.className = 'toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3000);
}

const classNames = <?= json_encode(
    array_combine(
        array_column($classes, 'id'),
        array_column($classes, 'name')
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

function filterProgress(classId, btn) {
    document.querySelectorAll('#tab-progress .filter-btn').forEach(b => b.classList.remove('active'));

    btn.classList.add('active');

    const title = document.getElementById('progress-title');
    if (classId === 'all') {
        title.textContent = 'Барлық оқушылардың прогресі';
    } else {
        title.textContent = (classNames[classId] || '') + ' — прогресс';
    }

    const rows = document.querySelectorAll('#progressTable tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        if (classId === 'all') {
            row.style.display = '';
            visibleCount++;
        } else {
            const rowClasses = JSON.parse(row.getAttribute('data-classes') || '[]');
            if (rowClasses.includes(parseInt(classId))) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
    });

    const noMsg = document.getElementById('no-students-msg');
    const tableWrap = document.querySelector('#tab-progress .progress-table-wrap');

    if (noMsg && tableWrap) {
        if (visibleCount === 0) {
            tableWrap.style.display = 'none';
            noMsg.style.display = 'block';
        } else {
            tableWrap.style.display = '';
            noMsg.style.display = 'none';
        }
    }
}

function showAvatarUpload() {
    document.getElementById('overlay').classList.add('show');
    document.getElementById('avatarForm').classList.add('show');
}
function hideAvatarUpload() {
    document.getElementById('overlay').classList.remove('show');
    document.getElementById('avatarForm').classList.remove('show');
}
function createClass() {
    const name = document.getElementById('newClassName').value.trim();

    if (!name) {
        showToast('Класс атауын енгізіңіз', 'error');
        return;
    }

    fetch('teacher_dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'action=create_class&class_name=' + encodeURIComponent(name)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Класс жасалды: ' + data.class_name);

            setTimeout(() => {
                window.location.reload();
            }, 700);
        } else {
            showToast(data.message || 'Класс жасау мүмкін болмады', 'error');
        }
    })
    .catch(() => {
        showToast('Қате шықты', 'error');
    });
}

function addClassCardToDOM(classId, className) {
    const grid = document.getElementById('classesGrid');
    const allStudents = <?= json_encode(array_values($all_students), JSON_UNESCAPED_UNICODE) ?>;

    let options = '<option value="">— Оқушы таңдаңыз —</option>';
    allStudents.forEach(s => {
        options += `<option value="${s.id}">${s.first_name} ${s.last_name}</option>`;
    });

    const card = document.createElement('div');
    card.className = 'class-card';
    card.id = 'class-card-' + classId;
    card.innerHTML = `
        <div class="class-header">
            <div class="class-name"><i class="fas fa-school"></i> ${className}</div>
            <button class="btn-delete-class" onclick="deleteClass(${classId}, '${className}')">
                <i class="fas fa-trash"></i> Өшіру
            </button>
        </div>
        <div class="class-members" id="members-${classId}">
            <div style="color:var(--text-light);font-size:13px" id="empty-msg-${classId}">
                <i class="fas fa-info-circle"></i> Оқушылар жоқ
            </div>
        </div>
        <div class="add-student-to-class">
            <select id="select-${classId}">${options}</select>
            <button class="btn-add-to-class" onclick="addToClass(${classId})">+</button>
        </div>
    `;
    grid.appendChild(card);
}

function deleteClass(classId, className) {
    if (!confirm(`"${className}" класын өшіруге сенімдісіз бе?`)) return;

    fetch('teacher_dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'action=delete_class&class_id=' + encodeURIComponent(classId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('class-card-' + classId)?.remove();
            showToast('Класс өшірілді');

            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            showToast(data.message || 'Класты өшіру мүмкін болмады', 'error');
        }
    })
    .catch(() => {
        showToast('Қате шықты', 'error');
    });
}

function addToClass(classId) {
    const sel = document.getElementById('select-' + classId);
    const studentId = sel.value;

    if (!studentId) {
        showToast('Оқушы таңдаңыз', 'error');
        return;
    }

    const studentName = sel.options[sel.selectedIndex].text;

    fetch('teacher_dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: `action=add_student_to_class&class_id=${encodeURIComponent(classId)}&student_id=${encodeURIComponent(studentId)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const emptyMsg = document.getElementById('empty-msg-' + classId);
            if (emptyMsg) emptyMsg.remove();

            const members = document.getElementById('members-' + classId);
            const chip = document.createElement('span');
            chip.className = 'class-member-chip';
            chip.id = `chip-${classId}-${studentId}`;
            chip.innerHTML = `${studentName} <span class="chip-remove" onclick="removeFromClass(${classId}, ${studentId}, '${studentName.replace(/'/g, "\\'")}')">✕</span>`;
            members.appendChild(chip);

            sel.value = '';
            showToast(studentName + ' класқа қосылды');

            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            showToast(data.message || 'Қосу мүмкін болмады', 'error');
        }
    })
    .catch(() => {
        showToast('Қате шықты', 'error');
    });
}


function removeFromClass(classId, studentId, studentName) {
    fetch('teacher_dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: `action=remove_student_from_class&class_id=${encodeURIComponent(classId)}&student_id=${encodeURIComponent(studentId)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById(`chip-${classId}-${studentId}`)?.remove();

            const members = document.getElementById('members-' + classId);
            if (members && members.querySelectorAll('.class-member-chip').length === 0) {
                members.innerHTML = '<div style="color:var(--text-light);font-size:13px" id="empty-msg-' + classId + '"><i class="fas fa-info-circle"></i> Оқушылар жоқ</div>';
            }

            showToast(studentName + ' класстан шығарылды');

            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            showToast(data.message || 'Өшіру мүмкін болмады', 'error');
        }
    })
    .catch(() => {
        showToast('Қате шықты', 'error');
    });
}

</script>
</body>
</html>