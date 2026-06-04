import { test, expect, Page } from '@playwright/test';
import { login, getEditorCanvas } from '../utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;
const MEDIA_IDS = ['1604', '1605'];

const BLOCK_CONTENT = `<!-- wp:blockstudio/type-files {"blockstudio":{"attributes":{"filesSingleId":1604,"filesMultipleId":[1604,1605]}}} /-->`;

let page: Page;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser, request }) => {
  const res = await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: BLOCK_CONTENT },
  });
  expect(res.ok()).toBeTruthy();

  const context = await browser.newContext();
  page = await context.newPage();
  await page.setViewportSize({ width: 1920, height: 1080 });
  await login(page);
});

test.afterAll(async ({ request }) => {
  await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
    data: { content: '' },
  });
  await page.close();
});

test.describe('Media Preloading', () => {
  test('files fields do not request already preloaded media', async () => {
    const fallbackRequests: string[] = [];

    page.on('request', (req) => {
      const requestUrl = new URL(req.url());

      if (
        !requestUrl.pathname.endsWith('/wp/v2/media') ||
        !requestUrl.searchParams.has('include')
      ) {
        return;
      }

      const includedIds = requestUrl.searchParams
        .get('include')!
        .split(',')
        .map((id) => id.trim());

      if (includedIds.some((id) => MEDIA_IDS.includes(id))) {
        fallbackRequests.push(req.url());
      }
    });

    await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`);
    await getEditorCanvas(page);

    await page.waitForFunction(
      (ids) => {
        const media = (window as any).blockstudio?.media;
        return (
          media &&
          (ids as string[]).every(
            (id) => media[id]?.id === Number.parseInt(id, 10),
          )
        );
      },
      MEDIA_IDS,
      { timeout: 30000 },
    );

    await page.waitForTimeout(5000);

    expect(fallbackRequests).toEqual([]);
  });
});
