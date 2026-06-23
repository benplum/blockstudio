import { ReactNode } from 'react';
import { BlockstudioAttribute } from '@/types/block';
import { Any, BlockstudioBlockAttributes } from '@/types/types';

export type RegisteredFieldType = {
  component: (props: Any) => ReactNode;
  normalizer?: (context: {
    allProps: Any;
    attributes: BlockstudioBlockAttributes;
    change: (value: Any, direct?: boolean, size?: string) => void;
    item: BlockstudioAttribute;
    value: Any;
  }) => Any;
};

const registry = new Map<string, RegisteredFieldType>();

export const registerFieldType = (
  type: string,
  definition: RegisteredFieldType,
) => {
  if (!type || typeof definition?.component !== 'function') {
    return;
  }

  registry.set(type, definition);
};

export const unregisterFieldType = (type: string) => {
  registry.delete(type);
};

export const getRegisteredFieldType = (type: string) => {
  return registry.get(type);
};

if (typeof window !== 'undefined') {
  const globalBlockstudio = (window as Any).blockstudio || {};

  globalBlockstudio.registerFieldType = registerFieldType;
  globalBlockstudio.unregisterFieldType = unregisterFieldType;

  (window as Any).blockstudio = globalBlockstudio;
}
