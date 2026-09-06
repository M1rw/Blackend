<?php
declare(strict_types=1);

/* blackend vault — zero-knowledge ephemeral storage API.
   No sessions, no cookies, no tracking. All actions are POST with
   JSON bodies so web-server access logs never record message IDs or keys. */

error_reporting(0);
ini_set('display_errors', '0');

if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === 'gc') {
        require_once __DIR__ . '/lib/Vault.php';
        (new Vault(require __DIR__ . '/config.php'))->gc();
        echo "gc done\n";
    } else {
        echo "usage: php vault.php gc\n";
    }
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function out(array $a, int $status = 200): void {
    http_response_code($status);
    echo json_encode($a, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(['ok' => false, 'error' => 'post only'], 405);
}

$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 8 * 1048576) {
    out(['ok' => false, 'error' => 'payload too large'], 413);
}

$in = json_decode($raw, true);
if (!is_array($in)) {
    out(['ok' => false, 'error' => 'bad json request'], 400);
}

require_once __DIR__ . '/lib/Vault.php';
$cfg = require __DIR__ . '/config.php';
$vault = new Vault($cfg);

// 1-in-40 opportunistic sweep to purge expired data
if (random_int(1, 40) === 1) {
    $vault->gc();
}

$action = (string)($in['action'] ?? '');

try {
    switch ($action) {
        case 'store':
            out($vault->store(
                (int)($in['exp'] ?? 0),
                !empty($in['pin']),
                (int)($in['nc'] ?? 0),
                (string)($in['iv'] ?? ''),
                (string)($in['ct'] ?? ''),
                (string)($in['salt'] ?? ''),
                (string)($in['wiv'] ?? ''),
                (string)($in['wrapped'] ?? '')
            ));

        case 'put':
            out($vault->put(
                (string)($in['id'] ?? ''),
                (int)($in['i'] ?? -1),
                (string)($in['data'] ?? '')
            ));

        case 'ready':
            out($vault->ready((string)($in['id'] ?? '')));

        case 'fetch':
            out($vault->fetch((string)($in['id'] ?? '')));

        case 'chunk':
            out($vault->chunk((string)($in['id'] ?? ''), (int)($in['i'] ?? -1)));

        case 'burn':
            out($vault->burn(
                (string)($in['id'] ?? ''),
                (string)($in['why'] ?? 'killed')
            ));

        case 'fail':
            out($vault->fail((string)($in['id'] ?? '')));

        case 'status':
            out($vault->status((string)($in['id'] ?? '')));

        case 'ping':
            out(['ok' => true, 'service' => 'blackend-vault', 'version' => '2.0.0']);

        default:
            out(['ok' => false, 'error' => 'unknown action'], 400);
    }
} catch (Throwable $e) {
    out(['ok' => false, 'error' => 'vault operation failed'], 500);
}