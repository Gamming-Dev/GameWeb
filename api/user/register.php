<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $referral_code = sanitize($_POST['referral_code'] ?? '');
    
    // Validações
    if (strlen($username) < 3 || strlen($username) > 20) {
        $error = 'Usuário deve ter entre 3 e 20 caracteres';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Usuário só pode conter letras, números e underline';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } elseif (strlen($password) < 6) {
        $error = 'Senha deve ter no mínimo 6 caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'Senhas não conferem';
    } else {
        // Verifica se usuário/email existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Usuário ou email já cadastrado';
        } else {
            // Gera código de referral único
            $my_referral = generateReferralCode();
            
            // Busca quem indicou (se houver)
            $referred_by = null;
            if ($referral_code) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->execute([$referral_code]);
                $referrer = $stmt->fetch();
                if ($referrer) {
                    $referred_by = $referrer['id'];
                }
            }
            
            // Cria usuário
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users 
                (username, email, password_hash, referral_code, referred_by, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$username, $email, $password_hash, $my_referral, $referred_by])) {
                $success = 'Conta criada com sucesso! Redirecionando...';
                
                // Auto login
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                
                header("Refresh: 2; URL=dashboard.php");
            } else {
                $error = 'Erro ao criar conta. Tente novamente.';
            }
        }
    }
}

function generateReferralCode() {
    return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - MinerCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        /* Mesmo CSS do login com adições */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --core-black: #0a0a0f;
            --core-dark: #12121a;
            --core-blue: #00d4ff;
            --core-blue-glow: rgba(0, 212, 255, 0.3);
            --core-orange: #ff6b35;
            --core-green: #00ff88;
            --core-red: #ff4757;
            --core-text: #e0e0e0;
            --core-text-dim: #8892b0;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            padding: 40px 20px;
        }

        .bg-grid {
            position: fixed;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 212, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            top: 0;
            left: 0;
        }

        .auth-container {
            width: 100%;
            max-width: 500px;
            z-index: 1;
        }

        .auth-box {
            background: var(--core-dark);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        .logo-center {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-dark));
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            margin-bottom: 15px;
            box-shadow: 0 0 30px var(--core-blue-glow);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            text-align: center;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff, var(--core-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            text-align: center;
            color: var(--core-text-dim);
            margin-bottom: 30px;
            font-size: 16px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: var(--core-text);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 15, 0.8);
            border: 2px solid rgba(0, 212, 255, 0.2);
            border-radius: 10px;
            color: var(--core-text);
            font-family: 'Rajdhani', sans-serif;
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--core-blue);
            box-shadow: 0 0 15px var(--core-blue-glow);
        }

        input::placeholder {
            color: var(--core-text-dim);
        }

        .referral-box {
            background: rgba(0, 212, 255, 0.05);
            border: 1px dashed rgba(0, 212, 255, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .referral-box label {
            color: var(--core-blue);
            font-size: 12px;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--core-blue), #0099cc);
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
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px var(--core-blue-glow);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error {
            background: rgba(255, 71, 87, 0.1);
            border: 1px solid var(--core-red);
            color: var(--core-red);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid var(--core-green);
            color: var(--core-green);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .links {
            text-align: center;
            margin-top: 25px;
            color: var(--core-text-dim);
            font-size: 15px;
        }

        .links a {
            color: var(--core-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .terms {
            font-size: 13px;
            color: var(--core-text-dim);
            text-align: center;
            margin-top: 20px;
            line-height: 1.6;
        }

        .terms a {
            color: var(--core-blue);
        }

        @media (max-width: 480px) {
            .auth-box { padding: 25px; }
            .form-row { grid-template-columns: 1fr; }
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
                <p class="subtitle">Crie sua conta gratuita</p>
            </div>

            <?php if ($error): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Usuário</label>
                        <input type="text" name="username" required 
                               placeholder="miner_pro" maxlength="20"
                               value="<?= $_POST['username'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required 
                               placeholder="voce@email.com"
                               value="<?= $_POST['email'] ?? '' ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Senha</label>
                        <input type="password" name="password" required 
                               placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirmar Senha</label>
                        <input type="password" name="confirm_password" required 
                               placeholder="Repita a senha">
                    </div>
                </div>

                <div class="referral-box">
                    <label>Código de Indicação (opcional)</label>
                    <input type="text" name="referral_code" 
                           placeholder="Código do amigo"
                           value="<?= $_GET['ref'] ?? '' ?>"
                           style="background: rgba(0,0,0,0.3);">
                </div>

                <button type="submit" class="btn" id="submitBtn">
                    Criar Conta →
                </button>
            </form>

            <div class="links">
                <p>Já tem uma conta? <a href="login.php">Entrar</a></p>
                <p style="margin-top: 8px;"><a href="index.php">← Voltar para home</a></p>
            </div>

            <p class="terms">
                Ao criar uma conta, você concorda com nossos 
                <a href="#">Termos de Serviço</a> e 
                <a href="#">Política de Privacidade</a>.
            </p>
        </div>
    </div>

    <script>
        // Validação frontend básica
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Criando conta...';
        });
    </script>
</body>
</html>