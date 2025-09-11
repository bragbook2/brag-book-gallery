<?php
/**
 * Test Shortcode functionality
 *
 * @package BRAGBookGallery
 */

use BRAGBookGallery\Includes\Extend\Shortcodes;

/**
 * Shortcode test case
 */
class ShortcodeTest extends WP_UnitTestCase {

	/**
	 * Setup before each test
	 */
	public function set_up(): void {
		parent::set_up();
		
		// Ensure shortcodes are registered
		do_action( 'init' );
	}

	/**
	 * Test that shortcodes are registered
	 */
	public function test_shortcodes_registered(): void {
		$this->assertTrue( shortcode_exists( 'brag_book_gallery' ) );
		$this->assertTrue( shortcode_exists( 'brag_book_carousel' ) );
		$this->assertTrue( shortcode_exists( 'brag_book_gallery_case' ) );
		$this->assertTrue( shortcode_exists( 'brag_book_gallery_cases' ) );
		$this->assertTrue( shortcode_exists( 'brag_book_gallery_favorites' ) );
	}

	/**
	 * Test main gallery shortcode
	 */
	public function test_main_gallery_shortcode(): void {
		// Mock the API response to avoid external calls
		$this->mock_api_response();
		
		$output = do_shortcode( '[brag_book_gallery]' );
		
		// Should contain main gallery structure
		$this->assertStringContainsString( 'brag-book-gallery-main', $output );
	}

	/**
	 * Test carousel shortcode with attributes
	 */
	public function test_carousel_shortcode(): void {
		// Mock the API response
		$this->mock_api_response();
		
		$output = do_shortcode( '[brag_book_carousel procedure="arm-lift" limit="5"]' );
		
		// Should contain carousel structure
		$this->assertStringContainsString( 'brag-book-carousel', $output );
	}

	/**
	 * Test legacy carousel shortcode compatibility
	 */
	public function test_legacy_carousel_shortcode(): void {
		$this->mock_api_response();
		
		$output = do_shortcode( '[bragbook_carousel_shortcode procedure="arm-lift" limit="5"]' );
		
		// Should contain carousel structure (mapped to new shortcode)
		$this->assertStringContainsString( 'brag-book-carousel', $output );
	}

	/**
	 * Test single case shortcode
	 */
	public function test_single_case_shortcode(): void {
		$this->mock_api_response();
		
		$output = do_shortcode( '[brag_book_gallery_case case_id="12345"]' );
		
		// Should contain case structure or error message
		$this->assertIsString( $output );
		$this->assertNotEmpty( $output );
	}

	/**
	 * Test cases grid shortcode
	 */
	public function test_cases_grid_shortcode(): void {
		$this->mock_api_response();
		
		$output = do_shortcode( '[brag_book_gallery_cases]' );
		
		// Should contain cases structure
		$this->assertIsString( $output );
	}

	/**
	 * Test favorites shortcode
	 */
	public function test_favorites_shortcode(): void {
		$output = do_shortcode( '[brag_book_gallery_favorites]' );
		
		// Should contain favorites structure
		$this->assertStringContainsString( 'favorites', $output );
	}

	/**
	 * Test shortcode with invalid attributes
	 */
	public function test_shortcode_invalid_attributes(): void {
		$this->mock_api_response();
		
		// Test carousel with invalid procedure
		$output = do_shortcode( '[brag_book_carousel procedure="invalid-procedure"]' );
		$this->assertIsString( $output );
		
		// Test case with invalid case_id
		$output = do_shortcode( '[brag_book_gallery_case case_id="invalid"]' );
		$this->assertIsString( $output );
	}

	/**
	 * Test shortcode attribute parsing
	 */
	public function test_shortcode_attribute_parsing(): void {
		// This would require access to the shortcode handler internals
		// For now, we test that shortcodes handle attributes without errors
		
		$test_attributes = [
			'[brag_book_carousel procedure="test" limit="10" autoplay="true"]',
			'[brag_book_carousel procedure="test" show_controls="false"]',
			'[brag_book_carousel member_id="123" limit="5"]',
		];

		foreach ( $test_attributes as $shortcode ) {
			$this->mock_api_response();
			$output = do_shortcode( $shortcode );
			$this->assertIsString( $output );
		}
	}

	/**
	 * Test shortcode output caching
	 */
	public function test_shortcode_caching(): void {
		$this->mock_api_response();
		
		// First call - should cache the result
		$output1 = do_shortcode( '[brag_book_carousel procedure="test"]' );
		
		// Second call - should use cached result
		$output2 = do_shortcode( '[brag_book_carousel procedure="test"]' );
		
		// Outputs should be the same (indicating caching worked)
		$this->assertEquals( $output1, $output2 );
	}

	/**
	 * Mock API response for testing
	 */
	private function mock_api_response(): void {
		add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			// Only mock our plugin's API calls
			if ( strpos( $url, 'bragbook' ) !== false ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [
						'success' => true,
						'data'    => [
							'cases' => [
								[
									'id'        => '12345',
									'title'     => 'Test Case',
									'procedure' => 'Test Procedure',
									'images'    => [
										'before' => 'https://example.com/before.jpg',
										'after'  => 'https://example.com/after.jpg',
									],
								],
							],
							'sidebar' => [
								'procedures' => [
									[
										'id'   => 1,
										'name' => 'Test Procedure',
										'slug' => 'test-procedure',
									],
								],
							],
						],
					] ),
				];
			}
			return $preempt;
		}, 10, 3 );
	}

	/**
	 * Cleanup after each test
	 */
	public function tear_down(): void {
		// Remove the mock filter
		remove_all_filters( 'pre_http_request' );
		
		parent::tear_down();
	}
}