# AfyaNova V3 — Security & Data Protection Architecture

## 1. Security Philosophy: Defense-in-Depth

Healthcare data is among the most sensitive personal data in existence. AfyaNova V3 enforces a **Defense-in-Depth** security model where every layer—network, transport, application, domain logic, and database—enforces independent security controls.

```
┌────────────────────────────────────────────────────────┐
│ 1. Network / Edge: TLS 1.3, HSTS, Cloudflare DDoS, WAF │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ 2. Application Perimeter: Rate Limiting, CORS, CSRF, CSP│
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ 3. Auth & Identity: MFA, Argon2id, Session Expiry, IDOR│
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ 4. Domain Authorization: Multi-Facility Scoped RBAC    │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ 5. Database Engine: PostgreSQL RLS, Column Encryption  │
└───────────────────────────┬────────────────────────────┘
                            │
┌───────────────────────────▼────────────────────────────┐
│ 6. Storage & Audit: Encrypted S3, Immutable Audit Logs │
└────────────────────────────────────────────────────────┘
```

---

## 2. Authentication & Identity Security

1. **Password Hashing**: Passwords are encrypted using **Argon2id** (`memory_cost=65536, time_cost=4, threads=1`), providing state-of-the-art resistance to GPU cracking.
2. **Multi-Factor Authentication (MFA)**:
   - Mandatory for high-privilege roles (Tenant Admins, Cashiers, Medical Directors).
   - Implemented via standard Time-based One-Time Password (TOTP, RFC 6238) with secure recovery codes.
3. **Session Hardening**:
   - HTTP-only, `SameSite=Strict`, `Secure` cookies.
   - Automatic session invalidation upon password change or privilege revocation.
   - Idle timeout after 15 minutes of inactivity for clinical and cashier workstations.

---

## 3. Data Protection & Cryptography

### 3.1. Encryption in Transit
- Mandatory TLS 1.3 with strict cipher suites.
- HTTP Strict Transport Security (HSTS) with `max-age=31536000; includeSubDomains; preload`.

### 3.2. Encryption at Rest
- Full-disk encryption (LUKS / AWS EBS AES-256) on database and cache volumes.
- **Application-Level Column Encryption**: Highly sensitive fields (e.g., patient National ID / NIDA, HIV / STI clinical records where mandated) utilize AES-256-GCM encryption with tenant-specific encryption keys.

### 3.3. Secure Medical Document Storage
- Diagnostic reports, lab PDFs, and patient scans are stored in private object storage (S3/MinIO).
- Direct public URLs are never generated. Access is granted exclusively via **temporary signed URLs** with a maximum lifetime of 5 minutes, verified against user permissions.

---

## 4. Application Web Vulnerability Mitigations

| Threat | Mitigation Mechanism in AfyaNova V3 |
| :--- | :--- |
| **Cross-Site Request Forgery (CSRF)** | Inertia.js and Laravel enforce cryptographic CSRF token validation on every state-mutating HTTP verb (`POST`, `PUT`, `PATCH`, `DELETE`). |
| **Cross-Site Scripting (XSS)** | Vue 3 template compiler automatically context-escapes rendered content. Strict Content Security Policy (CSP) headers block inline scripts. |
| **SQL Injection** | Exclusively uses parameterized queries via PDO and Eloquent ORM. Raw SQL strings with concatenated variables are strictly prohibited by CI linters. |
| **Mass Assignment** | Eloquent models enforce `$guarded = ['*']` or explicit `$fillable` whitelisting. Controllers accept only validated DTOs from Form Requests. |
| **Insecure Direct Object Reference (IDOR)**| UUIDv7 identifiers prevent ID enumeration. Laravel Policies verify ownership and facility scope on every resource lookup. |
| **Brute Force & Denial of Service** | Tiered rate limiters: Login (5 attempts/min), Clinical APIs (120 req/min), Payment Webhooks (60 req/min). |

---

## 5. Secret Management & Configuration
- Environment secrets are never committed to version control.
- In production, secrets are injected via runtime environment variables or secure key vaults (AWS Secrets Manager / HashiCorp Vault).
- Database credentials, payment gateway private keys, and webhook signing secrets are segregated per environment.
