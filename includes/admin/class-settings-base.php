<?php
/**
 * Base Settings Class - Abstract base for all settings pages
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
 * Base Settings Class
 *
 * Abstract base class that provides common functionality and structure for all
 * settings pages in the BRAG Book Gallery plugin. This class implements the
 * Template Method pattern to ensure consistent behavior across all settings pages
 * while allowing individual pages to customize their specific functionality.
 *
 * Key features provided:
 * - Consistent page structure with header, navigation, and footer
 * - Common form handling and validation patterns
 * - Shared navigation tabs with intelligent visibility
 * - WordPress settings API integration
 * - Security and capability checking
 * - Admin notice management
 *
 * All settings pages must extend this class and implement the abstract methods
 * to define their specific behavior while inheriting the common structure.
 *
 * @since 3.0.0
 */
abstract class Settings_Base {

	/**
	 * WordPress admin page slug identifier
	 *
	 * Unique identifier used by WordPress for:
	 * - Menu registration (add_menu_page, add_submenu_page)
	 * - URL routing (admin.php?page={slug})
	 * - Settings group identification
	 * - Asset targeting and hook detection
	 *
	 * Must be unique within the WordPress admin to prevent conflicts.
	 *
	 * @since 3.0.0
	 * @var string WordPress page slug (e.g., 'brag-book-gallery-settings')
	 */
	protected string $page_slug = '';

	/**
	 * Localized page title for admin display
	 *
	 * The full page title displayed in the browser title bar and page header.
	 * This should be a human-readable, translated string that clearly identifies
	 * the purpose of the settings page to administrators.
	 *
	 * Set during render() method to ensure translations are available.
	 *
	 * @since 3.0.0
	 * @var string Translated page title (e.g., 'BRAG Book Gallery Settings')
	 */
	protected string $page_title = '';

	/**
	 * Localized menu title for navigation display
	 *
	 * Shorter version of the page title used in admin menu and navigation tabs.
	 * Should be concise while remaining descriptive to fit limited menu space.
	 *
	 * Set during render() method to ensure translations are available.
	 *
	 * @since 3.0.0
	 * @var string Translated menu title (e.g., 'Settings', 'API', 'Help')
	 */
	protected string $menu_title = '';

	/**
	 * Constructor - Initialize settings page
	 *
	 * Automatically calls the init() method to allow child classes to perform
	 * their specific initialization without needing to override __construct().
	 * This follows the Template Method pattern where the base class controls
	 * the initialization flow.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the settings page - ABSTRACT METHOD
	 *
	 * Child classes must implement this method to perform their specific
	 * initialization tasks such as:
	 * - Setting page_slug property
	 * - Registering WordPress hooks (if needed)
	 * - Initializing page-specific properties
	 * - Setting up validation rules
	 *
	 * Note: Localized strings (page_title, menu_title) should be set in render()
	 * method to ensure translation functions are available.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	abstract protected function init(): void;

	/**
	 * Render the complete settings page - ABSTRACT METHOD
	 *
	 * Child classes must implement this method to define their page content.
	 * The method should:
	 * - Set localized page_title and menu_title properties
	 * - Handle form submissions (if applicable)
	 * - Call render_header() to output common page structure
	 * - Output page-specific content and forms
	 * - Call render_footer() to close page structure
	 *
	 * Example implementation structure:
	 * ```php
	 * $this->page_title = __('My Settings', 'textdomain');
	 * if (isset($_POST['submit'])) {
	 *     $this->save_settings();
	 * }
	 * $this->render_header();
	 * // Page content here
	 * $this->render_footer();
	 * ```
	 *
	 * @since 3.0.0
	 * @return void
	 */
	abstract public function render(): void;

	/**
	 * Get configuration data for all navigation tabs
	 *
	 * Provides the complete navigation structure used throughout the settings interface.
	 * Each tab includes localized labels and admin URLs. This method serves as the
	 * central source of truth for navigation configuration.
	 *
	 * Tab structure includes:
	 * - Consistent URL patterns following WordPress admin conventions
	 * - Localized labels for internationalization support
	 * - Unique slug identifiers for routing and state management
	 *
	 * Used by render_navigation() to generate the actual navigation HTML.
	 *
	 * @since 3.0.0
	 * @return array Array of tab configuration with keys: label, url, slug
	 */
	protected function get_navigation_tabs(): array {
		return array(
			'general' => array(
				'label' => __( 'General', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'slug'  => 'brag-book-gallery-settings',
			),
			'mode' => array(
				'label' => __( 'Mode', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-mode' ),
				'slug'  => 'brag-book-gallery-mode',
			),
			'javascript' => array(
				'label' => __( 'JavaScript Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-javascript' ),
				'slug'  => 'brag-book-gallery-javascript',
			),
			'local' => array(
				'label' => __( 'Local Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-local' ),
				'slug'  => 'brag-book-gallery-local',
			),
			'api' => array(
				'label' => __( 'API', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'slug'  => 'brag-book-gallery-api-settings',
			),
			'help' => array(
				'label' => __( 'Help', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
				'slug'  => 'brag-book-gallery-help',
			),
			'debug' => array(
				'label' => __( 'Debug', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
				'slug'  => 'brag-book-gallery-debug',
			),
		);
	}

	/**
	 * Render the complete navigation tab system with intelligent visibility
	 *
	 * Generates the HTML navigation interface that appears on all settings pages.
	 * Implements sophisticated logic to show/hide tabs based on plugin configuration:
	 *
	 * - API status determines which tabs are accessible
	 * - Current mode (JavaScript/Local) controls mode-specific tab visibility
	 * - Active tab highlighting provides clear visual feedback
	 * - Responsive design adapts to different screen sizes
	 * - Badge notifications indicate configuration status
	 *
	 * The navigation uses WordPress admin styling conventions and integrates
	 * seamlessly with the existing admin interface.
	 *
	 * Visual features:
	 * - Dashicons for consistent iconography
	 * - Hover states for better UX
	 * - Active state highlighting
	 * - Warning badges for unconfigured settings
	 * - Responsive layout for mobile devices
	 *
	 * @since 3.0.0
	 * @param string $current_page Current page slug for active tab highlighting
	 * @return void Outputs HTML navigation directly
	 */
	protected function render_navigation( string $current_page = '' ): void {
		if ( empty( $current_page ) ) {
			$current_page = $this->page_slug;
		}

		// Get current mode and API status
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$has_api_key = ! empty( $api_tokens );

		// Map page slugs to tab keys
		$page_to_tab = array(
			'brag-book-gallery-settings'     => 'general',
			'brag-book-gallery-mode'          => 'mode',
			'brag-book-gallery-javascript'    => 'javascript',
			'brag-book-gallery-local'         => 'local',
			'brag-book-gallery-api-settings'  => 'api',
			'brag-book-gallery-consultation'  => 'consultations',
			'brag-book-gallery-help'          => 'help',
			'brag-book-gallery-debug'         => 'debug',
		);

		$current_tab = isset( $page_to_tab[ $current_page ] ) ? $page_to_tab[ $current_page ] : 'general';

		// Tab configuration with icons
		$tabs = array(
			'general' => array(
				'title' => __( 'General', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'api' => array(
				'title' => __( 'API', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'icon'  => 'dashicons-admin-network',
			),
		);

		// Only add mode and mode-specific tabs if API key is configured
		if ( $has_api_key ) {
			$tabs['mode'] = array(
				'title' => __( 'Mode', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-mode' ),
				'icon'  => 'dashicons-admin-generic',
			);

			// Add mode-specific tabs
			if ( $current_mode === 'javascript' ) {
				$tabs['javascript'] = array(
					'title' => __( 'JavaScript', 'brag-book-gallery' ),
					'url'   => admin_url( 'admin.php?page=brag-book-gallery-javascript' ),
					'icon'  => 'dashicons-media-code',
				);
			} elseif ( $current_mode === 'local' ) {
				$tabs['local'] = array(
					'title' => __( 'Local', 'brag-book-gallery' ),
					'url'   => admin_url( 'admin.php?page=brag-book-gallery-local' ),
					'icon'  => 'dashicons-database',
				);
			}
		}

		// Add remaining tabs
		$tabs['consultations'] = array(
			'title' => __( 'Consultations', 'brag-book-gallery' ),
			'url'   => admin_url( 'admin.php?page=brag-book-gallery-consultation' ),
			'icon'  => 'dashicons-forms',
		);
		$tabs['help'] = array(
			'title' => __( 'Help', 'brag-book-gallery' ),
			'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
			'icon'  => 'dashicons-sos',
		);
		$tabs['debug'] = array(
			'title' => __( 'Debug', 'brag-book-gallery' ),
			'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
			'icon'  => 'dashicons-admin-tools',
		);

		// Check API configuration status for badge
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$has_api    = ! empty( $api_tokens );
		?>
		<nav class="brag-book-gallery-tabs">
			<ul class="brag-book-gallery-tab-list">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<li class="brag-book-gallery-tab-item <?php echo $current_tab === $key ? 'active' : ''; ?>">
						<a href="<?php echo esc_url( $tab['url'] ); ?>"
						   class="brag-book-gallery-tab-link">
							<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<span class="brag-book-gallery-tab-title"><?php echo esc_html( $tab['title'] ); ?></span>
							<?php if ( $key === 'api' && ! $has_api ) : ?>
								<span class="brag-book-gallery-tab-badge">!</span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Render settings header
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function render_header(): void {
		// Get plugin version
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '3.0.0';
		?>
		<div class="wrap brag-book-gallery-admin-wrap">
			<div class="brag-book-gallery-header">
				<div class="brag-book-gallery-header-left">
					<img
						src="<?php echo esc_url( plugin_dir_url( dirname( __DIR__ ) ) . 'assets/images/bragbook-logo.svg' ); ?>"
						alt="BRAG Book" class="brag-book-gallery-logo"/>
					<h1><?php esc_html_e( 'BRAG Book Gallery', 'brag-book-gallery' ); ?></h1>
				</div>
				<div class="brag-book-gallery-header-right">
					<span class="brag-book-gallery-version-badge">
						<?php echo esc_html( 'v' . $version ); ?>
					</span>
				</div>
			</div>

			<?php $this->render_navigation(); ?>

			<div class="brag-book-gallery-content">
		<?php
	}

	/**
	 * Render settings footer
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function render_footer(): void {
		?>
			</div><!-- .brag-book-gallery-content -->
		</div><!-- .wrap -->
		<?php
	}

	/**
	 * Validate form submission with comprehensive security checks
	 *
	 * Performs critical security validations before processing any form data.
	 * This method should be called at the beginning of any save operation
	 * to ensure the request is legitimate and authorized.
	 *
	 * Security checks performed:
	 * - WordPress nonce verification to prevent CSRF attacks
	 * - User capability verification to ensure proper permissions
	 * - Request method validation (POST required)
	 *
	 * The method returns false if any security check fails, allowing
	 * calling methods to abort processing safely without exposing
	 * sensitive operations to unauthorized users.
	 *
	 * This follows WordPress security best practices and should be used
	 * consistently across all settings save operations.
	 *
	 * @since 3.0.0
	 * @param string $nonce_action Nonce action name used when creating the nonce
	 * @param string $nonce_field  Form field name containing the nonce value
	 * @return bool True if all security checks pass, false if any check fails
	 */
	protected function save_settings( string $nonce_action, string $nonce_field ): bool {
		// Verify nonce to prevent CSRF attacks
		if ( ! isset( $_POST[ $nonce_field ] ) ||
		     ! wp_verify_nonce( $_POST[ $nonce_field ], $nonce_action ) ) {
			return false;
		}

		// Check user capabilities to ensure proper authorization
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add a WordPress admin notice for user feedback
	 *
	 * Creates an admin notice that will be displayed on the current settings page
	 * to provide feedback about operations (successful saves, errors, warnings, etc.).
	 *
	 * Uses WordPress's built-in settings error system which automatically handles:
	 * - Proper HTML structure and CSS classes
	 * - Dismissible notices where appropriate
	 * - Integration with WordPress admin styling
	 * - Accessibility features for screen readers
	 *
	 * Notice types and their typical usage:
	 * - 'success': Settings saved successfully
	 * - 'error': Validation errors or save failures
	 * - 'warning': Configuration issues or deprecation notices
	 * - 'info': Informational messages or status updates
	 *
	 * The notice will be displayed when settings_errors() is called,
	 * typically in the render() method of child classes.
	 *
	 * @since 3.0.0
	 * @param string $message Localized message text to display to user
	 * @param string $type    Notice type: 'success', 'error', 'warning', 'info'
	 * @return void Notice is queued for display, not output immediately
	 */
	protected function add_notice( string $message, string $type = 'success' ): void {
		add_settings_error(
			$this->page_slug,
			$this->page_slug . '_message',
			$message,
			$type
		);
	}

	/**
	 * Get current mode
	 *
	 * @since 3.0.0
	 * @return string Current mode (javascript or local).
	 */
	protected function get_current_mode(): string {
		return get_option( 'brag_book_gallery_mode', 'javascript' );
	}

	/**
	 * Check if in JavaScript mode
	 *
	 * @since 3.0.0
	 * @return bool True if in JavaScript mode.
	 */
	protected function is_javascript_mode(): bool {
		return $this->get_current_mode() === 'javascript';
	}

	/**
	 * Check if in Local mode
	 *
	 * @since 3.0.0
	 * @return bool True if in Local mode.
	 */
	protected function is_local_mode(): bool {
		return $this->get_current_mode() === 'local';
	}
}
