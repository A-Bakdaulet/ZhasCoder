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

$sections_query = "SELECT * FROM sections ORDER BY order_number";
$sections_result = $conn->query($sections_query);

$lessons_by_section = [];
$progress_by_lesson = [];

if ($sections_result->num_rows > 0) {
    while ($section = $sections_result->fetch_assoc()) {
        $section_id = $section['id'];
        
        $lessons_stmt = $conn->prepare("SELECT * FROM lessons WHERE section_id = ? ORDER BY order_number");
        $lessons_stmt->bind_param("i", $section_id);
        $lessons_stmt->execute();
        $lessons_result = $lessons_stmt->get_result();
        
        $lessons = [];
        while ($lesson = $lessons_result->fetch_assoc()) {
            $lessons[] = $lesson;
        }
        $lessons_by_section[$section_id] = $lessons;
        $lessons_stmt->close();
        
        $progress_stmt = $conn->prepare("
    SELECT
        lt.lesson_id,
        COUNT(lt.id) AS total_tasks,
        COUNT(CASE WHEN utp.completed = 1 THEN 1 END) AS completed_tasks
    FROM lesson_tasks lt
    LEFT JOIN user_task_progress utp
        ON utp.task_id = lt.id
        AND utp.user_id = ?
    WHERE lt.lesson_id IN (
        SELECT id FROM lessons WHERE section_id = ?
    )
    GROUP BY lt.lesson_id
");
$progress_stmt->bind_param("ii", $user_id, $section_id);
$progress_stmt->execute();
$progress_result = $progress_stmt->get_result();

while ($progress = $progress_result->fetch_assoc()) {
    $lessonId = (int)$progress['lesson_id'];
    $totalTasks = (int)$progress['total_tasks'];
    $completedTasks = (int)$progress['completed_tasks'];

    $progress_by_lesson[$lessonId] = [
        'completed' => ($totalTasks > 0 && $totalTasks === $completedTasks) ? 1 : 0
    ];
}
$progress_stmt->close();

    }
}

$total_lessons = 0;
$completed_lessons = 0;

$count_lessons = $conn->query("SELECT COUNT(*) as total FROM lessons");
if ($count_lessons) {
    $total_lessons = $count_lessons->fetch_assoc()['total'];
}

$count_completed = $conn->prepare("
    SELECT COUNT(*) AS completed_lessons
    FROM (
        SELECT lt.lesson_id
        FROM lesson_tasks lt
        LEFT JOIN user_task_progress utp
            ON utp.task_id = lt.id
            AND utp.user_id = ?
            AND utp.completed = 1
        GROUP BY lt.lesson_id
        HAVING COUNT(lt.id) = COUNT(utp.task_id)
    ) AS completed_list
");
$count_completed->bind_param("i", $user_id);
$count_completed->execute();
$completed_result = $count_completed->get_result();
if ($completed_result) {
    $completed_lessons = (int)($completed_result->fetch_assoc()['completed_lessons'] ?? 0);
}
$count_completed->close();


$total_progress_percent = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;

$stmt->close();
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
    <title>Тақырыптар - ZhasCoder</title>
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

        .notification-badge {
            position: relative;
            cursor: pointer;
        }

        .notification-badge .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid white;
        }

        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 42px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }

        .page-header p {
            font-size: 18px;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }

        .global-progress {
            background: white;
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .progress-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-title i {
            font-size: 28px;
            color: var(--primary);
        }

        .progress-title h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .progress-stats {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-pastel);
            padding: 8px 20px;
            border-radius: 30px;
        }

        .progress-bar-container {
            width: 100%;
            height: 12px;
            background: var(--gray-light);
            border-radius: 6px;
            overflow: hidden;
            margin-top: 15px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: <?php echo $total_progress_percent; ?>%;
            transition: width 0.3s ease;
            border-radius: 6px;
            position: relative;
        }

        .sections-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .section-card {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
            transition: all 0.3s ease;
        }

        .section-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.15);
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(99, 102, 241, 0.1);
        }

        .section-header:hover {
            background: linear-gradient(135deg, #d0d7ff 0%, #dcd4ff 100%);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .section-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .section-title h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .section-progress {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .section-progress-text {
            font-weight: 600;
            color: var(--primary-dark);
            background: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 14px;
        }

        .section-arrow {
            font-size: 20px;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .section-header.collapsed .section-arrow {
            transform: rotate(-90deg);
        }

        .lessons-list {
            padding: 20px 30px;
            background: white;
        }

        .section-header.collapsed + .lessons-list {
            display: none;
        }

        .lesson-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border-radius: 20px;
            background: var(--white-smoke);
            margin-bottom: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-dark);
            border: 1px solid transparent;
        }

        .lesson-item:hover {
            background: var(--primary-pastel);
            transform: translateX(5px);
            border-color: var(--primary-light);
        }

        .lesson-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .lesson-status {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .lesson-status.completed {
            background: var(--success);
            color: white;
        }

        .lesson-status.not-started {
            background: var(--border);
            color: var(--text-light);
        }

        .lesson-status i {
            font-size: 14px;
        }

        .lesson-title {
            font-weight: 600;
            font-size: 16px;
        }

        .lesson-points {
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-dark);
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
            
            .page-header h1 {
                font-size: 36px;
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

            .logo-text {
                font-size: 24px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .section-progress {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 768px) {
            .avatar-btn {
                padding: 6px 12px 6px 8px;
            }
            
            .avatar-img, .avatar-fallback {
                width: 36px;
                height: 36px;
                font-size: 16px;
                border-width: 2px;
            }
            
            .avatar-caret {
                font-size: 12px;
            }
            
            .avatar-menu {
                width: 260px;
                right: -5px;
            }
            
            .avatar-menu-header {
                padding: 16px;
            }
            
            .avatar-mini {
                width: 40px;
                height: 40px;
                border-radius: 12px;
            }
            
            .avatar-name {
                font-size: 14px;
            }
            
            .avatar-email {
                font-size: 12px;
            }
            
            .avatar-item {
                padding: 12px 16px;
            }
            
            .page-header h1 {
                font-size: 32px;
            }
            
            .section-title h3 {
                font-size: 20px;
            }
            
            .lesson-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .lesson-points {
                align-self: flex-end;
            }
        }

        @media (max-width: 480px) {
            .logo {
                padding: 5px 12px;
            }

            .logo-icon {
                width: 40px;
                height: 40px;
                font-size: 22px;
            }

            .logo-text {
                font-size: 20px;
            }

            .user-auth-container {
                margin-left: auto;
            }
            
            .avatar-btn {
                padding: 4px 10px 4px 6px;
            }
            
            .avatar-img, .avatar-fallback {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .avatar-menu {
                position: fixed;
                top: auto;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                border-radius: 24px 24px 0 0;
                transform: translateY(100%);
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .avatar-menu.open {
                transform: translateY(0);
            }
            
            .nav-container {
                gap: 1rem;
            }
            
            .page-header h1 {
                font-size: 28px;
            }
            
            .global-progress {
                padding: 20px;
            }
            
            .progress-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .section-header {
                padding: 20px;
            }
            
            .lessons-list {
                padding: 15px;
            }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            .nav-menu.show {
                max-height: 80vh;
                overflow-y: auto;
            }
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
        <div class="page-header">
            <h1>Python курс бөлімдері</h1>
            <p>Тақырыптарды ретімен оқып, тапсырмаларды орындаңыз</p>
        </div>

        <div class="global-progress">
            <div class="progress-header">
                <div class="progress-title">
                    <i class="fas fa-chart-line"></i>
                    <h2>Жалпы прогресс</h2>
                </div>
                <div class="progress-stats">
                    <?php echo $completed_lessons; ?> / <?php echo $total_lessons; ?> сабақ
                </div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo $total_progress_percent; ?>%;"></div>
            </div>
            <div style="text-align: right; margin-top: 10px; font-weight: 600; color: var(--primary-dark);">
                <?php echo $total_progress_percent; ?>%
            </div>
        </div>
        <div class="sections-container" id="sectionsContainer">
            <?php 
            $sections_query = "SELECT * FROM sections ORDER BY order_number";
            $sections_result = $conn->query($sections_query);
            
            if ($sections_result && $sections_result->num_rows > 0):
                while ($section = $sections_result->fetch_assoc()): 
                    $section_id = $section['id'];
                    
                    $count_lessons = $conn->prepare("SELECT COUNT(*) as total FROM lessons WHERE section_id = ?");
                    $count_lessons->bind_param("i", $section_id);
                    $count_lessons->execute();
                    $total_in_section = $count_lessons->get_result()->fetch_assoc()['total'];
                    $count_lessons->close();
                    
                    $count_completed = $conn->prepare("
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
    ) AS completed_section_lessons
");
$count_completed->bind_param("ii", $user_id, $section_id);
$count_completed->execute();
$completed_in_section = (int)($count_completed->get_result()->fetch_assoc()['completed_lessons'] ?? 0);
$count_completed->close();

                    
                    $section_progress = $total_in_section > 0 ? round(($completed_in_section / $total_in_section) * 100) : 0;
            ?>
            <div class="section-card">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-title">
                        <div class="section-number"><?php echo $section['order_number']; ?></div>
                        <h3><?php echo htmlspecialchars($section['title']); ?></h3>
                    </div>
                    <div class="section-progress">
                        <span class="section-progress-text"><?php echo $completed_in_section; ?>/<?php echo $total_in_section; ?></span>
                        <i class="fas fa-chevron-down section-arrow"></i>
                    </div>
                </div>
                
                <div class="lessons-list">
                    <?php
                    $lessons_stmt = $conn->prepare("SELECT * FROM lessons WHERE section_id = ? ORDER BY order_number");
                    $lessons_stmt->bind_param("i", $section_id);
                    $lessons_stmt->execute();
                    $lessons_result = $lessons_stmt->get_result();
                    
                    if ($lessons_result && $lessons_result->num_rows > 0):
                        while ($lesson = $lessons_result->fetch_assoc()):
                            $lesson_id = $lesson['id'];
                            $is_completed = isset($progress_by_lesson[$lesson_id]) && $progress_by_lesson[$lesson_id]['completed'] == 1;

                            
                            $score_stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(lt.points), 0) AS max_points,
        COALESCE(SUM(CASE WHEN utp.completed = 1 THEN utp.score ELSE 0 END), 0) AS earned_points
    FROM lesson_tasks lt
    LEFT JOIN user_task_progress utp
        ON utp.task_id = lt.id
        AND utp.user_id = ?
    WHERE lt.lesson_id = ?
");
$score_stmt->bind_param("ii", $user_id, $lesson_id);
$score_stmt->execute();
$score_result = $score_stmt->get_result();
$score_row = $score_result->fetch_assoc();
$earned_score = (int)($score_row['earned_points'] ?? 0);
$max_score = (int)($score_row['max_points'] ?? 0);
$score_stmt->close();

                    ?>
                    <a href="task.php?lesson=<?php echo $lesson_id; ?>" class="lesson-item">
                        <div class="lesson-info">
                            <div class="lesson-status <?php echo $is_completed ? 'completed' : 'not-started'; ?>">
                                <?php if ($is_completed): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <i class="fas fa-circle"></i>
                                <?php endif; ?>
                            </div>
                            <span class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></span>
                        </div>
                        <span class="lesson-points">
                            <?php echo $is_completed ? $earned_score . '/' . $max_score . ' балл' : $max_score . ' балл'; ?>
                        </span>
                    </a>
                    <?php 
                        endwhile;
                    endif;
                    $lessons_stmt->close();
                    ?>
                </div>
            </div>
            <?php 
                endwhile;
            endif;
            ?>
        </div>
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
            
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 850) {
                    const isClickInsideMenu = navMenu.contains(event.target);
                    const isClickOnMenuBtn = mobileBtn.contains(event.target);
                    
                    if (!isClickInsideMenu && !isClickOnMenuBtn && navMenu.classList.contains('show')) {
                        navMenu.classList.remove('show');
                    }
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 850) {
                    navMenu.classList.remove('show');
                }
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
                    avatarMenu.setAttribute('aria-hidden', 'true');
                } else {
                    avatarMenu.classList.add('open');
                    avatarBtn.setAttribute('aria-expanded', 'true');
                    avatarMenu.setAttribute('aria-hidden', 'false');
                }
            }

            avatarBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleAvatarMenu();
            });

            document.addEventListener('click', () => {
                avatarMenu.classList.remove('open');
                avatarBtn.setAttribute('aria-expanded', 'false');
                avatarMenu.setAttribute('aria-hidden', 'true');
            });

            avatarMenu.addEventListener('click', (e) => {
                if (e.target.closest('.avatar-item')) {
                    avatarMenu.classList.remove('open');
                    avatarBtn.setAttribute('aria-expanded', 'false');
                    avatarMenu.setAttribute('aria-hidden', 'true');
                }
                e.stopPropagation();
            });
        }

        function toggleSection(header) {
            header.classList.toggle('collapsed');
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