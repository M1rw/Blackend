/**
 * blackend crypto engine (v2.1)
 * Pure client-side AES-256-GCM & PBKDF2/HKDF encryption.
 * Decryption keys NEVER leave the browser and are never transmitted in clear to any server.
 */

const BlackendCrypto = (() => {
  const CHUNK_SIZE = 262144; // 256 KB
  const PBKDF2_ITERS = 120000;
  const HAS_CRYPTO = !!(typeof window !== 'undefined' && window.crypto && window.crypto.subtle)
    || !!(typeof globalThis !== 'undefined' && globalThis.crypto && globalThis.crypto.subtle);

  function getSubtle() {
    return (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
  }

  function getRandomBytes(n) {
    return (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(n));
  }

  /* ---------- Base64URL Helpers ---------- */
  function b64u(buf) {
    const bytes = buf instanceof Uint8Array ? buf : new Uint8Array(buf);
    let binary = '';
    const len = bytes.byteLength;
    for (let i = 0; i < len; i += 0x8000) {
      binary += String.fromCharCode.apply(null, bytes.subarray(i, Math.min(i + 0x8000, len)));
    }
    return (typeof btoa === 'function' ? btoa(binary) : Buffer.from(binary, 'binary').toString('base64'))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/, '');
  }

  function ub64(str) {
    let s = str.replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) s += '=';
    if (typeof atob === 'function') {
      const bin = atob(s);
      return Uint8Array.from(bin, c => c.charCodeAt(0));
    }
    return new Uint8Array(Buffer.from(s, 'base64'));
  }

  /* ---------- Key Derivation: PBKDF2 (for PIN) & HKDF (for Nano Seeds) ---------- */
  async function deriveWrapKey(pin, salt, iterations = PBKDF2_ITERS) {
    const subtle = getSubtle();
    const baseKey = await subtle.importKey(
      'raw',
      new TextEncoder().encode(pin),
      { name: 'PBKDF2' },
      false,
      ['deriveKey']
    );
    return subtle.deriveKey(
      { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
      baseKey,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );
  }

  /** Expands a compact 16-byte random seed into a full 256-bit AES-GCM key */
  async function deriveKeyFromSeed(seedBytes) {
    const subtle = getSubtle();
    const rawKey = await subtle.importKey(
      'raw',
      seedBytes,
      { name: 'HKDF' },
      false,
      ['deriveKey']
    );
    return subtle.deriveKey(
      {
        name: 'HKDF',
        hash: 'SHA-256',
        salt: new Uint8Array(16), // Fixed domain salt
        info: new TextEncoder().encode('blackend-v2-nano')
      },
      rawKey,
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );
  }

  /* =========================================================================
     1. DIRECT IN-LINK ENGINE (Zero-Server / Pure Hash Link)
     Payload format:
     - k1.iv.ct.key                               (no pin, no attachment: 4 parts)
     - k1.iv.ct.salt.wiv.wrapped                  (with pin, no attachment: 6 parts)
     - k2.iv.ct.key.att                           (no pin, with attachment: 5 parts)
     - k2.iv.ct.salt.wiv.wrapped.att              (with pin, with attachment: 7 parts)
     ========================================================================= */

  async function buildDirectPayload(msg, expSec, pin, file) {
    const subtle = getSubtle();
    const key = await subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);

    let fileMeta = null;
    let attBlob = null;

    if (file) {
      const buf = new Uint8Array(await (file.arrayBuffer ? file.arrayBuffer() : file));
      const nc = Math.max(1, Math.ceil(buf.byteLength / CHUNK_SIZE));
      fileMeta = {
        n: file.name || 'file',
        t: file.type || 'application/octet-stream',
        s: buf.byteLength,
        cs: CHUNK_SIZE,
        nc
      };

      const segs = [];
      for (let i = 0; i < nc; i++) {
        const iv = getRandomBytes(12);
        const chunkSlice = buf.subarray(i * CHUNK_SIZE, Math.min((i + 1) * CHUNK_SIZE, buf.byteLength));
        const ct = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv }, key, chunkSlice));
        
        const len = new Uint8Array(4);
        new DataView(len.buffer).setUint32(0, ct.length);
        segs.push([len, iv, ct]);
      }

      let totalLen = 0;
      for (const s of segs) totalLen += s[0].length + s[1].length + s[2].length;
      attBlob = new Uint8Array(totalLen);
      let offset = 0;
      for (const s of segs) {
        for (const part of s) {
          attBlob.set(part, offset);
          offset += part.length;
        }
      }
    }

    // Fix: Store absolute Unix timestamp (or 0 for 'after read')
    const expiresAt = expSec > 0 ? (Math.floor(Date.now() / 1000) + expSec) : 0;
    const iv = getRandomBytes(12);
    const pt = new TextEncoder().encode(JSON.stringify({ m: msg, x: expiresAt, f: fileMeta }));
    const ct = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv }, key, pt));
    const rawKb = new Uint8Array(await subtle.exportKey('raw', key));

    const parts = [fileMeta ? 'k2' : 'k1', b64u(iv), b64u(ct)];

    if (pin) {
      const salt = getRandomBytes(16);
      const wiv = getRandomBytes(12);
      const wk = await deriveWrapKey(pin, salt);
      const wrapped = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv: wiv }, wk, rawKb));
      parts.push(b64u(salt), b64u(wiv), b64u(wrapped));
    } else {
      parts.push(b64u(rawKb));
    }

    if (attBlob) {
      parts.push(b64u(attBlob));
    }

    return parts.join('.');
  }

  async function decryptDirectPayload(parts, pin) {
    const subtle = getSubtle();
    const iv = ub64(parts[1]);
    const ct = ub64(parts[2]);
    const n = parts.length;

    let kb;
    if (n === 4 || n === 5) {
      kb = ub64(parts[3]);
    } else if (n === 6 || n === 7) {
      if (!pin) throw new Error('needPin');
      const salt = ub64(parts[3]);
      const wiv = ub64(parts[4]);
      const wrapped = ub64(parts[5]);
      const wk = await deriveWrapKey(pin, salt);
      kb = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv: wiv }, wk, wrapped));
    } else {
      throw new Error('invalidParts');
    }

    const key = await subtle.importKey('raw', kb, { name: 'AES-GCM' }, false, ['decrypt']);
    const pt = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv }, key, ct));
    const obj = JSON.parse(new TextDecoder().decode(pt));
    return { obj, kb };
  }

  async function decryptDirectAttachment(parts, kb, mimeType) {
    const subtle = getSubtle();
    const bin = ub64(parts[parts.length - 1]);
    const key = await subtle.importKey('raw', kb, { name: 'AES-GCM' }, false, ['decrypt']);
    const jobs = [];
    let off = 0;

    while (off + 16 <= bin.length) {
      const len = new DataView(bin.buffer, bin.byteOffset + off, 4).getUint32(0);
      off += 4;
      const iv = bin.subarray(off, off + 12);
      off += 12;
      const ctb = bin.subarray(off, off + len);
      off += len;
      jobs.push(subtle.decrypt({ name: 'AES-GCM', iv }, key, ctb));
    }

    const bufs = await Promise.all(jobs);
    if (typeof Blob !== 'undefined') {
      return new Blob(bufs, { type: mimeType || 'application/octet-stream' });
    }
    return bufs;
  }

  /* =========================================================================
     2. VAULT ESCROW ENGINE (Hyper-Short Creative Links)
     - Without PIN: Uses compact 16-byte Nano-Seed (#n.seed -> ~22 chars)
     - With PIN: Key is wrapped with PBKDF2 and safely held in escrow envelope;
                 URL REQUIRES NO FRAGMENT AT ALL (# is omitted)!
     ========================================================================= */

  async function buildVaultPayload(msg, expSec, pin, file) {
    const subtle = getSubtle();
    let key;
    let seed = null;

    if (!pin) {
      // Generate compact 16-byte random seed and derive 256-bit AES key via HKDF
      seed = getRandomBytes(16);
      key = await deriveKeyFromSeed(seed);
    } else {
      key = await subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
    }

    const iv = getRandomBytes(12);
    let nc = 0;
    const chunkBlobs = [];

    if (file) {
      const buf = new Uint8Array(await (file.arrayBuffer ? file.arrayBuffer() : file));
      nc = Math.max(1, Math.ceil(buf.byteLength / CHUNK_SIZE));
      for (let i = 0; i < nc; i++) {
        const civ = getRandomBytes(12);
        const chunkSlice = buf.subarray(i * CHUNK_SIZE, Math.min((i + 1) * CHUNK_SIZE, buf.byteLength));
        const cct = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv: civ }, key, chunkSlice));
        
        const raw = new Uint8Array(12 + cct.length);
        raw.set(civ);
        raw.set(cct, 12);
        chunkBlobs.push(b64u(raw));
      }
    }

    const fileMeta = file ? {
      n: file.name || 'file',
      t: file.type || 'application/octet-stream',
      s: file.size !== undefined ? file.size : (file.byteLength || 0),
      nc
    } : null;

    // Fix: Store absolute Unix timestamp (or 0 for 'after read')
    const expiresAt = expSec > 0 ? (Math.floor(Date.now() / 1000) + expSec) : 0;
    const pt = new TextEncoder().encode(JSON.stringify({ m: msg, x: expiresAt, f: fileMeta }));
    const ct = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv }, key, pt));
    const rawKb = new Uint8Array(await subtle.exportKey('raw', key));

    let frag = '';
    const envelope = {
      exp: expSec,
      pin: !!pin,
      nc,
      iv: b64u(iv),
      ct: b64u(ct)
    };

    if (pin) {
      // Zero-Hash PIN Shield: Store PBKDF2 wrapped key inside the server envelope.
      // The server CANNOT decrypt it without the PIN.
      // Result: The URL needs NO FRAGMENT! (e.g. /v/ember-fox or ?m=k7x9q)
      const salt = getRandomBytes(16);
      const wiv = getRandomBytes(12);
      const wk = await deriveWrapKey(pin, salt);
      const wrapped = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv: wiv }, wk, rawKb));
      
      envelope.salt = b64u(salt);
      envelope.wiv = b64u(wiv);
      envelope.wrapped = b64u(wrapped);
      frag = ''; // No hash needed!
    } else {
      // Ultra-compact 16-byte Nano-Seed fragment
      frag = `n.${b64u(seed)}`;
    }

    return {
      envelope,
      chunks: chunkBlobs,
      frag,
      fileMeta
    };
  }

  async function decryptVaultPayload(envelopeOrIv, fragOrCt, pinOrFrag, maybePin) {
    const subtle = getSubtle();
    let resEnvelope, frag, pin;
    if (typeof envelopeOrIv === 'object' && envelopeOrIv !== null) {
      resEnvelope = envelopeOrIv;
      frag = fragOrCt || '';
      pin = pinOrFrag || null;
    } else {
      resEnvelope = {
        iv: envelopeOrIv,
        ct: fragOrCt
      };
      frag = pinOrFrag || '';
      pin = maybePin || null;
    }

    let key;
    let kbBytes = null;

    // Check if envelope has PIN protection (Zero-Hash PIN Shield)
    if (resEnvelope.wrapped && resEnvelope.salt && resEnvelope.wiv) {
      if (!pin) throw new Error('needPin');
      const salt = ub64(resEnvelope.salt);
      const wiv = ub64(resEnvelope.wiv);
      const wrapped = ub64(resEnvelope.wrapped);
      const wk = await deriveWrapKey(pin, salt);
      kbBytes = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv: wiv }, wk, wrapped));
      key = await subtle.importKey('raw', kbBytes, { name: 'AES-GCM' }, true, ['decrypt']);
    } else if (frag && frag.startsWith('n.')) {
      // Compact Nano-Seed key
      const seedBytes = ub64(frag.slice(2));
      key = await deriveKeyFromSeed(seedBytes);
      kbBytes = new Uint8Array(await subtle.exportKey('raw', key));
    } else if (frag && frag.startsWith('k1.')) {
      // Legacy unpinned key
      kbBytes = ub64(frag.slice(3));
      key = await subtle.importKey('raw', kbBytes, { name: 'AES-GCM' }, true, ['decrypt']);
    } else if (frag && frag.startsWith('k2.')) {
      // Legacy PIN wrapped fragment
      if (!pin) throw new Error('needPin');
      const parts = frag.split('.');
      const salt = ub64(parts[1]);
      const wiv = ub64(parts[2]);
      const wrapped = ub64(parts[3]);
      const wk = await deriveWrapKey(pin, salt);
      kbBytes = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv: wiv }, wk, wrapped));
      key = await subtle.importKey('raw', kbBytes, { name: 'AES-GCM' }, true, ['decrypt']);
    } else {
      // If PIN was expected
      if (resEnvelope.pin) throw new Error('needPin');
      throw new Error('invalidFrag');
    }

    const iv = ub64(resEnvelope.iv);
    const ct = ub64(resEnvelope.ct);
    const pt = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv }, key, ct));
    const obj = JSON.parse(new TextDecoder().decode(pt));
    return { obj, kb: kbBytes };
  }

  async function decryptVaultChunk(chunkB64, kb) {
    const subtle = getSubtle();
    const raw = ub64(chunkB64);
    const iv = raw.subarray(0, 12);
    const ct = raw.subarray(12);
    const key = await subtle.importKey('raw', kb, { name: 'AES-GCM' }, false, ['decrypt']);
    return new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv }, key, ct));
  }

  /* ---------- Universal Link Classifier & Extractor ---------- */
  function parseLink(pathname, search, hash) {
    if (arguments.length === 2) {
      hash = search;
      search = pathname;
      pathname = '';
    }
    const p = (pathname || '').replace(/\/+$/, '');
    const pathMatch = p.match(/\/(?:v|m)\/([A-Za-z0-9_-]+)/);
    const params = new URLSearchParams(search || '');
    const token = (pathMatch && pathMatch[1]) || params.get('m');
    const h = (hash || '').replace(/^#/, '');

    // 1. Vault Escrow Link: token present in path or ?m=
    if (token && /^[A-Za-z0-9_-]{4,64}$/.test(token)) {
      return { mode: 'vault', token, frag: h };
    }

    // 2. Direct In-Link: payload stored after #
    if (/^k[12]\./.test(h)) {
      const parts = h.split('.');
      if (parts.length >= 4) {
        return { mode: 'direct', parts, payload: h };
      }
    }

    return null;
  }

  return {
    CHUNK_SIZE,
    PBKDF2_ITERS,
    HAS_CRYPTO,
    b64u,
    ub64,
    deriveWrapKey,
    deriveKeyFromSeed,
    buildDirectPayload,
    decryptDirectPayload,
    decryptDirectAttachment,
    buildVaultPayload,
    decryptVaultPayload,
    decryptVaultChunk,
    parseLink
  };
})();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = BlackendCrypto;
}
