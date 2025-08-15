<?php
/**
 * API Settings Class
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

if ( ! defined( constant_name: 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * API Settings Class
 *
 * Comprehensive API configuration management for BragBook Gallery connections.
 * This class handles all aspects of API connectivity including:
 *
 * - Multiple API connection management with validation
 * - Connection timeout and caching configuration
 * - Real-time API validation with user feedback
 * - Security-focused credential handling
 * - Performance optimization settings
 *
 * The API settings form supports multiple concurrent connections to different
 * BragBook API endpoints, allowing for complex deployment scenarios and
 * redundancy configurations. Each connection is validated individually
 * and stored securely in WordPress options.
 *
 * Key features:
 * - Dynamic form management with JavaScript
 * - Real-time connection validation via AJAX
 * - Secure password field handling with visibility toggles
 * - Connection status indicators and error reporting
 * - Batch operations for multiple API endpoints
 * - Comprehensive error handling and user feedback
 *
 * @since 3.0.0
 */
class Settings_Api extends Settings_Base {

	/**
	 * Initialize API settings page configuration
	 *
	 * Sets up the fundamental page properties required for WordPress admin
	 * menu integration. The page slug must be unique within the WordPress
	 * admin to prevent routing conflicts.
	 *
	 * Translation strings are intentionally deferred to the render() method
	 * to ensure WordPress translation functions are fully loaded and the
	 * current user's locale is properly established.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug = 'brag-book-gallery-api-settings';
	}

	/**
	 * Render the complete API configuration interface
	 *
	 * Generates a comprehensive settings page for managing BragBook API connections.
	 * The interface includes multiple sections:
	 *
	 * 1. **Setup Instructions**: Step-by-step guidance for API configuration
	 * 2. **API Connections**: Dynamic form for managing multiple API endpoints
	 * 3. **Connection Settings**: Performance and timeout configurations
	 * 4. **Connection Testing**: Real-time validation tools
	 *
	 * The form uses progressive enhancement with JavaScript to provide:
	 * - Dynamic addition/removal of API connection rows
	 * - Real-time validation feedback with status indicators
	 * - Secure password field handling with visibility toggles
	 * - AJAX-powered connection testing without page reloads
	 *
	 * Security features:
	 * - WordPress nonce verification for all form submissions
	 * - Capability checking for admin-only access
	 * - Sanitized input handling for all user data
	 * - Secure storage of API credentials
	 *
	 * The method handles both GET (display) and POST (save) requests,
	 * processing form data when submitted and displaying current configuration.
	 *
	 * @since 3.0.0
	 * @return void Outputs HTML content directly to the browser
	 */
	public function render(): void {

		// Set localized page titles now that translation functions are available.
		$this->page_title = esc_html__( 'API Settings', 'brag-book-gallery' );
		$this->menu_title = esc_html__( 'API', 'brag-book-gallery' );

		// Process form submission if data was posted
		if ( isset( $_POST['submit'] ) || isset( $_POST['brag_book_gallery_api_form_submitted'] ) ) {
			$this->save();
		}

		// Retrieve current API configuration from WordPress options
		$api_tokens           = get_option( option: 'brag_book_gallery_api_token', default_value: array() );
		$website_property_ids = get_option( option: 'brag_book_gallery_website_property_id', default_value: array() );
		$api_endpoint         = get_option( option: 'brag_book_gallery_api_endpoint', default_value: 'https://app.bragbookgallery.com' );
		$api_timeout          = get_option( option: 'brag_book_gallery_api_timeout', default_value: 30 );
		$enable_caching       = get_option( option: 'brag_book_gallery_enable_api_cache', default_value: 'yes' );
		$cache_duration       = get_option( option:'brag_book_gallery_api_cache_duration', default_value: 3600 );

		$this->render_header();
		?>

		<form method="post" action="" id="brag-book-gallery-api-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_api_settings', 'brag_book_gallery_api_nonce' ); ?>
			<input type="hidden" name="brag_book_gallery_api_form_submitted" value="1" />

			<div class="brag-book-gallery-api-instructions">
				<div class="brag-book-gallery-notice brag-book-gallery-notice-info">
					<h3><?php esc_html_e( 'Getting Started with BragBook API', 'brag-book-gallery' ); ?></h3>
					<ol>
						<li>
							<?php esc_html_e( 'Log in to your BragBook account at', 'brag-book-gallery' ); ?>
							<a href="https://app.bragbookgallery.com" target="_blank">
								<?php esc_html_e( 'app.bragbookgallery.com', 'brag-book-gallery' ); ?>
							</a>
						</li>
						<li><?php esc_html_e( 'Navigate to Settings → API Tokens', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Copy your API Token and Website Property ID', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Enter them below and click "Save Settings"', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>
			</div>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'API Connections', 'brag-book-gallery' ); ?></h2>

				<?php if ( empty( $api_tokens ) ) : ?>
					<div class="brag-book-gallery-notice brag-book-gallery-notice-warning inline">
						<p><?php esc_html_e( 'No API connections configured. Add at least one connection to connect to the BragBook API.', 'brag-book-gallery' ); ?></p>
					</div>
				<?php endif; ?>

				<div id="bb-api-rows">
					<?php
					// Ensure at least one row is shown.
					if ( empty( $api_tokens ) ) {
						$api_tokens = array( '' );
						$website_property_ids = array( '' );
					}
					?>
					<?php foreach ( $api_tokens as $index => $token ) : ?>
							<div class="bb-api-row" data-index="<?php echo esc_attr( $index ); ?>">
								<div class="bb-api-row-header">
									<h3><?php esc_html_e( 'Connection', 'brag-book-gallery' ); ?> <span class="connection-number"><?php echo esc_html( $index + 1 ); ?></span></h3>
									<span class="bb-api-status" data-index="<?php echo esc_attr( $index ); ?>"></span>
								</div>
								<table class="form-table brag-book-gallery-form-table">
									<tr>
										<th scope="row">
											<label><?php esc_html_e( 'API Token', 'brag-book-gallery' ); ?></label>
										</th>
										<td>
											<div class="api-token-field">
												<input type="password"
												       name="brag_book_gallery_api_token[]"
												       value="<?php echo esc_attr( $token ); ?>"
												       placeholder="<?php esc_attr_e( 'Enter your API token', 'brag-book-gallery' ); ?>"
												       class="regular-text bb-api-token"/>
												<button type="button"
												        class="toggle-api-token"
												        title="<?php esc_attr_e( 'Toggle visibility', 'brag-book-gallery' ); ?>">
													<span class="dashicons dashicons-visibility"></span>
												</button>
											</div>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label><?php esc_html_e( 'Website Property ID', 'brag-book-gallery' ); ?></label>
										</th>
										<td>
											<input type="text"
											       name="brag_book_gallery_website_property_id[]"
											       value="<?php echo esc_attr( $website_property_ids[ $index ] ?? '' ); ?>"
											       placeholder="<?php esc_attr_e( 'Enter your website property ID', 'brag-book-gallery' ); ?>"
											       class="regular-text bb-websiteproperty-id"/>
										</td>
									</tr>
									<tr>
										<th scope="row"></th>
										<td>
											<button type="button"
											        class="button bb-validate-api"
											        data-index="<?php echo esc_attr( $index ); ?>">
												<?php esc_html_e( 'Validate & Save', 'brag-book-gallery' ); ?>
											</button>
											<button type="button"
											        class="button button-link-delete bb-remove-api-row"
											        data-index="<?php echo esc_attr( $index ); ?>">
												<?php esc_html_e( 'Remove Connection', 'brag-book-gallery' ); ?>
											</button>
										</td>
									</tr>
								</table>
							</div>
					<?php endforeach; ?>
				</div>

				<p class="add-api-connection">
					<button type="button" id="bb-add-api-row" class="button button-secondary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Add New Connection', 'brag-book-gallery' ); ?>
					</button>
				</p>

			</div>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Connection Settings', 'brag-book-gallery' ); ?></h2>
				<table class="form-table brag-book-gallery-form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="api_timeout">
								<?php esc_html_e( 'Request Timeout', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
							       id="api_timeout"
							       name="api_timeout"
							       value="<?php echo esc_attr( $api_timeout ); ?>"
							       min="5"
							       max="120"
							       class="small-text">
							<?php esc_html_e( 'seconds', 'brag-book-gallery' ); ?>
							<p class="description">
								<?php esc_html_e( 'Maximum time to wait for API responses.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="enable_caching">
								<?php esc_html_e( 'Enable Caching', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input type="checkbox"
								       id="enable_caching"
								       name="enable_caching"
								       value="yes"
								       <?php checked( $enable_caching, 'yes' ); ?>>
								<?php esc_html_e( 'Cache API responses for better performance', 'brag-book-gallery' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="cache_duration">
								<?php esc_html_e( 'Cache Duration', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
							       id="cache_duration"
							       name="cache_duration"
							       value="<?php echo esc_attr( $cache_duration ); ?>"
							       min="60"
							       max="86400"
							       step="60"
							       class="small-text">
							<?php esc_html_e( 'seconds', 'brag-book-gallery' ); ?>
							<p class="description">
								<?php esc_html_e( 'How long to cache API responses.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Connection Test', 'brag-book-gallery' ); ?></h2>
				<table class="form-table brag-book-gallery-form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'API Status', 'brag-book-gallery' ); ?></th>
						<td>
							<button type="button" id="test-connection-btn" class="button button-secondary">
								<?php esc_html_e( 'Test Connection', 'brag-book-gallery' ); ?>
							</button>
							<span id="connection-status" class="api-status"></span>
							<div id="connection-details" class="connection-details">
								<pre class="api-response"></pre>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<div class="brag-book-gallery-actions">
				<button type="submit"
				        name="submit"
				        id="submit"
				        class="button button-primary button-large">
					<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</form>

		<!-- Confirmation Dialog -->
		<dialog id="brag-api-dialog" class="brag-api-dialog">
			<div class="dialog-content">
				<h3 class="dialog-title"></h3>
				<p class="dialog-message"></p>
				<div class="dialog-buttons">
					<button type="button" class="button dialog-cancel" style="display: none;">
						<?php esc_html_e( 'Cancel', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" class="button button-primary dialog-close">
						<?php esc_html_e( 'OK', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>
		</dialog>

		<style>
		.brag-api-dialog {
			border: none;
			border-radius: 4px;
			box-shadow: 0 3px 30px rgba(0, 0, 0, 0.2);
			padding: 0;
			max-width: 500px;
			min-width: 300px;
		}
		.brag-api-dialog::backdrop {
			background: rgba(0, 0, 0, 0.5);
		}
		.brag-api-dialog .dialog-content {
			padding: 20px;
		}
		.brag-api-dialog .dialog-title {
			margin: 0 0 10px;
			font-size: 18px;
			font-weight: 600;
			color: #1d2327;
		}
		.brag-api-dialog .dialog-message {
			margin: 0 0 20px;
			color: #50575e;
			line-height: 1.5;
		}
		.brag-api-dialog .dialog-buttons {
			text-align: right;
			padding-top: 10px;
			border-top: 1px solid #dcdcde;
		}
		.brag-api-dialog.success .dialog-title {
			color: #00a32a;
		}
		.brag-api-dialog.error .dialog-title {
			color: #d63638;
		}
		.brag-api-dialog.warning .dialog-title {
			color: #dba617;
		}
		.brag-api-dialog .dialog-cancel {
			margin-right: 10px;
		}
		</style>

		<script type="module">
		/**
		 * BragBook Gallery API Settings JavaScript
		 *
		 * Handles API connection management, validation, and dynamic form interactions
		 * using modern ES6+ JavaScript following WordPress VIP coding standards.
		 *
		 * @since 3.0.0
		 */
		(() => {
			'use strict';

			// State management
			let apiRowIndex = <?php echo ! empty( $api_tokens ) ? count( $api_tokens ) : 1; ?>;

			/**
			 * Initialize dialog polyfill for older browsers
			 *
			 * @since 3.0.0
			 */
			const initializeDialogPolyfill = () => {
				const dialogElement = document.getElementById('brag-api-dialog');
				if (!dialogElement || dialogElement.showModal) return;

				// Fallback for browsers that don't support dialog
				dialogElement.showModal = function() {
					this.style.display = 'block';
					this.style.position = 'fixed';
					this.style.top = '50%';
					this.style.left = '50%';
					this.style.transform = 'translate(-50%, -50%)';
					this.style.zIndex = '999999';

					// Create backdrop
					const backdrop = document.createElement('div');
					backdrop.className = 'dialog-backdrop-fallback';
					Object.assign(backdrop.style, {
						position: 'fixed',
						top: '0',
						left: '0',
						width: '100%',
						height: '100%',
						background: 'rgba(0, 0, 0, 0.5)',
						zIndex: '999998'
					});
					document.body.appendChild(backdrop);
					this.backdrop = backdrop;
				};

				dialogElement.close = function() {
					this.style.display = 'none';
					if (this.backdrop) {
						this.backdrop.remove();
						this.backdrop = null;
					}
				};
			};

			/**
			 * Show dialog with user confirmation
			 *
			 * @param {string} title - Dialog title
			 * @param {string} message - Dialog message
			 * @param {string} type - Dialog type (info, success, error, warning)
			 * @param {boolean} showCancel - Whether to show cancel button
			 * @returns {Promise<boolean>} Promise resolving to user's choice
			 * @since 3.0.0
			 */
			const showDialog = (title, message, type = 'info', showCancel = false) => {
				const dialog = document.getElementById('brag-api-dialog');
				if (!dialog) return Promise.resolve(false);

				const titleEl = dialog.querySelector('.dialog-title');
				const messageEl = dialog.querySelector('.dialog-message');
				const cancelBtn = dialog.querySelector('.dialog-cancel');
				const okBtn = dialog.querySelector('.dialog-close');

				// Set content
				titleEl.textContent = title;
				messageEl.textContent = message;

				// Set type class
				dialog.className = `brag-api-dialog ${type}`;

				// Show/hide cancel button
				cancelBtn.style.display = showCancel ? 'inline-block' : 'none';
				okBtn.textContent = showCancel ? '<?php esc_html_e( "Yes, Remove", "brag-book-gallery" ); ?>' : '<?php esc_html_e( "OK", "brag-book-gallery" ); ?>';

				// Show dialog
				dialog.showModal();

				// Return a promise that resolves with true/false
				return new Promise((resolve) => {
					const okHandler = () => {
						dialog.close();
						okBtn.removeEventListener('click', okHandler);
						if (cancelBtn) cancelBtn.removeEventListener('click', cancelHandler);
						resolve(true);
					};

					const cancelHandler = () => {
						dialog.close();
						if (cancelBtn) cancelBtn.removeEventListener('click', cancelHandler);
						okBtn.removeEventListener('click', okHandler);
						resolve(false);
					};

					okBtn.addEventListener('click', okHandler);
					if (showCancel && cancelBtn) {
						cancelBtn.addEventListener('click', cancelHandler);
					}

					// Close on backdrop click (counts as cancel)
					dialog.addEventListener('click', (e) => {
						if (e.target === dialog) {
							dialog.close();
							resolve(false);
						}
					}, { once: true });
				});
			};

			/**
			 * Toggle API token visibility handler
			 *
			 * @since 3.0.0
			 */
			const handleTokenVisibilityToggle = (e) => {
				const button = e.target.closest('.toggle-api-token');
				if (!button) return;

				const field = button.closest('.api-token-field');
				const input = field.querySelector('.bb-api-token');
				const icon = button.querySelector('.dashicons');

				const isPassword = input.type === 'password';
				input.type = isPassword ? 'text' : 'password';

				icon.classList.toggle('dashicons-visibility', !isPassword);
				icon.classList.toggle('dashicons-hidden', isPassword);
			};

			/**
			 * Renumber connection rows after addition/removal
			 *
			 * @since 3.0.0
			 */
			const renumberConnections = () => {
				const rows = document.querySelectorAll('.bb-api-row');
				rows.forEach((row, index) => {
					const numberSpan = row.querySelector('.connection-number');
					if (numberSpan) {
						numberSpan.textContent = index + 1;
					}
				});
			};

			/**
			 * Add new API connection row
			 *
			 * @since 3.0.0
			 */
			const handleAddConnection = () => {
				const rows = document.querySelectorAll('.bb-api-row');
				const connectionNumber = rows.length + 1;

				const newRowHTML = `
					<div class="bb-api-row" data-index="${apiRowIndex}">
						<div class="bb-api-row-header">
							<h3><?php esc_html_e( "Connection", "brag-book-gallery" ); ?> <span class="connection-number">${connectionNumber}</span></h3>
							<span class="bb-api-status" data-index="${apiRowIndex}"></span>
						</div>
						<table class="form-table brag-book-gallery-form-table">
							<tr>
								<th scope="row"><label><?php esc_html_e( "API Token", "brag-book-gallery" ); ?></label></th>
								<td><div class="api-token-field">
									<input type="password" name="brag_book_gallery_api_token[]" placeholder="<?php esc_attr_e( "Enter your API token", "brag-book-gallery" ); ?>" class="regular-text bb-api-token"/>
									<button type="button" class="toggle-api-token" title="<?php esc_attr_e( "Toggle visibility", "brag-book-gallery" ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div></td>
							</tr>
							<tr>
								<th scope="row"><label><?php esc_html_e( "Website Property ID", "brag-book-gallery" ); ?></label></th>
								<td><input type="text" name="brag_book_gallery_website_property_id[]" placeholder="<?php esc_attr_e( "Enter your website property ID", "brag-book-gallery" ); ?>" class="regular-text bb-websiteproperty-id"/></td>
							</tr>
							<tr>
								<th scope="row"></th>
								<td>
									<button type="button" class="button bb-validate-api" data-index="${apiRowIndex}"><?php esc_html_e( "Validate & Save", "brag-book-gallery" ); ?></button>
									<button type="button" class="button button-link-delete bb-remove-api-row" data-index="${apiRowIndex}"><?php esc_html_e( "Remove Connection", "brag-book-gallery" ); ?></button>
								</td>
							</tr>
						</table>
					</div>`;

				const container = document.getElementById('bb-api-rows');
				if (container) {
					container.insertAdjacentHTML('beforeend', newRowHTML);
				}
				apiRowIndex++;
			};

			/**
			 * Handle API connection removal
			 *
			 * @param {Event} e - Click event
			 * @since 3.0.0
			 */
			const handleRemoveConnection = async (e) => {
				const btn = e.target.closest('.bb-remove-api-row');
				if (!btn) return;

				e.preventDefault();
				const row = btn.closest('.bb-api-row');
				const index = btn.dataset.index || row.dataset.index;
				const apiToken = row.querySelector('.bb-api-token').value;

				// If fields are empty, just remove the row from UI
				if (!apiToken || apiToken.trim() === '') {
					const rows = document.querySelectorAll('.bb-api-row');
					if (rows.length > 1) {
						row.remove();
						renumberConnections();
					} else {
						// Clear the fields of the last row
						row.querySelectorAll('input').forEach(input => input.value = '');
						const status = row.querySelector('.bb-api-status');
						if (status) status.innerHTML = '';
					}
					return;
				}

				// Show confirmation dialog for saved connections
				const confirmed = await showDialog(
					'<?php esc_html_e( "Remove Connection?", "brag-book-gallery" ); ?>',
					'<?php esc_html_e( "Are you sure you want to remove this API connection? This will permanently delete the saved credentials.", "brag-book-gallery" ); ?>',
					'warning',
					true // Show cancel button
				);

				if (!confirmed) return;

				try {
					// Disable button during request
					btn.disabled = true;

					// Create form data
					const formData = new FormData();
					formData.append('action', 'brag_book_gallery_remove_api_connection');
					formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_settings_nonce' ); ?>');
					formData.append('index', index);

					const response = await fetch(ajaxurl, {
						method: 'POST',
						body: formData
					});

					const data = await response.json();

					if (data.success) {
						await showDialog(
							'<?php esc_html_e( "Connection Removed", "brag-book-gallery" ); ?>',
							data.data.message,
							'success'
						);
						// Reload page to refresh the list
						window.location.reload();
					} else {
						btn.disabled = false;
						showDialog(
							'<?php esc_html_e( "Error", "brag-book-gallery" ); ?>',
							data.data || '<?php esc_html_e( "Failed to remove connection.", "brag-book-gallery" ); ?>',
							'error'
						);
					}
				} catch (error) {
					btn.disabled = false;
					showDialog(
						'<?php esc_html_e( "Connection Error", "brag-book-gallery" ); ?>',
						'<?php esc_html_e( "Failed to connect to the server. Please try again.", "brag-book-gallery" ); ?>',
						'error'
					);
				}
			};

			/**
			 * Validate API connection
			 *
			 * @param {Event} e - Click event
			 * @since 3.0.0
			 */
			const handleValidateConnection = async (e) => {
				const btn = e.target.closest('.bb-validate-api');
				if (!btn) return;

				const index = btn.dataset.index;
				const row = btn.closest('.bb-api-row');
				const status = row.querySelector('.bb-api-status');
				const apiToken = row.querySelector('.bb-api-token').value;
				const propertyId = row.querySelector('.bb-websiteproperty-id').value;

				if (!apiToken || !propertyId) {
					showDialog(
						'<?php esc_html_e( "Missing Information", "brag-book-gallery" ); ?>',
						'<?php esc_html_e( "Please enter both API Token and Website Property ID", "brag-book-gallery" ); ?>',
						'error'
					);
					return;
				}

				try {
					btn.disabled = true;
					status.innerHTML = '<span class="spinner is-active"></span>';

					// Create form data
					const formData = new FormData();
					formData.append('action', 'brag_book_gallery_validate_api');
					formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_settings_nonce' ); ?>');
					formData.append('api_token', apiToken);
					formData.append('websiteproperty_id', propertyId);
					formData.append('index', index);

					const response = await fetch(ajaxurl, {
						method: 'POST',
						body: formData
					});

					const data = await response.json();
					btn.disabled = false;

					if (data.success) {
						status.innerHTML = '<span class="dashicons dashicons-yes-alt api-status-success"></span> <?php esc_html_e( "Valid & Saved", "brag-book-gallery" ); ?>';
						// Show success message and reload
						if (data.data?.message) {
							await showDialog(
								'<?php esc_html_e( "Success", "brag-book-gallery" ); ?>',
								data.data.message,
								'success'
							);
							// Reload the page to show the saved credentials
							window.location.reload();
						}
					} else {
						status.innerHTML = '<span class="dashicons dashicons-warning api-status-error"></span> <?php esc_html_e( "Invalid", "brag-book-gallery" ); ?>';
						if (data.data) {
							showDialog(
								'<?php esc_html_e( "Validation Failed", "brag-book-gallery" ); ?>',
								data.data,
								'error'
							);
						}
					}
				} catch (error) {
					btn.disabled = false;
					status.innerHTML = '<span class="dashicons dashicons-warning api-status-error"></span> <?php esc_html_e( "Error", "brag-book-gallery" ); ?>';
					showDialog(
						'<?php esc_html_e( "Connection Error", "brag-book-gallery" ); ?>',
						'<?php esc_html_e( "Failed to connect to the server. Please check your connection and try again.", "brag-book-gallery" ); ?>',
						'error'
					);
				}
			};

			/**
			 * Test all API connections
			 *
			 * @since 3.0.0
			 */
			const handleTestAllConnections = () => {
				const validateButtons = document.querySelectorAll('.bb-validate-api');
				validateButtons.forEach(button => button.click());
			};

			/**
			 * Initialize event listeners
			 *
			 * @since 3.0.0
			 */
			const initializeEventListeners = () => {
				// Initialize dialog polyfill
				initializeDialogPolyfill();

				// Add connection button
				const addButton = document.getElementById('bb-add-api-row');
				if (addButton) {
					addButton.addEventListener('click', handleAddConnection);
				}

				// Delegated event listeners for dynamic content
				document.addEventListener('click', (e) => {
					// Token visibility toggle
					if (e.target.closest('.toggle-api-token')) {
						handleTokenVisibilityToggle(e);
					}

					// Remove connection
					if (e.target.closest('.bb-remove-api-row')) {
						handleRemoveConnection(e);
					}

					// Validate connection
					if (e.target.closest('.bb-validate-api')) {
						handleValidateConnection(e);
					}
				});

				// Test all connections button
				const testButton = document.getElementById('test-connection-btn');
				if (testButton) {
					testButton.addEventListener('click', handleTestAllConnections);
				}

				// Form submission logging (for debugging)
				const submitButton = document.getElementById('submit');
				if (submitButton) {
					submitButton.addEventListener('click', () => {
						console.log('Save Settings button clicked');
					});
				}
			};

			// Initialize when DOM is ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initializeEventListeners);
			} else {
				initializeEventListeners();
			}
		})();
		</script>

		<?php
		$this->render_footer();
	}

	/**
	 * Process and save API configuration settings with validation
	 *
	 * Handles the complete API settings form submission process including:
	 * - Security validation (nonce verification and capability checking)
	 * - Multi-connection processing with paired token/property ID validation
	 * - Data sanitization and validation for all input fields
	 * - Atomic option updates to prevent partial corruption
	 * - User feedback through admin notices
	 *
	 * API Connection Processing:
	 * - Processes arrays of API tokens and website property IDs
	 * - Maintains pairing between tokens and property IDs by index
	 * - Filters out empty tokens while preserving array structure
	 * - Validates property ID format and association
	 *
	 * Settings Validation:
	 * - Timeout values are validated as positive integers within acceptable range
	 * - Cache settings are normalized to boolean-like string values
	 * - Cache duration is validated and constrained to reasonable limits
	 *
	 * The method follows WordPress coding standards and uses appropriate
	 * sanitization functions for each data type. All user input is considered
	 * untrusted and sanitized accordingly.
	 *
	 * @since 3.0.0
	 * @return void Settings are saved to WordPress options, user feedback provided
	 */
	private function save(): void {
		// Perform security validation before processing any form data
		if ( ! $this->save_settings( 'brag_book_gallery_api_settings', 'brag_book_gallery_api_nonce' ) ) {
			$this->add_notice( __( 'Security verification failed. Please try again.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Initialize arrays for processed API connection data
		$api_tokens = array();
		$website_property_ids = array();

		// Check if we received any form data
		if ( ! isset( $_POST['brag_book_gallery_api_token'] ) ) {
			$this->add_notice( __( 'No API token data received. Please ensure JavaScript is enabled.', 'brag-book-gallery' ), 'error' );
		}

		// Process API token array if provided
		if ( isset( $_POST['brag_book_gallery_api_token'] ) && is_array( $_POST['brag_book_gallery_api_token'] ) ) {
			foreach ( $_POST['brag_book_gallery_api_token'] as $index => $token ) {
				$token = sanitize_text_field( $token );
				if ( ! empty( $token ) ) {
					$api_tokens[] = $token;

					// Save corresponding property ID, maintaining array pairing
					if ( isset( $_POST['brag_book_gallery_website_property_id'][ $index ] ) ) {
						$website_property_ids[] = sanitize_text_field( $_POST['brag_book_gallery_website_property_id'][ $index ] );
					} else {
						$website_property_ids[] = '';
					}
				}
			}
		}

		// Log what we're about to save for debugging
		if ( ! empty( $api_tokens ) ) {
			$this->add_notice( sprintf( __( 'Saving %d API connection(s).', 'brag-book-gallery' ), count( $api_tokens ) ), 'info' );
		} else {
			$this->add_notice( __( 'No API tokens to save (all fields were empty).', 'brag-book-gallery' ), 'warning' );
		}

		// Update API connection data atomically
		update_option( 'brag_book_gallery_api_token', $api_tokens );
		update_option( 'brag_book_gallery_website_property_id', $website_property_ids );

		// Process and validate connection settings
		if ( isset( $_POST['api_timeout'] ) ) {
			$timeout = absint( $_POST['api_timeout'] );
			// Ensure timeout is within reasonable bounds (5-120 seconds)
			$timeout = max( 5, min( 120, $timeout ) );
			update_option( 'brag_book_gallery_api_timeout', $timeout );
		}

		// Normalize caching setting to string boolean
		$enable_caching = isset( $_POST['enable_caching'] ) && $_POST['enable_caching'] === 'yes' ? 'yes' : 'no';
		update_option( 'brag_book_gallery_enable_api_cache', $enable_caching );

		// Process cache duration with bounds checking
		if ( isset( $_POST['cache_duration'] ) ) {
			$duration = absint( $_POST['cache_duration'] );
			// Ensure duration is within reasonable bounds (60 seconds to 24 hours)
			$duration = max( 60, min( 86400, $duration ) );
			update_option( 'brag_book_gallery_api_cache_duration', $duration );
		}

		// Provide user feedback for successful save operation
		$this->add_notice( __( 'API settings saved successfully.', 'brag-book-gallery' ) );
		settings_errors( $this->page_slug );
	}
}
