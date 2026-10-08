<?php
$dataDir = getenv('VAULT_DATA_DIR');
if (!$dataDir) {
    if (getenv('VERCEL') || getenv('VERCEL_ENV')) {
        $dataDir = sys_get_temp_dir() . '/blackend_data';
    } else {
        $defaultDir = __DIR__ . '/data';
        if (!is_dir($defaultDir) && !@mkdir($defaultDir, 0770, true) && !is_writable(__DIR__)) {
            $dataDir = sys_get_temp_dir() . '/blackend_data';
        } else {
            $dataDir = $defaultDir;
        }
    }
}

/* At-rest layer key. Prefer VAULT_SECRET; otherwise a random per-install key is
   generated once and kept beside the vault data (never a value committed to git). */
$secret = getenv('VAULT_SECRET') ?: '';
if ($secret === '') {
    $keyFile = rtrim($dataDir, '/\\') . '/.vault_secret';
    $secret = is_file($keyFile) ? trim((string)@file_get_contents($keyFile)) : '';
    if ($secret === '') {
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0770, true);
        }
        $secret = bin2hex(random_bytes(32));
        $tmp = $keyFile . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $secret) !== false) {
            @chmod($tmp, 0600);
            // Lose a concurrent first-run race gracefully: keep whichever key landed first
            if (!@link($tmp, $keyFile)) {
                $existing = trim((string)@file_get_contents($keyFile));
                if ($existing !== '') {
                    $secret = $existing;
                }
            }
            @unlink($tmp);
        }
    }
}

return [
    /* Server-blind E2E does not depend on this — it only protects raw
       disk/backup theft. Generate one: php -r "echo bin2hex(random_bytes(32));" */
    'secret'        => $secret,

    'data_dir'      => $dataDir,

    'chunk_size'    => 262144,   // 256 KB — must match CHUNK in index.php
    'max_file'      => 25 * 1048576,
    'max_chunks'    => 160,

    'max_life'      => 604800,   // hard cap: 7 days, even for read-once
    'claim_window'  => 600,      // seconds: 10 min to pull chunks after fetch
    'store_ttl'     => 1800,     // incomplete uploads GC'd after 30 min
    'tombstone_life'=> 86400,    // status tombstones live 24 h
];
