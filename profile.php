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
    
    // DELETE ACCOUNT
    if (isset($_POST['delete_account'])) {
        $password = $_POST['delete_password'];
        
        if (!password_verify($password, $user['password_hash'])) {
            $error = 'Incorrect password. Account deletion cancelled.';
        } else {
            // Deleta dados do usuário em ordem (foreign keys)
            $pdo->beginTransaction();
            try {
                // Deleta histórico
                $stmt = $pdo->prepare("DELETE FROM mining_history WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Deleta miners
                $stmt = $pdo->prepare("DELETE FROM user_miners WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Deleta boosts
                $stmt = $pdo->prepare("DELETE FROM active_boosts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Deleta usuário
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();
                
                // Destroi sessão e redireciona
                session_destroy();
                header("Location: index.php?deleted=1");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error deleting account. Please contact support.';
            }
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
    /* FUNDO CAVERNA */
    --core-black: #1b0f14;
    --core-dark: #24161d;
    --core-darker: #160c11;
    --core-card: #2b1b23;

    /* CRISTAL CIANO */
    --core-blue: #2de2e6;
    --core-blue-glow: rgba(45, 226, 230, 0.35);
    --core-blue-dark: #16b3b8;

    /* ÂMBAR / LARANJA DO MASCOTE */
    --core-orange: #ff9f1c;

    /* OURO QUENTE */
    --core-yellow: #ffbf3c;

    /* VERDE CRISTAL */
    --core-green: #3cffb3;
    --core-green-glow: rgba(60, 255, 179, 0.3);

    /* ALERTA / VERMELHO PERIGO */
    --core-red: #ff4d4d;
    --core-red-glow: rgba(255, 77, 77, 0.5);
    --core-red-dark: #cc3742;

    /* TEXTO - GARANTINDO CORES CLARAS */
    --core-text: #f5e6d3;
    --core-text-dim: #bfae9c;
    --core-text-bright: #ffffff;

    /* BORDA NEON CRISTAL */
    --core-border: rgba(45, 226, 230, 0.18);
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

/* Navegação horizontal centralizada e com fonte maior */
.sidebar-nav {
    display: flex !important;
    flex-direction: row !important;
    align-items: center;
    justify-content: center;
    gap: 30px;
    overflow-x: auto;
    padding: 20px 30px;
}

.nav-section {
    margin-bottom: 0 !important;
    display: flex;
    gap: 12px;
}

.nav-title {
    display: none !important;
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
    margin-bottom: 0 !important;
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

.nav-icon {
    width: 24px;
    text-align: center;
    font-size: 22px;
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

/* WRAPPER COM ANÚNCIOS - 3 COLUNAS */
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
    color: var(--core-text);
}

.ad-size {
    font-size: 12px;
    margin-top: 8px;
    opacity: 0.7;
    color: var(--core-text-dim);
}

/* MAIN CONTENT */
.main-content {
    flex: 1;
    margin-left: 0 !important;
    max-width: 100% !important;
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
    color: var(--core-text-bright);
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
    color: var(--core-text-bright);
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
    color: var(--core-text-bright);
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
    color: var(--core-text-bright);
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
    color: var(--core-text-bright);
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
    color: var(--core-text-dim);
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
    background: linear-gradient(135deg, var(--core-red), var(--core-red-dark));
    color: white;
}

.btn-danger:hover {
    box-shadow: 0 10px 30px var(--core-red-glow);
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

/* MODAL DELETE ACCOUNT */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(5px);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease;
}

.modal-overlay.active {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-delete {
    background: var(--core-card);
    border: 3px solid var(--core-red);
    border-radius: 20px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    position: relative;
    box-shadow: 
        0 0 50px var(--core-red-glow),
        0 0 100px rgba(255, 77, 77, 0.3),
        inset 0 0 60px rgba(255, 77, 77, 0.1);
    animation: slideIn 0.3s ease, pulseBorder 2s infinite;
}

@keyframes slideIn {
    from { 
        transform: translateY(-50px) scale(0.9);
        opacity: 0;
    }
    to { 
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

@keyframes pulseBorder {
    0%, 100% { 
        box-shadow: 
            0 0 50px var(--core-red-glow),
            0 0 100px rgba(255, 77, 77, 0.3),
            inset 0 0 60px rgba(255, 77, 77, 0.1);
    }
    50% { 
        box-shadow: 
            0 0 70px var(--core-red-glow),
            0 0 120px rgba(255, 77, 77, 0.4),
            inset 0 0 80px rgba(255, 77, 77, 0.15);
    }
}

.modal-header {
    text-align: center;
    margin-bottom: 30px;
}

.modal-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--core-red), var(--core-red-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    margin: 0 auto 20px;
    box-shadow: 0 0 30px var(--core-red-glow);
    animation: shake 0.5s ease-in-out infinite alternate;
}

@keyframes shake {
    from { transform: rotate(-5deg); }
    to { transform: rotate(5deg); }
}

.modal-title {
    font-family: 'Orbitron', sans-serif;
    font-size: 28px;
    font-weight: 900;
    color: var(--core-red);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 10px;
    text-shadow: 0 0 20px var(--core-red-glow);
}

.modal-subtitle {
    color: var(--core-text-dim);
    font-size: 16px;
    line-height: 1.6;
}

.warning-box {
    background: rgba(255, 77, 77, 0.1);
    border: 2px solid var(--core-red);
    border-radius: 12px;
    padding: 20px;
    margin: 25px 0;
}

.warning-title {
    color: var(--core-red);
    font-weight: 700;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.warning-list {
    list-style: none;
    color: var(--core-text-dim);
    font-size: 14px;
}

.warning-list li {
    padding: 5px 0;
    padding-left: 20px;
    position: relative;
}

.warning-list li:before {
    content: "⚠️";
    position: absolute;
    left: 0;
}

.modal-form {
    margin-top: 25px;
}

.modal-form label {
    color: var(--core-red);
    font-weight: 700;
}

.modal-form input {
    background: var(--core-darker);
    border: 2px solid var(--core-red);
    color: var(--core-text-bright);
}

.modal-form input:focus {
    border-color: var(--core-red);
    box-shadow: 0 0 20px var(--core-red-glow);
}

.modal-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.modal-buttons .btn {
    flex: 1;
    padding: 16px;
    font-size: 16px;
}

.btn-cancel {
    background: transparent;
    border: 2px solid var(--core-text-dim);
    color: var(--core-text-dim);
}

.btn-cancel:hover {
    background: var(--core-text-dim);
    color: var(--core-black);
    box-shadow: none;
}

.btn-confirm-delete {
    background: linear-gradient(135deg, var(--core-red), var(--core-red-dark));
    border: 2px solid var(--core-red);
    color: white;
}

.btn-confirm-delete:hover {
    background: var(--core-red);
    box-shadow: 0 10px 40px var(--core-red-glow);
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    background: none;
    border: none;
    color: var(--core-text-dim);
    font-size: 30px;
    cursor: pointer;
    transition: all 0.3s;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-modal:hover {
    color: var(--core-red);
    background: rgba(255, 77, 77, 0.1);
    transform: rotate(90deg);
}

/* MENU HAMBURGUER - MESMO PADRÃO DAS OUTRAS PÁGINAS */
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

/* RESPONSIVE */
@media (max-width: 1400px) {
    .dashboard-wrapper {
        grid-template-columns: 250px 1fr 250px;
    }
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 1200px) {
    .dashboard-wrapper {
        grid-template-columns: 200px 1fr 200px;
    }
    .content-grid { grid-template-columns: 1fr; }
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
    
    .profile-header-card { flex-direction: column; text-align: center; }
    .profile-meta { justify-content: center; }
    
    .modal-delete {
        padding: 30px 20px;
    }
    
    .modal-title {
        font-size: 22px;
    }
    
    .modal-buttons {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .stats-row { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: 1fr; }
    .page-title { font-size: 24px; }
    .profile-name { font-size: 24px; }
    .referral-code { font-size: 24px; }
    
    .dashboard-wrapper {
        padding: 0;
        gap: 15px;
    }
    
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
            <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
            <a href="games.php" class="nav-item">🎮 Games</a>
            <a href="store.php" class="nav-item">🛒 Store</a>
            <a href="market.php" class="nav-item">🛒 Marketplace</a>
            <a href="earning.php" class="nav-item">💵 ADS/PTC</a>
            <a href="history.php" class="nav-item">📜 History</a>
            <a href="wallet.php" class="nav-item">💰 Wallet</a>
            <a href="profile.php" class="nav-item active">👤 Profile</a>
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
                        <button type="button" class="btn btn-danger" onclick="openDeleteModal()">
                            Delete Account
                        </button>
                    </div>
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

    <!-- MODAL DELETE ACCOUNT -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-delete">
            <button class="close-modal" onclick="closeDeleteModal()">×</button>
            
            <div class="modal-header">
                <div class="modal-icon">⚠️</div>
                <h2 class="modal-title">Delete Account?</h2>
                <p class="modal-subtitle">
                    This action is <strong style="color: var(--core-red);">PERMANENT</strong> and cannot be undone. 
                    You will lose everything.
                </p>
            </div>
            
            <div class="warning-box">
                <div class="warning-title">
                    <span>🚨</span> You will lose:
                </div>
                <ul class="warning-list">
                    <li>All your miners and equipment</li>
                    <li>Your entire BTC balance (<?= number_format($user['total_mined'], 8) ?> BTC)</li>
                    <li>All mining history and statistics</li>
                    <li>Your referral code and commissions</li>
                    <li>Account access permanently</li>
                </ul>
            </div>
            
            <form method="POST" action="" class="modal-form" onsubmit="return confirmDelete()">
                <div class="form-group">
                    <label>Enter your password to confirm deletion:</label>
                    <input type="password" name="delete_password" id="deletePassword" required 
                           placeholder="Type your password here" autocomplete="off">
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">
                        Cancel, Keep Account
                    </button>
                    <button type="submit" name="delete_account" class="btn btn-confirm-delete">
                        Yes, Delete Forever
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle - MESMA FUNÇÃO DAS OUTRAS PÁGINAS
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function copyReferral() {
            const code = document.getElementById('refCode').textContent;
            const link = '<?= 'https://' . $_SERVER['HTTP_HOST'] ?>/register.php?ref=' + code;
            
            navigator.clipboard.writeText(link).then(() => {
                alert('Referral link copied to clipboard!');
            }).catch(() => {
                const textarea = document.createElement('textarea');
                textarea.value = link;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Referral link copied to clipboard!');
            });
        }

        // Modal functions
        function openDeleteModal() {
            document.getElementById('deleteModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                document.getElementById('deletePassword').focus();
            }, 100);
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('deletePassword').value = '';
        }

        function confirmDelete() {
            const password = document.getElementById('deletePassword').value;
            if (password.length < 1) {
                alert('Please enter your password to confirm.');
                return false;
            }
            return confirm('FINAL WARNING: Are you absolutely sure? This cannot be undone!');
        }

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>