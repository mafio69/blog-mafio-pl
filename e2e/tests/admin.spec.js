const { test, expect } = require('@playwright/test');

// Przyklad testu, jaki pisza INNE modele: startuje juz zalogowany (storageState),
// wiec od razu testuje panel — zero logowania, zero hasel.
test('zalogowany admin wchodzi do panelu', async ({ page }, testInfo) => {
    await page.goto('/admin');

    // Gdybysmy NIE byli zalogowani, /admin przekierowuje na /login. Zostanie na /admin = auth OK.
    await expect(page).toHaveURL(/\/admin/);
    await expect(page.locator('input[name="_password"]')).toHaveCount(0);

    await page.screenshot({ path: testInfo.outputPath('admin.png'), fullPage: true });
});
