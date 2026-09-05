/**
 * blackend crypto engine
 * Pure client-side AES-256-GCM & PBKDF2 encryption.
 * Decryption keys NEVER leave the browser and are never transmitted to any server.
 */

const BlackendCrypto = (() => {
  const CHUNK_SIZE = 262144; // 256 KB
  const PBKDF2_ITERS = 120000;
  const HAS_CRYPTO = !!(typeof window !== 'undefined' && window.crypto && window.crypto.subtle)
    || !!(typeof globalThis !== 'undefined' && globalThis.crypto && globalThis.crypto.subtle);

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

  /* ---------- PBKDF2 Key Derivation ---------- */
  async function deriveWrapKey(pin, salt, iterations = PBKDF2_ITERS) {
    const cryptoSubtle = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
    const baseKey = await cryptoSubtle.importKey(
      'raw',
      new TextEncoder().encode(pin),
      { name: 'PBKDF2' },
      false,
      ['deriveKey']
    );
    return cryptoSubtle.deriveKey(
      { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
      baseKey,
      { name: 'AES-GCM', length: 256 },
      false,
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
    const cryptoSubtle = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
    const key = await cryptoSubtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);

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
        const iv = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(12));
        const chunkSlice = buf.subarray(i * CHUNK_SIZE, Math.min((i + 1) * CHUNK_SIZE, buf.byteLength));
        const ct = new Uint8Array(await cryptoSubtle.encrypt({ name: 'AES-GCM', iv }, key, chunkSlice));
        
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

    const iv = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(12));
    const pt = new TextEncoder().encode(JSON.stringify({ m: msg, x: expSec, f: fileMeta }));
    const ct = new Uint8Array(await cryptoSubtle.encrypt({ name: 'AES-GCM', iv }, key, pt));
    const rawKb = new Uint8Array(await cryptoSubtle.exportKey('raw', key));

    const parts = [fileMeta ? 'k2' : 'k1', b64u(iv), b64u(ct)];

    if (pin) {
      const salt = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(16));
      const wiv = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(12));
      const wk = await deriveWrapKey(pin, salt);
      const wrapped = new Uint8Array(await cryptoSubtle.encrypt({ name: 'AES-GCM', iv: wiv }, wk, rawKb));
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
    const cryptoSubtle = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
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
      kb = new Uint8Array(await cryptoSubtle.decrypt({ name: 'AES-GCM', iv: wiv }, wk, wrapped));
    } else {
      throw new Error('invalidParts');
    }

    const key = await cryptoSubtle.importKey('raw', kb, { name: 'AES-GCM' }, false, ['decrypt']);
    const pt = new Uint8Array(await cryptoSubtle.decrypt({ name: 'AES-GCM', iv }, key, ct));
    const obj = JSON.parse(new TextDecoder().decode(pt));
    return { obj, kb };
  }

  async function decryptDirectAttachment(parts, kb, mimeType) {
    const cryptoSubtle = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
    const bin = ub64(parts[parts.length - 1]);
    const key = await cryptoSubtle.importKey('raw', kb, { name: 'AES-GCM' }, false, ['decrypt']);
    const jobs = [];
    let off = 0;

    while (off + 16 <= bin.length) {
      const len = new DataView(bin.buffer, bin.byteOffset + off, 4).getUint32(0);
      off += 4;
      const iv = bin.subarray(off, off + 12);
      off += 12;
      const ctb = bin.subarray(off, off + len);
      off += len;
      jobs.push(cryptoSubtle.decrypt({ name: 'AES-GCM', iv }, key, ctb));
    }

    const bufs = await Promise.all(jobs);
    if (typeof Blob !== 'undefined') {
      return new Blob(bufs, { type: mimeType || 'application/octet-stream' });
    }
    return bufs;
  }

  /* =========================================================================
     2. VAULT ESCROW ENGINE (Short Link / Blind Server Storage)
     Envelope uploaded to Vault; Key in URL fragment:
     - #k1.key                                    (no pin)
     - #k2.salt.wiv.wrapped                       (with pin)
     ========================================================================= */

  async function buildVaultPayload(msg, expSec, pin, file) {
    const cryptoSubtle = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
    const key = await cryptoSubtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
    const iv = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(12));

    let nc = 0;
    const chunkBlobs = [];

    if (file) {
      const buf = new Uint8Array(await (file.arrayBuffer ? file.arrayBuffer() : file));
      nc = Math.max(1, Math.ceil(buf.byteLength / CHUNK_SIZE));
      for (let i = 0; i < nc; i++) {
        const civ = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(12));
        const chunkSlice = buf.subarray(i * CHUNK_SIZE, Math.min((i + 1) * CHUNK_SIZE, buf.byteLength));
        const cct = new Uint8Array(await cryptoSubtle.encrypt({ name: 'AES-GCM', iv: civ }, key, chunkSlice));
        
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

    const pt = new TextEncoder().encode(JSON.stringify({ m: msg, x: expSec, f: fileMeta }));
    const ct = new Uint8Array(await cryptoSubtle.encrypt({ name: 'AES-GCM', iv }, key, pt));
    const rawKb = new Uint8Array(await cryptoSubtle.exportKey('raw', key));

    let frag;
    if (pin) {
      const salt = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(16));
      const wiv = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).getRandomValues(new Uint8Array(12));
      const wk = await deriveWrapKey(pin, salt);
      const wrapped = new Uint8Array(await cryptoSubtle.encrypt({ name: 'AES-GCM', iv: wiv }, wk, rawKb));
      frag = `k2.${b64u(salt)}.${b64u(wiv)}.${b64u(wrapped)}`;
    } else {
      frag = `k1.${b64u(rawKb)}`;
    }

    return {
      envelope: {
        exp: expSec,
        pin: !!pin,
        nc,
        iv: b64u(iv),
        ct: b64u(ct)
      },
      chunks: chunkBlobs,
      frag,
      fileMeta
    };
  }

  async function decryptVaultPayload(ivB64, ctB64, frag, pin) {
    const cryptoSubtle = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
    const parts = (frag || '').split('.');
    let kb;

    if (parts[0] === 'k1') {
      kb = ub64(parts[1]);
    } else if (parts[0] === 'k2') {
      if (!pin) throw new Error('needPin');
      const salt = ub64(parts[1]);
      const wiv = ub64(parts[2]);
      const wrapped = ub64(parts[3]);
      const wk = await deriveWrapKey(pin, salt);
      kb = new Uint8Array(await cryptoSubtle.decrypt({ name: 'AES-GCM', iv: wiv }, wk, wrapped));
    } else {
      throw new Error('invalidFrag');
    }

    const key = await cryptoSubtle.importKey('raw', kb, { name: 'AES-GCM' }, false, ['decrypt']);
    const iv = ub64(ivB64);
    const ct = ub64(ctB64);
    const pt = new Uint8Array(await cryptoSubtle.decrypt({ name: 'AES-GCM', iv }, key, ct));
    const obj = JSON.parse(new TextDecoder().decode(pt));
    return { obj, kb };
  }

  async function decryptVaultChunk(chunkB64, kb) {
    const cryptoSubtle = (typeof window !== 'undefined' ? window.crypto : globalThis.crypto).subtle;
    const raw = ub64(chunkB64);
    const iv = raw.subarray(0, 12);
    const ct = raw.subarray(12);
    const key = await cryptoSubtle.importKey('raw', kb, { name: 'AES-GCM' }, false, ['decrypt']);
    return new Uint8Array(await cryptoSubtle.decrypt({ name: 'AES-GCM', iv }, key, ct));
  }

  /* ---------- Link Classifier ---------- */
  function parseLink(search, hash) {
    const params = new URLSearchParams(search || '');
    const token = params.get('m');
    const h = (hash || '').replace(/^#/, '');

    if (token && /^[A-Za-z0-9]{16,64}$/.test(token) && (h.startsWith('k1.') || h.startsWith('k2.'))) {
      return { mode: 'vault', token, frag: h };
    }

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
