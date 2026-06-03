import { Page, Frame, expect } from '@playwright/test';
import { testType } from '../../utils/playwright-utils';

testType('transforms-2', '"text":"Tester"', () => {
  return [
    {
      description: 'check transforms',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-transforms-2"]');
        await page.click(
          '.block-editor-block-toolbar__block-controls .components-dropdown-menu__toggle'
        );
        await expect(
          page.getByRole('menuitem', { name: /Native Transforms 1/ })
        ).toBeVisible();
      },
    },
  ];
});
