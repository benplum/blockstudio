import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;

const LARGE_TEXT = 'Large RichText copy';
const SMALL_TEXT = 'Terminal RichText copy';

const BLOCK_CONTENT = [
  `<!-- wp:blockstudio/type-preload-richtext-class {"blockstudio":{"attributes":{"content":"${SMALL_TEXT}","cssClass":"preload-richtext-small text-[13px] leading-6"}}} /-->`,
  `<!-- wp:blockstudio/type-preload-richtext-class {"blockstudio":{"attributes":{"content":"${LARGE_TEXT}","cssClass":"preload-richtext-large text-lg leading-8"}}} /-->`,
].join('\n');

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser, request }) => {
  const res = await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: BLOCK_CONTENT },
  });
  expect(res.ok()).toBeTruthy();

  const context = await browser.newContext();
  page = await context.newPage();
  page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async ({ request }) => {
  await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: '' },
  });
  await page.close();
});

test.describe('RichText Preload Class Identity', () => {
  test('preloads include attributes for same-type RichText instances', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`);
    await getEditorCanvas(page);

    await page.waitForFunction(
      () => {
        const bs = (window as any).blockstudio;
        if (!bs?.blockstudioBlocks) return false;
        const blocks = bs.blockstudioBlocks;
        const entries = Array.isArray(blocks) ? blocks : Object.values(blocks);
        return (entries as any[]).filter(
          (e: any) => e.blockName === 'blockstudio/type-preload-richtext-class',
        ).length === 2;
      },
      { timeout: 30000 },
    );

    const entries = await page.evaluate(() => {
      const raw = (window as any).blockstudio.blockstudioBlocks;
      return ((Array.isArray(raw) ? raw : Object.values(raw)) as any[])
        .filter((e: any) => e.blockName === 'blockstudio/type-preload-richtext-class')
        .map((e: any) => ({
          content: e.attributes?.blockstudio?.attributes?.content,
          cssClass: e.attributes?.blockstudio?.attributes?.cssClass,
        }));
    });

    expect(entries).toEqual([
      {
        content: SMALL_TEXT,
        cssClass: 'preload-richtext-small text-[13px] leading-6',
      },
      {
        content: LARGE_TEXT,
        cssClass: 'preload-richtext-large text-lg leading-8',
      },
    ]);
  });

  test('same block type keeps the correct RichText wrapper classes', async () => {
    const renderCalls: string[] = [];

    page.on('request', (req) => {
      const url = req.url();
      if (url.includes('/blockstudio/v1/gutenberg/block/render/')) {
        renderCalls.push(url);
      }
    });

    await page.reload();
    const canvas = await getEditorCanvas(page);

    await expect(canvas.locator('.preload-richtext-small')).toHaveText(SMALL_TEXT);
    await expect(canvas.locator('.preload-richtext-large')).toHaveText(LARGE_TEXT);

    await expect
      .poll(
        () =>
          canvas.locator('p', { hasText: SMALL_TEXT }).evaluate((el) =>
            Array.from(el.classList).filter((className) =>
              className.startsWith('preload-richtext-'),
            ),
          ),
        { timeout: 30000 },
      )
      .toEqual(['preload-richtext-small']);

    await expect
      .poll(
        () =>
          canvas.locator('p', { hasText: LARGE_TEXT }).evaluate((el) =>
            Array.from(el.classList).filter((className) =>
              className.startsWith('preload-richtext-'),
            ),
          ),
        { timeout: 30000 },
      )
      .toEqual(['preload-richtext-large']);

    await page.waitForTimeout(1000);
    expect(renderCalls).toEqual([]);
  });
});
