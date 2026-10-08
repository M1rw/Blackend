<?php
declare(strict_types=1);

/**
 * Built-in PHP server router for blackend.
 * Usage: php -S 127.0.0.1:8090 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Vault API endpoints
if ($uri === '/vault.php' || $uri === '/api/vault.php') {
    require __DIR__ . '/vault.php';
    return true;
}

// Serve only public static assets as-is. Everything else (vault data, lib/,
// config.php, tests/, .git, docs) must never be reachable over HTTP.
if (preg_match('#^/assets/[A-Za-z0-9._/-]+$#', $uri) && strpos($uri, '..') === false
    && is_file(__DIR__ . $uri)) {
    return false;
}

// Clean URLs (/v/:token, /m/:token) and general routing to index.php
require __DIR__ . '/index.php';
return true;
