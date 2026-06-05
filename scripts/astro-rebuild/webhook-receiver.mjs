#!/usr/bin/env node
/**
 * Astro Rebuild Webhook Receiver
 *
 * Receives POST /rebuild from the Laravel backend, validates HMAC-SHA256,
 * responds 202 immediately, then builds Astro and deploys atomically
 * via a releases + symlink strategy (capistrano-style).
 *
 * GET /health returns the current build state.
 *
 * No npm dependencies — only Node.js built-ins.
 */

import http          from 'node:http';
import crypto        from 'node:crypto';
import fs            from 'node:fs';
import path          from 'node:path';
import { spawn }     from 'node:child_process';

// ─── Configuration ────────────────────────────────────────────────────────────

const CONFIG = {
    port:               parseInt(process.env.WEBHOOK_PORT              ?? '9000', 10),
    secret:             process.env.WEBHOOK_SECRET                     ?? '',
    projectDir:         process.env.WEBHOOK_PROJECT_DIR                ?? '',
    releasesDir:        process.env.WEBHOOK_RELEASES_DIR               ?? '',
    currentLink:        process.env.WEBHOOK_CURRENT_LINK               ?? '',
    environment:        process.env.WEBHOOK_ENV                        ?? 'production',
    keepReleases:       parseInt(process.env.WEBHOOK_KEEP_RELEASES     ?? '5',   10),
    tolerance:          parseInt(process.env.WEBHOOK_SIGNATURE_TOLERANCE ?? '300', 10),
    npmScript:          process.env.WEBHOOK_NPM_SCRIPT                 ?? 'build',
};

validateConfig();

// ─── Build state ──────────────────────────────────────────────────────────────

let building        = false;
let pending         = false;   // another rebuild requested while building
let lastSuccessAt   = null;    // ISO string
let lastFailureAt   = null;
let lastError       = null;
let currentRelease  = readCurrentRelease();

// ─── HTTP server ──────────────────────────────────────────────────────────────

const server = http.createServer((req, res) => {
    if (req.method === 'POST' && req.url === '/rebuild') {
        handleRebuild(req, res);
    } else if (req.method === 'GET' && req.url === '/health') {
        handleHealth(req, res);
    } else {
        res.writeHead(404, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Not Found' }));
    }
});

server.listen(CONFIG.port, '0.0.0.0', () => {
    log('info', `Webhook receiver listening on port ${CONFIG.port} (env: ${CONFIG.environment})`);
});

// ─── Handlers ────────────────────────────────────────────────────────────────

function handleRebuild(req, res) {
    const chunks = [];

    req.on('data', chunk => chunks.push(chunk));
    req.on('end', () => {
        const rawBody  = Buffer.concat(chunks).toString('utf8');
        const timestamp = req.headers['x-webhook-timestamp'] ?? '';
        const signature = req.headers['x-webhook-signature'] ?? '';

        // ── Validate HMAC ────────────────────────────────────────────────────
        const validationError = validateSignature(rawBody, timestamp, signature);
        if (validationError) {
            log('warn', `Rejected rebuild request: ${validationError}`);
            res.writeHead(401, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: validationError }));
            return;
        }

        // ── Respond 202 immediately ──────────────────────────────────────────
        // The build runs fully asynchronously after this point.
        res.writeHead(202, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ accepted: true, building, pending: building }));

        // ── Queue or start build ─────────────────────────────────────────────
        if (building) {
            // Mark pending so the running build triggers another one on completion.
            pending = true;
            log('info', 'Build already in progress — marked pending for next cycle.');
            return;
        }

        executeBuild();
    });

    req.on('error', err => {
        log('error', `Request error: ${err.message}`);
        res.writeHead(500);
        res.end();
    });
}

function handleHealth(req, res) {
    const payload = {
        ok:              true,
        environment:     CONFIG.environment,
        building,
        pending,
        last_success_at: lastSuccessAt,
        last_failure_at: lastFailureAt,
        last_error:      lastError,
        current_release: currentRelease,
    };

    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(payload));
}

// ─── HMAC validation ─────────────────────────────────────────────────────────

/**
 * Returns null on success, or an error string on failure.
 *
 * Signs: timestamp + "." + rawBody  (exact bytes, matches Laravel sendWebhook()).
 * Uses timingSafeEqual to prevent timing attacks.
 */
function validateSignature(rawBody, timestamp, signature) {
    if (!timestamp) return 'Missing X-Webhook-Timestamp header';
    if (!signature) return 'Missing X-Webhook-Signature header';

    // Timestamp freshness check
    const ts  = parseInt(timestamp, 10);
    const now = Math.floor(Date.now() / 1000);
    if (isNaN(ts) || Math.abs(now - ts) > CONFIG.tolerance) {
        return `Timestamp out of tolerance (|${now} - ${ts}| > ${CONFIG.tolerance}s)`;
    }

    // Compute expected HMAC
    const toSign   = `${timestamp}.${rawBody}`;
    const expected = 'sha256=' + crypto
        .createHmac('sha256', CONFIG.secret)
        .update(toSign, 'utf8')
        .digest('hex');

    // Constant-time comparison — always compare buffers of same length.
    const a = Buffer.from(expected,   'utf8');
    const b = Buffer.from(signature.length === expected.length ? signature : expected, 'utf8');

    if (!crypto.timingSafeEqual(a, b) || signature.length !== expected.length) {
        return 'Invalid signature';
    }

    return null;
}

// ─── Build orchestration ──────────────────────────────────────────────────────

async function executeBuild() {
    building = true;
    pending  = false;

    const releaseId  = releaseTimestamp();
    const releaseDir = path.resolve(CONFIG.releasesDir, releaseId);

    log('info', `Starting build → release ${releaseId}`);

    try {
        fs.mkdirSync(CONFIG.releasesDir, { recursive: true });

        await spawnBuild(releaseDir);
        atomicSwap(releaseDir);
        pruneReleases();

        currentRelease = releaseId;
        lastSuccessAt  = new Date().toISOString();
        lastError      = null;
        log('info', `Build succeeded → current = ${releaseId}`);

    } catch (err) {
        lastFailureAt = new Date().toISOString();
        lastError     = err.message;
        log('error', `Build failed: ${err.message}`);

        // Remove the failed release directory if it was created.
        try { fs.rmSync(releaseDir, { recursive: true, force: true }); } catch {}

    } finally {
        building = false;

        if (pending) {
            // Another rebuild was requested while we were building.
            log('info', 'Pending rebuild detected — starting next build cycle.');
            setImmediate(executeBuild);
        }
    }
}

/**
 * Run the build command and stream output to stdout/stderr.
 *
 * Production: npm run <WEBHOOK_NPM_SCRIPT> -- --outDir <releaseDir>
 * Tests:      WEBHOOK_BUILD_CMD="node /path/to/test-build.mjs"
 *             The command receives <releaseDir> as its last positional argument.
 */
function spawnBuild(releaseDir) {
    const testCmd = process.env.WEBHOOK_BUILD_CMD;

    let cmd, args, cwd;
    if (testCmd) {
        const parts = testCmd.trim().split(/\s+/);
        cmd  = parts[0];
        args = [...parts.slice(1), releaseDir];
        cwd  = process.cwd();
    } else {
        cmd  = 'npm';
        args = ['run', CONFIG.npmScript, '--', '--outDir', releaseDir];
        cwd  = CONFIG.projectDir;
    }

    log('info', `Exec: ${cmd} ${args.join(' ')}`);

    return new Promise((resolve, reject) => {
        const child = spawn(cmd, args, {
            cwd,
            stdio: 'pipe',
            env:   { ...process.env, FORCE_COLOR: '0' },
        });

        child.stdout.on('data', d => process.stdout.write(d));
        child.stderr.on('data', d => process.stderr.write(d));

        child.on('close', code => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`${cmd} exited with code ${code}`));
            }
        });

        child.on('error', err => reject(new Error(`Failed to start ${cmd}: ${err.message}`)));
    });
}

// ─── Atomic deployment ────────────────────────────────────────────────────────

/**
 * Atomically swap the `current` symlink to the new release directory.
 *
 * Strategy:
 *   1. Create a temporary symlink at `<currentLink>.tmp`
 *   2. rename(2) `<currentLink>.tmp` → `<currentLink>`
 *
 * rename(2) is atomic on Linux — there is no window where `current` is absent.
 */
function atomicSwap(releaseDir) {
    const tmpLink = CONFIG.currentLink + '.tmp';

    // Remove stale tmp if it exists from a previous failed swap.
    try { fs.unlinkSync(tmpLink); } catch {}

    fs.symlinkSync(releaseDir, tmpLink);
    fs.renameSync(tmpLink, CONFIG.currentLink);

    log('info', `Symlink → ${releaseDir}`);
}

/**
 * Delete oldest releases beyond the keep-limit.
 * Never deletes the currently active release.
 */
function pruneReleases() {
    let active = null;
    try { active = path.basename(fs.readlinkSync(CONFIG.currentLink)); } catch {}

    const all = fs.readdirSync(CONFIG.releasesDir)
        .filter(d => /^\d{8}T\d{6}$/.test(d))
        .sort()        // ascending by timestamp string
        .reverse();    // newest first

    all.slice(CONFIG.keepReleases).forEach(r => {
        if (r === active) return; // never prune the live release
        const dir = path.join(CONFIG.releasesDir, r);
        try {
            fs.rmSync(dir, { recursive: true, force: true });
            log('info', `Pruned release ${r}`);
        } catch (err) {
            log('warn', `Could not prune ${r}: ${err.message}`);
        }
    });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Returns YYYYMMDDTHHmmss (UTC) — sortable, URL-safe, human-readable. */
function releaseTimestamp() {
    return new Date().toISOString()  // 2026-06-05T14:23:01.000Z
        .replace(/[-:]/g, '')        // 20260605T142301.000Z
        .substring(0, 15);           // 20260605T142301
}

/** Derives the current release name from the symlink target. */
function readCurrentRelease() {
    try {
        return path.basename(fs.readlinkSync(CONFIG.currentLink));
    } catch {
        return null;
    }
}

function log(level, msg) {
    const ts = new Date().toISOString();
    console[level === 'error' ? 'error' : 'log'](`[${ts}] [${level.toUpperCase()}] ${msg}`);
}

function validateConfig() {
    const required = ['secret', 'projectDir', 'releasesDir', 'currentLink'];
    const missing  = required.filter(k => !CONFIG[k]);

    if (missing.length) {
        console.error(`[FATAL] Missing required env vars: ${missing.map(k => {
            return { secret: 'WEBHOOK_SECRET', projectDir: 'WEBHOOK_PROJECT_DIR',
                     releasesDir: 'WEBHOOK_RELEASES_DIR', currentLink: 'WEBHOOK_CURRENT_LINK' }[k];
        }).join(', ')}`);
        process.exit(1);
    }

    // Warn if running with guard off during development
    if (CONFIG.environment === 'development') {
        const absReleases = path.resolve(CONFIG.releasesDir);
        if (absReleases.startsWith('/var/www')) {
            console.error('[FATAL] WEBHOOK_RELEASES_DIR points to /var/www in development — aborting.');
            process.exit(1);
        }
    }
}
