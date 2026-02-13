<?php
function sanitize($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: " . BASE_URL . "/" . $url);
    exit;
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function formatHashrate($hash) {
    if ($hash >= 1000000000) {
        return number_format($hash / 1000000000, 2) . ' EH/s';
    } elseif ($hash >= 1000000) {
        return number_format($hash / 1000000, 2) . ' PH/s';
    } elseif ($hash >= 1000) {
        return number_format($hash / 1000, 2) . ' TH/s';
    } else {
        return number_format($hash, 2) . ' GH/s';
    }
}
?>