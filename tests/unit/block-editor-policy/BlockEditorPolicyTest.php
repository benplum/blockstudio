<?php

use Blockstudio\Block_Editor_Policy;
use Blockstudio\Patterns;
use PHPUnit\Framework\TestCase;

class BlockEditorPolicyTest extends TestCase {

	private array $filter_callbacks = array();
	private array $pattern_categories_before = array();
	private array $registered_blocks = array(
		'blockstudio-test/policy-a',
		'blockstudio-test/policy-b',
		'external/policy-c',
	);

	protected function setUp(): void {
		$this->register_test_blocks();
		$this->snapshot_pattern_categories();
	}

	protected function tearDown(): void {
		foreach ( $this->filter_callbacks as $cb ) {
			remove_filter( $cb[0], $cb[1], $cb[2] );
		}
		$this->filter_callbacks = array();

		$this->restore_pattern_categories();
		$this->restore_block_directory_action();
		$this->unregister_test_styles();
		$this->unregister_test_blocks();

		Patterns::reset();
		@Patterns::init( array( 'force' => true ) );
	}

	private function add_filter( string $name, callable $cb, int $priority = 10 ): void {
		add_filter( $name, $cb, $priority );
		$this->filter_callbacks[] = array( $name, $cb, $priority );
	}

	private function register_test_blocks(): void {
		foreach ( $this->registered_blocks as $block_name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
				continue;
			}

			register_block_type(
				$block_name,
				array(
					'api_version' => 3,
					'title'       => 'Policy Test Block',
					'category'    => 'widgets',
				)
			);
		}
	}

	private function unregister_test_blocks(): void {
		foreach ( $this->registered_blocks as $block_name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
				unregister_block_type( $block_name );
			}
		}
	}

	private function snapshot_pattern_categories(): void {
		if ( ! class_exists( '\WP_Block_Pattern_Categories_Registry' ) ) {
			return;
		}

		$this->pattern_categories_before = \WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();
	}

	private function restore_pattern_categories(): void {
		if ( ! class_exists( '\WP_Block_Pattern_Categories_Registry' ) ) {
			return;
		}

		$registry = \WP_Block_Pattern_Categories_Registry::get_instance();

		foreach ( $registry->get_all_registered() as $category ) {
			if ( isset( $category['name'] ) && $registry->is_registered( $category['name'] ) ) {
				unregister_block_pattern_category( $category['name'] );
			}
		}

		foreach ( $this->pattern_categories_before as $category ) {
			if ( empty( $category['name'] ) || empty( $category['label'] ) ) {
				continue;
			}

			register_block_pattern_category(
				$category['name'],
				array(
					'label' => $category['label'],
				)
			);
		}
	}

	private function restore_block_directory_action(): void {
		if (
			function_exists( 'wp_enqueue_editor_block_directory_assets' ) &&
			false === has_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' )
		) {
			add_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
		}
	}

	private function unregister_test_styles(): void {
		if ( ! class_exists( '\WP_Block_Styles_Registry' ) ) {
			return;
		}

		$registry = \WP_Block_Styles_Registry::get_instance();
		$blocks   = array_merge( $this->registered_blocks, array( 'unregistered/policy-style' ) );

		foreach ( array( 'policy-outline', 'policy-fill' ) as $style_name ) {
			foreach ( $blocks as $block_name ) {
				if ( $registry->is_registered( $block_name, $style_name ) ) {
					unregister_block_style( $block_name, $style_name );
				}
			}
		}
	}

	public function test_allowed_blocks_support_allow_and_deny_patterns(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/allow',
			function () {
				return array( 'blockstudio-test/*' );
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/deny',
			function () {
				return array( 'blockstudio-test/policy-b' );
			}
		);

		$allowed = Block_Editor_Policy::filter_allowed_block_types( true, new stdClass() );

		$this->assertContains( 'blockstudio-test/policy-a', $allowed );
		$this->assertNotContains( 'blockstudio-test/policy-b', $allowed );
		$this->assertNotContains( 'external/policy-c', $allowed );
	}

	public function test_allowed_blocks_preserve_existing_allow_list(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/allow',
			function () {
				return array( 'blockstudio-test/*', 'external/*' );
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/deny',
			function () {
				return array( 'blockstudio-test/policy-b' );
			}
		);

		$allowed = Block_Editor_Policy::filter_allowed_block_types(
			array( 'blockstudio-test/policy-a', 'blockstudio-test/policy-b' ),
			new stdClass()
		);

		$this->assertSame( array( 'blockstudio-test/policy-a' ), $allowed );
	}

	public function test_block_categories_can_be_filtered_renamed_and_ordered(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/categories/deny',
			function () {
				return array( 'media' );
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/categories/rename',
			function () {
				return array(
					'text' => 'Writing',
				);
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/categories/order',
			function () {
				return array( 'design', 'text' );
			}
		);

		$categories = Block_Editor_Policy::filter_block_categories(
			array(
				array(
					'slug'  => 'text',
					'title' => 'Text',
				),
				array(
					'slug'  => 'media',
					'title' => 'Media',
				),
				array(
					'slug'  => 'design',
					'title' => 'Design',
				),
			),
			new stdClass()
		);

		$this->assertSame( 'design', $categories[0]['slug'] );
		$this->assertSame( 'text', $categories[1]['slug'] );
		$this->assertSame( 'Writing', $categories[1]['title'] );
		$this->assertCount( 2, $categories );
	}

	public function test_remote_patterns_can_be_disabled(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/remote',
			function () {
				return false;
			}
		);

		$this->assertFalse( Block_Editor_Policy::filter_remote_patterns( true ) );
	}

	public function test_core_and_theme_patterns_can_be_disabled_before_registration(): void {
		add_theme_support( 'core-block-patterns' );
		add_action( 'init', '_register_theme_block_patterns' );

		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/core',
			function () {
				return false;
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/theme',
			function () {
				return false;
			}
		);

		Block_Editor_Policy::apply_early_init_policy();

		$this->assertFalse( get_theme_support( 'core-block-patterns' ) );
		$this->assertFalse( has_action( 'init', '_register_theme_block_patterns' ) );

		add_theme_support( 'core-block-patterns' );
		add_action( 'init', '_register_theme_block_patterns' );
	}

	public function test_blockstudio_patterns_can_be_disabled(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/blockstudio',
			function () {
				return false;
			}
		);

		Patterns::reset();
		Patterns::init( array( 'force' => true ) );

		$this->assertEmpty( Patterns::patterns() );
	}

	public function test_pattern_categories_can_be_filtered_renamed_and_ordered(): void {
		if ( ! class_exists( '\WP_Block_Pattern_Categories_Registry' ) ) {
			$this->markTestSkipped( 'Pattern category registry is not available.' );
		}

		register_block_pattern_category( 'policy-a', array( 'label' => 'Policy A' ) );
		register_block_pattern_category( 'policy-b', array( 'label' => 'Policy B' ) );
		register_block_pattern_category( 'policy-c', array( 'label' => 'Policy C' ) );

		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/categories/allow',
			function () {
				return array( 'policy-a', 'policy-b', 'policy-c' );
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/categories/deny',
			function () {
				return array( 'policy-c' );
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/categories/rename',
			function () {
				return array(
					'policy-a' => 'Renamed Policy A',
				);
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/patterns/categories/order',
			function () {
				return array( 'policy-b', 'policy-a' );
			}
		);

		Block_Editor_Policy::apply_pattern_categories();

		$categories = \WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered();

		$this->assertSame( 'policy-b', $categories[0]['name'] );
		$this->assertSame( 'policy-a', $categories[1]['name'] );
		$this->assertSame( 'Renamed Policy A', $categories[1]['label'] );
		$this->assertCount( 2, $categories );
	}

	public function test_openverse_can_be_disabled(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/media/openverse',
			function () {
				return false;
			}
		);

		$settings = Block_Editor_Policy::filter_editor_settings(
			array( 'enableOpenverseMediaCategory' => true ),
			new stdClass()
		);

		$this->assertFalse( $settings['enableOpenverseMediaCategory'] );
	}

	public function test_image_sizes_can_be_allowed_and_denied(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/media/image_sizes/allow',
			function () {
				return array( 'thumbnail', 'large' );
			}
		);
		$this->add_filter(
			'blockstudio/settings/block_editor/media/image_sizes/deny',
			function () {
				return array( 'thumbnail' );
			}
		);

		$sizes = Block_Editor_Policy::filter_image_sizes(
			array(
				'thumbnail' => 'Thumbnail',
				'medium'    => 'Medium',
				'large'     => 'Large',
			)
		);

		$this->assertSame( array( 'large' => 'Large' ), $sizes );
	}

	public function test_hidden_legacy_widgets_are_merged(): void {
		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/legacy_widgets/hide',
			function () {
				return array( 'archives', 'calendar' );
			}
		);

		$hidden = Block_Editor_Policy::filter_hidden_legacy_widgets( array( 'pages' ) );

		$this->assertSame( array( 'pages', 'archives', 'calendar' ), $hidden );
	}

	public function test_block_directory_can_be_disabled(): void {
		if ( ! function_exists( 'wp_enqueue_editor_block_directory_assets' ) ) {
			$this->markTestSkipped( 'Block directory assets callback is not available.' );
		}

		add_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );

		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/directory',
			function () {
				return false;
			}
		);

		Block_Editor_Policy::maybe_disable_block_directory();

		$this->assertFalse( has_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' ) );
	}

	public function test_php_registered_block_styles_can_be_denied(): void {
		if ( ! class_exists( '\WP_Block_Styles_Registry' ) ) {
			$this->markTestSkipped( 'Block styles registry is not available.' );
		}

		register_block_style(
			'blockstudio-test/policy-a',
			array(
				'name'  => 'policy-outline',
				'label' => 'Policy Outline',
			)
		);
		register_block_style(
			'blockstudio-test/policy-a',
			array(
				'name'  => 'policy-fill',
				'label' => 'Policy Fill',
			)
		);

		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/styles/deny',
			function () {
				return array(
					'blockstudio-test/policy-a' => array( 'policy-outline' ),
				);
			}
		);

		Block_Editor_Policy::apply_block_style_denies();

		$registry = \WP_Block_Styles_Registry::get_instance();

		$this->assertFalse( $registry->is_registered( 'blockstudio-test/policy-a', 'policy-outline' ) );
		$this->assertTrue( $registry->is_registered( 'blockstudio-test/policy-a', 'policy-fill' ) );
	}

	public function test_php_registered_block_styles_can_be_denied_before_block_type_exists(): void {
		if ( ! class_exists( '\WP_Block_Styles_Registry' ) ) {
			$this->markTestSkipped( 'Block styles registry is not available.' );
		}

		register_block_style(
			'unregistered/policy-style',
			array(
				'name'  => 'policy-outline',
				'label' => 'Policy Outline',
			)
		);

		$this->add_filter(
			'blockstudio/settings/block_editor/blocks/styles/deny',
			function () {
				return array(
					'unregistered/policy-style' => array( 'policy-outline' ),
				);
			}
		);

		Block_Editor_Policy::apply_block_style_denies();

		$registry = \WP_Block_Styles_Registry::get_instance();

		$this->assertFalse( $registry->is_registered( 'unregistered/policy-style', 'policy-outline' ) );
	}
}
