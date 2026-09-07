# blackend — System Audit & Issue Tracking (TODO)

This document tracks the resolution status of all architectural flaws, memory leaks, race conditions, and serverless execution issues identified in `ANTI_PATTERNS_ANALYSIS.md`.

---

## 1. Resolved Core Issues

| Issue ID | Area | Flaw / Anti-Pattern Description | Resolution Status | Verified In |
|---|---|---|---|---|
| **FIX-01** | `lib/Vault.php` | Truncated file shredding (`min($n, 1048576)`) leaving bytes >1MB on disk | ✅ RESOLVED | `tests/test_vault.php` (Test 9) |
| **FIX-02** | `lib/Vault.php` & `vault.php` | Unbounded 55s SSE stream causing Vercel 504 Gateway Timeouts | ✅ RESOLVED | `tests/test_vault.php` (Test 10) |
| **FIX-03** | `assets/js/backend.js` | Polling interval `setInterval` running after `stopWatch()` | ✅ RESOLVED | `tests/test_crypto.js` (Test 2.9) |
| **FIX-04** | `assets/js/app.js` | Orphaned global DOM event listeners during message re-reading | ✅ RESOLVED | Visual verification & Playwright script |
| **FIX-05** | `vault.php` | Omitted `break;` statements in `switch ($action)` API router | ✅ RESOLVED | PHP syntax & unit test suite |
| **FIX-06** | `lib/Vault.php` | Lockless chunk retrieval (`Vault::chunk`) causing concurrency races | ✅ RESOLVED | `tests/test_vault.php` (Test 6) |
| **FIX-07** | `assets/js/app.js` | `localStorage` quota overflow & corrupt array entry handling | ✅ RESOLVED | `loadChats()` & `persist()` error handlers |
| **FIX-08** | `assets/js/crypto.js` | LSB Image Steganography carrier payload encoding & extraction | ✅ RESOLVED | `tests/test_crypto.js` (Test 2.10) |
| **FIX-09** | `lib/Vault.php` & `crypto.js` | Multi-PIN Duress Decoy System (silent server burn on duress PIN) | ✅ RESOLVED | `tests/test_vault.php` & `test_crypto.js` |

---

## 2. Technical Issue Details & Fix Verification

### FIX-01: Multi-Megabyte File Overwrite
- **Problem**: `Vault::shred` previously capped overwrites at 1 MB before `unlink`, leaving data past 1 MB un-shredded.
- **Fix**: Replaced with a `while ($rem > 0)` loop overwriting 100% of file size in 1 MB chunks using `random_bytes()`.
- **Verification**: `tests/test_vault.php` Test 9 creates a 2.5 MB random file and confirms complete overwriting & unlinking.

### FIX-02: Vercel Serverless SSE Watch Duration
- **Problem**: 55s SSE loop hit Vercel's 10s execution cap, throwing 504 Gateway Timeouts.
- **Fix**: Added serverless environment detection (`VERCEL`, `VERCEL_ENV`, `AWS_LAMBDA_FUNCTION_NAME`) capping duration to 8s with proper buffer flushes (`while (ob_get_level() > 0) ob_end_clean()`).
- **Verification**: `tests/test_vault.php` Test 10 mocks `VERCEL=1` and verifies watch loop exits cleanly under 12 seconds.

### FIX-03: Asynchronous Watcher Interval Leaks
- **Problem**: Calling `stopWatch()` set `_watching = false`, but `_pollTimer` interval remained running in the background.
- **Fix**: `_startPoll` explicitly clears `_pollTimer` when `_watching` becomes false or when `stopWatch()` is called.
- **Verification**: `tests/test_crypto.js` Test 2.9 verifies `stopWatch()` is idempotent and cleans up timers cleanly.

### FIX-04: Global Window & Document Listener Accumulation
- **Problem**: View decryption added `visibilitychange`, `pagehide`, `beforeunload`, `blur`, and `focus` listeners repeatedly.
- **Fix**: In `renderDecryptedMessage()`, previously attached cleanup callback `window.__cdCleanup()` is invoked before attaching new handlers.
- **Verification**: Confirmed via Playwright interactive script and visual testing.

### FIX-05: API Switch Router Control Flow
- **Problem**: Missing `break;` statements created fallthrough risks if an action handler did not call `exit;`.
- **Fix**: Added explicit `break;` at the end of each `case` block in `vault.php`.
- **Verification**: Verified via backend unit tests (`php tests/test_vault.php`).

### FIX-06: Concurrency Locks for File Chunk Retrieval
- **Problem**: Concurrent chunk downloads or background GC sweeps could race during `Vault::chunk` reads.
- **Fix**: Wrapped `Vault::chunk` and `Vault::status` destruction logic inside `$fp = $this->lock($dir); try { ... } finally { $this->unlock($fp); }`.
- **Verification**: `tests/test_vault.php` Test 6 verifies single-read chunk delivery with process locking.

---

## 3. Maintenance Items for Future Iterations

1. [ ] **Post-Quantum Cryptography Module**: Integrate Kyber-1024 ML-KEM Wasm module for quantum-resistant key exchange.
2. [x] **Steganographic Image Payload Export**: PNG carrier LSB steganography encoder & extractor (`embedStego` / `extractStego` in `crypto.js`).
3. [ ] **WebAuthn Hardware Token Gate**: Add optional FIDO2 / YubiKey WebAuthn unlock challenge for PIN envelopes.
4. [x] **Multi-PIN Duress Decoy**: Multi-PIN envelope encryption (True PIN vs. Duress PIN) with silent primary envelope shredding on duress PIN entry (`Vault::burn(id, 'duress')`).
