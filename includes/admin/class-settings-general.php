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

namespace BRAGBookGallery\Includes\Admin;

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
class Settings_General extends Settings_Base {

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

		// Enqueue WordPress code editor for CSS with full features
		$editor_settings = false;
		if ( function_exists( 'wp_enqueue_code_editor' ) ) {
			// Enqueue code editor and settings for CSS
			$editor_settings = wp_enqueue_code_editor( array(
				'type' => 'text/css',
				'codemirror' => array(
					'indentUnit' => 4,
					'tabSize' => 4,
					'lineNumbers' => true,
					'lineWrapping' => false,
					'autoCloseBrackets' => true,
					'matchBrackets' => true,
					'lint' => true,
					'gutters' => array( 'CodeMirror-lint-markers', 'CodeMirror-linenumbers' ),
					'styleActiveLine' => true,
					'mode' => 'css',
				),
			) );
			
			// Ensure CSS linting is available
			if ( $editor_settings !== false ) {
				// The wp_enqueue_code_editor automatically loads needed scripts
				wp_enqueue_script( 'wp-theme-plugin-editor' );
				wp_enqueue_style( 'wp-codemirror' );
			}
		}

		// Handle form submission
		if ( isset( $_POST['submit'] ) && $this->save_settings( 'brag_book_gallery_general_settings', 'brag_book_gallery_general_nonce' ) ) {
			$this->save_general_settings();
		}

		$this->render_header();
		?>

		<!-- Custom Notices Section -->
		<div class="brag-book-gallery-notices">
			<?php $this->render_custom_notices(); ?>
		</div>

		<!-- General Settings Form -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Gallery Display Settings', 'brag-book-gallery' ); ?></h2>
			<?php settings_errors( $this->page_slug ); ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'brag_book_gallery_general_settings', 'brag_book_gallery_general_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="brag_book_gallery_columns">
								<?php esc_html_e( 'Gallery Columns', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$columns = get_option( 'brag_book_gallery_columns', '3' );
							?>
							<select id="brag_book_gallery_columns" name="brag_book_gallery_columns" class="regular-text">
								<option value="2" <?php selected( $columns, '2' ); ?>><?php esc_html_e( '2 Columns', 'brag-book-gallery' ); ?></option>
								<option value="3" <?php selected( $columns, '3' ); ?>><?php esc_html_e( '3 Columns', 'brag-book-gallery' ); ?></option>
							</select>
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
							<?php
							$items_per_page = get_option( 'brag_book_gallery_items_per_page', '10' );
							?>
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
							<label for="brag_book_gallery_infinite_scroll" class="brag-book-toggle-label">
								<?php esc_html_e( 'Enable Infinite Scroll', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$infinite_scroll = get_option( 'brag_book_gallery_infinite_scroll', 'no' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_infinite_scroll" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_infinite_scroll"
									   name="brag_book_gallery_infinite_scroll"
									   value="yes"
									   <?php checked( $infinite_scroll, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Automatically load more cases when scrolling near the bottom of the page. When disabled, users must click the "Load More" button.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_image_display_mode" class="brag-book-toggle-label">
								<?php esc_html_e( 'Before & After Mode', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_image_display_mode" value="single" />
								<input type="checkbox"
									   id="brag_book_gallery_image_display_mode"
									   name="brag_book_gallery_image_display_mode"
									   value="before_after"
									   <?php checked( $image_display_mode, 'before_after' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, shows before and after comparison images. When disabled, shows a single post-processed image.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_enable_sharing" class="brag-book-toggle-label">
								<?php esc_html_e( 'Enable Sharing', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_enable_sharing" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_enable_sharing"
									   name="brag_book_gallery_enable_sharing"
									   value="yes"
									   <?php checked( $enable_sharing, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Allow users to share gallery cases via social media and other platforms.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_enable_lightbox" class="brag-book-toggle-label">
								<?php esc_html_e( 'Enable Lightbox', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$enable_lightbox = get_option( 'brag_book_gallery_enable_lightbox', 'no' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_enable_lightbox" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_enable_lightbox"
									   name="brag_book_gallery_enable_lightbox"
									   value="yes"
									   <?php checked( $enable_lightbox, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Open gallery images in a lightbox popup when clicked.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_enable_filtering" class="brag-book-toggle-label">
								<?php esc_html_e( 'Enable Filtering', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$enable_filtering = get_option( 'brag_book_gallery_enable_filtering', 'yes' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_enable_filtering" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_enable_filtering"
									   name="brag_book_gallery_enable_filtering"
									   value="yes"
									   <?php checked( $enable_filtering, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Show filter options for procedures, categories, and demographics.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_use_custom_font" class="brag-book-toggle-label">
								<?php esc_html_e( 'Use Poppins Font', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$use_custom_font = get_option( 'brag_book_gallery_use_custom_font', 'yes' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_use_custom_font" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_use_custom_font"
									   name="brag_book_gallery_use_custom_font"
									   value="yes"
									   <?php checked( $use_custom_font, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Use the Poppins font family for gallery text. When disabled, uses your theme\'s default font.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Image Settings', 'brag-book-gallery' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="brag_book_gallery_image_size">
								<?php esc_html_e( 'Thumbnail Size', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$image_size = get_option( 'brag_book_gallery_image_size', 'medium' );
							?>
							<select id="brag_book_gallery_image_size" name="brag_book_gallery_image_size">
								<option value="thumbnail" <?php selected( $image_size, 'thumbnail' ); ?>><?php esc_html_e( 'Thumbnail (150x150)', 'brag-book-gallery' ); ?></option>
								<option value="medium" <?php selected( $image_size, 'medium' ); ?>><?php esc_html_e( 'Medium (300x300)', 'brag-book-gallery' ); ?></option>
								<option value="large" <?php selected( $image_size, 'large' ); ?>><?php esc_html_e( 'Large (1024x1024)', 'brag-book-gallery' ); ?></option>
								<option value="full" <?php selected( $image_size, 'full' ); ?>><?php esc_html_e( 'Full Size', 'brag-book-gallery' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Size of thumbnails to display in the gallery grid.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_lazy_load" class="brag-book-toggle-label">
								<?php esc_html_e( 'Enable Lazy Loading', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$lazy_load = get_option( 'brag_book_gallery_lazy_load', 'yes' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_lazy_load" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_lazy_load"
									   name="brag_book_gallery_lazy_load"
									   value="yes"
									   <?php checked( $lazy_load, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Load images only when they are visible in the viewport for better performance.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_image_quality">
								<?php esc_html_e( 'Image Quality', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$image_quality = get_option( 'brag_book_gallery_image_quality', '85' );
							?>
							<input type="number"
								   id="brag_book_gallery_image_quality"
								   name="brag_book_gallery_image_quality"
								   value="<?php echo esc_attr( $image_quality ); ?>"
								   min="1"
								   max="100"
								   step="1" />
							<span>%</span>
							<p class="description">
								<?php esc_html_e( 'JPEG compression quality (1-100). Higher values mean better quality but larger file sizes.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Performance Settings', 'brag-book-gallery' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="brag_book_gallery_cache_duration">
								<?php esc_html_e( 'Cache Duration', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$cache_duration = get_option( 'brag_book_gallery_cache_duration', '3600' );
							?>
							<select id="brag_book_gallery_cache_duration" name="brag_book_gallery_cache_duration">
								<option value="0" <?php selected( $cache_duration, '0' ); ?>><?php esc_html_e( 'No Cache', 'brag-book-gallery' ); ?></option>
								<option value="300" <?php selected( $cache_duration, '300' ); ?>><?php esc_html_e( '5 Minutes', 'brag-book-gallery' ); ?></option>
								<option value="900" <?php selected( $cache_duration, '900' ); ?>><?php esc_html_e( '15 Minutes', 'brag-book-gallery' ); ?></option>
								<option value="1800" <?php selected( $cache_duration, '1800' ); ?>><?php esc_html_e( '30 Minutes', 'brag-book-gallery' ); ?></option>
								<option value="3600" <?php selected( $cache_duration, '3600' ); ?>><?php esc_html_e( '1 Hour', 'brag-book-gallery' ); ?></option>
								<option value="7200" <?php selected( $cache_duration, '7200' ); ?>><?php esc_html_e( '2 Hours', 'brag-book-gallery' ); ?></option>
								<option value="21600" <?php selected( $cache_duration, '21600' ); ?>><?php esc_html_e( '6 Hours', 'brag-book-gallery' ); ?></option>
								<option value="43200" <?php selected( $cache_duration, '43200' ); ?>><?php esc_html_e( '12 Hours', 'brag-book-gallery' ); ?></option>
								<option value="86400" <?php selected( $cache_duration, '86400' ); ?>><?php esc_html_e( '24 Hours', 'brag-book-gallery' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'How long to cache API responses and gallery data.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_minify_assets" class="brag-book-toggle-label">
								<?php esc_html_e( 'Minify Assets', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$minify_assets = get_option( 'brag_book_gallery_minify_assets', 'yes' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_minify_assets" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_minify_assets"
									   name="brag_book_gallery_minify_assets"
									   value="yes"
									   <?php checked( $minify_assets, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Use minified versions of CSS and JavaScript files.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Advanced Settings', 'brag-book-gallery' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="brag_book_gallery_custom_css">
								<?php esc_html_e( 'Custom CSS', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$custom_css = get_option( 'brag_book_gallery_custom_css', '' );
							?>
							<div id="css-editor-container" style="position: relative; max-width: 800px;">
								<textarea id="brag_book_gallery_custom_css"
										  name="brag_book_gallery_custom_css"
										  rows="15"
										  cols="50"
										  class="large-text code"
										  style="width: 100%;"
										  placeholder="/* Your custom CSS here */
.brag-book-gallery {
    /* Custom styles */
}"><?php echo esc_textarea( $custom_css ); ?></textarea>
								<div id="css-error-message" style="display: none; margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
									<strong><?php esc_html_e( 'CSS Warning:', 'brag-book-gallery' ); ?></strong>
									<span id="css-error-text"></span>
								</div>
							</div>
							<p class="description">
								<?php esc_html_e( 'Add custom CSS styles for the gallery. This CSS will be applied to all pages with gallery shortcodes. Common selectors: .brag-book-gallery, .brag-book-gallery-item, .brag-book-gallery-filter', 'brag-book-gallery' ); ?>
							</p>
							<div style="margin-top: 10px;">
								<button type="button" id="validate-css-btn" class="button button-secondary">
									<?php esc_html_e( 'Validate CSS', 'brag-book-gallery' ); ?>
								</button>
								<button type="button" id="format-css-btn" class="button button-secondary">
									<?php esc_html_e( 'Format CSS', 'brag-book-gallery' ); ?>
								</button>
								<button type="button" id="minify-css-btn" class="button button-secondary">
									<?php esc_html_e( 'Minify CSS', 'brag-book-gallery' ); ?>
								</button>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_enable_debug" class="brag-book-toggle-label">
								<?php esc_html_e( 'Enable Debug Mode', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$enable_debug = get_option( 'brag_book_gallery_enable_debug', 'no' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_enable_debug" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_enable_debug"
									   name="brag_book_gallery_enable_debug"
									   value="yes"
									   <?php checked( $enable_debug, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Enable debug logging and additional diagnostic information.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="brag_book_gallery_delete_on_uninstall" class="brag-book-toggle-label">
								<?php esc_html_e( 'Delete Data on Uninstall', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$delete_on_uninstall = get_option( 'brag_book_gallery_delete_on_uninstall', 'no' );
							?>
							<label class="brag-book-toggle-switch">
								<input type="hidden" name="brag_book_gallery_delete_on_uninstall" value="no" />
								<input type="checkbox"
									   id="brag_book_gallery_delete_on_uninstall"
									   name="brag_book_gallery_delete_on_uninstall"
									   value="yes"
									   <?php checked( $delete_on_uninstall, 'yes' ); ?> />
								<span class="brag-book-toggle-slider"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Remove all plugin data when the plugin is uninstalled. This cannot be undone.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'brag-book-gallery' ) ); ?>
			</form>
		</div>
		
		<?php if ( $editor_settings !== false ) : ?>
		<script type="text/javascript">
		// Enhanced CSS Editor with WordPress CodeMirror
		jQuery(document).ready(function($) {
			'use strict';

			const cssTextarea = document.getElementById('brag_book_gallery_custom_css');
			const errorMessage = document.getElementById('css-error-message');
			const errorText = document.getElementById('css-error-text');
			let cssEditor = null;
			
			// Initialize WordPress CodeMirror with CSS linting
			if (typeof wp !== 'undefined' && wp.codeEditor && cssTextarea) {
				const editorSettings = <?php echo wp_json_encode( $editor_settings ); ?>;
				const editor = wp.codeEditor.initialize(cssTextarea, editorSettings);
				cssEditor = editor.codemirror;
				
				// Add custom error handling for lint messages
				cssEditor.on('change', function(cm) {
					// WordPress CodeMirror automatically updates the textarea
					// Check for lint errors
					const lintErrors = cm.state.lint ? cm.state.lint.marked : [];
					if (lintErrors && lintErrors.length > 0) {
						const errors = lintErrors.map(err => err.message || 'CSS Error').slice(0, 3);
						errorText.textContent = errors.join('; ');
						errorMessage.style.display = 'block';
					} else {
						errorMessage.style.display = 'none';
					}
				});
			}
			
			// Helper function to get CSS value
			function getCSSValue() {
				return cssEditor ? cssEditor.getValue() : cssTextarea.value;
			}
			
			// Helper function to set CSS value
			function setCSSValue(value) {
				if (cssEditor) {
					cssEditor.setValue(value);
				} else {
					cssTextarea.value = value;
				}
			}

			// Basic CSS validation
			function validateCSS(css) {
				const errors = [];
				
				// Check for unclosed brackets
				const openBrackets = (css.match(/{/g) || []).length;
				const closeBrackets = (css.match(/}/g) || []).length;
				if (openBrackets !== closeBrackets) {
					errors.push('Unmatched brackets: ' + openBrackets + ' { vs ' + closeBrackets + ' }');
				}

				// Check for unclosed comments
				const commentStarts = (css.match(/\/\*/g) || []).length;
				const commentEnds = (css.match(/\*\//g) || []).length;
				if (commentStarts !== commentEnds) {
					errors.push('Unclosed comment blocks');
				}

				// Check for missing semicolons (basic check)
				const lines = css.split('\n');
				let inComment = false;
				lines.forEach((line, index) => {
					if (line.includes('/*')) inComment = true;
					if (line.includes('*/')) inComment = false;
					
					if (!inComment && line.includes(':') && !line.includes(';') && !line.includes('{')) {
						const trimmed = line.trim();
						if (trimmed && !trimmed.endsWith('{') && !trimmed.startsWith('@')) {
							errors.push('Line ' + (index + 1) + ' might be missing a semicolon');
						}
					}
				});

				// Check for potentially malicious content
				if (css.match(/<script|javascript:|expression\s*\(|@import/i)) {
					errors.push('Potentially unsafe CSS detected');
				}

				return errors;
			}

			// Validate CSS button - Use CodeMirror's built-in linting
			if (document.getElementById('validate-css-btn')) {
				document.getElementById('validate-css-btn').addEventListener('click', function() {
					if (cssEditor) {
						// Trigger CodeMirror's lint update
						cssEditor.performLint();
						const lintErrors = cssEditor.state.lint ? cssEditor.state.lint.marked : [];
						if (lintErrors && lintErrors.length > 0) {
							const errors = lintErrors.map(err => err.message || 'CSS Error');
							errorText.textContent = errors.join('; ');
							errorMessage.style.display = 'block';
						} else {
							errorMessage.style.display = 'none';
							alert('<?php esc_html_e( 'CSS validation passed! No errors found.', 'brag-book-gallery' ); ?>');
						}
					} else {
						// Fallback validation
						const errors = validateCSS(getCSSValue());
						if (errors.length > 0) {
							errorText.textContent = errors.join('; ');
							errorMessage.style.display = 'block';
						} else {
							errorMessage.style.display = 'none';
							alert('<?php esc_html_e( 'CSS validation passed!', 'brag-book-gallery' ); ?>');
						}
					}
				});
			}

			// Format CSS
			if (document.getElementById('format-css-btn')) {
				document.getElementById('format-css-btn').addEventListener('click', function() {
					let css = getCSSValue();
					
					// Basic formatting
					css = css.replace(/\s*{\s*/g, ' {\n    ');
					css = css.replace(/;\s*/g, ';\n    ');
					css = css.replace(/\s*}\s*/g, '\n}\n');
					css = css.replace(/}\n{/g, '}\n\n{');
					css = css.replace(/\n\s*\n\s*\n/g, '\n\n');
					
					// Clean up extra spaces
					css = css.replace(/    }/g, '}');
					css = css.trim();
					
					setCSSValue(css);
				});
			}

			// Minify CSS
			if (document.getElementById('minify-css-btn')) {
				document.getElementById('minify-css-btn').addEventListener('click', function() {
					let css = getCSSValue();
					
					// Remove comments
					css = css.replace(/\/\*[\s\S]*?\*\//g, '');
					// Remove unnecessary whitespace
					css = css.replace(/\s+/g, ' ');
					css = css.replace(/\s*{\s*/g, '{');
					css = css.replace(/\s*}\s*/g, '}');
					css = css.replace(/\s*;\s*/g, ';');
					css = css.replace(/\s*:\s*/g, ':');
					css = css.replace(/\s*,\s*/g, ',');
					css = css.trim();
					
					setCSSValue(css);
				});
			}

			// Auto-validate on input (with debounce)
			let validateTimeout;
			if (cssTextarea) {
				cssTextarea.addEventListener('input', function() {
					clearTimeout(validateTimeout);
					validateTimeout = setTimeout(() => {
						const errors = validateCSS(getCSSValue());
						if (errors.length > 0) {
							errorText.textContent = errors.join('; ');
							errorMessage.style.display = 'block';
						} else {
							errorMessage.style.display = 'none';
						}
					}, 1000);
				});
			}

			// Add tab support in textarea (only if CodeMirror is not active)
			if (!cssEditor && cssTextarea) {
				cssTextarea.addEventListener('keydown', function(e) {
					if (e.key === 'Tab') {
						e.preventDefault();
						const start = this.selectionStart;
						const end = this.selectionEnd;
						const value = this.value;
						this.value = value.substring(0, start) + '    ' + value.substring(end);
						this.selectionStart = this.selectionEnd = start + 4;
					}
				});
			}
		});
		</script>
		<?php else : ?>
		<script type="text/javascript">
		// Fallback for when CodeMirror is not available
		jQuery(document).ready(function($) {
			// Add basic tab support
			const cssTextarea = document.getElementById('brag_book_gallery_custom_css');
			if (cssTextarea) {
				cssTextarea.addEventListener('keydown', function(e) {
					if (e.key === 'Tab') {
						e.preventDefault();
						const start = this.selectionStart;
						const end = this.selectionEnd;
						const value = this.value;
						this.value = value.substring(0, start) + '    ' + value.substring(end);
						this.selectionStart = this.selectionEnd = start + 4;
					}
				});
			}
		});
		</script>
		<?php endif; ?>
		
		<?php
		$this->render_footer();
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

		$infinite_scroll = isset( $_POST['brag_book_gallery_infinite_scroll'] ) ? sanitize_text_field( $_POST['brag_book_gallery_infinite_scroll'] ) : 'no';
		update_option( 'brag_book_gallery_infinite_scroll', $infinite_scroll );

		$image_display_mode = isset( $_POST['brag_book_gallery_image_display_mode'] ) && $_POST['brag_book_gallery_image_display_mode'] === 'before_after' ? 'before_after' : 'single';
		update_option( 'brag_book_gallery_image_display_mode', $image_display_mode );

		$enable_sharing = isset( $_POST['brag_book_gallery_enable_sharing'] ) ? sanitize_text_field( $_POST['brag_book_gallery_enable_sharing'] ) : 'no';
		update_option( 'brag_book_gallery_enable_sharing', $enable_sharing );

		$enable_lightbox = isset( $_POST['brag_book_gallery_enable_lightbox'] ) ? sanitize_text_field( $_POST['brag_book_gallery_enable_lightbox'] ) : 'no';
		update_option( 'brag_book_gallery_enable_lightbox', $enable_lightbox );

		$enable_filtering = isset( $_POST['brag_book_gallery_enable_filtering'] ) ? sanitize_text_field( $_POST['brag_book_gallery_enable_filtering'] ) : 'no';
		update_option( 'brag_book_gallery_enable_filtering', $enable_filtering );

		$use_custom_font = isset( $_POST['brag_book_gallery_use_custom_font'] ) ? sanitize_text_field( $_POST['brag_book_gallery_use_custom_font'] ) : 'no';
		update_option( 'brag_book_gallery_use_custom_font', $use_custom_font );

		// Image Settings
		$image_size = isset( $_POST['brag_book_gallery_image_size'] ) ? sanitize_text_field( $_POST['brag_book_gallery_image_size'] ) : 'medium';
		update_option( 'brag_book_gallery_image_size', $image_size );

		$lazy_load = isset( $_POST['brag_book_gallery_lazy_load'] ) ? sanitize_text_field( $_POST['brag_book_gallery_lazy_load'] ) : 'no';
		update_option( 'brag_book_gallery_lazy_load', $lazy_load );

		$image_quality = isset( $_POST['brag_book_gallery_image_quality'] ) ? absint( $_POST['brag_book_gallery_image_quality'] ) : 85;
		$image_quality = min( 100, max( 1, $image_quality ) ); // Ensure it's between 1 and 100
		update_option( 'brag_book_gallery_image_quality', $image_quality );

		// Performance Settings
		$cache_duration = isset( $_POST['brag_book_gallery_cache_duration'] ) ? absint( $_POST['brag_book_gallery_cache_duration'] ) : 3600;
		update_option( 'brag_book_gallery_cache_duration', $cache_duration );

		$minify_assets = isset( $_POST['brag_book_gallery_minify_assets'] ) ? sanitize_text_field( $_POST['brag_book_gallery_minify_assets'] ) : 'no';
		update_option( 'brag_book_gallery_minify_assets', $minify_assets );

		// Advanced Settings - Custom CSS with sanitization
		if ( isset( $_POST['brag_book_gallery_custom_css'] ) ) {
			// Sanitize CSS while preserving valid CSS syntax
			$custom_css = wp_strip_all_tags( $_POST['brag_book_gallery_custom_css'] );
			// Remove any potential XSS vectors while keeping CSS intact
			$custom_css = str_replace( array( '<script', '</script', '<style', '</style', 'javascript:', 'expression(' ), '', $custom_css );
			update_option( 'brag_book_gallery_custom_css', $custom_css );
		}

		$enable_debug = isset( $_POST['brag_book_gallery_enable_debug'] ) ? sanitize_text_field( $_POST['brag_book_gallery_enable_debug'] ) : 'no';
		update_option( 'brag_book_gallery_enable_debug', $enable_debug );

		$delete_on_uninstall = isset( $_POST['brag_book_gallery_delete_on_uninstall'] ) ? sanitize_text_field( $_POST['brag_book_gallery_delete_on_uninstall'] ) : 'no';
		update_option( 'brag_book_gallery_delete_on_uninstall', $delete_on_uninstall );

		$this->add_notice( __( 'General settings saved successfully.', 'brag-book-gallery' ), 'success' );
	}

}
