#!/usr/bin/env node
// Failing build fixture — exits with code 1 to simulate a broken npm build.
// Used by webhook-receiver.test.mjs to test that a failed build leaves current untouched.
process.exit(1);
