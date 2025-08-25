<?php
/**
 * Default Mode Settings Class
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
 * Default Mode Settings Class
 *
 * Specialized configuration interface for default mode operations in BRAG book Gallery.
 * This class manages settings that specifically affect how the plugin operates when
 * in default mode, where content is loaded dynamically from the BRAG book API.
 *
 * **Configuration Categories:**
 * - Gallery page settings and SEO metadata
 * - Performance optimization parameters
 * - Display and pagination controls
 * - AJAX timeout and caching configurations
 *
 * **Default Mode Features:**
 * - Real-time content loading from external API
 * - Dynamic gallery filtering and pagination
 * - Client-side image lazy loading
 * - Configurable cache duration for performance
 * - Custom SEO metadata for virtual pages
 *
 * **Performance Settings:**
 * - AJAX timeout configuration for API calls
 * - Cache duration settings for response data
 * - Lazy loading controls for image optimization
 * - Pagination settings for large galleries
 *
 * These settings only apply when the plugin operates in default mode,
 * providing fine-tuned control over API-driven content delivery.
 *
 * @since 3.0.0
 */
class Settings_Default extends Settings_Base {

	/**
	 * Initialize the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug  = 'brag-book-gallery-default';
		// Don't translate here - translations happen in render
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set translated strings when rendering (after init)
		$this->page_title = __( 'Default Mode Settings', 'brag-book-gallery' );
		$this->menu_title = __( 'Default Settings', 'brag-book-gallery' );

		// Handle form submission
		if ( isset( $_POST['submit'] ) ) {
			$this->save();
		}

		// Get current settings with default value
		$default_landing_text = '<h2>Go ahead, browse our before & afters... visualize your possibilities.</h2>' . "\n" . 
		                       '<p>Our gallery is full of our real patients. Keep in mind results vary.</p>';
		$landing_text            = get_option( 'brag_book_gallery_landing_page_text', $default_landing_text );
		// Use helper to get the first slug (handles array/string formats)
		$slug            = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( '' );
		
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
		$ajax_timeout            = get_option( 'brag_book_gallery_ajax_timeout', 30 );
		$cache_duration          = get_option( 'brag_book_gallery_cache_duration', 300 );
		$lazy_load              = get_option( 'brag_book_gallery_lazy_load', 'yes' );

		$this->render_header();

		// Show notice if not in default mode
		if ( ! $this->is_default_mode() ) {
			?>
			<div class="brag-book-gallery-notice brag-book-gallery-notice--warning">
				<p>
					<?php esc_html_e( 'These settings only apply when default mode is active.', 'brag-book-gallery' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-mode' ) ); ?>">
						<?php esc_html_e( 'Switch to Default Mode', 'brag-book-gallery' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
		?>
		<form method="post" action="" id="brag-book-gallery-default-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_default_settings', 'brag_book_gallery_default_nonce' ); ?>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Gallery Page Settings', 'brag-book-gallery' ); ?></h2>
				<table class="form-table brag-book-gallery-form-table" role="presentation">
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
								'textarea_rows' => 10,
								'media_buttons' => true,
								'teeny'         => false,
								'tinymce'       => array(
									'height' => 300,
									'convert_urls' => false,
									'remove_linebreaks' => false,
									'gecko_spellcheck' => false,
									'keep_styles' => false,
									'accessibility_focus' => false,
									'tabfocus_elements' => 'major-publishing-actions',
									'media_strict' => false,
									'paste_remove_styles' => false,
									'paste_remove_spans' => false,
									'paste_strip_class_attributes' => 'none',
									'paste_text_use_dialog' => false,
									'wpeditimage_disable_captions' => true,
									'plugins' => 'lists,paste,tabfocus,fullscreen,wordpress,wpautoresize,wpeditimage',
									'content_css' => false,
									'wpautop' => false,
									'apply_source_formatting' => false,
									'block_formats' => 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6;',
									'toolbar1' => 'bold,italic,underline,blockquote,strikethrough,bullist,numlist,alignleft,aligncenter,alignright,undo,redo,link,unlink,fullscreen',
									'toolbar2' => 'formatselect,forecolor,pastetext,removeformat,charmap,outdent,indent,wp_adv',
									'toolbar3' => '',
									'toolbar4' => '',
								),
								'quicktags' => array(
									'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close',
								),
								'wpautop' => false,
								'default_editor' => 'html',
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'Text displayed on the gallery landing page.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
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

				</table>
			</div>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'SEO Settings', 'brag-book-gallery' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure search engine optimization settings for your gallery pages.', 'brag-book-gallery' ); ?>
				</p>

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
			</div>

			<style>
			.character-count {
				margin-top: 5px;
				font-size: 13px;
				color: #666;
			}
			.character-count .warning-text {
				color: #d63638;
				margin-left: 10px;
			}
			.serp-preview-container {
				margin-top: 30px;
				padding: 20px;
				background: #f0f0f1;
				border-radius: 4px;
			}
			.serp-preview-container h3 {
				margin-top: 0;
				margin-bottom: 15px;
				color: #1d2327;
			}
			.serp-preview {
				background: #fff;
				padding: 20px;
				border-radius: 8px;
				font-family: arial, sans-serif;
				max-width: 600px;
				box-shadow: 0 1px 6px rgba(32,33,36,.28);
			}
			.serp-title {
				color: #1a0dab;
				font-size: 20px;
				line-height: 1.3;
				margin-bottom: 3px;
				cursor: pointer;
				display: -webkit-box;
				-webkit-line-clamp: 1;
				-webkit-box-orient: vertical;
				overflow: hidden;
			}
			.serp-title:hover {
				text-decoration: underline;
			}
			.serp-url {
				color: #006621;
				font-size: 14px;
				line-height: 1.3;
				margin-bottom: 5px;
			}
			.serp-description {
				color: #545454;
				font-size: 14px;
				line-height: 1.58;
				display: -webkit-box;
				-webkit-line-clamp: 2;
				-webkit-box-orient: vertical;
				overflow: hidden;
			}
			.slug-status {
				margin-top: 5px;
				font-size: 13px;
				display: none;
			}
			.slug-status.warning {
				color: #dba617;
				display: block;
			}
			.slug-status.success {
				color: #00a32a;
				display: block;
			}
			.slug-status.error {
				color: #d63638;
				display: block;
			}
			.slug-input-wrapper {
				display: flex;
				align-items: center;
			}
			#generate-page-btn:disabled {
				opacity: 0.6;
				cursor: not-allowed;
			}
			</style>

			<script type="module">
			/**
			 * BRAG book Gallery Default Settings
			 *
			 * Handles default mode settings including SEO optimization,
			 * slug validation, and dynamic page generation.
			 *
			 * @since 3.0.0
			 */
			(() => {
				'use strict';

				// DOM element references
				const elements = {
					titleInput: document.getElementById('brag_book_gallery_seo_title'),
					descInput: document.getElementById('brag_book_gallery_seo_description'),
					titleCount: document.getElementById('title-char-count'),
					descCount: document.getElementById('desc-char-count'),
					titleWarning: document.getElementById('title-char-warning'),
					descWarning: document.getElementById('desc-char-warning'),
					serpTitle: document.getElementById('serp-title'),
					serpDesc: document.getElementById('serp-description'),
					slugInput: document.getElementById('brag_book_gallery_page_slug'),
					slugStatus: document.getElementById('slug-status-message'),
					generateBtn: document.getElementById('generate-page-btn')
				};

				// State management
				let checkSlugTimeout = null;
				let currentSlugExists = false;

				/**
				 * Update title character count and SERP preview
				 *
				 * @since 3.0.0
				 */
				const updateTitle = () => {
					if (!elements.titleInput || !elements.titleCount || !elements.serpTitle) return;

					const length = elements.titleInput.value.length;
					elements.titleCount.textContent = length;

					// Show warning if too long
					if (length > 60) {
						elements.titleWarning.style.display = 'inline';
						elements.titleCount.style.color = '#d63638';
					} else if (length > 50) {
						elements.titleWarning.style.display = 'none';
						elements.titleCount.style.color = '#dba617';
					} else {
						elements.titleWarning.style.display = 'none';
						elements.titleCount.style.color = '#666';
					}

					// Update SERP preview
					elements.serpTitle.textContent = elements.titleInput.value || '<?php echo esc_js( get_bloginfo( 'name' ) . ' - Gallery' ); ?>';
				};

				/**
				 * Update description character count and SERP preview
				 *
				 * @since 3.0.0
				 */
				const updateDescription = () => {
					if (!elements.descInput || !elements.descCount || !elements.serpDesc) return;

					const length = elements.descInput.value.length;
					elements.descCount.textContent = length;

					// Show warning if too long
					if (length > 160) {
						elements.descWarning.style.display = 'inline';
						elements.descCount.style.color = '#d63638';
					} else if (length > 120) {
						elements.descWarning.style.display = 'none';
						elements.descCount.style.color = '#00a32a';
					} else {
						elements.descWarning.style.display = 'none';
						elements.descCount.style.color = '#666';
					}

					// Update SERP preview
					elements.serpDesc.textContent = elements.descInput.value || 'View our stunning gallery of before and after transformations. Browse through real patient results and see the amazing outcomes we achieve.';
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
						return;
					}

					// Show checking message
					elements.slugStatus.className = 'slug-status';
					elements.slugStatus.textContent = '<?php esc_html_e( 'Checking...', 'brag-book-gallery' ); ?>';
					elements.slugStatus.style.display = 'block';
					elements.generateBtn.disabled = true;

					if (typeof ajaxurl === 'undefined') return;

					try {
						// Check if page exists via AJAX
						const formData = new FormData();
						formData.append('action', 'brag_book_gallery_check_slug');
						formData.append('slug', slug);
						formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_check_slug' ); ?>');

						const response = await fetch(ajaxurl, {
							method: 'POST',
							body: formData
						});

						const data = await response.json();

						if (data.success) {
							currentSlugExists = data.data.exists;

							if (data.data.exists) {
								elements.slugStatus.className = 'slug-status warning';
								elements.slugStatus.innerHTML = `⚠️ ${data.data.message}`;
								elements.generateBtn.disabled = true;
								elements.generateBtn.textContent = '<?php esc_html_e( 'Page Exists', 'brag-book-gallery' ); ?>';
							} else {
								elements.slugStatus.className = 'slug-status success';
								elements.slugStatus.innerHTML = `✓ ${data.data.message}`;
								elements.generateBtn.disabled = false;
								elements.generateBtn.textContent = '<?php esc_html_e( 'Generate Page', 'brag-book-gallery' ); ?>';
							}
						} else {
							elements.slugStatus.className = 'slug-status error';
							elements.slugStatus.textContent = '<?php esc_html_e( 'Error checking slug availability', 'brag-book-gallery' ); ?>';
							elements.generateBtn.disabled = true;
						}
					} catch (error) {
						console.error('Error checking slug:', error);
						elements.slugStatus.className = 'slug-status';
						elements.slugStatus.textContent = '';
						elements.generateBtn.disabled = true;
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

					if (!slug || currentSlugExists) return;

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

						const response = await fetch(ajaxurl, {
							method: 'POST',
							body: formData
						});

						const data = await response.json();

						if (data.success) {
							elements.slugStatus.className = 'slug-status success';
							elements.slugStatus.innerHTML = `✓ ${data.data.message}`;
							elements.generateBtn.textContent = '<?php esc_html_e( 'Page Created', 'brag-book-gallery' ); ?>';
							elements.generateBtn.disabled = true;
							currentSlugExists = true;

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
						elements.slugStatus.textContent = '<?php esc_html_e( 'Connection error', 'brag-book-gallery' ); ?>';
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
					// SEO character counters
					if (elements.titleInput) {
						updateTitle();
						elements.titleInput.addEventListener('input', updateTitle);
					}

					if (elements.descInput) {
						updateDescription();
						elements.descInput.addEventListener('input', updateDescription);
					}

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

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Performance Settings', 'brag-book-gallery' ); ?></h2>
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
						<label>
							<input type="checkbox"
							       id="lazy_load"
							       name="lazy_load"
							       value="yes"
							       <?php checked( $lazy_load, 'yes' ); ?>>
							<?php esc_html_e( 'Enable lazy loading for gallery images', 'brag-book-gallery' ); ?>
						</label>
					</td>
				</tr>
				</table>
			</div>



			<div class="brag-book-gallery-actions">
				<button type="submit"
				        name="submit"
				        id="submit"
				        class="button button-primary button-large">
					<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
				</button>
				<button type="button"
				        id="clear-cache-btn"
				        class="button button-secondary button-large"
				        style="margin-left: 10px;">
					<?php esc_html_e( 'Clear Gallery Cache', 'brag-book-gallery' ); ?>
				</button>
			</div>

			<script>
			document.getElementById('clear-cache-btn').addEventListener('click', function() {
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
			</script>
		</form>
		<?php
		$this->render_footer();
	}

	/**
	 * Save settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_default_settings', 'brag_book_gallery_default_nonce' ) ) {
			return;
		}

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

			// Get existing slugs as array
			$existing_slugs = \BRAGBookGallery\Includes\Core\Slug_Helper::get_all_gallery_page_slugs();
			$old_slug = ! empty( $existing_slugs ) ? $existing_slugs[0] : '';

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
				// Replace the first slug or add if empty
				if ( ! empty( $existing_slugs ) ) {
					$existing_slugs[0] = $new_slug;
				} else {
					$existing_slugs = array( $new_slug );
				}
				update_option( 'brag_book_gallery_page_slug', $existing_slugs );
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


		$this->add_notice( __( 'Default mode settings saved successfully.', 'brag-book-gallery' ) );
		settings_errors( $this->page_slug );
	}
}
