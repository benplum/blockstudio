import { Global } from '@emotion/react';
import { style } from '@/const/index';

export const Styles = () => {
  return (
    <Global
      styles={{
        ':root': {
          '--blockstudio-border': style.border,
          '--blockstudio-border-radius': style.borderRadius,
          '--blockstudio-fields-space': '16px !important',
        },
        '.blockstudio-configurator': {
          '.interface-interface-skeleton__sidebar': {
            bottom: 'unset !important',
          },
          '.blockstudio-fields__field--link .components-button': {
            pointerEvents: 'none',
          },
        },
        '.blockstudio-space': {
          display: 'grid',
          gridTemplateColumns: 'minmax(0, 1fr)',
          gridGap: 'var(--blockstudio-fields-space)',

          '&--half': {
            gridGap: 'calc(var(--blockstudio-fields-space) / 3)',
          },
        },
        '.blockstudio-fields': {
          borderBottom: style.border,

          '&:first-child': {
            marginTop: '1px !important',
          },

          '.components-base-control, .components-base-control__field': {
            marginBottom: '0 !important',
          },
          '& > .blockstudio-fields__field--tabs > .components-panel__body': {
            padding: '0 !important',
          },
        },
        '.blockstudio-expanded-editor__trigger-container': {
          minHeight: '24px',
          paddingRight: '32px',
          position: 'relative',
        },
        '.blockstudio-expanded-editor__trigger-slot': {
          display: 'inline-flex',
          position: 'absolute',
          right: 0,
          top: '-1px',
        },
        '@keyframes blockstudio-expanded-editor-drawer-in': {
          from: {
            transform: 'translateX(100%)',
          },
          to: {
            transform: 'translateX(0)',
          },
        },
        '.blockstudio-expanded-editor__backdrop': {
          background: 'transparent',
          bottom: 0,
          left: 0,
          position: 'fixed',
          right: 0,
          top: 0,
          zIndex: 99999,
        },
        '.blockstudio-expanded-editor__drawer': {
          animation: 'blockstudio-expanded-editor-drawer-in 100ms cubic-bezier(0, 0, 0.2, 1)',
          background: '#fff',
          borderLeft: style.border,
          bottom: 0,
          boxShadow: 'none',
          color: '#1e1e1e',
          display: 'flex',
          flexDirection: 'column',
          maxWidth: '700px',
          outline: 'none',
          position: 'fixed',
          right: 0,
          top: 0,
          width: 'min(80vw, 700px)',
          zIndex: 100000,
        },
        '.blockstudio-expanded-editor__drawer-header': {
          alignItems: 'center',
          background: '#fff',
          borderBottom: style.border,
          display: 'flex',
          flexShrink: 0,
          gap: '16px',
          height: '65px',
          justifyContent: 'space-between',
          padding: '0 24px',
        },
        '.blockstudio-expanded-editor__drawer-title': {
          color: '#1e1e1e',
          fontSize: '20px',
          fontWeight: 600,
          lineHeight: 1.2,
          margin: 0,
          minWidth: 0,
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
        },
        '.blockstudio-expanded-editor__drawer-content': {
          flex: '1 1 auto',
          overflow: 'auto',
          padding: 0,
        },
        '.blockstudio-expanded-editor__content': {
          width: '100%',
        },
        '.blockstudio-expanded-editor__drawer .blockstudio-fields__field--group.components-panel':
          {
            border: 0,
          },
        '.blockstudio-expanded-editor__drawer .blockstudio-fields__field--group > .components-panel__body':
          {
            borderBottom: 0,
            borderTop: 0,
          },
        '@media (max-width: 782px)': {
          '.blockstudio-expanded-editor__drawer': {
            maxWidth: 'none',
            width: '100vw',
          },
        },
        '@media (prefers-reduced-motion: reduce)': {
          '.blockstudio-expanded-editor__drawer': {
            animation: 'none',
          },
        },
        '.blockstudio-fields__link-modal': {
          width: '100% !important',
          maxWidth: '500px !important',
        },
        '.blockstudio-fields__link-modal .components-modal__content': {
          padding: '0 !important',
        },
        '.blockstudio-fields__link-modal .block-editor-link-control__field': {
          marginLeft: '32px !important',
          marginRight: '32px !important',
        },
        '.blockstudio-fields__link-modal .block-editor-link-control__search-actions':
          {
            right: '35px !important',
          },
        '.blockstudio-fields__link-modal .block-editor-link-control__tools, .blockstudio-fields__link-modal .block-editor-link-control__search-item':
          {
            paddingLeft: '32px !important',
            paddingRight: '32px !important',
          },
        '.blockstudio-fields__link-modal .components-modal__content:before': {
          display: 'none !important',
        },
        '.blockstudio-fields__link-modal .block-editor-link-control__search-results':
          {
            padding: '8px 0 !important',
          },
        '.blockstudio-fields__field--tabs .blockstudio-fields__field--tabs > div > div':
          {
            border: 'var(--blockstudio-border) !important',
            borderRadius: 'var(--blockstudio-border-radius) !important',
          },
        '.components-panel__body + .blockstudio-fields__field--tabs': {
          borderTop: 'var(--blockstudio-border) !important',
        },
        '.components-tab-panel__tab-content > .blockstudio-fields__field--group:first-child':
          {
            marginTop: '1px',
          },

        '.wp-block.blockstudio-block': {
          '.block-editor-default-block-appender': {
            height: 'unset',
            minHeight: '24px',
          },
        },
      }}
    />
  );
};
