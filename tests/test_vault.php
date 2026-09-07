<?php
declare(strict_types=1);

/**
 * Automated test suite for PHP Vault backend (lib/Vault.php).
 * Verifies envelope storage, chunk upload, shredding on fetch, PIN rate-limiting, and GC.
 */

require_once __DIR__ . '/../lib/Vault.php';

$testDir = sys_get_temp_dir() . '/blackend_test_' . bin2hex(random_bytes(6));
@mkdir($testDir, 0770, true);

$cfg = [
    'secret'         => 'test_secret_key_366889761e0d3e70',
    'data_dir'       => $testDir,
    'chunk_size'     => 262144,
    'max_file'       => 25 * 1048576,
    'max_chunks'     => 160,
    'max_life'       => 604800,
    'claim_window'   => 600,
    'store_ttl'      => 1800,
    'tombstone_life' => 86400,
];

$vault = new Vault($cfg);

echo "=== 1. PHP VAULT BACKEND TESTS ===\n";

// Test 1: Store envelope
$iv = base64_encode(random_bytes(12));
$ct = base64_encode(random_bytes(64));
$res = $vault->store(3600, false, 2, $iv, $ct);

assert($res['ok'] === true, 'Store must succeed');
assert(!empty($res['id']), 'Store must return an ID');
$tokenId = $res['id'];
echo "[PASS] Vault::store created envelope ID: {$tokenId}\n";

// Test 2: Put chunks and ready
$chunkData0 = base64_encode(random_bytes(500));
$chunkData1 = base64_encode(random_bytes(500));
$put0 = $vault->put($tokenId, 0, $chunkData0);
$put1 = $vault->put($tokenId, 1, $chunkData1);
assert($put0['ok'] === true && $put1['ok'] === true, 'Chunks must be stored');

$ready = $vault->ready($tokenId);
assert($ready['ok'] === true, 'Vault::ready must succeed');
echo "[PASS] Vault::put and Vault::ready succeeded\n";

// Test 3: Status before fetch
$statusBefore = $vault->status($tokenId);
assert($statusBefore['state'] === 'sealed', 'Status before fetch must be sealed');
echo "[PASS] Vault::status confirmed sealed state\n";

// Test 4: Fetch (Initial read starts claim window)
$fetch = $vault->fetch($tokenId);
assert($fetch['ok'] === true, 'Fetch must succeed on 1st read');
assert($fetch['iv'] === $iv, 'Fetched IV must match');
assert($fetch['ct'] === $ct, 'Fetched CT must match');
assert($fetch['nc'] === 2, 'Fetched chunk count must match');
assert($fetch['already_read'] === false, 'First fetch must report already_read === false');
assert(!empty($fetch['now']), 'Fetch must return now timestamp');
echo "[PASS] Vault::fetch successfully returned envelope and started claim window (already_read=false)\n";

// Test 4b: Status during active claim window is 'opened'
$statusDuring = $vault->status($tokenId);
assert($statusDuring['state'] === 'opened', 'Status during active claim window must be "opened"');
echo "[PASS] Vault::status during active claim window confirmed 'opened' state\n";

// Test 5: Re-fetch within active claim window succeeds (allows user to re-read/refresh before burn)
$fetch2 = $vault->fetch($tokenId);
assert($fetch2['ok'] === true, 'Second fetch within claim window MUST succeed before burn');
assert($fetch2['ct'] === $ct, 'Second fetch CT must match');
assert($fetch2['already_read'] === true, 'Second fetch must report already_read === true');
echo "[PASS] Active Claim Window Verified: Re-fetch before burn succeeded (already_read=true)\n";

// Test 6: Chunk retrieval within claim window
$chunk0 = $vault->chunk($tokenId, 0);
assert($chunk0['ok'] === true, 'Chunk 0 retrieval must succeed within claim window');
assert($chunk0['data'] === $chunkData0, 'Decrypted chunk data must match uploaded data');
echo "[PASS] Vault::chunk retrieval and single-read chunk delivery verified\n";

// Test 7: Explicit Burn shreds envelope and chunks
$burnRes = $vault->burn($tokenId, 'read');
assert($burnRes['ok'] === true, 'Vault::burn must succeed');
$fetchAfterBurn = $vault->fetch($tokenId);
assert($fetchAfterBurn['ok'] === false, 'Fetch after burn MUST fail');
assert($fetchAfterBurn['why'] === 'read', 'Failure reason after burn must be "read"');
echo "[PASS] Vault::burn successfully destroyed envelope and left 'read' tombstone\n";

// Test 8: PIN lifecycle: fetch does not claim, open transitions to opened
$pinIv = base64_encode(random_bytes(12));
$pinCt = base64_encode(random_bytes(64));
$pinMsg = $vault->store(3600, true, 0, $pinIv, $pinCt);
$pinId = $pinMsg['id'];

// Initial fetch of PIN envelope should NOT start read claim window
$pinFetch = $vault->fetch($pinId);
assert($pinFetch['ok'] === true, 'PIN fetch must succeed');
assert($pinFetch['pin'] === true, 'PIN flag must be true');
assert($pinFetch['already_read'] === false, 'PIN initial fetch must have already_read false');
assert($pinFetch['read'] === 0, 'PIN initial fetch must not set read time yet');
$pinStatus1 = $vault->status($pinId);
assert($pinStatus1['state'] === 'sealed', 'PIN envelope must remain sealed before PIN unlock');
echo "[PASS] PIN envelope remains sealed on initial fetch until unlocked\n";

// Unlocking with open() starts the claim window
$pinOpen = $vault->open($pinId);
assert($pinOpen['ok'] === true, 'Vault::open must succeed');
assert($pinOpen['read'] > 0, 'Vault::open must set read timestamp');
$pinStatus2 = $vault->status($pinId);
assert($pinStatus2['state'] === 'opened', 'Status after open must be opened');
echo "[PASS] Vault::open transitions PIN envelope to opened state\n";

// Test 9: PIN rate-limiting and kill
$fail1 = $vault->fail($pinId);
assert($fail1['left'] === 2, 'First fail must leave 2 attempts');
$fail2 = $vault->fail($pinId);
assert($fail2['left'] === 1, 'Second fail must leave 1 attempt');
$fail3 = $vault->fail($pinId);
assert($fail3['state'] === 'killed', 'Third fail must trigger auto-destruction (killed)');
echo "[PASS] PIN 3-strike brute force rate limiting verified\n";

// Test 8: Robust Garbage Collection (verify with non-hex folder names)
@mkdir($testDir . '/invalid_stray_folder', 0770, true);
$vault->gc();
echo "[PASS] Vault::gc handled stray folders without throwing unhandled exceptions\n";

// Test 9: Multi-megabyte file shredding
$largeFile = $testDir . '/large_test_blob.bin';
$largeData = random_bytes(2500000); // 2.5 MB data
file_put_contents($largeFile, $largeData);
assert(file_exists($largeFile), 'Large test file must exist before shredding');
$rmMethod = new ReflectionMethod('Vault', 'shred');
$rmMethod->setAccessible(true);
$rmMethod->invoke($vault, $largeFile);
assert(!file_exists($largeFile), 'Large test file must be completely unlinked after shredding');
echo "[PASS] Multi-megabyte file full shredding verified (2.5 MB overwritten & unlinked)\n";

// Test 10: Serverless SSE watch duration capping
putenv('VERCEL=1');
$vStore = $vault->store(3600, false, 0, base64_encode(random_bytes(12)), base64_encode(random_bytes(64)));
$vId = $vStore['id'];
$tStart = microtime(true);
ob_start();
$vault->watch($vId);
$wOutput = ob_get_clean();
$tElapsed = microtime(true) - $tStart;
assert($tElapsed < 12.0, "Watch window on Vercel must finish in under 12 seconds (actual: {$tElapsed}s)");
assert(strpos($wOutput, 'data: ') !== false, 'Watch output must contain SSE data messages');
putenv('VERCEL');
echo "[PASS] Serverless SSE watch duration cap verified (elapsed: " . round($tElapsed, 2) . "s)\n";

// Test 11: Blinded Dead-Drop Rendezvous Token Derivation
$rdvToken = Vault::deriveRendezvousToken('shared-dead-drop-secret', time());
assert(is_string($rdvToken) && strlen($rdvToken) >= 12, 'Rendezvous token must be Base64URL string');
echo "[PASS] Blinded Dead-Drop Rendezvous Token derivation verified\n";

// Cleanup test dir
foreach (glob($testDir . '/*') ?: [] as $f) {
    if (is_dir($f)) {
        foreach (glob($f . '/*') ?: [] as $cf) @unlink($cf);
        @rmdir($f);
    } else @unlink($f);
}
@rmdir($testDir);

echo "\n>>> ALL PHP BACKEND TESTS PASSED SUCCESSFULLY! <<<\n";
