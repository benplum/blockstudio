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
    await page.locator('.block-editor-block-card__title').click();
    await expect(page.locator('.blockstudio-expanded-editor__drawer')).toHaveCount(
      0,
    );
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
          .locator('.blockstudio-fields__field--group')
          .first()
          .evaluate((element) => getComputedStyle(element).borderBottomWidth),
      )
      .toBe('1px');
  });

  test('keeps drawer divider rules scoped out of the sidebar', async () => {
    const getStyle = async (
      locator: ReturnType<Page['locator']>,
      property: keyof CSSStyleDeclaration,
    ) => {
      return locator.evaluate((element, property) => {
        const styles = getComputedStyle(element);
        return styles[property as keyof CSSStyleDeclaration];
      }, property);
    };

    const expectStyle = async (
      locator: ReturnType<Page['locator']>,
      property: keyof CSSStyleDeclaration,
      value: string,
    ) => {
      await expect
        .poll(() => getStyle(locator, property))
        .toBe(value);
    };

    const expectHeaderAligned = async (
      drawer: ReturnType<Page['locator']>,
    ) => {
      const header = drawer.locator(
        '.blockstudio-expanded-editor__drawer-header',
      );
      const fields = drawer.locator('.blockstudio-fields').first();

      await expect
        .poll(() =>
          header.evaluate((element) => element.getBoundingClientRect().height),
        )
        .toBe(65);
      await expect
        .poll(() =>
          Promise.all([
            header.evaluate((element) => element.getBoundingClientRect().bottom),
            fields.evaluate((element) => element.getBoundingClientRect().top),
          ]).then(([headerBottom, fieldsTop]) =>
            Math.round(fieldsTop - headerBottom),
          ),
        )
        .toBe(0);
    };

    await resetPageState(page);
    canvas = await getEditorCanvas(page);
    await addBlock(page, 'native');
    await count(canvas, '.is-root-container > .wp-block', 1);
    await canvas.click('[data-type="blockstudio/native"]');
    await openSidebar(page);

    const sidebar = page.locator('.interface-interface-skeleton__sidebar');
    const sidebarGroup = sidebar
      .locator('.blockstudio-fields__field--group > .components-panel__body')
      .first();

    await expectStyle(sidebarGroup, 'borderTopWidth', '1px');
    await expectStyle(sidebarGroup, 'borderBottomWidth', '1px');

    await sidebar
      .locator('.blockstudio-fields__field--group .components-panel__body-toggle')
      .first()
      .click();
    await expectStyle(sidebarGroup, 'borderBottomWidth', '1px');

    await page
      .getByRole('button', {
        name: 'Open Expanded Editor',
      })
      .click();

    let drawer = page.locator('.blockstudio-expanded-editor__drawer');
    await expect(drawer).toBeVisible();
    await expectHeaderAligned(drawer);

    const drawerGroupBody = drawer
      .locator('.blockstudio-fields__field--group > .components-panel__body')
      .first();
    const drawerGroup = drawer
      .locator('.blockstudio-fields__field--group')
      .first();

    await expectStyle(drawerGroupBody, 'marginTop', '0px');
    await expectStyle(drawerGroupBody, 'borderTopWidth', '0px');
    await expectStyle(drawerGroup, 'borderBottomWidth', '1px');

    await drawer.getByRole('button', { name: 'Done' }).click();
    await expect(drawer).toHaveCount(0);

    await resetPageState(page);
    canvas = await getEditorCanvas(page);
    await addBlock(page, 'type-tabs');
    await count(canvas, '.is-root-container > .wp-block', 1);
    await canvas.click('[data-type="blockstudio/type-tabs"]');
    await openSidebar(page);

    await sidebar.getByRole('tab', { name: 'Override tab' }).click();

    const sidebarTabs = sidebar
      .locator('.blockstudio-fields__field--tabs .components-tab-panel__tabs')
      .first();

    await expectStyle(sidebarTabs, 'boxShadow', 'none');

    await page
      .getByRole('button', {
        name: 'Open Expanded Editor',
      })
      .click();

    drawer = page.locator('.blockstudio-expanded-editor__drawer');
    await expect(drawer).toBeVisible();
    await expectHeaderAligned(drawer);
    await drawer.getByRole('tab', { name: 'Override tab' }).click();

    const drawerTabs = drawer
      .locator('.blockstudio-fields__field--tabs .components-tab-panel__tabs')
      .first();
    const drawerTabFields = drawer
      .locator('.blockstudio-fields:has(> .blockstudio-fields__field--tabs)')
      .first();
    const drawerTabGroupBody = drawer
      .locator(
        '.blockstudio-fields__field--tabs .blockstudio-fields__field--group > .components-panel__body',
      )
      .first();
    const drawerTabGroup = drawer
      .locator('.blockstudio-fields__field--tabs .blockstudio-fields__field--group')
      .first();

    await expectStyle(drawerTabs, 'borderBottomWidth', '1px');
    await expectStyle(drawerTabFields, 'borderBottomWidth', '0px');
    await expectStyle(drawerTabGroupBody, 'borderTopWidth', '0px');
    await expectStyle(drawerTabGroup, 'borderBottomWidth', '0px');
  });
});
