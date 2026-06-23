<?php
/**
 * Post Meta Storage Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Storage_Handlers;

use Blockstudio\Interfaces\Storage_Handler_Interface;
use Blockstudio\Field_Type_Config;

/**
 * Post meta storage handler.
 *
 * Handles storage in wp_postmeta table. Registers post meta with
 * REST API support for Gutenberg integration.
 *
 * @since 7.0.0
 */
class Post_Meta_Storage implements Storage_Handler_Interface {

	/**
	 * Get the storage type identifier.
	 *
	 * @return string The storage type.
	 */
	public function get_type(): string {
		return 'postMeta';
	}

	/**
	 * Register storage for a field.
	 *
	 * Registers post meta with REST API support.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return void
	 */
	public function register( string $block_name, array $field ): void {
		$meta_key  = $this->get_key( $block_name, $field );
		$meta_type = $field['__blockstudio_storage_value_type'] ?? $this->get_meta_type( $field );

		/**
		 * Filter storage value type for field registration.
		 *
		 * @param string $meta_type  Resolved storage value type.
		 * @param array  $field      Field configuration.
		 * @param string $block_name Block name.
		 * @param string $storage    Storage handler type.
		 */
		$meta_type = (string) apply_filters( 'blockstudio/storage/meta_type', $meta_type, $field, $block_name, $this->get_type() );

		$show_in_rest = true;
		if ( 'array' === $meta_type ) {
			$show_in_rest = array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			);
		}

		register_post_meta(
			'',
			$meta_key,
			array(
				'show_in_rest' => $show_in_rest,
				'single'       => true,
				'type'         => $meta_type,
			)
		);
	}

	/**
	 * Get the storage key for a field.
	 *
	 * @param string $block_name The block name.
	 * @param array  $field      The field configuration.
	 *
	 * @return string The meta key.
	 */
	public function get_key( string $block_name, array $field ): string {
		if ( isset( $field['storage']['postMetaKey'] ) ) {
			return $field['storage']['postMetaKey'];
		}

		return sanitize_key( $block_name . '_' . $field['id'] );
	}

	/**
	 * Get the meta type for a field type.
	 *
	 * Maps Blockstudio field types to WordPress meta types.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return string The meta type.
	 */
	private function get_meta_type( array $field ): string {
		$field_type = $field['type'] ?? 'text';

		$storage_type = Field_Type_Config::get_storage_value_type( $field_type );
		if ( null !== $storage_type ) {
			return $storage_type;
		}

		if ( Field_Type_Config::is_string_type( $field_type ) ) {
			return 'string';
		}

		if ( Field_Type_Config::is_number_type( $field_type ) ) {
			return 'number';
		}

		if ( Field_Type_Config::is_boolean_type( $field_type ) ) {
			return 'boolean';
		}

		if ( Field_Type_Config::is_array_type( $field_type ) ) {
			return 'array';
		}

		if ( Field_Type_Config::is_object_type( $field_type ) ) {
			return 'string';
		}

		// Default to string for object types and unknown types.
		return 'string';
	}
}
