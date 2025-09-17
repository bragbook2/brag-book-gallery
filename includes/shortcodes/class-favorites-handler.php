<?php
/**
 * Favorites Shortcode Handler for BRAGBook Gallery Plugin
 *
 * Comprehensive favorites management handler for displaying user's favorited cases,
 * managing email capture forms, and synchronizing with the BRAGBook API for persistent
 * favorites storage. Implements WordPress VIP standards with PHP 8.2+ optimizations.
 *
 * Key Features:
 * - [brag_book_gallery_favorites] shortcode for favorites display
 * - Email capture form for new users
 * - LocalStorage integration for client-side favorites persistence
 * - API synchronization for server-side favorites storage
 * - Responsive grid layouts with consistent styling
 * - AJAX-compatible for dynamic content updates
 * - Accessible design with ARIA labels and semantic HTML
 *
 * Architecture:
 * - Static methods for stateless operations and performance
 * - Modular rendering system with reusable components
 * - Security-first approach with comprehensive input sanitization
 * - Type-safe operations with PHP 8.2+ features
 * - WordPress VIP compliant error handling and logging
 *
 * Frontend Integration:
 * - JavaScript detection for existing user information
 * - Dynamic content population based on localStorage data
 * - Form validation and submission handling
 * - Progressive enhancement for better user experience
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Shortcodes
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Shortcodes;

use BRAGBookGallery\Includes\Resources\Asset_Manager;
use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Core\Trait_Api;
use BRAGBookGallery\Includes\Core\Trait_Sanitizer;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Favorites Shortcode Handler Class
 *
 * Manages the [brag_book_gallery_favorites] shortcode for displaying
 * user favorites with email capture and API synchronization.
 *
 * @since 3.0.0
 */
final class Favorites_Handler {
	use Trait_Api;
	use Trait_Sanitizer;

	/**
	 * Initialize the favorites handler
	 *
	 * Sets up shortcode registration.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {
		add_shortcode( 'brag_book_gallery_favorites', [ self::class, 'handle' ] );

		// Register AJAX handlers for favorites form submission
		add_action( 'wp_ajax_brag_book_add_favorite', [ self::class, 'ajax_add_favorite' ] );
		add_action( 'wp_ajax_nopriv_brag_book_add_favorite', [ self::class, 'ajax_add_favorite' ] );

		// Register AJAX handlers for favorites email lookup
		add_action( 'wp_ajax_brag_book_lookup_favorites', [ self::class, 'ajax_lookup_favorites' ] );
		add_action( 'wp_ajax_nopriv_brag_book_lookup_favorites', [ self::class, 'ajax_lookup_favorites' ] );

		// Register AJAX handlers for getting case data by API ID
		add_action( 'wp_ajax_brag_book_get_case_by_api_id', [ self::class, 'ajax_get_case_by_api_id' ] );
		add_action( 'wp_ajax_nopriv_brag_book_get_case_by_api_id', [ self::class, 'ajax_get_case_by_api_id' ] );

		// Add a test endpoint to verify AJAX is working
		add_action( 'wp_ajax_brag_book_test_ajax', [ self::class, 'ajax_test' ] );
		add_action( 'wp_ajax_nopriv_brag_book_test_ajax', [ self::class, 'ajax_test' ] );

		// Register AJAX handlers for favorites grid
		add_action( 'wp_ajax_brag_book_load_favorites_grid', [ self::class, 'ajax_load_favorites_grid' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_favorites_grid', [ self::class, 'ajax_load_favorites_grid' ] );
	}

	/**
	 * Handle the favorites shortcode
	 *
	 * Renders the favorites page with email capture form or favorites grid
	 * based on user's localStorage data.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered content.
	 * @since 3.0.0
	 */
	public static function handle( array $atts ): string {
		// Ensure attributes are in array format with type validation
		$atts = is_array( $atts ) ? $atts : [];

		$validated_atts = self::validate_and_sanitize_shortcode_attributes( $atts );

		// Check if favorites are enabled
		if ( ! \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) {
			return sprintf(
				'<p class="brag-book-gallery-error">%s</p>',
				esc_html__( 'Favorites functionality is not enabled.', 'brag-book-gallery' )
			);
		}

		// Enqueue required assets
		Asset_Manager::enqueue_gallery_assets();

		// Get API credentials from mode-based arrays
		$api_tokens_option = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids_option = get_option( 'brag_book_gallery_website_property_id', [] );
		$mode = 'default'; // Mode manager removed - default to 'default' mode

		$api_token = '';
		$website_property_id = '';

		if ( is_array( $api_tokens_option ) && isset( $api_tokens_option[ $mode ] ) ) {
			$api_token = $api_tokens_option[ $mode ];
		}

		if ( is_array( $website_property_ids_option ) && isset( $website_property_ids_option[ $mode ] ) ) {
			$website_property_id = $website_property_ids_option[ $mode ];
		}

		// Localize script with AJAX configuration (required for favorites functionality)
		Asset_Manager::localize_gallery_script(
			[
				'api_token' => $api_token,
				'website_property_id' => $website_property_id,
			],
			[], // Empty sidebar data for favorites page
			[]  // Empty cases data
		);

		// Generate and return favorites HTML
		$output = self::render_favorites_html( $validated_atts );

		return $output;
	}

	/**
	 * Validate and sanitize shortcode attributes
	 *
	 * @param array $raw_atts Raw shortcode attributes.
	 * @return array Validated and sanitized attributes.
	 * @since 3.0.0
	 */
	private static function validate_and_sanitize_shortcode_attributes( array $raw_atts ): array {
		// Define default attributes with proper types
		$defaults = [
			'show_header' => true,
			'columns'     => 3,
		];

		// Apply WordPress shortcode attribute parsing with defaults
		$atts = shortcode_atts( $defaults, $raw_atts, 'brag_book_gallery_favorites' );

		// Validate and sanitize each attribute
		return [
			'show_header' => filter_var( $atts['show_header'], FILTER_VALIDATE_BOOLEAN ),
			'columns'     => max( 1, min( 6, absint( $atts['columns'] ) ) ),
		];
	}

	/**
	 * Render the favorites HTML
	 *
	 * @param array $validated_atts Validated shortcode attributes.
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	private static function render_favorites_html( array $validated_atts ): string {
		$columns = $validated_atts['columns'] ?? 3;

		// Check if user has existing data in localStorage (client-side detection will handle actual logic)
		// Server-side we always render the email capture form as the default state
		// JavaScript will handle showing the appropriate view based on localStorage data

		// Generate CSS classes with conditional font handling
		$wrapper_class = self::generate_wrapper_classes();

		return self::render_email_capture_view( $wrapper_class, $columns );
	}

	/**
	 * Render the email capture view (no user details detected)
	 *
	 * @param string $wrapper_class CSS wrapper classes.
	 * @param int $columns Number of columns for the grid.
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	private static function render_email_capture_view( string $wrapper_class, int $columns ): string {
		ob_start();
		?>

		<!-- BRAG book Gallery Favorites Component Start -->
		<div class="<?php echo esc_attr( $wrapper_class ); ?>"
			 id="brag-book-gallery-favorites"
			 role="application"
			 aria-label="My Favorites Gallery">

			<!-- Email Lookup Form (default view when no user info exists) -->
			<div class="brag-book-gallery-favorites-email-capture" id="favoritesEmailCapture" style="display: block;">
				<div class="brag-book-gallery-favorites-container">
					<div class="brag-book-gallery-favorites-form-wrapper">
						<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
							<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"></path>
							<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"></path>
							<path fill="#121827" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"></path>
							<path fill="#121827" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"></path>
							<path fill="#121827" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"></path>
							<path fill="#121827" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"></path>
							<path fill="#121827" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"></path>
							<path fill="#121827" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"></path>
							<path fill="#121827" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"></path>
							<path fill="#121827" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"></path>
							<path fill="#121827" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"></path>
							<path fill="#121827" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"></path>
							<path fill="#121827" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"></path>
							<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"></path>
						</svg>
						<p><?php esc_html_e( 'Please enter your email to view your saved favorites:', 'brag-book-gallery' ); ?></p>
						<form class="brag-book-gallery-favorites-lookup-form" id="favorites-email-form">
							<div class="brag-book-gallery-form-group">
								<input type="email"
									   name="email"
									   class="brag-book-gallery-form-input"
									   placeholder="<?php esc_attr_e( 'Enter your email address', 'brag-book-gallery' ); ?>"
									   required>
								<button type="submit"
										class="brag-book-gallery-button brag-book-gallery-button--full"
										data-action="form-submit">
									<?php esc_html_e( 'View Favorites', 'brag-book-gallery' ); ?>
								</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<!-- Favorites Grid (shown when user info exists) -->
			<div class="brag-book-gallery-favorites-grid-container" id="favoritesGridContainer" style="display: none;">
				<!-- Empty state -->
				<div class="brag-book-gallery-favorites-empty" id="favoritesEmpty" style="display: none;">
					<div class="brag-book-gallery-favorites-empty-content">
						<svg class="brag-book-gallery-favorites-empty-icon"
							 xmlns="http://www.w3.org/2000/svg"
							 fill="none"
							 viewBox="0 0 24 24"
							 stroke="currentColor">
							<path stroke-linecap="round"
								  stroke-linejoin="round"
								  stroke-width="2"
								  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
						</svg>
						<h3><?php esc_html_e( 'No favorites yet', 'brag-book-gallery' ); ?></h3>
						<p>
							<?php esc_html_e( 'Start browsing our gallery and click the heart icon on any case to add it to your favorites.', 'brag-book-gallery' ); ?>
						</p>
						<a href="<?php echo esc_url( self::get_gallery_url() ); ?>"
						   class="brag-book-gallery-button">
							<?php esc_html_e( 'Browse Gallery', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>

				<!-- Favorites grid -->
				<div class="brag-book-gallery-favorites-grid"
					 id="favoritesGrid"
					 data-columns="<?php echo esc_attr( $columns ); ?>">
					<!-- Favorites will be populated by JavaScript -->
				</div>

				<!-- Favorites actions -->
				<div class="brag-book-gallery-favorites-actions" id="favoritesActions" style="display: none;">
					<button class="brag-book-gallery-button brag-book-gallery-button--secondary"
							data-action="clear-all-favorites">
						<?php esc_html_e( 'Clear All Favorites', 'brag-book-gallery' ); ?>
					</button>
					<button class="brag-book-gallery-button"
							data-action="send-favorites">
						<?php esc_html_e( 'Send My Favorites', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>

			<!-- Loading state -->
			<div class="brag-book-gallery-favorites-loading" id="favoritesLoading" style="display: none;">
				<div class="brag-book-gallery-loading-spinner"></div>
				<p><?php esc_html_e( 'Loading your favorites...', 'brag-book-gallery' ); ?></p>
			</div>
		</div>

		<script>
			// Initialize favorites page when DOM is ready
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof window.initializeFavoritesPage === 'function') {
					window.initializeFavoritesPage();
				}
			});
		</script>

		<!-- BRAG book Gallery Favorites Component End -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the gallery view (user has favorites data)
	 *
	 * @param bool $show_header Whether to show the header.
	 * @param string $wrapper_class CSS wrapper classes.
	 * @param int $columns Number of columns for the grid.
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	private static function render_gallery_view( bool $show_header, string $wrapper_class, int $columns ): string {
		ob_start();
		?>

		<!-- BRAG book Gallery Favorites Component Start -->
		<div class="<?php echo esc_attr( $wrapper_class ); ?>"
			 id="brag-book-gallery-favorites"
			 role="application"
			 aria-label="My Favorites Gallery">

			<!-- Email Lookup Form (hidden when user has data) -->
			<div class="brag-book-gallery-favorites-email-capture" id="favoritesEmailCapture" style="display: none;">
				<!-- Email form content here if needed for transitions -->
			</div>

			<!-- Favorites Grid (shown when user info exists) -->
			<div class="brag-book-gallery-favorites-grid-container" id="favoritesGridContainer" style="display: block;">
				<!-- Empty state -->
				<div class="brag-book-gallery-favorites-empty" id="favoritesEmpty" style="display: block;">
					<div class="brag-book-gallery-favorites-empty-content">
						<svg class="brag-book-gallery-favorites-empty-icon"
							 xmlns="http://www.w3.org/2000/svg"
							 fill="none"
							 viewBox="0 0 24 24"
							 stroke="currentColor">
							<path stroke-linecap="round"
								  stroke-linejoin="round"
								  stroke-width="2"
								  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
						</svg>
						<h3><?php esc_html_e( 'No favorites yet', 'brag-book-gallery' ); ?></h3>
						<p>
							<?php esc_html_e( 'Start browsing our gallery and click the heart icon on any case to add it to your favorites.', 'brag-book-gallery' ); ?>
						</p>
						<a href="<?php echo esc_url( self::get_gallery_url() ); ?>"
						   class="brag-book-gallery-button">
							<?php esc_html_e( 'Browse Gallery', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>

				<!-- Favorites grid -->
				<div class="brag-book-gallery-favorites-grid"
					 id="favoritesGrid"
					 data-columns="<?php echo esc_attr( $columns ); ?>">
					<!-- Favorites will be populated by JavaScript -->
				</div>

				<!-- Favorites actions -->
				<div class="brag-book-gallery-favorites-actions" id="favoritesActions" style="display: none;">
					<button class="brag-book-gallery-button brag-book-gallery-button--secondary"
							data-action="clear-all-favorites">
						<?php esc_html_e( 'Clear All Favorites', 'brag-book-gallery' ); ?>
					</button>
					<button class="brag-book-gallery-button"
							data-action="send-favorites">
						<?php esc_html_e( 'Send My Favorites', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>

			<!-- Loading state -->
			<div class="brag-book-gallery-favorites-loading" id="favoritesLoading" style="display: none;">
				<div class="brag-book-gallery-loading-spinner"></div>
				<p><?php esc_html_e( 'Loading your favorites...', 'brag-book-gallery' ); ?></p>
			</div>
		</div>

		<script>
			// Initialize favorites page when DOM is ready
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof window.initializeFavoritesPage === 'function') {
					window.initializeFavoritesPage();
				}
			});
		</script>

		<!-- BRAG book Gallery Favorites Component End -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate wrapper classes
	 *
	 * @return string CSS classes for the wrapper.
	 * @since 3.0.0
	 */
	private static function generate_wrapper_classes(): string {
		$wrapper_class = 'brag-book-gallery-favorites-wrapper';

		// Check custom font setting with default fallback
		$use_custom_font = get_option( 'brag_book_gallery_use_custom_font', 'yes' );

		if ( 'yes' !== $use_custom_font ) {
			$wrapper_class .= ' disable-custom-font';
		}

		// Check filter counts setting
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );

		if ( ! $show_filter_counts ) {
			$wrapper_class .= ' brag-book-gallery-hide-filter-counts';
		}

		return $wrapper_class;
	}

	/**
	 * Get gallery page URL
	 *
	 * @return string Gallery page URL.
	 * @since 3.0.0
	 */
	private static function get_gallery_url(): string {
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );

		// Handle legacy array format from old Slug Helper
		if ( is_array( $gallery_slug ) ) {
			$gallery_slug = $gallery_slug[0] ?? 'gallery';
		}

		return home_url( '/' . ltrim( $gallery_slug, '/' ) . '/' );
	}

	/**
	 * Handle AJAX request for adding favorites
	 *
	 * Processes the favorites form submission and saves user information and case to API.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function ajax_add_favorite(): void {
		try {

			// Verify nonce for security
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
				wp_send_json_error( [
					'message' => __( 'Security verification failed. Please try again.', 'brag-book-gallery' ),
				] );
			}

			// Validate required fields
			$name    = sanitize_text_field( $_POST['name'] ?? '' );
			$email   = sanitize_email( $_POST['email'] ?? '' );
			$phone   = sanitize_text_field( $_POST['phone'] ?? '' );
			$received_case_id = sanitize_text_field( $_POST['case_id'] ?? '' );

			// Handle both WordPress post IDs and BRAGBook API case IDs
			$api_case_id = '';
			$wp_post_id = 0;

			// First, try to treat it as a WordPress post ID
			if ( is_numeric( $received_case_id ) ) {
				$test_post_id = absint( $received_case_id );
				$test_post = get_post( $test_post_id );

				// If it's a valid WordPress post of type brag_book_cases
				if ( $test_post && $test_post->post_type === 'brag_book_cases' ) {
					$wp_post_id = $test_post_id;
					$api_case_id = get_post_meta( $wp_post_id, 'brag_book_gallery_api_id', true ) ?: get_post_meta( $wp_post_id, '_case_api_id', true );
				}
			}

			// If we didn't find a WordPress post, treat it as a BRAGBook API case ID
			if ( empty( $api_case_id ) && ! empty( $received_case_id ) ) {
				$api_case_id = $received_case_id; // Use it directly as API case ID
			}

			if ( empty( $name ) || empty( $email ) || empty( $phone ) ) {
				wp_send_json_error( [
					'message' => __( 'Please fill in all required fields.', 'brag-book-gallery' ),
				] );
			}

			if ( ! is_email( $email ) ) {
				wp_send_json_error( [
					'message' => __( 'Please enter a valid email address.', 'brag-book-gallery' ),
				] );
			}

			if ( empty( $received_case_id ) ) {
				wp_send_json_error( [
					'message' => __( 'Invalid case information. Please try again.', 'brag-book-gallery' ),
				] );
			}

			if ( empty( $api_case_id ) ) {
				wp_send_json_error( [
					'message' => __( 'This case is not available for favoriting. The case may not be properly configured.', 'brag-book-gallery' ),
				] );
			}

			// Submit favorite to BRAGBook API using the API case ID
			$result = self::add_favorite_to_api( $name, $email, $phone, $api_case_id );

			if ( $result ) {
				wp_send_json_success( [
					'message' => __( 'Favorite added successfully!', 'brag-book-gallery' ),
					'data'    => [
						'name'         => $name,
						'email'        => $email,
						'phone'        => $phone,
						'wp_post_id'   => $wp_post_id,
						'api_case_id'  => $api_case_id,
					],
				] );
			} else {
				wp_send_json_error( [
					'message' => __( 'Failed to save favorite. Please try again.', 'brag-book-gallery' ),
				] );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( [
				'message' => $e->getMessage(),
			] );
		} catch ( Throwable $e ) {
			wp_send_json_error( [
				'message' => __( 'A system error occurred. Please try again.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * Add a favorite to the BRAGBook API using direct HTTP call
	 *
	 * @param string $name User's name.
	 * @param string $email User's email.
	 * @param string $phone User's phone.
	 * @param string $case_id Case ID to favorite.
	 * @return bool Success status.
	 * @since 3.0.0
	 */
	private static function add_favorite_to_api( string $name, string $email, string $phone, string $case_id ): bool {
		// Get API credentials from arrays (stored as numeric arrays, not mode-based)
		$api_tokens_option = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids_option = get_option( 'brag_book_gallery_website_property_id', [] );


		// Extract values from numeric arrays
		$api_token = '';
		$website_property_id = '';

		// Check if stored as numeric array (current format)
		if ( is_array( $api_tokens_option ) && ! empty( $api_tokens_option[0] ) ) {
			$api_token = $api_tokens_option[0];
		}

		if ( is_array( $website_property_ids_option ) && ! empty( $website_property_ids_option[0] ) ) {
			$website_property_id = $website_property_ids_option[0];
		}


		// Validate API credentials
		if ( empty( $api_token ) ) {
			throw new \Exception( 'No API token configured. Please configure API settings first.' );
		}

		if ( empty( $website_property_id ) ) {
			throw new \Exception( 'No website property ID configured. Please configure API settings first.' );
		}

		// Convert to arrays for API format
		$api_tokens_array = [ $api_token ];
		$website_property_ids_array = [ intval( $website_property_id ) ];

		// Get API base URL
		$api_base_url = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );

		// Build full API URL
		$api_url = rtrim( $api_base_url, '/' ) . '/api/plugin/combine/favorites/add';

		// Prepare request body in exact format expected by BRAGBook API
		$body = [
			'apiTokens' => $api_tokens_array,
			'websitePropertyIds' => $website_property_ids_array,
			'email' => $email,
			'phone' => $phone,
			'name' => $name,
			'caseId' => (int) $case_id,
		];

		// Convert to JSON
		$json_body = wp_json_encode( $body );

		// Make direct API request with error handling
		try {
			$response = wp_remote_post( $api_url, [
				'timeout' => 30,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'User-Agent' => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
				],
				'body' => $json_body,
				'sslverify' => true,
			] );
		} catch ( \Exception $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			throw new \Exception( "HTTP request failed: " . $e->getMessage() );
		}

		// Handle WordPress HTTP errors
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}

		// Get response details
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Parse JSON response first
		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Invalid JSON response: ' . json_last_error_msg() );
		}

		// Check if successful (either 200 status or success=true in response)
		if ( $response_code === 200 ) {
			return isset( $data['success'] ) ? $data['success'] : true;
		}

		// Handle specific API errors with user-friendly messages
		if ( isset( $data['message'] ) ) {
			$api_message = $data['message'];

			// Map API error messages to user-friendly messages
			switch ( strtolower( $api_message ) ) {
				case 'case not found':
					throw new \Exception( __( 'This case is not available for favoriting. This may be due to a configuration issue with your API credentials or website property settings. Please contact support.', 'brag-book-gallery' ) );
				case 'invalid api token':
				case 'unauthorized':
					throw new \Exception( __( 'API configuration error. Please contact support.', 'brag-book-gallery' ) );
				default:
					throw new \Exception( sprintf(
						__( 'Unable to save favorite: %s', 'brag-book-gallery' ),
						$api_message
					) );
			}
		}

		// Generic error for non-200 status without a message
		throw new \Exception( sprintf(
			__( 'API request failed with status %d. Please try again.', 'brag-book-gallery' ),
			$response_code
		) );
	}

	/**
	 * Handle AJAX request for looking up favorites by email
	 *
	 * Checks if an email exists in the system and returns user's favorites.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function ajax_lookup_favorites(): void {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed. Please try again.', 'brag-book-gallery' ),
			] );
		}

		// Validate email
		$email = sanitize_email( $_POST['email'] ?? '' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( [
				'message' => __( 'Please enter a valid email address.', 'brag-book-gallery' ),
			] );
		}

		// Make API call to lookup user favorites by email
		try {
			$user_data = self::lookup_user_by_email( $email );

			if ( $user_data && ! empty( $user_data ) ) {
				// User found, return user details and favorites
				wp_send_json_success( [
					'message' => __( 'Account found! Loading your favorites...', 'brag-book-gallery' ),
					'user' => [
						'email' => $user_data['email'] ?? $email,
						'name' => $user_data['name'] ?? '',
						'phone' => $user_data['phone'] ?? '',
						'id' => $user_data['id'] ?? '',
					],
					'favorites' => [
						'case_ids' => $user_data['favorites']['case_ids'] ?? [],
						'cases_data' => $user_data['favorites']['cases_data'] ?? [],
						'total_count' => $user_data['favorites']['total_count'] ?? 0,
					],
				] );
			} else {
				// User not found
				wp_send_json_error( [
					'message' => sprintf(
						/* translators: %s: email address */
						__( 'We were unable to locate account details for %s. Please check your email address or create a new account.', 'brag-book-gallery' ),
						$email
					),
					'email' => $email,
				] );
			}
		} catch ( Exception $e ) {
			// API call failed
			wp_send_json_error( [
				'message' => __( 'Unable to verify email address at this time. Please try again later.', 'brag-book-gallery' ),
				'email' => $email,
				'error' => $e->getMessage(),
			] );
		}
	}

	/**
	 * AJAX handler for loading favorites grid
	 *
	 * Returns HTML for favorites grid based on user's favorites data.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function ajax_load_favorites_grid(): void {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed. Please try again.', 'brag-book-gallery' ),
			] );
		}

		// Get favorites data from request
		$favorites_data = $_POST['favorites'] ?? [];
		if ( ! is_array( $favorites_data ) ) {
			$favorites_data = json_decode( stripslashes( $favorites_data ), true ) ?: [];
		}

		// Get user info from request
		$user_info = $_POST['userInfo'] ?? [];
		if ( ! is_array( $user_info ) ) {
			$user_info = json_decode( stripslashes( $user_info ), true ) ?: [];
		}

		// Get grid configuration
		$columns = max( 1, min( 6, absint( $_POST['columns'] ?? 3 ) ) );

		if ( empty( $favorites_data ) ) {
			// Return empty state HTML
			wp_send_json_success( [
				'html' => self::render_favorites_empty_state(),
				'isEmpty' => true,
			] );
			return;
		}

		try {
			// Generate favorites grid HTML
			$grid_html = self::render_favorites_grid_cards( $favorites_data, $columns );

			wp_send_json_success( [
				'html' => $grid_html,
				'isEmpty' => false,
				'count' => count( $favorites_data ),
			] );

		} catch ( Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Unable to load favorites. Please try again.', 'brag-book-gallery' ),
				'error' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Render favorites grid cards
	 *
	 * @param array $favorites_data Array of favorite case data.
	 * @param int $columns Number of columns for the grid.
	 * @return string Rendered HTML for favorites grid.
	 * @since 3.0.0
	 */
	private static function render_favorites_grid_cards( array $favorites_data, int $columns ): string {
		if ( empty( $favorites_data ) ) {
			return self::render_favorites_empty_state();
		}

		ob_start();
		?>
		<div class="brag-book-gallery-favorites-grid" data-columns="<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $favorites_data as $case_data ) : ?>
				<?php echo self::render_favorites_case_card( $case_data ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single favorites case card
	 *
	 * @param array $case_data Case data from favorites.
	 * @return string Rendered HTML for case card.
	 * @since 3.0.0
	 */
	private static function render_favorites_case_card( array $case_data ): string {
		$case_id = $case_data['id'] ?? '';
		$images = $case_data['images'] ?? [];
		$procedures = $case_data['procedures'] ?? [];
		$age = $case_data['age'] ?? $case_data['patientAge'] ?? '';
		$gender = $case_data['gender'] ?? $case_data['patientGender'] ?? '';

		// Get primary image
		$primary_image = '';
		if ( ! empty( $images ) && is_array( $images ) ) {
			// Look for the first image with a URL
			foreach ( $images as $image ) {
				if ( ! empty( $image['url'] ) ) {
					$primary_image = $image['url'];
					break;
				}
			}
		}

		// Get primary procedure name
		$primary_procedure = 'Case';
		if ( ! empty( $procedures ) && is_array( $procedures ) ) {
			$primary_procedure = is_array( $procedures[0] ) ? $procedures[0]['name'] ?? 'Case' : $procedures[0];
		}

		// Generate case URL
		$gallery_slug = self::get_gallery_page_slug();
		$case_url = '/' . ltrim( $gallery_slug, '/' ) . '/' . $case_id . '/';

		// Build data attributes for filtering
		$data_attrs = [
			'data-card="true"',
			'data-case-id="' . esc_attr( $case_id ) . '"',
			'data-favorited="true"', // Always favorited in favorites view
		];

		if ( ! empty( $age ) ) {
			$data_attrs[] = 'data-age="' . esc_attr( $age ) . '"';
		}
		if ( ! empty( $gender ) ) {
			$data_attrs[] = 'data-gender="' . esc_attr( strtolower( $gender ) ) . '"';
		}

		ob_start();
		?>
		<article class="brag-book-gallery-case-card brag-book-gallery-favorites-card" <?php echo implode( ' ', $data_attrs ); ?>>
			<div class="brag-book-gallery-case-images single-image">
				<div class="brag-book-gallery-single-image">
					<div class="brag-book-gallery-image-container">
						<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>
						<div class="brag-book-gallery-item-actions">
							<button class="brag-book-gallery-favorite-button favorited"
									data-favorited="true"
									data-item-id="case-<?php echo esc_attr( $case_id ); ?>"
									aria-label="Remove from favorites">
								<svg fill="#ff595c" stroke="#ff595c" stroke-width="2" viewBox="0 0 24 24">
									<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
								</svg>
							</button>
						</div>
						<a href="<?php echo esc_url( $case_url ); ?>"
						   class="brag-book-gallery-case-permalink"
						   data-case-id="<?php echo esc_attr( $case_id ); ?>">
							<?php if ( ! empty( $primary_image ) ) : ?>
								<picture class="brag-book-gallery-picture">
									<img src="<?php echo esc_url( $primary_image ); ?>"
										 alt="<?php echo esc_attr( $primary_procedure . ' - Case ' . $case_id ); ?>"
										 loading="lazy"
										 data-image-type="single"
										 data-image-url="<?php echo esc_url( $primary_image ); ?>"
										 onload="this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';">
								</picture>
							<?php else : ?>
								<div class="brag-book-gallery-no-image">
									<span>No Image Available</span>
								</div>
							<?php endif; ?>
						</a>
					</div>
				</div>
			</div>
			<details class="brag-book-gallery-case-card-details">
				<summary class="brag-book-gallery-case-card-summary">
					<div class="brag-book-gallery-case-card-summary-info">
						<span class="brag-book-gallery-case-card-summary-info__name"><?php echo esc_html( $primary_procedure ); ?></span>
						<span class="brag-book-gallery-case-card-summary-info__case-number">Case #<?php echo esc_html( $case_id ); ?></span>
					</div>
					<div class="brag-book-gallery-case-card-summary-details">
						<p class="brag-book-gallery-case-card-summary-details__more">
							<strong>More Details</strong>
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
								<path d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>
							</svg>
						</p>
					</div>
				</summary>
				<div class="brag-book-gallery-case-card-details-content">
					<p class="brag-book-gallery-case-card-details-content__title">Procedures Performed:</p>
					<ul class="brag-book-gallery-case-card-procedures-list">
						<?php if ( ! empty( $procedures ) && is_array( $procedures ) ) : ?>
							<?php foreach ( $procedures as $procedure ) : ?>
								<li class="brag-book-gallery-case-card-procedures-list__item">
									<?php echo esc_html( is_array( $procedure ) ? $procedure['name'] ?? 'Unknown' : $procedure ); ?>
								</li>
							<?php endforeach; ?>
						<?php else : ?>
							<li class="brag-book-gallery-case-card-procedures-list__item">
								<?php echo esc_html( $primary_procedure ); ?>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</details>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render favorites empty state
	 *
	 * @return string Rendered HTML for empty state.
	 * @since 3.0.0
	 */
	private static function render_favorites_empty_state(): string {
		ob_start();
		?>
		<div class="brag-book-gallery-favorites-empty-content">
			<svg class="brag-book-gallery-favorites-empty-icon"
				 xmlns="http://www.w3.org/2000/svg"
				 fill="none"
				 viewBox="0 0 24 24"
				 stroke="currentColor">
				<path stroke-linecap="round"
					  stroke-linejoin="round"
					  stroke-width="2"
					  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
			</svg>
			<h3><?php esc_html_e( 'No favorites yet', 'brag-book-gallery' ); ?></h3>
			<p>
				<?php esc_html_e( 'Start browsing our gallery and click the heart icon on any case to add it to your favorites.', 'brag-book-gallery' ); ?>
			</p>
			<a href="<?php echo esc_url( self::get_gallery_url() ); ?>"
			   class="brag-book-gallery-button">
				<?php esc_html_e( 'Browse Gallery', 'brag-book-gallery' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get gallery page slug with legacy array format handling
	 *
	 * @since 3.0.0
	 * @param string $default Default value if option is not set
	 * @return string Gallery page slug
	 */
	private static function get_gallery_page_slug( string $default = 'gallery' ): string {
		$option = get_option( 'brag_book_gallery_page_slug', $default );

		// Handle legacy array format from old Slug Helper
		if ( is_array( $option ) ) {
			return $option[0] ?? $default;
		}

		return $option ?: $default;
	}

	/**
	 * Lookup user favorites by email address via BRAGBook API using direct HTTP call
	 *
	 * @param string $email Email address to lookup.
	 * @return array|false User data with favorites array or false if not found.
	 * @since 3.0.0
	 */
	public static function lookup_user_by_email( string $email ): array|false {
		if ( empty( $email ) || ! is_email( $email ) ) {
			return false;
		}

		// Get API credentials (handle array format)
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		// Ensure we have arrays for the API call
		if ( is_string( $api_tokens ) && ! empty( $api_tokens ) ) {
			$api_tokens_array = [ $api_tokens ];
		} elseif ( is_array( $api_tokens ) && ! empty( $api_tokens ) ) {
			$api_tokens_array = array_filter( $api_tokens ); // Remove empty values
		} else {
			throw new \Exception( 'No API token configured. Please configure API settings first.' );
		}

		if ( is_string( $website_property_ids ) && ! empty( $website_property_ids ) ) {
			$website_property_ids_array = [ (int) $website_property_ids ];
		} elseif ( is_array( $website_property_ids ) && ! empty( $website_property_ids ) ) {
			$website_property_ids_array = array_map( 'intval', array_filter( $website_property_ids ) );
		} else {
			throw new \Exception( 'No website property ID configured. Please configure API settings first.' );
		}

		// Get API base URL
		$api_base_url = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );

		// Build full API URL
		$api_url = rtrim( $api_base_url, '/' ) . '/api/plugin/combine/favorites/list';

		// Prepare request body in exact format expected by BRAGBook API
		$body = [
			'apiTokens' => $api_tokens_array,
			'websitePropertyIds' => $website_property_ids_array,
			'email' => $email,
		];

		// Convert to JSON
		$json_body = wp_json_encode( $body );

		// Make direct API request
		$response = wp_remote_post( $api_url, [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'User-Agent' => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'body' => $json_body,
			'sslverify' => true,
		] );

		// Handle WordPress HTTP errors
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}

		// Get response details
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Check HTTP status
		if ( $response_code !== 200 ) {
			throw new \Exception( "API returned HTTP {$response_code}. Response: {$response_body}" );
		}

		// Parse JSON response
		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Invalid JSON response: ' . json_last_error_msg() );
		}

		// Check if favorites were found
		if ( ! isset( $data['success'] ) || ! $data['success'] || empty( $data['favorites'] ) ) {
			return false;
		}

		$favorites_data = $data['favorites'];

		// Extract user info from first favorite entry (they should all be the same user)
		$first_favorite = reset( $favorites_data );
		if ( empty( $first_favorite ) ) {
			return false;
		}

		// Extract case IDs from all favorites
		$favorite_case_ids = [];
		$favorite_cases_data = [];

		foreach ( $favorites_data as $favorite ) {
			if ( ! empty( $favorite['cases'] ) && is_array( $favorite['cases'] ) ) {
				foreach ( $favorite['cases'] as $case ) {
					$case_id = $case['id'] ?? null;
					if ( $case_id ) {
						$favorite_case_ids[] = $case_id;
						// Store the full case data for reference
						$favorite_cases_data[ $case_id ] = $case;
					}
				}
			}
		}

		// Build user info structure
		$user_info = [
			'id' => $first_favorite['id'] ?? '',
			'email' => $first_favorite['email'] ?? $email,
			'name' => $first_favorite['name'] ?? '',
			'phone' => $first_favorite['phone'] ?? '',
			'favorites' => [
				'case_ids' => array_unique( $favorite_case_ids ),
				'cases_data' => $favorite_cases_data,
				'total_count' => count( array_unique( $favorite_case_ids ) )
			]
		];

		// Ensure we have essential user info
		if ( empty( $user_info['email'] ) || empty( $user_info['name'] ) || empty( $user_info['phone'] ) ) {
			return false;
		}

		return $user_info;
	}

	/**
	 * AJAX handler to get WordPress case data by API case ID
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function ajax_get_case_by_api_id(): void {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed.', 'brag-book-gallery' ),
			] );
		}

		$api_case_id = sanitize_text_field( $_POST['api_case_id'] ?? '' );

		if ( empty( $api_case_id ) ) {
			wp_send_json_error( [
				'message' => __( 'API case ID is required.', 'brag-book-gallery' ),
			] );
		}

		// Query for WordPress post with matching API case ID
		$posts = get_posts( [
			'post_type' => 'brag_book_cases',
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => 'brag_book_gallery_api_id',
					'value' => $api_case_id,
					'compare' => '='
				],
				[
					'key' => '_case_api_id',
					'value' => $api_case_id,
					'compare' => '='
				]
			],
			'posts_per_page' => 1,
			'post_status' => 'publish'
		] );

		if ( empty( $posts ) ) {
			wp_send_json_error( [
				'message' => __( 'No WordPress post found for this case ID.', 'brag-book-gallery' ),
			] );
		}

		$post = $posts[0];

		// Get post meta data
		$post_meta = get_post_meta( $post->ID );

		// Get image from processed URLs (first URL from semicolon-separated string)
		$processed_urls = get_post_meta( $post->ID, 'brag_book_gallery_case_post_processed_url', true );
		$featured_image_url = '';

		if ( ! empty( $processed_urls ) ) {
			// Split by semicolon and get first URL
			$url_array = explode( ';', $processed_urls );
			$featured_image_url = trim( $url_array[0] ?? '' );
		}

		// Fallback to featured image if no processed URLs
		if ( empty( $featured_image_url ) ) {
			$featured_image_url = get_the_post_thumbnail_url( $post->ID, 'large' ) ?: '';
		}

		// Get procedure information (assuming it's stored in taxonomies or meta)
		$procedure_terms = get_the_terms( $post->ID, 'procedures' );
		$procedure_name = 'Unknown Procedure';
		$procedure_slug = 'procedure';

		if ( $procedure_terms && ! is_wp_error( $procedure_terms ) ) {
			$procedure_term = $procedure_terms[0];
			$procedure_name = $procedure_term->name;
			$procedure_slug = $procedure_term->slug;
		}

		// Prepare response data
		$response_data = [
			'ID' => $post->ID,
			'post_title' => $post->post_title,
			'post_name' => $post->post_name,
			'post_content' => $post->post_content,
			'featured_image_url' => $featured_image_url,
			'procedure_name' => $procedure_name,
			'procedure_slug' => $procedure_slug,
			'post_meta' => array_map( function( $meta_array ) {
				return count( $meta_array ) === 1 ? $meta_array[0] : $meta_array;
			}, $post_meta )
		];

		wp_send_json_success( $response_data );
	}

	/**
	 * Test AJAX endpoint to verify AJAX is working
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function ajax_test(): void {
		error_log( 'BRAGBook: ajax_test endpoint called' );

		wp_send_json_success( [
			'message' => 'AJAX is working correctly!',
			'timestamp' => current_time( 'mysql' ),
		] );
	}
}
