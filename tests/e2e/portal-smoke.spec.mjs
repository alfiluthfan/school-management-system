import { test, expect } from '@playwright/test'

const username = process.env.PORTAL_E2E_USERNAME
const password = process.env.PORTAL_E2E_PASSWORD

// A deliberate FAIL rather than a silent skip prevents an untested browser flow
// from being reported as successful. Use a dedicated disposable Admin account.
test('authenticated portal read-only navigation, logout and guest guard', async ({ page }) => {
  if (!username || !password) throw new Error('Set PORTAL_E2E_USERNAME and PORTAL_E2E_PASSWORD for a disposable Admin account')

  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await page.locator('#identifier').fill(username)
  await page.locator('#password').fill(password)
  await page.getByRole('button', { name: /Masuk ke dashboard/i }).click()
  await expect(page).toHaveURL(/\/dashboard(?:\?|$)/)

  for (const path of ['/attendance', '/approvals', '/finance', '/announcements', '/reports', '/master-data']) {
    const response = await page.goto(path)
    expect(response?.status(), `${path} failed for Admin`).toBe(200)
    await expect(page.locator('#app')).toBeVisible()
    await expect(page).not.toHaveURL(/\/login(?:\?|$)/)
  }

  await page.getByRole('button', { name: 'Keluar dari akun' }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await page.goto('/master-data')
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
})
