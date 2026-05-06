import { select, subscribe } from '@wordpress/data';

type EditorBlock = {
  clientId: string;
  name: string;
  innerBlocks?: EditorBlock[];
};

const PENDING_CLASS = 'blockstudio-editor-enhance-pending';
const READY_CLASS = 'blockstudio-editor-enhance-ready';
const SETTLE_DELAY_MS = 500;
const MAX_WAIT_MS = 4000;

const expectedClientIds = new Set<string>();
const renderedClientIds = new Set<string>();

let enabled = false;
let ready = false;
let settleTimer: ReturnType<typeof setTimeout> | null = null;
let fallbackTimer: ReturnType<typeof setTimeout> | null = null;
let unsubscribe: (() => void) | null = null;
let classSyncDeadline = 0;

const editorEnhanceEnabled = (): boolean =>
  window.blockstudioAdmin?.options?.blockEditor?.enhance === true;

const blockTypes = (): Set<string> =>
  new Set(
    Object.values(window.blockstudioAdmin?.data?.blocksNative ?? {}).map(
      (block) => block.name,
    ),
  );

const editorDocument = (): Document => {
  const frame = document.querySelector(
    'iframe[name="editor-canvas"]',
  ) as HTMLIFrameElement | null;

  return frame?.contentDocument ?? document;
};

const retryClassSync = (callback: () => void) => {
  if (Date.now() > classSyncDeadline) return;
  window.setTimeout(callback, 50);
};

const editorWrapper = (): HTMLElement | null =>
  editorDocument().querySelector('.editor-styles-wrapper');

const allEditorBlocks = (): EditorBlock[] => {
  const store = select('core/block-editor') as
    | { getBlocks?: () => EditorBlock[] }
    | undefined;
  return store?.getBlocks?.() ?? [];
};

const flattenBlockstudioClientIds = (
  blocks: EditorBlock[],
  allowedTypes: Set<string>,
): string[] =>
  blocks.flatMap((block) => [
    ...(allowedTypes.has(block.name) ? [block.clientId] : []),
    ...flattenBlockstudioClientIds(block.innerBlocks ?? [], allowedTypes),
  ]);

const setPendingClass = () => {
  const wrapper = editorWrapper();
  if (!wrapper) {
    if (!ready) retryClassSync(setPendingClass);
    return;
  }
  if (ready) return;
  wrapper.classList.add(PENDING_CLASS);
  wrapper.classList.remove(READY_CLASS);
};

const setReadyClass = () => {
  const wrapper = editorWrapper();
  if (!wrapper) {
    retryClassSync(setReadyClass);
    return;
  }
  wrapper.classList.remove(PENDING_CLASS);
  wrapper.classList.add(READY_CLASS);
};

const reveal = () => {
  ready = true;
  if (settleTimer) {
    clearTimeout(settleTimer);
    settleTimer = null;
  }
  if (fallbackTimer) {
    clearTimeout(fallbackTimer);
    fallbackTimer = null;
  }
  unsubscribe?.();
  unsubscribe = null;
  setReadyClass();
};

const scheduleReveal = () => {
  if (ready || settleTimer) return;
  settleTimer = setTimeout(reveal, SETTLE_DELAY_MS);
};

const updateExpectedClientIds = () => {
  if (!enabled || ready) return;

  expectedClientIds.clear();
  flattenBlockstudioClientIds(allEditorBlocks(), blockTypes()).forEach(
    (clientId) => expectedClientIds.add(clientId),
  );

  if (expectedClientIds.size === 0) {
    scheduleReveal();
    return;
  }

  const allRendered = [...expectedClientIds].every((clientId) =>
    renderedClientIds.has(clientId),
  );
  if (allRendered) {
    scheduleReveal();
  }
};

export const initializeEditorReadinessGate = () => {
  if (enabled || !editorEnhanceEnabled()) return;
  enabled = true;
  classSyncDeadline = Date.now() + MAX_WAIT_MS + SETTLE_DELAY_MS;

  setPendingClass();
  updateExpectedClientIds();

  unsubscribe = subscribe(updateExpectedClientIds);
  fallbackTimer = setTimeout(reveal, MAX_WAIT_MS);
};

export const markEditorBlockRendered = (clientId: string) => {
  if (!enabled || ready) return;
  renderedClientIds.add(clientId);
  updateExpectedClientIds();
};
