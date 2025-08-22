<?php
/**
 * Rewrite Flush Tool
 *
 * Provides a comprehensive interface to flush WordPress rewrite rules with modern PHP 8.2 features
 * and ES6 JavaScript for enhanced user experience and error handling.
 *
 * @package    BragBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @version    3.0.0
 */

declare( strict_types=1 );

namespace BragBookGallery\Admin\Debug_Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Flush class
 *
 * Provides comprehensive rewrite rule flushing capabilities with modern PHP 8.2 features
 * and advanced JavaScript functionality for optimal user experience.
 *
 * @since 3.0.0
 */
final class Rewrite_Flush {

	/**
	 * Get checkmark SVG icon for visual feedback.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML markup for the icon.
	 */
	private function get_check_icon( bool $success = true ): string {
		if ( $success ) {
			// Green checkmark circle for success
			return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#4caf50" style="vertical-align: middle; margin-right: 5px;"><path d="m423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z"/></svg>';
		} else {
			// Red X for error/not set
			return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#f44336" style="vertical-align: middle; margin-right: 5px;"><path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/></svg>';
		}
	}

	/**
	 * Get warning SVG icon for informational feedback.
	 *
	 * @since 3.0.0
	 *
	 * @return string SVG HTML markup for the warning icon.
	 */
	private function get_warning_icon(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ff9800" style="vertical-align: middle; margin-right: 5px;"><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
	}

	/**
	 * Render the comprehensive flush tool interface.
	 *
	 * Displays a complete interface for rewrite rule flushing with multiple options,
	 * status information, and comprehensive diagnostics capabilities.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="rewrite-flush-tool">
			<h2><?php esc_html_e( 'Flush Rewrite Rules', 'brag-book-gallery' ); ?></h2>

			<div class="tool-section">
				<p><?php esc_html_e( 'Use this tool to regenerate WordPress rewrite rules. This can help fix issues with custom URLs not working properly.', 'brag-book-gallery' ); ?></p>

				<div class="notice notice-info">
					<p>
						<?php esc_html_e( 'Flushing rewrite rules is a resource-intensive operation. Only do this when necessary.', 'brag-book-gallery' ); ?>
					</p>
				</div>

				<h3><?php esc_html_e( 'When to flush rewrite rules:', 'brag-book-gallery' ); ?></h3>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( 'After changing permalink settings', 'brag-book-gallery' ); ?></li>
					<li><?php esc_html_e( 'After updating the gallery slug', 'brag-book-gallery' ); ?></li>
					<li><?php esc_html_e( 'When custom URLs return 404 errors', 'brag-book-gallery' ); ?></li>
					<li><?php esc_html_e( 'After plugin updates', 'brag-book-gallery' ); ?></li>
					<li><?php esc_html_e( 'After migrating the site', 'brag-book-gallery' ); ?></li>
				</ul>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Current Status', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_current_status(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Flush Options', 'brag-book-gallery' ); ?></h3>

				<div style="margin: 20px 0;">
					<button class="button button-primary button-large" id="flush-rules-standard">
						<?php esc_html_e( 'Standard Flush', 'brag-book-gallery' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Regenerates rewrite rules normally.', 'brag-book-gallery' ); ?>
					</p>
				</div>

				<div style="margin: 20px 0;">
					<button class="button button-secondary button-large" id="flush-rules-hard">
						<?php esc_html_e( 'Hard Flush', 'brag-book-gallery' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Forces complete regeneration and updates .htaccess file.', 'brag-book-gallery' ); ?>
					</p>
				</div>

				<div style="margin: 20px 0;">
					<button class="button button-secondary" id="flush-with-registration">
						<?php esc_html_e( 'Flush with Rule Registration', 'brag-book-gallery' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Re-registers custom rules before flushing.', 'brag-book-gallery' ); ?>
					</p>
				</div>
				
				<div style="margin: 20px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
					<h4 style="margin-top: 0;"><?php esc_html_e( 'Comprehensive Standalone Flush', 'brag-book-gallery' ); ?></h4>
					<button class="button button-secondary button-large" id="flush-rules-standalone" style="background: #0073aa; color: white; border-color: #0073aa;">
						<?php esc_html_e( 'Run Standalone Flush Process', 'brag-book-gallery' ); ?>
					</button>
					<p class="description">
						<strong><?php esc_html_e( 'Complete flush process that:', 'brag-book-gallery' ); ?></strong><br>
						• <?php esc_html_e( 'Registers all BRAGBook Gallery custom rewrite rules', 'brag-book-gallery' ); ?><br>
						• <?php esc_html_e( 'Performs hard flush with .htaccess update', 'brag-book-gallery' ); ?><br>
						• <?php esc_html_e( 'Clears all related transients', 'brag-book-gallery' ); ?><br>
						• <?php esc_html_e( 'Displays comprehensive diagnostic information', 'brag-book-gallery' ); ?><br>
						• <?php esc_html_e( 'Shows registered query variables and sample URLs', 'brag-book-gallery' ); ?>
					</p>
				</div>

				<div id="flush-result"></div>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'After Flushing', 'brag-book-gallery' ); ?></h3>
				<button class="button" id="verify-rules">
					<?php esc_html_e( 'Verify Rules', 'brag-book-gallery' ); ?>
				</button>
				<div id="verify-result"></div>
			</div>
			
			<!-- Dialog for Standalone Flush Results -->
			<dialog id="standalone-flush-dialog" style="width: 90%; max-width: 800px; max-height: 90vh; padding: 0; border: none; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
				<div style="display: flex; flex-direction: column; height: 100%;">
					<div style="padding: 20px; background: #f0f8ff; border-bottom: 1px solid #0073aa; display: flex; justify-content: space-between; align-items: center;">
						<h2 style="margin: 0; color: #0073aa;"><?php esc_html_e( 'Comprehensive Flush Results', 'brag-book-gallery' ); ?></h2>
						<button type="button" id="close-dialog" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
					</div>
					<div id="dialog-content" style="padding: 20px; overflow-y: auto; flex: 1;">
						<!-- Results will be inserted here -->
					</div>
					<div style="padding: 20px; background: #f5f5f5; border-top: 1px solid #ddd; text-align: right;">
						<button type="button" class="button button-primary" id="close-dialog-footer"><?php esc_html_e( 'Close', 'brag-book-gallery' ); ?></button>
					</div>
				</div>
			</dialog>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Alternative Methods', 'brag-book-gallery' ); ?></h3>
				<p><?php esc_html_e( 'You can also flush rewrite rules by:', 'brag-book-gallery' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li>
						<?php
						printf(
							/* translators: %s: Link to permalinks settings */
							esc_html__( 'Visiting %s and clicking "Save Changes"', 'brag-book-gallery' ),
							'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Permalinks Settings', 'brag-book-gallery' ) . '</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Using WP-CLI: wp rewrite flush', 'brag-book-gallery' ); ?></li>
				</ul>
			</div>
		</div>
		
		<script>
		document.addEventListener('DOMContentLoaded', () => {
			/**
			 * Modern AJAX request handler using ES6 features.
			 *
			 * @param {Object} data - Request data to send
			 * @param {Function} onSuccess - Success callback
			 * @param {Function} onError - Error callback
			 * @param {Function} onComplete - Complete callback
			 * @returns {Promise<void>}
			 */
			const ajaxRequest = async (data, onSuccess, onError, onComplete) => {
				try {
					const formData = new FormData();
					Object.entries(data).forEach(([key, value]) => {
						formData.append(key, value);
					});
					
					const response = await fetch(ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					});
					
					const result = await response.json();
					
					if (onSuccess) onSuccess(result);
				} catch (error) {
					console.error('AJAX request failed:', error);
					if (onError) onError(error);
				} finally {
					if (onComplete) onComplete();
				}
			};
			
			
			/**
			 * Handle button state during operations.
			 *
			 * @param {HTMLButtonElement} button - Button element
			 * @param {boolean} isLoading - Loading state
			 * @param {string} loadingText - Text to show during loading
			 */
			const handleButtonState = (button, isLoading, loadingText = 'Processing...') => {
				if (isLoading) {
					button.disabled = true;
					button.dataset.originalText = button.textContent;
					button.textContent = loadingText;
				} else {
					button.disabled = false;
					button.textContent = button.dataset.originalText || button.textContent;
				}
			};
			
			/**
			 * Display results with proper styling.
			 *
			 * @param {HTMLElement} target - Target element
			 * @param {string} content - Content to display
			 * @param {string} type - Result type
			 */
			const displayResult = (target, content, type = 'info') => {
				if (!target) return;
				
				const cssClass = {
					success: 'notice notice-success',
					error: 'notice notice-error',
					info: 'notice notice-info'
				}[type] || 'notice notice-info';
				
				target.innerHTML = `<div class="${cssClass}"><p>${content}</p></div>`;
			};
			
			// Element references
			const resultDiv = document.getElementById('flush-result');
			
			// Standard flush handler
			const standardFlushBtn = document.getElementById('flush-rules-standard');
			if (standardFlushBtn) {
				standardFlushBtn.addEventListener('click', async () => {
					handleButtonState(standardFlushBtn, true, 'Flushing...');
					displayResult(resultDiv, 'Flushing rewrite rules...', 'info');
					
					await ajaxRequest(
						{
							action: 'brag_book_flush_rewrite_rules',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_flush_rewrite_rules' ) ); ?>',
							flush_type: 'standard'
						},
						(response) => {
							const type = response.success ? 'success' : 'error';
							const content = response.success ? response.data : `Error: ${response.data}`;
							displayResult(resultDiv, content, type);
						},
						() => displayResult(resultDiv, 'Failed to flush rewrite rules. Please try again.', 'error'),
						() => handleButtonState(standardFlushBtn, false)
					);
				});
			}
			
			
			// Hard flush handler
			const hardFlushBtn = document.getElementById('flush-rules-hard');
			if (hardFlushBtn) {
				hardFlushBtn.addEventListener('click', async () => {
					handleButtonState(hardFlushBtn, true, 'Hard flushing...');
					displayResult(resultDiv, 'Performing hard flush...', 'info');
					
					await ajaxRequest(
						{
							action: 'brag_book_flush_rewrite_rules',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_flush_rewrite_rules' ) ); ?>',
							flush_type: 'hard'
						},
						(response) => {
							const type = response.success ? 'success' : 'error';
							const content = response.success ? response.data : `Error: ${response.data}`;
							displayResult(resultDiv, content, type);
						},
						() => displayResult(resultDiv, 'Failed to perform hard flush. Please try again.', 'error'),
						() => handleButtonState(hardFlushBtn, false)
					);
				});
			}
			
			
			// Flush with registration handler
			const flushWithRegBtn = document.getElementById('flush-with-registration');
			if (flushWithRegBtn) {
				flushWithRegBtn.addEventListener('click', async () => {
					handleButtonState(flushWithRegBtn, true, 'Re-registering...');
					displayResult(resultDiv, 'Re-registering rules and flushing...', 'info');
					
					await ajaxRequest(
						{
							action: 'brag_book_flush_rewrite_rules',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_flush_rewrite_rules' ) ); ?>',
							flush_type: 'with_registration'
						},
						(response) => {
							const type = response.success ? 'success' : 'error';
							const content = response.success ? response.data : `Error: ${response.data}`;
							displayResult(resultDiv, content, type);
						},
						() => displayResult(resultDiv, 'Failed to flush with registration. Please try again.', 'error'),
						() => handleButtonState(flushWithRegBtn, false)
					);
				});
			}
			
			
			// Standalone flush with dialog handler
			const standaloneFlushBtn = document.getElementById('flush-rules-standalone');
			if (standaloneFlushBtn) {
				standaloneFlushBtn.addEventListener('click', async () => {
					const dialog = document.getElementById('standalone-flush-dialog');
					const dialogContent = document.getElementById('dialog-content');
					
					if (!dialog || !dialogContent) {
						alert('Dialog elements not found');
						return;
					}
					
					if (!confirm('<?php echo esc_js( __( 'This will run the comprehensive standalone flush process. Continue?', 'brag-book-gallery' ) ); ?>')) {
						return;
					}
					
					handleButtonState(standaloneFlushBtn, true, '<?php echo esc_js( __( 'Running...', 'brag-book-gallery' ) ); ?>');
					
					// Show loading in dialog
					dialogContent.innerHTML = '<div style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none; margin: 0 auto;"></span><p><?php echo esc_js( __( 'Running comprehensive flush process...', 'brag-book-gallery' ) ); ?></p></div>';
					
					// Show the dialog
					if (dialog.showModal) {
						dialog.showModal();
					} else {
						// Fallback for older browsers
						dialog.setAttribute('open', '');
					}
					
					await ajaxRequest(
						{
							action: 'brag_book_debug_tool',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_debug_tools' ) ); ?>',
							tool: 'rewrite-flush',
							tool_action: 'standalone'
						},
						(response) => {
							if (response.success) {
								dialogContent.innerHTML = response.data;
							} else {
								dialogContent.innerHTML = `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`;
							}
						},
						() => {
							dialogContent.innerHTML = '<div class="notice notice-error"><p><?php echo esc_js( __( 'Failed to run standalone flush. Please try again.', 'brag-book-gallery' ) ); ?></p></div>';
						},
						() => handleButtonState(standaloneFlushBtn, false)
					);
				});
			}
			
			
			// Dialog close handlers with modern event handling
			const dialog = document.getElementById('standalone-flush-dialog');
			const closeDialogBtn = document.getElementById('close-dialog');
			const closeDialogFooterBtn = document.getElementById('close-dialog-footer');
			
			/**
			 * Close dialog helper function.
			 */
			const closeDialog = () => {
				if (!dialog) return;
				
				if (dialog.close) {
					dialog.close();
				} else {
					// Fallback for older browsers
					dialog.removeAttribute('open');
				}
			};
			
			if (closeDialogBtn) {
				closeDialogBtn.addEventListener('click', closeDialog);
			}
			
			if (closeDialogFooterBtn) {
				closeDialogFooterBtn.addEventListener('click', closeDialog);
			}
			
			// Close dialog on ESC key
			if (dialog) {
				dialog.addEventListener('cancel', (e) => {
					e.preventDefault();
					closeDialog();
				});
			}
			
			
			// Verify rules handler
			const verifyBtn = document.getElementById('verify-rules');
			const verifyResult = document.getElementById('verify-result');
			
			if (verifyBtn) {
				verifyBtn.addEventListener('click', async () => {
					handleButtonState(verifyBtn, true, 'Verifying...');
					displayResult(verifyResult, 'Verifying rules...', 'info');
					
					await ajaxRequest(
						{
							action: 'brag_book_debug_tool',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_debug_tools' ) ); ?>',
							tool: 'rewrite-flush',
							tool_action: 'verify'
						},
						(response) => {
							if (response.success) {
								if (verifyResult) verifyResult.innerHTML = response.data;
							} else {
								displayResult(verifyResult, `Error: ${response.data}`, 'error');
							}
						},
						() => displayResult(verifyResult, 'Failed to verify rules. Please try again.', 'error'),
						() => handleButtonState(verifyBtn, false)
					);
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render current rewrite rules status information.
	 *
	 * Displays comprehensive status including total rules count, gallery-specific rules,
	 * permalink structure, and environment-specific information.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function render_current_status(): void {
		global $wp_rewrite;

		$rules                = $wp_rewrite->wp_rewrite_rules();
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$gallery_rules_count  = 0;

		if ( ! empty( $rules ) ) {
			foreach ( $rules as $pattern => $query ) {
				if (
					( $brag_book_gallery_page_slug && str_contains( $pattern, $brag_book_gallery_page_slug ) ) ||
					str_contains( $query, 'procedure_title' ) ||
					str_contains( $query, 'case_id' )
				) {
					$gallery_rules_count++;
				}
			}
		}
		
		$permalink_structure = get_option( 'permalink_structure' ) ?: 'Plain';
		$has_pretty_permalinks = ! empty( get_option( 'permalink_structure' ) );
		?>
		<div class="status-cards-grid">
			<!-- Rules Overview Card -->
			<div class="status-card">
				<div class="status-card-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
						<polyline points="14 2 14 8 20 8"></polyline>
						<line x1="16" y1="13" x2="8" y2="13"></line>
						<line x1="16" y1="17" x2="8" y2="17"></line>
						<polyline points="10 9 9 9 8 9"></polyline>
					</svg>
				</div>
				<div class="status-card-content">
					<h4 class="status-card-title"><?php esc_html_e( 'Rewrite Rules', 'brag-book-gallery' ); ?></h4>
					<div class="status-metrics">
						<div class="status-metric">
							<span class="metric-value"><?php echo esc_html( count( $rules ) ); ?></span>
							<span class="metric-label"><?php esc_html_e( 'Total Rules', 'brag-book-gallery' ); ?></span>
						</div>
						<div class="status-metric">
							<span class="metric-value <?php echo $gallery_rules_count > 0 ? 'metric-success' : 'metric-warning'; ?>">
								<?php echo esc_html( $gallery_rules_count ); ?>
							</span>
							<span class="metric-label"><?php esc_html_e( 'Gallery Rules', 'brag-book-gallery' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<!-- Permalink Configuration Card -->
			<div class="status-card">
				<div class="status-card-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
						<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
					</svg>
				</div>
				<div class="status-card-content">
					<h4 class="status-card-title"><?php esc_html_e( 'Permalink Settings', 'brag-book-gallery' ); ?></h4>
					<div class="status-info">
						<div class="status-item">
							<span class="status-label"><?php esc_html_e( 'Structure:', 'brag-book-gallery' ); ?></span>
							<code class="status-code"><?php echo esc_html( $permalink_structure ); ?></code>
						</div>
						<?php if ( $brag_book_gallery_page_slug ) : ?>
						<div class="status-item">
							<span class="status-label"><?php esc_html_e( 'Gallery Slug:', 'brag-book-gallery' ); ?></span>
							<code class="status-code"><?php echo esc_html( $brag_book_gallery_page_slug ); ?></code>
						</div>
						<?php endif; ?>
					</div>
					<?php if ( ! $has_pretty_permalinks ) : ?>
					<div class="status-warning">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
							<line x1="12" y1="9" x2="12" y2="13"></line>
							<line x1="12" y1="17" x2="12.01" y2="17"></line>
						</svg>
						<span><?php esc_html_e( 'Pretty permalinks not enabled', 'brag-book-gallery' ); ?></span>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Environment Status Card -->
			<?php if ( defined( 'WPE_APIKEY' ) || defined( 'VIP_GO_APP_ID' ) ) : ?>
			<div class="status-card">
				<div class="status-card-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="12" y1="8" x2="12" y2="12"></line>
						<line x1="12" y1="16" x2="12.01" y2="16"></line>
					</svg>
				</div>
				<div class="status-card-content">
					<h4 class="status-card-title"><?php esc_html_e( 'Environment Notice', 'brag-book-gallery' ); ?></h4>
					<div class="status-info">
						<?php if ( defined( 'WPE_APIKEY' ) ) : ?>
						<div class="status-notice-warning">
							<strong><?php esc_html_e( 'WP Engine Detected', 'brag-book-gallery' ); ?></strong>
							<p><?php esc_html_e( 'May require cache clearing after flush', 'brag-book-gallery' ); ?></p>
						</div>
						<?php endif; ?>
						<?php if ( defined( 'VIP_GO_APP_ID' ) ) : ?>
						<div class="status-notice-warning">
							<strong><?php esc_html_e( 'VIP Platform Detected', 'brag-book-gallery' ); ?></strong>
							<p><?php esc_html_e( 'Rewrite rules are managed by the platform', 'brag-book-gallery' ); ?></p>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Quick Status Card -->
			<div class="status-card">
				<div class="status-card-icon <?php echo $gallery_rules_count > 0 ? 'icon-success' : 'icon-warning'; ?>">
					<?php if ( $gallery_rules_count > 0 ) : ?>
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
						<polyline points="22 4 12 14.01 9 11.01"></polyline>
					</svg>
					<?php else : ?>
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="12" y1="8" x2="12" y2="16"></line>
						<line x1="12" y1="16" x2="12.01" y2="16"></line>
					</svg>
					<?php endif; ?>
				</div>
				<div class="status-card-content">
					<h4 class="status-card-title"><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></h4>
					<div class="status-badge <?php echo $gallery_rules_count > 0 ? 'badge-success' : 'badge-warning'; ?>">
						<?php 
						if ( $gallery_rules_count > 0 ) {
							esc_html_e( 'Gallery rules active', 'brag-book-gallery' );
						} else {
							esc_html_e( 'No gallery rules found', 'brag-book-gallery' );
						}
						?>
					</div>
					<?php if ( $gallery_rules_count === 0 && $has_pretty_permalinks ) : ?>
					<p class="status-hint"><?php esc_html_e( 'Try flushing rules to regenerate', 'brag-book-gallery' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Execute tool actions via AJAX with modern error handling.
	 *
	 * Handles various flush operations including standard, hard, with registration,
	 * standalone comprehensive flush, and rules verification.
	 *
	 * @since 3.0.0
	 *
	 * @param string $action The specific action to execute.
	 * @param array  $data   Additional request data and parameters.
	 * @return string|array The result of the executed action.
	 * @throws \Exception When an invalid action is provided.
	 */
	public function execute( string $action, array $data ): string|array {
		return match ( $action ) {
			'flush' => $this->flush_rules( $data['flush_type'] ?? 'standard' ),
			'verify' => $this->verify_rules(),
			'standalone' => $this->run_standalone_flush(),
			default => throw new \Exception( sprintf( 'Invalid action: %s', esc_html( $action ) ) )
		};
	}

	/**
	 * Flush rewrite rules with comprehensive options.
	 *
	 * Performs different types of rewrite rule flushing including standard flush,
	 * hard flush with .htaccess updates, and flush with custom rule registration.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Type of flush to perform ('standard', 'hard', 'with_registration').
	 * @return string HTML-formatted flush results report.
	 */
	private function flush_rules( string $type ): string {
		$results = [];

		return match ( $type ) {
			'hard' => $this->perform_hard_flush(),
			'with_registration' => $this->perform_flush_with_registration(),
			'standard' => $this->perform_standard_flush(),
			default => $this->perform_standard_flush()
		};
	}
	
	/**
	 * Perform hard flush operation.
	 *
	 * @since 3.0.0
	 *
	 * @return string Flush results.
	 */
	private function perform_hard_flush(): string {
		$results = [];
		
		// Force complete regeneration
		delete_option( 'rewrite_rules' );
		$results[] = $this->get_check_icon( true ) . 'Cleared existing rules from database';
		
		// Register custom rules
		if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
			\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
			$results[] = $this->get_check_icon( true ) . 'Re-registered custom rules';
		}
		
		// Hard flush
		flush_rewrite_rules( true );
		$results[] = $this->get_check_icon( true ) . 'Performed hard flush (updated .htaccess)';
		
		return $this->format_flush_results( $results );
	}
	
	/**
	 * Perform flush with registration.
	 *
	 * @since 3.0.0
	 *
	 * @return string Flush results.
	 */
	private function perform_flush_with_registration(): string {
		$results = [];
		
		// Register custom rules first
		if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
			\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
			$results[] = $this->get_check_icon( true ) . 'Re-registered custom rules';
		}
		
		// Standard flush
		flush_rewrite_rules( false );
		$results[] = $this->get_check_icon( true ) . 'Flushed rewrite rules';
		
		return $this->format_flush_results( $results );
	}
	
	/**
	 * Perform standard flush operation.
	 *
	 * @since 3.0.0
	 *
	 * @return string Flush results.
	 */
	private function perform_standard_flush(): string {
		$results = [];
		
		// Standard flush
		flush_rewrite_rules( false );
		$results[] = $this->get_check_icon( true ) . 'Flushed rewrite rules';
		
		return $this->format_flush_results( $results );
	}
	
	/**
	 * Format flush results with common elements.
	 *
	 * @since 3.0.0
	 *
	 * @param array $results Results array.
	 * @return string Formatted results.
	 */
	private function format_flush_results( array $results ): string {
		// Clear any caches
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results[] = $this->get_check_icon( true ) . 'Cleared object cache';
		}
		
		// WP Engine specific notice
		if ( defined( 'WPE_APIKEY' ) ) {
			$results[] = $this->get_warning_icon() . 'Remember to clear WP Engine cache from the dashboard';
		}
		
		$output = implode( '<br>', $results );
		$output .= '<br><br><strong>' . __( 'Rewrite rules flushed successfully!', 'brag-book-gallery' ) . '</strong>';
		$output .= '<br>' . __( 'Test your gallery URLs to verify they work correctly.', 'brag-book-gallery' );
		
		return $output;
	}

	/**
	 * Run comprehensive standalone flush with detailed diagnostics.
	 *
	 * Performs a complete flush process with extensive diagnostic output,
	 * rule registration verification, and sample URL generation.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted comprehensive flush report.
	 */
	private function run_standalone_flush(): string {
		$output = '<div class="standalone-flush-results">';
		$output .= '<h3>' . __( 'Standalone Flush Results', 'brag-book-gallery' ) . '</h3>';
		
		// Ensure our rewrite rules are registered first
		if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
			\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
			$output .= '<p>' . $this->get_check_icon( true ) . __( 'Custom rewrite rules registered', 'brag-book-gallery' ) . '</p>';
		}
		
		// Flush the rewrite rules (hard flush)
		flush_rewrite_rules( true );
		$output .= '<p>' . $this->get_check_icon( true ) . __( 'Rewrite rules flushed (hard flush with .htaccess update)', 'brag-book-gallery' ) . '</p>';
		
		// Clear any rewrite notice transients
		delete_transient( 'bragbook_show_rewrite_notice' );
		$output .= '<p>' . $this->get_check_icon( true ) . __( 'Cleared rewrite notice transients', 'brag-book-gallery' ) . '</p>';
		
		// Display registered query variables
		$output .= '<h4>' . __( 'Registered Query Variables:', 'brag-book-gallery' ) . '</h4>';
		$output .= '<ul>';
		$output .= '<li><code>procedure_title</code> - ' . __( 'Used for case detail URLs', 'brag-book-gallery' ) . '</li>';
		$output .= '<li><code>case_id</code> - ' . __( 'Used for individual case pages', 'brag-book-gallery' ) . '</li>';
		$output .= '<li><code>filter_procedure</code> - ' . __( 'Used for procedure filtering', 'brag-book-gallery' ) . '</li>';
		$output .= '<li><code>filter_category</code> - ' . __( 'Used for category filtering', 'brag-book-gallery' ) . '</li>';
		$output .= '<li><code>favorites_section</code> - ' . __( 'Used for favorites pages', 'brag-book-gallery' ) . '</li>';
		$output .= '</ul>';
		
		// Display gallery page slugs
		$output .= '<h4>' . __( 'Gallery Page Slugs Found:', 'brag-book-gallery' ) . '</h4>';
		$output .= '<ul>';
		
		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
		if ( ! is_array( $gallery_slugs ) ) {
			$gallery_slugs = [ $gallery_slugs ];
		}
		
		if ( empty( $gallery_slugs ) || empty( $gallery_slugs[0] ) ) {
			$output .= '<li><em>' . __( 'No gallery page slugs configured', 'brag-book-gallery' ) . '</em></li>';
		} else {
			foreach ( $gallery_slugs as $slug ) {
				if ( ! empty( $slug ) ) {
					$output .= '<li><code>' . esc_html( $slug ) . '</code></li>';
				}
			}
		}
		$output .= '</ul>';
		
		// Sample rewrite rules
		$first_slug = ! empty( $gallery_slugs[0] ) ? $gallery_slugs[0] : 'gallery';
		$output .= '<h4>' . __( 'Sample Rewrite Rules:', 'brag-book-gallery' ) . '</h4>';
		$output .= '<ul>';
		$output .= '<li>' . __( 'Procedure Filter:', 'brag-book-gallery' ) . ' <code>/' . esc_html( $first_slug ) . '/procedure-name/</code></li>';
		$output .= '<li>' . __( 'Case Details:', 'brag-book-gallery' ) . ' <code>/' . esc_html( $first_slug ) . '/procedure-name/123/</code></li>';
		$output .= '</ul>';
		
		// Next steps
		$output .= '<h4>' . __( 'Next Steps:', 'brag-book-gallery' ) . '</h4>';
		$output .= '<ul>';
		$output .= '<li>' . __( 'Test your gallery URLs to ensure they\'re working properly', 'brag-book-gallery' ) . '</li>';
		$output .= '<li>' . __( 'If you still see 404 errors, check your permalink settings', 'brag-book-gallery' ) . '</li>';
		$output .= '<li>' . __( 'Make sure your gallery page exists and has the [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</li>';
		$output .= '</ul>';
		
		// Links
		$output .= '<p>';
		$output .= '<a href="' . esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ) . '" class="button">' . __( '← Back to Settings', 'brag-book-gallery' ) . '</a> ';
		$output .= '<a href="' . esc_url( home_url( '/' . $first_slug ) ) . '" class="button" target="_blank">' . __( 'View Gallery →', 'brag-book-gallery' ) . '</a>';
		$output .= '</p>';
		
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Verify rewrite rules and query variables.
	 *
	 * Performs comprehensive verification of current rewrite rules including
	 * gallery-specific rules, query variable registration, and sample rule display.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted verification report.
	 */
	private function verify_rules(): string {
		global $wp_rewrite;

		$output = '<div class="rules-verification">';
		$output .= '<h4>' . __( 'Verification Results:', 'brag-book-gallery' ) . '</h4>';

		// Check if rules exist
		$rules = $wp_rewrite->wp_rewrite_rules();
		if ( empty( $rules ) ) {
			$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . __( 'No rewrite rules found!', 'brag-book-gallery' ) . '</p>';
			return $output . '</div>';
		}

		$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . sprintf( __( 'Found %d total rewrite rules', 'brag-book-gallery' ), count( $rules ) ) . '</p>';

		// Check for gallery rules
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$found_gallery_rules  = false;

		if ( $brag_book_gallery_page_slug ) {
			foreach ( $rules as $pattern => $query ) {
				if ( str_contains( $pattern, $brag_book_gallery_page_slug ) ) {
					$found_gallery_rules = true;
					break;
				}
			}

			if ( $found_gallery_rules ) {
				$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . __( 'Gallery rules are present', 'brag-book-gallery' ) . '</p>';
			} else {
				$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . __( 'Gallery rules are missing', 'brag-book-gallery' ) . '</p>';
			}
		}

		// Check query vars
		global $wp, $wp_query;
		$required_vars = [ 'procedure_title', 'case_id', 'filter_procedure' ];
		$missing_vars  = [];

		// Check both in WP object and through the query_vars filter
		$registered_vars = apply_filters( 'query_vars', [] );

		foreach ( $required_vars as $var ) {
			// Check multiple places where query vars can be registered
			$is_registered = in_array( $var, $wp->public_query_vars, true ) ||
							in_array( $var, $wp->private_query_vars, true ) ||
							in_array( $var, $registered_vars, true ) ||
							isset( $wp->extra_query_vars[ $var ] );

			if ( ! $is_registered ) {
				$missing_vars[] = $var;
			}
		}

		if ( empty( $missing_vars ) ) {
			$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . __( 'All required query vars are registered', 'brag-book-gallery' ) . '</p>';
		} else {
			$output .= '<p style="color: orange;">' . $this->get_warning_icon() . __( 'Missing query vars:', 'brag-book-gallery' ) . ' ' . implode( ', ', $missing_vars ) . '</p>';
		}

		// Sample rules display
		$output .= '<h4>' . __( 'Sample Gallery Rules:', 'brag-book-gallery' ) . '</h4>';
		$output .= '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 200px;">';

		$sample_count = 0;
		foreach ( $rules as $pattern => $query ) {
			if (
				( $brag_book_gallery_page_slug && str_contains( $pattern, $brag_book_gallery_page_slug ) ) ||
				str_contains( $query, 'procedure_title' ) ||
				str_contains( $query, 'case_id' )
			) {
				$output .= htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
				$sample_count++;
				if ( $sample_count >= 5 ) {
					break;
				}
			}
		}

		if ( $sample_count === 0 ) {
			$output .= __( 'No gallery rules found', 'brag-book-gallery' );
		}

		$output .= '</pre>';
		$output .= '</div>';

		return $output;
	}
}
