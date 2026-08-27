<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; 
require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        $_SESSION['message'] = "❌ Бұл email тіркелмеген.";
        $_SESSION['message_type'] = "error";
    } else {
        $stmt->close();

        $token  = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $upd = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE email=?");
        $upd->bind_param("sss", $token, $expiry, $email);
        $upd->execute();
        $upd->close();

        $reset_link = "http://localhost/algolab/reset_password.php?token=" . $token;

        $mail = new PHPMailer(true);
        
        $mail->CharSet = 'UTF-8';

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username   = 'zhascoderpython@gmail.com';
            $mail->Password   = 'vqifzwsxkwaqfuha'; 
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('zhascoderpython@gmail.com', 'ZhasCoder');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Құпия сөзді қалпына келтіру';
            
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Poppins', sans-serif; background: #f8fafc; padding: 40px; }
                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 48px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(99,102,241,0.25); }
                    .header { text-align: center; margin-bottom: 30px; }
                    .logo-icon { width: 60px; height: 60px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 30px; }
                    h1 { color: #1e293b; font-size: 28px; margin-bottom: 15px; }
                    .btn { display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 16px 40px; text-decoration: none; border-radius: 50px; font-weight: 600; margin: 20px 0; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 2px dashed #e2e8f0; text-align: center; color: #64748b; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='logo-icon'>📚</div>
                        <h1>ZhasCoder</h1>
                    </div>
                    <p>Құпия сөзді қалпына келтіру үшін төмендегі батырманы басыңыз:</p>
                    <div style='text-align: center;'>
                        <a href='$reset_link' class='btn'>Құпия сөзді қалпына келтіру</a>
                    </div>
                    <p>Немесе мына сілтемені браузерге көшіріңіз:</p>
                    <p style='background: #f1f5f9; padding: 15px; border-radius: 12px; word-break: break-all;'>$reset_link</p>
                    <p>Бұл сілтеме 1 сағат бойы жарамды.</p>
                    <div class='footer'>
                        <p>© 2026 ZhasCoder. Барлық құқықтар қорғалған.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->AltBody = "Құпия сөзді қалпына келтіру сілтемесі: $reset_link\n\nБұл сілтеме 1 сағат бойы жарамды.";

            $mail->send();
            $_SESSION['message'] = "✅ Құпия сөзді қалпына келтіру сілтемесі email-ге жіберілді!";
            $_SESSION['message_type'] = "success";

        } catch (Exception $e) {
            $_SESSION['message'] = "❌ Email жіберу қатесі: " . $mail->ErrorInfo;
            $_SESSION['message_type'] = "error";
            
            echo "<pre>";
            echo "Exception: " . $e->getMessage();
            echo "</pre>";
            exit();
        }
    }

    header("Location: forgot_password.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Құпия сөзді қалпына келтіру - ZhasCoder</title>
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
            --error: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f4ff 100%);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
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
            display: flex;
            max-width: 1100px;
            width: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 48px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(99, 102, 241, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.5);
            min-height: 650px;
            position: relative;
            z-index: 10;
        }

        .brand-side {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .brand-side::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -30%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            font-size: 32px;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
            font-family: 'Montserrat', sans-serif;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-title {
            font-size: 38px;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            line-height: 1.2;
            font-family: 'Montserrat', sans-serif;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .brand-subtitle {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 40px;
            line-height: 1.7;
        }

        .features-list {
            list-style: none;
            margin-top: 40px;
        }

        .features-list li {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 25px;
            color: white;
            font-size: 17px;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 20px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .features-list li:hover {
            transform: translateX(10px);
            background: rgba(255, 255, 255, 0.15);
        }

        .features-list i {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }

        .brand-footer {
            position: relative;
            z-index: 2;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-side {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: white;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-subtitle {
            color: var(--text-light);
            font-size: 16px;
            line-height: 1.5;
        }

        .instruction-box {
            background: var(--primary-pastel);
            border-left: 4px solid var(--primary);
            padding: 20px;
            border-radius: 20px;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .instruction-box h4 {
            color: var(--primary-dark);
            margin-bottom: 12px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .instruction-box ul {
            margin-left: 25px;
            margin-top: 10px;
            color: var(--text-dark);
        }

        .instruction-box li {
            margin-bottom: 8px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
            font-family: 'Montserrat', sans-serif;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 18px;
        }

        .form-control {
            width: 100%;
            padding: 16px 18px 16px 52px;
            border: 2px solid var(--border);
            border-radius: 16px;
            background: var(--white-smoke);
            color: var(--text-dark);
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px var(--primary-pastel);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px -5px rgba(99, 102, 241, 0.5);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        .submit-btn i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .submit-btn:hover i {
            transform: translateX(5px);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 25px;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 15px;
        }

        .back-link:hover {
            color: var(--primary);
            transform: translateX(-5px);
        }

        .message {
            margin-bottom: 25px;
            padding: 18px;
            border-radius: 16px;
            font-weight: 500;
            text-align: center;
            font-size: 15px;
            animation: fadeIn 0.3s ease;
            border-left: 4px solid;
            display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.error {
            background-color: #fef2f2;
            color: var(--error);
            border-left-color: var(--error);
            display: block;
        }

        .message.success {
            background-color: #f0fdf4;
            color: var(--success);
            border-left-color: var(--success);
            display: block;
        }

        .auth-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px dashed var(--border);
        }

        @media (max-width: 992px) {
            .container {
                flex-direction: column;
                max-width: 550px;
            }
            
            .brand-side {
                padding: 50px 40px;
            }
            
            .form-side {
                padding: 50px 40px;
            }
            
            .brand-title {
                font-size: 32px;
            }
        }

        @media (max-width: 576px) {
            .brand-title {
                font-size: 28px;
            }
            
            .form-title {
                font-size: 28px;
            }
            
            .form-side {
                padding: 40px 30px;
            }
            
            .brand-side {
                padding: 40px 30px;
            }
            
            .features-list li {
                font-size: 15px;
                padding: 12px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <div class="container">
        <div class="brand-side">
            <div>
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="logo-text">ZhasCoder</div>
                </div>
                
                <div class="brand-content">
                    <h1 class="brand-title">Құпия сөзді қалпына келтіру</h1>
                    <p class="brand-subtitle">Email мекенжайыңызды енгізіңіз, біз сізге құпия сөзді қалпына келтіру сілтемесін жібереміз.</p>
                    
                    <ul class="features-list">
                        <li>
                            <i class="fas fa-shield-alt"></i>
                            <span>Қауіпсіз қалпына келтіру жүйесі</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>Сілтеме email-ге жіберіледі</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Сілтеме 1 сағат бойы жарамды</span>
                        </li>
                        <li>
                            <i class="fas fa-user-check"></i>
                            <span>Жылдам әрі оңай қалпына келтіру</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="brand-footer">
                <p>© 2026 ZhasCoder. Барлық құқықтар қорғалған.</p>
            </div>
        </div>

        <div class="form-side">
            <div class="form-header">
                <h2 class="form-title">Құпия сөзді ұмыттыңыз ба?</h2>
                <p class="form-subtitle">Email мекенжайыңызды енгізіңіз, біз сізге қалпына келтіру сілтемесін жібереміз.</p>
            </div>

            <div class="instruction-box">
                <h4><i class="fas fa-info-circle"></i> Қалай жұмыс істейді:</h4>
                <ul>
                    <li>Аккаунтыңызға тіркелген email-ді енгізіңіз</li>
                    <li>Email поштаңызды тексеріңіз</li>
                    <li>Сілтеме бойынша өтіп, жаңа құпия сөз орнатыңыз</li>
                    <li>Жаңа құпия сөзбен жүйеге кіріңіз</li>
                </ul>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="message <?php echo $_SESSION['message_type']; ?>">
                    <?php echo $_SESSION['message']; ?>
                </div>
                <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Email мекенжайыңыз</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="aidos@example.com" required>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Сілтеме жіберу
                </button>
            </form>
            
            <div class="auth-links">
                <p style="color: var(--text-light); font-size: 15px;">
                    Құпия сөз есіңізде ме? 
                    <a href="login.html" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i> Кіру
                    </a>
                </p>
            </div>
            
            <a href="index1.html" class="back-link">
                <i class="fas fa-arrow-left"></i> Басты бетке оралу
            </a>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>