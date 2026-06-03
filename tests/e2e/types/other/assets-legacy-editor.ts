import { expect, Page, test } from '@playwright/test';
import { login } from '../../utils/playwright-utils';

test.describe('assets in legacy non-iframed editor', () => {
  test('loads Blockstudio block CSS when a legacy API v1 block disables the iframe', async ({
    page,
  }: {
    page: Page;
  }) => {
    await login(page);

    await page.goto('/wp-admin/post.php?post=3900&action=edit');
    await expect(page.locator('.is-root-container')).toBeVisible({
      timeout: 30000,
    });
    await expect(page.locator('iframe[name="editor-canvas"]')).toHaveCount(0);
    await expect
      .poll(
        () => page.locator('#blockstudio-blockstudio-assets-test-css').count(),
        { timeout: 30000 },
      )
      .toBeGreaterThan(0);
  });
});
