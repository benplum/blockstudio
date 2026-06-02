<?php
/**
 * Build cache tests.
 *
 * @package Blockstudio
 */

use Blockstudio\Build_Cache;
use Blockstudio\Files;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the file-backed build cache.
 */
class BuildCacheTest extends TestCase {

	/**
	 * Cache files to clean up.
	 *
	 * @var array
	 */
	private array $cache_files = array();

	/**
	 * Temporary directories to clean up.
	 *
	 * @var array
	 */
	private array $temporary_directories = array();

	/**
	 * Clean up cache files and temporary directories.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->cache_files as $cache_file ) {
			if ( is_file( $cache_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing temporary cache files created by this test.
				unlink( $cache_file );
			}
		}

		foreach ( $this->temporary_directories as $temporary_directory ) {
			if ( is_dir( $temporary_directory ) ) {
				Files::delete_all_files( $temporary_directory );
			}
		}

		$this->cache_files           = array();
		$this->temporary_directories = array();
	}

	/**
	 * Track a cache file written by this test.
	 *
	 * @param string $scope Cache scope.
	 * @param string $key   Cache key.
	 *
	 * @return void
	 */
	private function track_cache_file( string $scope, string $key ): void {
		$this->cache_files[] = Build_Cache::get_cache_dir( $scope ) . '/' . sanitize_file_name( $key ) . '.php';
	}

	/**
	 * Create a temporary test directory.
	 *
	 * @return string Temporary directory path.
	 */
	private function create_temporary_directory(): string {
		$directory = BLOCKSTUDIO_DIR . '/.tmp-tests/blockstudio-cache-' . uniqid( '', true );
		wp_mkdir_p( $directory );
		$this->temporary_directories[] = $directory;

		return $directory;
	}

	/**
	 * Write a test file.
	 *
	 * @param string $path     File path.
	 * @param string $contents File contents.
	 *
	 * @return void
	 */
	private function write_file( string $path, string $contents ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing temporary test fixture files.
		file_put_contents( $path, $contents );
	}

	/**
	 * Cache directory uses the WordPress uploads directory.
	 *
	 * @return void
	 */
	public function test_cache_directory_uses_wordpress_uploads(): void {
		$uploads = wp_upload_dir();

		$this->assertStringStartsWith(
			rtrim( wp_normalize_path( $uploads['basedir'] ), '/' ),
			Build_Cache::get_cache_dir()
		);
		$this->assertStringEndsWith( '/blockstudio/cache', Build_Cache::get_cache_dir() );
	}

	/**
	 * Touch a test directory.
	 *
	 * @param string $path  Directory path.
	 * @param int    $mtime Modified time.
	 *
	 * @return void
	 */
	private function touch_directory( string $path, int $mtime ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Touching temporary directory to assert cache invalidation.
		touch( $path, $mtime );
	}

	/**
	 * Cache loads while watched files remain unchanged.
	 *
	 * @return void
	 */
	public function test_cache_loads_payload_when_watch_is_valid(): void {
		$directory = $this->create_temporary_directory();

		$watched_file = $directory . '/block.json';
		$this->write_file( $watched_file, '{"name":"blockstudio-test/cache"}' );

		$key     = 'valid-watch-' . uniqid();
		$payload = array(
			'value' => 'cached',
			'watch' => Build_Cache::create_watch_snapshot( array( $watched_file ) ),
		);

		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write( 'runtime', $key, $payload );

		$this->assertSame(
			'cached',
			Build_Cache::load( 'runtime', $key )['value'] ?? null
		);
	}

	/**
	 * Cache invalidates when a watched file changes.
	 *
	 * @return void
	 */
	public function test_cache_invalidates_when_watched_file_changes(): void {
		$directory = $this->create_temporary_directory();

		$watched_file = $directory . '/index.php';
		$this->write_file( $watched_file, '<?php echo "one";' );

		$key     = 'changed-file-' . uniqid();
		$payload = array(
			'value' => 'cached',
			'watch' => Build_Cache::create_watch_snapshot( array( $watched_file ) ),
		);

		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write( 'runtime', $key, $payload );
		$this->write_file( $watched_file, '<?php echo "changed file contents";' );

		$this->assertNull( Build_Cache::load( 'runtime', $key ) );
	}

	/**
	 * Cache invalidates when a watched directory changes.
	 *
	 * @return void
	 */
	public function test_cache_invalidates_when_watched_directory_changes(): void {
		$directory = $this->create_temporary_directory();

		$watched_directory = $directory . '/blocks';
		wp_mkdir_p( $watched_directory );

		$key     = 'changed-directory-' . uniqid();
		$payload = array(
			'value' => 'cached',
			'watch' => Build_Cache::create_watch_snapshot( array(), array( $watched_directory ) ),
		);

		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write( 'runtime', $key, $payload );
		$this->write_file( $watched_directory . '/new-file.php', '<?php echo "new";' );
		$this->touch_directory( $watched_directory, time() + 2 );

		$this->assertNull( Build_Cache::load( 'runtime', $key ) );
	}

	/**
	 * Runtime cache watches block files listed in the payload.
	 *
	 * @return void
	 */
	public function test_runtime_cache_watches_block_files_from_payload(): void {
		$directory = $this->create_temporary_directory();

		$block_directory = $directory . '/example';
		wp_mkdir_p( $block_directory );

		$block_json = $block_directory . '/block.json';
		$template   = $block_directory . '/index.php';

		$this->write_file( $block_json, '{"name":"blockstudio-test/cache-runtime","title":"Cache Runtime"}' );
		$this->write_file( $template, '<?php echo "one";' );

		$payload = array(
			'store'        => array(
				'blockstudio-test/cache-runtime' => array(
					'name'       => 'blockstudio-test/cache-runtime',
					'path'       => $block_json,
					'filesPaths' => array( $block_json, $template ),
				),
			),
			'registerable' => array(),
		);

		$key = Build_Cache::get_runtime_key( $directory, 'test-instance' );
		$this->track_cache_file( 'runtime', $key );
		Build_Cache::write_runtime( $directory, 'test-instance', $payload );

		$this->assertIsArray( Build_Cache::load_runtime( $directory, 'test-instance' ) );

		$this->write_file( $template, '<?php echo "changed runtime cache file";' );

		$this->assertNull( Build_Cache::load_runtime( $directory, 'test-instance' ) );
	}
}
