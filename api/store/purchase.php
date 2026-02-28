<?php
/**
 * Store API - Purchase Handler
 * Processa compras de itens da loja MinerCore
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Verifica autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$item_id = intval($data['item_id'] ?? 0);

if (!$item_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
    exit();
}

try {
    // Busca dados do usuário
    $stmt = $pdo->prepare("SELECT mct_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Busca item da loja
    $stmt = $pdo->prepare("SELECT * FROM store_items WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception('Item not found or unavailable');
    }
    
    // Verifica saldo
    if ($user['mct_balance'] < $item['price']) {
        throw new Exception('Insufficient MCT balance');
    }
    
    // Verifica estoque (se aplicável)
    if ($item['stock'] !== null && $item['stock'] <= 0) {
        throw new Exception('Item out of stock');
    }
    
    // Inicia transação
    $pdo->beginTransaction();
    
    // Deduz saldo MCT
    $stmt = $pdo->prepare("UPDATE users SET mct_balance = mct_balance - ? WHERE id = ?");
    $stmt->execute([$item['price'], $user_id]);
    
    // Processa compra conforme categoria
    switch ($item['category']) {
        case 'slot':
            // Desbloqueia novo slot
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_slots WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $total_slots = $stmt->fetchColumn();
            
            if ($total_slots >= 6) {
                throw new Exception('Maximum slots reached (6)');
            }
            
            $next_slot = $total_slots + 1;
            $stmt = $pdo->prepare("INSERT INTO user_slots (user_id, slot_number, bonus_level, is_unlocked) VALUES (?, ?, ?, TRUE)");
            $stmt->execute([$user_id, $next_slot, $item['bonus_level']]);
            break;
            
        case 'miner':
        case 'battery':
        case 'solar_panel':
            // Adiciona ao inventário
            $stmt = $pdo->prepare("
                INSERT INTO user_items (user_id, item_id, category, quantity) 
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE quantity = quantity + 1
            ");
            $stmt->execute([$user_id, $item_id, $item['category']]);
            break;
            
        default:
            throw new Exception('Invalid item category');
    }
    
    // Atualiza estoque (se aplicável)
    if ($item['stock'] !== null) {
        $stmt = $pdo->prepare("UPDATE store_items SET stock = stock - 1 WHERE id = ?");
        $stmt->execute([$item_id]);
    }
    
    // Registra transação
    $stmt = $pdo->prepare("
        INSERT INTO store_transactions (user_id, item_id, price_paid, transaction_type) 
        VALUES (?, ?, ?, 'purchase')
    ");
    $stmt->execute([$user_id, $item_id, $item['price']]);
    
    // Commit
    $pdo->commit();
    
    // Busca novo saldo
    $stmt = $pdo->prepare("SELECT mct_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully purchased {$item['name']}!",
        'new_balance' => floatval($new_balance),
        'item' => [
            'id' => $item['id'],
            'name' => $item['name'],
            'category' => $item['category']
        ]
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
