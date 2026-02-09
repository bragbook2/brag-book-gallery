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
 * Manages the main configuration options for BRAG book Gallery plugin.
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

	public function render(): void {

		// Set localized page titles now that translation functions are available.
		$this->page_title = esc_html__( 'General Settings', 'brag-book-gallery' );
		$this->menu_title = esc_html__( 'General', 'brag-book-gallery' );

		// Enqueue Monaco Editor for CSS editing.
		add_action(
			'admin_footer',
			array( $this, 'enqueue_monaco_conditionally' ),
			20
		);

		// Handle form submission.
		if (
			( isset( $_POST['submit'] ) || isset( $_POST['submit_css'] ) ) &&
			$this->save_settings(
				'brag_book_gallery_general_settings',
				'brag_book_gallery_general_nonce'
			)
		) {
			$this->save_general_settings();
		}

		// Handle default settings form submission.
		if (
			isset( $_POST['submit_default'] ) &&
			$this->save_settings(
				'brag_book_gallery_default_settings',
				'brag_book_gallery_default_nonce'
			)
		) {
			$this->save_default_settings();
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

		// Trumbowyg WYSIWYG Editor initialization (Vanilla ES6)
		(() => {
			'use strict';

			// Load Trumbowyg CSS
			const trumbowygCSS = document.createElement('link');
			trumbowygCSS.rel = 'stylesheet';
			trumbowygCSS.href = 'https://cdn.jsdelivr.net/npm/trumbowyg@2.27.3/dist/ui/trumbowyg.min.css';
			document.head.appendChild(trumbowygCSS);

			// Load Trumbowyg JS
			const trumbowygJS = document.createElement('script');
			trumbowygJS.src = 'https://cdn.jsdelivr.net/npm/trumbowyg@2.27.3/dist/trumbowyg.min.js';
			trumbowygJS.onload = () => {
				console.log('BRAGBook: Trumbowyg loaded');

				// Check if jQuery and Trumbowyg are available (Trumbowyg requires jQuery)
				const $ = window.jQuery;
				if (typeof $ !== 'undefined' && $.fn.trumbowyg) {
					const editor = document.getElementById('brag_book_gallery_landing_page_text');

					if (editor) {
						$(editor).trumbowyg({
							btns: [
								['viewHTML'],
								['undo', 'redo'],
								['formatting'],
								['strong', 'em', 'del'],
								['link'],
								['unorderedList', 'orderedList'],
								['horizontalRule'],
								['removeformat']
							],
							semantic: true,
							autogrow: true,
							removeformatPasted: true,
							resetCss: true,
							tagsToRemove: ['script', 'style']
						});

						console.log('BRAGBook: Trumbowyg initialized on landing page text');
					}
				}
			};
			document.head.appendChild(trumbowygJS);
		})();

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

		// Favorites page generation functionality
		(function() {
			'use strict';

			let checkFavoritesTimeout = null;
			let currentFavoritesPageExists = false;
			let canGenerateFavoritesPage = false;

			// DOM elements
			const elements = {
				favoritesPageStatus: document.getElementById('favorites-page-status-message'),
				generateFavoritesBtn: document.getElementById('generate-favorites-page-btn'),
				gallerySlugInput: document.getElementById('brag_book_gallery_page_slug')
			};

			/**
			 * Check favorites page status via AJAX
			 */
			const checkFavoritesPageStatus = async () => {
				if (!elements.favoritesPageStatus || !elements.generateFavoritesBtn) return;

				const gallerySlug = elements.gallerySlugInput ? elements.gallerySlugInput.value.trim() : '';

				if (!gallerySlug) {
					elements.favoritesPageStatus.className = 'slug-status';
					elements.favoritesPageStatus.innerHTML = '<?php esc_html_e( 'Please create the gallery page first.', 'brag-book-gallery' ); ?>';
					elements.favoritesPageStatus.style.display = 'block';
					elements.generateFavoritesBtn.disabled = true;
					canGenerateFavoritesPage = false;
					return;
				}

				// Show checking message
				elements.favoritesPageStatus.className = 'slug-status';
				elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Checking...', 'brag-book-gallery' ); ?>';
				elements.favoritesPageStatus.style.display = 'block';
				elements.generateFavoritesBtn.disabled = true;

				if (typeof ajaxurl === 'undefined') return;

				try {
					const formData = new FormData();
					formData.append('action', 'brag_book_gallery_check_favorites_page');
					formData.append('gallery_slug', gallerySlug);
					formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_check_favorites_page' ); ?>');

					const controller = new AbortController();
					const timeoutId = setTimeout(() => controller.abort(), 15000);

					const response = await fetch(ajaxurl, {
						method: 'POST',
						body: formData,
						signal: controller.signal
					});

					clearTimeout(timeoutId);
					const data = await response.json();

					if (data.success) {
						currentFavoritesPageExists = data.data.exists;
						canGenerateFavoritesPage = data.data.can_generate || false;
						const responseType = data.data.response_type || 'info';
						const editLink = data.data.edit_link || '';

						elements.favoritesPageStatus.className = `slug-status ${responseType}`;

						let icon = '';
						switch (responseType) {
							case 'success': icon = '✓'; break;
							case 'warning': icon = '⚠️'; break;
							case 'error': icon = '❌'; break;
							default: icon = 'ℹ️';
						}

						let statusMessage = `${icon} ${data.data.message}`;
						if (editLink && currentFavoritesPageExists) {
							statusMessage += ` <a href="${editLink}" target="_blank"><?php esc_html_e( 'Edit Page', 'brag-book-gallery' ); ?></a>`;
						}

						elements.favoritesPageStatus.innerHTML = statusMessage;

						if (canGenerateFavoritesPage) {
							elements.generateFavoritesBtn.disabled = false;
							elements.generateFavoritesBtn.textContent = '<?php esc_html_e( 'Generate My Favorites Page', 'brag-book-gallery' ); ?>';
						} else {
							elements.generateFavoritesBtn.disabled = true;
							elements.generateFavoritesBtn.textContent = currentFavoritesPageExists ?
								'<?php esc_html_e( 'Page Already Exists', 'brag-book-gallery' ); ?>' :
								'<?php esc_html_e( 'Generate My Favorites Page', 'brag-book-gallery' ); ?>';
						}
					} else {
						elements.favoritesPageStatus.className = 'slug-status error';
						elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Error checking page status', 'brag-book-gallery' ); ?>';
						elements.generateFavoritesBtn.disabled = true;
						canGenerateFavoritesPage = false;
					}
				} catch (error) {
					console.error('Error checking favorites page status:', error);
					elements.favoritesPageStatus.className = 'slug-status error';

					if (error.name === 'AbortError') {
						elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Request timed out. Please try again.', 'brag-book-gallery' ); ?>';
					} else if (error.message && error.message.includes('fetch')) {
						elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Connection error. Check your internet connection.', 'brag-book-gallery' ); ?>';
					} else {
						elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Error checking page status', 'brag-book-gallery' ); ?>';
					}

					elements.generateFavoritesBtn.disabled = true;
					canGenerateFavoritesPage = false;
				}
			};

			/**
			 * Handle favorites page generation
			 */
			const handleGenerateFavoritesPage = async () => {
				if (!elements.generateFavoritesBtn || !elements.favoritesPageStatus) return;

				const gallerySlug = elements.gallerySlugInput ? elements.gallerySlugInput.value.trim() : '';

				if (!gallerySlug || !canGenerateFavoritesPage) return;

				try {
					elements.generateFavoritesBtn.disabled = true;
					elements.generateFavoritesBtn.textContent = '<?php esc_html_e( 'Creating...', 'brag-book-gallery' ); ?>';

					const formData = new FormData();
					formData.append('action', 'brag_book_gallery_generate_favorites_page');
					formData.append('gallery_slug', gallerySlug);
					formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_generate_favorites_page' ); ?>');

					const controller = new AbortController();
					const timeoutId = setTimeout(() => controller.abort(), 45000);

					const response = await fetch(ajaxurl, {
						method: 'POST',
						body: formData,
						signal: controller.signal
					});

					clearTimeout(timeoutId);
					const data = await response.json();

					if (data.success) {
						elements.favoritesPageStatus.className = 'slug-status success';
						elements.favoritesPageStatus.innerHTML = `✓ ${data.data.message}`;
						elements.generateFavoritesBtn.textContent = '<?php esc_html_e( 'Page Already Exists', 'brag-book-gallery' ); ?>';
						elements.generateFavoritesBtn.disabled = true;
						currentFavoritesPageExists = true;
						canGenerateFavoritesPage = false;

						if (data.data.edit_link) {
							elements.favoritesPageStatus.innerHTML += ` <a href="${data.data.edit_link}" target="_blank"><?php esc_html_e( 'Edit Page', 'brag-book-gallery' ); ?></a>`;
						}
					} else {
						elements.favoritesPageStatus.className = 'slug-status error';
						elements.favoritesPageStatus.textContent = data.data || '<?php esc_html_e( 'Failed to create page', 'brag-book-gallery' ); ?>';
						elements.generateFavoritesBtn.textContent = '<?php esc_html_e( 'Generate My Favorites Page', 'brag-book-gallery' ); ?>';
						elements.generateFavoritesBtn.disabled = false;
					}
				} catch (error) {
					console.error('Error generating favorites page:', error);
					elements.favoritesPageStatus.className = 'slug-status error';

					if (error.name === 'AbortError') {
						elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Page creation timed out. This may happen on managed hosting. Please check if the page was created and try refreshing.', 'brag-book-gallery' ); ?>';
					} else if (error.message && error.message.includes('fetch')) {
						elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Connection error. Please check your internet connection and try again.', 'brag-book-gallery' ); ?>';
					} else {
						elements.favoritesPageStatus.textContent = '<?php esc_html_e( 'Unexpected error during page creation', 'brag-book-gallery' ); ?>';
					}

					elements.generateFavoritesBtn.textContent = '<?php esc_html_e( 'Generate My Favorites Page', 'brag-book-gallery' ); ?>';
					elements.generateFavoritesBtn.disabled = false;
				}
			};

			/**
			 * Initialize event listeners
			 */
			const initializeFavoritesEventListeners = () => {
				// Check status when gallery slug changes
				if (elements.gallerySlugInput) {
					elements.gallerySlugInput.addEventListener('input', () => {
						clearTimeout(checkFavoritesTimeout);
						checkFavoritesTimeout = setTimeout(checkFavoritesPageStatus, 500);
					});
				}

				// Generate button click handler
				if (elements.generateFavoritesBtn) {
					elements.generateFavoritesBtn.addEventListener('click', handleGenerateFavoritesPage);
				}

				// Check status on initial page load
				checkFavoritesPageStatus();
			};

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initializeFavoritesEventListeners);
			} else {
				initializeFavoritesEventListeners();
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
	 * Render display settings tab
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_display_settings_tab(): void {

		// Gallery display settings.
		$columns             = absint( get_option( 'brag_book_gallery_columns', 2 ) );
		$main_gallery_view   = sanitize_text_field( get_option( 'brag_book_gallery_main_gallery_view', 'default' ) );
		$procedures_view     = sanitize_text_field( get_option( 'brag_book_gallery_procedures_view', 'default' ) );
		$cases_view          = sanitize_text_field( get_option( 'brag_book_gallery_cases_view', 'default' ) );
		$favorites_view      = sanitize_text_field( get_option( 'brag_book_gallery_favorites_view', 'default' ) );
		$case_card_type      = sanitize_text_field( get_option( 'brag_book_gallery_case_card_type', 'default' ) );
		$case_image_carousel = (bool) get_option( 'brag_book_gallery_case_image_carousel', false );
		$items_per_page      = absint( get_option( 'brag_book_gallery_items_per_page', 200 ) );

		// Landing page content.
		$default_landing_text = sprintf(
			'<h2>%s</h2>' . "\n" . '<p>%s</p>',
			__( 'Go ahead, browse our before & afters... visualize your possibilities.', 'brag-book-gallery' ),
			__( 'Our gallery is full of our real patients. Keep in mind results vary.', 'brag-book-gallery' )
		);
		$landing_text = wp_kses_post( get_option( 'brag_book_gallery_landing_page_text', $default_landing_text ) );
		$slug         = sanitize_title( get_option( 'brag_book_gallery_page_slug', '' ) );

		// Toggle settings.
		$expand_nav_menus    = (bool) get_option( 'brag_book_gallery_expand_nav_menus', false );
		$show_filter_counts  = (bool) get_option( 'brag_book_gallery_show_filter_counts', true );
		$enable_favorites    = (bool) get_option( 'brag_book_gallery_enable_favorites', true );
		$enable_consultation = (bool) get_option( 'brag_book_gallery_enable_consultation', true );
		$show_doctor         = (bool) get_option( 'brag_book_gallery_show_doctor', false );

		// Property ID validation.
		$website_property_ids = (array) get_option( 'brag_book_gallery_website_property_id', array() );
		$has_property_111     = in_array( '111', $website_property_ids, true );

		// Current mode (default only)
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
						<?php esc_html_e( 'My Favorites Page', 'brag-book-gallery' ); ?>
					</th>
					<td>
						<?php
						// Check if favorites page already exists
						$gallery_page_slug = get_option( 'brag_book_gallery_page_slug', '' );
						$favorites_exists = false;
						$favorites_edit_link = '';
						$favorites_button_text = __( 'Generate My Favorites Page', 'brag-book-gallery' );
						$favorites_button_disabled = true;
						$show_initial_status = false;
						$initial_status_message = '';
						$initial_status_class = '';

						if ( ! empty( $gallery_page_slug ) ) {
							$favorites_full_path = $gallery_page_slug . '/myfavorites';
							$favorites_page = get_page_by_path( $favorites_full_path );

							if ( $favorites_page ) {
								$favorites_exists = true;
								$favorites_edit_link = get_edit_post_link( $favorites_page->ID, 'raw' );
								$favorites_button_disabled = true;
								$favorites_button_text = __( 'Page Already Exists', 'brag-book-gallery' );
								$show_initial_status = true;
								$initial_status_message = __( 'My Favorites page already exists.', 'brag-book-gallery' );
								$initial_status_class = 'slug-status success';
							} else {
								$favorites_button_disabled = false;
								$show_initial_status = true;
								$initial_status_message = __( 'Ready to create My Favorites page.', 'brag-book-gallery' );
								$initial_status_class = 'slug-status success';
							}
						} else {
							$show_initial_status = true;
							$initial_status_message = __( 'Please create the gallery page first.', 'brag-book-gallery' );
							$initial_status_class = 'slug-status';
						}
						?>
						<button type="button"
						        id="generate-favorites-page-btn"
						        class="button button-secondary"
						        <?php echo $favorites_button_disabled ? 'disabled' : ''; ?>>
							<?php echo esc_html( $favorites_button_text ); ?>
						</button>
						<div id="favorites-page-status-message" class="<?php echo esc_attr( $initial_status_class ); ?>" style="margin-top: 10px;">
							<?php
							if ( $show_initial_status ) {
								$icon = $favorites_exists ? '✓' : ( empty( $gallery_page_slug ) ? '' : '✓' );
								echo esc_html( $icon . ' ' . $initial_status_message );
								if ( $favorites_exists && ! empty( $favorites_edit_link ) ) {
									echo ' <a href="' . esc_url( $favorites_edit_link ) . '" target="_blank">' . esc_html__( 'Edit Page', 'brag-book-gallery' ) . '</a>';
								}
							}
							?>
						</div>
						<p class="description">
							<?php esc_html_e( 'Automatically creates a "My Favorites" child page under your gallery page. This button is only available after the gallery page is created.', 'brag-book-gallery' ); ?>
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
						// Properly unslash the content - WordPress adds slashes when retrieving from options
						$editor_content = wp_unslash( $landing_text );
						?>

						<!-- Trumbowyg WYSIWYG Editor Container -->
						<div id="trumbowyg-editor-wrapper" style="margin-bottom: 10px;">
							<textarea id="brag_book_gallery_landing_page_text"
							          name="brag_book_gallery_landing_page_text"
							          style="width: 100%;"><?php echo esc_textarea( $editor_content ); ?></textarea>
						</div>

						<p class="description">
							<?php esc_html_e( 'Text displayed on the gallery landing page. Edit visually or switch to HTML view using the toolbar.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- Display Settings Section - Now Second -->
			<h3><?php esc_html_e( 'Display Settings', 'brag-book-gallery' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="brag_book_gallery_main_gallery_view">
							<?php esc_html_e( 'Main Gallery View Type', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<select id="brag_book_gallery_main_gallery_view" name="brag_book_gallery_main_gallery_view">
							<option value="default" <?php selected( $main_gallery_view, 'default' ); ?>>
								<?php esc_html_e( 'Default', 'brag-book-gallery' ); ?>
							</option>
							<option value="columns" <?php selected( $main_gallery_view, 'columns' ); ?>>
								<?php esc_html_e( 'Columns', 'brag-book-gallery' ); ?>
							</option>
							<option value="tiles" <?php selected( $main_gallery_view, 'tiles' ); ?>>
								<?php esc_html_e( 'Tiles', 'brag-book-gallery' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose the view type for the main gallery display. Tiles view shows the landing page text.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_procedures_view">
							<?php esc_html_e( 'Procedures View Type', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<select id="brag_book_gallery_procedures_view" name="brag_book_gallery_procedures_view">
							<option value="default" <?php selected( $procedures_view, 'default' ); ?>>
								<?php esc_html_e( 'Default', 'brag-book-gallery' ); ?>
							</option>
							<option value="tiles" <?php selected( $procedures_view, 'tiles' ); ?>>
								<?php esc_html_e( 'Tiles', 'brag-book-gallery' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose the view type for procedures display.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_cases_view">
							<?php esc_html_e( 'Cases View Type', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<select id="brag_book_gallery_cases_view" name="brag_book_gallery_cases_view">
							<option value="default" <?php selected( $cases_view, 'default' ); ?>>
								<?php esc_html_e( 'Default', 'brag-book-gallery' ); ?>
							</option>
							<option value="alternative" <?php selected( $cases_view, 'alternative' ); ?>>
								<?php esc_html_e( 'Alternative', 'brag-book-gallery' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose the view type for cases display.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_favorites_view">
							<?php esc_html_e( 'MyFavorites View Type', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<select id="brag_book_gallery_favorites_view" name="brag_book_gallery_favorites_view">
							<option value="default" <?php selected( $favorites_view, 'default' ); ?>>
								<?php esc_html_e( 'Default', 'brag-book-gallery' ); ?>
							</option>
							<option value="alternative" <?php selected( $favorites_view, 'alternative' ); ?>>
								<?php esc_html_e( 'Alternative', 'brag-book-gallery' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose the view type for favorites display.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_case_card_type">
							<?php esc_html_e( 'Case Card Type', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<select id="brag_book_gallery_case_card_type" name="brag_book_gallery_case_card_type">
							<option value="default" <?php selected( $case_card_type, 'default' ); ?>>
								<?php esc_html_e( 'Default', 'brag-book-gallery' ); ?>
							</option>
							<option value="v2" <?php selected( $case_card_type, 'v2' ); ?>>
								<?php esc_html_e( 'V2', 'brag-book-gallery' ); ?>
							</option>
							<option value="v3" <?php selected( $case_card_type, 'v3' ); ?>>
								<?php esc_html_e( 'V3', 'brag-book-gallery' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose the card design type for case display.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_case_image_carousel">
							<?php esc_html_e( 'Enable Case Image Carousel', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="hidden" name="brag_book_gallery_case_image_carousel" value="0" />
								<input type="checkbox"
								       id="brag_book_gallery_case_image_carousel"
								       name="brag_book_gallery_case_image_carousel"
								       value="1"
								       <?php checked( $case_image_carousel, true ); ?> />
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Show carousel of high-resolution images in V2/V3 cards', 'brag-book-gallery' ); ?>
							</span>
						</div>
					</td>
				</tr>

				<?php if ( $has_property_111 ) : ?>
				<tr>
					<th scope="row">
						<label for="brag_book_gallery_show_doctor">
							<?php esc_html_e( 'Show Doctor Details', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="hidden" name="brag_book_gallery_show_doctor" value="0" />
								<input type="checkbox"
								       id="brag_book_gallery_show_doctor"
								       name="brag_book_gallery_show_doctor"
								       value="1"
								       <?php checked( $show_doctor, true ); ?> />
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Display doctor information in cases', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'When enabled, doctor details will be shown in case displays.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
				<?php endif; ?>

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
							   max="200"
							   step="1" />
						<p class="description">
							<?php esc_html_e( 'Number of gallery items to load initially. Additional items can be loaded with the "Load More" button.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="brag_book_gallery_release_channel">
							<?php esc_html_e( 'Plugin Update Channel', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<?php
						$release_channel = get_option( 'brag_book_gallery_release_channel', 'stable' );
						?>
						<select id="brag_book_gallery_release_channel" name="brag_book_gallery_release_channel">
							<option value="stable" <?php selected( $release_channel, 'stable' ); ?>>
								<?php esc_html_e( 'Stable (Recommended)', 'brag-book-gallery' ); ?>
							</option>
							<option value="rc" <?php selected( $release_channel, 'rc' ); ?>>
								<?php esc_html_e( 'Release Candidate', 'brag-book-gallery' ); ?>
							</option>
							<option value="beta" <?php selected( $release_channel, 'beta' ); ?>>
								<?php esc_html_e( 'Beta (Early Testing)', 'brag-book-gallery' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose which plugin updates to receive:', 'brag-book-gallery' ); ?>
							<br/>
							<strong><?php esc_html_e( 'Stable:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Production-ready releases only (recommended for live sites)', 'brag-book-gallery' ); ?>
							<br/>
							<strong><?php esc_html_e( 'Release Candidate:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Near-final versions for testing before stable release', 'brag-book-gallery' ); ?>
							<br/>
							<strong><?php esc_html_e( 'Beta:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Early access to new features (not recommended for production sites)', 'brag-book-gallery' ); ?>
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
	 * Render SEO Settings tab content
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_seo_settings_tab(): void {

		// Get SEO options (handle array format from API settings).
		$seo_title_option = get_option( 'brag_book_gallery_seo_page_title', '' );

		if ( is_array( $seo_title_option ) ) {
			$seo_title = ! empty( $seo_title_option[0] ) ? (string) $seo_title_option[0] : '';
		} else {
			$seo_title = (string) $seo_title_option;
		}

		$seo_desc_option = get_option( 'brag_book_gallery_seo_page_description', '' );

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
					<?php esc_html_e( 'BRAG book Gallery is integrated with your SEO plugin for optimized meta tags and structured data.', 'brag-book-gallery' ); ?>
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
								<input type="hidden" name="lazy_load" value="no" />
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
						<div id="monaco-editor-wrapper" style="margin-bottom: 10px;">
							<div id="monaco-editor-container" style="height: 500px; border: 1px solid #dcdcde; border-radius: 4px;"></div>
						</div>
						<textarea id="brag_book_gallery_custom_css"
						          name="brag_book_gallery_custom_css"
						          style="display: none;"><?php echo esc_textarea( $custom_css ); ?></textarea>
						<div class="monaco-editor-toolbar" style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
							<button type="button" id="format-css" class="button button-secondary">
								<?php esc_html_e( 'Format CSS', 'brag-book-gallery' ); ?>
							</button>
							<button type="button" id="reset-css" class="button button-secondary">
								<?php esc_html_e( 'Reset', 'brag-book-gallery' ); ?>
							</button>
							<span class="monaco-editor-status" style="margin-left: auto; color: #666; font-size: 12px;">
								<span id="css-line-count">0</span> <?php esc_html_e( 'lines', 'brag-book-gallery' ); ?> |
								<span id="css-char-count">0</span> <?php esc_html_e( 'characters', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description" style="margin-top: 10px;">
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

			// Monaco Editor initialization removed - now loaded conditionally via enqueue_monaco_conditionally()
			// to prevent AMD/RequireJS conflicts with TinyMCE

		});
		</script>
		<?php
	}

	/**
	 * Save general settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_general_settings(): void {

		// Handle custom CSS save separately
		if ( isset( $_POST['submit_css'] ) ) {
			$this->save_custom_css();
			return;
		}

		// Save landing page text
		$this->save_landing_page_text();

		// Save gallery page slug and create pages if needed
		$this->save_gallery_page_slug();

		// Save gallery display settings
		$this->save_display_settings();

		// Save view type settings
		$this->save_view_settings();

		// Save plugin release channel
		$this->save_release_channel();

		// Save navigation and filter settings
		$this->save_navigation_settings();

		// Save feature toggles
		$this->save_feature_toggles();

		// Clear settings cache
		if ( class_exists( '\BRAGBookGallery\Includes\Core\Settings_Helper' ) ) {
			\BRAGBookGallery\Includes\Core\Settings_Helper::clear_cache();
		}

		$this->add_notice(
			__( 'General settings saved successfully.', 'brag-book-gallery' ),
			'success'
		);
	}

	/**
	 * Save custom CSS
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_custom_css(): void {
		if ( ! isset( $_POST['brag_book_gallery_custom_css'] ) ) {
			return;
		}

		// Sanitize CSS while preserving valid syntax
		$custom_css = wp_strip_all_tags( wp_unslash( $_POST['brag_book_gallery_custom_css'] ) );

		// Remove potential XSS vectors
		$dangerous_patterns = array( '<script', '</script', '<style', '</style', 'javascript:', 'expression(' );
		$custom_css = str_replace( $dangerous_patterns, '', $custom_css );

		update_option( 'brag_book_gallery_custom_css', $custom_css );

		$this->add_notice(
			__( 'Custom CSS saved successfully.', 'brag-book-gallery' ),
			'success'
		);
	}

	/**
	 * Save landing page text
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_landing_page_text(): void {
		if ( ! isset( $_POST['brag_book_gallery_landing_page_text'] ) ) {
			return;
		}

		$landing_text = wp_kses_post( wp_unslash( $_POST['brag_book_gallery_landing_page_text'] ) );
		update_option( 'brag_book_gallery_landing_page_text', $landing_text );
	}

	/**
	 * Save gallery page slug and create pages
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_gallery_page_slug(): void {
		if ( ! isset( $_POST['brag_book_gallery_page_slug'] ) ) {
			return;
		}

		$new_slug = sanitize_title( wp_unslash( $_POST['brag_book_gallery_page_slug'] ) );
		$old_slug = get_option( 'brag_book_gallery_page_slug', '' );

		// Only proceed if slug changed and is not empty
		if ( empty( $new_slug ) || $new_slug === $old_slug ) {
			return;
		}

		// Check if page exists or create new one
		$existing_page = get_page_by_path( $new_slug );

		if ( ! $existing_page ) {
			$page_id = $this->create_gallery_page( $new_slug );
		} else {
			$page_id = $existing_page->ID;
			update_option( 'brag_book_gallery_page_id', $page_id );
		}

		// Create My Favorites child page if parent was created/found
		if ( ! empty( $page_id ) ) {
			$this->create_favorites_page( $new_slug, $page_id );
		}

		// Update slug options
		update_option( 'brag_book_gallery_page_slug', $new_slug );
		update_option( 'brag_book_gallery_slug', $new_slug ); // Backward compatibility

		// Flush rewrite rules after slug change
		flush_rewrite_rules();
	}

	/**
	 * Create gallery page with shortcode
	 *
	 * @since 3.0.0
	 * @param string $slug Page slug.
	 * @return int|false Page ID on success, false on failure.
	 */
	private function create_gallery_page( string $slug ): false|int {
		$page_data = array(
			'post_title'     => ucwords( str_replace( '-', ' ', $slug ) ),
			'post_name'      => $slug,
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_content'   => '[brag_book_gallery]',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);

		$page_id = wp_insert_post( $page_data );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'brag_book_gallery_page_id', $page_id );
			$this->add_notice(
				sprintf(
				/* translators: %s: gallery page slug */
					__( 'Gallery page "%s" created successfully.', 'brag-book-gallery' ),
					$slug
				),
				'success'
			);
			return $page_id;
		}

		return false;
	}

	/**
	 * Create My Favorites child page
	 *
	 * @since 3.0.0
	 * @param string $parent_slug Parent page slug.
	 * @param int    $parent_id   Parent page ID.
	 * @return void
	 */
	private function create_favorites_page( string $parent_slug, int $parent_id ): void {
		// Check if favorites page already exists
		$favorites_full_path = sprintf( '%s/myfavorites', $parent_slug );
		$favorites_page      = get_page_by_path( $favorites_full_path );

		if ( $favorites_page ) {
			// Page exists, update options
			update_option( 'brag_book_gallery_favorites_slug', 'myfavorites' );
			update_option( 'brag_book_gallery_favorites_page_id', $favorites_page->ID );
			return;
		}

		// Create My Favorites child page
		$favorites_page_data = array(
			'post_title'     => __( 'My Favorites', 'brag-book-gallery' ),
			'post_name'      => 'myfavorites',
			'post_parent'    => $parent_id,
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_content'   => '[brag_book_gallery view="favorites"]',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);

		$favorites_page_id = wp_insert_post( $favorites_page_data );

		if ( $favorites_page_id && ! is_wp_error( $favorites_page_id ) ) {
			update_option( 'brag_book_gallery_favorites_page_id', $favorites_page_id );
			update_option( 'brag_book_gallery_favorites_slug', 'myfavorites' );
			$this->add_notice(
				__( 'My Favorites page created successfully.', 'brag-book-gallery' ),
				'success'
			);
		} else {
			$this->add_notice(
				__( 'Could not create My Favorites page.', 'brag-book-gallery' ),
				'warning'
			);
		}
	}

	/**
	 * Save gallery display settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_display_settings(): void {
		// Columns (integer)
		$columns = isset( $_POST['brag_book_gallery_columns'] )
			? absint( $_POST['brag_book_gallery_columns'] )
			: 2;
		update_option( 'brag_book_gallery_columns', $columns );

		// Items per page (integer)
		$items_per_page = isset( $_POST['brag_book_gallery_items_per_page'] )
			? absint( $_POST['brag_book_gallery_items_per_page'] )
			: 200;
		update_option( 'brag_book_gallery_items_per_page', $items_per_page );

		// Case image carousel (boolean)
		$case_image_carousel = isset( $_POST['brag_book_gallery_case_image_carousel'] )
							   && '1' === $_POST['brag_book_gallery_case_image_carousel'];
		update_option( 'brag_book_gallery_case_image_carousel', $case_image_carousel );
	}

	/**
	 * Save view type settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_view_settings(): void {

		// View settings with allowed values.
		$view_settings = array(
			'brag_book_gallery_main_gallery_view' => array( 'default', 'columns', 'tiles' ),
			'brag_book_gallery_procedures_view'   => array( 'default', 'tiles' ),
			'brag_book_gallery_cases_view'        => array( 'default', 'alternative' ),
			'brag_book_gallery_favorites_view'    => array( 'default', 'alternative' ),
			'brag_book_gallery_case_card_type'    => array( 'default', 'v2', 'v3' ),
		);

		foreach ( $view_settings as $option_name => $allowed_values ) {
			$value = isset( $_POST[ $option_name ] )
				? sanitize_text_field( wp_unslash( $_POST[ $option_name ] ) )
				: 'default';

			// Validate against allowed values
			if ( ! in_array( $value, $allowed_values, true ) ) {
				$value = 'default';
			}

			update_option( $option_name, $value );
		}
	}

	/**
	 * Save plugin release channel
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_release_channel(): void {

		if ( ! isset( $_POST['brag_book_gallery_release_channel'] ) ) {
			return;
		}

		$allowed_channels = array(
			'stable',
			'rc',
			'beta'
		);

		$release_channel  = sanitize_text_field( wp_unslash( $_POST['brag_book_gallery_release_channel'] ) );

		// Validate channel value.
		if ( ! in_array( $release_channel, $allowed_channels, true ) ) {
			$release_channel = 'stable';
		}

		// Clear updater cache if channel changed.
		$old_channel = get_option( 'brag_book_gallery_release_channel', 'stable' );

		if ( $old_channel !== $release_channel ) {
			// Clear all channel caches
			foreach ( $allowed_channels as $channel ) {
				delete_transient(
					'brag_book_gallery_github_release_' . md5( 'bragbook2_brag-book-gallery_' . $channel )
				);
			}
			delete_site_transient( 'update_plugins' );
		}

		update_option( 'brag_book_gallery_release_channel', $release_channel );
	}

	/**
	 * Save navigation and filter settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_navigation_settings(): void {

		// Expand navigation menus.
		$expand_nav_menus = isset( $_POST['brag_book_gallery_expand_nav_menus'] ) && '1' === $_POST['brag_book_gallery_expand_nav_menus'];

		update_option(
			'brag_book_gallery_expand_nav_menus',
			$expand_nav_menus
		);

		// Show filter counts
		$show_filter_counts = isset( $_POST['brag_book_gallery_show_filter_counts'] ) && '1' === $_POST['brag_book_gallery_show_filter_counts'];

		update_option(
			'brag_book_gallery_show_filter_counts',
			$show_filter_counts
		);
	}

	/**
	 * Save feature toggle settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_feature_toggles(): void {

		$features = array(
			'brag_book_gallery_enable_favorites',
			'brag_book_gallery_enable_consultation',
			'brag_book_gallery_show_doctor',
		);

		foreach ( $features as $feature ) {
			$value = isset( $_POST[ $feature ] ) && '1' === $_POST[ $feature ];
			update_option( $feature, $value );
		}
	}

	/**
	 * Save default settings (for SEO and Performance forms)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_default_settings(): void {
		$saved_seo = $this->save_seo_settings();
		$saved_performance = $this->save_performance_settings();

		// Show appropriate success message
		if ( $saved_seo ) {
			$this->add_notice(
				__( 'SEO settings saved successfully.', 'brag-book-gallery' ),
				'success'
			);
		} elseif ( $saved_performance ) {
			$this->add_notice(
				__( 'Performance settings saved successfully.', 'brag-book-gallery' ),
				'success'
			);
		} else {
			$this->add_notice(
				__( 'Settings saved successfully.', 'brag-book-gallery' ),
				'success'
			);
		}
	}

	/**
	 * Save SEO settings
	 *
	 * @since 3.0.0
	 * @return bool True if SEO settings were saved, false otherwise.
	 */
	private function save_seo_settings(): bool {
		$saved = false;

		// SEO page title
		if ( isset( $_POST['brag_book_gallery_seo_title'] ) ) {
			$seo_title = sanitize_text_field( wp_unslash( $_POST['brag_book_gallery_seo_title'] ) );
			update_option( 'brag_book_gallery_seo_page_title', $seo_title );
			$saved = true;
		}

		// SEO page description
		if ( isset( $_POST['brag_book_gallery_seo_description'] ) ) {
			$seo_description = sanitize_textarea_field( wp_unslash( $_POST['brag_book_gallery_seo_description'] ) );
			update_option( 'brag_book_gallery_seo_page_description', $seo_description );
			$saved = true;
		}

		return $saved;
	}

	/**
	 * Save performance settings
	 *
	 * @since 3.0.0
	 * @return bool True if performance settings were saved, false otherwise.
	 */
	private function save_performance_settings(): bool {
		$saved = false;

		// AJAX timeout (milliseconds)
		if ( isset( $_POST['ajax_timeout'] ) ) {
			$ajax_timeout = absint( $_POST['ajax_timeout'] );
			update_option( 'brag_book_gallery_ajax_timeout', $ajax_timeout );
			$saved = true;
		}

		// Cache duration (seconds)
		if ( isset( $_POST['cache_duration'] ) ) {
			$cache_duration = absint( $_POST['cache_duration'] );
			update_option( 'brag_book_gallery_cache_duration', $cache_duration );
			$saved = true;
		}

		// Lazy load images
		if ( isset( $_POST['lazy_load'] ) ) {
			$lazy_load = 'yes' === $_POST['lazy_load'] ? 'yes' : 'no';
			update_option( 'brag_book_gallery_lazy_load', $lazy_load );
			$saved = true;
		}

		return $saved;
	}

	/**
	 * Enqueue Monaco Editor conditionally to avoid AMD conflicts
	 *
	 * Loads Monaco Editor dynamically only when Custom CSS tab becomes active.
	 * This runs AFTER AMD protection has removed any existing AMD loaders,
	 * so Monaco can safely add its own AMD loader for the editor.
	 *
	 * @since 3.3.2
	 * @return void
	 */
	public function enqueue_monaco_conditionally(): void {
		?>
		<script>
		(function($) {
			'use strict';

			var monacoLoaded = false;
			var monacoEditor = null;
			var monacoRequire = null;

			// Function to load Monaco Editor
			function loadMonaco() {
				if (monacoLoaded) return;
				monacoLoaded = true;

				console.log('BRAGBook: Loading Monaco Editor');

				// Load Monaco loader script
				var script = document.createElement('script');
				script.src = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js';
				script.onload = function() {
					// Capture Monaco's require and define
					monacoRequire = window.require;
					var monacoDefine = window.define;

					console.log('BRAGBook: Monaco loader loaded');

					// Keep AMD available for Monaco but isolate it
					// Monaco needs these to load its modules

					// Configure Monaco using its own require
					monacoRequire.config({
						paths: {
							'vs': 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs'
						}
					});

					// Initialize Monaco when loaded using its own require
					monacoRequire(['vs/editor/editor.main'], function() {
						// Monaco is now loaded, capture the monaco global
						var monacoInstance = window.monaco;

						var textarea = document.getElementById('brag_book_gallery_custom_css');
						var formatBtn = document.getElementById('format-css');
						var resetBtn = document.getElementById('reset-css');
						var lineCountEl = document.getElementById('css-line-count');
						var charCountEl = document.getElementById('css-char-count');

						// Use the existing container from the HTML
						var container = document.getElementById('monaco-editor-container');

						if (container && textarea && !monacoEditor && monacoInstance) {
							console.log('BRAGBook: Initializing Monaco Editor');

							monacoEditor = monacoInstance.editor.create(container, {
								value: textarea.value,
								language: 'css',
								theme: 'vs',
								automaticLayout: true,
								minimap: { enabled: false },
								scrollBeyondLastLine: false,
								fontSize: 13,
								lineNumbers: 'on',
								wordWrap: 'on',
								tabSize: 4,
								insertSpaces: false,
								fontFamily: 'Consolas, Monaco, "Courier New", monospace',
								renderLineHighlight: 'line',
								selectOnLineNumbers: true,
								roundedSelection: false,
								showUnused: true,
								folding: true,
								foldingHighlight: true
							});

							// Update statistics
							var updateStats = function() {
								var content = monacoEditor.getValue();
								var lines = content.split('\n').length;
								var chars = content.length;
								if (lineCountEl) lineCountEl.textContent = lines;
								if (charCountEl) charCountEl.textContent = chars;
							};

							// Sync Monaco content with textarea on change
							monacoEditor.onDidChangeModelContent(function() {
								textarea.value = monacoEditor.getValue();
								updateStats();
							});

							// Format CSS button
							if (formatBtn) {
								formatBtn.addEventListener('click', function() {
									monacoEditor.getAction('editor.action.formatDocument').run();
								});
							}

							// Reset button
							if (resetBtn) {
								resetBtn.addEventListener('click', function() {
									if (confirm('<?php esc_html_e( 'Are you sure you want to reset all custom CSS? This cannot be undone.', 'brag-book-gallery' ); ?>')) {
										monacoEditor.setValue('');
									}
								});
							}

							// Initial stats update
							updateStats();

							// Focus editor for better UX
							monacoEditor.focus();
						}
					});
				};
				document.head.appendChild(script);
			}

			// Function to dispose Monaco and clean up AMD
			function disposeMonaco() {
				if (monacoEditor) {
					console.log('BRAGBook: Disposing Monaco Editor');

					// Sync final content to textarea before disposing
					var textarea = document.getElementById('brag_book_gallery_custom_css');
					if (textarea && monacoEditor) {
						textarea.value = monacoEditor.getValue();
					}

					// Dispose the editor
					monacoEditor.dispose();
					monacoEditor = null;

					// Remove AMD loader to prevent TinyMCE conflicts
					try {
						if (typeof window.define === 'function' && window.define.amd) {
							console.log('BRAGBook: Removing AMD loader after disposing Monaco');
							delete window.define;
							delete window.require;
							delete window.monaco;
						}
					} catch (e) {
						console.log('BRAGBook: Could not remove AMD (non-configurable)');
					}

					monacoLoaded = false;
					monacoRequire = null;
				}
			}

			// Load Monaco when Custom CSS tab is clicked
			$(document).ready(function() {
				// Check if we're on the General Settings page
				if ($('#custom-css').length) {
					var $tabLinks = $('.brag-book-gallery-side-tabs a');

					// Load Monaco when Custom CSS tab is clicked
					$('a[href="#custom-css"]').on('click', function() {
						console.log('BRAGBook: Custom CSS tab activated, loading Monaco');
						setTimeout(loadMonaco, 100);
					});

					// Dispose Monaco when switching away from Custom CSS tab
					$tabLinks.not('a[href="#custom-css"]').on('click', function() {
						console.log('BRAGBook: Switching away from Custom CSS tab');
						disposeMonaco();
					});

					// If Custom CSS tab is active on page load (e.g., from URL hash)
					if (window.location.hash === '#custom-css') {
						setTimeout(loadMonaco, 100);
					}
				}

			});
		})(jQuery);
		</script>
		<?php
	}

}
