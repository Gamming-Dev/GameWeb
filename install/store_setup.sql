-- Adiciona coluna mct_balance na tabela users
ALTER TABLE users ADD COLUMN IF NOT EXISTS mct_balance DECIMAL(18,8) DEFAULT 100.00 COMMENT 'MinerCore Token balance pegged to USD';

-- Tabela de itens da loja
CREATE TABLE IF NOT EXISTS store_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category ENUM('miner','battery','solar_panel','slot') NOT NULL,
    price DECIMAL(18,8) NOT NULL COMMENT 'Price in MCT',
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common',
    
    -- Stats específicos para miners
    stats_hashrate DECIMAL(10,2) DEFAULT 0 COMMENT 'Hashrate in TH/s',
    stats_power INT DEFAULT 0 COMMENT 'Power consumption in Watts',
    stats_efficiency DECIMAL(10,2) DEFAULT 0 COMMENT 'Efficiency W/TH',
    
    -- Stats específicos para baterias
    stats_capacity DECIMAL(10,2) DEFAULT 0 COMMENT 'Battery capacity in kWh',
    stats_charge_rate DECIMAL(10,2) DEFAULT 0 COMMENT 'Charge rate in kW',
    
    -- Stats específicos para painéis solares
    stats_power_output DECIMAL(10,2) DEFAULT 0 COMMENT 'Power output in kW',
    stats_cost_reduction DECIMAL(3,2) DEFAULT 0 COMMENT 'Cost reduction percentage',
    
    -- Bonus level para slots
    bonus_level TINYINT DEFAULT 0 COMMENT 'Slot bonus level 1-3',
    
    is_active BOOLEAN DEFAULT TRUE,
    stock INT DEFAULT NULL COMMENT 'NULL = unlimited',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Tabela de slots dos usuários
CREATE TABLE IF NOT EXISTS user_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL COMMENT '1-6',
    bonus_level TINYINT DEFAULT 0 COMMENT '0-3 (0=no bonus, 1=+10%, 2=+20%, 3=+30%)',
    is_unlocked BOOLEAN DEFAULT FALSE,
    miner_id INT UNSIGNED NULL COMMENT 'ID do miner instalado',
    battery_id INT UNSIGNED NULL COMMENT 'ID da bateria instalada',
    solar_panel_id INT UNSIGNED NULL COMMENT 'ID do painel solar instalado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_slot (user_id, slot_number),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Tabela de itens dos usuários (inventário)
CREATE TABLE IF NOT EXISTS user_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    category ENUM('miner','battery','solar_panel') NOT NULL,
    quantity INT DEFAULT 1,
    is_equipped BOOLEAN DEFAULT FALSE,
    slot_id INT UNSIGNED NULL COMMENT 'Slot onde está equipado',
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES store_items(id),
    UNIQUE KEY unique_user_item (user_id, item_id),
    INDEX idx_user (user_id),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- Tabela de transações da loja
CREATE TABLE IF NOT EXISTS store_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    price_paid DECIMAL(18,8) NOT NULL,
    transaction_type ENUM('purchase','sale','refund') DEFAULT 'purchase',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES store_items(id),
    INDEX idx_user (user_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- ============================================
-- DADOS INICIAIS - SLOTS
-- ============================================
INSERT INTO store_items (name, description, category, price, rarity, bonus_level) VALUES
-- 6 Slots com 3 níveis de bonificação
('Basic Slot', 'Standard mining slot with no bonus. Perfect for beginners starting their mining journey.', 'slot', 50.00, 'common', 0),
('Basic Slot +1', 'Mining slot with Level 1 bonus. Increases hashrate by 10% for better efficiency.', 'slot', 150.00, 'common', 1),
('Enhanced Slot', 'Enhanced mining slot with no bonus. More stable and reliable than basic slots.', 'slot', 200.00, 'rare', 0),
('Enhanced Slot +2', 'Mining slot with Level 2 bonus. Boosts hashrate by 20% for professional miners.', 'slot', 400.00, 'rare', 2),
('Premium Slot', 'Premium mining slot with no bonus. Top-tier slot for serious operations.', 'slot', 600.00, 'epic', 0),
('Premium Slot +3', 'Mining slot with Level 3 bonus. Maximum 30% hashrate increase for elite miners!', 'slot', 1000.00, 'legendary', 3);

-- ============================================
-- DADOS INICIAIS - MINERS (6 máquinas diferentes)
-- ============================================
INSERT INTO store_items (name, description, category, price, rarity, stats_hashrate, stats_power, stats_efficiency) VALUES
('Nano Miner S1', 'Entry-level ASIC miner perfect for testing the waters. Low power consumption and affordable.', 'miner', 75.00, 'common', 5.00, 250, 50.00),
('CompactHash 3000', 'Compact yet powerful miner with balanced performance. Great for small-scale operations.', 'miner', 250.00, 'common', 15.00, 650, 43.33),
('ProMiner X7', 'Professional-grade miner with excellent efficiency. Ideal for serious hobbyists.', 'miner', 600.00, 'rare', 50.00, 1500, 30.00),
('TitanHash Ultra', 'High-performance miner with liquid cooling system. Built for 24/7 operations.', 'miner', 1500.00, 'epic', 150.00, 3500, 23.33),
('QuantumRig Elite', 'Next-generation mining rig with cutting-edge technology and supreme efficiency.', 'miner', 3500.00, 'epic', 400.00, 7500, 18.75),
('HyperForge Omega', 'Legendary mining powerhouse. The ultimate machine for maximum profits and performance!', 'miner', 10000.00, 'legendary', 1200.00, 18000, 15.00);

-- ============================================
-- DADOS INICIAIS - BATERIAS (3 tipos)
-- ============================================
INSERT INTO store_items (name, description, category, price, rarity, stats_capacity, stats_efficiency, stats_charge_rate) VALUES
('BasicCell 2000', 'Standard lithium battery pack. Provides reliable backup power for short outages.', 'battery', 200.00, 'common', 10.00, 0.85, 5.00),
('PowerVault Pro', 'Advanced battery system with high capacity. Perfect for extended mining sessions.', 'battery', 750.00, 'rare', 50.00, 0.92, 15.00),
('MegaStore X10', 'Industrial-grade energy storage system. Maximum capacity for uninterrupted operations!', 'battery', 2500.00, 'epic', 200.00, 0.97, 50.00);

-- ============================================
-- DADOS INICIAIS - PAINÉIS SOLARES (3 tipos)
-- ============================================
INSERT INTO store_items (name, description, category, price, rarity, stats_power_output, stats_efficiency, stats_cost_reduction) VALUES
('EcoPanel 100', 'Entry-level solar panel. Reduces energy costs and provides clean, renewable power.', 'solar_panel', 300.00, 'common', 5.00, 0.18, 0.15),
('SolarMax 500', 'High-efficiency solar array. Significant cost reduction and excellent power generation.', 'solar_panel', 1200.00, 'rare', 25.00, 0.24, 0.35),
('SunHarvester Titan', 'Ultimate solar power system. Maximum efficiency and dramatic cost savings!', 'solar_panel', 4000.00, 'legendary', 100.00, 0.32, 0.60);

-- ============================================
-- CONFIGURAÇÕES INICIAIS
-- ============================================

-- Atualiza configurações do sistema
INSERT INTO settings (config_key, config_value) VALUES
('mct_usd_peg', '1.00'),
('slot_unlock_limit', '6'),
('default_user_slots', '1')
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);

-- Adiciona MCT inicial para usuários existentes (opcional - para testes)
-- UPDATE users SET mct_balance = 1000.00 WHERE mct_balance = 0 OR mct_balance IS NULL;

-- Cria primeiro slot gratuito para todos os usuários
INSERT IGNORE INTO user_slots (user_id, slot_number, bonus_level, is_unlocked)
SELECT id, 1, 0, TRUE FROM users;
