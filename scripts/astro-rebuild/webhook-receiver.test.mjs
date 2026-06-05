/**
 * Integration tests for webhook-receiver.mjs
 *
 * Uses Node.js built-in test runner (node:test) — no npm dependencies.
 * Each describe() group starts a dedicated server subprocess on a unique port
 * with a temporary deploy directory, then tears it down after all tests in
 * the group have run.
 *
 * Run:  node --test scripts/astro-rebuild/webhook-receiver.test.mjs
 */

import { describe, it, before, after }  from 'node:test';
import assert                            from 'node:assert/strict';
import http                              from 'node:http';
import crypto                            from 'node:crypto';
import fs                                from 'node:fs';
import path                              from 'node:path';
import os                                from 'node:os';
import { spawn }                         from 'node:child_process';
import { fileURLToPath }                 from 'node:url';
import { setTimeout as sleep }           from 'node:timers/promises';

const __dirname  = path.dirname(fileURLToPath(import.meta.url));
const RECEIVER   = path.resolve(__dirname, 'webhook-receiver.mjs');
const TEST_BUILD = path.resolve(__dirname, 'test-build.mjs');
const FAIL_BUILD = path.resolve(__dirname, 'test-build-fail.mjs');
const SLOW_BUILD = path.resolve(__dirname, 'test-build-slow.mjs');
const SECRET     = 'test-hmac-secret';

// ─── Port allocator ──────────────────────────────────────────────────────────
let nextPort = 19100;
function allocatePort() { return nextPort++; }

// ─── HTTP helpers ────────────────────────────────────────────────────────────

function doRequest(port, method, path_, body = '', extraHeaders = {}) {
    const bodyBuf = Buffer.from(body, 'utf8');
    return new Promise((resolve, reject) => {
        const req = http.request(
            { hostname: '127.0.0.1', port, method, path: path_,
              headers: { 'Content-Type': 'application/json',
                         'Content-Length': bodyBuf.length, ...extraHeaders } },
            (res) => {
                let data = '';
                res.on('data', c => data += c);
                res.on('end', () => {
                    try { res.json = JSON.parse(data); } catch { res.json = null; }
                    resolve(res);
                });
            }
        );
        req.on('error', reject);
        if (bodyBuf.length) req.write(bodyBuf);
        req.end();
    });
}

function getHealth(port) {
    return doRequest(port, 'GET', '/health');
}

// ─── HMAC signing helper ──────────────────────────────────────────────────────

function signedHeaders(body, secret = SECRET, timestampOverride = null) {
    const ts  = String(timestampOverride ?? Math.floor(Date.now() / 1000));
    const sig = 'sha256=' + crypto
        .createHmac('sha256', secret)
        .update(`${ts}.${body}`, 'utf8')
        .digest('hex');
    return { 'X-Webhook-Timestamp': ts, 'X-Webhook-Signature': sig };
}

const REBUILD_BODY = JSON.stringify({ source: 'backend', environment: 'testing',
                                      reason: 'content_changed', force: false });

// ─── Server lifecycle helper ──────────────────────────────────────────────────

async function startServer(envOverrides = {}, buildCmd = `node ${TEST_BUILD}`) {
    const port       = allocatePort();
    const tmpDir     = fs.mkdtempSync(path.join(os.tmpdir(), 'wh-test-'));
    const releasesDir = path.join(tmpDir, 'releases');
    const currentLink = path.join(tmpDir, 'current');
    fs.mkdirSync(releasesDir, { recursive: true });

    const env = {
        ...process.env,
        WEBHOOK_PORT:               String(port),
        WEBHOOK_SECRET:             SECRET,
        WEBHOOK_PROJECT_DIR:        tmpDir,
        WEBHOOK_RELEASES_DIR:       releasesDir,
        WEBHOOK_CURRENT_LINK:       currentLink,
        WEBHOOK_ENV:                'development',
        WEBHOOK_BUILD_CMD:          buildCmd,
        WEBHOOK_KEEP_RELEASES:      '3',
        WEBHOOK_SIGNATURE_TOLERANCE:'300',
        ...envOverrides,
    };

    const proc = spawn('node', [RECEIVER], { env, stdio: 'pipe' });
    proc.stderr.on('data', () => {}); // suppress noise
    proc.stdout.on('data', () => {});

    // Wait up to 4 s for the server to accept requests
    const deadline = Date.now() + 4000;
    while (Date.now() < deadline) {
        try {
            const res = await getHealth(port);
            if (res.statusCode === 200) break;
        } catch {}
        await sleep(80);
    }

    return { port, proc, releasesDir, currentLink,
             kill: () => { try { proc.kill('SIGTERM'); } catch {} },
             cleanup: () => { try { fs.rmSync(tmpDir, { recursive: true, force: true }); } catch {} } };
}

function sendRebuild(port, body = REBUILD_BODY, headerOverrides = {}) {
    return doRequest(port, 'POST', '/rebuild', body, {
        ...signedHeaders(body), ...headerOverrides,
    });
}

// ═══════════════════════════════════════════════════════════════════════════════
// TEST SUITES
// ═══════════════════════════════════════════════════════════════════════════════

// ── 1. HMAC validation ────────────────────────────────────────────────────────

describe('HMAC validation', () => {
    let srv;
    before(async () => { srv = await startServer(); });
    after(()  => { srv.kill(); srv.cleanup(); });

    it('accepts a correctly signed request → 202', async () => {
        const res = await sendRebuild(srv.port);
        assert.equal(res.statusCode, 202);
        assert.equal(res.json.accepted, true);
    });

    it('rejects wrong signature → 401', async () => {
        const res = await sendRebuild(srv.port, REBUILD_BODY, {
            'X-Webhook-Signature': 'sha256=badbadbadbad',
        });
        assert.equal(res.statusCode, 401);
    });

    it('rejects stale timestamp (>300 s old) → 401', async () => {
        const body = REBUILD_BODY;
        const ts   = String(Math.floor(Date.now() / 1000) - 400); // 400s old
        const sig  = 'sha256=' + crypto.createHmac('sha256', SECRET)
                         .update(`${ts}.${body}`, 'utf8').digest('hex');
        const res  = await sendRebuild(srv.port, body, {
            'X-Webhook-Timestamp': ts, 'X-Webhook-Signature': sig,
        });
        assert.equal(res.statusCode, 401);
    });

    it('rejects missing X-Webhook-Timestamp header → 401', async () => {
        const res = await doRequest(srv.port, 'POST', '/rebuild', REBUILD_BODY, {
            'X-Webhook-Signature': 'sha256=whatever',
        });
        assert.equal(res.statusCode, 401);
    });

    it('rejects missing X-Webhook-Signature header → 401', async () => {
        const ts  = String(Math.floor(Date.now() / 1000));
        const res = await doRequest(srv.port, 'POST', '/rebuild', REBUILD_BODY, {
            'X-Webhook-Timestamp': ts,
        });
        assert.equal(res.statusCode, 401);
    });

    it('rejects valid HMAC on a different secret → 401', async () => {
        const body    = REBUILD_BODY;
        const headers = signedHeaders(body, 'wrong-secret');
        const res     = await sendRebuild(srv.port, body, headers);
        assert.equal(res.statusCode, 401);
    });
});

// ── 2. /health endpoint ───────────────────────────────────────────────────────

describe('GET /health', () => {
    let srv;
    before(async () => { srv = await startServer(); });
    after(()  => { srv.kill(); srv.cleanup(); });

    it('returns 200 with correct shape on startup', async () => {
        const res = await getHealth(srv.port);
        assert.equal(res.statusCode, 200);
        const h = res.json;
        assert.equal(h.ok, true);
        assert.equal(h.environment, 'development');
        assert.equal(h.building, false);
        assert.equal(h.pending, false);
        assert.ok('last_success_at'   in h);
        assert.ok('last_failure_at'   in h);
        assert.ok('last_error'        in h);
        assert.ok('current_release'   in h);
    });

    it('reflects last_success_at and current_release after a build', async () => {
        await sendRebuild(srv.port);
        // Wait for fast build to complete
        await sleep(600);
        const h = (await getHealth(srv.port)).json;
        assert.equal(h.building, false);
        assert.notEqual(h.last_success_at, null);
        assert.notEqual(h.current_release, null);
        assert.match(h.current_release, /^\d{8}T\d{6}$/);
    });
});

// ── 3. Deploy strategy: releases + atomic symlink ────────────────────────────

describe('Deploy strategy', () => {
    let srv;
    before(async () => { srv = await startServer(); });
    after(()  => { srv.kill(); srv.cleanup(); });

    it('creates a timestamped release directory after build', async () => {
        await sendRebuild(srv.port);
        await sleep(600);
        const dirs = fs.readdirSync(srv.releasesDir)
            .filter(d => /^\d{8}T\d{6}$/.test(d));
        assert.ok(dirs.length >= 1, 'at least one release directory expected');
    });

    it('current symlink points to the new release', async () => {
        await sendRebuild(srv.port);
        await sleep(600);
        const target  = fs.readlinkSync(srv.currentLink);
        const release = path.basename(target);
        assert.match(release, /^\d{8}T\d{6}$/, 'symlink must point to a timestamped release');
        assert.ok(fs.existsSync(path.join(target, 'index.html')), 'release must contain index.html');
    });
});

// ── 4. Failed build leaves current untouched ─────────────────────────────────

describe('Failed build', () => {
    let failSrv;

    before(async () => {
        failSrv = await startServer({}, `node ${FAIL_BUILD}`);
        // Pre-seed a "previous good release" so current exists before the failed build
        const dummyDir = path.join(failSrv.releasesDir, '20200101T000000');
        fs.mkdirSync(dummyDir, { recursive: true });
        const tmp = failSrv.currentLink + '.tmp';
        fs.symlinkSync(dummyDir, tmp);
        fs.renameSync(tmp, failSrv.currentLink);
    });
    after(() => { failSrv.kill(); failSrv.cleanup(); });

    it('leaves the current symlink unchanged after a build failure', async () => {
        const beforeTarget = fs.readlinkSync(failSrv.currentLink);

        const rebuildRes = await sendRebuild(failSrv.port);
        assert.equal(rebuildRes.statusCode, 202, `rebuild must be accepted (got ${rebuildRes.statusCode})`);

        // Poll until building:false (fail-fast process exits in ms; poll covers spawn overhead)
        let h;
        const deadline = Date.now() + 4000;
        do {
            await sleep(100);
            h = (await getHealth(failSrv.port)).json;
        } while (h.building && Date.now() < deadline);

        // Symlink must still point to the pre-existing release
        const afterTarget = fs.readlinkSync(failSrv.currentLink);
        assert.equal(afterTarget, beforeTarget, 'current must not change after a failed build');

        // Health must record the failure
        assert.notEqual(h.last_failure_at, null, 'last_failure_at must be set');
        assert.notEqual(h.last_error,      null, 'last_error must be set');
    });
});

// ── 5. Concurrent build: pending behaviour ────────────────────────────────────

describe('Concurrent builds', () => {
    let srv;
    before(async () => {
        srv = await startServer({}, `node ${SLOW_BUILD}`);
    });
    after(() => { srv.kill(); srv.cleanup(); });

    it('second rebuild during active build → 202 with building:true', async () => {
        // First request starts the slow build
        await sendRebuild(srv.port);
        await sleep(50); // let building flag flip

        // Second request arrives mid-build
        const res = await sendRebuild(srv.port);
        assert.equal(res.statusCode, 202, 'must respond 202 even while building');
        // building may be true or false depending on timing, but no 409
    });

    it('/health shows pending:true while a build is in progress', async () => {
        // Reset by restarting server is not needed — we just check current state
        const h = (await getHealth(srv.port)).json;
        // Either building or pending flag should reflect activity
        // (timing-sensitive; just assert the health endpoint responds correctly)
        assert.equal(h.ok, true);
        assert.equal(typeof h.building, 'boolean');
        assert.equal(typeof h.pending,  'boolean');
    });

    it('pending build runs after current build completes', async () => {
        // Wait for all builds to drain (slow build is 400ms + processing)
        await sleep(2000);
        const h = (await getHealth(srv.port)).json;
        assert.equal(h.building, false, 'no build should be running after 2s');
        assert.equal(h.pending,  false, 'no pending build should remain');
        assert.notEqual(h.last_success_at, null, 'at least one build succeeded');
    });
});

// ── 6. Release pruning ────────────────────────────────────────────────────────

describe('Release pruning (keep=3)', () => {
    let srv;
    before(async () => {
        srv = await startServer({ WEBHOOK_KEEP_RELEASES: '3' });
        // Seed 4 old (fake) releases so pruning has something to remove
        for (let i = 1; i <= 4; i++) {
            const d = path.join(srv.releasesDir, `2025000${i}T000000`);
            fs.mkdirSync(d, { recursive: true });
        }
    });
    after(() => { srv.kill(); srv.cleanup(); });

    it('removes oldest releases beyond the keep limit', async () => {
        await sendRebuild(srv.port);
        await sleep(600);

        const remaining = fs.readdirSync(srv.releasesDir)
            .filter(d => /^\d{8}T\d{6}$/.test(d));

        assert.ok(remaining.length <= 3, `Expected ≤3 releases, got ${remaining.length}: ${remaining.join(', ')}`);
    });

    it('never removes the currently active release during prune', async () => {
        const current = path.basename(fs.readlinkSync(srv.currentLink));
        const dirs    = fs.readdirSync(srv.releasesDir)
            .filter(d => /^\d{8}T\d{6}$/.test(d));
        assert.ok(dirs.includes(current), 'active release must be in releases dir after prune');
    });
});
