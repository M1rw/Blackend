/**
 * blackend backend module v1.0
 *
 * Provides three capabilities that cannot live in localStorage:
 *   1. Per-message settings delivery (sender settings travel with the vault, not the browser)
 *   2. Advanced escrow receipt keys (chain-of-custody audit without re-reading the message)
 *   3. Real-time burn watcher (SSE streaming, poll fallback)
 *
 * No dependencies. Works in browser and Node.js (for tests).
 */

const VaultBackend = (() => {
  'use strict';

  const EP = '/vault.php';
  const RK_NS = 'bk_rk_v1'; // localStorage namespace for receipt keys only

  /* ================================================================
     CORE API CALL
     ================================================================ */

  async function call(action, body = {}) {
    try {
      const r = await fetch(EP, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...body })
      });
      if (!r.ok) return { ok: false, error: 'http_' + r.status };
      return await r.json();
    } catch (_) {
      return { ok: false, error: 'network' };
    }
  }

  /* ================================================================
     SETTINGS — cross-browser message settings delivery
     Sender packs display settings into the vault store call.
     Recipient reads them from the fetch response BEFORE decryption,
     so theming (accent, burn animation) is applied immediately.
     ================================================================ */

  const VALID_ACCENTS = ['ember', 'crimson', 'mint', 'ice'];
  const VALID_BURNS   = ['calm', 'quick', 'custom'];

  /**
   * Packs the sender's current settings into a plain object
   * suitable for storage in the vault's public metadata.
   * @param {object} SET  — the local settings object from app.js
   */
  function packSettings(SET) {
    return {
      accent:   VALID_ACCENTS.includes(SET.accent) ? SET.accent : 'ember',
      burn:     VALID_BURNS.includes(SET.burn)     ? SET.burn   : 'calm',
      burnTime: Math.min(30, Math.max(0.2, parseFloat(SET.burnTime) || 2.0)),
      readTime: Math.min(300, Math.max(1, parseInt(SET.readTime, 10) || 4))
    };
  }

  /**
   * Applies server-delivered sender settings to the recipient's local state.
   * Called immediately after vault fetch, before showing gate or decrypting.
   * Only applies validated values — ignores anything malformed.
   *
   * @param {object}   settings       — settings object from vault fetch response
   * @param {object}   SET            — local settings object (mutated in-place)
   * @param {Function} applyAccentFn  — app.js applyAccent(name) callback
   */
  function applyServerSettings(settings, SET, applyAccentFn) {
    if (!settings || typeof settings !== 'object') return;

    if (settings.burn && VALID_BURNS.includes(settings.burn))
      SET.burn = settings.burn;

    if (typeof settings.burnTime === 'number' && settings.burnTime > 0)
      SET.burnTime = Math.min(30, Math.max(0.2, settings.burnTime));

    if (typeof settings.readTime === 'number' && settings.readTime > 0)
      SET.readTime = Math.min(300, Math.max(1, settings.readTime));

    if (settings.accent && VALID_ACCENTS.includes(settings.accent) &&
        typeof applyAccentFn === 'function') {
      applyAccentFn(settings.accent);
    }
  }

  /* ================================================================
     RECEIPT KEY SYSTEM
     ---------------------------------------------------------------
     When a sender seals a message they generate a random 32-byte
     receipt key (rk). Only the SHA-256 hash of rk is sent to the
     server and stored in vault metadata. The actual rk stays in the
     sender's localStorage only.

     To view the chain-of-custody audit trail, the sender hashes rk
     and presents it to the /vault.php?action=receipt endpoint.
     The server validates the hash, then returns timestamps for:
       created → opened → burned

     The recipient never has rk, and the server never has the plaintext rk.
     Even with full server access, nobody can query the receipt without rk.
     ================================================================ */

  function _b64u(bytes) {
    // Binary-safe base64url conversion for Uint8Array across Browser & Node
    let bin = '';
    const len = bytes.length || bytes.byteLength;
    for (let i = 0; i < len; i++) {
      bin += String.fromCharCode(bytes[i]);
    }
    const b64 = (typeof btoa !== 'undefined')
      ? btoa(bin)
      : Buffer.from(bytes).toString('base64');
    return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function _getSubtle() {
    return (typeof crypto !== 'undefined' ? crypto : globalThis.crypto).subtle;
  }

  function _getRandomValues(n) {
    return (typeof crypto !== 'undefined' ? crypto : globalThis.crypto)
      .getRandomValues(new Uint8Array(n));
  }

  /** Generate a 32-byte cryptographically random receipt key, base64url encoded. */
  function generateReceiptKey() {
    return _b64u(_getRandomValues(32));
  }

  /**
   * Hash a receipt key with SHA-256 (keyed with domain prefix).
   * This is what gets stored server-side and sent on receipt verification.
   * @param  {string} rk   — base64url receipt key
   * @returns {Promise<string>} base64url SHA-256 hash
   */
  async function hashReceiptKey(rk) {
    const data = new TextEncoder().encode('bk-receipt:' + rk);
    const hash = new Uint8Array(await _getSubtle().digest('SHA-256', data));
    return _b64u(hash);
  }

  function _loadRkStore() {
    try { return JSON.parse(localStorage.getItem(RK_NS) || '{}'); } catch (_) { return {}; }
  }

  function _saveRkStore(store) {
    try { localStorage.setItem(RK_NS, JSON.stringify(store)); } catch (_) {}
  }

  /**
   * Persist a receipt key for a vault token in local storage.
   * Automatically prunes the oldest keys when more than 100 are stored.
   */
  function saveReceiptKey(token, rk) {
    const store = _loadRkStore();
    store[token] = { rk, ts: Date.now() };
    const keys = Object.keys(store);
    if (keys.length > 100) {
      keys.sort((a, b) => (store[a].ts || 0) - (store[b].ts || 0))
          .slice(0, keys.length - 100)
          .forEach(k => delete store[k]);
    }
    _saveRkStore(store);
  }

  /** Retrieve the receipt key for a token, or null if not found. */
  function loadReceiptKey(token) {
    const s = _loadRkStore();
    return (s[token] && s[token].rk) || null;
  }

  /** Remove a receipt key (e.g. after message is fully ash). */
  function deleteReceiptKey(token) {
    const store = _loadRkStore();
    delete store[token];
    _saveRkStore(store);
  }

  /**
   * Verify receipt for a token. Hashes the stored rk and sends it to the server.
   * Returns a chain-of-custody object:
   *   { ok: true, created, opened, burned, why }  on success
   *   { ok: false, error: 'no_key' }               if no receipt key stored locally
   *   { ok: false, error: 'invalid_key' }           if server rejects the hash
   */
  async function verifyReceipt(token) {
    const rk = loadReceiptKey(token);
    if (!rk) return { ok: false, error: 'no_key' };
    try {
      const rkHash = await hashReceiptKey(rk);
      return await call('receipt', { id: token, rk: rkHash });
    } catch (_) {
      return { ok: false, error: 'crypto_error' };
    }
  }

  /* ================================================================
     WATCHER — real-time burn / read-receipt notifications
     ---------------------------------------------------------------
     Strategy:
       1. POST to /vault.php with action=watch — server replies with
          a text/event-stream (SSE) body read via fetch ReadableStream.
          The token never appears in a GET URL, so it is absent from
          all server access logs.
       2. If streaming is unsupported or fails, silently falls back to
          2.5 s setInterval polling via the existing status action.

     The caller receives an onEvent(evt) callback where evt = {state, why?, now}.
     ================================================================ */

  let _watchAbort = null;
  let _pollTimer  = null;
  let _watching   = false;

  async function _tryStream(token, onEvent, signal) {
    const r = await fetch(EP, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'watch', id: token }),
      signal
    });

    if (!r.ok || !r.body) throw new Error('no_stream');

    const reader = r.body.getReader();
    const dec    = new TextDecoder();
    let buf = '';

    for (;;) {
      const { value, done } = await reader.read();
      if (done) break;
      if (!_watching) break;
      buf += dec.decode(value, { stream: true });
      const lines = buf.split('\n');
      buf = lines.pop(); // keep incomplete trailing line
      for (const line of lines) {
        if (line.startsWith('data: ')) {
          try {
            const evt = JSON.parse(line.slice(6));
            if (evt && typeof evt.state === 'string') {
              onEvent(evt);
              if (evt.state === 'gone' || evt.state === 'timeout') return;
            }
          } catch (_) {}
        }
      }
    }
  }

  function _startPoll(token, onEvent) {
    let lastState = '';
    _pollTimer = setInterval(async () => {
      if (!_watching) {
        if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
        return;
      }
      try {
        const r = await call('status', { id: token });
        if (!r || !r.ok) return;
        if (r.state !== lastState) {
          lastState = r.state;
          onEvent(r);
          if (r.state === 'gone') stopWatch();
        }
      } catch (_) {}
    }, 2500);
  }

  /**
   * Start watching a vault token for state changes.
   * @param {string}   token    — vault message ID
   * @param {Function} onEvent  — called with each status event object
   */
  async function watch(token, onEvent) {
    stopWatch();
    _watching    = true;
    _watchAbort  = new AbortController();

    try {
      await _tryStream(token, onEvent, _watchAbort.signal);
    } catch (err) {
      if (err && err.name === 'AbortError') return; // intentionally stopped
      if (!_watching) return; // stopped while trying stream
      // Streaming unavailable (old browser, no ReadableStream, etc.) — use poll
      _startPoll(token, onEvent);
    }
  }

  /** Stop all active watchers (stream + poll). */
  function stopWatch() {
    _watching = false;
    if (_watchAbort) {
      try { _watchAbort.abort(); } catch (_) {}
      _watchAbort = null;
    }
    if (_pollTimer) {
      clearInterval(_pollTimer);
      _pollTimer = null;
    }
  }

  /* ================================================================
     PUBLIC API
     ================================================================ */
  return {
    // Core
    call,
    // Settings
    packSettings,
    applyServerSettings,
    // Receipt keys
    generateReceiptKey,
    hashReceiptKey,
    saveReceiptKey,
    loadReceiptKey,
    deleteReceiptKey,
    verifyReceipt,
    // Watcher
    watch,
    stopWatch
  };
})();

/* CommonJS export for Node.js test environments */
if (typeof module !== 'undefined' && module.exports) {
  module.exports = VaultBackend;
}
