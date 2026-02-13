-- Tabela de carteiras Web3 vinculadas
CREATE TABLE user_wallets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    wallet_address VARCHAR(42) NOT NULL COMMENT 'Endereço blockchain (0x...)',
    chain ENUM('BSC', 'POL') NOT NULL DEFAULT 'BSC',
    is_primary BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wallet_chain (user_id, chain, wallet_address),
    INDEX idx_user_chain (user_id, chain)
) ENGINE=InnoDB;

-- Atualizar tabela users com flag de conta excluída (soft delete)
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN deletion_reason VARCHAR(255) NULL;