# Pull Deployment

GitHub Actions builds the Laravel production package and publishes it as the mutable GitHub Release tag `production-latest`.
The production server pulls that package over outbound HTTPS and activates it locally.

## Required Server Values

Set these environment variables on the server:

```bash
export DEPLOY_PATH=/var/www/claesen-analytics
export GITHUB_REPOSITORY=cubanote816/claesen-analytics
export GITHUB_TOKEN=github_pat_read_only_token
```

`GITHUB_TOKEN` needs read-only access to repository contents. It is required for private repositories.

Optional values:

```bash
export GITHUB_RELEASE_TAG=production-latest
export PHP_BIN=php
export KEEP_RELEASES=5
export DEPLOY_MAINTENANCE_SECRET=admin-update
```

## First-Time Server Setup

Create the shared environment file once:

```bash
mkdir -p "$DEPLOY_PATH/shared"
nano "$DEPLOY_PATH/shared/.env"
```

Point the web server document root to:

```text
$DEPLOY_PATH/current/public
```

## Deploy Command

Run from any directory on the server:

```bash
bash /path/to/pull-deploy.sh
```

Or from a cron entry:

```cron
*/10 * * * * DEPLOY_PATH=/var/www/claesen-analytics GITHUB_REPOSITORY=cubanote816/claesen-analytics GITHUB_TOKEN=github_pat_read_only_token bash /var/www/claesen-analytics/pull-deploy.sh >> /var/log/claesen-deploy.log 2>&1
```

The server needs PHP, curl, tar, sha256sum, and outbound HTTPS access to `api.github.com`.
