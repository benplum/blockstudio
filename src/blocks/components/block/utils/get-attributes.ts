import { parse as nodeParser } from 'node-html-parser';
import { getRegex } from '@/blocks/components/block/utils/get-regex';

const jsonAttributes = [
  'template',
  'allowedBlocks',
  'allowedFormats',
  'autocompleters',
  'allowedTypes',
  'labels',
];

const booleanAttributes = [
  'useBlockProps',
  'directInsert',
  'templateInsertUpdatesSelection',
  'multiline',
  'withoutInteractiveFormatting',
  'preserveWhiteSpace',
  'addToGallery',
  'autoOpenMediaUpload',
  'disableDropZone',
  'dropZoneUIOnly',
  'isAppender',
  'disableMediaButtons',
];

const knownCamelCaseAttributes = [
  ...jsonAttributes,
  ...booleanAttributes,
  'defaultBlock',
  'prioritizedInserterBlocks',
  'renderAppender',
  'templateLock',
];

const normalizeAttributeNames = (attributes: Record<string, unknown>) => {
  const normalized = { ...attributes };

  knownCamelCaseAttributes.forEach((attributeName) => {
    const lowerAttributeName = attributeName.toLowerCase();
    const matchingAttributeName = Object.keys(normalized).find(
      (key) => key.toLowerCase() === lowerAttributeName,
    );

    if (!matchingAttributeName || matchingAttributeName === attributeName) {
      return;
    }

    if (!(attributeName in normalized)) {
      normalized[attributeName] = normalized[matchingAttributeName];
    }

    delete normalized[matchingAttributeName];
  });

  return normalized;
};

export const getAttributes = (value: string, elementName: string) => {
  const regex = getRegex(elementName);
  const match = value.match(regex);
  const attributesString = match?.[1] || '';
  const element = `<div ${attributesString}></div>`;

  const root = nodeParser(element);
  const div = root.querySelector('div');
  const attributes = normalizeAttributeNames(
    (div?.attributes as unknown as Record<string, unknown>) || {},
  );

  jsonAttributes.forEach((key) => {
    if (!attributes[key]) {
      return;
    }

    try {
      attributes[key] = JSON.parse(attributes[key] as string);
    } catch {
      attributes[key] = [];
    }
  });

  booleanAttributes.forEach((key) => {
    if (!attributes[key]) {
      return;
    }

    if (attributes[key] === 'false') {
      attributes[key] = false;
    } else if (attributes[key]) {
      attributes[key] = true;
    }
  });

  return attributes;
};
