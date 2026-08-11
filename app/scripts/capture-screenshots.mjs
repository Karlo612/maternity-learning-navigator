import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const baseUrl = process.env.BASE_URL ?? 'http://127.0.0.1:8080';
const screenshotPath = (name) => fileURLToPath(new URL(`../../docs/screenshots/${name}`, import.meta.url));
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

try {
    await page.goto(baseUrl);
    await page.locator('input[name="demo-sample"]').first().waitFor();
    await page.screenshot({ path: screenshotPath('home.png'), fullPage: true });

    await page.locator('input[name="demo-sample"]').first().check();
    await page.getByRole('button', { name: /Run the governed demonstration/ }).click();
    await page.getByText('Routing result').waitFor();
    await page.getByRole('button', { name: /Why this match/ }).click();
    await page.getByRole('heading', { name: 'LIME explanation' }).waitFor();
    await page.locator('.site-header').evaluate((element) => { element.style.display = 'none'; });
    await page.locator('.router-card').screenshot({ path: screenshotPath('lime.png') });

    await page.goto(`${baseUrl}/evidence`);
    await page.locator('.evidence-grid').first().waitFor();
    await page.screenshot({ path: screenshotPath('evidence.png'), fullPage: true });
} finally {
    await browser.close();
}
