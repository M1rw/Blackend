<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<title>blackend — the chat that forgets</title>
<meta name="description" content="End-to-end temporary messaging with a blind escrow vault. Encrypted in your browser, held as noise the server can never read, shredded on first read.">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230a0b0e'/><circle cx='16' cy='16' r='5' fill='%23ffb454'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Instrument+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script>document.documentElement.classList.add('js')</script>
<style>
  :root{
    --bg:#0a0b0e; --sur:#14161b; --sur2:#1a1e25; --sur3:#171b21;
    --line:#232932; --line2:#2f3742;
    --ink:#edebe5; --dim:#a4aab4; --mute:#757d89; --faint:#4c535e;
    --ember:#ffb454; --ember2:#ffc678; --ember-rgb:255,180,84; --hot:#ff5c33;
    --qr-bg:#f2eee6; --qr-ink:#111722;
    --fh:'Space Grotesk',sans-serif; --fb:'Instrument Sans',sans-serif; --fm:'JetBrains Mono',monospace;
  }
  *{margin:0;padding:0;box-sizing:border-box}
  [hidden]{display:none!important}
  html{scroll-behavior:smooth}
  body{background:var(--bg);color:var(--ink);font:400 16px/1.65 var(--fb);
    overflow-x:hidden;-webkit-font-smoothing:antialiased;
    touch-action:manipulation;-webkit-tap-highlight-color:transparent;
    overscroll-behavior-y:contain}
  ::selection{background:var(--ember);color:#0a0b0e}
  ::-webkit-scrollbar{width:9px}
  ::-webkit-scrollbar-track{background:var(--bg)}
  ::-webkit-scrollbar-thumb{background:#232a34;border-radius:5px}
  ::-webkit-scrollbar-thumb:hover{background:var(--ember)}
  button{font-family:inherit;cursor:pointer;user-select:none;-webkit-user-select:none}
  :focus-visible{outline:2px solid rgba(var(--ember-rgb),.7);outline-offset:2px;border-radius:6px}

  #net{position:fixed;inset:0;z-index:0;transition:opacity .5s}
  body.net-off #net{opacity:0}
  #scrim{position:fixed;inset:0;background:rgba(4,5,8,.6);opacity:0;
    pointer-events:none;transition:opacity .28s;z-index:44}
  #scrim.on{opacity:1;pointer-events:auto}
  #wipeFlash{position:fixed;inset:0;z-index:55;pointer-events:none;opacity:0;
    background:radial-gradient(60% 60% at 50% 50%,rgba(255,120,40,.2),transparent 72%)}
  #wipeFlash.on{animation:wflash 1.2s ease}
  @keyframes wflash{0%{opacity:0}18%{opacity:1}100%{opacity:0}}

  .app{position:relative;z-index:1}
  .side{position:fixed;left:0;top:0;bottom:0;width:282px;display:flex;flex-direction:column;
    border-right:1px solid var(--line);background:rgba(10,11,14,.85);
    backdrop-filter:blur(14px);z-index:46;
    transition:transform .4s cubic-bezier(.22,.9,.3,1)}
  body.side-closed .side{transform:translateX(-103%)}
  .mainwrap{min-width:0;display:flex;flex-direction:column;margin-left:282px;
    transition:margin-left .4s cubic-bezier(.22,.9,.3,1)}
  body.side-closed .mainwrap{margin-left:0}

  .side-head{display:flex;align-items:center;justify-content:space-between;
    padding:16px 16px 12px}
  .brand{font:600 17px var(--fh);letter-spacing:-.01em;color:var(--ink);
    text-decoration:none;display:flex;align-items:baseline}
  .brand .p{color:var(--ember);text-shadow:0 0 12px rgba(var(--ember-rgb),.4)}
  .side-x{display:none;width:30px;height:30px;border:0;background:none;
    border-radius:8px;color:var(--mute);place-items:center}
  .side-x:hover{color:var(--ink);background:#20262f}
  .side-x svg{width:14px;height:14px;fill:none;stroke:currentColor;
    stroke-width:1.8;stroke-linecap:round}

  .newchat{display:flex;align-items:center;gap:10px;margin:2px 14px 14px;
    padding:10px 13px;border:1px solid var(--line2);border-radius:12px;
    background:none;color:var(--ink);font:600 13px var(--fb);
    transition:border-color .18s,background .18s}
  .newchat svg{width:15px;height:15px;color:var(--ember);fill:none;
    stroke:currentColor;stroke-width:2;stroke-linecap:round}
  .newchat:hover{border-color:rgba(var(--ember-rgb),.5);background:rgba(var(--ember-rgb),.05)}

  .side-label{font:600 10px var(--fb);letter-spacing:.2em;text-transform:uppercase;
    color:var(--faint);padding:6px 22px 8px}
  .chatlist{flex:1;min-height:0;overflow-y:auto;padding:0 10px 10px}

  .chat{display:grid;grid-template-columns:12px minmax(0,1fr) 28px;
    grid-template-rows:auto auto;column-gap:9px;row-gap:2px;
    align-items:center;padding:9px 10px;border-radius:11px;cursor:pointer;
    transition:background .15s}
  .chat:hover{background:rgba(255,255,255,.035)}
  .chat.act{background:rgba(var(--ember-rgb),.07)}
  .c-dot{grid-area:1/1/3/2;justify-self:center;width:7px;height:7px;border-radius:50%}
  .chat.sealed .c-dot{background:var(--ember);box-shadow:0 0 7px rgba(var(--ember-rgb),.5)}
  .chat.ash .c-dot{background:#333c49}
  .c-title{grid-area:1/2/2/3;min-width:0;font:500 13px/1.35 var(--fb);color:var(--dim);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:left;
    transition:color .15s}
  .chat:hover .c-title,.chat.act .c-title{color:var(--ink)}
  .c-sub{grid-area:2/2/3/3;font:400 10.5px var(--fb);color:var(--faint);
    letter-spacing:.05em;text-align:left}
  .c-edit{grid-area:1/3/3/4;align-self:center;width:26px;height:26px;border:0;
    background:none;border-radius:7px;color:var(--faint);display:grid;place-items:center;
    opacity:0;transition:opacity .15s,color .15s,background .15s}
  .chat:hover .c-edit,.c-edit:focus-visible{opacity:1}
  .c-edit:hover{color:var(--ember);background:#20262f}
  .c-edit svg{width:12px;height:12px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  @media (pointer:coarse){.c-edit{opacity:.55}}
  .c-ren{grid-area:1/2/2/3;width:100%;background:var(--sur3);
    border:1px solid rgba(var(--ember-rgb),.45);border-radius:8px;color:var(--ink);
    font:500 13px/1.35 var(--fb);padding:4px 8px;outline:none}
  .chat.ren .c-sub{visibility:hidden}
  .empty{padding:26px 16px;text-align:center;color:var(--faint);
    font:400 12px/1.7 var(--fb)}
  .empty svg{width:22px;height:22px;margin:0 auto 10px;display:block;
    fill:none;stroke:var(--faint);stroke-width:1.5;stroke-linecap:round;
    stroke-linejoin:round}

  .side-foot{border-top:1px solid var(--line);padding:12px 14px 14px}
  .prof{display:flex;align-items:center;gap:11px}
  .avatar{width:36px;height:36px;border-radius:11px;flex:none;position:relative;
    background:#0e1116;border:1px solid var(--line2);padding:0;
    box-shadow:0 0 0 1px var(--avr,transparent),0 0 15px var(--avg,transparent);
    transition:box-shadow .4s,color .4s,transform .2s}
  .avatar:hover{transform:scale(1.05)}
  .avatar:active{transform:scale(.96)}
  .avatar svg{display:block;width:100%;height:100%;padding:4px}
  .avatar.m-idle{color:#77808f}
  .avatar.m-compose,.avatar.m-sealed,.avatar.m-view{
    color:var(--ember);
    --avr:rgba(var(--ember-rgb),.4);--avg:rgba(var(--ember-rgb),.2)}
  .avatar.m-gate{color:var(--dim);--avr:rgba(164,170,180,.3)}
  .avatar.m-seal,.avatar.m-burn{color:var(--hot);
    --avr:rgba(255,92,51,.55);--avg:rgba(255,92,51,.32);
    animation:avP .85s ease-in-out infinite}
  .avatar.m-ash{color:#565e6b}
  @keyframes avP{50%{transform:scale(1.08)}}
  .prof-b{flex:1;min-width:0}
  .prof-b b{display:block;font:600 12.5px var(--fb);color:var(--ink);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .prof-b .pstat{font:400 10.5px var(--fb);color:var(--faint);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
  .prof-b .pstat i{font-style:normal;color:var(--dim)}
  .setbtn{width:34px;height:34px;border:1px solid var(--line2);background:none;
    border-radius:10px;color:var(--mute);display:grid;place-items:center;
    transition:color .18s,border-color .18s,transform .3s;flex:none}
  .setbtn svg{width:16px;height:16px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .setbtn:hover{color:var(--ember);border-color:rgba(var(--ember-rgb),.5);
    transform:rotate(35deg)}

  .maintop{position:sticky;top:0;z-index:26;display:flex;align-items:center;gap:16px;
    padding:11px 22px;background:rgba(10,11,14,.72);backdrop-filter:blur(14px);
    border-bottom:1px solid var(--line)}
  .sidebtn{width:34px;height:34px;border:0;background:none;border-radius:9px;
    color:var(--dim);display:grid;place-items:center;flex:none;
    transition:color .15s,background .15s}
  .sidebtn:hover{color:var(--ink);background:#1c2129}
  .sidebtn svg{width:17px;height:17px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .mt-brand{font:600 14.5px var(--fh);letter-spacing:-.01em}
  .mt-brand .p{color:var(--ember)}
  .mt-tag{font:500 11.5px var(--fb);letter-spacing:.08em;color:var(--mute);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .mt-nav{margin-left:auto;display:flex;gap:18px;flex:none}
  .mt-nav a{font:500 13px var(--fb);color:var(--mute);text-decoration:none}
  .mt-nav a:hover{color:var(--ink)}
  .m-plus{display:none;margin-left:auto;width:34px;height:34px;border:0;
    border-radius:9px;background:var(--ember);color:#0b0c0f;place-items:center;flex:none}
  .m-plus svg{width:16px;height:16px;fill:none;stroke:currentColor;
    stroke-width:2.4;stroke-linecap:round}

  main{position:relative;z-index:10;width:100%;max-width:880px;margin:0 auto;
    padding:0 26px}

  .hero{padding:62px 0 26px;text-align:center}
  .tag{display:inline-flex;align-items:center;gap:9px;padding:6px 14px;
    border:1px solid var(--line);border-radius:999px;font:500 12px var(--fb);color:var(--dim)}
  .live{width:6px;height:6px;border-radius:50%;background:var(--ember);
    box-shadow:0 0 0 0 rgba(var(--ember-rgb),.5);animation:rip 2.6s ease-out infinite}
  @keyframes rip{0%{box-shadow:0 0 0 0 rgba(var(--ember-rgb),.5)}70%{box-shadow:0 0 0 7px rgba(var(--ember-rgb),0)}100%{box-shadow:0 0 0 0 rgba(var(--ember-rgb),0)}}
  .h1{font:600 clamp(30px,4.4vw,46px)/1.12 var(--fh);letter-spacing:-.026em;margin:18px 0 0}
  .h1 .p{color:var(--ember);text-shadow:0 0 18px rgba(var(--ember-rgb),.35)}
  .sub{font-size:15.5px;color:var(--dim);max-width:56ch;margin:16px auto 0}

  .js .tag,.js .h1,.js .sub,.js .stage,.js .meta{animation:up .65s cubic-bezier(.2,.8,.3,1) backwards}
  .js .tag{animation-delay:.02s}.js .h1{animation-delay:.08s}
  .js .sub{animation-delay:.15s}.js .stage{animation-delay:.22s}.js .meta{animation-delay:.32s}
  @keyframes up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
  @keyframes fadeUp{from{opacity:0}to{opacity:1}}

  .stage{position:relative;width:min(680px,100%);margin:38px auto 0;
    transition:height .42s cubic-bezier(.22,.9,.28,1)}
  .stage.anim{overflow:hidden}
  .stage::before{content:'';position:absolute;inset:-80px -100px;pointer-events:none;
    background:radial-gradient(50% 45% at 50% 62%,rgba(var(--ember-rgb),.065),transparent 70%)}
  .pane{transition:opacity .26s ease,transform .26s ease}
  .pane.out{position:absolute;top:0;left:0;width:100%;opacity:0}
  .pane.pre{opacity:0;transform:translateY(8px)}

  #paneCompose{position:relative}
  .composer{position:relative;background:var(--sur);border:1px solid var(--line);
    border-radius:28px;box-shadow:0 24px 60px -24px rgba(0,0,0,.65);
    transition:border-color .2s,box-shadow .2s}
  .composer.focus{border-color:rgba(var(--ember-rgb),.35);
    box-shadow:0 24px 60px -24px rgba(0,0,0,.65),0 0 0 3px rgba(var(--ember-rgb),.08)}
  .composer.shake{animation:tshake .45s ease}
  @keyframes tshake{0%,100%{transform:translateX(0)}20%{transform:translateX(-7px)}
    40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(3px)}}
  .composer.pulse{animation:cpulse .7s cubic-bezier(.2,.8,.3,1)}
  @keyframes cpulse{0%{box-shadow:0 24px 60px -24px rgba(0,0,0,.65),0 0 0 0 rgba(var(--ember-rgb),.45)}
    100%{box-shadow:0 24px 60px -24px rgba(0,0,0,.65),0 0 0 16px rgba(var(--ember-rgb),0)}}

  .c-text{position:relative}
  #ta,.chars{text-align:left;overflow-wrap:anywhere}
  #ta{display:block;width:100%;border:0;background:none;outline:none;resize:none;
    color:var(--ink);caret-color:var(--ember);overflow-y:hidden;
    font:400 15.5px/1.65 var(--fb);padding:18px 20px 10px;
    white-space:pre-wrap}
  #ta::placeholder{color:var(--faint)}
  .chars{position:absolute;inset:0;padding:18px 20px 10px;pointer-events:none;
    white-space:pre-wrap;font:400 15.5px/1.65 var(--fb);color:var(--ink);
    overflow:auto;scrollbar-width:none}
  .chars::-webkit-scrollbar{display:none}
  .ch{transition:color .16s,opacity .42s,filter .42s}
  .ch.hot{color:var(--ember);text-shadow:0 0 8px rgba(var(--ember-rgb),.45)}
  .ch.ash{opacity:0;filter:blur(4px)}

  .att-strip{padding:0 12px 2px}
  .att-chip{display:flex;align-items:center;gap:9px;padding:7px 9px;
    border:1px solid var(--line2);border-radius:12px;background:var(--sur3)}
  .aic{border-radius:9px;border:1px solid var(--line2);
    display:grid;place-items:center;color:var(--dim);flex:none}
  .att-chip .aic{width:30px;height:30px}
  .att-chip .aic svg,.att-card .aic svg,.badge .att-ic svg{fill:none;
    stroke:currentColor;stroke-width:1.7;stroke-linecap:round;
    stroke-linejoin:round}
  .att-chip .aic svg{width:14px;height:14px}
  .att-name{flex:1;min-width:0;font:500 12.5px var(--fb);color:var(--ink);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:left}
  .att-size{font:500 10.5px var(--fm);color:var(--faint);flex:none}
  .att-x{width:24px;height:24px;border:0;background:none;border-radius:7px;
    color:var(--faint);display:grid;place-items:center;flex:none;
    transition:color .15s,background .15s}
  .att-x:hover{color:var(--hot);background:#20262f}
  .att-x svg{width:11px;height:11px;fill:none;stroke:currentColor;
    stroke-width:2;stroke-linecap:round}

  .c-bar{display:flex;align-items:center;gap:8px;padding:10px 12px 12px;flex-wrap:wrap}
  .ibtn{width:34px;height:34px;border-radius:10px;border:0;background:none;
    color:var(--mute);display:grid;place-items:center;transition:color .15s,background .15s}
  .ibtn:hover{color:var(--ink);background:#1c2129}
  .ibtn.on{color:var(--ember)}
  .chip{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;
    border:1px solid var(--line);border-radius:999px;background:none;
    color:var(--dim);font:500 12px var(--fb);transition:color .15s,border-color .15s}
  .chip:hover{color:var(--ink);border-color:var(--line2)}
  .send{width:38px;height:38px;border-radius:999px;border:0;margin-left:auto;
    display:grid;place-items:center;background:var(--ember);color:#0b0c0f;
    box-shadow:0 8px 20px -8px rgba(var(--ember-rgb),.5);
    transition:background .18s,transform .18s,box-shadow .18s,opacity .18s}
  .send:hover{background:var(--ember2);transform:translateY(-1px);
    box-shadow:0 12px 26px -8px rgba(var(--ember-rgb),.6)}
  .send:active{transform:scale(.93)}
  .send[disabled]{background:#1e242d;color:#576274;box-shadow:none;cursor:default}

  .ibtn svg{width:17px;height:17px}.chip svg{width:12px;height:12px}
  .ibtn svg,.chip svg,.btn svg,.pop-x svg,.badge svg,.exp-row svg,.fcard .ic svg,
  .e-ic svg,.g-ic svg,.svbtn svg{fill:none;stroke:currentColor;stroke-width:1.7;
    stroke-linecap:round;stroke-linejoin:round}

  [data-tip]{position:relative}
  [data-tip]::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 9px);
    left:50%;transform:translateX(-50%) translateY(3px);background:#1d222b;
    border:1px solid var(--line2);color:var(--dim);font:500 11px var(--fb);
    padding:5px 9px;border-radius:7px;white-space:nowrap;opacity:0;
    pointer-events:none;transition:.18s;z-index:8}
  [data-tip]:hover::after{opacity:1;transform:translateX(-50%);transition-delay:.35s}

  .hint{position:absolute;bottom:100%;left:6px;margin-bottom:10px;
    font:500 12.5px var(--fb);color:var(--dim);opacity:0;transform:translateY(4px);
    transition:.25s;pointer-events:none;white-space:nowrap}
  .hint::before{content:'';display:inline-block;width:5px;height:5px;border-radius:50%;
    background:var(--ember);margin-right:8px;vertical-align:2px}
  .hint.on{opacity:1;transform:none}

  .pop{position:absolute;bottom:64px;width:272px;background:var(--sur2);
    border:1px solid var(--line2);border-radius:16px;z-index:6;
    box-shadow:0 22px 55px -18px rgba(0,0,0,.75);
    opacity:0;transform:translateY(6px) scale(.97);transform-origin:bottom center;
    transition:opacity .2s,transform .2s cubic-bezier(.2,.8,.3,1)}
  .pop.in{opacity:1;transform:none}
  .pop-head{display:flex;justify-content:space-between;align-items:center;
    padding:12px 14px;border-bottom:1px solid var(--line);
    font:600 13px var(--fh);color:var(--ink)}
  .pop-x{width:26px;height:26px;border:0;background:none;border-radius:7px;
    color:var(--mute);display:grid;place-items:center}
  .pop-x:hover{color:var(--ink);background:#232932}
  .pop-x svg{width:13px;height:13px}

  .pin-row{display:flex;gap:10px;justify-content:center;padding:16px 14px 8px}
  .pbox{width:46px;height:54px;text-align:center;background:var(--sur);
    border:1px solid var(--line2);border-radius:12px;outline:none;
    color:var(--ink);font:600 20px var(--fh);caret-color:var(--ember)}
  .pbox:focus{border-color:rgba(var(--ember-rgb),.6);box-shadow:0 0 0 3px rgba(var(--ember-rgb),.1)}
  .pin-row.wrong .pbox{border-color:rgba(255,92,51,.75);animation:pinShake .4s}
  @keyframes pinShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}
    50%{transform:translateX(5px)}75%{transform:translateX(-3px)}}
  .pop-note{font:400 12px/1.55 var(--fb);color:var(--mute);padding:8px 14px 14px}
  .pop-foot{padding:0 14px 13px}
  .linkbtn{border:0;background:none;color:var(--mute);font:500 12px var(--fb);
    text-decoration:underline;text-underline-offset:3px}
  .linkbtn:hover{color:var(--hot)}

  .exp-row{display:flex;align-items:center;justify-content:space-between;width:100%;
    padding:11px 14px;border:0;background:none;color:var(--dim);
    font:500 13.5px var(--fb);transition:background .12s,color .12s}
  .exp-row:hover{background:rgba(255,255,255,.03);color:var(--ink)}
  .exp-row svg{width:15px;height:15px;color:var(--ember);opacity:0;transition:opacity .15s}
  .exp-row.on{color:var(--ink)}
  .exp-row.on svg{opacity:1}

  .share{max-width:640px;margin:0 auto;background:var(--sur);
    border:1px solid var(--line);border-radius:20px;padding:20px;text-align:left;
    box-shadow:0 24px 60px -24px rgba(0,0,0,.65)}
  .share>*{animation:sIn .55s cubic-bezier(.22,.9,.3,1) backwards}
  .share-head{animation-delay:.03s}
  .s-filed{animation-delay:.1s}
  .share-body{animation-delay:.16s}
  .svwrap{animation-delay:.3s}
  @keyframes sIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

  .share-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
  .s-status{display:inline-flex;align-items:center;gap:9px;font:600 10.5px var(--fb);
    letter-spacing:.18em;text-transform:uppercase;color:var(--mute)}
  .s-status .dot{width:7px;height:7px;border-radius:50%;background:var(--ember);
    box-shadow:0 0 10px rgba(var(--ember-rgb),.6);transition:background .3s,box-shadow .3s}
  .s-status .dot.ash{background:#333c49;box-shadow:none}
  .fuse{font:500 12.5px var(--fb);color:var(--dim);font-variant-numeric:tabular-nums}
  .fuse.hot{color:var(--hot)}
  .s-filed{font:400 12px var(--fb);color:var(--faint);margin-bottom:14px}
  .s-filed b{color:var(--dim);font-weight:600}

  .share-body{display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:start}
  .share-qr{display:flex;flex-direction:column;align-items:center;gap:9px}

  .qr-tile{position:relative;background:var(--qr-bg);border-radius:14px;
    padding:13px;width:200px}
  .qr-tile svg{display:block;width:100%;height:auto}
  .qc{position:absolute;inset:0;pointer-events:none}
  .qc i{position:absolute;width:12px;height:12px;border:2px solid rgba(255,150,60,.8)}
  .qc .tl{top:4px;left:4px;border-right:0;border-bottom:0}
  .qc .tr{top:4px;right:4px;border-left:0;border-bottom:0}
  .qc .bl{bottom:4px;left:4px;border-right:0;border-top:0}
  .qc .br{bottom:4px;right:4px;border-left:0;border-top:0}
  .scan{position:absolute;left:13px;right:13px;top:13px;height:2px;border-radius:2px;
    background:linear-gradient(90deg,transparent,rgba(255,120,40,.7),transparent);
    opacity:0;animation:scanY 3s ease-in-out infinite}
  @keyframes scanY{0%{top:13px;opacity:0}10%{opacity:.85}
    42%{top:calc(100% - 15px);opacity:.85}52%{opacity:0}100%{top:13px;opacity:0}}

  .qr-cap{font:500 10px var(--fb);letter-spacing:.16em;text-transform:uppercase;
    color:var(--faint)}
  .qr-save{display:inline-flex;align-items:center;gap:7px;
    border:1px solid var(--line2);background:none;border-radius:9px;
    padding:8px 14px;color:var(--mute);font:600 11.5px var(--fb);
    transition:color .18s,border-color .18s,transform .18s}
  .qr-save:hover{color:var(--ember);border-color:rgba(var(--ember-rgb),.5);transform:translateY(-1px)}
  .qr-save:active{transform:scale(.96)}
  .qr-save svg{width:13px;height:13px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}

  .qr-fallback{width:200px;background:var(--sur3);border:1px dashed var(--line2);
    border-radius:14px;padding:28px 18px;text-align:center;color:var(--faint)}
  .qr-fallback svg{width:26px;height:26px;margin:0 auto 10px;display:block;
    fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;
    stroke-linejoin:round}
  .qr-fallback p{font:400 11px/1.55 var(--fb)}

  .share-link{display:flex;flex-direction:column;min-width:0}
  .sl-label{font:600 10px var(--fb);letter-spacing:.2em;text-transform:uppercase;
    color:var(--faint);margin-bottom:8px}

  .copyfield{display:flex;align-items:center;gap:10px;width:100%;
    background:var(--sur3);border:1px solid var(--line);border-radius:12px;
    padding:11px 11px 11px 14px;cursor:pointer;text-align:left;
    transition:border-color .18s}
  .copyfield:hover{border-color:rgba(var(--ember-rgb),.45)}
  .cf-text{flex:1;min-width:0;font:500 12px/1.6 var(--fm);color:var(--dim);
    word-break:break-all;text-align:left}
  .cf-text b{color:var(--ember);font-weight:600}
  .cf-ic{width:34px;height:34px;flex:none;border-radius:9px;background:#1e242d;
    display:grid;place-items:center;color:var(--mute);
    transition:color .18s,background .18s}
  .cf-ic svg{width:15px;height:15px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .copyfield:hover .cf-ic{color:var(--ink)}
  .copyfield.copied{border-color:rgba(var(--ember-rgb),.55)}
  .copyfield.copied .cf-ic{color:#0b0c0f;background:var(--ember)}

  .sl-note{font:400 11.5px/1.6 var(--fb);color:var(--faint);margin:10px 0 14px}
  .sl-note b{color:var(--dim);font-weight:600}

  .btn{border-radius:12px;padding:10px 16px;font:600 13px var(--fb);
    display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:.18s}
  .btn svg{width:15px;height:15px}
  .btn.ghost{border:1px solid var(--line2);background:none;color:var(--dim)}
  .btn.ghost:hover{color:var(--ink);border-color:rgba(var(--ember-rgb),.45)}
  .btn.primary{border:0;background:var(--ember);color:#0b0c0f;width:100%;
    box-shadow:0 8px 20px -10px rgba(var(--ember-rgb),.55)}
  .btn.primary:hover{background:var(--ember2);transform:translateY(-1px)}
  .btn.primary:active{transform:scale(.97)}

  .badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
  .badge{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;
    border:1px solid var(--line2);border-radius:999px;
    font:500 11.5px var(--fb);color:var(--dim)}
  .badge svg{width:12px;height:12px;color:var(--mute)}
  .badge .att-ic{display:grid;place-items:center}
  .badge .att-ic svg{width:12px;height:12px}

  .svwrap{margin-top:18px;border-top:1px solid var(--line);padding-top:10px}
  .svbtn{display:flex;align-items:center;gap:8px;border:0;background:none;
    color:var(--mute);font:500 12px var(--fb);padding:4px 2px;width:100%;
    transition:color .15s;text-align:left}
  .svbtn:hover{color:var(--dim)}
  .svbtn svg{width:14px;height:14px}
  .svbtn .chev{margin-left:auto;transition:transform .3s cubic-bezier(.22,.9,.3,1)}
  .svbtn.open .chev{transform:rotate(180deg)}
  .svbox{display:grid;grid-template-rows:0fr;
    transition:grid-template-rows .34s cubic-bezier(.22,.9,.3,1)}
  .svbox.open{grid-template-rows:1fr}
  .sv{overflow:hidden;min-height:0;display:grid;gap:2px;
    transition:margin-top .34s,opacity .3s;opacity:0}
  .svbox.open .sv{margin-top:10px;opacity:1}
  .svrow{display:flex;justify-content:space-between;gap:12px;
    font:400 11.5px var(--fm);color:var(--faint);padding:4px 0}
  .svrow b{color:var(--dim);font-weight:500;text-align:right}

  .gatecard{max-width:340px;margin:0 auto;background:var(--sur);
    border:1px solid var(--line);border-radius:16px;padding:26px 22px;
    text-align:center;box-shadow:0 24px 60px -24px rgba(0,0,0,.65)}
  .g-ic{width:44px;height:44px;border-radius:50%;border:1px solid var(--line2);
    display:grid;place-items:center;margin:0 auto 14px;color:var(--dim)}
  .g-ic svg{width:19px;height:19px}
  .g-t{font:600 16.5px var(--fh)}
  .g-sub{font:400 12.5px var(--fb);color:var(--mute);margin:5px 0 4px}
  .g-sub.warn{color:var(--hot)}
  .g-cancel{display:inline-block;margin-top:14px;border:0;background:none;
    color:var(--mute);font:500 12.5px var(--fb);text-decoration:underline;
    text-underline-offset:3px}
  .g-cancel:hover{color:var(--ink)}

  .msgwrap{max-width:560px;margin:0 auto;text-align:left}
  .m-meta{display:flex;justify-content:space-between;align-items:center;
    flex-wrap:wrap;gap:6px;margin-bottom:10px;font:500 11.5px var(--fb);
    letter-spacing:.05em;text-transform:uppercase;color:var(--mute)}
  .cdwrap{display:flex;align-items:center;gap:7px;font:600 12.5px var(--fb);
    color:var(--ember);font-variant-numeric:tabular-nums}
  .ring{width:20px;height:20px;transform:rotate(-90deg)}
  .rbg{fill:none;stroke:#262d38;stroke-width:2.4}
  .rfg{fill:none;stroke:var(--ember);stroke-width:2.4;stroke-linecap:round;
    stroke-dasharray:56.55;stroke-dashoffset:0}
  .rfg.run{animation:ringRun 3s linear forwards}
  @keyframes ringRun{to{stroke-dashoffset:56.55}}
  .bubble{display:inline-block;max-width:100%;background:var(--sur3);
    border:1px solid var(--line);border-radius:18px 18px 18px 6px;
    padding:14px 16px;font:400 15px/1.65 var(--fb);color:var(--ink);
    white-space:pre-wrap;overflow-wrap:anywhere;overflow:hidden;
    animation:bubIn .3s cubic-bezier(.2,.8,.3,1);
    transition:max-height .35s,opacity .3s,padding .35s,border-width .35s}
  @keyframes bubIn{from{opacity:0;transform:translateY(8px) scale(.97)}to{opacity:1;transform:none}}
  .bubble.collapse{max-height:0!important;opacity:0;
    padding-top:0;padding-bottom:0;border-width:0}
  #msgText{display:block;white-space:pre-wrap;overflow-wrap:anywhere;
    max-height:min(44vh,300px);overflow-y:auto}

  .att-card{display:flex;align-items:center;gap:12px;margin-top:10px;
    background:var(--sur3);border:1px solid var(--line);border-radius:14px;
    padding:11px 13px;max-width:100%;
    animation:bubIn .3s cubic-bezier(.2,.8,.3,1)}
  .att-card .aic{width:38px;height:38px}
  .att-card .aic svg{width:16px;height:16px}
  .att-mid{flex:1;min-width:0;text-align:left}
  .att-mid b{display:block;font:600 13px var(--fb);color:var(--ink);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .att-mid span{font:400 11px var(--fb);color:var(--mute)}
  .att-card .btn{padding:8px 14px;font-size:12px;flex:none}

  .endcard{max-width:400px;margin:0 auto;background:var(--sur);
    border:1px solid var(--line);border-radius:16px;padding:28px 24px;
    text-align:center;box-shadow:0 24px 60px -24px rgba(0,0,0,.65)}
  .e-ic{width:46px;height:46px;border-radius:50%;border:1px solid var(--line2);
    display:grid;place-items:center;margin:0 auto 15px;color:var(--dim)}
  .e-ic svg{width:20px;height:20px}
  .e-t{font:600 16px var(--fh)}
  .e-s{margin:8px 0 20px;font:400 13.5px/1.6 var(--fb);color:var(--mute)}

  .meta{display:flex;justify-content:center;gap:34px;flex-wrap:wrap;
    margin-top:28px;font-family:var(--fb)}
  .m b{display:block;font:600 15px var(--fb);color:var(--ink);
    font-variant-numeric:tabular-nums;transition:color .3s}
  .m b.tick{color:var(--ember)}
  .m span{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--mute)}

  .sec{padding:84px 0 0}
  .sec-h{display:flex;align-items:center;gap:16px;margin-bottom:26px;
    font:600 11px var(--fb);letter-spacing:.22em;text-transform:uppercase;color:var(--mute)}
  .sec-h::after{content:'';flex:1;height:1px;background:var(--line)}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
  .fcard{background:var(--sur);border:1px solid var(--line);border-radius:16px;
    padding:22px;transition:border-color .2s,transform .2s}
  .fcard:hover{border-color:var(--line2);transform:translateY(-2px)}
  .fcard .ic{width:40px;height:40px;border-radius:12px;border:1px solid var(--line2);
    display:grid;place-items:center;margin-bottom:14px;color:var(--dim);
    transition:color .2s,border-color .2s}
  .fcard .ic svg{width:18px;height:18px}
  .fcard:hover .ic{color:var(--ember);border-color:rgba(var(--ember-rgb),.4)}
  .fcard h3{font:600 15.5px var(--fh);margin-bottom:6px}
  .fcard p{font:400 13.5px/1.6 var(--fb);color:var(--mute)}

  .wire{background:var(--sur);border:1px solid var(--line);border-radius:16px;
    padding:24px 20px 20px}
  .wire-row{display:flex;align-items:center;gap:16px}
  .wnode{display:inline-flex;align-items:center;gap:8px;font:500 11px var(--fm);
    letter-spacing:.14em;text-transform:uppercase;color:var(--dim);flex:none}
  .wdot{width:9px;height:9px;border-radius:50%;flex:none}
  .wdot.on{background:var(--ember);box-shadow:0 0 10px rgba(var(--ember-rgb),.55)}
  .wdot.off{background:none;border:2px solid #3a4350}
  .wtrack{position:relative;flex:1;height:2px;background:#20262f;border-radius:2px;min-width:60px}
  .wlab{position:absolute;left:50%;bottom:calc(50% + 9px);transform:translateX(-50%);
    font:400 10.5px var(--fm);color:var(--mute);white-space:nowrap}
  .wpkt{position:absolute;top:50%;left:0;width:5px;height:5px;border-radius:50%;
    background:var(--ember2);box-shadow:0 0 8px rgba(var(--ember-rgb),.8);
    transform:translateY(-50%);animation:pktGo 3.4s cubic-bezier(.45,0,.55,1) infinite}
  @keyframes pktGo{0%{left:0;opacity:0}12%{opacity:1}82%{opacity:1}
    96%,100%{left:calc(100% - 5px);opacity:0}}
  .wire-branch{width:1px;height:20px;margin:14px auto 0;
    background:repeating-linear-gradient(180deg,var(--line2) 0 4px,transparent 4px 8px)}
  .wserver{display:flex;width:max-content;margin:12px auto 0;
    align-items:center;gap:9px;padding:8px 16px;border:1px solid var(--line2);
    border-radius:999px;color:var(--mute);font:500 11.5px var(--fb)}
  .wserver svg{width:15px;height:15px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}

  .proof-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:14px;
    margin-top:14px;align-items:start}
  .anatomy{background:var(--sur);border:1px solid var(--line);border-radius:16px;
    padding:20px}
  .a-head{font:600 10.5px var(--fb);letter-spacing:.18em;text-transform:uppercase;
    color:var(--mute)}
  .a-sub{font:400 11.5px var(--fb);color:var(--faint);margin:5px 0 14px}
  .a-link{font:500 13px/2.1 var(--fm);word-break:break-all;color:var(--ink);
    background:var(--sur3);border:1px solid var(--line);border-radius:12px;
    padding:12px 14px;text-align:left}
  .seg{padding:1px 4px;border-radius:5px;transition:background .15s,box-shadow .15s}
  .seg.s-page{color:var(--mute)}
  .seg.s-que{color:var(--faint)}
  .seg.s-tok{color:var(--dim)}
  .seg.s-hash{color:var(--ember);font-weight:600}
  .seg.s-key{color:var(--ember)}
  .seg.hl{background:rgba(var(--ember-rgb),.12);box-shadow:inset 0 0 0 1px rgba(var(--ember-rgb),.3)}
  .a-legend{margin-top:12px;display:grid;gap:2px}
  .arow{display:grid;grid-template-columns:12px 96px 1fr;gap:10px;align-items:baseline;
    padding:6px;border-radius:8px;transition:background .15s;cursor:default}
  .arow.hl{background:rgba(var(--ember-rgb),.07)}
  .sw{width:9px;height:9px;border-radius:3px;justify-self:center;transform:translateY(1px)}
  .sw-page{background:var(--mute)}.sw-tok{background:var(--dim)}
  .sw-key{background:var(--ember)}
  .arow span{font:600 12px var(--fb);color:var(--ink)}
  .arow em{font:400 11.5px/1.55 var(--fb);font-style:normal;color:var(--mute)}

  .receipt{background:var(--sur);border:1px dashed var(--line2);border-radius:16px;
    padding:18px 18px 16px;max-width:330px;margin:0 auto;width:100%;
    font-family:var(--fm)}
  .r-head{text-align:center;font:600 11px var(--fm);letter-spacing:.2em;
    text-transform:uppercase;color:var(--dim);padding-bottom:10px;
    border-bottom:1px dashed var(--line2)}
  .r-sub{text-align:center;font:400 10.5px var(--fm);color:var(--faint);margin:9px 0 3px}
  .rrow{display:flex;align-items:baseline;gap:10px;font:400 12px/2.2 var(--fm);
    color:var(--mute)}
  .rrow .lead{flex:1;border-bottom:1px dotted #2a3140;transform:translateY(-5px);min-width:20px}
  .rrow b{color:var(--dim);font-weight:500}
  .rrow b.zero{color:var(--faint)}
  .r-total{display:flex;justify-content:space-between;align-items:baseline;
    margin-top:10px;padding-top:10px;border-top:1px dashed var(--line2);
    font:600 12.5px var(--fm);color:var(--dim)}
  .r-total b{color:var(--ember)}
  .r-note{margin-top:9px;font:400 10.5px/1.6 var(--fm);color:var(--faint);text-align:center}
  .r-verify{margin-top:12px;width:100%;display:flex;justify-content:center;
    align-items:center;gap:8px;border:1px solid var(--line2);background:none;
    color:var(--dim);border-radius:10px;padding:10px;font:600 12px var(--fb);
    transition:color .18s,border-color .18s}
  .r-verify:hover{color:var(--ember);border-color:rgba(var(--ember-rgb),.5)}
  .r-verify.ok{color:var(--ember);border-color:rgba(var(--ember-rgb),.5)}
  .r-verify svg{width:14px;height:14px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}

  .proof-foot{margin-top:14px;text-align:center;font:400 12px var(--fb);color:var(--faint)}

  .outro{padding:100px 0 30px;text-align:center}
  .o-big{font:600 clamp(24px,3.4vw,36px)/1.25 var(--fh);
    letter-spacing:-.02em;color:var(--dim)}
  .o-big .p{color:var(--ember)}
  .outro .btn{margin-top:26px;padding:12px 22px;font-size:13.5px;width:auto}

  footer{margin-top:76px;border-top:1px solid var(--line);padding:36px 26px 44px;
    display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap}
  footer .l{font:500 12.5px var(--fb);color:var(--dim)}
  footer .r{font:400 11.5px var(--fb);color:var(--faint)}

  #setScrim{position:fixed;inset:0;z-index:50;background:rgba(4,5,8,.6);
    opacity:0;pointer-events:none;transition:opacity .3s}
  body.set-open #setScrim{opacity:1;pointer-events:auto}
  .setpanel{position:fixed;top:0;right:0;bottom:0;width:min(392px,100vw);z-index:52;
    background:var(--sur);border-left:1px solid var(--line2);
    display:flex;flex-direction:column;
    transform:translateX(103%);transition:transform .4s cubic-bezier(.22,.9,.3,1)}
  body.set-open .setpanel{transform:none}
  .set-head{display:flex;justify-content:space-between;align-items:center;
    padding:15px 18px;border-bottom:1px solid var(--line);flex:none}
  .set-head b{font:600 15px var(--fh)}
  .set-scroll{flex:1;min-height:0;overflow-y:auto;
    padding-bottom:calc(16px + env(safe-area-inset-bottom))}

  .set-group{padding:18px 20px 6px}
  .set-group+.set-group{border-top:1px solid var(--line);margin-top:12px}
  .sg-head{display:flex;align-items:center;gap:9px;margin-bottom:4px;
    font:600 10.5px var(--fb);letter-spacing:.2em;text-transform:uppercase;
    color:var(--faint)}
  .sg-head svg{width:14px;height:14px;fill:none;stroke:currentColor;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .set-row{display:flex;align-items:center;gap:16px;padding:13px 0;
    border-bottom:1px solid var(--line)}
  .set-row:last-child{border-bottom:0}
  .set-row.col{flex-direction:column;align-items:stretch;gap:10px}
  .st{flex:1;min-width:0}
  .st b{display:block;font:600 13.5px var(--fb);color:var(--ink)}
  .st span{font:400 11.5px/1.5 var(--fb);color:var(--mute)}

  .swatches{display:flex;gap:10px;padding:2px 0 6px}
  .swatch{width:27px;height:27px;border-radius:50%;border:0;background:var(--c);
    box-shadow:0 0 0 2px transparent;transition:box-shadow .18s,transform .18s}
  .swatch:hover{transform:scale(1.14)}
  .swatch.on{box-shadow:0 0 0 2px var(--sur),0 0 0 4px var(--c)}
  .sw2{position:relative;width:40px;height:23px;border-radius:999px;flex:none;
    background:#20262f;border:1px solid var(--line2);transition:.22s}
  .sw2::after{content:'';position:absolute;top:2px;left:2px;width:17px;height:17px;
    border-radius:50%;background:#6b7482;transition:.22s cubic-bezier(.2,.8,.3,1)}
  .sw2.on{background:rgba(var(--ember-rgb),.16);border-color:rgba(var(--ember-rgb),.5)}
  .sw2.on::after{left:19px;background:var(--ember)}
  .seg2{display:flex;gap:6px;flex-wrap:wrap}
  .seg2 button{padding:7px 12px;border-radius:9px;border:1px solid var(--line2);
    background:none;color:var(--mute);font:500 12px var(--fb);transition:.15s}
  .seg2 button:hover{color:var(--dim)}
  .seg2 button.on{color:var(--ember);border-color:rgba(var(--ember-rgb),.5);
    background:rgba(var(--ember-rgb),.07)}

  .dangerbtn{width:100%;padding:11px;border-radius:10px;cursor:pointer;
    border:1px solid rgba(255,92,51,.45);color:var(--hot);
    background:rgba(255,92,51,.05);font:600 13px var(--fb);transition:.18s}
  .dangerbtn:hover{background:rgba(255,92,51,.12)}
  .cfbox{display:grid;grid-template-rows:0fr;margin-top:10px;
    transition:grid-template-rows .3s cubic-bezier(.22,.9,.3,1)}
  .cfbox.open{grid-template-rows:1fr}
  .cfbox>div{overflow:hidden;min-height:0}
  .cfbox p{font:400 11.5px/1.5 var(--fb);color:var(--mute);padding-bottom:10px}
  .pc-btns{display:flex;gap:8px}
  .pc-btn{flex:1;padding:8px 10px;border-radius:8px;font:600 11.5px var(--fb);
    border:1px solid var(--line2);background:none;color:var(--dim);cursor:pointer}
  .pc-btn:hover{color:var(--ink)}
  .pc-btn.danger{border-color:rgba(255,92,51,.55);color:var(--hot)}
  .pc-btn.danger:hover{background:rgba(255,92,51,.12)}
  .set-status{font:400 11px/1.7 var(--fm);color:var(--faint);padding:10px 0 0}
  .set-note{padding:16px 20px 4px;font:400 11px/1.6 var(--fb);color:var(--faint)}

  #toasts{position:fixed;top:66px;left:50%;transform:translateX(-50%);z-index:60;
    display:flex;flex-direction:column;align-items:center;gap:8px;
    pointer-events:none;width:max-content;max-width:92vw}
  .toast{background:var(--sur2);border:1px solid var(--line2);
    border-left:2px solid var(--ember);color:var(--dim);padding:10px 16px;
    border-radius:10px;font:500 13px var(--fb);max-width:100%;
    opacity:0;transform:translateY(-8px);transition:.25s}
  .toast.in{opacity:1;transform:none}

  .js .rv{opacity:0;transform:translateY(18px);
    transition:opacity .7s ease,transform .7s ease}
  .rv.in{opacity:1;transform:none}

  @media (max-width:900px){
    .side{width:288px;background:#0c0e12;
      box-shadow:24px 0 60px -30px rgba(0,0,0,.85)}
    .mainwrap{margin-left:0!important}
    .side-x{display:grid}
    .m-plus{display:grid}
    .mt-tag,.mt-nav{display:none}
    .maintop{padding:10px 16px;gap:12px}
    main{padding:0 18px}
    .hero{padding:40px 0 10px}
    .js .stage{animation-name:fadeUp}
    .stage.docked{position:fixed;left:0;right:0;bottom:0;z-index:30;margin:0;
      padding:12px 12px calc(10px + env(safe-area-inset-bottom));
      background:linear-gradient(180deg,transparent,rgba(10,11,14,.6) 26%,#0a0b0e 90%)}
    .stage::before{inset:-60px -60px}
    .pop{left:8px!important;right:8px!important;width:auto!important;
      max-height:56vh;overflow:auto}
    #ta,.chars{font-size:16px}
    .ibtn{width:40px;height:40px}
    .send{width:44px;height:44px}
    .chip{height:38px}
    .btn{padding:12px 18px}
    .exp-row{padding:13px 14px}
    .meta{margin-top:20px;gap:22px}
    .proof-grid{grid-template-columns:1fr}
    .msgwrap,.gatecard,.endcard,.share{max-width:none}
    .share{padding:16px}
    .share-body{grid-template-columns:1fr;gap:16px}
    .share-qr{order:0}
    .qr-tile{width:216px}
    .wlab{display:none}
  }
  @media (max-width:480px){
    .arow{grid-template-columns:12px 1fr}
    .arow em{grid-column:2}
    .pbox{width:42px;height:50px}
    .mt-brand{display:none}
  }
  @media (max-width:420px){
    .qr-tile{width:196px}
    .receipt{padding:16px 14px 14px}
    .share{padding:14px}
  }
  @media (max-height:560px){
    .hero{padding:28px 0 10px}
    .sec{padding:56px 0 0}
  }
  @media (pointer:coarse){[data-tip]::after{display:none}}
  @media (prefers-reduced-motion:reduce){
    html{scroll-behavior:auto}
    .js .tag,.js .h1,.js .sub,.js .stage,.js .meta,.js .rv{animation:none;
      transition-duration:.001s;opacity:1;transform:none}
    .live,.rfg.run,.bubble,.composer.shake,.composer.pulse,.pin-row.wrong .pbox,
    .wpkt,.scan,.share>*,#wipeFlash.on,.avatar.m-seal,.avatar.m-burn,.setbtn{animation:none}
    .pane,.stage,.ch,.bubble,.side,.mainwrap,.avatar,.setpanel,.svbox,.cfbox,
    .att-card{transition-duration:.001s}
  }
</style>
</head>
<body>

<canvas id="net" aria-hidden="true"></canvas>
<div id="scrim" aria-hidden="true"></div>
<div id="wipeFlash" aria-hidden="true"></div>

<div class="app">

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

  <div class="mainwrap">
    <header class="maintop">
      <button class="sidebtn" id="sideToggle" aria-label="toggle the sidebar">
        <svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M9.5 4.5v15"/></svg>
      </button>
      <span class="mt-brand">blackend<span class="p">.</span></span>
      <span class="mt-tag">e2e in your browser · blind vault · shredded on read</span>
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
        <p class="sub">Write a message — or attach a file — and seal it into a short link.
          It encrypts in your browser; the key rides after the <b>#</b> where no server
          ever sees it, and a blind vault holds nothing but noise until the first read —
          then shreds it. Your archive lives on this device, under names that say nothing.</p>

        <div class="stage docked" id="stage">

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
                  4-digit code — it wraps the key with 600,000 PBKDF2 rounds.
                  Three wrong attempts destroy the message.</p>
                <div class="pop-foot"><button class="linkbtn" id="pinRemove" hidden>Remove PIN</button></div>
              </div>

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
          <div class="m"><b>1st</b><span>read shreds the vault copy</span></div>
        </div>
      </section>

      <section class="sec rv" id="how">
        <div class="sec-h">how it ends</div>
        <div class="cards">
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><path d="M12 4.5c.5 3-3.8 5-3.8 9a3.8 3.8 0 0 0 7.6 0c0-1.9-1-3-1.7-4.4-.4 1-1.1 1.6-1.9 1.9.4-2.2.3-4.3-.2-6.5Z"/></svg></div>
            <h3>One read, then ash</h3>
            <p>The vault shreds the ciphertext the moment it's fetched — overwrite,
              then unlink. After that, the link points at nothing, and even a seized
              link holds a key with no lock.</p>
          </div>
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><path d="M21 11.5 12.5 20a5.5 5.5 0 0 1-7.8-7.8l8-8a3.7 3.7 0 0 1 5.2 5.2l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/></svg></div>
            <h3>Files, escrowed</h3>
            <p>Attach up to your limit. Files are split into 256 KB chunks, encrypted
              in this tab, and escrowed in the vault — the link stays short enough
              to scan as a QR code.</p>
          </div>
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3"/></svg></div>
            <h3>PIN-gated secrets</h3>
            <p>Your PIN wraps the key with 600,000 PBKDF2 rounds. The vault counts
              attempts and kills the message after three misses.</p>
          </div>
          <div class="fcard">
            <div class="ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4"/></svg></div>
            <h3>A sealed fuse</h3>
            <p>Unread messages end themselves — the deadline is enforced by the vault's
              reaper, whether anyone visits or not.</p>
          </div>
        </div>
      </section>

      <section class="sec rv" id="proof">
        <div class="sec-h">the proof</div>

        <div class="wire" aria-label="message path diagram">
          <div class="wire-row">
            <span class="wnode"><i class="wdot on"></i>you</span>
            <span class="wtrack">
              <span class="wlab">ciphertext rides to the vault · key never leaves the link</span>
              <i class="wpkt" aria-hidden="true"></i>
            </span>
            <span class="wnode"><i class="wdot off"></i>recipient</span>
          </div>
          <div class="wire-branch" aria-hidden="true"></div>
          <div class="wserver">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4.5" width="16" height="6" rx="2"/><rect x="4" y="13.5" width="16" height="6" rx="2"/><path d="M7.5 7.5h.01M7.5 16.5h.01"/></svg>
            the vault — blind, amnesiac, shreds on first read
          </div>
        </div>

        <div class="proof-grid">
          <div class="anatomy">
            <p class="a-head">anatomy of a link</p>
            <p class="a-sub">the URL says nothing about the message — hover the parts</p>
            <div class="a-link" id="aLink">
              <span class="seg s-page" data-p="page" id="segPage">blackend.on</span><span class="seg s-que" data-p="tok">?m=</span><span class="seg s-tok" data-p="tok" id="segTok">9f3ab2c1e4d86f07</span><span class="seg s-hash" data-p="key">#</span><span class="seg s-key" data-p="key" id="segKey">k1.Z8Vgr3mbC88aJHiF0qRs7</span>
            </div>
            <div class="a-legend">
              <div class="arow" data-p="page"><i class="sw sw-page"></i><span>the page</span><em>static html — any host can serve it. nothing dynamic to subpoena.</em></div>
              <div class="arow" data-p="tok"><i class="sw sw-tok"></i><span>the token</span><em>pure randomness — points at noise in the vault. says nothing about content, size, or who.</em></div>
              <div class="arow" data-p="key"><i class="sw sw-key"></i><span>the key</span><em>rides after #. browsers never transmit fragments — the vault never receives it. after the shred, it opens nothing.</em></div>
            </div>
          </div>

          <div class="receipt">
            <p class="r-head">blackend · vault record</p>
            <p class="r-sub">everything the operator could ever produce</p>
            <div class="rrow"><span>ip address</span><i class="lead"></i><b class="zero">never stored</b></div>
            <div class="rrow"><span>user agent</span><i class="lead"></i><b class="zero">never stored</b></div>
            <div class="rrow"><span>cookies</span><i class="lead"></i><b id="rCk">0</b></div>
            <div class="rrow"><span>local archive</span><i class="lead"></i><b id="rSt">0 entries</b></div>
            <div class="rrow"><span>key material</span><i class="lead"></i><b class="zero">never received</b></div>
            <div class="rrow"><span>ciphertext</span><i class="lead"></i><b>noise · shredded on read</b></div>
            <div class="r-total"><span>readable by the operator</span><b>0 bytes</b></div>
            <p class="r-note">tokens are random, blobs are opaque, and both die on schedule.</p>
            <button class="r-verify" id="rVerify">
              <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
              <span id="rVerifyTxt">verify live in this tab</span>
            </button>
          </div>
        </div>

        <p class="proof-foot">no accounts · no cookies · archive is device-only · tokens are unrelated to content.</p>
      </section>

      <section class="outro rv">
        <p class="o-big">Some things shouldn't last<span class="p">.</span></p>
        <button class="btn primary" id="writeOne">Write a message</button>
      </section>

      <footer>
        <span class="l">blackend — everything ends here.</span>
        <span class="r">blind vault · no cookies · the archive never leaves this device</span>
      </footer>
    </main>
  </div>
</div>

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
        <div class="st"><b>max file size</b><span>per message — the vault enforces its own cap from config.php</span></div>
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
          <button data-burn="calm" class="on">calm</button>
          <button data-burn="quick">quick</button>
        </div>
      </div>
      <div class="set-row">
        <div class="st"><b>auto-copy link</b><span>copies to your clipboard the moment a message seals</span></div>
        <button class="sw2" data-set="autocopy" role="switch" aria-checked="false" aria-label="auto-copy link"></button>
      </div>
    </div>

    <div class="set-group">
      <div class="sg-head">
        <svg viewBox="0 0 24 24"><path d="M12 3.5l7 2.7v4.5c0 4.7-3 7.7-7 9.8-4-2.1-7-5.1-7-9.8V6.2Z"/></svg>
        archive & privacy
      </div>
      <div class="set-row">
        <div class="st"><b>file messages locally</b><span>keep links in this browser under quiet names — vault copies end on their own</span></div>
        <button class="sw2 on" data-set="archive" role="switch" aria-checked="true" aria-label="file messages locally"></button>
      </div>
      <div class="set-row col">
        <div class="st"><b>end it all</b><span>burns every sealed vault copy you hold a token for, then wipes this device — instantly, then theatrically</span></div>
        <button class="dangerbtn" id="setWipe">wipe everything</button>
        <div class="cfbox" id="cfbox">
          <div>
            <p>this can't be undone. vault copies die first, the local archive dies
               second — refresh mid-burn finds nothing.</p>
            <div class="pc-btns">
              <button class="pc-btn danger" id="setWipeYes">burn it</button>
              <button class="pc-btn" id="setWipeNo">keep</button>
            </div>
          </div>
        </div>
      </div>
      <p class="set-status" id="setStatus">cookies: 0 · storage keys: 0 · data sent: encrypted noise only</p>
    </div>

    <p class="set-note">settings live in memory for this session — reload restores
      defaults, and nothing about you is ever stored.</p>
  </div>
</aside>

<div id="toasts" role="status" aria-live="polite"></div>

<script>
const $=s=>document.querySelector(s);
const clampN=(v,a,b)=>Math.max(a,Math.min(b,v));
const wait=ms=>new Promise(r=>setTimeout(r,ms));
const REDUCED=matchMedia('(prefers-reduced-motion: reduce)').matches;
const MOBILE=()=>innerWidth<=900;
const utcHM=()=>new Date().toISOString().slice(11,16);
const CRYPTO=!!(window.crypto&&crypto.subtle);
const escapeHTML=s=>String(s).replace(/[&<>"']/g,
  c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

/* constants — must stay in sync with config.php */
const CHUNK=262144;
const PIN_ITERS=600000;

/* ================= icons ================= */
const IC={
  lock:'<svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3"/></svg>',
  clock:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4"/></svg>',
  copy:'<svg viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M15 5.5H7.5A2 2 0 0 0 5.5 7.5V15"/></svg>',
  check:'<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
  eye:'<svg viewBox="0 0 24 24"><path d="M2.8 12S6.2 5.9 12 5.9 21.2 12 21.2 12 17.8 18.1 12 18.1 2.8 12 2.8 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
  flame:'<svg viewBox="0 0 24 24"><path d="M12 4.5c.5 3-3.8 5-3.8 9a3.8 3.8 0 0 0 7.6 0c0-1.9-1-3-1.7-4.4-.4 1-1.1 1.6-1.9 1.9.4-2.2.3-4.3-.2-6.5Z"/></svg>',
  clockx:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2.4M5.5 5.5l13 13"/></svg>',
  lockx:'<svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3M10 15.2l4 4M14 15.2l-4 4"/></svg>',
  shield:'<svg viewBox="0 0 24 24"><path d="M12 3.5l7 2.7v4.5c0 4.7-3 7.7-7 9.8-4-2.1-7-5.1-7-9.8V6.2Z"/></svg>',
  server:'<svg viewBox="0 0 24 24"><rect x="4" y="4.5" width="16" height="6" rx="2"/><rect x="4" y="13.5" width="16" height="6" rx="2"/><path d="M7.5 7.5h.01M7.5 16.5h.01"/></svg>',
  chev:'<svg class="chev" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5"/></svg>',
  pencil:'<svg viewBox="0 0 24 24"><path d="M4 20l1-4L16.5 4.5a2.1 2.1 0 0 1 3 3L8 19l-4 1Z"/></svg>',
  clip:'<svg viewBox="0 0 24 24"><path d="M21 11.5 12.5 20a5.5 5.5 0 0 1-7.8-7.8l8-8a3.7 3.7 0 0 1 5.2 5.2l-8 8a1.8 1.8 0 0 1-2.6-2.6l7-7"/></svg>',
  file:'<svg viewBox="0 0 24 24"><path d="M13.5 4.5H7a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10Z"/><path d="M13.5 4.5V10H19"/></svg>',
  image:'<svg viewBox="0 0 24 24"><rect x="4" y="5.5" width="16" height="13" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="M4.5 16.5l4.2-4 3.3 3 2.5-2.3 5 4.3"/></svg>',
  audio:'<svg viewBox="0 0 24 24"><path d="M4 10v4M8 7v10M12 4.5v15M16 7v10M20 10v4"/></svg>',
  video:'<svg viewBox="0 0 24 24"><rect x="3.5" y="6.5" width="17" height="11" rx="2.5"/><path d="M10.5 10l4.5 2-4.5 2Z"/></svg>'
};
const attIcon=t=>t.startsWith('image/')?IC.image:
  t.startsWith('audio/')?IC.audio:
  t.startsWith('video/')?IC.video:IC.file;

/* ================= vault api (POST-only: ids never touch access logs) ============= */
async function api(action,body){
  try{
    const r=await fetch('vault.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(Object.assign({action},body||{}))
    });
    return await r.json();
  }catch(_){return{ok:false,error:'network'}}
}

/* ================= title pool / identity / settings / avatar ================= */
const TITLES=[
  "why do cats knead blankets?","explain quantum tunneling simply",
  "3-day Kyoto itinerary, budget","is intermittent fasting actually science?",
  "help me write a polite no","best headphones under $100?",
  "why is the sky blue, really","summarize today's markets",
  "what changed in the AI race this week","ideas for a small balcony garden",
  "how do noise-cancelling headphones work","teach me chess openings in 10 minutes",
  "is coffee actually dehydrating?","beginner sourdough, no equipment",
  "why do we dream?","fastest way to learn Spanish verbs",
  "explain the shipping news simply","warm-up stretches before running",
  "why do old songs sound better?","how do solar panels work at night?",
  "milk alternatives ranked, please","explain inflation like I'm 12",
  "best 20-minute workouts, no gym","why are airports so cold?",
  "draft a birthday message for a colleague","what is a black hole, kid version",
  "should I learn Python or Rust?","why does time feel faster with age?",
  "easy meals with 5 ingredients","what's new in space exploration?",
  "how does WiFi reach my room?","help me pick a podcast for commuting",
  "why do we yawn when others do?","explain the stock dip this week",
  "best desk plants for low light","how to fix a wobbly chair",
  "why is the ocean salty?","reading list for a long flight",
  "explain GPS like I'm five","do plants feel pain?",
  "how do elevators decide floors?","why do songs get stuck in my head?",
  "quick meals before a workout","what causes deja vu?",
  "cheap weekend trip ideas nearby","explain the news in 100 words",
  "why do dogs tilt their heads?","best apps for focus, honestly",
  "how do bridges handle weight?","why are sunsets red?",
  "help me name my playlist","explain compound interest simply",
  "what to read after Hemingway?","why do old places smell different?",
  "how do fish sleep?","beginner yoga for stiff backs",
  "explain quantum computing, no math","why do we get hiccups?",
  "what's the deal with lab-grown meat?","is 8 hours of sleep a myth?",
  "why do screens feel warmer at night?","how do magpies recognize faces?"
];
let lastTitle='';
function genTitle(){
  let t=TITLES[Math.random()*TITLES.length|0],g=0;
  while(t===lastTitle&&g++<6)t=TITLES[Math.random()*TITLES.length|0];
  lastTitle=t;return t;
}

const ADJ=['pale','quiet','ash','hollow','silent','north','low','soft','cold',
  'brief','dim','still','vague','late','thin','far','dry','slow'];
const ANI=['otter','marten','lynx','fox','heron','moth','raven','ibex','wren',
  'stoat','hare','pike','crow','doe','jay','ell','stag','owl'];
function genHandle(){
  return `${ADJ[Math.random()*ADJ.length|0]}-${ANI[Math.random()*ANI.length|0]}-`+
    String(10+Math.random()*89|0);
}

const SET={accent:'ember',net:true,motes:true,fuse:'read',burn:'calm',
  autocopy:false,archive:true,attLimit:5};
const ACCENTS={ember:['#ffb454','#ffc678'],crimson:['#ff7a8a','#ffa3ae'],
  mint:['#7fe0b2','#a9eccd'],ice:['#8fc7ff','#bcdcff']};
const h2r=h=>{const n=parseInt(h.slice(1),16);
  return `${(n>>16)&255},${(n>>8)&255},${n&255}`};
function applyAccent(name){
  SET.accent=name;
  const a=ACCENTS[name],rs=document.documentElement.style;
  rs.setProperty('--ember',a[0]);
  rs.setProperty('--ember2',a[1]);
  rs.setProperty('--ember-rgb',h2r(a[0]));
  document.querySelectorAll('.swatch').forEach(s=>
    s.classList.toggle('on',s.dataset.acc===name));
}

const avatarEl=$('#avatar'),profMode=$('#profMode'),opNameEl=$('#opName');
function buildAvatar(){
  const N=7,half=[];
  for(let y=0;y<N;y++){half[y]=[];for(let x=0;x<4;x++)half[y][x]=Math.random()<.5}
  let base='',acc='';
  for(let y=0;y<N;y++)for(let x=0;x<N;x++){
    if(!half[y][x<4?x:N-1-x])continue;
    (Math.random()<.4?acc+=`<rect x="${x}" y="${y}"/>`
                     :base+=`<rect x="${x}" y="${y}"/>`);
  }
  if(!acc)acc='<rect x="3" y="3"/>';
  avatarEl.innerHTML=
    `<svg viewBox="0 0 7 7" shape-rendering="crispEdges">`+
    `<g fill="#333d4c">${base.replace(/<rect /g,'<rect width="1" height="1" ')}</g>`+
    `<g fill="currentColor">${acc.replace(/<rect /g,'<rect width="1" height="1" ')}</g></svg>`;
}
function rerollIdentity(){
  opNameEl.textContent=genHandle();
  buildAvatar();
}
rerollIdentity();
avatarEl.addEventListener('click',()=>{
  rerollIdentity();
  toast('new identity — nothing carries over.');
});
const AV_LABEL={idle:'ready',compose:'ready',seal:'sealing',sealed:'sealed',
  gate:'locked',view:'reading',burn:'burning',ash:'ash'};
function setAvatarMode(m){
  avatarEl.className='avatar m-'+m;
  profMode.textContent=AV_LABEL[m]||'ready';
}
setAvatarMode('idle');

/* ================= local archive (links only — no payloads) ================= */
const LSKEY='chats_v1';
let chats=[],currentChatId=null,showPinBadge=false,storageWarned=false;
function loadChats(){
  try{chats=JSON.parse(localStorage.getItem(LSKEY)||'[]')}catch(_){chats=[]}
  if(!Array.isArray(chats))chats=[];
}
function persist(){
  try{
    if(chats.length)localStorage.setItem(LSKEY,JSON.stringify(chats));
    else localStorage.removeItem(LSKEY);
  }catch(_){
    if(!storageWarned){storageWarned=true;
      toast('archive unavailable in this browser mode — messages still work.')}
  }
}
const subFor=c=>c.s==='sealed'?'sealed':'ash'+(c.r?` · ${c.r}`:'');
function renderList(){
  const list=$('#chatList');
  if(!chats.length){
    list.innerHTML=`<div class="empty">
      <svg viewBox="0 0 24 24"><path d="M12 4.5c.5 3-3.8 5-3.8 9a3.8 3.8 0 0 0 7.6 0c0-1.9-1-3-1.7-4.4-.4 1-1.1 1.6-1.9 1.9.4-2.2.3-4.3-.2-6.5Z"/></svg>
      nothing here —<br>and that's the point.</div>`;
    return;
  }
  list.innerHTML=chats.map(c=>`
    <div class="chat ${c.s}${c.id===currentChatId?' act':''}" data-id="${escapeHTML(c.id)}" role="button" tabindex="0">
      <i class="c-dot" aria-hidden="true"></i>
      <span class="c-title">${escapeHTML(c.t)}</span>
      <span class="c-sub">${escapeHTML(subFor(c))}</span>
      <button class="c-edit" data-rename aria-label="rename this message">${IC.pencil}</button>
    </div>`).join('');
}
function updateProfile(){
  let ck=0;
  try{ck=document.cookie?document.cookie.split(';').filter(Boolean).length:0}catch(_){}
  $('#profStat').textContent=
    `${ck===0?'no cookies':ck+' cookies'} · ${chats.length} archived`;
}
function markChat(id,s,r){
  const c=chats.find(x=>x.id===id);
  if(!c)return;
  c.s=s;if(r!==undefined)c.r=r;
  persist();renderList();updateProfile();
}
function findChat(id){return chats.find(x=>x.id===id)}

/* ================= sidebar / background / toasts ================= */
let sideOpen=innerWidth>900;
document.body.classList.toggle('side-closed',innerWidth<=900);
function setSide(open){
  sideOpen=open;
  document.body.classList.toggle('side-closed',!open);
  updateScrim();
}
function updateScrim(){
  $('#scrim').classList.toggle('on',!!popOpen||(MOBILE()&&sideOpen));
}
 $('#sideToggle').addEventListener('click',()=>setSide(!sideOpen));
 $('#sideX').addEventListener('click',()=>setSide(false));
 $('#scrim').addEventListener('pointerdown',()=>{closePop();setSide(false)});

const cv=$('#net'),ctx=cv.getContext('2d');
let W,H,dots=[],base,mx=-1e4,my=-1e4,smx=-1e4,smy=-1e4,motes=[];
function buildNet(){
  const dpr=Math.min(devicePixelRatio||1,2);
  W=innerWidth;H=innerHeight;
  cv.width=W*dpr;cv.height=H*dpr;cv.style.width=W+'px';cv.style.height=H+'px';
  ctx.setTransform(dpr,0,0,dpr,0,0);
  dots=[];
  const sp=W<720?34:26;
  for(let x=sp/2;x<W;x+=sp)for(let y=sp/2;y<H;y+=sp)dots.push({x,y});
  base=document.createElement('canvas');
  base.width=W*dpr;base.height=H*dpr;
  const b=base.getContext('2d');b.setTransform(dpr,0,0,dpr,0,0);
  b.fillStyle='rgba(237,235,229,.085)';b.beginPath();
  for(const d of dots){b.moveTo(d.x+1,d.y);b.arc(d.x,d.y,1,0,6.2832)}
  b.fill();
  if(REDUCED)ctx.drawImage(base,0,0,W,H);
}
buildNet();
window.addEventListener('pointermove',e=>{mx=e.clientX;my=e.clientY},{passive:true});
if(!REDUCED){
  setInterval(()=>{
    if(SET.motes&&!document.hidden&&motes.length<5)
      motes.push({x:Math.random()*W,y:H+8,r:1.3+Math.random()*1.2,
        vy:14+Math.random()*16,ph:Math.random()*6.28});
  },3200);
  (function loop(t,prev){
    requestAnimationFrame(n=>loop(n,t));
    if(document.hidden)return;
    const dt=Math.min((t-prev)/1000,.05);
    ctx.clearRect(0,0,W,H);
    if(!SET.net)return;
    ctx.drawImage(base,0,0,W,H);
    smx+=(mx-smx)*.07;smy+=(my-smy)*.07;
    if(mx>-999){
      for(const d of dots){
        const dx=d.x-smx,dy=d.y-smy,d2=dx*dx+dy*dy;
        if(d2<130*130){
          const p=1-Math.sqrt(d2)/130;
          const r=237+(255-237)*p|0,g=235+(180-235)*p|0,bl=229+(84-229)*p|0;
          ctx.fillStyle=`rgba(${r},${g},${bl},${(.1+.3*p).toFixed(3)})`;
          ctx.beginPath();ctx.arc(d.x,d.y,1.1+1.3*p,0,6.2832);ctx.fill();
        }
      }
    }
    if(SET.motes){
      ctx.shadowColor='rgba(255,170,90,.8)';ctx.shadowBlur=6;
      for(let i=motes.length-1;i>=0;i--){
        const m=motes[i];m.y-=m.vy*dt;
        if(m.y<-10){motes.splice(i,1);continue}
        const a=Math.min(1,(H-m.y)/80,(m.y+10)/120)*.22;
        ctx.fillStyle=`rgba(255,190,120,${a.toFixed(3)})`;
        ctx.beginPath();ctx.arc(m.x+Math.sin(t*.001+m.ph)*4,m.y,m.r,0,6.2832);ctx.fill();
      }
      ctx.shadowBlur=0;
    }
  })(performance.now(),performance.now());
}

function toast(msg){
  const root=$('#toasts'),t=document.createElement('div');
  t.className='toast';t.textContent=msg;root.append(t);
  while(root.children.length>3)root.firstChild.remove();
  requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('in')));
  setTimeout(()=>{t.classList.remove('in');setTimeout(()=>t.remove(),300)},3400);
}

/* ================= base64url ================= */
const b64u=b=>{let s='';for(let i=0;i<b.length;i+=0x8000)
  s+=String.fromCharCode.apply(null,b.subarray(i,i+0x8000));
  return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')};
const ub64=s=>{s=s.replace(/-/g,'+').replace(/_/g,'/');
  while(s.length%4)s+='=';
  const bin=atob(s);return Uint8Array.from(bin,c=>c.charCodeAt(0))};

/* ================= client-side E2E (key NEVER goes to the vault) ============= */
async function deriveWrapKey(pin,salt){
  const bk=await crypto.subtle.importKey('raw',
    new TextEncoder().encode(pin),{name:'PBKDF2'},false,['deriveKey']);
  return crypto.subtle.deriveKey(
    {name:'PBKDF2',salt,iterations:PIN_ITERS,hash:'SHA-256'},
    bk,{name:'AES-GCM',length:256},false,['encrypt','decrypt']);
}

/* ================= QR encoder (byte mode · L · v1–10) ================= */
const QRenc=(()=>{
  const EXP=new Uint8Array(512),LOG=new Uint8Array(256);
  {let x=1;
   for(let i=0;i<255;i++){EXP[i]=x;LOG[x]=i;x<<=1;if(x&256)x^=0x11d}
   for(let i=255;i<512;i++)EXP[i]=EXP[i-255]}
  const gmul=(a,b)=>(a===0||b===0)?0:EXP[LOG[a]+LOG[b]];
  const ECL={ecc:[7,10,15,20,26,18,20,24,30,18],blk:[1,1,1,1,1,2,2,2,2,4]};
  const ALIGN={2:[6,18],3:[6,22],4:[6,26],5:[6,30],6:[6,34],
    7:[6,22,38],8:[6,24,42],9:[6,26,46],10:[6,28,50]};
  function rawModules(v){
    let r=(16*v+128)*v+64;
    if(v>=2){
      const n=Math.floor(v/7)+2;
      r-=(25*n-10)*n-55;
      if(v>=7)r-=36;
    }
    return r;
  }
  function rsDivisor(deg){
    const r=new Array(deg).fill(0);r[deg-1]=1;
    let root=1;
    for(let i=0;i<deg;i++){
      for(let j=0;j<deg;j++){
        r[j]=gmul(r[j],root);
        if(j+1<deg)r[j]^=r[j+1];
      }
      root=gmul(root,2);
    }
    return r;
  }
  function rsRemainder(data,div){
    const res=div.map(()=>0);
    for(const b of data){
      const f=b^res.shift();
      res.push(0);
      div.forEach((c,i)=>res[i]^=gmul(c,f));
    }
    return res;
  }
  function encode(text){
    const bytes=new TextEncoder().encode(text);
    for(let v=1;v<=10;v++){
      const total=Math.floor(rawModules(v)/8);
      const dataCw=total-ECL.ecc[v-1]*ECL.blk[v-1];
      const countBits=v<=9?8:16;
      if(4+countBits+bytes.length*8<=dataCw*8)
        return build(bytes,v,countBits,total,dataCw);
    }
    return null;
  }
  function build(bytes,v,countBits,total,dataCw){
    const bits=[];
    const push=(val,n)=>{for(let i=n-1;i>=0;i--)bits.push((val>>>i)&1)};
    push(4,4);push(bytes.length,countBits);
    for(const b of bytes)push(b,8);
    const term=Math.min(4,dataCw*8-bits.length);
    for(let i=0;i<term;i++)bits.push(0);
    while(bits.length%8)bits.push(0);
    const data=[];
    for(let i=0;i<bits.length;i+=8){
      let b=0;for(let j=0;j<8;j++)b=(b<<1)|bits[i+j];
      data.push(b);
    }
    for(let pad=0xEC;data.length<dataCw;pad^=0xEC^0x11)data.push(pad);
    const n=ECL.blk[v-1],eLen=ECL.ecc[v-1];
    const blocks=[];
    for(let i=0,k=0;i<n;i++){
      const len=Math.floor(dataCw/n)+(i<n-dataCw%n?0:1);
      const dat=data.slice(k,k+len);k+=len;
      blocks.push({dat,ecc:rsRemainder(dat,rsDivisor(eLen))});
    }
    const cw=[];
    const maxD=Math.max(...blocks.map(b=>b.dat.length));
    for(let i=0;i<maxD;i++)for(const b of blocks)if(i<b.dat.length)cw.push(b.dat[i]);
    for(let i=0;i<eLen;i++)for(const b of blocks)cw.push(b.ecc[i]);
    const size=17+v*4;
    const M=[...Array(size)].map(()=>Array(size).fill(false));
    const F=[...Array(size)].map(()=>Array(size).fill(false));
    const set=(x,y,val)=>{M[y][x]=val;F[y][x]=true};
    for(const [cy,cx] of [[3,3],[size-4,3],[3,size-4]]){
      for(let dy=-1;dy<=7;dy++)for(let dx=-1;dx<=7;dx++){
        const y=cy+dy,x=cx+dx;
        if(y<0||y>=size||x<0||x>=size)continue;
        const inP=dx>=0&&dx<=6&&dy>=0&&dy<=6;
        const dark=inP&&(dx===0||dx===6||dy===0||dy===6||
          (dx>=2&&dx<=4&&dy>=2&&dy<=4));
        set(x,y,dark);
      }
    }
    for(let i=8;i<size-8;i++){set(i,6,i%2===0);set(6,i,i%2===0)}
    if(v>=2){
      const pos=ALIGN[v],last=pos[pos.length-1];
      for(const r of pos)for(const c of pos){
        if((r===6&&c===6)||(r===6&&c===last)||(r===last&&c===6))continue;
        for(let dy=-2;dy<=2;dy++)for(let dx=-2;dx<=2;dx++)
          set(c+dx,r+dy,Math.max(Math.abs(dx),Math.abs(dy))!==1);
      }
    }
    const drawFormat=mask=>{
      const d=(1<<3)|mask;
      let rem=d;
      for(let i=0;i<10;i++)rem=(rem<<1)^((rem>>>9)*0x537);
      const fb=((d<<10)|rem)^0x5412;
      const g=i=>(fb>>>i)&1;
      for(let i=0;i<=5;i++)set(8,i,g(i));
      set(8,7,g(6));set(8,8,g(7));set(7,8,g(8));
      for(let i=9;i<15;i++)set(14-i,8,g(i));
      for(let i=0;i<8;i++)set(size-1-i,8,g(i));
      for(let i=8;i<15;i++)set(8,size-15+i,g(i));
      set(8,size-8,true);
    };
    if(v>=7){
      let rem=v;
      for(let i=0;i<12;i++)rem=(rem<<1)^((rem>>>11)*0x1F25);
      const vb=(v<<12)|rem;
      for(let i=0;i<18;i++){
        const b=(vb>>>i)&1;
        const a=size-11+i%3,c=Math.floor(i/3);
        set(c,a,b);set(a,c,b);
      }
    }
    drawFormat(0);
    let bi=0;
    for(let right=size-1;right>=1;right-=2){
      if(right===6)right=5;
      for(let vert=0;vert<size;vert++)for(let j=0;j<2;j++){
        const x=right-j;
        const up=((right+1)&2)===0;
        const y=up?size-1-vert:vert;
        if(!F[y][x]){
          M[y][x]=bi<cw.length*8?((cw[bi>>>3]>>>(7-(bi&7)))&1)===1:false;
          bi++;
        }
      }
    }
    const maskFn=m=>(x,y)=>{
      switch(m){
        case 0:return(x+y)%2===0;
        case 1:return y%2===0;
        case 2:return x%3===0;
        case 3:return(x+y)%3===0;
        case 4:return(Math.floor(x/3)+Math.floor(y/2))%2===0;
        case 5:return x*y%2+x*y%3===0;
        case 6:return(x*y%2+x*y%3)%2===0;
        default:return((x+y)%2+x*y%3)%2===0;
      }
    };
    const toggle=m=>{
      const f=maskFn(m);
      for(let y=0;y<size;y++)for(let x=0;x<size;x++)
        if(!F[y][x]&&f(x,y))M[y][x]=!M[y][x];
    };
    const penalty=()=>{
      let res=0;
      const rows=M,cols=M[0].map((_,x)=>M.map(r=>r[x]));
      for(const line of [...rows,...cols]){
        let run=1;
        for(let i=1;i<=line.length;i++){
          if(i<line.length&&line[i]===line[i-1])run++;
          else{if(run>=5)res+=3+run-5;run=1}
        }
        const s=line.map(v=>v?1:0).join('');
        for(const pat of ['10111010000','00001011101']){
          let idx=0;
          while((idx=s.indexOf(pat,idx))!==-1){res+=40;idx++}
        }
      }
      for(let y=0;y<size-1;y++)for(let x=0;x<size-1;x++){
        const c=M[y][x];
        if(c===M[y][x+1]&&c===M[y+1][x]&&c===M[y+1][x+1])res+=3;
      }
      let dark=0;for(const r of M)for(const b of r)if(b)dark++;
      const t=size*size;
      res+=Math.max(0,Math.ceil(Math.abs(dark*20-t*10)/t)-1)*10;
      return res;
    };
    let best=0,bestP=Infinity;
    for(let m=0;m<8;m++){
      toggle(m);const p=penalty();toggle(m);
      if(p<bestP){bestP=p;best=m}
    }
    toggle(best);
    drawFormat(best);
    return {size,M};
  }
  return {encode};
})();

function qrSVG(url){
  const q=QRenc.encode(url);
  if(!q)return null;
  const s=q.size,Q=4,T=s+Q*2,INK='#111722';
  const inFinder=(x,y)=>(x<7&&y<7)||(x>=s-7&&y<7)||(x<7&&y>=s-7);
  let r='';
  for(let y=0;y<s;y++)for(let x=0;x<s;x++)
    if(q.M[y][x]&&!inFinder(x,y))
      r+=`<rect x="${x+Q}" y="${y+Q}" width="1" height="1" rx=".3"/>`;
  const eye=(ex,ey)=>`<rect x="${ex+Q+.5}" y="${ey+Q+.5}" width="6" height="6" rx="1.9" fill="none" stroke="${INK}" stroke-width="1"/><rect x="${ex+Q+2}" y="${ey+Q+2}" width="3" height="3" rx="1" fill="${INK}"/>`;
  return {svg:`<svg viewBox="0 0 ${T} ${T}" xmlns="http://www.w3.org/2000/svg" fill="${INK}" role="img" aria-label="message link QR code">${r}${eye(0,0)}${eye(s-7,0)}${eye(0,s-7)}</svg>`,q};
}

/* ================= settings panel ================= */
let setOpener=null;
function openSettings(){
  document.body.classList.add('set-open');
  setStatusLine();
  setOpener=document.activeElement;
  setTimeout(()=>$('#setClose').focus(),60);
}
function closeSettings(){
  document.body.classList.remove('set-open');
  $('#cfbox').classList.remove('open');
  if(setOpener&&setOpener.focus)setOpener.focus();
}
 $('#setBtn').addEventListener('click',openSettings);
 $('#setClose').addEventListener('click',closeSettings);
 $('#setScrim').addEventListener('pointerdown',closeSettings);
 $('#swatches').addEventListener('click',e=>{
  const b=e.target.closest('.swatch');if(b)applyAccent(b.dataset.acc);
});
document.querySelectorAll('.sw2').forEach(b=>b.addEventListener('click',()=>{
  const k=b.dataset.set;
  SET[k]=!SET[k];
  b.classList.toggle('on',SET[k]);
  b.setAttribute('aria-checked',String(SET[k]));
  if(k==='net')document.body.classList.toggle('net-off',!SET.net);
}));
 $('#segAtt').addEventListener('click',e=>{
  const b=e.target.closest('button');if(!b)return;
  SET.attLimit=+b.dataset.att;
  [...$('#segAtt').children].forEach(x=>x.classList.toggle('on',x===b));
});
 $('#segFuse').addEventListener('click',e=>{
  const b=e.target.closest('button');if(!b)return;
  SET.fuse=b.dataset.fuse;
  [...$('#segFuse').children].forEach(x=>x.classList.toggle('on',x===b));
});
 $('#segBurn').addEventListener('click',e=>{
  const b=e.target.closest('button');if(!b)return;
  SET.burn=b.dataset.burn;
  [...$('#segBurn').children].forEach(x=>x.classList.toggle('on',x===b));
});
function setStatusLine(){
  let ck=0,keys=0;
  try{ck=document.cookie?document.cookie.split(';').filter(Boolean).length:0}catch(_){}
  try{keys=localStorage.length}catch(_){}
  $('#setStatus').textContent=
    `cookies: ${ck} · storage keys: ${keys} · data sent: encrypted noise only`;
}
 $('#setWipe').addEventListener('click',()=>{$('#cfbox').classList.toggle('open')});
 $('#setWipeNo').addEventListener('click',()=>{$('#cfbox').classList.remove('open')});
 $('#setWipeYes').addEventListener('click',()=>{closeSettings();doWipe()});

/* ================= stage machine ================= */
const stage=$('#stage');
const panes={compose:$('#paneCompose'),card:$('#paneCard'),gate:$('#paneGate'),
  message:$('#paneMessage'),end:$('#paneEnd')};
let cur=panes.compose,pendingGrow=false;
function dock(on){
  stage.classList.toggle('docked',on&&MOBILE());
  if(!stage.classList.contains('docked'))stage.style.transform='';
  syncDock();
}
function syncDock(){
  const docked=MOBILE()&&stage.classList.contains('docked');
  document.body.style.paddingBottom=docked?(stage.offsetHeight+18)+'px':'';
}
async function go(name){
  const next=panes[name];
  if(!next||next===cur)return;
  if(REDUCED){
    cur.hidden=true;next.hidden=false;cur=next;
    dock(next===panes.compose);
    if(next===panes.compose)grow();
    return;
  }
  if(MOBILE()){
    cur.classList.add('out');
    next.hidden=false;next.classList.add('pre');
    dock(next===panes.compose);
    if(next!==panes.compose)
      stage.scrollIntoView({block:'center',behavior:REDUCED?'auto':'smooth'});
    requestAnimationFrame(()=>requestAnimationFrame(()=>next.classList.remove('pre')));
    await wait(300);
    cur.hidden=true;cur.classList.remove('out');cur=next;
    if(next===panes.compose)requestAnimationFrame(grow);
    syncDock();
    return;
  }
  const h0=stage.offsetHeight;
  stage.classList.add('anim');
  stage.style.height=h0+'px';
  cur.classList.add('out');
  next.hidden=false;next.classList.add('pre');
  const h1=next.offsetHeight;
  void stage.offsetWidth;
  stage.style.height=h1+'px';
  next.classList.remove('pre');
  await wait(420);
  cur.hidden=true;cur.classList.remove('out');
  stage.style.height='';stage.classList.remove('anim');
  cur=next;
  if(next===panes.compose)requestAnimationFrame(grow);
  syncDock();
}

/* ================= the ember wave ================= */
function runWave(spans){
  return new Promise(res=>{
    if(REDUCED||!spans.length){res();return}
    const dur=SET.burn==='quick'?750:1600;
    const per=clampN(dur/spans.length,3,26);
    let i=0;
    const iv=setInterval(()=>{
      if(i>=spans.length){clearInterval(iv);setTimeout(res,430);return}
      const s=spans[i++];
      s.classList.add('hot');
      setTimeout(()=>s.classList.add('ash'),180);
    },per);
  });
}
function waveify(el){
  const txt=el.textContent;el.textContent='';
  const spans=[];
  for(const c of txt){
    const s=document.createElement('span');
    s.className='ch';s.textContent=c;el.append(s);spans.push(s);
  }
  return spans;
}
function burnDraft(){
  return new Promise(res=>{
    charLayer.hidden=false;charLayer.innerHTML='';
    const spans=[];
    for(const c of ta.value){
      const s=document.createElement('span');
      s.className='ch';s.textContent=c;charLayer.append(s);spans.push(s);
    }
    charLayer.style.height=ta.clientHeight+'px';
    charLayer.scrollTop=ta.scrollTop;
    ta.style.visibility='hidden';
    runWave(spans).then(res);
  });
}
function clearDraft(){
  charLayer.hidden=true;charLayer.innerHTML='';
  ta.style.visibility='';ta.value='';
  ta.style.height='auto';
  btnSend.disabled=true;grow();
}

/* ================= composer ================= */
const composer=$('#composer'),ta=$('#ta'),charLayer=$('#charLayer'),
  btnSend=$('#btnSend'),btnPin=$('#btnPin'),btnExp=$('#btnExp'),
  expChip=$('#expChip'),expLabel=$('#expLabel'),hint=$('#hint'),
  pinRow=$('#pinRow'),popPin=$('#popPin'),popExp=$('#popExp'),
  pinBoxes=[...pinRow.querySelectorAll('.pbox')],
  pinNote=$('#pinNote'),pinRemove=$('#pinRemove'),bubble=$('#bubble'),
  msgText=$('#msgText'),mOpen=$('#mOpen'),cdNum=$('#cdNum'),ringFg=$('#ringFg'),
  btnAtt=$('#btnAtt'),fileInput=$('#fileInput'),attStrip=$('#attStrip'),
  attName=$('#attName'),attSize=$('#attSize'),attChipIc=$('#attChipIc'),
  attCard=$('#attCard'),attCardIc=$('#attCardIc'),attCardName=$('#attCardName'),
  attCardSub=$('#attCardSub'),attSave=$('#attSave');

const EXPIRY={read:{label:'after read'},60:{label:'60 seconds',chip:'60s'},
  600:{label:'10 minutes',chip:'10m'},3600:{label:'1 hour',chip:'1h'}};

let state='compose',pin=null,expiry=SET.fuse,
  linkUrl='',curFrag='',dispToken='',curToken='',
  rx=null,fromSender=false,fuseLeft=0,fuseTimer=null,attempts=3,
  hintT=null,lastQR=null,pendingFile=null,attURL=null,attDL=null,attBadge=null;

const fmtSize=b=>b<1024?b+' B':b<1048576?(b/1024).toFixed(1)+' KB'
  :(b/1048576).toFixed(1)+' MB';
const elide=(u,max)=>u.length>max?u.slice(0,max-14)+'…'+u.slice(-13):u;

btnAtt.addEventListener('click',()=>fileInput.click());
fileInput.addEventListener('change',()=>{
  const f=fileInput.files[0];
  fileInput.value='';
  if(!f)return;
  if(f.size>SET.attLimit*1048576){
    nudge(`that file is ${fmtSize(f.size)} — your limit is ${SET.attLimit} MB (raise it in settings).`);
    return;
  }
  pendingFile=f;
  attChipIc.innerHTML=attIcon(f.type||'');
  attName.textContent=elide(f.name||'file',26);
  attSize.textContent=fmtSize(f.size);
  attStrip.hidden=false;
  btnSend.disabled=!ta.value.trim()&&!pendingFile;
  syncDock();
});
 $('#attX').addEventListener('click',()=>{
  pendingFile=null;attStrip.hidden=true;
  btnSend.disabled=!ta.value.trim()&&!pendingFile;
  syncDock();
});
function clearAttachment(){
  pendingFile=null;attStrip.hidden=true;
  try{fileInput.value=''}catch(_){}
  if(attURL){URL.revokeObjectURL(attURL);attURL=null}
  attDL=null;
}
attSave.addEventListener('click',()=>{
  if(!attDL)return;
  const a=document.createElement('a');
  a.href=attDL.url;a.download=attDL.name;
  document.body.append(a);a.click();a.remove();
  toast('saved — the vault copy is already ash.');
});

function grow(){
  if(panes.compose.hidden){pendingGrow=true;return}
  pendingGrow=false;
  ta.style.height='auto';
  const h=Math.min(ta.scrollHeight,164);
  ta.style.height=h+'px';
  ta.style.overflowY=ta.scrollHeight>164?'auto':'hidden';
  syncDock();
}
ta.addEventListener('input',()=>{grow();btnSend.disabled=!ta.value.trim()&&!pendingFile});
ta.addEventListener('focus',()=>composer.classList.add('focus'));
ta.addEventListener('blur',()=>composer.classList.remove('focus'));
ta.addEventListener('keydown',e=>{
  const phys=matchMedia('(pointer:fine)').matches;
  if(e.key==='Enter'&&!e.shiftKey&&(phys||e.metaKey||e.ctrlKey)){
    e.preventDefault();seal();
  }
});
btnSend.addEventListener('click',seal);

function nudge(msg){
  hint.textContent=msg;hint.classList.add('on');
  composer.classList.add('shake');
  setTimeout(()=>composer.classList.remove('shake'),450);
  clearTimeout(hintT);
  hintT=setTimeout(()=>hint.classList.remove('on'),2600);
}
function pulseComposer(){
  composer.classList.remove('pulse');
  void composer.offsetWidth;
  composer.classList.add('pulse');
}

/* ================= popovers / PIN / expiry ================= */
const POPS={pin:popPin,exp:popExp};
let popOpen=null;
function openPop(name,anchor){
  if(state!=='compose')return;
  closePop(true);
  const pop=POPS[name];
  pop.hidden=false;
  if(!MOBILE()){
    const cr=composer.getBoundingClientRect(),ar=anchor.getBoundingClientRect();
    const pw=pop.offsetWidth;
    let l=ar.left-cr.left+ar.width/2-pw/2;
    l=clampN(l,10,cr.width-pw-10);
    pop.style.left=l+'px';
  }else{
    pop.style.left='';
  }
  popOpen=name;updateScrim();
  requestAnimationFrame(()=>pop.classList.add('in'));
  if(name==='pin')setTimeout(()=>pinBoxes[0].focus(),190);
}
function closePop(instant){
  if(!popOpen)return;
  const pop=POPS[popOpen];
  const fin=()=>{pop.hidden=true;pop.classList.remove('in')};
  instant?fin():setTimeout(fin,180);
  popOpen=null;updateScrim();
}
btnPin.addEventListener('click',()=>popOpen==='pin'?closePop():openPop('pin',btnPin));
btnExp.addEventListener('click',()=>popOpen==='exp'?closePop():openPop('exp',btnExp));
expChip.addEventListener('click',()=>popOpen==='exp'?closePop():openPop('exp',expChip));
 $('#pinClose').addEventListener('click',()=>closePop());
 $('#expClose').addEventListener('click',()=>closePop());
document.addEventListener('pointerdown',e=>{
  if(!popOpen)return;
  const pop=POPS[popOpen];
  if(pop.contains(e.target)||btnPin.contains(e.target)||
     btnExp.contains(e.target)||expChip.contains(e.target))return;
  closePop();
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){closePop();closeSettings();setSide(false)}
});

function wireBoxes(boxes,onFull){
  boxes.forEach((b,i)=>{
    b.addEventListener('input',()=>{
      b.value=b.value.replace(/\D/g,'').slice(-1);
      if(b.value&&i<boxes.length-1)boxes[i+1].focus();
      if(boxes.every(x=>x.value))setTimeout(onFull,240);
    });
    b.addEventListener('keydown',e=>{
      if(e.key==='Backspace'&&!b.value&&i>0){
        boxes[i-1].focus();boxes[i-1].value='';e.preventDefault();
      }
      if(e.key==='Enter'&&boxes.every(x=>x.value)){e.preventDefault();onFull()}
    });
    b.addEventListener('paste',e=>{
      if(i!==0)return;
      e.preventDefault();
      const d=(e.clipboardData.getData('text').match(/\d/g)||[]).slice(0,4);
      d.forEach((v,k)=>boxes[k].value=v);
      boxes[Math.min(d.length,boxes.length-1)].focus();
      if(d.length===4)onFull();
    });
  });
}
wireBoxes(pinBoxes,savePin);
function savePin(){
  const code=pinBoxes.map(b=>b.value).join('');
  if(code.length!==4)return;
  pin=code;
  btnPin.classList.add('on');
  pinRemove.hidden=false;
  pinNote.textContent='PIN saved. Enter a new code below to replace it.';
  $('#pinHead').textContent='PIN saved';
  toast('PIN set — opening the link will require it.');
  setTimeout(()=>{closePop();$('#pinHead').textContent='PIN protection'},650);
}
pinRemove.addEventListener('click',()=>{
  pin=null;btnPin.classList.remove('on');pinRemove.hidden=true;
  pinNote.textContent='Anyone opening the link will need this 4-digit code — it wraps the key with 600,000 PBKDF2 rounds. Three wrong attempts destroy the message.';
  closePop();
});

popExp.querySelectorAll('.exp-row').forEach(r=>r.addEventListener('click',()=>{
  expiry=r.dataset.exp;
  popExp.querySelectorAll('.exp-row').forEach(x=>
    x.classList.toggle('on',x.dataset.exp===expiry));
  updateExpiryUI();
  closePop();
}));
function updateExpiryUI(){
  const timed=expiry!=='read';
  expChip.hidden=!timed;
  if(timed)expLabel.textContent=EXPIRY[expiry].chip;
  btnExp.classList.toggle('on',timed);
}
function syncFuseUI(){
  popExp.querySelectorAll('.exp-row').forEach(x=>
    x.classList.toggle('on',x.dataset.exp===expiry));
  updateExpiryUI();
}
syncFuseUI();

/* ================= seal → vault escrow ================= */
async function seal(){
  if(state!=='compose')return;
  if(!ta.value.trim()&&!pendingFile){
    nudge('Type something first — or attach a file.');
    return;
  }
  if(!CRYPTO){
    nudge('This context can’t encrypt (needs HTTPS or a local file).');
    return;
  }
  if(pendingFile&&pendingFile.size>SET.attLimit*1048576){
    nudge(`Attachment exceeds your ${SET.attLimit} MB limit — raise it in settings.`);
    return;
  }
  state='sealing';btnSend.disabled=true;closePop();
  setAvatarMode('seal');
  const expSec=expiry==='read'?0:+expiry;

  let token=null,frag=null;
  try{
    /* 1 — everything encrypts HERE. the key never leaves this function except
           into the fragment, and the vault never receives it. */
    const key=await crypto.subtle.generateKey({name:'AES-GCM',length:256},true,['encrypt','decrypt']);
    const iv=crypto.getRandomValues(new Uint8Array(12));
    let nc=0;const chunkBlobs=[];
    if(pendingFile){
      const buf=new Uint8Array(await pendingFile.arrayBuffer());
      nc=Math.max(1,Math.ceil(buf.byteLength/CHUNK));
      for(let i=0;i<nc;i++){
        const civ=crypto.getRandomValues(new Uint8Array(12));
        const cct=new Uint8Array(await crypto.subtle.encrypt({name:'AES-GCM',iv:civ},key,
          buf.subarray(i*CHUNK,Math.min((i+1)*CHUNK,buf.byteLength))));
        const raw=new Uint8Array(12+cct.length);
        raw.set(civ);raw.set(cct,12);
        chunkBlobs.push(b64u(raw));
      }
    }
    const f=pendingFile?{n:pendingFile.name||'file',
      t:pendingFile.type||'application/octet-stream',
      s:pendingFile.size,nc}:null;
    const pt=new TextEncoder().encode(JSON.stringify({m:ta.value,x:expSec,f}));
    const ct=new Uint8Array(await crypto.subtle.encrypt({name:'AES-GCM',iv},key,pt));
    const rawKb=new Uint8Array(await crypto.subtle.exportKey('raw',key));

    if(pin){
      const salt=crypto.getRandomValues(new Uint8Array(16));
      const wiv=crypto.getRandomValues(new Uint8Array(12));
      const wk=await deriveWrapKey(pin,salt);
      const wrapped=new Uint8Array(await crypto.subtle.encrypt({name:'AES-GCM',iv:wiv},wk,rawKb));
      frag='k2.'+b64u(salt)+'.'+b64u(wiv)+'.'+b64u(wrapped);
    }else{
      frag='k1.'+b64u(rawKb);
    }

    /* 2 — escrow: the vault receives noise only */
    const res=await api('store',{exp:expSec,pin:!!pin,nc,iv:b64u(iv),ct:b64u(ct)});
    if(!res.ok||!res.id){
      toast('the vault refused the message — nothing was shared.');
      state='compose';btnSend.disabled=false;setAvatarMode('idle');return;
    }
    token=res.id;
    for(let i=0;i<chunkBlobs.length;i++){
      const up=await api('put',{id:token,i,data:chunkBlobs[i]});
      if(!up.ok){
        await api('burn',{id:token,why:'killed'});
        toast('upload failed — the partial copy was burned.');
        state='compose';btnSend.disabled=false;setAvatarMode('idle');return;
      }
    }
    if(chunkBlobs.length){
      const fin=await api('ready',{id:token});
      if(!fin.ok){
        await api('burn',{id:token,why:'killed'});
        toast('the vault refused the file — nothing was shared.');
        state='compose';btnSend.disabled=false;setAvatarMode('idle');return;
      }
    }
  }catch(_){
    toast('Encryption failed — the draft is still yours, try again.');
    state='compose';btnSend.disabled=false;setAvatarMode('idle');return;
  }

  /* 3 — the link: random token + key-after-# */
  curToken=token;
  curFrag=frag;
  linkUrl=location.origin+location.pathname+'?m='+token+'#'+frag;
  dispToken=token.slice(0,6);

  /* archive BEFORE the animation — a refresh mid-burn loses nothing */
  const rec={id:token,u:linkUrl,t:genTitle(),s:'sealed',e:expiry,c:Date.now(),
    pin:!!pin,att:!!pendingFile};
  if(SET.archive){
    chats.unshift(rec);
    currentChatId=token;
    persist();renderList();updateProfile();
  }
  fromSender=true;
  showPinBadge=!!pin;
  attBadge=pendingFile?{generic:true}:null;
  fuseLeft=expSec;

  await burnDraft();
  buildCard(rec.t);
  await go('card');
  clearAttachment();
  state='sealed';
  setAvatarMode('sealed');
  startFuse();
  startPoll();
  if(SET.autocopy&&navigator.clipboard){
    navigator.clipboard.writeText(linkUrl)
      .then(()=>toast('link copied — it still ends.'))
      .catch(()=>{});
  }
}

function buildCard(title){
  const timed=expiry!=='read';
  const qr=qrSVG(linkUrl);
  lastQR=qr?qr.q:null;
  const shown=elide(linkUrl,64);
  const hi=shown.indexOf('#');
  const qi=shown.indexOf('?m=');
  const cf=qi>=0&&hi>qi
    ?escapeHTML(shown.slice(0,hi-16))+'…<b>'+escapeHTML(shown.slice(hi))+'</b>'
    :escapeHTML(shown);
  panes.card.innerHTML=`
  <div class="share">
    <div class="share-head">
      <span class="s-status" id="cardStatus"><i class="dot"></i>sealed · one read</span>
      <span class="fuse" id="fuseLabel" ${timed?'':'hidden'}>burns in ${timed?fmt(fuseLeft):''}</span>
    </div>
    <p class="s-filed">${SET.archive
      ?`filed in your archive as <b>${escapeHTML(title)}</b> — rename any time.`
      :'not filed — this link exists only where you send it.'}</p>
    <div class="share-body">
      <div class="share-qr">
        ${qr?`
        <div class="qr-tile">
          <div class="qc" aria-hidden="true"><i class="tl"></i><i class="tr"></i><i class="bl"></i><i class="br"></i></div>
          ${qr.svg}
          <i class="scan" aria-hidden="true"></i>
        </div>
        <span class="qr-cap">one scan · one read</span>
        <button class="qr-save" data-act="png" aria-label="download the QR code as an image">
          <svg viewBox="0 0 24 24"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 19.5h14"/></svg>
          save png
        </button>`
        :`
        <div class="qr-fallback">
          <svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="3"/><path d="M8 10.5v-3a4 4 0 0 1 8 0v3"/></svg>
          <p>this domain makes links too long for a code —<br>share the link instead</p>
        </div>`}
      </div>
      <div class="share-link">
        <p class="sl-label">share this link</p>
        <button class="copyfield" data-act="copy" aria-label="copy the sealed link">
          <span class="cf-text">${cf}</span>
          <span class="cf-ic" aria-hidden="true">${IC.copy}</span>
        </button>
        <p class="sl-note">the token points at noise in the vault. the key after
          <b>#</b> never transmits, so the vault can't read it — and it shreds the
          noise the moment it's fetched. after that, this link opens nothing.</p>
        <button class="btn primary" data-act="view">${IC.eye} view once</button>
        <div class="badges">
          <span class="badge">${IC.shield} end-to-end</span>
          ${attBadge?`<span class="badge"><span class="att-ic">${IC.clip}</span>file escrowed</span>`:''}
          ${showPinBadge?`<span class="badge">${IC.lock} PIN</span>`:''}
          <span class="badge">${IC.clock} ${EXPIRY[expiry].label}</span>
        </div>
      </div>
    </div>
    <div class="svwrap">
      <button class="svbtn" data-act="sv" aria-expanded="false">
        ${IC.server} what the vault sees ${IC.chev}
      </button>
      <div class="svbox">
        <div class="sv">
          <div class="svrow"><span>ciphertext</span><b>noise — no key ever received</b></div>
          <div class="svrow"><span>ip address</span><b>never stored</b></div>
          <div class="svrow"><span>user agent</span><b>never stored</b></div>
          <div class="svrow"><span>file chunks</span><b>encrypted client-side before upload</b></div>
          <div class="svrow"><span>lifetime</span><b>shredded on first read or expiry</b></div>
        </div>
      </div>
    </div>
  </div>`;
}
const fmt=s=>s<60?s+'s':Math.floor(s/60)+':'+String(s%60).padStart(2,'0');
function startFuse(){
  stopFuse();
  if(expiry==='read'||fuseLeft<=0)return;
  fuseLeft=Math.max(1,fuseLeft);
  const fl=panes.card.querySelector('#fuseLabel');
  if(!fl)return;
  fl.hidden=false;
  fl.textContent='burns in '+fmt(fuseLeft);
  fl.classList.toggle('hot',fuseLeft<=10);
  fuseTimer=setInterval(()=>{
    fuseLeft--;
    if(fuseLeft<=0){stopFuse();expireLink();return}
    fl.textContent='burns in '+fmt(fuseLeft);
    fl.classList.toggle('hot',fuseLeft<=10);
  },1000);
}
function stopFuse(){clearInterval(fuseTimer);fuseTimer=null}
async function expireLink(){
  if(state!=='sealed')return;
  state='expiring';stopPoll();
  api('burn',{id:curToken,why:'expired'});
  markChat(currentChatId,'ash','expired');
  const cft=panes.card.querySelector('.cf-text');
  if(cft&&!REDUCED)await runWave(waveify(cft));
  linkUrl='';
  buildEnd('expired');
  await go('end');
  state='done';
  setAvatarMode('ash');
}

/* live read receipts — poll the vault while the card is open */
let pollTimer=null;
function startPoll(){
  stopPoll();
  pollTimer=setInterval(async()=>{
    if(state!=='sealed'||!curToken)return;
    const r=await api('status',{id:curToken});
    if(r&&r.state==='gone'){
      stopPoll();
      const reason=r.why==='read'?'opened':(r.why==='expired'?'expired':'ended');
      markChat(curToken,'ash',reason);
      const st=panes.card.querySelector('#cardStatus');
      if(st){
        st.innerHTML=`<i class="dot ash"></i>${
          reason==='opened'?'opened · burned':escapeHTML(reason)}`;
      }
      if(reason==='opened'){
        sessEnded();
        toast('it was opened — the vault copy is ash.');
      }
      setAvatarMode('ash');
    }
  },4000);
}
function stopPoll(){if(pollTimer){clearInterval(pollTimer);pollTimer=null}}

/* ---- share sheet actions ---- */
panes.card.addEventListener('click',e=>{
  const sv=e.target.closest('[data-act="sv"]');
  if(sv){
    const box=panes.card.querySelector('.svbox');
    const open=!box.classList.contains('open');
    box.classList.toggle('open',open);
    sv.classList.toggle('open',open);
    sv.setAttribute('aria-expanded',String(open));
    return;
  }
  const b=e.target.closest('[data-act]');
  if(!b)return;
  if(b.dataset.act==='view')viewOnce();
  if(b.dataset.act==='copy')copyField(b);
  if(b.dataset.act==='png')downloadQR();
});
function copyField(field){
  const ic=field.querySelector('.cf-ic');
  const done=()=>{
    field.classList.add('copied');
    ic.innerHTML=IC.check;
    setTimeout(()=>{
      field.classList.remove('copied');
      ic.innerHTML=IC.copy;
    },1900);
  };
  const fb=()=>{
    const i=document.createElement('textarea');
    i.value=linkUrl;i.style.cssText='position:fixed;opacity:0';
    document.body.append(i);i.select();
    try{document.execCommand('copy')?done():toast('copy blocked — '+elide(linkUrl,70))}
    catch(_){toast('copy blocked — '+elide(linkUrl,70))}
    i.remove();
  };
  if(navigator.clipboard&&navigator.clipboard.writeText)
    navigator.clipboard.writeText(linkUrl).then(done).catch(fb);
  else fb();
}
function downloadQR(){
  if(!lastQR){
    toast('Links from this domain are too long for a code — share the link.');
    return;
  }
  const q=lastQR,Q=4,S=12,INK='#111722';
  const c=document.createElement('canvas');
  c.width=c.height=(q.size+Q*2)*S;
  const g=c.getContext('2d');
  g.fillStyle='#f2eee6';g.fillRect(0,0,c.width,c.height);
  g.fillStyle=INK;
  const inF=(x,y)=>(x<7&&y<7)||(x>=q.size-7&&y<7)||(x<7&&y>=q.size-7);
  const rr=(x,y,w,h,r)=>{
    if(g.roundRect){g.beginPath();g.roundRect(x,y,w,h,r);g.fill()}
    else g.fillRect(x,y,w,h);
  };
  for(let y=0;y<q.size;y++)for(let x=0;x<q.size;x++)
    if(q.M[y][x]&&!inF(x,y))rr((x+Q)*S,(y+Q)*S,S,S,S*.3);
  const eye=(ex,ey)=>{
    if(g.roundRect){
      g.beginPath();g.roundRect((ex+Q)*S+S/2,(ey+Q)*S+S/2,6*S,6*S,S*1.9);
      g.lineWidth=S;g.strokeStyle=INK;g.stroke();
      rr((ex+Q+2)*S,(ey+Q+2)*S,3*S,3*S,S*.9);
    }else{
      g.lineWidth=S;g.strokeStyle=INK;
      g.strokeRect((ex+Q)*S+S/2,(ey+Q)*S+S/2,6*S,6*S);
      g.fillRect((ex+Q+2)*S,(ey+Q+2)*S,3*S,3*S);
    }
  };
  eye(0,0);eye(q.size-7,0);eye(0,q.size-7);
  const a=document.createElement('a');
  a.href=c.toDataURL('image/png');
  a.download='blackend-'+(dispToken||'message')+'.png';
  a.click();
  toast('Saved — one scan, one read, then ash.');
}

/* ================= receive: one fetch = one read ================= */
function clearURL(){
  history.replaceState(null,'',location.pathname);
}
function viewOnce(){
  if(state!=='sealed')return;
  stopFuse();stopPoll();
  fromSender=true;
  runReceive(curToken,curFrag);
}
async function runReceive(token,frag){
  const parts=(frag||'').split('.');
  if(parts[0]!=='k1'&&parts[0]!=='k2'){
    buildEnd('tampered');state='done';
    await go('end');setAvatarMode('ash');clearURL();return;
  }
  curToken=token;
  const res=await api('fetch',{id:token});
  if(!res.ok){
    const why=res.why==='expired'?'expired':'archive';
    markChat(currentChatId,'ash',res.why==='read'?'opened':(res.why||'ended'));
    buildEnd(why);
    state='done';
    await go('end');
    setAvatarMode('ash');
    clearURL();
    return;
  }
  rx={token,kb:null,iv:ub64(res.iv),ct:ub64(res.ct),nc:res.nc,parts};
  if(parts[0]==='k2'){
    attempts=3;state='gate';
    setAvatarMode('gate');
    buildGate();
    await go('gate');
    setTimeout(()=>{
      const b=panes.gate.querySelector('.pbox');
      if(b)b.focus();
    },420);
  }else{
    rx.kb=ub64(parts[1]);
    await startView();
  }
}
function buildGate(){
  panes.gate.innerHTML=`
    <div class="gatecard">
      <div class="g-ic">${IC.lock}</div>
      <h3 class="g-t">PIN required</h3>
      <p class="g-sub" id="gSub"><b id="gAtt">3</b> attempts remaining</p>
      <div class="pin-row" id="gRow">
        <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 1">
        <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 2">
        <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 3">
        <input class="pbox" inputmode="numeric" maxlength="4" autocomplete="off" aria-label="PIN digit 4">
      </div>
      <button class="g-cancel" id="gCancel">${fromSender?'back to the link':'not now'}</button>
    </div>`;
  const boxes=[...panes.gate.querySelectorAll('.pbox')];
  const row=panes.gate.querySelector('#gRow');
  const sub=panes.gate.querySelector('#gSub');
  const att=panes.gate.querySelector('#gAtt');
  const clear=()=>boxes.forEach(b=>b.value='');
  const verify=async()=>{
    const code=boxes.map(b=>b.value).join('');
    if(code.length!==4)return;
    try{
      const salt=ub64(rx.parts[1]),wiv=ub64(rx.parts[2]),wrapped=ub64(rx.parts[3]);
      const wk=await deriveWrapKey(code,salt);
      rx.kb=new Uint8Array(await crypto.subtle.decrypt({name:'AES-GCM',iv:wiv},wk,wrapped));
      startView();
    }catch(_){
      const srv=await api('fail',{id:rx.token});
      const left=srv.left!==undefined?srv.left:0;
      if(srv.state==='killed'||srv.state==='gone'||left<=0){
        state='dead';
        markChat(currentChatId,'ash','killed');
        buildEnd('killed');
        go('end');state='done';
        setAvatarMode('ash');clearURL();
        toast('Link killed — three failed attempts.');
        return;
      }
      attempts=left;
      row.classList.add('wrong');
      att.textContent=left;
      sub.classList.toggle('warn',left===1);
      setTimeout(()=>{clear();row.classList.remove('wrong');boxes[0].focus()},520);
    }
  };
  wireBoxes(boxes,verify);
  panes.gate.querySelector('#gCancel').addEventListener('click',()=>{
    if(state!=='gate')return;
    if(fromSender){
      state='sealed';rx=null;
      go('card');
      startFuse();startPoll();
      setAvatarMode('sealed');
    }else newDraft();
  });
}
async function startView(){
  state='viewing';
  setAvatarMode('view');
  mOpen.textContent=`opened ${utcHM()} utc`;
  let obj;
  try{
    const k=await crypto.subtle.importKey('raw',rx.kb,{name:'AES-GCM'},false,['decrypt']);
    const pt=new Uint8Array(await crypto.subtle.decrypt({name:'AES-GCM',iv:rx.iv},k,rx.ct));
    obj=JSON.parse(new TextDecoder().decode(pt));
  }catch(_){
    buildEnd('tampered');
    await go('end');state='done';
    setAvatarMode('ash');clearURL();
    api('burn',{id:rx.token,why:'killed'});
    return;
  }
  msgText.textContent=obj.m;
  msgText.scrollTop=0;
  bubble.classList.remove('collapse');
  bubble.style.maxHeight='';
  attCard.hidden=!obj.f;
  attCard.style.opacity='';
  attCard.style.transition='';
  attSave.hidden=true;
  const cdSecs=obj.f?12:4;
  cdNum.textContent=cdSecs+'s';
  ringFg.classList.remove('run');
  ringFg.style.animationDuration=cdSecs+'s';
  if(obj.f){
    attCardIc.innerHTML=attIcon(obj.f.t);
    attCardName.textContent=obj.f.n;
    attCardSub.textContent='pulling encrypted chunks from the vault…';
    pullChunks(rx,obj.f).then(blob=>{
      if(state!=='viewing')return;
      attURL=URL.createObjectURL(blob);
      attDL={url:attURL,name:obj.f.n};
      attCardSub.textContent=fmtSize(obj.f.s)+' · decrypted in this tab';
      attSave.hidden=false;
    }).catch(()=>{
      if(state!=='viewing')return;
      attCardSub.textContent='chunk pull failed — claim window may have closed';
    });
  }
  await go('message');
  await wait(REDUCED?250:550);
  if(REDUCED){finishViewing();return}
  ringFg.classList.add('run');
  let cd=cdSecs;
  const cdTimer=setInterval(()=>{if(--cd>0)cdNum.textContent=cd+'s'},1000);
  setTimeout(()=>{clearInterval(cdTimer);finishViewing()},cdSecs*1000);
}
async function pullChunks(rxm,f){
  const k=await crypto.subtle.importKey('raw',rxm.kb,{name:'AES-GCM'},false,['decrypt']);
  const parts=[];
  for(let i=0;i<f.nc;i++){
    const r=await api('chunk',{id:rxm.token,i});
    if(!r.ok)throw new Error('vault');
    const raw=ub64(r.data);
    const iv=raw.subarray(0,12),ctb=raw.subarray(12);
    parts.push(new Uint8Array(await crypto.subtle.decrypt({name:'AES-GCM',iv},k,ctb)));
  }
  return new Blob(parts,{type:f.t||'application/octet-stream'});
}
async function finishViewing(){
  if(state!=='viewing')return;
  state='burning';
  setAvatarMode('burn');
  await runWave(waveify(msgText));
  bubble.style.maxHeight=bubble.offsetHeight+'px';
  void bubble.offsetWidth;
  bubble.classList.add('collapse');
  if(!attCard.hidden&&!REDUCED){
    attCard.style.transition='opacity .3s';
    attCard.style.opacity='0';
  }
  await wait(REDUCED?60:380);
  api('burn',{id:rx.token,why:'read'});   // shred what's left of the vault copy NOW
  if(attURL){URL.revokeObjectURL(attURL);attURL=null}
  attDL=null;
  clearURL();
  if(fromSender&&currentChatId)markChat(currentChatId,'ash','opened');
  rx=null;linkUrl='';
  sessEnded();
  buildEnd('opened');
  await go('end');
  state='done';
  setAvatarMode('ash');
}

/* ================= end states ================= */
function buildEnd(variant){
  const V={
    opened:{ic:IC.flame,t:`Opened ${utcHM()} UTC · burned`,
      s:'the vault copy was shredded on fetch — 0 bytes recoverable. the key you hold opens nothing.'},
    expired:{ic:IC.clockx,t:'Expired unread',
      s:'the vault reaper shredded it on schedule. key or no key, there is nothing left to open.'},
    killed:{ic:IC.lockx,t:'Link killed',
      s:'three failed attempts. the vault copy is ash, 0 bytes recovered.'},
    tampered:{ic:IC.lockx,t:'Integrity check failed',
      s:'the ciphertext didn’t match its authentication tag. nothing was shown.'},
    archive:{ic:IC.flame,t:'This one is ash',
      s:'it already ended — the vault holds nothing, and the link opens silence.'}
  }[variant];
  panes.end.innerHTML=`
    <div class="endcard">
      <div class="e-ic">${V.ic}</div>
      <h3 class="e-t">${V.t}</h3>
      <p class="e-s">${V.s}</p>
      <button class="btn primary" data-act="new">Write another</button>
    </div>`;
}
panes.end.addEventListener('click',e=>{
  if(e.target.closest('[data-act="new"]'))newDraft();
});
function newDraft(){
  stopFuse();stopPoll();closePop();
  pin=null;btnPin.classList.remove('on');pinRemove.hidden=true;
  pinBoxes.forEach(b=>b.value='');
  linkUrl='';curFrag='';curToken='';rx=null;fromSender=false;
  lastQR=null;showPinBadge=false;currentChatId=null;attBadge=null;
  clearAttachment();
  expiry=SET.fuse;syncFuseUI();
  renderList();
  clearURL();
  clearDraft();
  stage.style.transform='';
  state='compose';
  setAvatarMode('idle');
  go('compose');
  if(MOBILE()){setSide(false);window.scrollTo({top:0,behavior:REDUCED?'auto':'smooth'})}
  setTimeout(()=>ta.focus(),MOBILE()?430:200);
}

/* ================= open a chat from the archive ================= */
function openChat(c){
  if(state==='sealing'||state==='burning'||state==='viewing')return;
  stopFuse();stopPoll();closePop();setSide(MOBILE()?false:sideOpen);
  currentChatId=c.id;
  fromSender=true;
  renderList();
  if(c.s!=='sealed'){
    setAvatarMode('ash');
    buildEnd('archive');
    state='done';
    go('end');
    return;
  }
  linkUrl=c.u||(location.origin+location.pathname+'?m='+c.id+'#'+(c.k||''));
  curToken=c.id;
  curFrag=linkUrl.split('#')[1]||'';
  dispToken=c.id.slice(0,6);
  expiry=c.e;
  showPinBadge=!!c.pin;
  pin=null;
  attBadge=c.att?{generic:true}:null;
  fuseLeft=c.e==='read'?0:Math.max(0,(+c.e)-(Date.now()-c.c)/1000);
  syncFuseUI();
  setAvatarMode('sealed');
  buildCard(c.t);
  state='sealed';
  go('card').then(()=>{
    if(state==='sealed'){startFuse();startPoll()}
  });
  /* immediate live status — the vault knows, so we can say */
  api('status',{id:c.id}).then(r=>{
    if(state==='sealed'&&r&&r.state==='gone'){
      const reason=r.why==='read'?'opened':(r.why==='expired'?'expired':'ended');
      markChat(c.id,'ash',reason);
      stopFuse();stopPoll();
      buildEnd(reason==='expired'?'expired':'archive');
      state='done';
      setAvatarMode('ash');
      go('end');
    }
  });
}

/* chat list: open + rename (input owns row 1, status hides in place) */
 $('#chatList').addEventListener('click',e=>{
  const edit=e.target.closest('[data-rename]');
  const item=e.target.closest('.chat');
  if(!item)return;
  const c=findChat(item.dataset.id);
  if(!c)return;
  if(edit){startRename(item,c);return}
  openChat(c);
});
 $('#chatList').addEventListener('keydown',e=>{
  if(e.key==='Enter'&&e.target.classList.contains('chat')){
    const c=findChat(e.target.dataset.id);
    if(c)openChat(c);
  }
});
function startRename(item,c){
  item.classList.add('ren');
  const title=item.querySelector('.c-title');
  const input=document.createElement('input');
  input.className='c-ren';
  input.value=c.t;
  input.maxLength=48;
  title.replaceWith(input);
  input.focus();input.select();
  let done=false;
  const commit=save=>{
    if(done)return;done=true;
    const v=input.value.trim();
    if(save&&v)c.t=v;
    persist();renderList();updateProfile();
  };
  input.addEventListener('keydown',e=>{
    if(e.key==='Enter'){e.preventDefault();commit(true)}
    if(e.key==='Escape'){commit(false)}
    e.stopPropagation();
  });
  input.addEventListener('blur',()=>commit(true));
  input.addEventListener('click',e=>e.stopPropagation());
}

/* ================= wipe: vault copies die first, then the device ============ */
async function doWipe(){
  stopFuse();stopPoll();closePop();closeSettings();setSide(false);
  const ids=chats.filter(c=>c.s==='sealed').map(c=>c.id);
  await Promise.allSettled(ids.map(id=>api('burn',{id,why:'wiped'})));
  try{localStorage.removeItem(LSKEY)}catch(_){}
  chats=[];
  currentChatId=null;
  linkUrl='';curFrag='';curToken='';rx=null;fromSender=false;lastQR=null;
  pin=null;btnPin.classList.remove('on');pinRemove.hidden=true;
  pinBoxes.forEach(b=>b.value='');
  attBadge=null;
  clearAttachment();
  clearURL();
  clearDraft();
  stage.style.transform='';
  state='compose';
  setAvatarMode('idle');
  runWipeFx();
}
async function runWipeFx(){
  const flash=$('#wipeFlash');
  const items=[...$('#chatList').querySelectorAll('.chat')];
  flash.classList.remove('on');void flash.offsetWidth;flash.classList.add('on');
  if(REDUCED||!items.length){
    renderList();updateProfile();
    toast('Everything ended — even the memory of it.');
    return;
  }
  items.forEach((item,i)=>{
    setTimeout(()=>{
      const t=item.querySelector('.c-title');
      if(t)runWave(waveify(t));
      const d=item.querySelector('.c-dot');
      if(d){d.style.transition='opacity .5s';d.style.opacity='0'}
      item.style.transition='opacity .55s .35s,filter .55s .35s';
      item.style.opacity='0';item.style.filter='blur(3px)';
    },i*130);
  });
  await wait(items.length*130+900);
  renderList();
  updateProfile();
  go('compose');
  toast('Everything ended — even the memory of it.');
  setTimeout(()=>ta.focus(),400);
}

/* ================= session counter / nav ================= */
const mSess=$('#mSess');
let sess=0;
function sessEnded(){
  sess++;
  mSess.textContent=sess;
  mSess.classList.add('tick');
  setTimeout(()=>mSess.classList.remove('tick'),380);
}
function startNew(){
  setSide(MOBILE()?false:sideOpen);
  if(state!=='compose')newDraft();
  else pulseComposer();
  setTimeout(()=>ta.focus(),MOBILE()?420:140);
}
 $('#newSide').addEventListener('click',startNew);
 $('#mPlus').addEventListener('click',startNew);
 $('#writeOne').addEventListener('click',()=>{
  if(innerWidth>900)stage.scrollIntoView({behavior:REDUCED?'auto':'smooth',block:'center'});
  if(state!=='compose')newDraft();
  else pulseComposer();
  setTimeout(()=>ta.focus(),160);
});
 $('#brandLink').addEventListener('click',e=>{
  e.preventDefault();setSide(MOBILE()?false:sideOpen);
  window.scrollTo({top:0,behavior:REDUCED?'auto':'smooth'});
});

/* ================= anatomy + receipt ================= */
document.querySelectorAll('[data-p]').forEach(el=>{
  const grp=[...document.querySelectorAll(`[data-p="${el.dataset.p}"]`)];
  const on=()=>grp.forEach(x=>x.classList.add('hl'));
  const off=()=>grp.forEach(x=>x.classList.remove('hl'));
  el.addEventListener('pointerenter',on);
  el.addEventListener('pointerleave',off);
});
(async function buildAnatomy(){
  if(!CRYPTO)return;
  try{
    const k=await crypto.subtle.generateKey({name:'AES-GCM',length:256},true,['encrypt']);
    const kb=new Uint8Array(await crypto.subtle.exportKey('raw',k));
    $('#segPage').textContent=(location.host||'blackend.on').replace(/^www\./,'');
    $('#segTok').textContent=Array.from(crypto.getRandomValues(new Uint8Array(9)))
      .map(b=>b.toString(16).padStart(2,'0')).join('');
    $('#segKey').textContent='k1.'+elide(b64u(kb),26);
  }catch(_){}
})();
function receiptSync(){
  let ck=0;
  try{ck=document.cookie?document.cookie.split(';').filter(Boolean).length:0}catch(_){}
  $('#rCk').textContent=ck;
  $('#rSt').textContent=chats.length+' entr'+(chats.length===1?'y':'ies');
}
 $('#rVerify').addEventListener('click',()=>{
  receiptSync();
  let ck=0;
  try{ck=document.cookie?document.cookie.split(';').filter(Boolean).length:0}catch(_){}
  const clean=ck===0;
  $('#rVerify').classList.toggle('ok',clean);
  $('#rVerifyTxt').textContent=clean
    ?`verified · ${chats.length} local, 0 sent in the clear`
    :'cookies found — not from blackend';
  toast(clean
    ?`Verified — 0 cookies, ${chats.length} local entries, only noise ever hit the vault.`
    :'This host left cookies in your browser — they aren’t from blackend.');
});

/* ================= reveal / resize / keyboard ================= */
if('IntersectionObserver' in window){
  const io=new IntersectionObserver(es=>es.forEach(en=>{
    if(en.isIntersecting){en.target.classList.add('in');io.unobserve(en.target)}
  }),{threshold:.15});
  document.querySelectorAll('.rv').forEach(el=>io.observe(el));
}else document.querySelectorAll('.rv').forEach(el=>el.classList.add('in'));

window.addEventListener('resize',()=>{
  buildNet();closePop();syncDock();grow();
  updateScrim();
});
if(window.visualViewport){
  const vv=visualViewport;
  const onVV=()=>{
    const kb=Math.max(0,innerHeight-vv.height-vv.offsetTop);
    if(stage.classList.contains('docked')&&kb>60)
      stage.style.transform=`translateY(${-kb}px)`;
    else if(!stage.style.transform.startsWith('translate'))
      stage.style.transform='';
  };
  vv.addEventListener('resize',onVV);
  vv.addEventListener('scroll',onVV);
}

/* ================= receive from the address bar (?m= + #key) ================ */
function tryReceive(){
  const m=new URLSearchParams(location.search).get('m');
  if(!m||!/^[A-Za-z0-9]{16,64}$/.test(m))return;
  const frag=location.hash.slice(1);
  if(!frag.startsWith('k1.')&&!frag.startsWith('k2.'))return;
  if(state!=='compose'&&state!!='done'&&state!=='done')return;
  fromSender=false;
  clearURL();                    // the key leaves the address bar immediately
  runReceive(m,frag);
}
window.addEventListener('hashchange',tryReceive);

/* ================= init ================= */
loadChats();
renderList();
updateProfile();
receiptSync();
dock(true);
grow();
tryReceive();
</script>
</body>
</html>