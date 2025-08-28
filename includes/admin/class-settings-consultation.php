<?php
/**
 * Settings Consultation Class - Manages consultation settings and display
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

use BRAGBookGallery\Includes\Core\Consultation;
use BRAGBookGallery\Includes\Core\Setup;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Settings Consultation Class
 *
 * Provides consultation management interface for the BRAG book Gallery plugin.
 * This class handles both consultation settings configuration and the display
 * of consultation entries. It serves as the admin interface for managing
 * consultation forms and viewing submitted consultation requests.
 *
 * Key functionality:
 * - Display consultation entries with pagination
 * - Provide consultation settings configuration
 * - Export consultation data
 * - Manage consultation form notifications
 * - View and respond to consultation requests
 *
 * This class bridges the settings architecture with the Consultation core
 * functionality, providing a seamless admin experience for managing all
 * consultation-related features.
 *
 * @since 3.0.0
 */
class Settings_Consultation extends Settings_Base {

	/**
	 * Consultation handler instance
	 *
	 * Core consultation class that handles form processing and data management.
	 *
	 * @since 3.0.0
	 * @var Consultation|null
	 */
	private ?Consultation $consultation_handler = null;

	/**
	 * Initialize consultation settings page
	 *
	 * Sets up the page configuration and retrieves the consultation handler
	 * from the main plugin setup. The consultation handler provides the
	 * core functionality for managing consultation forms and entries.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug = 'brag-book-gallery-consultation';

		// Get consultation handler from setup
		$setup = Setup::get_instance();
		if ( method_exists( $setup, 'get_service' ) ) {
			$this->consultation_handler = $setup->get_service( 'consultation' );
		}

		// Fallback to creating new instance if needed
		if ( ! $this->consultation_handler ) {
			$this->consultation_handler = new Consultation();
		}
	}

	/**
	 * Render the consultation management page
	 *
	 * Displays the consultation entries table and settings interface.
	 * This method determines whether to show the settings page or the
	 * entries list based on the current tab or view parameter.
	 *
	 * The page provides two main views:
	 * 1. Entries View - Table of consultation form submissions
	 * 2. Settings View - Configuration options for consultation forms
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set localized page titles
		$this->page_title = __( 'Consultation Management', 'brag-book-gallery' );
		$this->menu_title = __( 'Consultations', 'brag-book-gallery' );

		// Check which view to display
		$current_view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'entries';

		$this->render_header();
		?>
		<div class="brag-book-gallery-content">
			<?php $this->render_consultation_tabs( $current_view ); ?>
			
			<?php if ( $current_view === 'entries' ) : ?>
				<?php $this->render_consultation_entries(); ?>
			<?php elseif ( $current_view === 'settings' ) : ?>
				<?php $this->render_consultation_settings(); ?>
			<?php elseif ( $current_view === 'stats' ) : ?>
				<?php $this->render_consultation_stats(); ?>
			<?php elseif ( $current_view === 'export' ) : ?>
				<?php $this->render_consultation_export(); ?>
			<?php endif; ?>
		</div>
		<?php
		$this->render_footer();
	}

	/**
	 * Render consultation-specific tabs
	 *
	 * Displays tabs for switching between entries and settings views.
	 *
	 * @since 3.0.0
	 * @param string $current_view Current active view
	 * @return void
	 */
	private function render_consultation_tabs( string $current_view ): void {
		$base_url = admin_url( 'admin.php?page=' . $this->page_slug );

		// Get count of consultation entries for badge
		$entries_count = wp_count_posts( 'form-entries' )->publish ?? 0;
		?>
		<div class="brag-book-gallery-tabs">
			<ul class="brag-book-gallery-tab-list">
				<li class="brag-book-gallery-tab-item <?php echo $current_view === 'entries' ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( $base_url . '&view=entries' ); ?>"
					   class="brag-book-gallery-tab-link">
						<?php esc_html_e( 'Entries', 'brag-book-gallery' ); ?>
						<?php if ( $entries_count > 0 ) : ?>
							<span class="brag-book-gallery-tab-badge"><?php echo esc_html( $entries_count ); ?></span>
						<?php endif; ?>
					</a>
				</li>
				<li class="brag-book-gallery-tab-item <?php echo $current_view === 'settings' ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( $base_url . '&view=settings' ); ?>"
					   class="brag-book-gallery-tab-link">
						<?php esc_html_e( 'Settings', 'brag-book-gallery' ); ?>
					</a>
				</li>
				<li class="brag-book-gallery-tab-item <?php echo $current_view === 'stats' ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( $base_url . '&view=stats' ); ?>"
					   class="brag-book-gallery-tab-link">
						<?php esc_html_e( 'Statistics', 'brag-book-gallery' ); ?>
					</a>
				</li>
				<li class="brag-book-gallery-tab-item <?php echo $current_view === 'export' ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( $base_url . '&view=export' ); ?>"
					   class="brag-book-gallery-tab-link">
						<?php esc_html_e( 'Export', 'brag-book-gallery' ); ?>
					</a>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render consultation entries view
	 *
	 * Displays the consultation entries directly without delegating to avoid double rendering.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_consultation_entries(): void {
		// Generate nonce for security.
		$nonce = wp_create_nonce( 'consultation_pagination_nonce' );
		$delete_nonce = wp_create_nonce( 'consultation_delete_nonce' );
		?>
		<div class="consultation-entries-wrapper">
			<div class="table-header">
				<h3><?php esc_html_e( 'Consultation Entries', 'brag-book-gallery' ); ?></h3>
			</div>

			<div class="bb_pag_loading" style="display: none;">
				<p><?php esc_html_e( 'Loading consultation entries...', 'brag-book-gallery' ); ?></p>
			</div>

			<div class="table-container">
				<table class="consultation-entries">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Email', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Date', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Message', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
						</tr>
					</thead>
					<tbody class="bb_universal_container">
						<tr>
							<td colspan="6" style="text-align: center; padding: 2rem;">
								<?php esc_html_e( 'Loading consultation entries...', 'brag-book-gallery' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="bb-pagination-nav"></div>
		</div>

		<!-- Consultation Details Dialog -->
		<dialog class="consultation-dialog" id="consultationDetailsDialog">
			<div class="consultation-dialog-content">
				<div class="consultation-dialog-header">
					<h2><?php esc_html_e( 'Consultation Details', 'brag-book-gallery' ); ?></h2>
					<button type="button" class="dialog-close" onclick="document.getElementById('consultationDetailsDialog').close()">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="consultation-dialog-body" id="consultationDetailsBody">
					<!-- Content will be loaded here -->
				</div>
				<div class="consultation-dialog-footer">
					<button type="button" class="button button-secondary" onclick="document.getElementById('consultationDetailsDialog').close()">
						<?php esc_html_e( 'Close', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>
		</dialog>

		<script type="text/javascript">
		document.addEventListener( 'DOMContentLoaded', () => {
			const ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce = <?php echo wp_json_encode( $nonce ); ?>;
			const deleteNonce = <?php echo wp_json_encode( $delete_nonce ); ?>;

			const loadConsultationPosts = async ( page ) => {
				const loadingDiv = document.querySelector( '.bb_pag_loading' );
				const container = document.querySelector( '.bb_universal_container' );
				const paginationNav = document.querySelector( '.bb-pagination-nav' );

				if ( loadingDiv ) loadingDiv.style.display = 'block';
				if ( container ) container.innerHTML = '<tr><td colspan="6" style="text-align: center;">Loading...</td></tr>';

				const formData = new FormData();
				formData.append( 'page', page );
				formData.append( 'nonce', nonce );
				formData.append( 'action', 'consultation-pagination-load-posts' );

				try {
					const response = await fetch( ajaxurl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					});

					const data = await response.json();

					if ( data.success ) {
						container.innerHTML = data.data.message;
						paginationNav.innerHTML = data.data.pagination;
					} else {
						container.innerHTML = `<tr><td colspan="6">${data.data || 'Error loading data'}</td></tr>`;
					}
				} catch ( error ) {
					console.error( 'Error loading consultation entries:', error );
					container.innerHTML = '<tr><td colspan="6">Failed to load consultation entries.</td></tr>';
				} finally {
					if ( loadingDiv ) loadingDiv.style.display = 'none';
				}
			};

			// Initial load
			loadConsultationPosts( 1 );

			// Handle pagination clicks
			document.addEventListener( 'click', ( event ) => {
				const activeButton = event.target.closest( '.bb-universal-pagination li.active' );
				if ( activeButton ) {
					const page = parseInt( activeButton.getAttribute( 'p' ), 10 );
					if ( !isNaN( page ) ) {
						loadConsultationPosts( page );
					}
				}
				
				// Handle view consultation clicks
				const viewButton = event.target.closest( '.view-consultation' );
				if ( viewButton ) {
					event.preventDefault();
					const postId = viewButton.dataset.id;
					viewConsultationDetails( postId );
				}
			});
			
			// View consultation details function
			const viewConsultationDetails = async ( postId ) => {
				const dialog = document.getElementById( 'consultationDetailsDialog' );
				const body = document.getElementById( 'consultationDetailsBody' );
				
				if ( !dialog || !body ) return;
				
				body.innerHTML = '<p style="text-align: center; padding: 20px;"><?php echo esc_js( __( 'Loading...', 'brag-book-gallery' ) ); ?></p>';
				dialog.showModal();
				
				const formData = new FormData();
				formData.append( 'post_id', postId );
				formData.append( 'nonce', nonce );
				formData.append( 'action', 'consultation-get-details' );
				
				try {
					const response = await fetch( ajaxurl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					});
					
					const data = await response.json();
					
					if ( data.success ) {
						body.innerHTML = data.data.html;
					} else {
						body.innerHTML = '<p style="color: red; padding: 20px;">' + ( data.data || '<?php echo esc_js( __( 'Error loading consultation details', 'brag-book-gallery' ) ); ?>' ) + '</p>';
					}
				} catch ( error ) {
					console.error( 'Error loading consultation details:', error );
					body.innerHTML = '<p style="color: red; padding: 20px;"><?php echo esc_js( __( 'Failed to load consultation details', 'brag-book-gallery' ) ); ?></p>';
				}
			};
		});
		</script>
		<?php
	}

	/**
	 * Render consultation settings view
	 *
	 * Displays configuration options for consultation forms including
	 * notification settings, form field configuration, and API integration.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_consultation_settings(): void {
		// Handle form submission
		if ( isset( $_POST['submit'] ) && $this->save_settings( 'brag_book_gallery_consultation_settings', 'brag_book_gallery_consultation_nonce' ) ) {
			$this->save_consultation_settings();
		}

		// Get current settings
		$enabled = get_option( 'brag_book_gallery_consultation_enabled', false );
		$notification_email = get_option( 'brag_book_gallery_consultation_email', get_option( 'admin_email' ) );
		$auto_respond = get_option( 'brag_book_gallery_consultation_auto_respond', false );
		$response_message = get_option( 'brag_book_gallery_consultation_response', __( 'Thank you for your consultation request. We will contact you soon.', 'brag-book-gallery' ) );
		$form_title = get_option( 'brag_book_gallery_consultation_form_title', __( 'Request a Consultation', 'brag-book-gallery' ) );
		$button_text = get_option( 'brag_book_gallery_consultation_button_text', __( 'Submit Request', 'brag-book-gallery' ) );
		?>

		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Consultation Settings', 'brag-book-gallery' ); ?></h2>

			<?php settings_errors( $this->page_slug ); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'brag_book_gallery_consultation_settings', 'brag_book_gallery_consultation_nonce' ); ?>

				<table class="form-table brag-book-gallery-form-table">
					<tr>
						<th scope="row">
							<label for="brag_book_gallery_consultation_enabled" class="brag-book-toggle-label">
								<?php esc_html_e( 'Enable Consultation Forms', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_consultation_enabled" value="0" />
								<input type="checkbox"
									   id="brag_book_gallery_consultation_enabled"
									   name="brag_book_gallery_consultation_enabled"
									   value="1"
									   <?php checked( $enabled, true ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Enable consultation request forms on gallery pages.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_consultation_email">
								<?php esc_html_e( 'Notification Email', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<input type="email"
								   id="brag_book_gallery_consultation_email"
								   name="brag_book_gallery_consultation_email"
								   value="<?php echo esc_attr( $notification_email ); ?>"
								   class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Email address to receive consultation notifications.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_consultation_form_title">
								<?php esc_html_e( 'Form Title', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<input type="text"
								   id="brag_book_gallery_consultation_form_title"
								   name="brag_book_gallery_consultation_form_title"
								   value="<?php echo esc_attr( $form_title ); ?>"
								   class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Title displayed above the consultation form.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_consultation_button_text">
								<?php esc_html_e( 'Submit Button Text', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<input type="text"
								   id="brag_book_gallery_consultation_button_text"
								   name="brag_book_gallery_consultation_button_text"
								   value="<?php echo esc_attr( $button_text ); ?>"
								   class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Text for the form submit button.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_consultation_auto_respond" class="brag-book-toggle-label">
								<?php esc_html_e( 'Auto-Response', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_consultation_auto_respond" value="0" />
								<input type="checkbox"
									   id="brag_book_gallery_consultation_auto_respond"
									   name="brag_book_gallery_consultation_auto_respond"
									   value="1"
									   <?php checked( $auto_respond, true ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Send automatic response to consultation requests', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_consultation_response">
								<?php esc_html_e( 'Response Message', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<textarea id="brag_book_gallery_consultation_response"
									  name="brag_book_gallery_consultation_response"
									  rows="5"
									  class="large-text"><?php echo esc_textarea( $response_message ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Message displayed or sent after form submission.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'brag-book-gallery' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save consultation settings
	 *
	 * Processes and saves consultation configuration options.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_consultation_settings(): void {
		// Save enabled state
		$enabled = isset( $_POST['brag_book_gallery_consultation_enabled'] ) && $_POST['brag_book_gallery_consultation_enabled'] === '1';
		update_option( 'brag_book_gallery_consultation_enabled', $enabled );

		// Save notification email
		if ( isset( $_POST['brag_book_gallery_consultation_email'] ) ) {
			$email = sanitize_email( $_POST['brag_book_gallery_consultation_email'] );
			if ( is_email( $email ) ) {
				update_option( 'brag_book_gallery_consultation_email', $email );
			}
		}

		// Save form title
		if ( isset( $_POST['brag_book_gallery_consultation_form_title'] ) ) {
			$form_title = sanitize_text_field( $_POST['brag_book_gallery_consultation_form_title'] );
			update_option( 'brag_book_gallery_consultation_form_title', $form_title );
		}

		// Save button text
		if ( isset( $_POST['brag_book_gallery_consultation_button_text'] ) ) {
			$button_text = sanitize_text_field( $_POST['brag_book_gallery_consultation_button_text'] );
			update_option( 'brag_book_gallery_consultation_button_text', $button_text );
		}

		// Save auto-response settings
		$auto_respond = isset( $_POST['brag_book_gallery_consultation_auto_respond'] ) && $_POST['brag_book_gallery_consultation_auto_respond'] === '1';
		update_option( 'brag_book_gallery_consultation_auto_respond', $auto_respond );

		// Save response message
		if ( isset( $_POST['brag_book_gallery_consultation_response'] ) ) {
			$response = wp_kses_post( $_POST['brag_book_gallery_consultation_response'] );
			update_option( 'brag_book_gallery_consultation_response', $response );
		}

		$this->add_notice( __( 'Consultation settings saved successfully.', 'brag-book-gallery' ), 'success' );
	}

	/**
	 * Render admin page wrapper
	 *
	 * Main entry point for rendering the consultation admin page.
	 * This method is called by the Menu class when registering the page.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render_admin_page(): void {
		// The render method already includes the wrapper
		$this->render();
	}

	/**
	 * Render consultation statistics view
	 *
	 * Displays statistics and analytics for consultation forms.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_consultation_stats(): void {
		// Get stats data
		$total = wp_count_posts( 'form-entries' )->publish ?? 0;

		// Get last 30 days data for chart
		$chart_data = [];
		for ( $i = 29; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$args = array(
				'post_type' => 'form-entries',
				'post_status' => 'publish',
				'date_query' => array(
					array(
						'year' => date( 'Y', strtotime( $date ) ),
						'month' => date( 'n', strtotime( $date ) ),
						'day' => date( 'j', strtotime( $date ) ),
					),
				),
				'posts_per_page' => -1,
			);
			$query = new \WP_Query( $args );
			$chart_data[] = array(
				'date' => date( 'M j', strtotime( $date ) ),
				'count' => $query->found_posts
			);
			wp_reset_postdata();
		}

		// Get monthly data for the year
		$monthly_data = [];
		for ( $i = 11; $i >= 0; $i-- ) {
			$date = date( 'Y-m', strtotime( "-{$i} months" ) );
			$args = array(
				'post_type' => 'form-entries',
				'post_status' => 'publish',
				'date_query' => array(
					array(
						'year' => date( 'Y', strtotime( $date ) ),
						'month' => date( 'n', strtotime( $date ) ),
					),
				),
				'posts_per_page' => -1,
			);
			$query = new \WP_Query( $args );
			$monthly_data[] = array(
				'month' => date( 'M', strtotime( $date ) ),
				'count' => $query->found_posts
			);
			wp_reset_postdata();
		}
		?>
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Consultation Statistics', 'brag-book-gallery' ); ?></h2>

			<div class="brag-book-gallery-case-section-content">
				<!-- Stats Cards -->
				<div class="consultation-stats">
					<div class="stat-card">
						<div class="stat-value"><?php echo esc_html( $total ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Total Consultations', 'brag-book-gallery' ); ?></div>
					</div>

					<div class="stat-card">
						<div class="stat-value">
							<?php
							$args = array(
								'post_type' => 'form-entries',
								'post_status' => 'publish',
								'date_query' => array(
									array( 'after' => '30 days ago' ),
								),
								'posts_per_page' => -1,
							);
							$recent = new \WP_Query( $args );
							echo esc_html( $recent->found_posts );
							wp_reset_postdata();
							?>
						</div>
						<div class="stat-label"><?php esc_html_e( 'Last 30 Days', 'brag-book-gallery' ); ?></div>
					</div>

					<div class="stat-card">
						<div class="stat-value">
							<?php
							$args = array(
								'post_type' => 'form-entries',
								'post_status' => 'publish',
								'date_query' => array(
									array( 'after' => '7 days ago' ),
								),
								'posts_per_page' => -1,
							);
							$week = new \WP_Query( $args );
							echo esc_html( $week->found_posts );
							wp_reset_postdata();
							?>
						</div>
						<div class="stat-label"><?php esc_html_e( 'Last 7 Days', 'brag-book-gallery' ); ?></div>
					</div>

					<div class="stat-card">
						<div class="stat-value">
							<?php
							$args = array(
								'post_type' => 'form-entries',
								'post_status' => 'publish',
								'date_query' => array(
									array( 'after' => 'today' ),
								),
								'posts_per_page' => -1,
							);
							$today = new \WP_Query( $args );
							echo esc_html( $today->found_posts );
							wp_reset_postdata();
							?>
						</div>
						<div class="stat-label"><?php esc_html_e( 'Today', 'brag-book-gallery' ); ?></div>
					</div>
				</div>

				<!-- Charts -->
				<div class="charts-grid">
					<div class="chart-container">
						<h3><?php esc_html_e( 'Daily Consultations (Last 30 Days)', 'brag-book-gallery' ); ?></h3>
						<canvas id="dailyChart" width="400" height="200"></canvas>
					</div>

					<div class="chart-container">
						<h3><?php esc_html_e( 'Monthly Consultations (Last 12 Months)', 'brag-book-gallery' ); ?></h3>
						<canvas id="monthlyChart" width="400" height="200"></canvas>
					</div>
				</div>
			</div>
		</div>

		<!-- Chart.js CDN -->
		<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Daily chart data
			const dailyData = <?php echo json_encode( $chart_data ); ?>;
			const monthlyData = <?php echo json_encode( $monthly_data ); ?>;

			// Daily Chart
			const dailyCtx = document.getElementById('dailyChart')?.getContext('2d');
			if (dailyCtx) {
				new Chart(dailyCtx, {
					type: 'line',
					data: {
						labels: dailyData.map(d => d.date),
						datasets: [{
							label: 'Consultations',
							data: dailyData.map(d => d.count),
							borderColor: '#D94540',
							backgroundColor: 'rgba(217, 69, 64, 0.1)',
							tension: 0.3,
							fill: true
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								display: false
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									stepSize: 1
								}
							}
						}
					}
				});
			}

			// Monthly Chart
			const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
			if (monthlyCtx) {
				new Chart(monthlyCtx, {
					type: 'bar',
					data: {
						labels: monthlyData.map(d => d.month),
						datasets: [{
							label: 'Consultations',
							data: monthlyData.map(d => d.count),
							backgroundColor: '#D94540',
							borderColor: '#D94540',
							borderWidth: 1
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								display: false
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									stepSize: 1
								}
							}
						}
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render consultation export view
	 *
	 * Provides options to export consultation data.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_consultation_export(): void {
		?>
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Export Consultation Data', 'brag-book-gallery' ); ?></h2>

			<p><?php esc_html_e( 'Export your consultation entries in various formats.', 'brag-book-gallery' ); ?></p>

			<h3><?php esc_html_e( 'Export Format', 'brag-book-gallery' ); ?></h3>

			<p>
				<button type="button" class="button button-primary" id="export-csv">
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-328.46 309.23-499.23l42.16-43.38L450-444v-336h60v336l98.61-98.61 42.16 43.38L480-328.46ZM252.31-180Q222-180 201-201q-21-21-21-51.31v-108.46h60v108.46q0 4.62 3.85 8.46 3.84 3.85 8.46 3.85h455.38q4.62 0 8.46-3.85 3.85-3.84 3.85-8.46v-108.46h60v108.46Q780-222 759-201q-21 21-51.31 21H252.31Z"/></svg>
					<?php esc_html_e( 'Export as CSV', 'brag-book-gallery' ); ?>
				</button>
				<span class="description"><?php esc_html_e( 'Download all consultation entries as a CSV file.', 'brag-book-gallery' ); ?></span>
			</p>

			<p>
				<button type="button" class="button" id="export-json">
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-328.46 309.23-499.23l42.16-43.38L450-444v-336h60v336l98.61-98.61 42.16 43.38L480-328.46ZM252.31-180Q222-180 201-201q-21-21-21-51.31v-108.46h60v108.46q0 4.62 3.85 8.46 3.84 3.85 8.46 3.85h455.38q4.62 0 8.46-3.85 3.85-3.84 3.85-8.46v-108.46h60v108.46Q780-222 759-201q-21 21-51.31 21H252.31Z"/></svg>
					<?php esc_html_e( 'Export as JSON', 'brag-book-gallery' ); ?>
				</button>
				<span class="description"><?php esc_html_e( 'Download all consultation entries as a JSON file.', 'brag-book-gallery' ); ?></span>
			</p>

			<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				// CSV Export
				document.getElementById('export-csv')?.addEventListener('click', function() {
					window.location.href = '<?php echo esc_url( admin_url( 'admin-ajax.php?action=export_consultations&format=csv&nonce=' . wp_create_nonce( 'export_consultations' ) ) ); ?>';
				});

				// JSON Export
				document.getElementById('export-json')?.addEventListener('click', function() {
					window.location.href = '<?php echo esc_url( admin_url( 'admin-ajax.php?action=export_consultations&format=json&nonce=' . wp_create_nonce( 'export_consultations' ) ) ); ?>';
				});
			});
			</script>
		</div>
		<?php
	}
}
