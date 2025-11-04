<?php
/**
 * Debug API Test Component
 *
 * Handles rendering and processing of API testing functionality. Provides comprehensive
 * API endpoint testing capabilities with response visualization and error handling.
 * Utilizes modern PHP 8.2+ syntax and WordPress VIP coding standards.
 *
 * NOTE: This is a wrapper class for the API testing functionality. The detailed
 * implementation is currently maintained in the Debug_Page class due to complexity.
 * Future versions should migrate the full API testing logic here.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug
 * @since      3.3.0
 * @version    3.3.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 *
 * @todo       Migrate full API test implementation from Debug_Page
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug API Test class
 *
 * Provides API endpoint testing interface with support for multiple API connections,
 * custom parameters, and detailed response analysis. Currently acts as a wrapper
 * for the main implementation.
 *
 * ## Features:
 * - API endpoint testing
 * - Multiple connection support
 * - Custom parameter configuration
 * - JSON response visualization
 * - Error handling and reporting
 * - Base URL configuration (dev/staging/production)
 *
 * ## Supported Endpoints:
 * - Gallery data retrieval
 * - Case information
 * - Procedure listings
 * - Category data
 * - Authentication validation
 *
 * ## Future Enhancements:
 * - Full migration of API test logic from Debug_Page
 * - Enhanced error reporting
 * - Response caching options
 * - Export test results
 *
 * @since 3.3.0
 */
final class Debug_API_Test {

	/**
	 * Render the API test interface
	 *
	 * Displays API testing panel with configuration options and test controls.
	 * Currently delegates to the Debug_Page implementation.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		// Enqueue CodeMirror for JSON display.
		wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );

		// Get API configuration.
		$api_tokens            = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids  = get_option( 'brag_book_gallery_website_property_id', array() );
		$has_api_config        = ! empty( $api_tokens ) && ! empty( $website_property_ids );

		?>
		<div class="brag-book-gallery-section">
			<h3><?php esc_html_e( 'API Endpoint Testing', 'brag-book-gallery' ); ?></h3>

			<?php if ( ! $has_api_config ) : ?>
				<div class="brag-book-gallery-notice brag-book-gallery-notice--warning">
					<p>
						<?php esc_html_e( 'Please configure your API settings first.', 'brag-book-gallery' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>">
							<?php esc_html_e( 'Go to API Settings', 'brag-book-gallery' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Test various BRAGBook API endpoints to verify connectivity and data retrieval.', 'brag-book-gallery' ); ?>
				</p>
				<?php
				/**
				 * Action hook for rendering API test content.
				 *
				 * This hook allows the Debug_Page or other components to inject
				 * the detailed API test interface here.
				 *
				 * @since 3.3.0
				 *
				 * @param array $api_tokens           Array of API tokens
				 * @param array $website_property_ids Array of website property IDs
				 */
				do_action( 'brag_book_gallery_render_api_test_content', $api_tokens, $website_property_ids );
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Check if API is configured
	 *
	 * Determines if the plugin has valid API configuration.
	 *
	 * @since 3.3.0
	 *
	 * @return bool True if API is configured, false otherwise
	 */
	public function is_api_configured(): bool {
		$api_tokens           = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		return ! empty( $api_tokens ) && ! empty( $website_property_ids );
	}

	/**
	 * Get API configuration
	 *
	 * Returns the current API configuration settings.
	 *
	 * @since 3.3.0
	 *
	 * @return array{tokens: array, property_ids: array, account_info: array} API configuration
	 */
	public function get_api_configuration(): array {
		return array(
			'tokens'       => get_option( 'brag_book_gallery_api_token', array() ),
			'property_ids' => get_option( 'brag_book_gallery_website_property_id', array() ),
			'account_info' => get_option( 'brag_book_gallery_account_info', array() ),
		);
	}
}
