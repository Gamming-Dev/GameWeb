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

// Busca dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Busca saldo MCT do usuário
$stmt = $pdo->prepare("SELECT mct_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$mct_balance = $stmt->fetchColumn() ?: 0;

// Busca slots do usuário
$stmt = $pdo->prepare("SELECT * FROM user_slots WHERE user_id = ? ORDER BY slot_number");
$stmt->execute([$user_id]);
$user_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca todos os produtos da loja (miners, baterias, painéis solares)
$stmt = $pdo->query("SELECT * FROM store_items WHERE is_active = TRUE ORDER BY category, price");
$store_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiza produtos por categoria
$miners = array_filter($store_items, fn($item) => $item['category'] === 'miner');
$batteries = array_filter($store_items, fn($item) => $item['category'] === 'battery');
$solar_panels = array_filter($store_items, fn($item) => $item['category'] === 'solar_panel');
$slot_upgrades = array_filter($store_items, fn($item) => $item['category'] === 'slot');

// Processa compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_item'])) {
    $item_id = intval($_POST['item_id']);
    
    // Busca item
    $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        $error = 'Item not found!';
    } elseif ($mct_balance < $item['price']) {
        $error = 'Insufficient MCT balance!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Deduz saldo
            $stmt = $pdo->prepare("UPDATE users SET mct_balance = mct_balance - ? WHERE id = ?");
            $stmt->execute([$item['price'], $user_id]);
            
            // Adiciona item ao inventário do usuário
            if ($item['category'] === 'miner') {
                // Adiciona miner ao inventário
                $stmt = $pdo->prepare("INSERT INTO user_items (user_id, item_id, category, quantity) VALUES (?, ?, 'miner', 1)");
                $stmt->execute([$user_id, $item_id]);
            } elseif ($item['category'] === 'slot') {
                // Desbloqueia slot
                $level = intval($item['bonus_level']);
                $stmt = $pdo->prepare("INSERT INTO user_slots (user_id, slot_number, bonus_level, is_unlocked) VALUES (?, ?, ?, TRUE)");
                // Busca próximo slot disponível
                $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM user_slots WHERE user_id = ?");
                $stmt_count->execute([$user_id]);
                $next_slot = $stmt_count->fetchColumn() + 1;
                $stmt->execute([$user_id, $next_slot, $level]);
            } else {
                // Baterias e painéis solares
                $stmt = $pdo->prepare("INSERT INTO user_items (user_id, item_id, category, quantity) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1");
                $stmt->execute([$user_id, $item_id, $item['category']]);
            }
            
            // Registra transação
            $stmt = $pdo->prepare("INSERT INTO store_transactions (user_id, item_id, price_paid, transaction_type) VALUES (?, ?, ?, 'purchase')");
            $stmt->execute([$user_id, $item_id, $item['price']]);
            
            $pdo->commit();
            $message = "Successfully purchased {$item['name']}!";
            
            // Atualiza saldo
            $mct_balance -= $item['price'];
            
            // Redireciona para evitar resubmit
            header("Location: store.php?success=1");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Purchase failed: ' . $e->getMessage();
        }
    }
}

// Mensagem de sucesso da URL
if (isset($_GET['success'])) {
    $message = 'Purchase completed successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store - MinerCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --core-black: #1b0f14;
            --core-dark: #24161d;
            --core-darker: #160c11;
            --core-card: #2b1b23;
            --core-blue: #2de2e6;
            --core-blue-glow: rgba(45, 226, 230, 0.35);
            --core-blue-dark: #16b3b8;
            --core-orange: #ff9f1c;
            --core-yellow: #ffbf3c;
            --core-green: #3cffb3;
            --core-green-glow: rgba(60, 255, 179, 0.3);
            --core-red: #ff4d4d;
            --core-purple: #9d4edd;
            --core-text: #f5e6d3;
            --core-text-dim: #bfae9c;
            --core-border: rgba(45, 226, 230, 0.18);
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            color: var(--core-text);
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            position: relative;
            width: 100%;
            background: var(--core-dark);
            border-bottom: 1px solid var(--core-border);
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
            background: linear-gradient(135deg, var(--core-blue), var(--core-green));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 0 20px var(--core-blue-glow);
        }

        .logo-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--core-blue), var(--core-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-menu {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .nav-link {
            padding: 12px 24px;
            color: var(--core-text-dim);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 16px;
        }

        .nav-link:hover {
            background: rgba(45, 226, 230, 0.1);
            color: var(--core-blue);
        }

        .nav-link.active {
            background: rgba(45, 226, 230, 0.15);
            color: var(--core-blue);
            border: 1px solid var(--core-border);
        }

        /* MAIN CONTENT */
        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 36px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--core-blue), var(--core-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--core-text-dim);
            font-size: 18px;
        }

        /* BALANCE CARD */
        .balance-card {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 0 30px rgba(45, 226, 230, 0.1);
        }

        .balance-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .balance-label {
            color: var(--core-text-dim);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .balance-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 32px;
            font-weight: 700;
            color: var(--core-yellow);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mct-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--core-yellow), var(--core-orange));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        /* ALERT MESSAGES */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(60, 255, 179, 0.15);
            border: 1px solid var(--core-green);
            color: var(--core-green);
        }

        .alert-error {
            background: rgba(255, 77, 77, 0.15);
            border: 1px solid var(--core-red);
            color: var(--core-red);
        }

        /* STORE SECTION */
        .store-section {
            margin-bottom: 50px;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--core-blue);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: rgba(45, 226, 230, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        /* PRODUCT GRID */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 16px;
            padding: 25px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(45, 226, 230, 0.2);
            border-color: var(--core-blue);
        }

        .product-card.common { border-top: 3px solid #9e9e9e; }
        .product-card.rare { border-top: 3px solid var(--core-blue); }
        .product-card.epic { border-top: 3px solid var(--core-purple); }
        .product-card.legendary { border-top: 3px solid var(--core-yellow); }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .product-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--core-text);
            margin-bottom: 5px;
        }

        .product-rarity {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .rarity-common {
            background: rgba(158, 158, 158, 0.2);
            color: #9e9e9e;
        }

        .rarity-rare {
            background: rgba(45, 226, 230, 0.2);
            color: var(--core-blue);
        }

        .rarity-epic {
            background: rgba(157, 78, 221, 0.2);
            color: var(--core-purple);
        }

        .rarity-legendary {
            background: rgba(255, 191, 60, 0.2);
            color: var(--core-yellow);
        }

        .product-icon {
            width: 80px;
            height: 80px;
            background: rgba(45, 226, 230, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 20px auto;
        }

        .product-description {
            color: var(--core-text-dim);
            font-size: 15px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .product-stats {
            margin-bottom: 20px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--core-border);
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: var(--core-text-dim);
            font-size: 14px;
        }

        .stat-value {
            color: var(--core-text);
            font-weight: 600;
            font-size: 14px;
        }

        .product-price {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            background: rgba(255, 191, 60, 0.1);
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .price-amount {
            font-family: 'JetBrains Mono', monospace;
            font-size: 24px;
            font-weight: 700;
            color: var(--core-yellow);
        }

        .price-currency {
            font-size: 14px;
            color: var(--core-text-dim);
            font-weight: 500;
        }

        .buy-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-green));
            border: none;
            border-radius: 12px;
            color: var(--core-black);
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .buy-button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px var(--core-blue-glow);
        }

        .buy-button:disabled {
            background: rgba(158, 158, 158, 0.3);
            cursor: not-allowed;
            transform: none;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .page-title {
                font-size: 28px;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }

            .balance-info {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* SLOT SPECIFIC STYLES */
        .bonus-badge {
            display: inline-block;
            padding: 6px 14px;
            background: linear-gradient(135deg, var(--core-green), var(--core-blue));
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            color: var(--core-black);
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <div class="logo-icon">💎</div>
                <span class="logo-text">MinerCore</span>
            </a>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="store.php" class="nav-link active">Store</a>
            <a href="wallet.php" class="nav-link">Wallet</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="logout.php" class="nav-link">Logout</a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">🛒 MinerCore Store</h1>
            <p class="page-subtitle">Upgrade your mining operation with premium equipment</p>
        </div>

        <!-- BALANCE CARD -->
        <div class="balance-card">
            <div class="balance-info">
                <div>
                    <div class="balance-label">Your MCT Balance</div>
                    <div class="balance-value">
                        <span class="mct-icon">💰</span>
                        <span><?php echo number_format($mct_balance, 2); ?> MCT</span>
                    </div>
                </div>
                <div>
                    <div class="balance-label">≈ USD Value</div>
                    <div class="balance-value" style="font-size: 24px; color: var(--core-green);">
                        $<?php echo number_format($mct_balance * 1.00, 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- SLOTS SECTION -->
        <section class="store-section">
            <h2 class="section-title">
                <span class="section-icon">🎰</span>
                Mining Slots
            </h2>
            <div class="product-grid">
                <?php foreach ($slot_upgrades as $slot): ?>
                    <div class="product-card <?php echo strtolower($slot['rarity']); ?>">
                        <div class="product-header">
                            <div>
                                <h3 class="product-name"><?php echo htmlspecialchars($slot['name']); ?></h3>
                                <span class="product-rarity rarity-<?php echo strtolower($slot['rarity']); ?>">
                                    <?php echo $slot['rarity']; ?>
                                </span>
                                <div class="bonus-badge">
                                    +<?php echo $slot['bonus_level'] * 10; ?>% Bonus
                                </div>
                            </div>
                        </div>
                        <div class="product-icon">🎰</div>
                        <p class="product-description"><?php echo htmlspecialchars($slot['description']); ?></p>
                        <div class="product-stats">
                            <div class="stat-row">
                                <span class="stat-label">Bonus Level</span>
                                <span class="stat-value">Level <?php echo $slot['bonus_level']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Hashrate Bonus</span>
                                <span class="stat-value">+<?php echo $slot['bonus_level'] * 10; ?>%</span>
                            </div>
                        </div>
                        <div class="product-price">
                            <span class="price-amount"><?php echo number_format($slot['price'], 2); ?></span>
                            <span class="price-currency">MCT</span>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="item_id" value="<?php echo $slot['id']; ?>">
                            <button type="submit" name="buy_item" class="buy-button" 
                                <?php echo ($mct_balance < $slot['price']) ? 'disabled' : ''; ?>>
                                <?php echo ($mct_balance < $slot['price']) ? 'Insufficient Balance' : 'Purchase Slot'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- MINERS SECTION -->
        <section class="store-section">
            <h2 class="section-title">
                <span class="section-icon">⚡</span>
                Mining Machines
            </h2>
            <div class="product-grid">
                <?php foreach ($miners as $miner): ?>
                    <div class="product-card <?php echo strtolower($miner['rarity']); ?>">
                        <div class="product-header">
                            <div>
                                <h3 class="product-name"><?php echo htmlspecialchars($miner['name']); ?></h3>
                                <span class="product-rarity rarity-<?php echo strtolower($miner['rarity']); ?>">
                                    <?php echo $miner['rarity']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="product-icon">⛏️</div>
                        <p class="product-description"><?php echo htmlspecialchars($miner['description']); ?></p>
                        <div class="product-stats">
                            <div class="stat-row">
                                <span class="stat-label">Hashrate</span>
                                <span class="stat-value"><?php echo number_format($miner['stats_hashrate'], 2); ?> TH/s</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Power</span>
                                <span class="stat-value"><?php echo number_format($miner['stats_power'], 0); ?> W</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Efficiency</span>
                                <span class="stat-value"><?php echo number_format($miner['stats_efficiency'], 2); ?> W/TH</span>
                            </div>
                        </div>
                        <div class="product-price">
                            <span class="price-amount"><?php echo number_format($miner['price'], 2); ?></span>
                            <span class="price-currency">MCT</span>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="item_id" value="<?php echo $miner['id']; ?>">
                            <button type="submit" name="buy_item" class="buy-button"
                                <?php echo ($mct_balance < $miner['price']) ? 'disabled' : ''; ?>>
                                <?php echo ($mct_balance < $miner['price']) ? 'Insufficient Balance' : 'Buy Miner'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- BATTERIES SECTION -->
        <section class="store-section">
            <h2 class="section-title">
                <span class="section-icon">🔋</span>
                Power Batteries
            </h2>
            <div class="product-grid">
                <?php foreach ($batteries as $battery): ?>
                    <div class="product-card <?php echo strtolower($battery['rarity']); ?>">
                        <div class="product-header">
                            <div>
                                <h3 class="product-name"><?php echo htmlspecialchars($battery['name']); ?></h3>
                                <span class="product-rarity rarity-<?php echo strtolower($battery['rarity']); ?>">
                                    <?php echo $battery['rarity']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="product-icon">🔋</div>
                        <p class="product-description"><?php echo htmlspecialchars($battery['description']); ?></p>
                        <div class="product-stats">
                            <div class="stat-row">
                                <span class="stat-label">Capacity</span>
                                <span class="stat-value"><?php echo number_format($battery['stats_capacity'], 0); ?> kWh</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Efficiency</span>
                                <span class="stat-value"><?php echo number_format($battery['stats_efficiency'] * 100, 0); ?>%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Charge Rate</span>
                                <span class="stat-value"><?php echo number_format($battery['stats_charge_rate'], 2); ?> kW</span>
                            </div>
                        </div>
                        <div class="product-price">
                            <span class="price-amount"><?php echo number_format($battery['price'], 2); ?></span>
                            <span class="price-currency">MCT</span>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="item_id" value="<?php echo $battery['id']; ?>">
                            <button type="submit" name="buy_item" class="buy-button"
                                <?php echo ($mct_balance < $battery['price']) ? 'disabled' : ''; ?>>
                                <?php echo ($mct_balance < $battery['price']) ? 'Insufficient Balance' : 'Buy Battery'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- SOLAR PANELS SECTION -->
        <section class="store-section">
            <h2 class="section-title">
                <span class="section-icon">☀️</span>
                Solar Panels
            </h2>
            <div class="product-grid">
                <?php foreach ($solar_panels as $panel): ?>
                    <div class="product-card <?php echo strtolower($panel['rarity']); ?>">
                        <div class="product-header">
                            <div>
                                <h3 class="product-name"><?php echo htmlspecialchars($panel['name']); ?></h3>
                                <span class="product-rarity rarity-<?php echo strtolower($panel['rarity']); ?>">
                                    <?php echo $panel['rarity']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="product-icon">☀️</div>
                        <p class="product-description"><?php echo htmlspecialchars($panel['description']); ?></p>
                        <div class="product-stats">
                            <div class="stat-row">
                                <span class="stat-label">Power Output</span>
                                <span class="stat-value"><?php echo number_format($panel['stats_power_output'], 2); ?> kW</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Efficiency</span>
                                <span class="stat-value"><?php echo number_format($panel['stats_efficiency'] * 100, 1); ?>%</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Cost Reduction</span>
                                <span class="stat-value">-<?php echo number_format($panel['stats_cost_reduction'] * 100, 0); ?>%</span>
                            </div>
                        </div>
                        <div class="product-price">
                            <span class="price-amount"><?php echo number_format($panel['price'], 2); ?></span>
                            <span class="price-currency">MCT</span>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="item_id" value="<?php echo $panel['id']; ?>">
                            <button type="submit" name="buy_item" class="buy-button"
                                <?php echo ($mct_balance < $panel['price']) ? 'disabled' : ''; ?>>
                                <?php echo ($mct_balance < $panel['price']) ? 'Insufficient Balance' : 'Buy Panel'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
