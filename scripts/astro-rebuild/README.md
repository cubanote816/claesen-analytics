# Astro Rebuild Webhook Receiver

Node.js HTTP server that runs on the Astro frontend server (`192.168.60.20`).
Receives signed rebuild webhooks from the Laravel backend (`192.168.60.10`),
responds `202 Accepted` immediately, then builds Astro and deploys atomically.

## How it works

```
Laravel backend                   Frontend server (192.168.60.20)
──────────────                    ───────────────────────────────
StaticSitePublicationService
  → POST /rebuild                 webhook-receiver.mjs
      HMAC validated              → 202 immediately
      202 received                → npm run build -- --outDir releases/20260605T142301/
                                  → ln -sfn (temp) → mv (atomic)
                                  → GET /health shows building state
```

### Deploy strategy (releases + atomic symlink)

```
/var/www/claesen-verlichting/v1/
  releases/
    20260604T143000/   ← kept (old)
    20260605T080000/   ← kept (old)
    20260605T142301/   ← active
  current → releases/20260605T142301/   ← web server document root
```

The swap is done via a temporary symlink renamed with `rename(2)` — atomic on Linux.
There is never a window where `current` is absent or broken.

---

## Installation on `192.168.60.20`

### 1. Copy the receiver script

```bash
sudo mkdir -p /opt/claesen
sudo cp webhook-receiver.mjs /opt/claesen/
sudo chmod +x /opt/claesen/webhook-receiver.mjs
```

### 2. Create a dedicated user

```bash
sudo useradd -r -s /bin/false -d /var/www astro-deploy
sudo chown -R astro-deploy:astro-deploy /var/www/claesen-verlichting
sudo chown -R astro-deploy:astro-deploy /var/www/astro-source   # Astro project
```

### 3. Configure environment

```bash
sudo mkdir -p /etc/claesen
sudo cp .env.example /etc/claesen/webhook-receiver.env
sudo nano /etc/claesen/webhook-receiver.env   # fill in all values
sudo chmod 640 /etc/claesen/webhook-receiver.env
sudo chown root:astro-deploy /etc/claesen/webhook-receiver.env
```

**Required variables:**

| Variable | Description |
|---|---|
| `WEBHOOK_PORT` | Port to listen on (`9000` prod, `9001` staging) |
| `WEBHOOK_SECRET` | Shared HMAC secret — must match `STATIC_SITE_WEBHOOK_SECRET` in Laravel |
| `WEBHOOK_PROJECT_DIR` | Path to the Astro project (`package.json` lives here) |
| `WEBHOOK_RELEASES_DIR` | Directory where versioned builds are stored |
| `WEBHOOK_CURRENT_LINK` | Symlink path — configure as document root in nginx/apache |
| `WEBHOOK_ENV` | `production` \| `staging` \| `development` |

**Optional variables:**

| Variable | Default | Description |
|---|---|---|
| `WEBHOOK_NPM_SCRIPT` | `build` | npm script to run |
| `WEBHOOK_KEEP_RELEASES` | `5` | Number of releases to keep |
| `WEBHOOK_SIGNATURE_TOLERANCE` | `300` | Max timestamp age in seconds |

### 4. Install and start the systemd service

```bash
sudo cp webhook-receiver.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable webhook-receiver
sudo systemctl start webhook-receiver
sudo systemctl status webhook-receiver
```

### 5. Verify logs

```bash
journalctl -u webhook-receiver -f
```

---

## Firewall — allow only backend server

The webhook port must be accessible **only from the backend** (`192.168.60.10`).
Block all other sources.

### Using `ufw`:

```bash
# Allow SSH (make sure this is set before adding restrictions)
sudo ufw allow 22/tcp

# Allow webhook only from backend
sudo ufw allow from 192.168.60.10 to any port 9000 proto tcp   # production
sudo ufw allow from 192.168.60.10 to any port 9001 proto tcp   # staging

# Block all other access to webhook ports
sudo ufw deny 9000/tcp
sudo ufw deny 9001/tcp

sudo ufw enable
sudo ufw status
```

### Using `iptables`:

```bash
# Accept from backend
iptables -A INPUT -s 192.168.60.10 -p tcp --dport 9000 -j ACCEPT

# Drop from all other sources
iptables -A INPUT -p tcp --dport 9000 -j DROP

# Persist (Debian/Ubuntu)
sudo apt-get install iptables-persistent
sudo netfilter-persistent save
```

---

## Nginx document root configuration

Configure nginx to serve from the `current` symlink:

```nginx
server {
    listen 80;
    server_name claesen-verlichting.be www.claesen-verlichting.be;

    root /var/www/claesen-verlichting/v1/current;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

Enable `follow_symlinks` (default in nginx): no extra configuration needed.

---

## Development setup

In development, the receiver must **never** touch `/var/www`.
It validates this at startup and exits if misconfigured.

```bash
# .env for development
WEBHOOK_PORT=9000
WEBHOOK_SECRET=dev-secret
WEBHOOK_PROJECT_DIR=/path/to/website-claesen-v1
WEBHOOK_RELEASES_DIR=./.deploy/releases
WEBHOOK_CURRENT_LINK=./.deploy/current
WEBHOOK_ENV=development
```

Start manually:
```bash
node webhook-receiver.mjs
```

---

## Test commands

### Health check

```bash
curl -s http://192.168.60.20:9000/health | jq .
```

Expected response:
```json
{
  "ok": true,
  "environment": "production",
  "building": false,
  "pending": false,
  "last_success_at": "2026-06-05T14:23:01.000Z",
  "last_failure_at": null,
  "last_error": null,
  "current_release": "20260605T142301"
}
```

### Manual rebuild (with valid HMAC)

The following bash snippet generates a valid signed request for testing:

```bash
#!/usr/bin/env bash
SECRET="your-webhook-secret"
URL="http://192.168.60.20:9000/rebuild"

TIMESTAMP=$(date +%s)
BODY='{"source":"backend","environment":"production","reason":"manual","force":true}'
TO_SIGN="${TIMESTAMP}.${BODY}"
SIG="sha256=$(echo -n "$TO_SIGN" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')"

curl -s -X POST "$URL" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Timestamp: $TIMESTAMP" \
  -H "X-Webhook-Signature: $SIG" \
  -d "$BODY"
```

Expected response: `{"accepted":true,"building":false,"pending":false}` (HTTP 202)

### Test invalid signature (should return 401)

```bash
curl -s -X POST http://192.168.60.20:9000/rebuild \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Timestamp: $(date +%s)" \
  -H "X-Webhook-Signature: sha256=invalid" \
  -d '{}'
```

### Test stale timestamp (should return 401)

```bash
curl -s -X POST http://192.168.60.20:9000/rebuild \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Timestamp: 1000000000" \
  -H "X-Webhook-Signature: sha256=anything" \
  -d '{}'
```

---

## Logs and troubleshooting

| Command | Purpose |
|---|---|
| `journalctl -u webhook-receiver -f` | Tail live logs |
| `journalctl -u webhook-receiver --since "1 hour ago"` | Recent logs |
| `systemctl status webhook-receiver` | Service status |
| `ls -la /var/www/claesen-verlichting/v1/releases/` | List releases |
| `readlink /var/www/claesen-verlichting/v1/current` | Check active release |

---

## Rolling back to a previous release

```bash
# List available releases (newest last)
ls /var/www/claesen-verlichting/v1/releases/

# Rollback atomically (replace current symlink)
TARGET="/var/www/claesen-verlichting/v1/releases/20260604T143000"
LINK="/var/www/claesen-verlichting/v1/current"
ln -sfn "$TARGET" "${LINK}.tmp" && mv "${LINK}.tmp" "$LINK"

# Verify
readlink /var/www/claesen-verlichting/v1/current
```
