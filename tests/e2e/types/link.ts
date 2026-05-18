import { Page, Frame } from '@playwright/test';
import {
  count,
  getEditorCanvas,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType(
  'link',
  '"link":{"id":"google.com","title":"google.com","url":"https:\\/\\/google.com","type":"URL"}',
  () => {
    return [
      {
        description: 'change link',
        testFunction: async (page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-link"]');
          await page.click(
            '.blockstudio-fields__field--link .components-button'
          );
          const edit = await page.$('[aria-label="Edit link"]');
          if (edit) {
            await edit.click();
          }
          await page.fill(
            '.block-editor-link-control__text-content .components-text-control__input',
            'Blockstudio'
          );
          await page.fill('[value="https://google.com"]', 'blockstudio.dev');
          await page.click('.block-editor-link-control__search-submit');
          await page.click('.components-modal__header [aria-label="Close"]');
          await saveAndReload(page);
        },
      },
      {
        description: 'check link',
        testFunction: async (_page: Page, canvas: Frame) => {
          await canvas.click('[data-type="blockstudio/type-link"]');
          await count(canvas, 'text=blockstudio.dev', 1);
        },
      },
      {
        description: 'create draft page from suggestion',
        testFunction: async (page: Page, canvas: Frame) => {
          const title = `Blockstudio Created Link ${Date.now()}`;

          await canvas.click('[data-type="blockstudio/type-link"]');
          await page.click(
            '.blockstudio-fields__field--link .components-button'
          );

          const edit = await page.$('[aria-label="Edit link"]');
          if (edit) {
            await edit.click();
          }

          await page
            .locator('.block-editor-link-control__search-input input')
            .fill(title);
          await page
            .getByRole('option', { name: new RegExp(`Create:.*${title}`) })
            .click();
          await page
            .locator('.block-editor-link-control')
            .getByText('page_id=')
            .waitFor({ state: 'visible' });
          await page.click('.components-modal__header [aria-label="Close"]');
          await saveAndReload(page);
          const reloadedCanvas = await getEditorCanvas(page);
          await text(reloadedCanvas, '?page_id=');
          await text(reloadedCanvas, '"type":"page"');
        },
      },
    ];
  }
);
