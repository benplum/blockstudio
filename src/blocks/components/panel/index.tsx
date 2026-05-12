import { Fragment, ReactNode } from 'react';
import { Panel as P, PanelBody } from '@wordpress/components';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlockAttributes } from '@/types/types';

export const Panel = ({
  attributes,
  element,
  isAllowedToRender,
  item,
  portal,
}: {
  attributes: BlockstudioBlockAttributes;
  element: (props: BlockstudioAttribute) => ReactNode;
  isAllowedToRender: (
    item: BlockstudioAttribute,
    attributes: BlockstudioBlockAttributes,
    outerBlock?: boolean,
  ) => boolean;
  item: BlockstudioAttribute;
  portal?: boolean;
}) => {
  const props = { ...item } as Partial<BlockstudioAttribute>;
  delete props.icon;
  delete props.id;
  delete props.type;

  if (!isAllowedToRender(item, attributes)) {
    return null;
  }

  const renderAttribute = (
    itemInner: BlockstudioAttribute,
    index: number,
    idPrefix = '',
  ) => {
    const itemInnerProps = {
      ...itemInner,
    } as unknown as BlockstudioAttribute;

    if (idPrefix && itemInnerProps.id) {
      itemInnerProps.id = `${idPrefix}_${itemInnerProps.id}`;
    }

    if (!isAllowedToRender(itemInnerProps, attributes, false)) {
      return null;
    }

    if (itemInnerProps.type === 'group') {
      if (itemInnerProps.id || !itemInnerProps.attributes?.length) {
        return null;
      }

      return (
        <div
          key={`group-${idPrefix}-${index}`}
          style={itemInnerProps.style}
          className={`blockstudio-space${
            itemInnerProps.class ? ` ${itemInnerProps.class}` : ''
          } `}
        >
          {itemInnerProps.attributes.map((nestedItem, nestedIndex) =>
            renderAttribute(
              nestedItem as unknown as BlockstudioAttribute,
              nestedIndex,
              idPrefix,
            ),
          )}
        </div>
      );
    }

    return (
      <Fragment key={itemInnerProps.id || `field-${index}`}>
        {element(itemInnerProps)}
      </Fragment>
    );
  };

  return item?.attributes?.length ? (
    <P
      className={`blockstudio-fields__field blockstudio-fields__field--${item.type}`}
    >
      <PanelBody {...props} initialOpen={portal ? true : props.initialOpen}>
        <div
          style={item.style}
          className={`blockstudio-space${item.class ? ` ${item.class}` : ''} `}
        >
          {item.attributes.map((itemInner, index) =>
            renderAttribute(
              itemInner as unknown as BlockstudioAttribute,
              index,
              item.id,
            ),
          )}
        </div>
      </PanelBody>
    </P>
  ) : null;
};
