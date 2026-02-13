<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

checkAuth(); // Redireciona se não logado

// Busca dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Busca miners do usuário
$stmt = $pdo->prepare("
    SELECT um.*, mt.name, mt.base_hashrate, mt.image 
    FROM user_miners um 
    JOIN miner_types mt ON um.miner_type_id = mt.id 
    WHERE um.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$miners = $stmt->fetchAll();

// Calcula hashrate total
$total_hashrate = 0;
foreach ($miners as $miner) {
    $total_hashrate += $miner['base_hashrate'] * $miner['efficiency'];
}

// Busca bloco atual
$stmt = $pdo->query("SELECT * FROM blocks WHERE status = 'mining' ORDER BY id DESC LIMIT 1");
$current_block = $stmt->fetch();

// Busca histórico recente
$stmt = $pdo->prepare("
    SELECT * FROM mining_history 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MinerCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
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
            --core-yellow: #ffd700;
            --core-text: #e0e0e0;
            --core-text-dim: #8892b0;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            color: var(--core-text);
            min-height: 100vh;
        }

        /* Layout Grid */
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: var(--core-dark);
            border-right: 1px solid rgba(0, 212, 255, 0.1);
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            width: 250px;
            overflow-y: auto;
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

        .nav-section {
            margin-bottom: 30px;
        }

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
            margin-bottom: 15px;
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

        .user-name {
            font-weight: 700;
            color: var(--core-text);
        }

        .user-status {
            font-size: 12px;
            color: var(--core-green);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-status::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--core-green);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Main Content */
        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn-header {
            padding: 10px 20px;
            background: var(--core-dark);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 8px;
            color: var(--core-blue);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-header:hover {
            background: var(--core-blue);
            color: var(--core-black);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--core-dark);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 15px;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--core-blue), transparent);
        }

        .stat-label {
            font-size: 14px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--core-blue);
        }

        .stat-sub {
            font-size: 14px;
            color: var(--core-text-dim);
            margin-top: 5px;
        }

        .stat-card.green .stat-value { color: var(--core-green); }
        .stat-card.orange .stat-value { color: var(--core-orange); }
        .stat-card.yellow .stat-value { color: var(--core-yellow); }

        /* Block Timer */
        .block-timer {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(255, 107, 53, 0.1));
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .block-label {
            font-size: 14px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }

        .block-countdown {
            font-family: 'Orbitron', sans-serif;
            font-size: 48px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--core-blue), var(--core-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }

        .block-reward {
            font-size: 20px;
            color: var(--core-green);
            font-weight: 700;
        }

        /* Mining Room Preview */
        .room-section {
            background: var(--core-dark);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
        }

        .miners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
        }

        .miner-slot {
            aspect-ratio: 1;
            background: var(--core-darker);
            border: 2px dashed rgba(0, 212, 255, 0.3);
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            position: relative;
        }

        .miner-slot.occupied {
            border-style: solid;
            border-color: var(--core-blue);
            background: rgba(0, 212, 255, 0.05);
        }

        .miner-slot:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.1);
        }

        .miner-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .miner-name {
            font-size: 14px;
            font-weight: 600;
            text-align: center;
        }

        .miner-hash {
            font-size: 12px;
            color: var(--core-blue);
            margin-top: 5px;
        }

        .empty-slot {
            color: var(--core-text-dim);
            font-size: 14px;
        }

        /* Recent Activity */
        .activity-section {
            background: var(--core-dark);
            border-radius: 20px;
            padding: 30px;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: var(--core-darker);
            border-radius: 10px;
            border-left: 3px solid var(--core-blue);
        }

        .activity-item.bonus { border-left-color: var(--core-green); }
        .activity-item.withdraw { border-left-color: var(--core-orange); }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(0, 212, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .activity-time {
            font-size: 13px;
            color: var(--core-text-dim);
        }

        .activity-amount {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            color: var(--core-green);
        }

        .activity-amount.negative {
            color: var(--core-red);
        }

        /* Mobile */
        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed;
                left: -250px;
                transition: left 0.3s;
                z-index: 1000;
            }
            .sidebar.open { left: 0; }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .block-countdown { font-size: 32px; }
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
                <a href="dashboard.php" class="nav-item active">
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
                <a href="profile.php" class="nav-item">
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
                <h1 class="page-title">Dashboard</h1>
                <div class="header-actions">
                    <a href="wallet.php" class="btn-header">
                        💰 <?= number_format($user['balance_available'], 8) ?> BTC
                    </a>
                    <a href="market.php" class="btn-header" style="background: var(--core-blue); color: var(--core-black);">
                        + Comprar Miner
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Seu Hashrate</div>
                    <div class="stat-value"><?= number_format($total_hashrate, 2) ?> GH/s</div>
                    <div class="stat-sub"><?= count($miners) ?> miners ativos</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Saldo Disponível</div>
                    <div class="stat-value"><?= number_format($user['balance_available'], 6) ?> BTC</div>
                    <div class="stat-sub">Pronto para saque</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-label">Ganhos Totais</div>
                    <div class="stat-value"><?= number_format($user['total_mined'], 6) ?> BTC</div>
                    <div class="stat-sub">Desde o início</div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-label">Próximo Bloco</div>
                    <div class="stat-value" id="next-estimate">~0.000000</div>
                    <div class="stat-sub">Estimativa (10min)</div>
                </div>
            </div>

            <!-- Block Timer -->
            <div class="block-timer">
                <div class="block-label">⏱️ Próximo Bloco em</div>
                <div class="block-countdown" id="countdown">10:00</div>
                <div class="block-reward">
                    💎 Recompensa do Bloco: <?= $current_block ? number_format($current_block['reward_pool'], 8) : '0.00010000' ?> BTC
                </div>
            </div>

            <!-- Mining Room Preview -->
            <div class="room-section">
                <div class="section-header">
                    <h2 class="section-title">⛏️ Sua Sala de Mineração</h2>
                    <a href="mining-room.php" style="color: var(--core-blue); text-decoration: none; font-weight: 600;">
                        Ver Completo →
                    </a>
                </div>
                <div class="miners-grid">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <?php if (isset($miners[$i])): ?>
                            <div class="miner-slot occupied">
                                <div class="miner-icon">🖥️</div>
                                <div class="miner-name"><?= $miners[$i]['name'] ?></div>
                                <div class="miner-hash"><?= $miners[$i]['base_hashrate'] ?> GH/s</div>
                            </div>
                        <?php else: ?>
                            <div class="miner-slot">
                                <div class="empty-slot">+ Vazio</div>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="activity-section">
                <div class="section-header">
                    <h2 class="section-title">📜 Atividade Recente</h2>
                    <a href="history.php" style="color: var(--core-text-dim); text-decoration: none; font-size: 14px;">
                        Ver Tudo
                    </a>
                </div>
                <div class="activity-list">
                    <?php if (empty($history)): ?>
                        <div class="activity-item">
                            <div class="activity-details">
                                <div class="activity-title">Nenhuma atividade recente</div>
                                <div class="activity-time">Comece minerando ou jogando!</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($history as $item): ?>
                            <div class="activity-item <?= $item['type'] ?>">
                                <div class="activity-icon">
                                    <?= $item['type'] == 'block_reward' ? '⛏️' : 
                                       ($item['type'] == 'game_bonus' ? '🎮' : '💰') ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">
                                        <?= $item['type'] == 'block_reward' ? 'Recompensa de Bloco' : 
                                           ($item['type'] == 'game_bonus' ? 'Bônus de Game' : 'Ganhos') ?>
                                    </div>
                                    <div class="activity-time"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></div>
                                </div>
                                <div class="activity-amount">+<?= number_format($item['amount'], 8) ?> BTC</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Countdown do bloco
        let blockTime = 600; // 10 minutos em segundos
        
        function updateCountdown() {
            const minutes = Math.floor(blockTime / 60);
            const seconds = blockTime % 60;
            document.getElementById('countdown').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (blockTime > 0) {
                blockTime--;
            } else {
                blockTime = 600; // Reseta (simulação)
                location.reload(); // Recarrega para novo bloco
            }
        }
        
        setInterval(updateCountdown, 1000);
        updateCountdown();

        // Estimativa de ganho (simulação)
        const userHashrate = <?= $total_hashrate ?>;
        const networkHashrate = 1000000; // Simulação: 1 TH/s total
        const blockReward = 0.0001;
        
        const estimate = (userHashrate / networkHashrate) * blockReward;
        document.getElementById('next-estimate').textContent = 
            '~' + estimate.toFixed(8) + ' BTC';
    </script>
</body>
</html>