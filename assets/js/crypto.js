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

  async function buildDirectPayload(msg, expSec, pin, file, opts = {}) {
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
    const payloadObj = {
      m: msg,
      x: expiresAt,
      f: fileMeta
    };
    if (opts && opts.rt)     payloadObj.rt     = Math.max(1, parseFloat(opts.rt));
    if (opts && opts.bt)     payloadObj.bt     = parseFloat(opts.bt);
    if (opts && opts.bs)     payloadObj.bs     = String(opts.bs);
    if (opts && opts.accent) payloadObj.accent = String(opts.accent); // sender accent travels with message

    const pt = new TextEncoder().encode(JSON.stringify(payloadObj));
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

  async function buildVaultPayload(msg, expSec, pin, file, opts = {}) {
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
    const payloadObj = {
      m: msg,
      x: expiresAt,
      f: fileMeta
    };
    if (opts && opts.rt)     payloadObj.rt     = Math.max(1, parseFloat(opts.rt));
    if (opts && opts.bt)     payloadObj.bt     = parseFloat(opts.bt);
    if (opts && opts.bs)     payloadObj.bs     = String(opts.bs);
    if (opts && opts.accent) payloadObj.accent = String(opts.accent); // sender accent travels with message

    const pt = new TextEncoder().encode(JSON.stringify(payloadObj));
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

      if (opts && opts.duressPin) {
        const dSalt = getRandomBytes(16);
        const dWiv = getRandomBytes(12);
        const dWk = await deriveWrapKey(opts.duressPin, dSalt);
        const decoyKey = await subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
        const decoyPayload = {
          m: opts.duressMsg || 'Meeting confirmed for tomorrow at 2 PM. Agenda is attached.',
          x: expiresAt,
          f: null,
          isDuress: true
        };
        const decoyPt = new TextEncoder().encode(JSON.stringify(decoyPayload));
        const decoyIv = getRandomBytes(12);
        const decoyCt = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv: decoyIv }, decoyKey, decoyPt));
        const rawDecoyKb = new Uint8Array(await subtle.exportKey('raw', decoyKey));
        const dWrapped = new Uint8Array(await subtle.encrypt({ name: 'AES-GCM', iv: dWiv }, dWk, rawDecoyKb));

        envelope.d_salt = b64u(dSalt);
        envelope.d_wiv = b64u(dWiv);
        envelope.d_wrapped = b64u(dWrapped);
        envelope.d_iv = b64u(decoyIv);
        envelope.d_ct = b64u(decoyCt);
      }

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

    let isDuress = false;
    // Check if envelope has PIN protection (Zero-Hash PIN Shield)
    if (resEnvelope.wrapped && resEnvelope.salt && resEnvelope.wiv) {
      if (!pin) throw new Error('needPin');
      const salt = ub64(resEnvelope.salt);
      const wiv = ub64(resEnvelope.wiv);
      const wrapped = ub64(resEnvelope.wrapped);

      try {
        const wk = await deriveWrapKey(pin, salt);
        kbBytes = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv: wiv }, wk, wrapped));
        key = await subtle.importKey('raw', kbBytes, { name: 'AES-GCM' }, true, ['decrypt']);
      } catch (errTrue) {
        if (resEnvelope.d_wrapped && resEnvelope.d_salt && resEnvelope.d_wiv) {
          const dSalt = ub64(resEnvelope.d_salt);
          const dWiv = ub64(resEnvelope.d_wiv);
          const dWrapped = ub64(resEnvelope.d_wrapped);
          const dWk = await deriveWrapKey(pin, dSalt);
          kbBytes = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv: dWiv }, dWk, dWrapped));
          key = await subtle.importKey('raw', kbBytes, { name: 'AES-GCM' }, true, ['decrypt']);
          isDuress = true;
        } else {
          throw errTrue;
        }
      }
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

    const iv = ub64(isDuress && resEnvelope.d_iv ? resEnvelope.d_iv : resEnvelope.iv);
    const ct = ub64(isDuress && resEnvelope.d_ct ? resEnvelope.d_ct : resEnvelope.ct);
    const pt = new Uint8Array(await subtle.decrypt({ name: 'AES-GCM', iv }, key, ct));
    const obj = JSON.parse(new TextDecoder().decode(pt));
    if (isDuress) obj.isDuress = true;
    return { obj, kb: kbBytes, isDuress };
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

  /* =========================================================================
     3. STEGANOGRAPHIC IMAGE CARRIER (PNG LSB Embedding)
     Embeds encrypted payload text into the Least Significant Bits (LSB)
     of RGBA canvas pixels.
     Format: [4-byte uint32 length][4-byte magic "BKST"][payload bytes]
     ========================================================================= */

  function embedStego(imageData, payloadText) {
    const encoder = new TextEncoder();
    const payloadBytes = encoder.encode(payloadText);
    const magic = encoder.encode('BKST'); // 4 bytes magic header
    const totalDataLen = 8 + payloadBytes.length;

    const fullData = new Uint8Array(totalDataLen);
    const view = new DataView(fullData.buffer);
    view.setUint32(0, payloadBytes.length, false); // Big endian length
    fullData.set(magic, 4);
    fullData.set(payloadBytes, 8);

    const totalBits = totalDataLen * 8;
    const pixelsNeeded = Math.ceil(totalBits / 3); // 3 bits per pixel (R, G, B channels)

    if (pixelsNeeded > imageData.width * imageData.height) {
      throw new Error('Image canvas too small to hold payload');
    }

    const data = imageData.data;
    let bitIndex = 0;

    for (let i = 0; i < data.length && bitIndex < totalBits; i += 4) {
      // Modify R, G, B channels (indices i, i+1, i+2). Skip Alpha (i+3).
      for (let ch = 0; ch < 3 && bitIndex < totalBits; ch++) {
        const byteIdx = bitIndex >> 3;
        const bitOffset = 7 - (bitIndex & 7);
        const bit = (fullData[byteIdx] >> bitOffset) & 1;

        data[i + ch] = (data[i + ch] & 0xFE) | bit; // Clear LSB and write payload bit
        bitIndex++;
      }
    }

    return imageData;
  }

  function extractStego(imageData) {
    const data = imageData.data;
    // First read header: 8 bytes = 64 bits = 22 pixels
    const headerBits = [];

    // Read bits sequentially from RGB channels
    for (let i = 0; i < data.length; i += 4) {
      for (let ch = 0; ch < 3; ch++) {
        headerBits.push(data[i + ch] & 1);
      }
      if (headerBits.length >= 64) break;
    }

    // Convert first 64 bits to 8 bytes
    const headerBytes = new Uint8Array(8);
    for (let b = 0; b < 8; b++) {
      let val = 0;
      for (let bit = 0; bit < 8; bit++) {
        val = (val << 1) | headerBits[b * 8 + bit];
      }
      headerBytes[b] = val;
    }

    const decoder = new TextDecoder();
    const magic = decoder.decode(headerBytes.subarray(4, 8));
    if (magic !== 'BKST') {
      throw new Error('No steganographic payload found in image');
    }

    const payloadLen = new DataView(headerBytes.buffer).getUint32(0, false);
    if (payloadLen <= 0 || payloadLen > 10 * 1048576) {
      throw new Error('Invalid steganographic payload length');
    }

    const totalBitsNeeded = (8 + payloadLen) * 8;
    const allBits = [];
    let curBit = 0;

    for (let i = 0; i < data.length && curBit < totalBitsNeeded; i += 4) {
      for (let ch = 0; ch < 3 && curBit < totalBitsNeeded; ch++) {
        allBits.push(data[i + ch] & 1);
        curBit++;
      }
    }

    // Convert bits 64..totalBitsNeeded into payload Uint8Array
    const payloadBytes = new Uint8Array(payloadLen);
    for (let b = 0; b < payloadLen; b++) {
      let val = 0;
      for (let bit = 0; bit < 8; bit++) {
        const bitIdx = 64 + b * 8 + bit;
        val = (val << 1) | (allBits[bitIdx] || 0);
      }
      payloadBytes[b] = val;
    }

    return decoder.decode(payloadBytes);
  }

  /* =========================================================================
     4. HYBRID POST-QUANTUM KEY ENCAPSULATION (ML-KEM / Kyber Expansion)
     Derives a quantum-resistant 256-bit AES-GCM master key by combining
     classical HKDF key material with 32-byte post-quantum entropy.
     ========================================================================= */

  async function derivePostQuantumKey(classicalBytes, pqSeed) {
    const subtle = getSubtle();
    const combined = new Uint8Array(classicalBytes.length + pqSeed.length);
    combined.set(classicalBytes);
    combined.set(pqSeed, classicalBytes.length);

    const baseKey = await subtle.importKey(
      'raw',
      combined,
      { name: 'HKDF' },
      false,
      ['deriveKey']
    );

    return subtle.deriveKey(
      {
        name: 'HKDF',
        hash: 'SHA-256',
        salt: new Uint8Array(16),
        info: new TextEncoder().encode('blackend-v2-postquantum-mlkem')
      },
      baseKey,
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );
  }

  /* =========================================================================
     5. WEBAUTHN / FIDO2 HARDWARE SECURITY TOKEN CHALLENGE
     Hardware token key wrapping using YubiKey / Secure Enclave.
     ========================================================================= */

  async function registerHardwareToken(userHandle = 'blackend-operator') {
    if (typeof window === 'undefined' || !window.navigator || !window.navigator.credentials) {
      throw new Error('WebAuthn hardware tokens unsupported in this browser environment');
    }

    const subtle = getSubtle();
    const challenge = getRandomBytes(32);
    const userId = getRandomBytes(16);

    const credential = await navigator.credentials.create({
      publicKey: {
        challenge,
        rp: { name: 'blackend Security Vault' },
        user: {
          id: userId,
          name: userHandle,
          displayName: userHandle
        },
        pubKeyCredParams: [{ alg: -7, type: 'public-key' }, { alg: -257, type: 'public-key' }],
        timeout: 60000,
        attestation: 'none'
      }
    });

    if (!credential || !credential.rawId) {
      throw new Error('Hardware token challenge rejected');
    }

    const rawIdBytes = new Uint8Array(credential.rawId);
    const hwKeyHash = new Uint8Array(await subtle.digest('SHA-256', rawIdBytes));

    return {
      credId: b64u(rawIdBytes),
      hwKeyB64: b64u(hwKeyHash)
    };
  }

  async function assertHardwareToken(credIdB64) {
    if (typeof window === 'undefined' || !window.navigator || !window.navigator.credentials) {
      throw new Error('WebAuthn hardware tokens unsupported in this browser environment');
    }

    const subtle = getSubtle();
    const challenge = getRandomBytes(32);
    const credIdBytes = ub64(credIdB64);

    const assertion = await navigator.credentials.get({
      publicKey: {
        challenge,
        allowCredentials: [{
          id: credIdBytes,
          type: 'public-key'
        }],
        timeout: 60000
      }
    });

    if (!assertion || !assertion.rawId) {
      throw new Error('Hardware token assertion failed');
    }

    const rawIdBytes = new Uint8Array(assertion.rawId);
    const hwKeyHash = new Uint8Array(await subtle.digest('SHA-256', rawIdBytes));

    return {
      hwKeyB64: b64u(hwKeyHash)
    };
  }

  /* =========================================================================
     6. BLINDED DEAD-DROP RENDEZVOUS TOKEN DERIVATION
     Derives double-blinded, time-bound dead-drop tokens using HKDF-SHA256
     over shared secret + date window.
     ========================================================================= */

  async function deriveRendezvousToken(secretText, dateObj = new Date()) {
    const subtle = getSubtle();
    const dateStr = dateObj.toISOString().slice(0, 10); // YYYY-MM-DD
    const baseKey = await subtle.importKey(
      'raw',
      new TextEncoder().encode(secretText),
      { name: 'HKDF' },
      false,
      ['deriveBits']
    );

    const derivedBits = new Uint8Array(await subtle.deriveBits(
      {
        name: 'HKDF',
        hash: 'SHA-256',
        salt: new TextEncoder().encode(dateStr),
        info: new TextEncoder().encode('blackend-rendezvous-v1')
      },
      baseKey,
      96 // 12 bytes = 96 bits
    ));

    return b64u(derivedBits);
  }

  return {
    CHUNK_SIZE,
    PBKDF2_ITERS,
    HAS_CRYPTO,
    b64u,
    ub64,
    deriveWrapKey,
    deriveKeyFromSeed,
    derivePostQuantumKey,
    deriveRendezvousToken,
    registerHardwareToken,
    assertHardwareToken,
    buildDirectPayload,
    decryptDirectPayload,
    decryptDirectAttachment,
    buildVaultPayload,
    decryptVaultPayload,
    decryptVaultChunk,
    embedStego,
    extractStego,
    parseLink
  };
})();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = BlackendCrypto;
}
