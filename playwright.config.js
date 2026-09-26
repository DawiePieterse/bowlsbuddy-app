// Browser tests for the member booking flow (PLAN.md Phase 3), at phone and desktop widths.
// Run with: scripts/e2e.sh (prepares an own database and starts the app).
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: 'tests/Browser',
    timeout: 60_000,
    retries: 0,
    workers: 1, // the flows share one database
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8901',
        screenshot: 'only-on-failure',
        // A preinstalled Chromium (e.g. E2E_CHROMIUM=/opt/pw-browsers/chromium) beats downloading one.
        launchOptions: process.env.E2E_CHROMIUM ? { executablePath: process.env.E2E_CHROMIUM } : {},
    },
    projects: [
        { name: 'phone', use: { viewport: { width: 390, height: 844 } } },
        { name: 'desktop', use: { viewport: { width: 1200, height: 800 } } },
    ],
});
