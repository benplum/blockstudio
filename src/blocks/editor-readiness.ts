import { select, subscribe } from '@wordpress/data';

type EditorBlock = {
  clientId: string;
  name: string;
  innerBlocks?: EditorBlock[];
};

const PENDING_CLASS = 'blockstudio-editor-enhance-pending';
const READY_CLASS = 'blockstudio-editor-enhance-ready';
const LOCKED_CLASS = 'blockstudio-editor-enhance-locked';
const SETTLE_DELAY_MS = 1000;
const UNLOCK_DELAY_MS = 50;
const MAX_WAIT_MS = 4000;

const expectedClientIds = new Set<string>();
const renderedClientIds = new Set<string>();

let enabled = false;
let ready = false;
let settleTimer: ReturnType<typeof setTimeout> | null = null;
let fallbackTimer: ReturnType<typeof setTimeout> | null = null;
let unlockTimer: ReturnType<typeof setTimeout> | null = null;
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

const editorDocument = (): Document | null => {
  const frame = document.querySelector(
    'iframe[name="editor-canvas"]',
  ) as HTMLIFrameElement | null;

  if (frame) {
    return frame.contentDocument;
  }

  return document.querySelector('.editor-styles-wrapper') ? document : null;
};

const parentBodyTargets = (): HTMLElement[] =>
  [document.documentElement, document.body].filter(
    (target): target is HTMLElement => Boolean(target),
  );

const retryClassSync = (callback: () => void) => {
  if (Date.now() > classSyncDeadline) return;
  window.setTimeout(callback, 50);
};

const editorWrapper = (): HTMLElement | null =>
  editorDocument()?.querySelector('.editor-styles-wrapper') ?? null;

const editorBodyTargets = (): HTMLElement[] => {
  const doc = editorDocument();
  if (!doc) return [];

  return [doc.documentElement, doc.body].filter(
    (target): target is HTMLElement => Boolean(target),
  );
};

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
  const bodyTargets = editorBodyTargets();
  if (!wrapper || !bodyTargets.length) {
    if (!ready) retryClassSync(setPendingClass);
    return;
  }
  if (ready) return;
  [...parentBodyTargets(), ...bodyTargets].forEach((target) => {
    target.classList.add(LOCKED_CLASS);
    target.classList.remove(READY_CLASS);
  });
  wrapper.classList.add(PENDING_CLASS);
  wrapper.classList.remove(READY_CLASS);
};

const setReadyClass = () => {
  const wrapper = editorWrapper();
  const bodyTargets = editorBodyTargets();
  if (!wrapper || !bodyTargets.length) {
    retryClassSync(setReadyClass);
    return;
  }
  [...parentBodyTargets(), ...bodyTargets].forEach((target) => {
    target.classList.add(READY_CLASS);
  });
  wrapper.classList.remove(PENDING_CLASS);
  wrapper.classList.add(READY_CLASS);
};

const unlockEditorBody = () => {
  [...parentBodyTargets(), ...editorBodyTargets()].forEach((target) => {
    target.classList.remove(LOCKED_CLASS);
  });
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
  if (unlockTimer) {
    clearTimeout(unlockTimer);
    unlockTimer = null;
  }
  unsubscribe?.();
  unsubscribe = null;
  setReadyClass();
  unlockTimer = setTimeout(unlockEditorBody, UNLOCK_DELAY_MS);
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
  classSyncDeadline =
    Date.now() + MAX_WAIT_MS + SETTLE_DELAY_MS + UNLOCK_DELAY_MS;

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
