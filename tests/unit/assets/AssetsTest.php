<?php

use Blockstudio\Assets;
use Blockstudio\Block;
use Blockstudio\Build;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase {

	private array $filter_callbacks = array();

	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $filter_callback ) {
			remove_filter( $filter_callback[0], $filter_callback[1], $filter_callback[2] );
		}

		$this->filter_callbacks = array();
	}

	private function add_filter( string $name, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $name, $callback, $priority, $args );
		$this->filter_callbacks[] = array( $name, $callback, $priority );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function test_plugin_bootstrap_registers_editor_asset_footer_once(): void {
		$this->assertSame( 1, $this->count_assets_admin_footer_callbacks() );
	}

	private function count_assets_admin_footer_callbacks(): int {
		global $wp_filter;

		if ( ! isset( $wp_filter['admin_footer'] ) ) {
			return 0;
		}

		$assets_file = wp_normalize_path( BLOCKSTUDIO_DIR . '/includes/classes/assets.php' );
		$count       = 0;

		foreach ( $wp_filter['admin_footer']->callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback ) {
				$callback_function = $callback['function'] ?? null;

				if ( ! $callback_function instanceof \Closure ) {
					continue;
				}

				$reflection    = new \ReflectionFunction( $callback_function );
				$callback_file = $reflection->getFileName();

				if ( false === $callback_file ) {
					continue;
				}

				if ( $assets_file === wp_normalize_path( $callback_file ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	public function test_get_interactivity_api_import_map_returns_importmap_script(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringContainsString( '<script type="importmap">', $result );
		$this->assertStringContainsString( '</script>', $result );
		$this->assertStringContainsString( '@wordpress/interactivity', $result );
	}

	public function test_get_interactivity_api_import_map_contains_preact(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringContainsString( 'preact', $result );
		$this->assertStringContainsString( 'preact/hooks', $result );
	}

	public function test_get_interactivity_api_import_map_contains_preact_signals(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringContainsString( '@preact/signals', $result );
		$this->assertStringContainsString( '@preact/signals-core', $result );
	}

	public function test_get_interactivity_api_import_map_resolves_path_placeholder(): void {
		$result = Assets::get_interactivity_api_import_map();

		$this->assertStringNotContainsString( '@path', $result );
		$this->assertStringContainsString( BLOCKSTUDIO_URL, $result );
	}

	public function test_get_interactivity_editor_assets_returns_string(): void {
		$result = Assets::get_interactivity_editor_assets();

		$this->assertIsString( $result );
	}

	public function test_get_interactivity_editor_assets_returns_script_tags_when_interactivity_blocks_exist(): void {
		$blocks              = Build::blocks();
		$has_interactivity   = false;

		foreach ( $blocks as $block ) {
			if ( Build::has_interactivity( $block->blockstudio ?? array() ) ) {
				$has_interactivity = true;
				break;
			}
		}

		$result = Assets::get_interactivity_editor_assets();

		if ( $has_interactivity ) {
			$this->assertStringContainsString( '<script', $result );
			$this->assertStringContainsString( '@wordpress/interactivity', $result );
		} else {
			$this->assertSame( '', $result );
		}
	}

	public function test_get_interactivity_importmap_returns_empty_when_no_interactivity_blocks(): void {
		$result = Assets::get_interactivity_importmap( array(), '<html></html>' );

		$this->assertSame( '', $result );
	}

	public function test_get_interactivity_importmap_returns_empty_when_no_module_tag_in_html(): void {
		$block              = new stdClass();
		$block->blockstudio = array( 'interactivity' => true );

		$result = Assets::get_interactivity_importmap(
			array( 'test/block' => $block ),
			'<html><head></head><body></body></html>'
		);

		$this->assertSame( '', $result );
	}

	public function test_get_interactivity_importmap_returns_importmap_when_module_tag_present(): void {
		$block              = new stdClass();
		$block->blockstudio = array( 'interactivity' => true );

		$html = '<script type="module" src="http://example.com/wp-includes/js/dist/interactivity.min.js" id="@wordpress/interactivity-js-module"></script>';

		$result = Assets::get_interactivity_importmap(
			array( 'test/block' => $block ),
			$html
		);

		$this->assertStringContainsString( '<script type="importmap">', $result );
		$this->assertStringContainsString( 'interactivity', $result );
		$this->assertStringContainsString( 'interactivity.min.js', $result );
	}

	public function test_is_css_returns_true_for_css_path(): void {
		$this->assertTrue( Assets::is_css( 'style.css' ) );
	}

	public function test_is_css_returns_true_for_scss_path(): void {
		$this->assertTrue( Assets::is_css( 'style.scss' ) );
	}

	public function test_is_css_returns_false_for_js_path(): void {
		$this->assertFalse( Assets::is_css( 'script.js' ) );
	}

	public function test_is_css_extension_returns_true_for_css(): void {
		$this->assertTrue( Assets::is_css_extension( 'css' ) );
	}

	public function test_is_css_extension_returns_true_for_scss(): void {
		$this->assertTrue( Assets::is_css_extension( 'scss' ) );
	}

	public function test_is_css_extension_returns_false_for_js(): void {
		$this->assertFalse( Assets::is_css_extension( 'js' ) );
	}

	public function test_get_id_returns_formatted_string(): void {
		$block = array( 'name' => 'test/my-block' );
		$id    = Assets::get_id( 'style.css', $block );

		$this->assertStringContainsString( 'blockstudio', $id );
		$this->assertStringContainsString( 'test', $id );
		$this->assertStringContainsString( 'my-block', $id );
		$this->assertStringNotContainsString( '/', $id );
	}

	public function test_parse_output_returns_string(): void {
		$assets = new Assets();
		$result = $assets->parse_output( '<html><head></head><body></body></html>' );

		$this->assertIsString( $result );
	}

	public function test_parse_output_preserves_body_and_head(): void {
		$assets = new Assets();
		$html   = '<html><head><title>Test</title></head><body><p>Content</p></body></html>';
		$result = $assets->parse_output( $html );

		$this->assertStringContainsString( '</head>', $result );
		$this->assertStringContainsString( '</body>', $result );
		$this->assertStringContainsString( '<p>Content</p>', $result );
	}

	public function test_parse_output_keeps_frontend_assets_when_render_filter_replaces_output(): void {
		$block_name = 'blockstudio/assets';
		$blocks     = Build::data();

		if ( ! isset( $blocks[ $block_name ] ) ) {
			$this->markTestSkipped( "{$block_name} not registered." );
		}

		$block_data        = $blocks[ $block_name ];
		$expected_asset_id = null;

		foreach ( $block_data['assets'] ?? array() as $asset_name => $asset ) {
			if ( str_contains( $asset_name, 'editor' ) || str_contains( $asset_name, 'admin' ) ) {
				continue;
			}

			if ( ! Assets::is_css( $asset_name ) || 'inline' === $asset['type'] ) {
				continue;
			}

			$expected_asset_id = Assets::get_id( $asset_name, $block_data );
			break;
		}

		if ( null === $expected_asset_id ) {
			$this->markTestSkipped( "{$block_name} has no external frontend CSS asset." );
		}

		$this->add_filter(
			'blockstudio/blocks/render',
			function ( $html, $block ) use ( $block_name ) {
				if ( ( $block->name ?? '' ) === $block_name ) {
					return '<section class="sage-blade-render">Blade output</section>';
				}

				return $html;
			},
			20,
			2
		);

		$rendered = Block::render(
			array(
				'blockstudio' => array(
					'name'       => $block_name,
					'attributes' => array(),
				),
			)
		);

		$result = ( new Assets() )->parse_output(
			'<html><head></head><body>' . $rendered . '</body></html>'
		);

		$this->assertStringContainsString( 'sage-blade-render', $result );
		$this->assertStringContainsString( "id='{$expected_asset_id}'", $result );
	}

	public function test_compile_scss_supports_bootstrap_prelude(): void {
		$bootstrap_path = BLOCKSTUDIO_DIR . '/node_modules/bootstrap/scss';

		$this->assertDirectoryExists( $bootstrap_path );

		$this->add_filter(
			'blockstudio/assets/process/scss/import_paths',
			function ( $paths ) use ( $bootstrap_path ) {
				$paths[] = $bootstrap_path;
				return $paths;
			}
		);

		$this->add_filter(
			'blockstudio/assets/process/scss/prelude',
			function () {
				return '@import "functions";' . "\n"
					. '@import "variables";' . "\n"
					. '@import "variables-dark";' . "\n"
					. '@import "maps";' . "\n"
					. '@import "mixins";';
			},
			10,
			3
		);

		$result = Assets::compile_scss(
			'.button { @include media-breakpoint-up(lg) { color: $primary; } }',
			BLOCKSTUDIO_DIR . '/tests/theme/style.scss'
		);

		$this->assertStringContainsString( '@media (min-width: 992px)', $result );
		$this->assertStringContainsString( 'color: #0d6efd;', $result );
	}

	public function test_get_imported_modification_times_changes_when_prelude_dependency_changes(): void {
		$directory = sys_get_temp_dir() . '/blockstudio-assets-' . uniqid( '', true );
		wp_mkdir_p( $directory );

		$path = $directory . '/style.scss';
		file_put_contents( $path, '.button { color: $color; }' );
		file_put_contents( $directory . '/_tokens.scss', '$color: #0d6efd;' );

		$this->add_filter(
			'blockstudio/assets/process/scss/import_paths',
			function ( $paths ) use ( $directory ) {
				$paths[] = $directory;
				return $paths;
			}
		);

		$this->add_filter(
			'blockstudio/assets/process/scss/prelude',
			function () {
				return '@import "tokens";';
			},
			10,
			3
		);

		$before = Assets::get_imported_modification_times( $path, '' );

		sleep( 1 );
		file_put_contents( $directory . '/_tokens.scss', '$color: #6610f2;' );
		clearstatcache();

		$after = Assets::get_imported_modification_times( $path, '' );

		$this->assertNotSame( $before, $after );
	}
}
