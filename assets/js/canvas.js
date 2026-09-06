/**
 * blackend background canvas
 * Interactive particle network and floating ember motes with performance optimization.
 */

const BlackendCanvas = (() => {
  let cv, ctx;
  let W, H, dots = [], baseCanvas, mx = -1e4, my = -1e4, smx = -1e4, smy = -1e4;
  let motes = [];
  let isReduced = false;
  let config = { net: true, motes: true };
  let animId = null;

  function init(canvasEl, userConfig = {}) {
    if (!canvasEl) return;
    cv = canvasEl;
    ctx = cv.getContext('2d');
    config = Object.assign(config, userConfig);
    isReduced = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    buildNet();

    window.addEventListener('pointermove', e => {
      mx = e.clientX;
      my = e.clientY;
    }, { passive: true });

    window.addEventListener('pointerleave', () => {
      mx = -1e4;
      my = -1e4;
    }, { passive: true });

    window.addEventListener('resize', () => {
      buildNet();
    }, { passive: true });

    if (!isReduced) {
      setInterval(() => {
        if (config.motes && typeof document !== 'undefined' && !document.hidden && motes.length < 6) {
          motes.push({
            x: Math.random() * W,
            y: H + 8,
            r: 1.3 + Math.random() * 1.2,
            vy: 14 + Math.random() * 16,
            ph: Math.random() * 6.28
          });
        }
      }, 3200);

      startLoop();
    }
  }

  function buildNet() {
    if (!cv || !ctx) return;
    const dpr = Math.min(typeof window !== 'undefined' ? (window.devicePixelRatio || 1) : 1, 2);
    W = window.innerWidth;
    H = window.innerHeight;
    cv.width = W * dpr;
    cv.height = H * dpr;
    cv.style.width = W + 'px';
    cv.style.height = H + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    dots = [];
    const sp = W < 720 ? 34 : 26;
    for (let x = sp / 2; x < W; x += sp) {
      for (let y = sp / 2; y < H; y += sp) {
        dots.push({ x, y });
      }
    }

    baseCanvas = document.createElement('canvas');
    baseCanvas.width = W * dpr;
    baseCanvas.height = H * dpr;
    const bCtx = baseCanvas.getContext('2d');
    bCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
    bCtx.fillStyle = 'rgba(237, 235, 229, 0.085)';
    bCtx.beginPath();
    for (const d of dots) {
      bCtx.moveTo(d.x + 1, d.y);
      bCtx.arc(d.x, d.y, 1, 0, 6.2832);
    }
    bCtx.fill();

    if (isReduced) {
      ctx.drawImage(baseCanvas, 0, 0, W, H);
    }
  }

  function startLoop() {
    let lastTime = performance.now();

    function loop(currentTime) {
      animId = requestAnimationFrame(loop);
      if (typeof document !== 'undefined' && document.hidden) return;

      const dt = Math.min((currentTime - lastTime) / 1000, 0.05);
      lastTime = currentTime;

      ctx.clearRect(0, 0, W, H);
      if (!config.net) return;

      ctx.drawImage(baseCanvas, 0, 0, W, H);
      smx += (mx - smx) * 0.08;
      smy += (my - smy) * 0.08;

      if (mx > -999 && smx > -100 && smx < W + 100 && smy > -100 && smy < H + 100) {
        const radius = 130;
        const r2 = radius * radius;
        const minX = smx - radius;
        const maxX = smx + radius;
        const minY = smy - radius;
        const maxY = smy + radius;

        for (let i = 0; i < dots.length; i++) {
          const d = dots[i];
          if (d.x < minX || d.x > maxX || d.y < minY || d.y > maxY) continue;
          const dx = d.x - smx;
          const dy = d.y - smy;
          const d2 = dx * dx + dy * dy;
          if (d2 < r2) {
            const p = 1 - Math.sqrt(d2) / radius;
            const r = 237 + (255 - 237) * p | 0;
            const g = 235 + (180 - 235) * p | 0;
            const bl = 229 + (84 - 229) * p | 0;
            ctx.fillStyle = `rgba(${r}, ${g}, ${bl}, ${(0.1 + 0.3 * p).toFixed(3)})`;
            ctx.beginPath();
            ctx.arc(d.x, d.y, 1.1 + 1.3 * p, 0, 6.2832);
            ctx.fill();
          }
        }
      }

      if (config.motes) {
        ctx.shadowColor = 'rgba(255, 170, 90, 0.8)';
        ctx.shadowBlur = 6;
        for (let i = motes.length - 1; i >= 0; i--) {
          const m = motes[i];
          m.y -= m.vy * dt;
          if (m.y < -10) {
            motes.splice(i, 1);
            continue;
          }
          const alpha = Math.min(1, (H - m.y) / 80, (m.y + 10) / 120) * 0.22;
          ctx.fillStyle = `rgba(255, 190, 120, ${alpha.toFixed(3)})`;
          ctx.beginPath();
          ctx.arc(m.x + Math.sin(currentTime * 0.001 + m.ph) * 4, m.y, m.r, 0, 6.2832);
          ctx.fill();
        }
        ctx.shadowBlur = 0;
      }
    }

    loop(performance.now());
  }

  function setConfig(newConfig) {
    Object.assign(config, newConfig);
    if (!config.net) {
      document.body.classList.add('net-off');
    } else {
      document.body.classList.remove('net-off');
    }
  }

  return {
    init,
    buildNet,
    setConfig
  };
})();

if (typeof module !== 'undefined' && module.exports) {
  module.exports = BlackendCanvas;
}
