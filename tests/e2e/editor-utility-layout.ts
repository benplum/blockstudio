import { test, expect, Frame, Page } from '@playwright/test';
import { getEditorCanvas, login } from './utils/playwright-utils';

const BASE = 'http://localhost:8888';
const TEST_API = `${BASE}/wp-json/blockstudio-test/v1`;
const POST_ID = 1483;
const BLOCK_CONTENT = [
	'<!-- wp:blockstudio/type-editor-utility-layout /-->',
	'<!-- wp:blockstudio/type-editor-utility-position /-->',
].join('\n');

type UtilityLayoutMetrics = {
	resetStylePresent: boolean;
	layoutDisplay: string;
	layoutAlignItems: string;
	actionsDisplay: string;
	gridDisplay: string;
	inlineDisplay: string;
	backgroundPosition: string;
	positionWrapperPosition: string;
	layoutCenter: number;
	contentCenter: number;
	contentTop: number;
	backgroundBottom: number;
	primaryTop: number;
	secondaryTop: number;
	primaryRight: number;
	secondaryLeft: number;
};

let page: Page;

const openEditor = async (): Promise<Frame> => {
	await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`, {
		waitUntil: 'domcontentloaded',
	});

	const canvas = await getEditorCanvas(page);
	await canvas.waitForSelector('.blockstudio-editor-utility-layout', {
		timeout: 30000,
	});
	await canvas.waitForSelector('.blockstudio-editor-utility-position', {
		timeout: 30000,
	});

	return canvas;
};

const readMetrics = async (
	context: Page | Frame,
): Promise<UtilityLayoutMetrics> =>
	context.evaluate(() => {
		const layout = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout',
		);
		const content = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout__content',
		);
		const actions = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout__actions',
		);
		const background = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout__background',
		);
		const grid = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout__grid',
		);
		const inline = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout__inline',
		);
		const positionWrapper = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-position',
		);
		const primary = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout__actions a:first-child',
		);
		const secondary = document.querySelector<HTMLElement>(
			'.blockstudio-editor-utility-layout__actions a:last-child',
		);

		if (
			!layout ||
			!content ||
			!actions ||
			!background ||
			!grid ||
			!inline ||
			!positionWrapper ||
			!primary ||
			!secondary
		) {
			throw new Error('Utility layout fixture not found.');
		}

		const layoutStyle = window.getComputedStyle(layout);
		const actionsStyle = window.getComputedStyle(actions);
		const gridStyle = window.getComputedStyle(grid);
		const inlineStyle = window.getComputedStyle(inline);
		const backgroundStyle = window.getComputedStyle(background);
		const positionStyle = window.getComputedStyle(positionWrapper);
		const layoutRect = layout.getBoundingClientRect();
		const contentRect = content.getBoundingClientRect();
		const backgroundRect = background.getBoundingClientRect();
		const primaryRect = primary.getBoundingClientRect();
		const secondaryRect = secondary.getBoundingClientRect();

		return {
			resetStylePresent: Boolean(
				document.getElementById('blockstudio-reset-utility-layout'),
			),
			layoutDisplay: layoutStyle.display,
			layoutAlignItems: layoutStyle.alignItems,
			actionsDisplay: actionsStyle.display,
			gridDisplay: gridStyle.display,
			inlineDisplay: inlineStyle.display,
			backgroundPosition: backgroundStyle.position,
			positionWrapperPosition: positionStyle.position,
			layoutCenter: layoutRect.left + layoutRect.width / 2,
			contentCenter: contentRect.left + contentRect.width / 2,
			contentTop: contentRect.top,
			backgroundBottom: backgroundRect.bottom,
			primaryTop: primaryRect.top,
			secondaryTop: secondaryRect.top,
			primaryRight: primaryRect.right,
			secondaryLeft: secondaryRect.left,
		};
	});

test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser, request }) => {
	await request.post(`${TEST_API}/e2e/assets-reset`, {
		data: { enabled: false },
	});
	await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
		data: { content: BLOCK_CONTENT },
	});

	const context = await browser.newContext();
	page = await context.newPage();
	await page.setViewportSize({ width: 1920, height: 1080 });
	await login(page);
});

test.afterAll(async ({ request }) => {
	await request.post(`${TEST_API}/e2e/assets-reset`, {
		data: { enabled: false },
	});
	await request.post(`${TEST_API}/pages/content/${POST_ID}`, {
		data: { content: '' },
	});
	await page.close();
});

test('reset off does not inject the editor utility layout reset', async () => {
	await page.request.post(`${TEST_API}/e2e/assets-reset`, {
		data: { enabled: false },
	});

	const canvas = await openEditor();

	await expect
		.poll(async () => (await readMetrics(canvas)).actionsDisplay, {
			timeout: 30000,
		})
		.toBe('flex');

	const metrics = await readMetrics(canvas);

	expect(metrics.resetStylePresent).toBe(false);
	expect(metrics.positionWrapperPosition).toBe('relative');
});

test('reset on restores utility display and position in the editor iframe', async () => {
	await page.request.post(`${TEST_API}/e2e/assets-reset`, {
		data: { enabled: true },
	});

	const canvas = await openEditor();

	await expect
		.poll(async () => (await readMetrics(canvas)).layoutDisplay, {
			timeout: 30000,
		})
		.toBe('flex');

	const metrics = await readMetrics(canvas);

	expect(metrics.resetStylePresent).toBe(true);
	expect(metrics.layoutAlignItems).toBe('center');
	expect(metrics.actionsDisplay).toBe('flex');
	expect(metrics.gridDisplay).toBe('grid');
	expect(metrics.inlineDisplay).toBe('inline-flex');
	expect(metrics.backgroundPosition).toBe('absolute');
	expect(metrics.positionWrapperPosition).toBe('absolute');
	expect(Math.abs(metrics.contentCenter - metrics.layoutCenter)).toBeLessThan(4);
	expect(Math.abs(metrics.primaryTop - metrics.secondaryTop)).toBeLessThan(2);
	expect(metrics.secondaryLeft - metrics.primaryRight).toBeGreaterThan(12);
	expect(metrics.contentTop).toBeLessThan(metrics.backgroundBottom);
});

test('frontend output stays unchanged', async () => {
	await page.goto(`${BASE}/native-single/`, { waitUntil: 'networkidle' });
	await page.waitForSelector('.blockstudio-editor-utility-layout', {
		timeout: 30000,
	});

	await expect
		.poll(async () => (await readMetrics(page)).layoutDisplay, {
			timeout: 30000,
		})
		.toBe('flex');

	const metrics = await readMetrics(page);

	expect(metrics.resetStylePresent).toBe(false);
	expect(metrics.backgroundPosition).toBe('absolute');
	expect(metrics.positionWrapperPosition).toBe('absolute');
});
