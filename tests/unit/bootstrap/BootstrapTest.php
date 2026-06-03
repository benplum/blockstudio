<?php

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase {

	public function test_composer_bootstrap_defers_before_wordpress_plugin_loading(): void {
		$bootstrap = dirname( __DIR__, 3 ) . '/bootstrap.php';
		$script    = tempnam( sys_get_temp_dir(), 'blockstudio-bootstrap-' );

		$this->assertNotFalse( $script );

		file_put_contents(
			$script,
			<<<PHP
<?php
define( 'ABSPATH', __DIR__ . '/' );
require '{$bootstrap}';
echo defined( 'BLOCKSTUDIO_VERSION' ) ? 'loaded' : 'deferred';
PHP
		);

		$output = array();
		$status = 0;

		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ), $output, $status );
		unlink( $script );

		$this->assertSame( 0, $status );
		$this->assertSame( 'deferred', implode( "\n", $output ) );
	}

	public function test_relative_plugin_path_normalizes_windows_path_separators(): void {
		$content_dir = 'D:/Users/example/Sites/site/wp-content';
		$plugin_dir  = 'D:\\Users\\example\\Sites\\site\\wp-content\\plugins\\blockstudio';

		$this->assertSame(
			$plugin_dir,
			str_replace( $content_dir, '', $plugin_dir )
		);

		$this->assertSame(
			'/plugins/blockstudio',
			blockstudio_get_relative_plugin_path( $content_dir, $plugin_dir )
		);
	}

	public function test_relative_plugin_path_preserves_normalized_paths(): void {
		$this->assertSame(
			'/plugins/blockstudio',
			blockstudio_get_relative_plugin_path(
				'/var/www/html/wp-content',
				'/var/www/html/wp-content/plugins/blockstudio'
			)
		);
	}
}
