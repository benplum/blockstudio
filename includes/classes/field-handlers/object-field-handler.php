<?php
/**
 * Object Field Handler class.
 *
 * @package Blockstudio
 */

namespace Blockstudio\Field_Handlers;

use Blockstudio\Field_Type_Config;

/**
 * Handler for object-based field types.
 *
 * Handles object-typed fields that are not option-based controls.
 *
 * @since 7.0.0
 */
class Object_Field_Handler extends Abstract_Field_Handler {

	/**
	 * Check whether this handler supports a field type.
	 *
	 * Select/radio/color/gradient are handled by Select_Field_Handler.
	 * This handler covers remaining object-typed fields, including
	 * custom types registered through the field type registry.
	 *
	 * @param string $type The field type.
	 *
	 * @return bool
	 */
	public function supports( string $type ): bool {
		if ( in_array( $type, array( 'select', 'radio', 'color', 'gradient' ), true ) ) {
			return false;
		}

		return Field_Type_Config::is_object_type( $type );
	}

	/**
	 * Build attribute data for an object field.
	 *
	 * @param array  $field      The field configuration.
	 * @param array  $attributes The attributes array (passed by reference).
	 * @param string $prefix     The attribute ID prefix.
	 *
	 * @return void
	 */
	public function build( array $field, array &$attributes, string $prefix = '' ): void {
		$type     = $field['type'] ?? '';
		$field_id = $this->get_field_id( $field, $prefix );

		if ( '' === $field_id ) {
			return;
		}

		$attribute = $this->create_base_attribute( $type, 'object' );

		$this->apply_defaults( $field, $attribute );
		$this->apply_storage( $field, $attribute );
		$attribute['id'] = $field_id;

		if ( $field['set'] ?? false ) {
			$attribute['set'] = $field['set'];
		}

		$attributes[ $field_id ] = $attribute;
	}

	/**
	 * Get the default value for an object field.
	 *
	 * @param array $field The field configuration.
	 *
	 * @return mixed The default value.
	 */
	public function get_default_value( array $field ): mixed {
		return $field['default'] ?? null;
	}
}
