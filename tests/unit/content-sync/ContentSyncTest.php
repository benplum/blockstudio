<?php
/**
 * Tests for Content Sync.
 *
 * @package Blockstudio
 */

use Blockstudio\Content_Sync;
use PHPUnit\Framework\TestCase;

/**
 * Content Sync tests.
 */
class ContentSyncTest extends TestCase {

	/**
	 * Test post type.
	 *
	 * @var string
	 */
	private string $post_type = 'bs_content_test';

	/**
	 * Test content path.
	 *
	 * @var string
	 */
	private string $content_path = 'content-sync-test';

	/**
	 * Created post IDs.
	 *
	 * @var array<int>
	 */
	private array $post_ids = array();

	/**
	 * Set up test state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! post_type_exists( $this->post_type ) ) {
			register_post_type(
				$this->post_type,
				array(
					'public'       => true,
					'hierarchical' => true,
					'label'        => 'Content Sync Test',
				)
			);
		}

		$this->delete_test_posts();
		$this->remove_content_dir();
	}

	/**
	 * Clean up test state.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$this->post_ids = array();
		$this->delete_test_posts();
		$this->remove_content_dir();

		parent::tearDown();
	}

	/**
	 * Pull writes post JSON and body files with portable references.
	 *
	 * @return void
	 */
	public function test_pull_writes_post_files_with_declared_references(): void {
		$attachment_id = $this->insert_attachment( 'content-sync-image.jpg' );
		$post_id       = $this->insert_post(
			array(
				'post_title'   => 'Sync Source',
				'post_name'    => 'sync-source',
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		update_post_meta( $post_id, '_my_subtitle', 'Portable subtitle' );
		update_post_meta( $post_id, '_thumbnail_id', $attachment_id );

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->pull();

		$this->assertContains( 'written', wp_list_pluck( $rows, 'action' ) );

		$uid            = get_post_meta( $post_id, Content_Sync::META_UID, true );
		$attachment_uid = get_post_meta( $attachment_id, Content_Sync::META_UID, true );

		$this->assertNotEmpty( $uid );
		$this->assertNotEmpty( $attachment_uid );

		$json_file = $this->find_post_json_file();
		$this->assertFileExists( $json_file );
		$this->assertFileExists( preg_replace( '/\.json$/', '.html', $json_file ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $json_file ), true );
		$this->assertSame( $uid, $data['uid'] );
		$this->assertSame( $this->post_type, $data['type'] );
		$this->assertSame( 'Portable subtitle', $data['meta']['_my_subtitle'] );
		$this->assertSame( $attachment_uid, $data['meta']['_thumbnail_id'] );
		$this->assertSame( 'scalar', $data['metaEncoding']['_thumbnail_id'] );
	}

	/**
	 * Pull dry run does not write UIDs or files.
	 *
	 * @return void
	 */
	public function test_pull_dry_run_does_not_write_database_or_files(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Dry Source',
				'post_name'  => 'dry-source',
			)
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->pull( array( 'dry-run' => true ) );

		$this->assertSame( array( 'would-write' ), array_values( array_unique( wp_list_pluck( $rows, 'action' ) ) ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, Content_Sync::META_UID, true ) );
		$this->assertFileDoesNotExist( $this->content_root() );
	}

	/**
	 * Pull does not rewrite unchanged files.
	 *
	 * @return void
	 */
	public function test_pull_does_not_rewrite_unchanged_files(): void {
		$this->insert_post(
			array(
				'post_title'   => 'Stable Source',
				'post_name'    => 'stable-source',
				'post_content' => '<p>Stable body</p>',
			)
		);

		$sync = new Content_Sync( $this->config() );
		$sync->pull();

		$json_file = $this->find_post_json_file();
		$body_file = preg_replace( '/\.json$/', '.html', $json_file );
		$this->assertFileExists( $body_file );

		$old_time = time() - 3600;
		touch( $json_file, $old_time );
		touch( $body_file, $old_time );
		clearstatcache( true, $json_file );
		clearstatcache( true, $body_file );

		$rows = $sync->pull();

		clearstatcache( true, $json_file );
		clearstatcache( true, $body_file );

		$this->assertSame( array( 'unchanged' ), wp_list_pluck( $rows, 'action' ) );
		$this->assertSame( $old_time, filemtime( $json_file ) );
		$this->assertSame( $old_time, filemtime( $body_file ) );
	}

	/**
	 * Push creates posts and rewrites declared post references.
	 *
	 * @return void
	 */
	public function test_push_creates_posts_and_rewrites_declared_references(): void {
		$parent_uid = wp_generate_uuid4();
		$child_uid  = wp_generate_uuid4();

		$this->write_post_file(
			'parent',
			array(
				'uid'          => $parent_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'parent',
				'title'        => 'Parent',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			'<p>Parent body</p>'
		);

		$this->write_post_file(
			'child',
			array(
				'uid'          => $child_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'child',
				'title'        => 'Child',
				'parent'       => $parent_uid,
				'menuOrder'    => 0,
				'meta'         => array(
					'_related_posts' => array( $parent_uid ),
					'_my_payload'    => array( 'id' => 123 ),
				),
				'metaEncoding' => array(
					'_related_posts' => 'json',
					'_my_payload'    => 'json',
				),
			),
			'<p>Child body</p>'
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$parent = $this->get_post_by_uid( $parent_uid );
		$child  = $this->get_post_by_uid( $child_uid );

		$this->assertInstanceOf( WP_Post::class, $parent );
		$this->assertInstanceOf( WP_Post::class, $child );
		$this->assertSame( $parent->ID, (int) $child->post_parent );
		$this->assertSame( array( $parent->ID ), json_decode( (string) get_post_meta( $child->ID, '_related_posts', true ), true ) );
		$this->assertSame( array( 'id' => 123 ), json_decode( (string) get_post_meta( $child->ID, '_my_payload', true ), true ) );
	}

	/**
	 * Push blocks unresolved declared attachment references.
	 *
	 * @return void
	 */
	public function test_push_blocks_unresolved_declared_attachment_reference(): void {
		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'needs-attachment',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'needs-attachment',
				'title'        => 'Needs Attachment',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_thumbnail_id' => wp_generate_uuid4(),
				),
				'metaEncoding' => array(
					'_thumbnail_id' => 'scalar',
				),
			),
			''
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $uid ) );
	}

	/**
	 * Page Sync managed posts are excluded by default.
	 *
	 * @return void
	 */
	public function test_pull_excludes_page_sync_managed_posts_by_default(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Page Sync Owned',
				'post_name'  => 'page-sync-owned',
			)
		);

		update_post_meta( $post_id, '_blockstudio_page_key', 'page-sync-owned' );

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->pull();

		$this->assertSame( array( 'skipped' ), wp_list_pluck( $rows, 'action' ) );
		$this->assertFileDoesNotExist( $this->content_root() );
	}

	/**
	 * Push reports slug conflicts before writing.
	 *
	 * @return void
	 */
	public function test_push_blocks_slug_conflict(): void {
		$this->insert_post(
			array(
				'post_title' => 'Existing',
				'post_name'  => 'same-slug',
			)
		);

		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'same-slug',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'same-slug',
				'title'        => 'Incoming',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			''
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'error', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( $this->get_post_by_uid( $uid ) );
	}

	/**
	 * Pull rewrites only declared structured reference paths.
	 *
	 * @return void
	 */
	public function test_pull_rewrites_only_declared_structured_reference_paths(): void {
		$attachment_id = $this->insert_attachment( 'structured-reference.jpg' );
		$post_id       = $this->insert_post(
			array(
				'post_title' => 'Structured Source',
				'post_name'  => 'structured-source',
			)
		);

		update_post_meta(
			$post_id,
			'_hero',
			wp_json_encode(
				array(
					'image' => array( 'id' => $attachment_id ),
					'copy'  => 'Hero',
				)
			)
		);
		update_post_meta(
			$post_id,
			'_my_payload',
			wp_json_encode(
				array(
					'image' => array( 'id' => 123 ),
				)
			)
		);

		$sync = new Content_Sync(
			$this->config(
				array(
					'meta' => array(
						'include'    => array( '_hero', '_my_payload' ),
						'references' => array(
							'_hero' => array(
								'kind' => 'attachment',
								'path' => 'image.id',
							),
						),
					),
				)
			)
		);
		$sync->pull();

		$attachment_uid = get_post_meta( $attachment_id, Content_Sync::META_UID, true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $this->find_post_json_file() ), true );

		$this->assertSame( $attachment_uid, $data['meta']['_hero']['image']['id'] );
		$this->assertSame( 123, $data['meta']['_my_payload']['image']['id'] );
		$this->assertSame( 'json', $data['metaEncoding']['_hero'] );
	}

	/**
	 * Push rewrites declared structured references back to local IDs.
	 *
	 * @return void
	 */
	public function test_push_rewrites_declared_structured_reference_paths(): void {
		$attachment_id  = $this->insert_attachment( 'structured-push.jpg' );
		$attachment_uid = wp_generate_uuid4();
		$post_uid       = wp_generate_uuid4();

		update_post_meta( $attachment_id, Content_Sync::META_UID, $attachment_uid );
		update_post_meta( $attachment_id, Content_Sync::META_SET, 'unit' );

		$this->write_post_file(
			'structured-push',
			array(
				'uid'          => $post_uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'structured-push',
				'title'        => 'Structured Push',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_hero' => array(
						'image' => array( 'id' => $attachment_uid ),
						'copy'  => 'Hero',
					),
				),
				'metaEncoding' => array(
					'_hero' => 'json',
				),
			),
			''
		);

		$sync = new Content_Sync(
			$this->config(
				array(
					'meta' => array(
						'include'    => array( '_hero' ),
						'references' => array(
							'_hero' => array(
								'kind' => 'attachment',
								'path' => 'image.id',
							),
						),
					),
				)
			)
		);
		$rows = $sync->push();

		$this->assertNotContains( 'error', wp_list_pluck( $rows, 'action' ) );

		$post = $this->get_post_by_uid( $post_uid );
		$this->assertInstanceOf( WP_Post::class, $post );

		$hero = json_decode( (string) get_post_meta( $post->ID, '_hero', true ), true );
		$this->assertSame( $attachment_id, $hero['image']['id'] );
	}

	/**
	 * Pull honors meta include and exclude patterns.
	 *
	 * @return void
	 */
	public function test_pull_honors_meta_include_and_exclude_patterns(): void {
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Meta Source',
				'post_name'  => 'meta-source',
			)
		);

		update_post_meta( $post_id, '_my_allowed', 'yes' );
		update_post_meta( $post_id, '_my_secret', 'no' );
		update_post_meta( $post_id, '_outside', 'no' );

		$sync = new Content_Sync(
			$this->config(
				array(
					'meta' => array(
						'include' => array( '_my_*' ),
						'exclude' => array( '_my_secret' ),
					),
				)
			)
		);
		$sync->pull();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local content sync fixture.
		$data = json_decode( (string) file_get_contents( $this->find_post_json_file() ), true );

		$this->assertSame( array( '_my_allowed' => 'yes' ), $data['meta'] );
	}

	/**
	 * Push skips locked content-owned posts.
	 *
	 * @return void
	 */
	public function test_push_skips_locked_posts(): void {
		$uid     = wp_generate_uuid4();
		$post_id = $this->insert_post(
			array(
				'post_title' => 'Locked Original',
				'post_name'  => 'locked-original',
			)
		);

		update_post_meta( $post_id, Content_Sync::META_UID, $uid );
		update_post_meta( $post_id, Content_Sync::META_SET, 'unit' );
		update_post_meta( $post_id, Content_Sync::META_LOCKED, '1' );

		$this->write_post_file(
			'locked-original',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'locked-original',
				'title'        => 'Locked Updated',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(),
				'metaEncoding' => array(),
			),
			'<p>Updated</p>'
		);

		$sync = new Content_Sync( $this->config() );
		$rows = $sync->push();

		$this->assertContains( 'locked', wp_list_pluck( $rows, 'action' ) );
		$this->assertSame( 'Locked Original', get_post( $post_id )->post_title );
	}

	/**
	 * Prune is scoped to the configured content set.
	 *
	 * @return void
	 */
	public function test_push_prune_deletes_only_current_content_set(): void {
		$owned_uid = wp_generate_uuid4();
		$other_uid = wp_generate_uuid4();

		$owned_id = $this->insert_post(
			array(
				'post_title' => 'Owned Orphan',
				'post_name'  => 'owned-orphan',
			)
		);
		$other_id = $this->insert_post(
			array(
				'post_title' => 'Other Set',
				'post_name'  => 'other-set',
			)
		);

		update_post_meta( $owned_id, Content_Sync::META_UID, $owned_uid );
		update_post_meta( $owned_id, Content_Sync::META_SET, 'unit' );
		update_post_meta( $other_id, Content_Sync::META_UID, $other_uid );
		update_post_meta( $other_id, Content_Sync::META_SET, 'other' );

		$filter = static fn() => 'delete';
		add_filter( 'blockstudio/content/orphan_action', $filter );

		try {
			$sync = new Content_Sync( $this->config() );
			$rows = $sync->push( array( 'prune' => true ) );
		} finally {
			remove_filter( 'blockstudio/content/orphan_action', $filter );
		}

		$this->assertContains( 'pruned-delete', wp_list_pluck( $rows, 'action' ) );
		$this->assertNull( get_post( $owned_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $other_id ) );
	}

	/**
	 * Status reports unchanged after a successful push.
	 *
	 * @return void
	 */
	public function test_status_reports_unchanged_after_push(): void {
		$uid = wp_generate_uuid4();

		$this->write_post_file(
			'status-source',
			array(
				'uid'          => $uid,
				'type'         => $this->post_type,
				'status'       => 'publish',
				'slug'         => 'status-source',
				'title'        => 'Status Source',
				'parent'       => null,
				'menuOrder'    => 0,
				'meta'         => array(
					'_my_subtitle' => 'Stable',
				),
				'metaEncoding' => array(
					'_my_subtitle' => 'scalar',
				),
			),
			'<p>Status body</p>'
		);

		$sync = new Content_Sync( $this->config() );
		$sync->push();
		$rows = $sync->status();

		$this->assertSame( array( 'unchanged' ), wp_list_pluck( $rows, 'action' ) );
	}

	/**
	 * Get test config.
	 *
	 * @param array $overrides Config overrides.
	 *
	 * @return array
	 */
	private function config( array $overrides = array() ): array {
		$config = array(
			'enabled'                => true,
			'id'                     => 'unit',
			'path'                   => $this->content_path,
			'includePageSyncManaged' => false,
			'authors'                => 'ignore',
			'postTypes'              => array( $this->post_type ),
			'meta'                   => array(
				'include'    => array( '_my_*', '_thumbnail_id', '_related_posts' ),
				'exclude'    => array( '_edit_lock', '_edit_last', '_wp_old_slug' ),
				'references' => array(
					'_thumbnail_id'  => array( 'kind' => 'attachment' ),
					'_related_posts' => array(
						'kind' => 'post',
						'path' => '*',
					),
				),
			),
			'taxonomies'             => array(),
			'media'                  => 'manifest',
		);

		return array_replace_recursive( $config, $overrides );
	}

	/**
	 * Insert a test post.
	 *
	 * @param array $args Post args.
	 *
	 * @return int
	 */
	private function insert_post( array $args ): int {
		$post_id = wp_insert_post(
			array_merge(
				array(
					'post_type'   => $this->post_type,
					'post_status' => 'publish',
				),
				$args
			),
			true
		);

		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Insert a test attachment.
	 *
	 * @param string $file File basename.
	 *
	 * @return int
	 */
	private function insert_attachment( string $file ): int {
		$post_id = wp_insert_attachment(
			array(
				'post_title'     => $file,
				'post_name'      => sanitize_title( $file ),
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'guid'           => 'https://example.test/' . $file,
			)
		);

		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Write a post content file pair.
	 *
	 * @param string $slug Slug.
	 * @param array  $data Data.
	 * @param string $body Body.
	 *
	 * @return string
	 */
	private function write_post_file( string $slug, array $data, string $body ): string {
		$dir  = $this->content_root() . '/posts/' . $this->post_type;
		$file = $dir . '/' . $slug . '.' . substr( (string) $data['uid'], 0, 8 ) . '.json';

		wp_mkdir_p( $dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local content sync fixture.
		file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

		if ( '' !== $body ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local content sync fixture.
			file_put_contents( preg_replace( '/\.json$/', '.html', $file ), $body );
		}

		return $file;
	}

	/**
	 * Get content root.
	 *
	 * @return string
	 */
	private function content_root(): string {
		return get_stylesheet_directory() . '/' . $this->content_path;
	}

	/**
	 * Find the generated post JSON file.
	 *
	 * @return string
	 */
	private function find_post_json_file(): string {
		$files = glob( $this->content_root() . '/posts/' . $this->post_type . '/*.json' );
		$this->assertNotEmpty( $files );

		return $files[0];
	}

	/**
	 * Find a post by content UID.
	 *
	 * @param string $uid UID.
	 *
	 * @return WP_Post|null
	 */
	private function get_post_by_uid( string $uid ): ?WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => Content_Sync::META_UID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $uid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $posts[0] ?? null;
	}

	/**
	 * Remove content directory.
	 *
	 * @return void
	 */
	private function remove_content_dir(): void {
		$root = $this->content_root();
		if ( ! is_dir( $root ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}

		rmdir( $root );
	}

	/**
	 * Delete posts from the test post type.
	 *
	 * @return void
	 */
	private function delete_test_posts(): void {
		$posts = get_posts(
			array(
				'post_type'      => $this->post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}
