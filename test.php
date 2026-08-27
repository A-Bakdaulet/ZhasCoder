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

$_SESSION['first_name'] = $user['first_name'] ?? '';
$_SESSION['last_name']  = $user['last_name'] ?? '';
$_SESSION['email']      = $user['email'] ?? '';
$_SESSION['avatar']     = $user['avatar'] ?? '';

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

$section_id = isset($_GET['section']) ? (int)$_GET['section'] : 0;
if ($section_id === 0) {
    header("Location: topics.php");
    exit();
}

$sec_stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
$sec_stmt->bind_param("i", $section_id);
$sec_stmt->execute();
$sec_result = $sec_stmt->get_result();
if ($sec_result->num_rows === 0) {
    header("Location: topics.php");
    exit();
}
$section = $sec_result->fetch_assoc();
$sec_stmt->close();

$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM lessons WHERE section_id = ?");
$total_stmt->bind_param("i", $section_id);
$total_stmt->execute();
$total_count = $total_stmt->get_result()->fetch_assoc()['total'];
$total_stmt->close();

$done_stmt = $conn->prepare("
    SELECT COUNT(*) AS done
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
    ) AS completed_lessons
");
$done_stmt->bind_param("ii", $user_id, $section_id);
$done_stmt->execute();
$done_count = (int)$done_stmt->get_result()->fetch_assoc()['done'];
$done_stmt->close();


$all_completed = ($done_count >= $total_count && $total_count > 0);

$q_stmt = $conn->prepare("
    SELECT id, question, option_a, option_b, option_c, option_d, correct, points, order_num
    FROM test_questions
    WHERE section_id = ?
    ORDER BY order_num ASC
");

$q_stmt->bind_param("i", $section_id);
$q_stmt->execute();
$q_result = $q_stmt->get_result();
$questions = [];
while ($row = $q_result->fetch_assoc()) {
    $questions[] = $row;
}
$q_stmt->close();

$test_already_done = false;
$test_result_data = null;
$saved_results = [];

$table_exists = $conn->query("SHOW TABLES LIKE 'test_results'")->num_rows > 0;

if ($table_exists) {
    $test_stmt = $conn->prepare("SELECT * FROM test_results WHERE user_id = ? AND section_id = ?");
    $test_stmt->bind_param("ii", $user_id, $section_id);
    $test_stmt->execute();
    $test_res = $test_stmt->get_result();

    if ($test_res->num_rows > 0) {
        $test_result_data = $test_res->fetch_assoc();
        $test_already_done = true;

        if (!empty($test_result_data['results'])) {
            $saved_results = json_decode($test_result_data['results'], true) ?: [];
        }
    }

    $test_stmt->close();
}

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
    <title><?php echo htmlspecialchars($section['title']); ?> — Тест | ZhasCoder</title>
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
            --shadow-light: rgba(99,102,241,0.08);
            --shadow-medium: rgba(99,102,241,0.15);
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

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
            inset: 0;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(99,102,241,.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 30%, rgba(139,92,246,.05) 0%, transparent 20%),
                radial-gradient(circle at 30% 70%, rgba(45,212,191,.05) 0%, transparent 30%);
            pointer-events: none;
            z-index: -1;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 40px;
            background: rgba(255,255,255,.85);
            box-shadow: 0 8px 30px var(--shadow-light);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(99,102,241,.15);
        }
        .logo {
            display:flex; align-items:center; gap:14px;
            padding:8px 20px; border-radius:50px;
            background:linear-gradient(135deg,var(--primary-pastel) 0%,var(--secondary-pastel) 100%);
            box-shadow:0 8px 20px var(--shadow-light);
            transition:all .4s cubic-bezier(.4,0,.2,1);
            border:1px solid rgba(255,255,255,.6);
            text-decoration:none;
        }
        .logo:hover { transform:translateY(-3px) scale(1.02); box-shadow:0 12px 30px var(--shadow-medium); }
        .logo-icon {
            width:48px; height:48px;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            border-radius:16px; display:flex; align-items:center; justify-content:center;
            color:var(--white); font-size:26px; font-weight:700;
            box-shadow:0 6px 15px rgba(99,102,241,.3);
            transform:rotate(5deg); transition:transform .3s ease;
        }
        .logo:hover .logo-icon { transform:rotate(0deg) scale(1.05); }
        .logo-text {
            font-size:28px; font-weight:700;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 60%,var(--accent) 100%);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
            font-family:'Montserrat',sans-serif; letter-spacing:-.5px;
        }
        .nav-menu {
            display:flex; align-items:center; gap:.5rem;
            background:rgba(255,255,255,.7); backdrop-filter:blur(10px);
            border-radius:40px; padding:6px;
            box-shadow:0 8px 25px rgba(99,102,241,.1);
            border:1px solid rgba(99,102,241,.15);
        }
        .nav-button {
            cursor:pointer; font-family:'Montserrat',sans-serif; font-weight:600; font-size:15px;
            padding:12px 28px; border-radius:30px; color:var(--text-light);
            background:transparent; border:none; transition:all .3s cubic-bezier(.4,0,.2,1);
            position:relative; z-index:1; letter-spacing:.3px;
        }
        .nav-button::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(135deg,var(--primary-pastel) 0%,var(--secondary-pastel) 100%);
            border-radius:30px; transform:scale(.85); opacity:0;
            transition:all .3s cubic-bezier(.4,0,.2,1); z-index:-1;
        }
        .nav-button:hover { color:var(--primary-dark); transform:translateY(-1px); }
        .nav-button:hover::before { transform:scale(1); opacity:1; }
        .nav-button.active {
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            color:var(--white); box-shadow:0 8px 20px rgba(99,102,241,.3);
        }
        .mobile-menu-btn {
            display:none;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            border:none; color:var(--white); font-size:24px; cursor:pointer;
            padding:14px 18px; border-radius:16px; transition:all .3s ease;
            box-shadow:0 8px 20px rgba(99,102,241,.3);
        }
        .user-auth-container { display:flex; align-items:center; gap:15px; }
        .avatar-dropdown { position:relative; }
        .avatar-btn {
            display:flex; align-items:center; gap:10px; border:none;
            background:linear-gradient(135deg,var(--primary-pastel) 0%,var(--secondary-pastel) 100%);
            cursor:pointer; padding:8px 16px 8px 12px; border-radius:30px;
            transition:all .3s cubic-bezier(.4,0,.2,1);
            box-shadow:0 4px 12px var(--shadow-light);
            border:1px solid rgba(255,255,255,.5);
        }
        .avatar-btn:hover { transform:translateY(-2px); box-shadow:0 8px 20px var(--shadow-medium); }
        .avatar-img { width:42px; height:42px; border-radius:50%; object-fit:cover; border:3px solid var(--white); }
        .avatar-fallback {
            width:42px; height:42px; border-radius:50%;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            color:var(--white); display:flex; align-items:center; justify-content:center;
            font-weight:600; font-size:18px; border:3px solid var(--white);
        }
        .avatar-caret { font-size:14px; color:var(--primary); transition:transform .3s ease; margin-left:5px; }
        .avatar-btn[aria-expanded="true"] .avatar-caret { transform:rotate(180deg); }
        .avatar-menu {
            position:absolute; right:0; top:calc(100% + 12px); width:280px;
            background:rgba(255,255,255,.95); backdrop-filter:blur(10px);
            border-radius:24px; box-shadow:0 20px 40px rgba(99,102,241,.15);
            overflow:hidden; transform:translateY(10px); opacity:0;
            pointer-events:none; transition:all .3s cubic-bezier(.4,0,.2,1);
            border:1px solid rgba(99,102,241,.15); z-index:1000;
        }
        .avatar-menu.open { opacity:1; transform:translateY(0); pointer-events:auto; }
        .avatar-menu-header {
            padding:20px;
            background:linear-gradient(135deg,var(--primary-pastel) 0%,var(--secondary-pastel) 100%);
            display:flex; align-items:center; gap:15px;
            border-bottom:1px solid rgba(99,102,241,.2);
        }
        .avatar-mini { width:48px; height:48px; border-radius:16px; overflow:hidden; flex-shrink:0; border:2px solid white; }
        .avatar-mini img { width:100%; height:100%; object-fit:cover; }
        .avatar-mini-fallback {
            width:100%; height:100%;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            color:var(--white); display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:20px;
        }
        .avatar-user { flex:1; min-width:0; }
        .avatar-name { font-weight:700; color:var(--primary-dark); font-size:16px; font-family:'Montserrat',sans-serif; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .avatar-email { font-size:13px; color:var(--text-light); margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .avatar-menu-divider { height:1px; background:linear-gradient(90deg,transparent,rgba(99,102,241,.3),transparent); margin:6px 0; }
        .avatar-item {
            display:flex; align-items:center; gap:12px; padding:14px 20px;
            text-decoration:none; color:var(--text-dark); font-weight:500;
            font-family:'Montserrat',sans-serif; transition:all .3s ease;
            border-left:3px solid transparent;
        }
        .avatar-item:hover { background:linear-gradient(135deg,var(--primary-pastel) 0%,var(--secondary-pastel) 50%); padding-left:25px; border-left-color:var(--primary); }
        .avatar-item .icon { width:24px; height:24px; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:16px; background:rgba(99,102,241,.1); border-radius:8px; }
        .avatar-item-danger { color:#ef4444; }
        .avatar-item-danger:hover { background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%); border-left-color:#ef4444; }
        .avatar-item-danger .icon { color:#ef4444; background:rgba(239,68,68,.1); }

        .main-container { max-width:900px; margin:40px auto; padding:0 20px; }

        .breadcrumb {
            display:flex; align-items:center; gap:10px; margin-bottom:30px;
            background:white; padding:15px 25px; border-radius:50px;
            box-shadow:0 5px 15px rgba(99,102,241,.1); border:1px solid rgba(99,102,241,.1);
        }
        .breadcrumb a { color:var(--text-light); text-decoration:none; font-weight:500; transition:color .3s ease; }
        .breadcrumb a:hover { color:var(--primary); }
        .breadcrumb i { color:var(--text-light); font-size:14px; }
        .breadcrumb .current { color:var(--primary); font-weight:600; }

        .test-hero {
            background:white;
            border-radius:30px;
            padding:40px;
            box-shadow:0 10px 30px rgba(99,102,241,.1);
            border:1px solid rgba(99,102,241,.1);
            text-align:center;
            margin-bottom:30px;
            position:relative;
            overflow:hidden;
        }
        .test-hero::before {
            content:'';
            position:absolute;
            top:-60px; right:-60px;
            width:200px; height:200px;
            background:linear-gradient(135deg,var(--primary-pastel),var(--secondary-pastel));
            border-radius:50%;
            opacity:.5;
        }
        .test-hero::after {
            content:'';
            position:absolute;
            bottom:-40px; left:-40px;
            width:140px; height:140px;
            background:linear-gradient(135deg,var(--secondary-pastel),var(--primary-pastel));
            border-radius:50%;
            opacity:.4;
        }
        .test-hero-icon {
            font-size:60px;
            margin-bottom:20px;
            display:block;
            position:relative;
            z-index:1;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%,100% { transform:translateY(0); }
            50% { transform:translateY(-10px); }
        }
        .test-hero h1 {
            font-size:32px; font-weight:700;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
            margin-bottom:10px; position:relative; z-index:1;
            font-family:'Montserrat',sans-serif;
        }
        .test-hero p {
            color:var(--text-light); font-size:16px; line-height:1.7;
            position:relative; z-index:1; max-width:600px; margin:0 auto 20px;
        }
        .test-meta {
            display:flex; justify-content:center; gap:20px; flex-wrap:wrap;
            position:relative; z-index:1;
        }
        .meta-chip {
            background:linear-gradient(135deg,var(--primary-pastel),var(--secondary-pastel));
            padding:8px 20px; border-radius:30px;
            font-weight:600; color:var(--primary-dark); font-size:14px;
            display:flex; align-items:center; gap:8px;
        }

        .locked-box {
            background:white; border-radius:30px; padding:50px 40px;
            box-shadow:0 10px 30px rgba(99,102,241,.1);
            border:1px solid rgba(99,102,241,.1);
            text-align:center;
        }
        .locked-icon { font-size:72px; display:block; margin-bottom:20px; }
        .locked-box h2 { font-size:26px; font-weight:700; margin-bottom:12px; color:var(--text-dark); font-family:'Montserrat',sans-serif; }
        .locked-box p { color:var(--text-light); font-size:16px; line-height:1.7; margin-bottom:25px; }
        .progress-display {
            background:var(--gray-light); border-radius:20px; padding:20px;
            margin-bottom:25px; display:flex; align-items:center; gap:15px;
        }
        .progress-display .track {
            flex:1; height:10px; background:rgba(99,102,241,.15); border-radius:5px; overflow:hidden;
        }
        .progress-display .fill {
            height:100%;
            background:linear-gradient(90deg,var(--primary),var(--secondary));
            border-radius:5px;
            transition:width 1s ease;
        }
        .progress-display .label { font-weight:600; color:var(--primary); white-space:nowrap; }

        .result-card {
            background:white; border-radius:30px; padding:50px 40px;
            box-shadow:0 10px 30px rgba(99,102,241,.1);
            border:1px solid rgba(99,102,241,.1);
            text-align:center;
        }
        .result-card .big-score {
            font-size:80px; font-weight:700; line-height:1;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
            font-family:'Montserrat',sans-serif;
        }
        .result-card .score-label { font-size:18px; color:var(--text-light); margin-bottom:20px; }
        .result-card .grade-badge {
            display:inline-block; padding:10px 30px; border-radius:30px;
            font-weight:700; font-size:18px; margin-bottom:25px;
        }
        .grade-A { background:linear-gradient(135deg,#d1fae5,#a7f3d0); color:#065f46; }
        .grade-B { background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:#1e40af; }
        .grade-C { background:linear-gradient(135deg,#fef3c7,#fde68a); color:#92400e; }
        .grade-F { background:linear-gradient(135deg,#fee2e2,#fecaca); color:#991b1b; }

        .test-form { display:flex; flex-direction:column; gap:25px; }

        .question-card {
            background:white; border-radius:24px; padding:30px;
            box-shadow:0 8px 25px rgba(99,102,241,.08);
            border:1px solid rgba(99,102,241,.1);
            transition:all .3s ease;
            animation: slideIn .4s ease both;
        }
        .question-card:hover { transform:translateY(-2px); box-shadow:0 12px 35px rgba(99,102,241,.15); }
        @keyframes slideIn {
            from { opacity:0; transform:translateY(20px); }
            to { opacity:1; transform:translateY(0); }
        }

        .q-number {
            display:inline-flex; align-items:center; justify-content:center;
            width:34px; height:34px; border-radius:10px;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            color:white; font-weight:700; font-size:16px;
            margin-bottom:15px; font-family:'Montserrat',sans-serif;
        }
        .q-title {
            font-weight:600; font-size:17px; color:var(--text-dark);
            margin-bottom:5px; font-family:'Montserrat',sans-serif;
        }
        .q-task {
            color:var(--text-light); font-size:15px; line-height:1.7;
            margin-bottom:20px;
            background:var(--primary-pastel); border-radius:14px; padding:15px 18px;
            border-left:3px solid var(--primary);
        }

        .answer-group { display:flex; flex-direction:column; gap:8px; }

        .answer-input {
            width:100%; padding:14px 18px;
            background:var(--white-smoke); border:2px solid rgba(99,102,241,.15);
            border-radius:14px; font-family:'Poppins',sans-serif; font-size:15px;
            color:var(--text-dark); outline:none;
            transition:all .3s ease; resize:vertical; min-height:50px;
        }
        .answer-input:focus {
            border-color:var(--primary);
            background:white;
            box-shadow:0 0 0 4px rgba(99,102,241,.1);
        }

        .answer-type-tabs {
            display:flex; gap:8px; margin-bottom:12px;
        }
        .type-tab {
            padding:7px 18px; border-radius:20px; border:2px solid rgba(99,102,241,.2);
            background:transparent; cursor:pointer; font-size:13px; font-weight:600;
            color:var(--text-light); transition:all .3s ease; font-family:'Montserrat',sans-serif;
        }
        .type-tab.active {
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            color:white; border-color:transparent;
        }

        .code-input {
            width:100%; padding:15px 18px;
            background:#1e1e2e; border:2px solid rgba(99,102,241,.2);
            border-radius:14px; font-family:'Courier New',monospace; font-size:13px;
            color:#e5e5e5; outline:none; resize:vertical; min-height:100px;
            transition:border-color .3s ease; line-height:1.6;
        }
        .code-input:focus { border-color:var(--primary); }

        .submit-area {
            background:white; border-radius:24px; padding:30px;
            box-shadow:0 8px 25px rgba(99,102,241,.08);
            border:1px solid rgba(99,102,241,.1);
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:20px;
        }
        .submit-info { color:var(--text-light); font-size:14px; }
        .submit-info strong { color:var(--primary); }

        .btn {
            padding:14px 36px; border-radius:50px; border:none;
            font-weight:700; font-size:16px; cursor:pointer; transition:all .3s ease;
            display:inline-flex; align-items:center; gap:10px;
            font-family:'Montserrat',sans-serif;
        }
        .btn-primary {
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            color:white; box-shadow:0 8px 20px rgba(99,102,241,.3);
        }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 12px 30px rgba(99,102,241,.4); }
        .btn-outline {
            background:transparent; border:2px solid var(--primary); color:var(--primary);
        }
        .btn-outline:hover { background:var(--primary); color:white; }
        .btn-back {
            background:white; color:var(--text-dark);
            border:1px solid rgba(99,102,241,.2);
            box-shadow:0 5px 15px rgba(99,102,241,.08);
            text-decoration:none;
        }
        .btn-back:hover { background:var(--primary-pastel); color:var(--primary-dark); transform:translateY(-2px); }

        .result-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(30,27,75,.5); backdrop-filter:blur(8px);
            z-index:2000; align-items:center; justify-content:center;
        }
        .result-overlay.show { display:flex; }
        .result-modal {
            background:white; border-radius:30px; padding:50px 40px;
            max-width:520px; width:90%; text-align:center;
            box-shadow:0 30px 60px rgba(99,102,241,.3);
            animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes popIn {
            from { opacity:0; transform:scale(.7); }
            to { opacity:1; transform:scale(1); }
        }
        .modal-icon { font-size:72px; display:block; margin-bottom:20px; }
        .modal-score {
            font-size:64px; font-weight:800; line-height:1;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
            font-family:'Montserrat',sans-serif;
        }
        .modal-out-of { font-size:18px; color:var(--text-light); margin-bottom:10px; }
        .modal-title { font-size:22px; font-weight:700; margin-bottom:10px; font-family:'Montserrat',sans-serif; }
        .modal-msg { color:var(--text-light); font-size:15px; line-height:1.7; margin-bottom:30px; }
        .modal-actions { display:flex; gap:15px; justify-content:center; flex-wrap:wrap; }

        .question-card.answered { border-left:4px solid var(--success); }
        .q-answered-badge {
            display:none; float:right;
            background:#d1fae5; color:#065f46; padding:4px 12px; border-radius:20px;
            font-size:12px; font-weight:600;
        }
        .question-card.answered .q-answered-badge { display:inline-block; }

        .test-progress {
            background:white; border-radius:20px; padding:20px 25px;
            margin-bottom:25px;
            box-shadow:0 5px 15px rgba(99,102,241,.08);
            border:1px solid rgba(99,102,241,.1);
            display:flex; align-items:center; gap:15px;
        }
        .test-progress-label { font-weight:600; color:var(--primary); font-size:14px; white-space:nowrap; }
        .test-progress-track { flex:1; height:8px; background:var(--gray-light); border-radius:4px; overflow:hidden; }
        .test-progress-fill {
            height:100%; background:linear-gradient(90deg,var(--primary),var(--secondary));
            border-radius:4px; transition:width .4s ease;
            width:0%;
        }
        .test-progress-count { font-size:13px; color:var(--text-light); white-space:nowrap; }

        @media (max-width:768px) {
            header { padding:15px 20px; }
            .main-container { padding:0 15px; }
            .test-hero { padding:30px 20px; }
            .test-hero h1 { font-size:24px; }
            .question-card { padding:20px; }
            .submit-area { flex-direction:column; align-items:stretch; }
            .modal-actions { flex-direction:column; }
            .mobile-menu-btn { display:block; }
            .nav-menu {
                display:none; position:absolute; top:100%; left:20px; right:20px;
                flex-direction:column; background:rgba(255,255,255,.95);
                backdrop-filter:blur(15px); padding:20px; border-radius:24px;
                box-shadow:0 20px 40px rgba(99,102,241,.15);
                border:1px solid rgba(99,102,241,.2); margin-top:10px;
            }
            .nav-menu.show { display:flex; }
            .nav-button { width:100%; text-align:center; }
        }
        .radio-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.option-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 2px solid rgba(99,102,241,.12);
    border-radius: 16px;
    background: var(--white-smoke);
    cursor: pointer;
    transition: all .25s ease;
}

.option-card:hover {
    border-color: var(--primary);
    background: white;
    transform: translateY(-1px);
}

.option-card input[type="radio"] {
    accent-color: var(--primary);
    transform: scale(1.15);
}

.option-card span {
    color: var(--text-dark);
    font-size: 15px;
    line-height: 1.5;
}

    </style>
</head>
<body>

<header>
    <a href="index.php" class="logo">
        <div class="logo-icon">📚</div>
        <span class="logo-text">ZhasCoder</span>
    </a>

    <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

    <div class="nav-menu" id="navMenu">
        <span class="nav-button" id="about-btn">Біз туралы</span>
        <span class="nav-button" id="main-btn">Басты бет</span>
        <span class="nav-button" id="contact-btn">Байланыс</span>
        <span class="nav-button active" data-href="topics.php">Тапсырмалар</span>
    </div>

    <div class="user-auth-container">
        <div class="avatar-dropdown">
            <button class="avatar-btn" id="avatarBtn" type="button" aria-expanded="false">
                <?php if (!empty($avatar_path)): ?>
                    <img src="<?= htmlspecialchars($avatar_path) ?>" alt="Avatar" class="avatar-img">
                <?php else: ?>
                    <div class="avatar-fallback"><?= htmlspecialchars($initials) ?></div>
                <?php endif; ?>
                <span class="avatar-caret">▾</span>
            </button>
            <div class="avatar-menu" id="avatarMenu" role="menu" aria-hidden="true">
                <div class="avatar-menu-header">
                    <div class="avatar-mini">
                        <?php if (!empty($avatar_path)): ?>
                            <img src="<?= htmlspecialchars($avatar_path) ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-mini-fallback"><?= htmlspecialchars($initials) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-user">
                        <div class="avatar-name"><?= htmlspecialchars(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??'')) ?></div>
                        <div class="avatar-email"><?= htmlspecialchars($_SESSION['email']??'') ?></div>
                    </div>
                </div>
                <div class="avatar-menu-divider"></div>
                <a class="avatar-item" href="<?= $profile_link ?>" role="menuitem">
                    <span class="icon">👤</span><span>Жеке кабинет</span>
                </a>
                <div class="avatar-menu-divider"></div>
                <a class="avatar-item avatar-item-danger" href="logout.php" role="menuitem">
                    <span class="icon">⎋</span><span>Шығу</span>
                </a>
            </div>
        </div>
    </div>
</header>

<div class="main-container">

    <div class="breadcrumb">
        <a href="topics.php">Тақырыптар</a>
        <i class="fas fa-chevron-right"></i>
        <span class="current"><?= htmlspecialchars($section['title']) ?></span>
        <i class="fas fa-chevron-right"></i>
        <span class="current">Тест</span>
    </div>

    <?php if (!$all_completed): ?>
    <div class="locked-box">
        <span class="locked-icon">🔒</span>
        <h2>Тест қол жетімді емес</h2>
        <p>Тестті өту үшін алдымен <strong>«<?= htmlspecialchars($section['title']) ?>»</strong> бөлімінің барлық сабақтарын аяқтауыңыз керек.</p>
        <div class="progress-display">
            <span class="label"><?= $done_count ?>/<?= $total_count ?></span>
            <div class="track">
                <div class="fill" style="width:<?= $total_count>0 ? round($done_count/$total_count*100) : 0 ?>%"></div>
            </div>
            <span class="label"><?= $total_count>0 ? round($done_count/$total_count*100) : 0 ?>%</span>
        </div>
        <a href="topics.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Сабақтарға оралу
        </a>
    </div>

    <?php elseif ($test_already_done && $test_result_data): ?>
    <?php
        $score = $test_result_data['score'];
        $max   = $test_result_data['max_score'];
        $pct   = $max > 0 ? round($score/$max*100) : 0;

        $results_map = [];
        foreach ($saved_results as $r) {
            if (isset($r['id'])) {
                $results_map[(int)$r['id']] = $r;
            }
        }

        $option_labels = [
            'a' => 'A',
            'b' => 'B',
            'c' => 'C',
            'd' => 'D'
        ];

        if ($pct >= 90)      { $grade = 'A'; $grade_class = 'grade-A'; $grade_msg = '🎉 Керемет нәтиже! Сіз осы тақырыпты өте жақсы меңгергенсіз.'; }
        elseif ($pct >= 75)  { $grade = 'B'; $grade_class = 'grade-B'; $grade_msg = '👍 Жақсы нәтиже! Бірнеше тақырыпты қайталап, 90%+ жетіңіз.'; }
        elseif ($pct >= 60)  { $grade = 'C'; $grade_class = 'grade-C'; $grade_msg = '📚 Жақсы, бірақ кейбір тақырыптарды тереңірек оқу керек.'; }
        else                 { $grade = 'F'; $grade_class = 'grade-F'; $grade_msg = '💪 Тырысыңыз! Сабақтарды қайта оқып, тесті қайта тапсырыңыз.'; }
    ?>

   <div class="result-card">
    <span style="font-size:64px;display:block;margin-bottom:20px;">🏆</span>
    <div class="big-score"><?= $score ?></div>
    <div class="score-label">/ <?= $max ?> балл · <?= $pct ?>%</div>
    <span class="grade-badge <?= $grade_class ?>">Баға: <?= $grade ?></span>
    <p style="color:var(--text-light);font-size:16px;line-height:1.7;margin-bottom:30px;"><?= $grade_msg ?></p>
    <div style="display:flex;gap:15px;justify-content:center;flex-wrap:wrap;">
        <a href="topics.php" class="btn btn-primary"><i class="fas fa-list"></i> Тақырыптар</a>
    </div>
</div>

<div class="test-form" style="margin-top:30px;">
    <?php foreach ($questions as $i => $q): ?>
        <?php
            $qid = (int)$q['id'];
            $user_result = $results_map[$qid] ?? null;
            $user_answer = trim(strtolower((string)($user_result['user_answer'] ?? '')));
            $correct_answer = trim(strtolower((string)($q['correct'] ?? '')));
            $is_correct = ($user_answer !== '' && $user_answer === $correct_answer);


            $options = [
                'a' => $q['option_a'],
                'b' => $q['option_b'],
                'c' => $q['option_c'],
                'd' => $q['option_d'],
            ];
        ?>
        <div class="question-card" style="border-left:4px solid <?= $is_correct ? 'var(--success)' : 'var(--error)' ?>;">
            <div class="q-number"><?= $i + 1 ?></div>
            <div class="q-title"><?= htmlspecialchars($q['question']) ?></div>

            <div style="margin-top:15px;display:flex;flex-direction:column;gap:10px;">
                <div>
                    <strong>Сіздің жауабыңыз:</strong>
                    <?= $user_answer && isset($options[$user_answer]) ? htmlspecialchars($option_labels[$user_answer] . '. ' . $options[$user_answer]) : 'Жауап берілмеген' ?>
                </div>
                <div>
                    <strong>Дұрыс жауап:</strong>
                    <?= htmlspecialchars($option_labels[$correct_answer] . '. ' . $options[$correct_answer]) ?>
                </div>
                <div style="font-weight:600;color:<?= $is_correct ? 'var(--success)' : 'var(--error)' ?>;">
                    <?= $is_correct ? 'Дұрыс' : 'Қате' ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
    <?php else: ?>
    <div class="test-hero">
        <span class="test-hero-icon">📝</span>
        <h1><?= htmlspecialchars($section['title']) ?> — Тест</h1>
        <p>Осы бөлімдегі барлық сабақтардың материалдарын тексереміз. Тапсырмаларды мұқият оқып, жауап беріңіз.</p>
        <div class="test-meta">
            <span class="meta-chip"><i class="fas fa-question-circle"></i> <?= count($questions) ?> сұрақ</span>
            <span class="meta-chip"><i class="fas fa-star"></i> <?= array_sum(array_column($questions,'points')) ?> балл</span>
            <span class="meta-chip"><i class="fas fa-clock"></i> Уақыт шектеусіз</span>
        </div>
    </div>
    <div class="test-progress">
        <span class="test-progress-label"><i class="fas fa-tasks"></i> Прогресс</span>
        <div class="test-progress-track">
            <div class="test-progress-fill" id="testProgressFill"></div>
        </div>
        <span class="test-progress-count" id="testProgressCount">0 / <?= count($questions) ?></span>
    </div>

    <form id="testForm" onsubmit="submitTest(event)">
    <div class="test-form">
        <?php foreach ($questions as $i => $q): ?>
                <div class="question-card" id="qcard-<?= $i ?>" style="animation-delay:<?= $i*0.07 ?>s">
            <span class="q-answered-badge">✓ Жауап берілді</span>
            <div class="q-number"><?= $i + 1 ?></div>
            <div class="q-title"><?= htmlspecialchars($q['question']) ?></div>

            <div class="answer-group radio-group">
                <label class="option-card">
                    <input type="radio" name="answer[<?= $i ?>]" value="a" onchange="markAnswered(<?= $i ?>)">
                    <span><strong>A.</strong> <?= htmlspecialchars($q['option_a']) ?></span>
                </label>

                <label class="option-card">
                    <input type="radio" name="answer[<?= $i ?>]" value="b" onchange="markAnswered(<?= $i ?>)">
                    <span><strong>B.</strong> <?= htmlspecialchars($q['option_b']) ?></span>
                </label>

                <label class="option-card">
                    <input type="radio" name="answer[<?= $i ?>]" value="c" onchange="markAnswered(<?= $i ?>)">
                    <span><strong>C.</strong> <?= htmlspecialchars($q['option_c']) ?></span>
                </label>

                <label class="option-card">
                    <input type="radio" name="answer[<?= $i ?>]" value="d" onchange="markAnswered(<?= $i ?>)">
                    <span><strong>D.</strong> <?= htmlspecialchars($q['option_d']) ?></span>
                </label>
            </div>
        </div>

        <?php endforeach; ?>

        <div class="submit-area">
            <div class="submit-info">
                <strong id="answeredCount">0</strong> / <?= count($questions) ?> сұраққа жауап берілді
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Тестті аяқтау
            </button>
        </div>
    </div>
    </form>
    <?php endif; ?>

</div>
<div class="result-overlay" id="resultOverlay">
    <div class="result-modal">
        <span class="modal-icon" id="modalIcon">🎉</span>
        <div class="modal-score" id="modalScore">0</div>
        <div class="modal-out-of" id="modalOutOf">/ 0 балл</div>
        <div class="modal-title" id="modalTitle">Тест аяқталды!</div>
        <div class="modal-msg" id="modalMsg"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="window.location.reload()">
                <i class="fas fa-eye"></i> Тест жауаптары
            </button>
            <a href="topics.php" class="btn btn-primary">
                <i class="fas fa-list"></i> Тақырыптар
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/pyodide/v0.23.4/full/pyodide.js"></script>
<script>
const TOTAL_Q   = <?= count($questions) ?>;
const SECTION_ID = <?= $section_id ?>;
const questionsData = <?= json_encode(array_values($questions)) ?>;
let answeredSet = new Set();


function markAnswered(idx) {
    const selected = document.querySelector(`input[name="answer[${idx}]"]:checked`);
    const card = document.getElementById('qcard-' + idx);

    if (selected) {
        answeredSet.add(idx);
        card.classList.add('answered');
    } else {
        answeredSet.delete(idx);
        card.classList.remove('answered');
    }

    updateProgress();
}

function updateProgress() {
    const c = answeredSet.size;
    document.getElementById('answeredCount').textContent = c;
    document.getElementById('testProgressCount').textContent = c + ' / ' + TOTAL_Q;
    document.getElementById('testProgressFill').style.width = (TOTAL_Q > 0 ? (c/TOTAL_Q*100) : 0) + '%';
}
async function submitTest(e) {
    e.preventDefault();

    const submitBtn = e.target.querySelector('[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Тексерілуде...';

    let totalScore = 0;
    let maxScore = 0;
    let results = [];

    for (let i = 0; i < questionsData.length; i++) {
        const q = questionsData[i];
        const selected = document.querySelector(`input[name="answer[${i}]"]:checked`);
        maxScore += parseInt(q.points, 10);

        if (!selected) {
            results.push({
                id: q.id,
                user_answer: '',
                correct_answer: String(q.correct).toLowerCase(),
                correct: false,
                score: 0
            });
            continue;
        }

        const userAnswer = selected.value.toLowerCase();
        const correctAnswer = String(q.correct).toLowerCase();
        const correct = userAnswer === correctAnswer;
        const pts = correct ? parseInt(q.points, 10) : 0;

        totalScore += pts;

        results.push({
            id: q.id,
            user_answer: userAnswer,
            correct_answer: correctAnswer,
            correct: correct,
            score: pts
        });
    }

    const fd = new FormData();
    fd.append('section_id', SECTION_ID);
    fd.append('score', totalScore);
    fd.append('max_score', maxScore);
    fd.append('results', JSON.stringify(results));

    try {
        await fetch('save_test_result.php', { method: 'POST', body: fd });
    } catch (err) {
        console.warn('Сақтау қатесі:', err);
    }

    const pct = maxScore > 0 ? Math.round(totalScore / maxScore * 100) : 0;
    let icon, title, msg;

    if (pct >= 90) {
        icon = '🏆';
        title = 'Тамаша нәтиже!';
        msg = 'Сіз осы бөлімді өте жоғары деңгейде меңгергенсіз. Керемет!';
    } else if (pct >= 75) {
        icon = '🎉';
        title = 'Жақсы нәтиже!';
        msg = 'Негізгі материалды жақсы білесіз. Сәл тереңірек зерделесе — өте жақсы болады!';
    } else if (pct >= 60) {
        icon = '📚';
        title = 'Орташа нәтиже';
        msg = 'Кейбір тақырыптарды қайталап оқуды ұсынамыз.';
    } else {
        icon = '💪';
        title = 'Тырысыңыз!';
        msg = 'Сабақтарды қайта оқып, тесті қайта тапсырыңыз. Сіз жасай аласыз!';
    }

    document.getElementById('modalIcon').textContent = icon;
    document.getElementById('modalScore').textContent = totalScore;
    document.getElementById('modalOutOf').textContent = '/ ' + maxScore + ' балл · ' + pct + '%';
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalMsg').textContent = msg;
    document.getElementById('resultOverlay').classList.add('show');
}

document.addEventListener('DOMContentLoaded', () => {
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    if (mobileBtn) mobileBtn.addEventListener('click', () => navMenu.classList.toggle('show'));
    document.querySelectorAll('.nav-button').forEach(btn => {
        btn.addEventListener('click', function() {
            if (window.innerWidth <= 850) navMenu.classList.remove('show');
            const href = this.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });
    document.getElementById('main-btn')?.addEventListener('click', () => window.location.href = 'index.php');
    document.getElementById('about-btn')?.addEventListener('click', () => window.location.href = 'index.php#about');
    document.getElementById('contact-btn')?.addEventListener('click', () => window.location.href = 'index.php#contact');

    const avatarBtn = document.getElementById('avatarBtn');
    const avatarMenu = document.getElementById('avatarMenu');
    if (avatarBtn) {
        avatarBtn.addEventListener('click', e => { e.stopPropagation(); avatarMenu.classList.toggle('open'); });
        document.addEventListener('click', () => avatarMenu.classList.remove('open'));
        avatarMenu.addEventListener('click', e => e.stopPropagation());
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>