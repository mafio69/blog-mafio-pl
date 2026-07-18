const { defineConfig } = require('@playwright/test');

// Model: aplikacja blog w OSOBNEJ instancji e2e (APP_ENV=test), Playwright osobno.
// Projekt "setup" loguje sie RAZ i zapisuje sesje; "chromium" dziedziczy ja (storageState),
// wiec kazdy test funkcji startuje juz zalogowany — bez dotykania logowania.
module.exports = defineConfig({
    testDir: 'tests',
    outputDir: '.pw-output',
    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8033',
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.js/ },
        {
            name: 'chromium',
            use: { storageState: '.auth/user.json' },
            dependencies: ['setup'],
        },
    ],
});
