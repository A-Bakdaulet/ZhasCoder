<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($token === '') {
    $_SESSION['message'] = "❌ Жарамсыз сілтеме (токен жоқ).";
    $_SESSION['message_type'] = "error";
    header("Location: forgot_password.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token=? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "❌ Жарамсыз немесе қолданылған токен.";
    $_SESSION['message_type'] = "error";
    header("Location: forgot_password.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

if ($user['reset_expires'] === null || strtotime($user['reset_expires']) < time()) {
    $_SESSION['message'] = "⏰ Токеннің мерзімі өткен. Жаңа сілтеме сұраңыз.";
    $_SESSION['message_type'] = "error";
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "❌ Құпия сөз кемінде 8 символ болуы керек.";
    } elseif ($password !== $confirm) {
        $error = "❌ Құпия сөздер сәйкес келмейді.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $upd = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?");
        $upd->bind_param("si", $hashed, $user['id']);
        $upd->execute();
        $upd->close();

        $_SESSION['message'] = "✅ Құпия сөз жаңартылды! Енді жүйеге кіре аласыз.";
        $_SESSION['message_type'] = "success";
        header("Location: login.html");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
            align-items: center;
            justify-content: center;
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
            position: relative;
            z-index: 10;
            transition: transform 0.3s ease;
        }

        .container:hover {
            transform: translateY(-5px);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Montserrat', sans-serif;
        }

        h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 30px;
            font-size: 16px;
        }

        .msg {
            padding: 16px 20px;
            border-radius: 20px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }

        .msg.error {
            background: #fef2f2;
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .msg.success {
            background: #f0fdf4;
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .msg i {
            font-size: 20px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 18px;
        }

        input {
            width: 100%;
            padding: 16px 18px 16px 52px;
            border: 2px solid var(--border);
            border-radius: 30px;
            background: var(--white-smoke);
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px var(--primary-pastel);
        }

        input::placeholder {
            color: #94a3b8;
        }

        .password-strength {
            margin-top: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .strength-bar {
            height: 4px;
            flex: 1;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }

        button {
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
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px -5px rgba(99, 102, 241, 0.5);
        }

        button i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        button:hover i {
            transform: translateX(5px);
        }

        .small {
            color: var(--text-light);
            font-size: 14px;
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px dashed var(--border);
        }

        .small a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .small a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
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

        @media (max-width: 480px) {
            .container {
                padding: 40px 25px;
                border-radius: 32px;
            }

            h2 {
                font-size: 28px;
            }

            input {
                padding: 14px 18px 14px 48px;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <div class="container">
        <div class="logo">
            <div class="logo-icon">📚</div>
            <div class="logo-text">ZhasCoder</div>
        </div>

        <h2>Құпия сөзді қалпына келтіру</h2>
        <div class="subtitle">Жаңа құпия сөз орнатыңыз</div>

        <?php if (!empty($error)): ?>
        <div class="msg error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="password">Жаңа құпия сөз</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Кемінде 8 символ" required>
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar">
                        <div class="strength-bar-fill"></div>
                    </div>
                    <span id="strengthText">Құпия сөз күші</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Құпия сөзді растау</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Қайта енгізіңіз" required>
                </div>
            </div>

            <button type="submit">
                <span>Жаңарту</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="small">
            <i class="fas fa-info-circle"></i> Егер сілтеменің мерзімі өткен болса, 
            <a href="forgot_password.php">жаңа сілтеме сұраңыз</a>.
        </div>

        <div style="text-align: center;">
            <a href="index1.html" class="back-link">
                <i class="fas fa-arrow-left"></i> Басты бетке оралу
            </a>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const strengthBar = document.querySelector('.strength-bar-fill');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            let strength = 0;
            let text = '';
            let color = '';
            
            if (val.length >= 8) strength++;
            if (val.match(/[a-z]+/)) strength++;
            if (val.match(/[A-Z]+/)) strength++;
            if (val.match(/[0-9]+/)) strength++;
            if (val.match(/[$@#&!]+/)) strength++;
            
            strength = Math.min(strength, 4);
            
            const strengthMap = {
                0: { text: 'Құпия сөз енгізіңіз', class: '', width: '0%' },
                1: { text: 'Әлсіз', class: '#ef4444', width: '25%' },
                2: { text: 'Орташа', class: '#f59e0b', width: '50%' },
                3: { text: 'Жақсы', class: '#3b82f6', width: '75%' },
                4: { text: 'Күшті', class: '#10b981', width: '100%' }
            };
            
            const level = strengthMap[strength] || strengthMap[0];
            strengthText.textContent = level.text;
            strengthBar.style.width = level.width;
            strengthBar.style.background = level.class;
        });
        const confirmInput = document.getElementById('confirm_password');
        const form = document.querySelector('form');

        form.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (password !== confirm) {
                e.preventDefault();
                alert('❌ Құпия сөздер сәйкес келмейді!');
            } else if (password.length < 8) {
                e.preventDefault();
                alert('❌ Құпия сөз кемінде 8 символ болуы керек!');
            }
        });
    </script>
</body>
</html>