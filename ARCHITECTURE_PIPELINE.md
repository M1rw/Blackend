# blackend — Architecture Pipeline, Interaction & Security Specification

## 1. Executive Overview

**blackend** is an enterprise-grade, zero-knowledge, self-destructing temporary messaging and file-sharing web application. It operates on a dual-mode client-side cryptography architecture:

1. **Direct In-Link Mode (100% Serverless)**:
   - Payload, IV, ciphertext, and decryption key reside entirely inside the URL fragment (`#k1...` or `#k2...`).
   - RFC 3986 section 3.5 guarantees browsers never transmit fragment identifiers (`#`) across the network to web servers or proxies.
   - Zero bytes stored on any server.

2. **Blind Escrow Vault Mode (Short URLs with Single-Read Shredding)**:
   - Formats: Clean short path (`/v/token#key`) or query format (`?m=token#key`).
   - **Zero-Hash PIN Shield**: When PIN protection is enabled, the decryption key is wrapped client-side using 120,000 PBKDF2-SHA256 iterations and stored inside the vault envelope. The URL requires **no fragment at all** (`/v/token`), guaranteeing maximum URL compatibility across SMS, messaging apps, and strict email filters.
   - First read delivers the envelope, activates a 10-minute claim window for multi-chunk file retrieval, and overwrites server storage with cryptographic random noise before unlinking (`unlink`).

---

## 2. Threat Model & Security Guarantees

| Threat Vector | Mitigating Mechanism | Security Guarantee |
|---|---|---|
| **Server Compromise / Malicious Host** | Client-side AES-256-GCM encryption in browser WebCrypto API. Server holds only encrypted noise. | Server operator or compromised host cannot read plaintext messages or attached files. |
| **Network Eavesdropping / Proxy Interception** | Decryption keys travel in URL fragment (`#`) or wrapped inside zero-knowledge PIN envelope. | Network observers see only encrypted blobs or token requests without decryption keys. |
| **Physical Device Theft / Forensic Recovery** | Messages are auto-burned on schedule or after read. Server overwrites file blocks with random bytes before deletion. | Storage blocks are cryptographically shredded (`random_bytes`), leaving zero recoverable bytes on disk. |
| **PIN Brute-Force Attacks** | Client-side 120,000 PBKDF2-SHA256 iterations + server-enforced 3-strike rate limiter (`Vault::fail`). | 3 wrong attempts permanently destroy the envelope on the server. |
| **Subpoenas / Legal Demands** | Server logs no IP addresses, user agents, or tracking cookies. Data is shredded immediately after read. | No historical logs or unencrypted data exist to subpoena. |

---

## 3. Click-by-Click Interaction & Button Purpose Matrix

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

## 4. State Machine Pipeline

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

## 5. Animation & Visual FX Engine

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

## 6. API Endpoint & POST Schema Specification (`/vault.php`)

All Vault API endpoints accept `POST` requests with `Content-Type: application/json`.

| Action | Required Request Body Fields | Response Payload | Description |
|---|---|---|---|
| `store` | `exp`, `pin`, `nc`, `iv`, `ct`, `salt`, `wiv`, `wrapped`, `settings`, `rk` | `{ ok: true, id: "token" }` | Seals a new message envelope and returns creative nano-token. |
| `put` | `id`, `i`, `data` | `{ ok: true }` | Uploads encrypted file chunk index `i`. |
| `ready` | `id` | `{ ok: true }` | Marks file chunk uploads complete. |
| `fetch` | `id` | `{ ok: true, iv, ct, salt, wiv, wrapped, pin, nc, read, claim, already_read, settings, now }` | Retrieves encrypted envelope and starts single-read claim window. |
| `chunk` | `id`, `i` | `{ ok: true, data }` | Downloads chunk `i` and immediately overwrites/shreds chunk file on disk. |
| `burn` | `id`, `why` | `{ ok: true }` | Overwrites envelope data with random noise and leaves tombstone. |
| `open` | `id` | `{ ok: true, read, claim, already_read, now }` | Explicitly claims envelope upon successful PIN entry. |
| `fail` | `id` | `{ ok: true, state: "locked"\|"killed", left: int }` | Increments PIN brute-force fail counter (3-strike destruction). |
| `status` | `id` | `{ ok: true, state: "sealed"\|"opened"\|"gone", why?, now }` | Queries envelope lifecycle state without claiming. |
| `watch` | `id` | `text/event-stream` SSE stream | Real-time SSE status stream (token remains in POST body). |
| `receipt` | `id`, `rk` | `{ ok: true, created, opened, burned, why, now }` | Queries chain-of-custody audit trail using SHA-256 receipt key hash. |

---

## 7. Local Storage & Client Quota Architecture

| Storage Key | Scope / Namespace | Data Format | Description & Quota Protection |
|---|---|---|---|
| `chats_v1` | Device local archive | Array of JSON objects | Stores pseudonymous archive history (`{id, mode, u, p, t, s, e, c, pin, att}`). Automatically trims in-link attachment blobs on quota exception. |
| `bk_rk_v1` | Receipt keys | Object map `{token: {rk, ts}}` | Keeps plaintext 32-byte receipt keys. Capped at 100 most recent keys to prevent storage bloating. |
| `blackend_settings_v2` | User preferences | JSON object | Persists theme accent, canvas toggles, burn speed, default fuse, and file limits. |

---

## 8. Serverless Topology & Vercel Constraints

- **Storage Fallback**: On Vercel serverless containers, `$dataDir` falls back to `/tmp/blackend_data` (writable ephemeral directory).
- **Execution Timeout Management**: SSE streaming in `Vault::watch` dynamically caps watch connection duration to 8 seconds on serverless environments (`VERCEL`, `VERCEL_ENV`) to eliminate HTTP 504 Gateway Timeouts while providing immediate status updates.
- **URL Rewrite Routing**: `vercel.json` maps clean path URLs (`/v/:token`, `/m/:token`) directly to `api/index.php` and `api/vault.php`.
