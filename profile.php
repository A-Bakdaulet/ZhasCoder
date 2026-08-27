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

$user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($user_id === 0) {
    session_destroy();
    header("Location: login.html");
    exit();
}


$profile_message = '';
$profile_message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    $errors = [];
    if (empty($first_name)) $errors[] = "Атыңызды енгізіңіз";
    if (empty($last_name)) $errors[] = "Тегіңізді енгізіңіз";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
        $stmt->bind_param("ssi", $first_name, $last_name, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['profile_message'] = "✅ Жеке мәліметтер жаңартылды!";
            $_SESSION['profile_message_type'] = "success";
            header("Location: profile.php");
            exit();
        } else {
            $profile_message = "❌ Мәліметтерді жаңарту кезінде қате кетті";
            $profile_message_type = "error";
        }
        $stmt->close();
    } else {
        $profile_message = implode("<br>", $errors);
        $profile_message_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();
    
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
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hashed, $user_id);
        
        if ($stmt->execute()) {
            $profile_message = "✅ Құпия сөз сәтті өзгертілді!";
            $profile_message_type = "success";
        } else {
            $profile_message = "❌ Құпия сөзді өзгерту кезінде қате кетті";
            $profile_message_type = "error";
        }
        $stmt->close();
    }
}

if (isset($_POST['remove_avatar'])) {
    $update_stmt = $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    $update_stmt->bind_param("i", $user_id);
    
    if ($update_stmt->execute()) {
        $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($old_avatar);
        $stmt->fetch();
        $stmt->close();
        if (!empty($old_avatar) && file_exists($old_avatar)) {
            unlink($old_avatar);
        }
        header("Location: profile.php?removed=success");
        exit();
    }
    $update_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && !isset($_POST['remove_avatar'])) {
    $target_dir = "uploads/avatars/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($file_extension, $allowed_extensions)) {
        $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            $update_stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $update_stmt->bind_param("si", $target_file, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            header("Location: profile.php?success=1");
            exit();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.html");
    exit();
}

$_SESSION['avatar'] = $user['avatar'] ?? '';
$_SESSION['first_name'] = $user['first_name'] ?? '';
$_SESSION['last_name'] = $user['last_name'] ?? '';
$_SESSION['email'] = $user['email'] ?? '';

$initials = '?';
if (!empty($_SESSION['first_name']) && !empty($_SESSION['last_name'])) {
    $initials = mb_strtoupper(mb_substr($_SESSION['first_name'],0,1) . mb_substr($_SESSION['last_name'],0,1));
} elseif (!empty($_SESSION['first_name'])) {
    $initials = mb_strtoupper(mb_substr($_SESSION['first_name'],0,1));
}

$avatar_path = '';
$avatar_timestamp = time();

if (!empty($user['avatar']) && file_exists($user['avatar'])) {
    $avatar_path = $user['avatar'] . '?v=' . $avatar_timestamp;
}

if (isset($_SESSION['profile_message'])) {
    $profile_message = $_SESSION['profile_message'];
    $profile_message_type = $_SESSION['profile_message_type'];
    unset($_SESSION['profile_message']);
    unset($_SESSION['profile_message_type']);
}

$total_lessons = $conn->query("SELECT COUNT(*) as total FROM lessons")->fetch_assoc()['total'];

$completed_lessons = $conn->prepare("
    SELECT COUNT(*) AS completed
    FROM (
        SELECT l.id
        FROM lessons l
        JOIN lesson_tasks lt ON lt.lesson_id = l.id
        LEFT JOIN user_task_progress utp
            ON utp.task_id = lt.id
            AND utp.user_id = ?
            AND utp.completed = 1
        GROUP BY l.id
        HAVING COUNT(lt.id) = COUNT(utp.task_id)
    ) AS completed_lesson_list
");
$completed_lessons->bind_param("i", $user_id);
$completed_lessons->execute();
$completed_count = (int)$completed_lessons->get_result()->fetch_assoc()['completed'];
$completed_lessons->close();


$progress_percent = $total_lessons > 0 ? round(($completed_count / $total_lessons) * 100) : 0;

$total_score = $conn->prepare("SELECT SUM(score) as total FROM user_task_progress WHERE user_id = ?");
$total_score->bind_param("i", $user_id);
$total_score->execute();
$total_score_result = $total_score->get_result()->fetch_assoc()['total'] ?? 0;
$total_score->close();

$sections_completed = $conn->prepare("
    SELECT COUNT(DISTINCT l.section_id) as sections
    FROM user_task_progress up
    JOIN lesson_tasks lt ON up.task_id = lt.id
    JOIN lessons l ON lt.lesson_id = l.id
    WHERE up.user_id = ? AND up.completed = 1
");
$sections_completed->bind_param("i", $user_id);
$sections_completed->execute();
$sections_count = $sections_completed->get_result()->fetch_assoc()['sections'] ?? 0;
$sections_completed->close();

$streak = 0;
$last_activity = $conn->prepare("SELECT completed_at FROM user_task_progress WHERE user_id = ? ORDER BY completed_at DESC LIMIT 1");
$last_activity->bind_param("i", $user_id);
$last_activity->execute();
$last_date = $last_activity->get_result()->fetch_assoc()['completed_at'] ?? null;
$last_activity->close();

if ($last_date) {
    $last_timestamp = strtotime($last_date);
    if (date('Y-m-d', $last_timestamp) == date('Y-m-d')) {
        $streak = 1;
        for ($i = 1; $i <= 30; $i++) {
            $check_date = date('Y-m-d', strtotime("-$i days"));
            $check_stmt = $conn->prepare("SELECT id FROM user_task_progress WHERE user_id = ? AND DATE(completed_at) = ? LIMIT 1");
            $check_stmt->bind_param("is", $user_id, $check_date);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $streak++;
            } else {
                break;
            }
            $check_stmt->close();
        }
    }
}

$activity_labels = [];
$activity_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $activity_labels[] = date('d M', strtotime($date));
    
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_task_progress WHERE user_id = ? AND DATE(completed_at) = ?");
    $count_stmt->bind_param("is", $user_id, $date);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'];
    $activity_data[] = $count;
    $count_stmt->close();
}

$sections_progress = [];
$test_results = [];
$table_check = $conn->query("SHOW TABLES LIKE 'test_results'")->num_rows > 0;
if ($table_check) {
    $tr_stmt = $conn->prepare("
        SELECT tr.score, tr.max_score, tr.retake_count, tr.taken_at,
            tr.section_id,
            s.title as section_title, s.order_number
        FROM test_results tr
        JOIN sections s ON s.id = tr.section_id
        WHERE tr.user_id = ?
        ORDER BY s.order_number ASC
    ");
    $tr_stmt->bind_param("i", $user_id);
    $tr_stmt->execute();
    $tr_result = $tr_stmt->get_result();
    while ($row = $tr_result->fetch_assoc()) {
        $test_results[] = $row;
    }
    $tr_stmt->close();
}
$sections_query = "SELECT * FROM sections ORDER BY order_number";
$sections_result = $conn->query($sections_query);

while ($section = $sections_result->fetch_assoc()) {
    $section_id = $section['id'];
    
    $total_in_section = $conn->prepare("SELECT COUNT(*) as total FROM lessons WHERE section_id = ?");
    $total_in_section->bind_param("i", $section_id);
    $total_in_section->execute();
    $total = $total_in_section->get_result()->fetch_assoc()['total'];
    $total_in_section->close();
    
    $completed_in_section = $conn->prepare("
        SELECT COUNT(*) as completed 
        FROM user_task_progress up
        JOIN lesson_tasks lt ON up.task_id = lt.id
        JOIN lessons l ON lt.lesson_id = l.id
        WHERE up.user_id = ? AND l.section_id = ? AND up.completed = 1
    ");
    $completed_in_section->bind_param("ii", $user_id, $section_id);
    $completed_in_section->execute();
    $completed = $completed_in_section->get_result()->fetch_assoc()['completed'];
    $completed_in_section->close();
    
    $sections_progress[] = [
        'title' => $section['title'],
        'total' => $total,
        'completed' => $completed,
        'percent' => $total > 0 ? round(($completed / $total) * 100) : 0
    ];
}
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Жеке кабинет - ZhasCoder</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--white-smoke) 0%, #f1f4ff 100%);
            color: var(--text-dark);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 30%, rgba(139, 92, 246, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 30% 70%, rgba(45, 212, 191, 0.05) 0%, transparent 30%),
                radial-gradient(circle at 70% 40%, rgba(99, 102, 241, 0.03) 0%, transparent 25%);
            pointer-events: none;
            z-index: -1;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 40px;
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 8px 30px var(--shadow-light);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(99, 102, 241, 0.15);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 20px;
            border-radius: 50px;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            box-shadow: 0 8px 20px var(--shadow-light);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            cursor: pointer;
        }

        .logo:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px var(--shadow-medium);
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 26px;
            font-weight: 700;
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.3);
            transform: rotate(5deg);
            transition: transform 0.3s ease;
        }

        .logo:hover .logo-icon {
            transform: rotate(0deg) scale(1.05);
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 60%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: -0.5px;
        }

        .nav-container {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 6px;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.15);
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
            letter-spacing: 0.3px;
        }

        .nav-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            border-radius: 30px;
            transform: scale(0.85);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: -1;
        }

        .nav-button:hover {
            color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .nav-button:hover::before {
            transform: scale(1);
            opacity: 1;
        }

        .nav-button.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .mobile-menu-btn {
            display: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: var(--white);
            font-size: 24px;
            cursor: pointer;
            padding: 14px 18px;
            border-radius: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .user-auth-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar-dropdown {
            position: relative;
        }

        .avatar-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            cursor: pointer;
            padding: 8px 16px 8px 12px;
            border-radius: 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .avatar-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px var(--shadow-medium);
            background: var(--white);
            border: 1px solid var(--primary-light);
        }

        .avatar-img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--white);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
        }

        .avatar-fallback {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 18px;
            border: 3px solid var(--white);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .avatar-caret {
            font-size: 14px;
            color: var(--primary);
            transition: transform 0.3s ease;
            margin-left: 5px;
        }

        .avatar-btn[aria-expanded="true"] .avatar-caret {
            transform: rotate(180deg);
        }

        .avatar-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 12px);
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
            overflow: hidden;
            transform: translateY(10px);
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(99, 102, 241, 0.15);
            z-index: 1000;
        }

        .avatar-menu.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .avatar-menu-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(99, 102, 241, 0.2);
        }

        .avatar-mini {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
            border: 2px solid white;
        }

        .avatar-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-mini-fallback {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
        }

        .avatar-user {
            flex: 1;
            min-width: 0;
        }

        .avatar-name {
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: 'Montserrat', sans-serif;
        }

        .avatar-email {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .avatar-menu-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.3), transparent);
            margin: 6px 0;
        }

        .avatar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            background: transparent;
            border-left: 3px solid transparent;
        }

        .avatar-item:hover {
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 50%);
            padding-left: 25px;
            border-left-color: var(--primary);
        }

        .avatar-item .icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 16px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .avatar-item:hover .icon {
            background: white;
            color: var(--primary);
            transform: scale(1.1);
        }

        .avatar-item-danger {
            color: #ef4444;
        }

        .avatar-item-danger:hover {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-left-color: #ef4444;
        }

        .avatar-item-danger .icon {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            background: white;
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
            flex-wrap: wrap;
        }

        .profile-avatar {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-pastel);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s ease;
        }

        .avatar-edit-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .profile-email {
            font-size: 16px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-email i {
            color: var(--primary);
        }

        .profile-stats {
            display: flex;
            gap: 30px;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-light);
            margin-top: 5px;
        }

        .streak-card {
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            border-radius: 20px;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .streak-icon {
            font-size: 40px;
            color: var(--warning);
        }

        .streak-info h3 {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 500;
        }

        .streak-count {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: white;
            border-radius: 30px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Montserrat', sans-serif;
            border-bottom: 2px solid var(--gray-light);
            padding-bottom: 12px;
        }

        .card-title i {
            color: var(--primary);
            font-size: 20px;
        }

        .progress-item {
            margin-bottom: 20px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .progress-bar {
            height: 10px;
            background: var(--gray-light);
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        .section-progress-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .section-progress-item:last-child {
            border-bottom: none;
        }

        .section-title {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .section-stats {
            font-size: 13px;
            color: var(--text-light);
            min-width: 60px;
        }

        .section-bar {
            width: 100px;
            height: 8px;
            background: var(--gray-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .section-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 4px;
        }

        .activity-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .activity-day {
            text-align: center;
        }

        .activity-bar {
            height: 60px;
            background: var(--primary-pastel);
            border-radius: 10px;
            margin-bottom: 5px;
            position: relative;
            overflow: hidden;
        }

        .activity-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(0deg, var(--primary), var(--secondary));
            border-radius: 10px;
            transition: height 0.3s ease;
        }

        .activity-label {
            font-size: 11px;
            color: var(--text-light);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-dark);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 16px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid var(--gray-light);
            border-radius: 30px;
            font-size: 15px;
            transition: all 0.2s;
            background: #fafbfc;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-control:disabled {
            background: var(--gray-light);
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-danger {
            width: 100%;
            padding: 12px;
            border-radius: 30px;
            border: 2px solid #ef4444;
            background: #fee2e2;
            color: #ef4444;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        .alert {
            background: var(--success);
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 3000;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            animation: slideIn 0.3s ease;
        }

        .alert.error {
            background: var(--error);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .message {
            padding: 14px 18px;
            border-radius: 20px;
            margin-bottom: 24px;
            font-weight: 500;
            animation: fadeIn 0.3s;
        }

        .message.success {
            background: #f0fff4;
            color: #38a169;
            border-left: 3px solid #38a169;
        }

        .message.error {
            background: #fff5f5;
            color: #e53e3e;
            border-left: 3px solid #e53e3e;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .avatar-upload-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            width: 90%;
            max-width: 400px;
        }

        .avatar-upload-form.show {
            display: block;
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-dark);
        }

        .file-input {
            width: 100%;
            padding: 10px;
            border: 2px dashed var(--primary-pastel);
            border-radius: 15px;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            gap: 15px;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1999;
        }

        .overlay.show {
            display: block;
        }

        small {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 8px;
            display: block;
        }

        small i {
            margin-right: 5px;
        }

        @media (max-width: 1024px) {
            header {
                padding: 15px 25px;
            }
            .nav-menu {
                gap: 0.3rem;
            }
            .nav-button {
                padding: 10px 20px;
                font-size: 14px;
            }
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 850px) {
            .mobile-menu-btn {
                display: block;
                order: 3;
            }
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 20px;
                right: 20px;
                flex-direction: column;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(15px);
                padding: 20px;
                border-radius: 24px;
                box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
                margin-top: 10px;
            }
            .nav-menu.show {
                display: flex;
            }
            .nav-button {
                width: 100%;
                text-align: center;
            }
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .profile-stats {
                margin-left: 0;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .activity-grid {
                gap: 5px;
            }
            .activity-bar {
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo" onclick="location.href='index1.php'">
            <div class="logo-icon">📚</div>
            <span class="logo-text">ZhasCoder</span>
        </div>

        <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

        <div class="nav-menu" id="navMenu">
            <span class="nav-button" data-section="about" id="about-btn">Біз туралы</span>
            <span class="nav-button" data-section="home" id="main-btn">Басты бет</span>
            <span class="nav-button" data-section="contact" id="contact-btn">Байланыс</span>
            <span class="nav-button" data-href="topics.php">Тапсырмалар</span>
        </div>

        <div class="nav-container">
            <div class="user-auth-container">
                <div class="avatar-dropdown">
                    <button class="avatar-btn" id="avatarBtn" type="button" aria-expanded="false">
                        <?php if (!empty($avatar_path)): ?>
                            <img src="<?= htmlspecialchars($avatar_path) ?>" alt="User Avatar" class="avatar-img">
                        <?php else: ?>
                            <div class="avatar-fallback"><?= htmlspecialchars($initials) ?></div>
                        <?php endif; ?>
                        <span class="avatar-caret">▾</span>
                    </button>

                    <div class="avatar-menu" id="avatarMenu" role="menu" aria-hidden="true">
                        <div class="avatar-menu-header">
                            <div class="avatar-mini">
                                <?php if (!empty($avatar_path)): ?>
                                    <img src="<?= htmlspecialchars($avatar_path) ?>" alt="User Avatar">
                                <?php else: ?>
                                    <div class="avatar-mini-fallback"><?= htmlspecialchars($initials) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-user">
                                <div class="avatar-name">
                                    <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                                </div>
                                <div class="avatar-email">
                                    <?= htmlspecialchars($user['email'] ?? '') ?>
                                </div>
                            </div>
                        </div>

                        <div class="avatar-menu-divider"></div>

                        <a class="avatar-item active" href="profile.php" role="menuitem">
                            <span class="icon">👤</span>
                            <span>Жеке кабинет</span>
                        </a>
                        <div class="avatar-menu-divider"></div>
                        <a class="avatar-item avatar-item-danger" href="logout.php" role="menuitem">
                            <span class="icon">⎋</span>
                            <span>Шығу</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-container">
        <?php if (isset($_GET['removed']) || isset($_GET['success'])): ?>
        <div class="alert" id="statusAlert" style="background: <?= isset($_GET['removed']) ? '#f59e0b' : '#10b981' ?>;">
            <i class="fas <?= isset($_GET['removed']) ? 'fa-trash-alt' : 'fa-check-circle' ?>"></i> 
            <?= isset($_GET['removed']) ? 'Аватар сәтті өшірілді! Енді бастапқы әріптер көрсетіледі.' : 'Аватар сәтті жаңартылды!' ?>
        </div>
        <script>
            setTimeout(() => {
                const alert = document.getElementById('statusAlert');
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }
            }, 3000);
            
            setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.delete('removed');
                url.searchParams.delete('success');
                window.history.replaceState({}, document.title, url);
            }, 100);
        </script>
        <?php endif; ?>

        <?php if ($profile_message): ?>
            <div class="message <?php echo $profile_message_type; ?>">
                <i class="fas <?php echo $profile_message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $profile_message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($avatar_path)): ?>
                    <img src="<?= htmlspecialchars($avatar_path) ?>" alt="Profile Avatar">
                <?php else: ?>
                    <div style="width:100%; height:100%; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; color:white; font-size:48px; font-weight:700;">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                <?php endif; ?>
                <div class="avatar-edit-btn" onclick="showAvatarUpload()">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
                <div class="profile-email">
                    <i class="fas fa-envelope"></i>
                    <?= htmlspecialchars($user['email']) ?>
                </div>
            </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= $completed_count ?>/<?= $total_lessons ?></div>
                    <div class="stat-label">Сабақтар</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $total_score_result ?></div>
                    <div class="stat-label">Жиналған балл</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $sections_count ?>/10</div>
                    <div class="stat-label">Бөлімдер</div>
                </div>
            </div>
        </div>

        <div class="streak-card">
            <div class="streak-icon">
                <i class="fas fa-fire"></i>
            </div>
            <div class="streak-info">
                <h3>Қатарынан оқу күндері</h3>
                <div class="streak-count">
                    <?= $streak ?> <small>күн</small>
                </div>
            </div>
        </div>

        <div class="profile-grid">
            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-chart-line"></i>
                    <span>Жалпы прогресс</span>
                </div>
                
                <div class="progress-item">
                    <div class="progress-header">
                        <span>Курс аяқталуы</span>
                        <span><?= $progress_percent ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progress_percent ?>%;"></div>
                    </div>
                </div>
                
                <div style="margin-top: 25px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <div>
                            <div style="font-size: 24px; font-weight: 700; color: var(--primary);"><?= $completed_count ?></div>
                            <div style="font-size: 14px; color: var(--text-light);">Аяқталған сабақ</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700; color: var(--secondary);"><?= $total_lessons - $completed_count ?></div>
                            <div style="font-size: 14px; color: var(--text-light);">Қалған сабақ</div>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700; color: var(--accent);"><?= $total_score_result ?></div>
                            <div style="font-size: 14px; color: var(--text-light);">Барлық балл</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <div class="card-title">
                    <i class="fas fa-book-open"></i>
                    <span>Бөлімдер бойынша прогресс</span>
                </div>
                
                <?php foreach ($sections_progress as $section): ?>
                <div class="section-progress-item">
                    <div class="section-title"><?= htmlspecialchars($section['title']) ?></div>
                    <div class="section-stats"><?= $section['completed'] ?>/<?= $section['total'] ?></div>
                    <div class="section-bar">
                        <div class="section-bar-fill" style="width: <?= $section['percent'] ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
                <?php if (!empty($test_results)): ?>
                <div class="profile-card" style="margin-bottom:30px; grid-column: span 2;">
                    <div class="card-title">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Тест нәтижелері</span>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($test_results as $tr): 
                            $pct = $tr['max_score'] > 0 ? round($tr['score'] / $tr['max_score'] * 100) : 0;
                            if ($pct >= 90)     { $color = '#10b981'; $grade = 'A'; }
                            elseif ($pct >= 75) { $color = '#6366f1'; $grade = 'B'; }
                            elseif ($pct >= 60) { $color = '#f59e0b'; $grade = 'C'; }
                            else                { $color = '#ef4444'; $grade = 'F'; }
                        ?>
                        <div style="display:flex; align-items:center; gap:15px; padding:14px 18px; background:var(--gray-light); border-radius:16px;">
                            
                            <div style="width:42px; height:42px; border-radius:12px; background:<?= $color ?>; color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; flex-shrink:0;">
                                <?= $grade ?>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:600; font-size:14px;"><?= htmlspecialchars($tr['section_title']) ?></div>
                                <div style="font-size:12px; color:var(--text-light); margin-top:2px;">
                                    <?= $tr['score'] ?>/<?= $tr['max_score'] ?> балл · 
                                    <?= date('d.m.Y', strtotime($tr['taken_at'])) ?>
                                    <?php if ($tr['retake_count'] > 0): ?>
                                    · <span style="color:var(--warning);">Қайта тапсырған: <?= $tr['retake_count'] ?> рет</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="width:120px;">
                                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                                    <span style="color:var(--text-light);"><?= $pct ?>%</span>
                                </div>
                                <div style="height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                    <div style="height:100%; width:<?= $pct ?>%; background:<?= $color ?>; border-radius:4px;"></div>
                                </div>
                            </div>
                            <a href="test.php?section=<?= $tr['section_id'] ?>" 
                            style="font-size:12px; color:var(--primary); text-decoration:none; font-weight:600; white-space:nowrap;">
                                Қарау →
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php 
                        $avg = count($test_results) > 0 
                            ? round(array_sum(array_map(fn($r) => $r['max_score'] > 0 ? $r['score']/$r['max_score']*100 : 0, $test_results)) / count($test_results)) 
                            : 0;
                    ?>
                    <div style="margin-top:16px; padding:14px 18px; background:linear-gradient(135deg,var(--primary-pastel),var(--secondary-pastel)); border-radius:16px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:600; color:var(--primary-dark);">Орташа нәтиже</span>
                        <span style="font-size:22px; font-weight:700; color:var(--primary);"><?= $avg ?>%</span>
                    </div>
                </div>
                <?php endif; ?>
        </div>


        <div class="profile-card" style="grid-column: span 2; margin-top: 0;">
            <div class="card-title">
                <i class="fas fa-calendar-alt"></i>
                <span>Соңғы 7 күндегі белсенділік</span>
            </div>
            
            <div class="activity-grid">
                <?php for ($i = 0; $i < 7; $i++): ?>
                <div class="activity-day">
                    <div class="activity-bar">
                        <?php 
                        $height = $activity_data[$i] * 20;
                        if ($height > 60) $height = 60;
                        ?>
                        <div class="activity-fill" style="height: <?= $height ?>px;"></div>
                    </div>
                    <div class="activity-label"><?= $activity_labels[$i] ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="profile-card" style="margin-top: 30px;">
            <div class="card-title">
                <i class="fas fa-user-edit"></i>
                <span>Жеке мәліметтер</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Аты</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Тегі</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-tag"></i>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>
                    <small><i class="fas fa-info-circle"></i> Email өзгерту мүмкін емес</small>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save"></i> Сақтау
                </button>
            </form>
        </div>
        <div class="profile-card" style="margin-bottom: 30px;">
            <div class="card-title">
                <i class="fas fa-lock"></i>
                <span>Құпия сөзді өзгерту</span>
            </div>
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
                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Өзгерту
                </button>
            </form>
        </div>
    </div>

    <div class="overlay" id="overlay" onclick="hideAvatarUpload()"></div>
    <div class="avatar-upload-form" id="avatarForm">
        <h2 class="form-title">Аватарды өзгерту</h2>
        
        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
            <input type="file" name="avatar" class="file-input" accept="image/*" required>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Жүктеу</button>
                <button type="button" class="btn btn-outline" onclick="hideAvatarUpload()">Болдырмау</button>
            </div>
        </form>
        <?php if (!empty($avatar_path)): ?>
        <div style="border-top: 1px solid var(--gray-light); padding-top: 20px;">
            <form method="POST" class="avatar-actions" onsubmit="return confirm('Аватарды өшіруге сенімдісіз бе? Бастапқы әріптер қалады.')">
                <button type="submit" name="remove_avatar" class="btn-danger">
                    <i class="fas fa-trash-alt"></i> Аватарды өшіру
                </button>
            </form>
            <p style="font-size: 12px; color: var(--text-light); margin-top: 10px; text-align: center;">
                <i class="fas fa-info-circle"></i> Аватар өшіріліп, орнына бастапқы әріптеріңіз көрсетіледі.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const mobileBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.getElementById('navMenu');

        if (mobileBtn && navMenu) {
            mobileBtn.addEventListener('click', () => {
                navMenu.classList.toggle('show');
            });
            
            document.querySelectorAll('.nav-button').forEach(button => {
                button.addEventListener('click', function() {
                    if (window.innerWidth <= 850) {
                        navMenu.classList.remove('show');
                    }
                    const href = this.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });
        }

        const avatarBtn = document.getElementById('avatarBtn');
        const avatarMenu = document.getElementById('avatarMenu');

        if (avatarBtn && avatarMenu) {
            function toggleAvatarMenu() {
                const isOpen = avatarMenu.classList.contains('open');
                if (isOpen) {
                    avatarMenu.classList.remove('open');
                    avatarBtn.setAttribute('aria-expanded', 'false');
                } else {
                    avatarMenu.classList.add('open');
                    avatarBtn.setAttribute('aria-expanded', 'true');
                }
            }

            avatarBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleAvatarMenu();
            });

            document.addEventListener('click', () => {
                avatarMenu.classList.remove('open');
                avatarBtn.setAttribute('aria-expanded', 'false');
            });

            avatarMenu.addEventListener('click', (e) => e.stopPropagation());
        }

        function showAvatarUpload() {
            document.getElementById('overlay').classList.add('show');
            document.getElementById('avatarForm').classList.add('show');
        }

        function hideAvatarUpload() {
            document.getElementById('overlay').classList.remove('show');
            document.getElementById('avatarForm').classList.remove('show');
        }

        document.getElementById('main-btn')?.addEventListener('click', function() {
            window.location.href = 'index.php';
        });
        
        document.getElementById('about-btn')?.addEventListener('click', function() {
            window.location.href = 'index.php#about';
        });
        
        document.getElementById('contact-btn')?.addEventListener('click', function() {
            window.location.href = 'index.php#contact';
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>