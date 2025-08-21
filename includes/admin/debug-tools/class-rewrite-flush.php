<?php
/**
 * Rewrite Flush Tool
 *
 * Provides a simple interface to flush WordPress rewrite rules
 *
 * @package BragBookGallery
 * @since 3.0.0
 */

namespace BragBookGallery\Admin\Debug_Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Flush class
 *
 * @since 3.0.0
 */
class Rewrite_Flush {

	/**
	 * Get checkmark SVG icon
	 *
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML.
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
	 * Get warning SVG icon
	 *
	 * @return string SVG HTML.
	 */
	private function get_warning_icon(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ff9800" style="vertical-align: middle; margin-right: 5px;"><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
	}

	/**
	 * Render the flush tool interface
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

				<div id="flush-result"></div>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'After Flushing', 'brag-book-gallery' ); ?></h3>
				<button class="button" id="verify-rules">
					<?php esc_html_e( 'Verify Rules', 'brag-book-gallery' ); ?>
				</button>
				<div id="verify-result"></div>
			</div>

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
		jQuery(document).ready(function($) {
			// Standard flush
			$('#flush-rules-standard').on('click', function() {
				var button = $(this);
				var resultDiv = $('#flush-result');
				
				button.prop('disabled', true);
				resultDiv.html('<p>Flushing rewrite rules...</p>');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'brag_book_flush_rewrite_rules',
						nonce: '<?php echo wp_create_nonce( 'brag_book_flush_rewrite_rules' ); ?>',
						flush_type: 'standard'
					},
					success: function(response) {
						if (response.success) {
							resultDiv.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
						} else {
							resultDiv.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
						}
					},
					error: function() {
						resultDiv.html('<div class="notice notice-error"><p>Failed to flush rewrite rules. Please try again.</p></div>');
					},
					complete: function() {
						button.prop('disabled', false);
					}
				});
			});
			
			// Hard flush
			$('#flush-rules-hard').on('click', function() {
				var button = $(this);
				var resultDiv = $('#flush-result');
				
				button.prop('disabled', true);
				resultDiv.html('<p>Performing hard flush...</p>');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'brag_book_flush_rewrite_rules',
						nonce: '<?php echo wp_create_nonce( 'brag_book_flush_rewrite_rules' ); ?>',
						flush_type: 'hard'
					},
					success: function(response) {
						if (response.success) {
							resultDiv.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
						} else {
							resultDiv.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
						}
					},
					error: function() {
						resultDiv.html('<div class="notice notice-error"><p>Failed to flush rewrite rules. Please try again.</p></div>');
					},
					complete: function() {
						button.prop('disabled', false);
					}
				});
			});
			
			// Flush with registration
			$('#flush-with-registration').on('click', function() {
				var button = $(this);
				var resultDiv = $('#flush-result');
				
				button.prop('disabled', true);
				resultDiv.html('<p>Re-registering rules and flushing...</p>');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'brag_book_flush_rewrite_rules',
						nonce: '<?php echo wp_create_nonce( 'brag_book_flush_rewrite_rules' ); ?>',
						flush_type: 'with_registration'
					},
					success: function(response) {
						if (response.success) {
							resultDiv.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
						} else {
							resultDiv.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
						}
					},
					error: function() {
						resultDiv.html('<div class="notice notice-error"><p>Failed to flush rewrite rules. Please try again.</p></div>');
					},
					complete: function() {
						button.prop('disabled', false);
					}
				});
			});
			
			// Verify rules
			$('#verify-rules').on('click', function() {
				var button = $(this);
				var resultDiv = $('#verify-result');
				
				button.prop('disabled', true);
				resultDiv.html('<p>Verifying rules...</p>');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'brag_book_flush_rewrite_rules',
						nonce: '<?php echo wp_create_nonce( 'brag_book_flush_rewrite_rules' ); ?>',
						flush_type: 'verify'
					},
					success: function(response) {
						if (response.success) {
							resultDiv.html('<div class="notice notice-info"><p>' + response.data + '</p></div>');
						} else {
							resultDiv.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
						}
					},
					error: function() {
						resultDiv.html('<div class="notice notice-error"><p>Failed to verify rules. Please try again.</p></div>');
					},
					complete: function() {
						button.prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render current status
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
					( $brag_book_gallery_page_slug && strpos( $pattern, $brag_book_gallery_page_slug ) !== false ) ||
					strpos( $query, 'procedure_title' ) !== false ||
					strpos( $query, 'case_id' ) !== false
				) {
					$gallery_rules_count++;
				}
			}
		}
		?>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Total Rewrite Rules', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( count( $rules ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery-specific Rules', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $gallery_rules_count ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Permalink Structure', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( get_option( 'permalink_structure' ) ?: 'Plain' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery Slug', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $brag_book_gallery_page_slug ?: '(not set)' ); ?></td>
				</tr>
				<?php if ( defined( 'WPE_APIKEY' ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Environment', 'brag-book-gallery' ); ?></th>
					<td style="color: orange;">
						<?php esc_html_e( 'WP Engine - May require cache clearing after flush', 'brag-book-gallery' ); ?>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Execute tool actions via AJAX
	 *
	 * @param string $action Action to execute.
	 * @param array  $data   Request data.
	 * @return mixed
	 */
	public function execute( string $action, array $data ) {
		switch ( $action ) {
			case 'flush':
				return $this->flush_rules( $data['flush_type'] ?? 'standard' );

			case 'verify':
				return $this->verify_rules();

			default:
				throw new \Exception( 'Invalid action' );
		}
	}

	/**
	 * Flush rewrite rules
	 *
	 * @param string $type Type of flush to perform.
	 * @return string
	 */
	private function flush_rules( string $type ): string {
		$results = [];

		switch ( $type ) {
			case 'hard':
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
				break;

			case 'with_registration':
				// Register custom rules first
				if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
					\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
					$results[] = $this->get_check_icon( true ) . 'Re-registered custom rules';
				}

				// Standard flush
				flush_rewrite_rules( false );
				$results[] = $this->get_check_icon( true ) . 'Flushed rewrite rules';
				break;

			case 'standard':
			default:
				// Standard flush
				flush_rewrite_rules( false );
				$results[] = $this->get_check_icon( true ) . 'Flushed rewrite rules';
				break;
		}

		// Clear any caches
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results[] = $this->get_check_icon( true ) . 'Cleared object cache';
		}

		// WP Engine specific
		if ( defined( 'WPE_APIKEY' ) ) {
			$results[] = $this->get_warning_icon() . 'Remember to clear WP Engine cache from the dashboard';
		}

		$output = implode( '<br>', $results );
		$output .= '<br><br><strong>' . __( 'Rewrite rules flushed successfully!', 'brag-book-gallery' ) . '</strong>';
		$output .= '<br>' . __( 'Test your gallery URLs to verify they work correctly.', 'brag-book-gallery' );

		return $output;
	}

	/**
	 * Verify rewrite rules
	 *
	 * @return string
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
				if ( strpos( $pattern, $brag_book_gallery_page_slug ) !== false ) {
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
				( $brag_book_gallery_page_slug && strpos( $pattern, $brag_book_gallery_page_slug ) !== false ) ||
				strpos( $query, 'procedure_title' ) !== false ||
				strpos( $query, 'case_id' ) !== false
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
