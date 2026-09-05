# blackend — the chat that forgets

**blackend** is an end-to-end (E2E) encrypted, self-destructing temporary messaging and file sharing web application with a server-blind escrow vault.

Messages and file attachments are encrypted directly in the browser. The decryption key rides in the URL fragment (`#`) which browsers never transmit to the server. The backend vault receives and holds only encrypted noise, which is permanently shredded upon the first read or when the expiration fuse triggers.

---

## Key Features

- **Browser-Based End-to-End Encryption**: AES-256-GCM encryption performed entirely client-side using Web Crypto API.
- **Server-Blind Vault**: The backend never sees or stores decryption keys, IP addresses, user agents, or cookies.
- **Single-Read Shredding**: As soon as a message is fetched, the vault overwrites the ciphertext with noise before unlinking.
- **Encrypted File Attachments**: Files are chunked into 256 KB segments, encrypted in browser, and escrowed securely.
- **PIN Protection**: Wrap keys with 600,000 PBKDF2 rounds and a 4-digit PIN. Three incorrect attempts immediately destroy the message.
- **Configurable Fuses**: Automatic destruction upon first read, 60 seconds, 10 minutes, 1 hour, or custom hard caps.
- **Local Device Archive**: Messages are saved locally in `localStorage` under pseudonyms. History never leaves your device.
- **Integrated QR Code Generator**: Share encrypted links with a single scan and download high-resolution QR codes.
- **Vercel & Serverless Ready**: Designed to run seamlessly on Vercel serverless functions or traditional PHP environments.

---

## Security & Architecture

### 1. Anatomy of an Encrypted Link

```text
https://your-domain.com/?m=9f3ab2c1e4d86f07#k1.Z8Vgr3mbC88aJHiF0qRs7
└── Domain ───────────┘ └── Token ──────┘ └── Fragment Key (Never sent to server) ──┘
```

- **The Token (`?m=...`)**: A randomly generated identifier pointing to encrypted noise in the vault. Contains zero payload metadata.
- **The Fragment Key (`#...`)**: The AES-256-GCM encryption key. RFC 3986 specifies HTTP clients do not transmit URL fragments to servers. The vault never receives the key.

### 2. At-Rest & Escrow Layer

Even if server disks or backups were seized, the vault applies an additional server-side AES-256-GCM layer using `VAULT_SECRET` at rest. Without both the server secret and the client URL fragment key, payloads cannot be decrypted.

---

## Local Development

### Requirements

- PHP 8.1+ with OpenSSL and JSON extensions enabled.

### Quick Start

1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/blackend.git
   cd blackend
   ```

2. Start the local PHP development server:
   ```bash
   php -S localhost:8080
   ```

3. Open your browser and navigate to `http://localhost:8080`.

---

## Vercel Deployment

`blackend` includes out-of-the-box support for [Vercel](https://vercel.com) using the community PHP runtime [`vercel-php`](https://github.com/vercel-community/php).

### Deploying via Vercel CLI

1. Install Vercel CLI:
   ```bash
   npm i -g vercel
   ```

2. Deploy the project:
   ```bash
   vercel
   ```

### Vercel Configuration (`vercel.json`)

`vercel.json` configures serverless execution using `vercel-php@0.9.0`:

```json
{
  "version": 2,
  "functions": {
    "*.php": {
      "runtime": "vercel-php@0.9.0"
    }
  },
  "routes": [
    {
      "src": "/vault.php",
      "dest": "/vault.php"
    },
    {
      "src": "/(.*)",
      "dest": "/index.php"
    }
  ]
}
```

In serverless environments such as Vercel, `blackend` automatically stores temporary vault files in `/tmp/blackend_data` (writable ephemeral storage in serverless environments).

---

## Environment Variables & Configuration

You can customize the application behavior in `config.php` or using environment variables:

| Variable | Default | Description |
|---|---|---|
| `VAULT_SECRET` | System default | 256-bit hex secret for server-side at-rest encryption layer. |
| `VAULT_DATA_DIR` | `__DIR__ . '/data'` (or `/tmp/blackend_data` on Vercel) | Path to directory where vault data is stored. |

### Generation of a Production Secret

In production environments, set `VAULT_SECRET` in your server or Vercel Environment Variables:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## Garbage Collection (GC)

`blackend` automatically triggers an opportunistic garbage collection sweep (1-in-40 probability on API requests) to clean up expired or abandoned messages.

For traditional hostings or CRON setups, you can execute GC periodically:

```bash
php vault.php gc
```

---

## License

This project is licensed under the [MIT License](LICENSE).
