<?php
/**
 * Admin Settings Class - Manages plugin settings and admin interface
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

use BRAGBookGallery\Includes\Traits\{Trait_Sanitizer, Trait_Tools};
use BRAGBookGallery\Includes\SEO\Sitemap;
use Exception;
use Error;
use WP_Error;

if ( ! defined( constant_name: 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Settings Class
 *
 * Handles all admin settings and configuration
 *
 * @since 3.0.0
 */
class Settings {
	use Trait_Sanitizer, Trait_Tools;

	/**
	 * API handler instance
	 *
	 * @since 3.0.0
	 * @var Api_Handler|null
	 */
	private ?Api_Handler $api_handler = null;

	/**
	 * Constructor
	 *
	 * @param bool $register_menus Whether to register menu pages (default true for backward compatibility).
	 *
	 * @since 3.0.0
	 */
	public function __construct( bool $register_menus = true ) {
		$this->init_hooks( $register_menus );
		$this->init_log_file();
	}

	/**
	 * Initialize log file if it doesn't exist
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function init_log_file(): void {

		$log_file = $this->get_log_file_path();

		if ( ! file_exists( $log_file ) ) {

			// Create log file with initial entry.
			$timestamp = current_time( 'Y-m-d H:i:s' );

			// Don't translate this - it's a log file entry.
			$initial_message = sprintf(
				'[%s] [INFO] Brag Book Gallery Debug Log initialized' . "\n",
				$timestamp
			);

			// Use WordPress filesystem for VIP compatibility.
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->put_contents(
				$log_file,
				$initial_message,
				FS_CHMOD_FILE
			);
		}
	}

	/**
	 * Initialize hooks
	 *
	 * @param bool $register_menus Whether to register menu pages.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function init_hooks( bool $register_menus = true ): void {

		// Register settings only if not managed by Settings_Manager.
		if ( $register_menus ) {
			add_action(
				'admin_menu',
				array( $this, 'add_menu_pages' )
			);
		}

		// AJAX handlers.
		add_action(
			'wp_ajax_brag_book_gallery_save_brag_book_gallery_settings',
			array( $this, 'handle_save_settings_debug' )
		);

		add_action(
			'wp_ajax_brag_book_gallery_save_api_settings',
			array( $this, 'handle_save_api_settings' )
		);

		// Validate API key
		add_action(
			'wp_ajax_brag_book_gallery_validate_api',
			array( $this, 'handle_validate_api' )
		);

		// Remove API connection
		add_action(
			'wp_ajax_brag_book_gallery_remove_api_connection',
			array( $this, 'handle_remove_api_connection' )
		);

		// Check slug availability
		add_action(
			'wp_ajax_brag_book_gallery_check_slug',
			array( $this, 'handle_check_slug' )
		);

		// Generate page from slug
		add_action(
			'wp_ajax_brag_book_gallery_generate_page',
			array( $this, 'handle_generate_page' )
		);

		// Add new row.
		add_action(
			'wp_ajax_brag_book_gallery_setting_remove_row',
			array( $this, 'handle_remove_row' )
		);

		// Update API key.
		add_action(
			'wp_ajax_brag_book_gallery_update_api',
			array( $this, 'handle_update_api' )
		);

		// Fetch website properties.
		add_action(
			'wp_ajax_brag_book_gallery_export_system_info',
			array( $this, 'handle_export_system_info' )
		);

		// Fetch website properties.
		add_action(
			'wp_ajax_brag_book_gallery_download_error_log',
			array( $this, 'handle_download_error_log' )
		);

		// Error log handlers.
		add_action(
			'wp_ajax_brag_book_gallery_get_error_log',
			array( $this, 'handle_get_error_log' )
		);

		// Clear error log.
		add_action(
			'wp_ajax_brag_book_gallery_clear_error_log',
			array( $this, 'handle_clear_error_log' )
		);

		// Save debug settings.
		add_action(
			'wp_ajax_brag_book_gallery_save_debug_settings',
			array( $this, 'handle_save_debug_settings' )
		);

		// API log handlers.
		add_action(
			'wp_ajax_brag_book_gallery_get_api_log',
			array( $this, 'handle_get_api_log' )
		);

		// Clear API log.
		add_action(
			'wp_ajax_brag_book_gallery_clear_api_log',
			array( $this, 'handle_clear_api_log' )
		);

		// Download API log.
		add_action(
			'wp_ajax_brag_book_gallery_download_api_log',
			array( $this, 'handle_download_api_log' )
		);

		// Register settings.
		add_action(
			'save_post',
			array( $this, 'handle_page_slug_update' )
		);

		// Flush rewrite rules on activation.
		add_action(
			'wp_trash_post',
			array( $this, 'handle_page_trash' )
		);

		// Flush rewrite rules on activation.
		add_action(
			'delete_post',
			array( $this, 'handle_page_delete' )
		);

		// Admin notices.
		add_action(
			'admin_notices',
			array( $this, 'display_admin_notices' )
		);

		// Admin styles
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_admin_styles' )
		);
	}

	/**
	 * Add admin menu pages
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function add_menu_pages(): void {

		// Main menu with custom emblem icon.
		// Use WordPress filesystem for VIP compatibility.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$svg_path = dirname( __DIR__, 2 ) . '/assets/images/bragbook-emblem.svg';
		$svg_content = $wp_filesystem->get_contents( $svg_path );
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode( $svg_content );

		add_menu_page(
			esc_html__( 'BRAG Book Gallery', 'brag-book-gallery' ),
			esc_html__( 'BRAG Book', 'brag-book-gallery' ),
			'manage_options',
			'brag-book-gallery-settings',
			array( $this, 'render_settings_page' ),
			$icon_svg,
			30
		);

		// Settings submenu
		add_submenu_page(
			'brag-book-gallery-settings',
			esc_html__( 'General Settings', 'brag-book-gallery' ),
			esc_html__( 'Settings', 'brag-book-gallery' ),
			'manage_options',
			'brag-book-gallery-settings',
			array( $this, 'render_settings_page' )
		);

		// API Settings submenu
		add_submenu_page(
			'brag-book-gallery-settings',
			esc_html__( 'API Settings', 'brag-book-gallery' ),
			esc_html__( 'API', 'brag-book-gallery' ),
			'manage_options',
			'brag-book-gallery-api-settings',
			array( $this, 'render_api_settings_page' )
		);

		// Mode Settings submenu
		add_submenu_page(
			'brag-book-gallery-settings',
			esc_html__( 'Mode Settings', 'brag-book-gallery' ),
			esc_html__( 'Mode', 'brag-book-gallery' ),
			'manage_options',
			'brag-book-gallery-mode',
			array( $this, 'render_mode_settings_page' )
		);

		// Help submenu
		add_submenu_page(
			'brag-book-gallery-settings',
			esc_html__( 'Help & Documentation', 'brag-book-gallery' ),
			esc_html__( 'Help', 'brag-book-gallery' ),
			'manage_options',
			'brag-book-gallery-help',
			array( $this, 'render_help_page' )
		);

		// Debug submenu
		add_submenu_page(
			'brag-book-gallery-settings',
			esc_html__( 'Debug & Diagnostics', 'brag-book-gallery' ),
			esc_html__( 'Debug', 'brag-book-gallery' ),
			'manage_options',
			'brag-book-gallery-debug',
			array( $this, 'render_debug_page' )
		);
	}

	/**
	 * Register settings
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function register_settings(): void {

		// API Token.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_api_token'
		);

		// Website Property ID.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_website_property_id'
		);

		// Gallery Page Slug.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_gallery_page_slug'
		);

		// SEO Page Title.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_seo_page_title'
		);

		// SEO Page Description.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_seo_page_description'
		);

		// Detected SEO Plugin.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_seo_plugin_selector'
		);

		// Landing Page Text.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_landing_page_text'
		);

		// Image Display Mode.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_image_display_mode'
		);

		// Combined Gallery Slug.
		register_setting(
			'brag_book_gallery_settings',
			'combine_gallery_slug'
		);

		// Combined Gallery SEO Title.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_combine_seo_page_title'
		);

		// Combined Gallery SEO Description.
		register_setting(
			'brag_book_gallery_settings',
			'brag_book_gallery_combine_seo_page_description'
		);
	}

	/**
	 * Render main settings page with improved layout
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_settings_page(): void {

		// Get current settings.
		$landing_text = get_option(
			'brag_book_gallery_landing_page_text',
			''
		);

		// Image display mode setting.
		$image_display_mode = get_option(
			'brag_book_gallery_image_display_mode',
			'single'
		);

		// Combined gallery settings.
		$combine_slug = get_option(
			'combine_gallery_slug',
			''
		);

		// SEO settings for combined gallery.
		$combine_seo_title = get_option(
			'brag_book_gallery_combine_seo_page_title',
			''
		);

		// SEO description for combined gallery.
		$combine_seo_description = get_option(
			'brag_book_gallery_combine_seo_page_description',
			''
		);

		// Detected SEO plugin.
		$seo_plugin = $this->detect_seo_plugin();

		// Debug: Check if detect_seo_plugin is working
		if ( ! isset( $seo_plugin ) ) {
			$seo_plugin = array(
				'id'       => '0',
				'name'     => __( 'None', 'brag-book-gallery' ),
				'detected' => false,
			);
		}
		?>
<div class="wrap brag-book-gallery-admin-wrap">
	<div class="brag-book-gallery-header">
		<div class="brag-book-gallery-header-left">
			<img
				src="<?php echo esc_url( plugins_url( 'assets/images/bragbook-logo.svg', dirname( __DIR__ ) ) ); ?>"
				alt="BRAG Book" class="brag-book-gallery-logo"/>
			<h1><?php esc_html_e( 'BRAG Book Gallery', 'brag-book-gallery' ); ?></h1>
		</div>
		<div class="brag-book-gallery-header-right">
                    <span class="brag-book-gallery-version-badge">
                        <?php echo esc_html__( 'v', 'brag-book-gallery' ) . esc_html( $this->get_plugin_info_value( 'Version' ) ); ?>
                    </span>
		</div>
	</div>

	<?php $this->render_tabs( 'settings' ); ?>

	<form method="post" action="" id="brag-book-gallery-settings-form">
		<?php wp_nonce_field( 'brag_book_gallery_settings_nonce', 'brag_book_gallery_nonce' ); ?>

		<!-- Gallery Configuration Section -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Gallery Configuration', 'brag-book-gallery' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="combine_gallery_slug">
							<?php esc_html_e( 'Combined Gallery', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<input type="text"
							   id="combine_gallery_slug"
							   name="combine_gallery_slug"
							   value="<?php echo esc_attr( $combine_slug ); ?>"
							   placeholder="<?php esc_attr_e( 'e.g., gallery', 'brag-book-gallery' ); ?>"
							   class="regular-text"/>
						<p class="description">
							<?php esc_html_e( 'Create a page that combines all gallery sources. Leave empty to disable.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label
							for="brag_book_gallery_combine_seo_page_title">
							<?php esc_html_e( 'Combined Gallery SEO', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-seo-fields">
							<div class="seo-field-group">
								<label
									for="brag_book_gallery_combine_seo_page_title"
									class="seo-label">
									<?php esc_html_e( 'SEO Title', 'brag-book-gallery' ); ?>
									<span class="seo-counter"
										  id="title-counter">
												<span id="title-count">0</span> / 60
											</span>
								</label>
								<input type="text"
									   id="brag_book_gallery_combine_seo_page_title"
									   name="brag_book_gallery_combine_seo_page_title"
									   value="<?php echo esc_attr( $combine_seo_title ); ?>"
									   placeholder="<?php esc_attr_e( 'SEO Title for Combined Gallery', 'brag-book-gallery' ); ?>"
									   class="large-text seo-title-input"
									   maxlength="60"/>
							</div>

							<div class="seo-field-group">
								<label
									for="brag_book_gallery_combine_seo_page_description"
									class="seo-label">
									<?php esc_html_e( 'SEO Description', 'brag-book-gallery' ); ?>
									<span class="seo-counter"
										  id="desc-counter">
												<span id="desc-count">0</span> / 160
											</span>
								</label>
								<textarea
									id="brag_book_gallery_combine_seo_page_description"
									name="brag_book_gallery_combine_seo_page_description"
									placeholder="<?php esc_attr_e( 'SEO Description for Combined Gallery', 'brag-book-gallery' ); ?>"
									rows="3"
									class="large-text seo-desc-input"
									maxlength="160"><?php echo esc_textarea( $combine_seo_description ); ?></textarea>
							</div>

							<!-- Google SERP Preview -->
							<div class="serp-preview-container">
								<h4><?php esc_html_e( 'Google Search Preview', 'brag-book-gallery' ); ?></h4>
								<div class="serp-preview">
									<div class="serp-url">
										<?php
										$site_url = parse_url( home_url(), PHP_URL_HOST );
										$combine_url = ! empty( $combine_slug ) ? $combine_slug : 'gallery';
										?>
										<span class="serp-favicon">
													<img
														src="https://www.google.com/s2/favicons?domain=<?php echo esc_attr( $site_url ); ?>"
														alt="favicon"/>
												</span>
										<span
											class="serp-site"><?php echo esc_html( $site_url ); ?></span>
										<span
											class="serp-separator"> › </span>
										<span class="serp-path"
											  id="serp-path"><?php echo esc_html( $combine_url ); ?></span>
									</div>
									<div class="serp-title"
										 id="serp-title">
										<?php echo esc_html( $combine_seo_title ?: get_bloginfo( 'name' ) . ' - ' . __( 'Gallery', 'brag-book-gallery' ) ); ?>
									</div>
									<div class="serp-description"
										 id="serp-description">
										<?php echo esc_html( $combine_seo_description ?: __( 'View our gallery of before and after photos showcasing our work and results.', 'brag-book-gallery' ) ); ?>
									</div>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<!-- SEO Integration Section -->
		<div class="brag-book-gallery-section">
			<h2 style="color: red; font-size: 24px;">SEO Integration (Debug Version)</h2>
			<p style="background: yellow; padding: 10px;">
				<strong>SEO Plugin Detection Status:</strong><br>
				Detected: <?php echo $seo_plugin['detected'] ? 'YES' : 'NO'; ?><br>
				Name: <?php echo esc_html( $seo_plugin['name'] ); ?><br>
				ID: <?php echo esc_html( $seo_plugin['id'] ); ?><br>
				<?php if ( isset( $seo_plugin['version'] ) ) : ?>
				Version: <?php echo esc_html( $seo_plugin['version'] ); ?>
				<?php endif; ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Detected SEO Plugin', 'brag-book-gallery' ); ?>
					</th>
					<td>
						<?php if ( $seo_plugin['detected'] ) : ?>
							<div class="notice notice-success" style="margin: 0;">
								<p>
									<strong><?php echo esc_html( $seo_plugin['name'] ); ?></strong>
									<?php if ( isset( $seo_plugin['version'] ) ) : ?>
										(Version: <?php echo esc_html( $seo_plugin['version'] ); ?>)
									<?php endif; ?>
								</p>
								<p><?php esc_html_e( 'SEO integration is automatically configured for this plugin.', 'brag-book-gallery' ); ?></p>
							</div>
						<?php else : ?>
							<div class="notice notice-info" style="margin: 0;">
								<p><?php esc_html_e( 'No SEO plugin detected. BRAG Book will use standard WordPress SEO features.', 'brag-book-gallery' ); ?></p>
								<p class="description"><?php esc_html_e( 'Supported plugins: Yoast SEO, All in One SEO, Rank Math, SEOPress', 'brag-book-gallery' ); ?></p>
							</div>
						<?php endif; ?>
						<input type="hidden"
							   name="brag_book_gallery_seo_plugin_selector"
							   value="<?php echo esc_attr( $seo_plugin['id'] ); ?>"/>
					</td>
				</tr>
			</table>
		</div>

		<!-- Landing Page Content Section -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Landing Page Content', 'brag-book-gallery' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label
							for="brag_book_gallery_landing_page_text">
							<?php esc_html_e( 'Default Content', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<?php
						wp_editor(
							$landing_text,
							'brag_book_gallery_landing_page_text',
							array(
								'textarea_name'  => 'brag_book_gallery_landing_page_text',
								'textarea_rows'  => 10,
								'media_buttons'  => true,
								'teeny'          => false,
								'tinymce'        => array(
									'height'                       => 300,
									'convert_urls'                 => false,
									'remove_linebreaks'            => false,
									'gecko_spellcheck'             => false,
									'keep_styles'                  => false,
									'accessibility_focus'          => false,
									'tabfocus_elements'            => 'major-publishing-actions',
									'media_strict'                 => false,
									'paste_remove_styles'          => false,
									'paste_remove_spans'           => false,
									'paste_strip_class_attributes' => 'none',
									'paste_text_use_dialog'        => false,
									'wpeditimage_disable_captions' => true,
									'plugins'                      => 'lists,paste,tabfocus,fullscreen,wordpress,wpautoresize,wpeditimage',
									'content_css'                  => false,
									'wpautop'                      => false,
									'apply_source_formatting'      => false,
									'block_formats'                => 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6;',
									'toolbar1'                     => 'bold,italic,underline,blockquote,strikethrough,bullist,numlist,alignleft,aligncenter,alignright,undo,redo,link,unlink,fullscreen',
									'toolbar2'                     => 'formatselect,forecolor,pastetext,removeformat,charmap,outdent,indent,wp_adv',
									'toolbar3'                     => '',
									'toolbar4'                     => '',
								),
								'quicktags'      => array(
									'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close',
								),
								'wpautop'        => false,
								'default_editor' => 'html',
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'This content appears at the top of gallery pages. You can use HTML and shortcodes.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Image Display Settings Section -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Image Display Settings', 'brag-book-gallery' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Before & After Mode', 'brag-book-gallery' ); ?>
					</th>
					<td>
						<label class="brag-book-toggle-switch">
							<input type="checkbox"
								   id="brag_book_gallery_image_display_mode"
								   name="brag_book_gallery_image_display_mode"
								   value="before_after"
								<?php checked( $image_display_mode, 'before_after' ); ?> />
							<span
								class="brag-book-toggle-slider"></span>
							<span class="brag-book-toggle-label">
										<?php esc_html_e( 'Enable Before & After Images', 'brag-book-gallery' ); ?>
									</span>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, shows before and after comparison images. When disabled, shows a single post-processed image.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"
					id="submit">
				<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
			</button>
		</p>
	</form>
</div>

<script type="module">
	/**
	 * BRAG Book Gallery Settings JavaScript
	 *
	 * Handles general settings form interactions including SERP preview,
	 * character counters, and AJAX form submission.
	 *
	 * @since 3.0.0
	 */
	(
		() => {
			'use strict';

			// DOM element references
			const elements = {
				titleInput: document.getElementById( 'brag_book_gallery_combine_seo_page_title' ),
				descInput: document.getElementById( 'brag_book_gallery_combine_seo_page_description' ),
				slugInput: document.getElementById( 'brag_book_gallery_combine_gallery_slug' ),
				serpTitle: document.getElementById( 'serp-title' ),
				serpDesc: document.getElementById( 'serp-description' ),
				serpPath: document.getElementById( 'serp-path' ),
				titleCount: document.getElementById( 'title-count' ),
				descCount: document.getElementById( 'desc-count' ),
				settingsForm: document.getElementById( 'brag-book-gallery-settings-form' ),
				nonceField: document.getElementById( 'brag_book_gallery_nonce' )
			};

			/**
			 * Update character counters
			 *
			 * @since 3.0.0
			 */
			const updateCounters = () => {
				if ( elements.titleInput && elements.titleCount ) {
					elements.titleCount.textContent = elements.titleInput.value.length;
					const counter = document.getElementById( 'title-counter' );
					if ( counter ) {
						counter.className = elements.titleInput.value.length > 60 ? 'seo-counter warning' : 'seo-counter';
					}
				}
				if ( elements.descInput && elements.descCount ) {
					elements.descCount.textContent = elements.descInput.value.length;
					const counter = document.getElementById( 'desc-counter' );
					if ( counter ) {
						counter.className = elements.descInput.value.length > 160 ? 'seo-counter warning' : 'seo-counter';
					}
				}
			};

			/**
			 * Update SERP preview
			 *
			 * @since 3.0.0
			 */
			const updateSerpPreview = () => {
				if ( elements.titleInput && elements.serpTitle ) {
					const title = elements.titleInput.value || '<?php echo esc_js( get_bloginfo( 'name' ) . ' - ' . __( 'Gallery', 'brag-book-gallery' ) ); ?>';
					elements.serpTitle.textContent = title.substring( 0, 60 ) + (
						title.length > 60 ? '...' : ''
					);
				}
				if ( elements.descInput && elements.serpDesc ) {
					const desc = elements.descInput.value || 'View our gallery of before and after photos showcasing our work and results.';
					elements.serpDesc.textContent = desc.substring( 0, 160 ) + (
						desc.length > 160 ? '...' : ''
					);
				}
				if ( elements.slugInput && elements.serpPath ) {
					elements.serpPath.textContent = elements.slugInput.value || 'gallery';
				}
			};

			/**
			 * Handle form submission
			 *
			 * @param {Event} e - Submit event
			 * @since 3.0.0
			 */
			const handleFormSubmit = async ( e ) => {
				e.preventDefault();

				const form = e.target;
				const submitBtn = form.querySelector( '#submit' );
				const formData = new FormData( form );

				if ( !elements.nonceField ) {
					return;
				}

				submitBtn.disabled = true;
				submitBtn.textContent = '<?php esc_html_e( 'Saving...', 'brag-book-gallery' ); ?>';

				// Convert FormData to URL-encoded string
				const params = new URLSearchParams();
				params.append( 'action', 'brag_book_gallery_save_brag_book_gallery_settings' );
				params.append( 'form_data', new URLSearchParams( formData ).toString() );
				params.append( 'nonce', elements.nonceField.value );

				try {
					const response = await fetch( ajaxurl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: params
					} );

					const data = await response.json();

					// Remove existing notices
					document.querySelectorAll( '.notice' ).forEach( n => n.remove() );

					// Create new notice
					const notice = document.createElement( 'div' );
					notice.className = `notice notice-${data.success ? 'success' : 'error'} is-dismissible`;
					notice.innerHTML = `<p>${data.data}</p>`;

					const header = document.querySelector( '.brag-book-gallery-header' );
					if ( header ) {
						header.parentNode.insertBefore( notice, header.nextSibling );
					}
				} catch ( error ) {
					console.error( 'Error saving settings:', error );
				} finally {
					submitBtn.disabled = false;
					submitBtn.textContent = '<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>';
				}
			};

			/**
			 * Initialize event listeners
			 *
			 * @since 3.0.0
			 */
			const initializeEventListeners = () => {
				// Add event listeners for real-time preview
				if ( elements.titleInput ) {
					elements.titleInput.addEventListener( 'input', () => {
						updateCounters();
						updateSerpPreview();
					} );
				}

				if ( elements.descInput ) {
					elements.descInput.addEventListener( 'input', () => {
						updateCounters();
						updateSerpPreview();
					} );
				}

				if ( elements.slugInput ) {
					elements.slugInput.addEventListener( 'input', updateSerpPreview );
				}

				// Initialize counters and preview
				updateCounters();
				updateSerpPreview();

				// Form submission
				if ( elements.settingsForm ) {
					elements.settingsForm.addEventListener( 'submit', handleFormSubmit );
				}
			};

			// Initialize when DOM is ready
			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', initializeEventListeners );
			} else {
				initializeEventListeners();
			}
		}
	)();
</script>
<?php
}

	/**
	 * Render API settings page
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_api_settings_page(): void {

	// Get current API settings.
	$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
	$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );
	$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
	?>
	<div class="wrap brag-book-gallery-admin-wrap">
		<div class="brag-book-gallery-header">
			<div class="brag-book-gallery-header-left">
				<img
					src="<?php echo esc_url( plugins_url( 'assets/images/brag-book-gallery-logo.svg', dirname( __DIR__ ) ) ); ?>"
					alt="BRAG Book" class="brag-book-gallery-logo"/>
				<h1><?php esc_html_e( 'BRAG Book Gallery', 'brag-book-gallery' ); ?></h1>
			</div>
			<div class="brag-book-gallery-header-right">
				<?php echo esc_html__( 'v', 'brag-book-gallery' ) . esc_html( $this->get_plugin_info_value( 'Version' ) ); ?>
			</div>
		</div>

		<?php $this->render_tabs( 'api' ); ?>

		<div class="brag-book-gallery-api-instructions">
			<div
				class="brag-book-gallery-notice brag-book-gallery-notice-info">
				<h3><?php esc_html_e( 'Getting Started with BRAG Book API', 'brag-book-gallery' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Log in to your BRAG Book account at', 'brag-book-gallery' ); ?>
						<a href="https://app.bragbookgallery.com"
						   target="_blank">app.bragbookgallery.com</a></li>
					<li><?php esc_html_e( 'Navigate to Settings → API Tokens', 'brag-book-gallery' ); ?></li>
					<li><?php esc_html_e( 'Copy your API Token and Website Property ID', 'brag-book-gallery' ); ?></li>
					<li><?php esc_html_e( 'Enter them below and click "Validate & Save"', 'brag-book-gallery' ); ?></li>
				</ol>
			</div>
		</div>

		<form method="post" action=""
			  id="brag-book-gallery-api-settings-form">
			<?php wp_nonce_field( 'brag_book_gallery_settings_nonce', 'brag_book_gallery_nonce' ); ?>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'API Connections', 'brag-book-gallery' ); ?></h2>
				<div id="bb-api-rows">
					<?php if ( ! empty( $api_tokens ) ) : ?>
					<?php foreach ( $api_tokens as $index => $token ) : ?>
					<div class="bb-api-row"
						 data-index="<?php echo esc_attr( $index ); ?>">
						<div class="bb-api-row-header">
							<h3><?php echo sprintf( esc_html__( 'Connection %d', 'brag-book-gallery' ), $index + 1 ); ?></h3>
							<span class="bb-api-status"
								  data-index="<?php echo esc_attr( $index ); ?>"></span>
						</div>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'API Token', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text"
										   name="brag_book_gallery_api_token[]"
										   value="<?php echo esc_attr( $token ); ?>"
										   placeholder="<?php esc_attr_e( 'Enter your API token', 'brag-book-gallery' ); ?>"
										   class="large-text bb-api-token"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Website Property ID', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text"
										   name="brag_book_gallery_website_property_id[]"
										   value="<?php echo esc_attr( $website_property_ids[ $index ] ?? '' ); ?>"
										   placeholder="<?php esc_attr_e( 'Enter your website property ID', 'brag-book-gallery' ); ?>"
										   class="regular-text bb-websiteproperty-id"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Gallery Page Slug', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text"
										   name="brag_book_gallery_gallery_page_slug[]"
										   value="<?php echo esc_attr( $gallery_slugs[ $index ] ?? '' ); ?>"
										   placeholder="<?php esc_attr_e( 'e.g., before-after-gallery', 'brag-book-gallery' ); ?>"
										   class="regular-text"/>
									<p class="description">
										<?php esc_html_e( 'The URL slug for this gallery page.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"></th>
								<td>
									<button type="button"
											class="button bb-validate-api"
											data-index="<?php echo esc_attr( $index ); ?>">
										<?php esc_html_e( 'Validate Connection', 'brag-book-gallery' ); ?>
									</button>
									<button type="button"
											class="button bb-remove-api-row">
										<?php esc_html_e( 'Remove', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>
						</table>
					</div>
					<?php endforeach; ?>
					<?php else : ?>
					<div class="bb-api-row" data-index="0">
						<div class="bb-api-row-header">
							<h3><?php esc_html_e( 'Connection 1', 'brag-book-gallery' ); ?></h3>
							<span class="bb-api-status"
								  data-index="0"></span>
						</div>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'API Token', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text"
										   name="brag_book_gallery_api_token[]"
										   value=""
										   placeholder="<?php esc_attr_e( 'Enter your API token', 'brag-book-gallery' ); ?>"
										   class="large-text bb-api-token"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Website Property ID', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text"
										   name="brag_book_gallery_website_property_id[]"
										   value=""
										   placeholder="<?php esc_attr_e( 'Enter your website property ID', 'brag-book-gallery' ); ?>"
										   class="regular-text bb-websiteproperty-id"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Gallery Page Slug', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text"
										   name="brag_book_gallery_gallery_page_slug[]"
										   value=""
										   placeholder="<?php esc_attr_e( 'e.g., before-after-gallery', 'brag-book-gallery' ); ?>"
										   class="regular-text"/>
									<p class="description">
										<?php esc_html_e( 'The URL slug for this gallery page.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"></th>
								<td>
									<button type="button"
											class="button bb-validate-api"
											data-index="0">
										<?php esc_html_e( 'Validate Connection', 'brag-book-gallery' ); ?>
									</button>
									<button type="button"
											class="button bb-remove-api-row">
										<?php esc_html_e( 'Remove', 'brag-book-gallery' ); ?>
									</button>
								</td>
							</tr>
						</table>
					</div>
					<?php endif; ?>
				</div>

				<p>
					<button type="button"
							class="button button-secondary bb-add-api-row">
						<?php esc_html_e( 'Add Another Connection', 'brag-book-gallery' ); ?>
					</button>
				</p>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary"
						id="submit">
					<?php esc_html_e( 'Validate & Save All', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-secondary"
						id="bb-clear-cache">
					<?php esc_html_e( 'Clear API Cache', 'brag-book-gallery' ); ?>
				</button>
			</p>
		</form>
	</div>

	<script>
		'use strict';
		document.addEventListener( 'DOMContentLoaded', function () {
			// Add API row
			const addBtn = document.querySelector( '.bb-add-api-row' );
			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					const rows = document.querySelectorAll( '.bb-api-row' );
					const newIndex = rows.length;
					const newRow = rows[0].cloneNode( true );

					newRow.setAttribute( 'data-index', newIndex );
					newRow.querySelectorAll( 'input' ).forEach( input => input.value = '' );
					newRow.querySelector( 'h3' ).textContent = '<?php esc_html_e( 'Connection', 'brag-book-gallery' ); ?> ' + (
															   newIndex + 1
					);
					const statusEl = newRow.querySelector( '.bb-api-status' );
					if ( statusEl ) {
						statusEl.setAttribute( 'data-index', newIndex );
						statusEl.innerHTML = '';
					}
					const validateBtn = newRow.querySelector( '.bb-validate-api' );
					if ( validateBtn ) {
						validateBtn.setAttribute( 'data-index', newIndex );
					}
					document.getElementById( 'bb-api-rows' ).appendChild( newRow );
				} );
			}

			// Remove API row
			document.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'bb-remove-api-row' ) ) {
					const rows = document.querySelectorAll( '.bb-api-row' );
					if ( rows.length > 1 ) {
						e.target.closest( '.bb-api-row' ).remove();
						// Renumber rows
						document.querySelectorAll( '.bb-api-row' ).forEach( ( row, index ) => {
							row.setAttribute( 'data-index', index );
							row.querySelector( 'h3' ).textContent = '<?php esc_html_e( 'Connection', 'brag-book-gallery' ); ?> ' + (
																	index + 1
							);
							const statusEl = row.querySelector( '.bb-api-status' );
							const validateBtn = row.querySelector( '.bb-validate-api' );
							if ( statusEl ) {
								statusEl.setAttribute( 'data-index', index );
							}
							if ( validateBtn ) {
								validateBtn.setAttribute( 'data-index', index );
							}
						} );
					} else {
						alert( '<?php esc_html_e( 'You must have at least one API connection.', 'brag-book-gallery' ); ?>' );
					}
				}

				// Validate single API connection
				if ( e.target.classList.contains( 'bb-validate-api' ) ) {
					const button = e.target;
					const row = button.closest( '.bb-api-row' );
					const statusEl = row.querySelector( '.bb-api-status' );
					const apiToken = row.querySelector( '.bb-api-token' ).value;
					const websitePropertyId = row.querySelector( '.bb-websiteproperty-id' ).value;
					const nonceField = document.getElementById( 'brag_book_gallery_nonce' );

					button.disabled = true;
					button.textContent = '<?php esc_html_e( 'Validating...', 'brag-book-gallery' ); ?>';
					statusEl.innerHTML = '<span class="spinner is-active"></span>';

					const params = new URLSearchParams();
					params.append( 'action', 'brag_book_gallery_validate_api' );
					params.append( 'api_token', apiToken );
					params.append( 'websiteproperty_id', websitePropertyId );
					params.append( 'nonce', nonceField.value );

					fetch( ajaxurl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: params
					} )
						.then( response => response.json() )
						.then( data => {
							if ( data.success ) {
								statusEl.innerHTML = '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' +
													 data.data.message;
							} else {
								statusEl.innerHTML = '<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ' +
													 data.data;
							}
						} )
						.catch( () => {
							statusEl.innerHTML = '<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <?php esc_html_e( 'Connection failed', 'brag-book-gallery' ); ?>';
						} )
						.finally( () => {
							button.disabled = false;
							button.textContent = '<?php esc_html_e( 'Validate Connection', 'brag-book-gallery' ); ?>';
						} );
				}
			} );

			// Save API settings
			const apiForm = document.getElementById( 'brag-book-gallery-api-settings-form' );
			if ( apiForm ) {
				apiForm.addEventListener( 'submit', async function ( e ) {
					e.preventDefault();

					const submitBtn = this.querySelector( '#submit' );
					const formData = new FormData( this );
					const nonceField = document.getElementById( 'brag_book_gallery_nonce' );

					submitBtn.disabled = true;
					submitBtn.textContent = '<?php esc_html_e( 'Validating & Saving...', 'brag-book-gallery' ); ?>';

					const params = new URLSearchParams();
					params.append( 'action', 'brag_book_gallery_save_api_settings' );
					params.append( 'form_data', new URLSearchParams( formData ).toString() );
					params.append( 'nonce', nonceField.value );

					try {
						const response = await fetch( ajaxurl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: params
						} );
						const data = await response.json();

						// Remove existing notices
						document.querySelectorAll( '.notice' ).forEach( n => n.remove() );

						// Create new notice
						const notice = document.createElement( 'div' );
						notice.className = `notice notice-${data.success ? 'success' : 'error'} is-dismissible`;
						notice.innerHTML = `<p>${data.data}</p>`;

						const header = document.querySelector( '.brag-book-gallery-header' );
						header.parentNode.insertBefore( notice, header.nextSibling );
					} catch ( error ) {
						console.error( 'Error saving API settings:', error );
					} finally {
						submitBtn.disabled = false;
						submitBtn.textContent = '<?php esc_html_e( 'Validate & Save All', 'brag-book-gallery' ); ?>';
					}
				} );
			}

			// Clear cache
			const clearCacheBtn = document.getElementById( 'bb-clear-cache' );
			if ( clearCacheBtn ) {
				clearCacheBtn.addEventListener( 'click', async function () {
					const button = this;
					const nonceField = document.getElementById( 'brag_book_gallery_nonce' );

					button.disabled = true;
					button.textContent = '<?php esc_html_e( 'Clearing...', 'brag-book-gallery' ); ?>';

					const params = new URLSearchParams();
					params.append( 'action', 'brag_book_gallery_update_api' );
					params.append( 'nonce', nonceField.value );

					try {
						const response = await fetch( ajaxurl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: params
						} );
						const data = await response.json();

						if ( data.success ) {
							// Remove existing notices
							document.querySelectorAll( '.notice' ).forEach( n => n.remove() );

							// Create new notice
							const notice = document.createElement( 'div' );
							notice.className = 'notice notice-success is-dismissible';
							notice.innerHTML = `<p>${data.data}</p>`;

							const header = document.querySelector( '.brag-book-gallery-header' );
							header.parentNode.insertBefore( notice, header.nextSibling );
						}
					} catch ( error ) {
						console.error( 'Error clearing cache:', error );
					} finally {
						button.disabled = false;
						button.textContent = '<?php esc_html_e( 'Clear API Cache', 'brag-book-gallery' ); ?>';
					}
				} );
			}
		} );
	</script>
	<?php
	}

	/**
	 * Render mode settings page
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_mode_settings_page(): void {
		// Get mode manager instance
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();
		$mode_settings = $mode_manager->get_mode_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mode Settings', 'brag-book-gallery' ); ?></h1>
			<?php $this->render_navigation( 'brag-book-gallery-mode' ); ?>

			<div class="brag-book-gallery-mode-content">
				<!-- Current Mode Status -->
				<div class="brag-book-gallery-section">
					<h2><?php esc_html_e( 'Current Mode', 'brag-book-gallery' ); ?></h2>
					<div class="notice notice-info inline">
						<p>
							<strong><?php esc_html_e( 'Active Mode:', 'brag-book-gallery' ); ?></strong>
							<span
								class="brag-mode-badge <?php echo esc_attr( $current_mode ); ?>">
										<?php echo esc_html( ucfirst( $current_mode ) ); ?> Mode
									</span>
						</p>
						<p>
							<?php
							if ( $current_mode === 'javascript' ) {
								esc_html_e( 'Content is loaded dynamically from the BRAG Book API. URLs are virtual and galleries update in real-time.', 'brag-book-gallery' );
							} else {
								esc_html_e( 'Content is stored locally in WordPress. Galleries use native post types and taxonomies for better SEO and performance.', 'brag-book-gallery' );
							}
							?>
						</p>
					</div>
				</div>

				<!-- Mode Switcher -->
				<div class="brag-book-gallery-section">
					<h2><?php esc_html_e( 'Switch Mode', 'brag-book-gallery' ); ?></h2>
					<form id="brag-mode-switch-form" method="post">
						<?php wp_nonce_field( 'brag_book_gallery_mode_switch', 'mode_switch_nonce' ); ?>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Select Mode', 'brag-book-gallery' ); ?></th>
								<td>
									<fieldset>
										<label>
											<input type="radio"
												   name="gallery_mode"
												   value="javascript"
												<?php checked( $current_mode, 'javascript' ); ?>>
											<strong><?php esc_html_e( 'JavaScript Mode', 'brag-book-gallery' ); ?></strong>
											<p class="description">
												<?php esc_html_e( 'Dynamic API-driven content. Best for real-time updates and minimal database usage.', 'brag-book-gallery' ); ?>
											</p>
										</label>
										<br><br>
										<label>
											<input type="radio"
												   name="gallery_mode"
												   value="local"
												<?php checked( $current_mode, 'local' ); ?>>
											<strong><?php esc_html_e( 'Local Mode', 'brag-book-gallery' ); ?></strong>
											<p class="description">
												<?php esc_html_e( 'WordPress native content. Best for SEO, performance, and offline access.', 'brag-book-gallery' ); ?>
											</p>
										</label>
									</fieldset>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="button" id="switch-mode-btn"
									class="button button-primary">
								<?php esc_html_e( 'Switch Mode', 'brag-book-gallery' ); ?>
							</button>
						</p>
					</form>
				</div>

				<!-- Mode-Specific Settings -->
				<div class="brag-book-gallery-section">
					<h2><?php echo esc_html( sprintf( __( '%s Mode Settings', 'brag-book-gallery' ), ucfirst( $current_mode ) ) ); ?></h2>

					<?php if ( $current_mode === 'local' ) : ?>
					<!-- Local Mode Settings -->
					<form id="local-mode-settings-form" method="post">
						<?php wp_nonce_field( 'brag_book_gallery_mode_settings', 'mode_settings_nonce' ); ?>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Sync Frequency', 'brag-book-gallery' ); ?></th>
								<td>
									<select name="sync_frequency"
											id="sync_frequency">
										<option
											value="manual" <?php selected( $mode_settings['sync_frequency'] ?? '', 'manual' ); ?>>
											<?php esc_html_e( 'Manual Only', 'brag-book-gallery' ); ?>
										</option>
										<option
											value="hourly" <?php selected( $mode_settings['sync_frequency'] ?? '', 'hourly' ); ?>>
											<?php esc_html_e( 'Hourly', 'brag-book-gallery' ); ?>
										</option>
										<option
											value="daily" <?php selected( $mode_settings['sync_frequency'] ?? '', 'daily' ); ?>>
											<?php esc_html_e( 'Daily', 'brag-book-gallery' ); ?>
										</option>
										<option
											value="weekly" <?php selected( $mode_settings['sync_frequency'] ?? '', 'weekly' ); ?>>
											<?php esc_html_e( 'Weekly', 'brag-book-gallery' ); ?>
										</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'How often to sync data from the API.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Auto Sync', 'brag-book-gallery' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											   name="auto_sync" value="1"
											<?php checked( $mode_settings['auto_sync'] ?? false, true ); ?>>
										<?php esc_html_e( 'Enable automatic synchronization', 'brag-book-gallery' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Import Images', 'brag-book-gallery' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											   name="import_images"
											   value="1"
											<?php checked( $mode_settings['import_images'] ?? true, true ); ?>>
										<?php esc_html_e( 'Download and store images locally', 'brag-book-gallery' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Store images in WordPress media library for better performance.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Batch Size', 'brag-book-gallery' ); ?></th>
								<td>
									<input type="number" name="batch_size"
										   value="<?php echo esc_attr( $mode_settings['batch_size'] ?? 20 ); ?>"
										   min="1" max="100" step="1">
									<p class="description">
										<?php esc_html_e( 'Number of items to process per batch during sync.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<!-- Sync Statistics -->
						<?php if ( $mode_manager->is_local_mode() ) : ?>
						<h3><?php esc_html_e( 'Sync Statistics', 'brag-book-gallery' ); ?></h3>
						<table class="widefat striped">
							<tr>
								<th><?php esc_html_e( 'Total Galleries', 'brag-book-gallery' ); ?></th>
								<td><?php echo esc_html( wp_count_posts( 'brag_gallery' )->publish ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Medical Categories', 'brag-book-gallery' ); ?></th>
								<td><?php echo esc_html( wp_count_terms( 'brag_category' ) ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></th>
								<td><?php echo esc_html( wp_count_terms( 'brag_procedure' ) ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Last Sync', 'brag-book-gallery' ); ?></th>
								<td>
									<?php
									$last_sync = get_option( 'brag_book_gallery_last_sync' );
									echo $last_sync ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync ) ) : esc_html__( 'Never', 'brag-book-gallery' );
									?>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="button" id="sync-now-btn"
									class="button">
								<?php esc_html_e( 'Sync Now', 'brag-book-gallery' ); ?>
							</button>
						</p>
						<?php endif; ?>

						<p class="submit">
							<button type="submit"
									class="button button-primary">
								<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
							</button>
						</p>
					</form>

					<?php else : ?>
					<!-- JavaScript Mode Settings -->
					<form id="javascript-mode-settings-form" method="post">
						<?php wp_nonce_field( 'brag_book_gallery_mode_settings', 'mode_settings_nonce' ); ?>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Cache Duration', 'brag-book-gallery' ); ?></th>
								<td>
									<input type="number"
										   name="cache_duration"
										   value="<?php echo esc_attr( $mode_settings['cache_duration'] ?? 300 ); ?>"
										   min="0" max="3600" step="60">
									<p class="description">
										<?php esc_html_e( 'API response cache duration in seconds (0 to disable).', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'API Timeout', 'brag-book-gallery' ); ?></th>
								<td>
									<input type="number" name="api_timeout"
										   value="<?php echo esc_attr( $mode_settings['api_timeout'] ?? 30 ); ?>"
										   min="5" max="120" step="5">
									<p class="description">
										<?php esc_html_e( 'API request timeout in seconds.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Lazy Loading', 'brag-book-gallery' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											   name="lazy_load" value="1"
											<?php checked( $mode_settings['lazy_load'] ?? true, true ); ?>>
										<?php esc_html_e( 'Enable lazy loading for images', 'brag-book-gallery' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Virtual URLs', 'brag-book-gallery' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											   name="virtual_urls" value="1"
											<?php checked( $mode_settings['virtual_urls'] ?? true, true ); ?>>
										<?php esc_html_e( 'Use virtual URLs for gallery pages', 'brag-book-gallery' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Creates SEO-friendly URLs without creating actual pages.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit"
									class="button button-primary">
								<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
							</button>
						</p>
					</form>
					<?php endif; ?>
				</div>

				<!-- Mode Comparison -->
				<div class="brag-book-gallery-section">
					<h2><?php esc_html_e( 'Mode Comparison', 'brag-book-gallery' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
						<tr>
							<th style="width: 30%;"><?php esc_html_e( 'Feature', 'brag-book-gallery' ); ?></th>
							<th style="width: 35%;"><?php esc_html_e( 'JavaScript Mode', 'brag-book-gallery' ); ?></th>
							<th style="width: 35%;"><?php esc_html_e( 'Local Mode', 'brag-book-gallery' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<tr>
							<td>
								<strong><?php esc_html_e( 'Data Source', 'brag-book-gallery' ); ?></strong>
							</td>
							<td><?php esc_html_e( 'External API (real-time)', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'WordPress database', 'brag-book-gallery' ); ?></td>
						</tr>
						<tr>
							<td>
								<strong><?php esc_html_e( 'Performance', 'brag-book-gallery' ); ?></strong>
							</td>
							<td><?php esc_html_e( 'Depends on API latency', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'Fast (local queries)', 'brag-book-gallery' ); ?></td>
						</tr>
						<tr>
							<td>
								<strong><?php esc_html_e( 'SEO', 'brag-book-gallery' ); ?></strong>
							</td>
							<td><?php esc_html_e( 'Limited (requires special handling)', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'Excellent (native WordPress)', 'brag-book-gallery' ); ?></td>
						</tr>
						<tr>
							<td>
								<strong><?php esc_html_e( 'Offline Access', 'brag-book-gallery' ); ?></strong>
							</td>
							<td><?php esc_html_e( 'Not available', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'Fully functional', 'brag-book-gallery' ); ?></td>
						</tr>
						<tr>
							<td>
								<strong><?php esc_html_e( 'Storage', 'brag-book-gallery' ); ?></strong>
							</td>
							<td><?php esc_html_e( 'Minimal', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'Requires database/media storage', 'brag-book-gallery' ); ?></td>
						</tr>
						<tr>
							<td>
								<strong><?php esc_html_e( 'Updates', 'brag-book-gallery' ); ?></strong>
							</td>
							<td><?php esc_html_e( 'Real-time', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'Requires sync', 'brag-book-gallery' ); ?></td>
						</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<style>
			.brag-mode-badge {
				display: inline-block;
				padding: 5px 10px;
				border-radius: 3px;
				font-weight: bold;
				margin-left: 10px;
			}

			.brag-mode-badge.javascript {
				background: #0073aa;
				color: white;
			}

			.brag-mode-badge.local {
				background: #46b450;
				color: white;
			}
		</style>

		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				// Mode switcher
				const switchModeBtn = document.getElementById( 'switch-mode-btn' );
				if ( switchModeBtn ) {
					switchModeBtn.addEventListener( 'click', function () {
						const selectedMode = document.querySelector( 'input[name="gallery_mode"]:checked' )?.value;
						const currentMode = '<?php echo esc_js( $current_mode ); ?>';

						if ( selectedMode === currentMode ) {
							alert( '<?php echo esc_js( __( 'You are already in this mode.', 'brag-book-gallery' ) ); ?>' );
							return;
						}

						if ( !confirm( '<?php echo esc_js( __( 'Are you sure you want to switch modes? This may take a few moments.', 'brag-book-gallery' ) ); ?>' ) ) {
							return;
						}

						const button = this;
						button.disabled = true;
						button.textContent = '<?php echo esc_js( __( 'Switching...', 'brag-book-gallery' ) ); ?>';

						const formData = new FormData();
						formData.append( 'action', 'brag_book_gallery_switch_mode' );
						formData.append( 'mode', selectedMode );
						formData.append( 'nonce', document.getElementById( 'mode_switch_nonce' ).value );

						fetch( ajaxurl, {
							method: 'POST',
							body: formData
						} )
							.then( response => response.json() )
							.then( response => {
								if ( response.success ) {
									alert( response.data.message );
									location.reload();
								} else {
									alert( response.data || '<?php echo esc_js( __( 'Failed to switch mode.', 'brag-book-gallery' ) ); ?>' );
									button.disabled = false;
									button.textContent = '<?php echo esc_js( __( 'Switch Mode', 'brag-book-gallery' ) ); ?>';
								}
							} )
							.catch( error => {
								alert( '<?php echo esc_js( __( 'An error occurred. Please try again.', 'brag-book-gallery' ) ); ?>' );
								button.disabled = false;
								button.textContent = '<?php echo esc_js( __( 'Switch Mode', 'brag-book-gallery' ) ); ?>';
							} );
					} );
				}

				// Sync now button
				const syncNowBtn = document.getElementById( 'sync-now-btn' );
				if ( syncNowBtn ) {
					syncNowBtn.addEventListener( 'click', function () {
						// Future implementation for sync functionality
						alert( '<?php echo esc_js( __( 'Sync functionality will be implemented in the next phase.', 'brag-book-gallery' ) ); ?>' );
					} );
				}

				// Settings forms
				const settingsForms = document.querySelectorAll( '#local-mode-settings-form, #javascript-mode-settings-form' );
				settingsForms.forEach( form => {
					form.addEventListener( 'submit', function ( e ) {
						e.preventDefault();
						// Future implementation for saving mode-specific settings
						alert( '<?php echo esc_js( __( 'Settings save functionality will be implemented in the next phase.', 'brag-book-gallery' ) ); ?>' );
					} );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Render help page
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_help_page(): void {
	?>
	<div class="wrap brag-book-gallery-admin-wrap">
		<div class="brag-book-gallery-header">
			<div class="brag-book-gallery-header-left">
				<img
					src="<?php echo esc_url( plugins_url( 'assets/images/brag-book-gallery-logo.svg', dirname( __DIR__ ) ) ); ?>"
					alt="BRAG Book" class="brag-book-gallery-logo"/>
				<h1><?php esc_html_e( 'BRAG Book Gallery', 'brag-book-gallery' ); ?></h1>
			</div>
			<div class="brag-book-gallery-header-right">
				<?php echo esc_html__( 'v', 'brag-book-gallery' ) . esc_html( $this->get_plugin_info_value( 'Version' ) ); ?>
			</div>
		</div>

		<?php $this->render_tabs( 'help' ); ?>

		<div class="brag-book-gallery-help-content">
			<!-- Quick Start Guide -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Quick Start Guide', 'brag-book-gallery' ); ?></h2>
				<ol class="brag-book-gallery-steps">
					<li>
						<h3><?php esc_html_e( 'Get Your API Credentials', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Log in to your BRAG Book account at', 'brag-book-gallery' ); ?>
							<a href="https://app.bragbookgallery.com"
							   target="_blank">app.bragbookgallery.com</a> <?php esc_html_e( 'and navigate to Settings → API Tokens to get your credentials.', 'brag-book-gallery' ); ?>
						</p>
					</li>
					<li>
						<h3><?php esc_html_e( 'Configure API Settings', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?>
							<a
								href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>"><?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?></a> <?php esc_html_e( 'and enter your API token and Website Property ID. Click "Validate Connection" to ensure everything is working.', 'brag-book-gallery' ); ?>
						</p>
					</li>
					<li>
						<h3><?php esc_html_e( 'Create Gallery Pages', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'The plugin will automatically create pages based on the slugs you provide. Each gallery page will display your before/after photos.', 'brag-book-gallery' ); ?></p>
					</li>
					<li>
						<h3><?php esc_html_e( 'Customize Your Galleries', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Use the General Settings to add landing page content and configure SEO settings for your galleries.', 'brag-book-gallery' ); ?></p>
					</li>
				</ol>
			</div>

			<!-- Shortcodes -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Available Shortcodes', 'brag-book-gallery' ); ?></h2>

				<h3><?php esc_html_e( 'Main Gallery Shortcodes', 'brag-book-gallery' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'Shortcode', 'brag-book-gallery' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Parameters', 'brag-book-gallery' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><code>[brag_book_gallery_gallery]</code></td>
						<td><?php esc_html_e( 'Display a single gallery', 'brag-book-gallery' ); ?></td>
						<td><?php esc_html_e( 'Automatically uses page settings', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>[brag_book_gallery_gallery
							combine="true"]</code>
						</td>
						<td><?php esc_html_e( 'Display combined galleries from all sources', 'brag-book-gallery' ); ?></td>
						<td><code>combine="true"</code>
							- <?php esc_html_e( 'Combine all galleries', 'brag-book-gallery' ); ?>
						</td>
					</tr>
					<tr>
						<td><code>[mvp-brag-gallery]</code></td>
						<td><?php esc_html_e( 'Legacy shortcode (backward compatibility)', 'brag-book-gallery' ); ?></td>
						<td><?php esc_html_e( 'Same as [brag_book_gallery_gallery]', 'brag-book-gallery' ); ?></td>
					</tr>
					</tbody>
				</table>

				<h3 style="margin-top: 30px;"><?php esc_html_e( 'Display Shortcode', 'brag-book-gallery' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th style="width: 30%;"><?php esc_html_e( 'Shortcode', 'brag-book-gallery' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
						<th style="width: 35%;"><?php esc_html_e( 'Parameters', 'brag-book-gallery' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><code>[brag_book_gallery]</code></td>
						<td><?php esc_html_e( 'Display the main BRAG Book gallery with filters and case grid', 'brag-book-gallery' ); ?></td>
						<td>
							<code>website_property_id</code>
							- <?php esc_html_e( 'Property ID (optional, uses default if not specified)', 'brag-book-gallery' ); ?>
						</td>
					</tr>
					</tbody>
				</table>

				<div class="notice notice-info inline"
					 style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e( 'Example Usage:', 'brag-book-gallery' ); ?></strong><br>
						<code>[brag_book_gallery]</code>
						- <?php esc_html_e( 'Uses the default API configuration', 'brag-book-gallery' ); ?>
						<br>
						<code>[brag_book_gallery
							website_property_id="123"]</code>
						- <?php esc_html_e( 'Uses specific property ID', 'brag-book-gallery' ); ?>
					</p>
				</div>
			</div>

			<!-- API Endpoints -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'REST API Endpoints', 'brag-book-gallery' ); ?></h2>
				<p><?php esc_html_e( 'The plugin communicates with the BRAG Book service using the following API endpoints:', 'brag-book-gallery' ); ?></p>

				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th style="width: 35%;"><?php esc_html_e( 'Endpoint', 'brag-book-gallery' ); ?></th>
						<th style="width: 15%;"><?php esc_html_e( 'Method', 'brag-book-gallery' ); ?></th>
						<th style="width: 50%;"><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><code>/api/plugin/combine/filters</code></td>
						<td><span class="dashicons dashicons-upload"></span>
							POST
						</td>
						<td><?php esc_html_e( 'Get available filter options for procedures and categories', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>/api/plugin/combine/cases</code></td>
						<td><span class="dashicons dashicons-upload"></span>
							POST
						</td>
						<td><?php esc_html_e( 'Get paginated list of cases with filtering options', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>/api/plugin/combine/cases/{id}</code></td>
						<td><span class="dashicons dashicons-upload"></span>
							POST
						</td>
						<td><?php esc_html_e( 'Get detailed information for a specific case', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>/api/plugin/combine/sidebar</code></td>
						<td><span class="dashicons dashicons-upload"></span>
							POST
						</td>
						<td><?php esc_html_e( 'Get hierarchical navigation menu data for procedures', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>/api/plugin/combine/favorites/add</code>
						</td>
						<td><span class="dashicons dashicons-upload"></span>
							POST
						</td>
						<td><?php esc_html_e( 'Add a case to user favorites with contact information', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>/api/plugin/combine/favorites/list</code>
						</td>
						<td><span class="dashicons dashicons-upload"></span>
							POST
						</td>
						<td><?php esc_html_e( 'Get list of user favorite cases by email', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>/api/plugin/tracker</code></td>
						<td><span class="dashicons dashicons-upload"></span>
							POST
						</td>
						<td><?php esc_html_e( 'Send plugin version and usage analytics', 'brag-book-gallery' ); ?></td>
					</tr>
					</tbody>
				</table>

				<div class="notice notice-info inline"
					 style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e( 'API Features:', 'brag-book-gallery' ); ?></strong>
					</p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php esc_html_e( 'Response caching for improved performance (5-30 minute cache)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Automatic retry with exponential backoff on failure', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Comprehensive error logging and debugging', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Support for multiple API tokens and website properties', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'SEO-optimized URLs with custom rewrite rules', 'brag-book-gallery' ); ?></li>
					</ul>
					<p>
						<strong><?php esc_html_e( 'Base API URL:', 'brag-book-gallery' ); ?></strong>
						<code>https://app.bragbookgallery.com</code>
					</p>
				</div>
			</div>

			<!-- Developer Hooks -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Developer Hooks & Filters', 'brag-book-gallery' ); ?></h2>
				<p><?php esc_html_e( 'Extend the plugin functionality using these WordPress hooks:', 'brag-book-gallery' ); ?></p>

				<h3><?php esc_html_e( 'Action Hooks', 'brag-book-gallery' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th style="width: 40%;"><?php esc_html_e( 'Hook Name', 'brag-book-gallery' ); ?></th>
						<th style="width: 60%;"><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><code>brag_book_gallery_init</code></td>
						<td><?php esc_html_e( 'Fired after plugin initialization (passes Setup instance)', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_admin_init</code></td>
						<td><?php esc_html_e( 'Fired during admin initialization (passes Setup instance)', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_activate</code></td>
						<td><?php esc_html_e( 'Fired when the plugin is activated', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_deactivate</code></td>
						<td><?php esc_html_e( 'Fired when the plugin is deactivated', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_rest_api_init</code>
						</td>
						<td><?php esc_html_e( 'Fired during REST API initialization', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td>
							<code>brag_book_gallery_enqueue_frontend_assets</code>
						</td>
						<td><?php esc_html_e( 'Fired when frontend assets are enqueued', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td>
							<code>brag_book_gallery_enqueue_admin_assets</code>
						</td>
						<td><?php esc_html_e( 'Fired when admin assets are enqueued (passes hook suffix)', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_run_upgrades</code></td>
						<td><?php esc_html_e( 'Fired during plugin upgrades (passes version)', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_create_tables</code>
						</td>
						<td><?php esc_html_e( 'Fired when creating database tables', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td>
							<code>brag_book_gallery_set_default_options</code>
						</td>
						<td><?php esc_html_e( 'Fired when setting default options (passes defaults array)', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td>
							<code>brag_book_gallery_clear_scheduled_events</code>
						</td>
						<td><?php esc_html_e( 'Fired when clearing scheduled cron events', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_api_cache_cleared</code>
						</td>
						<td><?php esc_html_e( 'Fired after API cache is cleared (passes cache type and result)', 'brag-book-gallery' ); ?></td>
					</tr>
					</tbody>
				</table>

				<h3 style="margin-top: 30px;"><?php esc_html_e( 'Filter Hooks', 'brag-book-gallery' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th style="width: 40%;"><?php esc_html_e( 'Filter Name', 'brag-book-gallery' ); ?></th>
						<th style="width: 60%;"><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td><code>brag_book_gallery_api_timeout</code></td>
						<td><?php esc_html_e( 'Filter API request timeout (default: 30 seconds)', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_cache_duration</code>
						</td>
						<td><?php esc_html_e( 'Filter API cache duration (default: 300 seconds)', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_gallery_output</code>
						</td>
						<td><?php esc_html_e( 'Filter the final gallery HTML output', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_api_request_args</code>
						</td>
						<td><?php esc_html_e( 'Filter API request arguments before sending', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<td><code>brag_book_gallery_rewrite_rules</code>
						</td>
						<td><?php esc_html_e( 'Filter custom rewrite rules for gallery URLs', 'brag-book-gallery' ); ?></td>
					</tr>
					</tbody>
				</table>

				<div class="notice notice-info inline"
					 style="margin-top: 20px;">
					<p>
						<strong><?php esc_html_e( 'Example Usage:', 'brag-book-gallery' ); ?></strong>
					</p>
					<pre
						style="background: #f0f0f0; padding: 10px; overflow-x: auto;">
	// Hook into plugin initialization
	add_action( 'brag_book_gallery_init', function( $setup_instance ) {
		// Your custom initialization code
		// Access services via $setup_instance->get_service( 'service_name' )
	});

	// Extend API timeout for slow connections
	add_filter( 'brag_book_gallery_api_timeout', function( $timeout ) {
		return 60; // 60 seconds
	});

	// Add custom default options
	add_action( 'brag_book_gallery_set_default_options', function( $defaults ) {
		// Add your custom options
		update_option( 'my_custom_option', 'default_value' );
	});

	// Modify gallery output
	add_filter( 'brag_book_gallery_gallery_output', function( $html, $gallery_data ) {
		// Add custom wrapper
		return '<div class="my-custom-wrapper">' . $html . '</div>';
	}, 10, 2);

	// Hook into admin initialization
	add_action( 'brag_book_gallery_admin_init', function( $setup_instance ) {
		// Add custom admin functionality
		if ( $setup_instance->is_initialized() ) {
			// Your admin code here
		}
	});
							</pre>
				</div>
			</div>

			<!-- Troubleshooting -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Troubleshooting', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-accordion">
					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'Gallery not displaying', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<ul>
								<li><?php esc_html_e( 'Verify your API credentials are correct', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Check that the gallery page slug matches your configuration', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Clear the API cache from the API Settings page', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Ensure your BRAG Book account has active galleries', 'brag-book-gallery' ); ?></li>
							</ul>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'API connection errors', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<ul>
								<li><?php esc_html_e( 'Verify your server can make outbound HTTPS requests', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Check that your API token has not expired', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Ensure your Website Property ID is correct', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Contact BRAG Book support if the issue persists', 'brag-book-gallery' ); ?></li>
							</ul>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'SEO settings not working', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<ul>
								<li><?php esc_html_e( 'The plugin automatically detects your SEO plugin', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Supported plugins: Yoast SEO, All in One SEO, Rank Math, SEOPress', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'If no SEO plugin is detected, standard WordPress SEO features are used', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Clear your site cache after making SEO changes', 'brag-book-gallery' ); ?></li>
							</ul>
						</div>
					</details>
				</div>
			</div>

			<!-- Support -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Need More Help?', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-support-cards">
					<div class="brag-book-gallery-card">
						<h3>
									<span
										class="dashicons dashicons-book"></span> <?php esc_html_e( 'Documentation', 'brag-book-gallery' ); ?>
						</h3>
						<p><?php esc_html_e( 'Visit our comprehensive documentation for detailed guides and tutorials.', 'brag-book-gallery' ); ?></p>
						<a href="https://www.bragbookgallery.com/docs/"
						   target="_blank" class="button button-secondary">
							<?php esc_html_e( 'View Documentation', 'brag-book-gallery' ); ?>
						</a>
					</div>
					<div class="brag-book-gallery-card">
						<h3>
									<span
										class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Support', 'brag-book-gallery' ); ?>
						</h3>
						<p><?php esc_html_e( 'Get help from our support team for technical issues and questions.', 'brag-book-gallery' ); ?></p>
						<a href="https://www.bragbookgallery.com/support/"
						   target="_blank" class="button button-secondary">
							<?php esc_html_e( 'Contact Support', 'brag-book-gallery' ); ?>
						</a>
					</div>
					<div class="brag-book-gallery-card">
						<h3>
									<span
										class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Your Account', 'brag-book-gallery' ); ?>
						</h3>
						<p><?php esc_html_e( 'Manage your galleries, API tokens, and account settings.', 'brag-book-gallery' ); ?></p>
						<a href="https://app.bragbookgallery.com"
						   target="_blank" class="button button-secondary">
							<?php esc_html_e( 'Go to BRAG Book App', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- System Info -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'System Information', 'brag-book-gallery' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Plugin Version', 'brag-book-gallery' ); ?></th>
						<td><?php echo esc_html( get_option( 'brag_book_gallery_version', '3.0.0' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></th>
						<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></th>
						<td><?php echo esc_html( phpversion() ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Active Theme', 'brag-book-gallery' ); ?></th>
						<td><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'API Status', 'brag-book-gallery' ); ?></th>
						<td>
							<?php
							$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
							if ( ! empty( $api_tokens ) ) {
								echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
								echo sprintf( esc_html__( '%d connection(s) configured', 'brag-book-gallery' ), count( $api_tokens ) );
							} else {
								echo '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span> ';
								echo esc_html__( 'No API connections configured', 'brag-book-gallery' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Plugin Architecture', 'brag-book-gallery' ); ?></th>
						<td><?php esc_html_e( 'Singleton Pattern with Service Container', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Autoloader', 'brag-book-gallery' ); ?></th>
						<td><?php esc_html_e( 'PSR-4 Compatible Custom Autoloader', 'brag-book-gallery' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Update Mechanism', 'brag-book-gallery' ); ?></th>
						<td><?php esc_html_e( 'GitHub Repository (bragbook2/brag-book-gallery)', 'brag-book-gallery' ); ?></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
	<?php
	}

	/**
	 * Render debug page
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_debug_page(): void {
	?>
	<div class="wrap brag-book-gallery-admin-wrap">
		<div class="brag-book-gallery-header">
			<div class="brag-book-gallery-header-left">
				<img
					src="<?php echo esc_url( plugins_url( 'assets/images/brag-book-gallery-logo.svg', dirname( __DIR__ ) ) ); ?>"
					alt="BRAG Book" class="brag-book-gallery-logo"/>
				<h1><?php esc_html_e( 'BRAG Book Gallery', 'brag-book-gallery' ); ?></h1>
			</div>
			<div class="brag-book-gallery-header-right">
				<?php echo esc_html__( 'v', 'brag-book-gallery' ) . esc_html( $this->get_plugin_info_value( 'Version' ) ); ?>
			</div>
		</div>

		<?php $this->render_tabs( 'debug' ); ?>

		<div class="brag-book-gallery-debug-content">
			<!-- Debug Log Tabs -->
			<div class="brag-book-gallery-log-tabs">
				<ul class="brag-book-gallery-log-tab-nav">
					<li><a href="#error-log-tab"
						   class="brag-book-gallery-tab-link active"
						   data-tab="error-log"><?php esc_html_e( 'Error Log', 'brag-book-gallery' ); ?></a>
					</li>
					<li><a href="#api-log-tab"
						   class="brag-book-gallery-tab-link"
						   data-tab="api-log"><?php esc_html_e( 'API Log', 'brag-book-gallery' ); ?></a>
					</li>
					<li><a href="#system-info-tab"
						   class="brag-book-gallery-tab-link"
						   data-tab="system-info"><?php esc_html_e( 'System Info', 'brag-book-gallery' ); ?></a>
					</li>
					<li><a href="#debug-settings-tab"
						   class="brag-book-gallery-tab-link"
						   data-tab="debug-settings"><?php esc_html_e( 'Settings', 'brag-book-gallery' ); ?></a>
					</li>
				</ul>

				<!-- Error Log Tab -->
				<div id="error-log-tab"
					 class="brag-book-gallery-log-tab-content active">
					<div class="brag-book-gallery-section">
						<h2><?php esc_html_e( 'Error Log', 'brag-book-gallery' ); ?></h2>
						<div class="brag-book-gallery-debug-controls">
							<button type="button"
									class="button button-secondary"
									id="bb-refresh-error-log">
										<span
											class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Refresh', 'brag-book-gallery' ); ?>
							</button>
							<button type="button"
									class="button button-secondary"
									id="bb-download-error-log">
										<span
											class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Download Log', 'brag-book-gallery' ); ?>
							</button>
							<button type="button" class="button"
									id="bb-clear-error-log">
										<span
											class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear Log', 'brag-book-gallery' ); ?>
							</button>
						</div>
						<div class="brag-book-gallery-log-viewer">
									<pre id="brag-book-gallery-error-log"
										 class="brag-book-gallery-error-log"><?php echo esc_html( $this->get_error_log() ); ?></pre>
						</div>
						<p class="description">
							<?php esc_html_e( 'Shows the last 100 entries from the BRAG Book error log. Newest entries appear first.', 'brag-book-gallery' ); ?>
						</p>
					</div>
				</div>

				<!-- API Log Tab -->
				<div id="api-log-tab"
					 class="brag-book-gallery-log-tab-content">
					<div class="brag-book-gallery-section">
						<h2><?php esc_html_e( 'API Log', 'brag-book-gallery' ); ?></h2>
						<div class="brag-book-gallery-debug-controls">
							<button type="button"
									class="button button-secondary"
									id="bb-refresh-api-log">
										<span
											class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Refresh', 'brag-book-gallery' ); ?>
							</button>
							<button type="button"
									class="button button-secondary"
									id="bb-download-api-log">
										<span
											class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Download Log', 'brag-book-gallery' ); ?>
							</button>
							<button type="button" class="button"
									id="bb-clear-api-log">
										<span
											class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear Log', 'brag-book-gallery' ); ?>
							</button>
						</div>
						<div class="brag-book-gallery-log-viewer">
									<pre id="brag-book-gallery-api-log"
										 class="brag-book-gallery-api-log"><?php echo esc_html( $this->get_api_log() ); ?></pre>
						</div>
						<p class="description">
							<?php esc_html_e( 'Shows the last 100 API requests and responses. Newest entries appear first.', 'brag-book-gallery' ); ?>
						</p>
					</div>
				</div>

				<!-- System Info Tab -->
				<div id="system-info-tab"
					 class="brag-book-gallery-log-tab-content">
					<div class="brag-book-gallery-section">
						<h2><?php esc_html_e( 'System Information', 'brag-book-gallery' ); ?></h2>
						<div class="brag-book-gallery-debug-controls">
							<button type="button"
									class="button button-primary"
									id="bb-copy-system-info">
										<span
											class="dashicons dashicons-clipboard"></span>
								<?php esc_html_e( 'Copy to Clipboard', 'brag-book-gallery' ); ?>
							</button>
							<button type="button"
									class="button button-secondary"
									id="bb-export-system-info">
										<span
											class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export as Text', 'brag-book-gallery' ); ?>
							</button>
							<span id="bb-copy-feedback"
								  class="brag-book-gallery-copy-feedback"><?php esc_html_e( 'Copied!', 'brag-book-gallery' ); ?></span>
						</div>
						<div class="brag-book-gallery-system-info">
									<pre
										id="brag-book-gallery-system-info"><?php echo esc_html( $this->get_system_info() ); ?></pre>
						</div>
						<p class="description">
							<?php esc_html_e( 'Copy this information when contacting support for assistance.', 'brag-book-gallery' ); ?>
						</p>
					</div>
				</div>

				<!-- Debug Settings Tab -->
				<div id="debug-settings-tab"
					 class="brag-book-gallery-log-tab-content">
					<div class="brag-book-gallery-section">
						<h2><?php esc_html_e( 'Debug Settings', 'brag-book-gallery' ); ?></h2>
						<table class="form-table">
							<tr>
								<th scope="row">
									<?php esc_html_e( 'WordPress Debug Mode', 'brag-book-gallery' ); ?>
								</th>
								<td>
									<label>
										<input type="checkbox"
											   id="wp-debug-mode"
											   name="wp_debug_mode"
											<?php checked( defined( 'WP_DEBUG' ) && WP_DEBUG ); ?>
											<?php echo ( ! is_writable( ABSPATH . 'wp-config.php' ) ) ? 'disabled' : ''; ?> />
										<?php esc_html_e( 'Enable WP_DEBUG mode', 'brag-book-gallery' ); ?>
									</label>
									<p class="description">
										<?php
										if ( ! is_writable( ABSPATH . 'wp-config.php' ) ) {
											esc_html_e( 'wp-config.php is not writable. Please update WP_DEBUG manually.', 'brag-book-gallery' );
										} else {
											esc_html_e( 'Enable WordPress debug mode to display PHP errors and warnings.', 'brag-book-gallery' );
										}
										?>
									</p>
									<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
									<p class="notice notice-info inline">
										<?php esc_html_e( 'WP_DEBUG is currently ENABLED', 'brag-book-gallery' ); ?>
									</p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'BRAG Book Debug Mode', 'brag-book-gallery' ); ?>
								</th>
								<td>
									<label>
										<input type="checkbox"
											   id="brag-book-gallery-debug-mode"
											   name="brag_book_gallery_debug_mode"
											<?php checked( get_option( 'brag_book_gallery_debug_mode', false ) ); ?> />
										<?php esc_html_e( 'Enable detailed plugin logging', 'brag-book-gallery' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'When enabled, additional debug information will be logged for troubleshooting.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'API Logging', 'brag-book-gallery' ); ?>
								</th>
								<td>
									<label>
										<input type="checkbox"
											   id="brag-book-gallery-api-logging"
											   name="brag_book_gallery_api_logging"
											<?php checked( get_option( 'brag_book_gallery_api_logging', false ) ); ?> />
										<?php esc_html_e( 'Log all API requests and responses', 'brag-book-gallery' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Enable logging of all API communications for debugging.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Log Level', 'brag-book-gallery' ); ?>
								</th>
								<td>
									<select id="brag-book-gallery-log-level"
											name="brag_book_gallery_log_level">
										<?php
										$log_level = get_option( 'brag_book_gallery_log_level', 'error' );
										$levels = [
											'error'   => __( 'Errors Only', 'brag-book-gallery' ),
											'warning' => __( 'Warnings & Errors', 'brag-book-gallery' ),
											'info'    => __( 'Info, Warnings & Errors', 'brag-book-gallery' ),
											'debug'   => __( 'All Messages (Debug)', 'brag-book-gallery' ),
										];
										foreach ( $levels as $value => $label ) :
										?>
										<option
											value="<?php echo esc_attr( $value ); ?>" <?php selected( $log_level, $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Controls the verbosity of error logging.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Log File Sizes', 'brag-book-gallery' ); ?>
								</th>
								<td>
									<?php
									$error_log_file = $this->get_log_file_path();
									$api_log_file = $this->get_log_file_path( 'api' );

									echo '<strong>' . esc_html__( 'Error Log:', 'brag-book-gallery' ) . '</strong> ';
									if ( file_exists( $error_log_file ) ) {
										$size = filesize( $error_log_file );
										echo esc_html( size_format( $size ) );
										if ( $size > 5 * MB_IN_BYTES ) {
											echo ' <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>';
										}
									} else {
										echo esc_html__( 'No log file', 'brag-book-gallery' );
									}

									echo '<br><strong>' . esc_html__( 'API Log:', 'brag-book-gallery' ) . '</strong> ';
									if ( file_exists( $api_log_file ) ) {
										$size = filesize( $api_log_file );
										echo esc_html( size_format( $size ) );
										if ( $size > 5 * MB_IN_BYTES ) {
											echo ' <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>';
										}
									} else {
										echo esc_html__( 'No log file', 'brag-book-gallery' );
									}
									?>
									<p class="description">
										<?php esc_html_e( 'Consider clearing log files if they exceed 5MB.', 'brag-book-gallery' ); ?>
									</p>
								</td>
							</tr>
						</table>
						<p class="submit">
							<button type="button"
									class="button button-primary"
									id="bb-save-debug-settings">
								<?php esc_html_e( 'Save Debug Settings', 'brag-book-gallery' ); ?>
							</button>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		// Tab switching for debug page
		const tabLinks = document.querySelectorAll('.brag-book-gallery-tab-link');
		const tabContents = document.querySelectorAll('.brag-book-gallery-log-tab-content');

		tabLinks.forEach(link => {
			link.addEventListener('click', function(e) {
				e.preventDefault();

				// Remove active class from all tabs and contents
				tabLinks.forEach(l => l.classList.remove('active'));
				tabContents.forEach(c => c.classList.remove('active'));

				// Add active class to clicked tab
				this.classList.add('active');

				// Show corresponding content
				const tabId = this.getAttribute('data-tab');
				const content = document.getElementById(tabId + '-tab');
				if (content) {
					content.classList.add('active');
				}
			});
		});

		// Copy system info to clipboard
		const copyBtn = document.getElementById('bb-copy-system-info');
		if (copyBtn) {
			copyBtn.addEventListener('click', function() {
				const systemInfo = document.getElementById('brag-book-gallery-system-info');
				if (systemInfo) {
					navigator.clipboard.writeText(systemInfo.textContent).then(() => {
						const feedback = document.getElementById('bb-copy-feedback');
						if (feedback) {
							feedback.style.display = 'inline';
							setTimeout(() => {
								feedback.style.display = 'none';
							}, 2000);
						}
					});
				}
			});
		}

		// Export system info
		const exportBtn = document.getElementById('bb-export-system-info');
		if (exportBtn) {
			exportBtn.addEventListener('click', function() {
				window.location.href = '<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=brag_book_gallery_export_system_info' ), 'brag_book_gallery_debug_nonce', 'nonce' ) ); ?>';
			});
		}
	});
	</script>

	<style type="text/css">
	.brag-book-gallery-log-tab-content {
		display: none;
	}
	.brag-book-gallery-log-tab-content.active {
		display: block;
	}
	.brag-book-gallery-log-tab-nav {
		display: flex;
		list-style: none;
		margin: 0;
		padding: 0;
		border-bottom: 1px solid #ccc;
		background: #f1f1f1;
	}
	.brag-book-gallery-log-tab-nav li {
		margin: 0;
	}
	.brag-book-gallery-tab-link {
		display: block;
		padding: 10px 20px;
		text-decoration: none;
		color: #555;
		background: #f1f1f1;
		border-right: 1px solid #ccc;
		transition: background 0.3s;
	}
	.brag-book-gallery-tab-link:hover {
		background: #e5e5e5;
	}
	.brag-book-gallery-tab-link.active {
		background: #fff;
		border-bottom: 1px solid #fff;
		margin-bottom: -1px;
		font-weight: bold;
		color: #000;
	}
	.brag-book-gallery-system-info {
		background: #f9f9f9;
		border: 1px solid #ddd;
		border-radius: 4px;
		padding: 15px;
		margin: 15px 0;
		max-height: 500px;
		overflow-y: auto;
	}
	.brag-book-gallery-system-info pre {
		margin: 0;
		white-space: pre-wrap;
		word-wrap: break-word;
		font-family: 'Courier New', Courier, monospace;
		font-size: 12px;
		line-height: 1.5;
	}
	.brag-book-gallery-copy-feedback {
		display: none;
		color: #46b450;
		margin-left: 10px;
		font-weight: bold;
	}
	.brag-book-gallery-log-viewer {
		background: #f9f9f9;
		border: 1px solid #ddd;
		border-radius: 4px;
		padding: 15px;
		margin: 15px 0;
		max-height: 400px;
		overflow-y: auto;
	}
	.brag-book-gallery-log-viewer pre {
		margin: 0;
		white-space: pre-wrap;
		word-wrap: break-word;
		font-family: 'Courier New', Courier, monospace;
		font-size: 12px;
	}
	</style>
	<?php
	}

	/**
	 * Get error log contents
	 *
	 * @return string Error log contents.
	 * @since 3.0.0
	 */
	private function get_error_log(): string {
		$log_file = $this->get_log_file_path( 'error' );

		if ( ! file_exists( $log_file ) ) {
			return __( 'No errors logged.', 'brag-book-gallery' );
		}

		// Read last 100 lines
		$lines = $this->tail_file( $log_file, 100 );

		if ( empty( $lines ) ) {
			return __( 'No errors logged.', 'brag-book-gallery' );
		}

		// Reverse to show newest first
		return implode( "\n", array_reverse( $lines ) );
	}

	/**
	 * Get API log contents
	 *
	 * @return string API log contents.
	 * @since 3.0.0
	 */
	private function get_api_log(): string {
		$log_file = $this->get_log_file_path( 'api' );

		if ( ! file_exists( $log_file ) ) {
			return __( 'No API requests logged.', 'brag-book-gallery' );
		}

		// Read last 100 lines
		$lines = $this->tail_file( $log_file, 100 );

		if ( empty( $lines ) ) {
			return __( 'No API requests logged.', 'brag-book-gallery' );
		}

		// Reverse to show newest first
		return implode( "\n", array_reverse( $lines ) );
	}

	/**
	 * Get system information
	 *
	 * @return string System information.
	 * @since 3.0.0
	 */
	private function get_system_info(): string {
		global $wpdb;

		$theme          = wp_get_theme();
		$active_plugins = get_option( 'active_plugins', [] );
		$api_tokens     = get_option( 'brag_book_gallery_api_token', [] );

		$info   = [];
		$info[] = '==========================================';
		$info[] = '    BRAGBOOK GALLERY SYSTEM REPORT';
		$info[] = '==========================================';
		/* translators: %s: current date and time */
		$info[] = sprintf( esc_html__( 'Generated: %s', 'brag-book-gallery' ), current_time( 'Y-m-d H:i:s' ) );
		$info[] = sprintf( esc_html__( 'Report Version: %s', 'brag-book-gallery' ), '1.0' );
		$info[] = '';

		// Plugin Information
		$info[] = esc_html__( '--- Plugin Information ---', 'brag-book-gallery' );
		/* translators: %s: plugin version */
		$info[] = sprintf( esc_html__( 'Plugin Version: %s', 'brag-book-gallery' ), get_option( 'brag_book_gallery_version', '3.0.0' ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'Debug Mode: %s', 'brag-book-gallery' ), ( get_option( 'brag_book_gallery_debug_mode', false ) ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: log level */
		$info[] = sprintf( esc_html__( 'Log Level: %s', 'brag-book-gallery' ), get_option( 'brag_book_gallery_log_level', 'error' ) );
		/* translators: %d: number of API connections */
		$info[] = sprintf( esc_html__( 'API Connections: %d', 'brag-book-gallery' ), count( $api_tokens ) );
		$info[] = '';

		// Browser Information
		$info[]     = '--- Browser Information ---';
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
		$info[]     = 'User Agent: ' . $user_agent;

		// Parse browser info from user agent
		$browser  = 'Unknown';
		$platform = esc_html__( 'Unknown', 'brag-book-gallery' );

		// Detect browser
		if ( preg_match( '/MSIE|Trident/i', $user_agent ) ) {
			$browser = esc_html__( 'Internet Explorer', 'brag-book-gallery' );
		} elseif ( preg_match( '/Edge/i', $user_agent ) ) {
			$browser = esc_html__( 'Microsoft Edge', 'brag-book-gallery' );
		} elseif ( preg_match( '/Firefox/i', $user_agent ) ) {
			$browser = esc_html__( 'Mozilla Firefox', 'brag-book-gallery' );
		} elseif ( preg_match( '/Chrome/i', $user_agent ) ) {
			if ( preg_match( '/OPR|Opera/i', $user_agent ) ) {
				$browser = esc_html__( 'Opera', 'brag-book-gallery' );
			} else {
				$browser = esc_html__( 'Google Chrome', 'brag-book-gallery' );
			}
		} elseif ( preg_match( '/Safari/i', $user_agent ) ) {
			$browser = esc_html__( 'Safari', 'brag-book-gallery' );
		}

		// Detect platform
		if ( preg_match( '/Windows/i', $user_agent ) ) {
			$platform = esc_html__( 'Windows', 'brag-book-gallery' );
		} elseif ( preg_match( '/Mac/i', $user_agent ) ) {
			$platform = esc_html__( 'macOS', 'brag-book-gallery' );
		} elseif ( preg_match( '/Linux/i', $user_agent ) ) {
			$platform = esc_html__( 'Linux', 'brag-book-gallery' );
		} elseif ( preg_match( '/Android/i', $user_agent ) ) {
			$platform = esc_html__( 'Android', 'brag-book-gallery' );
		} elseif ( preg_match( '/iPhone|iPad|iPod/i', $user_agent ) ) {
			$platform = esc_html__( 'iOS', 'brag-book-gallery' );
		}

		/* translators: %s: browser name */
		$info[] = sprintf( esc_html__( 'Browser: %s', 'brag-book-gallery' ), $browser );
		/* translators: %s: platform name */
		$info[] = sprintf( esc_html__( 'Platform: %s', 'brag-book-gallery' ), $platform );
		/* translators: %s: accept language header */
		$info[] = sprintf( esc_html__( 'Accept Language: %s', 'brag-book-gallery' ), ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? esc_html__( 'Not available', 'brag-book-gallery' ) ) );
		$info[] = '';

		// WordPress Information
		$info[] = esc_html__( '--- WordPress Information ---', 'brag-book-gallery' );
		/* translators: %s: site URL */
		$info[] = sprintf( esc_html__( 'Site URL: %s', 'brag-book-gallery' ), get_site_url() );
		/* translators: %s: home URL */
		$info[] = sprintf( esc_html__( 'Home URL: %s', 'brag-book-gallery' ), get_home_url() );
		/* translators: %s: WordPress address */
		$info[] = sprintf( esc_html__( 'WordPress Address (URL): %s', 'brag-book-gallery' ), get_option( 'siteurl' ) );
		/* translators: %s: WordPress version */
		$info[] = sprintf( esc_html__( 'WordPress Version: %s', 'brag-book-gallery' ), get_bloginfo( 'version' ) );
		/* translators: %s: yes or no */
		$info[] = sprintf( esc_html__( 'WordPress Multisite: %s', 'brag-book-gallery' ), ( is_multisite() ? esc_html__( 'Yes', 'brag-book-gallery' ) : esc_html__( 'No', 'brag-book-gallery' ) ) );
		/* translators: %s: memory limit */
		$info[] = sprintf( esc_html__( 'WordPress Memory Limit: %s', 'brag-book-gallery' ), WP_MEMORY_LIMIT );
		/* translators: %s: max memory limit */
		$info[] = sprintf( esc_html__( 'WordPress Max Memory Limit: %s', 'brag-book-gallery' ), ( defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'Not defined' ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'WordPress Debug Mode: %s', 'brag-book-gallery' ), ( defined( 'WP_DEBUG' ) && WP_DEBUG ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'WordPress Debug Display: %s', 'brag-book-gallery' ), ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'WordPress Debug Log: %s', 'brag-book-gallery' ), ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'Script Debug: %s', 'brag-book-gallery' ), ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: language locale */
		$info[] = sprintf( esc_html__( 'WordPress Language: %s', 'brag-book-gallery' ), get_locale() );
		/* translators: %s: timezone */
		$info[] = sprintf( esc_html__( 'WordPress Timezone: %s', 'brag-book-gallery' ), get_option( 'timezone_string' ) ?: 'UTC' . get_option( 'gmt_offset' ) );
		/* translators: %s: date format */
		$info[] = sprintf( esc_html__( 'Date Format: %s', 'brag-book-gallery' ), get_option( 'date_format' ) );
		/* translators: %s: time format */
		$info[] = sprintf( esc_html__( 'Time Format: %s', 'brag-book-gallery' ), get_option( 'time_format' ) );
		/* translators: %s: permalink structure */
		$info[] = sprintf( esc_html__( 'Permalink Structure: %s', 'brag-book-gallery' ), get_option( 'permalink_structure' ) ?: 'Plain' );
		/* translators: %s: ABSPATH */
		$info[] = sprintf( esc_html__( 'WordPress Root Path: %s', 'brag-book-gallery' ), ABSPATH );
		/* translators: %s: content directory */
		$info[] = sprintf( esc_html__( 'Content Directory: %s', 'brag-book-gallery' ), WP_CONTENT_DIR );
		/* translators: %s: plugin directory */
		$info[] = sprintf( esc_html__( 'Plugin Directory: %s', 'brag-book-gallery' ), WP_PLUGIN_DIR );
		/* translators: %s: uploads directory */
		$upload_dir = wp_upload_dir();
		$info[] = sprintf( esc_html__( 'Uploads Directory: %s', 'brag-book-gallery' ), $upload_dir['basedir'] );
		$info[] = '';

		// Server Information
		$info[] = esc_html__( '--- Server Information ---', 'brag-book-gallery' );
		/* translators: %s: server software */
		$info[] = sprintf( esc_html__( 'Server Software: %s', 'brag-book-gallery' ), ( $_SERVER['SERVER_SOFTWARE'] ?? esc_html__( 'Unknown', 'brag-book-gallery' ) ) );
		/* translators: %s: server name */
		$info[] = sprintf( esc_html__( 'Server Name: %s', 'brag-book-gallery' ), ( $_SERVER['SERVER_NAME'] ?? esc_html__( 'Unknown', 'brag-book-gallery' ) ) );
		/* translators: %s: server IP */
		$info[] = sprintf( esc_html__( 'Server IP: %s', 'brag-book-gallery' ), ( $_SERVER['SERVER_ADDR'] ?? esc_html__( 'Unknown', 'brag-book-gallery' ) ) );
		/* translators: %s: server port */
		$info[] = sprintf( esc_html__( 'Server Port: %s', 'brag-book-gallery' ), ( $_SERVER['SERVER_PORT'] ?? esc_html__( 'Unknown', 'brag-book-gallery' ) ) );
		/* translators: %s: server protocol */
		$info[] = sprintf( esc_html__( 'Server Protocol: %s', 'brag-book-gallery' ), ( $_SERVER['SERVER_PROTOCOL'] ?? esc_html__( 'Unknown', 'brag-book-gallery' ) ) );
		/* translators: %s: HTTPS status */
		$info[] = sprintf( esc_html__( 'HTTPS: %s', 'brag-book-gallery' ), ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? esc_html__( 'Yes', 'brag-book-gallery' ) : esc_html__( 'No', 'brag-book-gallery' ) ) );
		/* translators: %s: PHP version */
		$info[] = sprintf( esc_html__( 'PHP Version: %s', 'brag-book-gallery' ), phpversion() );
		/* translators: %s: PHP SAPI */
		$info[] = sprintf( esc_html__( 'PHP SAPI: %s', 'brag-book-gallery' ), php_sapi_name() );
		/* translators: %s: PHP user */
		$info[] = sprintf( esc_html__( 'PHP User: %s', 'brag-book-gallery' ), ( function_exists( 'get_current_user' ) ? get_current_user() : 'Unknown' ) );
		/* translators: %s: PHP memory limit */
		$info[] = sprintf( esc_html__( 'PHP Memory Limit: %s', 'brag-book-gallery' ), ini_get( 'memory_limit' ) );
		/* translators: %s: execution time in seconds */
		$info[] = sprintf( esc_html__( 'PHP Max Execution Time: %s seconds', 'brag-book-gallery' ), ini_get( 'max_execution_time' ) );
		/* translators: %s: max input time in seconds */
		$info[] = sprintf( esc_html__( 'PHP Max Input Time: %s seconds', 'brag-book-gallery' ), ini_get( 'max_input_time' ) );
		/* translators: %s: max input vars */
		$info[] = sprintf( esc_html__( 'PHP Max Input Vars: %s', 'brag-book-gallery' ), ini_get( 'max_input_vars' ) );
		/* translators: %s: post max size */
		$info[] = sprintf( esc_html__( 'PHP Post Max Size: %s', 'brag-book-gallery' ), ini_get( 'post_max_size' ) );
		/* translators: %s: upload max filesize */
		$info[] = sprintf( esc_html__( 'PHP Upload Max Filesize: %s', 'brag-book-gallery' ), ini_get( 'upload_max_filesize' ) );
		/* translators: %s: max file uploads */
		$info[] = sprintf( esc_html__( 'PHP Max File Uploads: %s', 'brag-book-gallery' ), ini_get( 'max_file_uploads' ) );
		/* translators: %s: cURL status and version */
		$curl_status = function_exists( 'curl_version' )
			? sprintf( esc_html__( 'Enabled (v%s)', 'brag-book-gallery' ), curl_version()['version'] )
			: esc_html__( 'Disabled', 'brag-book-gallery' );
		$info[]      = sprintf( esc_html__( 'PHP cURL: %s', 'brag-book-gallery' ), $curl_status );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'PHP GD: %s', 'brag-book-gallery' ), ( extension_loaded( 'gd' ) ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'PHP JSON: %s', 'brag-book-gallery' ), ( extension_loaded( 'json' ) ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'PHP XML: %s', 'brag-book-gallery' ), ( extension_loaded( 'xml' ) ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'PHP Mbstring: %s', 'brag-book-gallery' ), ( extension_loaded( 'mbstring' ) ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		/* translators: %s: enabled or disabled status */
		$info[] = sprintf( esc_html__( 'PHP OpenSSL: %s', 'brag-book-gallery' ), ( extension_loaded( 'openssl' ) ? esc_html__( 'Enabled', 'brag-book-gallery' ) : esc_html__( 'Disabled', 'brag-book-gallery' ) ) );
		$info[] = '';

		// Browser Information (from user agent)
		$info[] = esc_html__( '--- Browser Information ---', 'brag-book-gallery' );
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? esc_html__( 'Unknown', 'brag-book-gallery' );
		$info[] = sprintf( esc_html__( 'User Agent: %s', 'brag-book-gallery' ), $user_agent );

		// Parse browser details
		$browser = $this->get_browser_info( $user_agent );
		$info[] = sprintf( esc_html__( 'Browser: %s', 'brag-book-gallery' ), $browser['name'] );
		$info[] = sprintf( esc_html__( 'Browser Version: %s', 'brag-book-gallery' ), $browser['version'] );
		$info[] = sprintf( esc_html__( 'Operating System: %s', 'brag-book-gallery' ), $browser['os'] );
		$info[] = sprintf( esc_html__( 'Platform: %s', 'brag-book-gallery' ), $browser['platform'] );
		$info[] = '';

		// Database Information
		$info[] = esc_html__( '--- Database Information ---', 'brag-book-gallery' );
		/* translators: %s: database version */
		$info[] = sprintf( esc_html__( 'Database Version: %s', 'brag-book-gallery' ), $wpdb->db_version() );
		/* translators: %s: database prefix */
		$info[] = sprintf( esc_html__( 'Database Prefix: %s', 'brag-book-gallery' ), $wpdb->prefix );
		/* translators: %s: database charset */
		$info[] = sprintf( esc_html__( 'Database Charset: %s', 'brag-book-gallery' ), $wpdb->charset );
		/* translators: %s: database collation */
		$info[] = sprintf( esc_html__( 'Database Collation: %s', 'brag-book-gallery' ), $wpdb->collate );
		$info[] = '';

		// Theme Information
		$info[] = esc_html__( '--- Theme Information ---', 'brag-book-gallery' );
		/* translators: %s: theme name */
		$info[] = sprintf( esc_html__( 'Active Theme: %s', 'brag-book-gallery' ), $theme->get( 'Name' ) );
		/* translators: %s: theme version */
		$info[] = sprintf( esc_html__( 'Theme Version: %s', 'brag-book-gallery' ), $theme->get( 'Version' ) );
		/* translators: %s: theme author */
		$info[] = sprintf( esc_html__( 'Theme Author: %s', 'brag-book-gallery' ), $theme->get( 'Author' ) );
		/* translators: %s: theme URI */
		$info[] = sprintf( esc_html__( 'Theme URI: %s', 'brag-book-gallery' ), $theme->get( 'ThemeURI' ) );
		/* translators: %s: parent theme name or none */
		$info[] = sprintf( esc_html__( 'Parent Theme: %s', 'brag-book-gallery' ), ( $theme->parent() ? $theme->parent()->get( 'Name' ) : esc_html__( 'None', 'brag-book-gallery' ) ) );
		$info[] = '';

		// Active Plugins
		$info[] = esc_html__( '--- Active Plugins ---', 'brag-book-gallery' );
		foreach ( $active_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			/* translators: 1: plugin name, 2: version, 3: author */
			$info[] = sprintf( esc_html__( '%1$s (v%2$s) by %3$s', 'brag-book-gallery' ), $plugin_data['Name'], $plugin_data['Version'], $plugin_data['Author'] );
		}
		$info[] = '';

		// BRAG Book Specific
		$info[]        = '--- BRAG Book Configuration ---';
		$gallery_pages = get_option( 'brag_book_gallery_gallery_page_slug', [] );
		$info[]        = 'Gallery Pages: ' . count( $gallery_pages );

		// List gallery page URLs
		if ( ! empty( $gallery_pages ) ) {
			$info[] = esc_html__( 'Gallery Page Links:', 'brag-book-gallery' );
			foreach ( $gallery_pages as $index => $slug ) {
				$page_url = get_site_url() . '/' . $slug;
				/* translators: 1: gallery number, 2: page URL */
				$info[] = sprintf( esc_html__( '  - Gallery %1$d: %2$s', 'brag-book-gallery' ), ( $index + 1 ), $page_url );
			}
		}

		$combine_slug = get_option( 'combine_gallery_slug', '' );
		if ( $combine_slug ) {
			/* translators: %s: combined gallery URL */
			$info[] = sprintf( esc_html__( 'Combined Gallery: %s', 'brag-book-gallery' ), get_site_url() . '/' . $combine_slug );
		} else {
			$info[] = esc_html__( 'Combined Gallery: Disabled', 'brag-book-gallery' );
		}

		$info[] = 'SEO Plugin: ' . $this->detect_seo_plugin()['name'];

		// Safely get consultation count
		$consultation_count = 0;
		$consultation_posts = wp_count_posts( 'form-entries' );
		if ( is_object( $consultation_posts ) && property_exists( $consultation_posts, 'publish' ) ) {
			$consultation_count = $consultation_posts->publish;
		}
		$info[] = 'Consultation Entries: ' . $consultation_count;

		// Check API connectivity
		if ( ! empty( $api_tokens ) ) {
			$info[] = '';
			$info[] = esc_html__( '--- API Status ---', 'brag-book-gallery' );
			foreach ( $api_tokens as $index => $token ) {
				$website_property_id = get_option( 'brag_book_gallery_website_property_id', [] )[ $index ] ?? '';
				if ( $token && $website_property_id ) {
					$validation = $this->validate_api_credentials( $token, $website_property_id );
					/* translators: 1: connection number, 2: status message */
					$status = $validation['valid']
						? esc_html__( 'Connected', 'brag-book-gallery' )
						: sprintf( esc_html__( 'Failed - %s', 'brag-book-gallery' ), $validation['message'] );
					$info[] = sprintf( esc_html__( 'Connection %1$d: %2$s', 'brag-book-gallery' ), ( $index + 1 ), $status );
				}
			}
		}

		$info[] = '';
		$info[] = '==========================================';
		$info[] = '         END OF SYSTEM REPORT';
		$info[] = '==========================================';

		return implode( "\n", $info );
	}

	/**
	 * Parse browser information from user agent string
	 *
	 * @param string $user_agent User agent string.
	 * @return array Browser information.
	 * @since 3.0.0
	 */
	private function get_browser_info( string $user_agent ): array {
		$browser = array(
			'name'     => 'Unknown',
			'version'  => 'Unknown',
			'os'       => 'Unknown',
			'platform' => 'Unknown',
		);

		// Detect browser name and version
		if ( preg_match( '/MSIE/i', $user_agent ) && ! preg_match( '/Opera/i', $user_agent ) ) {
			$browser['name'] = 'Internet Explorer';
			$browser['version'] = preg_match( '/MSIE\s([0-9.]+)/', $user_agent, $match ) ? $match[1] : 'Unknown';
		} elseif ( preg_match( '/Trident/i', $user_agent ) ) {
			$browser['name'] = 'Internet Explorer';
			$browser['version'] = preg_match( '/rv:([0-9.]+)/', $user_agent, $match ) ? $match[1] : 'Unknown';
		} elseif ( preg_match( '/Firefox/i', $user_agent ) ) {
			$browser['name'] = 'Firefox';
			$browser['version'] = preg_match( '/Firefox\/([0-9.]+)/', $user_agent, $match ) ? $match[1] : 'Unknown';
		} elseif ( preg_match( '/Chrome/i', $user_agent ) && ! preg_match( '/Edge/i', $user_agent ) ) {
			$browser['name'] = 'Chrome';
			$browser['version'] = preg_match( '/Chrome\/([0-9.]+)/', $user_agent, $match ) ? $match[1] : 'Unknown';
		} elseif ( preg_match( '/Safari/i', $user_agent ) && ! preg_match( '/Chrome/i', $user_agent ) ) {
			$browser['name'] = 'Safari';
			$browser['version'] = preg_match( '/Version\/([0-9.]+)/', $user_agent, $match ) ? $match[1] : 'Unknown';
		} elseif ( preg_match( '/Opera/i', $user_agent ) ) {
			$browser['name'] = 'Opera';
			$browser['version'] = preg_match( '/Opera\/([0-9.]+)/', $user_agent, $match ) ? $match[1] : 'Unknown';
		} elseif ( preg_match( '/Edge/i', $user_agent ) ) {
			$browser['name'] = 'Microsoft Edge';
			$browser['version'] = preg_match( '/Edge\/([0-9.]+)/', $user_agent, $match ) ? $match[1] : 'Unknown';
		}

		// Detect operating system
		if ( preg_match( '/Windows NT 10/i', $user_agent ) ) {
			$browser['os'] = 'Windows 10';
		} elseif ( preg_match( '/Windows NT 11/i', $user_agent ) ) {
			$browser['os'] = 'Windows 11';
		} elseif ( preg_match( '/Windows NT 6.3/i', $user_agent ) ) {
			$browser['os'] = 'Windows 8.1';
		} elseif ( preg_match( '/Windows NT 6.2/i', $user_agent ) ) {
			$browser['os'] = 'Windows 8';
		} elseif ( preg_match( '/Windows NT 6.1/i', $user_agent ) ) {
			$browser['os'] = 'Windows 7';
		} elseif ( preg_match( '/Mac OS X/i', $user_agent ) ) {
			$browser['os'] = 'macOS';
			if ( preg_match( '/Mac OS X ([0-9_]+)/', $user_agent, $match ) ) {
				$version = str_replace( '_', '.', $match[1] );
				$browser['os'] = 'macOS ' . $version;
			}
		} elseif ( preg_match( '/Linux/i', $user_agent ) ) {
			$browser['os'] = 'Linux';
		} elseif ( preg_match( '/iPhone/i', $user_agent ) ) {
			$browser['os'] = 'iOS';
		} elseif ( preg_match( '/iPad/i', $user_agent ) ) {
			$browser['os'] = 'iPadOS';
		} elseif ( preg_match( '/Android/i', $user_agent ) ) {
			$browser['os'] = 'Android';
		}

		// Detect platform
		if ( preg_match( '/Mobile/i', $user_agent ) ) {
			$browser['platform'] = 'Mobile';
		} elseif ( preg_match( '/Tablet/i', $user_agent ) || preg_match( '/iPad/i', $user_agent ) ) {
			$browser['platform'] = 'Tablet';
		} else {
			$browser['platform'] = 'Desktop';
		}

		return $browser;
	}

	/**
	 * Get log file path
	 *
	 * @return string Log file path.
	 * @since 3.0.0
	 */
	private function get_log_file_path( string $type = 'error' ): string {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		// Create directory if it doesn't exist
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Add .htaccess to protect log files
			$htaccess = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, 'Deny from all' );
			}
		}

		$filename = ( 'api' === $type ) ? 'api.log' : 'error.log';

		return $log_dir . '/' . $filename;
	}

	/**
	 * Read last N lines from file
	 *
	 * @param string $file File path.
	 * @param int $lines Number of lines.
	 *
	 * @return array Lines from file.
	 * @since 3.0.0
	 */
	private function tail_file( string $file, int $lines = 100 ): array {
		$data = [];
		$fp   = fopen( $file, 'r' );

		if ( ! $fp ) {
			return $data;
		}

		$block = 4096;
		$max   = filesize( $file );

		for ( $len = 0; $len < $max; $len += $block ) {
			$seekSize = ( $max - $len < $block ) ? $max - $len : $block;
			fseek( $fp, - $seekSize, SEEK_END );
			$data = explode( "\n", fread( $fp, $seekSize ) . implode( "\n", $data ) );

			if ( count( $data ) >= $lines + 1 ) {
				break;
			}
		}

		fclose( $fp );

		return array_slice( $data, - $lines );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Error message.
	 * @param string $level Log level (error, warning, info, debug).
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function log_error( string $message, string $level = 'error' ): void {
		$debug_mode = get_option( 'brag_book_gallery_debug_mode', false );
		$log_level  = get_option( 'brag_book_gallery_log_level', 'error' );

		// Check if we should log this level
		$levels        = [
			'debug'   => 0,
			'info'    => 1,
			'warning' => 2,
			'error'   => 3
		];
		$current_level = $levels[ $log_level ] ?? 3;
		$message_level = $levels[ $level ] ?? 3;

		if ( $message_level < $current_level ) {
			return;
		}

		// Get log file path
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
		}

		$log_file = $log_dir . '/error.log';

		// Format message
		$timestamp = current_time( 'Y-m-d H:i:s' );
		/* translators: 1: timestamp, 2: log level, 3: message */
		$formatted_message = sprintf(
			"[%s] [%s] %s\n",
			$timestamp,
			strtoupper( $level ),
			$message
		);

		// Write to log
		error_log( $formatted_message, 3, $log_file );

		// Rotate log if it's too large (> 10MB)
		if ( file_exists( $log_file ) && filesize( $log_file ) > 10 * MB_IN_BYTES ) {
			$backup = $log_file . '.' . date( 'Y-m-d-His' );
			rename( $log_file, $backup );

			// Keep only last 5 backups
			$backups = glob( $log_dir . '/error.log.*' );
			if ( count( $backups ) > 5 ) {
				$old_backups = array_slice( $backups, 0, - 5 );
				foreach ( $old_backups as $old_backup ) {
					unlink( $old_backup );
				}
			}
		}
	}

	/**
	 * Log API request and response
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array $request Request data.
	 * @param array|WP_Error $response Response data.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function log_api( string $endpoint, string $method, array $request, array|WP_Error $response ): void {

		// Check if API logging is enabled.
		if ( ! get_option( 'brag_book_gallery_api_logging', false ) ) {
			return;
		}

		// Get log file path
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
		}

		$log_file = $log_dir . '/api.log';

		// Format message
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$status    = is_wp_error( $response ) ? 'ERROR' : 'SUCCESS';

		// Sanitize request data (remove sensitive info)
		if ( isset( $request['apiToken'] ) ) {
			$request['apiToken'] = substr( $request['apiToken'], 0, 8 ) . '...';
		}
		if ( isset( $request['password'] ) ) {
			$request['password'] = '***';
		}

		/* translators: 1: timestamp, 2: status, 3: method, 4: endpoint, 5: request data, 6: response data */
		$formatted_message = sprintf(
			"[%s] [%s] %s %s\n" . esc_html__( 'Request:', 'brag-book-gallery' ) . " %s\n" . esc_html__( 'Response:', 'brag-book-gallery' ) . " %s\n---\n",
			$timestamp,
			$status,
			$method,
			$endpoint,
			wp_json_encode( $request, JSON_PRETTY_PRINT ),
			is_wp_error( $response ) ? $response->get_error_message() : wp_json_encode( $response, JSON_PRETTY_PRINT )
		);

		// Write to log
		error_log( $formatted_message, 3, $log_file );

		// Rotate log if it's too large (> 10MB)
		if ( file_exists( $log_file ) && filesize( $log_file ) > 10 * MB_IN_BYTES ) {
			$backup = $log_file . '.' . date( 'Y-m-d-His' );
			rename( $log_file, $backup );

			// Keep only last 5 backups
			$backups = glob( $log_dir . '/api.log.*' );
			if ( count( $backups ) > 5 ) {
				$old_backups = array_slice( $backups, 0, - 5 );
				foreach ( $old_backups as $old_backup ) {
					unlink( $old_backup );
				}
			}
		}
	}

	/**
	 * Handle export system info AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_export_system_info(): void {
		// Verify nonce
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'brag_book_gallery_debug_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$system_info = $this->get_system_info();
		$filename    = 'brag-book-gallery-system-info-' . date( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $system_info ) );

		echo $system_info;
		exit;
	}

	/**
	 * Handle download error log AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_download_error_log(): void {
		// Verify nonce
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'brag_book_gallery_debug_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$log_file = $this->get_log_file_path();

		if ( ! file_exists( $log_file ) ) {
			wp_die( __( 'No error log found.', 'brag-book-gallery' ) );
		}

		$filename = 'brag-book-gallery-error-log-' . date( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );

		readfile( $log_file );
		exit;
	}

	/**
	 * Handle save settings AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_save_settings(): void {
		// Basic sanity checks
		if ( ! function_exists( 'wp_send_json_error' ) || ! function_exists( 'wp_send_json_success' ) ) {
			wp_die( esc_html__( 'WordPress AJAX functions not available', 'brag-book-gallery' ) );
		}

		try {
			// Verify nonce
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'brag_book_gallery_settings_nonce' ) ) {
				wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );

				return;
			}

			// Check capability
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );

				return;
			}

			// Parse form data
			if ( ! isset( $_POST['form_data'] ) ) {
				wp_send_json_error( __( 'No form data received.', 'brag-book-gallery' ) );

				return;
			}

			parse_str( $_POST['form_data'], $form_data );

			// Update general settings
			update_option( 'brag_book_gallery_seo_plugin_selector', sanitize_text_field( $form_data['brag_book_gallery_seo_plugin_selector'] ?? '0' ) );
			// Clean escaped quotes from WYSIWYG editor before saving
			$landing_text = $form_data['brag_book_gallery_landing_page_text'] ?? '';
			$landing_text = str_replace( '\"', '"', $landing_text );
			$landing_text = str_replace( "\'", "'", $landing_text );
			$landing_text = stripslashes( $landing_text );
			update_option( 'brag_book_gallery_landing_page_text', wp_kses_post( $landing_text ) );
			// Handle checkbox for image display mode
			$image_mode = isset( $form_data['brag_book_gallery_image_display_mode'] ) && $form_data['brag_book_gallery_image_display_mode'] === 'before_after' ? 'before_after' : 'single';
			update_option( 'brag_book_gallery_image_display_mode', $image_mode );
			update_option( 'combine_gallery_slug', sanitize_title( $form_data['combine_gallery_slug'] ?? '' ) );
			update_option( 'brag_book_gallery_combine_seo_page_title', sanitize_text_field( $form_data['brag_book_gallery_combine_seo_page_title'] ?? '' ) );
			update_option( 'brag_book_gallery_combine_seo_page_description', sanitize_textarea_field( $form_data['brag_book_gallery_combine_seo_page_description'] ?? '' ) );

			// Create combined gallery page if needed
			$combine_slug = $form_data['combine_gallery_slug'] ?? '';
			if ( ! empty( $combine_slug ) && ! get_page_by_path( $combine_slug ) ) {
				$page_id = wp_insert_post( [
					'post_title'   => ucwords( str_replace( '-', ' ', $combine_slug ) ),
					'post_name'    => $combine_slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '[brag_book_gallery_gallery combine="true"]',
				] );
				update_option( 'combine_gallery_page_id', $page_id );
			}

			// Clear cache
			$this->clear_plugin_cache();

			// Generate sitemap if needed (temporarily disabled for debugging)
			// if ( class_exists( 'BRAGBookGallery\Includes\SEO\Sitemap' ) ) {
			//	$sitemap = new Sitemap();
			//	$sitemap->generate();
			// }

			wp_send_json_success( __( 'Settings saved successfully.', 'brag-book-gallery' ) );

		} catch ( Exception $e ) {
			/* translators: 1: error message, 2: file name, 3: line number */
			error_log( sprintf( __( 'BRAG Book Settings Save Error: %1$s in %2$s on line %3$s', 'brag-book-gallery' ), $e->getMessage(), $e->getFile(), $e->getLine() ) );
			/* translators: 1: error message, 2: line number */
			wp_send_json_error( sprintf( __( 'Error: %1$s (Line: %2$s)', 'brag-book-gallery' ), $e->getMessage(), $e->getLine() ) );
		} catch ( Error $e ) {
			/* translators: 1: error message, 2: file name, 3: line number */
			error_log( sprintf( __( 'BRAG Book Settings Fatal Error: %1$s in %2$s on line %3$s', 'brag-book-gallery' ), $e->getMessage(), $e->getFile(), $e->getLine() ) );
			/* translators: 1: error message, 2: line number */
			wp_send_json_error( sprintf( __( 'Fatal Error: %1$s (Line: %2$s)', 'brag-book-gallery' ), $e->getMessage(), $e->getLine() ) );
		}
	}

	/**
	 * Handle remove row AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_remove_row(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$remove_id = isset( $_POST['brag_book_gallery_remove_id'] ) ? (int) $_POST['brag_book_gallery_remove_id'] : - 1;

		if ( $remove_id < 0 ) {
			wp_send_json_error( __( 'Invalid row ID.', 'brag-book-gallery' ) );
		}

		// Get current options
		$api_tokens   = get_option( 'brag_book_gallery_api_token', [] );
		$website_ids  = get_option( 'brag_book_gallery_website_property_id', [] );
		$slugs        = get_option( 'brag_book_gallery_gallery_page_slug', [] );
		$titles       = get_option( 'brag_book_gallery_seo_page_title', [] );
		$descriptions = get_option( 'brag_book_gallery_seo_page_description', [] );

		// Remove the specified index
		unset( $api_tokens[ $remove_id ] );
		unset( $website_ids[ $remove_id ] );
		unset( $slugs[ $remove_id ] );
		unset( $titles[ $remove_id ] );
		unset( $descriptions[ $remove_id ] );

		// Re-index arrays
		$api_tokens   = array_values( $api_tokens );
		$website_ids  = array_values( $website_ids );
		$slugs        = array_values( $slugs );
		$titles       = array_values( $titles );
		$descriptions = array_values( $descriptions );

		// Update options
		update_option( 'brag_book_gallery_api_token', $api_tokens );
		update_option( 'brag_book_gallery_website_property_id', $website_ids );
		update_option( 'brag_book_gallery_gallery_page_slug', $slugs );
		update_option( 'brag_book_gallery_seo_page_title', $titles );
		update_option( 'brag_book_gallery_seo_page_description', $descriptions );

		wp_send_json_success( __( 'Row removed successfully.', 'brag-book-gallery' ) );
	}

	/**
	 * Handle update API cache request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_update_api(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		// Clear all transients
		$this->clear_plugin_cache();

		// Regenerate sitemap
		if ( class_exists( 'BRAGBookGallery\Includes\Seo\Sitemap' ) ) {
			$sitemap = new Sitemap();
			$sitemap->generate();
		}

		wp_send_json_success( __( 'API cache updated successfully.', 'brag-book-gallery' ) );
	}

	/**
	 * Handle get error log AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_get_error_log(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$log_content = $this->get_error_log();
		wp_send_json_success( [ 'log' => $log_content ] );
	}

	/**
	 * Handle save debug settings AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_save_debug_settings(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		// Get and sanitize settings
		$debug_mode  = isset( $_POST['debug_mode'] ) && $_POST['debug_mode'] === '1';
		$api_logging = isset( $_POST['api_logging'] ) && $_POST['api_logging'] === '1';
		$wp_debug    = isset( $_POST['wp_debug'] ) && $_POST['wp_debug'] === '1';
		$log_level   = sanitize_text_field( $_POST['log_level'] ?? 'error' );

		// Validate log level
		$valid_levels = [ 'error', 'warning', 'info', 'debug' ];
		if ( ! in_array( $log_level, $valid_levels, true ) ) {
			$log_level = 'error';
		}

		// Save settings
		update_option( 'brag_book_gallery_debug_mode', $debug_mode );
		update_option( 'brag_book_gallery_api_logging', $api_logging );
		update_option( 'brag_book_gallery_log_level', $log_level );

		// Handle WP_DEBUG toggle if wp-config.php is writable
		$wp_debug_updated = false;
		$wp_config_path   = ABSPATH . 'wp-config.php';
		if ( is_writable( $wp_config_path ) ) {
			$config_content = file_get_contents( $wp_config_path );
			if ( $config_content !== false ) {
				$wp_debug_value = $wp_debug ? 'true' : 'false';
				$pattern        = "/define\s*\(\s*['\"]WP_DEBUG['\"],\s*(true|false)\s*\)/i";
				$replacement    = "define( 'WP_DEBUG', $wp_debug_value )";

				if ( preg_match( $pattern, $config_content ) ) {
					$new_content = preg_replace( $pattern, $replacement, $config_content );
					if ( $new_content !== $config_content ) {
						file_put_contents( $wp_config_path, $new_content );
						$wp_debug_updated = true;
					}
				}
			}
		}

		// Log a test message when debug mode is enabled
		if ( $debug_mode ) {
			self::log_error( 'Debug mode enabled - Test log entry', 'info' );
		}

		// Log a test API message when API logging is enabled
		if ( $api_logging ) {
			self::log_api( '/test/endpoint', 'GET', [ 'test' => 'data' ], [
				'status'  => 'success',
				'message' => esc_html__( 'API logging enabled', 'brag-book-gallery' )
			] );
		}

		// Build success message
		$messages   = [];
		$messages[] = __( 'Debug settings saved successfully.', 'brag-book-gallery' );

		if ( $wp_debug_updated ) {
			$messages[] = $wp_debug ?
				__( 'WP_DEBUG has been enabled in wp-config.php', 'brag-book-gallery' ) :
				__( 'WP_DEBUG has been disabled in wp-config.php', 'brag-book-gallery' );
		}

		wp_send_json_success( [ 'message' => implode( ' ', $messages ) ] );
	}

	/**
	 * Handle clear error log AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_clear_error_log(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$log_file = $this->get_log_file_path();

		// Clear the log file
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
			wp_send_json_success( [ 'message' => __( 'Error log cleared successfully.', 'brag-book-gallery' ) ] );
		} else {
			// Create empty log file
			file_put_contents( $log_file, '' );
			wp_send_json_success( [ 'message' => __( 'Error log cleared.', 'brag-book-gallery' ) ] );
		}
	}

	/**
	 * Handle get API log AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_get_api_log(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$log_content = $this->get_api_log();
		wp_send_json_success( [ 'log' => $log_content ] );
	}

	/**
	 * Handle clear API log AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_clear_api_log(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$log_file = $this->get_log_file_path( 'api' );

		// Clear the log file
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
			wp_send_json_success( [ 'message' => __( 'API log cleared successfully.', 'brag-book-gallery' ) ] );
		} else {
			// Create empty log file
			file_put_contents( $log_file, '' );
			wp_send_json_success( [ 'message' => __( 'API log cleared.', 'brag-book-gallery' ) ] );
		}
	}

	/**
	 * Handle download API log AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_download_api_log(): void {
		// Verify nonce
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'brag_book_gallery_debug_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$log_file = $this->get_log_file_path( 'api' );

		if ( ! file_exists( $log_file ) ) {
			wp_die( __( 'No API log found.', 'brag-book-gallery' ) );
		}

		$filename = 'brag-book-gallery-api-log-' . date( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );

		readfile( $log_file );
		exit;
	}

	/**
	 * Handle page slug update
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_page_slug_update( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'page' ) {
			return;
		}

		$new_slug      = get_post_field( 'post_name', $post_id );
		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

		// Check if this page is in our gallery pages
		$page_ids = get_option( 'brag_book_gallery_gallery_stored_pages_ids', [] );
		$index    = array_search( $post_id, $page_ids );

		if ( $index !== false && isset( $gallery_slugs[ $index ] ) ) {
			$gallery_slugs[ $index ] = $new_slug;
			update_option( 'brag_book_gallery_gallery_page_slug', $gallery_slugs );
		}

		// Check if it's the combined gallery page
		$combine_page_id = get_option( 'combine_gallery_page_id' );
		if ( $post_id === $combine_page_id ) {
			update_option( 'combine_gallery_slug', $new_slug );
		}
	}

	/**
	 * Handle page trash
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_page_trash( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'page' ) {
			return;
		}

		// Check if it's the combined gallery page
		$combine_page_id = get_option( 'combine_gallery_page_id' );
		if ( $post_id === $combine_page_id ) {
			update_option( 'combine_gallery_slug', '' );
		}
	}

	/**
	 * Handle page permanent delete
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_page_delete( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'page' ) {
			return;
		}

		// Remove from gallery pages if present
		$page_ids = get_option( 'brag_book_gallery_gallery_stored_pages_ids', [] );
		$index    = array_search( $post_id, $page_ids );

		if ( $index !== false ) {
			// Get all related options
			$api_tokens   = get_option( 'brag_book_gallery_api_token', [] );
			$website_ids  = get_option( 'brag_book_gallery_website_property_id', [] );
			$slugs        = get_option( 'brag_book_gallery_gallery_page_slug', [] );
			$titles       = get_option( 'brag_book_gallery_seo_page_title', [] );
			$descriptions = get_option( 'brag_book_gallery_seo_page_description', [] );

			// Remove this index
			unset( $api_tokens[ $index ] );
			unset( $website_ids[ $index ] );
			unset( $slugs[ $index ] );
			unset( $titles[ $index ] );
			unset( $descriptions[ $index ] );
			unset( $page_ids[ $index ] );

			// Re-index and update
			update_option( 'brag_book_gallery_api_token', array_values( $api_tokens ) );
			update_option( 'brag_book_gallery_website_property_id', array_values( $website_ids ) );
			update_option( 'brag_book_gallery_gallery_page_slug', array_values( $slugs ) );
			update_option( 'brag_book_gallery_seo_page_title', array_values( $titles ) );
			update_option( 'brag_book_gallery_seo_page_description', array_values( $descriptions ) );
			update_option( 'brag_book_gallery_gallery_stored_pages_ids', array_values( $page_ids ) );
		}

		// Check if it's the combined gallery page
		$combine_page_id = get_option( 'combine_gallery_page_id' );
		if ( $post_id === $combine_page_id ) {
			delete_option( 'combine_gallery_page_id' );
			delete_option( 'combine_gallery_slug' );
		}
	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function display_admin_notices(): void {

		// Check if we need to show setup notice.
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );

		if ( empty( $api_tokens ) && current_user_can( 'manage_options' ) ) {

			$screen = get_current_screen();

			if ( $screen && strpos( $screen->id, 'brag-book-gallery' ) === false ) {
				?>
					<div class="notice notice-warning">
						<p>
							<?php
							printf(
							/* translators: %s: settings page link */
								esc_html__( 'BRAG Book Gallery is not configured. Please %s to get started.', 'brag-book-gallery' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ) . '">' .
								esc_html__( 'configure your settings', 'brag-book-gallery' ) . '</a>'
							);
							?>
						</p>
					</div>
				<?php
			}
		}
	}

	/**
	 * Clear plugin cache
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function clear_plugin_cache(): void {
		global $wpdb;

		// Clear BRAG Book Gallery transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s
					OR option_name LIKE %s
					OR option_name LIKE %s
					OR option_name LIKE %s
					OR option_name LIKE %s",
				'_transient_brag_book_gallery_%',
				'_transient_timeout_brag_book_gallery_%',
				'_transient_filters_%',
				'_transient_timeout_filters_%',
				'_transient_cases_%',
				'_transient_timeout_cases_%'
			)
		);

		// Clear object cache.
		wp_cache_flush();

		// Fire action hook after cache is cleared.
		do_action( 'brag_book_gallery_api_cache_cleared', 'all', true );
	}

	/**
	 * Get API handler instance
	 *
	 * @return Api_Handler
	 * @since 3.0.0
	 */
	private function get_api_handler(): Api_Handler {
		if ( null === $this->api_handler ) {
			$this->api_handler = new Api_Handler();
		}

		return $this->api_handler;
	}

	/**
	 * Render unified navigation tabs
	 *
	 * @param string $current_page Current page slug.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	protected function render_navigation( string $current_page = '' ): void {
		$tabs = array(
			'general'    => array(
				'label' => __( 'General', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'slug'  => 'brag-book-gallery-settings',
			),
			'mode'       => array(
				'label' => __( 'Mode', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-mode' ),
				'slug'  => 'brag-book-gallery-mode',
			),
			'javascript' => array(
				'label' => __( 'JavaScript Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-javascript' ),
				'slug'  => 'brag-book-gallery-javascript',
			),
			'local'      => array(
				'label' => __( 'Local Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-local' ),
				'slug'  => 'brag-book-gallery-local',
			),
			'api'        => array(
				'label' => __( 'API', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'slug'  => 'brag-book-gallery-api-settings',
			),
			'help'       => array(
				'label' => __( 'Help', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
				'slug'  => 'brag-book-gallery-help',
			),
			'debug'      => array(
				'label' => __( 'Debug', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
				'slug'  => 'brag-book-gallery-debug',
			),
		);
		?>
		<nav class="nav-tab-wrapper wp-clearfix">
			<?php
			foreach ( $tabs as $tab ) {
				$class = ( $tab['slug'] === $current_page ) ? 'nav-tab nav-tab-active' : 'nav-tab';
				printf(
					'<a href="%s" class="%s">%s</a>',
					esc_url( $tab['url'] ),
					esc_attr( $class ),
					esc_html( $tab['label'] )
				);
			}
			?>
		</nav>
		<?php
	}

	/**
	 * Render navigation tabs (legacy - for backwards compatibility)
	 *
	 * @param string $current Current tab.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function render_tabs( string $current = 'settings' ): void {
		$tabs = array(
			'settings'      => array(
				'title' => __( 'General Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'api'           => array(
				'title' => __( 'API Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'icon'  => 'dashicons-admin-network',
			),
			'consultations' => array(
				'title' => __( 'Consultations', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-consultation' ),
				'icon'  => 'dashicons-forms',
			),
			'help'          => array(
				'title' => __( 'Help', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
				'icon'  => 'dashicons-sos',
			),
			'debug'         => array(
				'title' => __( 'Debug', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
				'icon'  => 'dashicons-admin-tools',
			),
		);

		// Check API configuration status
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$has_api = ! empty( $api_tokens );
		?>
		<nav class="brag-book-gallery-tabs">
			<ul class="brag-book-gallery-tab-list">
				<?php foreach ( $tabs as $key => $tab ) : ?>
				<li class="brag-book-gallery-tab-item <?php echo $current === $key ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( $tab['url'] ); ?>"
					   class="brag-book-gallery-tab-link">
									<span
										class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<span
							class="brag-book-gallery-tab-title"><?php echo esc_html( $tab['title'] ); ?></span>
						<?php if ( $key === 'api' && ! $has_api ) : ?>
						<span
							class="brag-book-gallery-tab-badge">!</span>
						<?php endif; ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Detect installed SEO plugin
	 *
	 * @return array SEO plugin info
	 * @since 3.0.0
	 */
	private function detect_seo_plugin(): array {
		$seo_plugin = [
			'id'       => '0',
			'name'     => __( 'None', 'brag-book-gallery' ),
			'detected' => false,
		];

		// Check for Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			$seo_plugin = [
				'id'       => '1',
				'name'     => 'Yoast SEO',
				'detected' => true,
				'version'  => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : 'Unknown',
			];
		} // Check for All in One SEO
		elseif ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' ) ) {
			$seo_plugin = [
				'id'       => '2',
				'name'     => 'All in One SEO',
				'detected' => true,
				'version'  => defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : 'Unknown',
			];
		} // Check for Rank Math
		elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$seo_plugin = [
				'id'       => '3',
				'name'     => 'Rank Math',
				'detected' => true,
				'version'  => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : 'Unknown',
			];
		} // Check for SEOPress
		elseif ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_activation' ) ) {
			$seo_plugin = [
				'id'       => '4',
				'name'     => 'SEOPress',
				'detected' => true,
				'version'  => defined( 'SEOPRESS_VERSION' ) ? SEOPRESS_VERSION : 'Unknown',
			];
		}

		// Store detected plugin
		if ( $seo_plugin['detected'] ) {
			update_option( 'brag_book_gallery_seo_plugin_selector', $seo_plugin['id'] );
		}

		return $seo_plugin;
	}

	/**
	 * Enqueue admin styles
	 *
	 * @param string $hook Admin page hook
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function enqueue_admin_styles( string $hook ): void {
		// Only load on BRAG Book pages
		if ( ! str_contains( $hook, 'brag-book-gallery' ) ) {
			return;
		}

		// Enqueue custom admin styles
		wp_enqueue_style(
			'brag-book-gallery-admin-branding',
			plugins_url( 'assets/css/brag-book-gallery-admin.css', dirname( __DIR__ ) ),
			[],
			'3.0.0'
		);

		// Enqueue admin scripts
		wp_enqueue_script(
			'brag-book-gallery-admin-scripts',
			plugins_url( 'assets/js/brag-book-gallery-admin.js', dirname( __DIR__ ) ),
			[],
			'3.0.0',
			true
		);

		// Localize script with AJAX data
		wp_localize_script( 'brag-book-gallery-admin-scripts', 'brag_book_gallery_ajax', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'brag_book_gallery_settings_nonce' ),
		] );
	}

	/**
	 * Log debug information
	 *
	 * @param string $context Context or title for the log entry.
	 * @param mixed $data Data to log.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function log_debug( string $context, $data ): void {
		$debug_mode = get_option( 'brag_book_gallery_debug_mode', false );
		if ( ! $debug_mode ) {
			return;
		}

		$message = sprintf( '[%s] %s', $context, wp_json_encode( $data, JSON_PRETTY_PRINT ) );
		self::log_error( $message, 'debug' );
	}

	/**
	 * Validate API credentials
	 *
	 * @param string $api_token API token
	 * @param string $website_property_id Website property ID
	 *
	 * @return array Validation result
	 * @since 3.0.0
	 */
	private function validate_api_credentials( string $api_token, string $website_property_id ): array {
		if ( empty( $api_token ) || empty( $website_property_id ) ) {
			return [
				'valid'   => false,
				'message' => __( 'API token and Website Property ID are required.', 'brag-book-gallery' ),
			];
		}

		// Use sidebar endpoint for validation - it's simple and just needs the API token
		$test_endpoint = 'https://app.bragbookgallery.com/api/plugin/combine/sidebar';

		// Sidebar endpoint expects POST with apiTokens array
		$request_body = [
			'apiTokens' => [ $api_token ],
		];

		// Log the request for debugging
		$this->log_debug( 'API Validation Request', [
			'method'   => 'POST',
			'endpoint' => $test_endpoint,
			'body'     => [
				'apiTokens' => [ substr( $api_token, 0, 10 ) . '...' ],
			],
		] );

		// Make POST request to sidebar endpoint for validation
		$response = wp_remote_post(
			$test_endpoint,
			[
				'timeout' => 10,
				'headers' => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $request_body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'valid'   => false,
				'message' => sprintf( __( 'API connection failed: %s', 'brag-book-gallery' ), $response->get_error_message() ),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		// Log the response for debugging
		$this->log_debug( 'API Validation Response', [
			'response_code' => $response_code,
			'response_body' => $response_body,
		] );

		// Check if response is successful
		if ( 200 !== $response_code && 201 !== $response_code ) {
			$error_message = $data['message'] ?? $data['error'] ?? __( 'Invalid API credentials.', 'brag-book-gallery' );

			// Check for specific error patterns
			if ( 401 === $response_code || 403 === $response_code ) {
				$error_message = __( 'Invalid API token or Website Property ID.', 'brag-book-gallery' );
			} elseif ( 404 === $response_code ) {
				$error_message = __( 'API endpoint not found. Please contact support.', 'brag-book-gallery' );
			}

			return [
				'valid'   => false,
				'message' => $error_message,
			];
		}

		// Check if we got valid data back
		if ( ! $data ) {
			return [
				'valid'   => false,
				'message' => __( 'Invalid response from API. No data returned.', 'brag-book-gallery' ),
			];
		}

		// Check for error in response
		if ( isset( $data['error'] ) && $data['error'] ) {
			return [
				'valid'   => false,
				'message' => $data['message'] ?? __( 'API returned an error.', 'brag-book-gallery' ),
			];
		}

		// Check if we have valid sidebar data (should have 'data' key with procedures)
		if ( ! isset( $data['data'] ) ) {
			return [
				'valid'   => false,
				'message' => __( 'Invalid response structure from API.', 'brag-book-gallery' ),
			];
		}

		// If we got here, credentials are valid
		// The sidebar endpoint returns procedures data if successful
		return [
			'valid'   => true,
			'message' => __( 'API credentials validated successfully!', 'brag-book-gallery' ),
			'data'    => $data,
		];
	}

	/**
	 * Handle validate API AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_validate_api(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$api_token          = sanitize_text_field( $_POST['api_token'] ?? '' );
		$website_property_id = sanitize_text_field( $_POST['websiteproperty_id'] ?? '' );
		$index              = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : 0;

		$validation = $this->validate_api_credentials( $api_token, $website_property_id );

		if ( $validation['valid'] ) {
			// Save the validated credentials
			$saved_tokens       = get_option( 'brag_book_gallery_api_token', array() );
			$saved_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

			// Ensure we have arrays
			if ( ! is_array( $saved_tokens ) ) {
				$saved_tokens = array();
			}
			if ( ! is_array( $saved_property_ids ) ) {
				$saved_property_ids = array();
			}

			// Update or add at the specified index
			$saved_tokens[ $index ]       = $api_token;
			$saved_property_ids[ $index ] = $website_property_id;

			// Re-index arrays to remove gaps
			$saved_tokens       = array_values( $saved_tokens );
			$saved_property_ids = array_values( $saved_property_ids );

			// Save the updated arrays
			update_option( 'brag_book_gallery_api_token', $saved_tokens );
			update_option( 'brag_book_gallery_website_property_id', $saved_property_ids );

			$validation['message'] = __( 'API credentials validated and saved successfully.', 'brag-book-gallery' );
			wp_send_json_success( $validation );
		} else {
			wp_send_json_error( $validation['message'] );
		}
	}

	/**
	 * Handle remove API connection AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_remove_api_connection(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : - 1;

		if ( $index < 0 ) {
			wp_send_json_error( __( 'Invalid connection index.', 'brag-book-gallery' ) );
		}

		// Get current saved arrays
		$saved_tokens       = get_option( 'brag_book_gallery_api_token', array() );
		$saved_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		// Ensure we have arrays
		if ( ! is_array( $saved_tokens ) ) {
			$saved_tokens = array();
		}
		if ( ! is_array( $saved_property_ids ) ) {
			$saved_property_ids = array();
		}

		// Remove the item at the specified index
		if ( isset( $saved_tokens[ $index ] ) ) {
			unset( $saved_tokens[ $index ] );
		}
		if ( isset( $saved_property_ids[ $index ] ) ) {
			unset( $saved_property_ids[ $index ] );
		}

		// Re-index arrays to remove gaps
		$saved_tokens       = array_values( $saved_tokens );
		$saved_property_ids = array_values( $saved_property_ids );

		// Save the updated arrays
		update_option( 'brag_book_gallery_api_token', $saved_tokens );
		update_option( 'brag_book_gallery_website_property_id', $saved_property_ids );

		wp_send_json_success( array(
			'message'   => __( 'API connection removed successfully.', 'brag-book-gallery' ),
			'remaining' => count( $saved_tokens )
		) );
	}

	/**
	 * Handle save API settings AJAX request
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_save_api_settings(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'brag_book_gallery_settings_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}

		// Parse form data
		parse_str( $_POST['form_data'], $form_data );

		// Validate all API credentials before saving
		$all_valid           = true;
		$validation_messages = [];

		if ( isset( $form_data['brag_book_gallery_api_token'] ) ) {
			foreach ( $form_data['brag_book_gallery_api_token'] as $index => $token ) {
				$website_property_id = $form_data['brag_book_gallery_website_property_id'][ $index ] ?? '';

				if ( ! empty( $token ) && ! empty( $website_property_id ) ) {
					$validation = $this->validate_api_credentials( $token, $website_property_id );
					if ( ! $validation['valid'] ) {
						$all_valid             = false;
						$validation_messages[] = sprintf(
							__( 'Row %d: %s', 'brag-book-gallery' ),
							$index + 1,
							$validation['message']
						);
					}
				}
			}
		}

		if ( ! $all_valid ) {
			wp_send_json_error( implode( '<br>', $validation_messages ) );
		}

		// Save validated settings
		if ( isset( $form_data['brag_book_gallery_api_token'] ) ) {
			update_option( 'brag_book_gallery_api_token', array_map( 'sanitize_text_field', $form_data['brag_book_gallery_api_token'] ) );
		}

		if ( isset( $form_data['brag_book_gallery_website_property_id'] ) ) {
			update_option( 'brag_book_gallery_website_property_id', array_map( 'sanitize_text_field', $form_data['brag_book_gallery_website_property_id'] ) );
		}

		if ( isset( $form_data['brag_book_gallery_gallery_page_slug'] ) ) {
			$slugs = array_map( 'sanitize_title', $form_data['brag_book_gallery_gallery_page_slug'] );
			update_option( 'brag_book_gallery_gallery_page_slug', $slugs );
		}

		// Clear cache
		$this->clear_plugin_cache();

		wp_send_json_success( __( 'API settings saved and validated successfully.', 'brag-book-gallery' ) );
	}

	/**
	 * Simple debug version of save settings
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_save_settings_debug(): void {
		// Very basic version for debugging
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'No permission', 'brag-book-gallery' ) );

			return;
		}

		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( esc_html__( 'No nonce provided', 'brag-book-gallery' ) );

			return;
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'brag_book_gallery_settings_nonce' ) ) {
			/* translators: %s: nonce value */
			wp_send_json_error( sprintf( esc_html__( 'Invalid nonce: %s', 'brag-book-gallery' ), $_POST['nonce'] ) );

			return;
		}

		if ( ! isset( $_POST['form_data'] ) ) {
			wp_send_json_error( esc_html__( 'No form data provided', 'brag-book-gallery' ) );

			return;
		}

		// Try basic parsing
		parse_str( $_POST['form_data'], $form_data );

		// Just save one option
		// Clean escaped quotes from WYSIWYG editor before saving
		$landing_text = $form_data['brag_book_gallery_landing_page_text'] ?? 'Debug test worked!';
		$landing_text = str_replace( '\"', '"', $landing_text );
		$landing_text = str_replace( "\'", "'", $landing_text );
		$landing_text = stripslashes( $landing_text );
		update_option( 'brag_book_gallery_landing_page_text', wp_kses_post( $landing_text ) );

		wp_send_json_success( esc_html__( 'Debug save successful!', 'brag-book-gallery' ) );
	}

	/**
	 * Handle slug availability check via AJAX
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_check_slug(): void {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'brag_book_gallery_check_slug' ) ) {
			wp_send_json_error( __( 'Security check failed', 'brag-book-gallery' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'brag-book-gallery' ) );
		}

		// Get and sanitize slug
		$slug = isset( $_POST['slug'] ) ? sanitize_title( $_POST['slug'] ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( __( 'Invalid slug', 'brag-book-gallery' ) );
		}

		// Check if page exists
		$existing_page = get_page_by_path( $slug );

		if ( $existing_page ) {
			wp_send_json_success( array(
				'exists'    => true,
				'message'   => sprintf(
					__( 'A page with slug "%s" already exists. The gallery will use this existing page.', 'brag-book-gallery' ),
					$slug
				),
				'page_id'   => $existing_page->ID,
				'edit_link' => get_edit_post_link( $existing_page->ID ),
			) );
		} else {
			wp_send_json_success( array(
				'exists'  => false,
				'message' => sprintf(
					__( 'Available! A new page with slug "%s" will be created when you save.', 'brag-book-gallery' ),
					$slug
				),
			) );
		}
	}

	/**
	 * Handle page generation via AJAX
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function handle_generate_page(): void {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'brag_book_gallery_generate_page' ) ) {
			wp_send_json_error( __( 'Security check failed', 'brag-book-gallery' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'brag-book-gallery' ) );
		}

		// Get and sanitize slug
		$slug = isset( $_POST['slug'] ) ? sanitize_title( $_POST['slug'] ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( __( 'Invalid slug', 'brag-book-gallery' ) );
		}

		// Check if page already exists
		$existing_page = get_page_by_path( $slug );
		if ( $existing_page ) {
			wp_send_json_error( __( 'A page with this slug already exists', 'brag-book-gallery' ) );
		}

		// Create the page with the gallery shortcode
		$page_data = array(
			'post_title'     => ucwords( str_replace( '-', ' ', $slug ) ),
			'post_name'      => $slug,
			'post_content'   => '[brag_book_gallery]', // Main gallery shortcode
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_author'    => get_current_user_id(),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);

		$page_id = wp_insert_post( $page_data );

		if ( is_wp_error( $page_id ) ) {
			wp_send_json_error( $page_id->get_error_message() );
		}

		// Also save the slug to the option so it's remembered
		update_option( 'combine_gallery_slug', $slug );

		wp_send_json_success( array(
			'message'   => sprintf(
				__( 'Gallery page "%s" has been created successfully!', 'brag-book-gallery' ),
				$slug
			),
			'page_id'   => $page_id,
			'edit_link' => get_edit_post_link( $page_id ),
			'view_link' => get_permalink( $page_id ),
		) );
	}
}
