<?php
/**
 * Build cache class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * File-backed cache for expensive block discovery and editor asset assembly.
 *
 * Runtime cache files are PHP arrays instead of JSON so WordPress can load the
 * cached payload with one guarded include. Validation stays cheap by comparing
 * file mtimes/sizes and directory mtimes captured when the cache was written.
 *
 * @since 7.3.3
 */
final class Build_Cache {

	/**
	 * Cache format version.
	 *
	 * Bump this when the serialized payload shape changes.
	 *
	 * @var int
	 */
	private const VERSION = 3;

	/**
	 * Get the cache directory path.
	 *
	 * @param string $scope Cache scope.
	 *
	 * @return string Cache directory.
	 */
	public static function get_cache_dir( string $scope = '' ): string {
		$uploads = wp_upload_dir();
		$base    = $uploads['basedir'] . '/blockstudio/cache';
		$base    = rtrim( wp_normalize_path( $base ), '/' );

		if ( '' === $scope ) {
			return $base;
		}

		return $base . '/' . sanitize_key( $scope );
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @return bool Whether caching is enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) Settings::get( 'cache/enabled', true );
	}

	/**
	 * Build a stable runtime cache key.
	 *
	 * @param string $path     Build path.
	 * @param string $instance Build instance.
	 *
	 * @return string Cache key.
	 */
	public static function get_runtime_key( string $path, string $instance ): string {
		return md5(
			wp_json_encode(
				array(
					'version'      => defined( 'BLOCKSTUDIO_VERSION' ) ? BLOCKSTUDIO_VERSION : '',
					'cacheVersion' => self::VERSION,
					'path'         => wp_normalize_path( $path ),
					'instance'     => $instance,
					'settings'     => self::get_settings_fingerprint(),
					'plugins'      => self::get_active_plugins_fingerprint(),
					'stylesheet'   => function_exists( 'get_stylesheet' ) ? get_stylesheet() : '',
					'template'     => function_exists( 'get_template' ) ? get_template() : '',
					'wpVersion'    => get_bloginfo( 'version' ),
					'phpVersion'   => PHP_VERSION,
				)
			)
		);
	}

	/**
	 * Load runtime cache payload if it is still valid.
	 *
	 * @param string $path     Build path.
	 * @param string $instance Build instance.
	 *
	 * @return array|null Runtime payload or null on miss.
	 */
	public static function load_runtime( string $path, string $instance ): ?array {
		if ( ! self::is_enabled() ) {
			return null;
		}

		return self::load( 'runtime', self::get_runtime_key( $path, $instance ) );
	}

	/**
	 * Write runtime cache payload.
	 *
	 * @param string $path     Build path.
	 * @param string $instance Build instance.
	 * @param array  $payload  Runtime payload.
	 *
	 * @return bool Whether the payload was written.
	 */
	public static function write_runtime( string $path, string $instance, array $payload ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$payload['watch'] = self::create_watch_snapshot(
			self::collect_runtime_watch_paths( $path, $payload ),
			self::collect_runtime_watch_dirs( $path, $payload )
		);

		return self::write( 'runtime', self::get_runtime_key( $path, $instance ), $payload );
	}

	/**
	 * Build an editor asset cache key from registered block assets.
	 *
	 * @return string Cache key.
	 */
	public static function get_editor_assets_key(): string {
		$files = array();

		foreach ( Build::data() as $block ) {
			foreach ( $block['assets'] ?? array() as $asset ) {
				if ( empty( $asset['path'] ) ) {
					continue;
				}

				$source_path = wp_normalize_path( $asset['path'] );
				$path        = self::resolve_asset_path( $asset['path'] );

				$files[ $source_path ] = self::get_file_snapshot( $source_path );
				$files[ $path ]        = self::get_file_snapshot( $path );
				$matches               = Assets::get_matches( $asset['path'] );
				$files                += self::snapshot_paths( $matches );
			}
		}

		ksort( $files );

		return md5(
			wp_json_encode(
				array(
					'version'      => defined( 'BLOCKSTUDIO_VERSION' ) ? BLOCKSTUDIO_VERSION : '',
					'cacheVersion' => self::VERSION,
					'files'        => $files,
					'settings'     => self::get_settings_fingerprint(),
					'wpVersion'    => get_bloginfo( 'version' ),
				)
			)
		);
	}

	/**
	 * Load editor asset cache payload.
	 *
	 * @return array|null Editor asset payload or null on miss.
	 */
	public static function load_editor_assets(): ?array {
		if ( ! self::is_enabled() ) {
			return null;
		}

		return self::load( 'editor-assets', self::get_editor_assets_key() );
	}

	/**
	 * Write editor asset cache payload.
	 *
	 * @param array $payload Editor asset payload.
	 *
	 * @return bool Whether the payload was written.
	 */
	public static function write_editor_assets( array $payload ): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		return self::write( 'editor-assets', self::get_editor_assets_key(), $payload );
	}

	/**
	 * Load a payload from disk.
	 *
	 * @param string $scope Cache scope.
	 * @param string $key   Cache key.
	 *
	 * @return array|null Payload or null on miss.
	 */
	public static function load( string $scope, string $key ): ?array {
		$file = self::get_cache_file( $scope, $key );

		if ( ! is_file( $file ) ) {
			return null;
		}

		$payload = include $file;

		if ( ! is_array( $payload ) || ( $payload['cacheVersion'] ?? null ) !== self::VERSION ) {
			return null;
		}

		if ( ! self::is_watch_valid( $payload['watch'] ?? array() ) ) {
			return null;
		}

		return $payload;
	}

	/**
	 * Write a payload to disk.
	 *
	 * @param string $scope   Cache scope.
	 * @param string $key     Cache key.
	 * @param array  $payload Payload.
	 *
	 * @return bool Whether the payload was written.
	 */
	public static function write( string $scope, string $key, array $payload ): bool {
		$dir = self::get_cache_dir( $scope );

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$payload['cacheVersion'] = self::VERSION;
		$file                    = self::get_cache_file( $scope, $key );
		$tmp                     = $file . '.tmp-' . wp_generate_uuid4();
		$contents                = self::export_payload( $payload );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing local cache file.
		if ( false === file_put_contents( $tmp, $contents, LOCK_EX ) ) {
			return false;
		}

		if ( ! rename( $tmp, $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return false;
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $file, true );
		}

		return true;
	}

	/**
	 * Export a cache payload as a guarded PHP file.
	 *
	 * Compressed serialization keeps large build payloads from becoming huge PHP
	 * array literals, which are expensive for PHP to parse and can exhaust CLI
	 * memory on large sites.
	 *
	 * @param array $payload Payload to export.
	 *
	 * @return string PHP file contents.
	 */
	private static function export_payload( array $payload ): string {
		if ( function_exists( 'gzcompress' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Internal cache payload with no user-provided execution.
			$compressed = gzcompress( serialize( $payload ), 1 );

			if ( false !== $compressed ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding compressed cache data, not executable code.
				$encoded = base64_encode( $compressed );

				return "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n\$encoded = '" . $encoded . "';\n\$compressed = base64_decode( \$encoded, true );\nif ( false === \$compressed ) { return null; }\n\$serialized = gzuncompress( \$compressed );\nif ( false === \$serialized ) { return null; }\nreturn unserialize( \$serialized, array( 'allowed_classes' => false ) );\n";
			}
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Fallback cache format when zlib is unavailable.
		return "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\nreturn " . var_export( $payload, true ) . ";\n";
	}

	/**
	 * Create a watch snapshot from files and directories.
	 *
	 * @param array $files Files to watch.
	 * @param array $dirs  Directories to watch.
	 *
	 * @return array Watch snapshot.
	 */
	public static function create_watch_snapshot( array $files, array $dirs = array() ): array {
		$files = self::snapshot_paths( $files );
		$dirs  = self::snapshot_dirs( $dirs );

		ksort( $files );
		ksort( $dirs );

		return array(
			'files' => $files,
			'dirs'  => $dirs,
		);
	}

	/**
	 * Check whether a watch snapshot still matches disk state.
	 *
	 * @param array $watch Watch snapshot.
	 *
	 * @return bool Whether the snapshot is valid.
	 */
	public static function is_watch_valid( array $watch ): bool {
		foreach ( $watch['files'] ?? array() as $path => $snapshot ) {
			if ( self::get_file_snapshot( $path ) !== $snapshot ) {
				return false;
			}
		}

		foreach ( $watch['dirs'] ?? array() as $path => $snapshot ) {
			if ( self::get_dir_snapshot( $path ) !== $snapshot ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get cache file path.
	 *
	 * @param string $scope Cache scope.
	 * @param string $key   Cache key.
	 *
	 * @return string Cache file path.
	 */
	private static function get_cache_file( string $scope, string $key ): string {
		return self::get_cache_dir( $scope ) . '/' . sanitize_file_name( $key ) . '.php';
	}

	/**
	 * Resolve asset paths to the file that render_inline/render_tag will read.
	 *
	 * @param string $path Asset path.
	 *
	 * @return string Resolved asset path.
	 */
	private static function resolve_asset_path( string $path ): string {
		$resolved = Assets::get_path( $path );

		return wp_normalize_path( $resolved );
	}

	/**
	 * Snapshot multiple paths.
	 *
	 * @param array $paths Paths.
	 *
	 * @return array Path snapshots.
	 */
	private static function snapshot_paths( array $paths ): array {
		$snapshots = array();

		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || '' === $path ) {
				continue;
			}

			$path               = wp_normalize_path( $path );
			$snapshots[ $path ] = self::get_file_snapshot( $path );
		}

		return $snapshots;
	}

	/**
	 * Snapshot multiple directories.
	 *
	 * @param array $dirs Directory paths.
	 *
	 * @return array Directory snapshots.
	 */
	private static function snapshot_dirs( array $dirs ): array {
		$snapshots = array();

		foreach ( $dirs as $dir ) {
			if ( ! is_string( $dir ) || '' === $dir ) {
				continue;
			}

			$dir               = wp_normalize_path( $dir );
			$snapshots[ $dir ] = self::get_dir_snapshot( $dir );
		}

		return $snapshots;
	}

	/**
	 * Get a file snapshot.
	 *
	 * @param string $path File path.
	 *
	 * @return array File snapshot.
	 */
	private static function get_file_snapshot( string $path ): array {
		clearstatcache( true, $path );

		if ( ! is_file( $path ) ) {
			return array(
				'exists' => false,
			);
		}

		return array(
			'exists' => true,
			'mtime'  => filemtime( $path ),
			'size'   => filesize( $path ),
		);
	}

	/**
	 * Get a directory snapshot.
	 *
	 * @param string $path Directory path.
	 *
	 * @return array Directory snapshot.
	 */
	private static function get_dir_snapshot( string $path ): array {
		clearstatcache( true, $path );

		if ( ! is_dir( $path ) ) {
			return array(
				'exists' => false,
			);
		}

		return array(
			'exists' => true,
			'mtime'  => filemtime( $path ),
		);
	}

	/**
	 * Collect runtime cache watch files.
	 *
	 * @param string $path    Build path.
	 * @param array  $payload Runtime payload.
	 *
	 * @return array File paths.
	 */
	private static function collect_runtime_watch_paths( string $path, array $payload ): array {
		$paths = array();

		foreach ( $payload['store'] ?? array() as $data ) {
			if ( isset( $data['path'] ) ) {
				$paths[] = $data['path'];
			}

			foreach ( $data['filesPaths'] ?? array() as $file_path ) {
				$paths[] = $file_path;
			}

			foreach ( $data['assets'] ?? array() as $asset ) {
				if ( isset( $asset['path'] ) ) {
					$paths[] = $asset['path'];
					$paths[] = self::resolve_asset_path( $asset['path'] );
				}
			}
		}

		foreach ( $payload['registerable'] ?? array() as $item ) {
			if ( isset( $item['data']['path'] ) ) {
				$paths[] = $item['data']['path'];
			}
		}

		foreach ( $payload['fieldPaths'] ?? array() as $field_path ) {
			$paths[] = $field_path;
		}

		$settings_json = Settings::json();
		if ( $settings_json ) {
			$paths[] = $settings_json;
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $watch_path ) => is_string( $watch_path ) && '' !== $watch_path
							? wp_normalize_path( $watch_path )
							: '',
						$paths
					)
				)
			)
		);
	}

	/**
	 * Collect runtime cache watch directories.
	 *
	 * Directory mtimes catch file additions/removals that are not represented by
	 * the watched file list yet.
	 *
	 * @param string $path    Build path.
	 * @param array  $payload Runtime payload.
	 *
	 * @return array Directory paths.
	 */
	private static function collect_runtime_watch_dirs( string $path, array $payload ): array {
		$dirs = array( $path );

		foreach ( $payload['fieldDirs'] ?? array() as $field_dir ) {
			$dirs[] = $field_dir;
		}

		foreach ( $payload['store'] ?? array() as $data ) {
			if ( isset( $data['path'] ) ) {
				$dirs[] = dirname( $data['path'] );
			}
		}

		foreach ( $payload['registerable'] ?? array() as $item ) {
			if ( isset( $item['data']['path'] ) ) {
				$dirs[] = dirname( $item['data']['path'] );
			}
		}

		foreach ( $dirs as $dir ) {
			if ( is_dir( $dir ) ) {
				$dirs = array_merge( $dirs, self::collect_directories( $dir ) );
			}
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $watch_dir ) => is_string( $watch_dir ) && '' !== $watch_dir
							? wp_normalize_path( $watch_dir )
							: '',
						$dirs
					)
				)
			)
		);
	}

	/**
	 * Recursively collect directory paths.
	 *
	 * @param string $path Base path.
	 *
	 * @return array Directory paths.
	 */
	private static function collect_directories( string $path ): array {
		if ( ! is_dir( $path ) ) {
			return array();
		}

		$directories = array( wp_normalize_path( $path ) );
		$iterator    = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$path,
				\FilesystemIterator::SKIP_DOTS
			),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				$directories[] = wp_normalize_path( $file->getPathname() );
			}
		}

		return $directories;
	}

	/**
	 * Get settings values that influence build and asset output.
	 *
	 * Settings::get_all() captures loaded settings, while explicit Settings::get()
	 * calls include runtime filters that can be registered after initialization.
	 *
	 * @return array Settings fingerprint.
	 */
	private static function get_settings_fingerprint(): array {
		return array(
			'all'               => Settings::get_all(),
			'assets/enqueue'    => Settings::get( 'assets/enqueue' ),
			'assets/process'    => Settings::get( 'assets/process' ),
			'blockEditor'       => Settings::get( 'blockEditor' ),
			'tailwind/enabled'  => Settings::get( 'tailwind/enabled' ),
			'tailwind/config'   => Settings::get( 'tailwind/config' ),
			'ui/enabled'        => Settings::get( 'ui/enabled' ),
			'blockTags/enabled' => Settings::get( 'blockTags/enabled' ),
			'cache/enabled'     => Settings::get( 'cache/enabled' ),
		);
	}

	/**
	 * Get active plugin fingerprint for cache keys.
	 *
	 * @return array Active plugin fingerprint.
	 */
	private static function get_active_plugins_fingerprint(): array {
		$active = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active = array_merge(
				$active,
				array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
			);
		}

		sort( $active );

		$fingerprint = array();

		foreach ( $active as $plugin ) {
			$path                   = WP_PLUGIN_DIR . '/' . $plugin;
			$fingerprint[ $plugin ] = self::get_file_snapshot( $path );
		}

		return $fingerprint;
	}
}
