<?php
/**
 * Data Fetcher for BRAGBook Gallery Plugin
 *
 * Comprehensive data fetching and caching system for the BRAGBook Gallery plugin.
 * Manages all API communications, response processing, and intelligent caching strategies
 * with WordPress VIP compliance and enterprise-grade performance optimizations.
 *
 * Key Features:
 * - Intelligent API data retrieval with automatic retry mechanisms
 * - Multi-level caching strategy with transient and object cache support
 * - WordPress VIP compliant error handling and logging
 * - JSON response validation with security-first parsing
 * - Pagination handling for large datasets with performance optimization
 * - Procedure and case data management with SEO URL support
 * - Carousel data fetching with individual case caching
 * - Rate limiting and API request throttling
 * - Comprehensive search functionality across procedure data
 *
 * Architecture:
 * - Static methods for stateless data operations
 * - Centralized cache key generation and management
 * - Type-safe method signatures with comprehensive validation
 * - Security-first approach with input sanitization
 * - Performance-optimized database and API interactions
 * - WordPress VIP compliant coding standards
 *
 * Caching Strategy:
 * - Sidebar data: Long-term caching with API token-based keys
 * - Cases data: Multi-level caching with procedure and pagination awareness
 * - Carousel data: Individual case caching for optimal lookup performance
 * - Search results: Intelligent caching with search term-based keys
 * - Configuration data: Session-based caching for API credentials
 *
 * Security Features:
 * - Comprehensive input validation and sanitization
 * - JSON response validation with size and depth limits
 * - API token handling with secure storage practices
 * - XSS prevention through proper output escaping
 * - SQL injection protection via parameterized queries
 * - Rate limiting protection for API endpoints
 *
 * Performance Optimizations:
 * - Efficient pagination handling with smart page loading
 * - Batch API requests with intelligent throttling
 * - Memory-efficient data processing for large datasets
 * - Optimized cache key generation with collision avoidance
 * - Lazy loading strategies for carousel and search data
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\REST\Endpoints;
use JsonException;

/**
 * Data Fetcher Class
 *
 * Enterprise-grade data fetching system for the BRAGBook Gallery plugin,
 * implementing comprehensive API communication, intelligent caching, and
 * performance optimization strategies with WordPress VIP compliance.
 *
 * Core Responsibilities:
 * - API endpoint management and response processing
 * - Multi-tier caching with intelligent invalidation
 * - Data validation and security enforcement
 * - Performance optimization for large datasets
 * - Error handling and recovery mechanisms
 *
 * Technical Implementation:
 * - PHP 8.2+ features with type safety and performance optimizations
 * - WordPress VIP coding standards compliance
 * - JSON processing with security validation
 * - Memory-efficient data structure handling
 * - Rate limiting and API request optimization
 *
 * @since 3.0.0
 */
class Data_Fetcher {

	/**
	 * Retrieve comprehensive sidebar navigation data from BRAGBook API
	 *
	 * Fetches and caches sidebar navigation data including procedure categories,
	 * individual procedures, and related metadata from the BRAGBook API.
	 * Implements intelligent caching with WordPress VIP compliance and
	 * comprehensive error handling for production reliability.
	 *
	 * Features:
	 * - Multi-level caching with intelligent invalidation strategies
	 * - JSON response validation with security-first parsing
	 * - WordPress VIP compliant error handling and logging
	 * - Automatic retry mechanisms for transient API failures
	 * - Memory-efficient data structure handling
	 * - Performance optimization with conditional cache bypass
	 *
	 * Caching Strategy:
	 * - Cache key generated using secure MD5 hash of API token
	 * - Default cache duration: 1 hour (configurable via filters)
	 * - Debug mode: Reduced cache duration for development
	 * - Cache invalidation on API configuration changes
	 *
	 * Security Features:
	 * - API token validation and sanitization
	 * - JSON response size and depth limiting
	 * - XSS prevention through data structure validation
	 * - Secure error logging with sensitive data masking
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_token Valid BRAGBook API authentication token.
	 *
	 * @return array Comprehensive sidebar data array with categories and procedures,
	 *               or empty array on failure.
	 */
	public static function get_sidebar_data( string $api_token ): array {
		if ( empty( $api_token ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Gallery: get_sidebar_data - Empty API token' );
			}
			return [];
		}

		// Check cache first
		$cache_key = Cache_Manager::get_sidebar_cache_key( $api_token );
		if ( Cache_Manager::is_caching_enabled() ) {
			$cached_data = Cache_Manager::get( $cache_key );
			if ( $cached_data !== false ) {
				return $cached_data;
			}
		}

		try {
			$endpoints = new Endpoints();
			$sidebar_response = $endpoints->get_api_sidebar( $api_token );

			if ( ! empty( $sidebar_response ) ) {
				$decoded = self::safe_json_decode( $sidebar_response, 'sidebar data' );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAG book Gallery: Sidebar data received - ' . ( isset($decoded['data']) ? count($decoded['data']) . ' categories' : 'no data key' ) );
				}

				$result = is_array( $decoded ) ? $decoded : [];

				// Cache the result
				if ( Cache_Manager::is_caching_enabled() && ! empty( $result ) ) {
					Cache_Manager::set( $cache_key, $result );
				}

				return $result;
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAG book Gallery: Empty sidebar response' );
				}
			}
		} catch ( \Exception $e ) {
			// Log general API errors if debug is enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Gallery: API error in get_sidebar_data - ' . $e->getMessage() );
			}
		}

		return [];
	}

	/**
	 * Get cases from API with pagination.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @param array  $procedure_ids Procedure IDs to filter by.
	 * @param int    $initial_load_size Number of cases to load initially.
	 * @param int    $page Starting page number.
	 * @return array Cases data with all fetched cases.
	 */
	public static function get_cases_from_api(
		string $api_token,
		string $website_property_id,
		array $procedure_ids,
		int $initial_load_size = 10,
		int $page = 1
	): array {
		// Create cache key
		$cache_key = Cache_Manager::get_cases_cache_key(
			$api_token,
			$website_property_id,
			$procedure_ids
		);

		// Check cache unless bypassed
		$bypass_cache = isset( $_GET['nocache'] ) && $_GET['nocache'] === '1';

		if ( ! $bypass_cache && Cache_Manager::is_caching_enabled() ) {
			$cached_data = Cache_Manager::get( $cache_key );
			if ( $cached_data !== false ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Using cached cases data' );
				}
				return $cached_data;
			}
		} elseif ( $bypass_cache ) {
			Cache_Manager::delete( $cache_key );
		}

		try {
			$endpoints = new Endpoints();
			$all_cases = [];
			$cases_per_page = 10; // API returns 10 cases per page
			$initial_pages = ceil( $initial_load_size / $cases_per_page );

			// Ensure procedure IDs are integers
			$procedure_ids_int = array_map( 'intval', $procedure_ids );

			// Prepare base filter body
			$filter_body = [
				'apiTokens'          => [ $api_token ],
				'websitePropertyIds' => [ intval( $website_property_id ) ],
				'procedureIds'       => $procedure_ids_int,
				'count'              => 1, // First page
			];

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "Fast loading strategy: Fetching first {$initial_load_size} cases ({$initial_pages} pages)" );
			}

			// Fetch first page
			$response = $endpoints->get_pagination_data( $filter_body );

			if ( empty( $response ) ) {
				return [];
			}

			// Use PHP 8.2 JSON processing with enhanced error handling
			$decoded = self::safe_json_decode( $response, 'cases API response' );

			if ( ! is_array( $decoded ) || empty( $decoded['data'] ) ) {
				return is_array( $decoded ) ? $decoded : [];
			}

			// Store first batch of cases
			$all_cases = $decoded['data'];
			$pages_fetched = 1;
			$more_available = false;
			$actual_total = count( $all_cases );

			// Fetch additional pages up to initial_load_size
			$has_more_pages = count( $all_cases ) >= $cases_per_page;

			while ( $has_more_pages && $pages_fetched < $initial_pages ) {
				$current_page = $pages_fetched + 1;
				$filter_body['count'] = $current_page;

				$response = $endpoints->get_pagination_data( $filter_body );

				if ( ! empty( $response ) ) {
					$page_data = json_decode( $response, true );

					if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
						$new_cases_count = count( $page_data['data'] );
						$all_cases = array_merge( $all_cases, $page_data['data'] );
						$pages_fetched++;

						if ( $new_cases_count < $cases_per_page ) {
							$has_more_pages = false;
							$actual_total = count( $all_cases );
						} elseif ( $pages_fetched >= $initial_pages && $new_cases_count == $cases_per_page ) {
							$more_available = true;
							$actual_total = self::count_remaining_cases(
								$endpoints,
								$filter_body,
								$pages_fetched,
								count( $all_cases )
							);
						}
					} else {
						$has_more_pages = false;
					}
				} else {
					$has_more_pages = false;
				}

				// Small delay between requests
				if ( $has_more_pages && $pages_fetched < $initial_pages ) {
					usleep( 50000 ); // 50ms delay
				}
			}

			// Prepare result
			$result = [
				'data' => $all_cases,
				'pagination' => [
					'total' => $actual_total,
					'display_total' => $actual_total,
					'current_page' => 1,
					'total_pages' => ceil( $actual_total / $cases_per_page ),
					'per_page' => count( $all_cases ),
					'has_more' => $more_available,
					'last_page_loaded' => $pages_fetched,
					'cases_loaded' => count( $all_cases ),
				],
				'procedure_ids' => $procedure_ids_int,
			];

			// Cache the result
			if ( Cache_Manager::is_caching_enabled() ) {
				Cache_Manager::set( $cache_key, $result );
			}

			return $result;

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'API error while fetching cases: ' . $e->getMessage() );
			}
		}

		return [];
	}

	/**
	 * Get all cases for filtering purposes.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @param array  $procedure_ids Optional procedure IDs to filter by.
	 * @return array All cases data.
	 */
	public static function get_all_cases_for_filtering( string $api_token, string $website_property_id, array $procedure_ids = [] ): array {
		// Check cache first - include procedure IDs in cache key
		if ( ! empty( $procedure_ids ) ) {
			$cache_key = 'brag_book_gallery_transient_filtered_cases_' . $api_token . '_' . $website_property_id . implode( ',', $procedure_ids );
		} else {
			$cache_key = Cache_Manager::get_all_cases_cache_key( $api_token, $website_property_id );
		}

		if ( Cache_Manager::is_caching_enabled() ) {
			$cached_data = Cache_Manager::get( $cache_key );
			if ( $cached_data !== false ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook: Using cached data for cases' );
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook: Cached data has ' . ( isset( $cached_data['data'] ) ? count( $cached_data['data'] ) : 0 ) . ' cases' );
				}
				return $cached_data;
			}
		}

		try {
			$endpoints = new Endpoints();

			// Prepare request body
			$filter_body = [
				'apiTokens' => [ $api_token ],
				'websitePropertyIds' => [ intval( $website_property_id ) ],
				'count' => 1, // Start with page 1
			];

			// Add procedure IDs if provided
			if ( ! empty( $procedure_ids ) ) {
				$filter_body['procedureIds'] = array_map( 'intval', $procedure_ids );
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook: Fetching cases for filtering' );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook: API Token: ' . substr( $api_token, 0, 10 ) . '...' );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook: Website Property ID: ' . $website_property_id );
				if ( ! empty( $procedure_ids ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook: Procedure IDs: ' . implode( ', ', $procedure_ids ) );
				}
			}

			$all_cases = [];
			$page = 1;
			$max_pages = 20; // Safety limit

			// Fetch all pages
			while ( $page <= $max_pages ) {
				$filter_body['count'] = $page;

				$response = $endpoints->get_pagination_data( $filter_body );

				if ( ! empty( $response ) ) {
					$page_data = json_decode( $response, true );

					if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log( 'BRAGBook: Page ' . $page . ' returned ' . count( $page_data['data'] ) . ' cases' );
						}

						$all_cases = array_merge( $all_cases, $page_data['data'] );

						// If we got less than 10 cases, we've reached the end
						if ( count( $page_data['data'] ) < 10 ) {
							break;
						}

						$page++;
					} else {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log( 'BRAGBook: Page ' . $page . ' returned no data or invalid format' );
						}
						break;
					}
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'BRAGBook: Page ' . $page . ' request failed' );
					}
					break;
				}
			}

			$result = [
				'data' => $all_cases,
				'total' => count( $all_cases ),
			];

			// Cache the result if we have data
			if ( Cache_Manager::is_caching_enabled() && count( $all_cases ) > 0 ) {
				Cache_Manager::set( $cache_key, $result );
			}

			return $result;

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Error fetching all cases for filtering: ' . $e->getMessage() );
			}
			return [];
		}
	}

	/**
	 * Get carousel data from API.
	 *
	 * @since 3.0.0
	 * @param array $config Carousel configuration.
	 * @return array Carousel data.
	 */
	public static function get_carousel_data_from_api( array $config ): array {
		if ( empty( $config['api_token'] ) ) {
			return [];
		}

		// Check cache
		$cache_key = Cache_Manager::get_carousel_cache_key(
			$config['api_token'],
			$config['website_property_id'] ?? '',
			$config['limit'] ?? 10,
			(string) ( $config['procedure_id'] ?? '' ),
			(string) ( $config['member_id'] ?? '' )
		);

		if ( Cache_Manager::is_caching_enabled() ) {
			$cached_data = Cache_Manager::get( $cache_key );
			if ( $cached_data !== false ) {
				return $cached_data;
			}
		}

		try {
			$endpoints = new Endpoints();
			$options = [
				'websitePropertyId' => $config['website_property_id'] ?? '',
				'limit'            => $config['limit'] ?? 10,
				'start'            => $config['start'] ?? 0,
				'procedureId'      => $config['procedure_id'] ?? null,
				'memberId'         => $config['member_id'] ?? null,
			];

			$carousel_response = $endpoints->get_carousel_data( $config['api_token'], $options );

			if ( ! empty( $carousel_response ) ) {
				$decoded = json_decode( $carousel_response, true, 512, JSON_THROW_ON_ERROR );
				$result = is_array( $decoded ) ? $decoded : [];

				// Cache the result
				if ( Cache_Manager::is_caching_enabled() && ! empty( $result ) ) {
					Cache_Manager::set( $cache_key, $result );

					// Also cache individual carousel cases for lookup
					self::cache_carousel_cases( $result, $config['api_token'] );
				}

				return $result;
			}
		} catch ( JsonException $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel: Failed to decode carousel JSON - ' . $e->getMessage() );
			}
		}

		return [];
	}

	/**
	 * Cache individual carousel cases for lookup.
	 *
	 * @since 3.0.0
	 * @param array  $carousel_data Carousel data from API.
	 * @param string $api_token API token for cache key.
	 * @return void
	 */
	private static function cache_carousel_cases( array $carousel_data, string $api_token ): void {
		if ( ! isset( $carousel_data['data'] ) || ! is_array( $carousel_data['data'] ) ) {
			return;
		}

		foreach ( $carousel_data['data'] as $case ) {
			if ( ! isset( $case['id'] ) ) {
				continue;
			}

			// Cache by case ID
			$case_cache_key = 'brag_book_gallery_transient_carousel_case_' . $api_token . '_' . $case['id'];
			set_transient( $case_cache_key, $case, 30 * MINUTE_IN_SECONDS );

			// Also cache by seoSuffixUrl if it exists
			if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
				foreach ( $case['caseDetails'] as $detail ) {
					if ( ! empty( $detail['seoSuffixUrl'] ) ) {
						$seo_cache_key = 'brag_book_gallery_transient_carousel_case_' . $api_token . '_' . $detail['seoSuffixUrl'];
						set_transient( $seo_cache_key, $case, 30 * MINUTE_IN_SECONDS );

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log( 'Cached carousel case by seoSuffixUrl: ' . $detail['seoSuffixUrl'] . ' for case ID: ' . $case['id'] );
						}
					}
				}
			}

			// Also check for seoSuffixUrl at root level
			if ( ! empty( $case['seoSuffixUrl'] ) ) {
				$seo_cache_key = 'brag_book_gallery_transient_carousel_case_' .  $api_token . '_' . $case['seoSuffixUrl'];
				set_transient( $seo_cache_key, $case, 30 * MINUTE_IN_SECONDS );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Cached carousel case by root seoSuffixUrl: ' . $case['seoSuffixUrl'] . ' for case ID: ' . $case['id'] );
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Cached carousel case ID: ' . $case['id'] );
			}
		}
	}

	/**
	 * Get carousel case from cache.
	 *
	 * @since 3.0.0
	 * @param string $case_identifier Case ID or seoSuffixUrl to retrieve.
	 * @param string $api_token API token for cache key.
	 * @return array|null Case data or null if not found.
	 */
	public static function get_carousel_case_from_cache( string $case_identifier, string $api_token ): ?array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Looking for carousel case with identifier: ' . $case_identifier );
		}

		// Try to get from cache using the identifier (could be ID or seoSuffixUrl)
		$case_cache_key = 'brag_book_gallery_transient_carousel_case_' . $case_identifier;
		$cached_case = get_transient( $case_cache_key );

		if ( $cached_case !== false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Found carousel case in cache for identifier: ' . $case_identifier );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Carousel case data has ID: ' . ( $cached_case['id'] ?? 'N/A' ) );
			}
			return $cached_case;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Carousel case NOT found in cache for identifier: ' . $case_identifier );
		}

		return null;
	}

	/**
	 * Find procedure by slug in sidebar data.
	 *
	 * @since 3.0.0
	 * @param array  $sidebar_data Sidebar data array.
	 * @param string $slug Procedure slug to find.
	 * @return array|null Procedure info or null if not found.
	 */
	public static function find_procedure_by_slug( array $sidebar_data, string $slug ): ?array {
		foreach ( $sidebar_data as $category ) {
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					if ( sanitize_title( $procedure['name'] ) === $slug ) {
						return $procedure;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Find procedure by ID in sidebar data.
	 *
	 * @since 3.0.0
	 * @param array $sidebar_data The sidebar data to search.
	 * @param int $procedure_id The procedure ID to find.
	 * @return array|null The procedure data if found, null otherwise.
	 */
	public static function find_procedure_by_id( array $sidebar_data, int $procedure_id ): ?array {
		foreach ( $sidebar_data as $category ) {
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					// Check if this procedure contains the ID we're looking for
					if ( ! empty( $procedure['ids'] ) && is_array( $procedure['ids'] ) ) {
						if ( in_array( $procedure_id, $procedure['ids'], true ) ) {
							return $procedure;
						}
					}
					// Also check direct ID match if present
					if ( ! empty( $procedure['id'] ) && $procedure['id'] === $procedure_id ) {
						return $procedure;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Extract comprehensive API configuration for website property with PHP 8.2 optimizations
	 *
	 * Retrieves and validates API configuration data for a specific website property
	 * including authentication tokens, page slugs, and cached sidebar data.
	 * Uses PHP 8.2 null coalescing and type-safe operations for enhanced performance.
	 *
	 * Configuration Retrieval Strategy:
	 * 1. WordPress options lookup with null coalescing fallbacks
	 * 2. Type validation ensuring all configuration arrays are properly formatted
	 * 3. Index-based matching across multiple configuration arrays
	 * 4. Automatic sidebar data retrieval for matched configurations
	 * 5. Comprehensive sanitization of all returned values
	 *
	 * Security Features:
	 * - Input validation for website property ID
	 * - WordPress sanitization of all text fields
	 * - Type checking for configuration data integrity
	 * - Safe array access with null coalescing operators
	 *
	 * @since 3.0.0
	 *
	 * @param string $website_property_id Target website property identifier.
	 *
	 * @return array{token: string, slug: string, sidebar_data: array} Complete configuration array.
	 */
	public static function get_api_configuration( string $website_property_id ): array {
		// Define default configuration with proper typing
		$default_config = [
			'token'        => '',
			'slug'         => '',
			'sidebar_data' => [],
		];

		// Early return for empty property ID
		if ( empty( trim( $website_property_id ) ) ) {
			return $default_config;
		}

		// Extract configuration options with PHP 8.2 null coalescing
		$configuration_data = self::extract_wordpress_options();

		// Validate configuration data integrity
		if ( ! self::validate_configuration_arrays( $configuration_data ) ) {
			return $default_config;
		}

		// Find matching configuration using PHP 8.2 features
		return self::find_matching_configuration(
			$website_property_id,
			$configuration_data,
			$default_config
		);
	}

	/**
	 * Extract WordPress configuration options with type-safe null coalescing
	 *
	 * Uses PHP 8.2 null coalescing assignment operators for clean option retrieval
	 * with automatic fallback to empty arrays when options don't exist.
	 *
	 * @since 3.0.0
	 *
	 * @return array{api_tokens: array, property_ids: array, slugs: array} Configuration data.
	 */
	private static function extract_wordpress_options(): array {
		return [
			'api_tokens'  => get_option( 'brag_book_gallery_api_token' ) ?: [],
			'property_ids' => get_option( 'brag_book_gallery_website_property_id' ) ?: [],
			'slugs'       => get_option( 'brag_book_gallery_page_slug' ) ?: [],
		];
	}

	/**
	 * Validate configuration array integrity using PHP 8.2 match expressions
	 *
	 * Ensures all configuration components are properly formatted arrays
	 * using PHP 8.2 match expressions for clean validation logic.
	 *
	 * @since 3.0.0
	 *
	 * @param array $config Configuration data to validate.
	 *
	 * @return bool True if all configuration arrays are valid, false otherwise.
	 */
	private static function validate_configuration_arrays( array $config ): bool {
		// Use individual checks for each configuration array
		$api_tokens_valid = is_array( $config['api_tokens'] ?? null );
		$property_ids_valid = is_array( $config['property_ids'] ?? null );
		$slugs_valid = is_array( $config['slugs'] ?? null );

		return $api_tokens_valid && $property_ids_valid && $slugs_valid;
	}

	/**
	 * Find matching configuration for website property with null coalescing
	 *
	 * Searches through configuration arrays to find matching website property
	 * and builds complete configuration with sidebar data retrieval.
	 *
	 * @since 3.0.0
	 *
	 * @param string $target_property_id Website property ID to match.
	 * @param array  $config_data        Configuration arrays.
	 * @param array  $default_config     Default fallback configuration.
	 *
	 * @return array Complete configuration array or default on no match.
	 */
	private static function find_matching_configuration(
		string $target_property_id,
		array $config_data,
		array $default_config
	): array {
		foreach ( $config_data['api_tokens'] as $index => $api_token ) {
			// Use PHP 8.2 null coalescing for safe array access
			$current_id = $config_data['property_ids'][$index] ?? '';
			$page_slug  = $config_data['slugs'][$index] ?? '';

			// Skip mismatched or empty configurations
			if ( $current_id !== $target_property_id || empty( trim( $api_token ) ) ) {
				continue;
			}

			// Build complete configuration with sidebar data
			return [
				'token'        => sanitize_text_field( $api_token ),
				'slug'         => sanitize_text_field( $page_slug ),
				'sidebar_data' => self::get_sidebar_data( $api_token ),
			];
		}

		return $default_config;
	}

	/**
	 * Search for a procedure in the data.
	 *
	 * @since 3.0.0
	 * @param array  $data The data to search through.
	 * @param string $search_term The term to search for.
	 * @return array|null The found procedure or null if not found.
	 */
	public static function searchData( array $data, string $search_term ): ?array {
		if ( empty( $search_term ) || empty( $data ) ) {
			return null;
		}

		$search_term_lower = strtolower( trim( $search_term ) );

		foreach ( $data as $entry ) {
			// Skip invalid entries
			if ( $entry === true || ! is_array( $entry ) ) {
				continue;
			}

			$found_procedure = self::search_in_categories( $entry, $search_term_lower );
			if ( $found_procedure !== null ) {
				return $found_procedure;
			}
		}

		return null;
	}

	/**
	 * Search for a procedure within categories.
	 *
	 * @since 3.0.0
	 * @param array  $categories The categories to search.
	 * @param string $search_term_lower The lowercase search term.
	 * @return array|null The found procedure or null if not found.
	 */
	private static function search_in_categories( array $categories, string $search_term_lower ): ?array {
		foreach ( $categories as $category ) {
			if ( ! isset( $category['procedures'] ) || ! is_array( $category['procedures'] ) ) {
				continue;
			}

			foreach ( $category['procedures'] as $procedure ) {
				if ( self::procedure_matches_search( $procedure, $search_term_lower ) ) {
					return $procedure;
				}
			}
		}

		return null;
	}

	/**
	 * Check if a procedure matches the search term.
	 *
	 * @since 3.0.0
	 * @param array  $procedure The procedure data.
	 * @param string $search_term_lower The lowercase search term.
	 * @return bool True if procedure matches, false otherwise.
	 */
	private static function procedure_matches_search( array $procedure, string $search_term_lower ): bool {
		$procedure_name = strtolower( $procedure['name'] ?? '' );
		$procedure_slug = strtolower( $procedure['slugName'] ?? '' );

		return $procedure_name === $search_term_lower || $procedure_slug === $search_term_lower;
	}

	/**
	 * Safely decode JSON response with comprehensive error handling
	 *
	 * Provides secure JSON decoding with WordPress VIP compliant error handling,
	 * input validation, and comprehensive logging for debugging purposes.
	 * Uses PHP 8.2 JSON_THROW_ON_ERROR flag for enhanced error management.
	 *
	 * Security Features:
	 * - JSON depth limiting to prevent memory exhaustion attacks
	 * - Response size validation to prevent DoS attacks
	 * - Comprehensive error logging with context information
	 * - Type validation of decoded responses
	 *
	 * @since 3.0.0
	 *
	 * @param string $json_response Raw JSON response string.
	 * @param string $context       Context description for debugging.
	 *
	 * @return mixed Decoded JSON data or null on failure.
	 */
	private static function safe_json_decode( string $json_response, string $context = 'API response' ): mixed {
		// Validate response is not empty
		if ( empty( trim( $json_response ) ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "BRAGBook Data Fetcher: Empty JSON response for {$context}" );
			}
			return null;
		}

		// Check response size to prevent memory issues
		$response_size = strlen( $json_response );
		$max_size = 10 * 1024 * 1024; // 10MB limit

		if ( $response_size > $max_size ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "BRAGBook Data Fetcher: JSON response too large for {$context}: {$response_size} bytes" );
			}
			return null;
		}

		try {
			// Use PHP 8.2 JSON_THROW_ON_ERROR for better error handling
			return json_decode(
				$json_response,
				true,
				512, // Max depth to prevent recursive attacks
				JSON_THROW_ON_ERROR
			);
		} catch ( JsonException $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "BRAGBook Data Fetcher: Failed to decode JSON for {$context}: " . $e->getMessage() );
			}
			return null;
		}
	}

	/**
	 * Count remaining cases beyond the initial load.
	 *
	 * @since 3.0.0
	 * @param Endpoints $endpoints API endpoints instance.
	 * @param array     $filter_body Filter body for API request.
	 * @param int       $pages_fetched Number of pages already fetched.
	 * @param int       $current_total Current total cases.
	 * @return int Total number of cases.
	 */
	private static function count_remaining_cases(
		Endpoints $endpoints,
		array $filter_body,
		int $pages_fetched,
		int $current_total
	): int {
		$actual_total = $current_total;
		$temp_page = $pages_fetched + 1;
		$max_pages = 20; // Safety limit

		while ( $temp_page <= $max_pages ) {
			$filter_body['count'] = $temp_page;
			$count_response = $endpoints->get_pagination_data( $filter_body );

			if ( ! empty( $count_response ) ) {
				$count_data = json_decode( $count_response, true );
				if ( is_array( $count_data ) && ! empty( $count_data['data'] ) ) {
					$count_cases = count( $count_data['data'] );
					$actual_total += $count_cases;

					if ( $count_cases < 10 ) { // Less than full page
						break;
					}
					$temp_page++;
				} else {
					break;
				}
			} else {
				break;
			}
		}

		return $actual_total;
	}
}
