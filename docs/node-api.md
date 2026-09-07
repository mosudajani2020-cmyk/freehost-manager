# Node API — Authenticated Hosting Node Foundation (P3)

P3 establishes the **secure, narrowly scoped communication boundary** between the restricted provisioning worker and a hosting node. The worker never gains arbitrary execution; every request is HMAC-authenticated, replay-protected, idempotent and allow-listed.

**Important:** The Node API is **NOT** a generic command execution service. No `/execute`, `/shell`, `/run` or arbitrary OS delegation exists. P3 defines the contract; P4 will wire typed operations to real Linux isolation.

## 1. Purpose & Trust Boundary

```
Customer → FreeHost Manager → Provisioning Queue → Restricted Worker
                                                        │
                                                        ▼ (HTTPS + HMAC)
                                                   Hosting Node API
                                                        │
                                                        ▼ (typed ops)
                                                   Linux Hosting Node
```

* Control panel = low-privilege web app (cannot call node API directly).
* Worker = separate process/account, holds per-node encrypted secret, signs requests.
* Node API = separate trust boundary, authenticates, authorizes, validates, logs.
* Customer PHP never touches the Node API.

## 2. Architecture

Worker resolves target `hosting_nodes.api_url` (trusted DB, never customer input) → builds `NodeApiSigner` headers → `NodeApiClient` (curl, timeouts 5s connect / 10s total, `CURLOPT_SSL_VERIFYPEER` enforced in production, redirects disabled, response size implicitly bounded by `body_limit`) → Node API controller → `NodeApiAuthenticator` → `NodeRequestValidator` → `LocalMockNodeApiProvider` (P3; real provider in P4) → audit + idempotency.

## 3. Authentication Protocol

Headers:

```
X-FHM-Node-ID
X-FHM-Timestamp   (unix seconds, string)
X-FHM-Nonce       (32-128 hex, random)
X-FHM-Request-ID  (8-64 hex, correlation)
X-FHM-Idempotency-Key (8-100 alnum+_-)
X-FHM-Signature   (64 hex)
```

Canonical request:

```
METHOD
PATH (normalized, no query)
TIMESTAMP
NONCE
REQUEST_ID
IDEMPOTENCY_KEY
BODY_HASH (hex sha256(body))
```

Signature: `hex(hmac-sha256(secret, canonical))`, verified with `hash_equals()`.

## 4. Signature Covers All Critical Fields

Modifying method, path, timestamp, nonce, request_id, idempotency_key or body breaks the signature. Constant-time comparison prevents timing leaks.

## 5. Timestamp Rules

* Must be integer seconds, skew ≤ `NODE_API_TIMESTAMP_TOLERANCE` (default 300s, configurable via `config/node-api.php`).
* Missing/malformed/future/expired timestamps → 401.
* Production nodes must use NTP-synchronized time.

## 6. Nonce / Replay Protection

* Nonce is cryptographically random hex (16-128 chars).
* `(node_id, nonce)` has a `UNIQUE` constraint in `node_api_nonces`.
* First claim `INSERT` succeeds; replay attempts violate the constraint and return 409.
* Rows have `expires_at = NOW()+nonce_ttl` (600s default) and are cleaned probabilistically; prevents unbounded growth.

## 7. Idempotency

* ` (node_id, idempotency_key)` is `UNIQUE` in `node_api_idempotency`.
* State-changing requests must include a key; first request reserves the key (202), executes, then `complete()` stores `status_code + response_body`.
* Replay returns the stored response (200) without re-executing.
* Concurrent duplicate `INSERT` is race-safe via the unique constraint (second gets 409 `idempotency_conflict`).

## 8. Authorization & Endpoint Allow-List

* All endpoints versioned under `/v1/node/...`.
* Allow-list map in `config/node-api.php`:
  * `POST /v1/node/hosting/create-identity` → `hosting.create_identity`
  * `POST /v1/node/hosting/create-filesystem`
  * `POST /v1/node/hosting/configure-php-fpm`
  * `POST /v1/node/hosting/apply-resource-policy`
  * `POST /v1/node/hosting/suspend|unsuspend|terminate`
  * `GET /v1/node/health` (no auth, no secrets)
* Unknown paths, `/execute`, `/shell`, `/run` → 404. Authentication ≠ authorization; inactive/offline nodes → 403.

## 9. Payload Validation

* `NodeRequestValidator` enforces: `account_id` positive int, optional `plan_id`, rejects forbidden derived fields (`username`, `uid`, `filesystem_root`, `socket_path`, arbitrary PHP directives), string size ≤500, field count ≤20, JSON depth ≤5, body ≤ `body_limit` (64KB default). Invalid JSON → 400, validation failure → 422.

## 10. Rate Limiting & Size Limits

* DB-backed `RateLimiter` (`rate_limits` table). Auth failures per node/ip are limited (`NODE_API_RATE_LIMIT_AUTH_FAIL` 10 per 60s window). Exceeding → 429, without leaking secret existence.

## 11. TLS Requirements

* `config/node-api.php` `tls_verify`: production (`APP_ENV=production`) defaults to `true`; `NODE_API_TLS_VERIFY` env can override but production must never default to false. Worker validates `api_url` is `https` in production and refuses embedded credentials.

## 12. Secret Separation & Rotation

* `.env` (control panel) never holds plaintext node secret.
* `hosting_nodes.api_secret_encrypted` holds `aes-256-gcm` ciphertext; `api_key_hash` holds sha256 for preview only. Encryption key is `APP_KEY` (base64 32 bytes). `NodeCredentialManager::encrypt/decrypt` uses authenticated encryption, never logs plaintext.
* Worker decrypts at request time (`retrievePlain`). Node holds its own secret in its own env (`NODE_API_SECRET` / `.env.node`), never in the control panel DB plaintext.
* Rotation: `store()` overwrites `api_secret_encrypted`, `secret_rotated_at = NOW()`, `api_key_hash/preview` updated. Old nonces still apply; idempotency keys are per-secret-period logically distinct (or keep both hashes during transition — documented for future).

## 13. Error Handling & Logging

* Consistent JSON: `{"error":{"code":"..." },"request_id":"..."}`; never exception messages, stack traces, paths, SQL, secrets or class names.
* Audit table `node_api_audit` records `request_id, node_id, operation, idempotency_key, result, status_code, duration_ms, actor, failure_code` with secrets redacted.
* Worker logs include `request_id` correlation.

## 14. Retry / Failure Behavior

| HTTP | Meaning | Retryable? |
|------|---------|------------|
| 200 | success | no |
| 401 | auth failed | **no** |
| 403 | unauthorized/inactive | no |
| 404 | not found | no |
| 409 | replay/idempotency conflict | maybe (with new nonce/key) |
| 413 | too large | no |
| 422 | validation | no |
| 429 | rate limited | yes (backoff) |
| 500/503 | node error | yes (backoff, idempotent) |

P1 `provisioning_jobs` retry system is reused; network 503 with idempotency key is safe to retry.

## 15. Windows Development Mode & Local Mock

* Default node `local-mock-1` (`http://localhost/mock-provisioning`) triggers `NodeApiClient::localDispatch` → `LocalMockNodeApiProvider` (generates identity/filesystem/FPM preview without OS changes).
* `LinuxIsolationProvider` remains uninvoked on Windows.

## 16. What P3 Does NOT Implement

No real Linux user/group creation, filesystem provisioning, PHP-FPM deployment, cgroups, quotas, DB `CREATE USER/GRANT`, DNS, SSL/ACME, backups, mail, payment, SSH, or orchestration. Those are P4+.

## 17. Threat Model (20 scenarios)

1. **Stolen worker credential** — HMAC still needed; rotate via `NodeCredentialManager::store`, old secret invalidated. Residual: short window.
2. **Replay** — nonce unique constraint → 409.
3. **Modified body/path/method/timestamp/nonce/request_id/idempotency** — signature breaks → 401.
4. **Nonce collision** — unique constraint, second rejected.
5. **Idempotency race** — reserve with unique constraint → one wins, other gets stored or 409.
6. **Compromised worker** — still bounded by allow-list and payload validation; cannot execute arbitrary commands.
7. **Compromised node** — worker validates response shape; node cannot inject control-panel access.
8. **Customer privilege escalation** — customer cannot supply `username/uid/socket` (validator rejects).
9. **SSRF** — `api_url` from DB, HTTPS enforced, no customer-controlled URL, embedded creds rejected.
10. **Oversized request** — body_limit → 413.
11. **Node unavailable/timeout** — 503, worker retries with same idempotency key (safe).
12. **Network timeout after completion** — idempotency ensures second attempt returns stored result.
13. **Log leakage** — scrubbed logging, no secrets in `audit` or responses.
14. **Clock drift** — tolerance 300s; NTP required; out-of-window 401.
15. **Rotation** — `secret_rotated_at`, dual-secret window documented.
16. **Unauthorized node** — inactive node → 403.
17. **Dynamic endpoint abuse** — allow-list → 404.
18. **Generic command execution** — no such endpoint; validator forbids arbitrary fields.
19. **Stolen nonce** — single use only.
20. **Rate flood** — per-node/IP rate limiter → 429.

## 18. Operations & Cleanup

* `node_api_nonces.cleanup()` deletes `expires_at < NOW()`.
* `node_api_idempotency` retains for audit; periodic cleanup by age can be added (document retention policy).

## 19. Hosting Node API Interface

`HostingNodeApiInterface` exposes typed methods `createIdentity`, `createFilesystem`, `configurePhpFpm`, `applyResourcePolicy`, `suspend`, `unsuspend`, `terminate` — each validates input and never executes arbitrary commands.

## 20. Production Topology (P4 will extend)

```
Admin/Customer → FreeHost Manager → Queue → Restricted Worker (decrypts secret, HMAC signs) → HTTPS Node API (verifies, rate-limits, idempotency) → LocalMock/Real Linux provider
```
