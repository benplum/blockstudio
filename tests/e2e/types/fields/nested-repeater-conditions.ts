import { expect, Frame, Page } from '@playwright/test';
import { testType } from '../../utils/playwright-utils';

testType(
  ['fields nested repeater conditions', 'fields-nested-repeater-conditions'],
  false,
  () => {
    return [
      {
        groupName: 'top-level custom field',
        testCases: [
          {
            description: 'hides conditional repeater fields by default',
            testFunction: async (page: Page, _canvas: Frame) => {
              await expect(
                page.locator('[data-id="top_image_default"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_image_sources"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_group_image_default"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_group_image_sources"]'),
              ).toHaveCount(0);
            },
          },
          {
            description: 'shows and hides conditional repeater fields',
            testFunction: async (page: Page, _canvas: Frame) => {
              const mediaType = page
                .locator('[data-id="top_media_type"] select')
                .first();

              await mediaType.selectOption('image');
              await expect(
                page.locator('[data-id="top_image_default"]'),
              ).toBeVisible();
              await expect(
                page.locator('[data-id="top_image_sources"]'),
              ).toBeVisible();

              await page
                .locator('[data-id="top_image_sources"]')
                .getByRole('button', { name: 'Add picture source' })
                .click();
              await expect(
                page.locator('[data-rfd-draggable-id="top_image_sources[0]"]'),
              ).toBeVisible();

              await mediaType.selectOption('video');
              await expect(
                page.locator('[data-id="top_image_default"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_image_sources"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-rfd-draggable-id="top_image_sources[0]"]'),
              ).toHaveCount(0);
            },
          },
          {
            description:
              'shows and hides conditional group-wrapped repeater fields',
            testFunction: async (page: Page, _canvas: Frame) => {
              const mediaType = page
                .locator('[data-id="top_group_media_type"] select')
                .first();

              await mediaType.selectOption('image');
              await expect(
                page.locator('[data-id="top_group_image_default"]'),
              ).toBeVisible();
              await expect(
                page.locator('[data-id="top_group_image_sources"]'),
              ).toBeVisible();

              await page
                .locator('[data-id="top_group_image_sources"]')
                .getByRole('button', { name: 'Add grouped picture source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="top_group_image_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('video');
              await expect(
                page.locator('[data-id="top_group_image_default"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_group_image_sources"]'),
              ).toHaveCount(0);
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="top_group_image_sources[0]"]',
                ),
              ).toHaveCount(0);
            },
          },
        ],
      },
      {
        groupName: 'custom field inside repeater',
        testCases: [
          {
            description: 'hides nested conditional repeater fields by default',
            testFunction: async (page: Page, _canvas: Frame) => {
              await page.getByRole('button', { name: /^Add card$/ }).click();

              const card = page.locator('[data-rfd-draggable-id="cards[0]"]');

              await expect(card).toBeVisible();
              await expect(
                card.locator('[data-id="image_default"]'),
              ).toHaveCount(0);
              await expect(
                card.locator('[data-id="image_sources"]'),
              ).toHaveCount(0);
            },
          },
          {
            description: 'shows and hides nested conditional repeater fields',
            testFunction: async (page: Page, _canvas: Frame) => {
              const card = page.locator('[data-rfd-draggable-id="cards[0]"]');
              const mediaType = card
                .locator('[data-id="media_type"] select')
                .first();

              await mediaType.selectOption('image');
              await expect(
                card.locator('[data-id="image_default"]'),
              ).toBeVisible();
              await expect(
                card.locator('[data-id="image_sources"]'),
              ).toBeVisible();

              await card
                .locator('[data-id="image_sources"]')
                .getByRole('button', { name: 'Add picture source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="cards[0].image_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('video');
              await expect(
                card.locator('[data-id="image_default"]'),
              ).toHaveCount(0);
              await expect(
                card.locator('[data-id="image_sources"]'),
              ).toHaveCount(0);
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="cards[0].image_sources[0]"]',
                ),
              ).toHaveCount(0);
            },
          },
        ],
      },
      {
        groupName: 'group wrapper inside nested custom field',
        testCases: [
          {
            description:
              'hides group-wrapped nested conditional repeater fields by default',
            testFunction: async (page: Page, _canvas: Frame) => {
              await page
                .getByRole('button', { name: /^Add group card$/ })
                .click();

              const card = page.locator(
                '[data-rfd-draggable-id="group_cards[0]"]',
              );

              await expect(card).toBeVisible();
              await expect(
                card.locator('[data-id="image_default"]'),
              ).toHaveCount(0);
              await expect(
                card.locator('[data-id="image_sources"]'),
              ).toHaveCount(0);
            },
          },
          {
            description:
              'shows and hides group-wrapped nested conditional repeater fields',
            testFunction: async (page: Page, _canvas: Frame) => {
              const card = page.locator(
                '[data-rfd-draggable-id="group_cards[0]"]',
              );
              const mediaType = card
                .locator('[data-id="media_type"] select')
                .first();

              await mediaType.selectOption('image');
              await expect(
                card.locator('[data-id="image_default"]'),
              ).toBeVisible();
              await expect(
                card.locator('[data-id="image_sources"]'),
              ).toBeVisible();

              await card
                .locator('[data-id="image_sources"]')
                .getByRole('button', { name: 'Add grouped picture source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="group_cards[0].image_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('video');
              await expect(
                card.locator('[data-id="image_default"]'),
              ).toHaveCount(0);
              await expect(
                card.locator('[data-id="image_sources"]'),
              ).toHaveCount(0);
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="group_cards[0].image_sources[0]"]',
                ),
              ).toHaveCount(0);
            },
          },
        ],
      },
    ];
  },
);
