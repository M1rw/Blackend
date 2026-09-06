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
                @fwrite($fp, random_bytes(min($n, 1048576)));
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
        return in_array($w, ['read', 'expired', 'killed', 'wiped'], true) ? $w : 'killed';
    }

    /** Full destruction, leaving status tombstone */
    private function destroy(string $dir, string $why): void
    {
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
        @file_put_contents(
            $dir . '/tombstone.json',
            json_encode(['why' => $this->whyOk($why), 't' => time()])
        );
    }

    /* ---------- API Methods ---------- */

    public function store(
        int $exp,
        bool $pin,
        int $nc,
        string $ivB64,
        string $ctB64,
        string $saltB64 = '',
        string $wivB64 = '',
        string $wrappedB64 = ''
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

        $env = $this->sealBlob(json_encode($envPayload));
        $this->write($dir . '/env.bin', $env);

        $life = $exp > 0 ? min($exp, (int)$this->cfg['max_life']) : (int)$this->cfg['max_life'];
        $this->putMeta($dir, [
            'v'       => 2,
            'exp'     => time() + $life,
            'pin'     => $pin ? 1 : 0,
            'nc'      => $nc,
            'tries'   => 0,
            'state'   => $nc > 0 ? 'incomplete' : 'complete',
            'created' => time(),
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
                $this->sealBlob($raw)
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

    /** ONE read: envelope is shredded the moment it leaves the vault */
    public function fetch(string $id): array
    {
        $dir = $this->dirOf($id);
        if (!is_dir($dir)) {
            return ['ok' => false, 'why' => 'unknown'];
        }
        $fp = $this->lock($dir);
        try {
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
            if (!empty($m['read'])) {
                return ['ok' => false, 'why' => 'read'];
            }

            $envP = $dir . '/env.bin';
            if (!is_file($envP)) {
                return ['ok' => false, 'why' => 'read'];
            }

            $env = json_decode($this->openBlob((string)file_get_contents($envP)), true);
            $this->shred($envP); // The single read happened: shred ciphertext
            $m['read']  = time();
            $m['claim'] = time() + (int)$this->cfg['claim_window'];
            $this->putMeta($dir, $m);

            return [
                'ok'      => true,
                'iv'      => (string)($env['iv'] ?? ''),
                'ct'      => (string)($env['ct'] ?? ''),
                'salt'    => (string)($env['salt'] ?? ''),
                'wiv'     => (string)($env['wiv'] ?? ''),
                'wrapped' => (string)($env['wrapped'] ?? ''),
                'pin'     => !empty($m['pin']),
                'nc'      => (int)$m['nc']
            ];
        } finally {
            $this->unlock($fp);
        }
    }

    public function chunk(string $id, int $i): array
    {
        $dir = $this->dirOf($id);
        $m = $this->meta($dir);
        if (!$m || empty($m['read'])) throw new RuntimeException('not claimed');
        if (time() > (int)($m['claim'] ?? 0)) throw new RuntimeException('claim expired');
        if ($i < 0 || $i >= (int)$m['nc']) throw new RuntimeException('index');
        $p = $dir . '/chunks/' . str_pad((string)$i, 4, '0', STR_PAD_LEFT) . '.bin';
        if (!is_file($p)) throw new RuntimeException('missing');
        return ['ok' => true, 'data' => self::b64e($this->openBlob((string)file_get_contents($p)))];
    }

    public function burn(string $id, string $why): array
    {
        $dir = $this->dirOf($id);
        if (!is_dir($dir)) return ['ok' => true];
        $fp = $this->lock($dir);
        try {
            $this->destroy($dir, $why);
            return ['ok' => true];
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
            if (!empty($m['read'])) return ['ok' => true, 'state' => 'gone'];
            $m['tries'] = ((int)($m['tries'] ?? 0)) + 1;
            if ($m['tries'] >= 3) {
                $this->destroy($dir, 'killed');
                return ['ok' => true, 'state' => 'killed'];
            }
            $this->putMeta($dir, $m);
            return ['ok' => true, 'state' => 'locked', 'left' => 3 - $m['tries']];
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
            return ['state' => 'gone', 'why' => (string)($t['why'] ?? 'unknown')];
        }
        $m = $this->meta($dir);
        if (!$m || ($m['state'] ?? '') !== 'complete') return ['state' => 'gone', 'why' => 'unknown'];
        if (!empty($m['read'])) return ['state' => 'gone', 'why' => 'read'];
        if (time() > (int)$m['exp']) return ['state' => 'gone', 'why' => 'expired'];
        return ['state' => 'sealed'];
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
}