<?php
declare(strict_types=1);

final class Vault
{
    private array $cfg;
    private string $dir;
    private string $key;

    // Poetic Diceware Codename word pools for creative URLs
    private const ADJECTIVES = [
        'ash', 'dark', 'pale', 'silent', 'quiet', 'cold', 'ember', 'frost',
        'hollow', 'neon', 'lunar', 'solar', 'shadow', 'swift', 'wild', 'ghost'
    ];
    private const NOUNS = [
        'fox', 'wolf', 'lynx', 'raven', 'moth', 'falcon', 'otter', 'hare',
        'viper', 'kite', 'heron', 'owl', 'stag', 'pike', 'crow', 'doe'
    ];

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->dir = rtrim((string)$cfg['data_dir'], '/\\');
        $this->key = hash('sha256', (string)$cfg['secret'], true);
        
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0770, true);
        }
        
        // Defense-in-depth: deny direct HTTP access to data directory
        if (is_dir($this->dir) && !is_file($this->dir . '/.htaccess')) {
            @file_put_contents($this->dir . '/.htaccess', "Require all denied\n");
        }
    }

    /* ---------- Plumbing & ID Generation ---------- */

    /** Derives a blinded, time-bound dead-drop token from shared secret and date */
    public static function deriveRendezvousToken(string $secret, ?int $timeSec = null): string
    {
        $dateStr = date('Y-m-d', $timeSec ?? time());
        $derivedBits = hash_hkdf('sha256', $secret, 12, 'blackend-rendezvous-v1', $dateStr);
        return rtrim(strtr(base64_encode($derivedBits), '+/', '-_'), '=');
    }

    /** Generates a creative nano-token (e.g. "ash-fox-42" or 6-char Base62 "7xK9pQ") */
    public function generateId(): string
    {
        // 50% chance of creative poetic codename, 50% 6-char Base62
        if (random_int(0, 1) === 1) {
            $adj = self::ADJECTIVES[random_int(0, count(self::ADJECTIVES) - 1)];
            $noun = self::NOUNS[random_int(0, count(self::NOUNS) - 1)];
            $num = random_int(10, 99);
            return "{$adj}-{$noun}-{$num}";
        }

        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $res = '';
        $bytes = random_bytes(6);
        for ($i = 0; $i < 6; $i++) {
            $res .= $chars[ord($bytes[$i]) % 62];
        }
        return $res;
    }

    private function dirOf(string $id): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{4,64}$/', $id)) {
            throw new RuntimeException('bad id');
        }
        return $this->dir . '/' . $id;
    }

    private function lock(string $dir)
    {
        $fp = @fopen($dir . '/lock', 'c');
        if (!$fp) {
            throw new RuntimeException('lock');
        }
        flock($fp, LOCK_EX);
        return $fp;
    }

    private function unlock($fp): void
    {
        if (is_resource($fp)) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function meta(string $dir): ?array
    {
        $p = $dir . '/meta.json';
        if (!is_file($p)) return null;
        $m = json_decode((string)@file_get_contents($p), true);
        return is_array($m) ? $m : null;
    }

    private function putMeta(string $dir, array $m): void
    {
        @file_put_contents($dir . '/meta.json', json_encode($m), LOCK_EX);
    }

    private function write(string $path, string $data): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $data, LOCK_EX);
        rename($tmp, $path);
    }

    /** Overwrite file with cryptographic random noise before unlink */
    private function shred(string $path): void
    {
        if (!is_file($path)) return;
        $n = (int)@filesize($path);
        $fp = @fopen($path, 'r+');
        if ($fp) {
            if ($n > 0) {
                @fseek($fp, 0);
                $rem = $n;
                while ($rem > 0) {
                    $writeLen = min($rem, 1048576);
                    @fwrite($fp, random_bytes($writeLen));
                    $rem -= $writeLen;
                }
                @fflush($fp);
            }
            @fclose($fp);
        }
        @unlink($path);
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->rmTree($path);
            } else {
                $this->shred($path);
            }
        }
        @rmdir($dir);
    }

    /* At-rest layer: blobs on disk are encrypted using server key */
    private function sealBlob(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new RuntimeException('seal failed');
        }
        return 'BK1' . $iv . $tag . $ct;
    }

    private function openBlob(string $blob): string
    {
        if (strlen($blob) < 31 || substr($blob, 0, 3) !== 'BK1') {
            throw new RuntimeException('invalid blob');
        }
        $iv = substr($blob, 3, 12);
        $tag = substr($blob, 15, 16);
        $ct = substr($blob, 31);
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new RuntimeException('blob decryption failed');
        }
        return $pt;
    }

    private static function b64d(string $s): string
    {
        $r = base64_decode(strtr($s, '-_', '+/'), true);
        return $r === false ? '' : $r;
    }

    private static function b64e(string $b): string
    {
        return rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    }

    private function whyOk(string $w): string
    {
        return in_array($w, ['read', 'expired', 'killed', 'wiped', 'duress'], true) ? $w : 'killed';
    }

    /** Full destruction, leaving status tombstone.
     *  Preserves receipt-relevant fields (rk_hash, created, opened)
     *  so the sender can still verify the chain-of-custody after burn. */
    private function destroy(string $dir, string $why): void
    {
        $tombP = $dir . '/tombstone.json';
        $existingTomb = is_file($tombP)
            ? (json_decode((string)@file_get_contents($tombP), true) ?: [])
            : [];

        // Snapshot receipt fields from meta BEFORE shredding it, or preserve from existing tombstone
        $m        = $this->meta($dir);
        $rkHash   = ($m ? ($m['rk_hash'] ?? null) : null) ?: ($existingTomb['rk_hash'] ?? null);
        $created  = ($m ? (int)($m['created'] ?? 0) : 0) ?: (int)($existingTomb['created'] ?? 0);
        $openedAt = ($m ? (int)($m['read']    ?? 0) : 0) ?: (int)($existingTomb['opened']  ?? 0);
        $existingWhy = (string)($existingTomb['why'] ?? '');

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $f) {
            if ($f === 'lock' || $f === 'tombstone.json') continue;
            $path = $dir . '/' . $f;
            if (is_dir($path)) {
                $this->rmTree($path);
            } else {
                $this->shred($path);
            }
        }

        $tombstone = [
            'why'     => $this->whyOk($existingWhy ?: $why),
            't'       => !empty($existingTomb['t']) ? (int)$existingTomb['t'] : time(),
            'created' => $created ?: null,
            'opened'  => $openedAt ?: null,
        ];
        if ($rkHash) $tombstone['rk_hash'] = $rkHash;

        @file_put_contents(
            $tombP,
            json_encode($tombstone)
        );
    }

    /* ---------- API Methods ---------- */

    public function store(
        int $exp,
        bool $pin,
        int $nc,
        string $ivB64,
        string $ctB64,
        string $saltB64    = '',
        string $wivB64     = '',
        string $wrappedB64 = '',
        array  $settings   = [],   // public per-message display settings (unencrypted)
        string $rkHash     = '',   // SHA-256 hash of sender's receipt key
        string $dSaltB64   = '',   // optional duress PIN salt
        string $dWivB64    = '',   // optional duress wrapper IV
        string $dWrappedB64 = '',  // optional duress wrapped key
        string $dIvB64     = '',   // optional duress payload IV
        string $dCtB64     = ''    // optional duress payload CT
    ): array {
        $iv = self::b64d($ivB64);
        $ct = self::b64d($ctB64);
        if (strlen($iv) !== 12 || strlen($ct) < 16 || strlen($ct) > 65536) {
            throw new RuntimeException('envelope');
        }
        if ($exp < 0 || $exp > (int)$this->cfg['max_life']) {
            throw new RuntimeException('exp');
        }
        if ($nc < 0) {
            throw new RuntimeException('chunks');
        }
        if ($nc * (int)$this->cfg['chunk_size'] > (int)$this->cfg['max_file'] || $nc > (int)$this->cfg['max_chunks']) {
            throw new RuntimeException('too large');
        }

        // Generate clean creative short token
        $id = $this->generateId();
        $dir = $this->dir . '/' . $id;
        while (is_dir($dir)) {
            $id = $this->generateId();
            $dir = $this->dir . '/' . $id;
        }

        if (!@mkdir($dir, 0770, true)) {
            throw new RuntimeException('mkdir');
        }

        $envPayload = [
            'iv'      => $ivB64,
            'ct'      => $ctB64,
            'salt'    => $saltB64,
            'wiv'     => $wivB64,
            'wrapped' => $wrappedB64
        ];
        if ($dSaltB64 && $dWivB64 && $dWrappedB64) {
            $envPayload['d_salt']    = $dSaltB64;
            $envPayload['d_wiv']     = $dWivB64;
            $envPayload['d_wrapped'] = $dWrappedB64;
            if ($dIvB64) $envPayload['d_iv'] = $dIvB64;
            if ($dCtB64) $envPayload['d_ct'] = $dCtB64;
        }

        $env = $this->sealBlob(json_encode($envPayload));
        $this->write($dir . '/env.bin', $env);

        // Whitelist and sanitise public display settings
        $safeSettings   = [];
        $allowedAccents = ['ember', 'crimson', 'mint', 'ice'];
        $allowedBurns   = ['calm', 'quick', 'custom'];
        if (!empty($settings['accent']) && in_array($settings['accent'], $allowedAccents, true))
            $safeSettings['accent']   = $settings['accent'];
        if (!empty($settings['burn']) && in_array($settings['burn'], $allowedBurns, true))
            $safeSettings['burn']     = $settings['burn'];
        if (isset($settings['burnTime'])) {
            $bt = (float)$settings['burnTime'];
            if ($bt >= 0.2 && $bt <= 30.0) $safeSettings['burnTime'] = round($bt, 2);
        }
        if (isset($settings['readTime'])) {
            $rt = (int)$settings['readTime'];
            if ($rt >= 1 && $rt <= 300) $safeSettings['readTime'] = $rt;
        }

        // Validate receipt key hash: base64url SHA-256 is always 43 chars
        $normRk = rtrim(strtr((string)$rkHash, '+/', '-_'), '=');
        $safeRkHash = '';
        if ($normRk && preg_match('/^[A-Za-z0-9_-]{43,44}$/', $normRk))
            $safeRkHash = $normRk;

        $life = $exp > 0 ? min($exp, (int)$this->cfg['max_life']) : (int)$this->cfg['max_life'];
        $this->putMeta($dir, [
            'v'        => 2,
            'exp'      => time() + $life,
            'pin'      => $pin ? 1 : 0,
            'nc'       => $nc,
            'tries'    => 0,
            'state'    => $nc > 0 ? 'incomplete' : 'complete',
            'created'  => time(),
            'settings' => $safeSettings ?: null,
            'rk_hash'  => $safeRkHash  ?: null,
        ]);
        return ['ok' => true, 'id' => $id];
    }

    public function put(string $id, int $i, string $dataB64): array
    {
        $dir = $this->dirOf($id);
        $fp = $this->lock($dir);
        try {
            $m = $this->meta($dir);
            if (!$m || ($m['state'] ?? '') !== 'incomplete') throw new RuntimeException('state');
            if ($i < 0 || $i >= (int)$m['nc']) throw new RuntimeException('index');
            $raw = self::b64d($dataB64);
            $cap = (int)$this->cfg['chunk_size'] + 64 + 4096;
            if (strlen($raw) < 13 || strlen($raw) > $cap) throw new RuntimeException('chunk');
            if (!is_dir($dir . '/chunks')) @mkdir($dir . '/chunks', 0770, true);
            $this->write(
                $dir . '/chunks/' . str_pad((string)$i, 4, '0', STR_PAD_LEFT) . '.bin',
                $this->sealBlob($dataB64)
            );
            return ['ok' => true];
        } finally {
            $this->unlock($fp);
        }
    }

    public function ready(string $id): array
    {
        $dir = $this->dirOf($id);
        $fp = $this->lock($dir);
        try {
            $m = $this->meta($dir);
            if (!$m) throw new RuntimeException('missing');
            $m['state'] = 'complete';
            $this->putMeta($dir, $m);
            return ['ok' => true];
        } finally {
            $this->unlock($fp);
        }
    }

    /** Envelope fetch: Accessible during active claim window; destroyed upon burn or claim expiry */
    public function fetch(string $id): array
    {
        $dir = $this->dirOf($id);
        if (!is_dir($dir)) {
            return ['ok' => false, 'why' => 'unknown'];
        }
        $fp = $this->lock($dir);
        try {
            $tomb = $dir . '/tombstone.json';
            if (is_file($tomb)) {
                $t = json_decode((string)@file_get_contents($tomb), true) ?: [];
                return ['ok' => false, 'why' => (string)($t['why'] ?? 'read')];
            }

            $m = $this->meta($dir);
            if (!$m) return ['ok' => false, 'why' => 'unknown'];
            if (time() > (int)$m['exp']) {
                $this->destroy($dir, 'expired');
                return ['ok' => false, 'why' => 'expired'];
            }
            if (($m['state'] ?? '') !== 'complete') {
                $this->destroy($dir, 'unknown');
                return ['ok' => false, 'why' => 'unknown'];
            }
            if (!empty($m['read']) && time() > (int)($m['claim'] ?? 0)) {
                $this->destroy($dir, 'read');
                return ['ok' => false, 'why' => 'read'];
            }

            $envP = $dir . '/env.bin';
            if (!is_file($envP)) {
                return ['ok' => false, 'why' => 'read'];
            }

            $env = json_decode($this->openBlob((string)file_get_contents($envP)), true);
            $alreadyRead = !empty($m['read']);
            $isPin = !empty($m['pin']);

            // Non-PIN envelopes claim on fetch. PIN-protected envelopes claim upon PIN unlock (open).
            if (empty($m['read']) && !$isPin) {
                $m['read']  = time();
                $m['claim'] = time() + (int)$this->cfg['claim_window'];
                $this->putMeta($dir, $m);
            }

            return [
                'ok'           => true,
                'iv'           => (string)($env['iv'] ?? ''),
                'ct'           => (string)($env['ct'] ?? ''),
                'salt'         => (string)($env['salt'] ?? ''),
                'wiv'          => (string)($env['wiv'] ?? ''),
                'wrapped'      => (string)($env['wrapped'] ?? ''),
                'd_salt'       => (string)($env['d_salt'] ?? ''),
                'd_wiv'        => (string)($env['d_wiv'] ?? ''),
                'd_wrapped'    => (string)($env['d_wrapped'] ?? ''),
                'd_iv'         => (string)($env['d_iv'] ?? ''),
                'd_ct'         => (string)($env['d_ct'] ?? ''),
                'pin'          => $isPin,
                'nc'           => (int)$m['nc'],
                'read'         => (int)($m['read'] ?? 0),
                'claim'        => (int)($m['claim'] ?? 0),
                'already_read' => $alreadyRead,
                'settings'     => $m['settings'] ?? null,  // sender display settings
                'now'          => time()
            ];
        } finally {
            $this->unlock($fp);
        }
    }

    public function chunk(string $id, int $i): array
    {
        $dir = $this->dirOf($id);
        if (!is_dir($dir)) throw new RuntimeException('missing');
        $fp = $this->lock($dir);
        try {
            $m = $this->meta($dir);
            if (!$m || empty($m['read'])) throw new RuntimeException('not claimed');
            if (time() > (int)($m['claim'] ?? 0)) throw new RuntimeException('claim expired');
            if ($i < 0 || $i >= (int)$m['nc']) throw new RuntimeException('index');
            $p = $dir . '/chunks/' . str_pad((string)$i, 4, '0', STR_PAD_LEFT) . '.bin';
            if (!is_file($p)) throw new RuntimeException('missing');
            $data = $this->openBlob((string)file_get_contents($p));
            $this->shred($p); // Shred chunk on delivery
            return ['ok' => true, 'data' => $data];
        } finally {
            $this->unlock($fp);
        }
    }

    public function burn(string $id, string $why): array
    {
        $dir = $this->dirOf($id);
        if (!is_dir($dir)) return ['ok' => true];
        $fp = $this->lock($dir);
        try {
            if (is_file($dir . '/tombstone.json')) {
                return ['ok' => true];
            }
            $this->destroy($dir, $why);
            return ['ok' => true];
        } finally {
            $this->unlock($fp);
        }
    }

    /** Explicit open / claim when PIN is unlocked or message starts viewing */
    public function open(string $id): array
    {
        $dir = $this->dirOf($id);
        if (!is_dir($dir)) return ['ok' => false, 'why' => 'unknown'];
        $fp = $this->lock($dir);
        try {
            $tomb = $dir . '/tombstone.json';
            if (is_file($tomb)) {
                $t = json_decode((string)@file_get_contents($tomb), true) ?: [];
                return ['ok' => false, 'why' => (string)($t['why'] ?? 'read')];
            }
            $m = $this->meta($dir);
            if (!$m || ($m['state'] ?? '') !== 'complete') return ['ok' => false, 'why' => 'unknown'];
            if (time() > (int)$m['exp']) {
                $this->destroy($dir, 'expired');
                return ['ok' => false, 'why' => 'expired'];
            }
            $alreadyRead = !empty($m['read']);
            if (empty($m['read'])) {
                $m['read']  = time();
                $m['claim'] = time() + (int)$this->cfg['claim_window'];
                $this->putMeta($dir, $m);
            }
            return [
                'ok'           => true,
                'read'         => (int)$m['read'],
                'claim'        => (int)$m['claim'],
                'already_read' => $alreadyRead,
                'now'          => time()
            ];
        } finally {
            $this->unlock($fp);
        }
    }

    /** PIN attempt counting — limits brute force */
    public function fail(string $id): array
    {
        $dir = $this->dirOf($id);
        if (!is_dir($dir)) return ['ok' => true, 'state' => 'gone'];
        $fp = $this->lock($dir);
        try {
            $m = $this->meta($dir);
            if (!$m) return ['ok' => true, 'state' => 'gone'];
            $m['tries'] = ((int)($m['tries'] ?? 0)) + 1;
            if ($m['tries'] >= 3) {
                $this->destroy($dir, 'killed');
                return ['ok' => true, 'state' => 'killed', 'left' => 0];
            }
            $this->putMeta($dir, $m);
            return ['ok' => true, 'state' => 'locked', 'left' => max(0, 3 - (int)$m['tries'])];
        } finally {
            $this->unlock($fp);
        }
    }

    public function status(string $id): array
    {
        $dir = $this->dirOf($id);
        $tomb = $dir . '/tombstone.json';
        if (is_file($tomb)) {
            $t = json_decode((string)@file_get_contents($tomb), true) ?: [];
            return ['ok' => true, 'state' => 'gone', 'why' => (string)($t['why'] ?? 'read'), 'now' => time()];
        }
        $m = $this->meta($dir);
        if (!$m || ($m['state'] ?? '') !== 'complete') return ['ok' => true, 'state' => 'gone', 'why' => 'unknown', 'now' => time()];
        if (time() > (int)$m['exp']) {
            $fp = $this->lock($dir);
            try { $this->destroy($dir, 'expired'); } finally { $this->unlock($fp); }
            return ['ok' => true, 'state' => 'gone', 'why' => 'expired', 'now' => time()];
        }
        if (!empty($m['read'])) {
            if (time() > (int)($m['claim'] ?? 0)) {
                $fp = $this->lock($dir);
                try { $this->destroy($dir, 'read'); } finally { $this->unlock($fp); }
                return ['ok' => true, 'state' => 'gone', 'why' => 'read', 'now' => time()];
            }
            return ['ok' => true, 'state' => 'opened', 'read' => (int)$m['read'], 'now' => time()];
        }
        return ['ok' => true, 'state' => 'sealed', 'now' => time()];
    }

    /** Robust garbage collection: safely purges expired tombstones and stale envelopes */
    public function gc(): void
    {
        $now = time();
        if (!is_dir($this->dir)) return;
        
        $entries = scandir($this->dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.htaccess') continue;
            
            // Validate token format safely
            if (!preg_match('/^[A-Za-z0-9_-]{4,64}$/', $entry)) continue;
            
            $dir = $this->dir . '/' . $entry;
            if (!is_dir($dir)) continue;

            try {
                $tomb = $dir . '/tombstone.json';
                if (is_file($tomb)) {
                    $t = json_decode((string)@file_get_contents($tomb), true) ?: [];
                    if ($now - (int)($t['t'] ?? 0) > (int)$this->cfg['tombstone_life']) {
                        $this->rmTree($dir);
                    }
                    continue;
                }
                
                $m = $this->meta($dir);
                if (!$m) {
                    if ($now - (int)@filemtime($dir) > (int)$this->cfg['store_ttl']) {
                        $this->rmTree($dir);
                    }
                    continue;
                }
                
                if (($m['state'] ?? '') !== 'complete'
                    && $now - (int)($m['created'] ?? 0) > (int)$this->cfg['store_ttl']) {
                    $this->rmTree($dir);
                    continue;
                }
                
                if (empty($m['read']) && $now > (int)$m['exp']) {
                    $fp = $this->lock($dir);
                    try { $this->destroy($dir, 'expired'); } finally { $this->unlock($fp); }
                    continue;
                }
                
                if (!empty($m['read']) && $now > (int)($m['claim'] ?? 0)) {
                    $fp = $this->lock($dir);
                    try { $this->destroy($dir, 'read'); } finally { $this->unlock($fp); }
                    continue;
                }
            } catch (Throwable $e) {
                // GC is best-effort
            }
        }
    }

    /* ---------- Real-time SSE Watcher ---------- */

    /**
     * Streams server-sent events for a vault token until state reaches 'gone'
     * or 55 seconds elapse (fits inside most proxy timeouts).
     *
     * Caller MUST have sent SSE-compatible HTTP headers before invoking this.
     * Emits: data: {ok, state, why?, read?, now}\n\n
     */
    public function watch(string $id): void
    {
        $isServerless = (bool)(getenv('VERCEL') || getenv('VERCEL_ENV') || getenv('AWS_LAMBDA_FUNCTION_NAME'));
        $maxSec = $isServerless ? 8 : 25;
        $deadline  = time() + $maxSec;
        $lastState = null;
        $hb        = 0;

        while (time() < $deadline && !connection_aborted()) {
            try {
                $r     = $this->status($id);
                $state = (string)($r['state'] ?? 'gone');

                if ($state !== $lastState) {
                    $lastState = $state;
                    echo 'data: ' . json_encode($r, JSON_UNESCAPED_SLASHES) . "\n\n";
                    @flush();
                    if ($state === 'gone') break;
                }
            } catch (Throwable $e) {
                echo 'data: ' . json_encode(['ok' => false, 'state' => 'gone', 'why' => 'error', 'now' => time()]) . "\n\n";
                @flush();
                break;
            }

            // Heartbeat every 5 s keeps proxies from closing the connection
            if (++$hb % 10 === 0) {
                echo ": heartbeat\n\n";
                @flush();
            }

            usleep(500000); // 0.5 sec sleep for responsive updates
        }

        if ($lastState !== 'gone') {
            echo 'data: ' . json_encode(['ok' => true, 'state' => 'timeout', 'now' => time()]) . "\n\n";
            @flush();
        }
    }

    /* ---------- Receipt Chain-of-Custody ---------- */

    /**
     * Returns a chain-of-custody audit trail for the sender.
     * Validates that the caller knows the plaintext receipt key by checking its
     * SHA-256 hash against the value stored at seal time.
     *
     * Returns: { ok, created, opened, burned, why, now }
     */
    public function receipt(string $id, string $rkHash): array
    {
        // Basic hash format guard (base64url SHA-256 = 43 chars)
        $normRk = rtrim(strtr((string)$rkHash, '+/', '-_'), '=');
        if (!preg_match('/^[A-Za-z0-9_-]{43,44}$/', $normRk)) {
            return ['ok' => false, 'error' => 'invalid_key'];
        }

        try {
            $dir = $this->dirOf($id);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        if (!is_dir($dir)) {
            return ['ok' => false, 'error' => 'purged'];
        }

        $metaP = $dir . '/meta.json';
        $tombP = $dir . '/tombstone.json';

        $m    = is_file($metaP) ? $this->meta($dir) : null;
        $tomb = is_file($tombP)
            ? (json_decode((string)@file_get_contents($tombP), true) ?: [])
            : null;

        // Receipt key hash may live in live meta OR tombstone (preserved on destroy)
        $storedHash = ($m['rk_hash'] ?? null) ?: ($tomb['rk_hash'] ?? null);

        if (!$storedHash) {
            return ['ok' => false, 'error' => 'no_receipt'];
        }

        if (!hash_equals((string)$storedHash, $normRk)) {
            return ['ok' => false, 'error' => 'key_mismatch'];
        }

        $created  = (int)(($m['created'] ?? null) ?? ($tomb['created'] ?? 0));
        $openedAt = (int)(($m['read']    ?? null) ?? ($tomb['opened']  ?? 0));
        $burnedAt = $tomb ? (int)($tomb['t']   ?? 0) : 0;
        $why      = $tomb ? (string)($tomb['why'] ?? '') : '';

        return [
            'ok'      => true,
            'created' => $created,
            'opened'  => $openedAt,
            'burned'  => $burnedAt,
            'why'     => $why ?: null,
            'now'     => time(),
        ];
    }
}