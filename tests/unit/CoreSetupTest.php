<?php
/**
 * Test Core Setup functionality
 *
 * @package BRAGBookGallery
 */

use BRAGBookGallery\Includes\Core\Setup;

/**
 * Core Setup test case
 */
class CoreSetupTest extends WP_UnitTestCase {

	/**
	 * Test plugin initialization
	 */
	public function test_plugin_initialization(): void {
		// Test that the plugin is loaded
		$this->assertTrue( class_exists( 'BRAGBookGallery\Includes\Core\Setup' ) );
		
		// Test singleton pattern
		$instance1 = Setup::get_instance();
		$instance2 = Setup::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test that required constants are defined
	 */
	public function test_required_constants(): void {
		$this->assertTrue( defined( 'BRAG_BOOK_GALLERY_VERSION' ) );
		$this->assertTrue( defined( 'BRAG_BOOK_GALLERY_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'BRAG_BOOK_GALLERY_PLUGIN_DIR' ) );
	}

	/**
	 * Test that required hooks are registered
	 */
	public function test_hooks_registered(): void {
		// Test init hook
		$this->assertGreaterThan( 0, has_action( 'init', [ Setup::get_instance(), 'init' ] ) );
		
		// Test cron hooks
		$this->assertGreaterThan( 0, has_action( 'brag_book_gallery_cleanup_expired_transients' ) );
		$this->assertGreaterThan( 0, has_action( 'brag_book_gallery_cleanup_wp_cache' ) );
	}

	/**
	 * Test that services can be retrieved
	 */
	public function test_service_retrieval(): void {
		$setup = Setup::get_instance();
		
		// Test that we can get services (they should return objects or null)
		$shortcodes = $setup->get_service( 'shortcodes' );
		$this->assertTrue( is_object( $shortcodes ) || is_null( $shortcodes ) );
		
		$settings = $setup->get_service( 'settings_manager' );
		$this->assertTrue( is_object( $settings ) || is_null( $settings ) );
	}

	/**
	 * Test cleanup methods exist
	 */
	public function test_cleanup_methods_exist(): void {
		$setup = Setup::get_instance();
		
		$this->assertTrue( method_exists( $setup, 'cleanup_expired_transients' ) );
		$this->assertTrue( method_exists( $setup, 'cleanup_wp_cache' ) );
	}

	/**
	 * Test cron job scheduling
	 */
	public function test_cron_scheduling(): void {
		// Clear any existing cron jobs
		wp_clear_scheduled_hook( 'brag_book_gallery_cleanup_expired_transients' );
		wp_clear_scheduled_hook( 'brag_book_gallery_cleanup_wp_cache' );
		
		// Trigger scheduling (normally done on plugin activation)
		do_action( 'brag_book_gallery_schedule_events' );
		
		// Check that cron jobs are scheduled
		$transient_cleanup = wp_next_scheduled( 'brag_book_gallery_cleanup_expired_transients' );
		$cache_cleanup = wp_next_scheduled( 'brag_book_gallery_cleanup_wp_cache' );
		
		$this->assertNotFalse( $transient_cleanup, 'Transient cleanup cron should be scheduled' );
		$this->assertNotFalse( $cache_cleanup, 'WP Cache cleanup cron should be scheduled' );
	}
}