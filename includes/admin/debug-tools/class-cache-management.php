<?php
/**
 * Cache Management Debug Tool
 *
 * Manages and displays cached data for the BRAGBook Gallery plugin.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 */

namespace BRAGBookGallery\Admin\Debug_Tools;

if ( ! defined( 'WPINC' ) ) {
	die( 'Direct access not allowed' );
}

/**
 * Cache Management Tool Class
 *
 * @since 3.0.0
 */
class Cache_Management {

	/**
	 * Cache prefix used by the plugin
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'brag_book_gallery_';

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
			<div class="cache-stats" style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 20px 0;">
				<h4 style="margin-top: 0;"><?php esc_html_e( 'Cache Statistics', 'brag-book-gallery' ); ?></h4>
				<ul style="margin: 0;">
					<li><?php printf( esc_html__( 'Total Cached Items: %d', 'brag-book-gallery' ), count( $cached_items ) ); ?></li>
					<li><?php printf( esc_html__( 'Total Cache Size: %s', 'brag-book-gallery' ), $this->format_bytes( $total_size ) ); ?></li>
					<li><?php printf( esc_html__( 'Cache Duration Setting: %d seconds', 'brag-book-gallery' ), get_option( 'brag_book_gallery_cache_duration', 3600 ) ); ?></li>
				</ul>
			</div>
			
			<!-- Action Buttons -->
			<div class="cache-actions" style="margin: 20px 0;">
				<button type="button" class="button button-primary" id="refresh-cache-list">
					<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Refresh List', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="clear-selected-cache" disabled>
					<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Clear Selected', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-danger" id="clear-all-cache" <?php echo empty( $cached_items ) ? 'disabled' : ''; ?>>
					<span class="dashicons dashicons-dismiss" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Clear All Cache', 'brag-book-gallery' ); ?>
				</button>
				<span id="cache-action-status" style="margin-left: 10px; display: none;"></span>
			</div>
			
			<!-- Cache Items Table -->
			<?php if ( ! empty( $cached_items ) ) : ?>
				<table class="wp-list-table widefat striped" id="cache-items-table">
					<thead>
						<tr>
							<th class="check-column">
								<input type="checkbox" id="select-all-cache" />
							</th>
							<th><?php esc_html_e( 'Cache Key', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Type', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Size', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Expiration', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cached_items as $item ) : ?>
							<tr data-cache-key="<?php echo esc_attr( $item['key'] ); ?>">
								<td class="check-column">
									<input type="checkbox" class="cache-item-checkbox" value="<?php echo esc_attr( $item['key'] ); ?>" />
								</td>
								<td>
									<strong><?php echo esc_html( $this->format_cache_key( $item['key'] ) ); ?></strong>
									<br>
									<small style="color: #666;"><?php echo esc_html( $item['key'] ); ?></small>
								</td>
								<td><?php echo esc_html( $item['type'] ); ?></td>
								<td><?php echo esc_html( $this->format_bytes( $item['size'] ) ); ?></td>
								<td>
									<?php if ( $item['expiration'] ) : ?>
										<?php
										$time_left = $item['expiration'] - time();
										if ( $time_left > 0 ) {
											echo esc_html( $this->format_time_remaining( $time_left ) );
										} else {
											echo '<span style="color: #d63638;">' . esc_html__( 'Expired', 'brag-book-gallery' ) . '</span>';
										}
										?>
										<br>
										<small style="color: #666;">
											<?php echo esc_html( wp_date( 'Y-m-d H:i:s', $item['expiration'] ) ); ?>
										</small>
									<?php else : ?>
										<span style="color: #2271b1;"><?php esc_html_e( 'No expiration', 'brag-book-gallery' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button button-small view-cache-data" data-key="<?php echo esc_attr( $item['key'] ); ?>">
										<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete delete-cache-item" data-key="<?php echo esc_attr( $item['key'] ); ?>">
										<?php esc_html_e( 'Delete', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No cached items found.', 'brag-book-gallery' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		
		<!-- Cache Data Modal -->
		<div id="cache-data-modal" style="display: none;">
			<div class="cache-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999998;"></div>
			<div class="cache-modal-content" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 4px; max-width: 80%; max-height: 80%; overflow: auto; z-index: 999999; box-shadow: 0 5px 30px rgba(0,0,0,0.3);">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Cache Data', 'brag-book-gallery' ); ?></h3>
				<div style="margin-bottom: 15px;">
					<button type="button" class="button button-small" id="copy-cache-data">
						<?php esc_html_e( 'Copy Data', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" class="button button-small" id="close-cache-modal">
						<?php esc_html_e( 'Close', 'brag-book-gallery' ); ?>
					</button>
				</div>
				<pre id="cache-data-content" style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px; font-size: 12px;"></pre>
			</div>
		</div>
		
		<style>
		.button-danger {
			background: #d63638 !important;
			border-color: #d63638 !important;
			color: white !important;
		}
		.button-danger:hover {
			background: #c13133 !important;
			border-color: #c13133 !important;
		}
		.button-danger:disabled {
			background: #f0f0f1 !important;
			border-color: #dcdcde !important;
			color: #a7aaad !important;
		}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			// Select all checkbox
			$('#select-all-cache').on('change', function() {
				$('.cache-item-checkbox').prop('checked', $(this).prop('checked'));
				updateSelectedButtons();
			});
			
			// Individual checkbox change
			$('.cache-item-checkbox').on('change', function() {
				updateSelectedButtons();
			});
			
			function updateSelectedButtons() {
				var checkedCount = $('.cache-item-checkbox:checked').length;
				$('#clear-selected-cache').prop('disabled', checkedCount === 0);
				
				if (checkedCount === $('.cache-item-checkbox').length && checkedCount > 0) {
					$('#select-all-cache').prop('checked', true);
				} else {
					$('#select-all-cache').prop('checked', false);
				}
			}
			
			// Refresh cache list
			$('#refresh-cache-list').on('click', function() {
				location.reload();
			});
			
			// Clear selected cache items
			$('#clear-selected-cache').on('click', function() {
				var selectedKeys = [];
				$('.cache-item-checkbox:checked').each(function() {
					selectedKeys.push($(this).val());
				});
				
				if (selectedKeys.length === 0) return;
				
				if (!confirm('<?php esc_html_e( 'Are you sure you want to delete the selected cache items?', 'brag-book-gallery' ); ?>')) {
					return;
				}
				
				var button = $(this);
				button.prop('disabled', true);
				
				$.post(ajaxurl, {
					action: 'brag_book_delete_cache_items',
					nonce: '<?php echo wp_create_nonce( 'brag_book_cache_management' ); ?>',
					keys: selectedKeys
				}, function(response) {
					if (response.success) {
						showStatus('<?php esc_html_e( 'Selected items cleared successfully!', 'brag-book-gallery' ); ?>', 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						showStatus(response.data || '<?php esc_html_e( 'Error clearing cache items.', 'brag-book-gallery' ); ?>', 'error');
						button.prop('disabled', false);
					}
				});
			});
			
			// Clear all cache
			$('#clear-all-cache').on('click', function() {
				if (!confirm('<?php esc_html_e( 'Are you sure you want to clear ALL cache? This cannot be undone.', 'brag-book-gallery' ); ?>')) {
					return;
				}
				
				var button = $(this);
				button.prop('disabled', true);
				
				$.post(ajaxurl, {
					action: 'brag_book_gallery_clear_cache',
					nonce: '<?php echo wp_create_nonce( 'brag_book_gallery_clear_cache' ); ?>'
				}, function(response) {
					if (response.success) {
						showStatus('<?php esc_html_e( 'All cache cleared successfully!', 'brag-book-gallery' ); ?>', 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						showStatus(response.data || '<?php esc_html_e( 'Error clearing cache.', 'brag-book-gallery' ); ?>', 'error');
						button.prop('disabled', false);
					}
				});
			});
			
			// Delete individual cache item
			$('.delete-cache-item').on('click', function() {
				var key = $(this).data('key');
				var row = $(this).closest('tr');
				
				if (!confirm('<?php esc_html_e( 'Delete this cache item?', 'brag-book-gallery' ); ?>')) {
					return;
				}
				
				$.post(ajaxurl, {
					action: 'brag_book_delete_cache_items',
					nonce: '<?php echo wp_create_nonce( 'brag_book_cache_management' ); ?>',
					keys: [key]
				}, function(response) {
					if (response.success) {
						row.fadeOut(function() {
							row.remove();
							if ($('#cache-items-table tbody tr').length === 0) {
								location.reload();
							}
						});
					} else {
						alert(response.data || '<?php esc_html_e( 'Error deleting cache item.', 'brag-book-gallery' ); ?>');
					}
				});
			});
			
			// View cache data
			$('.view-cache-data').on('click', function() {
				var key = $(this).data('key');
				
				$.post(ajaxurl, {
					action: 'brag_book_get_cache_data',
					nonce: '<?php echo wp_create_nonce( 'brag_book_cache_management' ); ?>',
					key: key
				}, function(response) {
					if (response.success) {
						$('#cache-data-content').text(JSON.stringify(response.data, null, 2));
						$('#cache-data-modal').fadeIn();
					} else {
						alert(response.data || '<?php esc_html_e( 'Error loading cache data.', 'brag-book-gallery' ); ?>');
					}
				});
			});
			
			// Close modal
			$('#close-cache-modal, .cache-modal-overlay').on('click', function() {
				$('#cache-data-modal').fadeOut();
			});
			
			// Copy cache data
			$('#copy-cache-data').on('click', function() {
				var content = $('#cache-data-content').text();
				navigator.clipboard.writeText(content).then(function() {
					alert('<?php esc_html_e( 'Data copied to clipboard!', 'brag-book-gallery' ); ?>');
				});
			});
			
			function showStatus(message, type) {
				var color = type === 'success' ? '#46b450' : '#d63638';
				$('#cache-action-status')
					.css('color', color)
					.text(message)
					.fadeIn()
					.delay(3000)
					.fadeOut();
			}
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
}