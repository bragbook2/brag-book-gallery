<?php
/**
 * AJAX handlers for BRAGBookGallery plugin.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\REST\Endpoints;
use Exception;

/**
 * Class Ajax_Handlers
 *
 * Handles all AJAX operations for the BRAGBookGallery plugin.
 *
 * @since 3.0.0
 */
class Ajax_Handlers {

	/**
	 * Register all AJAX handlers.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function register(): void {
		// Gallery filtering
		add_action( 'wp_ajax_brag_book_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );

		// Case details
		add_action( 'wp_ajax_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );

		add_action( 'wp_ajax_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );
		add_action( 'wp_ajax_nopriv_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );

		add_action( 'wp_ajax_brag_book_load_case_details_html', [ __CLASS__, 'ajax_load_case_details_html' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_case_details_html', [ __CLASS__, 'ajax_load_case_details_html' ] );

		// Load more and filtering
		add_action( 'wp_ajax_brag_book_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );

		add_action( 'wp_ajax_brag_book_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );

		// Cache management
		add_action( 'wp_ajax_brag_book_gallery_clear_cache', [ __CLASS__, 'ajax_clear_cache' ] );

		// Rewrite rules
		add_action( 'wp_ajax_brag_book_flush_rewrite_rules', [ __CLASS__, 'ajax_flush_rewrite_rules' ] );
	}

	/**
	 * AJAX handler to flush rewrite rules.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_flush_rewrite_rules(): void {
		// Check nonce
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'bragbook_flush' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			// Force register our rules
			Rewrite_Rules_Handler::custom_rewrite_rules();

			// Flush rewrite rules
			flush_rewrite_rules( true );

			// Clear the notice
			delete_transient( 'bragbook_show_rewrite_notice' );

			// Get debug info
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

			wp_send_json_success( [
				'message' => 'Rewrite rules flushed successfully',
				'gallery_slugs' => $gallery_slugs,
				'timestamp' => current_time( 'mysql' )
			] );

		} catch ( Exception $e ) {
			wp_send_json_error( 'Error flushing rules: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for loading filtered gallery content.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_filtered_gallery(): void {
		// Delegate to the original Shortcodes class method which has the full implementation
		Shortcodes::ajax_load_filtered_gallery();
	}

	/**
	 * AJAX handler to load case details.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_case_details(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get case ID
		$case_id = isset( $_POST['case_id'] ) ? sanitize_text_field( wp_unslash( $_POST['case_id'] ) ) : '';

		if ( empty( $case_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Case ID is required.', 'brag-book-gallery' ),
			] );
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		// Initialize endpoints
		$endpoints = new Endpoints();

		// Get case details from API
		$case_data = null;
		$error_messages = [];

		// Try to get case details using the caseNumber endpoint
		foreach ( $api_tokens as $index => $token ) {
			$property_id = $website_property_ids[ $index ] ?? null;

			if ( ! $property_id ) {
				$error_messages[] = 'Missing property ID for token index ' . $index;
				continue;
			}

			// Try to fetch case details
			$response = $endpoints->bb_get_case_by_number( $token, (int) $property_id, $case_id );

			if ( ! empty( $response ) && is_array( $response ) ) {
				$case_data = $response;
				break;
			} else {
				$error_messages[] = 'Failed to fetch from token index ' . $index;
			}
		}

		// If no case data found, try pagination endpoint as fallback
		if ( empty( $case_data ) ) {
			$filter_body = [
				'apiTokens' => $api_tokens,
				'websitePropertyIds' => array_map( 'intval', $website_property_ids ),
				'count' => 100,
			];

			$response = $endpoints->bb_get_pagination_data( $filter_body );

			if ( ! empty( $response['cases'] ) && is_array( $response['cases'] ) ) {
				// Search for the case in the results
				foreach ( $response['cases'] as $case ) {
					if ( isset( $case['caseNumber'] ) && $case['caseNumber'] === $case_id ) {
						$case_data = $case;
						break;
					}
				}
			}
		}

		if ( empty( $case_data ) ) {
			wp_send_json_error( [
				'message' => __( 'Case not found.', 'brag-book-gallery' ),
				'case_id' => $case_id,
				'errors' => $error_messages,
			] );
		}

		// Format case data for frontend
		$formatted_data = self::format_case_data_for_frontend( $case_data, $case_id );

		// Send response
		wp_send_json_success( $formatted_data );
	}

	/**
	 * AJAX handler for loading case details HTML.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_case_details_html(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$case_id = sanitize_text_field( $_POST['case_id'] ?? '' );
		$procedure_id = sanitize_text_field( $_POST['procedure_id'] ?? '' );

		if ( empty( $case_id ) ) {
			wp_send_json_error( 'Case ID is required' );
			return;
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
			wp_send_json_error( 'API configuration missing' );
			return;
		}

		$api_token = $api_tokens[0];
		$website_property_id = intval( $website_property_ids[0] );

		// Try to get case from cached data or fresh fetch
		$case_data = self::find_case_by_id( $case_id, $api_token, $website_property_id );

		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			// Generate HTML for case details using Shortcodes class method
			// Use reflection to access the private method
			$method = new \ReflectionMethod( Shortcodes::class, 'render_case_details_html' );
			$method->setAccessible( true );
			$html = $method->invoke( null, $case_data );

			wp_send_json_success( [
				'html' => $html,
				'case_id' => $case_id,
			] );
			return;
		}

		// Case not found
		wp_send_json_error( 'Case not found. Case ID: ' . $case_id );
	}

	/**
	 * Simple AJAX handler for case details.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_simple_case_handler(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$case_id = sanitize_text_field( $_POST['case_id'] ?? '' );

		if ( empty( $case_id ) ) {
			wp_send_json_error( 'Case ID is required' );
			return;
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
			wp_send_json_error( 'API configuration missing' );
			return;
		}

		$api_token = $api_tokens[0];
		$website_property_id = intval( $website_property_ids[0] );

		// Try to get case data
		$case_data = self::find_case_by_id( $case_id, $api_token, $website_property_id );

		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			// Generate HTML for case details using Shortcodes class method
			// Use reflection to access the private method
			$method = new \ReflectionMethod( Shortcodes::class, 'render_case_details_html' );
			$method->setAccessible( true );
			$html = $method->invoke( null, $case_data );
			
			wp_send_json_success( [
				'html' => $html,
				'case_id' => $case_id,
			] );
			return;
		}

		// Case not found
		wp_send_json_error( 'Case not found. Case ID: ' . $case_id );
	}

	/**
	 * AJAX handler for clearing gallery cache.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_clear_cache(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_clear_cache' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$result = Cache_Manager::clear_all_cache();

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * AJAX handler for loading more cases.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_more_cases(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get parameters
		$start_page = isset( $_POST['start_page'] ) ? intval( $_POST['start_page'] ) : 6;
		$procedure_ids = isset( $_POST['procedure_ids'] ) ? array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['procedure_ids'] ) ) ) ) : [];
		$has_nudity = isset( $_POST['has_nudity'] ) && $_POST['has_nudity'] === '1';

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		try {
			$endpoints = new Endpoints();
			$all_cases = [];
			$cases_per_page = 10;
			$pages_to_load = 1; // Load 1 page per click

			// Prepare base filter body
			$filter_body = [
				'apiTokens'          => [ $api_tokens[0] ],
				'websitePropertyIds' => [ intval( $website_property_ids[0] ) ],
				'procedureIds'       => $procedure_ids,
			];

			// Fetch additional pages
			$has_more = false;

			for ( $i = 0; $i < $pages_to_load; $i++ ) {
				$current_page = $start_page + $i;
				$filter_body['count'] = $current_page;

				$response = $endpoints->bb_get_pagination_data( $filter_body );

				if ( ! empty( $response ) ) {
					$page_data = json_decode( $response, true );

					if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
						$new_cases_count = count( $page_data['data'] );
						$all_cases = array_merge( $all_cases, $page_data['data'] );

						// Check if there might be more
						if ( $new_cases_count == $cases_per_page ) {
							$next_page = $current_page + 1;
							$filter_body['count'] = $next_page;
							$check_response = $endpoints->bb_get_pagination_data( $filter_body );
							if ( ! empty( $check_response ) ) {
								$check_data = json_decode( $check_response, true );
								if ( is_array( $check_data ) && ! empty( $check_data['data'] ) ) {
									$has_more = true;
								}
							}
						}

						// If we got less than a full page, we've reached the end
						if ( $new_cases_count < $cases_per_page ) {
							$has_more = false;
							break;
						}
					} else {
						break; // No more data
					}
				} else {
					break; // Failed to load
				}
			}

			// Generate HTML for the new cases
			$html = '';
			$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

			foreach ( $all_cases as $case ) {
				// Use reflection to access the private method from Shortcodes class
				$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
				$method->setAccessible( true );
				$html .= $method->invoke( null, $case, $image_display_mode, $has_nudity );
			}

			wp_send_json_success( [
				'html' => $html,
				'casesLoaded' => count( $all_cases ),
				'hasMore' => $has_more,
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load more cases.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * AJAX handler to load specific filtered cases.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_filtered_cases(): void {
		// Get case IDs from request
		$case_ids_str = isset( $_POST['case_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['case_ids'] ) ) : '';

		if ( empty( $case_ids_str ) ) {
			wp_send_json_error( [
				'message' => __( 'No case IDs provided.', 'brag-book-gallery' ),
			] );
			return;
		}

		// Convert to array of IDs
		$case_ids = array_map( 'trim', explode( ',', $case_ids_str ) );

		// Get API credentials
		$api_token = get_option( 'brag_book_gallery_api_token', '' );
		$website_property_id = get_option( 'brag_book_gallery_website_property_id', '' );

		if ( empty( $api_token ) || empty( $website_property_id ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
			return;
		}

		try {
			// Get cached cases data
			$cache_key = 'brag_book_cases_' . md5( $api_token . $website_property_id );
			$cached_data = get_transient( $cache_key );

			$html = '';
			$cases_found = 0;

			// Check for nudity in procedures
			$procedure_nudity_map = self::get_procedure_nudity_map();

			if ( $cached_data && isset( $cached_data['data'] ) ) {
				// Look for the requested cases in our cached data
				foreach ( $cached_data['data'] as $case ) {
					if ( in_array( $case['id'], $case_ids ) ) {
						// Get image display mode
						$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

						// Check if this case's procedure has nudity
						$case_nudity = false;
						if ( ! empty( $case['technique'] ) && isset( $procedure_nudity_map[ $case['technique'] ] ) ) {
							$case_nudity = $procedure_nudity_map[ $case['technique'] ];
						}

						// Render the case card using Shortcodes class method
						// Use reflection to access the private method
						$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
						$method->setAccessible( true );
						$html .= $method->invoke( null, $case, $image_display_mode, $case_nudity );
						$cases_found++;
					}
				}
			}

			wp_send_json_success( [
				'html' => $html,
				'casesFound' => $cases_found,
				'requestedCount' => count( $case_ids ),
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load filtered cases.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * Helper: Find a case by ID.
	 *
	 * @since 3.0.0
	 * @param string $case_id Case ID to find.
	 * @param string $api_token API token.
	 * @param int    $website_property_id Website property ID.
	 * @return array|null Case data or null if not found.
	 */
	private static function find_case_by_id( string $case_id, string $api_token, int $website_property_id ): ?array {
		// First, try to get the case from cached data
		$cache_key = 'brag_book_all_cases_' . md5( $api_token . $website_property_id );
		$cached_data = get_transient( $cache_key );
		$case_data = null;

		if ( $cached_data && isset( $cached_data['data'] ) && is_array( $cached_data['data'] ) ) {
			// Search for the case in cached data
			foreach ( $cached_data['data'] as $case ) {
				if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					return $case;
				}
			}
		}

		// If not found in cache, try to fetch all cases
		$all_cases = Data_Fetcher::get_all_cases_for_filtering( $api_token, $website_property_id );

		if ( ! empty( $all_cases['data'] ) ) {
			foreach ( $all_cases['data'] as $case ) {
				if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					return $case;
				}
			}
		}

		// Try using Endpoints as last resort
		$endpoints = new Endpoints();
		$filter_body = [
			'apiTokens' => [ $api_token ],
			'websitePropertyIds' => [ $website_property_id ],
			'count' => 1,
			'limit' => 100,
		];

		$response = $endpoints->bb_get_pagination_data( $filter_body );

		if ( ! empty( $response ) ) {
			$response_data = json_decode( $response, true );

			if ( ! empty( $response_data['data'] ) ) {
				foreach ( $response_data['data'] as $case ) {
					if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
						return $case;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Helper: Format case data for frontend.
	 *
	 * @since 3.0.0
	 * @param array  $case_data Case data from API.
	 * @param string $case_id Case ID.
	 * @return array Formatted case data.
	 */
	private static function format_case_data_for_frontend( array $case_data, string $case_id ): array {
		$procedure_names = [];
		if ( ! empty( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			$procedure_names[] = $case_data['technique'] ?? 'Procedure';
		}

		$formatted_data = [
			'caseNumber' => $case_data['id'] ?? $case_id,
			'procedureName' => ! empty( $procedure_names ) ? implode( ', ', $procedure_names ) : ( $case_data['technique'] ?? 'Case Details' ),
			'description' => $case_data['details'] ?? $case_data['description'] ?? '',
			'technique' => $case_data['technique'] ?? '',
			'gender' => $case_data['gender'] ?? '',
			'age' => $case_data['age'] ?? '',
			'photos' => [],
		];

		// Process photoSets
		if ( ! empty( $case_data['photoSets'] ) && is_array( $case_data['photoSets'] ) ) {
			foreach ( $case_data['photoSets'] as $photo_set ) {
				$before_image = $photo_set['postProcessedImageLocation'] ??
								$photo_set['beforeLocationUrl'] ??
								$photo_set['originalBeforeLocation'] ?? '';

				$after_image = $photo_set['afterLocationUrl1'] ??
							   $photo_set['originalAfterLocation1'] ?? '';

				if ( ! empty( $before_image ) || ! empty( $after_image ) ) {
					$formatted_data['photos'][] = [
						'beforeImage' => $before_image,
						'afterImage' => $after_image,
						'caption' => $photo_set['notes'] ?? '',
						'isProcessed' => ! empty( $photo_set['postProcessedImageLocation'] ),
					];
				}
			}
		}

		return $formatted_data;
	}

	/**
	 * Helper: Get procedure nudity map.
	 *
	 * @since 3.0.0
	 * @return array Map of procedure names to nudity boolean values.
	 */
	private static function get_procedure_nudity_map(): array {
		$procedure_nudity_map = [];
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		
		if ( ! empty( $api_tokens[0] ) ) {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );
			if ( ! empty( $sidebar_data['data'] ) ) {
				foreach ( $sidebar_data['data'] as $category ) {
					if ( ! empty( $category['procedures'] ) ) {
						foreach ( $category['procedures'] as $procedure ) {
							if ( ! empty( $procedure['nudity'] ) ) {
								$procedure_nudity_map[ $procedure['name'] ] = filter_var( $procedure['nudity'], FILTER_VALIDATE_BOOLEAN );
							}
						}
					}
				}
			}
		}
		
		return $procedure_nudity_map;
	}
}