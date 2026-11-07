# CLA-661 — Real client IP behind the sbapu03 tunnel (trustProxies)

Branch `fix/cla-661-trust-proxies` from `main` (41d67bd). Traffic that enters through 192.168.60.10 reaches 192.168.254.106 over a reverse SSH tunnel and arrives as REMOTE_ADDR 127.0.0.1; trust only that hop, and only X-Forwarded-For.

## Specs

- **S1** — A request from 127.0.0.1 carrying `X-Forwarded-For` resolves `$request->ip()` to the address the edge appended (the rightmost entry); entries a client put on the left are ignored. User words: "Propagá la IP real del cliente por X-Forwarded-For".
- **S2** — A request from any other address (direct LAN, e.g. 192.168.254.51) keeps its own REMOTE_ADDR as `ip()`, even if it sends `X-Forwarded-For`.
- **S3** — Only `X-Forwarded-For` is trusted: `X-Forwarded-Host`, `X-Forwarded-Proto` and `X-Forwarded-Port` from the tunnel do not change host, scheme or port.
- **S4** — The storage-URL decision in `AppServiceProvider` uses the raw connection address, so it does not depend on trusted proxies: requests to the backoffice host from 127.0.0.1 (tunnel) keep `MEDIA_URL`; direct LAN requests to the backoffice host get `APP_URL/storage`. Audit claim: "Si trustProxies devuelve la IP real, las peticiones del túnel con Host backoffice.claesen.local dejarían de cumplir esa condición" — today boot() runs before the TrustProxies middleware, so the break is latent (ordering-dependent), not immediate.
- **S5** — Campaign tracking events store the real client IP for tunnel requests (the CLA-654/656 symptom: "los cuatro eventos guardan una IP private/local").

## Tasks

| ID | Specs | Route | Commit |
|---|---|---|---|
| T1 | S1, S2, S3, S4, S5 | inline: `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, tests, docs | — |
| T2 | S1–S5 | gentle-ai-verify (Risk: item 2) | — |
| T3 | — | push branch and PR (user decides merge, which deploys) | — |

## Log

- **L1** (user, 2026-11-07, to the production instance): "Propagá la IP real del cliente por X-Forwarded-For". Then, after the audit and plan in this session: "go".
- **L2** Audit (read-only, production instance + user sudo on 192.168.60.10): every location proxying to 127.0.0.1:9443 on 192.168.60.10 sets `X-Forwarded-For $proxy_add_x_forwarded_for` and `X-Real-IP $remote_addr` (safety `/sanctum/` `/api/`, fieldops `/api/`, service `/sanctum/` `/api/`, tracking snippet). `backend-public.conf` on 192.168.254.106 does not use the tunnel. No local client calls 127.0.0.1:443.
- **L3** Behaviour changes on deploy: `throttle:*` and the login limiter (`core-login:{email}|{ip}`) go from one shared bucket for all tunnel users to per-IP; Turnstile, anti-spam, access analytics and tracking store real IPs from then on (history stays 127.0.0.1).
