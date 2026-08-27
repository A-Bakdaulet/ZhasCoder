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

$stmt->close();
$conn->close();
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
    <title>ZhasCoder</title>
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
        }

        html {
            scroll-behavior: smooth;
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
        .floating-shape {
            position: fixed;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            filter: blur(80px);
            opacity: 0.1;
            z-index: -1;
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            top: -100px;
            left: -100px;
        }

        .shape-2 {
            bottom: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--accent) 100%);
            animation-delay: -5s;
        }
        

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
            }
            33% {
                transform: translate(30px, -30px) rotate(120deg);
            }
            66% {
                transform: translate(-30px, 30px) rotate(240deg);
            }
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
        }
        .hero {
            padding: 80px 0;
            background: linear-gradient(135deg, #f0f4ff 0%, #f8f0ff 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .hero .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 32px;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 56px;
            font-weight: 700;
            line-height: 1.2;
            color: #1e293b;
            margin-bottom: 24px;
            max-width: 900px;
            text-align: center;
            margin-left: auto;
            margin-right: auto;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #2dd4bf 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 18px;
            color: #475569;
            max-width: 700px;
            margin: 0 auto 40px;
            text-align: center;
            line-height: 1.7;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 80px;
            flex-wrap: wrap;
        }

        .hero-buttons .btn {
            padding: 16px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
        }

        .hero-buttons .btn i {
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        .hero-buttons .btn:hover i {
            transform: translateX(5px);
        }

        .hero-buttons .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);
        }

        .hero-buttons .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px rgba(16, 185, 129, 0.5);
        }

        .hero-buttons .btn-outline {
            background: transparent;
            color: #4f46e5;
            border: 2px solid #4f46e5;
        }

        .hero-buttons .btn-outline:hover {
            background: #4f46e5;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
        }

        .hero-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(99, 102, 241, 0.15);
            border-radius: 32px;
            padding: 32px 24px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px -10px rgba(99, 102, 241, 0.1);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -12px rgba(79, 70, 229, 0.25);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .feature-card h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-card h3 i {
            font-size: 28px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .feature-card p {
            font-size: 16px;
            color: #475569;
            margin: 0;
            text-align: left;
            line-height: 1.6;
        }
        .info-blocks {
            padding: 80px 0;
            background: white;
            position: relative;
        }

        .info-blocks .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 32px;
        }

        .blocks-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .info-block {
            background: #ffffff;
            border-radius: 32px;
            padding: 36px 28px;
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            height: fit-content;
        }

        .info-block:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 45px -12px rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .block-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #e0e7ff 0%, #ede9fe 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .block-icon i {
            font-size: 36px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .info-block h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1e293b;
            line-height: 1.3;
        }

        .info-block p {
            font-size: 16px;
            color: #475569;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .block-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 25px;
            background: #f8fafc;
            padding: 20px 15px;
            border-radius: 24px;
        }

        .stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        .stats-highlight {
            margin: 40px 0;
            padding: 30px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 32px;
            box-shadow: 0 15px 35px -10px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.15);
        }

        .stats-highlight-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }

        .stats-highlight-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 5px 15px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .stats-highlight-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -10px rgba(99, 102, 241, 0.2);
            border-color: #6366f1;
        }

        .stats-highlight-item i {
            font-size: 40px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats-highlight-content {
            display: flex;
            flex-direction: column;
        }

        .stats-highlight-number {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            font-family: 'Montserrat', sans-serif;
        }

        .stats-highlight-label {
            font-size: 16px;
            color: #4f46e5;
            font-weight: 600;
            margin-top: 5px;
        }

        .stats-note {
            background: linear-gradient(135deg, #e0e7ff 0%, #ede9fe 100%);
            border-left: 4px solid #6366f1;
            margin-top: 20px;
        }

        .stats-note i {
            color: #6366f1;
        }

        .stats-note span {
            color: #1e293b;
            font-weight: 500;
        }

        .stats-note strong {
            color: #4f46e5;
            font-weight: 700;
        }


        .stat-label {
            font-size: 14px;
            color: #64748b;
        }

        .feature-list {
            list-style: none;
            margin: 20px 0;
        }

        .feature-list li {
            padding: 10px 0;
            border-bottom: 1px dashed #e2e8f0;
            color: #334155;
            font-size: 15px;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li strong {
            color: #4f46e5;
            font-weight: 600;
            min-width: 110px;
        }

        .highlight {
            background: #e0f2fe;
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            border-left: 4px solid #0ea5e9;
        }

        .highlight i {
            font-size: 24px;
            color: #0ea5e9;
        }

        .highlight span {
            color: #0369a1;
            font-size: 14px;
            font-weight: 500;
        }

        .python-uses {
            margin: 20px 0;
        }

        .use-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            color: #334155;
        }

        .use-item i {
            color: #10b981;
            font-size: 18px;
        }

        .note {
            background: linear-gradient(135deg, #f0f9ff 0%, #f0fdf4 100%);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            border: 1px dashed #6366f1;
        }

        .note i {
            color: #fbbf24;
            font-size: 24px;
        }

        .note span {
            color: #1e293b;
            font-weight: 500;
        }
        .contact-section {
            padding: 50px 0;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            position: relative;
            overflow: hidden;
        }

        .contact-section::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .contact-section::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }

        .contact-section .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 32px;
            position: relative;
            z-index: 1;
        }

        .contact-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .contact-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #2dd4bf 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-header p {
            font-size: 18px;
            color: #475569;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.7;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 60px;
        }

        .contact-info {
            background: white;
            border-radius: 40px;
            padding: 40px;
            box-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.1);
            transition: all 0.3s ease;
        }

        .contact-info:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 50px -20px rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .contact-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            color: #1e293b;
            font-family: 'Montserrat', sans-serif;
            position: relative;
            padding-bottom: 15px;
        }

        .contact-info h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 2px;
        }

        .info-items {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: linear-gradient(135deg, #e0e7ff 0%, #ede9fe 100%);
            transform: translateX(5px);
        }

        .info-icon {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.1);
        }

        .info-icon i {
            font-size: 28px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .info-text h4 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .info-text p {
            font-size: 16px;
            color: #475569;
            line-height: 1.5;
        }

        .info-text a {
            color: #475569;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .info-text a:hover {
            color: #6366f1;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .social-link {
            width: 50px;
            height: 50px;
            background: #f8fafc;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6366f1;
            font-size: 22px;
            transition: all 0.3s ease;
            text-decoration: none;
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .social-link:hover {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }
        .contact-qr {
            margin-top: 40px;
            padding: 10px 0;
            border-top: 1px solid rgba(99, 102, 241, 0.1);
            border-bottom: 1px solid rgba(99, 102, 241, 0.1);
            text-align: center;
        }

        .contact-qr h3 {
            font-size: 28px;
            font-weight: 700;
            font-weight: 700;
            color: #1e293b;
        }

        .contact-qr p {
            font-size: 20px;
            color: #475569;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .qr-box {
            margin-bottom: 20px;
        }

        .qr-box img {
            width: 180px;
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .qr-box img:hover {
            transform: scale(1.05);
        }

        .qr-btn {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: 0.3s ease;
        }

        .qr-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(99,102,241,0.3);
        }
        @media (max-width: 768px) {
            header {
                padding: 12px 16px;
            }

            .logo {
                padding: 5px 12px;
                gap: 8px;
            }

            .logo-icon {
                width: 36px;
                height: 36px;
                font-size: 18px;
            }

            .logo-text {
                font-size: 20px;
            }
            .mobile-menu-btn {
                display: block;
                padding: 10px 16px;
                font-size: 24px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: white;
                border: none;
                border-radius: 16px;
                cursor: pointer;
                box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
                transition: all 0.3s ease;
                z-index: 1001;
            }

            .mobile-menu-btn:active {
                transform: scale(0.95);
            }

            .nav-menu {
                display: none;
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(10px);
                flex-direction: column;
                padding: 20px;
                margin: 10px 16px;
                border-radius: 24px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(99, 102, 241, 0.2);
                z-index: 1000;
            }

            .nav-menu.show {
                display: flex;
            }

            .nav-button {
                width: 100%;
                padding: 16px !important;
                text-align: center;
                font-size: 16px !important;
                border-radius: 16px !important;
                margin: 4px 0;
            }

            .hero {
                padding: 40px 0;
            }

            .hero h1 {
                font-size: 32px !important;
                line-height: 1.3;
                padding: 0 16px;
            }

            .hero p {
                font-size: 16px;
                padding: 0 20px;
                margin-bottom: 30px;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
                gap: 12px;
                margin-bottom: 40px;
            }

            .hero-buttons .btn {
                width: 90%;
                padding: 14px 20px;
                font-size: 16px;
                justify-content: center;
            }

            .hero-features {
                grid-template-columns: 1fr !important;
                gap: 16px;
                padding: 0 16px;
            }

            .feature-card {
                padding: 24px 20px;
            }

            .info-blocks {
                padding: 40px 0;
            }

            .info-blocks .container {
                padding: 0 16px;
            }

            .blocks-grid {
                grid-template-columns: 1fr !important;
                gap: 20px;
            }

            .info-block {
                padding: 28px 20px;
            }

            .info-block h2 {
                font-size: 22px;
            }

            .block-stats {
                grid-template-columns: repeat(3, 1fr);
                padding: 15px 10px;
                gap: 8px;
            }

            .stat-number {
                font-size: 20px;
            }

            .stat-label {
                font-size: 12px;
            }

            .feature-list li {
                font-size: 14px;
            }

            .contact-section {
                padding: 40px 0;
            }

            .contact-section .container {
                padding: 0 16px;
            }

            .contact-header h2 {
                font-size: 32px;
            }

            .contact-grid {
                grid-template-columns: 1fr !important;
                gap: 24px;
            }

            .contact-info {
                padding: 24px 20px;
            }

            .contact-info h3 {
                font-size: 22px;
                margin-bottom: 20px;
            }

            .info-item {
                padding: 12px;
            }

            .info-icon {
                width: 48px;
                height: 48px;
            }

            .info-icon i {
                font-size: 22px;
            }

            .info-text h4 {
                font-size: 16px;
            }

            .info-text p {
                font-size: 14px;
            }

            .social-links {
                justify-content: center;
                flex-wrap: wrap;
                gap: 10px;
            }

            .social-link {
                width: 44px;
                height: 44px;
                font-size: 20px;
            }

            .contact-qr {
                padding: 24px 20px;
            }

            .contact-qr h3 {
                font-size: 22px;
            }

            .contact-qr p {
                font-size: 16px;
            }

            .qr-box img {
                width: 150px;
            }

            .qr-btn {
                padding: 12px 24px;
                font-size: 15px;
            }
            .stats-highlight-grid {
                grid-template-columns: 1fr !important;
                gap: 12px;
            }

            .stats-highlight-item {
                padding: 16px;
            }

            .stats-highlight-number {
                font-size: 28px;
            }
        }
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 28px !important;
            }

            .hero-buttons .btn {
                width: 100%;
                font-size: 15px;
                padding: 12px 16px;
            }

            .feature-card h3 {
                font-size: 18px;
            }

            .feature-card p {
                font-size: 14px;
            }

            .info-block h2 {
                font-size: 20px;
            }

            .block-stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 5px;
                padding: 10px 5px;
            }

            .stat-number {
                font-size: 18px;
            }

            .stat-label {
                font-size: 11px;
            }

            .contact-header h2 {
                font-size: 28px;
            }

            .info-item {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }

            .social-link {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .qr-box img {
                width: 120px;
            }

            .mobile-menu-btn {
                padding: 8px 14px;
                font-size: 20px;
            }
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            header {
                padding: 15px 25px;
            }

            .nav-menu {
                gap: 0.3rem;
            }

            .nav-button {
                padding: 10px 20px !important;
                font-size: 14px !important;
            }

            .hero h1 {
                font-size: 42px;
            }

            .hero-features {
                gap: 20px;
            }

            .feature-card {
                padding: 25px 20px;
            }

            .blocks-grid {
                gap: 20px;
            }

            .info-block {
                padding: 30px 25px;
            }

            .contact-grid {
                gap: 30px;
            }
        }
        @media (max-height: 500px) and (orientation: landscape) {
            .hero {
                padding: 30px 0;
            }

            .hero-features {
                grid-template-columns: repeat(3, 1fr);
            }

            .feature-card {
                padding: 20px 15px;
            }

            .nav-menu.show {
                max-height: 80vh;
                overflow-y: auto;
            }
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

        .nav-container {
            display: flex;
            align-items: center;
            gap: 2rem;
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
        }

        @media (max-width: 480px) {
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
    </style>
</head>
<body>
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <header>
        <div class="logo">
            <div class="logo-icon">📚</div>
            <span class="logo-text">ZhasCoder</span>
        </div>

        <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

        <div class="nav-menu" id="navMenu">
            <span class="nav-button" data-section="about" id="about-btn">Біз туралы</span>
            <span class="nav-button active" data-section="home" id="main-btn">Басты бет</span>
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

    <section class="hero" id="home">
        <div class="container">
            <h1>Компьютерлік ойлауды<br>Python арқылы үйреніңіз</h1>
            <p>Нөлден бастап бағдарламалауды меңгеріңіз. Интерактивті тапсырмалар, автоматты тексеру және көмек жүйесі сізбен бірге!</p>
            <div class="hero-buttons">
                <a href="topics.php" class="btn btn-success">
                    <i class="fas fa-rocket"></i> Сабақты бастау
                </a>
            </div>

            <div class="hero-features">
                <div class="feature-card">
                    <h3><i class="fas fa-brain"></i> Компьютерлік ойлау</h3>
                    <p>Мәселелерді талдау, алгоритмдеу және шешім қабылдау дағдыларын дамытыңыз.</p>
                </div>
                <div class="feature-card">
                    <h3><i class="fas fa-python"></i> Python тілі</h3>
                    <p>Әлемдегі ең танымал бағдарламалау тілін нөлден бастап үйреніңіз.</p>
                </div>
                <div class="feature-card">
                    <h3><i class="fas fa-gamepad"></i> Геймификация</h3>
                    <p>Ұпай жинаңыз, рейтингте көтеріліңіз, жетістіктерге жетіңіз!</p>
                </div>
            </div>
        </div>
    </section>

    <section class="info-blocks" id="about">
        <div class="container">
            <div class="blocks-grid">
                <div class="info-block">
                    <div class="block-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h2>Платформаның мақсаты</h2>
                    <p>Компьютерлік ойлау дағдыларын дамыту арқылы бағдарламалауды бастаушыларға түсінікті әрі қолжетімді ету. Интерактивті тапсырмалар, көмек жүйесі және практикалық жобалар арқылы әрбір оқушы өз қарқынымен үйрене алады.</p>
                    <div class="block-stats">
                        <div class="stat">
                            <span class="stat-number">15+</span>
                            <span class="stat-label">Тақырып</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">100+</span>
                            <span class="stat-label">Тапсырма</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">10</span>
                            <span class="stat-label">Mini-жоба</span>
                        </div>
                    </div>
                    <div class="highlight stats-note">
                        <i class="fas fa-chart-line"></i>
                        <span><strong>Тиімді оқыту жүйесі</strong> • Компьютерлік ойлауды дамыту • Python негіздерін жеңіл меңгеру</span>
                    </div>
                </div>

                <div class="info-block" >
                    <div class="block-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h2>🧠 Компьютерлік ойлау</h2>
                    <p>Бұл – күрделі мәселелерді шешудің әмбебап тәсілі. 4 негізгі кезеңнен тұрады:</p>
                    <ul class="feature-list">
                        <li><strong>Ыдырату</strong> – үлкен мәселені кіші бөліктерге бөлу</li>
                        <li><strong>Заңдылықтар</strong> – ұқсастықтар мен қайталануларды табу</li>
                        <li><strong>Дерексіздендіру</strong> – маңызды ақпаратты бөліп алу</li>
                        <li><strong>Алгоритм</strong> – шешімге жетудің қадамдық жоспары</li>
                    </ul>
                    <div class="highlight">
                        <i class="fas fa-lightbulb"></i>
                        <span>Кез келген салада қолданылады: бағдарламалау, математика, физика, тіпті күнделікті өмірде!</span>
                    </div>
                </div>

                <div class="info-block">
                    <div class="block-icon">
                        <i class="fab fa-python"></i>
                    </div>
                    <h2>🐍 Python не үшін керек?</h2>
                    <p>Python – қарапайым әрі қуатты бағдарламалау тілі. Оны үйрену арқылы сіз:</p>
                    <div class="python-uses">
                        <div class="use-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Алгоритмдерді тез тексеруге</span>
                        </div>
                        <div class="use-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Деректерді талдауға (Data Science)</span>
                        </div>
                        <div class="use-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Жасанды интеллект құруға</span>
                        </div>
                        <div class="use-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Веб-сайттар жасауға (Django, Flask)</span>
                        </div>
                        <div class="use-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Ойындар мен қосымшаларға</span>
                        </div>
                    </div>
                    <div class="note">
                        <i class="fas fa-star"></i>
                        <span>Әлемдегі ең танымал тілдердің бірі!</span>
                    </div>
                </div>
            </div>
        </div>
    </section>


        <section class="contact-section" id="contact">
            <div class="container">
                <div class="contact-header">
                    <h2>Бізбен байланысыңыз</h2>
                </div>
                <div class="contact-grid">
                    <div class="contact-info">
                        <h3>Байланыс мәліметтері</h3>
                        <div class="info-items">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-text">
                                    <h4>Email</h4>
                                    <p><a href="mailto:zhascoderpython@gmail.com">zhascoderpython@gmail.com</a></p>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <div class="info-text">
                                    <h4>Телефон</h4>
                                    <p><a href="tel:+7(702) 078 1252">+7(702) 078 1252</a></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="social-links">
                            <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-telegram"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                        <div class="contact-qr">
                            <h3>Пікір қалдыру</h3>
                            <p>QR кодты сканерлеп, Google форма арқылы пікір қалдырыңыз.</p>

                            <div class="qr-box">
                                <img src="http://qrcoder.ru/code/?https%3A%2F%2Fdocs.google.com%2Fforms%2Fd%2Fe%2F1FAIpQLSdpMVUsGAcYjEP8XwmP9mM1NfHbECMxtJlMkkpJTNOzYDIstw%2Fviewform%3Fusp%3Ddialog&4&0" alt="QR Code">
                            </div>

                            <a href="https://docs.google.com/forms/d/e/1FAIpQLSdpMVUsGAcYjEP8XwmP9mM1NfHbECMxtJlMkkpJTNOzYDIstw/viewform?usp=dialog"
                            target="_blank"
                            class="qr-btn">
                                Формаға өту
                            </a>
                        </div>
                </div>
            </div>
        </section>


    <script>
        const mobileBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.getElementById('navMenu');

        mobileBtn.addEventListener('click', () => {
            navMenu.classList.toggle('show');
        });
            document.querySelectorAll('.nav-button').forEach(button => {
                button.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        navMenu.classList.remove('show');
                    }
                });
            });
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                const isClickInsideMenu = navMenu.contains(event.target);
                const isClickOnMenuBtn = mobileBtn.contains(event.target);
                
                if (!isClickInsideMenu && !isClickOnMenuBtn && navMenu.classList.contains('show')) {
                    navMenu.classList.remove('show');
                }
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                navMenu.classList.remove('show');
            }
        });
        const avatarBtn = document.getElementById("avatarBtn");
        const avatarMenu = document.getElementById("avatarMenu");

        if (avatarBtn && avatarMenu) {
            function toggleAvatarMenu() {
                const isOpen = avatarMenu.classList.contains("open");
                if (isOpen) {
                    avatarMenu.classList.remove("open");
                    avatarBtn.setAttribute("aria-expanded", "false");
                    avatarMenu.setAttribute("aria-hidden", "true");
                } else {
                    avatarMenu.classList.add("open");
                    avatarBtn.setAttribute("aria-expanded", "true");
                    avatarMenu.setAttribute("aria-hidden", "false");
                }
            }

            avatarBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                toggleAvatarMenu();
            });

            document.addEventListener("click", () => {
                avatarMenu.classList.remove("open");
                avatarBtn.setAttribute("aria-expanded", "false");
                avatarMenu.setAttribute("aria-hidden", "true");
            });

            avatarMenu.addEventListener("click", (e) => {
                if (e.target.closest('.avatar-item')) {
                    avatarMenu.classList.remove("open");
                    avatarBtn.setAttribute("aria-expanded", "false");
                    avatarMenu.setAttribute("aria-hidden", "true");
                }
            });
        }

        function scrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (section) {
                section.scrollIntoView({ 
                    behavior: "smooth",
                    block: "start"
                });
                navMenu.classList.remove('show');
            }
        }

        function updateActiveButtonOnScroll() {
            const sections = [
                { id: 'home', buttonId: 'main-btn' },
                { id: 'about', buttonId: 'about-btn' },
                { id: 'contact', buttonId: 'contact-btn' }
            ];

            let currentSection = '';
            const scrollPosition = window.scrollY + 150; 

            sections.forEach(section => {
                const element = document.getElementById(section.id);
                if (element) {
                    const offsetTop = element.offsetTop;
                    const offsetBottom = offsetTop + element.offsetHeight;

                    if (scrollPosition >= offsetTop && scrollPosition < offsetBottom) {
                        currentSection = section.id;
                    }
                }
            });

            if (currentSection) {
                document.querySelectorAll('.nav-button').forEach(btn => {
                    btn.classList.remove('active');
                });

                sections.forEach(section => {
                    if (section.id === currentSection) {
                        const activeButton = document.getElementById(section.buttonId);
                        if (activeButton) {
                            activeButton.classList.add('active');
                        }
                    }
                });
            }
        }

        window.addEventListener('scroll', updateActiveButtonOnScroll);

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nav-button').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const section = this.getAttribute('data-section');
                    const href = this.getAttribute('data-href');
                    
                    if (section) {
                        scrollToSection(section);
                        document.querySelectorAll('.nav-button').forEach(btn => {
                            btn.classList.remove('active');
                        });
                        this.classList.add('active');
                        
                    } else if (href) {
                        window.location.href = href;
                    }
                });
            });

            setTimeout(() => {
                updateActiveButtonOnScroll();
            }, 100);
        });

        console.log("Home ID бар ма:", document.getElementById("home"));
        console.log("About ID бар ма:", document.getElementById("about"));
        console.log("Contact ID бар ма:", document.getElementById("contact"));
    </script>
</body>
</html>