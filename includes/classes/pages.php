<?php
/**
 * Pages class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Main orchestration class for file-based pages.
 *
 * This class provides the public API for the file-based pages feature,
 * handling discovery, registration, and syncing of pages.
 *
 * @since 7.0.0
 */
class Pages {

	/**
	 * Whether pages have been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Whether page hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Current page while rendering a layout.
	 *
	 * @var array|null
	 */
	private static ?array $current_page = null;

	/**
	 * Current page content while rendering a layout.
	 *
	 * @var string
	 */
	private static string $current_page_content = '';

	/**
	 * Whether a layout is currently rendering.
	 *
	 * @var bool
	 */
	private static bool $rendering_layout = false;

	/**
	 * Initialize the pages system.
	 *
	 * @param array $args Optional arguments.
	 *
	 * @return void
	 */
	public static function init( array $args = array() ): void {
		if ( ! self::can_init_in_current_context( $args ) ) {
			return;
		}

		if ( self::$initialized && empty( $args['force'] ) ) {
			return;
		}

		self::register_collection_post_types();

		$paths = self::get_paths();

		/**
		 * Filter the pages discovery paths.
		 *
		 * @param array $paths Array of directory paths to scan for pages.
		 */
		$paths = apply_filters( 'blockstudio/pages/paths', $paths );

		$registry = Page_Registry::instance();
		$sync     = new Page_Sync();

		if ( ! empty( $args['force'] ) ) {
			$registry->reset();
		}

		$active_sources = array();
		$post_types     = array();

		foreach ( $paths as $path ) {
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$path      = untrailingslashit( wp_normalize_path( $path ) );
			$discovery = new Page_Discovery();

			$registry->add_path( $path );

			$pages = $discovery->discover( $path );

			foreach ( $discovery->get_collections() as $collection => $collection_data ) {
				$registry->register_collection( $collection, $collection_data );
			}

			$registry->add_errors( $discovery->get_errors() );

			foreach ( $pages as $name => $page_data ) {
				$registry->register( $name, $page_data );
			}
		}

		foreach ( $registry->get_pages() as $name => $page_data ) {
			if ( ! empty( $page_data['parent_key'] ) && ! is_post_type_hierarchical( $page_data['postType'] ) ) {
				$registry->add_errors(
					array(
						array(
							'code'    => 'non_hierarchical_collection_path',
							'message' => 'Nested collection paths cannot be represented with post_parent on a non-hierarchical post type.',
							'context' => array(
								'collection' => $page_data['collection'] ?? null,
								'name'       => $page_data['name'] ?? null,
								'path'       => $page_data['path'] ?? null,
								'postType'   => $page_data['postType'] ?? null,
							),
						),
					)
				);
			}

			$post_id = $sync->sync( $page_data );

			if ( is_int( $post_id ) && $post_id > 0 ) {
				$registry->set_synced_post( $page_data['source_path'], $post_id );
				$registry->update_page_data( $name, 'post_id', $post_id );
				$registry->update_page_data( $name, 'post_parent', (int) get_post_field( 'post_parent', $post_id ) );
				$registry->update_page_data( $name, 'permalink', get_permalink( $post_id ) );

				$collection = $page_data['collection'] ?? null;

				if ( $collection ) {
					$active_sources[ $collection ][]                     = $page_data['source_path'];
					$post_types[ $collection ][ $page_data['postType'] ] = true;
				}
			}
		}

		foreach ( $active_sources as $collection => $sources ) {
			$sync->mark_stale_missing( $sources, $collection, array_keys( $post_types[ $collection ] ?? array() ) );
		}

		if ( ! self::$hooks_registered ) {
			self::register_template_for_hooks();
			self::register_template_lock_hooks();
			self::register_block_editing_mode_hooks();
			self::register_layout_hooks();

			self::$hooks_registered = true;
		}

		self::$initialized = true;

		/**
		 * Fires after pages have been synced.
		 *
		 * @param Page_Registry $registry The page registry instance.
		 */
		do_action( 'blockstudio/pages/synced', $registry );
	}

	/**
	 * Register custom post types declared by page collection manifests.
	 *
	 * @return void
	 */
	public static function register_collection_post_types(): void {
		$paths = self::get_paths();

		/** This filter is documented in init(). */
		$paths = apply_filters( 'blockstudio/pages/paths', $paths );

		foreach ( $paths as $path ) {
			if ( ! is_dir( $path ) ) {
				continue;
			}

			foreach ( Page_Discovery::discover_manifests( $path ) as $collection ) {
				self::register_collection_post_type( $collection );
			}
		}
	}

	/**
	 * Register one collection post type when needed.
	 *
	 * @param array $collection Collection data.
	 *
	 * @return void
	 */
	private static function register_collection_post_type( array $collection ): void {
		$post_type = $collection['postType'] ?? 'page';

		if ( 'page' === $post_type || post_type_exists( $post_type ) ) {
			return;
		}

		$args = wp_parse_args(
			$collection['postTypeArgs'] ?? array(),
			array(
				'label'        => $collection['title'] ?? Page_Discovery::title_from_value( $collection['slug'] ?? $post_type ),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'page-attributes', 'thumbnail', 'excerpt', 'revisions' ),
				'rewrite'      => array(
					'slug' => $collection['slug'] ?? $post_type,
				),
			)
		);

		/**
		 * Filter post type args for a page collection.
		 *
		 * @param array $args       Post type args.
		 * @param array $collection Collection data.
		 */
		$args = apply_filters( 'blockstudio/pages/collection_post_type_args', $args, $collection );

		register_post_type( $post_type, is_array( $args ) ? $args : array() );
	}

	/**
	 * Determine whether pages should initialize in the current request.
	 *
	 * Normal automatic initialization stays limited to admin and WP-CLI.
	 * Explicit force initialization is allowed for trusted callers that need to
	 * sync pages from controlled frontend contexts, such as local dev tooling.
	 *
	 * @param array     $args Optional arguments.
	 * @param bool|null $is_admin_request Optional admin context override for tests.
	 * @param bool|null $is_cli_request Optional WP-CLI context override for tests.
	 *
	 * @return bool Whether initialization is allowed.
	 */
	private static function can_init_in_current_context( array $args = array(), ?bool $is_admin_request = null, ?bool $is_cli_request = null ): bool {
		if ( ! empty( $args['force'] ) ) {
			return true;
		}

		$is_admin_request ??= is_admin();
		$is_cli_request   ??= defined( 'WP_CLI' ) && WP_CLI;

		return $is_admin_request || $is_cli_request;
	}

	/**
	 * Get default pages paths.
	 *
	 * @return array<string> Array of directory paths.
	 */
	public static function get_paths(): array {
		$paths = array();

		$theme_path = get_template_directory() . '/pages';

		if ( is_dir( $theme_path ) ) {
			$paths[] = $theme_path;
		}

		if ( is_child_theme() ) {
			$child_path = get_stylesheet_directory() . '/pages';

			if ( is_dir( $child_path ) ) {
				$paths[] = $child_path;
			}
		}

		return $paths;
	}

	/**
	 * Register hooks for template-for functionality.
	 *
	 * @return void
	 */
	private static function register_template_for_hooks(): void {
		$registry = Page_Registry::instance();

		$all_template_for = $registry->get_all_template_for();

		if ( empty( $all_template_for ) ) {
			return;
		}

		$parser = Html_Parser::from_settings();

		// Apply to already-registered post types (since Pages::init runs late).
		foreach ( $all_template_for as $post_type => $template_page ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( ! $post_type_object ) {
				continue;
			}

			$template = self::build_post_type_template( $parser, $template_page );

			if ( ! $template ) {
				continue;
			}

			$post_type_object->template      = $template;
			$post_type_object->template_lock = $template_page['templateLock'];
		}

		// Also hook for any post types registered after this point.
		add_filter(
			'register_post_type_args',
			function ( array $args, string $post_type ) use ( $registry, $parser ): array {
				$template_page = $registry->get_template_for( $post_type );

				if ( ! $template_page ) {
					return $args;
				}

				$template = self::build_post_type_template( $parser, $template_page );

				if ( ! $template ) {
					return $args;
				}

				$args['template']      = $template;
				$args['template_lock'] = $template_page['templateLock'];

				return $args;
			},
			10,
			2
		);
	}

	/**
	 * Build a post type template array from a page's template file.
	 *
	 * Parses the HTML template and converts parsed blocks to the
	 * WordPress post type template format: [blockName, attrs, innerBlocks].
	 *
	 * @param Html_Parser $parser        The HTML parser instance.
	 * @param array       $template_page The template page data.
	 *
	 * @return array|null The template array or null on failure.
	 */
	private static function build_post_type_template( Html_Parser $parser, array $template_page ): ?array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local template file.
		$template_content = file_get_contents( $template_page['template_path'] );

		if ( false === $template_content ) {
			return null;
		}

		if ( 'markdown' === ( $template_page['contentType'] ?? '' ) ) {
			$parts            = Page_Markdown::split_frontmatter( $template_content );
			$template_content = Page_Markdown::to_html( $parts['body'] );
		}

		$blocks = $parser->parse_to_array( $template_content );

		return self::blocks_to_template( $blocks );
	}

	/**
	 * Convert parsed blocks to WordPress post type template format.
	 *
	 * WordPress expects: [ [blockName, attrs, innerBlocks], ... ]
	 * parse_to_array returns: [ ['blockName' => ..., 'attrs' => ..., ...], ... ]
	 *
	 * Blocks like core/heading and core/paragraph store their text in innerHTML,
	 * but the template format needs it in attrs['content'].
	 *
	 * @param array $blocks Parsed blocks from Html_Parser.
	 *
	 * @return array Template-format blocks.
	 */
	private static function blocks_to_template( array $blocks ): array {
		$template = array();

		foreach ( $blocks as $block ) {
			$attrs = $block['attrs'];
			$inner = ! empty( $block['innerBlocks'] )
				? self::blocks_to_template( $block['innerBlocks'] )
				: array();

			// WordPress template format needs text in attrs['content'], not innerHTML.
			if ( ! isset( $attrs['content'] ) && ! empty( $block['innerHTML'] ) ) {
				$content = self::extract_block_content( $block['innerHTML'] );

				if ( '' !== $content ) {
					$attrs['content'] = $content;
				}
			}

			$template[] = array( $block['blockName'], $attrs, $inner );
		}

		return $template;
	}

	/**
	 * Extract inner text content from block innerHTML markup.
	 *
	 * Strips the outermost HTML tag wrapper to get the rich-text content.
	 * E.g. '<h1 class="wp-block-heading">Title</h1>' → 'Title'
	 *
	 * @param string $inner_html The block innerHTML.
	 *
	 * @return string The extracted content.
	 */
	private static function extract_block_content( string $inner_html ): string {
		$inner_html = trim( $inner_html );

		if ( preg_match( '/^<[^>]+>(.*)<\/[^>]+>$/s', $inner_html, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Register hooks for template lock on individual posts.
	 *
	 * @return void
	 */
	private static function register_template_lock_hooks(): void {
		add_filter(
			'block_editor_settings_all',
			function ( array $settings, \WP_Block_Editor_Context $context ): array {
				if ( empty( $context->post ) ) {
					return $settings;
				}

				$template_lock = get_post_meta( $context->post->ID, '_blockstudio_template_lock', true );

				if ( empty( $template_lock ) ) {
					return $settings;
				}

				$settings['templateLock']  = $template_lock;
				$settings['canLockBlocks'] = false;

				return $settings;
			},
			10,
			2
		);
	}

	/**
	 * Register hooks for block editing mode support.
	 *
	 * @return void
	 */
	private static function register_block_editing_mode_hooks(): void {
		add_filter(
			'block_editor_settings_all',
			function ( array $settings, \WP_Block_Editor_Context $context ): array {
				if ( empty( $context->post ) ) {
					return $settings;
				}

				$block_editing_mode = get_post_meta( $context->post->ID, '_blockstudio_block_editing_mode', true );

				if ( ! empty( $block_editing_mode ) ) {
					$settings['blockstudioBlockEditingMode'] = $block_editing_mode;
				}

				return $settings;
			},
			10,
			2
		);

		add_action(
			'enqueue_block_editor_assets',
			function (): void {
				$asset_file = BLOCKSTUDIO_DIR . '/includes/admin/assets/pages/index.asset.php';

				if ( ! file_exists( $asset_file ) ) {
					return;
				}

				$asset = include $asset_file;

				wp_enqueue_script(
					'blockstudio-pages',
					BLOCKSTUDIO_URL . 'includes/admin/assets/pages/index.js',
					$asset['dependencies'],
					$asset['version'],
					true
				);
			}
		);
	}

	/**
	 * Register frontend layout rendering.
	 *
	 * @return void
	 */
	private static function register_layout_hooks(): void {
		add_filter( 'the_content', array( __CLASS__, 'render_layout_content' ), 20 );
	}

	/**
	 * Render collection layout.php around frontend page content.
	 *
	 * @param string $content Original post content.
	 *
	 * @return string Content.
	 */
	public static function render_layout_content( string $content ): string {
		if ( is_admin() || self::$rendering_layout || ! is_singular() ) {
			return $content;
		}

		$post_id = (int) get_the_ID();

		if ( $post_id <= 0 ) {
			return $content;
		}

		$page = self::page_for_post_id( $post_id );

		if ( ! $page ) {
			return $content;
		}

		$layout_path = (string) ( $page['layout_path'] ?? get_post_meta( $post_id, '_blockstudio_page_layout', true ) );

		if ( '' === $layout_path || ! file_exists( $layout_path ) ) {
			return $content;
		}

		self::$rendering_layout     = true;
		self::$current_page         = $page;
		self::$current_page_content = $content;

		ob_start();
		include $layout_path;
		$layout_content = ob_get_clean();

		self::$current_page         = null;
		self::$current_page_content = '';
		self::$rendering_layout     = false;

		return false === $layout_content ? $content : $layout_content;
	}

	/**
	 * Get the content currently being wrapped by a page layout.
	 *
	 * @return string Page content.
	 */
	public static function page_content(): string {
		return self::$current_page_content;
	}

	/**
	 * Get current Blockstudio page data.
	 *
	 * @return array|null Page data.
	 */
	public static function current_page(): ?array {
		if ( null !== self::$current_page ) {
			return self::$current_page;
		}

		$post_id = (int) get_queried_object_id();

		return $post_id > 0 ? self::page_for_post_id( $post_id ) : null;
	}

	/**
	 * Get Blockstudio page data by post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array|null Page data.
	 */
	public static function page_for_post_id( int $post_id ): ?array {
		return Page_Registry::instance()->get_page_by_post_id( $post_id );
	}

	/**
	 * Get all registered pages.
	 *
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<string, array> The pages.
	 */
	public static function pages( ?string $collection = null ): array {
		$registry = Page_Registry::instance();

		return null === $collection ? $registry->get_pages() : $registry->in_collection( $collection );
	}

	/**
	 * Get pages in a collection.
	 *
	 * @param string $collection Collection slug.
	 *
	 * @return array<string, array> Pages.
	 */
	public static function in_collection( string $collection ): array {
		return Page_Registry::instance()->in_collection( $collection );
	}

	/**
	 * Get a nested page tree.
	 *
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<int, array> Page tree.
	 */
	public static function tree( ?string $collection = null ): array {
		return Page_Registry::instance()->tree( $collection );
	}

	/**
	 * Get direct child pages.
	 *
	 * @param string      $name       Page name or key.
	 * @param string|null $collection Optional collection slug.
	 *
	 * @return array<string, array> Child pages.
	 */
	public static function children( string $name, ?string $collection = null ): array {
		return Page_Registry::instance()->children( $name, $collection );
	}

	/**
	 * Get collection data.
	 *
	 * @param string $collection Collection slug.
	 *
	 * @return array|null Collection data.
	 */
	public static function collection( string $collection ): ?array {
		return Page_Registry::instance()->get_collection( $collection );
	}

	/**
	 * Get a page by name.
	 *
	 * @param string $name The page name.
	 *
	 * @return array|null The page data or null.
	 */
	public static function get_page( string $name ): ?array {
		return Page_Registry::instance()->get_page( $name );
	}

	/**
	 * Get synced post ID for a page.
	 *
	 * @param string $name The page name.
	 *
	 * @return int|null The post ID or null.
	 */
	public static function get_post_id( string $name ): ?int {
		$page = Page_Registry::instance()->get_page( $name );

		return $page['post_id'] ?? null;
	}

	/**
	 * Force sync all pages.
	 *
	 * @return array<string, int|WP_Error> Results indexed by page name.
	 */
	public static function force_sync_all(): array {
		$results  = array();
		$registry = Page_Registry::instance();
		$sync     = new Page_Sync();

		foreach ( $registry->get_pages() as $name => $page_data ) {
			$results[ $name ] = $sync->force_sync( $page_data );
		}

		return $results;
	}

	/**
	 * Force sync a single page.
	 *
	 * @param string $name The page name.
	 *
	 * @return int|WP_Error|null The post ID, WP_Error, or null if page not found.
	 */
	public static function force_sync( string $name ): int|\WP_Error|null {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page ) {
			return null;
		}

		$sync = new Page_Sync();

		return $sync->force_sync( $page );
	}

	/**
	 * Lock a page to prevent automatic updates.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool Whether the page was locked.
	 */
	public static function lock( string $name ): bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return false;
		}

		$sync = new Page_Sync();
		$sync->lock_post( $page['post_id'] );

		return true;
	}

	/**
	 * Unlock a page to allow automatic updates.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool Whether the page was unlocked.
	 */
	public static function unlock( string $name ): bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return false;
		}

		$sync = new Page_Sync();
		$sync->unlock_post( $page['post_id'] );

		return true;
	}

	/**
	 * Check if a page is locked.
	 *
	 * @param string $name The page name.
	 *
	 * @return bool|null Whether the page is locked, or null if page not found.
	 */
	public static function is_locked( string $name ): ?bool {
		$page = Page_Registry::instance()->get_page( $name );

		if ( ! $page || empty( $page['post_id'] ) ) {
			return null;
		}

		return (bool) get_post_meta( $page['post_id'], '_blockstudio_page_locked', true );
	}

	/**
	 * Get registered paths.
	 *
	 * @return array<string> The paths.
	 */
	public static function get_registered_paths(): array {
		return Page_Registry::instance()->get_paths();
	}

	/**
	 * Reset the pages system (mainly for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		Page_Registry::instance()->reset();
		self::$initialized          = false;
		self::$current_page         = null;
		self::$current_page_content = '';
		self::$rendering_layout     = false;
	}
}
