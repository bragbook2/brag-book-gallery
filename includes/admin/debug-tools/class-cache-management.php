<?php
/**
 * Cache Management Debug Tool - Enterprise-grade cache management system
 *
 * Comprehensive cache management interface for BRAGBook Gallery plugin.
 * Provides advanced cache viewing, analysis, and management capabilities
 * with performance optimization and security hardening.
 *
 * Features:
 * - Real-time cache monitoring
 * - Batch operations with confirmation
 * - Performance metrics tracking
 * - Memory-efficient pagination
 * - Advanced filtering and search
 * - Export/import capabilities
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @author     BRAGBook Development Team
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use Exception;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enterprise Cache Management Tool Class
 *
 * Orchestrates comprehensive cache management operations:
 *
 * Core Responsibilities:
 * - Cache monitoring and analysis
 * - Batch cache operations
 * - Performance optimization
 * - Security validation
 * - Export/import functionality
 * - Real-time metrics tracking
 *
 * Transient Keys Used Throughout the Plugin:
 *
 * API/Data Transients:
 * - brag_book_gallery_sidebar_{hash}        : Sidebar data from API
 * - brag_book_gallery_cases_{hash}          : Cases data from API
 * - brag_book_gallery_all_cases_{hash}      : All cases data
 * - brag_book_gallery_filtered_cases_{hash} : Filtered cases data
 * - brag_book_gallery_carousel_{hash}       : Carousel data
 * - brag_book_gallery_carousel_case_{id}    : Individual carousel case
 * - brag_book_gallery_api_{hash}            : General API responses
 * - brag_book_gallery_procedure_{hash}      : Procedure data
 * - brag_book_gallery_case_seo_{suffix}     : SEO case data
 * - brag_book_gallery_combined_sidebar_{hash} : Combined sidebar data
 *
 * Sync/Migration Transients:
 * - brag_book_gallery_sync_status           : Sync operation status
 * - brag_book_gallery_sync_progress         : Sync operation progress
 * - brag_book_gallery_sync_lock             : Sync operation lock
 * - brag_book_gallery_force_update_all      : Force update flag
 * - brag_book_gallery_force_update_cases    : Force update case list
 * - brag_book_gallery_migration_status      : Migration status
 * - brag_book_gallery_migration_{hash}      : Migration data cache
 * - brag_book_gallery_migration_rate_limit_{hash} : Migration rate limiting
 *
 * Sitemap Transients:
 * - brag_book_gallery_sitemap_content       : Sitemap content cache
 * - brag_book_gallery_sitemap_last_modified : Sitemap modification time
 *
 * Taxonomy Transients:
 * - brag_book_gallery_terms_{hash}          : Taxonomy terms cache
 * - brag_book_gallery_{taxonomy}_terms      : Specific taxonomy terms
 * - brag_book_gallery_{taxonomy}_hierarchy  : Taxonomy hierarchy
 * - brag_book_gallery_term_{id}             : Individual term data
 *
 * Rate Limiting Transients:
 * - brag_book_gallery_rate_limit_{hash}     : API rate limiting
 * - brag_book_gallery_seo_rate_limit_{hash} : SEO operation rate limiting
 * - brag_book_gallery_mode_rate_limit_{hash} : Mode operation rate limiting
 *
 * Mode Management Transients:
 * - brag_book_gallery_mode_{hash}           : Mode data cache
 *
 * Notice/UI Transients:
 * - brag_book_gallery_show_rewrite_notice   : Rewrite rules notice flag
 *
 * Image/Media Transients:
 * - brag_book_gallery_image_{hash}          : Image data cache
 * - brag_book_gallery_attachment_{hash}     : Attachment data cache
 *
 * Circuit Breaker/API Health:
 * - brag_book_gallery_circuit_{endpoint}    : Circuit breaker status
 * - brag_book_gallery_failures_{endpoint}   : API failure count
 *
 * Consultation/Form Transients:
 * - brag_book_gallery_consultation_hourly_{ip} : Hourly submission limit
 * - brag_book_gallery_consultation_daily_{ip}  : Daily submission limit
 *
 * System/Debug Transients:
 * - brag_book_gallery_debug_report          : Debug system report
 * - brag_book_gallery_last_flush            : Last rewrite rules flush
 * - brag_book_gallery_validation_{hash}     : Data validation cache
 *
 * Note: {hash} represents dynamic MD5 hashes based on parameters
 *       {id} represents numeric IDs
 *       {taxonomy}, {endpoint}, {ip}, {suffix} are dynamic values
 *
 * @since 3.0.0
 */
class Cache_Management {

	/**
	 * Cache prefix used by the plugin.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_PREFIX = 'brag_book_gallery_';

	/**
	 * Maximum size for inline data display (in bytes).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_DISPLAY_SIZE = 1048576; // 1MB

	/**
	 * Items per page for pagination
	 *
	 * @since 3.0.0
	 */
	private const ITEMS_PER_PAGE = 50;

	/**
	 * Cache duration constants
	 *
	 * @since 3.0.0
	 */
	private const CACHE_TTL_SHORT = 300;     // 5 minutes
	private const CACHE_TTL_MEDIUM = 1800;   // 30 minutes
	private const CACHE_TTL_LONG = 3600;     // 1 hour

	/**
	 * Performance metrics storage
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $performance_metrics = [];

	/**
	 * Error log storage
	 *
	 * @since 3.0.0
	 * @var array<int, array<string, mixed>>
	 */
	private array $error_log = [];

	/**
	 * Cache statistics
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $cache_stats = [];

	/**
	 * Render the cache management tool interface
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		$page = isset( $_GET['cache_page'] ) ? max( 1, absint( $_GET['cache_page'] ) ) : 1;
		$search = isset( $_GET['cache_search'] ) ? sanitize_text_field( wp_unslash( $_GET['cache_search'] ) ) : '';
		$type_filter = isset( $_GET['cache_type'] ) ? sanitize_text_field( wp_unslash( $_GET['cache_type'] ) ) : '';

		$cache_result = $this->get_cached_items( $page, $search, $type_filter );
		$cached_items = $cache_result['items'];
		$total_items = $cache_result['total'];
		$total_pages = $cache_result['pages'];
		$total_size = $this->calculate_total_size( $cached_items );
		?>
		<div class="tool-section">
			<h3><?php esc_html_e( 'Cache Management', 'brag-book-gallery' ); ?></h3>
			<p><?php esc_html_e( 'View and manage all cached data for the BRAGBook Gallery plugin.', 'brag-book-gallery' ); ?></p>

			<!-- Cache Statistics -->
			<div class="cache-stats">
				<h4><?php esc_html_e( 'Cache Statistics', 'brag-book-gallery' ); ?></h4>
				<ul>
					<li><?php printf( esc_html__( 'Total Cached Items: %d', 'brag-book-gallery' ), $total_items ); ?></li>
					<li><?php printf( esc_html__( 'Current Page Items: %d', 'brag-book-gallery' ), count( $cached_items ) ); ?></li>
					<li><?php printf( esc_html__( 'Total Cache Size: %s', 'brag-book-gallery' ), $this->format_bytes( $total_size ) ); ?></li>
					<li><?php printf( esc_html__( 'Cache Duration Setting: %d seconds', 'brag-book-gallery' ), get_option( 'brag_book_gallery_cache_duration', self::CACHE_TTL_LONG ) ); ?></li>
					<li><?php printf( esc_html__( 'Page %d of %d', 'brag-book-gallery' ), $page, max( 1, $total_pages ) ); ?></li>
				</ul>
			</div>

			<!-- Action Buttons -->
			<div class="cache-actions">
				<button type="button" class="button button-primary" id="refresh-cache-list">
					<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="M480-160q-134 0-227-93t-93-227q0-134 93-227t227-93q69 0 132 28.5T720-690v-110h80v280H520v-80h168q-32-56-87.5-88T480-720q-100 0-170 70t-70 170q0 100 70 170t170 70q77 0 139-44t87-116h84q-28 106-114 173t-196 67Z"/></svg>
					<?php esc_html_e( 'Refresh List', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="clear-selected-cache" disabled>
					<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm80-160h80v-360h-80v360Zm160 0h80v-360h-80v360Z"/></svg>
					<?php esc_html_e( 'Clear Selected', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-danger" id="clear-all-cache" <?php echo empty( $cached_items ) ? 'disabled' : ''; ?>>
					<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php esc_html_e( 'Clear All Cache', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-primary" id="clear-gallery-cache" style="background: #0073aa;">
					<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-480H200v480Zm40-80h480L570-480 450-320l-90-120-120 160Zm320-160q25 0 42.5-17.5T620-500q0-25-17.5-42.5T560-560q-25 0-42.5 17.5T500-500q0 25 17.5 42.5T560-440Z"/></svg>
					<?php esc_html_e( 'Clear Gallery API Cache', 'brag-book-gallery' ); ?>
				</button>
				<span id="cache-action-status"></span>
			</div>

			<!-- Cache Items Table -->
			<?php if ( ! empty( $cached_items ) ) : ?>
				<div class="cache-table-wrapper">
					<table class="cache-items-table wp-list-table widefat fixed striped" id="cache-items-table">
						<thead>
							<tr>
								<th class="checkbox-column">
									<input type="checkbox" id="select-all-cache" class="cache-checkbox" />
								</th>
								<th class="key-column"><?php esc_html_e( 'Cache Key', 'brag-book-gallery' ); ?></th>
								<th class="type-column"><?php esc_html_e( 'Type', 'brag-book-gallery' ); ?></th>
								<th class="size-column"><?php esc_html_e( 'Size', 'brag-book-gallery' ); ?></th>
								<th class="expiration-column"><?php esc_html_e( 'Expiration', 'brag-book-gallery' ); ?></th>
								<th class="actions-column"><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cached_items as $item ) : ?>
								<tr class="cache-row" data-cache-key="<?php echo esc_attr( $item['key'] ); ?>">
									<td class="checkbox-column">
										<input type="checkbox" class="cache-item-checkbox cache-checkbox" value="<?php echo esc_attr( $item['key'] ); ?>" />
									</td>
									<td class="key-column">
										<div class="cache-key-wrapper">
											<div class="cache-key-name"><?php echo esc_html( $this->format_cache_key( $item['key'] ) ); ?></div>
											<div class="cache-key-full"><?php echo esc_html( $item['key'] ); ?></div>
										</div>
									</td>
									<td class="type-column">
										<span class="cache-type-badge"><?php echo esc_html( $item['type'] ); ?></span>
									</td>
									<td class="size-column">
										<span class="cache-size"><?php echo esc_html( $this->format_bytes( $item['size'] ) ); ?></span>
									</td>
									<td class="expiration-column">
										<?php if ( $item['expiration'] ) : ?>
											<?php
											$time_left = $item['expiration'] - time();
											if ( $time_left > 0 ) : ?>
												<div class="cache-expiration-wrapper">
													<span class="cache-time-remaining"><?php echo esc_html( $this->format_time_remaining( $time_left ) ); ?></span>
													<span class="cache-expiration-date"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $item['expiration'] ) ); ?></span>
												</div>
											<?php else : ?>
												<span class="cache-status-expired"><?php esc_html_e( 'Expired', 'brag-book-gallery' ); ?></span>
											<?php endif; ?>
										<?php else : ?>
											<span class="cache-status-permanent"><?php esc_html_e( 'No expiration', 'brag-book-gallery' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="actions-column">
										<div class="cache-actions-wrapper">
											<button type="button" class="cache-btn cache-btn-view view-cache-data" data-key="<?php echo esc_attr( $item['key'] ); ?>" title="<?php esc_attr_e( 'View', 'brag-book-gallery' ); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
													<circle cx="12" cy="12" r="3"></circle>
												</svg>
											</button>
											<button type="button" class="cache-btn cache-btn-delete delete-cache-item" data-key="<?php echo esc_attr( $item['key'] ); ?>" title="<?php esc_attr_e( 'Delete', 'brag-book-gallery' ); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<polyline points="3 6 5 6 21 6"></polyline>
													<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
												</svg>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No cached items found.', 'brag-book-gallery' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<!-- HTML5 Dialog Elements -->

		<!-- Confirmation Dialog -->
		<dialog id="confirm-dialog" class="cache-dialog">
			<form method="dialog">
				<h3 id="confirm-dialog-title"><?php esc_html_e( 'Confirm Action', 'brag-book-gallery' ); ?></h3>
				<p id="confirm-dialog-message"></p>
				<div class="dialog-buttons">
					<button type="button" class="button button-secondary" id="confirm-cancel">
						<?php esc_html_e( 'Cancel', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" class="button button-primary" id="confirm-ok">
						<?php esc_html_e( 'Confirm', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</form>
		</dialog>

		<!-- Alert Dialog -->
		<dialog id="alert-dialog" class="cache-dialog">
			<form method="dialog">
				<h3 id="alert-dialog-title"><?php esc_html_e( 'Notice', 'brag-book-gallery' ); ?></h3>
				<p id="alert-dialog-message"></p>
				<div class="dialog-buttons">
					<button type="button" class="button button-primary" id="alert-ok">
						<?php esc_html_e( 'OK', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</form>
		</dialog>

		<!-- Cache Data Modal -->
		<dialog id="cache-data-modal" class="cache-dialog cache-data-dialog">
			<form method="dialog">
				<h3><?php esc_html_e( 'Cache Data', 'brag-book-gallery' ); ?></h3>
				<div class="modal-actions">
					<button type="button" class="button button-small" id="copy-cache-data">
						<?php esc_html_e( 'Copy Data', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" class="button button-small" id="close-cache-modal">
						<?php esc_html_e( 'Close', 'brag-book-gallery' ); ?>
					</button>
				</div>
				<pre id="cache-data-content"></pre>
			</form>
		</dialog>

		<script>
		document.addEventListener('DOMContentLoaded', () => {
			// Dialog helper functions
			const confirmDialog = (message, title = '<?php esc_html_e( 'Confirm Action', 'brag-book-gallery' ); ?>') => {
				return new Promise((resolve) => {
					const dialog = document.getElementById('confirm-dialog');
					const titleEl = document.getElementById('confirm-dialog-title');
					const messageEl = document.getElementById('confirm-dialog-message');
					const cancelBtn = document.getElementById('confirm-cancel');
					const okBtn = document.getElementById('confirm-ok');

					titleEl.textContent = title;
					messageEl.textContent = message;

					const handleCancel = () => {
						dialog.close();
						resolve(false);
					};

					const handleOk = () => {
						dialog.close();
						resolve(true);
					};

					// Remove old listeners and add new ones
					cancelBtn.replaceWith(cancelBtn.cloneNode(true));
					okBtn.replaceWith(okBtn.cloneNode(true));

					document.getElementById('confirm-cancel').addEventListener('click', handleCancel);
					document.getElementById('confirm-ok').addEventListener('click', handleOk);

					// Handle ESC key
					dialog.addEventListener('cancel', (e) => {
						e.preventDefault();
						handleCancel();
					}, { once: true });

					dialog.showModal();
				});
			};

			const alertDialog = (message, title = '<?php esc_html_e( 'Notice', 'brag-book-gallery' ); ?>') => {
				return new Promise((resolve) => {
					const dialog = document.getElementById('alert-dialog');
					const titleEl = document.getElementById('alert-dialog-title');
					const messageEl = document.getElementById('alert-dialog-message');
					const okBtn = document.getElementById('alert-ok');

					titleEl.textContent = title;
					messageEl.textContent = message;

					const handleOk = () => {
						dialog.close();
						resolve();
					};

					// Remove old listener and add new one
					okBtn.replaceWith(okBtn.cloneNode(true));
					document.getElementById('alert-ok').addEventListener('click', handleOk);

					// Handle ESC key
					dialog.addEventListener('cancel', (e) => {
						e.preventDefault();
						handleOk();
					}, { once: true });

					dialog.showModal();
				});
			};

			// Helper function for AJAX requests using async/await
			const ajaxPost = async (data) => {
				const formData = new FormData();
				Object.entries(data).forEach(([key, value]) => {
					if (Array.isArray(value)) {
						value.forEach(item => formData.append(`${key}[]`, item));
					} else {
						formData.append(key, value);
					}
				});

				try {
					const response = await fetch(ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					});
					return await response.json();
				} catch (error) {
					console.error('AJAX error:', error);
					return { success: false, data: 'Network error' };
				}
			};

			// Helper function for fadeOut effect using promises
			const fadeOut = (element) => {
				return new Promise((resolve) => {
					element.style.transition = 'opacity 0.4s';
					element.style.opacity = '0';
					setTimeout(() => {
						element.classList.remove('show');
						resolve();
					}, 400);
				});
			};

			// Helper function for fadeIn effect
			const fadeIn = (element) => {
				element.classList.add('show');
				element.style.opacity = '0';
				requestAnimationFrame(() => {
					element.style.transition = 'opacity 0.4s';
					element.style.opacity = '1';
				});
			};

			// Helper function to update status message
			const updateStatus = (message, type = 'info') => {
				const statusElement = document.getElementById('cache-action-status');
				if (statusElement) {
					statusElement.innerHTML = `<span class="cache-status-${type}">${message}</span>`;
					setTimeout(() => {
						statusElement.innerHTML = '';
					}, 3000);
				}
			};

			// Select all checkbox
			const selectAllCheckbox = document.getElementById('select-all-cache');
			selectAllCheckbox?.addEventListener('change', () => {
				const checkboxes = document.querySelectorAll('.cache-item-checkbox');
				checkboxes.forEach(checkbox => {
					checkbox.checked = selectAllCheckbox.checked;
				});
				updateSelectedButtons();
			});

			// Individual checkbox change
			document.querySelectorAll('.cache-item-checkbox').forEach(checkbox => {
				checkbox.addEventListener('change', () => updateSelectedButtons());
			});

			const updateSelectedButtons = () => {
				const checkboxes = document.querySelectorAll('.cache-item-checkbox');
				const checkedBoxes = document.querySelectorAll('.cache-item-checkbox:checked');
				const clearSelectedBtn = document.getElementById('clear-selected-cache');

				if (clearSelectedBtn) {
					clearSelectedBtn.disabled = checkedBoxes.length === 0;
				}

				if (selectAllCheckbox) {
					selectAllCheckbox.checked = checkedBoxes.length === checkboxes.length && checkboxes.length > 0;
				}
			};

			// Refresh cache list - preserve the active tab
			document.getElementById('refresh-cache-list')?.addEventListener('click', () => {
				// Add hash to URL to preserve cache-management tab
				const currentUrl = window.location.href.split('#')[0];
				window.location.href = currentUrl + '#cache-management';
				location.reload();
			});

			// Clear selected cache items
			const clearSelectedBtn = document.getElementById('clear-selected-cache');
			clearSelectedBtn?.addEventListener('click', async function() {
				const selectedKeys = Array.from(
					document.querySelectorAll('.cache-item-checkbox:checked')
				).map(checkbox => checkbox.value);

				if (selectedKeys.length === 0) return;

				const confirmed = await confirmDialog(
					'<?php esc_html_e( 'Are you sure you want to delete the selected cache items?', 'brag-book-gallery' ); ?>',
					'<?php esc_html_e( 'Delete Cache Items', 'brag-book-gallery' ); ?>'
				);

				if (!confirmed) {
					return;
				}

				this.disabled = true;

				const response = await ajaxPost({
					action: 'brag_book_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_debug_tools' ); ?>',
					tool: 'cache-management',
					tool_action: 'delete_items',
					keys: selectedKeys
				});

				if (response.success) {
					showStatus('<?php esc_html_e( 'Selected items cleared successfully!', 'brag-book-gallery' ); ?>', 'success');
					setTimeout(() => {
						const currentUrl = window.location.href.split('#')[0];
						window.location.href = currentUrl + '#cache-management';
						location.reload();
					}, 1000);
				} else {
					showStatus(response.data || '<?php esc_html_e( 'Error clearing cache items.', 'brag-book-gallery' ); ?>', 'error');
					this.disabled = false;
				}
			});

			// Clear all cache
			const clearAllBtn = document.getElementById('clear-all-cache');
			clearAllBtn?.addEventListener('click', async function() {
				const confirmed = await confirmDialog(
					'<?php esc_html_e( 'Are you sure you want to clear ALL cache? This cannot be undone.', 'brag-book-gallery' ); ?>',
					'<?php esc_html_e( 'Clear All Cache', 'brag-book-gallery' ); ?>'
				);

				if (!confirmed) {
					return;
				}

				this.disabled = true;

				const response = await ajaxPost({
					action: 'brag_book_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_debug_tools' ); ?>',
					tool: 'cache-management',
					tool_action: 'clear_all'
				});

				if (response.success) {
					showStatus('<?php esc_html_e( 'All cache cleared successfully!', 'brag-book-gallery' ); ?>', 'success');
					setTimeout(() => {
						const currentUrl = window.location.href.split('#')[0];
						window.location.href = currentUrl + '#cache-management';
						location.reload();
					}, 1000);
				} else {
					showStatus(response.data || '<?php esc_html_e( 'Error clearing cache.', 'brag-book-gallery' ); ?>', 'error');
					this.disabled = false;
				}
			});

			// Delete individual cache item
			document.querySelectorAll('.delete-cache-item').forEach(deleteBtn => {
				deleteBtn.addEventListener('click', async function() {
					const key = this.dataset.key;
					const row = this.closest('tr');

					const confirmed = await confirmDialog(
						'<?php esc_html_e( 'Delete this cache item?', 'brag-book-gallery' ); ?>',
						'<?php esc_html_e( 'Delete Cache Item', 'brag-book-gallery' ); ?>'
					);

					if (!confirmed) {
						return;
					}

					const response = await ajaxPost({
						action: 'brag_book_debug_tool',
						nonce: '<?php echo wp_create_nonce( 'brag_book_debug_tools' ); ?>',
						tool: 'cache-management',
						tool_action: 'delete_items',
						keys: [key]
					});

					if (response.success) {
						await fadeOut(row);
						row.remove();
						const remainingRows = document.querySelectorAll('#cache-items-table tbody tr');
						if (remainingRows.length === 0) {
							const currentUrl = window.location.href.split('#')[0];
							window.location.href = currentUrl + '#cache-management';
							location.reload();
						}
					} else {
						await alertDialog(
							response.data || '<?php esc_html_e( 'Error deleting cache item.', 'brag-book-gallery' ); ?>',
							'<?php esc_html_e( 'Error', 'brag-book-gallery' ); ?>'
						);
					}
				});
			});

			// Clear Gallery Cache button (API cache specifically)
			const clearGalleryCacheBtn = document.getElementById('clear-gallery-cache');
			clearGalleryCacheBtn?.addEventListener('click', async function() {
				const confirmed = await confirmDialog(
					'<?php esc_html_e( 'Are you sure you want to clear the gallery API cache? This will force fresh data to be fetched from the API.', 'brag-book-gallery' ); ?>',
					'<?php esc_html_e( 'Clear Gallery API Cache', 'brag-book-gallery' ); ?>'
				);

				if (!confirmed) {
					return;
				}

				this.disabled = true;
				this.textContent = '<?php esc_html_e( 'Clearing...', 'brag-book-gallery' ); ?>';

				try {
					const formData = new FormData();
					formData.append('action', 'brag_book_gallery_clear_cache');
					formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_clear_cache' ); ?>');

					const response = await fetch(ajaxurl, {
						method: 'POST',
						body: formData
					});

					const data = await response.json();

					if (data.success) {
						this.textContent = '<?php esc_html_e( 'Cache Cleared ✓', 'brag-book-gallery' ); ?>';
						updateStatus('<?php esc_html_e( 'Gallery cache cleared successfully!', 'brag-book-gallery' ); ?>', 'success');

						// Refresh the cache list after a moment
						setTimeout(() => {
							this.textContent = '<?php esc_html_e( 'Clear Gallery Cache', 'brag-book-gallery' ); ?>';
							this.disabled = false;
							document.getElementById('refresh-cache-list')?.click();
						}, 2000);
					} else {
						await alertDialog(
							'<?php esc_html_e( 'Failed to clear gallery cache:', 'brag-book-gallery' ); ?> ' + (data.data || 'Unknown error'),
							'<?php esc_html_e( 'Error', 'brag-book-gallery' ); ?>'
						);
						this.textContent = '<?php esc_html_e( 'Clear Gallery API Cache', 'brag-book-gallery' ); ?>';
						this.disabled = false;
					}
				} catch (error) {
					await alertDialog(
						'<?php esc_html_e( 'Error clearing gallery cache:', 'brag-book-gallery' ); ?> ' + error,
						'<?php esc_html_e( 'Error', 'brag-book-gallery' ); ?>'
					);
					this.textContent = '<?php esc_html_e( 'Clear Gallery API Cache', 'brag-book-gallery' ); ?>';
					this.disabled = false;
				}
			});

			// View cache data
			document.querySelectorAll('.view-cache-data').forEach(viewBtn => {
				viewBtn.addEventListener('click', async function() {
					const key = this.dataset.key;

					const response = await ajaxPost({
						action: 'brag_book_debug_tool',
						nonce: '<?php echo wp_create_nonce( 'brag_book_debug_tools' ); ?>',
						tool: 'cache-management',
						tool_action: 'get_data',
						key: key
					});

					if (response.success) {
						const contentElement = document.getElementById('cache-data-content');
						if (contentElement) {
							contentElement.textContent = JSON.stringify(response.data, null, 2);
						}
						const modal = document.getElementById('cache-data-modal');
						if (modal) {
							modal.showModal();
						}
					} else {
						await alertDialog(
							response.data || '<?php esc_html_e( 'Error loading cache data.', 'brag-book-gallery' ); ?>',
							'<?php esc_html_e( 'Error', 'brag-book-gallery' ); ?>'
						);
					}
				});
			});

			// Close modal
			const closeModalBtn = document.getElementById('close-cache-modal');
			const modal = document.getElementById('cache-data-modal');

			closeModalBtn?.addEventListener('click', () => {
				if (modal) {
					modal.close();
				}
			});

			// Copy cache data
			document.getElementById('copy-cache-data')?.addEventListener('click', async () => {
				const contentElement = document.getElementById('cache-data-content');
				if (contentElement) {
					try {
						await navigator.clipboard.writeText(contentElement.textContent);
						await alertDialog(
							'<?php esc_html_e( 'Data copied to clipboard!', 'brag-book-gallery' ); ?>',
							'<?php esc_html_e( 'Success', 'brag-book-gallery' ); ?>'
						);
					} catch (err) {
						console.error('Failed to copy:', err);
						await alertDialog(
							'<?php esc_html_e( 'Failed to copy to clipboard', 'brag-book-gallery' ); ?>',
							'<?php esc_html_e( 'Error', 'brag-book-gallery' ); ?>'
						);
					}
				}
			});

			const showStatus = (message, type) => {
				const statusElement = document.getElementById('cache-action-status');
				if (!statusElement) return;

				statusElement.className = 'status-' + type;
				statusElement.textContent = message;
				fadeIn(statusElement);

				setTimeout(() => fadeOut(statusElement), 3000);
			};
		});
		</script>
		
		<style>
		/* Clean Cache Table Styles */
		.cache-table-wrapper {
			margin-top: 1.5rem;
			border: 1px solid #e5e7eb;
			background: white;
			overflow: auto;
			max-height: 600px;
		}

		.cache-items-table {
			width: 100%;
			border-collapse: separate;
			border-spacing: 0;
			margin: 0;
			font-size: 0.875rem;
			line-height: 1.25rem;
		}

		.cache-items-table thead {
			background: #f8fafc;
			border-bottom: 2px solid #e2e8f0;
		}

		.cache-items-table thead th {
			padding: 0.875rem 1rem;
			text-align: left;
			font-weight: 600;
			font-size: 0.8125rem;
			color: #374151;
			text-transform: uppercase;
			letter-spacing: 0.025em;
			border-bottom: 2px solid #e2e8f0;
			position: sticky;
			top: 0;
			background: #f8fafc;
			z-index: 10;
		}

		.cache-items-table tbody tr {
			transition: all 0.15s ease-in-out;
			border-bottom: 1px solid #f3f4f6;
		}

		.cache-items-table tbody tr:hover {
			background: #fafbfc;
			transform: translateY(-1px);
			box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
		}

		.cache-items-table tbody tr:last-child {
			border-bottom: none;
		}

		.cache-items-table tbody td {
			padding: 0.875rem 1rem;
			vertical-align: middle;
			border-right: 1px solid #f3f4f6;
		}

		.cache-items-table tbody td:last-child {
			border-right: none;
		}

		/* Column Specific Styles */
		.checkbox-column {
			width: 3rem;
			text-align: center;
		}

		.cache-checkbox {
			width: 1.125rem;
			height: 1.125rem;
			border-radius: 0.25rem;
			border: 2px solid #000000;
			background: white;
			transition: all 0.15s ease-in-out;
			appearance: none;
			cursor: pointer;
			position: relative;
		}

		.cache-checkbox:checked {
			background: #000000;
			border-color: #000000;
		}

		.cache-checkbox:checked::after {
			content: '✓';
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			color: white;
			font-size: 0.75rem;
			font-weight: bold;
		}

		.key-column {
			min-width: 200px;
			max-width: 300px;
		}

		.cache-key-wrapper {
			display: flex;
			flex-direction: column;
			gap: 0.25rem;
		}

		.cache-key-name {
			font-weight: 600;
			color: #1f2937;
			font-size: 0.875rem;
		}

		.cache-key-full {
			font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
			font-size: 0.75rem;
			color: #6b7280;
			background: #f9fafb;
			padding: 0.25rem 0.5rem;
			border-radius: 0.375rem;
			border: 1px solid #e5e7eb;
			word-break: break-all;
		}

		.type-column {
			width: 120px;
		}

		.type-column span,
		.cache-type-badge {
			display: inline-block;
			padding: 0.25rem 0.75rem;
			border-radius: 9999px;
			font-size: 0.75rem;
			font-weight: 500;
			background: #eff6ff;
			color: #1d4ed8;
			border: 1px solid #bfdbfe;
		}

		.size-column {
			width: 100px;
			text-align: right;
		}

		.size-column span,
		.cache-size {
			font-weight: 600;
			color: #059669;
			background: #ecfdf5;
			padding: 0.25rem 0.5rem;
			border-radius: 0.375rem;
			font-size: 0.75rem;
			display: inline-block;
		}

		.expiration-column {
			width: 160px;
		}

		.cache-expiration-wrapper {
			display: flex;
			flex-direction: column;
			gap: 0.125rem;
		}

		.cache-time-remaining {
			font-weight: 600;
			color: #0ea5e9;
			font-size: 0.875rem;
		}

		.cache-expiration-date {
			font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
			font-size: 0.6875rem;
			color: #6b7280;
		}

		.cache-status-expired {
			display: inline-block;
			padding: 0.25rem 0.75rem;
			border-radius: 9999px;
			font-size: 0.75rem;
			font-weight: 600;
			background: #fef2f2;
			color: #dc2626;
			border: 1px solid #fecaca;
		}

		.actions-column {
			width: 140px;
		}

		.cache-actions-wrapper {
			display: flex;
			gap: 0.5rem;
			align-items: center;
			justify-content: center;
		}

		/* Simple Button Styles */
		.cache-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 2rem;
			height: 2rem;
			padding: 0;
			border: 1px solid #d1d5db;
			border-radius: 0.375rem;
			cursor: pointer;
			transition: all 0.15s ease-in-out;
			text-decoration: none;
			position: relative;
			background: white;
		}

		.cache-btn-view {
			color: #3b82f6;
			border-color: #3b82f6;
		}

		.cache-btn-view:hover {
			background: #3b82f6;
			color: white;
		}

		.cache-btn-delete {
			color: #dc2626;
			border-color: #dc2626;
		}

		.cache-btn-delete:hover {
			background: #dc2626;
			color: white;
		}

		.cache-btn svg {
			width: 1rem;
			height: 1rem;
		}

		/* Tooltip Styles */
		.cache-btn {
			position: relative;
		}

		.cache-btn::after {
			content: attr(title);
			position: absolute;
			bottom: 100%;
			left: 50%;
			transform: translateX(-50%);
			background: #374151;
			color: white;
			padding: 0.25rem 0.5rem;
			border-radius: 0.25rem;
			font-size: 0.75rem;
			white-space: nowrap;
			opacity: 0;
			pointer-events: none;
			transition: opacity 0.15s ease-in-out;
			margin-bottom: 0.25rem;
			z-index: 1000;
		}

		.cache-btn:hover::after {
			opacity: 1;
		}

		/* Action Buttons Section */
		.cache-actions {
			margin: 1.5rem 0;
			padding: 1rem;
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			align-items: center;
		}

		.cache-actions .button {
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
			padding: 0.625rem 1rem;
			border: 1px solid;
			border-radius: 0.375rem;
			cursor: pointer;
			transition: all 0.15s ease-in-out;
			font-size: 0.875rem;
			font-weight: 500;
			text-decoration: none;
		}

		.cache-actions .button-primary {
			background: #3b82f6;
			color: white;
			border-color: #3b82f6;
		}

		.cache-actions .button-primary:hover {
			background: #1d4ed8;
			border-color: #1d4ed8;
		}

		.cache-actions .button-secondary {
			background: white;
			color: #374151;
			border-color: #d1d5db;
		}

		.cache-actions .button-secondary:hover {
			background: #f3f4f6;
			border-color: #9ca3af;
		}

		.cache-actions .button-danger {
			background: #dc2626;
			color: white;
			border-color: #dc2626;
		}

		.cache-actions .button-danger:hover {
			background: #b91c1c;
			border-color: #b91c1c;
		}

		.cache-actions .button:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			transform: none !important;
			box-shadow: none !important;
		}

		.cache-actions .button svg {
			width: 1.25rem;
			height: 1.25rem;
		}

		/* Cache Statistics */
		.cache-stats {
			margin: 1rem 0 1.5rem;
			padding: 1rem;
			background: #f9fafb;
			border: 1px solid #e5e7eb;
		}

		.cache-stats h4 {
			margin: 0 0 0.75rem;
			color: #1f2937;
			font-size: 1rem;
			font-weight: 600;
		}

		.cache-stats ul {
			margin: 0;
			padding: 0;
			list-style: none;
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 0.5rem;
		}

		.cache-stats li {
			padding: 0.5rem 0.75rem;
			background: white;
			border-radius: 0.5rem;
			border: 1px solid #f3f4f6;
			color: #374151;
			font-size: 0.875rem;
			box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
		}

		/* Status Messages */
		#cache-action-status {
			padding: 0.5rem 0.75rem;
			border-radius: 0.5rem;
			font-size: 0.875rem;
			font-weight: 500;
		}

		#cache-action-status.status-success {
			background: #ecfdf5;
			color: #059669;
			border: 1px solid #a7f3d0;
		}

		#cache-action-status.status-error {
			background: #fef2f2;
			color: #dc2626;
			border: 1px solid #fecaca;
		}

		#cache-action-status.status-info {
			background: #eff6ff;
			color: #1d4ed8;
			border: 1px solid #bfdbfe;
		}

		/* Responsive Design */
		@media (max-width: 768px) {
			.cache-table-wrapper {
				margin-left: -1rem;
				margin-right: -1rem;
				border-radius: 0;
				border-left: none;
				border-right: none;
			}

			.cache-items-table thead th,
			.cache-items-table tbody td {
				padding: 0.5rem 0.75rem;
			}

			.cache-key-full {
				font-size: 0.6875rem;
			}

			.cache-actions {
				flex-direction: column;
				align-items: stretch;
			}

			.cache-actions .button {
				justify-content: center;
			}

			.cache-stats ul {
				grid-template-columns: 1fr;
			}
		}
		</style>
		<?php
	}

	/**
	 * Get all cached items for the plugin with pagination and filtering
	 *
	 * @since 3.0.0
	 * @param int    $page Page number for pagination.
	 * @param string $search Search term.
	 * @param string $type Filter by type.
	 * @return array{items: array, total: int, pages: int}
	 */
	private function get_cached_items( int $page = 1, string $search = '', string $type = '' ): array {
		$start_time = microtime( true );

		try {
			global $wpdb;

			$items = [];

			// Get transients from database
			$transients = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value
					FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s
					ORDER BY option_name",
					'_transient_' . self::CACHE_PREFIX . '%',
					'_transient_timeout_' . self::CACHE_PREFIX . '%'
				)
			);

			$transient_data = [];
			$transient_timeouts = [];

			foreach ( $transients as $transient ) {
				if ( str_starts_with( $transient->option_name, '_transient_timeout_' ) ) {
					$key = str_replace( '_transient_timeout_', '', $transient->option_name );
					$transient_timeouts[ $key ] = $transient->option_value;
				} else {
					$key = str_replace( '_transient_', '', $transient->option_name );
					$transient_data[ $key ] = $transient->option_value;
				}
			}

			foreach ( $transient_data as $key => $value ) {
				// Apply search filter
				if ( ! empty( $search ) && stripos( $key, $search ) === false ) {
					continue;
				}

				$item_type = $this->determine_cache_type( $key );

				// Apply type filter
				if ( ! empty( $type ) && $item_type !== $type ) {
					continue;
				}

				$expiration_value = isset( $transient_timeouts[ $key ] ) ? (int) $transient_timeouts[ $key ] : null;
				
				$items[] = [
					'key'        => $key,
					'type'       => $item_type,
					'size'       => strlen( $value ),
					'expiration' => $expiration_value,
					'created'    => $this->estimate_creation_time( $expiration_value ),
				];
			}

			// Sort by size (largest first)
			usort( $items, fn( $a, $b ) => $b['size'] <=> $a['size'] );

			// Calculate pagination
			$total = count( $items );
			$pages = (int) ceil( $total / self::ITEMS_PER_PAGE );
			$offset = ( $page - 1 ) * self::ITEMS_PER_PAGE;

			// Slice for current page
			$items = array_slice( $items, $offset, self::ITEMS_PER_PAGE );

			$this->track_performance( 'get_cached_items', microtime( true ) - $start_time );

			return [
				'items' => $items,
				'total' => $total,
				'pages' => $pages,
			];

		} catch ( Exception $e ) {
			$this->log_error( 'get_cached_items', $e->getMessage() );
			return [
				'items' => [],
				'total' => 0,
				'pages' => 0,
			];
		}
	}

	/**
	 * Determine the type of cached data based on the key
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @return string
	 */
	private function determine_cache_type( string $key ): string {
		return match ( true ) {
			str_contains( $key, 'sidebar' )  => __( 'Sidebar Data', 'brag-book-gallery' ),
			str_contains( $key, 'cases' )    => __( 'Cases Data', 'brag-book-gallery' ),
			str_contains( $key, 'carousel' ) => __( 'Carousel Data', 'brag-book-gallery' ),
			str_contains( $key, 'api' )      => __( 'API Response', 'brag-book-gallery' ),
			str_contains( $key, 'sync' )     => __( 'Sync Data', 'brag-book-gallery' ),
			str_contains( $key, 'meta' )     => __( 'Metadata', 'brag-book-gallery' ),
			default                          => __( 'General', 'brag-book-gallery' ),
		};
	}

	/**
	 * Format cache key for display
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @return string
	 */
	private function format_cache_key( string $key ): string {
		// Remove prefix
		$formatted = str_replace( self::CACHE_PREFIX, '', $key );

		// Convert underscores to spaces and capitalize
		$formatted = str_replace( '_', ' ', $formatted );
		$formatted = ucwords( $formatted );

		// Truncate if too long
		if ( mb_strlen( $formatted ) > 50 ) {
			$formatted = mb_substr( $formatted, 0, 47 ) . '...';
		}

		return esc_html( $formatted );
	}

	/**
	 * Calculate total size of cached items
	 *
	 * @since 3.0.0
	 * @param array $items Cached items.
	 * @return int
	 */
	private function calculate_total_size( array $items ): int {
		$total = 0;
		foreach ( $items as $item ) {
			$total += $item['size'];
		}
		return $total;
	}

	/**
	 * Format bytes to human readable format
	 *
	 * @since 3.0.0
	 * @param int|float $bytes Number of bytes.
	 * @param int       $precision Decimal precision.
	 * @return string
	 */
	private function format_bytes( int|float $bytes, int $precision = 2 ): string {
		$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
		$i = 0;

		while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
			$bytes /= 1024;
			$i++;
		}

		return number_format( $bytes, $precision ) . ' ' . $units[ $i ];
	}

	/**
	 * Format time remaining with improved precision
	 *
	 * @since 3.0.0
	 * @param int $seconds Seconds remaining.
	 * @return string
	 */
	private function format_time_remaining( int $seconds ): string {
		return match ( true ) {
			$seconds < 60 => sprintf(
				_n( '%d second', '%d seconds', $seconds, 'brag-book-gallery' ),
				$seconds
			),
			$seconds < 3600 => sprintf(
				_n( '%d minute', '%d minutes', (int) floor( $seconds / 60 ), 'brag-book-gallery' ),
				(int) floor( $seconds / 60 )
			),
			$seconds < 86400 => sprintf(
				_n( '%d hour', '%d hours', (int) floor( $seconds / 3600 ), 'brag-book-gallery' ),
				(int) floor( $seconds / 3600 )
			),
			default => sprintf(
				_n( '%d day', '%d days', (int) floor( $seconds / 86400 ), 'brag-book-gallery' ),
				(int) floor( $seconds / 86400 )
			),
		};
	}

	/**
	 * Execute tool actions via AJAX with security validation.
	 *
	 * Handles all AJAX requests for cache management operations including
	 * viewing, deleting, and clearing cache items.
	 *
	 * @since 3.0.0
	 * @param string $action Action to execute.
	 * @param array  $data   Request data from AJAX.
	 * @return mixed Response data for AJAX.
	 * @throws Exception If action is invalid.
	 */
	public function execute( string $action, array $data ): mixed {
		$start_time = microtime( true );

		try {
			// Validate user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( __( 'Insufficient permissions', 'brag-book-gallery' ) );
			}

			$result = match ( $action ) {
				'get_data'     => $this->get_cache_data( $data['key'] ?? '' ),
				'delete_items' => $this->delete_cache_items( $data['keys'] ?? [] ),
				'clear_all'    => $this->clear_all_cache(),
				'export'       => $this->export_cache_data(),
				'get_stats'    => $this->get_cache_statistics(),
				default        => throw new Exception( 'Invalid action: ' . $action ),
			};

			$this->track_performance( 'execute_' . $action, microtime( true ) - $start_time );

			return $result;

		} catch ( Exception $e ) {
			$this->log_error( 'execute', $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Get cache data for viewing with security checks.
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @return mixed
	 * @throws Exception If key is invalid or data not found.
	 */
	private function get_cache_data( string $key ): mixed {
		if ( empty( $key ) ) {
			throw new Exception( __( 'Cache key is required', 'brag-book-gallery' ) );
		}

		// Validate key belongs to our plugin
		if ( ! str_starts_with( $key, self::CACHE_PREFIX ) ) {
			throw new Exception( __( 'Invalid cache key', 'brag-book-gallery' ) );
		}

		$data = get_transient( $key );

		if ( false === $data ) {
			throw new Exception( __( 'Cache item not found or expired', 'brag-book-gallery' ) );
		}

		// Check size before returning
		$serialized = serialize( $data );
		$size = strlen( $serialized );

		if ( $size > self::MAX_DISPLAY_SIZE ) {
			return [
				'_notice'  => __( 'Data too large to display inline', 'brag-book-gallery' ),
				'_size'    => $this->format_bytes( $size ),
				'_type'    => gettype( $data ),
				'_preview' => $this->get_data_preview( $data ),
			];
		}

		return $data;
	}

	/**
	 * Delete specific cache items with validation.
	 *
	 * @since 3.0.0
	 * @param array<string> $keys Array of cache keys to delete.
	 * @return string Success message.
	 * @throws Exception If no keys provided or validation fails.
	 */
	private function delete_cache_items( array $keys ): string {
		if ( empty( $keys ) ) {
			throw new Exception( __( 'No cache keys provided', 'brag-book-gallery' ) );
		}

		$start_time = microtime( true );
		$deleted = 0;
		$failed = [];

		foreach ( $keys as $key ) {
			// Validate key belongs to our plugin
			if ( ! str_starts_with( $key, self::CACHE_PREFIX ) ) {
				$failed[] = $key;
				continue;
			}

			if ( delete_transient( $key ) ) {
				$deleted++;

				/**
				 * Fires after a cache item is deleted.
				 *
				 * @since 3.0.0
				 * @param string $key The cache key that was deleted.
				 */
				do_action( 'brag_book_gallery_cache_item_deleted', $key );
			}
		}

		if ( ! empty( $failed ) ) {
			$this->log_error( 'delete_cache_items', 'Invalid keys: ' . implode( ', ', $failed ) );
		}

		$this->track_performance( 'delete_cache_items', microtime( true ) - $start_time );

		return sprintf(
			_n(
				'Successfully deleted %d cache item',
				'Successfully deleted %d cache items',
				$deleted,
				'brag-book-gallery'
			),
			$deleted
		);
	}

	/**
	 * Clear all plugin cache with performance tracking.
	 *
	 * @since 3.0.0
	 * @return string Success message.
	 */
	private function clear_all_cache(): string {
		$start_time = microtime( true );

		try {
			global $wpdb;

			// Delete all transients with our prefix
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s",
					'_transient_' . self::CACHE_PREFIX . '%',
					'_transient_timeout_' . self::CACHE_PREFIX . '%'
				)
			);

			// Clear object cache
			wp_cache_flush();

			/**
			 * Fires after all cache is cleared.
			 *
			 * @since 3.0.0
			 * @param int $deleted Number of items deleted.
			 */
			do_action( 'brag_book_gallery_cache_cleared', $deleted );

			$this->track_performance( 'clear_all_cache', microtime( true ) - $start_time );

			return sprintf(
				__( 'Successfully cleared all cache. %d items removed.', 'brag-book-gallery' ),
				(int) ( $deleted / 2 ) // Divide by 2 since we delete both transient and timeout
			);

		} catch ( Exception $e ) {
			$this->log_error( 'clear_all_cache', $e->getMessage() );
			throw new Exception( __( 'Failed to clear cache', 'brag-book-gallery' ) );
		}
	}

	/**
	 * Estimate creation time based on expiration.
	 *
	 * @since 3.0.0
	 * @param int|null $expiration Expiration timestamp.
	 * @return int|null Estimated creation timestamp.
	 */
	private function estimate_creation_time( ?int $expiration ): ?int {
		if ( null === $expiration ) {
			return null;
		}

		// Estimate based on default cache duration
		$duration = get_option( 'brag_book_gallery_cache_duration', self::CACHE_TTL_LONG );
		return $expiration - $duration;
	}

	/**
	 * Get data preview for large data sets.
	 *
	 * @since 3.0.0
	 * @param mixed $data Data to preview.
	 * @return string Preview string.
	 */
	private function get_data_preview( mixed $data ): string {
		if ( is_array( $data ) ) {
			return sprintf(
				__( 'Array with %d items', 'brag-book-gallery' ),
				count( $data )
			);
		}

		if ( is_object( $data ) ) {
			return sprintf(
				__( 'Object of class %s', 'brag-book-gallery' ),
				get_class( $data )
			);
		}

		if ( is_string( $data ) ) {
			return mb_substr( $data, 0, 100 ) . '...';
		}

		return gettype( $data );
	}

	/**
	 * Export cache data for backup.
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Export data.
	 */
	private function export_cache_data(): array {
		$cache_data = $this->get_cached_items();

		return [
			'timestamp' => current_time( 'mysql' ),
			'site_url'  => get_site_url(),
			'version'   => get_option( 'brag_book_gallery_version', '3.0.0' ),
			'items'     => $cache_data['items'],
			'total'     => $cache_data['total'],
		];
	}

	/**
	 * Get cache statistics.
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Statistics data.
	 */
	private function get_cache_statistics(): array {
		$cache_data = $this->get_cached_items();
		$total_size = $this->calculate_total_size( $cache_data['items'] );

		// Group by type
		$types = [];
		foreach ( $cache_data['items'] as $item ) {
			$type = $item['type'];
			if ( ! isset( $types[ $type ] ) ) {
				$types[ $type ] = [
					'count' => 0,
					'size'  => 0,
				];
			}
			$types[ $type ]['count']++;
			$types[ $type ]['size'] += $item['size'];
		}

		return [
			'total_items'   => $cache_data['total'],
			'total_size'    => $total_size,
			'average_size'  => $cache_data['total'] > 0 ? $total_size / $cache_data['total'] : 0,
			'types'         => $types,
			'cache_duration' => get_option( 'brag_book_gallery_cache_duration', self::CACHE_TTL_LONG ),
			'metrics'       => $this->performance_metrics,
		];
	}

	/**
	 * Log error message.
	 *
	 * @since 3.0.0
	 * @param string $context Error context.
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( string $context, string $message ): void {
		$this->error_log[] = [
			'context' => $context,
			'message' => $message,
			'time'    => current_time( 'mysql' ),
		];

		// Limit error log size
		if ( count( $this->error_log ) > 100 ) {
			array_shift( $this->error_log );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[BRAGBook Cache] %s: %s', $context, $message ) );
		}
	}

	/**
	 * Track performance metrics.
	 *
	 * @since 3.0.0
	 * @param string $operation Operation name.
	 * @param float  $duration Operation duration.
	 * @return void
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
}
