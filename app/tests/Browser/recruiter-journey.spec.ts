import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

async function runReleasedJourney(page: import('@playwright/test').Page, locale: 'en' | 'ckb') {
    await page.goto('/');
    await page.getByLabel('Interface language').selectOption(locale);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ckb' ? 'rtl' : 'ltr');
    const samples = page.locator('input[name="demo-sample"]');
    await expect(samples).toHaveCount(6);
    await samples.first().check();
    await page.getByRole('button', { name: /Run the governed demonstration|پیشاندانی بەڕێوەبراو/ }).click();
    await expect(page.getByText(locale === 'ckb' ? 'ئەنجامی ئاراستەکردن' : 'Routing result')).toBeVisible();
    await page.getByRole('button', { name: /Why this match|بۆچی ئەم هاوتاییە/ }).click();
    await expect(page.getByRole('heading', { name: locale === 'ckb' ? 'ڕوونکردنەوەی LIME' : 'LIME explanation' })).toBeVisible();
    await expect(page.getByText(/approximate and non-causal|ناوخۆیی و نزیکەیی و ناهۆکارییە/)).toBeVisible();
}

test('English recruiter journey completes the approved demo', async ({ page }) => {
    await runReleasedJourney(page, 'en');
});

test('Sorani recruiter journey uses full RTL and the same governed boundary', async ({ page }) => {
    await runReleasedJourney(page, 'ckb');
});

test('the API exhibit executes live REST and GraphQL calls', async ({ page }) => {
    await page.goto('/api-docs');
    await page.getByRole('button', { name: 'GET health' }).click();
    await expect(page.locator('.http-status')).toHaveText('200');
    await expect(page.locator('.response-code')).toContainText('curated_demo');
    await page.getByRole('button', { name: 'POST demo classification' }).click();
    await expect(page.locator('.http-status')).toHaveText('200');
    await expect(page.locator('.response-code')).toContainText('"demo_only": true');
    await expect(page.locator('.response-code')).toContainText('"status": "matched"');
    await page.getByRole('button', { name: 'POST demo explanation' }).click();
    await expect(page.locator('.http-status')).toHaveText('200');
    await expect(page.locator('.response-code')).toContainText('"explained_class"');
    await expect(page.locator('.response-code')).toContainText('"features"');
    await page.getByRole('button', { name: 'GQL categories' }).click();
    await expect(page.locator('.http-status')).toHaveText('200');
    await expect(page.locator('.response-code')).toContainText('antenatal-appointments');
});

test('offline mode never enables a model call', async ({ page, context }) => {
    await page.goto('/');
    await context.setOffline(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));
    const runButton = page.getByRole('button', { name: /Run the governed demonstration/ });
    if (await runButton.count()) await expect(runButton).toBeDisabled();
    await expect(page.getByLabel('Public free text remains locked')).toBeDisabled();
    await context.setOffline(false);
});

test('home and API pages have no serious automated accessibility violations', async ({ page }) => {
    for (const path of ['/', '/api-docs']) {
        await page.goto(path);
        const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        const material = results.violations.filter(violation => ['serious', 'critical'].includes(violation.impact ?? ''));
        expect(material, `${path}: ${material.map(violation => violation.id).join(', ')}`).toEqual([]);
    }
});
