-- Tabela de usuários
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    referral_code VARCHAR(8) UNIQUE NOT NULL,
    referred_by INT UNSIGNED NULL,
    balance_pending DECIMAL(18,8) DEFAULT 0,
    balance_available DECIMAL(18,8) DEFAULT 0,
    total_mined DECIMAL(18,8) DEFAULT 0,
    is_admin BOOLEAN DEFAULT FALSE,
    status ENUM('active','banned','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP NULL,
    FOREIGN KEY (referred_by) REFERENCES users(id),
    INDEX idx_referral (referral_code),
    INDEX idx_referred (referred_by)
) ENGINE=InnoDB;

-- Tipos de miners (catálogo)
CREATE TABLE miner_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    base_hashrate DECIMAL(10,2) NOT NULL COMMENT 'H/s',
    power_watts INT UNSIGNED DEFAULT 100,
    price DECIMAL(18,8) NOT NULL,
    max_supply INT UNSIGNED NULL COMMENT 'NULL = ilimitado',
    image VARCHAR(100) DEFAULT 'default_miner.png',
    rarity ENUM('common','rare','epic','legendary') DEFAULT 'common',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Inventário dos usuários
CREATE TABLE user_miners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    miner_type_id INT UNSIGNED NOT NULL,
    slot_position TINYINT UNSIGNED DEFAULT 0 COMMENT '0-8 (grade 3x3)',
    efficiency DECIMAL(3,2) DEFAULT 1.00 COMMENT '0.50 a 2.00',
    durability INT DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (miner_type_id) REFERENCES miner_types(id),
    UNIQUE KEY unique_slot (user_id, slot_position),
    INDEX idx_user_active (user_id, is_active)
) ENGINE=InnoDB;

-- Blocos de mineração
CREATE TABLE blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_number INT UNSIGNED UNIQUE NOT NULL,
    reward_pool DECIMAL(18,8) NOT NULL,
    total_hashrate DECIMAL(20,2) DEFAULT 0,
    difficulty INT UNSIGNED DEFAULT 1,
    status ENUM('mining','completed','distributed') DEFAULT 'mining',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    distributed_at TIMESTAMP NULL,
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB;

-- Participação dos usuários nos blocos
CREATE TABLE block_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    hashrate DECIMAL(18,2) NOT NULL,
    percentage DECIMAL(6,4) NOT NULL COMMENT '0.0000 a 100.0000',
    reward_earned DECIMAL(18,8) DEFAULT 0,
    claimed BOOLEAN DEFAULT FALSE,
    claimed_at TIMESTAMP NULL,
    FOREIGN KEY (block_id) REFERENCES blocks(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_participation (block_id, user_id),
    INDEX idx_user_claimed (user_id, claimed)
) ENGINE=InnoDB;

-- Histórico de atividades
CREATE TABLE mining_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('block_reward','game_bonus','referral','deposit','withdrawal') NOT NULL,
    amount DECIMAL(18,8) NOT NULL,
    description VARCHAR(255),
    related_id INT UNSIGNED NULL COMMENT 'ID do bloco, game, etc',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_date (user_id, created_at)
) ENGINE=InnoDB;

-- Power-ups ativos
CREATE TABLE active_boosts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('hash','luck','energy_save') NOT NULL,
    multiplier DECIMAL(3,2) DEFAULT 1.50,
    expires_at TIMESTAMP NOT NULL,
    game_origin VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_expires (expires_at),
    INDEX idx_user_active (user_id, expires_at)
) ENGINE=InnoDB;

-- Configurações do sistema
CREATE TABLE settings (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Dados iniciais
INSERT INTO settings (config_key, config_value) VALUES
('block_time_seconds', '600'),
('current_block_reward', '0.00010000'),
('min_withdraw', '0.00100000'),
('withdraw_fee_percent', '5'),
('site_maintenance', '0'),
('total_network_hashrate', '1000000');

-- Miners iniciais (exemplos)
INSERT INTO miner_types (name, description, base_hashrate, power_watts, price, rarity) VALUES
('ASIC Junior', 'Miner básico para iniciantes', 10.00, 500, 0.00010000, 'common'),
('ASIC Pro', 'Equipamento intermediário', 50.00, 1200, 0.00050000, 'common'),
('Rig GTX', 'Placas de vídeo otimizadas', 100.00, 800, 0.00100000, 'rare'),
('Antminer S19', 'Equipamento profissional', 500.00, 3000, 0.00500000, 'epic'),
('Liquid Cooled X', 'Refrigeração líquida extrema', 1000.00, 2500, 0.01000000, 'legendary');

-- Criar bloco inicial
INSERT INTO blocks (block_number, reward_pool, status) VALUES (1, 0.00010000, 'mining');

-- Criar tabela de carteiras (SEM foreign key para evitar erro de charset)
CREATE TABLE IF NOT EXISTS user_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_address VARCHAR(42) NOT NULL,
    wallet_type VARCHAR(20) NOT NULL,
    network VARCHAR(10) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    balance_usd DECIMAL(15,2) DEFAULT 0.00,
    signature TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wallet_network (wallet_address, network),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de transações (SEM foreign key)
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_id INT,
    type ENUM('deposit', 'withdrawal', 'reward', 'purchase') NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    network VARCHAR(10) NOT NULL,
    tx_hash VARCHAR(66),
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_wallet_id (wallet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de saldos dos usuários (SEM foreign key)
CREATE TABLE user_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    usdc_offline DECIMAL(20, 8) DEFAULT 0.00000000,
    mcore DECIMAL(20, 8) DEFAULT 0.00000000,
    btc DECIMAL(20, 8) DEFAULT 0.00000000,
    eth DECIMAL(20, 16) DEFAULT 0.0000000000000000,
    sol DECIMAL(20, 16) DEFAULT 0.0000000000000000,
    UNIQUE KEY unique_user (user_id)
);