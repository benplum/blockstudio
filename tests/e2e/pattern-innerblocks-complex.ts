import { test, expect, Page, Frame } from '@playwright/test';
import {
  login,
  getEditorCanvas,
  resetPageState,
} from './utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.beforeEach(async () => {
  await resetPageState(page);
  canvas = await getEditorCanvas(page);
});

test.afterAll(async () => {
  await page.close();
});

type EditorBlock = {
  attributes: Record<string, unknown>;
  innerBlocks: EditorBlock[];
  name: string;
};

const insertPattern = async (searchTerm: string) => {
  await page.click('button[aria-label="Block Inserter"]');
  await page.click('role=tab[name="Patterns"]');
  await page.fill('input[placeholder="Search"]', searchTerm);

  const pattern = page
    .locator('[class*="block-editor-block-patterns-list"] [role="option"]')
    .first();
  await expect(pattern).toBeVisible({ timeout: 10000 });
  await pattern.click();
};

const getEditorBlocks = async (): Promise<EditorBlock[]> =>
  page.evaluate(() => {
    const serialize = (block: any): EditorBlock => ({
      attributes: block.attributes,
      innerBlocks: block.innerBlocks.map(serialize),
      name: block.name,
    });

    return (window as any).wp.data
      .select('core/block-editor')
      .getBlocks()
      .map(serialize);
  });

test.describe('Pattern with complex InnerBlocks (issue #28)', () => {
  test('core inner blocks render immediately on pattern insert', async () => {
    await insertPattern('InnerBlocks Complex');

    await expect(canvas.getByText('Get Involved', { exact: true })).toBeVisible(
      {
        timeout: 15000,
      },
    );
    await expect(
      canvas.getByText('A description.', { exact: true }),
    ).toBeVisible();
    await expect(canvas.locator('.wp-block-buttons')).toBeVisible();

    await expect
      .poll(async () => JSON.stringify(await getEditorBlocks()), {
        timeout: 15000,
      })
      .toContain('core/buttons');

    const blocks = await getEditorBlocks();
    expect(blocks).toMatchObject([
      {
        innerBlocks: [
          { name: 'core/heading' },
          { name: 'core/paragraph' },
          {
            innerBlocks: [{ name: 'core/button' }],
            name: 'core/buttons',
          },
        ],
        name: 'blockstudio/component-innerblocks-nested-wrapper',
      },
    ]);
  });

  test('nested Blockstudio inner blocks render immediately on pattern insert', async () => {
    await insertPattern('InnerBlocks Nested Blockstudio');

    await expect(
      canvas.getByText('Frequently Asked Questions', { exact: true }),
    ).toBeVisible({ timeout: 15000 });
    await expect(
      canvas.locator('summary', { hasText: 'Question?' }),
    ).toBeVisible();

    await expect
      .poll(async () => JSON.stringify(await getEditorBlocks()), {
        timeout: 15000,
      })
      .toContain('blockstudio/component-innerblocks-accordion');

    const blocks = await getEditorBlocks();
    expect(blocks).toMatchObject([
      {
        innerBlocks: [
          { name: 'core/heading' },
          {
            innerBlocks: [
              {
                attributes: { summary: 'Question?' },
                innerBlocks: [{ name: 'core/paragraph' }],
                name: 'core/details',
              },
            ],
            name: 'blockstudio/component-innerblocks-accordion',
          },
        ],
        name: 'blockstudio/component-innerblocks-nested-wrapper',
      },
    ]);
  });
});
