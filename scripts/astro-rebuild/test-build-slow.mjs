#!/usr/bin/env node
// Slow test build fixture — waits 400 ms then creates output.
// Used to test concurrent-build behaviour (build-in-progress + pending).
import fs from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const releaseDir = process.argv[process.argv.length - 1];
if (!releaseDir) { console.error('Usage: test-build-slow.mjs <releaseDir>'); process.exit(1); }

await sleep(400);
fs.mkdirSync(releaseDir, { recursive: true });
fs.writeFileSync(`${releaseDir}/index.html`, '<!DOCTYPE html><html><body>Slow Release</body></html>\n');
