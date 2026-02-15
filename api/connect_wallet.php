<?php
// Ativar exibição de erros apenas para debug (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir erros na saída

session_start();
header('Content-Type: application/json');

// Verificar se arquivos existem antes de incluir
$base_path = dirname(__DIR__); // Sobe um nível de /api para raiz

if (!file_exists($base_path . '/includes/config.php')) {
    echo json_encode(['success' => false, 'message' => 'Config file not found at: ' . $base_path]);
    exit();
}

require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/db.php';
require_once $base_path . '/includes/functions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Receber dados JSON
$json = file_get_contents('php://input');
if (empty($json)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

$data = json_decode($json, true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

// Validar campos obrigatórios
$required = array('user_id', 'wallet_address', 'wallet_type', 'network', 'signature');
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

$user_id = intval($data['user_id']);
$wallet_address = strtolower(trim($data['wallet_address']));
$wallet_type = strtolower(trim($data['wallet_type']));
$network = strtolower(trim($data['network']));
$signature = trim($data['signature']);

// Verificar se é o mesmo usuário da sessão
if ($user_id !== intval($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validar endereço Ethereum
if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
    echo json_encode(['success' => false, 'message' => 'Invalid wallet address format']);
    exit();
}

// Validar network
if (!in_array($network, array('bnb', 'polygon'))) {
    echo json_encode(['success' => false, 'message' => 'Invalid network']);
    exit();
}

try {
    // Verificar se tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_wallets'");
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Database table not found. Please run SQL setup.']);
        exit();
    }

    // Verificar se carteira já existe para este usuário nesta rede
    $stmt = $pdo->prepare("SELECT id FROM user_wallets WHERE wallet_address = ? AND network = ? AND user_id = ?");
    $stmt->execute([$wallet_address, $network, $user_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This wallet is already connected on this network']);
        exit();
    }

    // Verificar se é a primeira carteira do usuário
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $count = intval($stmt->fetchColumn());
    $is_primary = ($count == 0) ? 1 : 0;

    // Inserir nova carteira
    $stmt = $pdo->prepare("
        INSERT INTO user_wallets 
        (user_id, wallet_address, wallet_type, network, is_primary, signature, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $result = $stmt->execute([
        $user_id,
        $wallet_address,
        $wallet_type,
        $network,
        $is_primary,
        $signature
    ]);

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Wallet connected successfully',
            'wallet_id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to insert wallet']);
    }

} catch (PDOException $e) {
    error_log("Wallet connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Wallet connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}