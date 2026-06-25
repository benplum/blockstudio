<?php
/**
 * Query renderer trait.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Renders core/query and core/comments blocks.
 */
trait Query_Renderer {

	/**
	 * Render core/query block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_query( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );
		$class        = $this->get_query_wrapper_class( 'wp-block-query', $attrs );

		$content = array( '<div class="' . esc_attr( $class ) . '">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/query',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="' . esc_attr( $class ) . '"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Render core/comments block.
	 *
	 * @param array  $attrs         The attributes.
	 * @param string $inner_content The inner content.
	 *
	 * @return array The block array.
	 */
	public function render_comments( array $attrs, string $inner_content ): array {
		$inner_blocks = Block_Tags::parse_inner_blocks( $inner_content );
		$class        = $this->get_query_wrapper_class( 'wp-block-comments', $attrs );

		$content = array( '<div class="' . esc_attr( $class ) . '">' );
		foreach ( $inner_blocks as $block ) {
			$content[] = null;
		}
		$content[] = '</div>';

		return array(
			'blockName'    => 'core/comments',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '<div class="' . esc_attr( $class ) . '"></div>',
			'innerContent' => $content,
		);
	}

	/**
	 * Get wrapper classes for dynamic query-like block markup.
	 *
	 * @param string $base_class Base WordPress wrapper class.
	 * @param array  $attrs      Block attributes.
	 *
	 * @return string Wrapper class attribute value.
	 */
	private function get_query_wrapper_class( string $base_class, array $attrs ): string {
		if ( ! isset( $attrs['className'] ) || ! is_scalar( $attrs['className'] ) ) {
			return $base_class;
		}

		$class_name = trim( (string) $attrs['className'] );

		if ( '' === $class_name ) {
			return $base_class;
		}

		return trim( $base_class . ' ' . $class_name );
	}
}
