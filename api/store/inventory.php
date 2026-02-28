<?php
/**
 * Store API - User Inventory
 * Lista itens do inventário do usuário
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

try {
    // Busca slots do usuário
    $stmt = $pdo->prepare("
        SELECT 
            us.*,
            m.name as miner_name,
            m.stats_hashrate,
            b.name as battery_name,
            b.stats_capacity,
            sp.name as solar_panel_name,
            sp.stats_power_output
        FROM user_slots us
        LEFT JOIN user_items ui_m ON us.miner_id = ui_m.id
        LEFT JOIN store_items m ON ui_m.item_id = m.id
        LEFT JOIN user_items ui_b ON us.battery_id = ui_b.id
        LEFT JOIN store_items b ON ui_b.item_id = b.id
        LEFT JOIN user_items ui_sp ON us.solar_panel_id = ui_sp.id
        LEFT JOIN store_items sp ON ui_sp.item_id = sp.id
        WHERE us.user_id = ?
        ORDER BY us.slot_number
    ");
    $stmt->execute([$user_id]);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Busca inventário (itens não equipados)
    $stmt = $pdo->prepare("
        SELECT 
            ui.*,
            si.name,
            si.description,
            si.category,
            si.rarity,
            si.stats_hashrate,
            si.stats_power,
            si.stats_capacity,
            si.stats_power_output
        FROM user_items ui
        JOIN store_items si ON ui.item_id = si.id
        WHERE ui.user_id = ? AND ui.is_equipped = FALSE
        ORDER BY si.category, si.rarity DESC
    ");
    $stmt->execute([$user_id]);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Busca saldo MCT
    $stmt = $pdo->prepare("SELECT mct_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $mct_balance = $stmt->fetchColumn();
    
    // Calcula estatísticas totais
    $total_hashrate = 0;
    $total_power_consumption = 0;
    $total_battery_capacity = 0;
    $total_solar_output = 0;
    
    foreach ($slots as $slot) {
        if ($slot['is_unlocked'] && $slot['miner_id']) {
            $bonus_multiplier = 1 + ($slot['bonus_level'] * 0.10);
            $total_hashrate += floatval($slot['stats_hashrate']) * $bonus_multiplier;
        }
        if ($slot['battery_id']) {
            $total_battery_capacity += floatval($slot['stats_capacity']);
        }
        if ($slot['solar_panel_id']) {
            $total_solar_output += floatval($slot['stats_power_output']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'mct_balance' => floatval($mct_balance),
            'slots' => $slots,
            'inventory' => $inventory,
            'stats' => [
                'total_hashrate' => $total_hashrate,
                'total_power_consumption' => $total_power_consumption,
                'total_battery_capacity' => $total_battery_capacity,
                'total_solar_output' => $total_solar_output,
                'unlocked_slots' => count(array_filter($slots, fn($s) => $s['is_unlocked']))
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch inventory: ' . $e->getMessage()
    ]);
}
