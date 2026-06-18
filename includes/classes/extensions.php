<?php
/**
 * Extensions class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use WP_HTML_Tag_Processor;

/**
 * Block extensions system for adding attributes to existing blocks.
 *
 * Extensions allow adding custom attributes and behavior to any
 * registered WordPress block without modifying its source code.
 *
 * How Extensions Work:
 * 1. Create a block.json with blockstudio.extend: true
 * 2. Specify target blocks using the "name" field (supports wildcards)
 * 3. Define attributes that will be added to matched blocks
 * 4. Optionally use "set" to apply values to the block's HTML
 *
 * Example Extension (block.json):
 * ```json
 * {
 *   "name": "core/paragraph",       // Or ["core/paragraph", "core/heading"]
 *   "blockstudio": { "extend": true },
 *   "attributes": {
 *     "textColor": {
 *       "type": "string",
 *       "field": "select",
 *       "options": ["primary", "secondary"],
 *       "set": [{ "attribute": "class", "value": "text-{attributes.textColor}" }]
 *     }
 *   }
 * }
 * ```
 *
 * Wildcard Matching:
 * - "core/*" matches all core blocks
 * - "core/heading" matches only headings
 * - ["core/paragraph", "core/heading"] matches both
 *
 * Set Configuration:
 * The "set" array defines how attribute values modify block HTML:
 * - { "attribute": "class", "value": "{value}" } → adds CSS class
 * - { "attribute": "style", "value": "--color: {value}" } → adds inline style
 * - { "attribute": "data-foo", "value": "{value}" } → adds data attribute
 *
 * Template Syntax:
 * - {attributes.fieldName} → current field value
 * - {attributes.otherField} → another field's value
 *
 * @since 3.0.0
 */
class Extensions {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block', array( __CLASS__, 'render_blocks' ), 10, 2 );
	}

	/**
	 * Render blocks with extensions.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string The modified block content.
	 */
	public static function render_blocks( $block_content, $block ): string {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$extensions = Build::extensions();
		$matches    = self::get_matches( $block['blockName'], $extensions );

		$attributes              = array();
		$resolved_values         = array();
		$raw_attrs               = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$nested_block_attributes = is_array( $raw_attrs['blockstudio']['attributes'] ?? null )
			? $raw_attrs['blockstudio']['attributes']
			: array();

		foreach ( $matches as $match ) {
			foreach ( $match->attributes as $attribute_id => $attribute ) {
				if ( ! is_array( $attribute ) ) {
					continue;
				}

				$resolved_id = '';
				if ( is_string( $attribute_id ) ) {
					$resolved_id = $attribute_id;
				} elseif ( is_string( $attribute['id'] ?? null ) ) {
					$resolved_id = $attribute['id'];
				}

				if ( '' === $resolved_id ) {
					continue;
				}

				if ( array_key_exists( $resolved_id, $nested_block_attributes ) ) {
					$attributes[ $resolved_id ]      = $attribute;
					$resolved_values[ $resolved_id ] = $nested_block_attributes[ $resolved_id ];
				} elseif ( array_key_exists( $resolved_id, $raw_attrs ) ) {
					$attributes[ $resolved_id ]      = $attribute;
					$resolved_values[ $resolved_id ] = $raw_attrs[ $resolved_id ];
				}
			}
		}

		$blockstudio_attributes = array(
			'blockstudio' => array(
				'attributes' => $resolved_values,
				'disabled'   => $block['attrs']['blockstudio']['disabled'] ?? array(),
			),
		);
		$ref                    = $blockstudio_attributes;

		$attribute_data = Block::transform(
			$blockstudio_attributes,
			$ref,
			null,
			false,
			false,
			$attributes
		);

		if ( empty( $matches ) && ! ( $attribute_data['hasCodeSelector'] ?? false ) ) {
			return $block_content;
		}

		$content = new WP_HTML_Tag_Processor( $block_content );

		$is_sequential = function ( $arr ) {
			return is_array( $arr ) &&
				array_keys( $arr ) === range( 0, count( $arr ) - 1 );
		};

		if ( $content->next_tag() ) {
			$class         = '';
			$style         = '';
			$current_class = $content->get_attribute( 'class' );
			$current_style = $content->get_attribute( 'style' );

			if (
				'' !== trim( $current_style ?? '' ) &&
				! str_ends_with( $current_style, ';' )
			) {
				$current_style .= ';';
			}

			if ( $attribute_data['hasCodeSelector'] ) {
				$content->set_attribute(
					'data-assets',
					$attribute_data['selectorAttributeId']
				);
			}

			foreach ( $attributes as $key => $value ) {
				$attribute_value     = $blockstudio_attributes[ $key ] ?? $resolved_values[ $key ] ?? null;
				$template_attributes = array_merge(
					$resolved_values,
					is_array( $blockstudio_attributes ) ? $blockstudio_attributes : array()
				);

				if (
					! isset( $value['set'] ) &&
					isset( $value['field'] ) &&
					'attributes' === $value['field']
				) {
					if ( is_array( $attribute_value ) ) {
						foreach ( $attribute_value as $attr ) {
							$content->set_attribute(
								$attr['attribute'],
								$attr['value']
							);
						}
					}
				}

				foreach ( $value['set'] ?? array() as $set ) {
					$val = $attribute_value;

					if ( null === $val || false === $val || '' === $val ) {
						continue;
					}

					$apply_value = function (
						$value,
						$attr
					) use (
						&$class,
						&$style,
						$set,
						$content
					) {
						if ( $set['value'] ?? false ) {
							$value = self::parse_template(
								$set['value'],
								array(
									'attributes' => $attr,
								)
							);
						}

						if ( 'class' === $set['attribute'] ) {
							$value  = self::normalize_spacing_preset_class_value( (string) $value );
							$class .= ' ' . $value;
						} elseif ( 'style' === $set['attribute'] ) {
							$style .= ' ' . $value . ';';
							$style  = str_replace( ';;', ';', $style );
						} else {
							$content->set_attribute( $set['attribute'], $value );
						}
					};

					if ( $is_sequential( $val ) ) {
						$index = -1;
						foreach ( $val as $v ) {
							++$index;

							if ( ! isset( $val[ $index ] ) ) {
								continue;
							}

							$apply_value(
								$v,
								array_merge(
									$template_attributes,
									array(
										$key => $val[ $index ],
									)
								)
							);
						}
					} else {
						$apply_value( $val, $template_attributes );
					}
				}
			}

			$combined_class = trim( $current_class . $class );
			if ( '' !== $combined_class ) {
				$content->set_attribute( 'class', $combined_class );
			}

			$combined_style = trim( $current_style . $style );
			if ( '' !== $combined_style ) {
				$content->set_attribute(
					'style',
					str_replace( ';;', ';', $combined_style )
				);
			}
		}

		$element = $content->get_updated_html();
		$assets  = Assets::render_code_field_assets( $attribute_data );

		return $element . $assets;
	}

	/**
	 * Convert spacing preset tokens to class-friendly slugs.
	 *
	 * Example: var:preset|spacing|small -> small.
	 *
	 * @param string $value Class value.
	 *
	 * @return string Normalized class value.
	 */
	private static function normalize_spacing_preset_class_value( string $value ): string {
		return (string) preg_replace( '/var:preset\\|spacing\\|([a-z0-9_-]+)/i', '$1', $value );
	}

	/**
	 * Get matching extensions for a block.
	 *
	 * @param string $string     The block name.
	 * @param array  $extensions The extensions array.
	 *
	 * @return array Array of matching extensions.
	 */
	public static function get_matches( $string, $extensions ): array {
		$matches     = array();
		$match_found = function ( $name, $string ) {
			if ( ! $name || ! $string ) {
				return false;
			}

			if ( '*' === substr( $name, -1 ) ) {
				$prefix = substr( $name, 0, -1 );

				return 0 === strpos( $string, $prefix );
			} else {
				return $name === $string;
			}
		};

		foreach ( $extensions as $e ) {
			if ( is_array( $e->name ) ) {
				foreach ( $e->name as $name ) {
					if ( $match_found( $name, $string ) ) {
						$matches[] = $e;
					}
				}
			} elseif ( $match_found( $e->name, $string ) ) {
					$matches[] = $e;
			}
		}

		return $matches;
	}

	/**
	 * Replace template string placeholders.
	 *
	 * @param string $template_string The template string.
	 * @param array  $values          The values to replace.
	 *
	 * @return string|null The parsed string.
	 */
	public static function parse_template( $template_string, $values ) {
		return preg_replace_callback(
			'/\{([^}]+)\}/',
			function ( $matches ) use ( $values ) {
				$path = $matches[1];

				return self::get( $values, $path );
			},
			$template_string
		);
	}

	/**
	 * Get a nested array element similar to Lodash/Get.
	 *
	 * @param mixed       $target  The target array or object.
	 * @param string|null $key     The key path.
	 * @param mixed       $default The default value.
	 *
	 * @return mixed The value or default.
	 */
	public static function get( $target, $key, $default = null ) {
		if ( is_null( $key ) ) {
			return $target;
		}

		$key = is_array( $key ) ? $key : explode( '.', $key );

		foreach ( $key as $segment ) {
			if ( ! is_array( $target ) && ! is_object( $target ) ) {
				return $default;
			}
			if ( is_array( $target ) && array_key_exists( $segment, $target ) ) {
				$target = $target[ $segment ];
			} elseif (
				is_object( $target ) &&
				property_exists( $target, $segment )
			) {
				$target = $target->$segment;
			} else {
				return $default;
			}
		}

		return $target;
	}
}

new Extensions();
