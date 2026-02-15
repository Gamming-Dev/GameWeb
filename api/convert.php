<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Define que o retorno será JSON
header('Content-Type: application/json');

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Pega os dados do corpo da requisição
$data = json_decode(file_get_contents('php://input'), true);

// Valida os dados recebidos
if (!isset($data['user_id']) || !isset($data['coin']) || !isset($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$user_id = intval($data['user_id']);
$coin = strtoupper(sanitize($data['coin']));
$amount = floatval($data['amount']);

// Verifica se o usuário está autenticado
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== $user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Valida a moeda permitida
$allowed_coins = ['MCORE', 'BTC', 'ETH', 'SOL'];
if (!in_array($coin, $allowed_coins)) {
    echo json_encode(['success' => false, 'message' => 'Invalid coin']);
    exit();
}

// Valida o valor
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    exit();
}

try {
    // Inicia transação
    $pdo->beginTransaction();

    // Busca o saldo atual do usuário
    $stmt = $pdo->prepare("SELECT * FROM user_balances WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $balances = $stmt->fetch();

    if (!$balances) {
        throw new Exception('Balance not found');
    }

    // Define o campo da moeda no banco de dados
    $coin_field = strtolower($coin); // mcore, btc, eth, sol

    // Verifica se o usuário tem saldo suficiente
    if ($balances[$coin_field] < $amount) {
        throw new Exception('Insufficient balance');
    }

    // TAXA DE CONVERSÃO (ajuste conforme necessário)
    // Exemplo: 1 MCORE = $0.001 USDC
    // 1 BTC = $30,000 USDC (exemplo)
    // 1 ETH = $2,000 USDC (exemplo)
    // 1 SOL = $20 USDC (exemplo)
    
    $conversion_rates = [
        'MCORE' => 1.000,      // 1 MCORE = $0.001 USDC
        'BTC'   => 30000.00,   // 1 BTC = $30,000 USDC
        'ETH'   => 2000.00,    // 1 ETH = $2,000 USDC
        'SOL'   => 20.00       // 1 SOL = $20 USDC
    ];

    // TAXA DO SISTEMA (opcional - porcentagem cobrada na conversão)
    $system_fee_percent = 2; // 2% de taxa

    // Calcula o valor em USDC
    $usdc_value = $amount * $conversion_rates[$coin];
    
    // Aplica taxa do sistema
    $fee = $usdc_value * ($system_fee_percent / 100);
    $final_usdc = $usdc_value - $fee;

    // Atualiza o saldo da moeda convertida (subtrai)
    $new_coin_balance = $balances[$coin_field] - $amount;
    $stmt = $pdo->prepare("UPDATE user_balances SET {$coin_field} = ? WHERE user_id = ?");
    $stmt->execute([$new_coin_balance, $user_id]);

    // Atualiza o saldo USDC offline (adiciona)
    $new_usdc_balance = $balances['usdc_offline'] + $final_usdc;
    $stmt = $pdo->prepare("UPDATE user_balances SET usdc_offline = ? WHERE user_id = ?");
    $stmt->execute([$new_usdc_balance, $user_id]);

    // Registra a transação no histórico
    $stmt = $pdo->prepare("
        INSERT INTO transactions 
        (user_id, type, coin_from, amount_from, coin_to, amount_to, fee, status, created_at) 
        VALUES (?, 'convert', ?, ?, 'USDC', ?, ?, 'completed', NOW())
    ");
    $stmt->execute([
        $user_id,
        $coin,
        $amount,
        $final_usdc,
        $fee
    ]);

    // Registra no log de atividades (opcional)
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, action, details, created_at) 
        VALUES (?, 'coin_conversion', ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        "Converted {$amount} {$coin} to {$final_usdc} USDC (Fee: {$fee} USDC)"
    ]);

    // Commit da transação
    $pdo->commit();

    // Retorna sucesso
    echo json_encode([
        'success' => true,
        'message' => "Successfully converted {$amount} {$coin} to {$final_usdc} USDC",
        'data' => [
            'coin_converted' => $coin,
            'amount_converted' => $amount,
            'usdc_received' => $final_usdc,
            'fee' => $fee,
            'new_balance_usdc' => $new_usdc_balance,
            'new_balance_' . strtolower($coin) => $new_coin_balance
        ]
    ]);

} catch (Exception $e) {
    // Rollback em caso de erro
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>