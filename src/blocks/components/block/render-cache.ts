import { cloneDeep } from 'lodash-es';
import { replaceEmptyStringsWithFalse } from '@/blocks/utils/replace-empty-strings-with-false';

interface PreloadEntry {
  rendered: string;
  blockName: string;
  attributes?: unknown;
  mode?: string;
}

const cache = new Map<string, string>();
const cacheByBlock = new Map<string, Set<string>>();
const preloadQueues = new Map<string, string[]>();
const claimedByClient = new Map<string, string | undefined>();

const getBlockstudioFieldDefaults = (
  blockName: string,
): Record<string, unknown> => {
  if (typeof window === 'undefined') return {};

  const blocks = window.blockstudioAdmin?.data?.blocksNative as unknown as
    | Record<
        string,
        { attributes?: Record<string, { id?: string; default?: unknown }> }
      >
    | undefined;
  const block = blocks?.[blockName];

  if (!block?.attributes) return {};

  return Object.values(block.attributes).reduce<Record<string, unknown>>(
    (defaults, attribute) => {
      if (attribute.id) {
        defaults[attribute.id] = attribute.default;
      }
      return defaults;
    },
    {},
  );
};

const sortedStringify = (value: unknown): string => {
  if (value === null || value === undefined) return JSON.stringify(value);
  if (typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) {
    return '[' + value.map(sortedStringify).join(',') + ']';
  }
  const obj = value as Record<string, unknown>;
  const keys = Object.keys(obj).sort();
  const pairs = keys.map((k) => JSON.stringify(k) + ':' + sortedStringify(obj[k]));
  return '{' + pairs.join(',') + '}';
};

export const computeHash = (
  blockName: string,
  attributes: unknown,
  mode: string = 'editor',
): string => {
  let cloned = cloneDeep(attributes) as Record<string, unknown> | unknown[];
  if (Array.isArray(cloned)) {
    cloned = {};
  }

  const clonedRecord = cloned as Record<string, unknown>;
  const nestedAttributes =
    clonedRecord.blockstudio &&
    typeof clonedRecord.blockstudio === 'object' &&
    (clonedRecord.blockstudio as Record<string, unknown>).attributes &&
    typeof (clonedRecord.blockstudio as Record<string, unknown>).attributes ===
      'object' &&
    !Array.isArray(
      (clonedRecord.blockstudio as Record<string, unknown>).attributes,
    )
      ? ((clonedRecord.blockstudio as Record<string, unknown>)
          .attributes as Record<string, unknown>)
      : undefined;

  const fieldDefaults = getBlockstudioFieldDefaults(blockName);

  if (nestedAttributes) {
    Object.entries(fieldDefaults).forEach(([key, defaultValue]) => {
      if (
        key in nestedAttributes &&
        defaultValue !== undefined &&
        sortedStringify(nestedAttributes[key]) === sortedStringify(defaultValue)
      ) {
        delete nestedAttributes[key];
      }
    });

    if (Object.keys(nestedAttributes).length === 0) {
      delete (clonedRecord.blockstudio as Record<string, unknown>).attributes;
    }

    if (Object.keys(clonedRecord.blockstudio as object).length === 0) {
      delete clonedRecord.blockstudio;
    }

    const fieldIds = new Set([...Object.keys(fieldDefaults)]);

    fieldIds.forEach((key) => {
      if (key in clonedRecord) {
        delete clonedRecord[key];
      }
    });
  }

  Object.keys(clonedRecord).forEach((key) => {
    if (key.startsWith('BLOCKSTUDIO_RICH_TEXT')) {
      delete clonedRecord[key];
    }
  });
  if (clonedRecord.metadata && typeof clonedRecord.metadata === 'object') {
    delete (clonedRecord.metadata as Record<string, unknown>).blockVisibility;
    if (Object.keys(clonedRecord.metadata as object).length === 0) {
      delete clonedRecord.metadata;
    }
  }

  const attrs = replaceEmptyStringsWithFalse(
    clonedRecord as Parameters<typeof replaceEmptyStringsWithFalse>[0],
  );

  return sortedStringify({ blockName, attrs, mode })
    .replaceAll('{', '_')
    .replaceAll('}', '_')
    .replaceAll('[', '_')
    .replaceAll(']', '_')
    .replaceAll('"', '_')
    .replaceAll('/', '__')
    .replaceAll(' ', '_')
    .replaceAll(',', '_')
    .replaceAll(':', '_')
    .replaceAll('\\', '_');
};

export const renderCache = {
  initFromPreload() {
    const preloaded = window.blockstudio?.blockstudioBlocks;
    if (!preloaded) return;

    const entries = Array.isArray(preloaded)
      ? preloaded
      : Object.values(preloaded);

    this.addPreloads(entries);
  },

  claimPreloaded(
    blockName: string,
    clientId?: string,
    attributes?: unknown,
    mode: string = 'editor',
  ): string | undefined {
    if (clientId && claimedByClient.has(clientId)) {
      return claimedByClient.get(clientId);
    }

    if (attributes) {
      const cached = this.get(computeHash(blockName, attributes, mode));
      if (cached) {
        if (clientId) {
          claimedByClient.set(clientId, cached);
        }
        return cached;
      }
    }

    const queue = preloadQueues.get(blockName);
    if (!queue || queue.length === 0) return undefined;
    const rendered = queue.shift();
    if (clientId) {
      claimedByClient.set(clientId, rendered);
    }
    return rendered;
  },

  get(hash: string): string | undefined {
    return cache.get(hash);
  },

  set(hash: string, rendered: string, blockName?: string) {
    cache.set(hash, rendered);
    if (blockName) {
      const hashes = cacheByBlock.get(blockName) || new Set();
      hashes.add(hash);
      cacheByBlock.set(blockName, hashes);
    }
  },

  addPreloads(entries: PreloadEntry[]) {
    entries.forEach((data) => {
      if (!data.rendered || !data.blockName) {
        return;
      }

      if (data.attributes) {
        const hash = computeHash(
          data.blockName,
          data.attributes,
          data.mode || 'editor',
        );
        this.set(hash, data.rendered, data.blockName);
        return;
      }

      const queue = preloadQueues.get(data.blockName) || [];
      queue.push(data.rendered);
      preloadQueues.set(data.blockName, queue);
    });
  },

  replacePreloads(entries: PreloadEntry[]) {
    const affectedTypes = new Set<string>();
    entries.forEach((data) => {
      if (data.blockName) affectedTypes.add(data.blockName);
    });

    for (const blockName of affectedTypes) {
      preloadQueues.delete(blockName);

      const hashes = cacheByBlock.get(blockName);
      if (hashes) {
        for (const hash of hashes) cache.delete(hash);
        cacheByBlock.delete(blockName);
      }
    }

    this.addPreloads(entries);
  },
};
