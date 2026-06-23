<?php
/**
 * Field Type Registry class.
 *
 * @package Blockstudio
 */

namespace Blockstudio;

/**
 * Registry for custom field type definitions.
 *
 * Third parties can register field types via the blockstudio/field_types filter
 * or by calling register() directly.
 *
 * @since 7.3.3
 */
final class Field_Type_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Field_Type_Registry|null
	 */
	private static ?Field_Type_Registry $instance = null;

	/**
	 * Registered custom field types.
	 *
	 * @var array<string, array>
	 */
	private array $types = array();

	/**
	 * Whether filter-based registration has been loaded.
	 *
	 * @var bool
	 */
	private bool $loaded_from_filters = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Field_Type_Registry
	 */
	public static function instance(): Field_Type_Registry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Singleton.
	}

	/**
	 * Register a custom field type.
	 *
	 * Supported keys include:
	 * - attribute (required): string|array|null
	 * - default: mixed
	 * - options: bool
	 * - multiple: bool
	 * - container: bool
	 * - producesAttribute: bool
	 * - storageType: string
	 *
	 * @param string $type       Field type name.
	 * @param array  $definition Field type definition.
	 *
	 * @return void
	 */
	public function register( string $type, array $definition ): void {
		if ( '' === $type || ! array_key_exists( 'attribute', $definition ) ) {
			return;
		}

		$this->types[ $type ] = $definition;
	}

	/**
	 * Unregister a custom field type.
	 *
	 * @param string $type Field type name.
	 *
	 * @return void
	 */
	public function unregister( string $type ): void {
		unset( $this->types[ $type ] );
	}

	/**
	 * Get a registered field type definition.
	 *
	 * @param string $type Field type name.
	 *
	 * @return array|null
	 */
	public function get( string $type ): ?array {
		$this->load_from_filters();
		return $this->types[ $type ] ?? null;
	}

	/**
	 * Get all registered field type definitions.
	 *
	 * @return array<string, array>
	 */
	public function all(): array {
		$this->load_from_filters();
		return $this->types;
	}

	/**
	 * Reset registry (useful in tests).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->types               = array();
		$this->loaded_from_filters = false;
	}

	/**
	 * Load custom field types from filter once.
	 *
	 * @return void
	 */
	private function load_from_filters(): void {
		if ( $this->loaded_from_filters ) {
			return;
		}

		/**
		 * Filter custom field type registrations.
		 *
		 * @param array<string, array> $types Field type definitions keyed by type.
		 */
		$types = apply_filters( 'blockstudio/field_types', array() );

		if ( is_array( $types ) ) {
			foreach ( $types as $type => $definition ) {
				if ( is_string( $type ) && is_array( $definition ) ) {
					$this->register( $type, $definition );
				}
			}
		}

		$this->loaded_from_filters = true;
	}
}
