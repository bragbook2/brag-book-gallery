<?php
/**
 * API Testing Settings Class
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Pages;

use BRAGBookGallery\Includes\Admin\Core\Settings_Base;
use BRAGBookGallery\Includes\REST\Endpoints;
use BRAGBookGallery\Includes\Core\Setup;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * API Testing Settings Class
 *
 * Provides a testing interface for all BRAG book API endpoints.
 * Allows administrators to verify API connectivity and test various endpoints
 * with real-time response display.
 *
 * @since 3.0.0
 */
class API_Test_Page extends Settings_Base {

	/**
	 * Initialize the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug = 'brag-book-gallery-api-test';

		// Add AJAX handlers for API testing
		add_action( 'wp_ajax_brag_book_test_api', array( $this, 'handle_api_test' ) );
	}

	/**
	 * Handle API test requests via AJAX
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_api_test(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'brag_book_api_test' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Get request parameters
		$endpoint = sanitize_text_field( $_POST['endpoint'] ?? '' );
		$body = isset( $_POST['body'] ) ? json_decode( stripslashes( $_POST['body'] ), true ) : null;

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( array(
				'message' => 'API configuration is missing. Please configure your API settings.',
			) );
			return;
		}

		// Initialize Endpoints class
		$endpoints = new Endpoints();

		try {
			$response_body = null;
			$start_time = microtime( true );

			// Route to appropriate Endpoints method based on endpoint type
			switch ( $endpoint ) {
				case 'terms':
					$response_body = $endpoints->get_api_terms( $api_tokens[0] ?? '' );
					break;

				case 'cases':
					$response_body = $endpoints->get_pagination_data( $body );
					break;

				case 'carousel':
					$options = array(
						'websitePropertyId' => $website_property_ids[0],
						'procedureId' => $body['procedureId'] ?? null,
						'limit' => 10,
						'start' => 1,
					);
					$response_body = $endpoints->get_carousel_data( $api_tokens[0], $options );
					break;

				case 'filters':
					// Filters endpoint uses the cases endpoint with specific body
					$response_body = $endpoints->get_pagination_data( $body );
					break;

				case 'favorites-list':
					$email = $body['email'] ?? 'test@example.com';
					$response_body = $endpoints->get_favorite_list_data( $api_tokens, $website_property_ids, $email );
					break;

				case 'sitemap':
					$response_body = $endpoints->get_sitemap_data( $api_tokens, $website_property_ids );
					break;

				case 'single-case':
					$case_id = $body['caseId'] ?? '';
					if ( empty( $case_id ) ) {
						throw new \Exception( 'Case ID is required for single case endpoint' );
					}
					$response_body = $endpoints->get_case_details( (string) $case_id );
					break;

				case 'consultations':
					$response_body = $endpoints->submit_consultation(
						$api_tokens[0],
						intval( $website_property_ids[0] ),
						$body['email'] ?? 'test@example.com',
						$body['phone'] ?? '(555) 123-4567',
						$body['name'] ?? 'Test User',
						$body['details'] ?? 'Test consultation from API test page'
					);
					break;

				case 'views':
					$case_id = intval( $body['caseId'] ?? 0 );
					if ( $case_id <= 0 ) {
						throw new \Exception( 'Valid Case ID is required for views endpoint' );
					}
					$response_body = $endpoints->track_case_view( $api_tokens[0], $case_id );
					break;

				case 'validate-token':
					$result = $endpoints->validate_token( $api_tokens[0], intval( $website_property_ids[0] ) );
					$response_body = wp_json_encode( $result );
					break;

				case 'cases-v2':
					// For GET requests, parameters come from the URL
					$url_parts = wp_parse_url( sanitize_text_field( $_POST['url'] ?? '' ) );
					parse_str( $url_parts['query'] ?? '', $query_params );

					$procedure_id = intval( $query_params['procedureId'] ?? 0 );
					if ( $procedure_id <= 0 ) {
						throw new \Exception( 'Valid Procedure ID is required for v2 cases endpoint' );
					}

					$page = intval( $query_params['page'] ?? 1 );
					$limit = intval( $query_params['limit'] ?? 20 );
					$member_id = isset( $query_params['memberId'] ) ? strval( $query_params['memberId'] ) : null;

					$result = $endpoints->get_cases_v2(
						$api_tokens[0],
						intval( $website_property_ids[0] ),
						$procedure_id,
						$page,
						$limit,
						$member_id
					);
					$response_body = wp_json_encode( $result );
					break;

				case 'single-case-v2':
					// For GET requests, parameters come from the URL
					$url_parts = wp_parse_url( sanitize_text_field( $_POST['url'] ?? '' ) );
					parse_str( $url_parts['query'] ?? '', $query_params );

					// Case ID comes from the URL path
					preg_match( '/\/cases\/(\d+)/', sanitize_text_field( $_POST['url'] ?? '' ), $matches );
					$case_id = intval( $matches[1] ?? 0 );

					if ( $case_id <= 0 ) {
						throw new \Exception( 'Valid Case ID is required for v2 single case endpoint' );
					}

					$procedure_id = intval( $query_params['procedureId'] ?? 0 );
					$member_id = isset( $query_params['memberId'] ) ? strval( $query_params['memberId'] ) : null;

					$result = $endpoints->get_case_detail_v2(
						$api_tokens[0],
						$case_id,
						intval( $website_property_ids[0] ),
						$procedure_id > 0 ? $procedure_id : null,
						$member_id
					);
					$response_body = wp_json_encode( $result );
					break;

				default:
					throw new \Exception( 'Unsupported endpoint: ' . $endpoint );
			}

			$duration = microtime( true ) - $start_time;

			// Check if we got a response
			if ( $response_body === null ) {
				throw new \Exception( 'No response received from API' );
			}

			// Try to decode JSON response if it's a string
			$decoded_body = $response_body;
			if ( is_string( $response_body ) ) {
				$decoded = json_decode( $response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$decoded_body = $decoded;
				}
			}

			wp_send_json_success( array(
				'status' => 200,
				'body' => $decoded_body,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'duration' => round( $duration * 1000, 2 ) . 'ms',
			) );

		} catch ( \Exception $e ) {
			// Log detailed error information
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API Test Error: ' . $e->getMessage() );
				error_log( 'API Test Endpoint: ' . $endpoint );
			}

			wp_send_json_error( array(
				'message' => $e->getMessage(),
				'code' => 'api_test_error',
				'details' => 'Failed to test endpoint: ' . $endpoint,
			) );
		}
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set translated strings when rendering
		$this->page_title = __( 'API Testing', 'brag-book-gallery' );
		$this->menu_title = __( 'API Test', 'brag-book-gallery' );

		// Enqueue CodeMirror for JSON display
		wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		// Check if API is configured
		$has_api_config = ! empty( $api_tokens ) && ! empty( $website_property_ids );

		$this->render_header();
		?>

		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'API Endpoint Testing', 'brag-book-gallery' ); ?></h2>

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
					<?php esc_html_e( 'Test various BRAG book API endpoints to verify connectivity and data retrieval.', 'brag-book-gallery' ); ?>
				</p>

				<div class="api-test-config">
					<div class="config-info">
						<strong><?php esc_html_e( 'API Connections:', 'brag-book-gallery' ); ?></strong>
						<?php if ( count( $api_tokens ) > 1 ) : ?>
							<p class="description"><?php esc_html_e( 'Multiple connections configured. Tests will use all connections.', 'brag-book-gallery' ); ?></p>
						<?php endif; ?>
						<ul>
							<?php foreach ( $api_tokens as $index => $token ) : ?>
								<li>
									<?php
									echo esc_html( sprintf(
										__( 'Connection %d: Token %s... | Property ID: %s', 'brag-book-gallery' ),
										$index + 1,
										substr( $token, 0, 10 ),
										$website_property_ids[$index] ?? 'N/A'
									) );
									?>
								</li>
							<?php endforeach; ?>
						</ul>
						<p><strong><?php esc_html_e( 'Base URL:', 'brag-book-gallery' ); ?></strong> <code>https://app.bragbookgallery.com</code></p>
					</div>
					<div class="test-parameters">
						<strong><?php esc_html_e( 'Test Parameters:', 'brag-book-gallery' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Configure optional parameters for testing different scenarios.', 'brag-book-gallery' ); ?></p>
						<table class="form-table" style="margin-top: 10px;">
							<tr>
								<th style="padding: 5px; width: 150px;">
									<label for="test-procedure-id"><?php esc_html_e( 'Procedure ID:', 'brag-book-gallery' ); ?></label>
								</th>
								<td style="padding: 5px;">
									<input type="number" id="test-procedure-id" placeholder="3405" class="input-field regular-text" style="width: 150px;">
									<span class="description"><?php esc_html_e( 'Used by: Carousel, Cases, Filters (default: 3405)', 'brag-book-gallery' ); ?></span>
								</td>
							</tr>
							<tr>
								<th style="padding: 5px;">
									<label for="test-member-id"><?php esc_html_e( 'Member ID:', 'brag-book-gallery' ); ?></label>
								</th>
								<td style="padding: 5px;">
									<input type="number" id="test-member-id" placeholder="129" class="input-field regular-text" style="width: 150px;">
									<span class="description"><?php esc_html_e( 'Used by: Cases, Filters (default: 129) - Not used by Carousel', 'brag-book-gallery' ); ?></span>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="api-test-container">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Endpoint', 'brag-book-gallery' ); ?></th>
								<th><?php esc_html_e( 'Method', 'brag-book-gallery' ); ?></th>
								<th><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
								<th><?php esc_html_e( 'Action', 'brag-book-gallery' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<!-- Terms Endpoint -->
							<tr>
								<td><code>/api/plugin/v2/terms</code></td>
								<td><span class="method-badge method-get">GET</span></td>
								<td><?php esc_html_e( 'Get categories and procedures with case counts', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="terms"
									        data-method="GET"
									        data-url="/api/plugin/v2/terms">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Cases Endpoint (v1) -->
							<tr>
								<td><code>/api/plugin/combine/cases</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Get paginated case listings (v1)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="cases"
									        data-method="POST"
									        data-url="/api/plugin/combine/cases">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Cases Endpoint (v2) -->
							<tr>
								<td><code>/api/plugin/v2/cases/</code></td>
								<td><span class="method-badge method-get">GET</span></td>
								<td><?php esc_html_e( 'Get paginated case listings (v2 - requires procedureId)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="cases-v2"
									        data-method="GET"
									        data-url="/api/plugin/v2/cases/">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Carousel Endpoint -->
							<tr>
								<td><code>/api/plugin/carousel</code></td>
								<td><span class="method-badge method-get">GET</span></td>
								<td><?php esc_html_e( 'Get carousel data (requires procedureId)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="carousel"
									        data-method="GET"
									        data-url="/api/plugin/carousel">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Filters Endpoint -->
							<tr>
								<td><code>/api/plugin/combine/filters</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Get available filter options', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="filters"
									        data-method="POST"
									        data-url="/api/plugin/combine/filters">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Favorites List (v2) -->
							<tr>
								<td><code>/api/plugin/v2/leads/favorites/list</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Get user\'s favorite cases (v2 - Bearer auth)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="favorites-list-v2"
									        data-method="POST"
									        data-url="/api/plugin/v2/leads/favorites/list"
									        data-v2-bearer="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Favorites Add (v2) -->
							<tr>
								<td><code>/api/plugin/v2/leads/favorites/add</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Add case to favorites (v2 - Bearer auth)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="favorites-add-v2"
									        data-method="POST"
									        data-url="/api/plugin/v2/leads/favorites/add"
									        data-v2-bearer="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Favorites Remove (v2) -->
							<tr>
								<td><code>/api/plugin/v2/leads/favorites/remove</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Remove case from favorites (v2 - Bearer auth)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="favorites-remove-v2"
									        data-method="POST"
									        data-url="/api/plugin/v2/leads/favorites/remove"
									        data-v2-bearer="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Sitemap -->
							<tr>
								<td><code>/api/plugin/sitemap</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Generate sitemap data', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="sitemap"
									        data-method="POST"
									        data-url="/api/plugin/sitemap">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Single Case (v1) -->
							<tr>
								<td><code>/api/plugin/combine/cases/{id}</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td>
									<?php esc_html_e( 'Get specific case details (v1)', 'brag-book-gallery' ); ?>
									<input type="number" id="case-id-input" placeholder="Case ID" class="small-text" style="margin-left: 10px;">
								</td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="single-case"
									        data-method="POST"
									        data-url="/api/plugin/combine/cases/"
									        data-needs-id="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Single Case (v2) -->
							<tr>
								<td><code>/api/plugin/v2/cases/{id}</code></td>
								<td><span class="method-badge method-get">GET</span></td>
								<td>
									<?php esc_html_e( 'Get specific case details (v2 - Bearer auth)', 'brag-book-gallery' ); ?>
									<input type="number" id="case-id-v2-input" placeholder="Case ID" class="small-text" style="margin-left: 10px;">
								</td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="single-case-v2"
									        data-method="GET"
									        data-url="/api/plugin/v2/cases/"
									        data-needs-case-id-v2="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Consultations (v2) -->
							<tr>
								<td><code>/api/plugin/v2/leads/consultations</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td>
									<?php esc_html_e( 'Submit consultation request (v2 - Bearer auth)', 'brag-book-gallery' ); ?>
								</td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="consultations-v2"
									        data-method="POST"
									        data-url="/api/plugin/v2/leads/consultations"
									        data-test-consultation="true"
									        data-v2-bearer="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Views -->
							<tr>
								<td><code>/api/plugin/views</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td>
									<?php esc_html_e( 'Track case view for analytics', 'brag-book-gallery' ); ?>
									<input type="number" id="view-case-id-input" placeholder="Case ID" class="small-text" style="margin-left: 10px;">
								</td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="views"
									        data-method="POST"
									        data-url="/api/plugin/views"
									        data-needs-case-id="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Token Validation -->
							<tr>
								<td><code>/api/plugin/v2/validation/token</code></td>
								<td><span class="method-badge method-get">GET</span></td>
								<td><?php esc_html_e( 'Validate API token (Bearer auth)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button button-secondary test-endpoint-btn"
									        data-endpoint="validate-token"
									        data-method="GET"
									        data-url="/api/plugin/v2/validation/token">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Response Display Area -->
				<div class="api-response-container" style="display: none;">
					<h3><?php esc_html_e( 'API Response', 'brag-book-gallery' ); ?></h3>
					<div class="response-header">
						<span class="response-status"></span>
						<span class="response-time"></span>
						<button class="button button-small copy-response-btn">
							<?php esc_html_e( 'Copy Response', 'brag-book-gallery' ); ?>
						</button>
						<button class="button button-small clear-response-btn">
							<?php esc_html_e( 'Clear', 'brag-book-gallery' ); ?>
						</button>
					</div>
					<div class="request-details">
						<h4><?php esc_html_e( 'Request Details:', 'brag-book-gallery' ); ?></h4>
						<textarea class="api-request-content" readonly rows="10"></textarea>
					</div>
					<div class="response-details">
						<h4><?php esc_html_e( 'Response:', 'brag-book-gallery' ); ?></h4>
						<textarea class="api-response-content" readonly rows="15"></textarea>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<style>
		.api-test-config {
			background: var(--slate-100);
			border: 1px solid var(--slate-200);
			padding: var(--space-6);
			border-radius: 0.25rem;
			margin-block: var(--space-6);
			display: flex;
			gap: var(--space-6);
		}
		.config-info, .test-parameters {
			flex: 1;
		}
		.config-info ul {
			margin: 10px 0 0 20px;
		}
		.config-info code {
			background: #fff;
			padding: 2px 5px;
			border-radius: 3px;
		}
		.test-parameters {
			border-left: 1px solid #c3c4c7;
			padding-left: 30px;
		}
		.test-parameters .description {
			margin: 10px 0;
		}
		.method-badge {
			display: inline-block;
			padding: 3px 8px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
		}
		.method-get {
			background: #00a32a;
			color: white;
		}
		.method-post {
			background: #2271b1;
			color: white;
		}
		.api-test-container {
			margin: 20px 0;
		}
		.api-response-container {
			margin-top: 30px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 20px;
		}
		.response-header {
			display: flex;
			align-items: center;
			gap: 15px;
			margin-bottom: 15px;
			padding-bottom: 15px;
			border-bottom: 1px solid #dcdcde;
		}
		.response-status {
			font-weight: 600;
		}
		.response-status.success {
			color: #00a32a;
		}
		.response-status.error {
			color: #d63638;
		}
		.response-time {
			color: #646970;
			font-size: 13px;
		}
		.api-response-content, .api-request-content {
			width: 100%;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
			font-size: 13px;
			line-height: 1.6;
			resize: vertical;
		}

		/* CodeMirror will handle the styling, but we need some basic fallback */
		.CodeMirror {
			border: 1px solid #dcdcde;
			border-radius: 4px;
			max-height: 400px;
		}

		.CodeMirror-scroll {
			max-height: 400px;
		}
		.request-details, .response-details {
			margin-top: 20px;
		}
		.request-details h4, .response-details h4 {
			margin: 0 0 10px 0;
			color: #1d2327;
			font-size: 14px;
		}
		.test-endpoint-btn:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}
		.spinner {
			display: inline-block;
			margin-left: 5px;
		}
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Get all API tokens and property IDs as arrays
			const apiTokens = <?php echo wp_json_encode( array_values( array_filter( $api_tokens ) ) ); ?>;
			const websitePropertyIds = <?php echo wp_json_encode( array_values( array_filter( array_map( 'intval', $website_property_ids ) ) ) ); ?>;
			const baseUrl = 'https://app.bragbookgallery.com';
			const ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			const nonce = '<?php echo wp_create_nonce( 'brag_book_api_test' ); ?>';

			// Debug log the tokens and IDs
			console.log('API Tokens:', apiTokens);
			console.log('Website Property IDs:', websitePropertyIds);

			// Check if we have valid tokens
			if (!apiTokens || apiTokens.length === 0) {
				console.error('No valid API tokens found. Please check your API settings.');
			}

			// Helper functions
			const showElement = (selector) => {
				const el = document.querySelector(selector);
				if (el) el.style.display = 'block';
			};

			const hideElement = (selector) => {
				const el = document.querySelector(selector);
				if (el) el.style.display = 'none';
			};

			const setText = (selector, text) => {
				const el = document.querySelector(selector);
				if (el) {
					if (el.tagName.toLowerCase() === 'textarea') {
						el.value = text;
						// Trigger CodeMirror refresh if it exists
						if (el.codeMirrorInstance) {
							el.codeMirrorInstance.setValue(text);
							el.codeMirrorInstance.refresh();
						}
					} else {
						el.textContent = text;
					}
				}
			};

			const addClass = (selector, className) => {
				const el = document.querySelector(selector);
				if (el) el.classList.add(className);
			};

			const removeClass = (selector, className) => {
				const el = document.querySelector(selector);
				if (el) el.classList.remove(className);
			};

			// Initialize CodeMirror editors
			let requestEditor = null;
			let responseEditor = null;

			const initializeCodeMirror = () => {
				// Check if wp.codeEditor is available
				if (typeof wp !== 'undefined' && wp.codeEditor) {
					const editorSettings = wp.codeEditor.defaultSettings ? wp.codeEditor.defaultSettings : {};
					editorSettings.codemirror = {
						...editorSettings.codemirror,
						mode: 'application/json',
						lineNumbers: true,
						lineWrapping: true,
						readOnly: true,
						foldGutter: true,
						gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
						theme: 'default'
					};

					// Initialize request details editor
					const requestTextarea = document.querySelector('.api-request-content');
					if (requestTextarea && !requestEditor) {
						requestEditor = wp.codeEditor.initialize(requestTextarea, editorSettings);
						requestTextarea.codeMirrorInstance = requestEditor.codemirror;
					}

					// Initialize response editor
					const responseTextarea = document.querySelector('.api-response-content');
					if (responseTextarea && !responseEditor) {
						responseEditor = wp.codeEditor.initialize(responseTextarea, editorSettings);
						responseTextarea.codeMirrorInstance = responseEditor.codemirror;
					}
				}
			};

			// Format JSON for display
			const formatJSON = (json) => {
				if (typeof json === 'string') {
					try {
						json = JSON.parse(json);
					} catch (e) {
						return json; // Return as-is if not valid JSON
					}
				}
				return JSON.stringify(json, null, 2);
			};

			// Test endpoint button click
			document.querySelectorAll('.test-endpoint-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					const endpoint = this.dataset.endpoint;
					const method = this.dataset.method;
					let url = baseUrl + this.dataset.url;
					const needsId = this.dataset.needsId;
					const needsCaseIdV2 = this.dataset.needsCaseIdV2;

					// Check if case ID is needed (v1 endpoint)
					if (needsId) {
						const caseIdInput = document.getElementById('case-id-input');
						const caseId = caseIdInput ? caseIdInput.value : '';
						if (!caseId) {
							alert('Please enter a Case ID');
							return;
						}
						url += caseId;
					}

					// Check if case ID is needed (v2 endpoint)
					if (needsCaseIdV2) {
						const caseIdInput = document.getElementById('case-id-v2-input');
						const caseId = caseIdInput ? caseIdInput.value : '';
						if (!caseId) {
							alert('Please enter a Case ID for v2 endpoint');
							return;
						}
						url += caseId;
					}

					// Disable button and show loading
					this.disabled = true;
					const originalText = this.textContent;
					this.innerHTML = 'Testing... <span class="spinner is-active"></span>';

					// Clear previous response
					hideElement('.api-response-container');
					setText('.api-response-content', '');
					setText('.api-request-content', '');

					// Start timer
					const startTime = Date.now();

					// Build request body for POST requests
					let requestBody = null;
					let requestHeaders = {
						'Accept': 'application/json'
					};

					if (method === 'POST') {
						requestHeaders['Content-Type'] = 'application/json';

						// Build request body with arrays
						requestBody = {
							apiTokens: apiTokens,
							websitePropertyIds: websitePropertyIds
						};

						// Get optional parameters from inputs
						const procedureInput = document.getElementById('test-procedure-id');
						const memberInput = document.getElementById('test-member-id');
						const testProcedureId = procedureInput ? (procedureInput.value || null) : null;
						const testMemberId = memberInput ? (memberInput.value || null) : null;

						// Add endpoint-specific parameters
						switch(endpoint) {
							case 'cases':
								// Cases needs count for pagination
								requestBody.count = 1;
								// Optional: add procedure filter if specified
								if (testProcedureId) {
									requestBody.procedureIds = [parseInt(testProcedureId)];
								}
								// Optional: add member filter if specified
								if (testMemberId) {
									requestBody.memberId = parseInt(testMemberId);
								}
								break;

							case 'single-case':
								// Single case needs procedureIds (use default if not provided)
								requestBody.procedureIds = [parseInt(testProcedureId || 6851)];
								// Optional: add member ID if specified
								if (testMemberId) {
									requestBody.memberId = parseInt(testMemberId);
								}
								// Add caseId from input
								const singleCaseIdInput = document.getElementById('case-id-input');
								if (singleCaseIdInput && singleCaseIdInput.value) {
									requestBody.caseId = parseInt(singleCaseIdInput.value);
								}
								break;

							case 'single-case-v2':
								// V2 single case endpoint
								const caseIdV2Input = document.getElementById('case-id-v2-input');
								if (caseIdV2Input && caseIdV2Input.value) {
									requestBody.caseId = parseInt(caseIdV2Input.value);
								}
								// Optional: add procedure ID if specified
								if (testProcedureId) {
									requestBody.procedureId = parseInt(testProcedureId);
								}
								break;

							case 'cases-v2':
								// V2 cases endpoint requires procedureId
								if (!testProcedureId) {
									alert('Please enter a Procedure ID for v2 cases endpoint');
									button.disabled = false;
									button.textContent = originalText;
									return;
								}
								requestBody.procedureId = parseInt(testProcedureId);
								break;

							case 'filters':
								// Filters needs procedureIds (use default if not provided)
								requestBody.procedureIds = [parseInt(testProcedureId || 6851)];
								break;

							case 'favorites-list':
								// Favorites list uses default body (can add email later if needed)
								break;

							case 'sitemap':
								// Sitemap uses default body
								break;

							case 'consultations':
								// Consultations needs special handling - body is the form data
								// URL needs apiToken and websitepropertyId as query params
								url += '?apiToken=' + encodeURIComponent(apiTokens[0]) +
								       '&websitepropertyId=' + encodeURIComponent(websitePropertyIds[0]);

								// Body should be the consultation data
								requestBody = {
									email: "test@example.com",
									phone: "(555) 123-4567",
									name: "Test User",
									details: "This is a test consultation submission from the API testing page."
								};
								console.log('Consultations endpoint - URL:', url);
								console.log('Consultations endpoint - Body:', requestBody);
								break;

							case 'views':
								// Views needs case ID
								const viewCaseIdInput = document.getElementById('view-case-id-input');
								const viewCaseId = viewCaseIdInput ? viewCaseIdInput.value : '';
								if (!viewCaseId || viewCaseId <= 0) {
									alert('Please enter a valid Case ID for view tracking');
									button.disabled = false;
									button.textContent = originalText;
									return;
								}

								// URL needs apiToken as query param
								url += '?apiToken=' + encodeURIComponent(apiTokens[0]);

								// Body should contain caseId
								requestBody = {
									caseId: parseInt(viewCaseId)
								};
								console.log('Views endpoint - URL:', url);
								console.log('Views endpoint - Body:', requestBody);
								break;
						}

						console.log('POST Request Body:', requestBody);
						console.log('POST Request JSON:', JSON.stringify(requestBody));

					} else {
						// For GET requests, handle different endpoint types
						if (endpoint === 'terms') {
							// Terms endpoint uses Bearer auth, no query params
							requestHeaders['Authorization'] = 'Bearer ' + apiTokens[0];
							console.log('Terms GET Request URL:', url);
							console.log('Terms will use Bearer auth with token:', apiTokens[0].substring(0, 10) + '...');
						} else if (endpoint === 'validate-token') {
							// Token validation uses Bearer auth and websitePropertyId query param
							const params = new URLSearchParams({
								websitePropertyId: websitePropertyIds[0].toString()
							});
							url += '?' + params.toString();
							console.log('Token Validation GET Request URL:', url);
							console.log('Token Validation will use Bearer auth with token:', apiTokens[0].substring(0, 10) + '...');
						} else {
							// For other GET requests (carousel), add params to URL
							const procedureInput = document.getElementById('test-procedure-id');

							// Build parameters object
							const params = new URLSearchParams({
								websitePropertyId: websitePropertyIds[0].toString(),
								start: '1',
								limit: '10',
								apiToken: apiTokens[0]
							});

							// For carousel endpoint, include procedureId (no memberId needed)
							if (endpoint === 'carousel') {
								// Use provided value or default
								params.append('procedureId', procedureInput?.value || '3405');
							}

							url += '?' + params.toString();
							console.log('GET Request URL:', url);
						}
					}

					// Store request details for display
					const requestDetails = {
						url: url,
						method: method,
						headers: requestHeaders,
						body: requestBody
					};

					console.log('Making API request:', requestDetails);

					// Make the request through WordPress AJAX (server-side proxy)
					const button = this;

					// Prepare form data for AJAX
					const formData = new FormData();
					formData.append('action', 'brag_book_test_api');
					formData.append('nonce', nonce);
					formData.append('endpoint', endpoint);
					formData.append('method', method);
					formData.append('url', url);
					if (requestBody) {
						formData.append('body', JSON.stringify(requestBody));
					}

					// Make the request to WordPress AJAX
					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(result => {
						const endTime = Date.now();
						const duration = endTime - startTime;

						// Show request details
						setText('.api-request-content', formatJSON(requestDetails));

						console.log('AJAX Response:', result);

						if (result.success) {
							const apiResponse = result.data;

							if (apiResponse.status >= 200 && apiResponse.status < 300) {
								// Show success response
								showElement('.api-response-container');
								setText('.response-status', `Success (${apiResponse.status})`);
								addClass('.response-status', 'success');
								removeClass('.response-status', 'error');
								setText('.response-time', duration + 'ms');
								setText('.api-response-content', formatJSON(apiResponse.body));
							} else {
								// Show error response from API
								showElement('.api-response-container');
								setText('.response-status', `Error: ${apiResponse.status}`);
								addClass('.response-status', 'error');
								removeClass('.response-status', 'success');
								setText('.response-time', duration + 'ms');
								setText('.api-response-content', formatJSON({
									status: apiResponse.status,
									body: apiResponse.body,
									headers: apiResponse.headers
								}));
							}
						} else {
							// Show WordPress/network error
							showElement('.api-response-container');
							setText('.response-status', 'Server Error');
							addClass('.response-status', 'error');
							removeClass('.response-status', 'success');
							setText('.response-time', duration + 'ms');
							setText('.api-response-content', formatJSON(result.data || {
								message: 'Failed to connect to API'
							}));
						}

						// Re-enable button
						button.disabled = false;
						button.textContent = originalText;
					})
					.catch(error => {
						const endTime = Date.now();
						const duration = endTime - startTime;

						console.error('Fetch Error:', error);

						// Show request details
						setText('.api-request-content', formatJSON(requestDetails));

						// Show error response
						showElement('.api-response-container');
						setText('.response-status', 'Network Error');
						addClass('.response-status', 'error');
						removeClass('.response-status', 'success');
						setText('.response-time', duration + 'ms');
						setText('.api-response-content', formatJSON({
							error: error.message,
							message: 'Could not connect to WordPress AJAX'
						}));

						// Re-enable button
						button.disabled = false;
						button.textContent = originalText;
					});
				});
			});

			// Copy response button
			document.querySelector('.copy-response-btn')?.addEventListener('click', function() {
				const responseContent = document.querySelector('.api-response-content');
				// Get the plain text content, removing HTML tags
				const responseText = responseContent ? responseContent.textContent || responseContent.innerText : '';
				if (responseText) {
					navigator.clipboard.writeText(responseText).then(() => {
						alert('Response copied to clipboard!');
					}).catch(err => {
						console.error('Failed to copy:', err);
					});
				}
			});

			// Clear response button
			document.querySelector('.clear-response-btn')?.addEventListener('click', function() {
				hideElement('.api-response-container');
				setText('.api-response-content', '');
				setText('.api-request-content', '');
			});

			// Initialize CodeMirror when the response container becomes visible
			const observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
						const container = document.querySelector('.api-response-container');
						if (container && container.style.display !== 'none') {
							// Initialize CodeMirror after a small delay to ensure DOM is ready
							setTimeout(initializeCodeMirror, 100);
						}
					}
				});
			});

			const container = document.querySelector('.api-response-container');
			if (container) {
				observer.observe(container, {
					attributes: true,
					attributeFilter: ['style']
				});
			}
		});
		</script>

		<?php
		$this->render_footer();
	}
}
