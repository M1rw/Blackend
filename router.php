<?php
declare(strict_types=1);

/**
 * Built-in PHP server router for blackend.
 * Usage: php -S 127.0.0.1:8090 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Serve existing static files as-is
$filePath = __DIR__ . $uri;
if ($uri !== '/' && is_file($filePath)) {
    return false;
}

// Vault API endpoints
if ($uri === '/vault.php' || $uri === '/api/vault.php') {
    require __DIR__ . '/vault.php';
    return true;
}

// Clean URLs (/v/:token, /m/:token) and general routing to index.php
require __DIR__ . '/index.php';
return true;
