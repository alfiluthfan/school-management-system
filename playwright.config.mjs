import { defineConfig, devices } from '@playwright/test'

const baseURL = process.env.PORTAL_E2E_BASE_URL || 'http://127.0.0.1:8000'
if (!/^https?:\/\//.test(baseURL)) throw new Error('PORTAL_E2E_BASE_URL must be an HTTP(S) URL')

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/*.spec.mjs',
  timeout: 30_000,
  workers: 1,
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  reporter: 'list',
  use: {
    baseURL,
    ...devices['Desktop Chrome'],
    // Do not automatically capture personal student data in reports/traces.
    screenshot: 'off',
    trace: 'off',
    video: 'off',
  },
  // Start the user's existing Laravel server separately; do not silently deploy a test DB.
})
