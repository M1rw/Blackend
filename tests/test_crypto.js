/**
 * Automated test suite for Blackend Crypto, QR, and JS Modules.
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

// 1. Verify all JS files parse without syntax errors
const jsFiles = [
  'assets/js/crypto.js',
  'assets/js/qrenc.js',
  'assets/js/canvas.js',
  'assets/js/app.js'
];

console.log('=== 1. JS SYNTAX VALIDATION ===');
jsFiles.forEach(file => {
  const filePath = path.resolve(__dirname, '..', file);
  const code = fs.readFileSync(filePath, 'utf8');
  try {
    new Function(code);
    console.log(`[PASS] ${file} has valid JavaScript syntax`);
  } catch (err) {
    console.error(`[FAIL] ${file} SyntaxError:`, err.message);
    process.exit(1);
  }
});

// Load modules in Node environment
const BlackendCrypto = require('../assets/js/crypto.js');
const BlackendQR = require('../assets/js/qrenc.js');

async function runTests() {
  console.log('\n=== 2. CRYPTO & DUAL-ENGINE TESTS ===');

  // Test 2.1: Direct In-Link Plain Message
  {
    const now = Math.floor(Date.now() / 1000);
    const msg = 'Meet at midnight on the north pier.';
    const payload = await BlackendCrypto.buildDirectPayload(msg, 60, null, null);
    assert(payload.startsWith('k1.'), 'Direct payload should start with k1');
    const parts = payload.split('.');
    assert.strictEqual(parts.length, 4, 'Direct unpinned payload must have 4 parts');

    const { obj } = await BlackendCrypto.decryptDirectPayload(parts, null);
    assert.strictEqual(obj.m, msg, 'Decrypted text must match original');
    assert(obj.x >= now + 58 && obj.x <= now + 62, 'Decrypted expiry must be absolute unix timestamp');
    console.log('[PASS] Direct In-Link plain message encrypt/decrypt roundtrip with absolute timestamp');
  }

  // Test 2.2: Direct In-Link PIN-Protected Message
  {
    const msg = 'Top secret coordinates: 35.6895, 139.6917';
    const pin = '4829';
    const payload = await BlackendCrypto.buildDirectPayload(msg, 3600, pin, null);
    assert(payload.startsWith('k1.'), 'Direct payload should start with k1');
    const parts = payload.split('.');
    assert.strictEqual(parts.length, 6, 'Direct pinned payload must have 6 parts');

    // Wrong PIN should fail
    let failed = false;
    try {
      await BlackendCrypto.decryptDirectPayload(parts, '0000');
    } catch (_) {
      failed = true;
    }
    assert(failed, 'Wrong PIN must throw decryption error');

    // Correct PIN should succeed
    const { obj } = await BlackendCrypto.decryptDirectPayload(parts, pin);
    assert.strictEqual(obj.m, msg, 'Decrypted text with PIN must match original');
    console.log('[PASS] Direct In-Link PIN-protected encrypt/decrypt roundtrip (with brute-force defense test)');
  }

  // Test 2.3: Direct In-Link with Attachment
  {
    const msg = 'Document attached';
    const fakeFile = {
      name: 'notes.txt',
      type: 'text/plain',
      arrayBuffer: async () => Buffer.from('This is a confidential briefing paper. Keep it offline.')
    };
    const payload = await BlackendCrypto.buildDirectPayload(msg, 0, null, fakeFile);
    assert(payload.startsWith('k2.'), 'Direct payload with attachment must start with k2');
    const parts = payload.split('.');
    assert.strictEqual(parts.length, 5, 'Direct unpinned attachment must have 5 parts');

    const { obj, kb } = await BlackendCrypto.decryptDirectPayload(parts, null);
    assert.strictEqual(obj.m, msg);
    assert(obj.f, 'File metadata must exist');
    assert.strictEqual(obj.f.n, 'notes.txt');

    const decryptedBlob = await BlackendCrypto.decryptDirectAttachment(parts, kb, 'text/plain');
    let fullText;
    if (decryptedBlob instanceof Blob) {
      fullText = Buffer.from(await decryptedBlob.arrayBuffer()).toString('utf8');
    } else {
      fullText = Buffer.concat(decryptedBlob.map(b => Buffer.from(b))).toString('utf8');
    }
    assert.strictEqual(fullText, 'This is a confidential briefing paper. Keep it offline.');
    console.log('[PASS] Direct In-Link file chunking, encryption, and reconstitution roundtrip');
  }

  // Test 2.4: Vault Escrow Zero-Hash PIN Shield (No fragment!)
  {
    const msg = 'Vault escrow Zero-Hash PIN Shield test message';
    const pin = '7712';
    const vaultData = await BlackendCrypto.buildVaultPayload(msg, 600, pin, null);
    assert(vaultData.envelope.iv, 'Envelope must have IV');
    assert(vaultData.envelope.ct, 'Envelope must have Ciphertext');
    assert(vaultData.envelope.wrapped, 'Envelope must have PBKDF2 wrapped key');
    assert(vaultData.envelope.salt, 'Envelope must have PBKDF2 salt');
    assert(vaultData.envelope.wiv, 'Envelope must have wrapper IV');
    assert.strictEqual(vaultData.frag, '', 'Zero-Hash PIN Shield requires NO hash fragment at all!');

    // Wrong PIN
    let failed = false;
    try {
      await BlackendCrypto.decryptVaultPayload(vaultData.envelope, '', '1234');
    } catch (_) {
      failed = true;
    }
    assert(failed, 'Vault payload decryption with wrong PIN must fail');

    // Correct PIN
    const { obj } = await BlackendCrypto.decryptVaultPayload(
      vaultData.envelope,
      '',
      pin
    );
    assert.strictEqual(obj.m, msg);
    console.log('[PASS] Zero-Hash PIN Shield: Envelope encryption & fragmentless PIN decryption roundtrip');
  }

  // Test 2.5: Vault Escrow Unpinned Nano-Seed
  {
    const msg = 'Vault escrow Nano-Seed unpinned message';
    const vaultData = await BlackendCrypto.buildVaultPayload(msg, 0, null, null);
    assert(vaultData.frag.startsWith('n.'), 'Nano-Seed fragment must start with n.');
    assert(vaultData.frag.length <= 30, 'Nano-Seed fragment must be ultra-compact (<=30 chars)');

    const { obj } = await BlackendCrypto.decryptVaultPayload(
      vaultData.envelope,
      vaultData.frag,
      null
    );
    assert.strictEqual(obj.m, msg);
    console.log('[PASS] Ultra-compact Nano-Seed HKDF envelope roundtrip');
  }

  // Test 2.6: Link Classifier & Clean URLs
  {
    // Clean path URL with no fragment (Zero-Hash PIN Shield)
    const cleanPinnedLink = BlackendCrypto.parseLink('/v/ash-fox-42', '', '');
    assert.strictEqual(cleanPinnedLink.mode, 'vault');
    assert.strictEqual(cleanPinnedLink.token, 'ash-fox-42');
    assert.strictEqual(cleanPinnedLink.frag, '');

    // Clean query URL with Nano-Seed fragment
    const querySeedLink = BlackendCrypto.parseLink('', '?m=ember-lynx-99', '#n.7xK9pQ_89');
    assert.strictEqual(querySeedLink.mode, 'vault');
    assert.strictEqual(querySeedLink.token, 'ember-lynx-99');
    assert.strictEqual(querySeedLink.frag, 'n.7xK9pQ_89');

    // Direct In-Link
    const directLink = BlackendCrypto.parseLink('', '', '#k1.abc.def.ghi');
    assert.strictEqual(directLink.mode, 'direct');
    assert.strictEqual(directLink.parts.length, 4);

    console.log('[PASS] Link classifier accurately identifies clean URLs, query parameters, and fragments');
  }

  // Test 2.7: QR Code Generator
  {
    const qrSvg = BlackendQR.renderSVG('https://blackend.on/v/ash-fox-42');
    assert(qrSvg, 'QR SVG must be generated');
    assert(qrSvg.svg.includes('<svg'), 'QR SVG output must contain <svg tag');
    assert(qrSvg.q.size >= 21, 'QR matrix size must be valid');
    console.log('[PASS] QR Code byte-mode encoder and SVG renderer');
  }

  console.log('\n>>> ALL AUTOMATED TESTS PASSED SUCCESSFULLY! <<<\n');
}

runTests().catch(err => {
  console.error('[TEST SUITE ERROR]', err);
  process.exit(1);
});
