<?php
/**
 * Sync History Manager Component
 *
 * Handles rendering and management of sync history table with log viewing,
 * deletion, and filtering capabilities. Displays historical sync records from
 * the database with status, duration, and detailed information.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Sync
 * @since      3.3.0
 * @version    3.3.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync History Manager class
 *
 * Manages the display and manipulation of sync history records including
 * table rendering, bulk actions, individual record viewing/deletion, and
 * status tracking. Handles database queries for sync log retrieval.
 *
 * ## Features:
 * - Paginated sync history table
 * - Bulk delete selected records
 * - Clear all history
 * - Individual record viewing
 * - Status badges (completed, failed, in progress)
 * - Duration calculation and display
 * - Error message viewing
 * - Source tracking (manual, automatic, REST API)
 *
 * ## Database Schema:
 * - Table: wp_brag_book_sync_log
 * - Columns: id, started_at, completed_at, status, type, source, etc.
 *
 * @since 3.3.0
 */
final class Sync_History_Manager {

	/**
	 * Render sync history table
	 *
	 * Displays historical sync records with actions and filtering.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Check if table exists.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
		if ( ! $table_exists ) {
			echo '<p>' . esc_html__( 'Sync history table not found. Please re-activate the plugin.', 'brag-book-gallery' ) . '</p>';
			return;
		}

		// Get sync records.
		$records = $this->get_sync_records( 50 );

		if ( empty( $records ) ) {
			echo '<p>' . esc_html__( 'No sync history available.', 'brag-book-gallery' ) . '</p>';
			return;
		}
		?>

		<div class="brag-book-sync-history-wrapper">
			<!-- Bulk Actions -->
			<div class="tablenav top">
				<div class="alignleft actions">
					<button type="button" id="delete-selected-sync-records" class="button" disabled>
						<?php esc_html_e( 'Delete Selected', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" id="clear-all-sync-history" class="button">
						<?php esc_html_e( 'Clear All History', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>

			<!-- Sync History Table -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="check-column">
							<input type="checkbox" id="select-all-sync-records" />
						</td>
						<th><?php esc_html_e( 'Date/Time', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Type', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Source', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Processed', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Failed', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'brag-book-gallery' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $records as $record ) : ?>
						<?php $this->render_history_row( $record ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render individual history row
	 *
	 * Displays a single sync record with all details.
	 *
	 * @since 3.3.0
	 *
	 * @param object $record Sync record object from database.
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_history_row( object $record ): void {
		$details      = json_decode( $record->error_messages ?? $record->details ?? '{}', true );
		$started_time = $record->started_at ?? $record->created_at ?? null;
		$started      = $started_time ? strtotime( $started_time ) : time();
		$completed    = $record->completed_at ? strtotime( $record->completed_at ) : null;
		$duration_str = $this->calculate_duration( $started, $completed, $details );
		?>
		<tr data-record-id="<?php echo esc_attr( (string) $record->id ); ?>">
			<th class="check-column">
				<input type="checkbox" class="sync-record-checkbox" value="<?php echo esc_attr( (string) $record->id ); ?>" />
			</th>
			<td>
				<?php echo esc_html( wp_date( 'Y-m-d H:i:s', $started ) ); ?>
			</td>
			<td><?php echo esc_html( ucfirst( $record->sync_type ?? 'full' ) ); ?></td>
			<td><?php $this->render_status_badge( $record->status ); ?></td>
			<td><?php echo esc_html( ucfirst( $record->source ?? 'manual' ) ); ?></td>
			<td><?php echo esc_html( (string) ( $record->processed_count ?? 0 ) ); ?></td>
			<td><?php echo esc_html( (string) ( $record->failed_count ?? 0 ) ); ?></td>
			<td><?php echo esc_html( $duration_str ); ?></td>
			<td class="actions-column">
				<button type="button"
						class="button button-small view-sync-log"
						data-record-id="<?php echo esc_attr( (string) $record->id ); ?>"
						title="<?php esc_attr_e( 'View Details', 'brag-book-gallery' ); ?>">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
				</button>
				<button type="button"
						class="button button-small button-link-delete delete-sync-record"
						data-record-id="<?php echo esc_attr( (string) $record->id ); ?>"
						title="<?php esc_attr_e( 'Delete Record', 'brag-book-gallery' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render status badge
	 *
	 * Displays color-coded status badge for sync record.
	 *
	 * @since 3.3.0
	 *
	 * @param string $status Status string (completed, failed, in_progress).
	 *
	 * @return void Outputs HTML directly
	 */
	private function render_status_badge( string $status ): void {
		$badge_class = 'status-badge';
		$badge_text  = ucfirst( str_replace( '_', ' ', $status ) );

		switch ( $status ) {
			case 'completed':
				$badge_class .= ' status-completed';
				break;
			case 'failed':
				$badge_class .= ' status-failed';
				break;
			case 'in_progress':
				$badge_class .= ' status-in-progress';
				break;
			default:
				$badge_class .= ' status-unknown';
		}
		?>
		<span class="<?php echo esc_attr( $badge_class ); ?>">
			<?php echo esc_html( $badge_text ); ?>
		</span>
		<?php
	}

	/**
	 * Get sync records from database
	 *
	 * Retrieves sync history records ordered by most recent first.
	 *
	 * @since 3.3.0
	 *
	 * @param int $limit Maximum number of records to retrieve. Default 50.
	 *
	 * @return array Array of sync record objects
	 */
	private function get_sync_records( int $limit = 50 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		// Check which columns exist for ordering.
		$columns      = $wpdb->get_col( "DESCRIBE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order_column = 'id';

		if ( in_array( 'started_at', $columns, true ) ) {
			$order_column = 'started_at';
		} elseif ( in_array( 'created_at', $columns, true ) ) {
			$order_column = 'created_at';
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$table_name} ORDER BY {$order_column} DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		);

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Calculate duration string
	 *
	 * Calculates and formats the duration of a sync operation.
	 *
	 * @since 3.3.0
	 *
	 * @param int        $started   Start timestamp.
	 * @param int|null   $completed Completion timestamp or null if not completed.
	 * @param array|null $details   Optional details array with duration key.
	 *
	 * @return string Formatted duration string
	 */
	private function calculate_duration( int $started, ?int $completed, ?array $details = null ): string {
		// Check if duration is in details first.
		if ( ! empty( $details['duration'] ) ) {
			return $details['duration'];
		}

		// Calculate from timestamps.
		if ( ! $completed ) {
			return __( 'In progress', 'brag-book-gallery' );
		}

		$duration_seconds = $completed - $started;

		if ( $duration_seconds < 60 ) {
			return sprintf(
				/* translators: %d: number of seconds */
				_n( '%d second', '%d seconds', $duration_seconds, 'brag-book-gallery' ),
				$duration_seconds
			);
		}

		$minutes = floor( $duration_seconds / 60 );
		$seconds = $duration_seconds % 60;

		return sprintf(
			/* translators: 1: number of minutes, 2: number of seconds */
			__( '%1$d min %2$d sec', 'brag-book-gallery' ),
			$minutes,
			$seconds
		);
	}

	/**
	 * Delete sync record
	 *
	 * Removes a sync record from the database.
	 *
	 * @since 3.3.0
	 *
	 * @param int $record_id Record ID to delete.
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete_record( int $record_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $record_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Clear all sync history
	 *
	 * Removes all records from the sync history table.
	 *
	 * @since 3.3.0
	 *
	 * @return bool True on success, false on failure
	 */
	public function clear_all_history(): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'brag_book_sync_log';

		$result = $wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false !== $result;
	}
}
