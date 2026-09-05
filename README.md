# blackend — the chat that forgets

**blackend** is an enterprise-grade, zero-knowledge, self-destructing temporary messaging and file-sharing web application featuring a dual-mode cryptography engine: **Direct In-Link (100% Serverless)** and **Blind Escrow Vault (Short URLs with Single-Read Server Shredding)**.

All encryption occurs client-side via the Web Crypto API (AES-256-GCM). Decryption keys travel in the URL fragment (`#`), which RFC 3986 guarantees browsers never transmit across the network.

---

## Key Features

- **Dual-Engine Cryptography**:
  - **Direct In-Link Mode**: 100% serverless; encrypted payload travels entirely in the URL fragment (`#k1...` or `#k2...`). Zero server storage required.
  - **Escrow Vault Mode**: Generates short URLs (`?m=token#key`); the vault receives only encrypted noise and shreds it with random bytes on the first read.
- **Universal Receiver**: Automatically detects link mode (In-Link vs. Escrow Vault) and decrypts seamlessly.
- **Single-Read Shredding**: The vault overwrites ciphertext with random bytes before deletion.
- **Encrypted File Attachments**: Files are chunked into 256 KB segments and encrypted in-browser before upload.
- **PIN Protection**: Wraps encryption keys using 120,000 PBKDF2-SHA256 iterations. Three failed attempts permanently destroy the message.
- **Configurable Fuses**: Automatic destruction upon first read, 60 seconds, 10 minutes, or 1 hour.
- **Local Device Archive**: Messages are archived locally in `localStorage` under pseudonyms with quota protection. History never leaves your device.
- **Integrated QR Code Generator**: High-resolution scannable QR code generation and PNG export.
- **Modular Lead-Engineered Architecture**: Clean separation of PHP backend, CSS design system, and JavaScript modules (`crypto.js`, `qrenc.js`, `canvas.js`, `app.js`).
- **Serverless & Vercel Ready**: Out-of-the-box deployment for Vercel, Docker, Apache, Nginx, or static hosting.

---

## Architecture & File Structure

```text
blackend/
├── api/
│   ├── index.php              # Vercel serverless entrypoint for web UI
│   └── vault.php              # Vercel serverless entrypoint for Vault API
├── assets/
│   ├── css/
│   │   └── app.css            # Design system, glassmorphism, themes & responsive layouts
│   └── js/
│       ├── crypto.js          # Web Crypto AES-256-GCM, PBKDF2, chunking & dual-engine
│       ├── qrenc.js           # QR code generation engine (byte mode L v1-10) with SVG/PNG renderers
│       ├── canvas.js          # Interactive particle network & floating ember motes
│       └── app.js             # UI state machine, composer, receiver, archive & settings
├── config.php                 # Central backend configuration & environment handling
├── data/                      # Protected data storage (.htaccess deny all)
├── lib/
│   └── Vault.php              # Secure PHP Vault class (at-rest encryption, secure shredding, GC)
├── index.php                  # PHP entrypoint emitting security headers & rendering template
├── index.html                 # Standalone static HTML entrypoint for serverless/static hosting
├── vault.php                  # Backend REST API endpoint for vault operations
├── vercel.json                # Vercel deployment configuration
├── tests/
│   ├── test_crypto.js         # Automated JS syntax and cryptography test suite
│   └── test_vault.php         # Automated PHP backend unit test suite
└── README.md                  # System architecture and documentation
```

---

## Security & Link Formats

### 1. Direct In-Link Format (Pure Serverless)
```text
https://your-domain.com/#k1.Z8Vgr3mbC88.aJHiF0qRs7T2vXwYz3Lm5N8bC1dE6fGh4.Zml4ZWRrZXk
                         └─ Version & IV ─┘ └─ Ciphertext ─────────────────┘ └─ Decryption Key ┘
```
- Ciphertext and key stay after `#`.
- No server receives or stores anything.

### 2. Escrow Vault Format (Short URL)
```text
https://your-domain.com/?m=9f3ab2c1e4d86f07#k1.Z8Vgr3mbC88aJHiF0qRs7
└── Domain ───────────┘ └── Token ──────┘ └── Decryption Key (Never sent to server) ──┘
```
- **Token (`?m=...`)**: Random identifier pointing to encrypted noise in the vault.
- **Key (`#...`)**: Decryption key; never leaves the browser. Shredded from server on first read.

---

## Local Development & Testing

### Running the Web Server
- **PHP**:
  ```bash
  php -S localhost:8080
  ```
- **Node Static Server (Optional)**:
  ```bash
  npx serve .
  ```

### Running Automated Test Suites
- **JavaScript & Cryptography Tests**:
  ```bash
  node tests/test_crypto.js
  ```
- **PHP Backend Tests**:
  ```bash
  php tests/test_vault.php
  ```

---

## Deployment

### Vercel Deployment
Deploy instantly with Vercel CLI:
```bash
vercel
```

### Environment Variables
| Variable | Default | Description |
|---|---|---|
| `VAULT_SECRET` | System default | 256-bit hex secret for server-side at-rest encryption layer. |
| `VAULT_DATA_DIR` | `__DIR__ . '/data'` (or `/tmp/blackend_data` on Vercel) | Directory where vault data is stored. |

Generate a production secret:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.
