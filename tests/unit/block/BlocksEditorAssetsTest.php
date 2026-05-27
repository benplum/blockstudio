<?php
/**
 * Tests for Blockstudio editor asset enqueue behavior.
 *
 * @package Blockstudio
 */

use Blockstudio\Admin;
use Blockstudio\Blocks;
use Blockstudio\Build;
use PHPUnit\Framework\TestCase;

/**
 * Tests editor asset capture behavior.
 */
class BlocksEditorAssetsTest extends TestCase {

	/**
	 * Filter callbacks registered by the test.
	 *
	 * @var array<int, array{0: string, 1: callable, 2: int}>
	 */
	private array $filter_callbacks = array();

	/**
	 * Temporary post ID created by the test.
	 *
	 * @var int|null
	 */
	private ?int $post_id = null;

	/**
	 * Clean up temporary state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $filter_callback ) {
			remove_filter( $filter_callback[0], $filter_callback[1], $filter_callback[2] );
		}

		$this->filter_callbacks = array();

		if ( null !== $this->post_id ) {
			wp_delete_post( $this->post_id, true );
			$this->post_id = null;
		}

		delete_transient( 'blockstudio_editor_all_assets' );
		delete_transient( 'blockstudio_editor_captured_frontend_scripts' );
		delete_transient( 'blockstudio_editor_captured_frontend_styles' );

		$this->reset_editor_asset_state();
		$this->set_static_property( Blocks::class, 'getting_assets', false );
		$this->set_static_property( Admin::class, 'capturing_assets', false );

		wp_set_current_user( 0 );
		unset( $GLOBALS['post'] );
	}

	/**
	 * Asset capture should not run when no CSS autocomplete handles are configured.
	 *
	 * @return void
	 */
	public function test_empty_css_autocomplete_settings_skip_asset_capture(): void {
		$result = $this->run_editor_enqueue_with_css_settings( array(), array() );

		$this->assertSame( 1, $result['block_render_count'] );
		$this->assertSame( 0, $result['asset_capture_request_count'] );
	}

	/**
	 * Asset capture should not recursively pre-render the edited post content.
	 *
	 * @return void
	 */
	public function test_asset_capture_does_not_render_post_content_recursively(): void {
		$result = $this->run_editor_enqueue_with_css_settings(
			array( 'wp-block-library-theme' ),
			array( 'global-styles-css-custom-properties' )
		);

		$this->assertSame( 1, $result['block_render_count'] );
		$this->assertSame( 1, $result['asset_capture_request_count'] );
	}

	/**
	 * Run the editor enqueue action with controlled CSS autocomplete settings.
	 *
	 * @param array<int, string> $css_classes CSS class source handles.
	 * @param array<int, string> $css_variables CSS variable source handles.
	 *
	 * @return array{block_render_count: int, asset_capture_request_count: int}
	 */
	private function run_editor_enqueue_with_css_settings( array $css_classes, array $css_variables ): array {
		if ( ! isset( Build::blocks()['blockstudio/type-text'] ) ) {
			$this->markTestSkipped( 'blockstudio/type-text not registered.' );
		}

		wp_set_current_user( 1 );

		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		set_current_screen( 'post' );

		$this->post_id = wp_insert_post(
			array(
				'post_title'   => 'Editor asset capture test',
				'post_status'  => 'draft',
				'post_type'    => 'post',
				'post_content' => '<!-- wp:blockstudio/type-text {"blockstudio":{"attributes":{"text":"Hello"}}} /-->',
			),
			true
		);

		$this->assertIsInt( $this->post_id );

		$GLOBALS['post'] = get_post( $this->post_id );
		$_GET['post']    = (string) $this->post_id;
		$_GET['action']  = 'edit';

		$this->add_filter(
			'blockstudio/settings/block_editor/css_classes',
			static fn () => $css_classes
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/css_variables',
			static fn () => $css_variables
		);

		$block_render_count          = 0;
		$asset_capture_request_count = 0;

		$this->add_filter(
			'render_block_data',
			static function ( $parsed_block ) use ( &$block_render_count ) {
				if ( is_array( $parsed_block ) && 'blockstudio/type-text' === ( $parsed_block['blockName'] ?? null ) ) {
					++$block_render_count;
				}

				return $parsed_block;
			}
		);

		$this->add_filter(
			'pre_http_request',
			static function ( $preempt, $request_args, $url ) use ( &$asset_capture_request_count ) {
				if ( str_contains( (string) $url, 'blockstudio_editor_capture_assets_id=' ) ) {
					++$asset_capture_request_count;

					return array(
						'headers'  => array(),
						'body'     => '',
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => null,
					);
				}

				return $preempt;
			},
			10,
			3
		);

		$this->reset_editor_asset_state();
		delete_transient( 'blockstudio_editor_all_assets' );

		do_action( 'enqueue_block_editor_assets' );

		return array(
			'block_render_count'          => $block_render_count,
			'asset_capture_request_count' => $asset_capture_request_count,
		);
	}

	/**
	 * Add and track a filter for cleanup.
	 *
	 * @param string   $name Filter name.
	 * @param callable $callback Filter callback.
	 * @param int      $priority Filter priority.
	 * @param int      $args Accepted argument count.
	 *
	 * @return void
	 */
	private function add_filter( string $name, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $name, $callback, $priority, $args );
		$this->filter_callbacks[] = array( $name, $callback, $priority );
	}

	/**
	 * Reset script/style enqueue state for deterministic assertions.
	 *
	 * @return void
	 */
	private function reset_editor_asset_state(): void {
		global $wp_scripts, $wp_styles;

		if ( $wp_scripts instanceof WP_Scripts ) {
			$wp_scripts->queue = array();
			$wp_scripts->done  = array();
		}

		if ( $wp_styles instanceof WP_Styles ) {
			$wp_styles->queue = array();
			$wp_styles->done  = array();
		}

		wp_dequeue_script( 'blockstudio-blocks' );
		wp_deregister_script( 'blockstudio-blocks' );
	}

	/**
	 * Set a static property value for cleanup.
	 *
	 * @param string $class_name Class name.
	 * @param string $property_name Property name.
	 * @param mixed  $value Property value.
	 *
	 * @return void
	 */
	private function set_static_property( string $class_name, string $property_name, $value ): void {
		$reflection = new ReflectionClass( $class_name );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}
}
