<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

checkAuth();

// Busca dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Busca miners do usuário
$stmt = $pdo->prepare("
    SELECT um.*, mt.name, mt.base_hashrate, mt.image, mt.rarity 
    FROM user_miners um 
    JOIN miner_types mt ON um.miner_type_id = mt.id 
    WHERE um.user_id = ? AND um.is_active = TRUE
");
$stmt->execute([$_SESSION['user_id']]);
$miners = $stmt->fetchAll();

// Calcula hashrate total
$total_hashrate = 0;
$active_miners = count($miners);
foreach ($miners as $miner) {
    $total_hashrate += $miner['base_hashrate'] * $miner['efficiency'];
}

// Busca boosts ativos
$stmt = $pdo->prepare("
    SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_left 
    FROM active_boosts 
    WHERE user_id = ? AND expires_at > NOW()
");
$stmt->execute([$_SESSION['user_id']]);
$active_boosts = $stmt->fetchAll();

$boost_multiplier = 1;
foreach ($active_boosts as $boost) {
    $boost_multiplier += ($boost['multiplier'] - 1);
}
$final_hashrate = $total_hashrate * $boost_multiplier;

// Busca bloco atual
$stmt = $pdo->query("SELECT * FROM blocks WHERE status = 'mining' ORDER BY id DESC LIMIT 1");
$current_block = $stmt->fetch();

// Calcula estimativa
$network_hashrate = 1000000; // 1 PH/s simulation
$block_reward = $current_block ? floatval($current_block['reward_pool']) : 0.0001;
$estimate = ($final_hashrate / $network_hashrate) * $block_reward;

// Histórico recente
$stmt = $pdo->prepare("
    SELECT * FROM mining_history 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$history = $stmt->fetchAll();

// Configuração das salas (futuro: múltiplas salas)
$total_rooms = 5;
$current_room = 1;
$slots_per_room = 20; // 5x4 grid
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MinerCore</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
    /* FUNDO CAVERNA (roxo vinho profundo) */
    --core-black: #1b0f14;
    --core-dark: #24161d;
    --core-darker: #160c11;
    --core-card: #2b1b23;

    /* CRISTAL CIANO (cor principal da interface) */
    --core-blue: #2de2e6;
    --core-blue-glow: rgba(45, 226, 230, 0.35);
    --core-blue-dark: #16b3b8;

    /* ÂMBAR / LARANJA DO MASCOTE */
    --core-orange: #ff9f1c;

    /* OURO QUENTE (recompensas / destaque financeiro) */
    --core-yellow: #ffbf3c;

    /* VERDE CRISTAL (boost / positivo) */
    --core-green: #3cffb3;
    --core-green-glow: rgba(60, 255, 179, 0.3);

    /* ALERTA / NEGATIVO */
    --core-red: #ff4d4d;

    /* TEXTO */
    --core-text: #f5e6d3;
    --core-text-dim: #bfae9c;

    /* BORDA NEON CRISTAL */
    --core-border: rgba(45, 226, 230, 0.18);

    /* SALAS BLOQUEADAS */
    --room-locked: rgba(191, 174, 156, 0.25);
}


        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            color: var(--core-text);
            min-height: 100vh;
        }

        /* SIDEBAR - LAYOUT ORIGINAL DESKTOP */
        .sidebar {
            position: relative !important;
            width: 100% !important;
            height: auto !important;
            display: flex;
            flex-direction: column;
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
            display: flex !important;
            flex-direction: row !important;
            align-items: center;
            justify-content: center;
            gap: 30px;
            overflow-x: auto;
            padding: 20px 30px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 24px;
            color: var(--core-text-dim) !important;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            white-space: nowrap;
            font-weight: 700;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-item:hover {
            background: rgba(0, 212, 255, 0.08);
            color: var(--core-text-bright) !important;
        }

        .nav-item.active {
            background: rgba(0, 212, 255, 0.15);
            color: var(--core-blue) !important;
            border-left: 3px solid var(--core-blue);
        }

        .sidebar-footer {
            position: absolute !important;
            right: 30px;
            top: 20px;
            border: none !important;
            padding: 0;
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

        /* LAYOUT COM ANÚNCIOS - 3 COLUNAS */
        .dashboard-wrapper {
            display: grid;
            grid-template-columns: 300px 1fr 300px;
            gap: 0;
            max-width: 100%;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .ad-sidebar {
            background: var(--core-card);
            border: none;
            border-radius: 0;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .ad-sidebar:first-child {
            border-right: 1px solid var(--core-border);
        }

        .ad-sidebar:last-child {
            border-left: 1px solid var(--core-border);
        }

        .ad-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            text-align: center;
            border-bottom: 1px solid var(--core-border);
            padding-bottom: 10px;
        }

        .ad-placeholder {
            background: var(--core-darker);
            border: 2px dashed var(--core-border);
            border-radius: 12px;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--core-text-dim);
            text-align: center;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .ad-placeholder:hover {
            border-color: var(--core-blue);
            background: rgba(45, 226, 230, 0.05);
        }

        .ad-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .ad-text {
            font-size: 14px;
            font-weight: 600;
        }

        .ad-size {
            font-size: 12px;
            margin-top: 8px;
            opacity: 0.7;
        }

        .main-content {
            max-width: 100%;
            margin: 0;
            padding: 30px;
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

        .page-subtitle {
            color: var(--core-text-dim);
            font-size: 16px;
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 16px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: rgba(0, 212, 255, 0.4);
            transform: translateY(-2px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--core-blue), transparent);
        }

        .stat-card.green::before {
            background: linear-gradient(90deg, var(--core-green), transparent);
        }

        .stat-card.orange::before {
            background: linear-gradient(90deg, var(--core-orange), transparent);
        }

        .stat-card.yellow::before {
            background: linear-gradient(90deg, var(--core-yellow), transparent);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-icon {
            width: 32px;
            height: 32px;
            background: rgba(0, 212, 255, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-card.green .stat-icon { background: rgba(0, 255, 136, 0.1); }
        .stat-card.orange .stat-icon { background: rgba(255, 107, 53, 0.1); }
        .stat-card.yellow .stat-icon { background: rgba(255, 215, 0, 0.1); }

        .stat-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 28px;
            font-weight: 700;
            color: var(--core-blue);
            line-height: 1.2;
            margin-bottom: 6px;
        }

        .stat-card.green .stat-value { color: var(--core-green); }
        .stat-card.orange .stat-value { color: var(--core-orange); }
        .stat-card.yellow .stat-value { color: var(--core-yellow); }

        .stat-sub {
            font-size: 13px;
            color: var(--core-text-dim);
        }

        .boost-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(0, 255, 136, 0.15);
            color: var(--core-green);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 8px;
        }

        /* BLOCK TIMER */
        .block-section {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.08), rgba(255, 107, 53, 0.05));
            border: 1px solid rgba(0, 212, 255, 0.25);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .block-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 212, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .block-content {
            position: relative;
            z-index: 1;
        }

        .block-label {
            font-size: 14px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .block-timer {
            font-family: 'Orbitron', sans-serif;
            font-size: 56px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--core-blue), var(--core-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            text-shadow: 0 0 30px rgba(0, 212, 255, 0.3);
        }

        .block-reward {
            font-size: 18px;
            color: var(--core-green);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .block-reward-icon {
            font-size: 24px;
        }

        /* MINING ROOM SECTION */
        .room-section {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ROOM SELECTOR (5 squares) */
        .room-selector {
            display: flex;
            gap: 8px;
        }

        .room-slot {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .room-slot.active {
            background: var(--core-blue);
            color: var(--core-black);
            box-shadow: 0 0 15px var(--core-blue-glow);
        }

        .room-slot.available {
            background: rgba(0, 212, 255, 0.1);
            border: 2px dashed var(--core-blue);
            color: var(--core-blue);
        }

        .room-slot.available:hover {
            background: rgba(0, 212, 255, 0.2);
            transform: scale(1.1);
        }

        .room-slot.locked {
            background: var(--room-locked);
            color: var(--core-text-dim);
            cursor: not-allowed;
            border: 2px solid transparent;
        }

        .section-link {
            color: var(--core-blue);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .section-link:hover {
            gap: 10px;
        }

        /* MINING GRID 5x4 */
        .mining-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
        }

        .miner-slot {
            aspect-ratio: 1;
            background: var(--core-darker);
            border: 2px dashed rgba(0, 212, 255, 0.25);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            position: relative;
            cursor: pointer;
            min-height: 100px;
        }

        .miner-slot:hover {
            border-color: rgba(0, 212, 255, 0.5);
            background: rgba(0, 212, 255, 0.05);
        }

        .miner-slot.occupied {
            border-style: solid;
            border-color: var(--core-blue);
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(0, 212, 255, 0.05));
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.1);
        }

        .miner-slot.occupied:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.2);
        }

        .slot-icon {
            font-size: 36px;
            margin-bottom: 6px;
            opacity: 0.6;
        }

        .miner-slot.occupied .slot-icon {
            opacity: 1;
            filter: drop-shadow(0 0 8px var(--core-blue-glow));
        }

        .slot-label {
            font-size: 12px;
            color: var(--core-text-dim);
            font-weight: 500;
            text-align: center;
            padding: 0 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .miner-slot.occupied .slot-label {
            color: var(--core-text);
            font-weight: 700;
        }

        .slot-hash {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--core-blue);
            margin-top: 3px;
            font-weight: 700;
        }

        .slot-rarity {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            box-shadow: 0 0 8px currentColor;
        }

        .rarity-common { background: #8892b0; color: #8892b0; }
        .rarity-rare { background: var(--core-blue); color: var(--core-blue); }
        .rarity-epic { background: #a855f7; color: #a855f7; }
        .rarity-legendary { background: var(--core-orange); color: var(--core-orange); }

        /* ACTIVITY SECTION */
        .activity-section {
            background: var(--core-card);
            border: 1px solid var(--core-border);
            border-radius: 20px;
            padding: 25px;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            background: var(--core-darker);
            border-radius: 12px;
            border-left: 3px solid var(--core-blue);
            transition: all 0.3s;
        }

        .activity-item:hover {
            background: rgba(0, 212, 255, 0.05);
            transform: translateX(5px);
        }

        .activity-item.bonus { border-left-color: var(--core-green); }
        .activity-item.withdraw { border-left-color: var(--core-orange); }
        .activity-item.deposit { border-left-color: var(--core-yellow); }

        .activity-icon {
            width: 44px;
            height: 44px;
            background: rgba(0, 212, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .activity-item.bonus .activity-icon { background: rgba(0, 255, 136, 0.1); }
        .activity-item.withdraw .activity-icon { background: rgba(255, 107, 53, 0.1); }
        .activity-item.deposit .activity-icon { background: rgba(255, 215, 0, 0.1); }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 13px;
            color: var(--core-text-dim);
        }

        .activity-amount {
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            font-weight: 700;
            color: var(--core-green);
        }

        .activity-amount.negative {
            color: var(--core-red);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--core-text-dim);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* MENU HAMBURGUER - MESMO PADRÃO DA WALLET */
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

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
            visibility: visible;
        }

        @media (max-width: 1400px) {
            .dashboard-wrapper {
                grid-template-columns: 250px 1fr 250px;
            }
        }

        @media (max-width: 1200px) {
            .dashboard-wrapper {
                grid-template-columns: 200px 1fr 200px;
            }
        }

        @media (max-width: 1024px) {
            .menu-toggle { display: block; }
            
            /* Sidebar vira menu lateral fixo */
            .sidebar { 
                position: fixed !important; 
                top: 0; 
                left: 0; 
                width: 280px !important; 
                height: 100vh !important; 
                z-index: 100; 
                transform: translateX(-100%); 
                transition: transform 0.3s ease;
                border-bottom: none;
                border-right: 1px solid var(--core-border);
            }
            
            .sidebar.open { 
                transform: translateX(0); 
            }
            
            /* Navegação vertical no mobile */
            .sidebar-nav { 
                flex-direction: column !important; 
                align-items: flex-start !important; 
                padding: 20px; 
                gap: 5px; 
                overflow-x: visible; 
                overflow-y: auto;
            }
            
            .nav-item { 
                width: 100%; 
                font-size: 16px; 
                padding: 14px 18px; 
                white-space: normal;
            }
            
            /* Header com padding para não ficar atrás do menu */
            .sidebar-header { 
                padding-top: 70px; 
            }
            
            /* Footer no bottom */
            .sidebar-footer { 
                position: relative !important; 
                right: auto; 
                top: auto; 
                border-top: 1px solid var(--core-border) !important; 
                padding: 20px; 
                margin-top: auto; 
            }
            
            /* Layout com anúncios vira coluna */
            .dashboard-wrapper {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 0;
            }
            
            .ad-sidebar {
                position: relative;
                top: 0;
                order: 2;
                border-radius: 16px;
                margin: 0 20px;
                border: 1px solid var(--core-border) !important;
            }

            .ad-sidebar:first-child,
            .ad-sidebar:last-child {
                border: 1px solid var(--core-border) !important;
            }
            
            .main-content {
                order: 1;
                padding: 80px 20px 20px;
            }
            
            .ad-sidebar:last-child {
                order: 3;
            }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .mining-grid { grid-template-columns: repeat(2, 1fr); }
            .block-timer { font-size: 40px; }
            .room-selector { order: -1; width: 100%; justify-content: center; }
            .section-header { flex-direction: column; align-items: flex-start; }
            
            .ad-sidebar {
                padding: 15px;
                margin: 0 15px;
            }
            
            .ad-placeholder {
                min-height: 200px;
            }

            .main-content {
                padding: 80px 15px 15px;
            }
        }

        @media (max-width: 480px) {
            .mining-grid { grid-template-columns: repeat(2, 1fr); }
            .miner-slot { min-height: 80px; }
            .slot-icon { font-size: 28px; }
        }
        
        .logo-icon img {
            width: 45px;   /* ajuste conforme necessário */
            height: auto;
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon"> <img src="images/logo1.png" alt="Logo" /></div>
                <span class="logo-text">MINERCORE</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">📊 Dashboard</a>
            <a href="games.php" class="nav-item">🎮 Games</a>
            <a href="store.php" class="nav-item">🛒 Store</a>
            <a href="market.php" class="nav-item">🛒 Marketplace</a>
            <a href="earning.php" class="nav-item">💵 ADS/PTC</a>
            <a href="history.php" class="nav-item">📜 History</a>
            <a href="wallet.php" class="nav-item">💰 Wallet</a>
            <a href="profile.php" class="nav-item">👤 Profile</a>
            <a href="logout.php" class="nav-item">🚪 Logout</a>
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

    <!-- WRAPPER COM ANÚNCIOS -->
    <div class="dashboard-wrapper">
        
        <!-- ANÚNCIOS LATERAL ESQUERDA -->
        <aside class="ad-sidebar">
            <div class="ad-title">📢 Sponsored</div>
            
            <div class="ad-placeholder">
                <div class="ad-icon">🚀</div>
                <div class="ad-text">Ad Space Available</div>
                <div class="ad-size">300x250</div>
            </div>
            
            <div class="ad-placeholder">
                <div class="ad-icon">💎</div>
                <div class="ad-text">Premium Miner Sale</div>
                <div class="ad-size">300x250</div>
            </div>
            
            <div class="ad-placeholder">
                <div class="ad-icon">🎮</div>
                <div class="ad-text">New Game Launch</div>
                <div class="ad-size">300x250</div>
            </div>
        </aside>

        <!-- CONTEÚDO CENTRAL -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Overview of your mining operation</p>
            </div>

            <!-- STATS GRID -->
            <div class="stats-grid">
                <!-- Hashrate -->
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Your Hashrate</span>
                        <div class="stat-icon">⚡</div>
                    </div>
                    <div class="stat-value"><?= number_format($final_hashrate, 2) ?> <small>GH/s</small></div>
                    <div class="stat-sub"><?= $active_miners ?> active miners</div>
                    <?php if ($boost_multiplier > 1): ?>
                    <div class="boost-badge">
                        🔥 +<?= (($boost_multiplier - 1) * 100) ?>% BOOST
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Miners -->
                <div class="stat-card green">
                    <div class="stat-header">
                        <span class="stat-label">Active Miners</span>
                        <div class="stat-icon">🖥️</div>
                    </div>
                    <div class="stat-value"><?= $active_miners ?> <small>/ 20</small></div>
                    <div class="stat-sub">Available slots: <?= 20 - $active_miners ?></div>
                </div>

                <!-- Efficiency -->
                <div class="stat-card orange">
                    <div class="stat-header">
                        <span class="stat-label">Avg Efficiency</span>
                        <div class="stat-icon">📈</div>
                    </div>
                    <div class="stat-value">100%</div>
                    <div class="stat-sub">Base: <?= number_format($total_hashrate, 2) ?> GH/s</div>
                </div>

                <!-- Estimate -->
                <div class="stat-card yellow">
                    <div class="stat-header">
                        <span class="stat-label">Est. per Block</span>
                        <div class="stat-icon">💎</div>
                    </div>
                    <div class="stat-value"><?= number_format($estimate, 8) ?></div>
                    <div class="stat-sub">~<?= number_format($estimate * 144, 8) ?> BTC/day</div>
                </div>
            </div>

            <!-- BLOCK TIMER -->
            <div class="block-section">
                <div class="block-content">
                    <div class="block-label">⏱️ Next Block In</div>
                    <div class="block-timer" id="countdown">10:00</div>
                    <div class="block-reward">
                        <span class="block-reward-icon">💎</span>
                        <span>Block Reward: <?= number_format($block_reward, 8) ?> BTC</span>
                    </div>
                    <!-- TEMPORARY: This timer resets on refresh. In production, it will use real block timestamp from database -->
                </div>
            </div>

            <!-- MINING ROOM -->
            <div class="room-section">
                <div class="section-header">
                    <div class="section-title-wrapper">
                        <h2 class="section-title">
                            <span>⛏️</span>
                            <span>Your Mining Room</span>
                        </h2>
                        
                        <!-- ROOM SELECTOR: 5 squares -->
                        <div class="room-selector">
                            <?php for ($r = 1; $r <= 5; $r++): ?>
                                <?php if ($r == $current_room): ?>
                                    <div class="room-slot active" title="Room <?= $r ?> (Current)"><?= $r ?></div>
                                <?php elseif ($r <= 2): ?>
                                    <!-- First 2 rooms free, others locked until purchased -->
                                    <div class="room-slot available" title="Buy Room <?= $r ?>" onclick="alert('Room <?= $r ?> available for purchase!')">+</div>
                                <?php else: ?>
                                    <div class="room-slot locked" title="Locked">🔒</div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <a href="mining-room.php" class="section-link">
                        View Full →
                    </a>
                </div>
                
                <!-- MINING GRID: 5 columns x 4 rows = 20 slots -->
                <div class="mining-grid">
                    <?php for ($i = 0; $i < 20; $i++): ?>
                        <?php if (isset($miners[$i])): 
                            $rarityClass = 'rarity-' . $miners[$i]['rarity'];
                        ?>
                        <div class="miner-slot occupied" onclick="location.href='mining-room.php'" title="<?= $miners[$i]['name'] ?>">
                            <div class="slot-rarity <?= $rarityClass ?>"></div>
                            <div class="slot-icon">🖥️</div>
                            <div class="slot-label"><?= $miners[$i]['name'] ?></div>
                            <div class="slot-hash"><?= number_format($miners[$i]['base_hashrate'], 1) ?> GH/s</div>
                        </div>
                        <?php else: ?>
                        <div class="miner-slot" onclick="location.href='market.php'" title="Empty Slot - Click to buy miner">
                            <div class="slot-icon">➕</div>
                            <div class="slot-label">Empty</div>
                        </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- RECENT ACTIVITY -->
            <div class="activity-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span>📜</span>
                        <span>Recent Activity</span>
                    </h2>
                    <a href="history.php" class="section-link">View All →</a>
                </div>
                
                <div class="activity-list">
                    <?php if (empty($history)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No recent activity</p>
                        <p style="font-size: 14px; margin-top: 5px;">Start mining or playing games!</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($history as $item): 
                            $typeClass = $item['type'];
                            $icon = $item['type'] == 'block_reward' ? '⛏️' : 
                                   ($item['type'] == 'game_bonus' ? '🎮' : 
                                   ($item['type'] == 'withdrawal' ? '💸' : '💰'));
                            $title = $item['type'] == 'block_reward' ? 'Block Reward' : 
                                    ($item['type'] == 'game_bonus' ? 'Game Bonus' : 
                                    ($item['type'] == 'withdrawal' ? 'Withdrawal' : 'Deposit'));
                            $amountClass = $item['amount'] < 0 ? 'negative' : '';
                            $sign = $item['amount'] < 0 ? '' : '+';
                        ?>
                        <div class="activity-item <?= $typeClass ?>">
                            <div class="activity-icon"><?= $icon ?></div>
                            <div class="activity-content">
                                <div class="activity-title"><?= $title ?></div>
                                <div class="activity-time"><?= date('M d, Y H:i', strtotime($item['created_at'])) ?></div>
                            </div>
                            <div class="activity-amount <?= $amountClass ?>">
                                <?= $sign ?><?= number_format($item['amount'], 8) ?> BTC
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- ANÚNCIOS LATERAL DIREITA -->
        <aside class="ad-sidebar">
            <div class="ad-title">🔥 Trending</div>
            
            <div class="ad-placeholder">
                <div class="ad-icon">⚡</div>
                <div class="ad-text">Boost Your Hashrate</div>
                <div class="ad-size">300x250</div>
            </div>
            
            <div class="ad-placeholder">
                <div class="ad-icon">🏆</div>
                <div class="ad-text">Tournament Event</div>
                <div class="ad-size">300x250</div>
            </div>
            
            <div class="ad-placeholder">
                <div class="ad-icon">🎁</div>
                <div class="ad-text">Daily Bonus</div>
                <div class="ad-size">300x250</div>
            </div>
        </aside>
        
    </div>

    <script>
        // Mobile sidebar toggle - MESMA FUNÇÃO DA WALLET
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        // TEMPORARY: Client-side countdown (resets on refresh)
        // In production, this will use the actual block timestamp from database
        let blockTime = 600; // 10 minutes in seconds
        
        function updateCountdown() {
            const minutes = Math.floor(blockTime / 60);
            const seconds = blockTime % 60;
            document.getElementById('countdown').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (blockTime > 0) {
                blockTime--;
            } else {
                // Reset for demo purposes
                blockTime = 600;
                // In production: location.reload() to get new block data
            }
        }
        
        setInterval(updateCountdown, 1000);
        updateCountdown();

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            fetch('api/update-stats.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Update values dynamically
                    }
                })
                .catch(e => console.log('Update failed:', e));
        }, 30000);
    </script>
</body>
</html>