<?php
/**
 * Gallery Page Checker Tool - Enterprise-grade gallery configuration validator
 *
 * Comprehensive gallery page validation and management system for BRAGBook Gallery.
 * Provides advanced diagnostics, automatic fixes, and configuration optimization
 * with performance tracking and security hardening.
 *
 * Features:
 * - Real-time gallery page validation
 * - Automatic page creation and configuration
 * - Rewrite rule analysis and debugging
 * - Shortcode detection and validation
 * - SEO metadata integration
 * - Performance metrics tracking
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @package    BragBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @author     BRAGBook Development Team
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Admin\Debug;

use Exception;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enterprise Gallery Configuration Checker Class
 *
 * Orchestrates comprehensive gallery validation operations:
 *
 * Core Responsibilities:
 * - Gallery page validation and health checks
 * - Automatic page creation and repair
 * - Rewrite rule debugging and optimization
 * - Shortcode validation and management
 * - Configuration synchronization
 * - Performance monitoring
 *
 * @since 3.0.0
 */
class Gallery_Checker {

	/**
	 * Configuration constants
	 *
	 * @since 3.0.0
	 */
	private const DEFAULT_PAGE_TITLE = 'Before & After Gallery';
	private const DEFAULT_PAGE_SLUG = 'before-after';
	private const SHORTCODE_NAME = 'brag_book_gallery';
	private const MAX_RULE_DISPLAY = 50;

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
	 * Validation errors
	 *
	 * @since 3.0.0
	 * @var array<string, string[]>
	 */
	private array $validation_errors = [];

	/**
	 * Cache for expensive operations
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $cache = [];

	/**
	 * Get status icon with proper escaping
	 *
	 * @since 3.0.0
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML.
	 */
	private function get_check_icon( bool $success = true ): string {
		$color = $success ? '#4caf50' : '#f44336';
		$path = $success
			? 'm423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z'
			: 'M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z';

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="%s" style="vertical-align: middle; margin-right: 5px;"><path d="%s"/></svg>',
			esc_attr( $color ),
			esc_attr( $path )
		);
	}

	/**
	 * Get warning icon with proper escaping
	 *
	 * @since 3.0.0
	 * @return string SVG HTML.
	 */
	private function get_warning_icon(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ff9800" style="vertical-align: middle; margin-right: 5px;"><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
	}

	/**
	 * Render the checker tool interface
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="gallery-checker-tool">
			<h2><?php esc_html_e( 'Gallery Page Setup Check', 'brag-book-gallery' ); ?></h2>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Available Gallery Pages', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_available_pages(); ?>

				<hr/>

				<h3><?php esc_html_e( 'Page Status', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_page_status(); ?>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', () => {
			// Helper function for AJAX requests using async/await
			const ajaxPost = async (data) => {
				const formData = new FormData();
				Object.entries(data).forEach(([key, value]) => {
					formData.append(key, value);
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

			// Create gallery page
			document.getElementById('create-gallery-page')?.addEventListener('click', async function() {
				if (!confirm('<?php esc_html_e( 'This will create a new page with the [brag_book_gallery] shortcode. Continue?', 'brag-book-gallery' ); ?>')) {
					return;
				}

				this.disabled = true;
				const resultDiv = document.getElementById('gallery-action-result');
				if (resultDiv) {
					resultDiv.innerHTML = '<p><?php esc_html_e( 'Creating gallery page...', 'brag-book-gallery' ); ?></p>';
				}

				const response = await ajaxPost({
					action: 'brag_book_gallery_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_gallery_debug_tools' ); ?>',
					tool: 'gallery-checker',
					tool_action: 'create_page'
				});

				if (resultDiv) {
					if (response.success) {
						resultDiv.innerHTML = `<div class="notice notice-success"><p>${response.data}</p></div>`;
						setTimeout(() => location.reload(), 2000);
					} else {
						resultDiv.innerHTML = `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`;
					}
				}
				this.disabled = false;
			});

			// Update gallery slug
			document.getElementById('update-gallery-slug')?.addEventListener('click', async function() {
				const newSlug = prompt('<?php esc_html_e( 'Enter the slug of an existing page that contains [brag_book_gallery]:', 'brag-book-gallery' ); ?>');
				if (!newSlug) return;

				this.disabled = true;
				const resultDiv = document.getElementById('gallery-action-result');
				if (resultDiv) {
					resultDiv.innerHTML = '<p><?php esc_html_e( 'Updating gallery slug...', 'brag-book-gallery' ); ?></p>';
				}

				const response = await ajaxPost({
					action: 'brag_book_gallery_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_gallery_debug_tools' ); ?>',
					tool: 'gallery-checker',
					tool_action: 'update_slug',
					gallery_slug: newSlug
				});

				if (resultDiv) {
					if (response.success) {
						resultDiv.innerHTML = `<div class="notice notice-success"><p>${response.data}</p></div>`;
						setTimeout(() => location.reload(), 2000);
					} else {
						resultDiv.innerHTML = `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`;
					}
				}
				this.disabled = false;
			});

			// Show gallery rules
			document.getElementById('show-gallery-rules')?.addEventListener('click', async function() {
				this.disabled = true;
				const displayDiv = document.getElementById('gallery-rules-display');
				if (displayDiv) {
					displayDiv.innerHTML = '<p><?php esc_html_e( 'Loading rules...', 'brag-book-gallery' ); ?></p>';
				}

				const response = await ajaxPost({
					action: 'brag_book_gallery_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_gallery_debug_tools' ); ?>',
					tool: 'gallery-checker',
					tool_action: 'show_rules'
				});

				if (displayDiv) {
					if (response.success) {
						displayDiv.innerHTML = response.data;
					} else {
						displayDiv.innerHTML = `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`;
					}
				}
				this.disabled = false;
			});
		});
		</script>
		<?php
	}

	/**
	 * Render current settings with comprehensive validation
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_current_settings(): void {
		try {
			// Get gallery page slug from option
			$brag_book_gallery_page_slug = get_option( 'brag_book_gallery_page_slug', '' );
			$brag_book_gallery_page_id = absint( get_option( 'brag_book_gallery_page_id', 0 ) );
			$page = $brag_book_gallery_page_id ? get_post( $brag_book_gallery_page_id ) : null;

			// Fallback: If no page found from options, search for pages with shortcode
			$found_via_fallback = false;
			if ( ! $page && empty( $brag_book_gallery_page_slug ) ) {
				global $wpdb;
				$shortcode_pattern = '%[' . self::SHORTCODE_NAME . '%';
				$found_page = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID, post_name, post_title, post_status, post_modified
						FROM {$wpdb->posts}
						WHERE post_content LIKE %s
						AND post_type = 'page'
						AND post_status = 'publish'
						ORDER BY post_modified DESC
						LIMIT 1",
						$shortcode_pattern
					)
				);

				if ( $found_page ) {
					$page = get_post( $found_page->ID );
					$brag_book_gallery_page_slug = $found_page->post_name;
					$found_via_fallback = true;
				}
			}

			$page_url = $page ? get_permalink( $page->ID ) : null;
			$edit_link = $page ? get_edit_post_link( $page->ID ) : null;
		?>
		<div class="gallery-config-cards">
			<!-- Gallery Page Card -->
			<div class="config-card">
				<div class="config-card-header">
					<div class="config-card-icon <?php echo $brag_book_gallery_page_slug ? 'icon-active' : 'icon-inactive'; ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
							<polyline points="14 2 14 8 20 8"></polyline>
						</svg>
					</div>
					<h4 class="config-card-title"><?php esc_html_e( 'Gallery Page', 'brag-book-gallery' ); ?></h4>
				</div>
				<div class="config-card-content">
					<?php if ( $brag_book_gallery_page_slug ) : ?>
						<div class="config-item">
							<span class="config-label"><?php esc_html_e( 'Slug:', 'brag-book-gallery' ); ?></span>
							<code class="config-value">/<?php echo esc_html( $brag_book_gallery_page_slug ); ?>/</code>
						</div>
						<?php if ( $page ) : ?>
							<div class="config-item">
								<span class="config-label"><?php esc_html_e( 'Title:', 'brag-book-gallery' ); ?></span>
								<span class="config-value"><?php echo esc_html( $page->post_title ); ?></span>
							</div>
							<div class="config-item">
								<span class="config-label"><?php esc_html_e( 'ID:', 'brag-book-gallery' ); ?></span>
								<span class="config-value-badge">#<?php echo esc_html( $page->ID ); ?></span>
							</div>
							<?php if ( $found_via_fallback ) : ?>
								<div class="config-item">
									<span class="config-label"><?php esc_html_e( 'Status:', 'brag-book-gallery' ); ?></span>
									<span class="config-value" style="color: #ff9800;"><?php esc_html_e( 'Found but not configured', 'brag-book-gallery' ); ?></span>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					<?php else : ?>
						<div class="config-empty">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<circle cx="12" cy="12" r="10"></circle>
								<line x1="12" y1="8" x2="12" y2="12"></line>
								<line x1="12" y1="16" x2="12.01" y2="16"></line>
							</svg>
							<span><?php esc_html_e( 'No gallery page configured', 'brag-book-gallery' ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $page_url || $edit_link ) : ?>
						<div class="config-actions">
							<?php if ( $page_url ) : ?>
								<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" class="config-link">
									<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $edit_link ) : ?>
								<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank" class="config-link">
									<?php esc_html_e( 'Edit', 'brag-book-gallery' ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Shortcode Status Card -->
			<div class="config-card">
				<div class="config-card-header">
					<div class="config-card-icon <?php echo ( $page && str_contains( $page->post_content, '[' . self::SHORTCODE_NAME ) ) ? 'icon-active' : 'icon-inactive'; ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="16 18 22 12 16 6"></polyline>
							<polyline points="8 6 2 12 8 18"></polyline>
						</svg>
					</div>
					<h4 class="config-card-title"><?php esc_html_e( 'Shortcode Status', 'brag-book-gallery' ); ?></h4>
				</div>
				<div class="config-card-content">
					<?php if ( $page ) : ?>
						<?php if ( str_contains( $page->post_content, '[' . self::SHORTCODE_NAME ) ) : ?>
							<div class="config-status-success">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
									<polyline points="22 4 12 14.01 9 11.01"></polyline>
								</svg>
								<span><?php esc_html_e( 'Shortcode found', 'brag-book-gallery' ); ?></span>
							</div>
							<div class="config-code">
								<code>[brag_book_gallery]</code>
							</div>
							<?php if ( $found_via_fallback ) : ?>
								<div class="config-status-warning">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
										<line x1="12" y1="9" x2="12" y2="13"></line>
										<line x1="12" y1="17" x2="12.01" y2="17"></line>
									</svg>
									<span><?php esc_html_e( 'Page found but not configured', 'brag-book-gallery' ); ?></span>
								</div>
								<p class="config-hint"><?php esc_html_e( 'Use "Update Gallery Slug" button below to configure this page properly', 'brag-book-gallery' ); ?></p>
							<?php endif; ?>
						<?php else : ?>
							<div class="config-status-warning">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
									<line x1="12" y1="9" x2="12" y2="13"></line>
									<line x1="12" y1="17" x2="12.01" y2="17"></line>
								</svg>
								<span><?php esc_html_e( 'Shortcode missing', 'brag-book-gallery' ); ?></span>
							</div>
							<p class="config-hint"><?php esc_html_e( 'Add [brag_book_gallery] to the page content', 'brag-book-gallery' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<div class="config-empty">
							<span><?php esc_html_e( 'No page to check', 'brag-book-gallery' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Page Status Card -->
			<div class="config-card">
				<div class="config-card-header">
					<div class="config-card-icon <?php echo ( $page && $page->post_status === 'publish' ) ? 'icon-active' : 'icon-inactive'; ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="10"></circle>
							<polyline points="12 6 12 12 16 14"></polyline>
						</svg>
					</div>
					<p class="config-card-title"><?php esc_html_e( 'Page Status', 'brag-book-gallery' ); ?></p>
				</div>
				<div class="config-card-content">
					<?php if ( $page ) : ?>
						<div class="config-status-badge status-<?php echo esc_attr( $page->post_status ); ?>">
							<?php echo esc_html( ucfirst( $page->post_status ) ); ?>
						</div>
						<?php if ( $page->post_status !== 'publish' ) : ?>
							<p class="config-hint"><?php esc_html_e( 'Page must be published to work', 'brag-book-gallery' ); ?></p>
						<?php else : ?>
							<div class="config-item">
								<span class="config-label"><?php esc_html_e( 'Modified:', 'brag-book-gallery' ); ?></span>
								<span class="config-value"><?php
									if ( ! empty( $page->post_modified ) && $page->post_modified !== '0000-00-00 00:00:00' ) {
										echo esc_html( human_time_diff( strtotime( $page->post_modified ), current_time( 'timestamp' ) ) . ' ago' );
									} else {
										esc_html_e( 'Unknown', 'brag-book-gallery' );
									}
								?></span>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="config-empty">
							<span><?php esc_html_e( 'No page configured', 'brag-book-gallery' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		} catch ( Exception $e ) {
			error_log( 'Gallery Checker render_available_pages error: ' . $e->getMessage() );
			// Continue with basic display
		}
	}

	/**
	 * Render page status with detailed diagnostics
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_page_status(): void {
		// Get gallery page slug from option
		$brag_book_gallery_page_slug = get_option( 'brag_book_gallery_page_slug', '' );
		$page = null;
		$found_via_fallback = false;

		if ( $brag_book_gallery_page_slug ) {
			$page = get_page_by_path( $brag_book_gallery_page_slug );
		} else {
			// Fallback: If no slug configured, search for pages with shortcode
			global $wpdb;
			$shortcode_pattern = '%[' . self::SHORTCODE_NAME . '%';
			$found_page = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT ID, post_name, post_title, post_status, post_modified
					FROM {$wpdb->posts}
					WHERE post_content LIKE %s
					AND post_type = 'page'
					AND post_status = 'publish'
					ORDER BY post_modified DESC
					LIMIT 1",
					$shortcode_pattern
				)
			);

			if ( $found_page ) {
				$page = get_post( $found_page->ID );
				$brag_book_gallery_page_slug = $found_page->post_name;
				$found_via_fallback = true;
			}
		}

		if ( ! $brag_book_gallery_page_slug && ! $found_via_fallback ) {
			echo '<p style="color: orange;">' . $this->get_warning_icon() . esc_html__( 'No gallery slug configured', 'brag-book-gallery' ) . '</p>';
			return;
		}

		if ( $page ) {
			echo '<p style="color: green;">' . $this->get_check_icon( true ) . sprintf(
				/* translators: %1$s: page slug, %2$d: page ID, %3$s: page title */
				esc_html__( 'Found page with slug "%1$s" (ID: %2$d, Title: %3$s)', 'brag-book-gallery' ),
				esc_html( $brag_book_gallery_page_slug ),
				$page->ID,
				esc_html( $page->post_title )
			) . '</p>';

			// Check for shortcode
			if ( str_contains( $page->post_content, '[' . self::SHORTCODE_NAME ) ) {
				echo '<p style="color: green;">' . $this->get_check_icon( true ) . esc_html__( 'Page contains [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</p>';
			} else {
				echo '<p style="color: orange;">' . $this->get_warning_icon() . esc_html__( 'Page does NOT contain [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</p>';
				echo '<p>' . esc_html__( 'You need to add the shortcode to this page for it to work.', 'brag-book-gallery' ) . '</p>';
			}

			// Show page URL
			$page_url = get_permalink( $page->ID );
			if ( $page_url ) {
				echo '<p>' . esc_html__( 'Page URL:', 'brag-book-gallery' ) . ' <a href="' . esc_url( $page_url ) . '" target="_blank">' . esc_url( $page_url ) . '</a></p>';
			}

			// Show warning if found via fallback
			if ( $found_via_fallback ) {
				echo '<p style="color: orange;">' . $this->get_warning_icon() . esc_html__( 'Warning: This page was found automatically but is not properly configured in settings.', 'brag-book-gallery' ) . '</p>';
				echo '<p>' . esc_html__( 'Use the "Update Gallery Slug" button below to configure this page properly.', 'brag-book-gallery' ) . '</p>';
			}
		} else {
			echo '<p style="color: red;">' . $this->get_check_icon( false ) . sprintf(
				/* translators: %s: gallery slug */
				esc_html__( 'No page found with slug "%s"', 'brag-book-gallery' ),
				esc_html( $brag_book_gallery_page_slug )
			) . '</p>';

			echo '<p><strong>' . esc_html__( 'This is the problem!', 'brag-book-gallery' ) . '</strong> ' . esc_html__( 'You need to either:', 'brag-book-gallery' ) . '</p>';
			echo '<ol>';
			echo '<li>' . sprintf(
				/* translators: %s: gallery slug */
				esc_html__( 'Create a page with the slug "%s" and add [brag_book_gallery] shortcode to it', 'brag-book-gallery' ),
				esc_html( $brag_book_gallery_page_slug )
			) . '</li>';
			echo '<li>' . esc_html__( 'OR use an existing page slug that has the [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</li>';
			echo '</ol>';
		}
	}

	/**
	 * Render available pages with shortcode using secure queries
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_available_pages(): void {
		try {
			global $wpdb;

			$shortcode_pattern = '%[' . self::SHORTCODE_NAME . '%';
			$pages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_name, post_title, post_status, post_modified
					FROM {$wpdb->posts}
					WHERE post_content LIKE %s
					AND post_type = 'page'
					ORDER BY post_status DESC, post_title ASC",
					$shortcode_pattern
				)
			);

		if ( empty( $pages ) ) {
			echo '<p style="color: red;">' . esc_html__( 'No pages found with the [brag_book_gallery] shortcode!', 'brag-book-gallery' ) . '</p>';
			echo '<p>' . esc_html__( 'You need to create a page and add the [brag_book_gallery] shortcode to it.', 'brag-book-gallery' ) . '</p>';
			return;
		}
		?>
		<div class="gallery-pages-cards">
			<?php foreach ( $pages as $page ) : ?>
				<?php
				$view_link = get_permalink( $page->ID );
				$edit_link = get_edit_post_link( $page->ID );
				$is_published = ( $page->post_status === 'publish' );
				?>
				<div class="page-card">
					<div class="page-card-header">
						<div class="page-card-icon <?php echo $is_published ? 'icon-active' : 'icon-inactive'; ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
								<polyline points="14 2 14 8 20 8"></polyline>
							</svg>
						</div>
						<div class="page-card-title">
							<h4><?php echo esc_html( $page->post_title ); ?></h4>
							<span class="page-card-id">#<?php echo esc_html( $page->ID ); ?></span>
						</div>
						<div class="page-card-status">
							<span class="config-status-badge status-<?php echo esc_attr( $page->post_status ); ?>">
								<?php echo esc_html( ucfirst( $page->post_status ) ); ?>
							</span>
						</div>
					</div>
					<div class="page-card-content">
						<div class="config-item">
							<span class="config-label"><?php esc_html_e( 'Slug:', 'brag-book-gallery' ); ?></span>
							<code class="config-value">/<?php echo esc_html( $page->post_name ); ?>/</code>
						</div>
						<div class="config-item">
							<span class="config-label"><?php esc_html_e( 'Modified:', 'brag-book-gallery' ); ?></span>
							<span class="config-value"><?php
								if ( ! empty( $page->post_modified ) && $page->post_modified !== '0000-00-00 00:00:00' ) {
									echo esc_html( human_time_diff( strtotime( $page->post_modified ), current_time( 'timestamp' ) ) . ' ago' );
								} else {
									esc_html_e( 'Unknown', 'brag-book-gallery' );
								}
							?></span>
						</div>

						<?php if ( $view_link || $edit_link ) : ?>
							<div class="config-actions">
								<?php if ( $view_link ) : ?>
									<a href="<?php echo esc_url( $view_link ); ?>" target="_blank" class="config-link">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
											<polyline points="15 3 21 3 21 9"></polyline>
											<line x1="10" y1="14" x2="21" y2="3"></line>
										</svg>
										<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
									</a>
								<?php endif; ?>
								<?php if ( $edit_link ) : ?>
									<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank" class="config-link">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
											<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
										</svg>
										<?php esc_html_e( 'Edit', 'brag-book-gallery' ); ?>
									</a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		} catch ( Exception $e ) {
			error_log( 'Gallery Checker render_available_pages error: ' . $e->getMessage() );
			// Continue with basic display
		}
	}

	/**
	 * Execute tool actions via AJAX with security validation.
	 *
	 * Handles all AJAX requests for gallery checker operations.
	 *
	 * @since 3.0.0
	 * @param string $action Action to execute.
	 * @param array  $data   Request data from AJAX.
	 * @return mixed Response data for AJAX.
	 * @throws Exception If action is invalid or unauthorized.
	 */
	public function execute( string $action, array $data ): mixed {
		try {
			// Validate user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( __( 'Insufficient permissions', 'brag-book-gallery' ) );
			}

			return match ( $action ) {
				'create_page'  => $this->create_gallery_page(),
				'update_slug'  => $this->update_gallery_slug( $data['gallery_slug'] ?? '' ),
				'show_rules'   => $this->show_gallery_rules(),
				default        => throw new Exception( 'Invalid action: ' . $action ),
			};

		} catch ( Exception $e ) {
			error_log( 'Gallery Checker execute error: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Create a new gallery page with SEO optimization
	 *
	 * @since 3.0.0
	 * @return string Success message.
	 * @throws Exception If page creation fails.
	 */
	private function create_gallery_page(): string {
		try {
			$page_title = apply_filters( 'brag_book_gallery_default_page_title', self::DEFAULT_PAGE_TITLE );
			$page_slug  = apply_filters( 'brag_book_gallery_default_page_slug', self::DEFAULT_PAGE_SLUG );

			// Check if page already exists
			$existing = get_page_by_path( $page_slug );
			if ( $existing ) {
				return __( 'A page with this slug already exists!', 'brag-book-gallery' );
			}

			// Create the page
			$page_data = [
				'post_title'     => $page_title,
				'post_name'      => $page_slug,
				'post_content'   => '[' . self::SHORTCODE_NAME . ']',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => get_current_user_id(),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			];

			$page_id = wp_insert_post( $page_data, true );

			if ( is_wp_error( $page_id ) ) {
				return $page_id->get_error_message();
			}

			// Add SEO meta fields if they exist
		$seo_title = get_option( 'brag_book_gallery_seo_page_title', '' );
		$seo_description = get_option( 'brag_book_gallery_seo_page_description', '' );

		if ( ! empty( $seo_title ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_title', $seo_title );
			update_post_meta( $page_id, '_aioseo_title', $seo_title );
			update_post_meta( $page_id, '_seopress_titles_title', $seo_title );
			update_post_meta( $page_id, '_rank_math_title', $seo_title );
		}

		if ( ! empty( $seo_description ) ) {
			update_post_meta( $page_id, '_yoast_wpseo_metadesc', $seo_description );
			update_post_meta( $page_id, '_aioseo_description', $seo_description );
			update_post_meta( $page_id, '_seopress_titles_desc', $seo_description );
			update_post_meta( $page_id, '_rank_math_description', $seo_description );
		}

			// Update options
			update_option( 'brag_book_gallery_page_slug', $page_slug );
			update_option( 'brag_book_gallery_page_id', $page_id );

			// Flush rewrite rules
			flush_rewrite_rules( true );

			return sprintf(
				/* translators: %1$s: page title, %2$d: page ID */
				__( 'Gallery page "%1$s" created successfully (ID: %2$d). Rewrite rules have been flushed.', 'brag-book-gallery' ),
				$page_title,
				$page_id
			);

		} catch ( Exception $e ) {
			error_log( 'Gallery Checker create_gallery_page error: ' . $e->getMessage() );
			return __( 'Failed to create gallery page', 'brag-book-gallery' );
		}
	}

	/**
	 * Update gallery slug
	 *
	 * @param string $slug New gallery slug.
	 * @return string
	 */
	private function update_gallery_slug( string $slug ): string {
		$slug = sanitize_title( $slug );

		if ( empty( $slug ) ) {
			return __( 'Invalid slug provided', 'brag-book-gallery' );
		}

		// Check if page exists
		$page = get_page_by_path( $slug );
		if ( ! $page ) {
			return sprintf(
				/* translators: %s: page slug */
				__( 'No page found with slug "%s"', 'brag-book-gallery' ),
				$slug
			);
		}

		// Check for shortcode
		if ( str_contains( $page->post_content, '[' . self::SHORTCODE_NAME ) === false ) {
			return __( 'The selected page does not contain the [brag_book_gallery] shortcode', 'brag-book-gallery' );
		}

		// Update options
		update_option( 'brag_book_gallery_page_slug', $slug );
		update_option( 'brag_book_gallery_page_id', $page->ID );

		// Flush rewrite rules
		flush_rewrite_rules( true );

		return sprintf(
			/* translators: %s: page slug */
			__( 'Gallery slug updated to "%s" and rewrite rules flushed.', 'brag-book-gallery' ),
			$slug
		);
	}

	/**
	 * Show gallery-related rewrite rules with enhanced formatting
	 *
	 * @since 3.0.0
	 * @return string HTML output of rewrite rules.
	 */
	private function show_gallery_rules(): string {
		global $wp_rewrite;

		// Get gallery page slug from option
		$brag_book_gallery_page_slug = get_option( 'brag_book_gallery_page_slug', '' );
		$rules = $wp_rewrite->wp_rewrite_rules();

		$output = '<div class="rewrite-rules-display">';
		$output .= '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 400px; font-family: monospace;">';

		// Debug info
		$output .= sprintf(
			"<strong>Debug Info:</strong>\n" .
			"Gallery Slug: %s\n" .
			"Total Rewrite Rules: %d\n\n",
			$brag_book_gallery_page_slug ? htmlspecialchars( $brag_book_gallery_page_slug ) : 'Not configured',
			is_array( $rules ) ? count( $rules ) : 0
		);

		$found = false;
		$rule_count = 0;

		// Ensure $rules is an array before iterating
		if ( is_array( $rules ) ) {
			foreach ( $rules as $pattern => $query ) {
				// Look for gallery-related rules more broadly
				$is_gallery_rule = false;

				if ( $brag_book_gallery_page_slug ) {
					// Check if pattern starts with slug or contains gallery slug
					$is_gallery_rule = str_starts_with( $pattern, $brag_book_gallery_page_slug ) ||
					                  str_contains( $pattern, $brag_book_gallery_page_slug ) ||
					                  str_contains( $query, $brag_book_gallery_page_slug );
				}

				// Also show any brag_book related rules
				if ( ! $is_gallery_rule ) {
					$is_gallery_rule = str_contains( $pattern, 'brag' ) ||
					                  str_contains( $query, 'brag' ) ||
					                  str_contains( $query, 'gallery' );
				}

				if ( $is_gallery_rule ) {
					$output .= sprintf(
						"<span style='color: #2271b1;'>%s</span>\n    => <span style='color: #135e96;'>%s</span>\n\n",
						htmlspecialchars( $pattern ),
						htmlspecialchars( $query )
					);
					$found = true;
					$rule_count++;

					if ( $rule_count >= self::MAX_RULE_DISPLAY ) {
						$output .= sprintf(
							'<em>%s</em>',
							__( '... and more rules (limit reached)', 'brag-book-gallery' )
						);
						break;
					}
				}
			}
		}

		if ( ! $found ) {
			$output .= '<span style="color: #d63638;">';
			$output .= __( 'No rewrite rules found for the current gallery slug.', 'brag-book-gallery' );
			if ( ! $brag_book_gallery_page_slug ) {
				$output .= "\n" . __( 'Gallery slug is not configured.', 'brag-book-gallery' );
			}
			$output .= '</span>';
		} else {
			$output = sprintf(
				'<div class="rule-summary" style="margin-bottom: 10px;"><strong>%s</strong></div>',
				sprintf(
					/* translators: %d: number of rules found */
					_n( 'Found %d gallery rule', 'Found %d gallery rules', $rule_count, 'brag-book-gallery' ),
					$rule_count
				)
			) . $output;
		}

		$output .= '</pre>';
		$output .= '</div>';

		return $output;
	}

}
