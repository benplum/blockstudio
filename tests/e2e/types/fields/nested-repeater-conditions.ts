import { expect, Frame, Locator, Page } from '@playwright/test';
import { testType, text } from '../../utils/playwright-utils';

testType(
  ['fields nested repeater conditions', 'fields-nested-repeater-conditions'],
  false,
  () => {
    const expectVisibleField = async (
      scope: Page | Locator,
      id: string,
    ) => {
      await expect(scope.locator(`[data-id="${id}"]`)).toBeVisible();
    };

    const expectHiddenField = async (scope: Page | Locator, id: string) => {
      await expect(scope.locator(`[data-id="${id}"]`)).toHaveCount(0);
    };

    const expectImageFields = async (
      scope: Page | Locator,
      prefix = '',
    ) => {
      await expectVisibleField(scope, `${prefix}image_default`);
      await expectVisibleField(scope, `${prefix}image_sources`);
      await expectHiddenField(scope, `${prefix}video_mp4`);
      await expectHiddenField(scope, `${prefix}video_sources`);
    };

    const expectVideoFields = async (
      scope: Page | Locator,
      prefix = '',
    ) => {
      await expectHiddenField(scope, `${prefix}image_default`);
      await expectHiddenField(scope, `${prefix}image_sources`);
      await expectVisibleField(scope, `${prefix}video_mp4`);
      await expectVisibleField(scope, `${prefix}video_sources`);
    };

    const expectNoMediaFields = async (
      scope: Page | Locator,
      prefix = '',
    ) => {
      await expectHiddenField(scope, `${prefix}image_default`);
      await expectHiddenField(scope, `${prefix}image_sources`);
      await expectHiddenField(scope, `${prefix}video_mp4`);
      await expectHiddenField(scope, `${prefix}video_sources`);
    };

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
              await expect(
                page.locator('[data-id="top_video_mp4"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_video_sources"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_group_video_mp4"]'),
              ).toHaveCount(0);
              await expect(
                page.locator('[data-id="top_group_video_sources"]'),
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
              await expectImageFields(page, 'top_');

              await page
                .locator('[data-id="top_image_sources"]')
                .getByRole('button', { name: 'Add picture source' })
                .click();
              await expect(
                page.locator('[data-rfd-draggable-id="top_image_sources[0]"]'),
              ).toBeVisible();

              await mediaType.selectOption('video');
              await expectVideoFields(page, 'top_');
              await expect(
                page.locator('[data-rfd-draggable-id="top_image_sources[0]"]'),
              ).toHaveCount(0);

              await page
                .locator('[data-id="top_video_sources"]')
                .getByRole('button', { name: 'Add video source' })
                .click();
              await expect(
                page.locator('[data-rfd-draggable-id="top_video_sources[0]"]'),
              ).toBeVisible();

              await mediaType.selectOption('image');
              await expectImageFields(page, 'top_');
              await expect(
                page.locator('[data-rfd-draggable-id="top_video_sources[0]"]'),
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
              await expectImageFields(page, 'top_group_');

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
              await expectVideoFields(page, 'top_group_');
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="top_group_image_sources[0]"]',
                ),
              ).toHaveCount(0);

              await page
                .locator('[data-id="top_group_video_sources"]')
                .getByRole('button', { name: 'Add grouped video source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="top_group_video_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('image');
              await expectImageFields(page, 'top_group_');
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="top_group_video_sources[0]"]',
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
              await expectNoMediaFields(card);
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
              await expectImageFields(card);

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
              await expectVideoFields(card);
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="cards[0].image_sources[0]"]',
                ),
              ).toHaveCount(0);

              await card
                .locator('[data-id="video_sources"]')
                .getByRole('button', { name: 'Add video source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="cards[0].video_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('image');
              await expectImageFields(card);
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="cards[0].video_sources[0]"]',
                ),
              ).toHaveCount(0);
            },
          },
          {
            description:
              'applies idStructure conditions to custom fields inside repeaters',
            testFunction: async (page: Page, _canvas: Frame) => {
              await page
                .getByRole('button', { name: /^Add structured card$/ })
                .click();

              const card = page.locator(
                '[data-rfd-draggable-id="structured_cards[0]"]',
              );
              const mediaType = card
                .locator('[data-id="item_media_type"] select')
                .first();

              await expect(card).toBeVisible();
              await expectNoMediaFields(card, 'item_');

              await mediaType.selectOption('image');
              await expectImageFields(card, 'item_');

              await card
                .locator('[data-id="item_image_sources"]')
                .getByRole('button', { name: 'Add picture source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="structured_cards[0].item_image_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('video');
              await expectVideoFields(card, 'item_');
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="structured_cards[0].item_image_sources[0]"]',
                ),
              ).toHaveCount(0);
            },
          },
          {
            description:
              'applies inline nested repeater conditions inside repeaters',
            testFunction: async (page: Page, _canvas: Frame) => {
              await page.getByRole('button', { name: /^Add item$/ }).click();

              const item = page.locator('[data-rfd-draggable-id="items[0]"]');
              const mediaType = item
                .locator('[data-id="item_media_type"] select')
                .first();

              await expect(item).toBeVisible();
              await expectImageFields(item, 'item_');

              await item
                .locator('[data-id="item_image_sources"]')
                .getByRole('button', { name: 'Add inline picture source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="items[0].item_image_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('video');
              await expectVideoFields(item, 'item_');
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="items[0].item_image_sources[0]"]',
                ),
              ).toHaveCount(0);
            },
          },
          {
            description: 'renders nested id-less group fields inside repeaters',
            testFunction: async (page: Page, canvas: Frame) => {
              await page
                .getByRole('button', { name: /^Add nested group card$/ })
                .click();

              const card = page.locator(
                '[data-rfd-draggable-id="nested_group_cards[0]"]',
              );

              await expect(card).toBeVisible();
              await expect(card.locator('[data-id="heading"]')).toBeVisible();
              await card
                .locator('[data-id="heading"] input')
                .fill('Nested repeater group');

              await text(
                canvas,
                '"nested_group_cards":[{"heading":"Nested repeater group"}]',
              );
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
              await expectNoMediaFields(card);
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
              await expectImageFields(card);

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
              await expectVideoFields(card);
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="group_cards[0].image_sources[0]"]',
                ),
              ).toHaveCount(0);

              await card
                .locator('[data-id="video_sources"]')
                .getByRole('button', { name: 'Add grouped video source' })
                .click();
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="group_cards[0].video_sources[0]"]',
                ),
              ).toBeVisible();

              await mediaType.selectOption('image');
              await expectImageFields(card);
              await expect(
                page.locator(
                  '[data-rfd-draggable-id="group_cards[0].video_sources[0]"]',
                ),
              ).toHaveCount(0);
            },
          },
        ],
      },
    ];
  },
);
