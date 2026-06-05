#!/usr/bin/env node
// Test build fixture — creates a minimal Astro-like output in <releaseDir>.
// Used by webhook-receiver.test.mjs via WEBHOOK_BUILD_CMD.
// Receives the release directory as its last positional argument.
import fs from 'node:fs';

const releaseDir = process.argv[process.argv.length - 1];
if (!releaseDir) { console.error('Usage: test-build.mjs <releaseDir>'); process.exit(1); }

fs.mkdirSync(releaseDir, { recursive: true });
fs.writeFileSync(`${releaseDir}/index.html`, '<!DOCTYPE html><html><body>Test Release</body></html>\n');
