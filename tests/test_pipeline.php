<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Vault.php';
$cfg = require __DIR__ . '/../config.php';
$vault = new Vault($cfg);

echo "=== FULL PIPELINE & AUDIT TRAIL MONITORING TEST ===\n";

// 1. Generate receipt key and hash
$rk = 'pipeline_rk_' . bin2hex(random_bytes(16));
$data = 'bk-receipt:' . $rk;
$rawHash = hash('sha256', $data, true);
$rkHash = rtrim(strtr(base64_encode($rawHash), '+/', '-_'), '=');

echo "[1] Generated rkHash: $rkHash\n";

// 2. Store envelope
$res = $vault->store(
    3600,
    false,
    0,
    base64_encode(random_bytes(12)),
    base64_encode(random_bytes(32)),
    '', '', '',
    ['accent' => 'ember', 'burn' => 'calm'],
    $rkHash
);

assert($res['ok'] === true, 'Store failed');
$id = $res['id'];
echo "[2] Envelope stored successfully: ID=$id\n";

// 3. Verify receipt immediately (Sealed state)
$rcpt1 = $vault->receipt($id, $rkHash);
echo "[3] Receipt check #1 (Sealed): " . json_encode($rcpt1) . "\n";
assert($rcpt1['ok'] === true, 'Receipt 1 failed');
assert($rcpt1['opened'] === 0, 'Opened should be 0');
assert($rcpt1['burned'] === 0, 'Burned should be 0');

// 4. Recipient fetches envelope
$fetch = $vault->fetch($id);
echo "[4] Envelope fetched by recipient: ok=" . ($fetch['ok'] ? 'true' : 'false') . "\n";
assert($fetch['ok'] === true, 'Fetch failed');

// 5. Verify receipt after fetch (Opened state)
$rcpt2 = $vault->receipt($id, $rkHash);
echo "[5] Receipt check #2 (Opened): " . json_encode($rcpt2) . "\n";
assert($rcpt2['ok'] === true, 'Receipt 2 failed');
assert($rcpt2['opened'] > 0, 'Opened timestamp should be non-zero');

// 6. Recipient / server burns envelope
$burn = $vault->burn($id, 'read');
echo "[6] Envelope burned: ok=" . ($burn['ok'] ? 'true' : 'false') . "\n";

// 7. Verify receipt after burn (Tombstone state)
$rcpt3 = $vault->receipt($id, $rkHash);
echo "[7] Receipt check #3 (Burned/Tombstone): " . json_encode($rcpt3) . "\n";
assert($rcpt3['ok'] === true, 'Receipt 3 failed');
assert($rcpt3['burned'] > 0, 'Burned timestamp should be non-zero');
assert($rcpt3['why'] === 'read', 'Why should be read');

// 8. Run GC (Opportunistic Sweep)
$vault->gc();
echo "[8] GC run completed.\n";

// 9. Verify receipt after GC (Tombstone preserved)
$rcpt4 = $vault->receipt($id, $rkHash);
echo "[9] Receipt check #4 (Post-GC Tombstone): " . json_encode($rcpt4) . "\n";
assert($rcpt4['ok'] === true, 'Receipt 4 failed');

echo "\n>>> ALL PIPELINE CHECKS PASSED PERFECTLY! <<<\n";
