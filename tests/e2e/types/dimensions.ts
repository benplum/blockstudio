import { Frame, Page, expect } from '@playwright/test';
import {
  saveAndReload,
  testType,
  text,
} from '../utils/playwright-utils';

testType('dimensions', '"dimensions":{"top":"default","bottom":"default"}', () => {
  return [
    {
      description: 'renders dimensions control',
      testFunction: async (page: Page, canvas: Frame) => {
        await canvas.click('[data-type="blockstudio/type-dimensions"]');
        await expect(
          page.locator('.blockstudio-fields__field--dimensions select').first()
        ).toHaveValue('default');
      },
    },
    {
      description: 'updates and persists linked top and bottom values',
      testFunction: async (page: Page, canvas: Frame) => {
        const controls = page.locator('.blockstudio-fields__field--dimensions select');
        await controls.nth(0).selectOption({ value: 'small' });
        await controls.nth(1).selectOption({ value: 'large' });

        await text(canvas, '"dimensions":{"top":"small","bottom":"large"');
        await saveAndReload(page);
      },
    },
  ];
});
