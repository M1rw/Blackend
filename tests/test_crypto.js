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
    const msg = 'Meet at midnight on the north pier.';
    const payload = await BlackendCrypto.buildDirectPayload(msg, 60, null, null);
    assert(payload.startsWith('k1.'), 'Direct payload should start with k1');
    const parts = payload.split('.');
    assert.strictEqual(parts.length, 4, 'Direct unpinned payload must have 4 parts');

    const { obj } = await BlackendCrypto.decryptDirectPayload(parts, null);
    assert.strictEqual(obj.m, msg, 'Decrypted text must match original');
    assert.strictEqual(obj.x, 60, 'Decrypted expiry must match original');
    console.log('[PASS] Direct In-Link plain message encrypt/decrypt roundtrip');
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

  // Test 2.4: Vault Escrow Payload
  {
    const msg = 'Vault escrow test message';
    const pin = '7712';
    const vaultData = await BlackendCrypto.buildVaultPayload(msg, 600, pin, null);
    assert(vaultData.envelope.iv, 'Envelope must have IV');
    assert(vaultData.envelope.ct, 'Envelope must have Ciphertext');
    assert(vaultData.frag.startsWith('k2.'), 'Vault key fragment for PIN must start with k2');

    // Wrong PIN
    let failed = false;
    try {
      await BlackendCrypto.decryptVaultPayload(vaultData.envelope.iv, vaultData.envelope.ct, vaultData.frag, '1234');
    } catch (_) {
      failed = true;
    }
    assert(failed, 'Vault payload decryption with wrong PIN must fail');

    // Correct PIN
    const { obj } = await BlackendCrypto.decryptVaultPayload(
      vaultData.envelope.iv,
      vaultData.envelope.ct,
      vaultData.frag,
      pin
    );
    assert.strictEqual(obj.m, msg);
    console.log('[PASS] Vault Escrow envelope encryption and key-fragment decryption roundtrip');
  }

  // Test 2.5: Link Classifier
  {
    const directLink = BlackendCrypto.parseLink('', '#k1.abc.def.ghi');
    assert.strictEqual(directLink.mode, 'direct');
    assert.strictEqual(directLink.parts.length, 4);

    const vaultLink = BlackendCrypto.parseLink('?m=9f3ab2c1e4d86f07a1b2c3d4e5f60718', '#k1.secretkey');
    assert.strictEqual(vaultLink.mode, 'vault');
    assert.strictEqual(vaultLink.token, '9f3ab2c1e4d86f07a1b2c3d4e5f60718');
    assert.strictEqual(vaultLink.frag, 'k1.secretkey');

    console.log('[PASS] Link classifier accurately identifies Direct vs Escrow links');
  }

  // Test 2.6: QR Code Generator
  {
    const qrSvg = BlackendQR.renderSVG('https://blackend.on/?m=9f3ab2c1e4d86f07#k1.Z8Vgr3mbC88');
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
