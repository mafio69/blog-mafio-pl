const { test, expect } = require('@playwright/test');

// Krok 1 (dowod srodowiska): publiczna strona /login realnie renderuje formularz.
// Nie rusza danych. Kolejny krok: logowanie admina + seed + token round-trip na /admin.
test('strona logowania renderuje formularz', async ({ page }, testInfo) => {
    await page.goto('/login');

    await expect(page.locator('input[type="password"]')).toBeVisible();

    await page.screenshot({ path: testInfo.outputPath('login.png'), fullPage: true });
});
