<?php
/**
 * Debug Tools Manager
 *
 * Provides administrative debug tools for rewrite rules and system diagnostics
 * Compatible with WP Engine and other managed hosting environments
 *
 * @package BragBookGallery
 * @since 3.0.0
 */

namespace BRAGBookGallery\Includes\Admin;

use BRAGBookGallery\Includes\Admin\Debug_Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Tools class
 *
 * @since 3.0.0
 */
class Debug_Tools {

	/**
	 * Instance of this class
	 *
	 * @var Debug_Tools|null
	 */
	private static ?Debug_Tools $instance = null;

	/**
	 * Tool instances
	 *
	 * @var array
	 */
	private array $tools = [];

	/**
	 * Get instance
	 *
	 * @return Debug_Tools
	 */
	public static function get_instance(): Debug_Tools {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize debug tools
	 *
	 * @return void
	 */
	private function init(): void {
		// Register tools
		$this->register_tools();

		// Register AJAX handlers - these need to be available even before user check
		add_action( 'wp_ajax_brag_book_debug_tool', [ $this, 'handle_ajax_request' ] );
	}

	/**
	 * Register available tools
	 *
	 * @return void
	 */
	private function register_tools(): void {
		// Initialize tools - classes are loaded via autoloader
		$this->tools['gallery-checker']   = new Debug_Tools\Gallery_Checker();
		$this->tools['rewrite-debug']     = new Debug_Tools\Rewrite_Debug();
		$this->tools['rewrite-fix']       = new Debug_Tools\Rewrite_Fix();
		$this->tools['rewrite-flush']     = new Debug_Tools\Rewrite_Flush();
		$this->tools['cache-management']  = new Debug_Tools\Cache_Management();
		$this->tools['system-info']       = new Debug_Tools\System_Info();
	}


	/**
	 * Handle AJAX requests for debug tools
	 *
	 * @return void
	 */
	public function handle_ajax_request(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_debug_tools', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get tool and action
		$tool   = sanitize_text_field( $_POST['tool'] ?? '' );
		$action = sanitize_text_field( $_POST['tool_action'] ?? '' );

		// Validate tool exists
		if ( ! isset( $this->tools[ $tool ] ) ) {
			wp_send_json_error( 'Invalid tool' );
		}

		// Execute tool action
		try {
			$result = $this->tools[ $tool ]->execute( $action, $_POST );
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Get a specific tool instance
	 *
	 * @param string $tool_name Tool identifier.
	 * @return object|null
	 */
	public function get_tool( string $tool_name ): ?object {
		return $this->tools[ $tool_name ] ?? null;
	}
}
