# blackend — Anti-Pattern, Bad Logic & Flaw Analysis

This document provides a critical architectural audit of bad logic, anti-patterns, race conditions, memory leaks, and serverless execution flaws identified across the system pipeline (referenced against `ARCHITECTURE_PIPELINE.md`), along with their corresponding engineered remedies.

---

## 1. Truncated File Shredding Anti-Pattern

### ❌ The Bad Logic
In secure single-read messaging systems, shredded files must be completely overwritten with random bytes prior to unlinking (`unlink`) so physical disk sectors contain zero residual data.

In the initial implementation of `lib/Vault.php`:
```php
// FLAWED IMPLEMENTATION
private function shred(string $path): void {
    if (!is_file($path)) return;
    $n = (int)@filesize($path);
    $fp = @fopen($path, 'r+');
    if ($fp && $n > 0) {
        @fseek($fp, 0);
        @fwrite($fp, random_bytes(min($n, 1048576))); // ⚠️ Only overwrote up to 1 MB!
        @fflush($fp);
        @fclose($fp);
    }
    @unlink($path);
}
```
If a user attached a 5 MB image or audio file, `min($n, 1048576)` overwrote only the first 1 MB with random noise. The remaining 4 MB remained in plain unencrypted disk sectors before `unlink()`, leaving data recoverable via un-delete or forensic tools.

### ✅ Engineered Remedy
```php
// REFACTORED SECURE IMPLEMENTATION
private function shred(string $path): void {
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
```
**Impact**: 100% of bytes in multi-megabyte files are cryptographically overwritten before unlinking.

---

## 2. Unbounded Serverless SSE Stream Execution Timeout

### ❌ The Bad Logic
In traditional server environments (Apache/Nginx with long-lived PHP processes), long-polling or Server-Sent Events (SSE) streaming connections can run for 55+ seconds safely.

In `lib/Vault.php`:
```php
// FLAWED SERVERLESS STREAM
public function watch(string $id): void {
    $deadline = time() + 55; // ⚠️ Caused 504 Gateway Timeout on Vercel!
    while (time() < $deadline && !connection_aborted()) {
        ...
        sleep(1);
    }
}
```
Vercel serverless function limits default to 10 seconds on standard/hobby tier. Setting a 55-second loop caused Vercel to terminate the process abruptly with a `504 Gateway Timeout` error, generating broken client-side HTTP connections.

### ✅ Engineered Remedy
```php
// SERVERLESS-AWARE WATCHER
public function watch(string $id): void {
    $isServerless = (bool)(getenv('VERCEL') || getenv('VERCEL_ENV') || getenv('AWS_LAMBDA_FUNCTION_NAME'));
    $maxSec = $isServerless ? 8 : 25;
    $deadline = time() + $maxSec;
    ...
}
```
**Impact**: Caps streaming window to 8s on serverless runtimes. Client transparently reconnects or receives status without gateway timeout failures.

---

## 3. Polling Interval & Watcher Memory Leaks

### ❌ The Bad Logic
In `assets/js/backend.js`, when SSE streaming fails or isn't supported, `watch()` falls back to `_startPoll()` via `setInterval()`:

```javascript
// FLAWED WATCHER FALLBACK
function _startPoll(token, onEvent) {
    _pollTimer = setInterval(async () => {
        if (!_watching) return; // ⚠️ Stopped polling, BUT NEVER CLEARED THE INTERVAL!
        ...
    }, 2500);
}
```
When `_watching` was set to `false` via `stopWatch()`, `_startPoll()` exited the callback early, but `setInterval` continued to fire every 2.5 seconds indefinitely in the background, consuming CPU cycles and leaking memory in single-page navigation.

### ✅ Engineered Remedy
```javascript
// REFACTORED WATCHER CLEANUP
function _startPoll(token, onEvent) {
    _pollTimer = setInterval(async () => {
        if (!_watching) {
            if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
            return;
        }
        ...
    }, 2500);
}
```
**Impact**: Instantly clears `_pollTimer` and terminates interval execution whenever watcher state changes.

---

## 4. Orphaned Global Event Listeners in View Decryption

### ❌ The Bad Logic
When decrypting and viewing a message in `assets/js/app.js`:
```javascript
document.addEventListener('visibilitychange', onVisibility);
window.addEventListener('pagehide', onBurnBeforeExit);
window.addEventListener('beforeunload', onBurnBeforeExit);
window.addEventListener('blur', cdPause);
window.addEventListener('focus', cdResume);
```
If `renderDecryptedMessage` was called multiple times across session viewings, previous listeners remained attached to `window` and `document`, accumulating orphaned handlers and firing duplicate burn triggers upon visibility changes.

### ✅ Engineered Remedy
```javascript
async function renderDecryptedMessage(obj, kb, customCdSecs) {
    // Explicitly clean up any previously registered event listeners
    if (typeof window.__cdCleanup === 'function') {
        window.__cdCleanup();
        window.__cdCleanup = null;
    }
    ...
}
```
**Impact**: Guarantees zero duplicate listeners or orphaned handlers on global window objects.

---

## 5. API Switch Case Router Fallthrough

### ❌ The Bad Logic
In `vault.php`:
```php
switch ($action) {
    case 'store':
        out($vault->store(...));
    case 'put':
        out($vault->put(...));
    case 'ready':
        ...
```
Although `out()` calls `exit;`, omitting defensive `break;` statements created fallthrough risks if `out()` failed or threw an exception, leading to execution of unintended actions.

### ✅ Engineered Remedy
Added explicit `break;` statements to every `case` block in `vault.php`.

---

## 6. Unlocked File Operations in Multi-Chunk File Retrieval

### ❌ The Bad Logic
In `Vault::chunk($id, $i)`:
Reading and shredding chunk file `$p` was performed without acquiring an exclusive lock (`$this->lock($dir)`). Concurrent chunk download requests or concurrent garbage collection could race, causing missing chunk exceptions mid-download.

### ✅ Engineered Remedy
Wrapped `Vault::chunk` and `Vault::status` destruction logic inside `$fp = $this->lock($dir); try { ... } finally { $this->unlock($fp); }`.

---

## 7. Summary Matrix of Anti-Patterns vs Remedies

| Issue Area | Flawed Logic / Anti-Pattern | Root Cause | Engineered Fix |
|---|---|---|---|
| **Storage Security** | Overwrote only 1 MB of multi-megabyte attachments | `min($n, 1048576)` capped overwrite | Chunked loop overwriting 100% of file size before `unlink` |
| **Serverless Deployment** | 55-second SSE watch loop | Exceeded Vercel 10s execution cap | Environment detection + 8s serverless watch deadline |
| **JS Engine Memory** | `setInterval` running after `stopWatch()` | Missing `clearInterval` call | Explicit `clearInterval` inside interval check and `stopWatch()` |
| **DOM Event Handlers** | Accumulated window listeners on re-read | No pre-detachment handle | Prior invocation of `window.__cdCleanup()` in view renderer |
| **API Control Flow** | Omitted `break` in `switch ($action)` | Implicit reliance on `exit;` | Added explicit `break;` on all API cases |
| **Concurrency** | Lockless chunk reading & shredding | Missing `flock` wrapper | Enclosed `Vault::chunk` in `$this->lock($dir)` |
