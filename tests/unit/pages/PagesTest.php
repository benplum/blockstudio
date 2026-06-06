<?php

use Blockstudio\Page_Discovery;
use Blockstudio\Page_Registry;
use Blockstudio\Page_Sync;
use Blockstudio\Pages;
use PHPUnit\Framework\TestCase;

class PagesTest extends TestCase {

	private string $pages_path;

	protected function setUp(): void {
		$this->pages_path = get_template_directory() . '/pages';

		Pages::reset();

		$discovery = new Page_Discovery();
		$registry  = Page_Registry::instance();
		$sync      = new Page_Sync();

		Pages::register_collection_post_types();

		$registry->add_path( $this->pages_path );

		$pages = $discovery->discover( $this->pages_path );

		foreach ( $discovery->get_collections() as $collection => $collection_data ) {
			$registry->register_collection( $collection, $collection_data );
		}

		$registry->add_errors( $discovery->get_errors() );

		foreach ( $pages as $name => $page_data ) {
			$registry->register( $name, $page_data );

			$post_id = $sync->sync( $page_data );

			if ( is_int( $post_id ) && $post_id > 0 ) {
				$registry->set_synced_post( $page_data['source_path'], $post_id );
				$registry->update_page_data( $name, 'post_id', $post_id );
				$registry->update_page_data( $name, 'post_parent', (int) get_post_field( 'post_parent', $post_id ) );
			}
		}
	}

	protected function tearDown(): void {
		Pages::reset();
	}

	// pages()

	public function test_pages_returns_array(): void {
		$pages = Pages::pages();
		$this->assertIsArray( $pages );
	}

	public function test_pages_is_not_empty(): void {
		$pages = Pages::pages();
		$this->assertNotEmpty( $pages );
	}

	public function test_pages_contains_test_page(): void {
		$pages = Pages::pages();
		$this->assertArrayHasKey( 'blockstudio-e2e-test', $pages );
	}

	public function test_pages_contains_sync_test_page(): void {
		$pages = Pages::pages();
		$this->assertArrayHasKey( 'blockstudio-sync-test', $pages );
	}

	public function test_pages_contains_standalone_markdown_page(): void {
		$pages = Pages::pages();
		$this->assertArrayHasKey( 'blockstudio-markdown-test', $pages );
	}

	// get_page()

	public function test_get_page_returns_array_for_known_page(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertIsArray( $page );
	}

	public function test_get_page_has_expected_title(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'Blockstudio E2E Test Page', $page['title'] );
	}

	public function test_get_page_has_expected_slug(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'blockstudio-e2e-test', $page['slug'] );
	}

	public function test_get_page_has_template_path(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertArrayHasKey( 'template_path', $page );
		$this->assertStringEndsWith( '/index.php', $page['template_path'] );
	}

	public function test_get_page_has_post_type(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'page', $page['postType'] );
	}

	public function test_get_page_has_template_lock(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$this->assertSame( 'all', $page['templateLock'] );
	}

	public function test_get_markdown_page_has_markdown_content_type(): void {
		$page = Pages::get_page( 'blockstudio-markdown-test' );
		$this->assertSame( 'markdown', $page['contentType'] );
		$this->assertTrue( $page['is_markdown'] );
	}

	public function test_get_page_returns_null_for_unknown(): void {
		$page = Pages::get_page( 'nonexistent-page' );
		$this->assertNull( $page );
	}

	// get_post_id()

	public function test_get_post_id_returns_int_for_synced_page(): void {
		$post_id = Pages::get_post_id( 'blockstudio-e2e-test' );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_get_post_id_returns_null_for_unknown(): void {
		$post_id = Pages::get_post_id( 'nonexistent-page' );
		$this->assertNull( $post_id );
	}

	public function test_get_post_id_corresponds_to_real_post(): void {
		$post_id = Pages::get_post_id( 'blockstudio-e2e-test' );
		$post    = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'page', $post->post_type );
	}

	// is_locked()

	public function test_is_locked_returns_bool_for_synced_page(): void {
		$locked = Pages::is_locked( 'blockstudio-e2e-test' );
		$this->assertIsBool( $locked );
	}

	public function test_is_locked_returns_false_by_default(): void {
		$locked = Pages::is_locked( 'blockstudio-e2e-test' );
		$this->assertFalse( $locked );
	}

	public function test_is_locked_returns_null_for_unknown(): void {
		$locked = Pages::is_locked( 'nonexistent-page' );
		$this->assertNull( $locked );
	}

	public function test_lock_then_is_locked_returns_true(): void {
		Pages::lock( 'blockstudio-e2e-test' );
		$this->assertTrue( Pages::is_locked( 'blockstudio-e2e-test' ) );

		Pages::unlock( 'blockstudio-e2e-test' );
	}

	public function test_unlock_after_lock_returns_false(): void {
		Pages::lock( 'blockstudio-e2e-test' );
		Pages::unlock( 'blockstudio-e2e-test' );
		$this->assertFalse( Pages::is_locked( 'blockstudio-e2e-test' ) );
	}

	// force_sync()

	public function test_force_sync_returns_int_for_known_page(): void {
		$result = Pages::force_sync( 'blockstudio-e2e-test' );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	public function test_force_sync_returns_null_for_unknown(): void {
		$result = Pages::force_sync( 'nonexistent-page' );
		$this->assertNull( $result );
	}

	public function test_force_sync_preserves_post_id(): void {
		$original = Pages::get_post_id( 'blockstudio-e2e-test' );
		$synced   = Pages::force_sync( 'blockstudio-e2e-test' );
		$this->assertSame( $original, $synced );
	}

	public function test_force_sync_skips_unrelated_post_with_same_slug(): void {
		$manual_post_id = wp_insert_post(
			array(
				'post_title'   => 'Manual Collision',
				'post_name'    => 'blockstudio-slug-collision-test',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Manual content should stay untouched.',
			)
		);

		$this->assertIsInt( $manual_post_id );
		$this->assertGreaterThan( 0, $manual_post_id );

		$page_data                = Pages::get_page( 'blockstudio-e2e-test' );
		$page_data['name']        = 'blockstudio-slug-collision-test';
		$page_data['title']       = 'Blockstudio Slug Collision Test';
		$page_data['slug']        = 'blockstudio-slug-collision-test';
		$page_data['source_path'] = 'pages/blockstudio-slug-collision-test';
		$page_data['postId']      = null;

		try {
			$result = ( new Page_Sync() )->force_sync( $page_data );

			$this->assertSame( 0, $result );

			$manual_post = get_post( $manual_post_id );
			$this->assertInstanceOf( WP_Post::class, $manual_post );
			$this->assertSame( 'Manual Collision', $manual_post->post_title );
			$this->assertSame( 'Manual content should stay untouched.', $manual_post->post_content );

			$posts = get_posts(
				array(
					'meta_key'       => '_blockstudio_page_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $page_data['source_path'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'post_type'      => 'page',
					'posts_per_page' => 1,
					'post_status'    => 'any',
				)
			);

			$this->assertEmpty( $posts );
		} finally {
			wp_delete_post( $manual_post_id, true );
		}
	}

	public function test_force_sync_rebinds_existing_page_by_name_when_source_path_changes(): void {
		$page_data        = Pages::get_page( 'blockstudio-e2e-test' );
		$original_post_id = Pages::get_post_id( 'blockstudio-e2e-test' );
		$original_source  = get_post_meta( $original_post_id, '_blockstudio_page_source', true );

		$page_data['source_path'] = 'pages/moved/blockstudio-e2e-test';

		try {
			$result = ( new Page_Sync() )->force_sync( $page_data );

			$this->assertSame( $original_post_id, $result );
			$this->assertSame(
				$page_data['source_path'],
				get_post_meta( $original_post_id, '_blockstudio_page_source', true )
			);
		} finally {
			update_post_meta( $original_post_id, '_blockstudio_page_source', $original_source );
		}
	}

	// Discovery

	public function test_discovery_finds_multiple_pages(): void {
		$pages = Pages::pages();
		$this->assertGreaterThanOrEqual( 3, count( $pages ) );
	}

	public function test_page_data_has_required_keys(): void {
		$page = Pages::get_page( 'blockstudio-e2e-test' );
		$expected_keys = array( 'name', 'title', 'slug', 'postType', 'templateLock', 'template_path', 'json_path', 'directory', 'source_path' );

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $page, "Missing key: {$key}" );
		}
	}

	public function test_collection_manifest_is_registered(): void {
		$collection = Pages::collection( 'docs' );

		$this->assertIsArray( $collection );
		$this->assertSame( 'Documentation', $collection['title'] );
		$this->assertSame( 'bs_docs', $collection['postType'] );
		$this->assertSame( 'primary', $collection['meta']['navigation'] );
	}

	public function test_collection_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'bs_docs' ) );
		$this->assertTrue( is_post_type_hierarchical( 'bs_docs' ) );
	}

	public function test_collection_pages_are_namespaced(): void {
		$pages = Pages::in_collection( 'docs' );

		$this->assertArrayHasKey( 'docs:docs-home', $pages );
		$this->assertArrayHasKey( 'docs:docs-reference', $pages );
		$this->assertArrayHasKey( 'docs:docs-loader-api', $pages );
	}

	public function test_collection_generates_missing_container_pages(): void {
		$guide = Pages::get_page( 'docs-guide' );
		$api   = Pages::get_page( 'docs-api' );

		$this->assertIsArray( $guide );
		$this->assertTrue( $guide['generated'] );
		$this->assertSame( 'guide', $guide['path'] );
		$this->assertIsArray( $api );
		$this->assertTrue( $api['generated'] );
		$this->assertSame( 'api', $api['path'] );
	}

	public function test_collection_children_use_logical_paths(): void {
		$children = Pages::children( 'docs-home', 'docs' );
		$names    = array_column( $children, 'name' );

		$this->assertContains( 'docs-guide', $names );
		$this->assertContains( 'docs-reference', $names );
		$this->assertContains( 'docs-api', $names );
	}

	public function test_collection_tree_nests_generated_containers(): void {
		$tree = Pages::tree( 'docs' );

		$this->assertCount( 1, $tree );
		$this->assertSame( 'docs-home', $tree[0]['name'] );

		$guide = array_values(
			array_filter(
				$tree[0]['children'],
				static fn ( array $child ): bool => 'docs-guide' === $child['name']
			)
		);

		$this->assertCount( 1, $guide );
		$this->assertSame( 'docs-install', $guide[0]['children'][0]['name'] );
	}

	public function test_markdown_content_syncs_to_heading_blocks_with_anchor(): void {
		$post_id = Pages::get_post_id( 'docs-home' );
		$post    = get_post( $post_id );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertStringContainsString( 'wp:heading', $post->post_content );
		$this->assertStringContainsString( 'documentation-home', $post->post_content );
	}

	public function test_page_json_can_use_index_markdown_content(): void {
		$post_id = Pages::get_post_id( 'docs-reference' );
		$post    = get_post( $post_id );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertStringContainsString( 'Reference', $post->post_content );
	}

	public function test_loader_markdown_page_syncs_and_is_sanitized_generated_content(): void {
		$page    = Pages::get_page( 'docs-loader-api' );
		$post_id = Pages::get_post_id( 'docs-loader-api' );
		$post    = get_post( $post_id );

		$this->assertIsArray( $page );
		$this->assertTrue( $page['generated'] );
		$this->assertSame( 'markdown', $page['contentType'] );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertStringContainsString( 'Loader API', $post->post_content );
	}

	public function test_synced_collection_pages_store_identity_meta(): void {
		$post_id = Pages::get_post_id( 'docs-install' );

		$this->assertSame( 'docs:docs-install', get_post_meta( $post_id, '_blockstudio_page_key', true ) );
		$this->assertSame( 'docs', get_post_meta( $post_id, '_blockstudio_page_collection', true ) );
		$this->assertSame( 'guide/install', get_post_meta( $post_id, '_blockstudio_page_path', true ) );
		$this->assertNotEmpty( get_post_meta( $post_id, '_blockstudio_page_fingerprint', true ) );
	}

	public function test_collection_children_have_wordpress_parent_ids(): void {
		$parent_id = Pages::get_post_id( 'docs-guide' );
		$child_id  = Pages::get_post_id( 'docs-install' );

		$this->assertGreaterThan( 0, $parent_id );
		$this->assertSame( $parent_id, (int) get_post_field( 'post_parent', $child_id ) );
	}

	public function test_layout_file_is_not_discovered_as_page_source(): void {
		$sources = array_column( Pages::in_collection( 'docs' ), 'source_path' );

		foreach ( $sources as $source ) {
			$this->assertStringNotContainsString( 'layout.php', $source );
		}
	}

	public function test_global_page_helpers_proxy_pages_api(): void {
		$this->assertSame( Pages::in_collection( 'docs' ), blockstudio_pages( 'docs' ) );
		$this->assertSame( Pages::collection( 'docs' ), blockstudio_page_collection( 'docs' ) );
		$this->assertSame( Pages::children( 'docs-home', 'docs' ), blockstudio_page_children( 'docs-home', 'docs' ) );
	}

	// get_registered_paths()

	public function test_get_registered_paths_returns_array(): void {
		$paths = Pages::get_registered_paths();
		$this->assertIsArray( $paths );
		$this->assertNotEmpty( $paths );
	}

	public function test_get_registered_paths_contains_theme_pages_dir(): void {
		$paths = Pages::get_registered_paths();
		$this->assertContains( $this->pages_path, $paths );
	}

	// reset()

	public function test_reset_clears_pages(): void {
		$this->assertNotEmpty( Pages::pages() );

		Pages::reset();

		$this->assertEmpty( Pages::pages() );
	}

	public function test_init_context_blocks_frontend_without_force(): void {
		$method = new ReflectionMethod( Pages::class, 'can_init_in_current_context' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( null, array(), false, false ) );
	}

	public function test_init_context_allows_frontend_with_force(): void {
		$method = new ReflectionMethod( Pages::class, 'can_init_in_current_context' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, array( 'force' => true ), false, false ) );
	}

	public function test_init_context_allows_admin_and_cli_without_force(): void {
		$method = new ReflectionMethod( Pages::class, 'can_init_in_current_context' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null, array(), true, false ) );
		$this->assertTrue( $method->invoke( null, array(), false, true ) );
	}
}
