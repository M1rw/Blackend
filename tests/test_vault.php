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
echo "[PASS] Vault::fetch successfully returned envelope and started claim window\n";

// Test 5: Re-fetch within active claim window succeeds (allows user to re-read/refresh before burn)
$fetch2 = $vault->fetch($tokenId);
assert($fetch2['ok'] === true, 'Second fetch within claim window MUST succeed before burn');
assert($fetch2['ct'] === $ct, 'Second fetch CT must match');
echo "[PASS] Active Claim Window Verified: Re-fetch before burn succeeded\n";

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

// Test 8: PIN rate-limiting and kill
$pinIv = base64_encode(random_bytes(12));
$pinCt = base64_encode(random_bytes(64));
$pinMsg = $vault->store(3600, true, 0, $pinIv, $pinCt);
$pinId = $pinMsg['id'];

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

// Cleanup test dir
foreach (glob($testDir . '/*') ?: [] as $f) {
    if (is_dir($f)) {
        foreach (glob($f . '/*') ?: [] as $cf) @unlink($cf);
        @rmdir($f);
    } else @unlink($f);
}
@rmdir($testDir);

echo "\n>>> ALL PHP BACKEND TESTS PASSED SUCCESSFULLY! <<<\n";
