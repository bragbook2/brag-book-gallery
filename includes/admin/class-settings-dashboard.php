<?php
/**
 * Dashboard Settings Class - Main dashboard and welcome page
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

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Dashboard Settings Class
 *
 * Provides the main dashboard interface for BRAG book Gallery.
 * This class serves as the primary landing page for plugin administration,
 * offering users a welcome experience, setup guidance, and quick access
 * to important features and information.
 *
 * Key functionality:
 * - Welcome message and getting started guide
 * - Setup progress tracking
 * - Quick status overview
 * - Recent activity and statistics
 * - Quick action buttons for common tasks
 * - System status and requirements check
 *
 * The dashboard adapts its content based on:
 * - First-time setup vs returning users
 * - Current configuration state
 * - API connection status
 * - Active mode and features
 *
 * @since 3.0.0
 */
class Settings_Dashboard extends Settings_Base {

	/**
	 * Initialize dashboard settings page
	 *
	 * Sets up the page slug for the main dashboard page, which serves
	 * as the default landing page when users access the plugin settings.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug = 'brag-book-gallery-settings';
	}

	/**
	 * Render the dashboard page
	 *
	 * Displays the main dashboard with welcome message, setup progress,
	 * and quick access to important features.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set localized page titles
		$this->page_title = __( 'Dashboard', 'brag-book-gallery' );
		$this->menu_title = __( 'Dashboard', 'brag-book-gallery' );

		// Get plugin metadata
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '3.0.0';

		// Get current state
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$has_api = ! empty( $api_tokens );
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();

		// Calculate setup progress
		$setup_steps = $this->calculate_setup_progress();

		$this->render_header();
		?>

		<!-- Custom Notices Section -->
		<div class="brag-book-gallery-notices">
			<?php $this->render_custom_notices(); ?>
		</div>

		<!-- Welcome Section -->
		<section class="brag-book-gallery-welcome-hero">
			<div class="welcome-content">
				<h2>
					<?php esc_html_e( 'Welcome to BRAG book Gallery', 'brag-book-gallery' ); ?>
				</h2>
				<p class="welcome-description">
					<?php esc_html_e( 'The most powerful before & after gallery plugin for WordPress. Display stunning medical and cosmetic procedure galleries with confidence and ease.', 'brag-book-gallery' ); ?>
				</p>

				<?php if ( ! $has_api ) : ?>
					<div class="welcome-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Get Started', 'brag-book-gallery' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-help' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View Documentation', 'brag-book-gallery' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<!-- Setup Progress -->
		<?php if ( $setup_steps['total'] > 0 && $setup_steps['completed'] < $setup_steps['total'] ) : ?>
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Setup Progress', 'brag-book-gallery' ); ?></h2>
			<div class="setup-progress">
				<div class="progress-bar">
					<div class="progress-fill" style="width: <?php echo esc_attr( $setup_steps['percentage'] ); ?>%;">
						<span class="progress-text"><?php echo esc_html( $setup_steps['percentage'] ); ?>%</span>
					</div>
				</div>
				<p class="progress-status">
					<?php
					printf(
						/* translators: 1: Number of completed steps, 2: Total number of steps */
						esc_html__( '%1$d of %2$d steps completed', 'brag-book-gallery' ),
						$setup_steps['completed'],
						$setup_steps['total']
					);
					?>
				</p>
			</div>

			<div class="setup-checklist">
				<?php foreach ( $setup_steps['steps'] as $step ) : ?>
				<div class="setup-step <?php echo $step['completed'] ? 'completed' : 'pending'; ?>">
					<span class="step-icon">
						<?php if ( $step['completed'] ) : ?>
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="m423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z"/></svg>
						<?php else : ?>
							<span class="dashicons dashicons-marker"></span>
						<?php endif; ?>
					</span>
					<div class="step-content">
						<h4><?php echo esc_html( $step['title'] ); ?></h4>
						<p><?php echo esc_html( $step['description'] ); ?></p>
						<?php if ( ! $step['completed'] && ! empty( $step['action_url'] ) ) : ?>
							<a href="<?php echo esc_url( $step['action_url'] ); ?>" class="button button-small button-secondary">
								<?php echo esc_html( $step['action_text'] ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Quick Status -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'System Status', 'brag-book-gallery' ); ?></h2>
			<div class="brag-book-gallery-stats-grid">
				<div class="stat-card">
					<div class="stat-header">
						<span class="stat-label"><?php esc_html_e( 'Plugin Version', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<?php echo esc_html( $version ); ?>
						<?php if ( get_option( 'brag_book_gallery_update_available', false ) ) : ?>
							<span class="update-badge"><?php esc_html_e( 'Update Available', 'brag-book-gallery' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-header">
						<span class="stat-label"><?php esc_html_e( 'API Connection', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<?php if ( $has_api ) : ?>
							<span class="status-badge status-success"><?php esc_html_e( 'Connected', 'brag-book-gallery' ); ?></span>
						<?php else : ?>
							<span class="status-badge status-warning"><?php esc_html_e( 'Not Configured', 'brag-book-gallery' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-header">
						<span class="stat-label"><?php esc_html_e( 'Active Mode', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<span class="mode-badge mode-<?php echo esc_attr( $current_mode ); ?>">
							<?php echo esc_html( ucfirst( $current_mode ) ); ?>
						</span>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-header">
						<span class="stat-label"><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<?php echo esc_html( phpversion() ); ?>
						<?php if ( version_compare( phpversion(), '8.2', '>=' ) ) : ?>
							<span class="status-badge status-success">✓</span>
						<?php else : ?>
							<span class="status-badge status-warning"><?php esc_html_e( 'Update Recommended', 'brag-book-gallery' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Quick Actions -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Quick Actions', 'brag-book-gallery' ); ?></h2>
			<div class="action-cards-grid">
				<?php if ( ! $has_api ) : ?>
					<div class="action-card featured">
						<div class="action-card-content">
							<h3><?php esc_html_e( 'Connect to BRAG book', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'Configure your API connection to start displaying galleries.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Configure API', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>
				<?php else : ?>
					<?php if ( $current_mode === 'javascript' ) : ?>
						<div class="action-card">
							<div class="action-card-content">
								<h3><?php esc_html_e( 'JavaScript Settings', 'brag-book-gallery' ); ?></h3>
								<p><?php esc_html_e( 'Configure gallery display and performance settings.', 'brag-book-gallery' ); ?></p>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-javascript' ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'Configure', 'brag-book-gallery' ); ?>
								</a>
							</div>
						</div>
					<?php else : ?>
						<div class="action-card">
							<div class="action-card-content">
								<h3><?php esc_html_e( 'Local Settings', 'brag-book-gallery' ); ?></h3>
								<p><?php esc_html_e( 'Manage sync settings and local gallery data.', 'brag-book-gallery' ); ?></p>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-local' ) ); ?>" class="button button-primary">
									<?php esc_html_e( 'Configure', 'brag-book-gallery' ); ?>
								</a>
							</div>
						</div>
					<?php endif; ?>

					<div class="action-card">
						<div class="action-card-content">
							<h3><?php esc_html_e( 'Test API Connection', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'Verify your API connection and test endpoints.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-test' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'Run Tests', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>
				<?php endif; ?>

				<div class="action-card">
					<div class="action-card-content">
						<h3><?php esc_html_e( 'Shortcode Reference', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Learn how to display galleries using shortcodes.', 'brag-book-gallery' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-help' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View Docs', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>

				<div class="action-card">
					<div class="action-card-content">
						<h3><?php esc_html_e( 'Get Support', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Access documentation and support resources.', 'brag-book-gallery' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-help' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Help Center', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Recent Activity -->
		<?php if ( $current_mode === 'local' ) : ?>
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Gallery Statistics', 'brag-book-gallery' ); ?></h2>
			<div class="brag-book-gallery-stats-grid">
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_posts( 'brag_gallery' )->publish ?? 0 ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Published Galleries', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_terms( 'brag_category' ) ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Categories', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_terms( 'brag_procedure' ) ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php
						$last_sync = get_option( 'brag_book_gallery_last_sync', '' );
						if ( $last_sync ) {
							echo esc_html( human_time_diff( strtotime( $last_sync ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'brag-book-gallery' ) );
						} else {
							esc_html_e( 'Never', 'brag-book-gallery' );
						}
						?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Last Sync', 'brag-book-gallery' ); ?></div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Resources Section -->
		<div class="brag-book-gallery-section" style="display:none;">
			<h2><?php esc_html_e( 'Resources', 'brag-book-gallery' ); ?></h2>
			<div class="resources-grid">
				<div class="resource-card">
					<h3><?php esc_html_e( 'Documentation', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Complete guide to using BRAG book Gallery.', 'brag-book-gallery' ); ?></p>
					<a href="https://bragbookgallery.com/docs" target="_blank"><?php esc_html_e( 'View Docs', 'brag-book-gallery' ); ?> →</a>
				</div>
				<div class="resource-card">
					<h3><?php esc_html_e( 'Video Tutorials', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Watch step-by-step setup and usage videos.', 'brag-book-gallery' ); ?></p>
					<a href="https://bragbookgallery.com/tutorials" target="_blank"><?php esc_html_e( 'Watch Videos', 'brag-book-gallery' ); ?> →</a>
				</div>
				<div class="resource-card">
					<h3><?php esc_html_e( 'Community', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Join our community for tips and support.', 'brag-book-gallery' ); ?></p>
					<a href="https://bragbookgallery.com/community" target="_blank"><?php esc_html_e( 'Join Now', 'brag-book-gallery' ); ?> →</a>
				</div>
				<div class="resource-card">
					<h3><?php esc_html_e( 'Changelog', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'See what\'s new in the latest version.', 'brag-book-gallery' ); ?></p>
					<a href="https://bragbookgallery.com/changelog" target="_blank"><?php esc_html_e( 'View Changes', 'brag-book-gallery' ); ?> →</a>
				</div>
			</div>
		</div>

		<?php
		$this->render_footer();
	}

	/**
	 * Calculate setup progress
	 *
	 * Determines which setup steps have been completed and calculates
	 * the overall progress percentage.
	 *
	 * @since 3.0.0
	 * @return array Setup progress data
	 */
	private function calculate_setup_progress(): array {
		$steps = array();
		$completed = 0;

		// Step 1: API Configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$has_api = ! empty( $api_tokens );
		$steps[] = array(
			'title' => __( 'Connect to BRAG book API', 'brag-book-gallery' ),
			'description' => __( 'Configure your API credentials to connect to BRAG book.', 'brag-book-gallery' ),
			'completed' => $has_api,
			'action_url' => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
			'action_text' => __( 'Configure API', 'brag-book-gallery' ),
		);
		if ( $has_api ) $completed++;

		// Step 2: Select Mode
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();
		$mode_selected = $has_api && ( $current_mode === 'javascript' || $current_mode === 'local' );
		$steps[] = array(
			'title' => __( 'Select Operating Mode', 'brag-book-gallery' ),
			'description' => __( 'Choose between JavaScript or Local mode for your galleries.', 'brag-book-gallery' ),
			'completed' => $mode_selected,
			'action_url' => admin_url( 'admin.php?page=brag-book-gallery-mode' ),
			'action_text' => __( 'Select Mode', 'brag-book-gallery' ),
		);
		if ( $mode_selected ) $completed++;

		// Step 3: Configure Gallery Settings
		$mode_configured = false;
		if ( $mode_selected ) {
			if ( $current_mode === 'javascript' ) {
				// For JavaScript mode, check if gallery page slug is configured
				$gallery_slugs = get_option( 'brag_book_gallery_page_slug', array() );
				// Check if we have at least one non-empty slug
				if ( is_array( $gallery_slugs ) ) {
					$mode_configured = count( array_filter( $gallery_slugs ) ) > 0;
				} else if ( is_string( $gallery_slugs ) ) {
					$mode_configured = ! empty( $gallery_slugs );
				}
				$settings_url = admin_url( 'admin.php?page=brag-book-gallery-javascript' );
			} else {
				// For Local mode, just check if mode is selected (local mode doesn't need additional config)
				$mode_configured = true;
				$settings_url = admin_url( 'admin.php?page=brag-book-gallery-local' );
			}
		}
		$steps[] = array(
			'title' => __( 'Configure Gallery Settings', 'brag-book-gallery' ),
			'description' => sprintf(
				/* translators: %s: Current mode name */
				__( 'Complete the %s mode configuration.', 'brag-book-gallery' ),
				ucfirst( $current_mode )
			),
			'completed' => $mode_configured,
			'action_url' => $settings_url ?? admin_url( 'admin.php?page=brag-book-gallery-mode' ),
			'action_text' => __( 'Configure', 'brag-book-gallery' ),
		);
		if ( $mode_configured ) $completed++;

		// Step 4: Create First Gallery
		$has_gallery = false;
		if ( $current_mode === 'local' ) {
			$gallery_count = wp_count_posts( 'brag_gallery' );
			$has_gallery = ( $gallery_count->publish ?? 0 ) > 0;
		} else if ( $current_mode === 'javascript' ) {
			// For JavaScript mode, check if gallery page exists (page with shortcode)
			global $wpdb;

			// First check for pages with the shortcode
			$pages_with_shortcode = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_content LIKE '%[brag_book_gallery%'
				AND post_status = 'publish'
				AND post_type IN ('page', 'post')"
			);
			$has_gallery = $pages_with_shortcode > 0;

			// If no shortcode found, check if a page exists with the configured slug
			if ( ! $has_gallery ) {
				$gallery_slugs = get_option( 'brag_book_gallery_page_slug', array() );
				if ( ! empty( $gallery_slugs ) ) {
					// Handle both array and string formats
					$slugs_to_check = is_array( $gallery_slugs ) ? $gallery_slugs : array( $gallery_slugs );
					foreach ( $slugs_to_check as $slug ) {
						if ( empty( $slug ) ) continue;
						$page = get_page_by_path( $slug );
						if ( $page && $page->post_status === 'publish' ) {
							// Check if this page has the shortcode
							if ( strpos( $page->post_content, '[brag_book_gallery' ) !== false ) {
								$has_gallery = true;
								break;
							}
						}
					}
				}
			}
		}
		$steps[] = array(
			'title' => __( 'Create Your First Gallery', 'brag-book-gallery' ),
			'description' => __( 'Add your first before & after gallery to a page or post.', 'brag-book-gallery' ),
			'completed' => $has_gallery,
			'action_url' => $current_mode === 'local'
				? admin_url( 'post-new.php?post_type=brag_gallery' )
				: admin_url( 'admin.php?page=brag-book-gallery-help' ),
			'action_text' => $current_mode === 'local'
				? __( 'Create Gallery', 'brag-book-gallery' )
				: __( 'View Guide', 'brag-book-gallery' ),
		);
		if ( $has_gallery ) $completed++;

		$total = count( $steps );
		$percentage = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;

		return array(
			'steps' => $steps,
			'completed' => $completed,
			'total' => $total,
			'percentage' => $percentage,
		);
	}

	/**
	 * Render custom notices in a specific location
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function render_custom_notices(): void {
		// Check if coming from a factory reset
		if ( isset( $_GET['reset'] ) && 'success' === $_GET['reset'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Factory Reset Complete!', 'brag-book-gallery' ); ?></strong></p>
				<p><?php esc_html_e( 'All plugin settings have been reset to their default values.', 'brag-book-gallery' ); ?></p>
			</div>
			<?php
		}
	}
}
