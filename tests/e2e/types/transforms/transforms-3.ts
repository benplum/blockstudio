import { Page, Frame, expect } from '@playwright/test';
import { count, testType } from '../../utils/playwright-utils';

testType('transforms-3', '"text":19', () => {
  return [
    {
      description: 'check transforms',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-transforms-3"]');
        await page.click(
          '.block-editor-block-toolbar__block-controls .components-dropdown-menu__toggle'
        );
        await expect(
          page.getByRole('menuitem', { name: /Native Transforms 1/ })
        ).toHaveCount(0);
        await expect(
          page.getByRole('menuitem', { name: /Native Transforms 2/ })
        ).toHaveCount(0);
      },
    },
    {
      description: 'regex transform',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-transforms-3"]');
        await page.keyboard.press('Enter');
        await page.keyboard.press('-');
        await page.keyboard.press('-');
        await page.keyboard.press('-');
        await page.keyboard.press('Enter');
        await canvas
          .locator('[data-type="blockstudio/type-transforms-3"]')
          .nth(1)
          .click();
        await count(canvas, '[data-type="blockstudio/type-transforms-3"]', 2);
      },
    },
    {
      description: 'prefix transform',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-transforms-3"]');
        await page.keyboard.press('Enter');
        await page.keyboard.press('?');
        await page.keyboard.press('?');
        await page.keyboard.press('?');
        await page.keyboard.press('Space');
        await count(canvas, '[data-type="blockstudio/type-transforms-3"]', 3);
      },
    },
  ];
});
