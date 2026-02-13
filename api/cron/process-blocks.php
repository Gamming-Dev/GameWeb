<?php
// Não é acessível via navegador (só CLI ou Cron)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado');
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

// Processa "ticks" de mineração acumulados desde última execução
$lastRun = getLastCronRun();
$now = time();
$elapsed = $now - $lastRun;

// Simula X segundos de mineração de uma vez
processMiningTicks($elapsed);

updateLastCronRun($now);