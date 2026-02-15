<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'] ?? false;
        
        // Atualiza último login
        $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")->execute([$user['id']]);
        
        redirect('dashboard.php');
    } else {
        $error = 'Usuário ou senha incorretos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MinerCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
    /* FUNDO CAVERNA */
    --core-black: #1b0f14;
    --core-dark: #24161d;

    /* CRISTAL CIANO */
    --core-blue: #2de2e6;
    --core-blue-glow: rgba(45, 226, 230, 0.35);

    /* ÂMBAR DO MASCOTE */
    --core-orange: #ff9f1c;

    /* VERDE CRISTAL */
    --core-green: #3cffb3;

    /* ALERTA */
    --core-red: #ff4d4d;

    /* TEXTO */
    --core-text: #f5e6d3;
    --core-text-dim: #bfae9c;
}


        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .bg-grid {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 212, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            z-index: 1;
        }

        .auth-box {
            background: var(--core-dark);
            border: 1px solid rgba(45, 226, 230, 0.25);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        .logo-center {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-dark));
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 20px;
            box-shadow: 0 0 30px var(--core-blue-glow);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, var(--core-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            text-align: center;
            color: var(--core-text-dim);
            margin-bottom: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--core-text);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-wrapper {
            position: relative;
        }

        input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(27, 15, 20, 0.9);
            border: 2px solid rgba(45, 226, 230, 0.25);
            border-radius: 10px;
            color: var(--core-text);
            font-family: 'Rajdhani', sans-serif;
            font-size: 18px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--core-blue);
            box-shadow: 0 0 15px var(--core-blue-glow);
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--core-text-dim);
        }

        .btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-orange));
            border: none;
            border-radius: 10px;
            color: var(--core-black);
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px var(--core-blue-glow);
        }

        .error {
            background: rgba(255, 71, 87, 0.1);
            border: 1px solid var(--core-red);
            color: var(--core-red);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .links {
            text-align: center;
            margin-top: 30px;
            color: var(--core-text-dim);
        }

        .links a {
            color: var(--core-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: var(--core-text-dim);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(45, 226, 230, 0.25);
        }

        .divider span {
            padding: 0 15px;
        }

        @media (max-width: 480px) {
            .auth-box { padding: 30px; }
            h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    
    <div class="auth-container">
        <div class="auth-box">
            <div class="logo-center">
                <div class="logo-icon">⛏️</div>
                <h1>MINERCORE</h1>
                <p class="subtitle">Access Your Account</p>
            </div>

            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Username or Email</label>
                    <div class="input-wrapper">
                        <input type="text" name="username" required autofocus 
                               placeholder="your_username or email@example.com">
                    </div>
                </div>

                <div class="form-group">
                    <label>Passwword</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" required 
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn">Login</button>
            </form>

            <div class="divider"><span>or</span></div>

            <div class="links">
                <p>Don't Have an Account? <a href="register.php">Create Free Account</a></p>
                <p style="margin-top: 10px;"><a href="index.php">← Return to Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>