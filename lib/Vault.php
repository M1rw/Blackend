<?php
declare(strict_types=1);

final class Vault
{
    private array $cfg;
    private string $dir;
    private string $key;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->dir = rtrim((string)$cfg['data_dir'], '/');
        $this->key = hash('sha256', (string)$cfg['secret'], true);
        if (!is_dir($this->dir)) @mkdir($this->dir, 0770, true);
        // defense-in-depth for Apache; nginx: deny /data/ manually
        if (!is_file($this->dir . '/.htaccess')) {
            @file_put_contents($this->dir . '/.htaccess', "Require all denied\n");
        }
    }

    /* ---------- plumbing ---------- */

    private function dirOf(string $id): string
    {
        if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $id)) throw new RuntimeException('bad id');
        return $this->dir . '/' . $id;
    }

    private function lock(string $dir)
    {
        $fp = @fopen($dir . '/lock', 'c');
        if (!$fp) throw new RuntimeException('lock');
        flock($fp, LOCK_EX);
        return $fp;
    }

    private function unlock($fp): void { flock($fp, LOCK_UN); fclose($fp); }

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

    /** overwrite with noise, then unlink */
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
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_dir($f)) $this->rmTree($f); else @unlink($f);
        }
        @rmdir($dir);
    }

    /* at-rest layer: blobs on disk are opaque even to a disk thief.
       (the operator CAN open this layer — the E2E layer above it cannot be) */
    private function sealBlob(string $plain): string
    {
        $iv = random_bytes(12); $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) throw new RuntimeException('seal');
        return 'BK1' . $iv . $tag . $ct;
    }

    private function openBlob(string $blob): string
    {
        if (strlen($blob) < 31 || substr($blob, 0, 3) !== 'BK1') throw new RuntimeException('blob');
        $iv = substr($blob, 3, 12); $tag = substr($blob, 15, 16); $ct = substr($blob, 31);
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) throw new RuntimeException('blob');
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

    /** full destruction, leaves a status tombstone */
    private function destroy(string $dir, string $why): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            $b = basename($f);
            if ($b === 'lock' || $b === 'tombstone.json') continue;
            if (is_dir($f)) {
                foreach (glob($f . '/*') ?: [] as $cf) if (is_file($cf)) $this->shred($cf);
                @rmdir($f);
            } else {
                $this->shred($f);
            }
        }
        @file_put_contents($dir . '/tombstone.json',
            json_encode(['why' => $this->whyOk($why), 't' => time()]));
    }

    /* ---------- API ---------- */

    public function store(int $exp, bool $pin, int $nc, string $ivB64, string $ctB64): array
    {
        $iv = self::b64d($ivB64);
        $ct = self::b64d($ctB64);
        if (strlen($iv) !== 12 || strlen($ct) < 16 || strlen($ct) > 65536) throw new RuntimeException('envelope');
        if ($exp < 0 || $exp > (int)$this->cfg['max_life']) throw new RuntimeException('exp');
        if ($nc < 0) throw new RuntimeException('chunks');
        if ($nc * (int)$this->cfg['chunk_size'] > (int)$this->cfg['max_file'] || $nc > (int)$this->cfg['max_chunks'])
            throw new RuntimeException('too large');

        $id  = bin2hex(random_bytes(16));           // the token: pure randomness
        $dir = $this->dir . '/' . $id;
        if (!@mkdir($dir, 0770, true)) throw new RuntimeException('mkdir');

        $env = $this->sealBlob(json_encode(['iv' => $ivB64, 'ct' => $ctB64]));
        $this->write($dir . '/env.bin', $env);

        $life = $exp > 0 ? min($exp, (int)$this->cfg['max_life']) : (int)$this->cfg['max_life'];
        $this->putMeta($dir, [
            'v' => 1, 'exp' => time() + $life, 'pin' => $pin ? 1 : 0,
            'nc' => $nc, 'tries' => 0,
            'state' => $nc > 0 ? 'incomplete' : 'complete',
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
            $this->write($dir . '/chunks/' . str_pad((string)$i, 4, '0', STR_PAD_LEFT) . '.bin',
                $this->sealBlob($raw));
            return ['ok' => true];
        } finally { $this->unlock($fp); }
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
        } finally { $this->unlock($fp); }
    }

    /** ONE read: envelope is shredded the moment it leaves the vault */
    public function fetch(string $id): array
    {
        $dir = $this->dirOf($id);
        $fp = $this->lock($dir);
        try {
            $m = $this->meta($dir);
            if (!$m) return ['ok' => false, 'why' => 'unknown'];
            if (time() > (int)$m['exp']) { $this->destroy($dir, 'expired'); return ['ok' => false, 'why' => 'expired']; }
            if (($m['state'] ?? '') !== 'complete') { $this->destroy($dir, 'unknown'); return ['ok' => false, 'why' => 'unknown']; }
            if (!empty($m['read'])) return ['ok' => false, 'why' => 'read'];

            $envP = $dir . '/env.bin';
            if (!is_file($envP)) return ['ok' => false, 'why' => 'read'];

            $env = json_decode($this->openBlob((string)file_get_contents($envP)), true);
            $this->shred($envP);                       // the one read just happened
            $m['read']  = time();
            $m['claim'] = time() + (int)$this->cfg['claim_window'];
            $this->putMeta($dir, $m);
            return ['ok' => true, 'iv' => (string)$env['iv'], 'ct' => (string)$env['ct'], 'nc' => (int)$m['nc']];
        } finally { $this->unlock($fp); }
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
        try { $this->destroy($dir, $why); return ['ok' => true]; }
        finally { $this->unlock($fp); }
    }

    /** PIN attempt counting — honest online limit; see README for the offline caveat */
    public function fail(string $id): array
    {
        $dir = $this->dirOf($id);
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
        } finally { $this->unlock($fp); }
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

    public function gc(): void
    {
        $now = time();
        foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (!$this->dirOf(basename($dir))) continue;
            try {
                $tomb = $dir . '/tombstone.json';
                if (is_file($tomb)) {
                    $t = json_decode((string)@file_get_contents($tomb), true) ?: [];
                    if ($now - (int)($t['t'] ?? 0) > (int)$this->cfg['tombstone_life']) $this->rmTree($dir);
                    continue;
                }
                $m = $this->meta($dir);
                if (!$m) {
                    if ($now - (int)@filemtime($dir) > (int)$this->cfg['store_ttl']) $this->rmTree($dir);
                    continue;
                }
                if (($m['state'] ?? '') !== 'complete'
                    && $now - (int)($m['created'] ?? 0) > (int)$this->cfg['store_ttl']) {
                    $this->rmTree($dir); continue;
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
            } catch (Throwable $e) { /* gc is best-effort */ }
        }
    }
}