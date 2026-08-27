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

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;

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

if (!empty($user['avatar'])) {
    $possible_path = __DIR__ . '/' . $user['avatar'];
    if (file_exists($possible_path)) {
        $avatar_path = $user['avatar'] . '?v=' . $avatar_timestamp;
    } else {
        $basename = basename($user['avatar']);
        if (file_exists(__DIR__ . '/uploads/' . $basename)) {
            $avatar_path = 'uploads/' . $basename . '?v=' . $avatar_timestamp;
        }
    }
}

$lesson_id = isset($_GET['lesson']) ? (int)$_GET['lesson'] : 0;

if ($lesson_id === 0) {
    header("Location: topics.php");
    exit();
}

$lesson_stmt = $conn->prepare("
    SELECT l.*, s.title as section_title, s.id as section_id 
    FROM lessons l
    JOIN sections s ON l.section_id = s.id
    WHERE l.id = ?
");
$lesson_stmt->bind_param("i", $lesson_id);
$lesson_stmt->execute();
$lesson_result = $lesson_stmt->get_result();

if ($lesson_result->num_rows === 0) {
    header("Location: topics.php");
    exit();
}

$lesson = $lesson_result->fetch_assoc();
$lesson_stmt->close();

$tasks_stmt = $conn->prepare("
    SELECT * FROM lesson_tasks
    WHERE lesson_id = ?
    ORDER BY order_number ASC
");
$tasks_stmt->bind_param("i", $lesson_id);
$tasks_stmt->execute();
$tasks_result = $tasks_stmt->get_result();

$tasks = [];
while ($task = $tasks_result->fetch_assoc()) {
    $task_id = (int)$task['id'];

    $progress_stmt = $conn->prepare("
        SELECT completed, score, hints_used
        FROM user_task_progress
        WHERE user_id = ? AND task_id = ?
    ");
    $progress_stmt->bind_param("ii", $user_id, $task_id);
    $progress_stmt->execute();
    $progress_result = $progress_stmt->get_result();
    $progress = $progress_result->fetch_assoc();
    $progress_stmt->close();

    $hints_stmt = $conn->prepare("
        SELECT * FROM hints
        WHERE task_id = ?
        ORDER BY level ASC
    ");
    $hints_stmt->bind_param("i", $task_id);
    $hints_stmt->execute();
    $hints_result = $hints_stmt->get_result();

    $task_hints = [];
    while ($hint = $hints_result->fetch_assoc()) {
        $task_hints[$hint['level']] = $hint;
    }
    $hints_stmt->close();

    $saved_code_stmt = $conn->prepare("
        SELECT code
        FROM user_saved_codes
        WHERE user_id = ? AND task_id = ?
    ");
    $saved_code_stmt->bind_param("ii", $user_id, $task_id);
    $saved_code_stmt->execute();
    $saved_code_result = $saved_code_stmt->get_result();
    $saved_code_row = $saved_code_result->fetch_assoc();
    $saved_code_stmt->close();

    $task['progress'] = [
        'completed' => $progress ? (int)$progress['completed'] : 0,
        'score' => $progress ? (int)$progress['score'] : (int)$task['points'],
        'hints_used' => $progress ? (int)$progress['hints_used'] : 0,
    ];

    $task['hints'] = $task_hints;
    $task['saved_code'] = $saved_code_row['code'] ?? '';

    $tasks[] = $task;
}
$tasks_stmt->close();

if (empty($tasks)) {
    header("Location: topics.php");
    exit();
}

$current_task_index = isset($_GET['task']) ? (int)$_GET['task'] - 1 : 0;
if ($current_task_index < 0 || $current_task_index >= count($tasks)) {
    $current_task_index = 0;
}

$current_task = $tasks[$current_task_index];
$is_completed = $current_task['progress']['completed'];
$current_score = $current_task['progress']['score'];
$hints_used_count = $current_task['progress']['hints_used'];

$prev_stmt = $conn->prepare("
    SELECT id FROM lessons
    WHERE section_id = ? AND order_number < ?
    ORDER BY order_number DESC
    LIMIT 1
");
$prev_stmt->bind_param("ii", $lesson['section_id'], $lesson['order_number']);
$prev_stmt->execute();
$prev_result = $prev_stmt->get_result();
$prev_lesson = $prev_result->fetch_assoc();
$prev_stmt->close();

$next_stmt = $conn->prepare("
    SELECT id FROM lessons
    WHERE section_id = ? AND order_number > ?
    ORDER BY order_number ASC
    LIMIT 1
");
$next_stmt->bind_param("ii", $lesson['section_id'], $lesson['order_number']);
$next_stmt->execute();
$next_result = $next_stmt->get_result();
$next_lesson = $next_result->fetch_assoc();
$next_stmt->close();


$total_lessons = $conn->prepare("SELECT COUNT(*) as total FROM lessons WHERE section_id = ?");
$total_lessons->bind_param("i", $lesson['section_id']);
$total_lessons->execute();
$total_count = $total_lessons->get_result()->fetch_assoc()['total'];
$total_lessons->close();

$completed_lessons_stmt = $conn->prepare("
    SELECT COUNT(*) AS completed_lessons
    FROM (
        SELECT l.id
        FROM lessons l
        JOIN lesson_tasks lt ON lt.lesson_id = l.id
        LEFT JOIN user_task_progress utp
            ON utp.task_id = lt.id
            AND utp.user_id = ?
            AND utp.completed = 1
        WHERE l.section_id = ?
        GROUP BY l.id
        HAVING COUNT(lt.id) = COUNT(utp.task_id)
    ) AS completed_lesson_list
");
$completed_lessons_stmt->bind_param("ii", $user_id, $lesson['section_id']);
$completed_lessons_stmt->execute();
$completed_count = (int)$completed_lessons_stmt->get_result()->fetch_assoc()['completed_lessons'];
$completed_lessons_stmt->close();


$section_progress = $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0;

?>
<?php
$profile_link = 'profile.php';

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') {
        $profile_link = 'student_profile.php';
    } elseif ($_SESSION['role'] === 'teacher') {
        $profile_link = 'teacher_dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - ZhasCoder</title>
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
            --editor-bg: #1e1e2e;
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
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(99, 102, 241, 0.15);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            padding: 8px 20px;
            border-radius: 50px;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            box-shadow: 0 8px 20px var(--shadow-light);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.6);
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
            -webkit-backdrop-filter: blur(10px);
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
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
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

        .nav-button.active:hover {
            transform: translateY(-2px);
            color: var(--primary-dark);
            box-shadow: 0 12px 25px rgba(99, 102, 241, 0.4);
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

        .mobile-menu-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.4);
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
            background: linear-gradient(135deg, var(--white) 0%, var(--white) 100%);
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
            -webkit-backdrop-filter: blur(10px);
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
            box-shadow: 0 4px 8px rgba(99, 102, 241, 0.2);
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

        .avatar-item-danger:hover .icon {
            background: white;
            color: #ef4444;
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.2);
        }

        .main-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            background: white;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .breadcrumb a {
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .breadcrumb i {
            color: var(--text-light);
            font-size: 14px;
        }

        .breadcrumb .current {
            color: var(--primary);
            font-weight: 600;
        }

        .lesson-progress {
            background: white;
            border-radius: 30px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-badge {
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .lesson-progress-bar {
            flex: 1;
            min-width: 200px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-light);
        }

        .progress-track {
            height: 8px;
            background: var(--gray-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: <?php echo $section_progress; ?>%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }

        .score-badge {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            padding: 8px 25px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .explanation-panel {
            background: white;
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .lesson-title {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }

        .explanation-box {
            background: var(--primary-pastel);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary);
            max-height: 300px;
            overflow-y: auto;   
        }

        .explanation-box p {
            font-size: 16px;
            color: var(--text-dark);
            line-height: 1.7;
            margin-bottom: 15px;
        }
        .explanation-box::-webkit-scrollbar {
            width: 8px;
        }
        .explanation-box::-webkit-scrollbar-thumb {
            background-color: var(--primary);
            border-radius: 4px;
        }
        .explanation-box::-webkit-scrollbar-track {
            background: var(--primary-pastel);
        }
        .example-box {
            background: var(--primary-pastel);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            max-width: 800px; 
            margin-left: auto;
            margin-right: auto;
        }

        .example-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-weight: 400;
        }

        .example-header i {
            color: var(--warning);
        }

        .example-code {
            background: #f5f5ff;
            padding: 10px 15px; 
            border-radius: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: var(--text-dark);
            border-left: 3px solid var(--primary);
            white-space: pre-wrap; 
            word-break: break-word; 
            overflow-x: hidden; 
        }


        .task-box {
            background: var(--white-smoke);
            border-radius: 20px;
            padding: 25px;
            border-left: 4px solid var(--warning);
        }

        .task-box h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--text-dark);
        }

        .task-box h3 i {
            color: var(--warning);
        }

        .task-text {
            font-size: 16px;
            color: var(--text-dark);
            line-height: 1.7;
        }
        .code-panel {
            background: white;
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .code-editor {
            background: var(--editor-bg);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .code-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            color: #abb2bf;
        }

        .code-lines {
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        textarea {
            width: 100%;
            min-height: 250px;
            background: transparent;
            border: none;
            color: #e5e5e5;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
            outline: none;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover:not(:disabled) {
            background: var(--primary);
            color: white;
        }

        .btn-outline:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .result-panel {
            background: var(--white-smoke);
            border-radius: 20px;
            padding: 20px;
            border-left: 4px solid var(--border);
            margin-bottom: 25px;
        }

        .result-panel.success {
            border-left-color: var(--success);
            background: #e6f7e6;
        }

        .result-panel.error {
            border-left-color: var(--error);
            background: #fee2e2;
        }

        .result-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .result-content {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
        }

        .hints-section {
            margin-top: 25px;
        }

        .hints-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
        }

        .hints-title i {
            color: var(--warning);
        }

        .hint-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .hint-btn {
            padding: 12px 25px;
            border-radius: 50px;
            border: 2px solid;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .hint-btn.level-1 {
            border-color: #10b981;
            color: #10b981;
        }

        .hint-btn.level-1:hover:not(:disabled) {
            background: #10b981;
            color: white;
        }

        .hint-btn.level-2 {
            border-color: #f59e0b;
            color: #f59e0b;
        }

        .hint-btn.level-2:hover:not(:disabled) {
            background: #f59e0b;
            color: white;
        }

        .hint-btn.level-3 {
            border-color: #ef4444;
            color: #ef4444;
        }

        .hint-btn.level-3:hover:not(:disabled) {
            background: #ef4444;
            color: white;
        }

        .hint-btn.used {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f1f5f9;
        }

        .hint-btn.used.level-1 {
            background: #10b981;
            color: white;
            opacity: 0.8;
        }

        .hint-btn.used.level-2 {
            background: #f59e0b;
            color: white;
            opacity: 0.8;
        }

        .hint-btn.used.level-3 {
            background: #ef4444;
            color: white;
            opacity: 0.8;
        }

        .hint-content {
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            border-radius: 20px;
            padding: 25px;
            margin-top: 20px;
        }

        .hint-text {
            font-size: 16px;
            color: var(--text-dark);
            line-height: 1.7;
            margin-bottom: 15px;
        }

        .hint-code {
            background: var(--editor-bg);
            color: #e5e5e5;
            padding: 15px;
            border-radius: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
        }

        .lesson-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }

        .nav-btn-lesson {
            padding: 12px 25px;
            border-radius: 50px;
            border: none;
            background: white;
            color: var(--text-dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
            text-decoration: none;
        }

        .nav-btn-lesson:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            transform: translateY(-2px);
        }

        .nav-btn-lesson:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 1024px) {
            header {
                padding: 15px 25px;
            }
            
            .two-column {
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
                border: 1px solid rgba(99, 102, 241, 0.2);
                margin-top: 10px;
            }

            .nav-menu.show {
                display: flex;
            }

            .nav-button {
                width: 100%;
                text-align: center;
            }
            
            .lesson-progress {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .hint-buttons {
                flex-direction: column;
            }
            
            .hint-btn {
                width: 100%;
                text-align: center;
            }
            
            .lesson-navigation {
                flex-direction: column;
            }
            
            .nav-btn-lesson {
                width: 100%;
                justify-content: center;
            }
        }
        .task-type-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 8px;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            border-radius: 50px;
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.1);
        }

        .type-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 10px 25px;
            border-radius: 40px;
            transition: all 0.3s ease;
            flex: 1;
            justify-content: center;
            font-weight: 500;
            color: var(--text-dark);
            background: transparent;
        }

        .type-option:hover {
            background: rgba(255, 255, 255, 0.7);
            transform: translateY(-1px);
        }

        .type-option input[type="radio"] {
            accent-color: var(--primary);
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .type-option input[type="radio"]:checked + span {
            color: var(--primary-dark);
            font-weight: 600;
        }

        .type-option span {
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .code-editor {
            transition: all 0.3s ease;
        }

        .code-editor.text-mode .code-header i {
            color: var(--warning);
        }

        .code-editor.text-mode textarea {
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            line-height: 1.8;
            color: var(--text-dark);
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
            <span class="nav-button" data-section="about" id="about-btn">Біз туралы</span>
            <span class="nav-button" data-section="home" id="main-btn">Басты бет</span>
            <span class="nav-button" data-section="contact" id="contact-btn">Байланыс</span>
            <span class="nav-button active" data-href="topics.php">Тапсырмалар</span>
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
                                    <?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?>
                                </div>
                                <div class="avatar-email">
                                    <?= htmlspecialchars($_SESSION['email'] ?? '') ?>
                                </div>
                            </div>
                        </div>

                        <div class="avatar-menu-divider"></div>

                        <a class="avatar-item" href="<?= $profile_link ?>" role="menuitem">
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
        <div class="breadcrumb">
            <a href="topics.php">Тақырыптар</a>
            <i class="fas fa-chevron-right"></i>
            <span class="current"><?php echo htmlspecialchars($lesson['section_title']); ?></span>
            <i class="fas fa-chevron-right"></i>
            <span class="current"><?php echo htmlspecialchars($lesson['title']); ?></span>
        </div>

        <div class="lesson-progress">
            <div class="section-info">
                <span class="section-badge">
                    <i class="fas fa-book-open"></i> 
                    <?php echo htmlspecialchars($lesson['section_title']); ?>
                </span>
                <span><i class="fas fa-list"></i> Сабақ <?php echo $lesson['order_number']; ?>/<?php echo $total_count; ?></span>
            </div>
            
            <div class="lesson-progress-bar">
                <div class="progress-header">
                    <span>Бөлім прогрессі</span>
                    <span><?php echo $completed_count; ?>/<?php echo $total_count; ?></span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: <?php echo $section_progress; ?>%;"></div>
                </div>
            </div>
            
            <div class="score-badge">
                <i class="fas fa-star"></i> 
                <span id="currentScore"><?php echo $current_score; ?></span>/<span id="taskPoints"><?php echo (int)$current_task['points']; ?></span>
            </div>
        </div>
        <div class="two-column">
            <div class="explanation-panel">
                <h1 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                
                <div class="explanation-box">
                    <p><?php echo nl2br(htmlspecialchars($lesson['explanation'])); ?></p>
                </div>
                
                <?php if (!empty($current_task['example_code'])): ?>
                <div class="example-box">
                    <div class="example-header">
                        <i class="fas fa-code"></i>
                        <span>Мысал</span>
                    </div>
                    <div class="example-code" id="exampleCode"><?php echo nl2br(htmlspecialchars($current_task['example_code'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="task-box">
                    <h3><i class="fas fa-tasks"></i> Тапсырма</h3>
                    <div class="task-text">
                        <span id="taskText"><?php echo nl2br(htmlspecialchars($current_task['task'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="code-panel">
                <?php if (count($tasks) > 1): ?>
                    <div class="task-switcher" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                        <?php foreach ($tasks as $index => $task): ?>
                            <button
                                type="button"
                                data-task-index="<?php echo $index; ?>"
                                class="btn <?php echo $index === $current_task_index ? 'btn-primary' : 'btn-outline'; ?>"
                                onclick="switchTask(<?php echo $index; ?>)">
                                Тапсырма <?php echo $index + 1; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="task-type-selector">
                    <label class="type-option">
                        <input type="radio" name="taskType" value="code" checked onchange="toggleTaskType('code')">
                        <span>🐍 Python код</span>
                    </label>
                    <label class="type-option">
                        <input type="radio" name="taskType" value="text" onchange="toggleTaskType('text')">
                        <span>📝 Мәтіндік жауап</span>
                    </label>
                </div>
                <div class="code-editor">
                    <div class="code-header">
                        <span><i class="fab fa-python"></i> Python код</span>
                        <span class="code-lines">⏎</span>
                    </div>
                    <textarea id="codeInput" <?php echo $is_completed ? 'disabled' : ''; ?> placeholder="# Кодыңызды осында жазыңыз..."><?php echo htmlspecialchars($current_task['saved_code']); ?></textarea>
                </div>

                <div class="action-buttons">
                    <button id="checkBtn" class="btn btn-primary" onclick="checkCode()" <?php echo $is_completed ? 'disabled' : ''; ?>>
                        <i class="fas fa-check"></i> Тексеру
                    </button>

                    <button id="resetCodeBtn" class="btn btn-outline" onclick="resetCode()" <?php echo $is_completed ? 'disabled' : ''; ?>>
                        <i class="fas fa-undo"></i> Қайта бастау
                    </button>

                    <button id="runBtn" class="btn btn-outline" onclick="runCode()" <?php echo $is_completed ? 'disabled' : ''; ?>>
                        <i class="fas fa-play"></i> Орындау
                    </button>

                    <button id="resetLessonBtn" class="btn btn-outline" onclick="resetLesson()" style="border-color: #f59e0b;">
                        <i class="fas fa-redo-alt"></i> Қайта шешу
                    </button>
                </div>

                <div class="result-panel" id="resultPanel">
                    <div class="result-header">
                        <i class="fas fa-terminal"></i>
                        <span>Нәтиже</span>
                    </div>
                    <div class="result-content" id="resultContent">
                        <?php if ($is_completed): ?>
                            ✅ Тапсырма орындалған! Балл: <?php echo $current_score; ?>/<?php echo (int)$current_task['points']; ?>
                        <?php else: ?>
                            Кодты тексеру үшін "Тексеру" батырмасын басыңыз.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hints-section">
                    <div class="hints-title">
                        <i class="fas fa-lightbulb"></i>
                        <span>Көмек (хинт) - балл шегеріледі</span>
                    </div>

                    <div class="hint-buttons">
                        <?php for ($level = 1; $level <= 3; $level++): 
                            $hint = $current_task['hints'][$level] ?? null;
                            $is_used = $hints_used_count >= $level;
                            $penalty = $hint ? $hint['penalty'] : ($level == 1 ? 2 : ($level == 2 ? 3 : 5));
                        ?>
                        <button class="hint-btn level-<?php echo $level; ?> <?php echo ($is_used || $is_completed) ? 'used' : ''; ?>" 
                                onclick="getHint(<?php echo $level; ?>)"
                                <?php echo ($is_used || $is_completed) ? 'disabled' : ''; ?>
                                data-level="<?php echo $level; ?>">
                            <i class="fas fa-lightbulb"></i> 
                            <?php echo $level; ?>-деңгей ( -<?php echo $penalty; ?> балл )
                        </button>
                        <?php endfor; ?>
                    </div>

                    <div class="hint-content" id="hintContent">
                        <div class="hint-text" id="hintText">
    <?php if ($is_completed): ?>
        ✅ Тапсырма орындалған!
    <?php elseif ($hints_used_count > 0 && isset($current_task['hints'][$hints_used_count])): ?>
        <?php echo htmlspecialchars($current_task['hints'][$hints_used_count]['hint_text']); ?>
    <?php else: ?>
        Хинт алу үшін батырманы басыңыз. Баллыңыз азаяды.
    <?php endif; ?>
</div>
<div class="hint-code" id="hintCode">
    <?php if ($is_completed): ?>
        #
    <?php elseif ($hints_used_count > 0 && isset($current_task['hints'][$hints_used_count])): ?>
        <?php echo htmlspecialchars($current_task['hints'][$hints_used_count]['hint_code'] ?? ''); ?>
    <?php else: ?>
        #
    <?php endif; ?>
</div>

                    </div>
                </div>
            </div>
        </div>

        <div class="lesson-navigation">
            <?php if ($prev_lesson): ?>
                <a href="task.php?lesson=<?php echo $prev_lesson['id']; ?>" class="nav-btn-lesson">
                    <i class="fas fa-arrow-left"></i> Алдыңғы сабақ
                </a>
            <?php else: ?>
                <button class="nav-btn-lesson" disabled>
                    <i class="fas fa-arrow-left"></i> Алдыңғы сабақ
                </button>
            <?php endif; ?>

            <a href="topics.php" class="nav-btn-lesson">
                <i class="fas fa-list"></i> Тақырыптар тізімі
            </a>

            <?php if ($next_lesson): ?>
                <a href="task.php?lesson=<?php echo $next_lesson['id']; ?>" class="nav-btn-lesson">
                    Келесі сабақ <i class="fas fa-arrow-right"></i>
                </a>
            <?php else: ?>
                <button class="nav-btn-lesson" disabled>
                    Келесі сабақ <i class="fas fa-arrow-right"></i>
                </button>
            <?php endif; ?>
        </div>
        <?php
            $is_last = ($lesson['order_number'] == $total_count);
            if ($is_last): ?>
            <div style="text-align:center; margin-top:20px; margin-left: 50px;">
                <a href="test.php?section=<?= $lesson['section_id'] ?>" class="btn btn-primary">
                    <i class="fas fa-clipboard-check"></i> Бөлім тестін өту
                </a>
            </div>
        <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/pyodide/v0.23.4/full/pyodide.js"></script>
<script>
    let pyodideReady = loadPyodide();
    
    async function runPythonCode(code) {
        let pyodide = await pyodideReady;
        try {
            pyodide.runPython(`
                import sys
                from io import StringIO
                sys.stdout = StringIO()
            `);
            pyodide.runPython(code);
            let output = pyodide.runPython("sys.stdout.getvalue()");
            return output || "Код орындалды.";
        } catch (error) {
            return error.toString();
        }
    }

    const lessonId = <?php echo $lesson_id; ?>;
    const userId = <?php echo $user_id; ?>;
    const tasks = <?php echo json_encode($tasks, JSON_UNESCAPED_UNICODE); ?>;
    let currentTaskIndex = <?php echo $current_task_index; ?>;
    let currentTask = tasks[currentTaskIndex];

    let isCompleted = currentTask.progress.completed == 1;
    let currentScore = parseInt(currentTask.progress.score, 10) || parseInt(currentTask.points, 10) || 0;
    let hintsUsedCount = parseInt(currentTask.progress.hints_used, 10) || 0;

    
    let currentTaskType = 'code'; 
    let autoSaveTimer;

    function showMessage(text, type) {
        const resultPanel = document.getElementById('resultPanel');
        const resultContent = document.getElementById('resultContent');
        resultPanel.className = `result-panel ${type}`;
        resultContent.textContent = text;
    }
    function switchTask(index) {
    currentTaskIndex = index;
    currentTask = tasks[index];

    isCompleted = currentTask.progress.completed == 1;
    currentScore = parseInt(currentTask.progress.score, 10) || parseInt(currentTask.points, 10) || 0;
    hintsUsedCount = parseInt(currentTask.progress.hints_used, 10) || 0;

    document.getElementById('taskText').innerHTML = currentTask.task.replace(/\n/g, '<br>');

    const exampleCodeEl = document.getElementById('exampleCode');
    if (exampleCodeEl) {
        exampleCodeEl.innerHTML = (currentTask.example_code || '').replace(/\n/g, '<br>');
    }

    document.getElementById('taskPoints').textContent = currentTask.points;
    document.getElementById('currentScore').textContent = currentScore;
    document.getElementById('codeInput').value = currentTask.saved_code || '';
    document.getElementById('codeInput').disabled = isCompleted;

    document.getElementById('checkBtn').disabled = isCompleted;
    document.getElementById('resetCodeBtn').disabled = isCompleted || currentTaskType !== 'code';
    document.getElementById('runBtn').disabled = isCompleted || currentTaskType !== 'code';
    document.getElementById('resetLessonBtn').disabled = false;

    document.querySelectorAll('[data-task-index]').forEach((btn) => {
        const btnIndex = parseInt(btn.dataset.taskIndex, 10);
        if (btnIndex === index) {
            btn.classList.remove('btn-outline');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline');
        }
    });

    updateHintUI();
    showMessage(isCompleted ? '✅ Тапсырма орындалған!' : 'Тапсырма ауыстырылды.', isCompleted ? 'success' : '');
}




    function updateHintUI() {
    const hintText = document.getElementById('hintText');
    const hintCode = document.getElementById('hintCode');

    if (isCompleted) {
        hintText.innerHTML = '✅ Тапсырма орындалған!';
        hintCode.innerHTML = '#';
    } else if (hintsUsedCount > 0 && currentTask.hints[hintsUsedCount]) {
        hintText.innerHTML = currentTask.hints[hintsUsedCount].hint_text || '';
        hintCode.innerHTML = currentTask.hints[hintsUsedCount].hint_code || '#';
    } else {
        hintText.innerHTML = 'Хинт алу үшін батырманы басыңыз. Баллыңыз азаяды.';
        hintCode.innerHTML = '#';
    }

    document.querySelectorAll('.hint-btn').forEach(btn => {
        const level = parseInt(btn.dataset.level, 10);
        const used = isCompleted || hintsUsedCount >= level;
        btn.disabled = used;
        btn.classList.toggle('used', used);
    });
}


    function toggleTaskType(type) {
        currentTaskType = type;
        const textarea = document.getElementById('codeInput');
        const codeEditor = document.querySelector('.code-editor');
        const runBtn = document.querySelector('button[onclick="runCode()"]');
        const resetBtn = document.querySelector('button[onclick="resetCode()"]');
        const codeHeader = document.querySelector('.code-header span:first-child');
        
        if (type === 'code') {
            textarea.placeholder = '# Кодыңызды осында жазыңыз...';
            textarea.style.fontFamily = "'Courier New', monospace";
            textarea.style.fontSize = '14px';
            textarea.style.color = '#e5e5e5';
            textarea.style.lineHeight = '1.6';
            textarea.style.background = 'var(--editor-bg)';
            codeEditor.classList.remove('text-mode');
            if (codeHeader) codeHeader.innerHTML = '<i class="fab fa-python"></i> Python код';
            runBtn.style.display = 'inline-flex';
            resetBtn.style.display = 'inline-flex';
            showMessage('Код режимі. Python кодыңызды жазыңыз.', '');
        } else {
            textarea.placeholder = 'Жауабыңызды осында жазыңыз...';
            textarea.style.fontFamily = "'Poppins', sans-serif";
            textarea.style.fontSize = '16px';
            textarea.style.color = 'var(--text-dark)';
            textarea.style.lineHeight = '1.8';
            textarea.style.background = 'var(--white-smoke)';
            codeEditor.classList.add('text-mode');
            if (codeHeader) codeHeader.innerHTML = '<i class="fas fa-pencil-alt"></i> Мәтіндік жауап';
            runBtn.style.display = 'none';
            resetBtn.style.display = 'none';
            showMessage('Мәтіндік режим. Жауабыңызды жазыңыз.', '');
        }
        localStorage.setItem('preferredTaskType', type);
    }

    function loadTaskTypePreference() {
        const saved = localStorage.getItem('preferredTaskType');
        if (saved) {
            const radio = document.querySelector(`input[value="${saved}"]`);
            if (radio) {
                radio.checked = true;
                toggleTaskType(saved);
            }
        }
    }

    function checkCode() {
        if (isCompleted) {
            showMessage('✅ Тапсырма орындалған!', 'success');
            return;
        }
        const userInput = document.getElementById('codeInput').value;
        if (!userInput.trim()) {
            showMessage('❌ Жауап жазыңыз!', 'error');
            return;
        }
        if (currentTaskType === 'code') {
            checkCodeTask(userInput);
        } else {
            checkTextTask(userInput);
        }
    }

    async function checkCodeTask(userCode) {
        showMessage('⏳ Код тексерілуде...', '');
        try {
            const userOutput = await runPythonCode(userCode);
            const normalizeOutput = (text) => (text || '')
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .trim();

const normalizedUserOutput = normalizeOutput(userOutput);
const correctVariants = (currentTask.code_answer || '')
    .split('||')
    .map(v => normalizeOutput(v))
    .filter(Boolean);

const isCorrect = correctVariants.some(variant => normalizedUserOutput === variant);


            if (isCorrect) {
                showMessage('✅ Дұрыс! Тапсырма орындалды.\n\nСіздің нәтиже:\n' + userOutput, 'success');
                markLessonCompleted();
            } else {
                if (userOutput.includes('NameError') && isTextInput(userCode)) {
                    document.getElementById('resultPanel').className = 'result-panel warning';
                    document.getElementById('resultContent').innerHTML = `
❌ Сіз код режимінде текст жазып отырсыз! Сіз жазған: "${userCode}"
📝 Бұл текстік жауап сияқты. Текстік режимде тексеріңіз.
💡 Кеңес: Егер бұл Python коды болса: print("${userCode}")`;
                } else {
                    showMessage(`❌ Қате. Қайталап көріңіз.\n\nСіздің нәтиже:\n${userOutput}`, 'error');
                }
            }
        } catch (error) {
            if (error.toString().includes('SyntaxError') && isTextInput(userCode)) {
                showMessage(`❌ Python синтаксисі дұрыс емес! Сіз жазған: "${userCode}"\n📝 Бұл текстік жауап сияқты.\n💡 Кеңес: print("${userCode}")`, 'error');
            } else {
                showMessage(`❌ Қате: ${error}`, 'error');
            }
        }
    }

    function checkTextTask(userInput) {
        const normalizedUserInput = userInput.trim();
        const correctVariants = (currentTask.text_answer || '').split('||').map(v => v.trim()).filter(Boolean);
        const isCorrect = correctVariants.some(variant => normalizedUserInput === variant);

        if (isCorrect) {
            showMessage('✅ Дұрыс! Тапсырма орындалды.\n\nСіздің жауап: ' + userInput, 'success');
            markLessonCompleted();
        } else {
            showMessage('❌ Қате. Қайталап көріңіз.', 'error');
        }
    }

    function isTextInput(input) {
        if (!input || input.length < 2) return false;
        if (/[а-яА-ЯёЁәіңғүұқөһӘІҢҒҮҰҚӨҺ]/.test(input)) return true;
        if (input.includes('print(')) return false;
        const pythonKeywords = ['def ', 'if ', 'else:', 'for ', 'while ', 'return ', 'import '];
        for (let keyword of pythonKeywords) {
            if (input.includes(keyword)) return false;
        }
        if (/[(){}\[\]]/.test(input)) return false;
        if (/^[a-zA-Z\s.,!?]+$/.test(input)) return true;
        return false;
    }

    async function runCode() {
        if (isCompleted) {
            showMessage('✅ Тапсырма орындалған!', 'success');
            return;
        }
        if (currentTaskType !== 'code') {
            showMessage('⚠️ Бұл режимде код орындау мүмкін емес.', 'warning');
            return;
        }
        const code = document.getElementById('codeInput').value;
        if (!code.trim()) {
            showMessage('❌ Код жазыңыз!', 'error');
            return;
        }
        showMessage('⏳ Код орындалуда...', '');
        const output = await runPythonCode(code);
        showMessage("🐍 Нәтиже:\n\n" + output, 'success');
    }

    function resetCode() {
        if (isCompleted) {
            showMessage('✅ Тапсырма орындалған!', 'success');
            return;
        }
        document.getElementById('codeInput').value = '';
        showMessage(currentTaskType === 'code' ? 'Код тазартылды.' : 'Жауап тазартылды.', '');
    }

    function saveCodeLocally() {
        const code = document.getElementById('codeInput').value;
        localStorage.setItem(`lesson_${lessonId}_code`, code);
    }

    function saveCodeToServer() {
        const code = document.getElementById('codeInput').value;
        const formData = new FormData();
        formData.append('task_id', currentTask.id);
        formData.append('code', code);
        fetch('save_code.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(data => console.log('✅ Код сақталды:', data))
            .catch(err => console.error('❌ Сақтау қатесі:', err));
    }

    function saveAll() {
    if (!isCompleted) saveCodeToServer();
}

    function setupAutoSave() {
        const codeInput = document.getElementById('codeInput');
        codeInput.addEventListener('input', () => {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(saveAll, 1000);
        });
    }

    function loadSavedCode() {
    const codeInput = document.getElementById('codeInput');

    fetch(`get_saved_code.php?task_id=${currentTask.id}`)
        .then(response => response.text())
        .then(code => {
            if (code && code !== 'null') {
                codeInput.value = code;
            }
        })
        .catch(err => console.error('Жүктеу қатесі:', err));
}

    function markLessonCompleted() {
    const formData = new FormData();
    formData.append('task_id', currentTask.id);
    formData.append('hints_used', hintsUsedCount);

    fetch('save_progress.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(data => {
            if (data === 'saved') {
    isCompleted = true;
    currentTask.progress.completed = 1;
    currentTask.progress.hints_used = hintsUsedCount;

    const taskPoints = parseInt(currentTask.points, 10) || 0;
    currentScore = Math.max(0, parseInt(data.score ?? currentTask.progress.score ?? taskPoints, 10) || taskPoints);
    currentTask.progress.score = currentScore;

    document.getElementById('currentScore').textContent = currentScore;
    document.getElementById('codeInput').disabled = true;

    document.getElementById('checkBtn').disabled = true;
    document.getElementById('resetCodeBtn').disabled = true;
    document.getElementById('runBtn').disabled = true;
    document.getElementById('resetLessonBtn').disabled = false;

    updateHintUI();
    showMessage(`✅ Тапсырма орындалды! Балл: ${currentScore}/${taskPoints}`, 'success');
}
 else {
                showMessage('❌ Қате: ' + data, 'error');
            }
        })
        .catch(() => showMessage('❌ Серверге қосылу мүмкін емес', 'error'));
}


    function getHint(level) {
    if (isCompleted) {
        showMessage('✅ Тапсырма орындалған!', 'success');
        return;
    }

    if (hintsUsedCount >= level) {
        showMessage('⚠️ Бұл хинтті қолданғансыз!', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('task_id', currentTask.id);
    formData.append('hint_level', level);

    fetch('get_hint.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hintsUsedCount = level;
                currentTask.progress.hints_used = level;

                const taskPoints = parseInt(currentTask.points, 10) || 0;
                const penalty = parseInt(data.penalty, 10) || 0;
                currentScore = Math.max(0, taskPoints - penalty);
                currentTask.progress.score = currentScore;

                document.getElementById('currentScore').textContent = currentScore;
                document.getElementById('hintText').innerHTML = data.hint_text;
                document.getElementById('hintCode').innerHTML = data.hint_code || '#';

                const btn = document.querySelector(`.hint-btn[data-level="${level}"]`);
                if (btn) {
                    btn.classList.add('used');
                }

                updateHintUI();
                showMessage(`🔍 ${level}-деңгей көмек көрсетілді. -${penalty} балл`, 'warning');
            } else {
                showMessage('❌ Қате: ' + data.message, 'error');
            }
        })
        .catch(() => showMessage('❌ Серверге қосылу мүмкін емес', 'error'));
}


    function resetLesson() {
    if (!confirm('Тапсырманы қайта шешуге дайынсыз ба? Барлық прогресс жойылады.')) return;

    showMessage('⏳ Тапсырма қалпына келтірілуде...', '');

    const formData = new FormData();
    formData.append('task_id', currentTask.id);

    fetch('reset_lesson.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(data => {
            if (data === 'ok') {
    isCompleted = false;
    hintsUsedCount = 0;
    currentScore = parseInt(currentTask.points, 10) || 0;

    currentTask.progress.completed = 0;
    currentTask.progress.hints_used = 0;
    currentTask.progress.score = currentScore;
    currentTask.saved_code = '';

    document.getElementById('currentScore').textContent = currentScore;
    document.getElementById('codeInput').disabled = false;
    document.getElementById('codeInput').value = '';

    document.getElementById('checkBtn').disabled = false;
    document.getElementById('resetCodeBtn').disabled = currentTaskType !== 'code';
    document.getElementById('runBtn').disabled = currentTaskType !== 'code';
    document.getElementById('resetLessonBtn').disabled = false;

    document.querySelectorAll('.hint-btn').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('used');
    });

    updateHintUI();
    showMessage('✅ Тапсырма қалпына келтірілді!', 'success');
}
 else {
                showMessage('❌ Қате: ' + data, 'error');
            }
        })
        .catch(() => showMessage('❌ Серверге қосылу мүмкін емес', 'error'));
}

    document.addEventListener('DOMContentLoaded', function() {
        loadTaskTypePreference();
        loadSavedCode();
        updateHintUI();
        setupAutoSave();
        setInterval(saveAll, 30000);
        window.addEventListener('beforeunload', saveAll);

        const mobileBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.getElementById('navMenu');
        if (mobileBtn && navMenu) {
            mobileBtn.addEventListener('click', () => navMenu.classList.toggle('show'));
            document.querySelectorAll('.nav-button').forEach(button => {
                button.addEventListener('click', function() {
                    if (window.innerWidth <= 850) navMenu.classList.remove('show');
                    const href = this.getAttribute('data-href');
                    if (href) window.location.href = href;
                });
            });
        }

        const avatarBtn = document.getElementById('avatarBtn');
        const avatarMenu = document.getElementById('avatarMenu');
        if (avatarBtn && avatarMenu) {
            avatarBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                avatarMenu.classList.toggle('open');
            });
            document.addEventListener('click', () => avatarMenu.classList.remove('open'));
            avatarMenu.addEventListener('click', (e) => e.stopPropagation());
        }
        document.getElementById('main-btn')?.addEventListener('click', () => window.location.href = 'index.php');
        document.getElementById('about-btn')?.addEventListener('click', () => window.location.href = 'index.php#about');
        document.getElementById('contact-btn')?.addEventListener('click', () => window.location.href = 'index.php#contact');
    });
</script>
</body>
</html>
<?php $conn->close(); ?>