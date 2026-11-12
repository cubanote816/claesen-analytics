import { expect, test } from '@playwright/test';

test('unauthenticated visit lands on the Filament login page', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('input[type=email]')).toBeVisible();
    await expect(page.locator('input[type=password]')).toBeVisible();
    await expect(page.locator('button[type=submit]')).toBeVisible();
});
