import { test, expect } from '@playwright/test';

const PAGE = '/select-options-test/';

test.describe('bsui/select options attribute', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(PAGE);
		await page.waitForSelector('[data-bsui-select-root]');
	});

	test('renders options from the options attribute', async ({ page }) => {
		const root = page.locator('[data-bsui-select-root]');
		await root.locator('[data-bsui-select-trigger]').click();
		const options = root.locator('[role="option"]');
		await expect(options).toHaveCount(6);
		await expect(options.first()).toContainText('Apple');
	});

	test('popup uses fixed positioning so it escapes overflow', async ({ page }) => {
		const root = page.locator('[data-bsui-select-root]');
		await root.locator('[data-bsui-select-trigger]').click();
		const listbox = root.locator('[role="listbox"]');
		await expect(listbox).toBeVisible();
		await expect(listbox).toHaveCSS('position', 'fixed');
	});

	test('multi-select keeps the popup open and joins labels', async ({ page }) => {
		const root = page.locator('[data-bsui-select-root]');
		const trigger = root.locator('[data-bsui-select-trigger]');
		await trigger.click();
		const listbox = root.locator('[role="listbox"]');

		await root.locator('[role="option"]', { hasText: 'Apple' }).click();
		await root.locator('[role="option"]', { hasText: 'Cherry' }).click();

		await expect(listbox).toBeVisible();
		await expect(trigger).toContainText('Apple');
		await expect(trigger).toContainText('Cherry');
	});

	test('emits a change event in multiple mode', async ({ page }) => {
		const root = page.locator('[data-bsui-select-root]');
		await page.evaluate(() => {
			(window as any).__lastChange = null;
			document
				.querySelector('[data-bsui-select-root]')
				?.addEventListener('change', (e: any) => {
					(window as any).__lastChange = e.detail?.value;
				});
		});

		await root.locator('[data-bsui-select-trigger]').click();
		await root.locator('[role="option"]', { hasText: 'Banana' }).click();

		const detail = await page.evaluate(() => (window as any).__lastChange);
		expect(Array.isArray(detail)).toBe(true);
		expect(detail).toContain('banana');
	});
});
