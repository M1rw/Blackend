# blackend — Architecture Pipeline & Interaction Specification

## Executive Overview

**blackend** is an enterprise-grade, zero-knowledge, self-destructing temporary messaging and file-sharing web application. It operates on a dual-mode cryptography architecture:

1. **Direct In-Link Mode (100% Serverless)**:
   - Ciphertext, IV, and decryption key reside entirely in the URL fragment (`#k1...` or `#k2...`).
   - RFC 3986 guarantees browsers never transmit fragment identifiers (`#`) across the network to any web server or proxy.
   - Zero bytes stored on any server.

2. **Blind Escrow Vault Mode (Short URLs with Single-Read Shredding)**:
   - Formats: Clean short path (`/v/token#key`) or query format (`?m=token#key`).
   - **Zero-Hash PIN Shield**: When PIN protection is enabled, the decryption key is wrapped client-side using 120,000 PBKDF2-SHA256 iterations and stored in the vault envelope. The URL requires **no fragment at all** (`/v/token`), guaranteeing max URL compatibility.
   - First read delivers the envelope, activates a 10-minute claim window for multi-chunk file retrieval, and shreds the data from server memory/disk using cryptographic random noise before `unlink`.

---

## Click-by-Click Interaction & Button Purpose Matrix

| Element Selector | Target / Location | Event | Trigger Context | Action Executed | Next State | Security & Purpose |
|---|---|---|---|---|---|---|
| `#sideToggle` | Main Header | `click` | Any state | Toggles sidebar visibility by toggling `.side-closed` on `<body>` | Sidebar toggled | Controls archive panel drawer without disturbing message draft |
| `#sideX` | Sidebar Header | `click` | Mobile view | Closes mobile sidebar drawer | Sidebar closed | Releases viewport focus on mobile devices |
| `#scrim` | Overlay Scrim | `pointerdown` | Mobile drawer open | Closes mobile drawer | Sidebar closed | Dismisses drawer when user taps outside on touch screens |
| `#newSide`, `#mPlus` | Sidebar / Header | `click` | Any state | Invokes `startNew()` / `newDraft()`, resets composer, clears draft and attachment | `compose` | Starts a fresh zero-trace draft session |
| `#avatar` | Sidebar Footer | `click` | Any state | Invokes `rerollIdentity()`, generates new pseudonym handle (`genHandle()`) and procedural SVG avatar | Avatar rerolled | Guarantees identity anonymity per session |
| `#setBtn` | Sidebar Footer | `click` | Any state | Opens settings slide-over panel (`openSettings()`) | Settings open | Accesses local preferences (theme, burn speed, file size limits) |
| `#setClose`, `#setScrim` | Settings Panel | `click` / `pointerdown` | Settings panel open | Closes settings panel (`closeSettings()`) | Settings closed | Returns focus to previous opener element |
| `#swatches .swatch` | Settings Panel | `click` | Settings panel open | Updates system accent theme (`applyAccent()`) to ember, crimson, mint, or ice | Theme updated | Synchronizes CSS variables `--ember`, `--ember-rgb` across UI |
| `[data-set]` `.sw2` | Settings Panel | `click` | Settings panel open | Toggles boolean setting (`net`, `motes`, `autocopy`, `archive`) and saves to `localStorage` | Setting toggled | Customizes background canvas effects and archive behavior |
| `#segAtt button` | Settings Panel | `click` | Settings panel open | Sets maximum attachment file size limit (1, 5, 10, 25 MB) | Limit updated | Enforces client-side file upload quota check before encryption |
| `#segFuse button` | Settings Panel | `click` | Settings panel open | Updates default fuse expiry (`read`, `60`, `600`, `3600`) | Fuse updated | Pre-selects default message lifespan for future messages |
| `#segBurn button` | Settings Panel | `click` | Settings panel open | Updates burn speed (`calm`, `quick`, `custom`) and shows custom slider if selected | Burn speed updated | Customizes text ember-dissolve animation duration |
| `#burnSlider`, `#burnInput` | Settings Panel | `input` | Custom burn active | Adjusts `SET.burnTime` (0.1s to 30.0s) and synchronizes slider/number box | Value synced | Precise control over ember wave animation speed |
| `#burnTestBtn` | Settings Panel | `click` | Custom burn active | Plays preview wave animation on sample text | Sample burned | Visual feedback for custom burn speed |
| `#readSlider`, `#readInput` | Settings Panel | `input` | Settings panel open | Adjusts `SET.readTime` (1s to 300s) | Read time updated | Configures read countdown duration before auto-burn |
| `#setWipe` | Settings Panel | `click` | Settings panel open | Expands wipe confirmation box (`#cfbox`) | Confirmation shown | Prevents accidental deletion of local archive |
| `#setWipeYes` | Confirmation Box | `click` | Confirmed wipe | Burns all remote vault envelopes in archive, purges `localStorage`, plays theatrical wave wipe FX | Archive purged, `compose` | Complete zero-trace local and server purge |
| `#setWipeNo` | Confirmation Box | `click` | Confirmation shown | Collapses wipe confirmation box | Settings open | Cancels wipe operation |
| `#btnAtt` | Composer Toolbar | `click` | `compose` | Triggers hidden `<input type="file" id="fileInput">` picker | File browser opened | Prepares file attachment for client-side WebCrypto chunking |
| `#attX` | Attachment Chip | `click` | File selected | Clears selected file attachment (`clearAttachment()`) | File removed | Removes attached file before sealing |
| `#btnPin` | Composer Toolbar | `click` | `compose` | Toggles PIN popover (`#popPin`) | Popover toggled | Accesses 4-digit PBKDF2 PIN protection setup |
| `.pbox` (PIN inputs) | PIN Popover | `input` / `paste` | PIN popover open | Captures 4-digit numeric code, sets `pin`, enables PIN mode | PIN saved | Derives 120,000 PBKDF2-SHA256 key wrapping layer |
| `#pinRemove` | PIN Popover | `click` | PIN active | Removes PIN protection, clears PIN input boxes | PIN removed | Converts message back to unpinned Nano-Seed format |
| `#btnExp` / `#expChip` | Composer Toolbar | `click` | `compose` | Toggles expiry popover (`#popExp`) | Popover toggled | Selects burn fuse (`after read`, `60s`, `10m`, `1h`) |
| `.exp-row` | Expiry Popover | `click` | Expiry popover open | Sets `expiry`, updates chip label, closes popover | Expiry selected | Sets absolute Unix expiration timestamp inside ciphertext |
| `#btnSend` | Composer Toolbar | `click` | Draft typed / attached | Invokes `seal()`, encrypts payload in WebCrypto AES-256-GCM, uploads to vault | `sealing` -> `card` | Executes dual-engine sealing pipeline |
| `[data-act="copy"]` | Share Card | `click` | `sealed` | Copies sealed link URL (`linkUrl`) to clipboard using `navigator.clipboard` or execCommand fallback | Copied toast shown | Shares self-destructing link |
| `[data-act="png"]` | Share Card | `click` | `sealed` | Generates high-res PNG canvas from QR matrix and triggers download | PNG downloaded | Exports scannable physical QR code |
| `[data-act="view"]` | Share Card | `click` | Sender testing link | Invokes `viewOnce()`, decrypts message in place as recipient | `viewing` | Demonstrates receiver experience |
| `[data-act="rcpt"]` | Share Card | `click` | Vault mode | Toggles delivery receipt chain-of-custody audit panel | Accordion toggled | Provides sender access to cryptographic audit trail |
| `[data-act="rcpt-verify"]`| Receipt Accordion | `click` | Vault mode | Hashes local receipt key (`hashReceiptKey`), calls `/vault.php` `action=receipt` | Chain verified | Proves exact timestamp of creation, opening, and burning |
| `[data-act="sv"]` | Share Card | `click` | `sealed` | Toggles "What Any Server Sees" technical transparency box | Accordion toggled | Educates users on client-side zero-knowledge mechanics |
| `.gatecard .pbox` | PIN Gate Modal | `input` / `paste` | `gate` | Submits PIN attempt, decrypts envelope or increments server fail counter (`Vault::fail`) | `viewing` or `gate` wrong | Rate-limits PIN brute force attempts (3-strike limit) |
| `#gCancel` | PIN Gate Modal | `click` | `gate` | Cancels PIN entry, returns to share card or new draft | `sealed` or `compose` | Aborts PIN entry |
| `#attSave` | Message View | `click` | `viewing` with attachment | Downloads reconstituted decrypted attachment Blob | File saved | Saves attached media locally before auto-burn |
| `[data-act="new"]` | End Card | `click` | `done` / `end` | Invokes `newDraft()`, resets state machine to clean slate | `compose` | Prepares next temporary message |
| `.chat` (Item) | Sidebar List | `click` | Any state | Opens archived chat (`openChat(c)`), checks status on server if vault link | `card` or `end` | Inspects local archive entry status |
| `.c-edit` | Sidebar List Item| `click` | Any state | Converts title span to input (`startRename()`) for custom pseudonym renaming | Editing title | Customizes local archive item label |

---

## State Machine Pipeline

```text
               ┌───────────────┐
               │    compose    │ ◄──────────────────────────────┐
               └───────┬───────┘                                │
                       │ (#btnSend / seal)                      │
                       ▼                                        │
               ┌───────────────┐                                │
               │    sealing    │                                │
               └───────┬───────┘                                │
                       │ (WebCrypto AES-256-GCM + Vault API)    │
                       ▼                                        │
               ┌───────────────┐                                │
               │    sealed     │ ──(Link Opened by Receiver)──┐ │
               └───────┬───────┘                              │ │
                       │                                      ▼ │
                       │                            ┌───────────┴───┐
                       │                            │  Vault Fetch  │
                       │                            └───────┬───────┘
                       │                                    │
                       │                        ┌───────────┴───────────┐
                       │                        │  Is PIN Protected?   │
                       │                        └─────┬───────────┬─────┘
                       │                           YES│           │NO
                       │                              ▼           │
                       │                       ┌─────────────┐    │
                       │                       │    gate     │    │
                       │                       └──────┬──────┘    │
                       │                              │(3 strikes)│
                       │                        Correct PIN       │
                       │                              │           │
                       ▼                              ▼           ▼
               ┌───────────────┐                       ┌───────────────┐
               │    viewing    │ ◄─────────────────────┤    viewing    │
               └───────┬───────┘                       └───────┬───────┘
                       │                                       │
                       │ (Read Countdown / Timer Expiry)       │
                       ▼                                       │
               ┌───────────────┐                               │
               │    burning    │                               │
               └───────┬───────┘                               │
                       │ (Ember Wave Animation + Vault Burn)   │
                       ▼                                       │
               ┌───────────────┐                               │
               │  done / end   │ ──(Write Another / #newSide)──┘
               └───────────────┘
```

---

## Animation & Visual FX Engine

1. **Ember Wave Text Dissolve (`runWave`)**:
   - Converts plaintext DOM text into individual `<span>` characters (`waveify`).
   - Calculates wave progress using `performance.now()`.
   - Sequential staggered class addition: `.hot` (glowing ember) -> `.ash` (blur and translate shift).
   - Fully synchronized with configurable burn duration (`SET.burnTime`).

2. **Interactive Background Network (`assets/js/canvas.js`)**:
   - High-DPR particle grid canvas element (`#net`).
   - Smooth pointer proximity illumination using radial interpolation.
   - Floating ember motes with rising sinusoidal drift physics.
   - Respects `prefers-reduced-motion` OS media query for accessibility.

3. **Wipe Animation (`runWipeFx`)**:
   - Screen-wide amber gradient radial flash (`#wipeFlash.on`).
   - Staggered text wave dissolve applied across all archived chat entries in parallel.
   - Complete canvas reset upon completion.

---

## Cryptographic Architecture & Data Flow

```text
[ Sender Browser ]
  ├─ 1. Generate 256-bit AES-GCM Key (or 16-byte Nano-Seed)
  ├─ 2. Encrypt Payload + Attachments in WebCrypto API
  ├─ 3. (Optional) PBKDF2-SHA256 Key Wrapping (120,000 iters)
  ├─ 4. Generate 32-byte Random Receipt Key (rk)
  ├─ 5. Hash rk with SHA-256 ('bk-receipt:' + rk) -> rk_hash
  └─ 6. Send Encrypted Noise + rk_hash to /vault.php
                             │
                             ▼
                   [ /vault.php Backend ]
                     ├─ OpenSSL AES-256-GCM At-Rest Encryption
                     ├─ Store env.bin + meta.json in data_dir
                     └─ Return Token ("ash-fox-42")
                             │
                             ▼
[ Recipient Browser ]
  ├─ 1. Fetch Envelope via POST /vault.php (action=fetch)
  ├─ 2. Read Server Display Settings (Accent, Burn Speed, Fuse)
  ├─ 3. Obtain Key Fragment from URL (#) or PIN Prompt
  ├─ 4. Decrypt Ciphertext in Browser WebCrypto API
  ├─ 5. Single-Read Shredding: Server overwrites chunk files with
  │     random bytes and unlinks envelope upon open/claim expiry.
  └─ 6. Key is destroyed; 0 bytes remain on disk/server.
```
