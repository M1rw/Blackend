<?php
declare(strict_types=1);

/* blackend vault — the only public backend surface.
   No sessions, no cookies, no logging of tokens. All actions are POST with
   JSON bodies so web-server access logs never see message IDs. */

error_reporting(0);
ini_set('display_errors', '0');

if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === 'gc') {
        require __DIR__ . '/lib/Vault.php';
        (new Vault(require __DIR__ . '/config.php'))->gc();
        echo "gc done\n";
    } else {
        echo "usage: php vault.php gc\n";
    }
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
// no CORS headers: same-origin only. No cookies => no CSRF surface.

function out(array $a): void { echo json_encode($a); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['ok' => false, 'error' => 'post only']);

 $raw = (string)file_get_contents('php://input');
if (strlen($raw) > 4 * 1048576) out(['ok' => false, 'error' => 'too large']);
 $in = json_decode($raw, true);
if (!is_array($in)) out(['ok' => false, 'error' => 'bad request']);

require __DIR__ . '/lib/Vault.php';
 $cfg = require __DIR__ . '/config.php';
 $vault = new Vault($cfg);

if (random_int(1, 40) === 1) $vault->gc();   // opportunistic sweep

 $action = (string)($in['action'] ?? '');

try {
    switch ($action) {
        case 'store':
            out($vault->store(
                (int)($in['exp'] ?? 0),
                !empty($in['pin']),
                (int)($in['nc'] ?? 0),
                (string)($in['iv'] ?? ''),
                (string)($in['ct'] ?? '')
            ));

        case 'put':
            out($vault->put((string)($in['id'] ?? ''), (int)($in['i'] ?? -1), (string)($in['data'] ?? '')));

        case 'ready':
            out($vault->ready((string)($in['id'] ?? '')));

        case 'fetch':
            out($vault->fetch((string)($in['id'] ?? '')));

        case 'chunk':
            out($vault->chunk((string)($in['id'] ?? ''), (int)($in['i'] ?? -1)));

        case 'burn':
            out($vault->burn((string)($in['id'] ?? ''), (string)($in['why'] ?? 'killed')));

        case 'fail':
            out($vault->fail((string)($in['id'] ?? '')));

        case 'status':
            out($vault->status((string)($in['id'] ?? '')));

        default:
            out(['ok' => false, 'error' => 'unknown action']);
    }
} catch (Throwable $e) {
    out(['ok' => false, 'error' => 'vault error']);
}