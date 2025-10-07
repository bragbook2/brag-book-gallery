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

namespace BRAGBookGallery\Includes\Admin\Pages;

use BRAGBookGallery\Includes\Admin\Core\Settings_Base;

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
class Dashboard_Page extends Settings_Base {

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
						<span class="stat-label"><?php esc_html_e( 'Synced Cases', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<?php echo esc_html( wp_count_posts( 'brag_book_cases' )->publish ?? 0 ); ?>
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
					<div class="action-card">
						<div class="action-card-content">
							<h3><?php esc_html_e( 'Create Gallery Page', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'Create a page with the gallery shortcode to display your cases.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-general' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Configure', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>

					<div class="action-card">
						<div class="action-card-content">
							<h3><?php esc_html_e( 'Sync from BRAG book', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'Synchronize procedures and cases from the BRAG book API.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-sync' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Sync Now', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>

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

		<!-- Gallery Statistics -->
		<?php if ( $has_api ) : ?>
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Gallery Statistics', 'brag-book-gallery' ); ?></h2>
			<div class="brag-book-gallery-stats-grid">
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_posts( 'brag_book_cases' )->publish ?? 0 ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Synced Cases', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_terms( array( 'taxonomy' => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES, 'hide_empty' => false ) ) ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php
						$stage3_status = get_option( 'brag_book_stage3_last_run', array() );
						if ( ! empty( $stage3_status['completed_at'] ) ) {
							echo esc_html( human_time_diff( strtotime( $stage3_status['completed_at'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'brag-book-gallery' ) );
						} else {
							esc_html_e( 'Never', 'brag-book-gallery' );
						}
						?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Last Sync', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php
						// Count pages with the shortcode
						global $wpdb;
						$pages_with_gallery = $wpdb->get_var(
							"SELECT COUNT(*) FROM {$wpdb->posts}
							WHERE post_content LIKE '%[brag_book_gallery%'
							AND post_status = 'publish'
							AND post_type IN ('page', 'post')"
						);
						echo esc_html( $pages_with_gallery );
						?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Gallery Pages', 'brag-book-gallery' ); ?></div>
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

		// Step 2: Create Gallery Page
		global $wpdb;
		$pages_with_shortcode = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_content LIKE '%[brag_book_gallery%'
			AND post_status = 'publish'
			AND post_type IN ('page', 'post')"
		);
		$has_gallery_page = $pages_with_shortcode > 0;

		$steps[] = array(
			'title' => __( 'Create Gallery Page', 'brag-book-gallery' ),
			'description' => __( 'Create a page with the [brag_book_gallery] shortcode to display your cases.', 'brag-book-gallery' ),
			'completed' => $has_gallery_page,
			'action_url' => admin_url( 'post-new.php?post_type=page' ),
			'action_text' => __( 'Create Page', 'brag-book-gallery' ),
		);
		if ( $has_gallery_page ) $completed++;

		// Step 3: Sync Cases
		$has_synced_cases = ( wp_count_posts( 'brag_book_cases' )->publish ?? 0 ) > 0;

		$steps[] = array(
			'title' => __( 'Sync Cases from BRAG book', 'brag-book-gallery' ),
			'description' => __( 'Synchronize your procedures and cases from the BRAG book API.', 'brag-book-gallery' ),
			'completed' => $has_synced_cases,
			'action_url' => admin_url( 'admin.php?page=brag-book-gallery-sync' ),
			'action_text' => __( 'Sync Now', 'brag-book-gallery' ),
		);
		if ( $has_synced_cases ) $completed++;

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
