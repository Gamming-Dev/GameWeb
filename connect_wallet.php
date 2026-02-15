<?php
// No topo do connect_wallet.php, depois de session_start()
file_put_contents('debug.txt', date('Y-m-d H:i:s') . " - User: " . ($_SESSION['user_id'] ?? 'NONE') . "\n", FILE_APPEND);
file_put_contents('debug.txt', "Input: " . file_get_contents('php://input') . "\n\n", FILE_APPEND);
// Desativar exibição de erros
error_reporting(0);
ini_set('display_errors', 0);

// Headers CORS e JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Responder preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    session_start();
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }
    
    // Verificar autenticação
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated. Please login first.');
    }
    
    $user_id = intval($_SESSION['user_id']);
    
    // Incluir arquivos necessários
    $required_files = ['includes/config.php', 'includes/db.php', 'includes/functions.php'];
    foreach ($required_files as $file) {
        if (!file_exists($file)) {
            throw new Exception("Required file not found: $file");
        }
        require_once $file;
    }
    
    // Receber e validar JSON
    $json = file_get_contents('php://input');
    if (empty($json)) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format: ' . json_last_error_msg());
    }
    
    // Validar campos obrigatórios
    $required_fields = ['wallet_address', 'wallet_type', 'network', 'signature'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitizar dados
    $wallet_address = strtolower(trim($data['wallet_address']));
    $wallet_type = strtolower(trim($data['wallet_type']));
    $network = strtolower(trim($data['network']));
    $signature = trim($data['signature']);
    
    // Validar formato do endereço Ethereum
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        throw new Exception('Invalid Ethereum wallet address format');
    }
    
    // Validar network permitida
    $allowed_networks = ['bnb', 'polygon'];
    if (!in_array($network, $allowed_networks)) {
        throw new Exception('Invalid network. Allowed: ' . implode(', ', $allowed_networks));
    }
    
    // Validar tipo de wallet
    $allowed_types = ['metamask', 'trust', 'rabbit', 'rabby', 'coinbase', 'walletconnect'];
    if (!in_array($wallet_type, $allowed_types)) {
        $wallet_type = 'unknown';
    }
    
    // Verificar se tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_wallets'");
    if ($stmt->rowCount() == 0) {
        // Tentar criar tabela automaticamente
        $create_sql = "CREATE TABLE IF NOT EXISTS user_wallets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            wallet_address VARCHAR(42) NOT NULL,
            wallet_type VARCHAR(20) NOT NULL DEFAULT 'metamask',
            network VARCHAR(10) NOT NULL DEFAULT 'bnb',
            is_primary TINYINT(1) DEFAULT 0,
            balance_usd DECIMAL(15,2) DEFAULT 0.00,
            signature TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_wallet_network (wallet_address, network),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $pdo->exec($create_sql);
        } catch (PDOException $e) {
            throw new Exception('Database table not found and could not be created. Please run SQL setup manually.');
        }
    }
    
    // Verificar se carteira já existe para este usuário nesta rede
    $stmt = $pdo->prepare("SELECT id FROM user_wallets WHERE wallet_address = ? AND network = ? AND user_id = ?");
    $stmt->execute([$wallet_address, $network, $user_id]);
    
    if ($stmt->fetch()) {
        throw new Exception('This wallet is already connected on this network');
    }
    
    // Verificar se é a primeira carteira (definir como primária)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet_count = intval($stmt->fetchColumn());
    $is_primary = ($wallet_count === 0) ? 1 : 0;
    
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
    
    if (!$result) {
        throw new Exception('Failed to save wallet to database');
    }
    
    $wallet_id = $pdo->lastInsertId();
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Wallet connected successfully',
        'wallet_id' => $wallet_id,
        'wallet_address' => $wallet_address,
        'network' => $network,
        'is_primary' => $is_primary
    ]);
    
} catch (PDOException $e) {
    // Erro de banco de dados
    error_log("Wallet DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    
} catch (Exception $e) {
    // Outros erros
    error_log("Wallet Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}