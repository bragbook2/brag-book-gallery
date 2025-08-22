<?php
/**
 * Cache Management Debug Tool
 *
 * Provides comprehensive cache management interface for BRAGBook Gallery plugin.
 * Allows viewing, searching, and clearing of transient cache data.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @author     BRAGBook Development Team
 */

namespace BRAGBookGallery\Admin\Debug_Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache Management Tool Class
 *
 * Handles all cache-related debug operations including viewing, deleting,
 * and analyzing cached transient data.
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
	 * Render the cache management tool interface
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		$cached_items = $this->get_cached_items();
		$total_size = $this->calculate_total_size( $cached_items );
		?>
		<div class="tool-section">
			<h3><?php esc_html_e( 'Cache Management', 'brag-book-gallery' ); ?></h3>
			<p><?php esc_html_e( 'View and manage all cached data for the BRAGBook Gallery plugin.', 'brag-book-gallery' ); ?></p>

			<!-- Cache Statistics -->
			<div class="cache-stats">
				<h4><?php esc_html_e( 'Cache Statistics', 'brag-book-gallery' ); ?></h4>
				<ul>
					<li><?php printf( esc_html__( 'Total Cached Items: %d', 'brag-book-gallery' ), count( $cached_items ) ); ?></li>
					<li><?php printf( esc_html__( 'Total Cache Size: %s', 'brag-book-gallery' ), $this->format_bytes( $total_size ) ); ?></li>
					<li><?php printf( esc_html__( 'Cache Duration Setting: %d seconds', 'brag-book-gallery' ), get_option( 'brag_book_gallery_cache_duration', 3600 ) ); ?></li>
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
					<table class="cache-items-table" id="cache-items-table">
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
											<button type="button" class="cache-btn cache-btn-view view-cache-data" data-key="<?php echo esc_attr( $item['key'] ); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
													<circle cx="12" cy="12" r="3"></circle>
												</svg>
												<span><?php esc_html_e( 'View', 'brag-book-gallery' ); ?></span>
											</button>
											<button type="button" class="cache-btn cache-btn-delete delete-cache-item" data-key="<?php echo esc_attr( $item['key'] ); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<polyline points="3 6 5 6 21 6"></polyline>
													<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
												</svg>
												<span><?php esc_html_e( 'Delete', 'brag-book-gallery' ); ?></span>
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

			// Refresh cache list
			document.getElementById('refresh-cache-list')?.addEventListener('click', () => location.reload());

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
					setTimeout(() => location.reload(), 1000);
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
					setTimeout(() => location.reload(), 1000);
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
		<?php
	}

	/**
	 * Get all cached items for the plugin
	 *
	 * @since 3.0.0
	 * @return array
	 */
	private function get_cached_items(): array {
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
			if ( strpos( $transient->option_name, '_transient_timeout_' ) === 0 ) {
				$key = str_replace( '_transient_timeout_', '', $transient->option_name );
				$transient_timeouts[ $key ] = $transient->option_value;
			} else {
				$key = str_replace( '_transient_', '', $transient->option_name );
				$transient_data[ $key ] = $transient->option_value;
			}
		}

		foreach ( $transient_data as $key => $value ) {
			$type = $this->determine_cache_type( $key );
			$items[] = [
				'key'        => $key,
				'type'       => $type,
				'size'       => strlen( $value ),
				'expiration' => isset( $transient_timeouts[ $key ] ) ? intval( $transient_timeouts[ $key ] ) : null,
			];
		}

		return $items;
	}

	/**
	 * Determine the type of cached data based on the key
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @return string
	 */
	private function determine_cache_type( string $key ): string {
		if ( strpos( $key, 'sidebar' ) !== false ) {
			return __( 'Sidebar Data', 'brag-book-gallery' );
		} elseif ( strpos( $key, 'cases' ) !== false ) {
			return __( 'Cases Data', 'brag-book-gallery' );
		} elseif ( strpos( $key, 'carousel' ) !== false ) {
			return __( 'Carousel Data', 'brag-book-gallery' );
		} elseif ( strpos( $key, 'api' ) !== false ) {
			return __( 'API Response', 'brag-book-gallery' );
		} else {
			return __( 'General', 'brag-book-gallery' );
		}
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
		if ( strlen( $formatted ) > 50 ) {
			$formatted = substr( $formatted, 0, 47 ) . '...';
		}

		return $formatted;
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
	 * @param int $bytes Number of bytes.
	 * @return string
	 */
	private function format_bytes( int $bytes ): string {
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$i = 0;

		while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
			$bytes /= 1024;
			$i++;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Format time remaining
	 *
	 * @since 3.0.0
	 * @param int $seconds Seconds remaining.
	 * @return string
	 */
	private function format_time_remaining( int $seconds ): string {
		if ( $seconds < 60 ) {
			return sprintf( _n( '%d second', '%d seconds', $seconds, 'brag-book-gallery' ), $seconds );
		} elseif ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'brag-book-gallery' ), $minutes );
		} else {
			$hours = floor( $seconds / 3600 );
			return sprintf( _n( '%d hour', '%d hours', $hours, 'brag-book-gallery' ), $hours );
		}
	}

	/**
	 * Execute tool actions via AJAX.
	 *
	 * Handles all AJAX requests for cache management operations including
	 * viewing, deleting, and clearing cache items.
	 *
	 * @since 3.0.0
	 * @param string $action Action to execute.
	 * @param array  $data   Request data from AJAX.
	 * @return mixed Response data for AJAX.
	 * @throws \Exception If action is invalid.
	 */
	public function execute( string $action, array $data ): mixed {
		return match ( $action ) {
			'get_data' => $this->get_cache_data( $data['key'] ?? '' ),
			'delete_items' => $this->delete_cache_items( $data['keys'] ?? [] ),
			'clear_all' => $this->clear_all_cache(),
			default => throw new \Exception( 'Invalid action: ' . $action ),
		};
	}

	/**
	 * Get cache data for viewing.
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @return mixed
	 */
	private function get_cache_data( string $key ): mixed {
		if ( empty( $key ) ) {
			throw new \Exception( __( 'Cache key is required', 'brag-book-gallery' ) );
		}

		$data = get_transient( $key );

		if ( false === $data ) {
			throw new \Exception( __( 'Cache item not found or expired', 'brag-book-gallery' ) );
		}

		// Check size before returning
		$serialized = serialize( $data );
		if ( strlen( $serialized ) > self::MAX_DISPLAY_SIZE ) {
			return [
				'_notice' => __( 'Data too large to display inline', 'brag-book-gallery' ),
				'_size' => $this->format_bytes( strlen( $serialized ) ),
				'_type' => gettype( $data ),
			];
		}

		return $data;
	}

	/**
	 * Delete specific cache items.
	 *
	 * @since 3.0.0
	 * @param array $keys Array of cache keys to delete.
	 * @return string Success message.
	 */
	private function delete_cache_items( array $keys ): string {
		if ( empty( $keys ) ) {
			throw new \Exception( __( 'No cache keys provided', 'brag-book-gallery' ) );
		}

		$deleted = 0;
		foreach ( $keys as $key ) {
			if ( delete_transient( $key ) ) {
				$deleted++;
			}
		}

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
	 * Clear all plugin cache.
	 *
	 * @since 3.0.0
	 * @return string Success message.
	 */
	private function clear_all_cache(): string {
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

		return sprintf(
			__( 'Successfully cleared all cache. %d items removed.', 'brag-book-gallery' ),
			$deleted / 2 // Divide by 2 since we delete both transient and timeout
		);
	}
}
