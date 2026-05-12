import { expect, Frame, Page } from '@playwright/test';
import { testType, text } from '../../utils/playwright-utils';

testType(
  ['fields custom structures', 'fields-custom-structures'],
  false,
  () => {
    return [
      {
        groupName: 'custom field containing tabs',
        testCases: [
          {
            description: 'rewrites tab child ids and conditions',
            testFunction: async (page: Page, canvas: Frame) => {
              await expect(
                page.locator('[data-id="tabs_title"]'),
              ).toBeVisible();
              await expect(page.locator('[data-id="tabs_detail"]')).toHaveCount(
                0,
              );

              await page
                .locator('[data-id="tabs_show_detail"] input[type="checkbox"]')
                .check();
              await expect(
                page.locator('[data-id="tabs_detail"]'),
              ).toBeVisible();

              await page
                .locator('[data-id="tabs_title"] input')
                .fill('Tabbed custom');
              await page
                .locator('[data-id="tabs_detail"] input')
                .fill('Visible detail');

              await text(canvas, '"tabs_title":"Tabbed custom"');
              await text(canvas, '"tabs_detail":"Visible detail"');
            },
          },
          {
            description: 'expands a custom field inside a custom field tab',
            testFunction: async (page: Page, canvas: Frame) => {
              await page.getByRole('tab', { name: 'Nested Custom' }).click();

              await expect(
                page.locator('[data-id="tabs_inner_heading"]'),
              ).toBeVisible();
              await page
                .locator('[data-id="tabs_inner_heading"] input')
                .fill('Nested tab custom');

              await text(canvas, '"tabs_inner_heading":"Nested tab custom"');
            },
          },
        ],
      },
      {
        groupName: 'nested id-less groups',
        testCases: [
          {
            description:
              'render nested id-less group children with idStructure',
            testFunction: async (page: Page, canvas: Frame) => {
              await expect(
                page.locator('[data-id="nested_enable"]'),
              ).toBeVisible();
              await expect(
                page.locator('[data-id="nested_detail"]'),
              ).toHaveCount(0);

              await page
                .locator('[data-id="nested_enable"] input[type="checkbox"]')
                .check();
              await expect(
                page.locator('[data-id="nested_detail"]'),
              ).toBeVisible();
              await page
                .locator('[data-id="nested_detail"] input')
                .fill('Nested group detail');

              await text(canvas, '"nested_detail":"Nested group detail"');
            },
          },
        ],
      },
      {
        groupName: 'custom field inside id-bearing group',
        testCases: [
          {
            description: 'prefixes expanded custom field ids with the group id',
            testFunction: async (page: Page, canvas: Frame) => {
              await expect(
                page.locator('[data-id="holder_inside_heading"]'),
              ).toBeVisible();
              await page
                .locator('[data-id="holder_inside_heading"] input')
                .fill('Grouped custom');

              await text(canvas, '"holder_inside_heading":"Grouped custom"');
            },
          },
        ],
      },
      {
        groupName: 'block field with custom fields',
        testCases: [
          {
            description: 'expands custom fields from the referenced block',
            testFunction: async (page: Page, canvas: Frame) => {
              await expect(
                page.locator('[data-id="component_hero_heading"]'),
              ).toBeVisible();
              await page
                .locator('[data-id="component_hero_heading"] input')
                .fill('Block field custom');

              await text(canvas, '"component_custom"');
              await text(canvas, '"hero_heading":"Block field custom"');
            },
          },
        ],
      },
    ];
  },
);
