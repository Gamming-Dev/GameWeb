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

// Estatísticas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_miners WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_miners = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM mining_history WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_mining_ops = $stmt->fetchColumn();

// Conta referrals
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmt->execute([$user_id]);
$total_referrals = $stmt->fetchColumn();

// Atualização de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = sanitize($_POST['email']);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user_id]);
            $message = 'Profile updated successfully!';
            $user['email'] = $email;
        } else {
            $error = 'Invalid email address';
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $message = 'Password changed successfully!';
        }
    }
}

$days_active = max(1, floor((time() - strtotime($user['created_at'])) / 86400));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - MinerCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --core-black: #0a0a0f;
            --core-dark: #12121a;
            --core-darker: #0d0d12;
            --core-card: #16161f;
            --core-blue: #00d4ff;
            --core-blue-glow: rgba(0, 212, 255, 0.3);
            --core-blue-dark: #0099cc;
            --core-orange: #ff6b35;
            --core-green: #00ff88;
            --core-green-glow: rgba(0, 255, 136, 0.3);
            --core-yellow: #ffd700;
            --core-red: #ff4757;
            --core-text: #e0e0e0;
            --core-text-dim: #8892b0;
            --core-border: rgba(0, 212, 255, 0.15);
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            color: var(--core-text);
            min-height: 100vh;
            display: flex;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--core-dark);
            border-right: 1px solid var(--core-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
        }

        .sidebar-header {
            padding: 25px;
            border-bottom: 1px solid var(--core-border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 0 20px var(--core-blue-glow);
        }

        .logo-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--core-blue);
            letter-spacing: 1px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 15px;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 25px;
        }

        .nav-title {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--core-text-dim);
            letter-spacing: 1.5px;
            margin-bottom: 12px;
            padding-left: 15px;
            font-weight: 700;
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
            margin-bottom: 4px;
            font-weight: 500;
            font-size: 15px;
        }

        .nav-item:hover {
            background: rgba(0, 212, 255, 0.08);
            color: var(--core-text);
        }

        .nav-item.active {
            background: rgba(0, 212, 255, 0.15);
            color: var(--core-blue);
            border-left: 3px solid var(--core-blue);
        }

        .nav-icon {
            width: 24px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--core-border);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--core-orange), var(--core-red));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: white;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 700;
            font-size: 15px;
            color: var(--core-text);
        }

        .user-status {
            font-size: 12px;
            color: var(--core-green);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: var(--core-green);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--core-green);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            max-width: calc(100% - 260px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .breadcrumb {
            color: var(--core-text-dim);
            font-size: 14px;
        }

        .breadcrumb a {
            color: var(--core-blue);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* ALERTS */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* PROFILE HEADER CARD */
        .profile-header-card {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-orange));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 900;
            color: white;
            border: 4px solid var(--core-dark);
            box-shadow: 0 0 30px var(--core-blue-glow);
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #fff, var(--core-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--core-text-dim);
            font-size: 14px;
        }

        .meta-icon {
            font-size: 16px;
        }

        /* STATS ROW */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-box:hover {
            border-color: rgba(0, 212, 255, 0.4);
            transform: translateY(-2px);
        }

        .stat-box-icon {
            width: 48px;
            height: 48px;
            background: rgba(0, 212, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 12px;
        }

        .stat-box-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 28px;
            font-weight: 700;
            color: var(--core-blue);
            margin-bottom: 6px;
        }

        .stat-box-label {
            font-size: 12px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* CONTENT GRID */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        /* CARDS */
        .card {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 24px 24px 0;
            margin-bottom: 20px;
        }

        .card-title-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .card-icon {
            width: 44px;
            height: 44px;
            background: rgba(0, 212, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .card-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 700;
        }

        .card-subtitle {
            color: var(--core-text-dim);
            font-size: 14px;
            padding-left: 56px;
        }

        .card-body {
            padding: 0 24px 24px;
        }

        /* FORMS */
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            background: var(--core-darker);
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

        input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: rgba(0, 0, 0, 0.3);
        }

        .input-hint {
            font-size: 12px;
            color: var(--core-text-dim);
            margin-top: 6px;
        }

        .btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-blue-dark));
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

        /* REFERRAL CARD */
        .referral-card {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.08), rgba(255, 107, 53, 0.05));
            border: 2px dashed var(--core-blue);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }

        .referral-title {
            font-size: 14px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .referral-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 32px;
            font-weight: 700;
            color: var(--core-blue);
            letter-spacing: 4px;
            margin-bottom: 16px;
            padding: 16px;
            background: var(--core-darker);
            border-radius: 12px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            word-break: break-all;
        }

        .referral-link {
            font-size: 13px;
            color: var(--core-text-dim);
            margin-bottom: 16px;
            word-break: break-all;
            padding: 0 10px;
        }

        .referral-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 212, 255, 0.2);
        }

        .referral-stat {
            text-align: center;
        }

        .referral-stat-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 24px;
            font-weight: 700;
            color: var(--core-green);
        }

        .referral-stat-label {
            font-size: 12px;
            color: var(--core-text-dim);
            text-transform: uppercase;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .content-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; max-width: 100%; }
            .profile-header-card { flex-direction: column; text-align: center; }
            .profile-meta { justify-content: center; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .stats-row { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 24px; }
            .profile-name { font-size: 24px; }
            .referral-code { font-size: 24px; }
        }

        /* MOBILE MENU */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: var(--core-card);
            border: 1px solid var(--core-border);
            color: var(--core-blue);
            padding: 12px;
            border-radius: 10px;
            font-size: 20px;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .menu-toggle { display: block; }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">⛏️</div>
                <span class="logo-text">MINERCORE</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-title">Main Menu</div>
                <a href="dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="mining-room.php" class="nav-item">
                    <span class="nav-icon">⛏️</span>
                    <span>Mining Room</span>
                </a>
                <a href="games.php" class="nav-item">
                    <span class="nav-icon">🎮</span>
                    <span>Games</span>
                </a>
                <a href="market.php" class="nav-item">
                    <span class="nav-icon">🛒</span>
                    <span>Marketplace</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Finance</div>
                <a href="wallet.php" class="nav-item">
                    <span class="nav-icon">💰</span>
                    <span>Wallet</span>
                </a>
                <a href="referrals.php" class="nav-item">
                    <span class="nav-icon">🤝</span>
                    <span>Referrals</span>
                </a>
                <a href="history.php" class="nav-item">
                    <span class="nav-icon">📜</span>
                    <span>History</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Account</div>
                <a href="profile.php" class="nav-item active">
                    <span class="nav-icon">👤</span>
                    <span>Profile</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <span class="nav-icon">⚙️</span>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 2)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                    <div class="user-status">
                        <span class="status-dot"></span>
                        <span>Online</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">My Profile</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / Profile
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                ✅ <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- PROFILE HEADER -->
        <div class="profile-header-card">
            <div class="profile-avatar-large">
                <?= strtoupper(substr($user['username'], 0, 2)) ?>
            </div>
            <div class="profile-info">
                <h2 class="profile-name"><?= htmlspecialchars($user['username']) ?></h2>
                <div class="profile-meta">
                    <div class="meta-item">
                        <span class="meta-icon">📅</span>
                        <span>Member for <?= $days_active ?> days</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-icon">📧</span>
                        <span><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-icon">🏆</span>
                        <span>Level <?= floor($days_active / 30) + 1 ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-icon">🖥️</div>
                <div class="stat-box-value"><?= $total_miners ?></div>
                <div class="stat-box-label">Miners</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-icon">⛏️</div>
                <div class="stat-box-value"><?= $total_mining_ops ?></div>
                <div class="stat-box-label">Mining Ops</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-icon">🤝</div>
                <div class="stat-box-value"><?= $total_referrals ?></div>
                <div class="stat-box-label">Referrals</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-icon">💰</div>
                <div class="stat-box-value"><?= number_format($user['total_mined'], 4) ?></div>
                <div class="stat-box-label">BTC Total</div>
            </div>
        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">
            <!-- ACCOUNT INFO -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-wrapper">
                        <div class="card-icon">👤</div>
                        <h3 class="card-title">Account Information</h3>
                    </div>
                    <p class="card-subtitle">Update your personal details</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <div class="input-hint">Username cannot be changed</div>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Registration Date</label>
                                <input type="text" value="<?= date('M d, Y H:i', strtotime($user['created_at'])) ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Last Login</label>
                                <input type="text" value="<?= $user['last_active'] ? date('M d, Y H:i', strtotime($user['last_active'])) : 'Never' ?>" disabled>
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- REFERRAL PROGRAM -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-wrapper">
                        <div class="card-icon">🤝</div>
                        <h3 class="card-title">Referral Program</h3>
                    </div>
                    <p class="card-subtitle">Invite friends and earn commissions</p>
                </div>
                <div class="card-body">
                    <div class="referral-card">
                        <div class="referral-title">Your Referral Code</div>
                        <div class="referral-code" id="refCode"><?= $user['referral_code'] ?></div>
                        <div class="referral-link">
                            <?= 'https://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $user['referral_code'] ?>
                        </div>
                        <button class="btn btn-secondary" onclick="copyReferral()">
                            📋 Copy Link
                        </button>
                        
                        <div class="referral-stats">
                            <div class="referral-stat">
                                <div class="referral-stat-value"><?= $total_referrals ?></div>
                                <div class="referral-stat-label">Total Referrals</div>
                            </div>
                            <div class="referral-stat">
                                <div class="referral-stat-value">10%</div>
                                <div class="referral-stat-label">Commission</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECURITY -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-wrapper">
                        <div class="card-icon">🔒</div>
                        <h3 class="card-title">Security</h3>
                    </div>
                    <p class="card-subtitle">Change your password periodically</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required minlength="6" placeholder="Min 6 characters">
                            </div>
                            <div class="form-group full">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required placeholder="Repeat new password">
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- DANGER ZONE -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-wrapper">
                        <div class="card-icon" style="background: rgba(255, 71, 87, 0.1);">⚠️</div>
                        <h3 class="card-title">Danger Zone</h3>
                    </div>
                    <p class="card-subtitle">Irreversible actions</p>
                </div>
                <div class="card-body">
                    <p style="color: var(--core-text-dim); margin-bottom: 20px; line-height: 1.6;">
                        Once you delete your account, there is no going back. All your data, miners, and balance will be permanently removed.
                    </p>
                    <button class="btn btn-danger" onclick="alert('Contact support to delete your account')">
                        Delete Account
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        function copyReferral() {
            const code = document.getElementById('refCode').textContent;
            const link = '<?= 'https://' . $_SERVER['HTTP_HOST'] ?>/register.php?ref=' + code;
            
            navigator.clipboard.writeText(link).then(() => {
                alert('Referral link copied to clipboard!');
            }).catch(() => {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = link;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Referral link copied to clipboard!');
            });
        }
    </script>
</body>
</html>