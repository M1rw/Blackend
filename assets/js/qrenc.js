/**
 * blackend QR encoder & renderer
 * Byte-mode QR Code Generator (Versions 1–10, ECL L).
 * Renders crisp SVG markup and renders downloadable high-res PNG canvases.
 */

const BlackendQR = (() => {
  const EXP = new Uint8Array(512);
  const LOG = new Uint8Array(256);
  {
    let x = 1;
    for (let i = 0; i < 255; i++) {
      EXP[i] = x;
      LOG[x] = i;
      x <<= 1;
      if (x & 256) x ^= 0x11d;
    }
    for (let i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
  }

  const gmul = (a, b) => (a === 0 || b === 0) ? 0 : EXP[LOG[a] + LOG[b]];
  const ECL = { ecc: [7, 10, 15, 20, 26, 18, 20, 24, 30, 18], blk: [1, 1, 1, 1, 1, 2, 2, 2, 2, 4] };
  const ALIGN = {
    2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30], 6: [6, 34],
    7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50]
  };

  function rawModules(v) {
    let r = (16 * v + 128) * v + 64;
    if (v >= 2) {
      const n = Math.floor(v/7) + 2;
      r -= (25 * n - 10) * n - 55;
      if (v >= 7) r -= 36;
    }
    return r;
  }

  function rsDivisor(deg) {
    const r = new Array(deg).fill(0);
    r[deg - 1] = 1;
    let root = 1;
    for (let i = 0; i < deg; i++) {
      for (let j = 0; j < deg; j++) {
        r[j] = gmul(r[j], root);
        if (j + 1 < deg) r[j] ^= r[j + 1];
      }
      root = gmul(root, 2);
    }
    return r;
  }

  function rsRemainder(data, div) {
    const res = div.map(() => 0);
    for (const b of data) {
      const f = b ^ res.shift();
      res.push(0);
      div.forEach((c, i) => res[i] ^= gmul(c, f));
    }
    return res;
  }

  function encode(text) {
    const bytes = typeof text === 'string' ? new TextEncoder().encode(text) : text;
    for (let v = 1; v <= 10; v++) {
      const total = Math.floor(rawModules(v) / 8);
      const dataCw = total - ECL.ecc[v - 1] * ECL.blk[v - 1];
      const countBits = v <= 9 ? 8 : 16;
      if (4 + countBits + bytes.length * 8 <= dataCw * 8) {
        return build(bytes, v, countBits, total, dataCw);
      }
    }
    return null; // Exceeds version 10 payload capacity
  }

  function build(bytes, v, countBits, total, dataCw) {
    const bits = [];
    const push = (val, n) => {
      for (let i = n - 1; i >= 0; i--) bits.push((val >>> i) & 1);
    };
    push(4, 4);
    push(bytes.length, countBits);
    for (const b of bytes) push(b, 8);
    const term = Math.min(4, dataCw * 8 - bits.length);
    for (let i = 0; i < term; i++) bits.push(0);
    while (bits.length % 8) bits.push(0);
    const data = [];
    for (let i = 0; i < bits.length; i += 8) {
      let b = 0;
      for (let j = 0; j < 8; j++) b = (b << 1) | bits[i + j];
      data.push(b);
    }
    for (let pad = 0xEC; data.length < dataCw; pad ^= 0xEC ^ 0x11) data.push(pad);
    const n = ECL.blk[v - 1];
    const eLen = ECL.ecc[v - 1];
    const blocks = [];
    for (let i = 0, k = 0; i < n; i++) {
      const len = Math.floor(dataCw / n) + (i < n - dataCw % n ? 0 : 1);
      const dat = data.slice(k, k + len);
      k += len;
      blocks.push({ dat, ecc: rsRemainder(dat, rsDivisor(eLen)) });
    }
    const cw = [];
    const maxD = Math.max(...blocks.map(b => b.dat.length));
    for (let i = 0; i < maxD; i++) {
      for (const b of blocks) {
        if (i < b.dat.length) cw.push(b.dat[i]);
      }
    }
    for (let i = 0; i < eLen; i++) {
      for (const b of blocks) cw.push(b.ecc[i]);
    }
    const size = 17 + v * 4;
    const M = [...Array(size)].map(() => Array(size).fill(false));
    const F = [...Array(size)].map(() => Array(size).fill(false));
    const set = (x, y, val) => { M[y][x] = val; F[y][x] = true; };

    for (const [cy, cx] of [[3, 3], [size - 4, 3], [3, size - 4]]) {
      for (let dy = -1; dy <= 7; dy++) {
        for (let dx = -1; dx <= 7; dx++) {
          const y = cy + dy;
          const x = cx + dx;
          if (y < 0 || y >= size || x < 0 || x >= size) continue;
          const inP = dx >= 0 && dx <= 6 && dy >= 0 && dy <= 6;
          const dark = inP && (dx === 0 || dx === 6 || dy === 0 || dy === 6 || (dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4));
          set(x, y, dark);
        }
      }
    }

    for (let i = 8; i < size - 8; i++) {
      set(i, 6, i % 2 === 0);
      set(6, i, i % 2 === 0);
    }

    if (v >= 2) {
      const pos = ALIGN[v];
      const last = pos[pos.length - 1];
      for (const r of pos) {
        for (const c of pos) {
          if ((r === 6 && c === 6) || (r === 6 && c === last) || (r === last && c === 6)) continue;
          for (let dy = -2; dy <= 2; dy++) {
            for (let dx = -2; dx <= 2; dx++) {
              set(c + dx, r + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
            }
          }
        }
      }
    }

    const drawFormat = mask => {
      const d = (1 << 3) | mask;
      let rem = d;
      for (let i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
      const fb = ((d << 10) | rem) ^ 0x5412;
      const g = i => (fb >>> i) & 1;
      for (let i = 0; i <= 5; i++) set(8, i, g(i));
      set(8, 7, g(6)); set(8, 8, g(7)); set(7, 8, g(8));
      for (let i = 9; i < 15; i++) set(14 - i, 8, g(i));
      for (let i = 0; i < 8; i++) set(size - 1 - i, 8, g(i));
      for (let i = 8; i < 15; i++) set(8, size - 15 + i, g(i));
      set(8, size - 8, true);
    };

    if (v >= 7) {
      let rem = v;
      for (let i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
      const vb = (v << 12) | rem;
      for (let i = 0; i < 18; i++) {
        const b = (vb >>> i) & 1;
        const a = size - 11 + i % 3;
        const c = Math.floor(i / 3);
        set(c, a, b);
        set(a, c, b);
      }
    }

    drawFormat(0);
    let bi = 0;
    for (let right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (let vert = 0; vert < size; vert++) {
        for (let j = 0; j < 2; j++) {
          const x = right - j;
          const up = ((right + 1) & 2) === 0;
          const y = up ? size - 1 - vert : vert;
          if (!F[y][x]) {
            M[y][x] = bi < cw.length * 8 ? ((cw[bi >>> 3] >>> (7 - (bi & 7))) & 1) === 1 : false;
            bi++;
          }
        }
      }
    }

    const maskFn = m => (x, y) => {
      switch (m) {
        case 0: return (x + y) % 2 === 0;
        case 1: return y % 2 === 0;
        case 2: return x % 3 === 0;
        case 3: return (x + y) % 3 === 0;
        case 4: return (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0;
        case 5: return (x * y) % 2 + (x * y) % 3 === 0;
        case 6: return ((x * y) % 2 + (x * y) % 3) % 2 === 0;
        default: return ((x + y) % 2 + (x * y) % 3) % 2 === 0;
      }
    };

    const toggle = m => {
      const f = maskFn(m);
      for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
          if (!F[y][x] && f(x, y)) M[y][x] = !M[y][x];
        }
      }
    };

    const penalty = () => {
      let res = 0;
      const rows = M;
      const cols = M[0].map((_, x) => M.map(r => r[x]));
      for (const line of [...rows, ...cols]) {
        let run = 1;
        for (let i = 1; i <= line.length; i++) {
          if (i < line.length && line[i] === line[i - 1]) run++;
          else {
            if (run >= 5) res += 3 + run - 5;
            run = 1;
          }
        }
        const s = line.map(v => v ? 1 : 0).join('');
        for (const pat of ['10111010000', '00001011101']) {
          let idx = 0;
          while ((idx = s.indexOf(pat, idx)) !== -1) {
            res += 40;
            idx++;
          }
        }
      }
      for (let y = 0; y < size - 1; y++) {
        for (let x = 0; x < size - 1; x++) {
          const c = M[y][x];
          if (c === M[y][x + 1] && c === M[y + 1][x] && c === M[y + 1][x + 1]) res += 3;
        }
      }
      let dark = 0;
      for (const r of M) {
        for (const b of r) if (b) dark++;
      }
      const t = size * size;
      res += Math.max(0, Math.ceil(Math.abs(dark * 20 - t * 10) / t) - 1) * 10;
      return res;
    };

    let best = 0;
    let bestP = Infinity;
    for (let m = 0; m < 8; m++) {
      toggle(m);
      const p = penalty();
      toggle(m);
      if (p < bestP) {
        bestP = p;
        best = m;
      }
    }
    toggle(best);
    drawFormat(best);
    return { size, M };
  }

  function renderSVG(url) {
    const q = encode(url);
    if (!q) return null;
    const s = q.size;
    const Q = 4;
    const T = s + Q * 2;
    const INK = '#111722';
    const inFinder = (x, y) => (x < 7 && y < 7) || (x >= s - 7 && y < 7) || (x < 7 && y >= s - 7);

    let r = '';
    for (let y = 0; y < s; y++) {
      for (let x = 0; x < s; x++) {
        if (q.M[y][x] && !inFinder(x, y)) {
          r += `<rect x="${x + Q}" y="${y + Q}" width="1" height="1" rx=".3"/>`;
        }
      }
    }

    const eye = (ex, ey) =>
      `<rect x="${ex + Q + 0.5}" y="${ey + Q + 0.5}" width="6" height="6" rx="1.9" fill="none" stroke="${INK}" stroke-width="1"/><rect x="${ex + Q + 2}" y="${ey + Q + 2}" width="3" height="3" rx="1" fill="${INK}"/>`;

    return {
      svg: `<svg viewBox="0 0 ${T} ${T}" xmlns="http://www.w3.org/2000/svg" fill="${INK}" role="img" aria-label="message link QR code">${r}${eye(0, 0)}${eye(s - 7, 0)}${eye(0, s - 7)}</svg>`,
      q
    };
  }

  function downloadPNG(q, filename = 'blackend-qr.png') {
    if (!q || typeof document === 'undefined') return;
    const Q = 4;
    const S = 12;
    const INK = '#111722';
    const c = document.createElement('canvas');
    c.width = c.height = (q.size + Q * 2) * S;
    const g = c.getContext('2d');
    g.fillStyle = '#f2eee6';
    g.fillRect(0, 0, c.width, c.height);
    g.fillStyle = INK;

    const inF = (x, y) => (x < 7 && y < 7) || (x >= q.size - 7 && y < 7) || (x < 7 && y >= q.size - 7);
    const rr = (x, y, w, h, r) => {
      if (g.roundRect) {
        g.beginPath();
        g.roundRect(x, y, w, h, r);
        g.fill();
      } else {
        g.fillRect(x, y, w, h);
      }
    };

    for (let y = 0; y < q.size; y++) {
      for (let x = 0; x < q.size; x++) {
        if (q.M[y][x] && !inF(x, y)) {
          rr((x + Q) * S, (y + Q) * S, S, S, S * 0.3);
        }
      }
    }

    const eye = (ex, ey) => {
      if (g.roundRect) {
        g.beginPath();
        g.roundRect((ex + Q) * S + S / 2, (ey + Q) * S + S / 2, 6 * S, 6 * S, S * 1.9);
        g.lineWidth = S;
        g.strokeStyle = INK;
        g.stroke();
        rr((ex + Q + 2) * S, (ey + Q + 2) * S, 3 * S, 3 * S, S * 0.9);
      } else {
        g.lineWidth = S;
        g.strokeStyle = INK;
        g.strokeRect((ex + Q) * S + S / 2, (ey + Q) * S + S / 2, 6 * S, 6 * S);
        g.fillRect((ex + Q + 2) * S, (ey + Q) * S + S / 2, 3 * S, 3 * S);
      }
    };

    eye(0, 0);
    eye(q.size - 7, 0);
    eye(0, q.size - 7);

    const a = document.createElement('a');
    a.href = c.toDataURL('image/png');
    a.download = filename;
    document.body.append(a);
    a.click();
    a.remove();
  }

  return {
    encode,
    renderSVG,
    downloadPNG
  };
})();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = BlackendQR;
}
