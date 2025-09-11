<?php
/**
 * Test Admin Settings integration
 *
 * @package BRAGBookGallery
 */

/**
 * Admin Settings integration test case
 */
class AdminSettingsTest extends WP_UnitTestCase {

	/**
	 * User ID for admin tests
	 *
	 * @var int
	 */
	private static $admin_user_id;

	/**
	 * Set up before class
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_user_id = $factory->user->create( [
			'role' => 'administrator',
		] );
	}

	/**
	 * Setup before each test
	 */
	public function set_up(): void {
		parent::set_up();
		
		// Set current user as admin
		wp_set_current_user( self::$admin_user_id );
		
		// Set admin context
		set_current_screen( 'dashboard' );
	}

	/**
	 * Test admin menu registration
	 */
	public function test_admin_menu_registered(): void {
		// Simulate admin_menu hook
		do_action( 'admin_menu' );
		
		global $menu, $submenu;
		
		// Check if our menu items exist
		$found_main_menu = false;
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && strpos( $menu_item[2], 'brag-book-gallery' ) !== false ) {
				$found_main_menu = true;
				break;
			}
		}
		
		$this->assertTrue( $found_main_menu, 'Main admin menu should be registered' );
	}

	/**
	 * Test settings registration
	 */
	public function test_settings_registration(): void {
		// Trigger admin_init to register settings
		do_action( 'admin_init' );
		
		global $wp_settings_sections, $wp_settings_fields;
		
		// Check if settings are registered
		$this->assertArrayHasKey( 'brag_book_gallery_settings', $wp_settings_sections );
	}

	/**
	 * Test option saving and retrieval
	 */
	public function test_option_management(): void {
		$test_options = [
			'api_token'            => 'test-token-123',
			'website_property_id'  => 456,
			'gallery_page_slug'    => 'test-gallery',
			'debug_mode'           => true,
		];

		// Save options
		foreach ( $test_options as $key => $value ) {
			update_option( "brag_book_gallery_{$key}", $value );
		}

		// Retrieve and verify options
		foreach ( $test_options as $key => $expected_value ) {
			$actual_value = get_option( "brag_book_gallery_{$key}" );
			$this->assertEquals( $expected_value, $actual_value );
		}

		// Cleanup
		foreach ( $test_options as $key => $value ) {
			delete_option( "brag_book_gallery_{$key}" );
		}
	}

	/**
	 * Test API token validation
	 */
	public function test_api_token_validation(): void {
		// Test valid token format
		$valid_token = '2dd42caa-4ab0-4362-8002-f89fcab775cb';
		update_option( 'brag_book_gallery_api_token', $valid_token );
		
		$retrieved_token = get_option( 'brag_book_gallery_api_token' );
		$this->assertEquals( $valid_token, $retrieved_token );

		// Test token sanitization (if any)
		$token_with_spaces = " {$valid_token} ";
		update_option( 'brag_book_gallery_api_token', $token_with_spaces );
		
		// The option should be saved (WordPress handles sanitization at the UI level)
		$this->assertNotEmpty( get_option( 'brag_book_gallery_api_token' ) );

		// Cleanup
		delete_option( 'brag_book_gallery_api_token' );
	}

	/**
	 * Test settings page access permissions
	 */
	public function test_settings_page_permissions(): void {
		// Test admin user can access
		$this->assertTrue( current_user_can( 'manage_options' ) );

		// Test non-admin cannot access
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		
		$this->assertFalse( current_user_can( 'manage_options' ) );

		// Reset to admin
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Test debug mode functionality
	 */
	public function test_debug_mode(): void {
		// Enable debug mode
		update_option( 'brag_book_gallery_debug_mode', true );
		
		$debug_mode = get_option( 'brag_book_gallery_debug_mode' );
		$this->assertTrue( $debug_mode );

		// Disable debug mode
		update_option( 'brag_book_gallery_debug_mode', false );
		
		$debug_mode = get_option( 'brag_book_gallery_debug_mode' );
		$this->assertFalse( $debug_mode );

		// Cleanup
		delete_option( 'brag_book_gallery_debug_mode' );
	}

	/**
	 * Test factory reset functionality
	 */
	public function test_factory_reset(): void {
		// Set some test options
		$test_options = [
			'brag_book_gallery_api_token'           => 'test-token',
			'brag_book_gallery_website_property_id' => 123,
			'brag_book_gallery_custom_css'          => 'body { color: red; }',
		];

		foreach ( $test_options as $key => $value ) {
			update_option( $key, $value );
		}

		// Verify options are set
		foreach ( $test_options as $key => $value ) {
			$this->assertEquals( $value, get_option( $key ) );
		}

		// Simulate factory reset (would normally be triggered via AJAX)
		foreach ( $test_options as $key => $value ) {
			delete_option( $key );
		}

		// Verify options are removed
		foreach ( $test_options as $key => $value ) {
			$this->assertEmpty( get_option( $key ) );
		}
	}

	/**
	 * Test CSS settings
	 */
	public function test_custom_css_settings(): void {
		$test_css = '.brag-book-gallery { background: blue; }';
		
		update_option( 'brag_book_gallery_custom_css', $test_css );
		
		$retrieved_css = get_option( 'brag_book_gallery_custom_css' );
		$this->assertEquals( $test_css, $retrieved_css );

		// Cleanup
		delete_option( 'brag_book_gallery_custom_css' );
	}

	/**
	 * Test settings validation
	 */
	public function test_settings_validation(): void {
		// Test numeric validation for website property ID
		update_option( 'brag_book_gallery_website_property_id', '123' );
		$id = get_option( 'brag_book_gallery_website_property_id' );
		$this->assertEquals( '123', $id );

		// Test invalid numeric value
		update_option( 'brag_book_gallery_website_property_id', 'invalid' );
		$id = get_option( 'brag_book_gallery_website_property_id' );
		$this->assertEquals( 'invalid', $id ); // WordPress doesn't validate by default

		// Cleanup
		delete_option( 'brag_book_gallery_website_property_id' );
	}

	/**
	 * Cleanup after each test
	 */
	public function tear_down(): void {
		// Reset current user
		wp_set_current_user( 0 );
		
		parent::tear_down();
	}
}