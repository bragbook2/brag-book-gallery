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

namespace BRAGBookGallery\Includes\Admin;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * API Testing Settings Class
 *
 * Provides a testing interface for all BragBook API endpoints.
 * Allows administrators to verify API connectivity and test various endpoints
 * with real-time response display.
 *
 * @since 3.0.0
 */
class Settings_Api_Test extends Settings_Base {

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
		}
		
		// Get request parameters
		$endpoint = sanitize_text_field( $_POST['endpoint'] ?? '' );
		$method = sanitize_text_field( $_POST['method'] ?? 'POST' );
		$url = sanitize_url( $_POST['url'] ?? '' );
		$body = isset( $_POST['body'] ) ? json_decode( stripslashes( $_POST['body'] ), true ) : null;
		
		// Make the API request using wp_remote_request
		$args = array(
			'method' => $method,
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);
		
		// Only add Content-Type for POST requests with body
		if ( $method === 'POST' && $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $body );
		}
		
		// For GET requests, URL already contains query parameters
		
		// Log the request for debugging
		error_log( 'API Test Request: ' . print_r( array( 'url' => $url, 'method' => $method, 'body' => $body ), true ) );
		
		// Make the request
		$response = wp_remote_request( $url, $args );
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => $response->get_error_message(),
				'code' => $response->get_error_code(),
				'details' => 'Failed to connect to API endpoint: ' . $url,
			) );
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		// Try to decode JSON response
		$decoded_body = json_decode( $response_body, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$response_body = $decoded_body;
		}
		
		wp_send_json_success( array(
			'status' => $response_code,
			'body' => $response_body,
			'headers' => wp_remote_retrieve_headers( $response )->getAll(),
		) );
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
				<div class="brag-book-gallery-notice brag-book-gallery-notice-warning">
					<p>
						<?php esc_html_e( 'Please configure your API settings first.', 'brag-book-gallery' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>">
							<?php esc_html_e( 'Go to API Settings', 'brag-book-gallery' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Test various BragBook API endpoints to verify connectivity and data retrieval.', 'brag-book-gallery' ); ?>
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
									<input type="number" id="test-procedure-id" placeholder="6839" class="regular-text" style="width: 150px;">
									<span class="description"><?php esc_html_e( 'Used by: Carousel, Cases, Filters (default: 6839)', 'brag-book-gallery' ); ?></span>
								</td>
							</tr>
							<tr>
								<th style="padding: 5px;">
									<label for="test-member-id"><?php esc_html_e( 'Member ID:', 'brag-book-gallery' ); ?></label>
								</th>
								<td style="padding: 5px;">
									<input type="number" id="test-member-id" placeholder="129" class="regular-text" style="width: 150px;">
									<span class="description"><?php esc_html_e( 'Used by: Carousel, Cases (default: 129)', 'brag-book-gallery' ); ?></span>
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
							<!-- Sidebar Endpoint -->
							<tr>
								<td><code>/api/plugin/combine/sidebar</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Get categories and procedures with case counts', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button test-endpoint-btn" 
									        data-endpoint="sidebar"
									        data-method="POST"
									        data-url="/api/plugin/combine/sidebar">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Cases Endpoint -->
							<tr>
								<td><code>/api/plugin/combine/cases</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Get paginated case listings', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button test-endpoint-btn" 
									        data-endpoint="cases"
									        data-method="POST"
									        data-url="/api/plugin/combine/cases">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Carousel Endpoint -->
							<tr>
								<td><code>/api/plugin/carousel</code></td>
								<td><span class="method-badge method-get">GET</span></td>
								<td><?php esc_html_e( 'Get carousel data (may require procedureId & memberId)', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button test-endpoint-btn" 
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
									<button class="button test-endpoint-btn" 
									        data-endpoint="filters"
									        data-method="POST"
									        data-url="/api/plugin/combine/filters">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Favorites List -->
							<tr>
								<td><code>/api/plugin/combine/favorites/list</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td><?php esc_html_e( 'Get user\'s favorite cases', 'brag-book-gallery' ); ?></td>
								<td>
									<button class="button test-endpoint-btn" 
									        data-endpoint="favorites-list"
									        data-method="POST"
									        data-url="/api/plugin/combine/favorites/list">
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
									<button class="button test-endpoint-btn" 
									        data-endpoint="sitemap"
									        data-method="POST"
									        data-url="/api/plugin/sitemap">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Single Case -->
							<tr>
								<td><code>/api/plugin/combine/cases/{id}</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td>
									<?php esc_html_e( 'Get specific case details', 'brag-book-gallery' ); ?>
									<input type="number" id="case-id-input" placeholder="Case ID" class="small-text" style="margin-left: 10px;">
								</td>
								<td>
									<button class="button test-endpoint-btn" 
									        data-endpoint="single-case"
									        data-method="POST"
									        data-url="/api/plugin/combine/cases/"
									        data-needs-id="true">
										<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>

							<!-- Consultations -->
							<tr>
								<td><code>/api/plugin/consultations</code></td>
								<td><span class="method-badge method-post">POST</span></td>
								<td>
									<?php esc_html_e( 'Submit consultation request (Test with sample data)', 'brag-book-gallery' ); ?>
								</td>
								<td>
									<button class="button test-endpoint-btn" 
									        data-endpoint="consultations"
									        data-method="POST"
									        data-url="/api/plugin/consultations"
									        data-test-consultation="true">
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
						<pre class="api-request-content"></pre>
					</div>
					<div class="response-details">
						<h4><?php esc_html_e( 'Response:', 'brag-book-gallery' ); ?></h4>
						<pre class="api-response-content"></pre>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<style>
		.api-test-config {
			background: #f0f0f1;
			padding: 15px;
			border-radius: 4px;
			margin: 20px 0;
			display: flex;
			gap: 30px;
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
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 15px;
			overflow-x: auto;
			max-height: 400px;
			overflow-y: auto;
			font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
			font-size: 13px;
			line-height: 1.4;
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
			const apiTokens = <?php echo wp_json_encode( $api_tokens ); ?>;
			const websitePropertyIds = <?php echo wp_json_encode( array_map( 'intval', $website_property_ids ) ); ?>;
			const baseUrl = 'https://app.bragbookgallery.com';
			const ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			const nonce = '<?php echo wp_create_nonce( 'brag_book_api_test' ); ?>';

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
				if (el) el.textContent = text;
			};
			
			const addClass = (selector, className) => {
				const el = document.querySelector(selector);
				if (el) el.classList.add(className);
			};
			
			const removeClass = (selector, className) => {
				const el = document.querySelector(selector);
				if (el) el.classList.remove(className);
			};

			// Test endpoint button click
			document.querySelectorAll('.test-endpoint-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					const endpoint = this.dataset.endpoint;
					const method = this.dataset.method;
					let url = baseUrl + this.dataset.url;
					const needsId = this.dataset.needsId;
					
					// Check if case ID is needed
					if (needsId) {
						const caseIdInput = document.getElementById('case-id-input');
						const caseId = caseIdInput ? caseIdInput.value : '';
						if (!caseId) {
							alert('Please enter a Case ID');
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
							case 'sidebar':
								// Sidebar only needs apiTokens
								requestBody = {
									apiTokens: apiTokens.filter(token => token && token.length > 0)
								};
								break;
							
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
						}
						
						console.log('POST Request Body:', requestBody);
						console.log('POST Request JSON:', JSON.stringify(requestBody));
						
						// For sidebar, verify the exact format
						if (endpoint === 'sidebar') {
							console.log('Sidebar request verification:');
							console.log('Expected format: {"apiTokens":["your-token-here"]}');
							console.log('Actual format:', JSON.stringify(requestBody));
						}
					} else {
						// For GET requests (carousel), add params to URL
						const procedureInput = document.getElementById('test-procedure-id');
						const memberInput = document.getElementById('test-member-id');
						
						// Build parameters object
						const params = new URLSearchParams({
							websitePropertyId: websitePropertyIds[0].toString(),
							start: '1',
							limit: '10',
							apiToken: apiTokens[0]
						});
						
						// For carousel endpoint, always include procedureId and memberId with defaults
						if (endpoint === 'carousel') {
							// Use provided values or defaults
							params.append('procedureId', procedureInput?.value || '6839');
							params.append('memberId', memberInput?.value || '129');
						}
						
						url += '?' + params.toString();
						console.log('GET Request URL:', url);
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
						setText('.api-request-content', JSON.stringify(requestDetails, null, 2));
						
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
								setText('.api-response-content', JSON.stringify(apiResponse.body, null, 2));
							} else {
								// Show error response from API
								showElement('.api-response-container');
								setText('.response-status', `Error: ${apiResponse.status}`);
								addClass('.response-status', 'error');
								removeClass('.response-status', 'success');
								setText('.response-time', duration + 'ms');
								setText('.api-response-content', JSON.stringify({
									status: apiResponse.status,
									body: apiResponse.body,
									headers: apiResponse.headers
								}, null, 2));
							}
						} else {
							// Show WordPress/network error
							showElement('.api-response-container');
							setText('.response-status', 'Server Error');
							addClass('.response-status', 'error');
							removeClass('.response-status', 'success');
							setText('.response-time', duration + 'ms');
							setText('.api-response-content', JSON.stringify(result.data || {
								message: 'Failed to connect to API'
							}, null, 2));
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
						setText('.api-request-content', JSON.stringify(requestDetails, null, 2));
						
						// Show error response
						showElement('.api-response-container');
						setText('.response-status', 'Network Error');
						addClass('.response-status', 'error');
						removeClass('.response-status', 'success');
						setText('.response-time', duration + 'ms');
						setText('.api-response-content', JSON.stringify({
							error: error.message,
							message: 'Could not connect to WordPress AJAX'
						}, null, 2));
						
						// Re-enable button
						button.disabled = false;
						button.textContent = originalText;
					});
				});
			});
			
			// Copy response button
			document.querySelector('.copy-response-btn')?.addEventListener('click', function() {
				const responseContent = document.querySelector('.api-response-content');
				const responseText = responseContent ? responseContent.textContent : '';
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
		});
		</script>

		<?php
		$this->render_footer();
	}
}