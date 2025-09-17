<?php
/**
 * General Settings Class - Manages general plugin settings
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
 * General Settings Class
 *
 * Manages the main configuration options for BRAG Book Gallery plugin.
 * This class provides a comprehensive settings interface for controlling
 * gallery display, image handling, performance optimization, and advanced
 * plugin features.
 *
 * Key functionality:
 * - Gallery display configuration (columns, items per page, sharing, lightbox, filtering)
 * - Image settings management (thumbnail size, lazy loading, quality)
 * - Performance optimization options (caching, CDN, asset minification)
 * - Advanced settings (custom CSS, debug mode, uninstall behavior)
 *
 * The settings are organized into logical sections:
 * - Gallery Display Settings - Controls how galleries appear to users
 * - Image Settings - Manages image handling and optimization
 * - Performance Settings - Configures caching and optimization features
 * - Advanced Settings - Provides developer tools and custom configurations
 *
 * All settings are stored using WordPress options API and include proper
 * sanitization and validation to ensure data integrity.
 *
 * @since 3.0.0
 */
class General_Page extends Settings_Base {

	/**
	 * Initialize general settings page configuration
	 *
	 * Sets up the page slug for the general settings interface.
	 * This page provides comprehensive configuration options for
	 * gallery display, image handling, performance, and advanced features.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug  = 'brag-book-gallery-general';
		// Don't translate here - translations happen in render method
	}

	/**
	 * Render the general settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set localized page titles now that translation functions are available
		$this->page_title = __( 'General Settings', 'brag-book-gallery' );
		$this->menu_title = __( 'General', 'brag-book-gallery' );

		// Enqueue Monaco Editor for CSS editing
		wp_enqueue_script(
			'monaco-editor',
			'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js',
			array(),
			'0.45.0',
			true
		);

		// Debug form submission
		error_log( "BRAGBook Debug - POST submit: " . ( isset( $_POST['submit'] ) ? 'TRUE' : 'FALSE' ) );
		error_log( "BRAGBook Debug - POST submit_css: " . ( isset( $_POST['submit_css'] ) ? 'TRUE' : 'FALSE' ) );
		error_log( "BRAGBook Debug - POST submit_default: " . ( isset( $_POST['submit_default'] ) ? 'TRUE' : 'FALSE' ) );

		// Handle form submission
		if ( ( isset( $_POST['submit'] ) || isset( $_POST['submit_css'] ) ) && $this->save_settings( 'brag_book_gallery_general_settings', 'brag_book_gallery_general_nonce' ) ) {
			error_log( "BRAGBook Debug - Calling save_general_settings()" );
			$this->save_general_settings();
		}

		// Handle default settings form submission
		if ( isset( $_POST['submit_default'] ) && $this->save_settings( 'brag_book_gallery_default_settings', 'brag_book_gallery_default_nonce' ) ) {
			$this->save_default_settings();
		}

		// Handle mode switch submission
		if ( isset( $_POST['switch_mode'] ) ) {
			$this->handle_mode_switch();
		}

		$this->render_header();
		?>

		<!-- Custom Notices Section -->
		<div class="brag-book-gallery-notices">
			<?php $this->render_custom_notices(); ?>
		</div>

		<!-- General Settings with Side Tabs -->
		<div class="brag-book-gallery-tabbed-section">
			<?php $this->render_side_tabs(); ?>
			<div class="brag-book-gallery-tab-content">
				<?php $this->render_tab_content(); ?>
			</div>
		</div>


		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Tab switching functionality
			const tabLinks = document.querySelectorAll('.brag-book-gallery-side-tabs a');
			const tabPanels = document.querySelectorAll('.brag-book-gallery-tab-panel');

			tabLinks.forEach(link => {
				link.addEventListener('click', function(e) {
					e.preventDefault();

					// Remove active class from all tabs and panels
					tabLinks.forEach(l => l.classList.remove('active'));
					tabPanels.forEach(p => p.classList.remove('active'));

					// Add active class to clicked tab
					this.classList.add('active');

					// Show corresponding panel
					const targetId = this.getAttribute('href').substring(1);
					const targetPanel = document.getElementById(targetId);
					if (targetPanel) {
						targetPanel.classList.add('active');
					}
				});
			});

			// Set default active tab
			const defaultTab = document.querySelector('.brag-book-gallery-side-tabs a[href="#display-settings"]');
			const defaultPanel = document.getElementById('display-settings');
			if (defaultTab && defaultPanel) {
				defaultTab.classList.add('active');
				defaultPanel.classList.add('active');
			}
		});

		// Page slug functionality
		(function() {
			'use strict';

			let checkSlugTimeout = null;
			let currentSlugExists = false;
			let canGeneratePage = false;

			// DOM elements
			const elements = {
				slugInput: document.getElementById('brag_book_gallery_page_slug'),
				slugStatus: document.getElementById('slug-status-message'),
				generateBtn: document.getElementById('generate-page-btn')
			};

			/**
			 * Check slug availability via AJAX
			 *
			 * @since 3.0.0
			 */
			const checkSlugAvailability = async () => {
				if (!elements.slugInput || !elements.slugStatus || !elements.generateBtn) return;

				const slug = elements.slugInput.value.trim();

				if (!slug) {
					elements.slugStatus.className = 'slug-status';
					elements.slugStatus.textContent = '';
					elements.generateBtn.disabled = true;
					currentSlugExists = false;
					canGeneratePage = false;
					return;
				}

				// Show checking message
				elements.slugStatus.className = 'slug-status';
				elements.slugStatus.textContent = '<?php esc_html_e( 'Checking...', 'brag-book-gallery' ); ?>';
				elements.slugStatus.style.display = 'block';
				elements.generateBtn.disabled = true;

				if (typeof ajaxurl === 'undefined') return;

				try {
					// Check if page exists via AJAX with timeout
					const formData = new FormData();
					formData.append('action', 'brag_book_gallery_check_slug');
					formData.append('slug', slug);
					formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_check_slug' ); ?>');

					// Create AbortController for timeout handling
					const controller = new AbortController();
					const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout

					const response = await fetch(ajaxurl, {
						method: 'POST',
						body: formData,
						signal: controller.signal
					});

					clearTimeout(timeoutId);

					const data = await response.json();

					if (data.success) {
						currentSlugExists = data.data.exists;
						canGeneratePage = data.data.can_generate || false;
						const responseType = data.data.response_type || 'info';
						const needsShortcode = data.data.needs_shortcode || false;
						const editLink = data.data.edit_link || '';

						// Set status class based on response type
						elements.slugStatus.className = `slug-status ${responseType}`;

						// Set appropriate icon based on response type
						let icon = '';
						switch (responseType) {
							case 'success':
								icon = '✓';
								break;
							case 'warning':
								icon = '⚠️';
								break;
							case 'error':
								icon = '❌';
								break;
							default:
								icon = 'ℹ️';
						}

						// Build status message with optional edit link
						let statusMessage = `${icon} ${data.data.message}`;
						if (editLink && (needsShortcode || data.data.exists)) {
							statusMessage += ` <a href="${editLink}" target="_blank"><?php esc_html_e( 'Edit Page', 'brag-book-gallery' ); ?></a>`;
						}

						elements.slugStatus.innerHTML = statusMessage;

						// Configure button based on response
						if (canGeneratePage) {
							elements.generateBtn.disabled = false;
							elements.generateBtn.textContent = '<?php esc_html_e( 'Generate Page', 'brag-book-gallery' ); ?>';
						} else if (needsShortcode) {
							elements.generateBtn.disabled = true;
							elements.generateBtn.textContent = '<?php esc_html_e( 'Add Shortcode', 'brag-book-gallery' ); ?>';
						} else {
							elements.generateBtn.disabled = true;
							elements.generateBtn.textContent = data.data.has_shortcode ?
								'<?php esc_html_e( 'Page Ready', 'brag-book-gallery' ); ?>' :
								'<?php esc_html_e( 'Page Exists', 'brag-book-gallery' ); ?>';
						}
					} else {
						elements.slugStatus.className = 'slug-status error';
						elements.slugStatus.textContent = '<?php esc_html_e( 'Error checking slug availability', 'brag-book-gallery' ); ?>';
						elements.generateBtn.disabled = true;
						canGeneratePage = false;
					}
				} catch (error) {
					console.error('Error checking slug:', error);
					elements.slugStatus.className = 'slug-status error';

					if (error.name === 'AbortError') {
						elements.slugStatus.textContent = '<?php esc_html_e( 'Request timed out. Please try again.', 'brag-book-gallery' ); ?>';
					} else if (error.message && error.message.includes('fetch')) {
						elements.slugStatus.textContent = '<?php esc_html_e( 'Connection error. Check your internet connection.', 'brag-book-gallery' ); ?>';
					} else {
						elements.slugStatus.textContent = '<?php esc_html_e( 'Error checking slug availability', 'brag-book-gallery' ); ?>';
					}

					elements.generateBtn.disabled = true;
					canGeneratePage = false;
				}
			};

			/**
			 * Handle page generation
			 *
			 * @since 3.0.0
			 */
			const handleGeneratePage = async () => {
				if (!elements.slugInput || !elements.generateBtn || !elements.slugStatus) return;

				const slug = elements.slugInput.value.trim();

				// Only proceed if we have a slug and generation is allowed
				if (!slug || !canGeneratePage) return;

				try {
					elements.generateBtn.disabled = true;
					elements.generateBtn.textContent = '<?php esc_html_e( 'Creating...', 'brag-book-gallery' ); ?>';

					// Generate a title from the slug
					const title = slug.split('-').map(word =>
						word.charAt(0).toUpperCase() + word.slice(1)
					).join(' ');

					const formData = new FormData();
					formData.append('action', 'brag_book_gallery_generate_page');
					formData.append('slug', slug);
					formData.append('title', title);
					formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_generate_page' ); ?>');

					// Create AbortController for timeout handling (longer timeout for page generation)
					const controller = new AbortController();
					const timeoutId = setTimeout(() => controller.abort(), 45000); // 45 second timeout

					const response = await fetch(ajaxurl, {
						method: 'POST',
						body: formData,
						signal: controller.signal
					});

					clearTimeout(timeoutId);

					const data = await response.json();

					if (data.success) {
						elements.slugStatus.className = 'slug-status success';
						elements.slugStatus.innerHTML = `✓ ${data.data.message}`;
						elements.generateBtn.textContent = '<?php esc_html_e( 'Page Created', 'brag-book-gallery' ); ?>';
						elements.generateBtn.disabled = true;
						currentSlugExists = true;
						canGeneratePage = false;

						if (data.data.edit_link) {
							elements.slugStatus.innerHTML += ` <a href="${data.data.edit_link}" target="_blank"><?php esc_html_e( 'Edit Page', 'brag-book-gallery' ); ?></a>`;
						}
					} else {
						elements.slugStatus.className = 'slug-status error';
						elements.slugStatus.textContent = data.data || '<?php esc_html_e( 'Failed to create page', 'brag-book-gallery' ); ?>';
						elements.generateBtn.textContent = '<?php esc_html_e( 'Generate Page', 'brag-book-gallery' ); ?>';
						elements.generateBtn.disabled = false;
					}
				} catch (error) {
					console.error('Error generating page:', error);
					elements.slugStatus.className = 'slug-status error';

					if (error.name === 'AbortError') {
						elements.slugStatus.textContent = '<?php esc_html_e( 'Page creation timed out. This may happen on managed hosting. Please check if the page was created and try refreshing.', 'brag-book-gallery' ); ?>';
					} else if (error.message && error.message.includes('fetch')) {
						elements.slugStatus.textContent = '<?php esc_html_e( 'Connection error. Please check your internet connection and try again.', 'brag-book-gallery' ); ?>';
					} else {
						elements.slugStatus.textContent = '<?php esc_html_e( 'Unexpected error during page creation', 'brag-book-gallery' ); ?>';
					}

					elements.generateBtn.textContent = '<?php esc_html_e( 'Generate Page', 'brag-book-gallery' ); ?>';
					elements.generateBtn.disabled = false;
				}
			};

			/**
			 * Initialize event listeners
			 *
			 * @since 3.0.0
			 */
			const initializeEventListeners = () => {
				// Slug validation
				if (elements.slugInput) {
					elements.slugInput.addEventListener('input', () => {
						clearTimeout(checkSlugTimeout);
						checkSlugTimeout = setTimeout(checkSlugAvailability, 500);
					});

					// Check on page load if there's a value
					if (elements.slugInput.value) {
						checkSlugAvailability();
					}
				}

				// Generate page button
				if (elements.generateBtn) {
					elements.generateBtn.addEventListener('click', handleGeneratePage);
				}
			};

			// Initialize when DOM is ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initializeEventListeners);
			} else {
				initializeEventListeners();
			}
		})();
		</script>

		<?php
		$this->render_footer();
	}

	/**
	 * Render side tabs navigation
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_side_tabs(): void {
		?>
		<div class="brag-book-gallery-side-tabs">
			<ul>
				<li><a href="#display-settings"><?php esc_html_e( 'Display & Gallery', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#seo-settings"><?php esc_html_e( 'SEO Settings', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#performance-settings"><?php esc_html_e( 'Performance', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#custom-css"><?php esc_html_e( 'Custom CSS', 'brag-book-gallery' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render tab content panels
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_tab_content(): void {
		?>
		<!-- Display & Gallery Settings Tab -->
		<div id="display-settings" class="brag-book-gallery-tab-panel">
			<?php $this->render_display_settings_tab(); ?>
		</div>

		<!-- SEO Settings Tab -->
		<div id="seo-settings" class="brag-book-gallery-tab-panel">
			<?php $this->render_seo_settings_tab(); ?>
		</div>

		<!-- Performance Settings Tab -->
		<div id="performance-settings" class="brag-book-gallery-tab-panel">
			<?php $this->render_performance_settings_tab(); ?>
		</div>

		<!-- Custom CSS Tab -->
		<div id="custom-css" class="brag-book-gallery-tab-panel">
			<?php $this->render_custom_css_tab(); ?>
		</div>
		<?php
	}

	/**
	 * Render Display & Gallery Settings tab content
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_display_settings_tab(): void {
		// Get current settings with default values
		$columns = get_option( 'brag_book_gallery_columns', '3' );
		$items_per_page = get_option( 'brag_book_gallery_items_per_page', '10' );
		$default_landing_text = '<h2>Go ahead, browse our before & afters... visualize your possibilities.</h2>' . "\n" .
		                       '<p>Our gallery is full of our real patients. Keep in mind results vary.</p>';
		$landing_text = get_option( 'brag_book_gallery_landing_page_text', $default_landing_text );
		$slug = get_option( 'brag_book_gallery_page_slug', '' );

		// Get new toggle settings
		$expand_nav_menus = get_option( 'brag_book_gallery_expand_nav_menus', false );
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );
		$enable_favorites = get_option( 'brag_book_gallery_enable_favorites', true );
		$enable_consultation = get_option( 'brag_book_gallery_enable_consultation', true );

		// Debug what's actually in the database
		$raw_db_value = get_option( 'brag_book_gallery_enable_favorites', 'NOT_FOUND' );
		error_log( "BRAGBook Debug - Raw DB value: " . print_r( $raw_db_value, true ) . " (type: " . gettype( $raw_db_value ) . ")" );
		error_log( "BRAGBook Debug - Final enable_favorites: " . print_r( $enable_favorites, true ) . " (type: " . gettype( $enable_favorites ) . ")" );

		// Show notice if not in default mode (for gallery settings section)
		// Mode manager removed - default to 'default' mode
		$current_mode = 'default';
		?>

		<h2><?php esc_html_e( 'Display & Gallery Settings', 'brag-book-gallery' ); ?></h2>
		<?php settings_errors( $this->page_slug ); ?>

		<form method="post" action="" id="brag-book-gallery-display-gallery-form">
			<?php
			wp_nonce_field( 'brag_book_gallery_general_settings', 'brag_book_gallery_general_nonce' );
			wp_nonce_field( 'brag_book_gallery_default_settings', 'brag_book_gallery_default_nonce' );
			?>

			<!-- Gallery Page Settings Section - Now First -->
			<h3><?php esc_html_e( 'Gallery Page Settings', 'brag-book-gallery' ); ?></h3>

			<?php if ( $current_mode !== 'default' ) : ?>
				<div class="brag-book-gallery-notice brag-book-gallery-notice--warning">
					<p>
						<?php esc_html_e( 'These gallery page settings only apply when default mode is active.', 'brag-book-gallery' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<table class="form-table brag-book-gallery-form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="brag_book_gallery_page_slug">
							<?php esc_html_e( 'Gallery Slug', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="slug-input-wrapper">
							<input type="text"
							       id="brag_book_gallery_page_slug"
							       name="brag_book_gallery_page_slug"
							       value="<?php echo esc_attr( $slug ); ?>"
							       class="regular-text"
							       placeholder="<?php esc_attr_e( 'e.g., gallery, portfolio, before-after', 'brag-book-gallery' ); ?>">
							<button type="button"
							        id="generate-page-btn"
							        class="button button-secondary"
							        style="margin-left: 10px;"
							        disabled>
								<?php esc_html_e( 'Generate Page', 'brag-book-gallery' ); ?>
							</button>
						</div>
						<div id="slug-status-message" class="slug-status"></div>
						<p class="description">
							<?php esc_html_e( 'Enter a slug and click "Generate Page" to create the gallery page, or it will be created when you save settings.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_landing_page_text">
							<?php esc_html_e( 'Landing Page Text', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<?php
						wp_editor(
							$landing_text,
							'brag_book_gallery_landing_page_text',
							array(
								'textarea_name' => 'brag_book_gallery_landing_page_text',
								'textarea_rows' => 8,
								'media_buttons' => false,
								'teeny'         => true,
								'tinymce'       => true,
								'quicktags'     => true
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'Text displayed on the gallery landing page.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- Display Settings Section - Now Second -->
			<h3><?php esc_html_e( 'Display Settings', 'brag-book-gallery' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label>
							<?php esc_html_e( 'Gallery Columns', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-grid-buttons">
							<label class="brag-book-gallery-grid-option">
								<input type="radio" name="brag_book_gallery_columns" value="2" <?php checked( $columns, '2' ); ?> />
								<div class="brag-book-gallery-grid-btn" aria-label="View in 2 columns">
									<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
										<rect x="1" y="1" width="6" height="6"></rect>
										<rect x="9" y="1" width="6" height="6"></rect>
										<rect x="1" y="9" width="6" height="6"></rect>
										<rect x="9" y="9" width="6" height="6"></rect>
									</svg>
									<span class="sr-only">2 Columns</span>
								</div>
							</label>
							<label class="brag-book-gallery-grid-option">
								<input type="radio" name="brag_book_gallery_columns" value="3" <?php checked( $columns, '3' ); ?> />
								<div class="brag-book-gallery-grid-btn" aria-label="View in 3 columns">
									<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
										<rect x="1" y="1" width="4" height="4"></rect>
										<rect x="6" y="1" width="4" height="4"></rect>
										<rect x="11" y="1" width="4" height="4"></rect>
										<rect x="1" y="6" width="4" height="4"></rect>
										<rect x="6" y="6" width="4" height="4"></rect>
										<rect x="11" y="6" width="4" height="4"></rect>
										<rect x="1" y="11" width="4" height="4"></rect>
										<rect x="6" y="11" width="4" height="4"></rect>
										<rect x="11" y="11" width="4" height="4"></rect>
									</svg>
									<span class="sr-only">3 Columns</span>
								</div>
							</label>
						</div>
						<p class="description">
							<?php esc_html_e( 'Number of columns to display in the gallery grid layout.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_items_per_page">
							<?php esc_html_e( 'Items Per Page', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
							   id="brag_book_gallery_items_per_page"
							   name="brag_book_gallery_items_per_page"
							   value="<?php echo esc_attr( $items_per_page ); ?>"
							   min="1"
							   max="100"
							   step="1" />
						<p class="description">
							<?php esc_html_e( 'Number of gallery items to load initially. Additional items can be loaded with the "Load More" button.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_expand_nav_menus">
							<?php esc_html_e( 'Expand Navigation Menus', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="hidden" name="brag_book_gallery_expand_nav_menus" value="0" />
								<input type="checkbox"
								       id="brag_book_gallery_expand_nav_menus"
								       name="brag_book_gallery_expand_nav_menus"
								       value="1"
								       <?php checked( $expand_nav_menus, true ); ?> />
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Show navigation filter menus expanded by default', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'When enabled, all navigation filter menus will be expanded by default when users load the gallery page.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_show_filter_counts">
							<?php esc_html_e( 'Show Filter Counts', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="hidden" name="brag_book_gallery_show_filter_counts" value="0" />
								<input type="checkbox"
								       id="brag_book_gallery_show_filter_counts"
								       name="brag_book_gallery_show_filter_counts"
								       value="1"
								       <?php checked( $show_filter_counts, true ); ?> />
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Display case counts next to filter categories', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Display the number of available items for each filter category (e.g., "Procedure (15)", "Age Group (8)").', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_enable_favorites">
							<?php esc_html_e( 'Enable Favorites', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="hidden" name="brag_book_gallery_enable_favorites" value="0" />
								<input type="checkbox"
								       id="brag_book_gallery_enable_favorites"
								       name="brag_book_gallery_enable_favorites"
								       value="1"
								       <?php checked( $enable_favorites, true ); ?> />
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Allow users to save and manage favorite cases', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'When enabled, favorite buttons and the My Favorites page will be available. When disabled, all favorites functionality is hidden.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_enable_consultation">
							<?php esc_html_e( 'Enable Consultation Requests', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="hidden" name="brag_book_gallery_enable_consultation" value="0" />
								<input type="checkbox"
								       id="brag_book_gallery_enable_consultation"
								       name="brag_book_gallery_enable_consultation"
								       value="1"
								       <?php checked( $enable_consultation, true ); ?> />
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Display consultation request CTA and dialog forms', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'When enabled, the "Ready for the next step?" text, "Request a Consultation" button, and consultation dialog will be shown. When disabled, all consultation elements are hidden.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="brag-book-gallery-tab-actions">
				<button type="submit" name="submit" class="button button-primary button-large">
					<?php esc_html_e( 'Save Display & Gallery Settings', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Render Mode Settings tab content
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_mode_settings_tab(): void {
		$this->render_mode_settings();
	}


	/**
	 * Render SEO Settings tab content
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_seo_settings_tab(): void {
		// Get SEO options (handle array format from API settings)
		$seo_title_option = get_option( 'brag_book_gallery_seo_page_title', '' );
		$seo_title = '';
		if ( is_array( $seo_title_option ) ) {
			$seo_title = ! empty( $seo_title_option[0] ) ? (string) $seo_title_option[0] : '';
		} else {
			$seo_title = (string) $seo_title_option;
		}

		$seo_desc_option = get_option( 'brag_book_gallery_seo_page_description', '' );
		$seo_description = '';
		if ( is_array( $seo_desc_option ) ) {
			$seo_description = ! empty( $seo_desc_option[0] ) ? (string) $seo_desc_option[0] : '';
		} else {
			$seo_description = (string) $seo_desc_option;
		}

		$slug = get_option( 'brag_book_gallery_page_slug', '' );
		?>

		<h2><?php esc_html_e( 'SEO Settings', 'brag-book-gallery' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure search engine optimization settings for your gallery pages.', 'brag-book-gallery' ); ?>
		</p>

		<!-- SEO Integration Status -->
		<?php
		// Get SEO plugin info from the SEO Manager
		$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$seo_manager = $setup->get_service( 'seo_manager' );
		$seo_info = array();

		if ( $seo_manager && method_exists( $seo_manager, 'get_active_seo_plugin_info' ) ) {
			$seo_info = $seo_manager->get_active_seo_plugin_info();
		}

		if ( ! empty( $seo_info ) && $seo_info['active'] ) {
			?>
			<div class="brag-book-gallery-notice brag-book-gallery-notice--success inline">
				<p>
					<strong><?php esc_html_e( 'SEO Integration:', 'brag-book-gallery' ); ?></strong>
					<?php echo esc_html( $seo_info['name'] ); ?> <?php esc_html_e( 'detected and active', 'brag-book-gallery' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'BRAGBook Gallery is integrated with your SEO plugin for optimized meta tags and structured data.', 'brag-book-gallery' ); ?>
				</p>
			</div>
			<?php
		} else {
			?>
			<div class="brag-book-gallery-notice brag-book-gallery-notice--warning inline">
				<p>
					<strong><?php esc_html_e( 'SEO Integration:', 'brag-book-gallery' ); ?></strong>
					<?php esc_html_e( 'No SEO plugin detected', 'brag-book-gallery' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Consider installing an SEO plugin like Yoast SEO, RankMath, or All in One SEO for better search engine optimization.', 'brag-book-gallery' ); ?>
				</p>
			</div>
			<?php
		}
		?>

		<form method="post" action="" id="brag-book-gallery-seo-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_default_settings', 'brag_book_gallery_default_nonce' ); ?>

			<table class="form-table brag-book-gallery-form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="brag_book_gallery_seo_title">
							<?php esc_html_e( 'SEO Page Title', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<input type="text"
						       id="brag_book_gallery_seo_title"
						       name="brag_book_gallery_seo_title"
						       value="<?php echo esc_attr( $seo_title ); ?>"
						       class="large-text"
						       maxlength="60">
						<div class="character-count">
							<span id="title-char-count">0</span> / 60 <?php esc_html_e( 'characters', 'brag-book-gallery' ); ?>
							<span id="title-char-warning" class="warning-text" style="display: none;">
								<?php esc_html_e( 'Title is too long', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Recommended: 50-60 characters. This appears in search engine results.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_seo_description">
							<?php esc_html_e( 'SEO Meta Description', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<textarea id="brag_book_gallery_seo_description"
						          name="brag_book_gallery_seo_description"
						          rows="3"
						          class="large-text"
						          maxlength="160"><?php echo esc_textarea( $seo_description ); ?></textarea>
						<div class="character-count">
							<span id="desc-char-count">0</span> / 160 <?php esc_html_e( 'characters', 'brag-book-gallery' ); ?>
							<span id="desc-char-warning" class="warning-text" style="display: none;">
								<?php esc_html_e( 'Description is too long', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Recommended: 120-160 characters. This is the snippet shown in search results.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- SERP Preview -->
			<div class="serp-preview-container">
				<h3><?php esc_html_e( 'Search Result Preview', 'brag-book-gallery' ); ?></h3>
				<div class="serp-preview">
					<div class="serp-title" id="serp-title">
						<?php
						if ( ! empty( $seo_title ) ) {
							echo esc_html( $seo_title );
						} else {
							echo esc_html( get_bloginfo( 'name' ) . ' - Gallery' );
						}
						?>
					</div>
					<div class="serp-url">
						<?php echo esc_url( home_url( '/' . ( $slug ?: 'gallery' ) . '/' ) ); ?>
					</div>
					<div class="serp-description" id="serp-description">
						<?php
						if ( ! empty( $seo_description ) ) {
							echo esc_html( $seo_description );
						} else {
							echo esc_html( 'View our stunning gallery of before and after transformations. Browse through real patient results and see the amazing outcomes we achieve.' );
						}
						?>
					</div>
				</div>
			</div>

			<div class="brag-book-gallery-tab-actions">
				<button type="submit" name="submit_default" class="button button-primary button-large">
					<?php esc_html_e( 'Save SEO Settings', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Render Performance Settings tab content
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_performance_settings_tab(): void {
		$ajax_timeout = get_option( 'brag_book_gallery_ajax_timeout', 30 );
		$cache_duration = get_option( 'brag_book_gallery_cache_duration', 300 );
		$lazy_load = get_option( 'brag_book_gallery_lazy_load', 'yes' );
		?>

		<h2><?php esc_html_e( 'Performance Settings', 'brag-book-gallery' ); ?></h2>

		<form method="post" action="" id="brag-book-gallery-performance-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_default_settings', 'brag_book_gallery_default_nonce' ); ?>

			<table class="form-table brag-book-gallery-form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="ajax_timeout">
							<?php esc_html_e( 'AJAX Timeout', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
						       id="ajax_timeout"
						       name="ajax_timeout"
						       value="<?php echo esc_attr( $ajax_timeout ); ?>"
						       min="5"
						       max="120"
						       class="small-text">
						<?php esc_html_e( 'seconds', 'brag-book-gallery' ); ?>
						<p class="description">
							<?php esc_html_e( 'Maximum time to wait for API responses.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="cache_duration">
							<?php esc_html_e( 'Cache Duration', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<input type="number"
						       id="cache_duration"
						       name="cache_duration"
						       value="<?php echo esc_attr( $cache_duration ); ?>"
						       min="0"
						       max="86400"
						       class="small-text">
						<?php esc_html_e( 'seconds', 'brag-book-gallery' ); ?>
						<p class="description">
							<?php esc_html_e( 'How long to cache API responses (0 to disable).', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="lazy_load">
							<?php esc_html_e( 'Lazy Load Images', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="checkbox"
								       id="lazy_load"
								       name="lazy_load"
								       value="yes"
								       <?php checked( $lazy_load, 'yes' ); ?>>
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Enable lazy loading for gallery images', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Improves page load times by loading images only when they become visible.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="brag-book-gallery-tab-actions">
				<button type="submit" name="submit_default" class="button button-primary button-large">
					<?php esc_html_e( 'Save Performance Settings', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" id="clear-cache-btn" class="button button-secondary button-large" style="margin-left: 10px;">
					<?php esc_html_e( 'Clear Gallery Cache', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Render Custom CSS tab content
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_custom_css_tab(): void {
		$custom_css = get_option( 'brag_book_gallery_custom_css', '' );
		?>

		<h2><?php esc_html_e( 'Custom CSS', 'brag-book-gallery' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Add custom CSS styles to customize the appearance of your gallery.', 'brag-book-gallery' ); ?>
		</p>

		<form method="post" action="" id="brag-book-gallery-css-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_general_settings', 'brag_book_gallery_general_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="brag_book_gallery_custom_css">
							<?php esc_html_e( 'Custom CSS', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div id="monaco-editor-container" style="height: 400px; border: 1px solid #dcdcde; border-radius: 4px; margin-bottom: 10px;">
							<div id="monaco-editor" style="height: 100%;"></div>
						</div>
						<textarea id="brag_book_gallery_custom_css"
						          name="brag_book_gallery_custom_css"
						          style="display: none;"><?php echo esc_textarea( $custom_css ); ?></textarea>
						<div class="monaco-editor-toolbar">
							<button type="button" id="format-css" class="button button-secondary">
								<?php esc_html_e( 'Format CSS', 'brag-book-gallery' ); ?>
							</button>
							<button type="button" id="reset-css" class="button button-secondary">
								<?php esc_html_e( 'Reset', 'brag-book-gallery' ); ?>
							</button>
							<span class="monaco-editor-status">
								<span id="css-line-count">0</span> <?php esc_html_e( 'lines', 'brag-book-gallery' ); ?> |
								<span id="css-char-count">0</span> <?php esc_html_e( 'characters', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Add custom CSS styles to customize your gallery appearance. Monaco Editor provides IntelliSense, syntax highlighting, and error checking.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="brag-book-gallery-tab-actions">
				<button type="submit" name="submit_css" class="button button-primary button-large">
					<?php esc_html_e( 'Save Custom CSS', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</form>

		<!-- Add JavaScript for SEO features and other functionality -->
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// SEO character counting functionality
			const titleInput = document.getElementById('brag_book_gallery_seo_title');
			const descInput = document.getElementById('brag_book_gallery_seo_description');
			const titleCount = document.getElementById('title-char-count');
			const descCount = document.getElementById('desc-char-count');
			const titleWarning = document.getElementById('title-char-warning');
			const descWarning = document.getElementById('desc-char-warning');
			const serpTitle = document.getElementById('serp-title');
			const serpDesc = document.getElementById('serp-description');

			// Update title character count and SERP preview
			const updateTitle = () => {
				if (!titleInput || !titleCount || !serpTitle) return;

				const length = titleInput.value.length;
				titleCount.textContent = length;

				// Show warning if too long
				if (length > 60) {
					titleWarning.style.display = 'inline';
					titleCount.style.color = '#d63638';
				} else if (length > 50) {
					titleWarning.style.display = 'none';
					titleCount.style.color = '#dba617';
				} else {
					titleWarning.style.display = 'none';
					titleCount.style.color = '#666';
				}

				// Update SERP preview
				serpTitle.textContent = titleInput.value || '<?php echo esc_js( get_bloginfo( 'name' ) . ' - Gallery' ); ?>';
			};

			// Update description character count and SERP preview
			const updateDescription = () => {
				if (!descInput || !descCount || !serpDesc) return;

				const length = descInput.value.length;
				descCount.textContent = length;

				// Show warning if too long
				if (length > 160) {
					descWarning.style.display = 'inline';
					descCount.style.color = '#d63638';
				} else if (length > 120) {
					descWarning.style.display = 'none';
					descCount.style.color = '#00a32a';
				} else {
					descWarning.style.display = 'none';
					descCount.style.color = '#666';
				}

				// Update SERP preview
				serpDesc.textContent = descInput.value || 'View our stunning gallery of before and after transformations. Browse through real patient results and see the amazing outcomes we achieve.';
			};

			// Initialize and add event listeners
			if (titleInput) {
				updateTitle();
				titleInput.addEventListener('input', updateTitle);
			}

			if (descInput) {
				updateDescription();
				descInput.addEventListener('input', updateDescription);
			}

			// Clear cache button functionality
			const clearCacheBtn = document.getElementById('clear-cache-btn');
			if (clearCacheBtn) {
				clearCacheBtn.addEventListener('click', function() {
					if (confirm('This will clear all cached gallery data. Continue?')) {
						const button = this;
						button.disabled = true;
						button.textContent = 'Clearing...';

						// Make AJAX request to clear cache
						const formData = new FormData();
						formData.append('action', 'brag_book_gallery_clear_cache');
						formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_clear_cache' ); ?>');

						fetch(ajaxurl, {
							method: 'POST',
							body: formData
						})
						.then(response => response.json())
						.then(data => {
							if (data.success) {
								alert('Cache cleared successfully!');
								button.textContent = 'Cache Cleared ✓';
								setTimeout(() => {
									button.textContent = 'Clear Gallery Cache';
									button.disabled = false;
								}, 2000);
							} else {
								alert('Failed to clear cache: ' + (data.data || 'Unknown error'));
								button.textContent = 'Clear Gallery Cache';
								button.disabled = false;
							}
						})
						.catch(error => {
							alert('Error clearing cache: ' + error);
							button.textContent = 'Clear Gallery Cache';
							button.disabled = false;
						});
					}
				});
			}

			// Initialize Monaco Editor for CSS editing
			let monacoEditor = null;

			const initMonacoEditor = () => {
				const container = document.getElementById('monaco-editor');
				const textarea = document.getElementById('brag_book_gallery_custom_css');
				const formatBtn = document.getElementById('format-css');
				const resetBtn = document.getElementById('reset-css');
				const lineCountEl = document.getElementById('css-line-count');
				const charCountEl = document.getElementById('css-char-count');

				if (!container || !textarea) return;

				// Configure Monaco Editor
				require.config({
					paths: {
						'vs': 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs'
					}
				});

				require(['vs/editor/editor.main'], function() {
					// Create Monaco Editor instance
					monacoEditor = monaco.editor.create(container, {
						value: textarea.value,
						language: 'css',
						theme: 'vs',
						automaticLayout: true,
						minimap: { enabled: false },
						lineNumbers: 'on',
						wordWrap: 'on',
						tabSize: 4,
						insertSpaces: false,
						fontSize: 13,
						fontFamily: 'Consolas, Monaco, "Courier New", monospace',
						scrollBeyondLastLine: false,
						renderLineHighlight: 'line',
						selectOnLineNumbers: true,
						roundedSelection: false,
						readOnly: false,
						cursorStyle: 'line',
						showUnused: true,
						folding: true,
						foldingHighlight: true,
						suggest: {
							insertMode: 'replace'
						}
					});

					// Update textarea when editor content changes
					monacoEditor.onDidChangeModelContent(() => {
						textarea.value = monacoEditor.getValue();
						updateStats();
					});

					// Update statistics
					const updateStats = () => {
						const content = monacoEditor.getValue();
						const lines = content.split('\n').length;
						const chars = content.length;

						if (lineCountEl) lineCountEl.textContent = lines;
						if (charCountEl) charCountEl.textContent = chars;
					};

					// Format CSS button
					if (formatBtn) {
						formatBtn.addEventListener('click', () => {
							monacoEditor.getAction('editor.action.formatDocument').run();
						});
					}

					// Reset button
					if (resetBtn) {
						resetBtn.addEventListener('click', () => {
							if (confirm('<?php esc_html_e( 'Are you sure you want to reset all custom CSS? This cannot be undone.', 'brag-book-gallery' ); ?>')) {
								monacoEditor.setValue('');
							}
						});
					}

					// Initial stats update
					updateStats();

					// Focus editor for better UX
					monacoEditor.focus();
				});
			};

			// Initialize Monaco Editor if container exists
			if (document.getElementById('monaco-editor-container')) {
				initMonacoEditor();
			}

		});
		</script>
		<?php
	}

	/**
	 * Render mode settings section
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_mode_settings(): void {
		// Get mode manager instance
		// Mode manager removed - default to 'default' mode
		$current_mode = 'default';

		?>
		<h2><?php esc_html_e( 'Mode Settings', 'brag-book-gallery' ); ?></h2>

		<!-- Current Mode Status -->
		<div class="brag-book-gallery-notice brag-book-gallery-notice--info inline">
			<p>
				<strong><?php esc_html_e( 'Active Mode:', 'brag-book-gallery' ); ?></strong>
				<span class="brag-mode-badge <?php echo esc_attr( $current_mode ); ?>">
					<?php echo esc_html( ucfirst( $current_mode ) ); ?> Mode
				</span>
			</p>
			<p>
				<?php
				if ( $current_mode === 'default' ) {
					esc_html_e( 'Content is loaded dynamically from the BRAG book API. URLs are virtual and galleries update in real-time.', 'brag-book-gallery' );
				} else {
					esc_html_e( 'Content is stored locally in WordPress. Galleries use native post types and taxonomies for better SEO and performance.', 'brag-book-gallery' );
				}
				?>
			</p>
		</div>


		<!-- Mode Switcher -->
		<form id="brag-mode-switch-form" method="post" style="margin-top: 20px;">
			<?php wp_nonce_field( 'brag_book_gallery_mode_switch', 'mode_switch_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Operating Mode', 'brag-book-gallery' ); ?></th>
					<td>
						<fieldset>
							<label style="display: block; margin-bottom: 20px;">
								<input type="radio" name="gallery_mode" value="default"
									<?php checked( $current_mode, 'default' ); ?>>
								<strong><?php esc_html_e( 'Default Mode', 'brag-book-gallery' ); ?></strong>
								<p class="description" style="margin-left: 24px; margin-top: 5px;">
									<?php esc_html_e( 'Dynamic API-driven content. Best for real-time updates and minimal database usage.', 'brag-book-gallery' ); ?>
								</p>
							</label>

							<label style="display: block; opacity: 0.6; cursor: not-allowed;">
								<input type="radio" name="gallery_mode" value="local"
									<?php checked( $current_mode, 'local' ); ?> disabled>
								<strong><?php esc_html_e( 'Local Mode', 'brag-book-gallery' ); ?></strong>
								<span style="display: inline-block; margin-left: 10px; padding: 2px 8px; background: #f0f0f1; border-radius: 3px; font-size: 12px; font-weight: 600; color: #666;">
									<?php esc_html_e( 'COMING SOON', 'brag-book-gallery' ); ?>
								</span>
								<p class="description" style="margin-left: 24px; margin-top: 5px;">
									<?php esc_html_e( 'WordPress native content. Best for SEO, performance, and offline access.', 'brag-book-gallery' ); ?>
									<br><em style="color: #666;"><?php esc_html_e( 'This mode is currently under development and will be available in a future update.', 'brag-book-gallery' ); ?></em>
								</p>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>

			<div class="brag-book-gallery-tab-actions">
				<button type="submit" name="switch_mode" class="button button-primary"
					<?php echo ( $current_mode === 'default' ) ? 'disabled' : ''; ?>>
					<?php esc_html_e( 'Switch Mode', 'brag-book-gallery' ); ?>
				</button>
				<?php if ( $current_mode === 'default' ) : ?>
					<span class="description" style="display: inline-block; margin-left: 10px; line-height: 30px;">
						<?php esc_html_e( 'Default Mode is currently active', 'brag-book-gallery' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</form>

		<style>
		.brag-mode-badge {
			display: inline-block;
			padding: 3px 8px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: 600;
			text-transform: uppercase;
		}
		.brag-mode-badge.default {
			background: #2271b1;
			color: white;
		}
		.brag-mode-badge.local {
			background: #00a32a;
			color: white;
		}
		</style>
		<?php
	}

	/**
	 * Handle mode switch submission
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function handle_mode_switch(): void {
		// Verify nonce
		if ( ! isset( $_POST['mode_switch_nonce'] ) || ! wp_verify_nonce( $_POST['mode_switch_nonce'], 'brag_book_gallery_mode_switch' ) ) {
			$this->add_notice( __( 'Security check failed. Please try again.', 'brag-book-gallery' ), 'error' );
			return;
		}

		$new_mode = isset( $_POST['gallery_mode'] ) ? sanitize_text_field( $_POST['gallery_mode'] ) : '';

		if ( ! in_array( $new_mode, [ 'default', 'local' ], true ) ) {
			$this->add_notice( __( 'Invalid mode selected.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Local mode is not yet available
		if ( $new_mode === 'local' ) {
			$this->add_notice( __( 'Local Mode is not yet available. Coming soon in a future update.', 'brag-book-gallery' ), 'warning' );
			return;
		}

		// Get mode manager and switch mode
		// Mode manager removed - default to 'default' mode
		$current_mode = 'default';

		if ( $current_mode === $new_mode ) {
			$this->add_notice( sprintf( __( '%s Mode is already active.', 'brag-book-gallery' ), ucfirst( $new_mode ) ), 'info' );
			return;
		}

		// Attempt to switch modes
		try {
			$result = $mode_manager->switch_mode( $new_mode );

			if ( $result ) {
				$this->add_notice( sprintf( __( 'Successfully switched to %s Mode.', 'brag-book-gallery' ), ucfirst( $new_mode ) ), 'success' );
			} else {
				$this->add_notice( __( 'Failed to switch modes. Please try again.', 'brag-book-gallery' ), 'error' );
			}
		} catch ( \Exception $e ) {
			$this->add_notice( sprintf( __( 'Error switching modes: %s', 'brag-book-gallery' ), $e->getMessage() ), 'error' );
		}
	}

	/**
	 * Save general settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_general_settings(): void {
		// Gallery Display Settings
		$columns = isset( $_POST['brag_book_gallery_columns'] ) ? sanitize_text_field( $_POST['brag_book_gallery_columns'] ) : '3';
		update_option( 'brag_book_gallery_columns', $columns );

		$items_per_page = isset( $_POST['brag_book_gallery_items_per_page'] ) ? absint( $_POST['brag_book_gallery_items_per_page'] ) : 10;
		update_option( 'brag_book_gallery_items_per_page', $items_per_page );

		// Navigation and Filter Settings
		$expand_nav_menus = isset( $_POST['brag_book_gallery_expand_nav_menus'] ) && $_POST['brag_book_gallery_expand_nav_menus'] === '1';
		update_option( 'brag_book_gallery_expand_nav_menus', $expand_nav_menus );

		$show_filter_counts = isset( $_POST['brag_book_gallery_show_filter_counts'] ) && $_POST['brag_book_gallery_show_filter_counts'] === '1';
		update_option( 'brag_book_gallery_show_filter_counts', $show_filter_counts );

		// Debug favorites toggle processing
		$raw_post_value = $_POST['brag_book_gallery_enable_favorites'] ?? 'NOT_SET';
		$is_isset = isset( $_POST['brag_book_gallery_enable_favorites'] );
		$equals_one = isset( $_POST['brag_book_gallery_enable_favorites'] ) && $_POST['brag_book_gallery_enable_favorites'] === '1';

		error_log( "BRAGBook Debug - Raw POST value: " . print_r( $raw_post_value, true ) );
		error_log( "BRAGBook Debug - isset check: " . ( $is_isset ? 'TRUE' : 'FALSE' ) );
		error_log( "BRAGBook Debug - equals '1' check: " . ( $equals_one ? 'TRUE' : 'FALSE' ) );

		$enable_favorites = isset( $_POST['brag_book_gallery_enable_favorites'] ) && $_POST['brag_book_gallery_enable_favorites'] === '1';

		error_log( "BRAGBook Debug - Final boolean value: " . ( $enable_favorites ? 'TRUE' : 'FALSE' ) );
		error_log( "BRAGBook Debug - About to save to database..." );

		// Force the option to be created/updated even if value is false
		delete_option( 'brag_book_gallery_enable_favorites' );
		$update_result = add_option( 'brag_book_gallery_enable_favorites', $enable_favorites, '', 'no' );

		error_log( "BRAGBook Debug - Update result: " . ( $update_result ? 'SUCCESS' : 'NO_CHANGE_OR_FAIL' ) );
		error_log( "BRAGBook Debug - Value now in DB: " . print_r( get_option( 'brag_book_gallery_enable_favorites' ), true ) );

		// Consultation Settings
		$enable_consultation = isset( $_POST['brag_book_gallery_enable_consultation'] ) && $_POST['brag_book_gallery_enable_consultation'] === '1';

		// Force the option to be created/updated even if value is false
		delete_option( 'brag_book_gallery_enable_consultation' );
		add_option( 'brag_book_gallery_enable_consultation', $enable_consultation, '', 'no' );

		// Clear settings helper cache when favorites setting is updated
		if ( class_exists( '\BRAGBookGallery\Includes\Core\Settings_Helper' ) ) {
			\BRAGBookGallery\Includes\Core\Settings_Helper::clear_cache();
		}

		// Advanced Settings - Custom CSS with sanitization
		if ( isset( $_POST['brag_book_gallery_custom_css'] ) ) {
			// Sanitize CSS while preserving valid CSS syntax
			$custom_css = wp_strip_all_tags( $_POST['brag_book_gallery_custom_css'] );
			// Remove any potential XSS vectors while keeping CSS intact
			$custom_css = str_replace( array( '<script', '</script', '<style', '</style', 'javascript:', 'expression(' ), '', $custom_css );
			update_option( 'brag_book_gallery_custom_css', $custom_css );
		}

		// Only add notice if it was the main submit button, not the CSS one
		if ( isset( $_POST['submit'] ) ) {
			$this->add_notice( __( 'General settings saved successfully.', 'brag-book-gallery' ), 'success' );
		} elseif ( isset( $_POST['submit_css'] ) ) {
			$this->add_notice( __( 'Custom CSS saved successfully.', 'brag-book-gallery' ), 'success' );
		}
	}

	/**
	 * Save default settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_default_settings(): void {
		// Save landing page text
		if ( isset( $_POST['brag_book_gallery_landing_page_text'] ) ) {
			// Clean escaped quotes from WYSIWYG editor before saving
			$landing_text = $_POST['brag_book_gallery_landing_page_text'];
			$landing_text = str_replace( '\"', '"', $landing_text );
			$landing_text = str_replace( "\'", "'", $landing_text );
			$landing_text = stripslashes( $landing_text );
			update_option(
				'brag_book_gallery_landing_page_text',
				wp_kses_post( $landing_text )
			);
		}

		// Save combined gallery settings and create page if needed
		if ( isset( $_POST['brag_book_gallery_page_slug'] ) ) {
			$new_slug = sanitize_title( $_POST['brag_book_gallery_page_slug'] );

			// Get existing slug from option
			$old_slug = get_option( 'brag_book_gallery_page_slug', '' );

			if ( ! empty( $new_slug ) && $new_slug !== $old_slug ) {
				// Check if page with this slug exists
				$existing_page = get_page_by_path( $new_slug );

				if ( ! $existing_page ) {
					// Create the page with the gallery shortcode
					$page_data = array(
						'post_title'    => ucwords( str_replace( '-', ' ', $new_slug ) ),
						'post_name'     => $new_slug,
						'post_content'  => '[brag_book_gallery]', // Main gallery shortcode
						'post_status'   => 'publish',
						'post_type'     => 'page',
						'post_author'   => get_current_user_id(),
						'comment_status' => 'closed',
						'ping_status'   => 'closed',
					);

					$page_id = wp_insert_post( $page_data );

					if ( ! is_wp_error( $page_id ) ) {
						// Add SEO meta fields if they exist (handle array format)
						$seo_title_option = get_option( 'brag_book_gallery_seo_page_title', '' );
						$seo_title = is_array( $seo_title_option ) && ! empty( $seo_title_option[0] ) ? $seo_title_option[0] : $seo_title_option;

						$seo_desc_option = get_option( 'brag_book_gallery_seo_page_description', '' );
						$seo_description = is_array( $seo_desc_option ) && ! empty( $seo_desc_option[0] ) ? $seo_desc_option[0] : $seo_desc_option;

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

						// Flush rewrite rules after creating gallery page
						flush_rewrite_rules();

						$this->add_notice(
							sprintf(
								__( 'Gallery page "%s" has been created successfully.', 'brag-book-gallery' ),
								$new_slug
							)
						);
					}
				} else {
					$this->add_notice(
						sprintf(
							__( 'A page with slug "%s" already exists. The gallery will use the existing page.', 'brag-book-gallery' ),
							$new_slug
						),
						'warning'
					);
				}
			}

			// Update the slug - maintain array format for consistency
			if ( ! empty( $new_slug ) ) {
				// Store as a single string value
				update_option( 'brag_book_gallery_page_slug', $new_slug );
			}
		}

		if ( isset( $_POST['brag_book_gallery_seo_title'] ) ) {
			update_option( 'brag_book_gallery_seo_page_title', sanitize_text_field( $_POST['brag_book_gallery_seo_title'] ) );
		}

		if ( isset( $_POST['brag_book_gallery_seo_description'] ) ) {
			update_option( 'brag_book_gallery_seo_page_description', sanitize_textarea_field( $_POST['brag_book_gallery_seo_description'] ) );
		}

		// Save performance settings
		if ( isset( $_POST['ajax_timeout'] ) ) {
			update_option( 'brag_book_gallery_ajax_timeout', absint( $_POST['ajax_timeout'] ) );
		}

		if ( isset( $_POST['cache_duration'] ) ) {
			update_option( 'brag_book_gallery_cache_duration', absint( $_POST['cache_duration'] ) );
		}

		$lazy_load = isset( $_POST['lazy_load'] ) && $_POST['lazy_load'] === 'yes' ? 'yes' : 'no';
		update_option( 'brag_book_gallery_lazy_load', $lazy_load );

		$this->add_notice( __( 'Gallery settings saved successfully.', 'brag-book-gallery' ) );
	}

}
