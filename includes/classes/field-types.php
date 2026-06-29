<?php
/**
 * Field Types helper class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

use Blockstudio\Interfaces\Field_Handler_Interface;

/**
 * Helper API for registering custom field types.
 *
 * This class wraps the underlying filter-based extension points and provides
 * one registration entry point for type metadata, handlers, and storage
 * resolver callbacks.
 *
 * @since 7.3.3
 */
final class Field_Types {

	/**
	 * Registered field type definitions.
	 *
	 * @var array<string, array>
	 */
	private static array $definitions = array();

	/**
	 * Registered field handlers keyed by field type.
	 *
	 * @var array<string, Field_Handler_Interface>
	 */
	private static array $handlers = array();

	/**
	 * Registered storage resolvers keyed by field type.
	 *
	 * @var array<string, callable>
	 */
	private static array $storage_resolvers = array();

	/**
	 * Whether hooks were attached.
	 *
	 * @var bool
	 */
	private static bool $hooks_attached = false;

	/**
	 * Register a custom field type.
	 *
	 * @param string $type       Field type name.
	 * @param array  $definition Field type definition.
	 * @param array  $options    Optional registration settings.
	 *
	 * @return bool True when registration succeeds.
	 */
	public static function register( string $type, array $definition, array $options = array() ): bool {
		if ( ! self::is_valid_type_name( $type ) ) {
			return false;
		}

		if ( ! array_key_exists( 'attribute', $definition ) ) {
			return false;
		}

		$replace_existing = (bool) ( $options['replace'] ?? false );
		if ( isset( self::$definitions[ $type ] ) && ! $replace_existing ) {
			return false;
		}

		self::$definitions[ $type ] = $definition;

		$handler = $options['handler'] ?? null;
		if ( $handler instanceof Field_Handler_Interface ) {
			self::$handlers[ $type ] = $handler;
		}

		$resolver = $options['storageResolver'] ?? null;
		if ( is_callable( $resolver ) ) {
			self::$storage_resolvers[ $type ] = $resolver;
		}

		self::ensure_hooks();

		// Eagerly hydrate runtime registry when available.
		Field_Type_Registry::instance()->register( $type, $definition );

		return true;
	}

	/**
	 * Unregister a previously registered custom field type.
	 *
	 * @param string $type Field type name.
	 *
	 * @return void
	 */
	public static function unregister( string $type ): void {
		unset( self::$definitions[ $type ] );
		unset( self::$handlers[ $type ] );
		unset( self::$storage_resolvers[ $type ] );

		Field_Type_Registry::instance()->unregister( $type );
	}

	/**
	 * Filter callback for field type definitions.
	 *
	 * @param array $types Existing type definitions.
	 *
	 * @return array
	 */
	public static function filter_field_types( array $types ): array {
		foreach ( self::$definitions as $type => $definition ) {
			$types[ $type ] = $definition;
		}

		return $types;
	}

	/**
	 * Filter callback for attribute builder handlers.
	 *
	 * @param array $handlers Existing handlers.
	 *
	 * @return array
	 */
	public static function filter_handlers( array $handlers ): array {
		foreach ( self::$handlers as $handler ) {
			$handlers[] = $handler;
		}

		return $handlers;
	}

	/**
	 * Filter callback for storage meta type resolution.
	 *
	 * @param string $meta_type  Resolved meta type.
	 * @param array  $field      Field configuration.
	 * @param string $block_name Block name.
	 * @param string $storage    Storage type.
	 *
	 * @return string
	 */
	public static function filter_storage_type( string $meta_type, array $field, string $block_name, string $storage ): string {
		$type = $field['type'] ?? '';
		if ( ! is_string( $type ) || ! isset( self::$storage_resolvers[ $type ] ) ) {
			return $meta_type;
		}

		$result = call_user_func(
			self::$storage_resolvers[ $type ],
			$meta_type,
			$field,
			$block_name,
			$storage,
			$type
		);

		return is_string( $result ) ? $result : $meta_type;
	}

	/**
	 * Ensure integration hooks are attached once.
	 *
	 * @return void
	 */
	private static function ensure_hooks(): void {
		if ( self::$hooks_attached || ! function_exists( 'add_filter' ) ) {
			return;
		}

		\add_filter( 'blockstudio/field_types', array( self::class, 'filter_field_types' ) );
		\add_filter( 'blockstudio/attribute_builder/handlers', array( self::class, 'filter_handlers' ), 10, 2 );
		\add_filter( 'blockstudio/storage/meta_type', array( self::class, 'filter_storage_type' ), 10, 4 );

		self::$hooks_attached = true;
	}

	/**
	 * Validate custom field type naming.
	 *
	 * @param string $type Field type name.
	 *
	 * @return bool
	 */
	private static function is_valid_type_name( string $type ): bool {
		return 1 === preg_match( '/^([a-z0-9-]+\/)?[a-z0-9-]+:[a-z0-9-]+$/', $type );
	}
}
