<?php
/**
 * Base test case for BRAGBook Gallery tests
 *
 * @package BRAGBookGallery
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase as BaseTestCase;

/**
 * Base test case class
 */
abstract class TestCase extends BaseTestCase {

	/**
	 * Setup before each test
	 */
	public function set_up(): void {
		parent::set_up();
		
		// Reset any global state
		$this->reset_plugin_state();
	}

	/**
	 * Cleanup after each test
	 */
	public function tear_down(): void {
		// Clean up any test data
		$this->cleanup_test_data();
		
		parent::tear_down();
	}

	/**
	 * Reset plugin state for testing
	 */
	protected function reset_plugin_state(): void {
		// Clear any cached data
		wp_cache_flush();
		
		// Remove any test transients
		delete_transient( 'brag_book_test_transient' );
	}

	/**
	 * Clean up test data
	 */
	protected function cleanup_test_data(): void {
		// Remove test posts, terms, etc.
		global $wpdb;
		
		// Clean up test posts
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_title LIKE '%test_%'" );
		
		// Clean up test options
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%brag_book_test_%'" );
		
		// Clean up test transients
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient_brag_book_test_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient_timeout_brag_book_test_%'" );
	}

	/**
	 * Assert that a WordPress action was fired
	 *
	 * @param string $action Action name.
	 * @param int    $times  Expected number of times fired.
	 */
	protected function assert_action_fired( string $action, int $times = 1 ): void {
		$this->assertEquals( $times, did_action( $action ), "Action '{$action}' should have been fired {$times} times." );
	}

	/**
	 * Assert that a WordPress filter was applied
	 *
	 * @param string $filter Filter name.
	 */
	protected function assert_filter_exists( string $filter ): void {
		$this->assertTrue( has_filter( $filter ), "Filter '{$filter}' should exist." );
	}

	/**
	 * Create a mock API response
	 *
	 * @param array $data Response data.
	 * @return array
	 */
	protected function create_mock_api_response( array $data = [] ): array {
		return wp_parse_args( $data, [
			'success' => true,
			'data'    => [
				'cases' => [
					[
						'id'          => 123,
						'title'       => 'Test Case',
						'procedure'   => 'Test Procedure',
						'images'      => [
							'before' => 'https://example.com/before.jpg',
							'after'  => 'https://example.com/after.jpg',
						],
					],
				],
			],
		] );
	}

	/**
	 * Create test option values
	 *
	 * @param array $options Options to set.
	 */
	protected function set_test_options( array $options ): void {
		foreach ( $options as $key => $value ) {
			update_option( "brag_book_test_{$key}", $value );
		}
	}

	/**
	 * Mock WordPress HTTP API
	 *
	 * @param array $response Mock response.
	 * @param int   $status   HTTP status code.
	 */
	protected function mock_wp_http( array $response, int $status = 200 ): void {
		add_filter( 'pre_http_request', function() use ( $response, $status ) {
			return [
				'response' => [ 'code' => $status ],
				'body'     => wp_json_encode( $response ),
			];
		} );
	}
}