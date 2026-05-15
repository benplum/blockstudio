import { expect, Frame, Page, test } from '@playwright/test';
import {
  addBlock,
  checkStyle,
  count,
  getEditorCanvas,
  getSharedPage,
  openSidebar,
  resetPageState,
  saveAndReload,
} from '../utils/playwright-utils';

let page: Page;
let canvas: Frame;

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  page = await getSharedPage(browser);
  await resetPageState(page);
  canvas = await getEditorCanvas(page);
});

test.describe('expanded editor', () => {
  test('opens the full field editor and syncs edits back to the sidebar', async () => {
    await addBlock(page, 'native');
    await count(canvas, '.is-root-container > .wp-block', 1);
    await canvas.click('[data-type="blockstudio/native"]');
    await openSidebar(page);

    const sidebar = page.locator('.interface-interface-skeleton__sidebar');
    const sidebarFields = sidebar.locator('.blockstudio-fields__field');
    const trigger = page.getByRole('button', {
      name: 'Open Expanded Editor',
    });

    await expect(trigger).toBeVisible();
    await expect(sidebarFields.first()).toBeVisible();
    const sidebarFieldCount = await sidebarFields.count();
    expect(sidebarFieldCount).toBeGreaterThan(10);
    const selectedClientId = await page.evaluate(() =>
      (window as any).wp.data
        .select('core/block-editor')
        .getSelectedBlockClientId(),
    );

    const sidebarBox = await sidebar.boundingBox();
    await trigger.click();

    const drawer = page.locator('.blockstudio-expanded-editor__drawer');
    const drawerFields = drawer.locator('.blockstudio-fields__field');
    await expect(drawer).toBeVisible();
    await expect(page.locator('#wpwrap')).not.toHaveAttribute(
      'data-base-ui-inert',
      '',
    );
    await expect(page.locator('#wpwrap')).not.toHaveAttribute(
      'aria-hidden',
      'true',
    );
    expect(await drawerFields.count()).toBe(sidebarFieldCount);

    const drawerBox = await drawer.boundingBox();
    expect(drawerBox?.width ?? 0).toBeGreaterThan(
      (sidebarBox?.width ?? 0) * 1.5,
    );
    expect(drawerBox?.width ?? 0).toBeLessThanOrEqual(701);

    await drawer
      .locator('.blockstudio-fields__field[data-id="message"] input')
      .first()
      .fill('LiveEdit');
    await expect(canvas.getByText('Message Outside: LiveEdit')).toBeVisible();

    await page
      .locator('.blockstudio-expanded-editor__drawer textarea')
      .first()
      .fill('Expanded editor value');
    await page
      .locator('.blockstudio-expanded-editor__drawer')
      .getByRole('button', { name: 'Done' })
      .click();

    await expect(drawer).toHaveCount(0);
    expect(
      await page.evaluate(() =>
        (window as any).wp.data
          .select('core/block-editor')
          .getSelectedBlockClientId(),
      ),
    ).toBe(selectedClientId);
    await expect(
      page.locator('.blockstudio-fields__field--textarea textarea').first(),
    ).toHaveValue('Expanded editor value');
    await expect(canvas.getByText('Message Outside: LiveEdit')).toBeVisible();
    await expect(
      sidebar.locator('.blockstudio-fields__field[data-id="message"] input'),
    ).toHaveValue('LiveEdit');

    await trigger.click();
    await expect(drawer).toBeVisible();
    await page.locator('.blockstudio-expanded-editor__backdrop').click({
      position: {
        x: 20,
        y: 120,
      },
    });

    await expect(drawer).toHaveCount(0);
    expect(
      await page.evaluate(() =>
        (window as any).wp.data
          .select('core/block-editor')
          .getSelectedBlockClientId(),
      ),
    ).toBe(selectedClientId);
    await expect(trigger).toBeVisible();

    await saveAndReload(page);
    canvas = await getEditorCanvas(page);
    await canvas.click('[data-type="blockstudio/native"]');
    await openSidebar(page);
    await expect(canvas.getByText('Message Outside: LiveEdit')).toBeVisible();
    await expect(
      page.locator('.blockstudio-fields__field[data-id="message"] input'),
    ).toHaveValue('LiveEdit');
    await expect(
      page.locator('.blockstudio-fields__field--textarea textarea').first(),
    ).toHaveValue('Expanded editor value');
  });

  test('does not duplicate code fields while the drawer is open', async () => {
    await resetPageState(page);
    canvas = await getEditorCanvas(page);
    await addBlock(page, 'type-code-selector-asset');
    await count(canvas, '.is-root-container > .wp-block', 1);
    await canvas.click('[data-type="blockstudio/type-code-selector-asset"]');
    await openSidebar(page);

    await page
      .getByRole('button', {
        name: 'Open Expanded Editor',
      })
      .click();

    const drawer = page.locator('.blockstudio-expanded-editor__drawer');
    await expect(drawer).toBeVisible();
    await expect(
      page.locator(
        '.interface-interface-skeleton__sidebar .blockstudio-fields',
      ),
    ).toHaveCount(0);

    await drawer.locator('.blockstudio-fields__field--code .cm-line').click();
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.press('Backspace');
    await page.keyboard.type(
      '%selector% { background: rgb(12, 34, 56); }',
    );

    await checkStyle(
      canvas,
      '[data-type="blockstudio/type-code-selector-asset"]',
      'backgroundColor',
      'rgb(12, 34, 56)',
    );

    await drawer.getByRole('button', { name: 'Done' }).click();
    await expect(drawer).toHaveCount(0);
    await checkStyle(
      canvas,
      '[data-type="blockstudio/type-code-selector-asset"]',
      'backgroundColor',
      'rgb(12, 34, 56)',
    );
  });

  test('includes extension field groups in the drawer', async () => {
    await resetPageState(page);
    canvas = await getEditorCanvas(page);
    await addBlock(page, 'type-text');
    await count(canvas, '.is-root-container > .wp-block', 1);
    await canvas.click('[data-type="blockstudio/type-text"]');
    await openSidebar(page);

    const sidebar = page.locator('.interface-interface-skeleton__sidebar');
    await expect(sidebar.getByText('Addons attributes')).toBeVisible();
    await expect(sidebar.getByText('Addons style')).toBeVisible();

    const sidebarFieldCount = await sidebar
      .locator('.blockstudio-fields__field')
      .count();

    await page
      .getByRole('button', {
        name: 'Open Expanded Editor',
      })
      .click();

    const drawer = page.locator('.blockstudio-expanded-editor__drawer');
    await expect(drawer).toBeVisible();
    await expect(drawer.getByText('Addons attributes')).toBeVisible();
    await expect(drawer.getByText('Addons style')).toBeVisible();
    expect(await drawer.locator('.blockstudio-fields__field').count()).toBe(
      sidebarFieldCount,
    );
    await expect
      .poll(() =>
        drawer
          .locator('.blockstudio-fields__field--group')
          .first()
          .evaluate((element) => getComputedStyle(element).borderLeftWidth),
      )
      .toBe('0px');
    await expect
      .poll(() =>
        drawer
          .locator(
            '.blockstudio-fields__field--group > .components-panel__body',
          )
          .first()
          .evaluate((element) => getComputedStyle(element).borderTopWidth),
      )
      .toBe('0px');
    await expect
      .poll(() =>
        drawer
          .locator(
            '.blockstudio-fields__field--group > .components-panel__body',
          )
          .first()
          .evaluate((element) => getComputedStyle(element).borderBottomWidth),
      )
      .toBe('0px');
  });
});
