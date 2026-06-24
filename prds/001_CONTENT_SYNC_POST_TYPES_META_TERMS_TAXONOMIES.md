# 001: Content Sync (Post Types + Postmeta, Terms, Taxonomies)

## Summary

Blockstudio already turns *page templates* into files via **Page Sync**: a one-way
files to database projection with stable identity, SHA256 fingerprinting, orphan
pruning, and keyed-block merge. What it does not do is version **content**: the
posts, postmeta, terms, and taxonomies that make up a site's actual data.

This PRD adds **Content Sync**: a bidirectional, git-friendly projection of
WordPress content to and from human-diffable files, with **portable identity** so
the same content round-trips across environments (local to staging to production)
and across git branches without auto-increment ID collisions.

The content surface is the WordPress content core:

- `wp_posts` (any allowlisted post type) and `wp_postmeta`
- `wp_terms`, `wp_term_taxonomy`, `wp_termmeta`
- `wp_term_relationships` (the post-to-term join: the connective tissue)

Two developer commands frame the workflow:

- `wp bs content pull` (database to files): capture current content into files.
- `wp bs content push` (files to database): apply the file content to this
  environment.

Files are the source of truth; the database is a projection. This is the same
stance Page Sync already takes, generalized from page templates to content data.

This is a Blockstudio-owned feature. It sits natively next to `Page_Sync`,
`Storage_Sync`, and `Database`, and reuses their proven internals (see
"Architecture: Extract A Shared Sync Core"). Downstream products (for example a
git-workspace product that wants content variants per worktree) consume the same
commands and files; none of that coupling lives here.

## Goals

- Project allowlisted content to files that are diffable, reviewable, and
  mergeable in git.
- Round-trip content across environments and a fresh database with **zero broken
  references** (parents, attachments, term assignments, IDs embedded in meta).
- Bidirectional and idempotent: `pull` then `push` then `pull` yields identical
  files; a no-change `push` performs no database writes.
- Allowlist-scoped, with a safe-by-default exclusion of users, secrets, and core
  internal state.
- Reuse Blockstudio's existing identity, fingerprint, orphan, meta, and
  file-projection machinery rather than reimplementing it. Extract a shared core
  where reuse is real.

## Non Goals (v1)

- Media binaries. Attachments are referenced by a portable manifest; the binary
  files in `wp-content/uploads` are not copied or committed in v1.
- `wp_options`, widgets, nav menus, comments, users, and post revisions. The
  allowlist is the boundary; git is the revision history.
- Full-database mirroring (VersionPress-style live change tracking). Content Sync
  is explicit and on-demand, not a write-time hook on every database mutation.
- A general migration or staging product. This is a content projection, not a
  deployment pipeline.

## Current State (grounded findings)

Blockstudio is further along than a blank slate. The identity, fingerprint,
orphan, meta read/write, and file-projection concerns already exist in mature
form. Content Sync builds on them.

### Page Sync (files to database, one way)

`includes/classes/page-sync.php`:

- `sync()` (~L46) inserts or updates one post per page file.
- `find_existing_post()` (L300-384): three-tier identity lookup. A pinned
  `postId` in `page.json`, then `_blockstudio_page_key` meta, then
  `_blockstudio_page_source` meta, then `_blockstudio_page_name` plus
  `_blockstudio_page_collection`. This is how a file maps back to the same row on
  every run, and how duplicates are avoided.
- `build_fingerprint()` (L834-874): SHA256 over page data plus full template and
  layout file contents, stored in `_blockstudio_page_fingerprint`. Drives
  skip-if-unchanged.
- `prune_duplicate_posts()` (L747-774) and `mark_stale_missing()` (L682-711):
  orphan detection and pruning, with the `blockstudio/pages/orphan_action` filter
  (L730) choosing trash, delete, or keep.
- Hooks: `create_post_data` (L461), `post_created` (L477), `update_post_data`
  (L526), `post_updated` (L542).
- `_blockstudio_page_locked` plus `Pages::lock()` / `Pages::unlock()`
  (`pages.php` L1468-1499) prevent sync from overwriting in-progress edits.
- Direction is one way. There is no database to file export anywhere in the
  plugin today.

`includes/classes/page-discovery.php` discovers files and topologically sorts
pages parent-first (L1044-1065, parent-child resolution L1005-1037).
`includes/classes/page-registry.php` is the in-memory registry.
`includes/classes/block-merger.php` preserves keyed-block client edits on update.

### Postmeta read/write already exists

- `includes/classes/storage-sync.php`: on `save_post` (L42), extracts block field
  values and writes them to postmeta via `update_post_meta` / `delete_post_meta`
  (L244-249).
- `includes/classes/storage-handlers/post-meta-storage.php`: `register()`
  (L42-66) wires `register_post_meta` with `show_in_rest` and a field-type to
  meta-type mapping (L93-112), including array handling.

### A records-to-files precedent already exists

- `includes/classes/database.php` has a `db.php` schema system with multiple
  storage backends: `table`, `sqlite`, `jsonc`, `meta`, and `post_type`.
- `storage: post_type` registers a `bs_`-prefixed CPT and stores each record as a
  post with fields as postmeta (`register_post_types()` L2119-2144, `cpt_create()`
  L2308-2330, `cpt_to_record()` L2154-2183).
- `storage: jsonc` already writes records to JSONC files on disk with
  `wp_json_encode` (`jsonc_write()` ~L1604). This is the existing
  records-as-files pattern Content Sync's file writer should learn from or share.

### Taxonomies are read-only today

- `includes/classes/populate.php` (L158-192) reads existing taxonomies via
  `get_terms()` for field population. Blockstudio does **not** call
  `register_taxonomy`, `wp_insert_term`, or `wp_set_object_terms` anywhere.
- Therefore term creation, termmeta, and term-relationship writes are genuinely
  new. Reading and writing terms is simpler than posts (no block content), but the
  relationship join and hierarchical parents still need ordered, reference-correct
  application.

### Config and command surfaces

- `includes/classes/settings.php`: config is `blockstudio.json` in the theme root
  (`json_path()` L306-310), merged defaults to `wp_options` to JSON to filters.
  `Settings::get('a/b/c')` reads nested paths (L420-440). Defaults at L75-170
  already include keys like `assets`, `tailwind`, `blockEditor`, `blockTags`,
  `users`. A new `content` key fits this model.
- `includes/classes/cli.php`: `Cli::register()` (L24-34) registers `wp bs blocks`,
  `wp bs db`, `wp bs settings`, and others. `wp bs db list --format=json`
  (L115-129) already exports records via `WP_CLI\Utils\format_items`. `wp bs
  content` is the natural home for the new commands.
- REST exists too (`includes/classes/admin-page.php` L547-600; `registry/import`
  already writes files), but v1 is CLI-first. REST is optional and later.

### What this means

- Reuse: identity lookup, fingerprinting, orphan pruning, locking, discovery and
  topological ordering, postmeta read/write with type mapping, and a
  records-to-files writer all already exist.
- New: the database to file (pull) direction, term and taxonomy writes,
  term-relationship sync, and above all **portable cross-environment identity**
  with reference rewriting.

## The Core Problem: Portable Identity

Auto-increment IDs are not portable. Post 42 locally is post 99 in production.
Nearly everything references those IDs: `post_parent`, `_thumbnail_id`, term
relationships, `post_author`, and IDs embedded inside serialized or JSON
`meta_value`. If files are keyed by ID, they do not survive a second environment
and git merges corrupt references.

Page Sync partially solved this for pages with `_blockstudio_page_key` (a stable
key) and optional `postId` pinning, but the key is path-derived and effectively
environment-local, and Page Sync never has to rewrite references because it does
not export.

Content Sync needs a stronger, explicit identity layer:

1. **A portable UID per entity.** On first pull, assign each synced post and term
   a GUID stored in meta (`_blockstudio_uid` in postmeta and termmeta). The file
   is keyed by this UID, never by the auto-increment ID. The UID travels with the
   file across environments and branches.
2. **References stored as UIDs, not local IDs.** `post_parent`, attachment
   references (`_thumbnail_id` and any allowlisted attachment-id meta), term
   relationships, and author are all serialized in the file as UIDs.
3. **Reference rewriting on both directions.** On pull, local IDs are translated
   to UIDs (local to UID). On push, UIDs are translated to this environment's
   local IDs (UID to local), inserting missing entities first.
4. **Dependency-ordered, transactional application.** Push applies in order:
   taxonomies, then terms (parents before children), then posts (parents before
   children, attachments before posts that reference them), then term
   relationships, then meta with rewritten references. A failure rolls back the
   batch.

Get this layer right and the rest is mechanical projection. Get it wrong and the
files do not survive a second database. The portability test in "Portability
Acceptance" is the gate that proves it.

## Architecture: Extract A Shared Sync Core

Content Sync should not fork or duplicate Page Sync. The two share most of their
mechanics. Extract the proven, behavior-stable internals of Page Sync (and the
relevant pieces of Storage Sync and Database) into an internal, reusable **sync
core** that both Page Sync and Content Sync consume.

Extraction is justified here because the second consumer is real (Content Sync)
and the boundary is already well understood from Page Sync's mature
implementation. This is not speculative abstraction. The discipline:

- Page Sync is the first consumer and its behavior must stay **identical**. The
  existing Page Sync E2E and unit tests are the regression gate for the
  extraction. Refactor behind a stable seam; do not change page behavior.
- Extract only where reuse is genuine. Page-specific logic (keyed block merge,
  block markup parsing, template languages) stays in Page Sync.

Candidate core services (internal namespace, for example `Blockstudio\Sync\`):

| Core service | Extract from | Reused by Content Sync |
|---|---|---|
| Identity (UID issue and lookup, insert-vs-update) | `Page_Sync::find_existing_post`, page meta keys | UID for posts and terms, generalized to any entity |
| Fingerprint (SHA256, mtime, skip-if-unchanged) | `Page_Sync::build_fingerprint` (L834-874) | Per-file change detection on push and pull |
| Orphan and prune (stale detect, trash/delete/keep) | `prune_duplicate_posts`, `mark_stale_missing`, `orphan_action` filter | `--prune` on push, deleted-in-files handling |
| Lock (skip in-progress edits) | `_blockstudio_page_locked`, `Pages::lock/unlock` | Per-entity lock during editing |
| Discovery and ordering (scan files, topological parent-first) | `Page_Discovery` ordering (L1044-1065) | Content file discovery, dependency ordering |
| Meta projection (read/write, type map, array/serialized) | `Storage_Sync` (L244-249), `Post_Meta_Storage` (L42-112) | Postmeta and termmeta read/write |
| File projection (records to files, encode/format) | `Database::jsonc_write` (~L1604) | Content file writer and reader |

New code that has no existing equivalent (lives in Content Sync, may graduate to
the core later):

- Reference rewriting (UID to local ID and back) and the dependency graph.
- Term, taxonomy, and term-relationship read/write.
- The database to file (pull) direction and serialization format.

Deliverable of this section: a refactor step that lands the shared core with Page
Sync green and unchanged, followed by Content Sync built on top. If the
extraction proves riskier than expected for any one service, that service stays
duplicated with a tracking note rather than destabilizing Page Sync. Reuse is the
default, never at the cost of a Page Sync regression.

## Design

### Identity and references

- `_blockstudio_uid`: a GUID in postmeta (posts) and termmeta (terms), issued on
  first pull, never changed. The portability anchor. Distinct from
  `_blockstudio_page_key` (path-derived, environment-local), which Page Sync keeps
  for its own use.
- The file name carries the UID for collision safety, with a human-readable slug
  for readability, for example `about.<short-uid>.md`. Final naming in Open
  Questions.
- Reference fields serialized as UIDs: `parent` (post or term UID), `terms`
  (taxonomy to list of term UIDs), `author` (a portable author reference, see
  Edge Cases), and any allowlisted meta key flagged as an attachment or
  post reference.
- A reference resolver builds a UID-to-local-ID map for the current environment on
  push (creating missing entities in dependency order) and a local-ID-to-UID map
  on pull. Meta values are walked (including inside serialized and JSON
  structures) and rewritten in place.

### Content surface and allowlist (blockstudio.json)

A new `content` settings key, read via `Settings::get('content/...')`:

```json
{
  "content": {
    "enabled": true,
    "path": "content",
    "postTypes": ["page", "post", "team_member"],
    "meta": {
      "include": ["_my_*"],
      "exclude": ["_edit_lock", "_edit_last", "_wp_old_slug"],
      "references": { "_thumbnail_id": "attachment" }
    },
    "taxonomies": ["category", "post_tag"],
    "media": "manifest"
  }
}
```

- `postTypes`: allowlist of post types to sync. Empty means none. There is no
  implicit "all".
- `meta.include` / `meta.exclude`: glob-aware allowlist of meta keys. The default
  exclude list covers WordPress internal and edit-lock meta. Anything not matched
  by `include` is not written to files.
- `meta.references`: meta keys whose values are entity IDs needing rewriting, with
  the referenced kind (`attachment`, `post`, `term`).
- `taxonomies`: which taxonomies' terms and relationships to sync. v1 assumes the
  taxonomy is registered by the site (Blockstudio does not register taxonomies).
  Capturing `register_taxonomy` definitions for a fresh environment is a thin,
  optional extension (Open Questions), not required for v1.
- `media`: `manifest` (default) records attachment references and a content hash;
  `none` drops attachment references; copying binaries is out of scope for v1.
- The allowlist is also the security boundary (see Security And Safety).

### File layout and serialization

```
<theme>/content/
  <post-type>/
    <slug>.<short-uid>.md       # one file per post
  terms/
    <taxonomy>/
      <slug>.<short-uid>.json   # one file per term
  taxonomies/
    <taxonomy>.json             # optional taxonomy definition capture
  media/
    manifest.json               # attachment uid -> {path, hash, mime, alt}
```

Post file: YAML front matter plus body.

```markdown
---
uid: 9b1c0e6e-...
type: page
status: publish
slug: about
title: About Us
date: 2026-01-02T10:00:00Z
modified: 2026-01-04T08:30:00Z
parent: 3f2a... | null
author: { login: "jane" } | null
menu_order: 0
terms:
  category: [c1a2..., 7d44...]
meta:
  _my_subtitle: "We build things"
  _thumbnail_id: 5e90...        # rewritten to attachment uid
  _structured: { rows: [ ... ] } # unserialized, references rewritten
---
<post_content>
```

- Front matter keys are written in a stable order; lists are stable-sorted by UID
  where order is not semantic, preserved where it is (menu_order, block order in
  body). The goal is minimal, meaningful git diffs.
- `post_content` is written as-is (block markup or HTML). Body parsing and keyed
  merge are Page Sync's concern, not Content Sync's; Content Sync treats body as
  opaque content unless the post is also a Page Sync page (see Boundaries).
- Postmeta is unserialized for the file (PHP `serialize` and JSON both handled)
  and reserialized on push to the exact storage form WordPress expects. Embedded
  references inside structures are rewritten.
- A per-file `fingerprint` (reusing the core fingerprint service) supports
  skip-if-unchanged on push and dirty detection on pull.

### Commands

```
wp bs content pull [--post-type=<type>] [--taxonomy=<tax>] [--dry-run]
wp bs content push [--dry-run] [--prune] [--yes]
wp bs content status
```

Direction is explicit and unambiguous:

| Command | Direction | Meaning |
|---|---|---|
| `pull` | database to files | Extract current content into files. Captures edits made in wp-admin. |
| `push` | files to database | Apply file content to this environment. The authoritative deploy direction. |
| `status` | read only | Show the diff between files and database (created, updated, orphaned, conflicted). |

- Unlike Page Sync, Content Sync does **not** run on every admin `init`. Content
  is data; writing it must be deliberate. Sync is on-demand via CLI (and later
  optional REST for an admin UI). This avoids surprise database writes on page
  load.
- `--dry-run` prints the plan (the exact inserts, updates, prunes, and reference
  resolutions) without writing. Required to be honest and complete.
- `--prune` enables orphan handling on push (see below). Off by default so push
  is additive unless asked.
- `--yes` skips the confirmation prompt for destructive operations.

### Push semantics (files to database)

1. Discover and parse content files; build the dependency graph.
2. Resolve identity: for each file UID, find the local entity via
   `_blockstudio_uid` meta. Insert if missing, update if present, in dependency
   order (taxonomies, terms parents-first, posts parents-and-attachments-first,
   relationships, meta).
3. Skip-if-unchanged: if the file fingerprint matches the stored fingerprint and
   the entity is unlocked, perform no write.
4. Rewrite references UID to local ID using the resolver, including inside
   serialized and JSON meta.
5. Apply term relationships (`wp_set_object_terms`) after posts and terms exist.
6. Prune (only with `--prune`): entities that carry a `_blockstudio_uid` from this
   content set but are absent from the files are treated as orphans via the
   shared orphan service and the `orphan_action` policy (trash, delete, keep).
7. Locked entities (`_blockstudio_uid` plus a lock flag) are never overwritten;
   they are reported in `status` and skipped.
8. The batch is transactional per run: a mid-apply failure rolls back so the
   database is never left with dangling references.

### Pull semantics (database to files)

1. Query allowlisted content (post types, taxonomies) honoring the meta
   allowlist.
2. Ensure identity: any entity without `_blockstudio_uid` is assigned one (a
   database write, the only write `pull` performs, and an idempotent one).
3. Map local IDs to UIDs; serialize each entity to its file with references as
   UIDs and meta unserialized.
4. Write only changed files (fingerprint compare) so `pull` produces a minimal,
   reviewable diff.
5. Build or update `media/manifest.json` for referenced attachments.
6. Report created, updated, and (with a flag) files whose database source no
   longer exists.

### Conflict, source of truth, idempotency

- Files are the source of truth. `push` is authoritative; `pull` captures.
- Both directions use the shared fingerprint service to avoid spurious writes.
  Idempotency invariant: `pull; push; pull` leaves files byte-identical, and a
  second `push` with no file change writes nothing.
- Conflict (the database changed under a file since last sync): `status` flags it;
  `push` honors the lock and the files-win rule but surfaces the overwrite in
  `--dry-run`; `pull` would capture the database state into the file for git to
  resolve. The lock mechanism (reused from Page Sync) is the explicit "do not
  overwrite this" signal.

## Edge Cases

- Serialized meta with embedded IDs: unserialize, rewrite references inside, never
  string-edit (length prefixes corrupt). Round-trip must be exact for values with
  no references.
- Hierarchical terms and posts: parents before children; a missing parent UID is
  an ordered insert, not an error.
- Attachments: referenced by UID via the manifest in v1. A reference to an
  attachment not present in the target environment is reported, not silently
  dropped; the post still applies with the reference unresolved and flagged.
- Authors: stored as a portable reference (login or a stable user key), not a raw
  user ID. On push, resolve to a local user if present, otherwise fall back to the
  importing user and flag. Users are not synced (Non Goals).
- Duplicate slugs across post types or terms across taxonomies: UID is the
  identity, slug is cosmetic; collisions are tolerated.
- Multisite: scope to the current site; UIDs are per content set. Cross-site is
  out of scope for v1.
- Large content sets: stream and batch; `pull` and `push` must not load the entire
  database into memory at once.
- Non-UTF8 or binary-ish meta: detect and either base64-encode in the file with a
  marker or exclude with a report. Never silently mangle.
- Block markup referencing IDs (for example a query block or an image block with a
  hardcoded attachment id): treated as content in the body; v1 does not rewrite
  IDs inside block markup, and this limitation is logged in `status` so it is
  visible rather than a silent break. Rewriting block-embedded IDs is a defined
  later phase.
- Deleted in files vs deleted in database: deleted-in-files is the `--prune` path;
  deleted-in-database surfaces on `pull` as a stale file report.

## Security And Safety

- The allowlist is the boundary. Nothing syncs unless a post type or taxonomy is
  explicitly listed, and meta requires an `include` match.
- Default exclusions protect against accidental leakage: no users, no
  `wp_options`, no transients, no edit-lock or session meta. Document a warning
  against adding meta keys that hold secrets, tokens, or PII to the allowlist.
- Files may end up in a git remote. Treat the allowlist as "this is safe to commit
  to a repository other people can read." `status` should warn if an allowlisted
  meta key matches common secret patterns.
- All commands require `manage_options` (CLI runs as admin); a REST surface, if
  added later, uses the same capability check pattern as existing routes
  (`admin-page.php`).

## Portability Acceptance (the bar)

The single acceptance test that proves the identity layer:

1. On environment A (seeded content), run `wp bs content pull`.
2. Commit the files. On environment B with a **fresh, empty database** (different
   auto-increment baseline), check out the files and run `wp bs content push`.
3. Assert environment B renders and queries identically to A: every post present,
   every parent correct, every term assignment intact, every allowlisted meta
   value equal, every attachment reference resolved against B's media (or flagged
   identically), and no dangling references.
4. Run `wp bs content pull` on B and assert the files are byte-identical to the
   committed files (round-trip stability).

If this passes on a database with a deliberately offset auto-increment, identity
is portable. This is the gate for v1.

## Verification And CI

Blockstudio CI (`.github/workflows/ci.yml`):

- Lint (TypeScript plus PHPCS) runs on every push. 100% WordPress Coding
  Standards, no exceptions.
- Unit tests run when the commit message contains `[unit]` or `[all]` (L43).
- E2E tests run when the commit message contains `[e2e]` or `[all]` (L78).

The **final conclusion gate for this feature is `[all]`**: a commit tagged
`[all]` runs lint, unit, and E2E together, and that combined run going green is
the acceptance signal. Use `[all]` for the landing commits of each phase.

Required coverage:

- Unit: UID issue and idempotency, reference rewriting (including inside
  serialized and JSON meta), serialization round-trip exactness, allowlist
  enforcement (include and exclude), dependency ordering, orphan and prune
  decisions, lock skip.
- E2E (wp-env, real database): a full `pull` then `push` cycle against seeded
  content; term-relationship correctness; the portability test above run against
  the second (empty) wp-env that CI already starts.
- Regression: the existing Page Sync unit and E2E suites must stay green across
  the shared-core extraction. That is the guardrail for the refactor.

Follow the repo conventions: update `docs/src/schemas/` for the new
`blockstudio.json` `content` settings, add the TypeScript types, document the
commands under `docs/content/docs/`, and add a `readme.txt` changelog entry at the
release boundary (not for iterative work).

## Phasing And Implementation Order

1. **Shared sync core extraction.** Lift identity, fingerprint, orphan, lock,
   discovery and ordering, meta projection, and file projection into
   `Blockstudio\Sync\` with Page Sync as the first consumer, behavior identical,
   Page Sync tests green. No Content Sync behavior yet.
2. **Identity and one post type.** UID issue and lookup, reference resolver,
   `pull` and `push` for a single allowlisted post type plus its allowlisted meta.
   Land the portability test against an offset-ID database. This is the
   make-or-break slice.
3. **Terms, taxonomies, relationships.** Term and termmeta read/write, parents,
   `wp_set_object_terms`, term-relationship sync, ordering integrated into the
   graph.
4. **Orphan, lock, status, dry-run.** `--prune`, lock skipping, `status` diff,
   `--dry-run` plans for both directions.
5. **Widen and harden.** Multiple post types, full meta allowlist semantics, media
   manifest, large-set batching, multisite scoping, secret-pattern warnings.

Deferred beyond v1: media binary copying, block-embedded ID rewriting, options
and menus, taxonomy definition capture, a REST and admin UI surface.

## Boundaries And Relationships

- **Page Sync** owns page *template and structure* authoring (files to database,
  keyed merge, block markup). **Content Sync** owns content *data* (posts as
  records, meta, terms, taxonomies, relationships) with portable identity and
  bidirectional push and pull. A page can be authored by Page Sync and have its
  content data captured by Content Sync; they share the core but own different
  concerns. Content Sync treats `post_content` as opaque body and does not parse
  blocks.
- **`storage: jsonc`** in `Database` is the existing records-to-files pattern.
  Content Sync's file writer should share or mirror it. Whether Content Sync
  eventually subsumes `jsonc` storage is an Open Question; v1 keeps them separate.
- **Downstream products** (for example a git-workspace product that wants content
  variants compared across worktrees) consume `wp bs content` and the files. That
  integration does not live in Blockstudio and is out of scope here.

## Open Questions

- File naming: `slug.<short-uid>.md` versus a `uid`-only name with slug in front
  matter. Diff readability versus rename churn when slugs change.
- Author portability: login versus a dedicated portable user key; behavior when
  the user is absent on push (fall back and flag is the proposed default).
- Taxonomy definition capture: whether to record `register_taxonomy` args so a
  fresh environment can register the taxonomy, given Blockstudio does not register
  taxonomies today. Proposed: optional and deferred.
- Relationship to `storage: jsonc`: share the writer now, or keep separate until
  the format stabilizes. Proposed: separate in v1, converge later.
- `push`/`pull` naming confirmation: the table above defines them as files-as-local
  (push deploys files to the database, pull captures the database to files). Lock
  this before implementation.
