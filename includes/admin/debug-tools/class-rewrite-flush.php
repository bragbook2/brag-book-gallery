<?php
/**
 * Rewrite Flush Tool
 *
 * Provides comprehensive rewrite rules flushing capabilities with advanced diagnostics,
 * performance monitoring, and hosting-specific optimizations. Features modern PHP 8.2
 * syntax and enterprise-grade error handling for production environments.
 *
 * @package    BragBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @version    3.0.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 *
 * @see \BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler For rule registration
 * @see \BRAGBookGallery\Includes\Core\Slug_Helper For slug management
 * @see flush_rewrite_rules() WordPress core function for rule flushing
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use WP_Error;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Flush class
 *
 * Enterprise-grade rewrite rule flushing tool with comprehensive diagnostics,
 * performance monitoring, and hosting-specific optimizations.
 *
 * ## Features:
 * - Multiple flush strategies (standard, hard, with registration)
 * - Comprehensive standalone flush with diagnostics
 * - Performance metrics and monitoring
 * - Hosting provider detection and optimization
 * - Query variable verification
 * - Rule conflict detection
 * - Visual status cards with metrics
 * - Security-hardened operations
 *
 * @since 3.0.0
 * @final
 */
final class Rewrite_Flush {

	/**
	 * Performance metrics storage.
	 *
	 * @since 3.0.0
	 * @var array<string, float>
	 */
	private array $metrics = [];

	/**
	 * Cache key prefix for transient operations.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_PREFIX = 'brag_book_gallery_transient_flush_';

	/**
	 * Cache duration in seconds (5 minutes).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_DURATION = 300;

	/**
	 * Maximum rules to display in samples.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_SAMPLE_RULES = 10;

	/**
	 * Flush operation history.
	 *
	 * @since 3.0.0
	 * @var array<array{time: float, type: string, success: bool}>
	 */
	private array $flush_history = [];

	/**
	 * Get checkmark SVG icon for visual feedback.
	 *
	 * Returns an inline SVG icon for success or error states, optimized for
	 * accessibility with proper ARIA attributes.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML markup for the icon with accessibility attributes.
	 */
	private function get_check_icon( bool $success = true ): string {
		$color = $success ? '#4caf50' : '#f44336';
		$title = $success ? 'Success' : 'Error';
		$path = $success
			? 'm423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z'
			: 'M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z';

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="%s" style="vertical-align: middle; margin-right: 5px;" role="img" aria-label="%s"><title>%s</title><path d="%s"/></svg>',
			esc_attr( $color ),
			esc_attr( $title ),
			esc_html( $title ),
			esc_attr( $path )
		);
	}

	/**
	 * Get warning SVG icon for informational feedback.
	 *
	 * Returns an inline SVG warning icon optimized for accessibility.
	 *
	 * @since 3.0.0
	 *
	 * @return string SVG HTML markup for the warning icon with accessibility attributes.
	 */
	private function get_warning_icon(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ff9800" style="vertical-align: middle; margin-right: 5px;" role="img" aria-label="Warning"><title>Warning</title><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
	}

	/**
	 * Get information SVG icon for notices.
	 *
	 * @since 3.0.0
	 *
	 * @return string SVG HTML markup for the info icon.
	 */
	private function get_info_icon(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#2196f3" style="vertical-align: middle; margin-right: 5px;" role="img" aria-label="Info"><title>Information</title><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
	}

	/**
	 * Detect hosting provider.
	 *
	 * @since 3.0.0
	 *
	 * @return string Detected hosting provider name.
	 */
	private function detect_hosting_provider(): string {
		return match ( true ) {
			defined( 'WPE_APIKEY' ) => 'WP Engine',
			defined( 'KINSTAMU_VERSION' ) => 'Kinsta',
			defined( 'IS_PRESSABLE' ) => 'Pressable',
			defined( 'FLYWHEEL_CONFIG_DIR' ) => 'Flywheel',
			defined( 'WPCOMSH_VERSION' ) => 'WordPress.com',
			defined( 'VIP_GO_APP_ID' ) => 'VIP',
			file_exists( '/etc/siteground' ) => 'SiteGround',
			str_contains( gethostname(), 'dreamhost' ) => 'DreamHost',
			default => 'Standard',
		};
	}

	/**
	 * Get hosting-specific cache clearing instructions.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, string> Cache clearing instructions by provider.
	 */
	private function get_cache_instructions(): array {
		return match ( $this->detect_hosting_provider() ) {
			'WP Engine' => [
				'instruction' => __( 'Clear cache from WP Engine dashboard', 'brag-book-gallery' ),
				'priority' => 'high',
			],
			'Kinsta' => [
				'instruction' => __( 'Clear cache in MyKinsta dashboard', 'brag-book-gallery' ),
				'priority' => 'high',
			],
			'VIP' => [
				'instruction' => __( 'Cache will clear automatically', 'brag-book-gallery' ),
				'priority' => 'low',
			],
			default => [
				'instruction' => '',
				'priority' => 'none',
			],
		};
	}

	/**
	 * Render the comprehensive flush tool interface.
	 *
	 * Displays a complete interface for rewrite rule flushing with multiple options,
	 * status information, comprehensive diagnostics capabilities, and performance metrics.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 * @throws Exception If rendering fails.
	 */
	public function render(): void {
		try {
			$start_time = microtime( true );

			// Security check
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die(
					esc_html__( 'You do not have sufficient permissions to access this page.', 'brag-book-gallery' ),
					403
				);
			}

			// Check if rules were recently deleted
			$deletion_info = get_transient( 'brag_book_gallery_rules_deleted' );
			if ( $deletion_info ) {
				if ( is_array( $deletion_info ) ) {
					$message = sprintf(
						__( 'Rewrite rules deletion complete: %d total rules deleted (%d were gallery rules). %d rules remaining. You may need to re-register custom rules and flush again.', 'brag-book-gallery' ),
						$deletion_info['deleted_count'] ?? 0,
						$deletion_info['gallery_count'] ?? 0,
						$deletion_info['remaining'] ?? 0
					);
				} else {
					$message = __( 'Rewrite rules were deleted. WordPress has regenerated default rules. You may need to re-register custom rules and flush again.', 'brag-book-gallery' );
				}
				$this->show_admin_notice( $message, 'warning' );
				delete_transient( 'brag_book_gallery_rules_deleted' );
			}

			// Check recent flush operations
			$last_flush = brag_book_get_cache( self::CACHE_PREFIX . 'last_flush' );
			if ( $last_flush && ( time() - $last_flush['time'] ) < 60 ) {
				$this->show_admin_notice(
					sprintf(
						/* translators: %s: Time ago */
						__( 'Rules were recently flushed %s ago', 'brag-book-gallery' ),
						human_time_diff( $last_flush['time'] )
					),
					'info'
				);
			}
			?>
			<div class="rewrite-flush-tool" data-nonce="<?php echo esc_attr( wp_create_nonce( 'brag_book_gallery_debug_tools' ) ); ?>">
				<h2><?php esc_html_e( 'Flush Rewrite Rules', 'brag-book-gallery' ); ?> <small style="color: #666;">(Enhanced)</small></h2>

				<div class="tool-section">
					<p><?php esc_html_e( 'Regenerate WordPress rewrite rules and register BRAGBook Gallery query variables. Use this when gallery URLs return 404 errors or after plugin updates.', 'brag-book-gallery' ); ?></p>

					<div class="notice notice-info">
						<p>
							<?php echo $this->get_info_icon(); ?>
							<?php esc_html_e( 'The comprehensive flush registers all 7 query variables and is recommended for troubleshooting.', 'brag-book-gallery' ); ?>
						</p>
					</div>

					<h3><?php esc_html_e( 'When to flush rewrite rules:', 'brag-book-gallery' ); ?></h3>
					<ul>
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

					<div class="flush-option">
						<button class="button button-primary button-large" id="flush-rules-standard">
							<?php esc_html_e( 'Standard Flush', 'brag-book-gallery' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Regenerates rewrite rules normally.', 'brag-book-gallery' ); ?>
						</p>
					</div>

					<div class="flush-option">
						<button class="button button-secondary button-large" id="flush-rules-hard">
							<?php esc_html_e( 'Hard Flush', 'brag-book-gallery' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Forces complete regeneration and updates .htaccess file.', 'brag-book-gallery' ); ?>
						</p>
					</div>

					<div class="flush-option">
						<button class="button button-secondary" id="flush-with-registration">
							<?php esc_html_e( 'Flush with Rule Registration', 'brag-book-gallery' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Re-registers custom rules before flushing.', 'brag-book-gallery' ); ?>
						</p>
					</div>

					<div class="flush-option featured">
						<h4><?php esc_html_e( 'Comprehensive Standalone Flush', 'brag-book-gallery' ); ?></h4>
						<button class="button button-primary button-large" id="flush-rules-standalone">
							<?php esc_html_e( 'Run Comprehensive Flush Process', 'brag-book-gallery' ); ?>
						</button>
						<p class="description">
							<strong><?php esc_html_e( 'Complete 5-step flush process that:', 'brag-book-gallery' ); ?></strong><br>
							• <?php esc_html_e( 'Performs environment compatibility check', 'brag-book-gallery' ); ?><br>
							• <?php esc_html_e( 'Registers all 7 BRAGBook query variables', 'brag-book-gallery' ); ?><br>
							• <?php esc_html_e( 'Re-registers custom rewrite rules', 'brag-book-gallery' ); ?><br>
							• <?php esc_html_e( 'Performs hard flush with .htaccess update', 'brag-book-gallery' ); ?><br>
							• <?php esc_html_e( 'Clears all caches and transients', 'brag-book-gallery' ); ?><br>
							<em><?php esc_html_e( 'Includes detailed diagnostics and sample URLs', 'brag-book-gallery' ); ?></em>
						</p>
					</div>

					<div id="flush-result"></div>
				</div>

				<div class="tool-section">
					<h3><?php esc_html_e( 'After Flushing', 'brag-book-gallery' ); ?></h3>
					<button class="button" id="verify-rules">
						<?php esc_html_e( 'Verify Rules', 'brag-book-gallery' ); ?>
					</button>
					<button class="button" id="test-urls">
						<?php esc_html_e( 'Test URLs', 'brag-book-gallery' ); ?>
					</button>
					<div id="verify-result"></div>
				</div>

				<!-- Dialog for Standalone Flush Results -->
				<dialog id="standalone-flush-dialog">
					<div style="display: flex; flex-direction: column; height: 100%;">
						<div class="dialog-header">
							<h2><?php esc_html_e( 'Comprehensive Flush Results', 'brag-book-gallery' ); ?></h2>
							<button type="button" id="close-dialog" aria-label="<?php esc_attr_e( 'Close dialog', 'brag-book-gallery' ); ?>">&times;</button>
						</div>
						<div id="dialog-content" class="dialog-content">
							<!-- Results will be inserted here -->
						</div>
						<div class="dialog-footer">
							<button type="button" class="button button-primary" id="close-dialog-footer"><?php esc_html_e( 'Close', 'brag-book-gallery' ); ?></button>
						</div>
					</div>
				</dialog>

				<div class="tool-section">
					<h3><?php esc_html_e( 'Danger Zone', 'brag-book-gallery' ); ?></h3>
					<div class="flush-option" style="border: 2px solid #dc3545; padding: 15px; border-radius: 4px; background-color: #fff5f5;">
						<button class="button button-link-delete" id="delete-all-rules" style="background-color: #dc3545; border-color: #dc3545; color: white;">
							<?php esc_html_e( 'Delete All Rewrite Rules', 'brag-book-gallery' ); ?>
						</button>
						<p class="description" style="color: #dc3545;">
							<strong><?php esc_html_e( 'Warning:', 'brag-book-gallery' ); ?></strong> 
							<?php esc_html_e( 'This will remove ALL rewrite rules from the database. WordPress will regenerate default rules on next page load, but custom rules will need to be re-registered.', 'brag-book-gallery' ); ?>
						</p>
					</div>
				</div>

				<div class="tool-section">
					<h3><?php esc_html_e( 'Alternative Methods', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'You can also flush rewrite rules by:', 'brag-book-gallery' ); ?></p>
					<ul>
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
						<?php if ( 'VIP' === $this->detect_hosting_provider() ) : ?>
						<li><?php esc_html_e( 'VIP Platform: Rules are managed automatically', 'brag-book-gallery' ); ?></li>
						<?php endif; ?>
					</ul>
				</div>
			</div>

			<script>
			document.addEventListener('DOMContentLoaded', function() {
				/**
				 * Modern AJAX request handler using fetch API with async/await.
				 *
				 * @param {Object} data - Request data to send
				 * @param {Function} onSuccess - Success callback
				 * @param {Function} onError - Error callback
				 * @param {Function} onComplete - Complete callback
				 * @returns {Promise<void>}
				 */
				const ajaxRequest = async function(data, onSuccess, onError, onComplete) {
					try {
						const formData = new FormData();
						Object.entries(data).forEach(function([key, value]) {
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
				const handleButtonState = function(button, isLoading, loadingText = 'Processing...') {
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
				const displayResult = function(target, content, type = 'info') {
					if (!target) return;

					const cssClass = {
						success: 'notice notice-success',
						error: 'notice notice-error',
						info: 'notice notice-info',
						warning: 'notice notice-warning'
					}[type] || 'notice notice-info';

					target.innerHTML = '<div class="' + cssClass + '"><p>' + content + '</p></div>';
				};

				// Element references
				const resultDiv = document.getElementById('flush-result');
				const verifyResult = document.getElementById('verify-result');

				// Standard flush handler
				const standardFlushBtn = document.getElementById('flush-rules-standard');
				if (standardFlushBtn) {
					standardFlushBtn.addEventListener('click', async function() {
						handleButtonState(standardFlushBtn, true, 'Flushing...');
						displayResult(resultDiv, 'Flushing rewrite rules...', 'info');

						await ajaxRequest(
							{
								action: 'brag_book_gallery_flush_rewrite_rules',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_flush_rewrite_rules' ) ); ?>',
								flush_type: 'standard'
							},
							function(response) {
								const type = response.success ? 'success' : 'error';
								const content = response.success ? response.data : 'Error: ' + response.data;
								displayResult(resultDiv, content, type);
							},
							function() { displayResult(resultDiv, 'Failed to flush rewrite rules. Please try again.', 'error'); },
							function() { handleButtonState(standardFlushBtn, false); }
						);
					});
				}

				// Hard flush handler
				const hardFlushBtn = document.getElementById('flush-rules-hard');
				if (hardFlushBtn) {
					hardFlushBtn.addEventListener('click', async function() {
						handleButtonState(hardFlushBtn, true, 'Hard flushing...');
						displayResult(resultDiv, 'Performing hard flush...', 'info');

						await ajaxRequest(
							{
								action: 'brag_book_gallery_flush_rewrite_rules',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_flush_rewrite_rules' ) ); ?>',
								flush_type: 'hard'
							},
							function(response) {
								const type = response.success ? 'success' : 'error';
								const content = response.success ? response.data : 'Error: ' + response.data;
								displayResult(resultDiv, content, type);
							},
							function() { displayResult(resultDiv, 'Failed to perform hard flush. Please try again.', 'error'); },
							function() { handleButtonState(hardFlushBtn, false); }
						);
					});
				}

				// Flush with registration handler
				const flushWithRegBtn = document.getElementById('flush-with-registration');
				if (flushWithRegBtn) {
					flushWithRegBtn.addEventListener('click', async function() {
						handleButtonState(flushWithRegBtn, true, 'Re-registering...');
						displayResult(resultDiv, 'Re-registering rules and flushing...', 'info');

						await ajaxRequest(
							{
								action: 'brag_book_gallery_flush_rewrite_rules',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_flush_rewrite_rules' ) ); ?>',
								flush_type: 'with_registration'
							},
							function(response) {
								const type = response.success ? 'success' : 'error';
								const content = response.success ? response.data : 'Error: ' + response.data;
								displayResult(resultDiv, content, type);
							},
							function() { displayResult(resultDiv, 'Failed to flush with registration. Please try again.', 'error'); },
							function() { handleButtonState(flushWithRegBtn, false); }
						);
					});
				}

				// Standalone flush with dialog handler
				const standaloneFlushBtn = document.getElementById('flush-rules-standalone');
				if (standaloneFlushBtn) {
					standaloneFlushBtn.addEventListener('click', async function() {
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
								action: 'brag_book_gallery_debug_tool',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_debug_tools' ) ); ?>',
								tool: 'rewrite-flush',
								tool_action: 'standalone'
							},
							function(response) {
								if (response.success) {
									dialogContent.innerHTML = response.data;
								} else {
									dialogContent.innerHTML = '<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>';
								}
							},
							function() {
								dialogContent.innerHTML = '<div class="notice notice-error"><p><?php echo esc_js( __( 'Failed to run standalone flush. Please try again.', 'brag-book-gallery' ) ); ?></p></div>';
							},
							function() { handleButtonState(standaloneFlushBtn, false); }
						);
					});
				}

				// Dialog close handlers
				const dialog = document.getElementById('standalone-flush-dialog');
				const closeDialogBtn = document.getElementById('close-dialog');
				const closeDialogFooterBtn = document.getElementById('close-dialog-footer');

				/**
				 * Close dialog helper function.
				 */
				const closeDialog = function() {
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
					dialog.addEventListener('cancel', function(e) {
						e.preventDefault();
						closeDialog();
					});
				}

				// Verify rules handler
				const verifyBtn = document.getElementById('verify-rules');
				if (verifyBtn) {
					verifyBtn.addEventListener('click', async function() {
						handleButtonState(verifyBtn, true, 'Verifying...');
						displayResult(verifyResult, 'Verifying rules...', 'info');

						await ajaxRequest(
							{
								action: 'brag_book_gallery_debug_tool',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_debug_tools' ) ); ?>',
								tool: 'rewrite-flush',
								tool_action: 'verify'
							},
							function(response) {
								if (response.success) {
									if (verifyResult) verifyResult.innerHTML = response.data;
								} else {
									displayResult(verifyResult, 'Error: ' + response.data, 'error');
								}
							},
							function() { displayResult(verifyResult, 'Failed to verify rules. Please try again.', 'error'); },
							function() { handleButtonState(verifyBtn, false); }
						);
					});
				}

				// Test URLs handler
				const testUrlsBtn = document.getElementById('test-urls');
				if (testUrlsBtn) {
					testUrlsBtn.addEventListener('click', async function() {
						handleButtonState(testUrlsBtn, true, 'Testing...');
						displayResult(verifyResult, 'Testing URLs...', 'info');

						await ajaxRequest(
							{
								action: 'brag_book_gallery_debug_tool',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_debug_tools' ) ); ?>',
								tool: 'rewrite-flush',
								tool_action: 'test_urls'
							},
							function(response) {
								if (response.success) {
									if (verifyResult) verifyResult.innerHTML = response.data;
								} else {
									displayResult(verifyResult, 'Error: ' + response.data, 'error');
								}
							},
							function() { displayResult(verifyResult, 'Failed to test URLs. Please try again.', 'error'); },
							function() { handleButtonState(testUrlsBtn, false); }
						);
					});
				}

				// Delete all rules handler
				const deleteAllRulesBtn = document.getElementById('delete-all-rules');
				if (deleteAllRulesBtn) {
					deleteAllRulesBtn.addEventListener('click', async function() {
						if (!confirm('<?php echo esc_js( __( 'WARNING: This will delete ALL rewrite rules from the database. WordPress will regenerate default rules, but custom rules will be lost. Are you absolutely sure?', 'brag-book-gallery' ) ); ?>')) {
							return;
						}

						// Double confirmation for safety
						if (!confirm('<?php echo esc_js( __( 'This action cannot be undone. Please confirm again to delete all rewrite rules.', 'brag-book-gallery' ) ); ?>')) {
							return;
						}

						handleButtonState(deleteAllRulesBtn, true, 'Deleting...');
						displayResult(resultDiv, 'Deleting all rewrite rules...', 'warning');

						await ajaxRequest(
							{
								action: 'brag_book_gallery_delete_all_rewrite_rules',
								nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_gallery_delete_rewrite_rules' ) ); ?>'
							},
							function(response) {
								if (response.success) {
									displayResult(resultDiv, response.data, 'success');
									// Reload page after 2 seconds to show updated status
									setTimeout(function() {
										window.location.reload();
									}, 2000);
								} else {
									// Better error handling
									const errorMsg = typeof response.data === 'string' 
										? response.data 
										: (response.data?.message || JSON.stringify(response.data));
									displayResult(resultDiv, 'Error: ' + errorMsg, 'error');
								}
							},
							function(error) { 
								// Better error handling for network/parse errors
								const errorMsg = error?.message || error?.toString() || 'Failed to delete rewrite rules';
								displayResult(resultDiv, errorMsg, 'error'); 
							},
							function() { handleButtonState(deleteAllRulesBtn, false); }
						);
					});
				}
			});
			</script>
			<?php

			$this->metrics['render_time'] = microtime( true ) - $start_time;
			$this->maybe_log_performance();

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			echo '<div class="notice notice-error"><p>' .
				esc_html( sprintf(
					/* translators: %s: Error message */
					__( 'Failed to render flush tool: %s', 'brag-book-gallery' ),
					$e->getMessage()
				) ) . '</p></div>';
		}
	}

	/**
	 * Render current rewrite rules status information.
	 *
	 * Displays comprehensive status including total rules count, gallery-specific rules,
	 * permalink structure, environment-specific information, and performance metrics.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function render_current_status(): void {
		try {
			global $wp_rewrite;

			// Check if rewrite rules option exists at all
			$rules_option = get_option( 'rewrite_rules' );
			
			// If the option is false or empty, WordPress hasn't regenerated yet
			if ( $rules_option === false ) {
				$rules = array();
			} else {
				$rules = $wp_rewrite->wp_rewrite_rules();
			}
			
			$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
			$gallery_rules_count = 0;
			$rule_patterns = [];

			if ( ! empty( $rules ) ) {
				foreach ( $rules as $pattern => $query ) {
					if (
						( $brag_book_gallery_page_slug && str_contains( $pattern, $brag_book_gallery_page_slug ) ) ||
						str_contains( $query, 'brag_book_gallery_view' ) ||
						str_contains( $query, 'brag_gallery_slug' ) ||
						str_contains( $query, 'brag_gallery_category' ) ||
						str_contains( $query, 'brag_book_gallery_case' ) ||
						str_contains( $query, 'favorites_page' ) ||
						str_contains( $query, 'procedure_title' ) ||
						str_contains( $query, 'case_id' )
					) {
						$gallery_rules_count++;
						$rule_patterns[] = $pattern;
					}
				}
			}

			$permalink_structure = get_option( 'permalink_structure' ) ?: 'Plain';
			$has_pretty_permalinks = ! empty( get_option( 'permalink_structure' ) );
			$hosting_provider = $this->detect_hosting_provider();
			
			// Special display if rules were completely deleted
			if ( $rules_option === false ) {
				?>
				<div class="notice notice-warning" style="margin: 20px 0;">
					<p>
						<strong><?php esc_html_e( 'No rewrite rules found in database!', 'brag-book-gallery' ); ?></strong><br>
						<?php esc_html_e( 'The rewrite_rules option has been completely deleted. WordPress will regenerate rules when needed.', 'brag-book-gallery' ); ?>
					</p>
				</div>
				<?php
			}
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
						<?php if ( ! empty( $rule_patterns ) ) : ?>
						<details style="margin-top: 10px;">
							<summary style="cursor: pointer;"><?php esc_html_e( 'View patterns', 'brag-book-gallery' ); ?></summary>
							<ul style="margin: 5px 0; font-size: 11px;">
								<?php foreach ( array_slice( $rule_patterns, 0, 3 ) as $pattern ) : ?>
								<li><code><?php echo esc_html( substr( $pattern, 0, 50 ) . ( strlen( $pattern ) > 50 ? '...' : '' ) ); ?></code></li>
								<?php endforeach; ?>
							</ul>
						</details>
						<?php endif; ?>
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
				<?php if ( 'Standard' !== $hosting_provider ) : ?>
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
							<div class="status-notice-warning">
								<strong><?php echo esc_html( $hosting_provider ); ?> <?php esc_html_e( 'Detected', 'brag-book-gallery' ); ?></strong>
								<?php
								$cache_instructions = $this->get_cache_instructions();
								if ( ! empty( $cache_instructions['instruction'] ) ) :
								?>
								<p><?php echo esc_html( $cache_instructions['instruction'] ); ?></p>
								<?php endif; ?>
							</div>
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
						<h4 class="status-card-title"><?php esc_html_e( 'Health Status', 'brag-book-gallery' ); ?></h4>
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
						<?php
						// Show last flush time if available
						$last_flush = brag_book_get_cache( self::CACHE_PREFIX . 'last_flush' );
						if ( $last_flush ) :
						?>
						<p class="status-hint" style="margin-top: 10px; font-size: 11px;">
							<?php
							printf(
								/* translators: %s: Time ago */
								esc_html__( 'Last flush: %s ago', 'brag-book-gallery' ),
								human_time_diff( $last_flush['time'] )
							);
							?>
						</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to load status', 'brag-book-gallery' ) . '</p></div>';
		}
	}

	/**
	 * Execute tool actions via AJAX with modern error handling.
	 *
	 * Handles various flush operations including standard, hard, with registration,
	 * standalone comprehensive flush, rules verification, and URL testing.
	 *
	 * @since 3.0.0
	 *
	 * @param string $action The specific action to execute.
	 * @param array  $data   Additional request data and parameters.
	 * @return string|array The result of the executed action.
	 * @throws Exception When an invalid action is provided or security check fails.
	 */
	public function execute( string $action, array $data ): string|array {
		try {
			$start_time = microtime( true );

			// Security validation
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( __( 'Insufficient permissions', 'brag-book-gallery' ) );
			}

			// Rate limiting check
			$last_flush = brag_book_get_cache( self::CACHE_PREFIX . 'last_flush' );
			if ( $last_flush && ( time() - $last_flush['time'] ) < 10 && in_array( $action, [ 'flush', 'standalone' ], true ) ) {
				throw new Exception( __( 'Please wait before flushing again', 'brag-book-gallery' ) );
			}

			$result = match ( $action ) {
				'flush' => $this->flush_rules( $data['flush_type'] ?? 'standard' ),
				'verify' => $this->verify_rules(),
				'standalone' => $this->run_standalone_flush(),
				'test_urls' => $this->test_gallery_urls(),
				default => throw new Exception( sprintf( 'Invalid action: %s', esc_html( $action ) ) )
			};

			// Track flush operations
			if ( in_array( $action, [ 'flush', 'standalone' ], true ) ) {
				brag_book_set_cache(
					self::CACHE_PREFIX . 'last_flush',
					[
						'time' => time(),
						'type' => $data['flush_type'] ?? $action,
					],
					3600
				);
			}

			$this->metrics[ $action . '_time' ] = microtime( true ) - $start_time;
			$this->maybe_log_performance();

			return $result;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			throw $e;
		}
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
		try {
			return match ( $type ) {
				'hard' => $this->perform_hard_flush(),
				'with_registration' => $this->perform_flush_with_registration(),
				'standard' => $this->perform_standard_flush(),
				default => $this->perform_standard_flush()
			};
		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}

	/**
	 * Perform hard flush operation.
	 *
	 * Forces complete regeneration of rewrite rules including database cleanup,
	 * rule re-registration, and .htaccess updates.
	 *
	 * @since 3.0.0
	 *
	 * @return string Flush results.
	 * @throws Exception If flush operation fails.
	 */
	private function perform_hard_flush(): string {
		$results = [];
		$start_time = microtime( true );

		try {
			// Force complete regeneration
			delete_option( 'rewrite_rules' );
			$results[] = $this->get_check_icon( true ) . 'Cleared existing rules from database';

			// Clear any cached rules
			wp_cache_delete( 'rewrite_rules', 'options' );
			$results[] = $this->get_check_icon( true ) . 'Cleared rules cache';

			// Register custom rules
			if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
				\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
				$results[] = $this->get_check_icon( true ) . 'Re-registered custom rules';
			}

			// Hard flush
			flush_rewrite_rules( true );
			$results[] = $this->get_check_icon( true ) . 'Performed hard flush (updated .htaccess)';

			// Clear transients
			$this->clear_related_transients();
			$results[] = $this->get_check_icon( true ) . 'Cleared related transients';

			$this->metrics['hard_flush_time'] = microtime( true ) - $start_time;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			$results[] = $this->get_check_icon( false ) . 'Flush failed: ' . $e->getMessage();
		}

		return $this->format_flush_results( $results, 'hard' );
	}

	/**
	 * Perform flush with registration.
	 *
	 * Re-registers custom rules before performing a standard flush operation.
	 *
	 * @since 3.0.0
	 *
	 * @return string Flush results.
	 * @throws Exception If flush operation fails.
	 */
	private function perform_flush_with_registration(): string {
		$results = [];
		$start_time = microtime( true );

		try {
			// Register query vars
			global $wp;
			$query_vars = [ 
				'procedure_title',    // Procedure name in case detail URLs
				'case_suffix',        // Case identifier (ID or SEO suffix)
				'favorites_section',  // Legacy favorites section indicator
				'filter_category',    // Category filter parameter
				'filter_procedure',   // Procedure filter parameter
				'favorites_page',     // Favorites page indicator
			];
			foreach ( $query_vars as $var ) {
				$wp->add_query_var( $var );
			}
			$results[] = $this->get_check_icon( true ) . 'Registered query variables';

			// Register custom rules
			if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
				\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
				$results[] = $this->get_check_icon( true ) . 'Re-registered custom rules';
			}

			// Standard flush
			flush_rewrite_rules( false );
			$results[] = $this->get_check_icon( true ) . 'Flushed rewrite rules';

			$this->metrics['registration_flush_time'] = microtime( true ) - $start_time;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			$results[] = $this->get_check_icon( false ) . 'Flush failed: ' . $e->getMessage();
		}

		return $this->format_flush_results( $results, 'with_registration' );
	}

	/**
	 * Perform standard flush operation.
	 *
	 * Performs a standard rewrite rules flush without additional operations.
	 *
	 * @since 3.0.0
	 *
	 * @return string Flush results.
	 * @throws Exception If flush operation fails.
	 */
	private function perform_standard_flush(): string {
		$results = [];
		$start_time = microtime( true );

		try {
			// Standard flush
			flush_rewrite_rules( false );
			$results[] = $this->get_check_icon( true ) . 'Flushed rewrite rules';

			// Clear basic cache
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
				$results[] = $this->get_check_icon( true ) . 'Cleared object cache';
			}

			$this->metrics['standard_flush_time'] = microtime( true ) - $start_time;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			$results[] = $this->get_check_icon( false ) . 'Flush failed: ' . $e->getMessage();
		}

		return $this->format_flush_results( $results, 'standard' );
	}

	/**
	 * Format flush results with common elements.
	 *
	 * Adds hosting-specific instructions and performance metrics to flush results.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $results Results array.
	 * @param string $type    Type of flush performed.
	 * @return string Formatted results.
	 */
	private function format_flush_results( array $results, string $type = 'standard' ): string {
		// Add hosting-specific instructions
		$cache_instructions = $this->get_cache_instructions();
		if ( ! empty( $cache_instructions['instruction'] ) ) {
			$icon = 'high' === $cache_instructions['priority'] ? $this->get_warning_icon() : $this->get_info_icon();
			$results[] = $icon . $cache_instructions['instruction'];
		}

		// Add performance metrics if available
		if ( ! empty( $this->metrics ) ) {
			$total_time = array_sum( $this->metrics );
			$results[] = sprintf(
				'<small>%s</small>',
				esc_html( sprintf(
					/* translators: %s: Time in seconds */
					__( 'Completed in %s seconds', 'brag-book-gallery' ),
					number_format( $total_time, 3 )
				) )
			);
		}

		$output = implode( '<br>', $results );
		$output .= '<br><br><strong>' . __( 'Rewrite rules flushed successfully!', 'brag-book-gallery' ) . '</strong>';
		$output .= '<br>' . __( 'Test your gallery URLs to verify they work correctly.', 'brag-book-gallery' );

		// Add test button
		$output .= '<br><br><button type="button" class="button" onclick="document.getElementById(\'verify-rules\').click();">' .
				   __( 'Verify Rules', 'brag-book-gallery' ) . '</button>';

		return $output;
	}

	/**
	 * Run comprehensive standalone flush with detailed diagnostics.
	 *
	 * Performs a complete flush process with extensive diagnostic output,
	 * rule registration verification, sample URL generation, and performance metrics.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted comprehensive flush report.
	 */
	private function run_standalone_flush(): string {
		try {
			$start_time = microtime( true );
			$output = '<div class="standalone-flush-results">';
			$output .= '<h3>' . __( 'Comprehensive Flush Process', 'brag-book-gallery' ) . '</h3>';

			// Step 1: Environment check
			$output .= '<h4>' . __( 'Step 1: Environment Check', 'brag-book-gallery' ) . '</h4>';
			$hosting = $this->detect_hosting_provider();
			$output .= '<p>' . $this->get_check_icon( true ) . sprintf(
				/* translators: %s: Hosting provider */
				__( 'Hosting: %s', 'brag-book-gallery' ),
				esc_html( $hosting )
			) . '</p>';

			// Step 2: Register query variables
			$output .= '<h4>' . __( 'Step 2: Register Query Variables', 'brag-book-gallery' ) . '</h4>';
			global $wp;
			$query_vars = [ 
				'procedure_title',    // Procedure name in case detail URLs
				'case_suffix',        // Case identifier (ID or SEO suffix)
				'favorites_section',  // Legacy favorites section indicator
				'filter_category',    // Category filter parameter
				'filter_procedure',   // Procedure filter parameter
				'favorites_page',     // Favorites page indicator
			];
			foreach ( $query_vars as $var ) {
				$wp->add_query_var( $var );
			}
			$output .= '<p>' . $this->get_check_icon( true ) . sprintf( 
				/* translators: %d: Number of query variables registered */
				__( 'Registered %d query variables', 'brag-book-gallery' ), 
				count( $query_vars ) 
			) . '</p>';

			// Step 3: Register rules
			$output .= '<h4>' . __( 'Step 3: Rule Registration', 'brag-book-gallery' ) . '</h4>';
			if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
				\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
				$output .= '<p>' . $this->get_check_icon( true ) . __( 'Custom rewrite rules registered', 'brag-book-gallery' ) . '</p>';
			}

			// Step 4: Flush rules
			$output .= '<h4>' . __( 'Step 4: Flush Rules', 'brag-book-gallery' ) . '</h4>';
			flush_rewrite_rules( true );
			$output .= '<p>' . $this->get_check_icon( true ) . __( 'Rewrite rules flushed (hard flush with .htaccess update)', 'brag-book-gallery' ) . '</p>';

			// Step 5: Clear caches
			$output .= '<h4>' . __( 'Step 5: Clear Caches', 'brag-book-gallery' ) . '</h4>';
			$this->clear_all_caches();
			$output .= '<p>' . $this->get_check_icon( true ) . __( 'All caches cleared', 'brag-book-gallery' ) . '</p>';

			// Display registered query variables
			$output .= '<h4>' . __( 'Registered Query Variables:', 'brag-book-gallery' ) . '</h4>';
			$output .= '<ul>';
			$query_vars = [
				'procedure_title' => __( 'Used for procedure names in case detail URLs', 'brag-book-gallery' ),
				'case_suffix' => __( 'Used for case identifiers (ID or SEO suffix)', 'brag-book-gallery' ),
				'favorites_section' => __( 'Used for legacy favorites section indicator', 'brag-book-gallery' ),
				'filter_category' => __( 'Used for category filtering in gallery views', 'brag-book-gallery' ),
				'filter_procedure' => __( 'Used for procedure filtering in gallery views', 'brag-book-gallery' ),
				'favorites_page' => __( 'Used for favorites page detection and routing', 'brag-book-gallery' ),
			];
			foreach ( $query_vars as $var => $description ) {
				$output .= '<li><code>' . esc_html( $var ) . '</code> - ' . esc_html( $description ) . '</li>';
			}
			$output .= '</ul>';

			// Display gallery configuration
			$output .= '<h4>' . __( 'Gallery Configuration:', 'brag-book-gallery' ) . '</h4>';
			$gallery_slugs = $this->get_gallery_slugs();
			$output .= '<ul>';
			if ( empty( $gallery_slugs ) ) {
				$output .= '<li><em>' . __( 'No gallery page slugs configured', 'brag-book-gallery' ) . '</em></li>';
			} else {
				foreach ( $gallery_slugs as $slug ) {
					$output .= '<li><code>' . esc_html( $slug ) . '</code></li>';
				}
			}
			$output .= '</ul>';

			// Sample URLs
			$output .= '<h4>' . __( 'Sample URLs:', 'brag-book-gallery' ) . '</h4>';
			$output .= $this->generate_sample_urls();

			// Performance metrics
			$elapsed_time = microtime( true ) - $start_time;
			$output .= '<h4>' . __( 'Performance:', 'brag-book-gallery' ) . '</h4>';
			$output .= '<p>' . sprintf(
				/* translators: %s: Time in seconds */
				__( 'Process completed in %s seconds', 'brag-book-gallery' ),
				number_format( $elapsed_time, 3 )
			) . '</p>';

			// Next steps
			$output .= '<h4>' . __( 'Next Steps:', 'brag-book-gallery' ) . '</h4>';
			$output .= '<ul>';
			$output .= '<li>' . __( 'Test your gallery URLs to ensure they\'re working properly', 'brag-book-gallery' ) . '</li>';
			$output .= '<li>' . __( 'If you still see 404 errors, check your permalink settings', 'brag-book-gallery' ) . '</li>';
			$output .= '<li>' . __( 'Make sure your gallery page exists and has the [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</li>';

			// Add hosting-specific instructions
			$cache_instructions = $this->get_cache_instructions();
			if ( ! empty( $cache_instructions['instruction'] ) ) {
				$output .= '<li><strong>' . esc_html( $cache_instructions['instruction'] ) . '</strong></li>';
			}
			$output .= '</ul>';

			// Action buttons
			$first_slug = ! empty( $gallery_slugs ) ? reset( $gallery_slugs ) : 'gallery';
			$output .= '<p>';
			$output .= '<a href="' . esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ) . '" class="button">' . __( '← Back to Settings', 'brag-book-gallery' ) . '</a> ';
			$output .= '<a href="' . esc_url( home_url( '/' . $first_slug ) ) . '" class="button" target="_blank">' . __( 'View Gallery →', 'brag-book-gallery' ) . '</a>';
			$output .= '</p>';

			$output .= '</div>';

			return $output;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}

	/**
	 * Verify rewrite rules and query variables.
	 *
	 * Performs comprehensive verification of current rewrite rules including
	 * gallery-specific rules, query variable registration, conflict detection,
	 * and sample rule display.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted verification report.
	 */
	private function verify_rules(): string {
		try {
			global $wpdb, $wp;

			$output = '<div class="rules-verification">';
			$output .= '<h4>' . __( 'Verification Results:', 'brag-book-gallery' ) . '</h4>';
			
			// Check if rules were recently deleted
			$deletion_info = get_transient( 'brag_book_gallery_rules_deleted' );
			if ( $deletion_info && is_array( $deletion_info ) ) {
				$output .= '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 4px;">';
				$output .= '<strong>' . __( 'Recent Deletion:', 'brag-book-gallery' ) . '</strong> ';
				$output .= sprintf(
					__( '%d total rules deleted (%d were gallery rules).', 'brag-book-gallery' ),
					$deletion_info['deleted_count'] ?? 0,
					$deletion_info['gallery_count'] ?? 0
				);
				$output .= '</div>';
			}

			// Check if rules exist DIRECTLY in database without triggering regeneration
			// Use direct SQL to avoid triggering WordPress filters
			$rules_in_db = $wpdb->get_var( 
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = 'rewrite_rules' LIMIT 1" 
			);
			
			if ( $rules_in_db === null ) {
				// No rules in database at all
				$output .= '<div style="background: #d4edda; border: 1px solid #28a745; padding: 15px; margin: 10px 0; border-radius: 4px;">';
				$output .= '<p style="color: #28a745; margin: 0;"><strong>' . $this->get_check_icon( true ) . __( 'SUCCESS: No rewrite rules exist in the database!', 'brag-book-gallery' ) . '</strong></p>';
				$output .= '<p style="margin: 5px 0 0 0;">' . __( 'The rewrite_rules option has been completely removed.', 'brag-book-gallery' ) . '</p>';
				$output .= '<p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">' . __( 'Note: WordPress will regenerate rules automatically when needed (on next page visit).', 'brag-book-gallery' ) . '</p>';
				$output .= '</div>';
				$output .= '</div>';
				return $output;
			}
			
			// Rules exist - unserialize and count them
			$rules = maybe_unserialize( $rules_in_db );
			if ( ! is_array( $rules ) ) {
				$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . __( 'Invalid rewrite rules format in database!', 'brag-book-gallery' ) . '</p>';
				$output .= '</div>';
				return $output;
			}
			if ( empty( $rules ) ) {
				$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . __( 'No rewrite rules found!', 'brag-book-gallery' ) . '</p>';
				$output .= '<p>' . __( 'Recommendation: Perform a hard flush to regenerate rules.', 'brag-book-gallery' ) . '</p>';
				return $output . '</div>';
			}

			// Successfully got rules from database
			$rules_count = count( $rules );
			$output .= '<p style="color: ' . ($rules_count > 0 ? 'blue' : 'red') . ';">' . 
				($rules_count > 0 ? $this->get_info_icon() : $this->get_check_icon( false )) . 
				sprintf(
					/* translators: %d: Number of rules */
					__( 'Database contains %d rewrite rules', 'brag-book-gallery' ),
					$rules_count
				) . '</p>';

			// Check for gallery rules
			$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
			$gallery_rule_count = 0;
			$sample_rules = [];

			foreach ( $rules as $pattern => $query ) {
				if (
					( $brag_book_gallery_page_slug && str_contains( $pattern, $brag_book_gallery_page_slug ) ) ||
					str_contains( $query, 'brag_book_gallery_view' ) ||
					str_contains( $query, 'brag_gallery_slug' ) ||
					str_contains( $query, 'brag_gallery_category' ) ||
					str_contains( $query, 'brag_book_gallery_case' ) ||
					str_contains( $query, 'favorites_page' ) ||
					str_contains( $query, 'procedure_title' ) ||
					str_contains( $query, 'case_id' )
				) {
					$gallery_rule_count++;
					if ( count( $sample_rules ) < self::MAX_SAMPLE_RULES ) {
						$sample_rules[ $pattern ] = $query;
					}
				}
			}

			if ( $gallery_rule_count > 0 ) {
				$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . sprintf(
					/* translators: %d: Number of gallery rules */
					__( 'Found %d gallery rules', 'brag-book-gallery' ),
					$gallery_rule_count
				) . '</p>';
			} else {
				$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . __( 'Gallery rules are missing', 'brag-book-gallery' ) . '</p>';
			}

			// Check query vars (must match URL_Router and Rewrite_Rules_Handler)
			$required_vars = [ 
				// URL Router vars
				'brag_book_gallery_view',  // View type (index, category, single, search)
				'brag_gallery_slug',        // Category or procedure slug
				'brag_gallery_category',    // Category context for cases
				'brag_book_gallery_case',   // Individual case identifier
				'brag_gallery_search',      // Search term
				'brag_gallery_page',        // Pagination number
				'brag_gallery_filter',      // Additional filters
				'favorites_page',           // Favorites page indicator
				'filter_procedure',         // Procedure filter parameter
				// Legacy Rewrite Rules Handler vars
				'procedure_title',          // Procedure name in case detail URLs
				'case_suffix',              // Case identifier (ID or SEO suffix)
				'favorites_section',        // Legacy favorites section indicator
				'filter_category',          // Category filter parameter
			];
			$missing_vars = [];
			$registered_vars = [];

			// Check multiple places where query vars can be registered
			$all_query_vars = array_merge(
				$wp->public_query_vars ?? [],
				$wp->private_query_vars ?? [],
				array_keys( $wp->extra_query_vars ?? [] ),
				apply_filters( 'query_vars', [] )
			);

			foreach ( $required_vars as $var ) {
				if ( in_array( $var, $all_query_vars, true ) ) {
					$registered_vars[] = $var;
				} else {
					$missing_vars[] = $var;
				}
			}

			if ( empty( $missing_vars ) ) {
				$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . __( 'All required query vars are registered', 'brag-book-gallery' ) . '</p>';
			} else {
				$output .= '<p style="color: orange;">' . $this->get_warning_icon() . __( 'Missing query vars:', 'brag-book-gallery' ) . ' <code>' . implode( ', ', $missing_vars ) . '</code></p>';
			}

			// Check for potential conflicts
			$conflicts = $this->detect_rule_conflicts( $rules );
			if ( ! empty( $conflicts ) ) {
				$output .= '<h4>' . __( 'Potential Conflicts:', 'brag-book-gallery' ) . '</h4>';
				$output .= '<ul>';
				foreach ( $conflicts as $conflict ) {
					$output .= '<li>' . esc_html( $conflict ) . '</li>';
				}
				$output .= '</ul>';
			}

			// Sample rules display
			if ( ! empty( $sample_rules ) ) {
				$output .= '<h4>' . __( 'Sample Gallery Rules:', 'brag-book-gallery' ) . '</h4>';
				$output .= '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
				foreach ( $sample_rules as $pattern => $query ) {
					$output .= htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
				}
				$output .= '</pre>';
			}

			// Recommendations
			if ( $gallery_rule_count === 0 || ! empty( $missing_vars ) ) {
				$output .= '<h4>' . __( 'Recommendations:', 'brag-book-gallery' ) . '</h4>';
				$output .= '<ul>';
				if ( $gallery_rule_count === 0 ) {
					$output .= '<li>' . __( 'Perform a "Flush with Rule Registration" to add gallery rules', 'brag-book-gallery' ) . '</li>';
				}
				if ( ! empty( $missing_vars ) ) {
					$output .= '<li>' . __( 'Re-register query variables by using the comprehensive flush', 'brag-book-gallery' ) . '</li>';
				}
				$output .= '</ul>';
			}

			$output .= '</div>';
			return $output;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}

	/**
	 * Test gallery URLs for proper routing.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted test results.
	 */
	private function test_gallery_urls(): string {
		try {
			$output = '<div class="url-test-results">';
			$output .= '<h4>' . __( 'Gallery URL Patterns:', 'brag-book-gallery' ) . '</h4>';

			$gallery_slugs = $this->get_gallery_slugs();
			if ( empty( $gallery_slugs ) ) {
				$output .= '<p>' . __( 'No gallery slugs configured. URL patterns will be available once a gallery page is set up.', 'brag-book-gallery' ) . '</p>';
				return $output . '</div>';
			}

			$site_url = home_url();
			$first_slug = reset( $gallery_slugs );
			
			// Generate comprehensive URL lists from real API data
			$url_patterns = $this->generate_comprehensive_url_patterns( $site_url, $first_slug );

			$output .= '<div style="margin-bottom: 20px;">';
			$output .= '<p>' . __( 'The following URL patterns are available for your gallery:', 'brag-book-gallery' ) . '</p>';
			$output .= '<p><strong>' . __( 'Current gallery slug:', 'brag-book-gallery' ) . '</strong> <code>' . esc_html( $first_slug ) . '</code></p>';
			$output .= '</div>';

			$output .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
			$output .= '<thead><tr>';
			$output .= '<th style="text-align: left; padding: 12px; border-bottom: 2px solid #ddd; background-color: #f9f9f9;">' . __( 'URL Pattern', 'brag-book-gallery' ) . '</th>';
			$output .= '<th style="text-align: left; padding: 12px; border-bottom: 2px solid #ddd; background-color: #f9f9f9;">' . __( 'Live Example', 'brag-book-gallery' ) . '</th>';
			$output .= '<th style="text-align: left; padding: 12px; border-bottom: 2px solid #ddd; background-color: #f9f9f9;">' . __( 'Description', 'brag-book-gallery' ) . '</th>';
			$output .= '</tr></thead><tbody>';

			foreach ( $url_patterns as $type => $pattern_data ) {
				$status = $this->test_single_url( $pattern_data['example'] );
				$output .= '<tr style="border-bottom: 1px solid #eee;">';
				$output .= '<td style="padding: 12px; vertical-align: top;"><strong>' . esc_html( $type ) . '</strong><br><code style="background: #f0f0f0; padding: 2px 4px; border-radius: 3px; font-size: 12px;">' . esc_html( $pattern_data['pattern'] ) . '</code></td>';
				$output .= '<td style="padding: 12px; vertical-align: top;"><a href="' . esc_url( $pattern_data['example'] ) . '" target="_blank" style="text-decoration: none;">' . esc_html( $pattern_data['example'] ) . '</a><br><small style="color: #666;">' . $status . '</small></td>';
				$output .= '<td style="padding: 12px; vertical-align: top; color: #666;">' . esc_html( $pattern_data['description'] ) . '</td>';
				$output .= '</tr>';
			}

			$output .= '</tbody></table>';
			
			$output .= '<div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin-top: 20px;">';
			$output .= '<h5 style="margin-top: 0;">' . __( 'Pattern Variables (Live Examples):', 'brag-book-gallery' ) . '</h5>';
			$output .= '<ul style="margin-bottom: 0;">';
			$output .= '<li><code>{site_url}</code> - ' . __( 'Your site\'s base URL', 'brag-book-gallery' ) . ' (<code>' . esc_html( $site_url ) . '</code>)</li>';
			$output .= '<li><code>{gallery_slug}</code> - ' . __( 'Your gallery page slug', 'brag-book-gallery' ) . ' (<code>' . esc_html( $first_slug ) . '</code>)</li>';
			$output .= '<li><code>{procedure-name}</code> - ' . __( 'Real procedure from your API', 'brag-book-gallery' ) . ' (<code>' . esc_html( $api_data['procedure_slug'] ) . '</code>)</li>';
			$output .= '<li><code>{case-identifier}</code> - ' . __( 'Real case from your API', 'brag-book-gallery' ) . ' (<code>' . esc_html( $api_data['case_identifier'] ) . '</code>)</li>';
			$output .= '</ul>';
			$output .= '<p style="margin: 10px 0 0 0; font-style: italic; color: #666;">' . __( 'Examples above use real data from your configured API token and website property ID.', 'brag-book-gallery' ) . '</p>';
			$output .= '</div>';
			
			$output .= '</div>';

			return $output;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
		}
	}

	/**
	 * Test a single URL for proper routing.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url URL to test.
	 * @return string Status HTML.
	 */
	private function test_single_url( string $url ): string {
		$response = wp_remote_head( $url, [
			'timeout' => 5,
			'redirection' => 0,
			'sslverify' => false,
		] );

		if ( is_wp_error( $response ) ) {
			return '<span style="color: red;">' . $this->get_check_icon( false ) . __( 'Error', 'brag-book-gallery' ) . '</span>';
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		return match ( true ) {
			$status_code === 200 => '<span style="color: green;">' . $this->get_check_icon( true ) . __( 'OK', 'brag-book-gallery' ) . '</span>',
			$status_code === 404 => '<span style="color: red;">' . $this->get_check_icon( false ) . __( '404 Not Found', 'brag-book-gallery' ) . '</span>',
			in_array( $status_code, [ 301, 302 ], true ) => '<span style="color: orange;">' . $this->get_warning_icon() . __( 'Redirect', 'brag-book-gallery' ) . '</span>',
			default => '<span style="color: orange;">' . $this->get_warning_icon() . sprintf( __( 'HTTP %d', 'brag-book-gallery' ), $status_code ) . '</span>',
		};
	}

	/**
	 * Detect rule conflicts.
	 *
	 * @since 3.0.0
	 *
	 * @param array $rules WordPress rewrite rules.
	 * @return array<string> List of detected conflicts.
	 */
	private function detect_rule_conflicts( array $rules ): array {
		$conflicts = [];
		$gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();

		if ( ! $gallery_slug ) {
			return $conflicts;
		}

		// Check for conflicting patterns
		foreach ( $rules as $pattern => $query ) {
			// Check if non-gallery rules might capture gallery URLs
			if ( ! str_contains( $pattern, $gallery_slug ) &&
				 preg_match( '/^\^?([^\/]+)/', $pattern, $matches ) ) {
				if ( str_starts_with( $gallery_slug, $matches[1] ) ) {
					$conflicts[] = sprintf(
						/* translators: %s: Pattern that may conflict */
						__( 'Pattern "%s" may conflict with gallery URLs', 'brag-book-gallery' ),
						$pattern
					);
				}
			}
		}

		return array_slice( $conflicts, 0, 3 ); // Limit to 3 conflicts
	}

	/**
	 * Get gallery slugs.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string> Gallery slugs.
	 */
	private function get_gallery_slugs(): array {
		$slugs = [];

		// 1. Use the same method as rewrite rules handler - primary source
		if ( class_exists( '\BRAGBookGallery\Includes\Core\Slug_Helper' ) ) {
			$gallery_page_slugs = \BRAGBookGallery\Includes\Core\Slug_Helper::get_all_gallery_page_slugs();
			if ( is_array( $gallery_page_slugs ) ) {
				$slugs = array_merge( $slugs, $gallery_page_slugs );
			}
		}

		// 2. Auto-detect pages with gallery shortcode (same as rewrite rules handler)
		if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
			// Use reflection to access private method if needed, or duplicate the logic
			$pages = $this->find_pages_with_shortcode();
			foreach ( $pages as $page ) {
				if ( ! empty( $page->post_name ) ) {
					$slugs[] = $page->post_name;
				}
			}
		}

		// 3. Check legacy options as fallback
		$gallery_option_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
		if ( ! is_array( $gallery_option_slugs ) ) {
			$gallery_option_slugs = [ $gallery_option_slugs ];
		}
		$slugs = array_merge( $slugs, $gallery_option_slugs );

		// 4. Check stored pages option
		$stored_pages = get_option( 'brag_book_gallery_stored_pages', [] );
		if ( is_array( $stored_pages ) ) {
			foreach ( $stored_pages as $page_path ) {
				$page_slug = basename( trim( $page_path, '/' ) );
				if ( ! empty( $page_slug ) ) {
					$slugs[] = $page_slug;
				}
			}
		}

		// Remove duplicates and empty values
		$slugs = array_unique( array_filter( $slugs ) );
		
		return $slugs;
	}

	/**
	 * Get real API data for test URL examples
	 *
	 * @since 3.0.0
	 * @return array Array with real procedure and case data for URL examples.
	 */
	private function get_real_api_data(): array {
		// Default fallback values
		$defaults = [
			'procedure_slug' => 'tummy-tuck',
			'procedure_name' => 'Tummy Tuck',
			'case_identifier' => '12345',
			'case_title' => 'Sample Case',
		];

		try {
			// Get API configuration
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

			if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
				return $defaults;
			}

			// Try to get sidebar data which contains procedure information
			if ( class_exists( '\BRAGBookGallery\Includes\Extend\Data_Fetcher' ) ) {
				$sidebar_data = \BRAGBookGallery\Includes\Extend\Data_Fetcher::get_sidebar_data( $api_tokens[0] );
				
				if ( ! empty( $sidebar_data ) && isset( $sidebar_data['data'] ) && is_array( $sidebar_data['data'] ) ) {
					// Extract first procedure from any category for example
					foreach ( $sidebar_data['data'] as $category ) {
						if ( isset( $category['procedures'] ) && is_array( $category['procedures'] ) && ! empty( $category['procedures'] ) ) {
							$first_procedure = reset( $category['procedures'] );
							if ( ! empty( $first_procedure['slug'] ) ) {
								$defaults['procedure_slug'] = $first_procedure['slug'];
								$defaults['procedure_name'] = $first_procedure['name'] ?? $first_procedure['slug'];
								break; // Found a procedure, stop looking
							}
						}
					}
				}
			}

			// Try to get case data for a real case example
			if ( class_exists( '\BRAGBookGallery\Includes\Extend\Data_Fetcher' ) ) {
				$cases_data = \BRAGBookGallery\Includes\Extend\Data_Fetcher::get_cases_from_api( $api_tokens[0], (string) intval( $website_property_ids[0] ), [], 1, 1 );
				
				if ( ! empty( $cases_data ) && isset( $cases_data['data'] ) && is_array( $cases_data['data'] ) && ! empty( $cases_data['data'] ) ) {
					$first_case = reset( $cases_data['data'] );
					
					// Use case ID as identifier
					if ( ! empty( $first_case['id'] ) ) {
						$defaults['case_identifier'] = $first_case['id'];
					}
					
					// Try to get a better case title
					if ( ! empty( $first_case['caseDetails'] ) && is_array( $first_case['caseDetails'] ) ) {
						$first_detail = reset( $first_case['caseDetails'] );
						if ( ! empty( $first_detail['seoHeadline'] ) ) {
							$defaults['case_title'] = $first_detail['seoHeadline'];
						} elseif ( ! empty( $first_detail['seoPageTitle'] ) ) {
							$defaults['case_title'] = $first_detail['seoPageTitle'];
						}
					}
					
					// Use seoSuffixUrl if available for more SEO-friendly URLs
					if ( ! empty( $first_case['seoSuffixUrl'] ) ) {
						$defaults['case_identifier'] = $first_case['seoSuffixUrl'];
					} elseif ( ! empty( $first_case['caseDetails'] ) && is_array( $first_case['caseDetails'] ) ) {
						$first_detail = reset( $first_case['caseDetails'] );
						if ( ! empty( $first_detail['seoSuffixUrl'] ) ) {
							$defaults['case_identifier'] = $first_detail['seoSuffixUrl'];
						}
					}
				}
			}

		} catch ( Exception $e ) {
			// If anything fails, just return defaults
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery Test URLs: Failed to get real API data - ' . $e->getMessage() );
			}
		}

		return $defaults;
	}

	/**
	 * Generate comprehensive URL patterns with all procedures and cases from API
	 *
	 * @since 3.0.0
	 * @param string $site_url The site URL.
	 * @param string $gallery_slug The gallery slug.
	 * @return array Comprehensive array of URL patterns with all procedures and cases.
	 */
	private function generate_comprehensive_url_patterns( string $site_url, string $gallery_slug ): array {
		$url_patterns = [];

		try {
			// Get API configuration
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

			if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
				// Fallback to basic patterns if no API config
				return $this->get_basic_url_patterns( $site_url, $gallery_slug );
			}

			// Add gallery home
			$url_patterns['Gallery Home'] = [
				'pattern' => '{site_url}/{gallery_slug}/',
				'example' => $site_url . '/' . $gallery_slug . '/',
				'description' => __( 'Main gallery page showing all cases with filters', 'brag-book-gallery' )
			];

			// Get sidebar data for all procedures
			if ( class_exists( '\BRAGBookGallery\Includes\Extend\Data_Fetcher' ) ) {
				$sidebar_data = \BRAGBookGallery\Includes\Extend\Data_Fetcher::get_sidebar_data( $api_tokens[0] );
				
				// Debug sidebar data structure
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Debug URL Patterns - Sidebar data structure: ' . print_r( $sidebar_data, true ) );
				}
				
				if ( ! empty( $sidebar_data ) && isset( $sidebar_data['data'] ) && is_array( $sidebar_data['data'] ) ) {
					$procedure_count = 0;
					
					// Add all procedure filter URLs
					foreach ( $sidebar_data['data'] as $category_key => $category ) {
						if ( isset( $category['procedures'] ) && is_array( $category['procedures'] ) ) {
							foreach ( $category['procedures'] as $procedure ) {
								if ( ! empty( $procedure['slug'] ) ) {
									$procedure_count++;
									$url_patterns['Procedure: ' . ( $procedure['name'] ?? $procedure['slug'] )] = [
										'pattern' => '{site_url}/{gallery_slug}/{procedure-slug}/',
										'example' => $site_url . '/' . $gallery_slug . '/' . $procedure['slug'] . '/',
										'description' => sprintf( 
											__( 'Gallery filtered by %s procedure (%d cases)', 'brag-book-gallery' ), 
											$procedure['name'] ?? $procedure['slug'],
											$procedure['caseCount'] ?? 0
										)
									];
								}
							}
						}
					}
					
					// Debug procedure count
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Debug URL Patterns - Found ' . $procedure_count . ' procedures' );
					}
				} else {
					// Debug empty sidebar data
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Debug URL Patterns - No sidebar data or invalid structure' );
					}
				}

				// Get some case examples (first 20 cases to avoid overwhelming the display)
				$cases_data = \BRAGBookGallery\Includes\Extend\Data_Fetcher::get_cases_from_api( 
					$api_tokens[0], 
					(string) intval( $website_property_ids[0] ), 
					[], 
					20, 
					1 
				);
				
				// Debug cases data structure
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Debug URL Patterns - Cases data structure: ' . print_r( $cases_data, true ) );
				}
				
				if ( ! empty( $cases_data ) && isset( $cases_data['data'] ) && is_array( $cases_data['data'] ) ) {
					$case_count = 0;
					
					foreach ( $cases_data['data'] as $case ) {
						if ( ! empty( $case['id'] ) && ! empty( $case['procedureNames'] ) ) {
							$case_count++;
							
							// Get case identifier (prefer seoSuffixUrl)
							$case_identifier = $case['seoSuffixUrl'] ?? $case['id'];
							
							// Get procedure slug from case data
							$procedure_slug = 'unknown';
							if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
								$first_detail = reset( $case['caseDetails'] );
								if ( ! empty( $first_detail['procedureSlug'] ) ) {
									$procedure_slug = $first_detail['procedureSlug'];
								}
							}
							
							// Create case title
							$case_title = sprintf( '%s Case %s', $case['procedureNames'][0] ?? 'Unknown', $case['id'] );
							if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
								$first_detail = reset( $case['caseDetails'] );
								if ( ! empty( $first_detail['seoHeadline'] ) ) {
									$case_title = $first_detail['seoHeadline'];
								}
							}
							
							$url_patterns['Case: ' . $case_title] = [
								'pattern' => '{site_url}/{gallery_slug}/{procedure-slug}/{case-identifier}/',
								'example' => $site_url . '/' . $gallery_slug . '/' . $procedure_slug . '/' . $case_identifier . '/',
								'description' => sprintf( 
									__( 'Individual case detail view for Case ID %s', 'brag-book-gallery' ), 
									$case['id'] 
								)
							];
							
							// Limit to first 20 cases to avoid overwhelming the display
							if ( $case_count >= 20 ) {
								$url_patterns['... and more cases'] = [
									'pattern' => '{site_url}/{gallery_slug}/{procedure-slug}/{case-identifier}/',
									'example' => '... additional case URLs available ...',
									'description' => sprintf( 
										__( 'Total cases available: %d (showing first 20)', 'brag-book-gallery' ),
										$cases_data['totalCount'] ?? 'unknown'
									)
								];
								break;
							}
						}
					}
				}
			}

			// Add favorites page
			$url_patterns['Favorites Page'] = [
				'pattern' => '{site_url}/{gallery_slug}/myfavorites/',
				'example' => $site_url . '/' . $gallery_slug . '/myfavorites/',
				'description' => __( 'User favorites collection (if favorites are enabled)', 'brag-book-gallery' )
			];

		} catch ( Exception $e ) {
			// If anything fails, return basic patterns
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery Test URLs: Failed to generate comprehensive patterns - ' . $e->getMessage() );
			}
			return $this->get_basic_url_patterns( $site_url, $gallery_slug );
		}

		// Debug final URL patterns count
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Debug URL Patterns - Generated ' . count( $url_patterns ) . ' total URL patterns' );
			error_log( 'Debug URL Patterns - Pattern keys: ' . implode( ', ', array_keys( $url_patterns ) ) );
		}

		return $url_patterns;
	}

	/**
	 * Get basic URL patterns as fallback
	 *
	 * @since 3.0.0
	 * @param string $site_url The site URL.
	 * @param string $gallery_slug The gallery slug.
	 * @return array Basic URL patterns.
	 */
	private function get_basic_url_patterns( string $site_url, string $gallery_slug ): array {
		$api_data = $this->get_real_api_data();
		
		return [
			'Gallery Home' => [
				'pattern' => '{site_url}/{gallery_slug}/',
				'example' => $site_url . '/' . $gallery_slug . '/',
				'description' => __( 'Main gallery page showing all cases with filters', 'brag-book-gallery' )
			],
			'Procedure Filter (Example)' => [
				'pattern' => '{site_url}/{gallery_slug}/{procedure-name}/',
				'example' => $site_url . '/' . $gallery_slug . '/' . $api_data['procedure_slug'] . '/',
				'description' => __( 'Gallery filtered by specific procedure', 'brag-book-gallery' ) . ( $api_data['procedure_name'] ? ' (' . $api_data['procedure_name'] . ')' : '' )
			],
			'Case Detail (Example)' => [
				'pattern' => '{site_url}/{gallery_slug}/{procedure-name}/{case-identifier}/',
				'example' => $site_url . '/' . $gallery_slug . '/' . $api_data['procedure_slug'] . '/' . $api_data['case_identifier'] . '/',
				'description' => __( 'Individual case detail view with before/after images', 'brag-book-gallery' ) . ( $api_data['case_title'] ? ' (' . $api_data['case_title'] . ')' : '' )
			],
			'Favorites Page' => [
				'pattern' => '{site_url}/{gallery_slug}/myfavorites/',
				'example' => $site_url . '/' . $gallery_slug . '/myfavorites/',
				'description' => __( 'User favorites collection (if favorites are enabled)', 'brag-book-gallery' )
			],
		];
	}

	/**
	 * Find pages with gallery shortcode
	 *
	 * @since 3.0.0
	 * @return array Array of page objects containing the gallery shortcode.
	 */
	private function find_pages_with_shortcode(): array {
		global $wpdb;

		// Execute database query to find pages with gallery shortcode
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for LIKE query with shortcode detection
		$pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_content
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s
				AND post_content LIKE %s",
				'page',
				'publish',
				'%[brag_book_gallery%'
			)
		);

		return is_array( $pages ) ? $pages : [];
	}

	/**
	 * Generate sample URLs for display.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML list of sample URLs.
	 */
	private function generate_sample_urls(): string {
		$gallery_slugs = $this->get_gallery_slugs();
		$first_slug = ! empty( $gallery_slugs ) ? reset( $gallery_slugs ) : 'gallery';

		// Get real API data for examples
		$api_data = $this->get_real_api_data();

		$urls = [
			__( 'Gallery Home', 'brag-book-gallery' ) => home_url( '/' . $first_slug . '/' ),
			__( 'Procedure Filter', 'brag-book-gallery' ) => home_url( '/' . $first_slug . '/' . $api_data['procedure_slug'] . '/' ),
			__( 'Case Details', 'brag-book-gallery' ) => home_url( '/' . $first_slug . '/' . $api_data['procedure_slug'] . '/' . $api_data['case_identifier'] . '/' ),
			__( 'Favorites', 'brag-book-gallery' ) => home_url( '/' . $first_slug . '/myfavorites/' ),
		];

		$output = '<ul>';
		foreach ( $urls as $label => $url ) {
			$output .= '<li>' . esc_html( $label ) . ': <code>' . esc_html( $url ) . '</code></li>';
		}
		$output .= '</ul>';

		return $output;
	}

	/**
	 * Clear all related caches.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function clear_all_caches(): void {
		// Clear object cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clear transients
		$this->clear_related_transients();

		// Clear any page cache plugins
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// Trigger action for other plugins
		do_action( 'brag_book_gallery_cache_cleared' );
	}

	/**
	 * Clear related transients.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function clear_related_transients(): void {
		$transients = [
			'brag_book_gallery_transient_show_rewrite_notice',
			'brag_book_gallery_rules_check',
			self::CACHE_PREFIX . 'verification',
		];

		foreach ( $transients as $transient ) {
			brag_book_delete_cache( $transient );
		}
	}

	/**
	 * Show admin notice.
	 *
	 * @since 3.0.0
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function show_admin_notice( string $message, string $type = 'info' ): void {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Handle errors with logging.
	 *
	 * @since 3.0.0
	 *
	 * @param Exception $e       Exception to handle.
	 * @param string    $context Context where error occurred.
	 * @return void
	 */
	private function handle_error( Exception $e, string $context ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'BRAGBook Gallery Rewrite Flush Error in %s: %s',
				$context,
				$e->getMessage()
			) );
		}
	}

	/**
	 * Log performance metrics if needed.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function maybe_log_performance(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $this->metrics ) ) {
			$total_time = array_sum( $this->metrics );
			if ( $total_time > 0.5 ) { // Log if operations take more than 0.5 seconds
				error_log( sprintf(
					'BRAGBook Gallery Rewrite Flush Performance: %s',
					wp_json_encode( $this->metrics )
				) );
			}
		}
	}
}
