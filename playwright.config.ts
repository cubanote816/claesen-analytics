import { defineConfig, devices } from '@playwright/test';

// The app is NOT started by Playwright: bring it up separately (Sail) and
// point E2E_BASE_URL at it if it is not on the default http://localhost:8001.
export default defineConfig({
    testDir: 'tests/e2e',
    outputDir: 'test-results/e2e',
    retries: 0,
    reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8001',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
