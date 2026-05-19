// @ts-ignore
import { __experimentalLinkControl as LinkControl } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import {
  Button,
  Modal,
  ExternalLink,
  __experimentalVStack as VStack,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { BlockstudioAttribute } from '@/types/block';
import { __ } from '@/utils/__';

type Link = {
  id?: number | string;
  url: string;
  title?: string;
  opensInNewTab?: boolean;
  type?: string;
};

type CreatedPage = {
  id: number;
  link?: string;
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
        value={value}
        onChange={(link: Link) => onChange(link)}
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
