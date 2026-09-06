<?php
declare(strict_types=1);

// Prevent caching and emit defensive HTTP security headers
header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<base href="/">
<title>blackend — the chat that forgets</title>
<meta name="description" content="Serverless end-to-end temporary messaging with blind escrow vault. The encrypted key stays inside the link — one read, then ash. No accounts, no tracking, no permanent storage.">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230a0b0e'/><circle cx='16' cy='16' r='5' fill='%23ffb454'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Instrument+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<script>document.documentElement.classList.add('js');</script>
</head>
<body>

<canvas id="net" aria-hidden="true"></canvas>
<div id="scrim" aria-hidden="true"></div>
<div id="wipeFlash" aria-hidden="true"></div>

<div class="app">

  <!-- ============ sidebar ============ -->
  <aside class="side" id="side" aria-label="message archive">
    <div class="side-head">
      <a class="brand" href="#" id="brandLink">blackend<span class="p">.</span></a>
      <button class="side-x" id="sideX" aria-label="close panel">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <button class="newchat" id="newSide">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      New message
    </button>
    <div class="side-label">messages</div>
    <div class="chatlist" id="chatList"></div>
    <div class="side-foot">
      <div class="prof">
        <button class="avatar m-idle" id="avatar" data-tip="new identity"
          aria-label="regenerate your identity"></button>
        <div class="prof-b">
          <b id="opName">—</b>
          <span class="pstat"><i id="profMode">ready</i> · <span id="profStat">no cookies · 0 archived</span></span>
        </div>
        <button class="setbtn" id="setBtn" data-tip="settings" aria-label="open settings">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </button>
      </div>
    </div>
  </aside>

  <!-- ============ main ============ -->
  <div class="mainwrap">
    <header class="maintop">
      <button class="sidebtn" id="sideToggle" aria-label="toggle the sidebar">
        <svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M9.5 4.5v15"/></svg>
      </button>
      <span class="mt-brand">blackend<span class="p">.</span></span>
      <span class="mt-tag">end-to-end · serverless escrow · nothing leaves in the clear</span>
      <nav class="mt-nav">
        <a href="#how">how it ends</a>
        <a href="#proof">the proof</a>
      </nav>
      <button class="m-plus" id="mPlus" aria-label="new message">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      </button>
    </header>

    <main>
      <section class="hero">
        <span class="tag"><i class="live"></i>the chat that forgets</span>
        <h1 class="h1">Say it once. It ends<span class="p">.</span></h1>
        <p class="sub">Write a message — or attach a file — and seal it into a self-destructing link.
          The encryption happens in your browser and the key travels inside the link —
          browsers never send the part after <b>#</b>. One read, then ash. Your archive
          lives only on this device, under names that say nothing.</p>

        <div class="stage docked" id="stage">

          <!-- pane 1 · composer -->
          <div class="pane" id="paneCompose">
            <div class="composer" id="composer">
              <div class="c-text">
                <textarea id="ta" rows="1" maxlength="1000" aria-label="message draft"
                  placeholder="Say what shouldn't exist forever…"></textarea>
                <div class="chars" id="charLayer" hidden></div>
              </div>
              <input type="file" id="fileInput" hidden>
              <div class="att-strip" id="attStrip" hidden>
                <span class="att-chip">
                  <span class="aic" id="attChipIc" aria-hidden="true"></span>
                  <span class="att-name" id="attName"></span>
                  <span class="att-size" id="attSize"></span>
                  <button class="att-x" id="attX" aria-label="remove attachment">
                    <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                  </button>
                </span>
              </div>
              <div class="c-bar">
                <button class="ibtn" id="btnAtt" data-tip="Attach a file"
                  aria-label="Attach a file">
                  <svg viewBox="0 0 24 24"><path d="M21 11.5 12.5 20a5.5 5.5 0 0 1-7.8-7.8l8-8a3.7 3.7 0 0 1 5.2 5.2l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/></svg>
                </button>
                <button class="ibtn" id="btnPin" data-tip="PIN protection"
                  aria-label="Set a PIN for this message">
                  <svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3"/></svg>
                </button>
                <button class="ibtn" id="btnExp" data-tip="Burn after"
                  aria-label="Choose when the message burns">
                  <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4"/></svg>
                </button>
                <button class="chip" id="expChip" hidden aria-label="Expiry setting">
                  <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4"/></svg>
                  <span id="expLabel">60s</span>
                </button>
                <button class="send" id="btnSend" disabled aria-label="Seal this message">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V6M6.5 11.5 12 6l5.5 5.5"/></svg>
                </button>
              </div>

              <!-- PIN popover -->
              <div class="pop" id="popPin" role="dialog" aria-label="PIN protection" hidden>
                <div class="pop-head"><span id="pinHead">PIN protection</span>
                  <button class="pop-x" id="pinClose" aria-label="Close">
                    <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                  </button>
                </div>
                <div class="pin-row" id="pinRow">
                  <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 1">
                  <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 2">
                  <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 3">
                  <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 4">
                </div>
                <p class="pop-note" id="pinNote">Anyone opening the link will need this
                  4-digit code — it wraps the key with 120,000 PBKDF2 rounds.
                  Three wrong attempts destroy the message.</p>
                <div class="pop-foot"><button class="linkbtn" id="pinRemove" hidden>Remove PIN</button></div>
              </div>

              <!-- expiry popover -->
              <div class="pop" id="popExp" role="dialog" aria-label="Expiry" hidden>
                <div class="pop-head"><span>Burn after</span>
                  <button class="pop-x" id="expClose" aria-label="Close">
                    <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                  </button>
                </div>
                <button class="exp-row on" data-exp="read">After it's read
                  <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></button>
                <button class="exp-row" data-exp="60">60 seconds
                  <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></button>
                <button class="exp-row" data-exp="600">10 minutes
                  <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></button>
                <button class="exp-row" data-exp="3600">1 hour
                  <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></button>
              </div>
            </div>
            <div class="hint" id="hint"></div>
          </div>

          <!-- panes 2–5 rendered dynamically -->
          <div class="pane" id="paneCard" hidden></div>
          <div class="pane" id="paneGate" hidden></div>
          <div class="pane" id="paneMessage" hidden>
            <div class="msgwrap">
              <div class="m-meta">
                <span id="mOpen">opened — utc</span>
                <span class="cdwrap">
                  <svg class="ring" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="rbg" cx="12" cy="12" r="9"/>
                    <circle class="rfg" id="ringFg" cx="12" cy="12" r="9"/>
                  </svg>
                  <b id="cdNum">3s</b>
                </span>
              </div>
              <div class="bubble" id="bubble"><div id="msgText"></div></div>
              <div class="att-card" id="attCard" hidden>
                <span class="aic" id="attCardIc" aria-hidden="true"></span>
                <span class="att-mid">
                  <b id="attCardName"></b>
                  <span id="attCardSub"></span>
                </span>
                <button class="btn ghost" id="attSave">
                  <svg viewBox="0 0 24 24"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/></svg>
                  save
                </button>
              </div>
            </div>
          </div>
          <div class="pane" id="paneEnd" hidden></div>

        </div>

        <div class="meta">
          <div class="m"><b id="mSess">0</b><span>ended in this session</span></div>
          <div class="m"><b>0</b><span>servers that can read your words</span></div>
          <div class="m"><b>0</b><span>words that left this device unencrypted</span></div>
        </div>
      </section>

      <!-- ============ how it ends ============ -->
      <section class="sec rv" id="how">
        <div class="sec-h">how it ends</div>
        <div class="cards rv-stagger">
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><path d="M12 4.5c.5 3-3.8 5-3.8 9a3.8 3.8 0 0 0 7.6 0c0-1.9-1-3-1.7-4.4-.4 1-1.1 1.6-1.9 1.9.4-2.2.3-4.3-.2-6.5Z"/></svg></div>
            <h3>One read, then ash</h3>
            <p>Seal it into a link — or a scannable code. One open decrypts it once,
              then the key is shredded. Nothing to forward, leak, or subpoena.</p>
          </div>
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><path d="M21 11.5 12.5 20a5.5 5.5 0 0 1-7.8-7.8l8-8a3.7 3.7 0 0 1 5.2 5.2l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/></svg></div>
            <h3>Files, same fate</h3>
            <p>Attach up to your limit — images, audio, documents. They're split into
              256 KB chunks and encrypted right here in your browser.</p>
          </div>
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3"/></svg></div>
            <h3>PIN-gated secrets</h3>
            <p>Your PIN derives a wrapping key with 120,000 PBKDF2 rounds around the
              message key. Three wrong attempts burn it for good.</p>
          </div>
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4"/></svg></div>
            <h3>A sealed fuse</h3>
            <p>The expiry is encrypted into the message and enforced the instant anyone
              tries to open it — unread links end themselves, on schedule.</p>
          </div>
        </div>
      </section>

      <!-- ============ the proof ============ -->
      <section class="sec rv" id="proof">
        <div class="sec-h">the proof</div>

        <div class="wire" aria-label="message path diagram">
          <div class="wire-row">
            <span class="wnode"><i class="wdot on"></i>you</span>
            <span class="wtrack">
              <span class="wlab">the ciphertext rides encrypted · key stays in fragment</span>
              <i class="wpkt" aria-hidden="true"></i>
            </span>
            <span class="wnode"><i class="wdot off"></i>recipient</span>
          </div>
          <div class="wire-branch" aria-hidden="true"></div>
          <div class="wserver">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4.5" width="16" height="6" rx="2"/><rect x="4" y="13.5" width="16" height="6" rx="2"/><path d="M7.5 7.5h.01M7.5 16.5h.01"/></svg>
            zero plaintext on any server
          </div>
        </div>

        <div class="proof-grid rv-stagger">
          <div class="anatomy">
            <p class="a-head">anatomy of a link</p>
            <p class="a-sub">sealed live in this tab — hover the parts</p>
            <div class="a-link" id="aLink">
              <span class="seg s-page" data-p="page" id="segPage">blackend.on</span><span class="seg s-hash" data-p="hash">#</span><span class="seg s-ver" data-p="ver">k1</span><span class="seg s-iv" data-p="iv" id="segIv">.Z8Vgr3mbC88</span><span class="seg s-ct" data-p="ct" id="segCt">.aJHiF0qRs7T2vXwYz3Lm5N8bC1dE6fGh4…</span>
            </div>
            <div class="a-legend">
              <div class="arow" data-p="page"><i class="sw sw-page"></i><span>the page</span><em>static html — any host can serve it. nothing dynamic to subpoena.</em></div>
              <div class="arow" data-p="hash"><i class="sw sw-hash"></i><span>the boundary</span><em>browsers cut everything after # out of every request. the message starts here.</em></div>
              <div class="arow" data-p="ver"><i class="sw sw-ver"></i><span>version</span><em>k1 words only · k2 words plus sealed file chunks — old links fail loudly, never silently.</em></div>
              <div class="arow" data-p="iv"><i class="sw sw-iv"></i><span>randomness</span><em>fresh IV — the same message sealed twice looks nothing alike.</em></div>
              <div class="arow" data-p="ct"><i class="sw sw-ct"></i><span>ciphertext</span><em>your words and the key, AES-256-GCM. this is the message.</em></div>
            </div>
          </div>

          <div class="receipt">
            <p class="r-head">blackend · server log</p>
            <p class="r-sub">for every message, ever</p>
            <div class="rrow"><span>ip address</span><i class="lead"></i><b class="zero">—</b></div>
            <div class="rrow"><span>user agent</span><i class="lead"></i><b class="zero">—</b></div>
            <div class="rrow"><span>cookies</span><i class="lead"></i><b id="rCk">0</b></div>
            <div class="rrow"><span>local archive</span><i class="lead"></i><b id="rSt">0 entries</b></div>
            <div class="rrow"><span>keys held</span><i class="lead"></i><b class="zero">0 (client only)</b></div>
            <div class="r-total"><span>leaves this device in the clear</span><b>0 bytes</b></div>
            <p class="r-note">the only copy lives in this browser — wipe it any time.</p>
            <button class="r-verify" id="rVerify">
              <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
              <span id="rVerifyTxt">verify live in this tab</span>
            </button>
          </div>
        </div>

        <p class="proof-foot">no accounts · no cookies · archive is device-only · works offline once loaded.</p>
      </section>

      <!-- ============ outro ============ -->
      <section class="outro rv">
        <p class="o-big">Some things shouldn't last<span class="p">.</span></p>
        <button class="btn primary" id="writeOne">Write a message</button>
      </section>

      <footer>
        <span class="l">blackend — everything ends here.</span>
        <span class="r">no cookies · zero-knowledge · the archive never leaves this device</span>
      </footer>
    </main>
  </div>
</div>

<!-- ============ settings slide-over ============ -->
<div id="setScrim" aria-hidden="true"></div>
<aside class="setpanel" id="setPanel" role="dialog" aria-modal="true" aria-label="blackend settings">
  <div class="set-head">
    <b>settings</b>
    <button class="pop-x" id="setClose" aria-label="close settings">
      <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>
  <div class="set-scroll">

    <div class="set-group">
      <div class="sg-head">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4"/></svg>
        appearance
      </div>
      <div class="set-row col">
        <div class="st"><b>accent</b><span>one color for the whole system — seals, burns, links, this panel</span></div>
        <div class="swatches" id="swatches">
          <button class="swatch on" data-acc="ember"   style="--c:#ffb454" aria-label="ember"></button>
          <button class="swatch" data-acc="crimson" style="--c:#ff7a8a" aria-label="crimson"></button>
          <button class="swatch" data-acc="mint"    style="--c:#7fe0b2" aria-label="mint"></button>
          <button class="swatch" data-acc="ice"     style="--c:#8fc7ff" aria-label="ice"></button>
        </div>
      </div>
      <div class="set-row">
        <div class="st"><b>node field</b><span>the dotted background grid</span></div>
        <button class="sw2 on" data-set="net" role="switch" aria-checked="true" aria-label="node field"></button>
      </div>
      <div class="set-row">
        <div class="st"><b>drifting ash</b><span>embers rising from ended messages</span></div>
        <button class="sw2 on" data-set="motes" role="switch" aria-checked="true" aria-label="drifting ash"></button>
      </div>
    </div>

    <div class="set-group">
      <div class="sg-head">
        <svg viewBox="0 0 24 24"><path d="M21 11.5 12.5 20a5.5 5.5 0 0 1-7.8-7.8l8-8a3.7 3.7 0 0 1 5.2 5.2l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/></svg>
        attachments
      </div>
      <div class="set-row col">
        <div class="st"><b>max file size</b><span>per message — files are split into 256 KB chunks and encrypted in browser</span></div>
        <div class="seg2" id="segAtt">
          <button data-att="1">1 MB</button>
          <button data-att="5" class="on">5 MB</button>
          <button data-att="10">10 MB</button>
          <button data-att="25">25 MB</button>
        </div>
      </div>
    </div>

    <div class="set-group">
      <div class="sg-head">
        <svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.6A8.5 8.5 0 1 1 21 11.5Z"/></svg>
        messages
      </div>
      <div class="set-row col">
        <div class="st"><b>default fuse</b><span>preselected every time you start a message</span></div>
        <div class="seg2" id="segFuse">
          <button data-fuse="read" class="on">after read</button>
          <button data-fuse="60">60s</button>
          <button data-fuse="600">10m</button>
          <button data-fuse="3600">1h</button>
        </div>
      </div>
      <div class="set-row col">
        <div class="st"><b>burn speed</b><span>how fast text turns to embers</span></div>
        <div class="seg2" id="segBurn">
          <button data-burn="calm" class="on">calm (1.4s)</button>
          <button data-burn="quick">quick (0.6s)</button>
          <button data-burn="custom" id="btnBurnCustom">custom</button>
        </div>
        <div class="burn-custom-wrap" id="burnCustomWrap" hidden>
          <div class="burn-custom-row">
            <input type="range" id="burnSlider" min="0.2" max="8.0" step="0.1" value="2.0" class="burn-slider" aria-label="burn speed slider">
            <div class="burn-val-box">
              <input type="number" id="burnInput" min="0.1" max="30.0" step="0.1" value="2.0" class="burn-input" aria-label="custom burn seconds">
              <span class="burn-unit">s</span>
            </div>
            <button type="button" class="burn-test-btn" id="burnTestBtn" title="preview custom burn speed">test</button>
          </div>
          <div class="burn-preview-line" id="burnPreviewLine">
            <span class="bp-sample" id="bpSample">this message will turn to embers and ash…</span>
          </div>
        </div>
      </div>
      <div class="set-row">
        <div class="st"><b>auto-copy link</b><span>copies to your clipboard the moment a message seals</span></div>
        <button class="sw2" data-set="autocopy" role="switch" aria-checked="false" aria-label="auto-copy link"></button>
      </div>
      <div class="set-row col">
        <div class="st"><b>read countdown</b><span>how long the message stays visible before it burns (after-read fuse)</span></div>
        <div class="burn-custom-wrap" id="readCustomWrap" style="margin-top:6px">
          <div class="burn-custom-row">
            <input type="range" id="readSlider" min="1" max="60" step="1" value="4" class="burn-slider" aria-label="read countdown slider">
            <div class="burn-val-box">
              <input type="number" id="readInput" min="1" max="300" step="1" value="4" class="burn-input" aria-label="read countdown seconds">
              <span class="burn-unit">s</span>
            </div>
          </div>
          <p style="margin:2px 0 0;font:400 11.5px var(--fb);color:var(--mute);line-height:1.4">default is 4s · with an attachment the countdown adds 8s extra · max 300s</p>
        </div>
      </div>

    </div>

    <div class="set-group">
      <div class="sg-head">
        <svg viewBox="0 0 24 24"><path d="M12 3.5l7 2.7v4.5c0 4.7-3 7.7-7 9.8-4-2.1-7-5.1-7-9.8V6.2Z"/></svg>
        archive & privacy
      </div>
      <div class="set-row">
        <div class="st"><b>file messages locally</b><span>keep sealed links in this browser under quiet names</span></div>
        <button class="sw2 on" data-set="archive" role="switch" aria-checked="true" aria-label="file messages locally"></button>
      </div>
      <div class="set-row col">
        <div class="st"><b>end it all</b><span>burn every archived message on this device — instantly, then theatrically</span></div>
        <button class="dangerbtn" id="setWipe">wipe the archive</button>
        <div class="cfbox" id="cfbox">
          <div>
            <p>this can't be undone. the delete happens before the animation —
               refresh mid-burn finds nothing.</p>
            <div class="pc-btns">
              <button class="pc-btn danger" id="setWipeYes">burn it</button>
              <button class="pc-btn" id="setWipeNo">keep</button>
            </div>
          </div>
        </div>
      </div>
      <p class="set-status" id="setStatus">cookies: 0 · storage keys: 0 · data sent: 0 in clear</p>
    </div>

    <p class="set-note">settings live in memory for this session — reload restores
      defaults, and nothing about you is ever stored.</p>
  </div>
</aside>

<div id="toasts" role="status" aria-live="polite"></div>

<!-- Modular Scripts -->
<script src="/assets/js/crypto.js"></script>
<script src="/assets/js/qrenc.js"></script>
<script src="/assets/js/canvas.js"></script>
<script src="/assets/js/app.js"></script>

</body>
</html>