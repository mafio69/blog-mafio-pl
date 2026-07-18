const { test: setup, expect } = require('@playwright/test');

const authFile = '.auth/user.json';

// Loguje sie RAZ jako testowy admin i zapisuje sesje. Pozostale testy dziedzicza ten stan
// (storageState) i startuja juz zalogowane. User/haslo to atrapy z config/packages/test.
setup('logowanie testowego admina', async ({ page }) => {
    await page.goto('/login');

    await page.fill('input[name="_username"]', process.env.E2E_USER || 'admin');
    await page.fill('input[name="_password"]', process.env.E2E_PASS || 'e2e-test-pass');
    await page.click('form button');

    // default_target_path=/admin -> po zalogowaniu jestesmy w panelu (dowod, ze zalogowani).
    await expect(page).toHaveURL(/\/admin/);

    await page.context().storageState({ path: authFile });
});
