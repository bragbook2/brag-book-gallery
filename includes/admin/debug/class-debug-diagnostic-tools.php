<?php
/**
 * Debug Diagnostic Tools Component
 *
 * Handles rendering of diagnostic tools in the debug interface. Provides access
 * to advanced troubleshooting tools including gallery checker and system analysis.
 * Utilizes modern PHP 8.2+ syntax and WordPress VIP coding standards.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug
 * @since      3.3.0
 * @version    3.3.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug;

use BRAGBookGallery\Includes\Extend\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Diagnostic Tools class
 *
 * Provides access to advanced diagnostic and troubleshooting tools for analyzing
 * gallery functionality, page detection, shortcode validation, and other
 * gallery-specific issues.
 *
 * ## Features:
 * - Gallery page checker tool integration
 * - Advanced diagnostic capabilities
 * - Troubleshooting utilities
 *
 * ## Available Tools:
 * - Gallery Checker: Validates gallery pages and shortcodes
 *
 * @since 3.3.0
 */
final class Debug_Diagnostic_Tools {

	/**
	 * AJAX action for resetting child procedure order values.
	 *
	 * @since 4.4.7
	 */
	private const ACTION_RESET_PROCEDURE_ORDER = 'brag_book_gallery_reset_procedure_order';

	/**
	 * Nonce identifier for the procedure-order reset action.
	 *
	 * @since 4.4.7
	 */
	private const NONCE_RESET_PROCEDURE_ORDER = 'brag_book_gallery_reset_procedure_order';

	/**
	 * Constructor — wire up AJAX handlers.
	 *
	 * @since 4.4.7
	 */
	public function __construct() {
		add_action(
			'wp_ajax_' . self::ACTION_RESET_PROCEDURE_ORDER,
			array( $this, 'handle_reset_procedure_order_ajax' )
		);
	}

	/**
	 * Render the diagnostic tools interface
	 *
	 * Displays available diagnostic tools with descriptions and controls.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		?>
		<div id="diagnostic-tools" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Advanced diagnostic tools for troubleshooting and debugging gallery functionality.', 'brag-book-gallery' ); ?>
			</p>

			<!-- Database Tables Check -->
			<div class="diagnostic-section database-tables-section">
				<?php $this->render_database_tables_check(); ?>
			</div>

			<!-- Term Order Tools -->
			<div class="diagnostic-section term-order-tools-section">
				<?php $this->render_term_order_tools(); ?>
			</div>

			<!-- Gallery Checker Section -->
			<div class="diagnostic-section gallery-checker-section">
				<?php
				$gallery_checker_tool = ( new Gallery_Checker() )->render();
				?>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the procedure-order reset tool
	 *
	 * Outputs a button that clears `procedure_order` term meta for every
	 * child term in the procedures taxonomy. Parent categories keep their
	 * values because their order is API-driven and used by the sidebar/nav
	 * sort. Children are intentionally unordered post-sync, so legacy values
	 * left over from earlier sync builds can still skew sort behaviour —
	 * this control wipes them in one pass.
	 *
	 * @since 4.4.7
	 *
	 * @return void Outputs HTML directly.
	 */
	private function render_term_order_tools(): void {
		$nonce = wp_create_nonce( self::NONCE_RESET_PROCEDURE_ORDER );
		?>
		<h3><?php esc_html_e( 'Procedure Order', 'brag-book-gallery' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Clears the order value on every child procedure term while leaving parent categories untouched. Use this if legacy order values are causing incorrect sorting.', 'brag-book-gallery' ); ?>
		</p>
		<p>
			<button
				type="button"
				class="button button-secondary"
				id="brag-book-reset-procedure-order"
				data-nonce="<?php echo esc_attr( $nonce ); ?>"
			>
				<?php esc_html_e( 'Reset Order Numbers', 'brag-book-gallery' ); ?>
			</button>
		</p>
		<div id="brag-book-reset-procedure-order-result" class="brag-book-reset-result" aria-live="polite"></div>
		<script>
			(function () {
				const button = document.getElementById( 'brag-book-reset-procedure-order' );
				if ( ! button ) {
					return;
				}

				const resultEl = document.getElementById( 'brag-book-reset-procedure-order-result' );
				const confirmText = <?php echo wp_json_encode( esc_html__( 'Clear procedure_order on all child procedure terms? Parent categories will not be changed.', 'brag-book-gallery' ) ); ?>;
				const workingText = <?php echo wp_json_encode( esc_html__( 'Resetting child procedure order values…', 'brag-book-gallery' ) ); ?>;
				const networkErrorText = <?php echo wp_json_encode( esc_html__( 'Network error — please try again.', 'brag-book-gallery' ) ); ?>;
				const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				const action = <?php echo wp_json_encode( self::ACTION_RESET_PROCEDURE_ORDER ); ?>;

				button.addEventListener( 'click', async function () {
					if ( ! window.confirm( confirmText ) ) {
						return;
					}

					button.disabled = true;
					if ( resultEl ) {
						resultEl.innerHTML = '<p>' + workingText + '</p>';
					}

					const body = new FormData();
					body.append( 'action', action );
					body.append( 'nonce', button.dataset.nonce );

					try {
						const response = await fetch( ajaxUrl, {
							method: 'POST',
							credentials: 'same-origin',
							body: body,
						} );
						const json = await response.json();
						if ( resultEl ) {
							const noticeClass = json.success ? 'notice-success' : 'notice-error';
							const message = ( json.data && json.data.message ) || json.data || '';
							resultEl.innerHTML = '<div class="notice ' + noticeClass + ' inline"><p>' + message + '</p></div>';
						}
					} catch ( err ) {
						if ( resultEl ) {
							resultEl.innerHTML = '<div class="notice notice-error inline"><p>' + networkErrorText + '</p></div>';
						}
					} finally {
						button.disabled = false;
					}
				} );
			})();
		</script>
		<?php
	}

	/**
	 * AJAX handler — clear `procedure_order` term meta for child terms only.
	 *
	 * @since 4.4.7
	 *
	 * @return void Sends a JSON response and exits.
	 */
	public function handle_reset_procedure_order_ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) ),
				403
			);
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_RESET_PROCEDURE_ORDER ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'brag-book-gallery' ) ),
				400
			);
		}

		$cleared = $this->reset_child_procedure_order();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of child procedure terms updated. */
					_n(
						'Cleared procedure_order on %d child procedure term.',
						'Cleared procedure_order on %d child procedure terms.',
						$cleared,
						'brag-book-gallery'
					),
					$cleared
				),
				'cleared' => $cleared,
			)
		);
	}

	/**
	 * Delete `procedure_order` term meta from every child procedure term.
	 *
	 * Parents (top-level categories) keep their order — sync writes that value
	 * and the sidebar/nav rely on it. Only terms with a non-zero `parent` are
	 * touched.
	 *
	 * @since 4.4.7
	 *
	 * @return int Number of terms whose `procedure_order` meta was deleted.
	 */
	private function reset_child_procedure_order(): int {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
				'hide_empty' => false,
				'fields'     => 'id=>parent',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return 0;
		}

		$cleared = 0;
		foreach ( $terms as $term_id => $parent_id ) {
			if ( 0 === (int) $parent_id ) {
				continue;
			}

			$term_id = (int) $term_id;
			if ( $term_id <= 0 ) {
				continue;
			}

			if ( '' === get_term_meta( $term_id, 'procedure_order', true ) ) {
				continue;
			}

			if ( delete_term_meta( $term_id, 'procedure_order' ) ) {
				++$cleared;
			}
		}

		return $cleared;
	}

	/**
	 * Render the database tables check
	 *
	 * Verifies that required plugin database tables exist and reports row counts.
	 *
	 * @since 4.3.3
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_database_tables_check(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'brag_sync_registry' => __( 'Sync Registry', 'brag-book-gallery' ),
			$wpdb->prefix . 'brag_sync_log'      => __( 'Sync Log', 'brag-book-gallery' ),
		];

		?>
		<h3><?php esc_html_e( 'Database Tables', 'brag-book-gallery' ); ?></h3>
		<div class="system-status-rows">
			<?php
			$index = 0;
			foreach ( $tables as $table_name => $label ) :
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
				$row_count = 0;

				if ( $exists ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );
				}
				?>
				<div class="system-status-row<?php echo $index % 2 !== 0 ? ' system-status-row--alt' : ''; ?>">
					<span class="system-status-label"><?php echo esc_html( $label ); ?></span>
					<span class="system-status-value"><code><?php echo esc_html( $table_name ); ?></code></span>
					<span class="system-status-value"><?php echo $exists ? esc_html( number_format_i18n( $row_count ) ) . ' ' . esc_html__( 'rows', 'brag-book-gallery' ) : '—'; ?></span>
					<span class="system-status-indicator">
						<?php if ( $exists ) : ?>
							<span class="status-badge status-badge--success">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
							</span>
						<?php else : ?>
							<span class="status-badge status-badge--error">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
							</span>
						<?php endif; ?>
					</span>
				</div>
				<?php
				++$index;
			endforeach;
			?>
		</div>
		<?php
	}

	/**
	 * Get available diagnostic tools
	 *
	 * Returns an array of available diagnostic tools with their metadata.
	 *
	 * @since 3.3.0
	 *
	 * @return array Array of diagnostic tools
	 */
	public function get_available_tools(): array {
		return array(
			'gallery_checker' => array(
				'name'        => __( 'Gallery Checker', 'brag-book-gallery' ),
				'description' => __( 'Validates gallery pages, shortcodes, and configuration.', 'brag-book-gallery' ),
				'class'       => Gallery_Checker::class,
			),
		);
	}
}
