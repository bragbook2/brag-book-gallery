<?php
/**
 * Test Cache Helper Functions
 *
 * @package BRAGBookGallery
 */

/**
 * Cache Helpers test case
 */
class CacheHelpersTest extends WP_UnitTestCase {

	/**
	 * Setup before each test
	 */
	public function set_up(): void {
		parent::set_up();
		
		// Ensure cache helpers are loaded
		$helpers_file = BRAG_BOOK_GALLERY_PLUGIN_DIR . '/includes/functions/cache-helpers.php';
		if ( file_exists( $helpers_file ) ) {
			require_once $helpers_file;
		}
	}

	/**
	 * Test WP Engine detection function
	 */
	public function test_wp_engine_detection(): void {
		$this->assertTrue( function_exists( 'brag_book_is_wp_engine' ) );
		
		// Test detection logic (will be false in test environment)
		$is_wp_engine = brag_book_is_wp_engine();
		$this->assertIsBool( $is_wp_engine );
	}

	/**
	 * Test cache helper functions exist
	 */
	public function test_cache_functions_exist(): void {
		$this->assertTrue( function_exists( 'brag_book_set_cache' ) );
		$this->assertTrue( function_exists( 'brag_book_get_cache' ) );
		$this->assertTrue( function_exists( 'brag_book_delete_cache' ) );
	}

	/**
	 * Test cache set and get functionality
	 */
	public function test_cache_set_get(): void {
		$key = 'test_cache_key';
		$value = [ 'test' => 'data', 'number' => 123 ];
		$expiration = 3600;

		// Test setting cache
		$result = brag_book_set_cache( $key, $value, $expiration );
		$this->assertTrue( $result );

		// Test getting cache
		$retrieved = brag_book_get_cache( $key );
		$this->assertEquals( $value, $retrieved );
	}

	/**
	 * Test cache deletion
	 */
	public function test_cache_delete(): void {
		$key = 'test_delete_key';
		$value = 'test_delete_value';

		// Set cache first
		brag_book_set_cache( $key, $value );
		$this->assertEquals( $value, brag_book_get_cache( $key ) );

		// Delete cache
		$result = brag_book_delete_cache( $key );
		$this->assertTrue( $result );

		// Verify deletion
		$this->assertFalse( brag_book_get_cache( $key ) );
	}

	/**
	 * Test cache with no expiration
	 */
	public function test_cache_no_expiration(): void {
		$key = 'test_no_expiration';
		$value = 'persistent_data';

		// Test with 0 expiration (no expiration)
		$result = brag_book_set_cache( $key, $value, 0 );
		$this->assertTrue( $result );

		$retrieved = brag_book_get_cache( $key );
		$this->assertEquals( $value, $retrieved );

		// Cleanup
		brag_book_delete_cache( $key );
	}

	/**
	 * Test cache with different data types
	 */
	public function test_cache_data_types(): void {
		$test_cases = [
			'string'  => 'test string',
			'integer' => 42,
			'float'   => 3.14,
			'boolean' => true,
			'array'   => [ 'key' => 'value', 'nested' => [ 'data' => 123 ] ],
			'object'  => (object) [ 'property' => 'value' ],
		];

		foreach ( $test_cases as $type => $value ) {
			$key = "test_type_{$type}";
			
			// Set and get each data type
			$this->assertTrue( brag_book_set_cache( $key, $value ) );
			$this->assertEquals( $value, brag_book_get_cache( $key ) );
			
			// Cleanup
			brag_book_delete_cache( $key );
		}
	}

	/**
	 * Test nonexistent cache key
	 */
	public function test_nonexistent_cache_key(): void {
		$result = brag_book_get_cache( 'nonexistent_key' );
		$this->assertFalse( $result );
	}

	/**
	 * Test cache key validation
	 */
	public function test_cache_key_validation(): void {
		// Test empty key
		$result = brag_book_set_cache( '', 'value' );
		$this->assertFalse( $result );

		// Test valid key
		$result = brag_book_set_cache( 'valid_key', 'value' );
		$this->assertTrue( $result );
		
		// Cleanup
		brag_book_delete_cache( 'valid_key' );
	}

	/**
	 * Cleanup after each test
	 */
	public function tear_down(): void {
		// Clean up any test cache entries
		$test_keys = [
			'test_cache_key',
			'test_delete_key',
			'test_no_expiration',
			'valid_key',
		];

		foreach ( $test_keys as $key ) {
			brag_book_delete_cache( $key );
		}

		parent::tear_down();
	}
}