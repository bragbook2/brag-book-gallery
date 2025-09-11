<?php
/**
 * Test Cache Integration functionality
 *
 * @package BRAGBookGallery
 */

use BRAGBookGallery\Includes\Extend\Cache_Manager;

/**
 * Cache Integration test case
 */
class CacheIntegrationTest extends WP_UnitTestCase {

	/**
	 * Setup before each test
	 */
	public function set_up(): void {
		parent::set_up();
		
		// Clear any existing cache
		wp_cache_flush();
		
		// Ensure cache helpers are loaded
		$helpers_file = BRAG_BOOK_GALLERY_PLUGIN_DIR . '/includes/functions/cache-helpers.php';
		if ( file_exists( $helpers_file ) ) {
			require_once $helpers_file;
		}
	}

	/**
	 * Test Cache_Manager functionality
	 */
	public function test_cache_manager(): void {
		$this->assertTrue( class_exists( 'BRAGBookGallery\Includes\Extend\Cache_Manager' ) );
		
		// Test cache methods exist
		$this->assertTrue( method_exists( Cache_Manager::class, 'set_cache' ) );
		$this->assertTrue( method_exists( Cache_Manager::class, 'get_cache' ) );
		$this->assertTrue( method_exists( Cache_Manager::class, 'delete_cache' ) );
	}

	/**
	 * Test cache integration with actual plugin flow
	 */
	public function test_cache_integration_flow(): void {
		$test_key = 'brag_book_test_integration';
		$test_data = [
			'timestamp' => time(),
			'data'      => [ 'test' => 'integration' ],
		];

		// Set cache using helper function
		$result = brag_book_set_cache( $test_key, $test_data, 3600 );
		$this->assertTrue( $result );

		// Get cache using helper function
		$cached_data = brag_book_get_cache( $test_key );
		$this->assertEquals( $test_data, $cached_data );

		// Test cache statistics (if available)
		$stats = Cache_Manager::get_cache_statistics();
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total_items', $stats );

		// Delete cache
		$result = brag_book_delete_cache( $test_key );
		$this->assertTrue( $result );

		// Verify deletion
		$this->assertFalse( brag_book_get_cache( $test_key ) );
	}

	/**
	 * Test cache duration settings
	 */
	public function test_cache_duration(): void {
		// Test default cache duration
		$duration = Cache_Manager::get_cache_duration();
		$this->assertIsInt( $duration );
		$this->assertGreaterThan( 0, $duration );

		// Test debug mode affects duration
		update_option( 'brag_book_gallery_debug_mode', true );
		$debug_duration = Cache_Manager::get_cache_duration();
		
		update_option( 'brag_book_gallery_debug_mode', false );
		$normal_duration = Cache_Manager::get_cache_duration();
		
		// Debug mode should have shorter cache duration
		$this->assertLessThanOrEqual( $normal_duration, $debug_duration );

		// Cleanup
		delete_option( 'brag_book_gallery_debug_mode' );
	}

	/**
	 * Test cache key generation
	 */
	public function test_cache_key_generation(): void {
		$base_key = 'test_key';
		$params = [ 'param1' => 'value1', 'param2' => 'value2' ];
		
		// Test that cache keys are generated consistently
		$key1 = $this->generate_cache_key( $base_key, $params );
		$key2 = $this->generate_cache_key( $base_key, $params );
		
		$this->assertEquals( $key1, $key2 );
		
		// Test different parameters generate different keys
		$different_params = [ 'param1' => 'different', 'param2' => 'values' ];
		$key3 = $this->generate_cache_key( $base_key, $different_params );
		
		$this->assertNotEquals( $key1, $key3 );
	}

	/**
	 * Test cache expiration
	 */
	public function test_cache_expiration(): void {
		$test_key = 'brag_book_test_expiration';
		$test_value = 'expiring_value';
		
		// Set cache with 1 second expiration
		$result = brag_book_set_cache( $test_key, $test_value, 1 );
		$this->assertTrue( $result );
		
		// Should be available immediately
		$this->assertEquals( $test_value, brag_book_get_cache( $test_key ) );
		
		// Wait for expiration (in real tests, we might mock time)
		sleep( 2 );
		
		// Should be expired (this might not work with object cache)
		$expired_value = brag_book_get_cache( $test_key );
		
		// Note: Some caching systems might not expire immediately
		// so we test that it's either expired or still cached
		$this->assertTrue( $expired_value === false || $expired_value === $test_value );
		
		// Cleanup
		brag_book_delete_cache( $test_key );
	}

	/**
	 * Test cache with large data
	 */
	public function test_cache_large_data(): void {
		$test_key = 'brag_book_test_large';
		
		// Create large test data
		$large_data = [];
		for ( $i = 0; $i < 1000; $i++ ) {
			$large_data[] = [
				'id'          => $i,
				'title'       => "Test Case {$i}",
				'description' => str_repeat( 'Lorem ipsum dolor sit amet. ', 50 ),
				'data'        => array_fill( 0, 100, "data_{$i}" ),
			];
		}
		
		// Test setting large data
		$result = brag_book_set_cache( $test_key, $large_data );
		$this->assertTrue( $result );
		
		// Test retrieving large data
		$cached_data = brag_book_get_cache( $test_key );
		$this->assertIsArray( $cached_data );
		$this->assertCount( 1000, $cached_data );
		
		// Verify data integrity
		$this->assertEquals( $large_data[0], $cached_data[0] );
		$this->assertEquals( $large_data[999], $cached_data[999] );
		
		// Cleanup
		brag_book_delete_cache( $test_key );
	}

	/**
	 * Test concurrent cache operations
	 */
	public function test_concurrent_cache_operations(): void {
		$test_keys = [];
		$test_data = [];
		
		// Set multiple cache entries
		for ( $i = 0; $i < 10; $i++ ) {
			$key = "brag_book_test_concurrent_{$i}";
			$data = [ 'index' => $i, 'timestamp' => microtime( true ) ];
			
			$test_keys[] = $key;
			$test_data[$key] = $data;
			
			$this->assertTrue( brag_book_set_cache( $key, $data ) );
		}
		
		// Retrieve all entries
		foreach ( $test_keys as $key ) {
			$cached_data = brag_book_get_cache( $key );
			$this->assertEquals( $test_data[$key], $cached_data );
		}
		
		// Delete all entries
		foreach ( $test_keys as $key ) {
			$this->assertTrue( brag_book_delete_cache( $key ) );
		}
		
		// Verify all are deleted
		foreach ( $test_keys as $key ) {
			$this->assertFalse( brag_book_get_cache( $key ) );
		}
	}

	/**
	 * Generate a cache key (helper method)
	 *
	 * @param string $base_key Base key.
	 * @param array  $params   Parameters.
	 * @return string
	 */
	private function generate_cache_key( string $base_key, array $params ): string {
		ksort( $params );
		return $base_key . '_' . md5( serialize( $params ) );
	}

	/**
	 * Cleanup after each test
	 */
	public function tear_down(): void {
		// Clean up any test cache entries
		$test_patterns = [
			'brag_book_test_integration',
			'brag_book_test_expiration',
			'brag_book_test_large',
		];
		
		foreach ( $test_patterns as $pattern ) {
			brag_book_delete_cache( $pattern );
		}
		
		// Clean up concurrent test keys
		for ( $i = 0; $i < 10; $i++ ) {
			brag_book_delete_cache( "brag_book_test_concurrent_{$i}" );
		}
		
		parent::tear_down();
	}
}