<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

checkAuth();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Busca dados atualizados
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Conta estatísticas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_miners WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_miners = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM mining_history WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_mining_ops = $stmt->fetchColumn();

// Atualização de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = sanitize($_POST['email']);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user_id]);
            $message = 'Perfil atualizado com sucesso!';
            $user['email'] = $email;
        } else {
            $error = 'Email inválido';
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (!password_verify($current, $user['password_hash'])) {
            $error = 'Senha atual incorreta';
        } elseif (strlen($new) < 6) {
            $error = 'Nova senha deve ter no mínimo 6 caracteres';
        } elseif ($new !== $confirm) {
            $error = 'Senhas não conferem';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $message = 'Senha alterada com sucesso!';
        }
    }
}

// Calcula tempo na plataforma
$days_active = floor((time() - strtotime($user['created_at'])) / 86400);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - MinerCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        /* Mesmas variáveis CSS do dashboard */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --core-black: #0a0a0f;
            --core-dark: #12121a;
            --core-darker: #0d0d12;
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
            color: var(--core-text);
            min-height: 100vh;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar (mesmo do dashboard) */
        .sidebar {
            background: var(--core-dark);
            border-right: 1px solid rgba(0, 212, 255, 0.1);
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            width: 250px;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            text-decoration: none;
        }

        .sidebar-logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .sidebar-logo-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--core-blue);
        }

        .nav-section { margin-bottom: 30px; }
        
        .nav-title {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--core-text-dim);
            letter-spacing: 1px;
            margin-bottom: 15px;
            padding-left: 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: var(--core-text-dim);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(0, 212, 255, 0.1);
            color: var(--core-blue);
        }

        .nav-item.active {
            border-left: 3px solid var(--core-blue);
        }

        .user-section {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 212, 255, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--core-orange), var(--core-red));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }

        .user-name { font-weight: 700; }
        
        .user-status {
            font-size: 12px;
            color: var(--core-green);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
            max-width: 900px;
        }

        .header {
            margin-bottom: 30px;
        }

        .page-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .breadcrumb {
            color: var(--core-text-dim);
            font-size: 14px;
        }

        .breadcrumb a {
            color: var(--core-blue);
            text-decoration: none;
        }

        /* Cards */
        .card {
            background: var(--core-dark);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: rgba(0, 212, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .card-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
        }

        .card-subtitle {
            color: var(--core-text-dim);
            font-size: 14px;
            margin-top: 5px;
        }

        /* Profile Header */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-orange));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 900;
            border: 4px solid var(--core-dark);
            box-shadow: 0 0 30px var(--core-blue-glow);
        }

        .profile-info h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            color: var(--core-text-dim);
            font-size: 14px;
        }

        .profile-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: var(--core-darker);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(0, 212, 255, 0.1);
        }

        .stat-box-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--core-blue);
            margin-bottom: 5px;
        }

        .stat-box-label {
            font-size: 12px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--core-text);
            font-weight: 600;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 14px 18px;
            background: var(--core-darker);
            border: 2px solid rgba(0, 212, 255, 0.2);
            border-radius: 10px;
            color: var(--core-text);
            font-family: 'Rajdhani', sans-serif;
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--core-blue);
            box-shadow: 0 0 15px var(--core-blue-glow);
        }

        input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .input-hint {
            font-size: 13px;
            color: var(--core-text-dim);
            margin-top: 5px;
        }

        .btn {
            padding: 14px 30px;
            background: linear-gradient(135deg, var(--core-blue), #0099cc);
            border: none;
            border-radius: 10px;
            color: var(--core-black);
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px var(--core-blue-glow);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid var(--core-blue);
            color: var(--core-blue);
        }

        .btn-secondary:hover {
            background: var(--core-blue);
            color: var(--core-black);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--core-red), #cc3742);
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid var(--core-green);
            color: var(--core-green);
        }

        .alert-error {
            background: rgba(255, 71, 87, 0.1);
            border: 1px solid var(--core-red);
            color: var(--core-red);
        }

        /* Referral Section */
        .referral-box {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(255, 107, 53, 0.1));
            border: 2px dashed var(--core-blue);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }

        .referral-code {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 900;
            color: var(--core-blue);
            letter-spacing: 3px;
            margin: 15px 0;
            padding: 15px;
            background: var(--core-darker);
            border-radius: 10px;
            border: 1px solid rgba(0, 212, 255, 0.3);
        }

        .referral-link {
            font-size: 14px;
            color: var(--core-text-dim);
            margin-bottom: 15px;
            word-break: break-all;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="dashboard.php" class="sidebar-logo">
                <div class="sidebar-logo-icon">⛏️</div>
                <span class="sidebar-logo-text">MINERCORE</span>
            </a>

            <div class="nav-section">
                <div class="nav-title">Menu Principal</div>
                <a href="dashboard.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="mining-room.php" class="nav-item">
                    <span>⛏️</span> Mineração
                </a>
                <a href="games.php" class="nav-item">
                    <span>🎮</span> Games
                </a>
                <a href="market.php" class="nav-item">
                    <span>🛒</span> Mercado
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Financeiro</div>
                <a href="wallet.php" class="nav-item">
                    <span>💰</span> Carteira
                </a>
                <a href="referrals.php" class="nav-item">
                    <span>🤝</span> Indicações
                </a>
                <a href="history.php" class="nav-item">
                    <span>📜</span> Histórico
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Conta</div>
                <a href="profile.php" class="nav-item active">
                    <span>👤</span> Perfil
                </a>
                <a href="settings.php" class="nav-item">
                    <span>⚙️</span> Configurações
                </a>
                <a href="logout.php" class="nav-item">
                    <span>🚪</span> Sair
                </a>
            </div>

            <div class="user-section">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 2)) ?></div>
                    <div>
                        <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                        <div class="user-status">Online</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <div class="header">
                <h1 class="page-title">Meu Perfil</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Dashboard</a> / Perfil
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <?= strtoupper(substr($user['username'], 0, 2)) ?>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($user['username']) ?></h2>
                    <div class="profile-meta">
                        <span>📅 Membro há <?= $days_active ?> dias</span>
                        <span>📧 <?= htmlspecialchars($user['email']) ?></span>
                        <span>🏆 Nível <?= floor($days_active / 30) + 1 ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-box-value"><?= $total_miners ?></div>
                    <div class="stat-box-label">Miners</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-value"><?= $total_mining_ops ?></div>
                    <div class="stat-box-label">Minerações</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-value"><?= number_format($user['total_mined'], 4) ?></div>
                    <div class="stat-box-label">BTC Total</div>
                </div>
            </div>

            <!-- Informações Básicas -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">👤</div>
                    <div>
                        <div class="card-title">Informações da Conta</div>
                        <div class="card-subtitle">Atualize seus dados pessoais</div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Usuário</label>
                            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <div class="input-hint">Nome de usuário não pode ser alterado</div>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Data de Cadastro</label>
                            <input type="text" value="<?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Último Acesso</label>
                            <input type="text" value="<?= $user['last_active'] ? date('d/m/Y H:i', strtotime($user['last_active'])) : 'Nunca' ?>" disabled>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn">Salvar Alterações</button>
                </form>
            </div>

            <!-- Código de Indicação -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">🤝</div>
                    <div>
                        <div class="card-title">Programa de Indicação</div>
                        <div class="card-subtitle">Convide amigos e ganhe comissões</div>
                    </div>
                </div>
                
                <div class="referral-box">
                    <p style="margin-bottom: 10px;">Seu Código de Indicação:</p>
                    <div class="referral-code" id="refCode"><?= $user['referral_code'] ?></div>
                    <div class="referral-link">
                        <?= 'https://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $user['referral_code'] ?>
                    </div>
                    <button class="btn btn-secondary" onclick="copyReferral()">Copiar Link</button>
                </div>
            </div>

            <!-- Segurança -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">🔒</div>
                    <div>
                        <div class="card-title">Segurança</div>
                        <div class="card-subtitle">Altere sua senha periodicamente</div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Senha Atual</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label>Nova Senha</label>
                            <input type="password" name="new_password" required minlength="6">
                        </div>
                        <div class="form-group full">
                            <label>Confirmar Nova Senha</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn">Alterar Senha</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        function copyReferral() {
            const code = document.getElementById('refCode').textContent;
            const link = '<?= 'https://' . $_SERVER['HTTP_HOST'] ?>/register.php?ref=' + code;
            
            navigator.clipboard.writeText(link).then(() => {
                alert('Link copiado para a área de transferência!');
            });
        }
    </script>
</body>
</html>