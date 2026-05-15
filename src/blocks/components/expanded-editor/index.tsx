import type { MouseEvent } from 'react';
import { Drawer } from '@base-ui/react/drawer';
import { Button } from '@wordpress/components';
import {
  createPortal,
  useCallback,
  useEffect,
  useState,
} from '@wordpress/element';
import { pencil } from '@wordpress/icons';
import { Fields } from '@/blocks/components/fields';
import {
  BlockstudioBlock,
  BlockstudioBlockAttributes,
} from '@/types/types';
import { __ } from '@/utils/__';

export const ExpandedEditor = ({
  attributes,
  block,
  blocks,
  clientId,
  extensions,
  onOpenChange,
  setAttributes,
  title,
}: {
  attributes: BlockstudioBlockAttributes;
  block?: BlockstudioBlock;
  blocks?: BlockstudioBlock[];
  clientId: string;
  extensions?: boolean;
  onOpenChange?: (open: boolean) => void;
  setAttributes: (attributes: BlockstudioBlockAttributes) => void;
  title?: string;
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [triggerTarget, setTriggerTarget] = useState<HTMLElement | null>(null);
  const editorBlocks = blocks?.length ? blocks : block ? [block] : [];
  const hasFields = editorBlocks.some(
    (editorBlock) => (editorBlock.blockstudio?.attributes || []).length > 0,
  );

  const openEditor = useCallback(() => {
    setIsOpen(true);
    onOpenChange?.(true);
  }, [onOpenChange]);

  const closeEditor = useCallback(() => {
    setIsOpen(false);
    onOpenChange?.(false);
  }, [onOpenChange]);
  const handleOpenChange = useCallback(
    (open: boolean) => {
      if (open) {
        openEditor();
      } else {
        closeEditor();
      }
    },
    [closeEditor, openEditor],
  );
  const stopOutsideInteraction = (event: MouseEvent<HTMLDivElement>) => {
    event.preventDefault();
    event.stopPropagation();
  };
  const closeFromBackdrop = (event: MouseEvent<HTMLDivElement>) => {
    stopOutsideInteraction(event);
    closeEditor();
  };

  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeEditor();
      }
    };

    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [closeEditor, isOpen]);

  useEffect(() => {
    if (!hasFields) return;

    let target: HTMLSpanElement | null = null;
    const frame = window.requestAnimationFrame(() => {
      const inspector = document.querySelector(
        '.block-editor-block-inspector',
      );
      const titles = inspector?.querySelectorAll(
        '.block-editor-block-card__title',
      );
      const title = titles?.[titles.length - 1];

      if (!title) return;

      target = document.createElement('span');
      target.dataset.blockstudioExpandedEditorTrigger = clientId;
      title.appendChild(target);
      setTriggerTarget(target);
    });

    return () => {
      window.cancelAnimationFrame(frame);
      target?.remove();
      setTriggerTarget(null);
    };
  }, [clientId, hasFields]);

  if (!hasFields) return null;

  const drawerTitle = title || editorBlocks[0]?.title || editorBlocks[0]?.name;

  return (
    <Drawer.Root
      disablePointerDismissal
      modal={false}
      onOpenChange={handleOpenChange}
      open={isOpen}
      swipeDirection="right"
    >
      {triggerTarget &&
        createPortal(
          <Drawer.Trigger
            render={
              <Button
                icon={pencil}
                iconSize={16}
                label={__('Open Expanded Editor')}
                size="small"
                style={{ minWidth: 24, padding: 0 }}
                variant="tertiary"
              />
            }
          />,
          triggerTarget,
        )}

      {isOpen &&
        createPortal(
          <>
            <div
              aria-hidden="true"
              className="blockstudio-expanded-editor__backdrop"
              onClick={closeFromBackdrop}
              onMouseDown={stopOutsideInteraction}
            />
            <div
              aria-label={String(drawerTitle)}
              aria-modal="false"
              className="blockstudio-expanded-editor__drawer"
              onMouseDown={(event: MouseEvent<HTMLDivElement>) =>
                event.stopPropagation()
              }
              role="dialog"
            >
              <div className="blockstudio-expanded-editor__drawer-header">
                <Drawer.Title className="blockstudio-expanded-editor__drawer-title">
                  {drawerTitle}
                </Drawer.Title>
                <Button onClick={closeEditor} variant="primary">
                  {__('Done')}
                </Button>
              </div>
              <Drawer.Content className="blockstudio-expanded-editor__drawer-content">
                <div className="blockstudio-expanded-editor__content">
                  {editorBlocks.map((editorBlock) => {
                    const isExtensionBlock =
                      extensions || editorBlock.name !== block?.name;

                    return (
                      <Fields
                        key={editorBlock.name}
                        attributes={attributes}
                        block={editorBlock}
                        clientId={clientId}
                        extensions={isExtensionBlock}
                        setAttributes={setAttributes}
                      />
                    );
                  })}
                </div>
              </Drawer.Content>
            </div>
          </>,
          document.body,
        )}
    </Drawer.Root>
  );
};
