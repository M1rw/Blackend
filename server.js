/**
 * blackend local dev server
 * Automatically detects and runs native PHP (php -S 127.0.0.1:PORT router.php)
 * with transparent fallback to Node.js when PHP is not installed.
 */

const { spawn, execSync } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 8090;
const ROOT = __dirname;
const DATA_DIR = path.join(ROOT, 'data');

if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

function findPhpBinary() {
  const candidates = [
    process.env.PHP_BIN,
    'php',
    'C:\\xampp\\php\\php.exe',
    'C:\\php\\php.exe',
    'C:\\tools\\php\\php.exe',
    'C:\\Program Files\\php\\php.exe'
  ].filter(Boolean);

  for (const bin of candidates) {
    try {
      if (fs.existsSync(bin)) return bin;
      execSync(`"${bin}" -v`, { stdio: 'ignore' });
      return bin;
    } catch (_) {}
  }
  return null;
}

// 1. Try launching native PHP server if PHP is present
const phpBin = findPhpBinary();
if (phpBin && !process.env.FORCE_NODE_SERVER) {
  console.log(`[blackend] Starting native PHP server using ${phpBin} on http://localhost:${PORT}...`);
  const routerPath = path.join(ROOT, 'router.php');
  const phpProcess = spawn(phpBin, ['-S', `127.0.0.1:${PORT}`, routerPath], {
    cwd: ROOT,
    stdio: 'inherit'
  });

  phpProcess.on('error', (err) => {
    console.error('[blackend] Failed to launch PHP process, falling back to Node...', err.message);
    startNodeServer();
  });

  phpProcess.on('exit', (code) => {
    if (code !== 0 && code !== null) {
      console.warn(`[blackend] PHP server exited with code ${code}. Falling back to Node...`);
      startNodeServer();
    }
  });
} else {
  startNodeServer();
}

// 2. Node.js fallback server
function startNodeServer() {
  console.log(`[blackend] Running in Node.js fallback mode on http://localhost:${PORT}`);

  const ADJECTIVES = [
    'ash', 'neon', 'void', 'amber', 'frost', 'dark', 'solar', 'silent',
    'ghost', 'zero', 'ruby', 'cobalt', 'iron', 'cipher', 'dusk', 'onyx',
    'static', 'lunar', 'haze', 'phantom', 'flux', 'echo', 'prime', 'rebel',
    'shadow', 'nova', 'drift', 'zenith', 'pulse', 'apex'
  ];
  const NOUNS = [
    'wolf', 'lynx', 'hawk', 'fox', 'moth', 'seal', 'viper', 'bear',
    'raven', 'crow', 'owl', 'stag', 'pike', 'crane', 'finch', 'ray',
    'crab', 'wasp', 'ant', 'swan', 'hare', 'trout', 'rook', 'gull',
    'toad', 'newt', 'drake', 'hound', 'falcon', 'lion'
  ];

  function generateId() {
    if (Math.random() < 0.6) {
      const adj = ADJECTIVES[Math.floor(Math.random() * ADJECTIVES.length)];
      const noun = NOUNS[Math.floor(Math.random() * NOUNS.length)];
      const num = Math.floor(Math.random() * 90) + 10;
      return `${adj}-${noun}-${num}`;
    }
    const chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let id = '';
    const bytes = crypto.randomBytes(6);
    for (let i = 0; i < 6; i++) {
      id += chars[bytes[i] % chars.length];
    }
    return id;
  }

  const vaultStore = new Map();

  function handleVaultAction(action, body) {
    const now = Math.floor(Date.now() / 1000);

    switch (action) {
      case 'ping':
        return { ok: true, service: 'blackend-vault', version: '2.0.0' };

      case 'store': {
        let id = generateId();
        while (vaultStore.has(id)) id = generateId();

        const exp = parseInt(body.exp, 10) || 0;
        const life = exp > 0 ? Math.min(exp, 604800) : 604800;

        const record = {
          id,
          exp: now + life,
          pin: !!body.pin,
          nc: parseInt(body.nc, 10) || 0,
          iv: body.iv || '',
          ct: body.ct || '',
          salt: body.salt || '',
          wiv: body.wiv || '',
          wrapped: body.wrapped || '',
          tries: 0,
          state: (body.nc > 0) ? 'incomplete' : 'complete',
          chunks: new Map(),
          read: null,
          claim: null,
          why: null
        };

        vaultStore.set(id, record);
        return { ok: true, id };
      }

      case 'put': {
        const rec = vaultStore.get(body.id);
        if (!rec || rec.state !== 'incomplete') return { ok: false, error: 'state' };
        const i = parseInt(body.i, 10);
        if (i < 0 || i >= rec.nc) return { ok: false, error: 'index' };
        rec.chunks.set(i, body.data);
        return { ok: true };
      }

      case 'ready': {
        const rec = vaultStore.get(body.id);
        if (!rec) return { ok: false, error: 'missing' };
        rec.state = 'complete';
        return { ok: true };
      }

      case 'fetch': {
        const rec = vaultStore.get(body.id);
        if (!rec) return { ok: false, why: 'unknown' };
        if (now > rec.exp) {
          rec.state = 'gone';
          rec.why = 'expired';
          return { ok: false, why: 'expired' };
        }
        if (rec.state === 'gone') {
          return { ok: false, why: rec.why || 'read' };
        }
        if (rec.read && rec.claim && now > rec.claim) {
          rec.state = 'gone';
          rec.why = 'read';
          return { ok: false, why: 'read' };
        }
        if (rec.state !== 'complete') {
          return { ok: false, why: 'unknown' };
        }

        // Active claim window: record read time on first fetch, keep envelope accessible until burn or claim expiry
        if (!rec.read) {
          rec.read = now;
          rec.claim = now + 600; // 10 min claim window
        }

        return {
          ok: true,
          iv: rec.iv,
          ct: rec.ct,
          salt: rec.salt,
          wiv: rec.wiv,
          wrapped: rec.wrapped,
          pin: rec.pin,
          nc: rec.nc,
          read: rec.read,
          claim: rec.claim
        };
      }

      case 'chunk': {
        const rec = vaultStore.get(body.id);
        if (!rec) return { ok: false, error: 'unknown' };
        const i = parseInt(body.i, 10);
        const data = rec.chunks.get(i);
        if (!data) return { ok: false, error: 'missing_chunk' };
        rec.chunks.delete(i); // Shred chunk on delivery
        return { ok: true, data };
      }

      case 'fail': {
        const rec = vaultStore.get(body.id);
        if (!rec) return { ok: false, error: 'unknown' };
        rec.tries = (rec.tries || 0) + 1;
        const left = Math.max(0, 3 - rec.tries);
        if (rec.tries >= 3) {
          rec.state = 'gone';
          rec.why = 'killed';
          rec.iv = '';
          rec.ct = '';
          rec.wrapped = '';
          rec.chunks.clear();
          return { ok: true, state: 'killed', left: 0 };
        }
        return { ok: true, state: 'locked', left };
      }

      case 'burn': {
        const rec = vaultStore.get(body.id);
        if (rec) {
          rec.state = 'gone';
          rec.why = body.why || 'read';
          rec.iv = '';
          rec.ct = '';
          rec.wrapped = '';
          rec.chunks.clear();
        }
        return { ok: true };
      }

      case 'status': {
        const rec = vaultStore.get(body.id);
        if (!rec) return { ok: true, state: 'gone', why: 'unknown' };
        if (rec.state === 'gone') return { ok: true, state: 'gone', why: rec.why || 'read' };
        if (now > rec.exp) return { ok: true, state: 'gone', why: 'expired' };
        if (rec.read) {
          if (rec.claim && now > rec.claim) {
            rec.state = 'gone';
            rec.why = 'read';
            return { ok: true, state: 'gone', why: 'read' };
          }
          return { ok: true, state: 'opened', read: rec.read };
        }
        return { ok: true, state: 'sealed' };
      }

      default:
        return { ok: false, error: 'unknown action' };
    }
  }

  const MIME_TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.ico': 'image/x-icon'
  };

  function sendHtmlPage(res, filePath) {
    fs.readFile(filePath, 'utf8', (err, content) => {
      if (err) {
        res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Server error');
        return;
      }
      // Strip any leading <?php ... ?> tags so PHP source never leaks into browser
      const cleanHtml = content.replace(/^<\?php[\s\S]*?\?>\s*/i, '');
      res.writeHead(200, {
        'Content-Type': 'text/html; charset=utf-8',
        'X-Content-Type-Options': 'nosniff',
        'Referrer-Policy': 'no-referrer',
        'Cache-Control': 'no-store, no-cache, must-revalidate',
        'Content-Security-Policy': "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'"
      });
      res.end(cleanHtml);
    });
  }

  const server = http.createServer((req, res) => {
    const parsedUrl = new URL(req.url, `http://${req.headers.host}`);
    const pathname = parsedUrl.pathname;

    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('Referrer-Policy', 'no-referrer');
    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');

    // Vault API endpoints (POST /vault.php or POST /api/vault.php)
    if (req.method === 'POST' && (pathname === '/vault.php' || pathname === '/api/vault.php')) {
      let body = '';
      req.on('data', chunk => {
        body += chunk;
        if (body.length > 8 * 1048576) {
          res.writeHead(413, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ ok: false, error: 'payload too large' }));
          req.destroy();
        }
      });

      req.on('end', () => {
        try {
          const json = JSON.parse(body || '{}');
          const action = json.action || '';
          const result = handleVaultAction(action, json);
          res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify(result));
        } catch (err) {
          res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
          res.end(JSON.stringify({ ok: false, error: 'bad json' }));
        }
      });
      return;
    }

    // Clean URL Routing: /v/:token or /m/:token -> serve index.php
    if (pathname.match(/^\/(?:v|m)\/[A-Za-z0-9_-]+/)) {
      sendHtmlPage(res, path.join(ROOT, 'index.php'));
      return;
    }

    // Static File Serving
    if (pathname === '/' || pathname === '' || pathname === '/index.php' || pathname === '/index.html') {
      sendHtmlPage(res, path.join(ROOT, 'index.php'));
      return;
    }

    const filePath = path.join(ROOT, pathname);
    fs.stat(filePath, (err, stats) => {
      if (err || !stats.isFile()) {
        sendHtmlPage(res, path.join(ROOT, 'index.php'));
        return;
      }

      const ext = path.extname(filePath).toLowerCase();
      const contentType = MIME_TYPES[ext] || 'application/octet-stream';

      fs.readFile(filePath, (err2, data) => {
        if (err2) {
          res.writeHead(500);
          res.end('Error reading file');
          return;
        }
        res.writeHead(200, { 'Content-Type': contentType });
        res.end(data);
      });
    });
  });

  server.listen(PORT, '127.0.0.1', () => {
    console.log(`[blackend] Server running on http://localhost:${PORT}`);
  });
}
