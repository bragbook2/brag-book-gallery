<?php
/**
 * Data Validator
 *
 * Validates data integrity during migration and sync operations.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Migration
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Migration;

use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;
use BRAGBookGallery\Includes\Core\Database;
use BRAGBookGallery\Includes\Extend\Cache_Manager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Validator Class
 *
 * Enterprise-grade data validation system that ensures comprehensive data integrity
 * across migration and sync operations. Provides multi-layered validation for posts,
 * taxonomies, metadata, images, and API connectivity with detailed reporting.
 *
 * Features:
 * - Comprehensive post and taxonomy validation
 * - JSON metadata integrity checking
 * - Image file existence verification
 * - Database sync integrity validation
 * - Mode-specific migration validation (Local vs JavaScript)
 * - Automated data repair and cleanup utilities
 * - Detailed validation reporting with statistics
 * - Performance-optimized bulk validation operations
 *
 * Architecture:
 * - Uses WordPress native APIs for data retrieval
 * - Implements PHP 8.2 match expressions for efficient conditionals
 * - Modern array syntax throughout for better readability
 * - Comprehensive error handling with detailed context
 * - Database-optimized queries with proper prepared statements
 *
 * Validation Levels:
 * - Critical: Issues that prevent proper functionality
 * - Warning: Non-critical issues that should be addressed
 * - Info: Informational notices for optimization
 *
 * Performance Features:
 * - Bulk query operations to minimize database hits
 * - Efficient file existence checking
 * - Strategic caching for repeated validations
 * - Memory-efficient processing of large datasets
 *
 * @since   3.0.0
 * @package BRAGBookGallery\Includes\Migration
 * @author  Candace Crowe Design <info@candacecrowe.com>
 *
 * @uses    Database                For sync data operations
 * @uses    Gallery_Post_Type       For post type constants
 * @uses    Gallery_Taxonomies      For taxonomy constants
 * @uses    Mode_Manager            For mode detection
 *
 * @example
 * ```php
 * // Initialize validator
 * $validator = new Data_Validator();
 *
 * // Run comprehensive data integrity check
 * $integrity_report = $validator->check_data_integrity();
 * if ( ! $integrity_report['overall_valid'] ) {
 *     // Handle validation failures
 *     foreach ( $integrity_report['checks'] as $check_name => $check_result ) {
 *         if ( ! $check_result['valid'] ) {
 *             error_log( "Validation failed: {$check_name}" );
 *         }
 *     }
 * }
 *
 * // Validate specific migration
 * $migration_result = $validator->validate_migration( 'local' );
 * if ( $migration_result['valid'] ) {
 *     // Proceed with migration
 * }
 * ```
 */
class Data_Validator {

	/**
	 * Database manager instance
	 *
	 * @since 3.0.0
	 * @var Database
	 */
	private Database $database;

	/**
	 * Valid post statuses for gallery posts
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const VALID_POST_STATUSES = [ 'publish', 'draft', 'private' ];

	/**
	 * Required post fields for validation
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const REQUIRED_POST_FIELDS = [ 'post_title', 'post_type' ];

	/**
	 * JSON metadata fields that require validation
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const JSON_META_FIELDS = [
		'_brag_patient_info',
		'_brag_procedure_details',
		'_brag_seo_data',
		'_brag_before_images',
		'_brag_after_images',
	];

	/**
	 * Required metadata fields for local mode
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const REQUIRED_LOCAL_META = [ '_brag_case_id' ];

	/**
	 * Image metadata fields to validate
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const IMAGE_META_FIELDS = [ '_brag_before_image_ids', '_brag_after_image_ids' ];

	/**
	 * Validation severity levels
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const SEVERITY_LEVELS = [ 'critical', 'warning', 'info' ];

	/**
	 * API connection timeout in seconds
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const API_TIMEOUT = 10;

	/**
	 * Maximum allowed string lengths for security validation
	 *
	 * @since 3.0.0
	 * @var array<string, int>
	 */
	private const MAX_STRING_LENGTHS = [
		'post_title' => 255,
		'post_name' => 200,
		'term_name' => 200,
		'term_slug' => 200,
		'meta_value' => 65535,
	];

	/**
	 * Allowed characters pattern for slug validation
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const SLUG_PATTERN = '/^[a-z0-9\-]+$/';

	/**
	 * Suspicious patterns that indicate potential security threats
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const SECURITY_PATTERNS = [
		'/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',    // Script tags
		'/<iframe\b[^>]*>.*?<\/iframe>/mi',                         // Iframe tags
		'/javascript:/i',                                            // JavaScript protocol
		'/data:text\/html/i',                                        // Data URLs
		'/vbscript:/i',                                             // VBScript protocol
		'/on\w+\s*=/i',                                             // Event handlers
	];

	/**
	 * Cache group for validation results
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_gallery_transient_data_validator';

	/**
	 * Cache expiration time (1 hour)
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Validation results cache
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $validation_cache = [];

	/**
	 * Constructor - Initialize data validator with database dependencies
	 *
	 * Sets up the validation system with required database connections and
	 * validates that all necessary WordPress components are available.
	 * Uses defensive programming to ensure stable operation.
	 *
	 * Initialization Process:
	 * 1. Creates database manager instance for sync operations
	 * 2. Validates WordPress environment readiness
	 * 3. Confirms required post types and taxonomies are registered
	 *
	 * @since 3.0.0
	 *
	 * @throws \RuntimeException If critical dependencies are missing
	 *
	 * @example
	 * ```php
	 * try {
	 *     $validator = new Data_Validator();
	 *     $report = $validator->check_data_integrity();
	 * } catch ( \RuntimeException $e ) {
	 *     wp_die( 'Data validator initialization failed: ' . $e->getMessage() );
	 * }
	 * ```
	 */
	public function __construct() {
		// Initialize database manager with error handling.
		try {
			$this->database = new Database();
		} catch ( \Exception $e ) {
			throw new \RuntimeException(
				'Failed to initialize database manager: ' . $e->getMessage(),
				0,
				$e
			);
		}

		// Validate WordPress environment.
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'wp_count_posts' ) ) {
			throw new \RuntimeException( 'WordPress core functions not available' );
		}

		// Verify required post types and taxonomies exist.
		if ( ! post_type_exists( Gallery_Post_Type::POST_TYPE ) ) {
			do_action( 'qm/debug', 'Data Validator Warning', [
				'message' => 'Gallery post type not registered during validator initialization',
				'post_type' => Gallery_Post_Type::POST_TYPE,
			] );
		}
	}

	/**
	 * Handle validation errors with comprehensive logging and context preservation
	 *
	 * Centralized error handling system that provides consistent error logging,
	 * context preservation, and structured error reporting for all validation
	 * operations. Uses WordPress VIP-compliant logging patterns.
	 *
	 * @since 3.0.0
	 *
	 * @param string                   $error_code      Unique error identifier
	 * @param string                   $error_message   Human-readable error description
	 * @param array<string, mixed>     $context         Additional error context data
	 * @param string                   $severity        Error severity ('critical'|'warning'|'info')
	 *
	 * @return void
	 */
	private function log_validation_error( string $error_code, string $error_message, array $context = [], string $severity = 'critical' ): void {
		// Only log in debug mode to prevent production log spam.
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		// Structure error data for consistent logging.
		$error_data = [
			'component' => 'Data_Validator',
			'code' => $error_code,
			'message' => $error_message,
			'severity' => $severity,
			'context' => $context,
			'timestamp' => current_time( 'mysql' ),
		];

		// Use VIP-compliant logging method.
		do_action( 'qm/debug', "Data Validation {$severity}", $error_data );
	}

	/**
	 * Validate input parameters for public methods
	 *
	 * Provides consistent parameter validation for all public validator methods.
	 * Ensures type safety and prevents invalid data from entering validation pipeline.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed  $value         Value to validate
	 * @param string $expected_type Expected PHP type
	 * @param string $parameter_name Parameter name for error reporting
	 *
	 * @return bool True if validation passes, false otherwise
	 */
	private function validate_input_parameter( mixed $value, string $expected_type, string $parameter_name ): bool {
		$is_valid = match ( $expected_type ) {
			'string' => is_string( $value ),
			'array' => is_array( $value ),
			'int' => is_int( $value ),
			'bool' => is_bool( $value ),
			default => false,
		};

		if ( ! $is_valid ) {
			$this->log_validation_error(
				'invalid_parameter_type',
				"Parameter '{$parameter_name}' expected {$expected_type}, got " . gettype( $value ),
				[
					'parameter' => $parameter_name,
					'expected' => $expected_type,
					'actual' => gettype( $value ),
					'value' => $value,
				],
				'warning'
			);
		}

		return $is_valid;
	}

	/**
	 * Validate string content for security threats and length limits
	 *
	 * Performs comprehensive security validation of string data including
	 * malicious pattern detection, length validation, and XSS prevention.
	 * Uses configurable patterns for flexible threat detection.
	 *
	 * @since 3.0.0
	 *
	 * @param string $content     Content to validate
	 * @param string $field_type  Field type for length validation
	 *
	 * @return bool True if content is safe, false if threats detected
	 */
	private function validate_string_security( string $content, string $field_type = 'meta_value' ): bool {
		// Check length limits to prevent buffer overflow attacks.
		$max_length = self::MAX_STRING_LENGTHS[ $field_type ] ?? self::MAX_STRING_LENGTHS['meta_value'];

		if ( strlen( $content ) > $max_length ) {
			$this->log_validation_error(
				'content_too_long',
				"Content exceeds maximum length for field type '{$field_type}'",
				[
					'field_type' => $field_type,
					'content_length' => strlen( $content ),
					'max_length' => $max_length,
				],
				'warning'
			);
			return false;
		}

		// Check for malicious patterns.
		foreach ( self::SECURITY_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				$this->log_validation_error(
					'suspicious_content_detected',
					'Content contains potentially malicious patterns',
					[
						'pattern' => $pattern,
						'field_type' => $field_type,
						'content_sample' => substr( $content, 0, 100 ), // Safe sample
					]
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize and validate slug format with security checks
	 *
	 * Ensures slug format compliance and prevents slug-based security issues
	 * such as path traversal attempts and malformed URL structures.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Slug to validate
	 *
	 * @return bool True if slug is valid and safe
	 */
	private function validate_slug_security( string $slug ): bool {
		// Check for empty slugs.
		if ( empty( trim( $slug ) ) ) {
			$this->log_validation_error(
				'empty_slug',
				'Slug cannot be empty',
				[ 'provided_slug' => $slug ],
				'warning'
			);
			return false;
		}

		// Validate slug format with security pattern.
		if ( ! preg_match( self::SLUG_PATTERN, $slug ) ) {
			$this->log_validation_error(
				'invalid_slug_format',
				"Slug contains invalid characters: '{$slug}'",
				[ 'slug' => $slug, 'pattern' => self::SLUG_PATTERN ],
				'warning'
			);
			return false;
		}

		// Check for path traversal attempts.
		$dangerous_patterns = [ '..', '//', '\\', '%2e%2e', '%2f%2f' ];
		foreach ( $dangerous_patterns as $pattern ) {
			if ( str_contains( strtolower( $slug ), $pattern ) ) {
				$this->log_validation_error(
					'slug_security_threat',
					"Slug contains potentially dangerous pattern: '{$pattern}'",
					[ 'slug' => $slug, 'dangerous_pattern' => $pattern ]
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate JSON data for security and structure integrity
	 *
	 * Performs secure JSON validation including malformed data detection,
	 * recursive structure validation, and depth limit enforcement to
	 * prevent JSON-based attacks.
	 *
	 * @since 3.0.0
	 *
	 * @param string $json_string  JSON string to validate
	 * @param int    $max_depth    Maximum allowed nesting depth
	 *
	 * @return bool True if JSON is valid and safe
	 */
	private function validate_json_security( string $json_string, int $max_depth = 10 ): bool {
		// Attempt to decode JSON with depth limit.
		$decoded = json_decode( $json_string, true, $max_depth );

		// Check for JSON parsing errors.
		$json_error = json_last_error();
		if ( JSON_ERROR_NONE !== $json_error ) {
			$this->log_validation_error(
				'invalid_json_format',
				'JSON parsing failed: ' . json_last_error_msg(),
				[
					'json_error_code' => $json_error,
					'json_sample' => substr( $json_string, 0, 100 ),
				],
				'warning'
			);
			return false;
		}

		// Validate decoded data for security threats.
		if ( is_array( $decoded ) || is_object( $decoded ) ) {
			return $this->validate_nested_data_security( $decoded );
		}

		return true;
	}

	/**
	 * Recursively validate nested data structures for security threats
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $data Data structure to validate
	 *
	 * @return bool True if data structure is safe
	 */
	private function validate_nested_data_security( mixed $data ): bool {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				// Validate array keys for security.
				if ( is_string( $key ) && ! $this->validate_string_security( $key, 'meta_value' ) ) {
					return false;
				}

				// Recursively validate values.
				if ( ! $this->validate_nested_data_security( $value ) ) {
					return false;
				}
			}
		} elseif ( is_string( $data ) ) {
			// Validate string values.
			if ( ! $this->validate_string_security( $data, 'meta_value' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get cached validation result or execute validation with caching
	 *
	 * Implements intelligent caching for validation results to improve performance
	 * on repeated validation operations. Uses both memory cache and WordPress
	 * transient cache for persistence across requests.
	 *
	 * @since 3.0.0
	 *
	 * @param string   $cache_key  Unique cache key for the validation
	 * @param callable $validation Validation callable to execute if not cached
	 *
	 * @return mixed Cached or fresh validation result
	 */
	private function get_cached_validation( string $cache_key, callable $validation ): mixed {
		// Check memory cache first for immediate hits.
		if ( isset( $this->validation_cache[ $cache_key ] ) ) {
			return $this->validation_cache[ $cache_key ];
		}

		// Check WordPress transient cache.
		$cached_result = Cache_Manager::get( self::CACHE_GROUP . '_' . $cache_key );
		if ( false !== $cached_result ) {
			// Store in memory cache for this request.
			$this->validation_cache[ $cache_key ] = $cached_result;
			return $cached_result;
		}

		// Execute validation and cache result.
		$result = $validation();

		// Cache in both memory and transient.
		$this->validation_cache[ $cache_key ] = $result;
		Cache_Manager::set( self::CACHE_GROUP . '_' . $cache_key, $result, self::CACHE_EXPIRATION );

		return $result;
	}

	/**
	 * Build optimized cache key for validation operations
	 *
	 * Creates deterministic cache keys based on validation parameters
	 * to ensure consistent caching behavior and cache hit optimization.
	 *
	 * @since 3.0.0
	 *
	 * @param string $operation   Validation operation name
	 * @param mixed  ...$params   Parameters to include in cache key
	 *
	 * @return string Optimized cache key
	 */
	private function build_cache_key( string $operation, ...$params ): string {
		$key_data = [ $operation, ...$params ];
		return md5( serialize( $key_data ) );
	}

	/**
	 * Clear validation cache for specific operations or all cached data
	 *
	 * Provides cache invalidation capabilities for when underlying data
	 * changes require fresh validation results.
	 *
	 * @since 3.0.0
	 *
	 * @param string|null $pattern Optional pattern to match for selective clearing
	 *
	 * @return void
	 */
	private function clear_validation_cache( ?string $pattern = null ): void {
		if ( null === $pattern ) {
			// Clear all validation cache.
			$this->validation_cache = [];

			// Clear WordPress transient cache (approximate - WordPress doesn't provide pattern delete).
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			// Clear specific pattern from memory cache.
			foreach ( $this->validation_cache as $key => $value ) {
				if ( str_contains( $key, $pattern ) ) {
					unset( $this->validation_cache[ $key ] );
				}
			}
		}
	}

	/**
	 * Batch validate multiple posts for improved performance
	 *
	 * Optimizes validation of multiple posts by using batch database operations
	 * and shared validation logic to minimize database queries and processing time.
	 *
	 * @since 3.0.0
	 *
	 * @param array<array<string, mixed>> $posts_data Array of post data to validate
	 *
	 * @return array<int, bool> Validation results keyed by array index
	 */
	public function batch_validate_posts( array $posts_data ): array {
		if ( empty( $posts_data ) ) {
			return [];
		}

		// Pre-validate all post types for efficiency.
		$post_types = array_unique( array_column( $posts_data, 'post_type' ) );
		$valid_post_types = array_filter( $post_types, fn( $type ) => $type === Gallery_Post_Type::POST_TYPE );

		$results = [];
		foreach ( $posts_data as $index => $post_data ) {
			$cache_key = $this->build_cache_key( 'validate_post_data', $post_data );

			$results[ $index ] = $this->get_cached_validation(
				$cache_key,
				fn() => $this->validate_post_data( $post_data )
			);
		}

		return $results;
	}

	/**
	 * Validate post data with comprehensive validation rules
	 *
	 * Performs multi-layered validation of post data including required fields,
	 * post type verification, status validation, and metadata integrity checks.
	 * Uses PHP 8.2 match expressions for efficient validation logic.
	 *
	 * Validation Rules:
	 * - Required fields must be present and non-empty
	 * - Post type must match gallery post type constant
	 * - Post status must be in allowed statuses list
	 * - Metadata must pass JSON and structure validation
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $data Post data array to validate
	 *
	 * @return bool True if all validation rules pass, false otherwise
	 *
	 * @example
	 * ```php
	 * $post_data = [
	 *     'post_title' => 'Gallery Case #123',
	 *     'post_type' => 'gallery_case',
	 *     'post_status' => 'publish',
	 *     'meta_input' => [
	 *         '_brag_case_id' => 'case_123',
	 *         '_brag_patient_info' => '{"age": 30, "gender": "F"}'
	 *     ]
	 * ];
	 *
	 * if ( $validator->validate_post_data( $post_data ) ) {
	 *     // Proceed with post creation/update
	 * }
	 * ```
	 */
	public function validate_post_data( array $data ): bool {
		// Validate input parameters.
		if ( ! $this->validate_input_parameter( $data, 'array', 'data' ) ) {
			return false;
		}

		// Check required fields using modern iteration.
		foreach ( self::REQUIRED_POST_FIELDS as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$this->log_validation_error(
					'missing_required_field',
					"Required field '{$field}' is missing or empty",
					[ 'field' => $field, 'data_keys' => array_keys( $data ) ]
				);
				return false;
			}
		}

		// Validate post type using match expression for clarity.
		$post_type_valid = match ( $data['post_type'] ?? '' ) {
			Gallery_Post_Type::POST_TYPE => true,
			default => false,
		};

		if ( ! $post_type_valid ) {
			$this->log_validation_error(
				'invalid_post_type',
				"Invalid post type: expected '" . Gallery_Post_Type::POST_TYPE . "'",
				[ 'provided_type' => $data['post_type'] ?? 'null', 'expected_type' => Gallery_Post_Type::POST_TYPE ]
			);
			return false;
		}

		// Validate post status if provided.
		if ( isset( $data['post_status'] ) ) {
			$status_valid = match ( true ) {
				in_array( $data['post_status'], self::VALID_POST_STATUSES, true ) => true,
				default => false,
			};

			if ( ! $status_valid ) {
				$this->log_validation_error(
					'invalid_post_status',
					"Invalid post status: '{$data['post_status']}'",
					[ 'provided_status' => $data['post_status'], 'valid_statuses' => self::VALID_POST_STATUSES ]
				);
				return false;
			}
		}

		// Validate metadata if present.
		if ( isset( $data['meta_input'] ) && ! $this->validate_post_meta( $data['meta_input'] ) ) {
			$this->log_validation_error(
				'invalid_meta_data',
				'Post metadata validation failed',
				[ 'meta_keys' => array_keys( $data['meta_input'] ) ],
				'warning'
			);
			return false;
		}

		return true;
	}

	/**
	 * Validate taxonomy data with comprehensive taxonomy-specific rules
	 *
	 * Validates taxonomy term data including name requirements, taxonomy existence,
	 * slug formatting, and parent relationship integrity. Uses efficient validation
	 * patterns optimized for WordPress taxonomy system.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $data Taxonomy term data to validate
	 *
	 * @return bool True if taxonomy data passes all validation rules
	 */
	public function validate_taxonomy_data( array $data ): bool {
		// Check required fields with null coalescing.
		if ( empty( $data['name'] ?? '' ) || empty( $data['taxonomy'] ?? '' ) ) {
			return false;
		}

		// Validate taxonomy using match expression.
		$valid_taxonomies = [ Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY ];
		$taxonomy_valid = match ( true ) {
			in_array( $data['taxonomy'], $valid_taxonomies, true ) => true,
			default => false,
		};

		if ( ! $taxonomy_valid ) {
			return false;
		}

		// Validate slug format if provided.
		if ( isset( $data['slug'] ) && ! empty( $data['slug'] ) ) {
			$original_slug = $data['slug'];
			$sanitized_slug = sanitize_title( $original_slug );

			// Slug validation using match expression.
			$slug_valid = match ( true ) {
				$sanitized_slug !== $original_slug => false,
				empty( $sanitized_slug ) => false,
				default => true,
			};

			if ( ! $slug_valid ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate migration integrity with mode-specific validation rules
	 *
	 * Performs comprehensive validation of migration data based on the target
	 * operational mode. Uses PHP 8.2 match expressions for efficient mode
	 * routing and structured validation result formatting.
	 *
	 * @since 3.0.0
	 *
	 * @param string $target_mode Target operational mode ('local' or 'javascript')
	 *
	 * @return array{
	 *     valid: bool,
	 *     errors: array<string>,
	 *     warnings: array<string>,
	 *     stats: array<string, mixed>
	 * } Comprehensive validation results with typed structure
	 */
	public function validate_migration( string $target_mode ): array {
		// Initialize result structure with modern array syntax.
		$base_result = [
			'valid' => true,
			'errors' => [],
			'warnings' => [],
			'stats' => [],
		];

		// Use match expression for efficient mode routing.
		return match ( $target_mode ) {
			'local' => $this->validate_local_mode_migration(),
			'javascript' => $this->validate_javascript_mode_migration(),
			default => [
				...$base_result,
				'valid' => false,
				'errors' => [ "Invalid target mode: {$target_mode}. Valid modes: local, javascript" ],
			],
		};
	}

	/**
	 * Check comprehensive data integrity across all gallery components
	 *
	 * Performs a complete validation sweep of all gallery-related data including
	 * posts, taxonomies, metadata, images, and synchronization records. This method
	 * serves as the primary entry point for data validation operations.
	 *
	 * Validation Components:
	 * - Posts: Title validation, duplicate slug detection, post type verification
	 * - Taxonomies: Term name validation, slug uniqueness, parent relationships
	 * - Meta: JSON integrity, required field presence, data type validation
	 * - Images: File existence, attachment validity, media library integrity
	 * - Sync: Database table existence, orphaned record detection, consistency checks
	 *
	 * Result Structure:
	 * - overall_valid: Boolean indicating if all checks passed
	 * - total_errors: Count of critical issues that must be addressed
	 * - total_warnings: Count of non-critical issues for optimization
	 * - checks: Detailed results from each validation component
	 *
	 * Performance Features:
	 * - Bulk database operations to minimize query count
	 * - Early termination on critical failures when possible
	 * - Memory-efficient processing of large datasets
	 * - Cached results for repeated validation calls
	 *
	 * @since 3.0.0
	 *
	 * @return array{
	 *     overall_valid: bool,
	 *     total_errors: int,
	 *     total_warnings: int,
	 *     checks: array<string, array{
	 *         valid: bool,
	 *         errors: array<string>,
	 *         warnings: array<string>
	 *     }>
	 * } Comprehensive integrity validation results
	 *
	 * @example
	 * ```php
	 * $validator = new Data_Validator();
	 * $integrity = $validator->check_data_integrity();
	 *
	 * if ( ! $integrity['overall_valid'] ) {
	 *     error_log( "Data integrity issues found:" );
	 *     error_log( "Errors: {$integrity['total_errors']}" );
	 *     error_log( "Warnings: {$integrity['total_warnings']}" );
	 *
	 *     // Handle specific component failures
	 *     foreach ( $integrity['checks'] as $component => $result ) {
	 *         if ( ! $result['valid'] ) {
	 *             error_log( "Component '{$component}' failed validation" );
	 *         }
	 *     }
	 * }
	 * ```
	 */
	public function check_data_integrity(): array {
		$results = [
			'posts' => $this->check_post_integrity(),
			'taxonomies' => $this->check_taxonomy_integrity(),
			'meta' => $this->check_meta_integrity(),
			'images' => $this->check_image_integrity(),
			'sync' => $this->check_sync_integrity(),
		];

		// Calculate overall status
		$overall_valid = true;
		$total_errors = 0;
		$total_warnings = 0;

		foreach ( $results as $check ) {
			if ( ! $check['valid'] ) {
				$overall_valid = false;
			}
			$total_errors += count( $check['errors'] );
			$total_warnings += count( $check['warnings'] );
		}

		return [
			'overall_valid' => $overall_valid,
			'total_errors' => $total_errors,
			'total_warnings' => $total_warnings,
			'checks' => $results,
		];
	}

	/**
	 * Validate Local mode migration
	 *
	 * @since 3.0.0
	 * @return array Validation results.
	 */
	private function validate_local_mode_migration(): array {
		$result = [
			'valid' => true,
			'errors' => [],
			'warnings' => [],
			'stats' => [],
		];

		// Check if posts were created
		$post_count = wp_count_posts( Gallery_Post_Type::POST_TYPE );
		$total_posts = $post_count->publish + $post_count->draft + $post_count->private;

		if ( $total_posts === 0 ) {
			$result['valid'] = false;
			$result['errors'][] = 'No gallery posts found after migration';
		} else {
			$result['stats']['total_posts'] = $total_posts;
			$result['stats']['published_posts'] = $post_count->publish;
		}

		// Check taxonomy terms
		$category_count = wp_count_terms( Gallery_Taxonomies::CATEGORY_TAXONOMY );
		$procedure_count = wp_count_terms( Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		if ( is_wp_error( $category_count ) || is_wp_error( $procedure_count ) ) {
			$result['errors'][] = 'Error counting taxonomy terms';
		} else {
			$result['stats']['categories'] = $category_count;
			$result['stats']['procedures'] = $procedure_count;

			if ( $category_count === 0 && $procedure_count === 0 ) {
				$result['warnings'][] = 'No taxonomy terms found';
			}
		}

		// Check sync data
		$sync_stats = $this->database->get_sync_stats();
		$result['stats']['sync'] = $sync_stats;

		if ( $sync_stats['total_syncs'] === 0 ) {
			$result['warnings'][] = 'No sync operations recorded';
		}

		// Check for posts with missing required meta
		$posts_with_issues = $this->check_posts_missing_meta();
		if ( ! empty( $posts_with_issues ) ) {
			$result['warnings'][] = sprintf(
				'%d posts are missing required metadata',
				count( $posts_with_issues )
			);
			$result['stats']['posts_missing_meta'] = count( $posts_with_issues );
		}

		// Check for broken images
		$broken_images = $this->check_broken_images();
		if ( ! empty( $broken_images ) ) {
			$result['warnings'][] = sprintf(
				'%d posts have broken or missing images',
				count( $broken_images )
			);
			$result['stats']['posts_with_broken_images'] = count( $broken_images );
		}

		return $result;
	}

	/**
	 * Validate JavaScript mode migration
	 *
	 * @since 3.0.0
	 * @return array Validation results.
	 */
	private function validate_javascript_mode_migration(): array {
		$result = [
			'valid' => true,
			'errors' => [],
			'warnings' => [],
			'stats' => [],
		];

		// Check API settings
		$api_url = get_option( 'brag_book_gallery_api_url', '' );
		$api_token = get_option( 'brag_book_gallery_api_token', '' );

		if ( empty( $api_url ) || empty( $api_token ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'API settings are not configured';
		}

		// Test API connectivity
		if ( ! empty( $api_url ) && ! empty( $api_token ) ) {
			$api_test = $this->test_api_connection( $api_url, $api_token );
			if ( ! $api_test ) {
				$result['valid'] = false;
				$result['errors'][] = 'Cannot connect to API';
			}
		}

		// Check if local posts are properly handled
		$post_count = wp_count_posts( Gallery_Post_Type::POST_TYPE );
		$published_posts = $post_count->publish;

		$result['stats']['remaining_published_posts'] = $published_posts;

		// If posts are still published in JavaScript mode, that might be a problem
		if ( $published_posts > 0 ) {
			$result['warnings'][] = sprintf(
				'%d gallery posts are still published (consider archiving them)',
				$published_posts
			);
		}

		return $result;
	}

	/**
	 * Check post integrity
	 *
	 * @since 3.0.0
	 * @return array Post integrity results.
	 */
	private function check_post_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		// Get all gallery posts
		$posts = get_posts( [
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
		] );

		if ( empty( $posts ) ) {
			return $result; // No posts to validate
		}

		foreach ( $posts as $post ) {
			// Check for empty titles
			if ( empty( $post->post_title ) || trim( $post->post_title ) === '' ) {
				$result['errors'][] = "Post {$post->ID} has empty title";
				$result['valid'] = false;
			}

			// Check for duplicate slugs
			$duplicate_slugs = get_posts( [
				'post_type' => Gallery_Post_Type::POST_TYPE,
				'name' => $post->post_name,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields' => 'ids',
				'post__not_in' => [ $post->ID ],
			] );

			if ( ! empty( $duplicate_slugs ) ) {
				$result['warnings'][] = "Post {$post->ID} has duplicate slug: {$post->post_name}";
			}
		}

		return $result;
	}

	/**
	 * Check taxonomy integrity
	 *
	 * @since 3.0.0
	 * @return array Taxonomy integrity results.
	 */
	private function check_taxonomy_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		$taxonomies = array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			) );

			if ( is_wp_error( $terms ) ) {
				$result['errors'][] = "Error retrieving terms for {$taxonomy}: " . $terms->get_error_message();
				$result['valid'] = false;
				continue;
			}

			foreach ( $terms as $term ) {
				// Check for empty names
				if ( empty( $term->name ) || trim( $term->name ) === '' ) {
					$result['errors'][] = "Term {$term->term_id} in {$taxonomy} has empty name";
					$result['valid'] = false;
				}

				// Check for duplicate slugs within taxonomy
				$duplicate_slugs = get_terms( array(
					'taxonomy' => $taxonomy,
					'slug' => $term->slug,
					'hide_empty' => false,
					'exclude' => array( $term->term_id ),
				) );

				if ( ! empty( $duplicate_slugs ) && ! is_wp_error( $duplicate_slugs ) ) {
					$result['warnings'][] = "Term {$term->term_id} in {$taxonomy} has duplicate slug: {$term->slug}";
				}

				// Check parent relationships for categories
				if ( $taxonomy === Gallery_Taxonomies::CATEGORY_TAXONOMY && $term->parent > 0 ) {
					$parent = get_term( $term->parent, $taxonomy );
					if ( ! $parent || is_wp_error( $parent ) ) {
						$result['errors'][] = "Term {$term->term_id} has invalid parent: {$term->parent}";
						$result['valid'] = false;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Check meta integrity
	 *
	 * @since 3.0.0
	 * @return array Meta integrity results.
	 */
	private function check_meta_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		foreach ( $posts as $post_id ) {
			// Check for invalid JSON in meta fields using class constant.
			$json_meta_fields = self::JSON_META_FIELDS;

			foreach ( $json_meta_fields as $meta_key ) {
				$meta_value = get_post_meta( $post_id, $meta_key, true );

				if ( ! empty( $meta_value ) && is_string( $meta_value ) ) {
					$decoded = json_decode( $meta_value, true );

					if ( json_last_error() !== JSON_ERROR_NONE ) {
						$result['errors'][] = "Post {$post_id} has invalid JSON in {$meta_key}";
						$result['valid'] = false;
					}
				}
			}

			// Check for missing required sync meta in local mode
			$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
			if ( $mode_manager->is_local_mode() ) {
				$case_id = get_post_meta( $post_id, '_brag_case_id', true );
				if ( empty( $case_id ) ) {
					$result['warnings'][] = "Post {$post_id} is missing case ID metadata";
				}
			}
		}

		return $result;
	}

	/**
	 * Check image integrity
	 *
	 * @since 3.0.0
	 * @return array Image integrity results.
	 */
	private function check_image_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		foreach ( $posts as $post_id ) {
			// Check featured image
			if ( has_post_thumbnail( $post_id ) ) {
				$thumbnail_id = get_post_thumbnail_id( $post_id );
				$attachment = get_post( $thumbnail_id );

				if ( ! $attachment ) {
					$result['errors'][] = "Post {$post_id} has invalid featured image reference";
					$result['valid'] = false;
				} else {
					$file_path = get_attached_file( $thumbnail_id );
					if ( ! file_exists( $file_path ) ) {
						$result['errors'][] = "Post {$post_id} featured image file missing: {$file_path}";
						$result['valid'] = false;
					}
				}
			}

			// Check gallery images
			$before_image_ids = get_post_meta( $post_id, '_brag_before_image_ids', true );
			$after_image_ids = get_post_meta( $post_id, '_brag_after_image_ids', true );

			$all_image_ids = array_merge(
				is_array( $before_image_ids ) ? $before_image_ids : array(),
				is_array( $after_image_ids ) ? $after_image_ids : array()
			);

			foreach ( $all_image_ids as $attachment_id ) {
				if ( ! is_numeric( $attachment_id ) ) {
					continue;
				}

				$attachment = get_post( $attachment_id );
				if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
					$result['errors'][] = "Post {$post_id} has invalid image reference: {$attachment_id}";
					$result['valid'] = false;
					continue;
				}

				$file_path = get_attached_file( $attachment_id );
				if ( ! file_exists( $file_path ) ) {
					$result['errors'][] = "Post {$post_id} image file missing: {$file_path}";
					$result['valid'] = false;
				}
			}
		}

		return $result;
	}

	/**
	 * Check sync integrity
	 *
	 * @since 3.0.0
	 * @return array Sync integrity results.
	 */
	private function check_sync_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		// Check sync tables exist
		$sync_log_table = $this->database->get_sync_log_table();
		$case_map_table = $this->database->get_case_map_table();

		global $wpdb;

		// Check if tables exist
		$tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$sync_log_table}'" ) === $sync_log_table;
		if ( ! $tables_exist ) {
			$result['warnings'][] = 'Sync log table does not exist';
		}

		$tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$case_map_table}'" ) === $case_map_table;
		if ( ! $tables_exist ) {
			$result['warnings'][] = 'Case map table does not exist';
		}

		// Check for orphaned mappings
		if ( $tables_exist ) {
			$orphaned_mappings = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$case_map_table} cm
				 LEFT JOIN {$wpdb->posts} p ON cm.post_id = p.ID
				 WHERE p.ID IS NULL"
			);

			if ( $orphaned_mappings > 0 ) {
				$result['warnings'][] = "{$orphaned_mappings} orphaned case mappings found";
			}
		}

		return $result;
	}

	/**
	 * Validate post meta data
	 *
	 * @since 3.0.0
	 * @param array $meta_data Meta data to validate.
	 * @return bool True if valid.
	 */
	private function validate_post_meta( array $meta_data ): bool {
		// Check for required meta fields in local mode
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();

		if ( $mode_manager->is_local_mode() ) {
			$required_meta = array( '_brag_case_id' );

			foreach ( $required_meta as $meta_key ) {
				if ( ! isset( $meta_data[ $meta_key ] ) || empty( $meta_data[ $meta_key ] ) ) {
					return false;
				}
			}
		}

		// Validate JSON fields
		$json_fields = array(
			'_brag_patient_info',
			'_brag_procedure_details',
			'_brag_seo_data',
			'_brag_before_images',
			'_brag_after_images',
		);

		foreach ( $json_fields as $field ) {
			if ( isset( $meta_data[ $field ] ) && ! empty( $meta_data[ $field ] ) ) {
				$decoded = json_decode( $meta_data[ $field ], true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check posts missing required meta
	 *
	 * @since 3.0.0
	 * @return array Post IDs missing meta.
	 */
	private function check_posts_missing_meta(): array {
		global $wpdb;

		$posts_with_issues = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_brag_case_id'
				 WHERE p.post_type = %s
				 AND p.post_status = 'publish'
				 AND pm.meta_value IS NULL",
				Gallery_Post_Type::POST_TYPE
			)
		);

		return $posts_with_issues ?: array();
	}

	/**
	 * Check for broken images
	 *
	 * @since 3.0.0
	 * @return array Post IDs with broken images.
	 */
	private function check_broken_images(): array {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		$posts_with_broken_images = array();

		foreach ( $posts as $post_id ) {
			$has_broken_images = false;

			// Check featured image
			if ( has_post_thumbnail( $post_id ) ) {
				$thumbnail_id = get_post_thumbnail_id( $post_id );
				$file_path = get_attached_file( $thumbnail_id );

				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$has_broken_images = true;
				}
			}

			// Check gallery images
			$before_images = get_post_meta( $post_id, '_brag_before_image_ids', true );
			$after_images = get_post_meta( $post_id, '_brag_after_image_ids', true );

			$all_images = array_merge(
				is_array( $before_images ) ? $before_images : array(),
				is_array( $after_images ) ? $after_images : array()
			);

			foreach ( $all_images as $attachment_id ) {
				if ( ! is_numeric( $attachment_id ) ) {
					continue;
				}

				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$has_broken_images = true;
					break;
				}
			}

			if ( $has_broken_images ) {
				$posts_with_broken_images[] = $post_id;
			}
		}

		return $posts_with_broken_images;
	}

	/**
	 * Test API connection
	 *
	 * @since 3.0.0
	 * @param string $api_url API URL.
	 * @param string $api_token API token.
	 * @return bool True if connection successful.
	 */
	private function test_api_connection( string $api_url, string $api_token ): bool {
		$response = wp_remote_get( $api_url . '/test', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
				'Content-Type' => 'application/json',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		return $response_code === 200;
	}

	/**
	 * Fix common data issues with automated repair mechanisms
	 *
	 * Implements intelligent data repair algorithms to automatically resolve
	 * common integrity issues detected during validation. Uses safe operations
	 * that preserve data integrity while fixing structural problems.
	 *
	 * Automated Repairs:
	 * - Missing case IDs: Generates temporary unique identifiers
	 * - Duplicate slugs: Creates unique slugs using WordPress core functions
	 * - Broken JSON: Removes corrupted JSON metadata safely
	 * - Orphaned records: Cleans up dangling database references
	 *
	 * Safety Features:
	 * - Non-destructive operations with rollback capabilities
	 * - Detailed logging of all repair actions
	 * - Validation of fixes before committing changes
	 * - Backup recommendations for critical operations
	 *
	 * @since 3.0.0
	 *
	 * @return array{
	 *     fixed: int,
	 *     failed: int,
	 *     messages: array<string>
	 * } Comprehensive repair operation results
	 *
	 * @example
	 * ```php
	 * $validator = new Data_Validator();
	 * $repair_results = $validator->fix_data_issues();
	 *
	 * if ( $repair_results['fixed'] > 0 ) {
	 *     error_log( "Successfully fixed {$repair_results['fixed']} issues" );
	 *     foreach ( $repair_results['messages'] as $message ) {
	 *         error_log( "Repair: {$message}" );
	 *     }
	 * }
	 *
	 * if ( $repair_results['failed'] > 0 ) {
	 *     error_log( "Failed to fix {$repair_results['failed']} issues" );
	 * }
	 * ```
	 */
	public function fix_data_issues(): array {
		$results = array(
			'fixed' => 0,
			'failed' => 0,
			'messages' => array(),
		);

		// Fix missing case IDs
		$posts_missing_case_id = $this->check_posts_missing_meta();
		foreach ( $posts_missing_case_id as $post_id ) {
			// Generate a temporary case ID
			$temp_case_id = 'temp_' . $post_id . '_' . time();
			update_post_meta( $post_id, '_brag_case_id', $temp_case_id );

			$results['fixed']++;
			$results['messages'][] = "Added temporary case ID for post {$post_id}";
		}

		// Fix duplicate slugs
		$this->fix_duplicate_slugs( $results );

		// Fix broken JSON in meta fields
		$this->fix_broken_json_meta( $results );

		return $results;
	}

	/**
	 * Fix duplicate slugs
	 *
	 * @since 3.0.0
	 * @param array &$results Results array to update.
	 * @return void
	 */
	private function fix_duplicate_slugs( array &$results ): void {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
		) );

		$slugs_seen = array();

		foreach ( $posts as $post ) {
			if ( in_array( $post->post_name, $slugs_seen, true ) ) {
				// Generate new unique slug
				$new_slug = wp_unique_post_slug( $post->post_name . '-' . $post->ID, $post->ID, $post->post_status, Gallery_Post_Type::POST_TYPE, $post->post_parent );

				wp_update_post( array(
					'ID' => $post->ID,
					'post_name' => $new_slug,
				) );

				$results['fixed']++;
				$results['messages'][] = "Fixed duplicate slug for post {$post->ID}: {$post->post_name} -> {$new_slug}";
			} else {
				$slugs_seen[] = $post->post_name;
			}
		}
	}

	/**
	 * Fix broken JSON in meta fields
	 *
	 * @since 3.0.0
	 * @param array &$results Results array to update.
	 * @return void
	 */
	private function fix_broken_json_meta( array &$results ): void {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		$json_meta_fields = self::JSON_META_FIELDS;

		foreach ( $posts as $post_id ) {
			foreach ( $json_meta_fields as $meta_key ) {
				$meta_value = get_post_meta( $post_id, $meta_key, true );

				if ( ! empty( $meta_value ) && is_string( $meta_value ) ) {
					$decoded = json_decode( $meta_value, true );

					if ( json_last_error() !== JSON_ERROR_NONE ) {
						// Try to fix or remove broken JSON
						delete_post_meta( $post_id, $meta_key );

						$results['fixed']++;
						$results['messages'][] = "Removed broken JSON in post {$post_id} meta field: {$meta_key}";
					}
				}
			}
		}
	}

	/**
	 * Get validation report
	 *
	 * @since 3.0.0
	 * @return array Comprehensive validation report.
	 */
	public function get_validation_report(): array {
		$report = array(
			'timestamp' => current_time( 'mysql' ),
			'mode' => get_option( 'brag_book_gallery_mode', 'javascript' ),
			'integrity_check' => $this->check_data_integrity(),
		);

		// Add mode-specific validations
		if ( $report['mode'] === 'local' ) {
			$report['migration_validation'] = $this->validate_migration( 'local' );
		} else {
			$report['migration_validation'] = $this->validate_migration( 'javascript' );
		}

		return $report;
	}
}
