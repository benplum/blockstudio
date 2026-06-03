import apiFetch from '@wordpress/api-fetch';
import * as blockEditor from '@wordpress/block-editor';
import {
  Button,
  Modal,
  ExternalLink,
  __experimentalVStack as VStack,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { BlockstudioAttribute } from '@/types/block';
import { __ } from '@/utils/__';

// @ts-ignore
const LinkControl = blockEditor.__experimentalLinkControl;

type Link = {
  id?: number | string;
  url: string;
  title?: string;
  opensInNewTab?: boolean;
  target?: string;
  linkTarget?: string;
  rel?: string;
  type?: string;
};

type CreatedPage = {
  id: number;
  link?: string;
};

const NEW_TAB_TARGET = '_blank';
const NEW_TAB_REL = ['noreferrer', 'noopener'];

const isNewTabLink = (link: Link): boolean =>
  typeof link.opensInNewTab === 'boolean'
    ? link.opensInNewTab
    : link.target === NEW_TAB_TARGET || link.linkTarget === NEW_TAB_TARGET;

const normalizeRel = (rel: string | undefined, opensInNewTab: boolean) => {
  const tokens = new Set((rel ?? '').split(/\s+/).filter(Boolean));

  NEW_TAB_REL.forEach((token) => {
    if (opensInNewTab) {
      tokens.add(token);
    } else {
      tokens.delete(token);
    }
  });

  const normalized = Array.from(tokens).join(' ');

  return normalized || undefined;
};

const normalizeLinkValue = (value: string | NonNullable<unknown>) => {
  if (!value || typeof value !== 'object') {
    return value;
  }

  const link = value as Link;

  return {
    ...link,
    opensInNewTab: isNewTabLink(link),
  };
};

const normalizeLinkChange = (link: Link): Link => {
  const normalized = {
    ...link,
    opensInNewTab: isNewTabLink(link),
  };

  if (normalized.opensInNewTab) {
    normalized.target = NEW_TAB_TARGET;
    normalized.linkTarget = NEW_TAB_TARGET;
  } else {
    delete normalized.target;
    delete normalized.linkTarget;
  }

  normalized.rel = normalizeRel(normalized.rel, normalized.opensInNewTab);

  return normalized;
};

const createPageSuggestion = async (title: string): Promise<Link> => {
  const page = await apiFetch<CreatedPage>({
    path: '/wp/v2/pages',
    method: 'POST',
    data: {
      status: 'draft',
      title,
    },
  });

  return {
    id: page.id,
    title,
    type: 'page',
    url: page.link || `?page_id=${page.id}`,
  };
};

export const LinkModal = ({
  onChange,
  onRemove,
  opensInNewTab,
  setOpen,
  value = '',
  withCreateSuggestion,
  ...rest
}: {
  onChange: (link: Link) => void;
  onRemove: (link: Link) => void;
  opensInNewTab: boolean;
  setOpen: (open: boolean) => void;
  value: string | NonNullable<unknown>;
  withCreateSuggestion?: boolean;
}) => {
  return (
    <Modal
      title={__('Select link')}
      className={'blockstudio-fields__link-modal'}
      onRequestClose={() => setOpen(false)}
    >
      <LinkControl
        {...rest}
        withCreateSuggestion={withCreateSuggestion}
        createSuggestion={
          withCreateSuggestion ? createPageSuggestion : undefined
        }
        value={normalizeLinkValue(value)}
        onChange={(link: Link) => onChange(normalizeLinkChange(link))}
        onRemove={(link: Link) => onRemove(link)}
        settings={
          opensInNewTab
            ? [
                {
                  id: 'opensInNewTab',
                  title: __('Open in new tab'),
                },
              ]
            : []
        }
        hasTextControl
      />
    </Modal>
  );
};

export const Link = ({
  item,
  value,
  change,
  ...rest
}: {
  item: BlockstudioAttribute;
  value: {
    url?: string;
    title?: string;
  };
  change: (link: NonNullable<unknown>) => void;
}) => {
  const [open, setOpen] = useState(false);

  return (
    <VStack>
      {value?.url && (
        <div>
          <ExternalLink href={value.url}>
            {value?.title || value.url}
          </ExternalLink>
        </div>
      )}
      <div>
        <Button variant="secondary" onClick={() => setOpen(true)}>
          {__(
            item?.textButton || 'Select Link',
            item?.textButton as unknown as boolean,
          )}
        </Button>
      </div>
      {open && (
        <LinkModal
          {...rest}
          value={value}
          onChange={(link) => change(link)}
          onRemove={() => change({})}
          opensInNewTab={item?.opensInNewTab ?? false}
          setOpen={setOpen}
        />
      )}
    </VStack>
  );
};
