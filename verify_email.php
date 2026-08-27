<?php
session_start();

header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'algolab';

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Қосылу қатесі: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$verification_code = $_GET['code'] ?? '';

if (empty($verification_code)) {
    $message = "Жарамсыз растау сілтемесі.";
    $type = "error";
    $user_email = "";
} else {
    $stmt = $conn->prepare("SELECT id, email, is_verified, created_at FROM users WHERE verification_code = ?");
    $stmt->bind_param("s", $verification_code);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 0) {
        $message = "Жарамсыз немесе мерзімі өткен растау сілтемесі.";
        $type = "error";
        $user_email = "";
    } else {
        $stmt->bind_result($user_id, $user_email, $is_verified, $created_at);
        $stmt->fetch();
        
        $created_timestamp = strtotime($created_at);
        $current_timestamp = time();
        $hours_diff = ($current_timestamp - $created_timestamp) / 3600;
        
        if ($hours_diff > 24) {
            $message = "Растау кодының мерзімі өткен. Қайта тіркеліңіз.";
            $type = "error";
            
            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_verified = 0");
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        } elseif ($is_verified == 1) {
            $message = "Email бұрын расталған. Жүйеге кіре аласыз.";
            $type = "info";
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            
            if ($update_stmt->execute()) {
                $message = "Email сәтті расталды! Енді жүйеге кіре аласыз.";
                $type = "success";
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $user_email;
                $_SESSION['just_verified'] = true;
            } else {
                $message = "Растау сәтсіз аяқталды. Қайталап көріңіз.";
                $type = "error";
            }
            $update_stmt->close();
        }
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email растау - ZhasCoder</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --text-light: #475569;
            --border: #e2e8f0;
            --success: #10b981;
            --success-light: #d1fae5;
            --error: #ef4444;
            --error-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f4ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
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
                radial-gradient(circle at 30% 70%, rgba(45, 212, 191, 0.05) 0%, transparent 30%);
            pointer-events: none;
            z-index: -1;
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
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-30px, 30px) rotate(240deg); }
        }

        .container {
            max-width: 520px;
            width: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 48px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px -12px rgba(99, 102, 241, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.5);
            text-align: center;
            position: relative;
            z-index: 10;
            transition: transform 0.3s ease;
        }

        .container:hover {
            transform: translateY(-5px);
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 8px 24px;
            background: linear-gradient(135deg, var(--primary-pastel) 0%, var(--secondary-pastel) 100%);
            border-radius: 50px;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Montserrat', sans-serif;
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 48px;
            transition: all 0.3s ease;
        }

        .success .icon-wrapper { 
            background: var(--success-light); 
            color: var(--success);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }
        .error .icon-wrapper { 
            background: var(--error-light); 
            color: var(--error);
            box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.3);
        }
        .info .icon-wrapper { 
            background: var(--info-light); 
            color: var(--info);
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
        }
        .warning .icon-wrapper { 
            background: var(--warning-light); 
            color: var(--warning);
            box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.3);
        }

        h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .message-box {
            background: var(--white-smoke);
            border-radius: 24px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid;
        }

        .success .message-box { border-left-color: var(--success); }
        .error .message-box { border-left-color: var(--error); }
        .info .message-box { border-left-color: var(--info); }
        .warning .message-box { border-left-color: var(--warning); }

        .message-box p {
            color: var(--text-dark);
            font-size: 18px;
            line-height: 1.7;
            margin-bottom: 15px;
        }

        .email-highlight {
            background: white;
            padding: 12px 20px;
            border-radius: 50px;
            display: inline-block;
            font-weight: 600;
            color: var(--primary);
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px -5px rgba(99, 102, 241, 0.5);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        .btn i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .btn:hover i {
            transform: translateX(5px);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px dashed var(--border);
        }

        .feature-item {
            text-align: center;
        }

        .feature-item i {
            font-size: 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .feature-item span {
            display: block;
            font-size: 13px;
            color: var(--text-light);
        }

        @media (max-width: 480px) {
            .container {
                padding: 40px 25px;
                border-radius: 32px;
            }

            h1 {
                font-size: 28px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .features {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <div class="container <?php echo $type; ?>">
        <div class="logo">
            <div class="logo-icon">📚</div>
            <span class="logo-text">ZhasCoder</span>
        </div>

        <div class="icon-wrapper">
            <?php if ($type == 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php elseif ($type == 'error'): ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php elseif ($type == 'info'): ?>
                <i class="fas fa-info-circle"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle"></i>
            <?php endif; ?>
        </div>

        <h1>
            <?php 
            if ($type == 'success') echo "Сәтті расталды!";
            elseif ($type == 'error') echo "Қате орын алды";
            elseif ($type == 'info') echo "Ақпарат";
            else echo "Ескерту";
            ?>
        </h1>

        <div class="message-box">
            <p><?php echo $message; ?></p>
            <?php if (!empty($user_email) && $type != 'error'): ?>
                <div class="email-highlight">
                    <i class="fas fa-envelope" style="margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($user_email); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="btn-group">
            <?php if ($type == 'success' || $type == 'info'): ?>
                <a href="login.html" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Кіру
                </a>
            <?php else: ?>
                <a href="register.html" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Қайта тіркелу
                </a>
                <a href="index1.html" class="btn btn-outline">
                    <i class="fas fa-home"></i> Басты бет
                </a>
            <?php endif; ?>
        </div>

        <div class="features">
            <div class="feature-item">
                <i class="fas fa-brain"></i>
                <span>Компьютерлік ойлау</span>
            </div>
            <div class="feature-item">
                <i class="fab fa-python"></i>
                <span>Python бағдарламалау</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-tasks"></i>
                <span>100+ тапсырма</span>
            </div>
        </div>
    </div>
</body>
</html>