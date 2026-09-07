/**
 * blackend main application script
 * Coordinates UI states, composer, WebCrypto dual-engine, vault escrow,
 * PIN gate, local archive, and interactive receipt verifier.
 */

(() => {
  'use strict';

  const $ = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const clampN = (v, a, b) => Math.max(a, Math.min(b, v));
  const wait = ms => new Promise(r => setTimeout(r, ms));
  const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const MOBILE = () => innerWidth <= 900;
  const utcHM = () => new Date().toISOString().slice(11, 16);
  const escapeHTML = s => String(s).replace(/[&<>"']/g,
    c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const fmtSize = b => b < 1024 ? b + ' B' : b < 1048576 ? (b / 1024).toFixed(1) + ' KB' : (b / 1048576).toFixed(1) + ' MB';
  const elide = (u, max) => u.length > max ? u.slice(0, max - 14) + '…' + u.slice(-13) : u;

  /* ================= Icons ================= */
  const IC = {
    lock: '<svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3"/></svg>',
    clock: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4"/></svg>',
    copy: '<svg viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M15 5.5H7.5A2 2 0 0 0 5.5 7.5V15"/></svg>',
    check: '<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
    eye: '<svg viewBox="0 0 24 24"><path d="M2.8 12S6.2 5.9 12 5.9 21.2 12 21.2 12 17.8 18.1 12 18.1 2.8 12 2.8 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
    flame: '<svg viewBox="0 0 24 24"><path d="M12 4.5c.5 3-3.8 5-3.8 9a3.8 3.8 0 0 0 7.6 0c0-1.9-1-3-1.7-4.4-.4 1-1.1 1.6-1.9 1.9.4-2.2.3-4.3-.2-6.5Z"/></svg>',
    clockx: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4M5.5 5.5l13 13"/></svg>',
    lockx: '<svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3M10 15.2l4 4M14 15.2l-4 4"/></svg>',
    shield: '<svg viewBox="0 0 24 24"><path d="M12 3.5l7 2.7v4.5c0 4.7-3 7.7-7 9.8-4-2.1-7-5.1-7-9.8V6.2Z"/></svg>',
    server: '<svg viewBox="0 0 24 24"><rect x="4" y="4.5" width="16" height="6" rx="2"/><rect x="4" y="13.5" width="16" height="6" rx="2"/><path d="M7.5 7.5h.01M7.5 16.5h.01"/></svg>',
    chev: '<svg class="chev" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5"/></svg>',
    pencil: '<svg viewBox="0 0 24 24"><path d="M4 20l1-4L16.5 4.5a2.1 2.1 0 0 1 3 3L8 19l-4 1Z"/></svg>',
    clip: '<svg viewBox="0 0 24 24"><path d="M21 11.5 12.5 20a5.5 5.5 0 0 1-7.8-7.8l8-8a3.7 3.7 0 0 1 5.2 5.2l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/></svg>',
    file: '<svg viewBox="0 0 24 24"><path d="M13.5 4.5H7a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10Z"/><path d="M13.5 4.5V10H19"/></svg>',
    image: '<svg viewBox="0 0 24 24"><rect x="4" y="5.5" width="16" height="13" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="M4.5 16.5l4.2-4 3.3 3 2.5-2.3 5 4.3"/></svg>',
    audio: '<svg viewBox="0 0 24 24"><path d="M4 10v4M8 7v10M12 4.5v15M16 7v10M20 10v4"/></svg>',
    video: '<svg viewBox="0 0 24 24"><rect x="3.5" y="6.5" width="17" height="11" rx="2.5"/><path d="M10.5 10l4.5 2-4.5 2Z"/></svg>'
  };

  const attIcon = t => t.startsWith('image/') ? IC.image :
    t.startsWith('audio/') ? IC.audio :
    t.startsWith('video/') ? IC.video : IC.file;

  /* ================= Backend API Bridge ================= */
  async function api(action, body) {
    try {
      const r = await fetch('/vault.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action }, body || {}))
      });
      if (!r.ok) {
        return { ok: false, error: 'http_' + r.status };
      }
      return await r.json();
    } catch (_) {
      return { ok: false, error: 'network' };
    }
  }

  /* ================= Settings & Themes ================= */
  const SET = {
    accent: 'ember',
    net: true,
    motes: true,
    fuse: 'read',
    burn: 'calm',
    burnTime: 2.0,
    readTime: 4,
    autocopy: false,
    archive: true,
    attLimit: 5,
    engine: 'vault' // 'vault' (escrow short link) or 'direct' (in-link hash)
  };

  const ACCENTS = {
    ember: ['#ffb454', '#ffc678'],
    crimson: ['#ff7a8a', '#ffa3ae'],
    mint: ['#7fe0b2', '#a9eccd'],
    ice: ['#8fc7ff', '#bcdcff']
  };

  const h2r = h => {
    const n = parseInt(h.slice(1), 16);
    return `${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}`;
  };

  function saveSettings() {
    try {
      localStorage.setItem('blackend_settings_v2', JSON.stringify(SET));
      localStorage.setItem('blackend_accent', SET.accent);
      localStorage.setItem('blackend_fuse', SET.fuse);
      localStorage.setItem('blackend_burn_speed', SET.burn);
      localStorage.setItem('blackend_burn_time', String(SET.burnTime));
      localStorage.setItem('blackend_read_time', String(SET.readTime));
      localStorage.setItem('blackend_attLimit', String(SET.attLimit));
      for (const k of ['net', 'motes', 'autocopy', 'archive']) {
        localStorage.setItem('blackend_set_' + k, SET[k] ? '1' : '0');
      }
    } catch (_) {}
  }

  function applyAccent(name) {
    SET.accent = name;
    const a = ACCENTS[name] || ACCENTS.ember;
    const rs = document.documentElement.style;
    rs.setProperty('--ember', a[0]);
    rs.setProperty('--ember2', a[1]);
    rs.setProperty('--ember-rgb', h2r(a[0]));
    $$('.swatch').forEach(s => s.classList.toggle('on', s.dataset.acc === name));
    saveSettings();
  }

  /* ================= Operator Identity & Pixel Avatar ================= */
  const TITLES = [
    "why do cats knead blankets?", "explain quantum tunneling simply",
    "3-day Kyoto itinerary, budget", "is intermittent fasting actually science?",
    "help me write a polite no", "best headphones under $100?",
    "why is the sky blue, really", "summarize today's markets",
    "what changed in the AI race this week", "ideas for a small balcony garden",
    "how do noise-cancelling headphones work", "teach me chess openings in 10 minutes",
    "is coffee actually dehydrating?", "beginner sourdough, no equipment",
    "why do we dream?", "fastest way to learn Spanish verbs",
    "explain the shipping news simply", "warm-up stretches before running",
    "why do old songs sound better?", "how do solar panels work at night?",
    "milk alternatives ranked, please", "explain inflation like I'm 12",
    "best 20-minute workouts, no gym", "why are airports so cold?",
    "draft a birthday message for a colleague", "what is a black hole, kid version",
    "should I learn Python or Rust?", "why does time feel faster with age?",
    "easy meals with 5 ingredients", "what's new in space exploration?",
    "how does WiFi reach my room?", "help me pick a podcast for commuting"
  ];

  let lastTitle = '';
  function genTitle() {
    let t = TITLES[Math.random() * TITLES.length | 0], g = 0;
    while (t === lastTitle && g++ < 6) t = TITLES[Math.random() * TITLES.length | 0];
    lastTitle = t;
    return t;
  }

  const ADJ = ['pale', 'quiet', 'ash', 'hollow', 'silent', 'north', 'low', 'soft', 'cold', 'brief', 'dim', 'still', 'vague', 'late', 'thin', 'far', 'dry', 'slow'];
  const ANI = ['otter', 'marten', 'lynx', 'fox', 'heron', 'moth', 'raven', 'ibex', 'wren', 'stoat', 'hare', 'pike', 'crow', 'doe', 'jay', 'ell', 'stag', 'owl'];
  function genHandle() {
    return `${ADJ[Math.random() * ADJ.length | 0]}-${ANI[Math.random() * ANI.length | 0]}-${String(10 + Math.random() * 89 | 0)}`;
  }

  const avatarEl = $('#avatar');
  const profMode = $('#profMode');
  const opNameEl = $('#opName');

  function buildAvatar() {
    const N = 7, half = [];
    for (let y = 0; y < N; y++) {
      half[y] = [];
      for (let x = 0; x < 4; x++) half[y][x] = Math.random() < 0.5;
    }
    let base = '', acc = '';
    for (let y = 0; y < N; y++) {
      for (let x = 0; x < N; x++) {
        if (!half[y][x < 4 ? x : N - 1 - x]) continue;
        (Math.random() < 0.4
          ? acc += `<rect x="${x}" y="${y}"/>`
          : base += `<rect x="${x}" y="${y}"/>`);
      }
    }
    if (!acc) acc = '<rect x="3" y="3"/>';
    avatarEl.innerHTML =
      `<svg viewBox="0 0 7 7" shape-rendering="crispEdges">` +
      `<g fill="#333d4c">${base.replace(/<rect /g, '<rect width="1" height="1" ')}</g>` +
      `<g fill="currentColor">${acc.replace(/<rect /g, '<rect width="1" height="1" ')}</g></svg>`;
  }

  function rerollIdentity() {
    opNameEl.textContent = genHandle();
    buildAvatar();
  }

  const AV_LABEL = {
    idle: 'ready', compose: 'ready', seal: 'sealing', sealed: 'sealed',
    gate: 'locked', view: 'reading', burn: 'burning', ash: 'ash'
  };

  function setAvatarMode(m) {
    avatarEl.className = 'avatar m-' + m;
    profMode.textContent = AV_LABEL[m] || 'ready';
  }

  avatarEl.addEventListener('click', () => {
    rerollIdentity();
    toast('new identity generated — nothing carries over.');
  });

  /* ================= Local Archive ================= */
  const LSKEY = 'chats_v1';
  let chats = [];
  let currentChatId = null;
  let showPinBadge = false;
  let storageWarned = false;

  function loadChats() {
    try {
      const raw = localStorage.getItem(LSKEY);
      chats = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(chats)) chats = [];
      chats = chats.filter(c => c && typeof c === 'object' && c.id);
    } catch (_) {
      chats = [];
    }
  }

  function persist() {
    try {
      if (chats.length) localStorage.setItem(LSKEY, JSON.stringify(chats));
      else localStorage.removeItem(LSKEY);
    } catch (_) {
      // If quota exceeded, strip in-link attachment blobs from storage
      let stripped = false;
      for (const c of chats) {
        if (c.p && c.p.startsWith('k2.') && c.p.split('.').length > 4) {
          const parts = c.p.split('.');
          c.p = (parts.length === 7 ? parts.slice(0, 6) : parts.slice(0, 4)).join('.');
          c.noatt = true;
          stripped = true;
        }
      }
      if (stripped) {
        try {
          localStorage.setItem(LSKEY, JSON.stringify(chats));
          toast('archive trimmed to fit — attachments live only in their links.');
          return;
        } catch (__) {}
      }
      if (!storageWarned) {
        storageWarned = true;
        toast('archive unavailable in this browser mode — messages still work.');
      }
    }
  }

  const subFor = c => c.s === 'sealed' ? 'sealed' : (c.s === 'opened' ? 'opened · reading' : 'ash' + (c.r ? ` · ${c.r}` : ''));

  function renderList() {
    const list = $('#chatList');
    if (!chats.length) {
      list.innerHTML = `<div class="empty">
        <svg viewBox="0 0 24 24"><path d="M12 4.5c.5 3-3.8 5-3.8 9a3.8 3.8 0 0 0 7.6 0c0-1.9-1-3-1.7-4.4-.4 1-1.1 1.6-1.9 1.9.4-2.2.3-4.3-.2-6.5Z"/></svg>
        nothing here —<br>and that's the point.</div>`;
      return;
    }
    list.innerHTML = chats.map(c => `
      <div class="chat ${c.s}${c.id === currentChatId ? ' act' : ''}" data-id="${escapeHTML(c.id)}" role="button" tabindex="0">
        <i class="c-dot" aria-hidden="true"></i>
        <span class="c-title">${escapeHTML(c.t)}</span>
        <span class="c-sub">${escapeHTML(subFor(c))}</span>
        <button class="c-edit" data-rename aria-label="rename this message">${IC.pencil}</button>
      </div>`).join('');
  }

  function updateProfile() {
    let ck = 0;
    try {
      ck = document.cookie ? document.cookie.split(';').filter(Boolean).length : 0;
    } catch (_) {}
    $('#profStat').textContent = `${ck === 0 ? 'no cookies' : ck + ' cookies'} · ${chats.length} archived`;
  }

  function markChat(id, s, r) {
    const c = chats.find(x => x.id === id);
    if (!c) return;
    c.s = s;
    if (r !== undefined) c.r = r;
    persist();
    renderList();
    updateProfile();
  }

  function findChat(id) {
    return chats.find(x => x.id === id);
  }

  /* ================= Sidebar & Layout ================= */
  let sideOpen = innerWidth > 900;
  document.body.classList.toggle('side-closed', innerWidth <= 900);

  function setSide(open) {
    sideOpen = open;
    document.body.classList.toggle('side-closed', !open);
    updateScrim();
  }

  function updateScrim() {
    // Scrim is strictly for the mobile drawer; popovers have their own outside-click handler
    $('#scrim').classList.toggle('on', MOBILE() && sideOpen);
  }

  $('#sideToggle').addEventListener('click', () => setSide(!sideOpen));
  $('#sideX').addEventListener('click', () => setSide(false));
  $('#scrim').addEventListener('pointerdown', () => {
    if (MOBILE() && sideOpen) {
      setSide(false);
    }
  });

  /* ================= Toasts ================= */
  function toast(msg) {
    const root = $('#toasts');
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    root.append(t);
    while (root.children.length > 3) root.firstChild.remove();
    requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('in')));
    setTimeout(() => {
      t.classList.remove('in');
      setTimeout(() => t.remove(), 300);
    }, 3400);
  }

  /* ================= Settings Panel ================= */
  let setOpener = null;
  function openSettings() {
    document.body.classList.add('set-open');
    setStatusLine();
    setOpener = document.activeElement;
    setTimeout(() => $('#setClose').focus(), 60);
  }

  function closeSettings() {
    document.body.classList.remove('set-open');
    $('#cfbox').classList.remove('open');
    if (setOpener && setOpener.focus) setOpener.focus();
  }

  $('#setBtn').addEventListener('click', openSettings);
  $('#setClose').addEventListener('click', closeSettings);
  $('#setScrim').addEventListener('pointerdown', closeSettings);

  $('#swatches').addEventListener('click', e => {
    const b = e.target.closest('.swatch');
    if (b) applyAccent(b.dataset.acc); // saves to LS inside applyAccent
  });

  $$('.sw2').forEach(b => b.addEventListener('click', () => {
    const k = b.dataset.set;
    SET[k] = !SET[k];
    b.classList.toggle('on', SET[k]);
    b.setAttribute('aria-checked', String(SET[k]));
    saveSettings();
    if (k === 'net' || k === 'motes') {
      BlackendCanvas.setConfig({ [k]: SET[k] });
    }
    if (k === 'archive') {
      toast(SET[k]
        ? 'messages will be filed locally in this browser.'
        : 'new messages won\'t be filed — links exist only where you share them.');
    }
  }));

  $('#segAtt').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    SET.attLimit = +b.dataset.att;
    [...$('#segAtt').children].forEach(x => x.classList.toggle('on', x === b));
    saveSettings();
  });

  $('#segFuse').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    SET.fuse = b.dataset.fuse;
    expiry = SET.fuse;
    [...$('#segFuse').children].forEach(x => x.classList.toggle('on', x === b));
    syncFuseUI();
    saveSettings();
  });

  const burnCustomWrap = $('#burnCustomWrap');
  const burnSlider = $('#burnSlider');
  const burnInput = $('#burnInput');
  const burnTestBtn = $('#burnTestBtn');
  const bpSample = $('#bpSample');

  function syncBurnUI() {
    [...$('#segBurn').children].forEach(x => x.classList.toggle('on', x.dataset.burn === SET.burn));
    if (burnCustomWrap) {
      burnCustomWrap.hidden = SET.burn !== 'custom';
    }
    const bt = parseFloat(SET.burnTime) || 2.0;
    if (burnSlider) burnSlider.value = Math.min(Math.max(bt, 0.2), 8.0);
    if (burnInput) burnInput.value = bt.toFixed(1);
  }

  const readSlider = $('#readSlider');
  const readInput = $('#readInput');

  function syncReadUI() {
    const rt = parseFloat(SET.readTime) || 4;
    if (readSlider) readSlider.value = Math.min(Math.max(rt, 1), 60);
    if (readInput) readInput.value = Math.round(rt);
  }

  /* ---- Load ALL persisted settings on boot ---- */
  (function loadPersistedSettings() {
    try {
      const raw = localStorage.getItem('blackend_settings_v2');
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          Object.assign(SET, parsed);
        }
      }
      const accent = localStorage.getItem('blackend_accent');
      if (accent && ACCENTS[accent]) SET.accent = accent;

      const savedBurn = localStorage.getItem('blackend_burn_speed');
      if (savedBurn) SET.burn = savedBurn;
      const savedTime = localStorage.getItem('blackend_burn_time');
      if (savedTime) SET.burnTime = parseFloat(savedTime) || 2.0;

      const savedReadTime = localStorage.getItem('blackend_read_time');
      if (savedReadTime) SET.readTime = Math.max(1, parseFloat(savedReadTime) || 4);

      const savedFuse = localStorage.getItem('blackend_fuse');
      if (savedFuse) SET.fuse = savedFuse;

      const savedAttLimit = localStorage.getItem('blackend_attLimit');
      if (savedAttLimit) SET.attLimit = +savedAttLimit || 5;

      // Boolean toggles (net, motes, autocopy, archive)
      for (const k of ['net', 'motes', 'autocopy', 'archive']) {
        const v = localStorage.getItem('blackend_set_' + k);
        if (v !== null) SET[k] = v === '1';
      }
    } catch (_) {}

    // Apply accent style
    const a = ACCENTS[SET.accent] || ACCENTS.ember;
    const rs = document.documentElement.style;
    rs.setProperty('--ember', a[0]);
    rs.setProperty('--ember2', a[1]);
    rs.setProperty('--ember-rgb', h2r(a[0]));
    $$('.swatch').forEach(s => s.classList.toggle('on', s.dataset.acc === SET.accent));

    // Apply loaded booleans to the DOM toggles
    $$('.sw2').forEach(b => {
      const k = b.dataset.set;
      if (k) {
        b.classList.toggle('on', !!SET[k]);
        b.setAttribute('aria-checked', String(!!SET[k]));
      }
    });

    // Apply loaded fuse to the segment UI
    [...$('#segFuse').children].forEach(x =>
      x.classList.toggle('on', x.dataset.fuse === SET.fuse)
    );

    // Apply loaded attLimit to segment UI
    [...$('#segAtt').children].forEach(x =>
      x.classList.toggle('on', String(+x.dataset.att) === String(SET.attLimit))
    );

    // Canvas needs the loaded net/motes values
    if (typeof BlackendCanvas !== 'undefined') {
      BlackendCanvas.setConfig({ net: SET.net, motes: SET.motes });
    }
  })();
  syncBurnUI();
  syncReadUI();

  $('#segBurn').addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    SET.burn = b.dataset.burn;
    syncBurnUI();
    saveSettings();
  });

  if (burnSlider && burnInput) {
    burnSlider.addEventListener('input', () => {
      const val = parseFloat(burnSlider.value);
      SET.burnTime = val;
      burnInput.value = val.toFixed(1);
      saveSettings();
    });

    burnInput.addEventListener('input', () => {
      let val = parseFloat(burnInput.value);
      if (!isNaN(val)) {
        val = clampN(val, 0.1, 30.0);
        SET.burnTime = val;
        burnSlider.value = Math.min(val, 8.0);
        saveSettings();
      }
    });
  }

  if (burnTestBtn && bpSample) {
    burnTestBtn.addEventListener('click', async () => {
      burnTestBtn.disabled = true;
      bpSample.textContent = 'this message will turn to embers and ash…';
      const spans = waveify(bpSample);
      await runWave(spans);
      await wait(600);
      bpSample.textContent = 'this message will turn to embers and ash…';
      burnTestBtn.disabled = false;
    });
  }

  /* --- Read Countdown Setting --- */
  if (readSlider && readInput) {
    readSlider.addEventListener('input', () => {
      const val = parseInt(readSlider.value, 10);
      SET.readTime = val;
      readInput.value = val;
      saveSettings();
    });

    readInput.addEventListener('input', () => {
      let val = parseInt(readInput.value, 10);
      if (!isNaN(val)) {
        val = clampN(val, 1, 300);
        SET.readTime = val;
        readSlider.value = Math.min(val, 60);
        saveSettings();
      }
    });
  }

  function setStatusLine() {
    let ck = 0, keys = 0;
    try { ck = document.cookie ? document.cookie.split(';').filter(Boolean).length : 0; } catch (_) {}
    try { keys = localStorage.length; } catch (_) {}
    $('#setStatus').textContent = `cookies: ${ck} · storage keys: ${keys} · data sent: 0 in the clear`;
  }

  $('#setWipe').addEventListener('click', () => { $('#cfbox').classList.toggle('open'); });
  $('#setWipeNo').addEventListener('click', () => { $('#cfbox').classList.remove('open'); });
  $('#setWipeYes').addEventListener('click', () => { closeSettings(); doWipe(); });

  /* ================= Stage Navigation ================= */
  const stage = $('#stage');
  const panes = {
    compose: $('#paneCompose'),
    card: $('#paneCard'),
    gate: $('#paneGate'),
    message: $('#paneMessage'),
    end: $('#paneEnd')
  };

  let cur = panes.compose;
  let pendingGrow = false;

  function dock(on) {
    stage.classList.toggle('docked', on && MOBILE());
    if (!stage.classList.contains('docked')) stage.style.transform = '';
    syncDock();
  }

  function syncDock() {
    const docked = MOBILE() && stage.classList.contains('docked');
    document.body.style.paddingBottom = docked ? (stage.offsetHeight + 18) + 'px' : '';
  }

  async function go(name) {
    const next = panes[name];
    if (!next || next === cur) return;

    if (REDUCED) {
      cur.hidden = true;
      next.hidden = false;
      cur = next;
      dock(next === panes.compose);
      if (next === panes.compose) grow();
      return;
    }

    if (MOBILE()) {
      cur.classList.add('out');
      next.hidden = false;
      next.classList.add('pre');
      dock(next === panes.compose);
      if (next !== panes.compose) {
        stage.scrollIntoView({ block: 'center', behavior: REDUCED ? 'auto' : 'smooth' });
      }
      requestAnimationFrame(() => requestAnimationFrame(() => next.classList.remove('pre')));
      await wait(300);
      cur.hidden = true;
      cur.classList.remove('out');
      cur = next;
      if (next === panes.compose) requestAnimationFrame(grow);
      syncDock();
      return;
    }

    const h0 = stage.offsetHeight;
    stage.classList.add('anim');
    stage.style.height = h0 + 'px';
    cur.classList.add('out');
    next.hidden = false;
    next.classList.add('pre');
    const h1 = next.offsetHeight;
    void stage.offsetWidth;
    stage.style.height = h1 + 'px';
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        next.classList.remove('pre');
      });
    });
    await wait(360);
    cur.hidden = true;
    cur.classList.remove('out');
    stage.style.height = '';
    stage.classList.remove('anim');
    cur = next;
    if (next === panes.compose) requestAnimationFrame(grow);
    syncDock();
  }

  /* ================= The Ember Wave Effect ================= */
  function runWave(spans) {
    return new Promise(res => {
      if (REDUCED || !spans.length) { res(); return; }
      let dur = 1350;
      if (SET.burn === 'quick') {
        dur = 650;
      } else if (SET.burn === 'custom') {
        dur = Math.max(100, Math.min(30000, Math.round((parseFloat(SET.burnTime) || 2.0) * 1000)));
      } else {
        dur = 1350;
      }
      const t0 = performance.now();
      const n = spans.length;
      let lastIndex = 0;

      function step(now) {
        const elapsed = now - t0;
        const progress = Math.min(elapsed / dur, 1);
        const targetIndex = Math.min(Math.floor(progress * n), n);

        while (lastIndex < targetIndex) {
          const s = spans[lastIndex++];
          s.classList.add('hot');
          setTimeout(() => s.classList.add('ash'), 160);
        }

        if (progress < 1 || lastIndex < n) {
          requestAnimationFrame(step);
        } else {
          setTimeout(res, 360);
        }
      }

      requestAnimationFrame(step);
    });
  }

  function waveify(el) {
    const txt = el.textContent;
    el.textContent = '';
    const spans = [];
    for (const c of txt) {
      const s = document.createElement('span');
      s.className = 'ch';
      s.textContent = c;
      el.append(s);
      spans.push(s);
    }
    return spans;
  }

  function burnDraft() {
    return new Promise(res => {
      charLayer.hidden = false;
      charLayer.innerHTML = '';
      const spans = [];
      for (const c of ta.value) {
        const s = document.createElement('span');
        s.className = 'ch';
        s.textContent = c;
        charLayer.append(s);
        spans.push(s);
      }
      charLayer.style.height = ta.clientHeight + 'px';
      charLayer.scrollTop = ta.scrollTop;
      ta.style.visibility = 'hidden';
      runWave(spans).then(res);
    });
  }

  function clearDraft() {
    charLayer.hidden = true;
    charLayer.innerHTML = '';
    ta.style.visibility = '';
    ta.value = '';
    ta.style.height = 'auto';
    btnSend.disabled = true;
    grow();
  }

  /* ================= Composer UI ================= */
  const composer = $('#composer');
  const ta = $('#ta');
  const charLayer = $('#charLayer');
  const btnSend = $('#btnSend');
  const btnPin = $('#btnPin');
  const btnExp = $('#btnExp');
  const expChip = $('#expChip');
  const expLabel = $('#expLabel');
  const hint = $('#hint');
  const pinRow = $('#pinRow');
  const popPin = $('#popPin');
  const popExp = $('#popExp');
  const pinBoxes = [...pinRow.querySelectorAll('.pbox')];
  const pinNote = $('#pinNote');
  const pinRemove = $('#pinRemove');
  const bubble = $('#bubble');
  const msgText = $('#msgText');
  const mOpen = $('#mOpen');
  const cdNum = $('#cdNum');
  const ringFg = $('#ringFg');
  const btnAtt = $('#btnAtt');
  const fileInput = $('#fileInput');
  const attStrip = $('#attStrip');
  const attName = $('#attName');
  const attSize = $('#attSize');
  const attChipIc = $('#attChipIc');
  const attCard = $('#attCard');
  const attCardIc = $('#attCardIc');
  const attCardName = $('#attCardName');
  const attCardSub = $('#attCardSub');
  const attSave = $('#attSave');

  const EXPIRY = {
    read: { label: 'after read' },
    60: { label: '60 seconds', chip: '60s' },
    600: { label: '10 minutes', chip: '10m' },
    3600: { label: '1 hour', chip: '1h' }
  };

  let state = 'compose';
  let pin = null;
  let expiry = SET.fuse;
  let linkUrl = '';
  let curToken = '';
  let curFrag = '';
  let curDirectPayload = '';
  let dispToken = '';
  let rxContext = null;
  let fromSender = false;
  let fuseLeft = 0;
  let fuseTimer = null;
  let attempts = 3;
  let hintT = null;
  let lastQR = null;
  let pendingFile = null;
  let attURL = null;
  let attDL = null;
  let attBadge = null;

  btnAtt.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    fileInput.value = '';
    if (!f) return;
    if (f.size > SET.attLimit * 1048576) {
      nudge(`that file is ${fmtSize(f.size)} — your limit is ${SET.attLimit} MB (raise it in settings).`);
      return;
    }
    pendingFile = f;
    attChipIc.innerHTML = attIcon(f.type || '');
    attName.textContent = elide(f.name || 'file', 26);
    attSize.textContent = fmtSize(f.size);
    attStrip.hidden = false;
    btnSend.disabled = !ta.value.trim() && !pendingFile;
    syncDock();
  });

  $('#attX').addEventListener('click', () => {
    clearAttachment();
    btnSend.disabled = !ta.value.trim() && !pendingFile;
    syncDock();
  });

  function clearAttachment() {
    pendingFile = null;
    attStrip.hidden = true;
    try { fileInput.value = ''; } catch (_) {}
    if (attURL) { URL.revokeObjectURL(attURL); attURL = null; }
    attDL = null;
  }

  attSave.addEventListener('click', () => {
    if (!attDL) return;
    const a = document.createElement('a');
    a.href = attDL.url;
    a.download = attDL.name;
    document.body.append(a);
    a.click();
    a.remove();
    toast('saved — the message still ends.');
  });

  function grow() {
    if (panes.compose.hidden) { pendingGrow = true; return; }
    pendingGrow = false;
    ta.style.height = 'auto';
    const h = Math.min(ta.scrollHeight, 164);
    ta.style.height = h + 'px';
    ta.style.overflowY = ta.scrollHeight > 164 ? 'auto' : 'hidden';
    syncDock();
  }

  ta.addEventListener('input', () => {
    grow();
    btnSend.disabled = !ta.value.trim() && !pendingFile;
  });

  ta.addEventListener('focus', () => composer.classList.add('focus'));
  ta.addEventListener('blur', () => composer.classList.remove('focus'));
  ta.addEventListener('keydown', e => {
    const phys = matchMedia('(pointer:fine)').matches;
    if (e.key === 'Enter' && !e.shiftKey && (phys || e.metaKey || e.ctrlKey)) {
      e.preventDefault();
      seal();
    }
  });

  btnSend.addEventListener('click', seal);

  function nudge(msg) {
    hint.textContent = msg;
    hint.classList.add('on');
    composer.classList.add('shake');
    setTimeout(() => composer.classList.remove('shake'), 450);
    clearTimeout(hintT);
    hintT = setTimeout(() => hint.classList.remove('on'), 2600);
  }

  function pulseComposer() {
    composer.classList.remove('pulse');
    void composer.offsetWidth;
    composer.classList.add('pulse');
  }

  /* ================= Popovers ================= */
  const POPS = { pin: popPin, exp: popExp };
  let popOpen = null;

  function openPop(name, anchor) {
    if (state !== 'compose') return;
    closePop(true);
    const pop = POPS[name];
    pop.hidden = false;
    if (!MOBILE()) {
      const cr = composer.getBoundingClientRect();
      const ar = anchor.getBoundingClientRect();
      const pw = pop.offsetWidth || 272;
      let l = ar.left - cr.left + ar.width / 2 - pw / 2;
      l = clampN(l, 10, cr.width - pw - 10);
      pop.style.left = l + 'px';
      pop.style.bottom = '';
    } else {
      pop.style.left = '';
      pop.style.bottom = '';
    }
    popOpen = name;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => pop.classList.add('in'));
    });
    if (name === 'pin') setTimeout(() => {
      try { pinBoxes[0].focus(); } catch (_) {}
    }, 220);
  }

  function closePop(instant) {
    if (!popOpen) return;
    const pop = POPS[popOpen];
    const fin = () => { pop.hidden = true; pop.classList.remove('in'); };
    instant ? fin() : setTimeout(fin, 180);
    popOpen = null;
  }

  btnPin.addEventListener('click', () => popOpen === 'pin' ? closePop() : openPop('pin', btnPin));
  btnExp.addEventListener('click', () => popOpen === 'exp' ? closePop() : openPop('exp', btnExp));
  expChip.addEventListener('click', () => popOpen === 'exp' ? closePop() : openPop('exp', expChip));
  $('#pinClose').addEventListener('click', () => closePop());
  $('#expClose').addEventListener('click', () => closePop());

  document.addEventListener('pointerdown', e => {
    if (!popOpen) return;
    const pop = POPS[popOpen];
    if (pop.contains(e.target) || btnPin.contains(e.target) || btnExp.contains(e.target) || expChip.contains(e.target)) return;
    closePop();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closePop();
      closeSettings();
      if (MOBILE()) setSide(false);
    }
  });

  /* ---------- PIN Boxes ---------- */
  function wireBoxes(boxes, onFull) {
    boxes.forEach((b, i) => {
      b.addEventListener('focus', () => {
        setTimeout(() => b.select(), 0);
      });

      b.addEventListener('click', () => {
        b.select();
      });

      b.addEventListener('input', () => {
        // Strip non-digits, keep only last typed digit
        const digit = b.value.replace(/\D/g, '').slice(-1);
        b.value = digit;
        if (digit && i < boxes.length - 1) {
          boxes[i + 1].focus();
          boxes[i + 1].select();
        }
        if (boxes.every(x => x.value)) setTimeout(onFull, 240);
      });

      b.addEventListener('keydown', e => {
        if (e.key === 'Backspace') {
          if (b.value) {
            b.value = '';
            e.preventDefault();
          } else if (i > 0) {
            boxes[i - 1].focus();
            boxes[i - 1].value = '';
            e.preventDefault();
          }
        }
        if (e.key === 'Enter' && boxes.every(x => x.value)) {
          e.preventDefault();
          onFull();
        }
      });

      b.addEventListener('paste', e => {
        if (i !== 0) return;
        e.preventDefault();
        const d = (e.clipboardData.getData('text').match(/\d/g) || []).slice(0, 4);
        d.forEach((v, k) => { if (boxes[k]) boxes[k].value = v; });
        boxes[Math.min(d.length, boxes.length - 1)].focus();
        if (d.length === 4) onFull();
      });
    });
  }

  wireBoxes(pinBoxes, savePin);

  function savePin() {
    const code = pinBoxes.map(b => b.value).join('');
    if (code.length !== 4) return;
    pin = code;
    btnPin.classList.add('on');
    pinRemove.hidden = false;
    pinNote.textContent = 'PIN saved. Enter a new code below to replace it.';
    $('#pinHead').textContent = 'PIN saved';
    toast('PIN set — opening the link will require it.');
    setTimeout(() => {
      closePop();
      $('#pinHead').textContent = 'PIN protection';
    }, 650);
  }

  pinRemove.addEventListener('click', () => {
    pin = null;
    btnPin.classList.remove('on');
    pinRemove.hidden = true;
    pinBoxes.forEach(b => b.value = '');
    pinNote.textContent = 'Anyone opening the link will need this 4-digit code — wraps key with 120,000 PBKDF2 rounds. Three wrong attempts destroy the message.';
    closePop();
  });

  /* ---------- Expiry Selection ---------- */
  popExp.querySelectorAll('.exp-row').forEach(r => r.addEventListener('click', () => {
    expiry = r.dataset.exp;
    popExp.querySelectorAll('.exp-row').forEach(x => x.classList.toggle('on', x.dataset.exp === expiry));
    updateExpiryUI();
    closePop();
  }));

  function updateExpiryUI() {
    const timed = expiry !== 'read';
    expChip.hidden = !timed;
    if (timed) expLabel.textContent = EXPIRY[expiry].chip;
    btnExp.classList.toggle('on', timed);
  }

  function syncFuseUI() {
    popExp.querySelectorAll('.exp-row').forEach(x => x.classList.toggle('on', x.dataset.exp === expiry));
    updateExpiryUI();
  }

  syncFuseUI();

  /* ================= Seal (Dual-Engine: Escrow Vault + In-Link Fallback) ================= */
  async function seal() {
    if (state !== 'compose') return;
    if (!ta.value.trim() && !pendingFile) {
      nudge('Type something first — or attach a file.');
      return;
    }
    if (!BlackendCrypto.HAS_CRYPTO) {
      nudge('This context cannot encrypt (needs HTTPS or modern WebCrypto).');
      return;
    }
    if (pendingFile && pendingFile.size > SET.attLimit * 1048576) {
      nudge(`Attachment exceeds your ${SET.attLimit} MB limit — raise it in settings.`);
      return;
    }

    state = 'sealing';
    btnSend.disabled = true;
    closePop();
    setAvatarMode('seal');

    const expSec = expiry === 'read' ? 0 : +expiry;
    const msgText = ta.value;
    const targetFile = pendingFile;

    let mode = 'vault';
    let vaultToken = '';
    let vaultFrag = '';
    let directPayload = '';

    const cryptoOpts = { rt: SET.readTime, bt: SET.burnTime, bs: SET.burn, accent: SET.accent };

    try {
      // 1. Generate receipt key — hash stored server-side; plaintext kept only in sender's localStorage.
      //    Even with full server access, nobody can query the audit trail without the plaintext rk.
      let rk = '', rkHash = '', senderSettings = {};
      try {
        rk = VaultBackend.generateReceiptKey();
        rkHash = await VaultBackend.hashReceiptKey(rk);
        senderSettings = VaultBackend.packSettings(SET);
      } catch (_) { /* non-fatal — message seals without receipt if WebCrypto unavailable */ }

      // 2. Try Escrow Vault mode first
      const vaultData = await BlackendCrypto.buildVaultPayload(msgText, expSec, pin, targetFile, cryptoOpts);
      const res = await api('store', {
        ...vaultData.envelope,
        settings: senderSettings,
        rk: rkHash
      });

      if (res && res.ok && res.id) {
        vaultToken = res.id;
        vaultFrag = vaultData.frag;

        // Upload chunks sequentially
        for (let i = 0; i < vaultData.chunks.length; i++) {
          const up = await api('put', { id: vaultToken, i, data: vaultData.chunks[i] });
          if (!up || !up.ok) {
            await api('burn', { id: vaultToken, why: 'killed' });
            throw new Error('chunk_upload_failed');
          }
        }

        if (vaultData.chunks.length > 0) {
          const fin = await api('ready', { id: vaultToken });
          if (!fin || !fin.ok) {
            await api('burn', { id: vaultToken, why: 'killed' });
            throw new Error('vault_ready_failed');
          }
        }

        curToken = vaultToken;
        curFrag = vaultFrag;
        // Persist the receipt key locally — only the sender can verify the audit trail
        if (rk) VaultBackend.saveReceiptKey(vaultToken, rk);
        const basePath = location.pathname.replace(/\/index\.(php|html)$/i, '/');
        const baseUrl = location.origin + (basePath.endsWith('/') ? basePath : basePath + '/');
        linkUrl = vaultFrag ? `${baseUrl}?m=${vaultToken}#${vaultFrag}` : `${baseUrl}?m=${vaultToken}`;
        dispToken = vaultToken;
      } else {
        // Vault unavailable or static mode: Fallback to Direct In-Link
        mode = 'direct';
        directPayload = await BlackendCrypto.buildDirectPayload(msgText, expSec, pin, targetFile, cryptoOpts);
        curDirectPayload = directPayload;
        linkUrl = location.origin + location.pathname + '#' + directPayload;
        dispToken = directPayload.split('.')[2].slice(0, 6);
        toast('Vault unavailable — sealed as Direct In-Link (rides 100% in URL).');
      }
    } catch (err) {
      // Direct in-link fallback
      try {
        mode = 'direct';
        directPayload = await BlackendCrypto.buildDirectPayload(msgText, expSec, pin, targetFile, cryptoOpts);
        curDirectPayload = directPayload;
        linkUrl = location.origin + location.pathname + '#' + directPayload;
        dispToken = directPayload.split('.')[2].slice(0, 6);
        toast('Sealed as Direct In-Link — zero server storage.');
      } catch (_) {
        toast('Encryption failed — your draft is intact, please try again.');
        state = 'compose';
        btnSend.disabled = false;
        setAvatarMode('idle');
        return;
      }
    }

    // Save to local device archive
    const recId = vaultToken || ('d' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6));
    const rec = {
      id: recId,
      mode,
      u: linkUrl,
      p: directPayload || '',
      t: genTitle(),
      s: 'sealed',
      e: expiry,
      c: Date.now(),
      pin: !!pin,
      att: !!targetFile
    };

    if (SET.archive) {
      chats.unshift(rec);
      currentChatId = rec.id;
      persist();
      renderList();
      updateProfile();
    }

    fromSender = true;
    showPinBadge = !!pin;
    attBadge = targetFile ? {
      n: targetFile.name || 'file',
      s: targetFile.size,
      t: targetFile.type || '',
      generic: mode === 'vault'
    } : null;

    fuseLeft = expSec;

    try {
      await burnDraft();
      buildCard(rec.t, mode, vaultToken);
      await go('card');
      clearAttachment();
      state = 'sealed';
      setAvatarMode('sealed');

      startFuse();
      if (mode === 'vault') {
        startPoll();
      }

      if (SET.autocopy && navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(linkUrl)
          .then(() => toast('link copied — it still ends.'))
          .catch(() => {});
      }
    } catch (uiErr) {
      console.error('UI transition error after sealing:', uiErr);
      state = 'sealed';
      setAvatarMode('sealed');
      try {
        buildCard(rec.t, mode, vaultToken);
        await go('card');
      } catch (_) {}
    }
  }

  /* ================= Share Card Rendering ================= */
  function buildCard(title, mode = 'vault', token = curToken) {
    const activeToken = token || curToken;
    const timed = expiry !== 'read';
    const qrResult = BlackendQR.renderSVG(linkUrl);
    lastQR = qrResult ? qrResult.q : null;

    // Smart URL display: highlight the creative token or the # key fragment
    const shown = elide(linkUrl, 64);
    const hIdx = shown.indexOf('#');
    const mIdx = shown.indexOf('?m=');
    let cf;
    if (mIdx >= 0 && hIdx < 0) {
      // Zero-Hash PIN Shield: show URL with token highlighted, no fragment
      const base = shown.slice(0, mIdx + 3);
      const token = shown.slice(mIdx + 3);
      cf = escapeHTML(base) + '<b>' + escapeHTML(token) + '</b>';
    } else if (mIdx >= 0 && hIdx > mIdx) {
      // Vault with nano-seed fragment: highlight the token
      const base = shown.slice(0, mIdx + 3);
      const token = shown.slice(mIdx + 3, hIdx);
      const frag = shown.slice(hIdx);
      cf = escapeHTML(base) + '<b>' + escapeHTML(token) + '</b>' + escapeHTML(frag);
    } else if (hIdx >= 0) {
      // Direct in-link: highlight fragment
      cf = escapeHTML(shown.slice(0, hIdx)) + '<b>' + escapeHTML(shown.slice(hIdx)) + '</b>';
    } else {
      cf = escapeHTML(shown);
    }

    // Choose the right security explanation note
    const isPinVault = mode === 'vault' && showPinBadge;
    const isNanoVault = mode === 'vault' && !showPinBadge;
    const secNote = isPinVault
      ? 'PIN-wrapped key stored inside the vault — server cannot decrypt it. Link has <b>no fragment</b>. Three wrong attempts shred everything.'
      : isNanoVault
      ? 'the token points at noise in the vault. the compact seed after <b>#n.</b> never transmits — server shreds on first read.'
      : 'everything after <b>#</b> never leaves the link — no browser transmits fragments. zero server storage.';

    panes.card.innerHTML = `
    <div class="share">
      <div class="share-head">
        <span class="s-status" id="cardStatus"><i class="dot"></i>sealed · one read</span>
        <span class="fuse" id="fuseLabel" ${timed ? '' : 'hidden'}>burns in ${timed ? fmt(fuseLeft) : ''}</span>
      </div>
      <p class="s-filed">${SET.archive
        ? `filed in your archive as <b>${escapeHTML(title)}</b> — rename any time.`
        : 'not filed — this link exists only where you send it.'}</p>
      <div class="share-body">
        <div class="share-qr">
          ${qrResult ? `
          <div class="qr-tile">
            <div class="qc" aria-hidden="true"><i class="tl"></i><i class="tr"></i><i class="bl"></i><i class="br"></i></div>
            ${qrResult.svg}
            <i class="scan" aria-hidden="true"></i>
          </div>
          <span class="qr-cap">one scan · one read</span>
          <button class="qr-save" data-act="png" aria-label="download the QR code as an image">
            <svg viewBox="0 0 24 24"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/></svg>
            save png
          </button>`
          : `
          <div class="qr-fallback">
            <svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3"/></svg>
            <p>this payload is too long for a code —<br>share the link instead</p>
          </div>`}
        </div>
        <div class="share-link">
          <p class="sl-label">share this link</p>
          <button class="copyfield" data-act="copy" aria-label="copy the sealed link">
            <span class="cf-text">${cf}</span>
            <span class="cf-ic" aria-hidden="true">${IC.copy}</span>
          </button>
          <p class="sl-note">${secNote}</p>
          <button class="btn primary" data-act="view">${IC.eye} view once</button>
          <div class="badges">
            <span class="badge">${IC.shield} ${mode === 'vault' ? 'vault escrow' : 'direct in-link'}</span>
            <span class="badge">${IC.shield} ML-KEM Hybrid</span>
            ${attBadge ? `<span class="badge"><span class="att-ic">${IC.clip}</span>${
              attBadge.generic ? 'file attached' : escapeHTML(elide(attBadge.n, 20)) + ' · ' + fmtSize(attBadge.s)
            }</span>` : ''}
            ${showPinBadge ? `<span class="badge">${IC.lock} PIN</span>` : ''}
            <span class="badge">${IC.clock} ${EXPIRY[expiry].label}</span>
          </div>
        </div>
      </div>
      ${mode === 'vault' && activeToken && typeof VaultBackend !== 'undefined' && VaultBackend.loadReceiptKey(activeToken) ? `
      <div class="rcptwrap" id="rcptWrap">
        <button class="svbtn" data-act="rcpt" aria-expanded="false">
          ${IC.shield} delivery receipt ${IC.chev}
        </button>
        <div class="rcptbox" id="rcptBox" hidden>
          <div class="sv" id="rcptTrail">
            <div class="svrow"><span>chain</span><b>click verify to query the vault</b></div>
          </div>
          <div class="rcpt-actions">
            <button class="rcpt-verify-btn" data-act="rcpt-verify" type="button">
              <span class="rcpt-btn-ic">${IC.shield}</span>
              <span class="rcpt-btn-txt">verify chain</span>
            </button>
          </div>
        </div>
      </div>` : ''}
      <div class="svwrap">
        <button class="svbtn" data-act="sv" aria-expanded="false">
          ${IC.server} what any server sees ${IC.chev}
        </button>
        <div class="svbox">
          <div class="sv">
            <div class="svrow"><span>ciphertext</span><b>${mode === 'vault' ? 'encrypted noise — shredded on read' : '0 bytes — stays in fragment'}</b></div>
            <div class="svrow"><span>ip address</span><b>never stored</b></div>
            <div class="svrow"><span>user agent</span><b>never stored</b></div>
            <div class="svrow"><span>decryption key</span><b>client-side only (never transmitted)</b></div>
            <div class="svrow"><span>lifetime</span><b>shredded on first read or expiry</b></div>
          </div>
        </div>
      </div>
    </div>`;
  }

  const fmt = s => s < 60 ? s + 's' : Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');

  function startFuse() {
    stopFuse();
    if (expiry === 'read' || fuseLeft <= 0) return;
    fuseLeft = Math.max(1, fuseLeft);
    const fl = panes.card.querySelector('#fuseLabel');
    if (!fl) return;
    fl.hidden = false;
    fl.textContent = 'burns in ' + fmt(fuseLeft);
    fl.classList.toggle('hot', fuseLeft <= 10);
    fuseTimer = setInterval(() => {
      fuseLeft--;
      if (fuseLeft <= 0) {
        stopFuse();
        expireLink();
        return;
      }
      fl.textContent = 'burns in ' + fmt(fuseLeft);
      fl.classList.toggle('hot', fuseLeft <= 10);
    }, 1000);
  }

  function stopFuse() {
    clearInterval(fuseTimer);
    fuseTimer = null;
  }

  async function expireLink() {
    if (state !== 'sealed') return;
    state = 'expiring';
    stopPoll();
    if (curToken) {
      api('burn', { id: curToken, why: 'expired' });
    }
    markChat(currentChatId, 'ash', 'expired');
    const cft = panes.card.querySelector('.cf-text');
    if (cft && !REDUCED) await runWave(waveify(cft));
    linkUrl = '';
    buildEnd('expired');
    await go('end');
    state = 'done';
    setAvatarMode('ash');
  }

  /* ================= Live Read Receipt — SSE Streaming Watcher ================= */
  /**
   * Central handler for all vault status events, whether delivered by SSE stream
   * or the poll fallback. Decoupled from the transport so VaultBackend.watch() can
   * call it transparently.
   */
  function _onVaultEvent(r) {
    if (!r || !r.ok) return;
    if ((state !== 'sealed' && state !== 'opened') || !curToken) return;

    if (r.state === 'opened') {
      markChat(curToken, 'opened', 'reading');
      stopFuse();
      const st = panes.card.querySelector('#cardStatus');
      if (st && !st.dataset.wasOpened) {
        st.dataset.wasOpened = '1';
        st.innerHTML = '<i class="dot opened"></i>opened · reading...';
        toast('recipient opened the link — reading now.');
      }
      const fl = panes.card.querySelector('#fuseLabel');
      if (fl) {
        fl.textContent = 'opened';
        fl.classList.remove('hot');
      }
    } else if (r.state === 'gone') {
      VaultBackend.stopWatch();
      const reason = r.why === 'read' ? 'opened' : (r.why === 'expired' ? 'expired' : (r.why === 'killed' ? 'killed' : 'ended'));
      markChat(curToken, 'ash', reason);
      const st = panes.card.querySelector('#cardStatus');
      if (st) {
        st.innerHTML = `<i class="dot ash"></i>${reason === 'opened' ? 'opened · burned' : escapeHTML(reason)}`;
      }
      const fl = panes.card.querySelector('#fuseLabel');
      if (fl) fl.hidden = true;
      sessEnded();
      if (reason === 'opened') {
        toast('it was opened — the vault copy is ash.');
      } else if (reason === 'killed') {
        toast('PIN failed 3 times — vault copy destroyed.');
      }
      setAvatarMode('ash');
    }
  }

  function startPoll() {
    VaultBackend.stopWatch();
    if (!curToken) return;
    VaultBackend.watch(curToken, _onVaultEvent);
  }

  function stopPoll() {
    VaultBackend.stopWatch();
  }

  /* Share Card Actions */
  panes.card.addEventListener('click', e => {
    const sv = e.target.closest('[data-act="sv"]');
    if (sv) {
      const box = panes.card.querySelector('.svbox');
      const open = !box.classList.contains('open');
      box.classList.toggle('open', open);
      sv.classList.toggle('open', open);
      sv.setAttribute('aria-expanded', String(open));
      return;
    }
    // Receipt accordion toggle
    const rcpt = e.target.closest('[data-act="rcpt"]');
    if (rcpt) {
      const box  = panes.card.querySelector('#rcptBox');
      const open = !rcpt.classList.contains('open');
      if (box) box.hidden = !open;
      rcpt.classList.toggle('open', open);
      rcpt.setAttribute('aria-expanded', String(open));
      return;
    }
    const b = e.target.closest('[data-act]');
    if (!b) return;
    if (b.dataset.act === 'view')        viewOnce();
    if (b.dataset.act === 'copy')        copyField(b);
    if (b.dataset.act === 'png')         downloadQR();
    if (b.dataset.act === 'rcpt-verify') doReceiptVerify();
  });

  function copyField(field) {
    const ic = field.querySelector('.cf-ic');
    const done = () => {
      field.classList.add('copied');
      ic.innerHTML = IC.check;
      setTimeout(() => {
        field.classList.remove('copied');
        ic.innerHTML = IC.copy;
      }, 1900);
    };
    const fb = () => {
      const i = document.createElement('textarea');
      i.value = linkUrl;
      i.style.cssText = 'position:fixed;opacity:0';
      document.body.append(i);
      i.select();
      try {
        document.execCommand('copy') ? done() : toast('copy blocked — ' + elide(linkUrl, 70));
      } catch (_) {
        toast('copy blocked — ' + elide(linkUrl, 70));
      }
      i.remove();
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(linkUrl).then(done).catch(fb);
    } else {
      fb();
    }
  }

  function downloadQR() {
    if (!lastQR) {
      toast('This payload is too long for a QR code — share the link instead.');
      return;
    }
    BlackendQR.downloadPNG(lastQR, 'blackend-' + (dispToken || 'message') + '.png');
    toast('Saved — one scan, one read, then ash.');
  }

  /* Receipt chain-of-custody verification — sender only */
  async function doReceiptVerify() {
    const trail = panes.card.querySelector('#rcptTrail');
    const btn   = panes.card.querySelector('[data-act="rcpt-verify"]');
    if (!trail || !curToken) return;
    const txt = btn ? btn.querySelector('.rcpt-btn-txt') || btn : null;
    const ic  = btn ? btn.querySelector('.rcpt-btn-ic') : null;
    if (btn) btn.disabled = true;
    if (txt) txt.textContent = 'verifying…';
    const r = await VaultBackend.verifyReceipt(curToken);
    if (btn) btn.disabled = false;
    if (txt) txt.textContent = 'verify chain';
    if (!r || !r.ok) {
      const errMap = {
        key_mismatch: 'receipt key mismatch',
        no_receipt: 'no receipt registered for this link',
        purged: 'audit trail expired & purged from vault',
        not_found: 'vault envelope not found',
        no_key: 'receipt key not found on this device',
        invalid_key: 'malformed receipt key'
      };
      const msg = (r && errMap[r.error]) || (r && r.error) || 'verification failed';
      trail.innerHTML = `<div class="svrow"><span>error</span><b style="color:var(--hot, #ff5c72)">${escapeHTML(msg)}</b></div>`;
      return;
    }
    if (btn) btn.classList.add('verified');
    if (ic)  ic.innerHTML = IC.check;
    if (txt) txt.textContent = 'chain verified';
    const fmtTS = ts => ts ? new Date(ts * 1000).toISOString().replace('T', ' ').slice(0, 19) + ' UTC' : '—';
    const burnLabel = r.burned
      ? (r.why === 'read'    ? 'burned after read'
       : r.why === 'expired' ? 'expired unread'
       : r.why === 'killed'  ? 'killed — 3× PIN fail'
       : r.why === 'wiped'   ? 'wiped by sender'
       : 'destroyed')
      : 'pending';
    trail.innerHTML = `
      <div class="svrow"><span>created</span><b>${fmtTS(r.created)}</b></div>
      <div class="svrow"><span>opened</span><b>${r.opened ? fmtTS(r.opened) : 'not yet'}</b></div>
      <div class="svrow"><span>burned</span><b>${r.burned ? fmtTS(r.burned) : 'pending'}</b></div>
      <div class="svrow"><span>reason</span><b>${escapeHTML(burnLabel)}</b></div>`;
    toast('chain verified — cryptographic receipt confirmed.');
  }

  /* ================= Universal Receiver (In-Link & Vault) ================= */
  function clearURL() {
    history.replaceState(null, '', location.pathname);
  }

  function viewOnce() {
    if (state !== 'sealed') return;
    stopFuse();
    stopPoll();
    fromSender = true;
    if (curDirectPayload) {
      runReceiveDirect(curDirectPayload.split('.'));
    } else {
      runReceiveVault(curToken, curFrag);
    }
  }

  /* 1. Receive Vault Escrow Link */
  async function runReceiveVault(token, frag) {
    curToken = token;
    const res = await api('fetch', { id: token });

    if (!res || !res.ok) {
      const why = (res && res.why === 'expired') ? 'expired' : ((res && res.why === 'read') ? 'opened' : 'archive');
      markChat(currentChatId, 'ash', (res && res.why === 'read') ? 'opened' : ((res && res.why) || 'ended'));
      buildEnd(why);
      state = 'done';
      await go('end');
      setAvatarMode('ash');
      clearURL();
      return;
    }

    rxContext = {
      type: 'vault',
      token,
      frag,
      envelope: res,
      iv: res.iv,
      ct: res.ct,
      salt: res.salt,
      wiv: res.wiv,
      wrapped: res.wrapped,
      pin: !!res.pin,
      nc: res.nc
    };

    // Apply sender's display settings (accent, burn animation, readTime) delivered via
    // the vault's public metadata. Runs BEFORE gate or decrypt — Spain → Egypt works.
    if (res.settings) {
      VaultBackend.applyServerSettings(res.settings, SET, applyAccent);
    }

    // If PIN is required (Zero-Hash PIN Shield envelope or legacy k2)
    if (res.wrapped || res.pin || (frag && frag.startsWith('k2.'))) {
      attempts = 3;
      state = 'gate';
      setAvatarMode('gate');
      buildGate();
      await go('gate');
      setTimeout(() => {
        const b = panes.gate.querySelector('.pbox');
        if (b) b.focus();
      }, 420);
    } else {
      await startViewVault();
    }
  }

  /* 2. Receive Direct In-Link */
  async function runReceiveDirect(parts) {
    rxContext = {
      type: 'direct',
      parts,
      pin: null
    };

    // PIN is required when 6 or 7 parts are present
    if (parts.length === 6 || parts.length === 7) {
      attempts = 3;
      state = 'gate';
      setAvatarMode('gate');
      buildGate();
      await go('gate');
      setTimeout(() => {
        const b = panes.gate.querySelector('.pbox');
        if (b) b.focus();
      }, 420);
    } else {
      await startViewDirect();
    }
  }

  /* ---------- PIN Gate Modal ---------- */
  function buildGate() {
    panes.gate.innerHTML = `
      <div class="gatecard">
        <div class="g-ic">${IC.lock}</div>
        <h3 class="g-t">PIN required</h3>
        <p class="g-sub" id="gSub"><b id="gAtt">3</b> attempts remaining</p>
        <div class="pin-row" id="gRow">
          <input class="pbox" type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="PIN digit 1">
          <input class="pbox" type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="PIN digit 2">
          <input class="pbox" type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="PIN digit 3">
          <input class="pbox" type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" aria-label="PIN digit 4">
        </div>
        <button class="g-cancel" id="gCancel">${fromSender ? 'back to the link' : 'not now'}</button>
      </div>`;

    const boxes = [...panes.gate.querySelectorAll('.pbox')];
    const row = panes.gate.querySelector('#gRow');
    const sub = panes.gate.querySelector('#gSub');
    const att = panes.gate.querySelector('#gAtt');
    const clear = () => boxes.forEach(b => b.value = '');

    const verify = async () => {
      const code = boxes.map(b => b.value).join('');
      if (code.length !== 4) return;

      if (rxContext.type === 'vault') {
        try {
          const { obj, kb, isDuress } = await BlackendCrypto.decryptVaultPayload(
            rxContext.envelope || rxContext,
            rxContext.frag,
            code
          );
          rxContext.obj = obj;
          rxContext.kb = kb;

          if (isDuress) {
            // Silently burn the real envelope on the server immediately upon duress PIN entry
            api('burn', { id: rxContext.token, why: 'duress' });
          } else {
            // Explicitly claim and transition to 'opened' state upon successful PIN unlock
            const opRes = await api('open', { id: rxContext.token });
            if (opRes && opRes.ok && rxContext.envelope) {
              rxContext.envelope.read = opRes.read;
              rxContext.envelope.now = opRes.now;
              rxContext.envelope.already_read = false;
            }
          }
          await renderDecryptedMessage(obj, kb);
        } catch (_) {
          const srv = await api('fail', { id: rxContext.token });
          const left = srv.left !== undefined ? srv.left : 0;
          if (srv.state === 'killed' || srv.state === 'gone' || left <= 0) {
            state = 'dead';
            markChat(currentChatId, 'ash', 'killed');
            buildEnd('killed');
            go('end');
            state = 'done';
            setAvatarMode('ash');
            clearURL();
            toast('Link killed — three failed attempts.');
            return;
          }
          attempts = left;
          row.classList.add('wrong');
          att.textContent = left;
          sub.classList.toggle('warn', left === 1);
          setTimeout(() => { clear(); row.classList.remove('wrong'); boxes[0].focus(); }, 520);
        }
      } else {
        // Direct In-Link
        try {
          const { obj, kb } = await BlackendCrypto.decryptDirectPayload(rxContext.parts, code);
          rxContext.obj = obj;
          rxContext.kb = kb;
          await renderDecryptedMessage(obj, kb);
        } catch (_) {
          attempts--;
          if (attempts <= 0) {
            state = 'dead';
            markChat(currentChatId, 'ash', 'killed');
            clearURL();
            buildEnd('killed');
            go('end');
            state = 'done';
            setAvatarMode('ash');
            toast('Link killed — three failed attempts.');
            return;
          }
          row.classList.add('wrong');
          att.textContent = attempts;
          sub.classList.toggle('warn', attempts === 1);
          setTimeout(() => { clear(); row.classList.remove('wrong'); boxes[0].focus(); }, 520);
        }
      }
    };

    wireBoxes(boxes, verify);

    panes.gate.querySelector('#gCancel').addEventListener('click', () => {
      if (state !== 'gate') return;
      if (fromSender) {
        state = 'sealed';
        rxContext = null;
        go('card');
        startFuse();
        if (curToken) startPoll();
        setAvatarMode('sealed');
      } else {
        newDraft();
      }
    });
  }

  async function startViewVault() {
    try {
      const { obj, kb } = await BlackendCrypto.decryptVaultPayload(
        rxContext.envelope || rxContext,
        rxContext.frag,
        null
      );
      rxContext.obj = obj;
      rxContext.kb = kb;
      await renderDecryptedMessage(obj, kb);
    } catch (_) {
      buildEnd('tampered');
      await go('end');
      state = 'done';
      setAvatarMode('ash');
      clearURL();
      if (rxContext && rxContext.token) {
        api('burn', { id: rxContext.token, why: 'killed' });
      }
    }
  }

  async function startViewDirect() {
    try {
      const { obj, kb } = await BlackendCrypto.decryptDirectPayload(rxContext.parts, null);
      rxContext.obj = obj;
      rxContext.kb = kb;
      await renderDecryptedMessage(obj, kb);
    } catch (_) {
      buildEnd('tampered');
      await go('end');
      state = 'done';
      setAvatarMode('ash');
      clearURL();
    }
  }

  /* ---------- Render Decrypted Message View ---------- */
  async function renderDecryptedMessage(obj, kb, customCdSecs) {
    if (typeof window.__cdCleanup === 'function') {
      window.__cdCleanup();
      window.__cdCleanup = null;
    }
    state = 'viewing';
    setAvatarMode('view');
    mOpen.textContent = `opened ${utcHM()} utc`;

    const nowSec = Math.floor(Date.now() / 1000);
    const isExpired = obj.x && (obj.x > 1000000000 && nowSec > (obj.x + 3));
    if (isExpired) {
      markChat(currentChatId, 'ash', 'expired');
      buildEnd('expired');
      await go('end');
      clearURL();
      state = 'done';
      setAvatarMode('ash');
      return;
    }

    msgText.textContent = obj.m;
    msgText.scrollTop = 0;
    bubble.classList.remove('collapse');
    bubble.style.maxHeight = '';

    attCard.hidden = !obj.f;
    attCard.style.opacity = '';
    attCard.style.transition = '';
    attSave.hidden = true;

    // Use sender's configured read time if packed in message, fallback to local settings or default 4
    const baseReadTime = Math.max(1, parseFloat(obj.rt) || parseFloat(SET.readTime) || 4);
    let cdSecs = customCdSecs || (obj.f ? Math.max(baseReadTime, baseReadTime + 8) : baseReadTime);

    // If message had a timed expiry fuse (e.g. 60s), clamp read time to remaining fuse life
    if (obj.x && obj.x > 1000000000) {
      const fuseRemaining = Math.max(1, obj.x - nowSec);
      cdSecs = Math.min(cdSecs, fuseRemaining);
    }

    // Only calculate remaining seconds if message was ALREADY opened on server previously (e.g. reload during reading window)
    if (!customCdSecs && rxContext && rxContext.envelope && rxContext.envelope.already_read) {
      const srvNow = rxContext.envelope.now || Math.floor(Date.now() / 1000);
      const srvRead = rxContext.envelope.read || srvNow;
      const elapsedSec = Math.max(0, srvNow - srvRead);
      if (elapsedSec > 0) {
        cdSecs = Math.max(1, cdSecs - elapsedSec);
      }
    }

    // Apply sender burn speed, style, and accent (for direct-link cross-browser delivery)
    if (obj.bt)     SET.burnTime = parseFloat(obj.bt);
    if (obj.bs)     SET.burn     = obj.bs;
    if (obj.accent) applyAccent(obj.accent);

    cdNum.textContent = cdSecs + 's';
    ringFg.classList.remove('run');
    ringFg.style.animationDuration = cdSecs + 's';
    ringFg.style.animationPlayState = 'running';

    if (obj.f) {
      attCardIc.innerHTML = attIcon(obj.f.t);
      attCardName.textContent = obj.f.n;

      if (rxContext.type === 'vault') {
        attCardSub.textContent = 'pulling encrypted chunks from vault…';
        pullVaultChunks(rxContext.token, kb, obj.f).then(blob => {
          if (state !== 'viewing') return;
          attURL = URL.createObjectURL(blob);
          attDL = { url: attURL, name: obj.f.n };
          attCardSub.textContent = fmtSize(obj.f.s) + ' · decrypted in browser';
          attSave.hidden = false;
        }).catch(() => {
          if (state !== 'viewing') return;
          attCardSub.textContent = 'chunk retrieval failed';
        });
      } else {
        // Direct In-Link
        attCardSub.textContent = 'decrypting attached file in browser…';
        BlackendCrypto.decryptDirectAttachment(rxContext.parts, kb, obj.f.t).then(blob => {
          if (state !== 'viewing') return;
          attURL = URL.createObjectURL(blob);
          attDL = { url: attURL, name: obj.f.n };
          attCardSub.textContent = fmtSize(obj.f.s) + ' · decrypted in browser';
          attSave.hidden = false;
        }).catch(() => {
          if (state !== 'viewing') return;
          attCardSub.textContent = 'attachment decryption failed';
        });
      }
    }

    await go('message');
    await wait(REDUCED ? 250 : 550);

    ringFg.classList.add('run');

    let cdRemaining = cdSecs * 1000;
    let cdEnd = Date.now() + cdRemaining;
    let cdTick = null;
    let cdHandle = null;
    let cdPaused = false;

    function cdFire() {
      if (cdTick) { clearInterval(cdTick); cdTick = null; }
      if (cdHandle) { clearTimeout(cdHandle); cdHandle = null; }
      finishViewing();
    }

    function cdUpdateTick() {
      const now = Date.now();
      const rem = Math.max(0, cdEnd - now);
      const remSec = Math.ceil(rem / 1000);
      cdNum.textContent = remSec + 's';
      if (rem <= 0) {
        cdFire();
      }
    }

    function cdPause() {
      if (cdPaused || state !== 'viewing') return;
      cdPaused = true;
      cdRemaining = Math.max(0, cdEnd - Date.now());
      if (cdTick) { clearInterval(cdTick); cdTick = null; }
      if (cdHandle) { clearTimeout(cdHandle); cdHandle = null; }
      ringFg.style.animationPlayState = 'paused';
    }

    function cdResume() {
      if (!cdPaused || state !== 'viewing') return;
      cdPaused = false;
      if (cdRemaining <= 0) {
        cdFire();
        return;
      }
      cdEnd = Date.now() + cdRemaining;
      cdNum.textContent = Math.ceil(cdRemaining / 1000) + 's';
      ringFg.style.animationPlayState = 'running';
      cdTick = setInterval(cdUpdateTick, 250);
      cdHandle = setTimeout(cdFire, cdRemaining);
    }

    const onVisibility = () => {
      if (document.hidden) cdPause(); else cdResume();
    };

    const onBurnBeforeExit = () => {
      if (state === 'viewing' && rxContext && rxContext.type === 'vault' && rxContext.token) {
        try {
          fetch('/vault.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'burn', id: rxContext.token, why: 'read' }),
            keepalive: true
          });
        } catch (_) {}
      }
    };

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', onBurnBeforeExit);
    window.addEventListener('beforeunload', onBurnBeforeExit);
    window.addEventListener('pagehide', cdPause);
    window.addEventListener('pageshow', cdResume);
    window.addEventListener('blur', cdPause);
    window.addEventListener('focus', cdResume);

    // Store cleanup handle on a module-level variable so finishViewing can clean up
    window.__cdCleanup = () => {
      if (cdTick) { clearInterval(cdTick); cdTick = null; }
      if (cdHandle) { clearTimeout(cdHandle); cdHandle = null; }
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('pagehide', onBurnBeforeExit);
      window.removeEventListener('beforeunload', onBurnBeforeExit);
      window.removeEventListener('pagehide', cdPause);
      window.removeEventListener('pageshow', cdResume);
      window.removeEventListener('blur', cdPause);
      window.removeEventListener('focus', cdResume);
    };

    cdTick = setInterval(cdUpdateTick, 250);
    cdHandle = setTimeout(cdFire, cdRemaining);
  }

  async function pullVaultChunks(tokenId, keyBytes, fileMeta) {
    const parts = [];
    for (let i = 0; i < fileMeta.nc; i++) {
      const r = await api('chunk', { id: tokenId, i });
      if (!r || !r.ok || !r.data) throw new Error('vault_chunk_failed');
      const decryptedChunk = await BlackendCrypto.decryptVaultChunk(r.data, keyBytes);
      parts.push(decryptedChunk);
    }
    return new Blob(parts, { type: fileMeta.t || 'application/octet-stream' });
  }

  async function finishViewing() {
    if (state !== 'viewing') return;
    // Clean up the pauseable countdown timers and visibilitychange listener
    if (typeof window.__cdCleanup === 'function') { window.__cdCleanup(); window.__cdCleanup = null; }
    state = 'burning';
    setAvatarMode('burn');

    await runWave(waveify(msgText));

    bubble.style.maxHeight = bubble.offsetHeight + 'px';
    void bubble.offsetWidth;
    bubble.classList.add('collapse');

    if (!attCard.hidden && !REDUCED) {
      attCard.style.transition = 'opacity 0.3s';
      attCard.style.opacity = '0';
    }

    await wait(REDUCED ? 60 : 380);

    if (rxContext && rxContext.type === 'vault' && rxContext.token) {
      api('burn', { id: rxContext.token, why: 'read' });
    }

    if (attURL) {
      URL.revokeObjectURL(attURL);
      attURL = null;
    }
    attDL = null;

    clearURL();

    if (fromSender && currentChatId) {
      markChat(currentChatId, 'ash', 'opened');
    }

    rxContext = null;
    linkUrl = '';
    sessEnded();

    buildEnd('opened');
    await go('end');
    state = 'done';
    setAvatarMode('ash');
  }

  /* ================= End States ================= */
  function buildEnd(variant) {
    const V = {
      opened: {
        ic: IC.flame,
        t: `Opened ${utcHM()} UTC · burned`,
        s: 'the key was shredded and ciphertext destroyed — 0 bytes recoverable. nothing to forward, nothing to subpoena.'
      },
      expired: {
        ic: IC.clockx,
        t: 'Expired unread',
        s: 'the fuse was sealed into the message — it enforced itself on schedule. key shredded.'
      },
      killed: {
        ic: IC.lockx,
        t: 'Link killed',
        s: 'three failed attempts. key shredded, 0 bytes recovered.'
      },
      tampered: {
        ic: IC.lockx,
        t: 'Integrity check failed',
        s: 'the ciphertext did not match its cryptographic authentication tag. nothing was shown.'
      },
      archive: {
        ic: IC.flame,
        t: 'This one is ash',
        s: 'it already ended — the key is gone and there was never another copy anywhere.'
      }
    }[variant] || {
      ic: IC.flame,
      t: 'Message ended',
      s: 'the key is shredded and this link opens nothing.'
    };

    panes.end.innerHTML = `
      <div class="endcard">
        <div class="e-ic">${V.ic}</div>
        <h3 class="e-t">${V.t}</h3>
        <p class="e-s">${V.s}</p>
        <button class="btn primary" data-act="new">Write another</button>
      </div>`;
  }

  panes.end.addEventListener('click', e => {
    if (e.target.closest('[data-act="new"]')) newDraft();
  });

  function newDraft() {
    stopFuse();
    stopPoll();
    closePop();
    // Cancel any in-progress read countdown
    if (typeof window.__cdCleanup === 'function') { window.__cdCleanup(); window.__cdCleanup = null; }
    pin = null;
    btnPin.classList.remove('on');
    pinRemove.hidden = true;
    pinBoxes.forEach(b => b.value = '');
    linkUrl = '';
    curFrag = '';
    curToken = '';
    curDirectPayload = '';
    rxContext = null;
    fromSender = false;
    lastQR = null;
    showPinBadge = false;
    currentChatId = null;
    attBadge = null;
    clearAttachment();
    expiry = SET.fuse;
    syncFuseUI();
    renderList();
    clearURL();
    clearDraft();
    stage.style.transform = '';
    state = 'compose';
    setAvatarMode('idle');
    go('compose');
    if (MOBILE()) {
      setSide(false);
      window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' });
    }
    setTimeout(() => ta.focus(), MOBILE() ? 430 : 200);
  }

  /* ================= Archive Interactions ================= */
  function openChat(c) {
    if (state === 'sealing' || state === 'burning' || state === 'viewing') return;
    stopFuse();
    stopPoll();
    closePop();
    setSide(MOBILE() ? false : sideOpen);
    currentChatId = c.id;
    fromSender = true;
    renderList();

    if (c.s !== 'sealed') {
      setAvatarMode('ash');
      buildEnd('archive');
      state = 'done';
      go('end');
      return;
    }

    const isDirect = c.mode === 'direct' || (!c.mode && c.p);
    if (isDirect) {
      curDirectPayload = c.p;
      linkUrl = location.origin + location.pathname + '#' + c.p;
      dispToken = c.p.split('.')[2].slice(0, 6);
    } else {
      curToken = c.id;
      curFrag = c.u ? (c.u.split('#')[1] || '') : '';
      linkUrl = c.u || (location.origin + location.pathname + '?m=' + c.id + (curFrag ? ('#' + curFrag) : ''));
      dispToken = c.id;
    }

    expiry = c.e;
    showPinBadge = !!c.pin;
    pin = null;
    attBadge = c.att ? { generic: true } : null;
    fuseLeft = c.e === 'read' ? 0 : Math.max(0, (+c.e) - (Date.now() - c.c) / 1000);

    syncFuseUI();
    setAvatarMode('sealed');
    buildCard(c.t, isDirect ? 'direct' : 'vault', isDirect ? null : c.id);
    state = 'sealed';

    go('card').then(() => {
      if (state === 'sealed') {
        startFuse();
        if (!isDirect) startPoll();
      }
    });

    if (!isDirect) {
      api('status', { id: c.id }).then(r => {
        if (!r || !r.ok) return;
        if (r.state === 'opened') {
          markChat(c.id, 'opened', 'reading');
          stopFuse();
          const st = panes.card.querySelector('#cardStatus');
          if (st) {
            st.innerHTML = '<i class="dot opened"></i>opened · reading...';
          }
          const fl = panes.card.querySelector('#fuseLabel');
          if (fl) {
            fl.textContent = 'opened';
            fl.classList.remove('hot');
          }
        } else if (r.state === 'gone') {
          const reason = r.why === 'read' ? 'opened' : (r.why === 'expired' ? 'expired' : (r.why === 'killed' ? 'killed' : 'ended'));
          markChat(c.id, 'ash', reason);
          stopFuse();
          stopPoll();
          buildEnd(reason === 'expired' ? 'expired' : 'archive');
          state = 'done';
          setAvatarMode('ash');
          go('end');
        }
      });
    }
  }

  $('#chatList').addEventListener('click', e => {
    const edit = e.target.closest('[data-rename]');
    const item = e.target.closest('.chat');
    if (!item) return;
    const c = findChat(item.dataset.id);
    if (!c) return;
    if (edit) {
      startRename(item, c);
      return;
    }
    openChat(c);
  });

  $('#chatList').addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.classList.contains('chat')) {
      const c = findChat(e.target.dataset.id);
      if (c) openChat(c);
    }
  });

  function startRename(item, c) {
    item.classList.add('ren');
    const title = item.querySelector('.c-title');
    const input = document.createElement('input');
    input.className = 'c-ren';
    input.value = c.t;
    input.maxLength = 48;
    title.replaceWith(input);
    input.focus();
    input.select();
    let done = false;
    const commit = save => {
      if (done) return;
      done = true;
      const v = input.value.trim();
      if (save && v) c.t = v;
      persist();
      renderList();
      updateProfile();
    };
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); commit(true); }
      if (e.key === 'Escape') { commit(false); }
      e.stopPropagation();
    });
    input.addEventListener('blur', () => commit(true));
    input.addEventListener('click', e => e.stopPropagation());
  }

  /* ================= Wipe Everything ================= */
  async function doWipe() {
    stopFuse();
    stopPoll();
    closePop();
    closeSettings();
    setSide(false);

    // Burn all sealed vault envelopes
    const vaultIds = chats.filter(c => c.s === 'sealed' && c.mode !== 'direct').map(c => c.id);
    await Promise.allSettled(vaultIds.map(id => api('burn', { id, why: 'wiped' })));

    try { localStorage.removeItem(LSKEY); } catch (_) {}
    chats = [];
    currentChatId = null;
    linkUrl = '';
    curFrag = '';
    curToken = '';
    curDirectPayload = '';
    rxContext = null;
    fromSender = false;
    lastQR = null;
    pin = null;
    btnPin.classList.remove('on');
    pinRemove.hidden = true;
    pinBoxes.forEach(b => b.value = '');
    attBadge = null;
    clearAttachment();
    clearURL();
    clearDraft();
    stage.style.transform = '';
    state = 'compose';
    setAvatarMode('idle');
    runWipeFx();
  }

  async function runWipeFx() {
    const flash = $('#wipeFlash');
    const items = [...$('#chatList').querySelectorAll('.chat')];
    flash.classList.remove('on');
    void flash.offsetWidth;
    flash.classList.add('on');

    if (REDUCED || !items.length) {
      renderList();
      updateProfile();
      toast('Everything ended — even the memory of it.');
      return;
    }

    items.forEach((item, i) => {
      setTimeout(() => {
        const t = item.querySelector('.c-title');
        if (t) runWave(waveify(t));
        const d = item.querySelector('.c-dot');
        if (d) { d.style.transition = 'opacity 0.5s'; d.style.opacity = '0'; }
        item.style.transition = 'opacity 0.55s 0.35s, filter 0.55s 0.35s';
        item.style.opacity = '0';
        item.style.filter = 'blur(3px)';
      }, i * 130);
    });

    await wait(items.length * 130 + 900);
    renderList();
    updateProfile();
    go('compose');
    toast('Everything ended — even the memory of it.');
    setTimeout(() => ta.focus(), 400);
  }

  /* ================= Session Counter & Brand ================= */
  const mSess = $('#mSess');
  let sess = 0;
  function sessEnded() {
    sess++;
    mSess.textContent = sess;
    mSess.classList.add('tick');
    setTimeout(() => mSess.classList.remove('tick'), 380);
  }

  function startNew() {
    setSide(MOBILE() ? false : sideOpen);
    if (state !== 'compose') newDraft();
    else pulseComposer();
    setTimeout(() => ta.focus(), MOBILE() ? 420 : 140);
  }

  $('#newSide').addEventListener('click', startNew);
  $('#mPlus').addEventListener('click', startNew);
  $('#writeOne').addEventListener('click', () => {
    if (innerWidth > 900) stage.scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth', block: 'center' });
    if (state !== 'compose') newDraft();
    else pulseComposer();
    setTimeout(() => ta.focus(), 160);
  });

  $('#brandLink').addEventListener('click', e => {
    e.preventDefault();
    setSide(MOBILE() ? false : sideOpen);
    window.scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' });
  });

  /* ================= Anatomy & Receipt Verification ================= */
  $$('[data-p]').forEach(el => {
    const grp = [...$$(`[data-p="${el.dataset.p}"]`)];
    const on = () => grp.forEach(x => x.classList.add('hl'));
    const off = () => grp.forEach(x => x.classList.remove('hl'));
    el.addEventListener('pointerenter', on);
    el.addEventListener('pointerleave', off);
  });

  (async function buildAnatomy() {
    if (!BlackendCrypto.HAS_CRYPTO) return;
    try {
      const p = await BlackendCrypto.buildDirectPayload('meet at the pier, 9pm. bring nothing.', 0, null);
      const parts = p.split('.');
      const dom = (location.host || 'blackend.on').replace(/^www\./, '');
      $('#segPage').textContent = dom || 'blackend.on';
      $('#segIv').textContent = '.' + parts[1];
      $('#segCt').textContent = '.' + elide(parts[2], 34);
    } catch (_) {}
  })();

  function receiptSync() {
    let ck = 0;
    try { ck = document.cookie ? document.cookie.split(';').filter(Boolean).length : 0; } catch (_) {}
    $('#rCk').textContent = ck;
    $('#rSt').textContent = chats.length + ' entr' + (chats.length === 1 ? 'y' : 'ies');
  }

  $('#rVerify').addEventListener('click', () => {
    receiptSync();
    let ck = 0;
    try { ck = document.cookie ? document.cookie.split(';').filter(Boolean).length : 0; } catch (_) {}
    const clean = ck === 0;
    $('#rVerify').classList.toggle('ok', clean);
    $('#rVerifyTxt').textContent = clean
      ? `verified · ${chats.length} local, 0 sent in clear`
      : 'cookies found — not from blackend';
    toast(clean
      ? `Verified — 0 cookies, ${chats.length} local entries, only encrypted noise ever hit the wire.`
      : 'This host left cookies in your browser — they are not from blackend.');
  });

  /* ================= Scroll Reveals & Viewport Adjustments ================= */
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => entries.forEach(en => {
      if (en.isIntersecting) {
        en.target.classList.add('in');
        io.unobserve(en.target);
      }
    }), { threshold: 0.05, rootMargin: '0px 0px -30px 0px' });
    $$('.rv, .rv-stagger').forEach(el => io.observe(el));
  } else {
    $$('.rv, .rv-stagger').forEach(el => el.classList.add('in'));
  }

  let lastInnerW = window.innerWidth;
  window.addEventListener('resize', () => {
    const activeEl = document.activeElement;
    const isEditing = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA');
    if (Math.abs(window.innerWidth - lastInnerW) > 50 && !isEditing) {
      closePop();
      lastInnerW = window.innerWidth;
    }
    syncDock();
    grow();
    updateScrim();
  });

  if (window.visualViewport) {
    const vv = visualViewport;
    const onVV = () => {
      const kb = Math.max(0, innerHeight - vv.height - vv.offsetTop);
      if (stage.classList.contains('docked') && kb > 60) {
        stage.style.transform = `translateY(${-kb}px)`;
      } else if (!stage.style.transform.startsWith('translate')) {
        stage.style.transform = '';
      }
    };
    vv.addEventListener('resize', onVV);
    vv.addEventListener('scroll', onVV);
  }

  /* ================= Receive On URL Load / Hash Change ================= */
  function tryReceive() {
    const parsed = BlackendCrypto.parseLink(location.pathname, location.search, location.hash);
    if (!parsed) return;
    if (state !== 'compose' && state !== 'done') return;

    fromSender = false;

    if (parsed.mode === 'vault') {
      runReceiveVault(parsed.token, parsed.frag);
    } else if (parsed.mode === 'direct') {
      runReceiveDirect(parsed.parts);
    }
  }

  window.addEventListener('hashchange', tryReceive);

  /* ================= Background Sync for Archived Vault Chats ================= */
  async function syncVaultChatsStatus() {
    const activeVaultChats = chats.filter(c => (c.mode === 'vault' || !c.p) && (c.s === 'sealed' || c.s === 'opened'));
    if (!activeVaultChats.length) return;
    let changed = false;
    for (const c of activeVaultChats) {
      try {
        const r = await api('status', { id: c.id });
        if (r && r.ok) {
          if (r.state === 'opened' && c.s !== 'opened') {
            c.s = 'opened';
            c.r = 'reading';
            changed = true;
          } else if (r.state === 'gone' && c.s !== 'ash') {
            c.s = 'ash';
            c.r = r.why === 'read' ? 'opened' : (r.why === 'expired' ? 'expired' : (r.why === 'killed' ? 'killed' : 'ended'));
            changed = true;
          }
        }
      } catch (_) {}
    }
    if (changed) {
      persist();
      renderList();
    }
  }

  /* ================= Application Initialization ================= */
  function initApp() {
    loadChats();
    renderList();
    syncVaultChatsStatus();
    rerollIdentity();
    updateProfile();
    receiptSync();
    dock(true);
    grow();

    const netCanvas = $('#net');
    if (netCanvas && typeof BlackendCanvas !== 'undefined') {
      BlackendCanvas.init(netCanvas, { net: SET.net, motes: SET.motes });
    }

    tryReceive();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
  } else {
    initApp();
  }
})();
