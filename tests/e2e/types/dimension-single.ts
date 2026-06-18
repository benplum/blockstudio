import { Page, Frame, expect } from '@playwright/test';
import {
  count,
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType('dimension-single', '"dimensionSingle":"default"', () => {
  return [
    {
      description: 'renders default value',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-dimension-single"]');
        await expect(
          page.locator('.blockstudio-fields__field--dimensionSingle select')
        ).toHaveValue('default');
        await expect(canvas.locator('.blockstudio-test__block')).toHaveClass(
          /margin-default/
        );
      },
    },
    {
      description: 'updates and persists value',
      testFunction: async (page: Page, canvas: Frame) => {
        const fieldSelect = page.locator(
          '.blockstudio-fields__field--dimensionSingle select'
        );

        if ((await fieldSelect.count()) > 0) {
          await fieldSelect.first().selectOption({ value: 'small' });
        }
        await text(canvas, '"dimensionSingle":"small"');
        await expect(canvas.locator('.blockstudio-test__block')).toHaveClass(
          /margin-small/
        );
        await saveAndReload(page);
      },
    },
    {
      description: 'keeps value after reload',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-dimension-single"]');
        await expect(
          page.locator('.blockstudio-fields__field--dimensionSingle select')
        ).toHaveValue('small');
        await text(canvas, '"dimensionSingle":"small"');
        await expect(canvas.locator('.blockstudio-test__block')).toHaveClass(
          /margin-small/
        );
        await count(page, '.blockstudio-fields__field--dimensionSingle select', 1);
      },
    },
  ];
});
