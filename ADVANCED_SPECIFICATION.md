# blackend — Advanced Operational Architecture & High-Security Roadmap

This specification details the advanced logical architecture utilized by high-security operations, intelligence agencies, and darknet dead-drop protocols for zero-trace, self-destructing communications, alongside a technical roadmap of missing features and recommended future upgrades for **blackend**.

---

## 1. High-Security Operational Architecture Model

State-level covert communications and high-security dead-drop channels require resistance not only against standard eavesdropping, but also against **quantum computing attacks**, **traffic metadata analysis**, **physical device seizure**, and **coercive disclosure**.

```text
[ Sender Offline Environment ]
  │
  ├─ 1. Hybrid Post-Quantum Key Exchange (ML-KEM / Kyber-1024 + X25519)
  ├─ 2. Multi-Pass Plausible Deniability Encryption (Primary vs. Decoy PIN)
  ├─ 3. Steganographic Cover Payloads (LSB / Frequency Image Embedding)
  └─ 4. Onion / Mixnet Dead-Drop Routing (Tor / Nym Cover Traffic)
                             │
                             ▼
              [ Blinded Dead-Drop Rendezvous Server ]
                ├─ Zero Access Logs & Memory-Only Ramdisk
                ├─ Cryptographic Shredding (DoD 5220.22-M / Random Overwrite)
                └─ Blinded Token Indexing (Hash-Based Dead Drop)
                             │
                             ▼
[ Recipient Environment ]
  ├─ 1. Hardware Token / WebAuthn Challenge Unlocking (YubiKey / Secure Enclave)
  ├─ 2. In-Memory Decryption & Zero-Disk Rendering
  └─ 3. Immediate Memory Zeroization & Anti-Forensic Screen Shield
```

---

## 2. Core Cryptographic & Operational Pillars

### A. Post-Quantum Hybrid Cryptography
* **Current Baseline**: AES-256-GCM + PBKDF2-SHA256 (120,000 iterations).
* **High-Security Standard**: Hybrid Post-Quantum KEM (**ML-KEM / Kyber-1024** + X25519).
* **Purpose**: Protects messages against "Harvest Now, Decrypt Later" (HNDL) attacks where adversaries log encrypted network traffic to decrypt once quantum computers (CRQCs) become operational.

### B. Plausible Deniability & Decoy Payloads
* **Current Baseline**: Single PIN unlocks the actual payload; 3 wrong attempts destroy the envelope.
* **High-Security Standard**: Multi-Key Plausible Deniability.
  - **PIN Alpha** -> Decrypts real sensitive payload.
  - **PIN Beta (Decoy PIN)** -> Decrypts convincing decoy message (e.g. routine meeting notes), while silently destroying the real payload in background memory.
* **Purpose**: Resists physical coercion, forced disclosure, or threat actor interrogation.

### C. Steganographic Carrier Payloads
* **Current Baseline**: Payloads are serialized as JSON blobs or Hex/Base62 strings.
* **High-Security Standard**: Image / Audio LSB Steganography.
  - Encrypted ciphertext is embedded into noise bits of an innocent PNG, WEBP, or WAV file.
  - To external firewalls, DLP systems, and network proxies, the link points to a normal image asset (`/assets/img/photo.png`).

### D. Blinded Dead-Drop Rendezvous Protocol
* **Current Baseline**: Tokens (`/v/ash-fox-42`) map directly to folder paths.
* **High-Security Standard**: Double-Blinded Rendezvous Tokens.
  - Sender and recipient derive a shared time-bound token using HKDF (`HMAC-SHA256(Secret, Date)`).
  - Server index contains only ephemeral hash anchors; the server cannot associate the upload transaction with the download request.

---

## 3. Missing Features & Recommended Upgrade Roadmap

To upgrade **blackend** into an operational state-level temporary messaging infrastructure, the following features and upgrades are recommended for implementation:

### 1. Post-Quantum Kyber/ML-KEM WebAssembly Module
- **Upgrade**: Integrate `@noble/post-quantum` or Wasm-compiled Kyber-1024 ML-KEM.
- **Benefit**: Ensures 100% quantum-resistant key encapsulation for all direct and vault link payloads.

### 2. Steganographic Image Payload Export
- **Upgrade**: Allow senders to package the encrypted payload inside a downloadable PNG or image URL fragment.
- **Benefit**: Bypasses strict enterprise Deep Packet Inspection (DLP) and firewall content filters.

### 3. Hardware Security Token / WebAuthn Unlocking
- **Upgrade**: Support WebAuthn / FIDO2 YubiKey hardware token challenges for PIN envelopes.
- **Benefit**: Ensures messages can only be opened when a physical hardware security key is plugged into the recipient's device.

### 4. Plausible Deniability Multi-PIN Storage
- **Upgrade**: Allow senders to define both a **True PIN** and a **Duress PIN**.
- **Benefit**: Entering the Duress PIN renders a plausible decoy message while permanently shredding the primary envelope on the server.

### 5. In-Memory Anti-Forensic Screen Shield
- **Upgrade**: Add CSS `user-select: none`, Canvas screenshot-blocking overlays, and auto-clear DOM text upon window blur or tab change.
- **Benefit**: Minimizes residual RAM inspection and unauthorized screenshot captures.

---

## 4. Architectural Summary

| Security Feature | Current blackend Status | Operational High-Security Standard |
|---|---|---|
| **Symmetric Cipher** | AES-256-GCM | AES-256-GCM + ChaCha20-Poly1305 Dual-Cipher |
| **Key Exchange** | HKDF-SHA256 / WebCrypto | Hybrid ML-KEM (Kyber-1024) + X25519 |
| **Authentication** | PBKDF2-SHA256 (120,000 iters) | PBKDF2 + WebAuthn Hardware Token (FIDO2) |
| **Covert Carrier** | Direct Hash Fragment / Short URL | Steganographic PNG / Covert Carrier Payload |
| **Coercion Resistance** | 3-Strike Auto-Destruction | Plausible Deniability Duress PIN & Decoy Payload |
| **Server Storage** | Overwritten with `random_bytes` | Memory-Only RAMDisk + DoD Multi-Pass Shredding |
