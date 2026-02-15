<?php
// MinerCore - Configurações Globais

// Ambiente
define('ENVIRONMENT', 'development'); // production | development

// Database (altere para seus dados de hospedagem)
define('DB_HOST', 'localhost');
define('DB_NAME', 'u639477934_minercore');
define('DB_USER', 'u639477934_miner_dev');
define('DB_PASS', '$g99n(A[V[l-');
define('DB_CHARSET', 'utf8mb4');

// URLs
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST']);
define('ASSETS_URL', BASE_URL . '/assets');

// Configurações de Mineração
define('BLOCK_TIME_SECONDS', 600); // 10 minutos
define('DEFAULT_BLOCK_REWARD', 0.00010000); // BTC
define('MIN_WITHDRAW', 0.00100000);
define('WITHDRAW_FEE_PERCENT', 5);

// Segurança
define('SESSION_LIFETIME', 7200); // 2 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_REQUESTS', 60); // por minuto

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Error reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>