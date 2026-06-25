// To parse this data:
//
//   import { Convert, Blockstudio } from "./file";
//
//   const blockstudio = Convert.toBlockstudio(json);
//
// These functions will throw an error if the JSON doesn't
// match the expected interface, even if the JSON is valid.

export interface Blockstudio {
  /**
   * Settings related to AI-powered context generation.
   */
  ai?: AI;
  /**
   * Settings related to asset management.
   */
  assets?: Assets;
  /**
   * Settings related to Gutenberg.
   */
  blockEditor?: BlockEditor;
  /**
   * Settings for bs: tag rendering. Controls page-level replacement of <bs:block-name> tags
   * with rendered block output. Template-level rendering is always active.
   */
  blockTags?: BlockTags;
  /**
   * Settings related to Blockstudio runtime caching.
   */
  cache?: Cache;
  /**
   * Settings for Content Sync, the WP-CLI driven projection of allowlisted WordPress content
   * to portable files.
   */
  content?: Content;
  /**
   * Settings related to developer tools.
   */
  dev?: Dev;
  /**
   * Settings related to the editor.
   */
  editor?: Editor;
  /**
   * Settings related to Tailwind.
   */
  tailwind?: Tailwind;
  /**
   * Settings related to bundled UI components.
   */
  ui?: UI;
  /**
   * Settings related to allowed users with access to the settings and editor.
   */
  users?: Users;
  [property: string]: any;
}

/**
 * Settings related to AI-powered context generation.
 */
export interface AI {
  /**
   * Enables the automatic creation of a comprehensive context file for use with large
   * language model (LLM) tools (e.g., Cursor). This file compiles current installation data:
   * all available block definitions and paths, Blockstudio-specific settings, relevant block
   * schemas, and combined Blockstudio documentation, providing a ready-to-use resource for
   * prompt engineering and AI code development.
   */
  enableContextGeneration?: boolean;
  [property: string]: any;
}

/**
 * Settings related to asset management.
 */
export interface Assets {
  /**
   * Enqueue assets in frontend and editor.
   */
  enqueue?: boolean;
  /**
   * Settings related to asset minification.
   */
  minify?: Minify;
  /**
   * Settings related to asset processing.
   */
  process?: Process;
  /**
   * Control removal of WordPress core block styles.
   */
  reset?: Reset;
  [property: string]: any;
}

/**
 * Settings related to asset minification.
 */
export interface Minify {
  /**
   * Minify CSS.
   */
  css?: boolean;
  /**
   * Minify JS.
   */
  js?: boolean;
  [property: string]: any;
}

/**
 * Settings related to asset processing.
 */
export interface Process {
  /**
   * Process SCSS in .css files.
   */
  scss?: boolean;
  /**
   * Process .scss files to CSS.
   */
  scssFiles?: boolean;
  [property: string]: any;
}

/**
 * Control removal of WordPress core block styles.
 */
export interface Reset {
  /**
   * Remove all WordPress core block styles on the frontend and in the editor.
   */
  enabled?: boolean;
  /**
   * Post types where the editor uses full-width layout by removing classic editor constraints.
   */
  fullWidth?: string[];
  [property: string]: any;
}

/**
 * Settings related to Gutenberg.
 */
export interface BlockEditor {
  /**
   * Global block editor policies that map to WordPress PHP block editor hooks.
   */
  blocks?: Blocks;
  /**
   * Stylesheets whose CSS classes should be available for choice in the class field.
   */
  cssClasses?: any[];
  /**
   * Stylesheets whose CSS variables should be available for autocompletion in the code field.
   */
  cssVariables?: any[];
  /**
   * Disable loading of blocks inside the Block Editor.
   */
  disableLoading?: boolean;
  /**
   * Enable Blockstudio editor affordances such as cleaner focus styles and hover/selection
   * outlines.
   */
  enhance?: boolean;
  /**
   * Global media policies for the block editor.
   */
  media?: MediaObject;
  /**
   * Global pattern inserter policies that map to WordPress PHP pattern hooks.
   */
  patterns?: Patterns;
  [property: string]: any;
}

/**
 * Global block editor policies that map to WordPress PHP block editor hooks.
 */
export interface Blocks {
  /**
   * Block names that are allowed in the inserter. Supports wildcards such as core/*.
   */
  allow?: string[];
  /**
   * Filter, rename, and order block inserter categories by slug.
   */
  categories?: BlocksCategories;
  /**
   * Block names that are removed from the inserter. Supports wildcards such as core/embed.
   */
  deny?: string[];
  /**
   * Enable the WordPress block directory assets in the editor.
   */
  directory?: boolean;
  /**
   * Policies for the legacy widget block.
   */
  legacyWidgets?: LegacyWidgets;
  /**
   * Policies for PHP-registered block styles.
   */
  styles?: Styles;
  [property: string]: any;
}

/**
 * Filter, rename, and order block inserter categories by slug.
 */
export interface BlocksCategories {
  /**
   * Block category slugs that should remain available. Empty means all categories are allowed.
   */
  allow?: string[];
  /**
   * Block category slugs that should be removed from the inserter.
   */
  deny?: string[];
  /**
   * Block category slugs that should be moved to the beginning of the inserter in the
   * provided order.
   */
  order?: string[];
  /**
   * Map block category slugs to replacement labels.
   */
  rename?: { [key: string]: string };
  [property: string]: any;
}

/**
 * Policies for the legacy widget block.
 */
export interface LegacyWidgets {
  /**
   * Legacy widget IDs that should be hidden from the legacy widget block.
   */
  hide?: string[];
  [property: string]: any;
}

/**
 * Policies for PHP-registered block styles.
 */
export interface Styles {
  /**
   * Map block names to PHP-registered block style names that should be unregistered. Use * as
   * a style name to remove all registered styles for a block.
   */
  deny?: { [key: string]: string[] };
  [property: string]: any;
}

/**
 * Global media policies for the block editor.
 */
export interface MediaObject {
  /**
   * Filter image size choices shown in editor media controls.
   */
  imageSizes?: ImageSizes;
  /**
   * Enable the Openverse media category in the block editor media inserter.
   */
  openverse?: boolean;
  [property: string]: any;
}

/**
 * Filter image size choices shown in editor media controls.
 */
export interface ImageSizes {
  /**
   * Image size names that should remain available. Empty means all image sizes are allowed.
   */
  allow?: string[];
  /**
   * Image size names that should be removed from editor media controls.
   */
  deny?: string[];
  [property: string]: any;
}

/**
 * Global pattern inserter policies that map to WordPress PHP pattern hooks.
 */
export interface Patterns {
  /**
   * Enable Blockstudio file-based patterns.
   */
  blockstudio?: boolean;
  /**
   * Filter, rename, and order block pattern categories by slug.
   */
  categories?: PatternsCategories;
  /**
   * Enable WordPress core block patterns.
   */
  core?: boolean;
  /**
   * Enable remote patterns from the WordPress pattern directory.
   */
  remote?: boolean;
  /**
   * Enable theme-provided block patterns.
   */
  theme?: boolean;
  [property: string]: any;
}

/**
 * Filter, rename, and order block pattern categories by slug.
 */
export interface PatternsCategories {
  /**
   * Pattern category slugs that should remain available. Empty means all categories are
   * allowed.
   */
  allow?: string[];
  /**
   * Pattern category slugs that should be removed from the pattern inserter.
   */
  deny?: string[];
  /**
   * Pattern category slugs that should be moved to the beginning of the inserter in the
   * provided order.
   */
  order?: string[];
  /**
   * Map pattern category slugs to replacement labels.
   */
  rename?: { [key: string]: string };
  [property: string]: any;
}

/**
 * Settings for bs: tag rendering. Controls page-level replacement of <bs:block-name> tags
 * with rendered block output. Template-level rendering is always active.
 */
export interface BlockTags {
  /**
   * Allowlist of block name patterns. Supports wildcards via fnmatch(). When set, only
   * matching blocks render via tags.
   */
  allow?: string[];
  /**
   * Denylist of block name patterns. Supports wildcards via fnmatch(). Matching blocks are
   * excluded from tag rendering. Takes precedence over allow.
   */
  deny?: string[];
  /**
   * Enable page-level bs: tag rendering in post content and widget areas.
   */
  enabled?: boolean;
  /**
   * Prefix to namespace shorthands for block tags. Each key is a lowercase prefix without
   * dashes, and each value is a namespace string or ordered namespace array.
   */
  prefixes?: { [key: string]: any };
  [property: string]: any;
}

/**
 * Settings related to Blockstudio runtime caching.
 */
export interface Cache {
  /**
   * Enable Blockstudio file-backed runtime, block registration, and editor asset caches.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings for Content Sync, the WP-CLI driven projection of allowlisted WordPress content
 * to portable files.
 */
export interface Content {
  /**
   * Author portability mode. Use ignore to omit authors or login to store author logins and
   * resolve existing users on push.
   */
  authors?: Authors;
  /**
   * Enable Content Sync configuration for this theme.
   */
  enabled?: boolean;
  /**
   * Content-set identity stored on synced entities. Prune and ownership checks are scoped to
   * this value.
   */
  id?: string;
  /**
   * Include Page Sync managed posts. Their post_content remains owned by Page Sync.
   */
  includePageSyncManaged?: boolean;
  /**
   * Attachment reference behavior. manifest records referenced attachments and validates them
   * on push; none drops attachment references.
   */
  media?: MediaEnum;
  /**
   * Postmeta allowlist, exclude list, and declared reference rewriting rules.
   */
  meta?: Meta;
  /**
   * Theme-relative directory where Content Sync reads and writes files.
   */
  path?: string;
  /**
   * Allowlisted post types to sync. Empty means no post types are synced.
   */
  postTypes?: string[];
  /**
   * Allowlisted registered taxonomies whose terms and post relationships are synced. Taxonomy
   * definitions are not captured.
   */
  taxonomies?: string[];
  [property: string]: any;
}

/**
 * Author portability mode. The v1 default ignores authors because users are not synced.
 */
export enum Authors {
  Ignore = 'ignore',
  Login = 'login',
}

/**
 * Attachment reference behavior. manifest records referenced attachments and validates them
 * on push; none drops attachment references.
 */
export enum MediaEnum {
  Manifest = 'manifest',
  None = 'none',
}

/**
 * Postmeta allowlist, exclude list, and declared reference rewriting rules.
 */
export interface Meta {
  /**
   * Glob patterns for meta keys that must never be projected, even if included.
   */
  exclude?: string[];
  /**
   * Glob patterns for meta keys that may be projected to files.
   */
  include?: string[];
  /**
   * Declared meta references. Only these keys and paths are rewritten between local IDs and
   * portable UIDs.
   */
  references?: { [key: string]: Reference };
  [property: string]: any;
}

export interface Reference {
  /**
   * Referenced entity type.
   */
  kind: Kind;
  /**
   * Optional dot path inside structured meta. Use * to map every array item.
   */
  path?: string;
  [property: string]: any;
}

/**
 * Referenced entity type.
 */
export enum Kind {
  Attachment = 'attachment',
  Post = 'post',
  Term = 'term',
}

/**
 * Settings related to developer tools.
 */
export interface Dev {
  /**
   * Settings related to the canvas.
   */
  canvas?: Canvas;
  /**
   * Settings related to the element grabber.
   */
  grab?: Grab;
  /**
   * Enable the performance profiler. Shows Server-Timing headers and a debug panel on every
   * page load.
   */
  perf?: boolean;
  [property: string]: any;
}

/**
 * Settings related to the canvas.
 */
export interface Canvas {
  /**
   * Show the WordPress admin bar when viewing the canvas.
   */
  adminBar?: boolean;
  /**
   * Enable the canvas.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings related to the element grabber.
 */
export interface Grab {
  /**
   * Enable the element grabber.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings related to the editor.
 */
export interface Editor {
  /**
   * Additional asset IDs to be enqueued.
   */
  assets?: any[];
  /**
   * Format code upon saving.
   */
  formatOnSave?: boolean;
  /**
   * Additional markup to be added to the end of the editor.
   */
  markup?: boolean | string;
  [property: string]: any;
}

/**
 * Settings related to Tailwind.
 */
export interface Tailwind {
  /**
   * Tailwind CSS configuration using v4 CSS-first syntax.
   */
  config?: string;
  /**
   * Enable Tailwind.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings related to bundled UI components.
 */
export interface UI {
  /**
   * Enable bundled UI components.
   */
  enabled?: boolean;
  [property: string]: any;
}

/**
 * Settings related to allowed users with access to the settings and editor.
 */
export interface Users {
  /**
   * List of user IDs with access to the settings and editor.
   */
  ids?: number[];
  /**
   * List of user roles with access to the settings and editor.
   */
  roles?: string[];
  [property: string]: any;
}

// Converts JSON strings to/from your types
// and asserts the results of JSON.parse at runtime
export class Convert {
  public static toBlockstudio(json: string): Blockstudio {
    return cast(JSON.parse(json), r('Blockstudio'));
  }

  public static blockstudioToJson(value: Blockstudio): string {
    return JSON.stringify(uncast(value, r('Blockstudio')), null, 2);
  }
}

function invalidValue(typ: any, val: any, key: any, parent: any = ''): never {
  const prettyTyp = prettyTypeName(typ);
  const parentText = parent ? ` on ${parent}` : '';
  const keyText = key ? ` for key "${key}"` : '';
  throw Error(
    `Invalid value${keyText}${parentText}. Expected ${prettyTyp} but got ${JSON.stringify(val)}`,
  );
}

function prettyTypeName(typ: any): string {
  if (Array.isArray(typ)) {
    if (typ.length === 2 && typ[0] === undefined) {
      return `an optional ${prettyTypeName(typ[1])}`;
    } else {
      return `one of [${typ
        .map((a) => {
          return prettyTypeName(a);
        })
        .join(', ')}]`;
    }
  } else if (typeof typ === 'object' && typ.literal !== undefined) {
    return typ.literal;
  } else {
    return typeof typ;
  }
}

function jsonToJSProps(typ: any): any {
  if (typ.jsonToJS === undefined) {
    const map: any = {};
    typ.props.forEach((p: any) => (map[p.json] = { key: p.js, typ: p.typ }));
    typ.jsonToJS = map;
  }
  return typ.jsonToJS;
}

function jsToJSONProps(typ: any): any {
  if (typ.jsToJSON === undefined) {
    const map: any = {};
    typ.props.forEach((p: any) => (map[p.js] = { key: p.json, typ: p.typ }));
    typ.jsToJSON = map;
  }
  return typ.jsToJSON;
}

function transform(
  val: any,
  typ: any,
  getProps: any,
  key: any = '',
  parent: any = '',
): any {
  function transformPrimitive(typ: string, val: any): any {
    if (typeof typ === typeof val) return val;
    return invalidValue(typ, val, key, parent);
  }

  function transformUnion(typs: any[], val: any): any {
    // val must validate against one typ in typs
    const l = typs.length;
    for (let i = 0; i < l; i++) {
      const typ = typs[i];
      try {
        return transform(val, typ, getProps);
      } catch {}
    }
    return invalidValue(typs, val, key, parent);
  }

  function transformEnum(cases: string[], val: any): any {
    if (cases.indexOf(val) !== -1) return val;
    return invalidValue(
      cases.map((a) => {
        return l(a);
      }),
      val,
      key,
      parent,
    );
  }

  function transformArray(typ: any, val: any): any {
    // val must be an array with no invalid elements
    if (!Array.isArray(val)) return invalidValue(l('array'), val, key, parent);
    return val.map((el) => transform(el, typ, getProps));
  }

  function transformDate(val: any): any {
    if (val === null) {
      return null;
    }
    const d = new Date(val);
    if (isNaN(d.valueOf())) {
      return invalidValue(l('Date'), val, key, parent);
    }
    return d;
  }

  function transformObject(
    props: { [k: string]: any },
    additional: any,
    val: any,
  ): any {
    if (val === null || typeof val !== 'object' || Array.isArray(val)) {
      return invalidValue(l(ref || 'object'), val, key, parent);
    }
    const result: any = {};
    Object.getOwnPropertyNames(props).forEach((key) => {
      const prop = props[key];
      const v = Object.prototype.hasOwnProperty.call(val, key)
        ? val[key]
        : undefined;
      result[prop.key] = transform(v, prop.typ, getProps, key, ref);
    });
    Object.getOwnPropertyNames(val).forEach((key) => {
      if (!Object.prototype.hasOwnProperty.call(props, key)) {
        result[key] = transform(val[key], additional, getProps, key, ref);
      }
    });
    return result;
  }

  if (typ === 'any') return val;
  if (typ === null) {
    if (val === null) return val;
    return invalidValue(typ, val, key, parent);
  }
  if (typ === false) return invalidValue(typ, val, key, parent);
  let ref: any = undefined;
  while (typeof typ === 'object' && typ.ref !== undefined) {
    ref = typ.ref;
    typ = typeMap[typ.ref];
  }
  if (Array.isArray(typ)) return transformEnum(typ, val);
  if (typeof typ === 'object') {
    return typ.hasOwnProperty('unionMembers')
      ? transformUnion(typ.unionMembers, val)
      : typ.hasOwnProperty('arrayItems')
        ? transformArray(typ.arrayItems, val)
        : typ.hasOwnProperty('props')
          ? transformObject(getProps(typ), typ.additional, val)
          : invalidValue(typ, val, key, parent);
  }
  // Numbers can be parsed by Date but shouldn't be.
  if (typ === Date && typeof val !== 'number') return transformDate(val);
  return transformPrimitive(typ, val);
}

function cast<T>(val: any, typ: any): T {
  return transform(val, typ, jsonToJSProps);
}

function uncast<T>(val: T, typ: any): any {
  return transform(val, typ, jsToJSONProps);
}

function l(typ: any) {
  return { literal: typ };
}

function a(typ: any) {
  return { arrayItems: typ };
}

function u(...typs: any[]) {
  return { unionMembers: typs };
}

function o(props: any[], additional: any) {
  return { props, additional };
}

function m(additional: any) {
  return { props: [], additional };
}

function r(name: string) {
  return { ref: name };
}

const typeMap: any = {
  Blockstudio: o(
    [
      { json: 'ai', js: 'ai', typ: u(undefined, r('AI')) },
      { json: 'assets', js: 'assets', typ: u(undefined, r('Assets')) },
      {
        json: 'blockEditor',
        js: 'blockEditor',
        typ: u(undefined, r('BlockEditor')),
      },
      { json: 'blockTags', js: 'blockTags', typ: u(undefined, r('BlockTags')) },
      { json: 'cache', js: 'cache', typ: u(undefined, r('Cache')) },
      { json: 'content', js: 'content', typ: u(undefined, r('Content')) },
      { json: 'dev', js: 'dev', typ: u(undefined, r('Dev')) },
      { json: 'editor', js: 'editor', typ: u(undefined, r('Editor')) },
      { json: 'tailwind', js: 'tailwind', typ: u(undefined, r('Tailwind')) },
      { json: 'ui', js: 'ui', typ: u(undefined, r('UI')) },
      { json: 'users', js: 'users', typ: u(undefined, r('Users')) },
    ],
    'any',
  ),
  AI: o(
    [
      {
        json: 'enableContextGeneration',
        js: 'enableContextGeneration',
        typ: u(undefined, true),
      },
    ],
    'any',
  ),
  Assets: o(
    [
      { json: 'enqueue', js: 'enqueue', typ: u(undefined, true) },
      { json: 'minify', js: 'minify', typ: u(undefined, r('Minify')) },
      { json: 'process', js: 'process', typ: u(undefined, r('Process')) },
      { json: 'reset', js: 'reset', typ: u(undefined, r('Reset')) },
    ],
    'any',
  ),
  Minify: o(
    [
      { json: 'css', js: 'css', typ: u(undefined, true) },
      { json: 'js', js: 'js', typ: u(undefined, true) },
    ],
    'any',
  ),
  Process: o(
    [
      { json: 'scss', js: 'scss', typ: u(undefined, true) },
      { json: 'scssFiles', js: 'scssFiles', typ: u(undefined, true) },
    ],
    'any',
  ),
  Reset: o(
    [
      { json: 'enabled', js: 'enabled', typ: u(undefined, true) },
      { json: 'fullWidth', js: 'fullWidth', typ: u(undefined, a('')) },
    ],
    'any',
  ),
  BlockEditor: o(
    [
      { json: 'blocks', js: 'blocks', typ: u(undefined, r('Blocks')) },
      { json: 'cssClasses', js: 'cssClasses', typ: u(undefined, a('any')) },
      { json: 'cssVariables', js: 'cssVariables', typ: u(undefined, a('any')) },
      { json: 'disableLoading', js: 'disableLoading', typ: u(undefined, true) },
      { json: 'enhance', js: 'enhance', typ: u(undefined, true) },
      { json: 'media', js: 'media', typ: u(undefined, r('MediaObject')) },
      { json: 'patterns', js: 'patterns', typ: u(undefined, r('Patterns')) },
    ],
    'any',
  ),
  Blocks: o(
    [
      { json: 'allow', js: 'allow', typ: u(undefined, a('')) },
      {
        json: 'categories',
        js: 'categories',
        typ: u(undefined, r('BlocksCategories')),
      },
      { json: 'deny', js: 'deny', typ: u(undefined, a('')) },
      { json: 'directory', js: 'directory', typ: u(undefined, true) },
      {
        json: 'legacyWidgets',
        js: 'legacyWidgets',
        typ: u(undefined, r('LegacyWidgets')),
      },
      { json: 'styles', js: 'styles', typ: u(undefined, r('Styles')) },
    ],
    'any',
  ),
  BlocksCategories: o(
    [
      { json: 'allow', js: 'allow', typ: u(undefined, a('')) },
      { json: 'deny', js: 'deny', typ: u(undefined, a('')) },
      { json: 'order', js: 'order', typ: u(undefined, a('')) },
      { json: 'rename', js: 'rename', typ: u(undefined, m('')) },
    ],
    'any',
  ),
  LegacyWidgets: o(
    [{ json: 'hide', js: 'hide', typ: u(undefined, a('')) }],
    'any',
  ),
  Styles: o([{ json: 'deny', js: 'deny', typ: u(undefined, m(a(''))) }], 'any'),
  MediaObject: o(
    [
      {
        json: 'imageSizes',
        js: 'imageSizes',
        typ: u(undefined, r('ImageSizes')),
      },
      { json: 'openverse', js: 'openverse', typ: u(undefined, true) },
    ],
    'any',
  ),
  ImageSizes: o(
    [
      { json: 'allow', js: 'allow', typ: u(undefined, a('')) },
      { json: 'deny', js: 'deny', typ: u(undefined, a('')) },
    ],
    'any',
  ),
  Patterns: o(
    [
      { json: 'blockstudio', js: 'blockstudio', typ: u(undefined, true) },
      {
        json: 'categories',
        js: 'categories',
        typ: u(undefined, r('PatternsCategories')),
      },
      { json: 'core', js: 'core', typ: u(undefined, true) },
      { json: 'remote', js: 'remote', typ: u(undefined, true) },
      { json: 'theme', js: 'theme', typ: u(undefined, true) },
    ],
    'any',
  ),
  PatternsCategories: o(
    [
      { json: 'allow', js: 'allow', typ: u(undefined, a('')) },
      { json: 'deny', js: 'deny', typ: u(undefined, a('')) },
      { json: 'order', js: 'order', typ: u(undefined, a('')) },
      { json: 'rename', js: 'rename', typ: u(undefined, m('')) },
    ],
    'any',
  ),
  BlockTags: o(
    [
      { json: 'allow', js: 'allow', typ: u(undefined, a('')) },
      { json: 'deny', js: 'deny', typ: u(undefined, a('')) },
      { json: 'enabled', js: 'enabled', typ: u(undefined, true) },
      { json: 'prefixes', js: 'prefixes', typ: u(undefined, m('any')) },
    ],
    'any',
  ),
  Cache: o(
    [{ json: 'enabled', js: 'enabled', typ: u(undefined, true) }],
    'any',
  ),
  Content: o(
    [
      { json: 'authors', js: 'authors', typ: u(undefined, r('Authors')) },
      { json: 'enabled', js: 'enabled', typ: u(undefined, true) },
      { json: 'id', js: 'id', typ: u(undefined, '') },
      {
        json: 'includePageSyncManaged',
        js: 'includePageSyncManaged',
        typ: u(undefined, true),
      },
      { json: 'media', js: 'media', typ: u(undefined, r('MediaEnum')) },
      { json: 'meta', js: 'meta', typ: u(undefined, r('Meta')) },
      { json: 'path', js: 'path', typ: u(undefined, '') },
      { json: 'postTypes', js: 'postTypes', typ: u(undefined, a('')) },
      { json: 'taxonomies', js: 'taxonomies', typ: u(undefined, a('')) },
    ],
    'any',
  ),
  Meta: o(
    [
      { json: 'exclude', js: 'exclude', typ: u(undefined, a('')) },
      { json: 'include', js: 'include', typ: u(undefined, a('')) },
      {
        json: 'references',
        js: 'references',
        typ: u(undefined, m(r('Reference'))),
      },
    ],
    'any',
  ),
  Reference: o(
    [
      { json: 'kind', js: 'kind', typ: r('Kind') },
      { json: 'path', js: 'path', typ: u(undefined, '') },
    ],
    'any',
  ),
  Dev: o(
    [
      { json: 'canvas', js: 'canvas', typ: u(undefined, r('Canvas')) },
      { json: 'grab', js: 'grab', typ: u(undefined, r('Grab')) },
      { json: 'perf', js: 'perf', typ: u(undefined, true) },
    ],
    'any',
  ),
  Canvas: o(
    [
      { json: 'adminBar', js: 'adminBar', typ: u(undefined, true) },
      { json: 'enabled', js: 'enabled', typ: u(undefined, true) },
    ],
    'any',
  ),
  Grab: o([{ json: 'enabled', js: 'enabled', typ: u(undefined, true) }], 'any'),
  Editor: o(
    [
      { json: 'assets', js: 'assets', typ: u(undefined, a('any')) },
      { json: 'formatOnSave', js: 'formatOnSave', typ: u(undefined, true) },
      { json: 'markup', js: 'markup', typ: u(undefined, u(true, '')) },
    ],
    'any',
  ),
  Tailwind: o(
    [
      { json: 'config', js: 'config', typ: u(undefined, '') },
      { json: 'enabled', js: 'enabled', typ: u(undefined, true) },
    ],
    'any',
  ),
  UI: o([{ json: 'enabled', js: 'enabled', typ: u(undefined, true) }], 'any'),
  Users: o(
    [
      { json: 'ids', js: 'ids', typ: u(undefined, a(0)) },
      { json: 'roles', js: 'roles', typ: u(undefined, a('')) },
    ],
    'any',
  ),
  Authors: ['ignore', 'login'],
  MediaEnum: ['manifest', 'none'],
  Kind: ['attachment', 'post', 'term'],
};
