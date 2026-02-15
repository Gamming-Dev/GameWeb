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

// Busca dados atualizados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Busca carteiras conectadas do usuário (apenas Polygon agora)
$stmt = $pdo->prepare("SELECT * FROM user_wallets WHERE user_id = ? AND network = 'polygon' ORDER BY is_primary DESC, created_at DESC");
$stmt->execute([$user_id]);
$connected_wallets = $stmt->fetchAll();

// Busca saldos offline (USDC e moedas mineradas)
$stmt = $pdo->prepare("SELECT * FROM user_balances WHERE user_id = ?");
$stmt->execute([$user_id]);
$balances = $stmt->fetch();

if (!$balances) {
    // Cria registro inicial se não existir
    $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, usdc_offline, mcore, btc, eth, sol) VALUES (?, 0, 0, 0, 0, 0)");
    $stmt->execute([$user_id]);
    $balances = ['usdc_offline' => 0, 'mcore' => 0, 'btc' => 0, 'eth' => 0, 'sol' => 0];
}

// Processa desconexão de carteira
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect_wallet'])) {
    $wallet_id = intval($_POST['wallet_id']);
    $stmt = $pdo->prepare("DELETE FROM user_wallets WHERE id = ? AND user_id = ?");
    $stmt->execute([$wallet_id, $user_id]);
    $message = 'Wallet disconnected successfully!';
    header("Location: wallet.php?disconnected=1");
    exit();
}

// Processa troca de carteira
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_wallet'])) {
    $stmt = $pdo->prepare("DELETE FROM user_wallets WHERE user_id = ? AND network = 'polygon'");
    $stmt->execute([$user_id]);
    $message = 'Wallet removed. You can now connect a new one.';
    header("Location: wallet.php?changed=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wallet - MinerCore</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/web3@latest/dist/web3.min.js"></script>
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
    --core-red-glow: rgba(255, 77, 77, 0.5);
    --core-red-dark: #cc3742;
    --core-purple: #9d4edd;
    --core-text: #f5e6d3;
    --core-text-dim: #bfae9c;
    --core-text-bright: #ffffff;
    --core-border: rgba(45, 226, 230, 0.18);
}

body {
    font-family: 'Rajdhani', sans-serif;
    background: var(--core-black);
    color: var(--core-text);
    min-height: 100vh;
    flex-direction: column;
}

/* SIDEBAR */
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
    color: var(--core-text-bright);
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

/* LAYOUT */
.dashboard-container {
    display: block;
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px;
    width: 100%;
}

.main-content {
    flex: 1;
    margin-left: 0 !important;
    max-width: 100% !important;
    padding: 0;
}

.page-header {
    margin-bottom: 30px;
    text-align: center;
}

.page-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--core-text-bright);
    background: linear-gradient(135deg, var(--core-blue), var(--core-green));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.breadcrumb {
    color: var(--core-text-dim);
    font-size: 14px;
}

.breadcrumb a {
    color: var(--core-blue);
    text-decoration: none;
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
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: rgba(0, 255, 136, 0.1);
    border: 1px solid var(--core-green);
    color: var(--core-green);
}

.alert-warning {
    background: rgba(255, 159, 28, 0.1);
    border: 1px solid var(--core-orange);
    color: var(--core-orange);
}

.alert-error {
    background: rgba(255, 71, 87, 0.1);
    border: 1px solid var(--core-red);
    color: var(--core-red);
}

/* WALLET GRID LAYOUT */
.wallet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* COMPACT WALLET CARDS */
.wallet-card-compact {
    background: linear-gradient(135deg, var(--core-darker), var(--core-card));
    border: 1px solid var(--core-border);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s ease;
}

.wallet-card-compact:hover {
    border-color: var(--core-blue);
    box-shadow: 0 0 20px var(--core-blue-glow);
}

.wallet-card-compact.connected {
    border-color: var(--core-green);
    box-shadow: 0 0 15px var(--core-green-glow);
}

.wallet-card-header-compact {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.wallet-icon-small {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.wallet-icon-small.metamask {
    background: linear-gradient(135deg, #e2761b, #c15c0f);
}

.wallet-icon-small.usdc {
    background: linear-gradient(135deg, #2775ca, #1a5ba0);
}

.wallet-icon-small.offline {
    background: linear-gradient(135deg, var(--core-purple), var(--core-blue));
}

.wallet-title-compact {
    font-family: 'Orbitron', sans-serif;
    font-size: 16px;
    font-weight: 700;
    color: var(--core-text-bright);
}

.wallet-subtitle-compact {
    font-size: 12px;
    color: var(--core-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* USDC ONLINE SPECIFIC */
.usdc-online-card {
    border: 2px solid var(--core-blue);
    background: linear-gradient(135deg, rgba(45, 226, 230, 0.05), var(--core-card));
}

.balance-display {
    background: var(--core-black);
    border: 1px solid var(--core-border);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
}

.balance-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.balance-row:last-child {
    border-bottom: none;
}

.balance-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--core-text-dim);
}

.balance-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    font-weight: 700;
    color: var(--core-text-bright);
}

.balance-value.loading {
    color: var(--core-text-dim);
    font-style: italic;
    font-size: 14px;
}

.balance-value.error {
    color: var(--core-red);
}

.balance-value.zero {
    color: var(--core-text-dim);
}

/* USDC OFFLINE */
.usdc-offline-card {
    border: 2px solid var(--core-purple);
    background: linear-gradient(135deg, rgba(157, 78, 221, 0.05), var(--core-card));
}

.deposit-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--core-purple), var(--core-blue));
    border: none;
    border-radius: 10px;
    color: white;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.deposit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(157, 78, 221, 0.4);
}

/* MINED COINS GRID */
.mined-coins-section {
    margin-top: 40px;
}

.section-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: var(--core-text-bright);
    margin-bottom: 20px;
    text-align: center;
}

.mined-coins-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.mined-coin-card {
    background: linear-gradient(135deg, var(--core-darker), var(--core-card));
    border: 1px solid var(--core-border);
    border-radius: 16px;
    padding: 20px;
    position: relative;
    overflow: hidden;
}

.mined-coin-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--core-orange), var(--core-yellow));
}

.coin-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
    background: linear-gradient(135deg, var(--core-orange), var(--core-yellow));
    box-shadow: 0 0 20px rgba(255, 159, 28, 0.3);
}

.coin-name {
    font-family: 'Orbitron', sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--core-text-bright);
    margin-bottom: 5px;
}

.coin-balance {
    font-family: 'JetBrains Mono', monospace;
    font-size: 24px;
    font-weight: 700;
    color: var(--core-yellow);
    margin-bottom: 20px;
}

.coin-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.btn-saque {
    padding: 12px;
    background: transparent;
    border: 2px solid var(--core-green);
    color: var(--core-green);
    border-radius: 8px;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-saque:hover {
    background: var(--core-green);
    color: var(--core-black);
    box-shadow: 0 0 15px var(--core-green-glow);
}

.btn-convert {
    padding: 12px;
    background: transparent;
    border: 2px solid var(--core-orange);
    color: var(--core-orange);
    border-radius: 8px;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-convert:hover {
    background: var(--core-orange);
    color: var(--core-black);
    box-shadow: 0 0 15px rgba(255, 159, 28, 0.3);
}

/* CONNECTED WALLETS - COMPACT VERSION */
.connected-wallets-section {
    margin-bottom: 30px;
}

.connected-wallet-card {
    background: linear-gradient(135deg, var(--core-darker), var(--core-card));
    border: 2px solid var(--core-green);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 0 30px var(--core-green-glow);
}

.wallet-card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.wallet-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    background: linear-gradient(135deg, var(--core-green), var(--core-blue));
    box-shadow: 0 0 15px var(--core-green-glow);
}

.wallet-card-info h3 {
    font-family: 'Orbitron', sans-serif;
    font-size: 20px;
    color: var(--core-text-bright);
    margin-bottom: 2px;
}

.wallet-card-info p {
    color: var(--core-text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 12px;
}

.wallet-address-box {
    background: var(--core-black);
    border: 2px solid var(--core-border);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
}

.address-label {
    font-size: 11px;
    color: var(--core-text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 6px;
}

.address-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    color: var(--core-blue);
    word-break: break-all;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-copy {
    background: var(--core-blue);
    border: none;
    color: var(--core-black);
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    font-size: 11px;
    transition: all 0.3s;
    white-space: nowrap;
}

.btn-copy:hover {
    transform: scale(1.05);
    box-shadow: 0 0 15px var(--core-blue-glow);
}

/* WALLET ACTIONS - 3 BOTÕES COMPACTOS */
.wallet-actions {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
}

@media (max-width: 768px) {
    .wallet-actions {
        grid-template-columns: 1fr;
    }
}

.btn-disconnect-wallet {
    padding: 12px 16px;
    background: transparent;
    border: 2px solid var(--core-red);
    color: var(--core-red);
    border-radius: 10px;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-disconnect-wallet:hover {
    background: var(--core-red);
    color: white;
    box-shadow: 0 10px 30px var(--core-red-glow);
}

.btn-change-wallet {
    padding: 12px 16px;
    background: transparent;
    border: 2px solid var(--core-orange);
    color: var(--core-orange);
    border-radius: 10px;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-change-wallet:hover {
    background: var(--core-orange);
    color: var(--core-black);
    box-shadow: 0 10px 30px rgba(255, 159, 28, 0.3);
}

.btn-explorer {
    padding: 12px 16px;
    background: transparent;
    border: 2px solid var(--core-blue);
    color: var(--core-blue);
    border-radius: 10px;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-decoration: none;
    text-align: center;
    display: inline-block;
}

.btn-explorer:hover {
    background: var(--core-blue);
    color: var(--core-black);
}

/* CONNECT SECTION - METAMASK ONLY - COMPACT */
.wallet-section {
    background: var(--core-card);
    border: 2px solid var(--core-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    text-align: center;
}

.wallet-section-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--core-text-bright);
    margin-bottom: 8px;
}

.wallet-section-subtitle {
    color: var(--core-text-dim);
    margin-bottom: 25px;
    font-size: 14px;
}

/* NETWORK SELECTOR - REMOVIDO BNB, APENAS POLYGON */
.network-selector {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-bottom: 25px;
}

.network-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    background: var(--core-darker);
    border: 2px solid transparent;
    border-radius: 50px;
    color: var(--core-text-dim);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
}

.network-chip:hover {
    border-color: var(--core-border);
    color: var(--core-text-bright);
}

.network-chip.active {
    border-color: var(--core-blue);
    background: rgba(45, 226, 230, 0.1);
    color: var(--core-blue);
    box-shadow: 0 0 15px var(--core-blue-glow);
}

/* METAMASK BUTTON ONLY - COMPACT */
.metamask-container {
    margin-bottom: 15px;
}

.metamask-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 18px 36px;
    background: var(--core-darker);
    border: 2px solid var(--core-border);
    border-radius: 14px;
    color: var(--core-text-bright);
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.metamask-btn:hover:not(:disabled) {
    border-color: #e2761b;
    box-shadow: 0 0 25px rgba(226, 118, 27, 0.3);
    transform: translateY(-2px);
}

.metamask-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.metamask-icon {
    width: 40px;
    height: 40px;
    background: #e2761b;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

/* NOT INSTALLED MESSAGE */
.not-installed {
    background: rgba(255, 159, 28, 0.1);
    border: 1px solid var(--core-orange);
    border-radius: 10px;
    padding: 15px;
    margin-top: 15px;
    color: var(--core-orange);
    font-size: 14px;
}

.not-installed a {
    color: var(--core-blue);
    text-decoration: underline;
}

/* PREVIEW CARD - COMPACT */
.wallet-preview-card {
    background: linear-gradient(135deg, var(--core-darker), var(--core-card));
    border: 2px dashed var(--core-blue);
    border-radius: 16px;
    padding: 25px;
    margin-top: 15px;
    text-align: left;
}

.wallet-preview-card .wallet-card-icon {
    background: linear-gradient(135deg, var(--core-orange), var(--core-yellow));
}

.btn-authorize {
    flex: 1;
    padding: 14px 20px;
    background: var(--core-green);
    border: 2px solid var(--core-green);
    color: var(--core-black);
    border-radius: 10px;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-authorize:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px var(--core-green-glow);
}

/* MODAIS */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    display: flex;
    opacity: 1;
}

.modal-content {
    background: var(--core-card);
    border: 2px solid var(--core-blue);
    border-radius: 20px;
    padding: 35px;
    max-width: 450px;
    width: 90%;
    text-align: center;
    box-shadow: 0 0 60px var(--core-blue-glow), 0 20px 60px rgba(0,0,0,0.5);
    transform: scale(0.9) translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-content {
    transform: scale(1) translateY(0);
}

.modal-content.warning {
    border-color: var(--core-orange);
    box-shadow: 0 0 60px rgba(255, 159, 28, 0.3), 0 20px 60px rgba(0,0,0,0.5);
}

.modal-content.danger {
    border-color: var(--core-red);
    box-shadow: 0 0 60px var(--core-red-glow), 0 20px 60px rgba(0,0,0,0.5);
}

.modal-icon {
    font-size: 48px;
    margin-bottom: 15px;
    line-height: 1;
}

.modal-content.warning .modal-icon {
    color: var(--core-orange);
    text-shadow: 0 0 30px rgba(255, 159, 28, 0.5);
}

.modal-content.danger .modal-icon {
    color: var(--core-red);
    text-shadow: 0 0 30px var(--core-red-glow);
}

.modal-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--core-text-bright);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.modal-text {
    color: var(--core-text-dim);
    line-height: 1.6;
    margin-bottom: 25px;
    font-size: 14px;
}

.modal-highlight {
    color: var(--core-text-bright);
    font-weight: 600;
    font-family: 'JetBrains Mono', monospace;
    background: rgba(255,255,255,0.05);
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    margin: 4px 0;
    font-size: 13px;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-btn {
    flex: 1;
    padding: 14px 24px;
    border-radius: 10px;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 2px solid;
}

.modal-btn-cancel {
    background: transparent;
    border-color: var(--core-text-dim);
    color: var(--core-text-dim);
}

.modal-btn-cancel:hover {
    background: var(--core-text-dim);
    color: var(--core-black);
    transform: translateY(-2px);
}

.modal-btn-confirm {
    background: var(--core-orange);
    border-color: var(--core-orange);
    color: var(--core-black);
    box-shadow: 0 4px 15px rgba(255, 159, 28, 0.3);
}

.modal-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 159, 28, 0.5);
}

.modal-btn-danger {
    background: var(--core-red);
    border-color: var(--core-red);
    color: white;
    box-shadow: 0 4px 15px var(--core-red-glow);
}

.modal-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px var(--core-red-glow);
}

.modal-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid var(--core-border);
    border-top-color: var(--core-blue);
    border-radius: 50%;
    margin: 0 auto 20px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ERROR MESSAGE */
.error-message {
    background: rgba(255, 77, 77, 0.1);
    border: 1px solid var(--core-red);
    border-radius: 10px;
    padding: 14px;
    margin-top: 15px;
    color: var(--core-red);
    display: none;
    text-align: center;
    font-size: 14px;
}

.error-message.show {
    display: block;
}

/* RESPONSIVE */
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
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; position: fixed !important; top: 0; left: 0; height: 100vh !important; width: 280px !important; z-index: 100; border-right: 1px solid var(--core-border); border-bottom: none; }
    .sidebar.open { transform: translateX(0); }
    .menu-toggle { display: block; }
    
    .dashboard-container { padding: 80px 20px 20px; }
    
    .sidebar-nav { flex-direction: column !important; align-items: flex-start !important; padding: 20px; gap: 5px; overflow-x: visible; }
    .nav-item { width: 100%; font-size: 16px; padding: 14px 18px; }
    .nav-item.active { border-left: 3px solid var(--core-blue); }
    
    .sidebar-header { border-bottom: 1px solid var(--core-border); padding-top: 70px; }
    .sidebar-footer { position: relative !important; right: auto; top: auto; border-top: 1px solid var(--core-border) !important; padding: 20px; margin-top: auto; }
}

@media (max-width: 768px) {
    .page-title { font-size: 28px; }
    .wallet-section { padding: 24px; }
    .metamask-btn { padding: 16px 28px; font-size: 14px; }
    .wallet-actions { grid-template-columns: 1fr; }
    .modal-buttons { flex-direction: column; }
    .modal-content { padding: 25px 20px; }
    .network-selector { flex-direction: column; align-items: center; }
    .network-chip { width: 100%; max-width: 250px; justify-content: center; }
    .address-value { flex-direction: column; align-items: flex-start; gap: 8px; }
    .btn-copy { width: 100%; }
    .wallet-grid { grid-template-columns: 1fr; }
    .mined-coins-grid { grid-template-columns: 1fr; }
    .coin-actions { grid-template-columns: 1fr; }
}

.logo-icon img {
    width: 45px;
    height: auto;
}

</style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon"> <img src="images/logo1.png" alt="Logo" /></div>
                <span class="logo-text">MINERCORE</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
            <a href="games.php" class="nav-item">🎮 Games</a>
            <a href="store.php" class="nav-item">🛒 Store</a>
            <a href="market.php" class="nav-item">🛒 Marketplace</a>
            <a href="earning.php" class="nav-item">💵 ADS/PTC</a>
            <a href="history.php" class="nav-item">📜 History</a>
            <a href="wallet.php" class="nav-item active">💰 Wallet</a>
            <a href="profile.php" class="nav-item">👤 Profile</a>
            <a href="logout.php" class="nav-item">🚪 Logout</a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="user-status">
                        <span class="status-dot"></span>
                        <span>Online</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTAINER -->
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">MetaMask Wallet</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Dashboard</a> / Wallet
                </div>
            </div>

            <?php if (isset($_GET['success']) || $message): ?>
                <div class="alert alert-success">
                    ✅ <?php echo $message ? $message : 'Operation completed successfully!'; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['disconnected'])): ?>
                <div class="alert alert-success">
                    ✅ Wallet disconnected successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['changed'])): ?>
                <div class="alert alert-warning">
                    ⚠️ Wallet removed. You can now connect a new one.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- GRID DE CARTEIRAS E SALDOS -->
            <div class="wallet-grid">
                
                <!-- CARTEIRA CONECTADA (COMPACTA) -->
                <?php if (!empty($connected_wallets)): ?>
                    <?php foreach ($connected_wallets as $wallet): 
                        $explorer_url = 'https://polygonscan.com/address/' . $wallet['wallet_address'];
                    ?>
                    <div class="wallet-card-compact connected">
                        <div class="wallet-card-header-compact">
                            <div class="wallet-icon-small metamask">🦊</div>
                            <div>
                                <div class="wallet-title-compact">MetaMask</div>
                                <div class="wallet-subtitle-compact">Polygon Network</div>
                            </div>
                        </div>
                        <div class="wallet-address-box" style="margin-bottom: 15px;">
                            <div class="address-label">Address</div>
                            <div class="address-value">
                                <span class="full-address"><?php echo substr($wallet['wallet_address'], 0, 6) . '...' . substr($wallet['wallet_address'], -4); ?></span>
                                <button class="btn-copy" onclick="copyAddress('<?php echo $wallet['wallet_address']; ?>')">Copy</button>
                            </div>
                        </div>
                        <div class="wallet-actions">
                            <a href="<?php echo $explorer_url; ?>" target="_blank" class="btn-explorer" style="padding: 10px; font-size: 11px;">Explorer</a>
                            <button type="button" class="btn-change-wallet" style="padding: 10px; font-size: 11px;" onclick="showChangeModal()">Change</button>
                            <button type="button" class="btn-disconnect-wallet" style="padding: 10px; font-size: 11px;" onclick="showDisconnectModal(<?php echo $wallet['id']; ?>)">Disconnect</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- CONECTAR NOVA CARTEIRA (COMPACTA) -->
                    <div class="wallet-card-compact" id="connectCard">
                        <div class="wallet-card-header-compact">
                            <div class="wallet-icon-small metamask">🦊</div>
                            <div>
                                <div class="wallet-title-compact">Connect Wallet</div>
                                <div class="wallet-subtitle-compact">MetaMask Required</div>
                            </div>
                        </div>
                        
                        <div class="network-selector" style="margin-bottom: 15px;">
                            <div class="network-chip active" data-network="polygon" style="padding: 8px 16px; font-size: 12px; cursor: default;">
                                <span style="color: #8247e5;">●</span>
                                <span>Polygon Only</span>
                            </div>
                        </div>

                        <button class="metamask-btn" id="metamaskBtn" onclick="connectMetaMask()" style="width: 100%; padding: 14px; font-size: 14px;">
                            <span class="metamask-icon" style="width: 32px; height: 32px; font-size: 16px;">🦊</span>
                            <span>Connect MetaMask</span>
                        </button>

                        <div class="not-installed" id="notInstalled" style="display: none; padding: 10px; font-size: 12px;">
                            <p>⚠️ MetaMask not detected! <a href="https://metamask.io/download/" target="_blank">Install here</a></p>
                        </div>

                        <div class="error-message" id="errorMessage" style="margin-top: 10px; padding: 10px; font-size: 12px;">
                            <span id="errorText"></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- USDC ONLINE - CORRIGIDO -->
                <div class="wallet-card-compact usdc-online-card">
                    <div class="wallet-card-header-compact">
                        <div class="wallet-icon-small usdc">💵</div>
                        <div>
                            <div class="wallet-title-compact">USDC Online</div>
                            <div class="wallet-subtitle-compact">Live Blockchain Balance</div>
                        </div>
                    </div>
                    
                    <div class="balance-display">
                        <div class="balance-row" style="border-bottom: none;">
                            <div class="balance-label">
                                <span style="color: #8247e5;">●</span>
                                <span>Polygon USDC</span>
                            </div>
                            <div class="balance-value loading" id="usdc-polygon">Loading...</div>
                        </div>
                    </div>

                    <div id="balance-status" style="text-align: center; color: var(--core-text-dim); font-size: 12px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px; margin-top: 10px;">
                        Connect wallet to view balance
                    </div>
                </div>

                <!-- USDC OFFLINE -->
                <div class="wallet-card-compact usdc-offline-card">
                    <div class="wallet-card-header-compact">
                        <div class="wallet-icon-small offline">🏦</div>
                        <div>
                            <div class="wallet-title-compact">USDC Offline</div>
                            <div class="wallet-subtitle-compact">Platform Balance</div>
                        </div>
                    </div>
                    
                    <div class="balance-display" style="margin-bottom: 15px;">
                        <div class="balance-row" style="border-bottom: none;">
                            <div class="balance-label">Available Balance</div>
                            <div class="balance-value" style="color: var(--core-purple);">
                                $<?php echo number_format($balances['usdc_offline'], 8); ?>
                            </div>
                        </div>
                    </div>

                    <button class="deposit-btn" onclick="openDepositModal()">
                        <span>💰</span>
                        <span>Deposit USDC</span>
                    </button>
                </div>

            </div>

            <!-- MOEDAS MINERADAS -->
            <div class="mined-coins-section">
                <h2 class="section-title">⛏️ Mined Coins</h2>
                
                <div class="mined-coins-grid">
                    <!-- MCORE -->
                    <div class="mined-coin-card">
                        <div class="coin-icon">⚡</div>
                        <div class="coin-name">MCORE</div>
                        <div class="coin-balance"><?php echo number_format($balances['mcore'], 8); ?></div>
                        <div class="coin-actions">
                            <button class="btn-saque" onclick="openWithdrawModal('MCORE', <?php echo $balances['mcore']; ?>)">Withdraw</button>
                            <button class="btn-convert" onclick="openConvertModal('MCORE', <?php echo $balances['mcore']; ?>)">Convert</button>
                        </div>
                    </div>

                    <!-- BTC -->
                    <div class="mined-coin-card">
                        <div class="coin-icon" style="background: linear-gradient(135deg, #f7931a, #ffad33);">₿</div>
                        <div class="coin-name">Bitcoin</div>
                        <div class="coin-balance"><?php echo number_format($balances['btc'], 8); ?></div>
                        <div class="coin-actions">
                            <button class="btn-saque" onclick="openWithdrawModal('BTC', <?php echo $balances['btc']; ?>)">Withdraw</button>
                            <button class="btn-convert" onclick="openConvertModal('BTC', <?php echo $balances['btc']; ?>)">Convert</button>
                        </div>
                    </div>

                    <!-- ETH -->
                    <div class="mined-coin-card">
                        <div class="coin-icon" style="background: linear-gradient(135deg, #627eea, #8c9eff);">Ξ</div>
                        <div class="coin-name">Ethereum</div>
                        <div class="coin-balance"><?php echo number_format($balances['eth'], 8); ?></div>
                        <div class="coin-actions">
                            <button class="btn-saque" onclick="openWithdrawModal('ETH', <?php echo $balances['eth']; ?>)">Withdraw</button>
                            <button class="btn-convert" onclick="openConvertModal('ETH', <?php echo $balances['eth']; ?>)">Convert</button>
                        </div>
                    </div>

                    <!-- SOL -->
                    <div class="mined-coin-card">
                        <div class="coin-icon" style="background: linear-gradient(135deg, #00ffa3, #dc1fff);">◎</div>
                        <div class="coin-name">Solana</div>
                        <div class="coin-balance"><?php echo number_format($balances['sol'], 8); ?></div>
                        <div class="coin-actions">
                            <button class="btn-saque" onclick="openWithdrawModal('SOL', <?php echo $balances['sol']; ?>)">Withdraw</button>
                            <button class="btn-convert" onclick="openConvertModal('SOL', <?php echo $balances['sol']; ?>)">Convert</button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL: DEPOSIT USDC - APENAS POLYGON -->
    <div class="modal-overlay" id="depositModal">
        <div class="modal-content">
            <div class="modal-icon">🏦</div>
            <h3 class="modal-title">Deposit USDC</h3>
            <p class="modal-text">
                Send USDC to the address below to credit your offline balance.<br><br>
                <span style="color: var(--core-red); font-size: 12px;">⚠️ Only send USDC on Polygon Network</span>
            </p>
            
            <!-- Campo Amount adicionado -->
            <div style="margin-bottom: 20px; text-align: left;">
                <label style="display: block; font-size: 12px; color: var(--core-text-dim); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Amount (USDC)</label>
                <input type="number" id="depositAmount" placeholder="0.00" step="0.01" min="0.01" 
                    style="width: 100%; padding: 14px; background: var(--core-black); border: 2px solid var(--core-border); border-radius: 10px; color: var(--core-text-bright); font-family: 'JetBrains Mono', monospace; font-size: 16px; outline: none; transition: all 0.3s;"
                    onfocus="this.style.borderColor='var(--core-blue)'" 
                    onblur="this.style.borderColor='var(--core-border)'">
            </div>
            
            <div class="wallet-address-box" style="margin-bottom: 20px;">
                <div class="address-label" style="margin-bottom: 8px;">Deposit Address (Polygon)</div>
                <div class="address-value" style="justify-content: center; font-size: 14px; flex-wrap: wrap; gap: 10px;">
                    <span id="depositAddress" style="word-break: break-all;">0x1234...5678</span>
                    <button class="btn-copy" onclick="copyDepositAddress()" style="flex-shrink: 0;">Copy</button>
                </div>
            </div>
            
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeDepositModal()">Close</button>
                <button class="modal-btn modal-btn-confirm" onclick="openMetaMaskForDeposit()">
                    Open MetaMask
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: SAQUE -->
    <div class="modal-overlay" id="withdrawModal">
        <div class="modal-content warning">
            <div class="modal-icon">💸</div>
            <h3 class="modal-title" style="color: var(--core-orange);">Withdraw <span id="withdrawCoin">MCORE</span></h3>
            <p class="modal-text">
                Available: <span class="modal-highlight" id="withdrawAmount">0.00000000</span><br><br>
                Enter wallet address for withdrawal:
            </p>
            <input type="text" id="withdrawAddress" placeholder="0x..." style="width: 100%; padding: 12px; background: var(--core-black); border: 2px solid var(--core-border); border-radius: 8px; color: var(--core-text-bright); font-family: 'JetBrains Mono', monospace; margin-bottom: 20px; font-size: 14px;">
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeWithdrawModal()">Cancel</button>
                <button class="modal-btn modal-btn-confirm" onclick="confirmWithdraw()">Confirm Withdraw</button>
            </div>
        </div>
    </div>

    <!-- MODAL: CONVERTER -->
    <div class="modal-overlay" id="convertModal">
        <div class="modal-content">
            <div class="modal-icon">🔄</div>
            <h3 class="modal-title">Convert <span id="convertCoin">MCORE</span> to USDC</h3>
            <p class="modal-text">
                Available: <span class="modal-highlight" id="convertAmount">0.00000000</span><br><br>
                This will convert your mined coins to USDC Offline balance at current market rate.
            </p>
            <input type="number" id="convertAmountInput" placeholder="Amount to convert" step="0.00000001" style="width: 100%; padding: 12px; background: var(--core-black); border: 2px solid var(--core-border); border-radius: 8px; color: var(--core-text-bright); font-family: 'JetBrains Mono', monospace; margin-bottom: 20px; font-size: 14px;">
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeConvertModal()">Cancel</button>
                <button class="modal-btn modal-btn-confirm" onclick="confirmConvert()">Convert Now</button>
            </div>
        </div>
    </div>

    <!-- MODAL: SUBSTITUIR CARTEIRA -->
    <div class="modal-overlay" id="confirmReplaceModal">
        <div class="modal-content warning">
            <div class="modal-icon">⚠️</div>
            <h3 class="modal-title" style="color: var(--core-orange);">Replace Wallet?</h3>
            <p class="modal-text">
                You already have a wallet saved in the system:<br>
                <span class="modal-highlight" id="existingWalletAddress"></span><br><br>
                Do you want to replace it with the new wallet?<br>
                <span style="color: var(--core-red); font-size: 13px; font-weight: 600;">This action cannot be undone.</span>
            </p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeReplaceModal()">
                    Keep Current
                </button>
                <button class="modal-btn modal-btn-confirm" onclick="confirmReplace()">
                    Yes, Replace
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: CHANGE WALLET -->
    <div class="modal-overlay" id="changeWalletModal">
        <div class="modal-content warning">
            <div class="modal-icon">🔄</div>
            <h3 class="modal-title" style="color: var(--core-orange);">Change Wallet?</h3>
            <p class="modal-text">
                This will <strong>remove your current wallet</strong> from the system and allow you to connect a new one.<br><br>
                Your wallet will remain in MetaMask, but will be disconnected from this site.<br><br>
                <span style="color: var(--core-text-bright);">Do you want to continue?</span>
            </p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeChangeModal()">
                    Cancel
                </button>
                <button class="modal-btn modal-btn-confirm" onclick="confirmChangeWallet()">
                    Yes, Change
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: DISCONNECT WALLET -->
    <div class="modal-overlay" id="disconnectModal">
        <div class="modal-content danger">
            <div class="modal-icon">🔌</div>
            <h3 class="modal-title" style="color: var(--core-red);">Disconnect Wallet?</h3>
            <p class="modal-text">
                This will remove the wallet from your account on this site.<br><br>
                <span style="color: var(--core-text-dim);">Your wallet will remain connected to MetaMask and you can reconnect it anytime.</span>
            </p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeDisconnectModal()">
                    Cancel
                </button>
                <button class="modal-btn modal-btn-danger" onclick="confirmDisconnect()">
                    Disconnect
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL: CARREGAMENTO -->
    <div class="modal-overlay" id="connectingModal">
        <div class="modal-content">
            <div class="modal-spinner"></div>
            <h3 class="modal-title">Connecting...</h3>
            <p class="modal-text">Please confirm in MetaMask</p>
        </div>
    </div>

    <!-- FORMS HIDDEN -->
    <form id="disconnectForm" method="POST" style="display: none;">
        <input type="hidden" name="wallet_id" id="disconnectWalletId" value="">
        <input type="hidden" name="disconnect_wallet" value="1">
    </form>

    <form id="changeForm" method="POST" style="display: none;">
        <input type="hidden" name="change_wallet" value="1">
    </form>

    <script>
        // CONFIGURAÇÕES CORRIGIDAS - APENAS POLYGON
        const CONFIG = {
            // USDC Nativo da Circle no Polygon (endereço correto)
            usdcContract: '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            
            // Endereço da carteira da equipe (SUBSTITUA PELO ENDEREÇO REAL)
            teamWallet: '0x1234567890123456789012345678901234567890',
            
            // RPC Polygon - SEM ESPAÇOS!
            polygonRpc: 'https://polygon-rpc.com',
            
            // ABI completo para USDC ERC-20
            usdcAbi: [
                {"constant":true,"inputs":[{"name":"_owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"balance","type":"uint256"}],"type":"function"},
                {"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint8"}],"type":"function"},
                {"constant":false,"inputs":[{"name":"_to","type":"address"},{"name":"_value","type":"uint256"}],"name":"transfer","outputs":[{"name":"","type":"bool"}],"type":"function"},
                {"constant":true,"inputs":[],"name":"symbol","outputs":[{"name":"","type":"string"}],"type":"function"},
                {"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"type":"function"}
            ]
        };

        // VARIÁVEIS GLOBAIS
        let web3 = null;
        let currentAddress = null;
        let hasExistingWallet = <?php echo !empty($connected_wallets) ? 'true' : 'false'; ?>;
        let existingWalletAddress = '<?php echo !empty($connected_wallets) ? $connected_wallets[0]['wallet_address'] : ''; ?>';
        let pendingSave = false;
        let tempWalletId = null;
        let currentCoin = '';
        let currentBalance = 0;
        let isLoadingBalance = false;

        // INICIALIZAÇÃO
        window.addEventListener('load', function() {
            checkMetaMask();
            // Sempre tenta carregar o saldo se MetaMask estiver disponível
            if (window.ethereum) {
                initBalanceLoading();
            }
        });

        // NOVA FUNÇÃO: Inicializa carregamento de saldo
        async function initBalanceLoading() {
            // Verifica se já tem permissão das contas
            try {
                const accounts = await window.ethereum.request({ method: 'eth_accounts' });
                if (accounts.length > 0) {
                    currentAddress = accounts[0];
                    document.getElementById('balance-status').textContent = 'Wallet connected: ' + accounts[0].substring(0, 6) + '...' + accounts[0].substring(-4);
                    loadUSDCBalance();
                } else {
                    document.getElementById('balance-status').textContent = 'Click Connect to view balance';
                    // Escuta por mudanças de conta
                    window.ethereum.on('accountsChanged', handleAccountsChanged);
                }
            } catch (error) {
                console.error('Error checking accounts:', error);
                document.getElementById('balance-status').textContent = 'Error checking wallet';
            }
        }

        function handleAccountsChanged(accounts) {
            if (accounts.length === 0) {
                // Usário desconectou
                document.getElementById('usdc-polygon').textContent = 'Connect Wallet';
                document.getElementById('usdc-polygon').className = 'balance-value';
                document.getElementById('balance-status').textContent = 'Wallet disconnected';
                currentAddress = null;
            } else {
                currentAddress = accounts[0];
                document.getElementById('balance-status').textContent = 'Wallet: ' + accounts[0].substring(0, 6) + '...';
                loadUSDCBalance();
            }
        }

        function checkMetaMask() {
            const hasMetaMask = window.ethereum && window.ethereum.isMetaMask;
            
            if (!hasMetaMask) {
                const btn = document.getElementById('metamaskBtn');
                if (btn) {
                    btn.style.display = 'none';
                    document.getElementById('notInstalled').style.display = 'block';
                }
            }
        }

        // ========== USDC BALANCE CORRIGIDO - APENAS POLYGON ==========
        async function loadUSDCBalance() {
            if (isLoadingBalance) return;
            isLoadingBalance = true;
            
            const balanceElement = document.getElementById('usdc-polygon');
            const statusElement = document.getElementById('balance-status');
            
            try {
                balanceElement.textContent = 'Loading...';
                balanceElement.className = 'balance-value loading';
                
                // Cria instância Web3 com RPC Polygon (mais confiável que injetado para leitura)
                const polygonWeb3 = new Web3(CONFIG.polygonRpc);
                
                // Se temos endereço do MetaMask, usa ele. Senão, usa o do banco
                let addressToCheck = currentAddress;
                
                <?php if (!empty($connected_wallets)): ?>
                if (!addressToCheck) {
                    addressToCheck = '<?php echo $connected_wallets[0]['wallet_address']; ?>';
                }
                <?php endif; ?>
                
                if (!addressToCheck) {
                    balanceElement.textContent = 'Connect Wallet';
                    balanceElement.className = 'balance-value';
                    statusElement.textContent = 'No wallet connected';
                    isLoadingBalance = false;
                    return;
                }
                
                console.log('Checking USDC balance for:', addressToCheck);
                
                // Cria contrato USDC
                const usdcContract = new polygonWeb3.eth.Contract(CONFIG.usdcAbi, CONFIG.usdcContract);
                
                // Busca saldo e decimais em paralelo
                const [balance, decimals] = await Promise.all([
                    usdcContract.methods.balanceOf(addressToCheck).call(),
                    usdcContract.methods.decimals().call()
                ]);
                
                console.log('Raw balance:', balance, 'Decimals:', decimals);
                
                // Formata o saldo
                const formattedBalance = (Number(balance) / (10 ** Number(decimals))).toFixed(8);
                
                // Atualiza UI
                if (Number(formattedBalance) > 0) {
                    balanceElement.textContent = '$' + formattedBalance;
                    balanceElement.className = 'balance-value';
                    balanceElement.style.color = 'var(--core-green)';
                } else {
                    balanceElement.textContent = '$0.00000000';
                    balanceElement.className = 'balance-value zero';
                }
                
                statusElement.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
                
            } catch (error) {
                console.error('Error loading USDC balance:', error);
                balanceElement.textContent = 'Error';
                balanceElement.className = 'balance-value error';
                statusElement.textContent = 'Failed to load balance. Retrying...';
                
                // Tenta novamente em 3 segundos
                setTimeout(() => {
                    isLoadingBalance = false;
                    loadUSDCBalance();
                }, 3000);
                return;
            }
            
            isLoadingBalance = false;
        }

        // ========== DEPOSIT CORRIGIDO ==========
        function openDepositModal() {
            document.getElementById('depositModal').classList.add('active');
            document.getElementById('depositAddress').textContent = CONFIG.teamWallet;
        }

        function closeDepositModal() {
            document.getElementById('depositModal').classList.remove('active');
            document.getElementById('depositAmount').value = '';
        }

        function copyDepositAddress() {
            copyAddress(CONFIG.teamWallet);
        }

        // Converte valor para unidades USDC (6 decimais)
        function toUSDCUnits(amount) {
            const cleanAmount = amount.toString().trim();
            const parts = cleanAmount.split('.');
            let integerPart = parts[0] || '0';
            let decimalPart = parts[1] || '';
            
            integerPart = integerPart.replace(/^0+/, '') || '0';
            
            if (decimalPart.length > 6) {
                decimalPart = decimalPart.substring(0, 6);
            } else {
                while (decimalPart.length < 6) {
                    decimalPart += '0';
                }
            }
            
            return (integerPart + decimalPart).replace(/^0+/, '') || '0';
        }

        async function openMetaMaskForDeposit() {
            const amount = document.getElementById('depositAmount').value;
            
            if (!amount || parseFloat(amount) <= 0) {
                showToast('Please enter a valid amount!');
                document.getElementById('depositAmount').style.borderColor = 'var(--core-red)';
                return;
            }
            
            if (!window.ethereum) {
                showToast('MetaMask not installed!');
                return;
            }
            
            try {
                // Força switch para Polygon antes do depósito
                await switchToPolygon();
                
                web3 = new Web3(window.ethereum);
                
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                if (accounts.length === 0) {
                    showToast('Please connect your wallet first!');
                    return;
                }
                
                const fromAddress = accounts[0];
                const amountInUnits = toUSDCUnits(amount);
                
                console.log('Sending', amount, 'USDC =', amountInUnits, 'units');
                
                const usdcContract = new web3.eth.Contract(CONFIG.usdcAbi, CONFIG.usdcContract);
                
                const transferData = usdcContract.methods.transfer(
                    CONFIG.teamWallet, 
                    amountInUnits
                ).encodeABI();
                
                // Estima gas
                let gasLimit;
                try {
                    gasLimit = await web3.eth.estimateGas({
                        from: fromAddress,
                        to: CONFIG.usdcContract,
                        data: transferData,
                        value: '0x0'
                    });
                    gasLimit = Math.floor(gasLimit * 1.2);
                } catch (gasError) {
                    console.warn('Gas estimation failed, using default:', gasError);
                    gasLimit = 100000;
                }
                
                const txHash = await window.ethereum.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: fromAddress,
                        to: CONFIG.usdcContract,
                        data: transferData,
                        value: '0x0',
                        gas: web3.utils.toHex(gasLimit)
                    }]
                });
                
                showToast('Transaction sent! Hash: ' + txHash.substring(0, 10) + '...');
                closeDepositModal();
                
                // Salva no backend
                await saveDepositTransaction(txHash, amount, 'polygon');
                
            } catch (error) {
                if (error.code === 4001) {
                    showToast('Transaction rejected by user.');
                } else {
                    console.error('Deposit error:', error);
                    showToast('Error: ' + (error.message || 'Unknown error'));
                }
            }
        }

        async function saveDepositTransaction(txHash, amount, network) {
            try {
                await fetch('api/deposit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: <?php echo $user_id; ?>,
                        tx_hash: txHash,
                        amount: amount,
                        network: network,
                        status: 'pending'
                    })
                });
            } catch (e) {
                console.error('Failed to save deposit:', e);
            }
        }

        // ========== SAQUE ==========
        function openWithdrawModal(coin, balance) {
            currentCoin = coin;
            currentBalance = balance;
            document.getElementById('withdrawCoin').textContent = coin;
            document.getElementById('withdrawAmount').textContent = balance.toFixed(8) + ' ' + coin;
            document.getElementById('withdrawModal').classList.add('active');
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').classList.remove('active');
        }

        function confirmWithdraw() {
            const address = document.getElementById('withdrawAddress').value;
            if (!address || address.length < 10) {
                showToast('Please enter a valid address!');
                return;
            }
            
            showToast('Withdrawal request submitted! Processing...');
            closeWithdrawModal();
            
            fetch('api/withdraw.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    coin: currentCoin,
                    amount: currentBalance,
                    address: address,
                    user_id: <?php echo $user_id; ?>
                })
            });
        }

        // ========== CONVERTER ==========
        function openConvertModal(coin, balance) {
            currentCoin = coin;
            currentBalance = balance;
            document.getElementById('convertCoin').textContent = coin;
            document.getElementById('convertAmount').textContent = balance.toFixed(8) + ' ' + coin;
            document.getElementById('convertAmountInput').max = balance;
            document.getElementById('convertModal').classList.add('active');
        }

        function closeConvertModal() {
            document.getElementById('convertModal').classList.remove('active');
        }

        function confirmConvert() {
            const amount = parseFloat(document.getElementById('convertAmountInput').value);
            if (!amount || amount <= 0 || amount > currentBalance) {
                showToast('Invalid amount!');
                return;
            }
            
            showToast('Converting ' + amount + ' ' + currentCoin + ' to USDC...');
            closeConvertModal();
            
            fetch('api/convert.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    coin: currentCoin,
                    amount: amount,
                    user_id: <?php echo $user_id; ?>
                })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    showToast('Conversion successful! USDC credited.');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }

        // ========== MODAIS ==========
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function showChangeModal() {
            openModal('changeWalletModal');
        }

        function closeChangeModal() {
            closeModal('changeWalletModal');
        }

        function confirmChangeWallet() {
            closeChangeModal();
            setTimeout(function() {
                document.getElementById('changeForm').submit();
            }, 200);
        }

        function showDisconnectModal(walletId) {
            tempWalletId = walletId;
            openModal('disconnectModal');
        }

        function closeDisconnectModal() {
            closeModal('disconnectModal');
            tempWalletId = null;
        }

        function confirmDisconnect() {
            if (!tempWalletId) return;
            closeModal('disconnectModal');
            document.getElementById('disconnectWalletId').value = tempWalletId;
            document.getElementById('disconnectForm').submit();
        }

        function showReplaceModal() {
            document.getElementById('existingWalletAddress').textContent = existingWalletAddress;
            openModal('confirmReplaceModal');
        }

        function closeReplaceModal() {
            closeModal('confirmReplaceModal');
            currentAddress = null;
            pendingSave = false;
        }

        function confirmReplace() {
            closeModal('confirmReplaceModal');
            pendingSave = true;
            saveWallet();
        }

        // ========== CONEXÃO METAMASK ==========
        async function connectMetaMask() {
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            
            errorDiv.classList.remove('show');

            if (!window.ethereum || !window.ethereum.isMetaMask) {
                errorText.textContent = 'MetaMask not found. Please install it.';
                errorDiv.classList.add('show');
                return;
            }

            openModal('connectingModal');

            try {
                web3 = new Web3(window.ethereum);

                const accounts = await window.ethereum.request({
                    method: 'eth_requestAccounts'
                });

                if (!accounts || accounts.length === 0) {
                    throw new Error('No accounts found.');
                }

                currentAddress = accounts[0];
                
                // Atualiza status imediatamente
                document.getElementById('balance-status').textContent = 'Connected: ' + currentAddress.substring(0, 6) + '...';
                
                closeModal('connectingModal');

                if (hasExistingWallet && existingWalletAddress.toLowerCase() !== currentAddress.toLowerCase()) {
                    showReplaceModal();
                } else {
                    pendingSave = false;
                    saveWallet();
                }

            } catch (error) {
                console.error('Error:', error);
                closeModal('connectingModal');
                
                let msg = 'Connection failed.';
                if (error.code === 4001) {
                    msg = '❌ You rejected the connection.';
                }
                
                errorText.textContent = msg;
                errorDiv.classList.add('show');
            }
        }

        async function saveWallet() {
            if (!currentAddress) return;
            
            openModal('connectingModal');

            try {
                await switchToPolygon();
                
                const message = "MinerCore Wallet Connection\nUser ID: <?php echo $user_id; ?>\nNetwork: POLYGON\nAddress: " + currentAddress + "\nTimestamp: " + Date.now();
                
                const signature = await web3.eth.personal.sign(message, currentAddress, '');
                
                const response = await fetch('connect_wallet.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: <?php echo $user_id; ?>,
                        wallet_address: currentAddress,
                        wallet_type: 'metamask',
                        network: 'polygon',
                        signature: signature,
                        message: message,
                        replace: pendingSave
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    window.location.reload();
                } else {
                    throw new Error(result.message);
                }

            } catch (error) {
                closeModal('connectingModal');
                document.getElementById('errorText').textContent = error.message || 'Failed to save';
                document.getElementById('errorMessage').classList.add('show');
            }
        }

        async function switchToPolygon() {
            const polygonConfig = { 
                chainId: '0x89', 
                chainName: 'Polygon Mainnet', 
                nativeCurrency: { name: 'MATIC', symbol: 'MATIC', decimals: 18 }, 
                rpcUrls: ['https://polygon-rpc.com'],
                blockExplorerUrls: ['https://polygonscan.com']
            };
            
            try {
                await window.ethereum.request({
                    method: 'wallet_switchEthereumChain',
                    params: [{ chainId: polygonConfig.chainId }],
                });
            } catch (switchError) {
                if (switchError.code === 4902) {
                    await window.ethereum.request({
                        method: 'wallet_addEthereumChain',
                        params: [polygonConfig],
                    });
                } else if (switchError.code !== 4001) {
                    throw switchError;
                }
            }
        }

        function copyAddress(address) {
            navigator.clipboard.writeText(address).then(function() {
                showToast('Address copied!');
            }).catch(function() {
                const t = document.createElement('textarea');
                t.value = address;
                document.body.appendChild(t);
                t.select();
                document.execCommand('copy');
                document.body.removeChild(t);
                showToast('Address copied!');
            });
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--core-green);
                color: var(--core-black);
                padding: 14px 28px;
                border-radius: 10px;
                font-family: 'Orbitron', sans-serif;
                font-weight: 700;
                font-size: 13px;
                z-index: 9999;
                box-shadow: 0 10px 30px var(--core-green-glow);
                animation: slideUp 0.3s ease;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() {
                    if (toast.parentNode) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 2500);
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        // Event listeners para fechar modais ao clicar fora
        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this && !this.id.includes('connecting')) {
                    this.classList.remove('active');
                }
            });
        });
        
        // Limpa URL params
        if (window.location.search.includes('disconnected=1') || 
            window.location.search.includes('changed=1') || 
            window.location.search.includes('success=1')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Recarrega saldo a cada 30 segundos se conectado
        setInterval(() => {
            if (currentAddress && !isLoadingBalance) {
                loadUSDCBalance();
            }
        }, 30000);
    </script>
</body>
</html>