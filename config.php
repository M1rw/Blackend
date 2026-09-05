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

return [
    /* At-rest layer key. Server-blind E2E does not depend on this — it only
       protects raw disk/backup theft. Generate once: php -r "echo bin2hex(random_bytes(32));" */
    'secret'        => getenv('VAULT_SECRET') ?: '366889761E0D3E707F02F74DE1784375',

    'data_dir'      => $dataDir,

    'chunk_size'    => 262144,   // 256 KB — must match CHUNK in index.php
    'max_file'      => 25 * 1048576,
    'max_chunks'    => 160,

    'max_life'      => 604800,   // hard cap: 7 days, even for read-once
    'claim_window'  => 600,      // seconds: 10 min to pull chunks after fetch
    'store_ttl'     => 1800,     // incomplete uploads GC'd after 30 min
    'tombstone_life'=> 86400,    // status tombstones live 24 h
];
