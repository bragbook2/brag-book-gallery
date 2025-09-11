<?php
/**
 * Gallery Taxonomies - Enterprise-grade taxonomy management system
 *
 * Comprehensive taxonomy system for BRAGBook Gallery plugin.
 * Manages medical categories and procedures with advanced features
 * for filtering, searching, and hierarchical organization.
 *
 * Features:
 * - Hierarchical medical categories
 * - Non-hierarchical procedures
 * - Custom meta fields for API integration
 * - REST API support
 * - Advanced filtering capabilities
 * - Performance optimization with caching
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Taxonomies
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Taxonomies;

use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Extend\Cache_Manager;
use WP_Term;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enterprise Taxonomy Management System
 *
 * Orchestrates comprehensive taxonomy operations:
 *
 * Core Responsibilities:
 * - Register and configure medical taxonomies
 * - Manage custom meta fields for taxonomies
 * - Provide admin UI for taxonomy management
 * - Handle API synchronization metadata
 * - Support filtering and search operations
 * - Enable REST API integration
 *
 * @since 3.0.0
 */
final class Gallery_Taxonomies {

	/**
	 * Medical categories taxonomy key
	 *
	 * @since 3.0.0
	 */
	public const CATEGORY_TAXONOMY = 'brag_category';

	/**
	 * Procedures taxonomy key
	 *
	 * @since 3.0.0
	 */
	public const PROCEDURE_TAXONOMY = 'brag_procedure';

	/**
	 * Cache duration constants
	 *
	 * @since 3.0.0
	 */
	private const CACHE_TTL_SHORT = 300;     // 5 minutes
	private const CACHE_TTL_MEDIUM = 1800;   // 30 minutes
	private const CACHE_TTL_LONG = 3600;     // 1 hour
	private const CACHE_TTL_EXTENDED = 7200; // 2 hours

	/**
	 * Maximum string lengths for security
	 *
	 * @since 3.0.0
	 */
	private const MAX_TERM_NAME_LENGTH = 200;
	private const MAX_SLUG_LENGTH = 200;
	private const MAX_DESCRIPTION_LENGTH = 500;

	/**
	 * Performance metrics storage
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $performance_metrics = [];

	/**
	 * Memory cache for optimization
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $memory_cache = [];

	/**
	 * Error tracking for reporting
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $error_log = [];

	/**
	 * Initialization status
	 *
	 * @since 3.0.0
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Only initialize if in Local Mode
		$current_mode = get_option( 'brag_book_gallery_current_mode', 'javascript' );
		if ( $current_mode === 'local' ) {
			$this->init();
		}
	}

	/**
	 * Initialize taxonomies with comprehensive hooks
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		if ( $this->initialized ) {
			return;
		}

		// Register taxonomies with priority
		add_action( 'init', [ $this, 'register_categories' ], 5 );
		add_action( 'init', [ $this, 'register_procedures' ], 5 );
		add_action( 'init', [ $this, 'register_term_meta' ], 10 );

		// Add form fields for taxonomies
		add_action( self::CATEGORY_TAXONOMY . '_add_form_fields', [ $this, 'add_category_fields' ] );
		add_action( self::CATEGORY_TAXONOMY . '_edit_form_fields', [ $this, 'edit_category_fields' ] );
		add_action( self::PROCEDURE_TAXONOMY . '_add_form_fields', [ $this, 'add_procedure_fields' ] );
		add_action( self::PROCEDURE_TAXONOMY . '_edit_form_fields', [ $this, 'edit_procedure_fields' ] );

		// Save term meta with validation
		add_action( 'created_' . self::CATEGORY_TAXONOMY, [ $this, 'save_category_meta' ] );
		add_action( 'edited_' . self::CATEGORY_TAXONOMY, [ $this, 'save_category_meta' ] );
		add_action( 'created_' . self::PROCEDURE_TAXONOMY, [ $this, 'save_procedure_meta' ] );
		add_action( 'edited_' . self::PROCEDURE_TAXONOMY, [ $this, 'save_procedure_meta' ] );

		// Add cache clearing hooks
		add_action( 'created_term', [ $this, 'clear_taxonomy_cache' ], 10, 3 );
		add_action( 'edited_term', [ $this, 'clear_taxonomy_cache' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'clear_taxonomy_cache' ], 10, 3 );

		// Add custom columns
		add_filter( 'manage_edit-' . self::CATEGORY_TAXONOMY . '_columns', [ $this, 'add_custom_columns' ] );
		add_filter( 'manage_edit-' . self::PROCEDURE_TAXONOMY . '_columns', [ $this, 'add_custom_columns' ] );
		add_filter( 'manage_' . self::CATEGORY_TAXONOMY . '_custom_column', [ $this, 'render_custom_column' ], 10, 3 );
		add_filter( 'manage_' . self::PROCEDURE_TAXONOMY . '_custom_column', [ $this, 'render_custom_column' ], 10, 3 );

		$this->initialized = true;
	}

	/**
	 * Register medical categories taxonomy with comprehensive configuration
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_categories(): void {
		$start_time = microtime( true );

		try {
			$labels = [
				'name'                       => _x( 'Medical Categories', 'taxonomy general name', 'brag-book-gallery' ),
				'singular_name'              => _x( 'Medical Category', 'taxonomy singular name', 'brag-book-gallery' ),
				'search_items'               => __( 'Search Medical Categories', 'brag-book-gallery' ),
				'popular_items'              => __( 'Popular Medical Categories', 'brag-book-gallery' ),
				'all_items'                  => __( 'All Medical Categories', 'brag-book-gallery' ),
				'parent_item'                => __( 'Parent Medical Category', 'brag-book-gallery' ),
				'parent_item_colon'          => __( 'Parent Medical Category:', 'brag-book-gallery' ),
				'edit_item'                  => __( 'Edit Medical Category', 'brag-book-gallery' ),
				'update_item'                => __( 'Update Medical Category', 'brag-book-gallery' ),
				'add_new_item'               => __( 'Add New Medical Category', 'brag-book-gallery' ),
				'new_item_name'              => __( 'New Medical Category Name', 'brag-book-gallery' ),
				'separate_items_with_commas' => __( 'Separate categories with commas', 'brag-book-gallery' ),
				'add_or_remove_items'        => __( 'Add or remove categories', 'brag-book-gallery' ),
				'choose_from_most_used'      => __( 'Choose from the most used categories', 'brag-book-gallery' ),
				'not_found'                  => __( 'No categories found.', 'brag-book-gallery' ),
				'menu_name'                  => __( 'Medical Categories', 'brag-book-gallery' ),
				'back_to_items'              => __( '← Back to Medical Categories', 'brag-book-gallery' ),
			];

			$args = [
				'hierarchical'          => true,
				'labels'                => $labels,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'show_in_nav_menus'     => true,
				'show_tagcloud'         => false,
				'query_var'             => true,
				'rewrite'               => [
					'slug'         => apply_filters( 'brag_book_gallery_category_slug', 'gallery-category' ),
					'with_front'   => false,
					'hierarchical' => true,
				],
				'show_in_rest'          => true,
				'rest_base'             => 'brag-categories',
				'rest_controller_class' => 'WP_REST_Terms_Controller',
				'capabilities'          => $this->get_taxonomy_capabilities( 'category' ),
				'update_count_callback' => [ $this, 'update_term_count' ],
			];

			register_taxonomy( self::CATEGORY_TAXONOMY, [ Gallery_Post_Type::POST_TYPE ], $args );

			$this->track_performance( 'register_categories', microtime( true ) - $start_time );

		} catch ( Exception $e ) {
			$this->log_error( 'register_categories', $e->getMessage() );
		}
	}

	/**
	 * Register procedures taxonomy with comprehensive configuration
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_procedures(): void {
		$start_time = microtime( true );

		try {
			$labels = [
				'name'                       => _x( 'Procedures', 'taxonomy general name', 'brag-book-gallery' ),
				'singular_name'              => _x( 'Procedure', 'taxonomy singular name', 'brag-book-gallery' ),
				'search_items'               => __( 'Search Procedures', 'brag-book-gallery' ),
				'popular_items'              => __( 'Popular Procedures', 'brag-book-gallery' ),
				'all_items'                  => __( 'All Procedures', 'brag-book-gallery' ),
				'parent_item'                => null,
				'parent_item_colon'          => null,
				'edit_item'                  => __( 'Edit Procedure', 'brag-book-gallery' ),
				'update_item'                => __( 'Update Procedure', 'brag-book-gallery' ),
				'add_new_item'               => __( 'Add New Procedure', 'brag-book-gallery' ),
				'new_item_name'              => __( 'New Procedure Name', 'brag-book-gallery' ),
				'separate_items_with_commas' => __( 'Separate procedures with commas', 'brag-book-gallery' ),
				'add_or_remove_items'        => __( 'Add or remove procedures', 'brag-book-gallery' ),
				'choose_from_most_used'      => __( 'Choose from the most used procedures', 'brag-book-gallery' ),
				'not_found'                  => __( 'No procedures found.', 'brag-book-gallery' ),
				'menu_name'                  => __( 'Procedures', 'brag-book-gallery' ),
				'back_to_items'              => __( '← Back to Procedures', 'brag-book-gallery' ),
			];

			$args = [
				'hierarchical'          => false,
				'labels'                => $labels,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'show_in_nav_menus'     => true,
				'show_tagcloud'         => true,
				'query_var'             => true,
				'rewrite'               => [
					'slug'         => apply_filters( 'brag_book_gallery_procedure_slug', 'gallery-procedure' ),
					'with_front'   => false,
					'hierarchical' => false,
				],
				'show_in_rest'          => true,
				'rest_base'             => 'brag-procedures',
				'rest_controller_class' => 'WP_REST_Terms_Controller',
				'capabilities'          => $this->get_taxonomy_capabilities( 'procedure' ),
				'update_count_callback' => [ $this, 'update_term_count' ],
			];

			register_taxonomy( self::PROCEDURE_TAXONOMY, [ Gallery_Post_Type::POST_TYPE ], $args );

			$this->track_performance( 'register_procedures', microtime( true ) - $start_time );

		} catch ( Exception $e ) {
			$this->log_error( 'register_procedures', $e->getMessage() );
		}
	}

	/**
	 * Register term meta fields with validation and sanitization
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_term_meta(): void {
		$start_time = microtime( true );

		try {
			// Category meta fields
			register_term_meta(
				self::CATEGORY_TAXONOMY,
				'brag_category_api_id',
				[
					'type'              => 'integer',
					'description'       => 'API category ID',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => fn() => current_user_can( 'edit_term_meta' ),
				]
			);

			register_term_meta(
				self::CATEGORY_TAXONOMY,
				'brag_category_order',
				[
					'type'              => 'integer',
					'description'       => 'Display order',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => fn() => current_user_can( 'edit_term_meta' ),
				]
			);

			// Procedure meta fields
			register_term_meta(
				self::PROCEDURE_TAXONOMY,
				'brag_procedure_api_id',
				[
					'type'              => 'integer',
					'description'       => 'API procedure ID',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => fn() => current_user_can( 'edit_term_meta' ),
				]
			);

			register_term_meta(
				self::PROCEDURE_TAXONOMY,
				'brag_procedure_slug_name',
				[
					'type'              => 'string',
					'description'       => 'URL slug name',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_title',
					'auth_callback'     => fn() => current_user_can( 'edit_term_meta' ),
				]
			);

			register_term_meta(
				self::PROCEDURE_TAXONOMY,
				'brag_procedure_nudity',
				[
					'type'              => 'boolean',
					'description'       => 'Contains nudity',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => fn() => current_user_can( 'edit_term_meta' ),
				]
			);

			register_term_meta(
				self::PROCEDURE_TAXONOMY,
				'brag_procedure_case_count',
				[
					'type'              => 'integer',
					'description'       => 'Total case count',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => fn() => current_user_can( 'edit_term_meta' ),
				]
			);

			/**
			 * Fires after term meta fields are registered.
			 *
			 * @since 3.0.0
			 */
			do_action( 'brag_book_gallery_term_meta_registered' );

			$this->track_performance( 'register_term_meta', microtime( true ) - $start_time );

		} catch ( Exception $e ) {
			$this->log_error( 'register_term_meta', $e->getMessage() );
		}
	}

	/**
	 * Add category form fields
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_category_fields(): void {
		?>
		<div class="form-field">
			<label for="brag_category_api_id"><?php esc_html_e( 'API Category ID', 'brag-book-gallery' ); ?></label>
			<input type="number" name="brag_category_api_id" id="brag_category_api_id" value="">
			<p class="description"><?php esc_html_e( 'The category ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
		</div>
		<div class="form-field">
			<label for="brag_category_order"><?php esc_html_e( 'Display Order', 'brag-book-gallery' ); ?></label>
			<input type="number" name="brag_category_order" id="brag_category_order" value="0">
			<p class="description"><?php esc_html_e( 'Order for displaying this category.', 'brag-book-gallery' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Edit category form fields
	 *
	 * @since 3.0.0
	 * @param WP_Term $term Current term object.
	 * @return void
	 */
	public function edit_category_fields( $term ): void {
		$api_id = get_term_meta( $term->term_id, 'brag_category_api_id', true );
		$order = get_term_meta( $term->term_id, 'brag_category_order', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_category_api_id"><?php esc_html_e( 'API Category ID', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="brag_category_api_id" id="brag_category_api_id" value="<?php echo esc_attr( $api_id ); ?>">
				<p class="description"><?php esc_html_e( 'The category ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_category_order"><?php esc_html_e( 'Display Order', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="brag_category_order" id="brag_category_order" value="<?php echo esc_attr( $order ); ?>">
				<p class="description"><?php esc_html_e( 'Order for displaying this category.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Add procedure form fields
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_procedure_fields(): void {
		?>
		<div class="form-field">
			<label for="brag_procedure_api_id"><?php esc_html_e( 'API Procedure ID', 'brag-book-gallery' ); ?></label>
			<input type="number" name="brag_procedure_api_id" id="brag_procedure_api_id" value="">
			<p class="description"><?php esc_html_e( 'The procedure ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
		</div>
		<div class="form-field">
			<label for="brag_procedure_slug_name"><?php esc_html_e( 'URL Slug', 'brag-book-gallery' ); ?></label>
			<input type="text" name="brag_procedure_slug_name" id="brag_procedure_slug_name" value="">
			<p class="description"><?php esc_html_e( 'URL-friendly slug for this procedure.', 'brag-book-gallery' ); ?></p>
		</div>
		<div class="form-field">
			<label for="brag_procedure_nudity"><?php esc_html_e( 'Contains Nudity', 'brag-book-gallery' ); ?></label>
			<input type="checkbox" name="brag_procedure_nudity" id="brag_procedure_nudity" value="1">
			<p class="description"><?php esc_html_e( 'Check if this procedure contains nudity.', 'brag-book-gallery' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Edit procedure form fields
	 *
	 * @since 3.0.0
	 * @param WP_Term $term Current term object.
	 * @return void
	 */
	public function edit_procedure_fields( $term ): void {
		$api_id = get_term_meta( $term->term_id, 'brag_procedure_api_id', true );
		$slug_name = get_term_meta( $term->term_id, 'brag_procedure_slug_name', true );
		$nudity = get_term_meta( $term->term_id, 'brag_procedure_nudity', true );
		$case_count = get_term_meta( $term->term_id, 'brag_procedure_case_count', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_procedure_api_id"><?php esc_html_e( 'API Procedure ID', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="brag_procedure_api_id" id="brag_procedure_api_id" value="<?php echo esc_attr( $api_id ); ?>">
				<p class="description"><?php esc_html_e( 'The procedure ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_procedure_slug_name"><?php esc_html_e( 'URL Slug', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="text" name="brag_procedure_slug_name" id="brag_procedure_slug_name" value="<?php echo esc_attr( $slug_name ); ?>">
				<p class="description"><?php esc_html_e( 'URL-friendly slug for this procedure.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_procedure_nudity"><?php esc_html_e( 'Contains Nudity', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="checkbox" name="brag_procedure_nudity" id="brag_procedure_nudity" value="1" <?php checked( $nudity, '1' ); ?>>
				<p class="description"><?php esc_html_e( 'Check if this procedure contains nudity.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label><?php esc_html_e( 'Case Count', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<strong><?php echo esc_html( $case_count ?: '0' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Total number of cases for this procedure.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save category meta with validation and security
	 *
	 * @since 3.0.0
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_category_meta( int $term_id ): void {
		try {
			// Verify user capabilities
			if ( ! current_user_can( 'edit_term', $term_id ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
			if ( isset( $_POST['brag_category_api_id'] ) ) {
				$api_id = $this->sanitize_and_validate_int( $_POST['brag_category_api_id'], 0, PHP_INT_MAX );
				update_term_meta( $term_id, 'brag_category_api_id', $api_id );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
			if ( isset( $_POST['brag_category_order'] ) ) {
				$order = $this->sanitize_and_validate_int( $_POST['brag_category_order'], 0, 9999 );
				update_term_meta( $term_id, 'brag_category_order', $order );
			}

			// Clear cache after save
			$this->clear_term_cache( $term_id, self::CATEGORY_TAXONOMY );

			/**
			 * Fires after category meta is saved.
			 *
			 * @since 3.0.0
			 * @param int $term_id The term ID.
			 */
			do_action( 'brag_book_gallery_category_meta_saved', $term_id );

		} catch ( Exception $e ) {
			$this->log_error( 'save_category_meta', $e->getMessage() );
		}
	}

	/**
	 * Save procedure meta with validation and security
	 *
	 * @since 3.0.0
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_procedure_meta( int $term_id ): void {
		try {
			// Verify user capabilities
			if ( ! current_user_can( 'edit_term', $term_id ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
			if ( isset( $_POST['brag_procedure_api_id'] ) ) {
				$api_id = $this->sanitize_and_validate_int( $_POST['brag_procedure_api_id'], 0, PHP_INT_MAX );
				update_term_meta( $term_id, 'brag_procedure_api_id', $api_id );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
			if ( isset( $_POST['brag_procedure_slug_name'] ) ) {
				$slug_name = $this->sanitize_string( $_POST['brag_procedure_slug_name'], self::MAX_SLUG_LENGTH );
				update_term_meta( $term_id, 'brag_procedure_slug_name', $slug_name );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
			$nudity = isset( $_POST['brag_procedure_nudity'] ) && $_POST['brag_procedure_nudity'] === '1';
			update_term_meta( $term_id, 'brag_procedure_nudity', $nudity ? '1' : '0' );

			// Clear cache after save
			$this->clear_term_cache( $term_id, self::PROCEDURE_TAXONOMY );

			/**
			 * Fires after procedure meta is saved.
			 *
			 * @since 3.0.0
			 * @param int $term_id The term ID.
			 */
			do_action( 'brag_book_gallery_procedure_meta_saved', $term_id );

		} catch ( Exception $e ) {
			$this->log_error( 'save_procedure_meta', $e->getMessage() );
		}
	}

	/**
	 * Clear taxonomy cache
	 *
	 * @since 3.0.0
	 * @param int    $term_id Term ID.
	 * @param int    $tt_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 */
	public function clear_taxonomy_cache( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! in_array( $taxonomy, [ self::CATEGORY_TAXONOMY, self::PROCEDURE_TAXONOMY ], true ) ) {
			return;
		}

		// Clear specific term cache
		$this->clear_term_cache( $term_id, $taxonomy );

		// Clear general taxonomy cache
		Cache_Manager::delete( 'brag_book_gallery_transient_' . $taxonomy . '_terms' );
		Cache_Manager::delete( 'brag_book_gallery_transient_' . $taxonomy . '_hierarchy' );

		// Clear memory cache
		$this->memory_cache = [];
	}

	/**
	 * Clear specific term cache
	 *
	 * @since 3.0.0
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy name.
	 */
	private function clear_term_cache( int $term_id, string $taxonomy ): void {
		clean_term_cache( $term_id, $taxonomy );
		// Caching disabled
	}

	/**
	 * Add custom columns to taxonomy list
	 *
	 * @since 3.0.0
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_custom_columns( array $columns ): array {
		$new_columns = [];

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( $key === 'name' ) {
				$new_columns['api_id'] = __( 'API ID', 'brag-book-gallery' );
				$new_columns['case_count'] = __( 'Cases', 'brag-book-gallery' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content
	 *
	 * @since 3.0.0
	 * @param string $content Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id Term ID.
	 * @return string Column content.
	 */
	public function render_custom_column( string $content, string $column_name, int $term_id ): string {
		$taxonomy = get_current_screen()->taxonomy ?? '';

		switch ( $column_name ) {
			case 'api_id':
				$meta_key = match ( $taxonomy ) {
					self::CATEGORY_TAXONOMY  => 'brag_category_api_id',
					self::PROCEDURE_TAXONOMY => 'brag_procedure_api_id',
					default                  => '',
				};

				if ( $meta_key ) {
					$api_id = get_term_meta( $term_id, $meta_key, true );
					$content = $api_id ? esc_html( $api_id ) : '—';
				}
				break;

			case 'case_count':
				$term = get_term( $term_id, $taxonomy );
				if ( ! is_wp_error( $term ) ) {
					$content = number_format_i18n( $term->count );
				}
				break;
		}

		return $content;
	}

	/**
	 * Get taxonomy capabilities
	 *
	 * @since 3.0.0
	 * @param string $type Taxonomy type (category or procedure).
	 * @return array<string, string> Capabilities array.
	 */
	private function get_taxonomy_capabilities( string $type ): array {
		$base = match ( $type ) {
			'category'  => 'manage_categories',
			'procedure' => 'manage_categories',
			default     => 'manage_categories',
		};

		return [
			'manage_terms' => $base,
			'edit_terms'   => $base,
			'delete_terms' => $base,
			'assign_terms' => 'edit_posts',
		];
	}

	/**
	 * Update term count callback
	 *
	 * @since 3.0.0
	 * @param array<int> $terms Term IDs.
	 * @param object     $taxonomy Taxonomy object.
	 */
	public function update_term_count( array $terms, object $taxonomy ): void {
		global $wpdb;

		foreach ( $terms as $term ) {
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->term_relationships
				WHERE term_taxonomy_id = %d",
				$term
			) );

			$wpdb->update(
				$wpdb->term_taxonomy,
				[ 'count' => $count ],
				[ 'term_taxonomy_id' => $term ]
			);
		}
	}

	/**
	 * Sanitize and validate integer
	 *
	 * @since 3.0.0
	 * @param mixed $value Value to sanitize.
	 * @param int   $min Minimum value.
	 * @param int   $max Maximum value.
	 * @return int Sanitized integer.
	 */
	private function sanitize_and_validate_int( $value, int $min = 0, int $max = PHP_INT_MAX ): int {
		$value = absint( $value );
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Sanitize string with length limit
	 *
	 * @since 3.0.0
	 * @param mixed $string String to sanitize.
	 * @param int   $max_length Maximum length.
	 * @return string Sanitized string.
	 */
	private function sanitize_string( $string, int $max_length ): string {
		$string = sanitize_text_field( (string) $string );
		return mb_substr( $string, 0, $max_length );
	}

	/**
	 * Log error message
	 *
	 * @since 3.0.0
	 * @param string $context Error context.
	 * @param string $message Error message.
	 */
	private function log_error( string $context, string $message ): void {
		$this->error_log[ $context ][] = [
			'time'    => current_time( 'mysql' ),
			'message' => $message,
		];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[BRAGBook Taxonomies] {$context}: {$message}" );
		}
	}

	/**
	 * Track performance metrics
	 *
	 * @since 3.0.0
	 * @param string $operation Operation name.
	 * @param float  $duration Operation duration.
	 */
	private function track_performance( string $operation, float $duration ): void {
		if ( ! isset( $this->performance_metrics[ $operation ] ) ) {
			$this->performance_metrics[ $operation ] = [
				'count'   => 0,
				'total'   => 0,
				'min'     => PHP_FLOAT_MAX,
				'max'     => 0,
			];
		}

		$metrics = &$this->performance_metrics[ $operation ];
		$metrics['count']++;
		$metrics['total'] += $duration;
		$metrics['min'] = min( $metrics['min'], $duration );
		$metrics['max'] = max( $metrics['max'], $duration );
		$metrics['average'] = $metrics['total'] / $metrics['count'];
	}

	/**
	 * Get terms with caching
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $args Query arguments.
	 * @return array<WP_Term> Array of terms.
	 */
	public function get_terms_cached( string $taxonomy, array $args = [] ): array {
		$cache_key = 'brag_book_gallery_transient_terms_' . $taxonomy . serialize( $args );

		// Check memory cache
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		// Check transient cache
		$cached = Cache_Manager::get( 'brag_book_gallery_transient_' . $cache_key );
		if ( false !== $cached ) {
			$this->memory_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Get fresh data
		$defaults = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );
		$terms = get_terms( $args );

		if ( ! is_wp_error( $terms ) ) {
			// Cache the results
			$this->memory_cache[ $cache_key ] = $terms;
			Cache_Manager::set( 'brag_book_gallery_transient_' . $cache_key, $terms, self::CACHE_TTL_MEDIUM );
		}

		return is_wp_error( $terms ) ? [] : $terms;
	}

	/**
	 * Get hierarchical term tree
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $parent Parent term ID.
	 * @return array<mixed> Hierarchical term tree.
	 */
	public function get_term_hierarchy( string $taxonomy, int $parent = 0 ): array {
		$cache_key = "brag_book_gallery_transient_hierarchy_{$taxonomy}_{$parent}";

		// Check cache
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		$terms = $this->get_terms_cached( $taxonomy, [
			'parent' => $parent,
		] );

		$hierarchy = [];

		foreach ( $terms as $term ) {
			$hierarchy[] = [
				'term'     => $term,
				'children' => $this->get_term_hierarchy( $taxonomy, $term->term_id ),
			];
		}

		$this->memory_cache[ $cache_key ] = $hierarchy;
		return $hierarchy;
	}

	/**
	 * Get performance metrics
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Performance metrics.
	 */
	public function get_performance_metrics(): array {
		return $this->performance_metrics;
	}

	/**
	 * Bulk import terms from array
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $terms Array of term data.
	 * @return array<string, mixed> Import results.
	 */
	public function bulk_import_terms( string $taxonomy, array $terms ): array {
		$start_time = microtime( true );
		$results = [
			'success' => [],
			'failed'  => [],
			'updated' => [],
		];

		try {
			foreach ( $terms as $term_data ) {
				$result = $this->import_single_term( $taxonomy, $term_data );

				if ( is_wp_error( $result ) ) {
					$results['failed'][] = [
						'data'  => $term_data,
						'error' => $result->get_error_message(),
					];
				} elseif ( $result['updated'] ) {
					$results['updated'][] = $result['term_id'];
				} else {
					$results['success'][] = $result['term_id'];
				}
			}

			// Clear cache after bulk import
			$this->clear_taxonomy_cache( 0, 0, $taxonomy );

			$this->track_performance( 'bulk_import_terms', microtime( true ) - $start_time );

		} catch ( Exception $e ) {
			$this->log_error( 'bulk_import_terms', $e->getMessage() );
		}

		return $results;
	}

	/**
	 * Import single term with metadata
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $term_data Term data including meta.
	 * @return array|\WP_Error Import result or error.
	 */
	private function import_single_term( string $taxonomy, array $term_data ) {
		// Validate required fields
		if ( empty( $term_data['name'] ) ) {
			return new \WP_Error( 'missing_name', 'Term name is required' );
		}

		$name = $this->sanitize_string( $term_data['name'], self::MAX_TERM_NAME_LENGTH );
		$slug = ! empty( $term_data['slug'] )
			? $this->sanitize_string( $term_data['slug'], self::MAX_SLUG_LENGTH )
			: sanitize_title( $name );

		// Check if term exists
		$existing_term = get_term_by( 'slug', $slug, $taxonomy );
		$updated = false;

		if ( $existing_term ) {
			$term_id = $existing_term->term_id;
			$updated = true;

			// Update term if needed
			if ( $existing_term->name !== $name ) {
				wp_update_term( $term_id, $taxonomy, [
					'name' => $name,
					'slug' => $slug,
				] );
			}
		} else {
			// Create new term
			$term_args = [
				'slug' => $slug,
			];

			if ( ! empty( $term_data['parent'] ) ) {
				$term_args['parent'] = absint( $term_data['parent'] );
			}

			if ( ! empty( $term_data['description'] ) ) {
				$term_args['description'] = $this->sanitize_string(
					$term_data['description'],
					self::MAX_DESCRIPTION_LENGTH
				);
			}

			$term = wp_insert_term( $name, $taxonomy, $term_args );

			if ( is_wp_error( $term ) ) {
				return $term;
			}

			$term_id = $term['term_id'];
		}

		// Update term meta
		if ( ! empty( $term_data['meta'] ) && is_array( $term_data['meta'] ) ) {
			foreach ( $term_data['meta'] as $meta_key => $meta_value ) {
				update_term_meta( $term_id, $meta_key, $meta_value );
			}
		}

		return [
			'term_id' => $term_id,
			'updated' => $updated,
		];
	}

	/**
	 * Export terms with metadata
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @return array<mixed> Exported terms.
	 */
	public function export_terms( string $taxonomy ): array {
		$terms = $this->get_terms_cached( $taxonomy, [
			'hide_empty' => false,
		] );

		$exported = array_map( function( $term ) {
			$meta = [];

			// Get all term meta
			$meta_keys = $this->get_taxonomy_meta_keys( $term->taxonomy );
			foreach ( $meta_keys as $key ) {
				$value = get_term_meta( $term->term_id, $key, true );
				if ( $value !== '' ) {
					$meta[ $key ] = $value;
				}
			}

			return [
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent'      => $term->parent,
				'count'       => $term->count,
				'meta'        => $meta,
			];
		}, $terms );

		return $exported;
	}

	/**
	 * Get taxonomy meta keys
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @return array<string> Meta keys.
	 */
	private function get_taxonomy_meta_keys( string $taxonomy ): array {
		return match ( $taxonomy ) {
			self::CATEGORY_TAXONOMY => [
				'brag_category_api_id',
				'brag_category_order',
			],
			self::PROCEDURE_TAXONOMY => [
				'brag_procedure_api_id',
				'brag_procedure_slug_name',
				'brag_procedure_nudity',
				'brag_procedure_case_count',
			],
			default => [],
		};
	}

	/**
	 * Search terms across taxonomies
	 *
	 * @since 3.0.0
	 * @param string $search Search query.
	 * @param array  $taxonomies Taxonomies to search.
	 * @return array<mixed> Search results.
	 */
	public function search_terms( string $search, array $taxonomies = [] ): array {
		if ( empty( $taxonomies ) ) {
			$taxonomies = [ self::CATEGORY_TAXONOMY, self::PROCEDURE_TAXONOMY ];
		}

		$cache_key = 'brag_book_gallery_transient_search_' .$search . serialize( $taxonomies );

		// Check cache
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		$results = [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = $this->get_terms_cached( $taxonomy, [
				'search'     => $search,
				'hide_empty' => false,
			] );

			foreach ( $terms as $term ) {
				$results[] = [
					'term_id'  => $term->term_id,
					'name'     => $term->name,
					'slug'     => $term->slug,
					'taxonomy' => $term->taxonomy,
					'count'    => $term->count,
				];
			}
		}

		// Sort by relevance (count)
		usort( $results, fn( $a, $b ) => $b['count'] <=> $a['count'] );

		$this->memory_cache[ $cache_key ] = $results;

		return $results;
	}

	/**
	 * Get term by API ID
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $api_id API ID.
	 * @return WP_Term|null Term object or null.
	 */
	public function get_term_by_api_id( string $taxonomy, int $api_id ): ?WP_Term {
		$cache_key = "brag_book_gallery_transient_api_term_{$taxonomy}_{$api_id}";

		// Check cache
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		$meta_key = match ( $taxonomy ) {
			self::CATEGORY_TAXONOMY  => 'brag_category_api_id',
			self::PROCEDURE_TAXONOMY => 'brag_procedure_api_id',
			default                  => '',
		};

		if ( ! $meta_key ) {
			return null;
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'meta_key'   => $meta_key,
			'meta_value' => $api_id,
			'hide_empty' => false,
			'number'     => 1,
		] );

		$term = ! empty( $terms ) && ! is_wp_error( $terms ) ? $terms[0] : null;

		$this->memory_cache[ $cache_key ] = $term;

		return $term;
	}

	/**
	 * Sync term counts
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Taxonomy name.
	 * @return int Number of terms updated.
	 */
	public function sync_term_counts( string $taxonomy ): int {
		$start_time = microtime( true );
		$updated = 0;

		try {
			$terms = $this->get_terms_cached( $taxonomy, [
				'hide_empty' => false,
				'fields'     => 'ids',
			] );

			wp_update_term_count_now( $terms, $taxonomy );
			$updated = count( $terms );

			// Clear cache after sync
			$this->clear_taxonomy_cache( 0, 0, $taxonomy );

			$this->track_performance( 'sync_term_counts', microtime( true ) - $start_time );

		} catch ( Exception $e ) {
			$this->log_error( 'sync_term_counts', $e->getMessage() );
		}

		return $updated;
	}
}
