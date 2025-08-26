<?php
/**
 * Cases Shortcode Handler Class
 *
 * Manages cases and case details shortcode functionality.
 * Handles display of case grids, single case views, and filtering.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Slug_Helper;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cases Shortcode Handler Class
 *
 * Manages the [brag_book_gallery_cases] and [brag_book_gallery_case] shortcodes.
 * Provides case display functionality with filtering and single case views.
 *
 * @since 3.0.0
 */
final class Cases_Shortcode_Handler {

	/**
	 * Default cases per page
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_CASES_LIMIT = 20;

	/**
	 * Default grid columns
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_COLUMNS = 3;

	/**
	 * Cache group for cases data
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_cases';

	/**
	 * Cache expiration time in seconds
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600; // 1 hour

	/**
	 * Handle the cases shortcode
	 *
	 * Displays cases from the API with optional filtering by procedure.
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Cases HTML or error message.
	 */
	public static function handle( array $atts ): string {
		// Parse shortcode attributes.
		$atts = shortcode_atts(
			array(
				'api_token'           => '',
				'website_property_id' => '',
				'procedure_ids'       => '',
				'limit'               => self::DEFAULT_CASES_LIMIT,
				'page'                => 1,
				'columns'             => self::DEFAULT_COLUMNS,
				'show_details'        => 'true',
				'class'               => '',
			),
			$atts,
			'brag_book_gallery_cases'
		);

		// Get API configuration if not provided.
		$atts = self::get_api_configuration( $atts );

		// Get filter from URL if present.
		$filter_procedure = sanitize_text_field( get_query_var( 'filter_procedure', '' ) );
		$procedure_title  = sanitize_text_field( get_query_var( 'procedure_title', '' ) );
		$case_suffix      = sanitize_text_field( get_query_var( 'case_suffix', '' ) );

		// If we have procedure_title but not filter_procedure (case detail URL), use procedure_title for filtering.
		if ( empty( $filter_procedure ) && ! empty( $procedure_title ) ) {
			$filter_procedure = $procedure_title;
		}

		// Debug logging if enabled.
		self::debug_log_query_vars( $filter_procedure, $procedure_title, $case_suffix, $atts );

		// Use case_suffix which now contains both numeric IDs and SEO suffixes.
		$case_identifier = ! empty( $case_suffix ) ? $case_suffix : '';

		// If we have a case identifier, show single case.
		if ( ! empty( $case_identifier ) ) {
			return self::render_single_case( $case_identifier, $atts );
		}

		// Validate required fields.
		if ( empty( $atts['api_token'] ) || empty( $atts['website_property_id'] ) ) {
			return sprintf(
				'<p class="brag-book-gallery-cases-error">%s</p>',
				esc_html__( 'Please configure API settings to display cases.', 'brag-book-gallery' )
			);
		}

		// Get procedure IDs based on filter.
		$procedure_ids = self::get_procedure_ids_for_filter( $filter_procedure, $atts );

		// When filtering by procedure, load ALL cases for that procedure to enable proper filtering.
		$initial_load_size = ! empty( $filter_procedure ) ? 200 : 10;

		// Get cases from API.
		$cases_data = Data_Fetcher::get_cases_from_api(
			$atts['api_token'],
			$atts['website_property_id'],
			$procedure_ids,
			$initial_load_size,
			absint( $atts['page'] )
		);

		// Enqueue cases assets.
		Asset_Manager::enqueue_cases_assets();

		// Render cases grid.
		return self::render_cases_grid( $cases_data, $atts, $filter_procedure );
	}

	/**
	 * Handle the case details shortcode
	 *
	 * Displays a single case with all its details.
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Case details HTML or error message.
	 */
	public static function handle_case_details( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'case_id'   => '',
				'procedure' => '',
			),
			$atts,
			'brag_book_gallery_case'
		);

		// If no case_id provided, try to get from URL.
		if ( empty( $atts['case_id'] ) ) {
			// Get case_suffix which now contains both numeric IDs and SEO suffixes.
			$atts['case_id'] = sanitize_text_field( get_query_var( 'case_suffix' ) );
		}

		if ( empty( $atts['case_id'] ) ) {
			return '<div class="brag-book-gallery-error">' . esc_html__( 'Case ID not specified.', 'brag-book-gallery' ) . '</div>';
		}

		// Get API configuration.
		$atts = self::get_api_configuration( $atts );

		// Render the single case.
		return self::render_single_case( $atts['case_id'], $atts );
	}

	/**
	 * Get API configuration from options
	 *
	 * Retrieves API token and website property ID from plugin settings.
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return array Updated attributes with API configuration.
	 */
	private static function get_api_configuration( array $atts ): array {
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
			if ( is_array( $api_tokens ) && ! empty( $api_tokens[0] ) ) {
				$atts['api_token'] = sanitize_text_field( $api_tokens[0] );
			} elseif ( is_string( $api_tokens ) ) {
				$atts['api_token'] = sanitize_text_field( $api_tokens );
			}
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );
			if ( is_array( $website_property_ids ) && ! empty( $website_property_ids[0] ) ) {
				$atts['website_property_id'] = sanitize_text_field( $website_property_ids[0] );
			} elseif ( is_string( $website_property_ids ) ) {
				$atts['website_property_id'] = sanitize_text_field( $website_property_ids );
			}
		}

		return $atts;
	}

	/**
	 * Debug log query variables
	 *
	 * Logs query variables for debugging when WP_DEBUG is enabled.
	 *
	 * @since 3.0.0
	 *
	 * @param string $filter_procedure Filter procedure slug.
	 * @param string $procedure_title  Procedure title from URL.
	 * @param string $case_suffix      Case suffix from URL.
	 * @param array  $atts             Shortcode attributes.
	 *
	 * @return void
	 */
	private static function debug_log_query_vars( string $filter_procedure, string $procedure_title, string $case_suffix, array $atts ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( 'Cases Shortcode Debug:' );
		error_log( 'filter_procedure: ' . $filter_procedure );
		error_log( 'procedure_title: ' . $procedure_title );
		error_log( 'case_suffix: ' . $case_suffix );
		error_log( 'API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) );
		error_log( 'Website Property ID: ' . $atts['website_property_id'] );
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Debug logging only.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : 'N/A';
		error_log( 'Current URL: ' . esc_url_raw( $request_uri ) );
	}

	/**
	 * Get procedure IDs for filtering
	 *
	 * Determines procedure IDs based on filter or shortcode attributes.
	 *
	 * @since 3.0.0
	 *
	 * @param string $filter_procedure Procedure slug to filter by.
	 * @param array  $atts             Shortcode attributes.
	 *
	 * @return array Array of procedure IDs.
	 */
	private static function get_procedure_ids_for_filter( string $filter_procedure, array $atts ): array {
		$procedure_ids = array();

		if ( ! empty( $filter_procedure ) ) {
			// Try to find matching procedure in sidebar data.
			$sidebar_data = Data_Fetcher::get_sidebar_data( $atts['api_token'] );
			
			if ( ! empty( $sidebar_data['data'] ) ) {
				$procedure_info = Data_Fetcher::find_procedure_by_slug( $sidebar_data['data'], $filter_procedure );
				
				// Use 'ids' array which contains all procedure IDs for this procedure type.
				if ( ! empty( $procedure_info['ids'] ) && is_array( $procedure_info['ids'] ) ) {
					$procedure_ids = array_map( 'intval', $procedure_info['ids'] );
					
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Cases shortcode - Found procedure IDs for ' . $filter_procedure . ': ' . implode( ',', $procedure_ids ) );
						error_log( 'Total case count from sidebar: ' . ( $procedure_info['totalCase'] ?? 0 ) );
					}
				} elseif ( ! empty( $procedure_info['id'] ) ) {
					// Fallback to single 'id' if 'ids' array doesn't exist.
					$procedure_ids = array( intval( $procedure_info['id'] ) );
					
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Cases shortcode - Using single procedure ID for ' . $filter_procedure . ': ' . $procedure_info['id'] );
					}
				}
			}
		} elseif ( ! empty( $atts['procedure_ids'] ) ) {
			$procedure_ids = array_map( 'intval', explode( ',', $atts['procedure_ids'] ) );
		}

		return $procedure_ids;
	}

	/**
	 * Render cases grid
	 *
	 * Generates HTML for cases grid display.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $cases_data       Cases data from API.
	 * @param array  $atts             Shortcode attributes.
	 * @param string $filter_procedure Procedure filter if any.
	 *
	 * @return string HTML output.
	 */
	public static function render_cases_grid( array $cases_data, array $atts, string $filter_procedure = '' ): string {
		if ( empty( $cases_data ) || empty( $cases_data['data'] ) ) {
			return sprintf(
				'<p class="brag-book-gallery-cases-no-data">%s</p>',
				esc_html__( 'No cases found.', 'brag-book-gallery' )
			);
		}

		$cases        = $cases_data['data'];
		$columns      = absint( $atts['columns'] ) ?: self::DEFAULT_COLUMNS;
		$show_details = filter_var( $atts['show_details'], FILTER_VALIDATE_BOOLEAN );

		// Start output with proper container structure.
		$output = '<div class="brag-book-gallery-cases-container">';
		$output .= '<div class="brag-book-gallery-cases-grid" data-columns="' . esc_attr( $columns ) . '">';

		// Get image display mode setting.
		$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

		foreach ( $cases as $case ) {
			// Render each case card.
			$output .= self::render_case_card(
				$case,
				$image_display_mode,
				false,
				$filter_procedure
			);
		}

		$output .= '</div>'; // Close .brag-book-gallery-cases-grid
		$output .= '</div>'; // Close .brag-book-gallery-cases-container

		// Add pagination if available.
		if ( ! empty( $cases_data['pagination'] ) ) {
			$gallery_slug = Slug_Helper::get_first_gallery_page_slug( 'gallery' );
			$base_path    = get_site_url() . '/' . $gallery_slug;
			$output      .= self::render_pagination( $cases_data['pagination'], $base_path, $filter_procedure );
		}

		return $output;
	}

	/**
	 * Render single case
	 *
	 * Displays a single case with all its details.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case identifier (ID or SEO suffix).
	 * @param array  $atts    Shortcode attributes.
	 *
	 * @return string HTML output.
	 */
	public static function render_single_case( string $case_id, array $atts ): string {
		// Sanitize case ID.
		$case_id = sanitize_text_field( $case_id );

		// Debug logging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'render_single_case - Looking for case ID: ' . $case_id );
			error_log( 'API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) );
			error_log( 'Website Property ID: ' . $atts['website_property_id'] );
		}

		// Try to get from cache first.
		$case_data = self::get_case_from_cache( $case_id, $atts );

		// If not in cache, fetch from API.
		if ( ! $case_data ) {
			$case_data = self::fetch_case_from_api( $case_id, $atts );
		}

		if ( ! $case_data ) {
			return sprintf(
				'<div class="brag-book-gallery-case-not-found">%s</div>',
				esc_html__( 'Case not found', 'brag-book-gallery' )
			);
		}

		// Enqueue cases assets.
		Asset_Manager::enqueue_cases_assets();

		// Render the case details.
		return self::render_case_details( $case_data );
	}

	/**
	 * Get case from cache
	 *
	 * Attempts to retrieve a case from cached data.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case identifier.
	 * @param array  $atts    Shortcode attributes.
	 *
	 * @return array|null Case data or null if not found.
	 */
	private static function get_case_from_cache( string $case_id, array $atts ): ?array {
		$cache_key   = 'brag_book_all_cases_' . md5( $atts['api_token'] . $atts['website_property_id'] );
		$cached_data = get_transient( $cache_key );

		// Check if cache exists and has valid data.
		if ( ! $cached_data || ! isset( $cached_data['data'] ) || ! is_array( $cached_data['data'] ) || empty( $cached_data['data'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'No valid cached data available' );
			}
			return null;
		}

		// Search for the case in cached data.
		foreach ( $cached_data['data'] as $case ) {
			// Check by ID (loose comparison to handle string/int mismatch).
			if ( isset( $case['id'] ) && ( strval( $case['id'] ) === strval( $case_id ) ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Case found in cache by ID!' );
				}
				return $case;
			}

			// Also check by SEO suffix if available.
			if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
				foreach ( $case['caseDetails'] as $detail ) {
					if ( ! empty( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'Case found in cache by SEO suffix!' );
						}
						return $case;
					}
				}
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Case not found in cache. Total cached cases: ' . count( $cached_data['data'] ) );
		}

		return null;
	}

	/**
	 * Fetch case from API
	 *
	 * Retrieves a single case from the API.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case identifier.
	 * @param array  $atts    Shortcode attributes.
	 *
	 * @return array|null Case data or null if not found.
	 */
	private static function fetch_case_from_api( string $case_id, array $atts ): ?array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Fetching case from API...' );
		}

		// Try to get all cases (which might give us the case we need).
		$all_cases = Data_Fetcher::get_all_cases_for_filtering(
			$atts['api_token'],
			$atts['website_property_id']
		);

		if ( ! empty( $all_cases['data'] ) ) {
			foreach ( $all_cases['data'] as $case ) {
				// Check by ID.
				if ( isset( $case['id'] ) && ( strval( $case['id'] ) === strval( $case_id ) ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Case found via API by ID!' );
					}
					return $case;
				}

				// Check by SEO suffix.
				if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
					foreach ( $case['caseDetails'] as $detail ) {
						if ( ! empty( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( 'Case found via API by SEO suffix!' );
							}
							return $case;
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Render case details
	 *
	 * Generates HTML for single case display.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return string HTML output.
	 */
	private static function render_case_details( array $case_data ): string {
		ob_start();
		?>
		<div class="brag-book-gallery-single-case">
			<div class="case-images">
				<?php
				if ( ! empty( $case_data['photoSets'] ) ) {
					foreach ( $case_data['photoSets'] as $photo_set ) {
						if ( ! empty( $photo_set['beforePhoto'] ) ) {
							?>
							<div class="case-image before-image">
								<img src="<?php echo esc_url( $photo_set['beforePhoto'] ); ?>" 
									 alt="<?php esc_attr_e( 'Before', 'brag-book-gallery' ); ?>" />
								<span class="image-label"><?php esc_html_e( 'Before', 'brag-book-gallery' ); ?></span>
							</div>
							<?php
						}
						if ( ! empty( $photo_set['afterPhoto'] ) ) {
							?>
							<div class="case-image after-image">
								<img src="<?php echo esc_url( $photo_set['afterPhoto'] ); ?>" 
									 alt="<?php esc_attr_e( 'After', 'brag-book-gallery' ); ?>" />
								<span class="image-label"><?php esc_html_e( 'After', 'brag-book-gallery' ); ?></span>
							</div>
							<?php
						}
					}
				}
				?>
			</div>

			<div class="case-details">
				<?php if ( ! empty( $case_data['description'] ) ) : ?>
					<div class="case-description">
						<?php echo wp_kses_post( $case_data['description'] ); ?>
					</div>
				<?php endif; ?>

				<div class="case-meta">
					<?php if ( ! empty( $case_data['age'] ) ) : ?>
						<div class="meta-item">
							<span class="meta-label"><?php esc_html_e( 'Age:', 'brag-book-gallery' ); ?></span>
							<span class="meta-value"><?php echo esc_html( $case_data['age'] ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $case_data['gender'] ) ) : ?>
						<div class="meta-item">
							<span class="meta-label"><?php esc_html_e( 'Gender:', 'brag-book-gallery' ); ?></span>
							<span class="meta-value"><?php echo esc_html( $case_data['gender'] ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $case_data['ethnicity'] ) ) : ?>
						<div class="meta-item">
							<span class="meta-label"><?php esc_html_e( 'Ethnicity:', 'brag-book-gallery' ); ?></span>
							<span class="meta-value"><?php echo esc_html( $case_data['ethnicity'] ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		
		return ob_get_clean();
	}

	/**
	 * Render case card for main gallery AJAX
	 *
	 * Generates HTML specifically for main gallery case cards loaded via AJAX.
	 * This uses the exact structure expected by the main gallery JavaScript.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case                Case data.
	 * @param string $image_display_mode  Image display mode.
	 * @param bool   $procedure_nudity    Whether procedure has nudity.
	 * @param string $procedure_context   Procedure context from filter.
	 *
	 * @return string HTML output.
	 */
	public static function render_ajax_case_card( array $case, string $image_display_mode, bool $procedure_nudity = false, string $procedure_context = '' ): string {
		$html = '';
		$case_id = sanitize_text_field( $case['id'] ?? '' );
		
		if ( empty( $case_id ) ) {
			return '';
		}
		
		// Prepare data attributes
		$data_attrs = array(
			'data-card="true"',
			'data-case-id="' . esc_attr( $case_id ) . '"',
		);
		
		// Add filtering attributes
		if ( ! empty( $case['age'] ) ) {
			$data_attrs[] = 'data-age="' . esc_attr( $case['age'] ) . '"';
		}
		if ( ! empty( $case['gender'] ) ) {
			$data_attrs[] = 'data-gender="' . esc_attr( strtolower( $case['gender'] ) ) . '"';
		}
		if ( ! empty( $case['ethnicity'] ) ) {
			$data_attrs[] = 'data-ethnicity="' . esc_attr( strtolower( $case['ethnicity'] ) ) . '"';
		}
		
		// Add procedure IDs
		$procedure_ids = '';
		if ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'absint', $case['procedureIds'] ) );
			$data_attrs[] = 'data-procedure-ids="' . esc_attr( $procedure_ids ) . '"';
		}
		
		// Get case URL
		$gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( 'gallery' );
		$seo_suffix = '';
		
		// Extract SEO suffix if available
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );
			$seo_suffix = ! empty( $first_detail['seoSuffixUrl'] ) ? $first_detail['seoSuffixUrl'] : $case_id;
		} else {
			$seo_suffix = $case_id;
		}
		
		$procedure_slug = ! empty( $procedure_context ) ? sanitize_title( $procedure_context ) : 'case';
		$case_url = home_url( '/' . $gallery_slug . '/' . $procedure_slug . '/' . $seo_suffix );
		
		// Start article
		$html .= '<article class="brag-book-gallery-case-card" ' . implode( ' ', $data_attrs ) . '>';
		
		// Image section
		$html .= '<div class="brag-book-gallery-case-images single-image">';
		$html .= '<div class="brag-book-gallery-single-image">';
		$html .= '<div class="brag-book-gallery-image-container">';
		
		// Skeleton loader
		$html .= '<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>';
		
		// Item actions (favorites button)
		$html .= '<div class="brag-book-gallery-item-actions">';
		$html .= '<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Add to favorites">';
		$html .= '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
		$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>';
		$html .= '</svg>';
		$html .= '</button>';
		$html .= '</div>';
		
		// Case link with image
		$html .= '<a href="' . esc_url( $case_url ) . '" class="brag-book-gallery-case-card-link" data-case-id="' . esc_attr( $case_id ) . '" data-procedure-ids="' . esc_attr( $procedure_ids ) . '">';
		
		// Get main image URL - try multiple possible data structures
		$main_image_url = '';
		$alt_text = ' - Case ' . $case_id;
		
		// Try photoSets first (most common structure)
		if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
			$first_photo = reset( $case['photoSets'] );
			// Try various field names used by the API
			if ( ! empty( $first_photo['postProcessedImageLocation'] ) ) {
				$main_image_url = $first_photo['postProcessedImageLocation'];
			} elseif ( ! empty( $first_photo['afterLocationUrl1'] ) ) {
				$main_image_url = $first_photo['afterLocationUrl1'];
			} elseif ( ! empty( $first_photo['beforeLocationUrl'] ) ) {
				$main_image_url = $first_photo['beforeLocationUrl'];
			} elseif ( ! empty( $first_photo['afterPhoto'] ) ) {
				$main_image_url = $first_photo['afterPhoto'];
			} elseif ( ! empty( $first_photo['beforePhoto'] ) ) {
				$main_image_url = $first_photo['beforePhoto'];
			}
		}
		
		// Fallback to afterImage/beforeImage properties (direct on case object)
		if ( empty( $main_image_url ) ) {
			if ( ! empty( $case['afterImage'] ) ) {
				$main_image_url = $case['afterImage'];
			} elseif ( ! empty( $case['beforeImage'] ) ) {
				$main_image_url = $case['beforeImage'];
			}
		}
		
		// Fallback to mainImageUrl property
		if ( empty( $main_image_url ) && ! empty( $case['mainImageUrl'] ) ) {
			$main_image_url = $case['mainImageUrl'];
		}
		
		// Try to find any image URL in caseDetails if still empty
		if ( empty( $main_image_url ) && ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			foreach ( $case['caseDetails'] as $detail ) {
				if ( ! empty( $detail['afterPhoto'] ) ) {
					$main_image_url = $detail['afterPhoto'];
					break;
				} elseif ( ! empty( $detail['beforePhoto'] ) ) {
					$main_image_url = $detail['beforePhoto'];
					break;
				}
			}
		}
		
		// Always render the picture/img tags, even if empty (for consistent structure)
		$html .= '<picture class="brag-book-gallery-picture">';
		if ( ! empty( $main_image_url ) ) {
			$html .= '<img src="' . esc_url( $main_image_url ) . '" alt="' . esc_attr( $alt_text ) . '" loading="lazy" ';
			$html .= 'data-image-type="single" data-image-url="' . esc_url( $main_image_url ) . '" ';
			$html .= 'onload="this.closest(\'.brag-book-gallery-image-container\').querySelector(\'.brag-book-gallery-skeleton-loader\').style.display=\'none\';">';
		}
		$html .= '</picture>';
		
		$html .= '</a>'; // Close case-card-link
		$html .= '</div>'; // Close image-container
		$html .= '</div>'; // Close single-image
		$html .= '</div>'; // Close case-images
		
		// Case details section
		$html .= '<details class="brag-book-gallery-case-card-details">';
		
		// Summary
		$html .= '<summary class="brag-book-gallery-case-card-summary">';
		$html .= '<div class="brag-book-gallery-case-card-summary-info">';
		
		// Get procedure name - use the context if provided (for filtered views)
		$procedure_name = 'Case';
		
		// If we have a procedure context (from filtered gallery view), use that
		if ( ! empty( $procedure_context ) ) {
			// Convert slug back to proper case title (e.g., 'facelift' -> 'Facelift')
			$procedure_name = ucwords( str_replace( '-', ' ', $procedure_context ) );
		} else {
			// Otherwise fall back to the case's actual procedure
			if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
				$first_procedure = reset( $case['procedures'] );
				$procedure_name = $first_procedure['name'] ?? 'Case';
			}
		}
		
		$html .= '<span class="brag-book-gallery-case-card-summary-info__name">' . esc_html( $procedure_name ) . '</span>';
		$html .= '<span class="brag-book-gallery-case-card-summary-info__case-number">Case #' . esc_html( $case_id ) . '</span>';
		$html .= '</div>';
		
		$html .= '<div class="brag-book-gallery-case-card-summary-details">';
		$html .= '<p class="brag-book-gallery-case-card-summary-details__more">';
		$html .= '<strong>More Details</strong> ';
		$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
		$html .= '<path d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>';
		$html .= '</svg>';
		$html .= '</p>';
		$html .= '</div>';
		$html .= '</summary>';
		
		// Details content
		$html .= '<div class="brag-book-gallery-case-card-details-content">';
		$html .= '<p class="brag-book-gallery-case-card-details-content__title">Procedures Performed:</p>';
		$html .= '<ul class="brag-book-gallery-case-card-procedures-list">';
		
		if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			foreach ( $case['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$html .= '<li class="brag-book-gallery-case-card-procedures-list__item">' . esc_html( $procedure['name'] ) . '</li>';
				}
			}
		}
		
		$html .= '</ul>';
		$html .= '</div>';
		$html .= '</details>';
		
		$html .= '</article>';
		
		return $html;
	}
	
	/**
	 * Render case card for grid display
	 *
	 * Generates HTML for a single case card in the grid.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case                Case data.
	 * @param string $image_display_mode  Image display mode.
	 * @param bool   $procedure_nudity    Whether procedure has nudity.
	 * @param string $procedure_context   Procedure context from filter.
	 *
	 * @return string HTML output.
	 */
	public static function render_case_card( array $case, string $image_display_mode, bool $procedure_nudity = false, string $procedure_context = '' ): string {
		$html = '';

		// Prepare data attributes for filtering.
		$data_attrs = self::prepare_case_data_attributes( $case );

		// Get case ID and SEO information.
		$case_info = self::extract_case_info( $case );

		// Get procedure IDs for this case.
		$procedure_ids = '';
		if ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'intval', $case['procedureIds'] ) );
		}

		$html .= sprintf(
			'<article class="brag-book-gallery-case-card" %s data-case-id="%s" data-procedure-ids="%s">',
			$data_attrs,
			esc_attr( $case_info['case_id'] ),
			esc_attr( $procedure_ids )
		);

		// Add nudity warning if needed.
		if ( $procedure_nudity ) {
			$html .= self::render_nudity_warning();
		}

		// Get case URL.
		$case_url = self::get_case_url( $case_info, $procedure_context, $case );

		// Add case content.
		$html .= '<a href="' . esc_url( $case_url ) . '" class="case-link">';

		// Add images.
		if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
			$first_photo = reset( $case['photoSets'] );
			
			if ( 'before_after' === $image_display_mode ) {
				// Show both before and after images.
				$html .= '<div class="case-images before-after">';
				if ( ! empty( $first_photo['beforePhoto'] ) ) {
					$html .= sprintf(
						'<img src="%s" alt="%s" class="before-image" />',
						esc_url( $first_photo['beforePhoto'] ),
						esc_attr__( 'Before', 'brag-book-gallery' )
					);
				}
				if ( ! empty( $first_photo['afterPhoto'] ) ) {
					$html .= sprintf(
						'<img src="%s" alt="%s" class="after-image" />',
						esc_url( $first_photo['afterPhoto'] ),
						esc_attr__( 'After', 'brag-book-gallery' )
					);
				}
				$html .= '</div>';
			} else {
				// Show single image (after preferred, fallback to before).
				$image_url = $first_photo['afterPhoto'] ?? $first_photo['beforePhoto'] ?? '';
				if ( ! empty( $image_url ) ) {
					$html .= sprintf(
						'<div class="case-image"><img src="%s" alt="%s" /></div>',
						esc_url( $image_url ),
						esc_attr__( 'Case Image', 'brag-book-gallery' )
					);
				}
			}
		}

		// Add case title if available.
		if ( ! empty( $case_info['seo_headline'] ) ) {
			$html .= '<h3 class="case-title">' . esc_html( $case_info['seo_headline'] ) . '</h3>';
		}

		$html .= '</a>';
		$html .= '</article>';

		return $html;
	}

	/**
	 * Prepare case data attributes
	 *
	 * Prepares data attributes for case card filtering.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return string Data attributes HTML.
	 */
	private static function prepare_case_data_attributes( array $case ): string {
		$attrs = 'data-card="true"';

		// Add age.
		if ( ! empty( $case['age'] ) ) {
			$attrs .= ' data-age="' . esc_attr( $case['age'] ) . '"';
		}

		// Add gender.
		if ( ! empty( $case['gender'] ) ) {
			$attrs .= ' data-gender="' . esc_attr( strtolower( $case['gender'] ) ) . '"';
		}

		// Add ethnicity.
		if ( ! empty( $case['ethnicity'] ) ) {
			$attrs .= ' data-ethnicity="' . esc_attr( strtolower( $case['ethnicity'] ) ) . '"';
		}

		// Add height with unit.
		if ( ! empty( $case['height'] ) ) {
			$height_value = $case['height'];
			$height_unit  = ! empty( $case['heightUnit'] ) ? $case['heightUnit'] : '';
			$attrs       .= ' data-height="' . esc_attr( $height_value ) . '"';
			$attrs       .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
			$attrs       .= ' data-height-full="' . esc_attr( $height_value . $height_unit ) . '"';
		}

		// Add weight with unit.
		if ( ! empty( $case['weight'] ) ) {
			$weight_value = $case['weight'];
			$weight_unit  = ! empty( $case['weightUnit'] ) ? $case['weightUnit'] : '';
			$attrs       .= ' data-weight="' . esc_attr( $weight_value ) . '"';
			$attrs       .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
			$attrs       .= ' data-weight-full="' . esc_attr( $weight_value . $weight_unit ) . '"';
		}

		return $attrs;
	}

	/**
	 * Extract case information
	 *
	 * Extracts case ID and SEO information from case data.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return array Case information array.
	 */
	private static function extract_case_info( array $case ): array {
		$info = array(
			'case_id'              => $case['id'] ?? '',
			'seo_suffix_url'       => '',
			'seo_headline'         => '',
			'seo_page_title'       => '',
			'seo_page_description' => '',
		);

		// Extract SEO fields from caseDetails if available.
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );
			
			if ( empty( $info['case_id'] ) ) {
				$info['case_id'] = $first_detail['caseId'] ?? '';
			}
			
			$info['seo_suffix_url']       = $first_detail['seoSuffixUrl'] ?? '';
			$info['seo_headline']         = $first_detail['seoHeadline'] ?? '';
			$info['seo_page_title']       = $first_detail['seoPageTitle'] ?? '';
			$info['seo_page_description'] = $first_detail['seoPageDescription'] ?? '';
		}

		// Use seoSuffixUrl for URL if available, otherwise use case_id.
		$info['url_suffix'] = ! empty( $info['seo_suffix_url'] ) ? $info['seo_suffix_url'] : $info['case_id'];

		return $info;
	}

	/**
	 * Get case URL
	 *
	 * Generates the URL for a case detail page.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case_info         Case information.
	 * @param string $procedure_context Procedure context.
	 * @param array  $case              Full case data.
	 *
	 * @return string Case URL.
	 */
	private static function get_case_url( array $case_info, string $procedure_context, array $case ): string {
		// Get query vars.
		$filter_procedure = sanitize_text_field( get_query_var( 'filter_procedure', '' ) );
		$procedure_title  = sanitize_text_field( get_query_var( 'procedure_title', '' ) );

		// Determine procedure slug.
		$procedure_slug = '';
		
		// First priority: use procedure context passed from AJAX filter.
		if ( ! empty( $procedure_context ) ) {
			$procedure_slug = sanitize_title( $procedure_context );
		} elseif ( ! empty( $filter_procedure ) ) {
			$procedure_slug = $filter_procedure;
		} elseif ( ! empty( $procedure_title ) ) {
			$procedure_slug = $procedure_title;
		} else {
			// Parse current URL to get procedure slug.
			$procedure_slug = self::extract_procedure_from_url( $case );
		}

		// Build the URL.
		$gallery_slug = Slug_Helper::get_first_gallery_page_slug( 'gallery' );
		$base_url     = home_url( '/' . $gallery_slug );

		return sprintf(
			'%s/%s/%s/',
			$base_url,
			$procedure_slug,
			$case_info['url_suffix']
		);
	}

	/**
	 * Extract procedure from URL
	 *
	 * Extracts procedure slug from the current URL or case data.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return string Procedure slug.
	 */
	private static function extract_procedure_from_url( array $case ): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URL parsing only.
		$current_url  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$gallery_slug = Slug_Helper::get_first_gallery_page_slug( 'gallery' );

		// Extract procedure from URL pattern.
		$pattern = '/' . preg_quote( $gallery_slug, '/' ) . '\/([^\/]+)(?:\/|$)/';
		if ( preg_match( $pattern, $current_url, $matches ) && ! empty( $matches[1] ) ) {
			return sanitize_title( $matches[1] );
		}

		// Fallback: extract procedure name from case data.
		if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			$first_procedure = reset( $case['procedures'] );
			$procedure_name  = $first_procedure['name'] ?? 'case';
			return sanitize_title( $procedure_name );
		}

		return 'case';
	}

	/**
	 * Render nudity warning
	 *
	 * Generates HTML for nudity warning overlay.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML output.
	 */
	private static function render_nudity_warning(): string {
		ob_start();
		?>
		<div class="brag-book-gallery-nudity-warning">
			<div class="brag-book-gallery-nudity-warning-content">
				<h4 class="brag-book-gallery-nudity-warning-title">
					<?php esc_html_e( 'Nudity Warning', 'brag-book-gallery' ); ?>
				</h4>
				<p class="brag-book-gallery-nudity-warning-caption">
					<?php esc_html_e( 'This procedure may contain nudity or sensitive content. Click to proceed if you wish to view.', 'brag-book-gallery' ); ?>
				</p>
				<button class="brag-book-gallery-nudity-warning-button">
					<?php esc_html_e( 'Proceed', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render pagination
	 *
	 * Generates pagination HTML for cases grid.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $pagination       Pagination data.
	 * @param string $base_path        Base URL path.
	 * @param string $filter_procedure Filter procedure if any.
	 *
	 * @return string HTML output.
	 */
	private static function render_pagination( array $pagination, string $base_path, string $filter_procedure ): string {
		$current_page = absint( $pagination['currentPage'] ?? 1 );
		$total_pages  = absint( $pagination['totalPages'] ?? 1 );

		if ( $total_pages <= 1 ) {
			return '';
		}

		ob_start();
		?>
		<div class="brag-book-gallery-pagination">
			<?php
			// Previous link.
			if ( $current_page > 1 ) {
				$prev_url = $base_path;
				if ( $filter_procedure ) {
					$prev_url .= '/' . $filter_procedure;
				}
				$prev_url .= '?page=' . ( $current_page - 1 );
				?>
				<a href="<?php echo esc_url( $prev_url ); ?>" class="prev-page">
					<?php esc_html_e( '&laquo; Previous', 'brag-book-gallery' ); ?>
				</a>
				<?php
			}

			// Page numbers.
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				if ( $i === $current_page ) {
					?>
					<span class="current-page"><?php echo esc_html( (string) $i ); ?></span>
					<?php
				} else {
					$page_url = $base_path;
					if ( $filter_procedure ) {
						$page_url .= '/' . $filter_procedure;
					}
					$page_url .= '?page=' . $i;
					?>
					<a href="<?php echo esc_url( $page_url ); ?>" class="page-number">
						<?php echo esc_html( (string) $i ); ?>
					</a>
					<?php
				}
			}

			// Next link.
			if ( $current_page < $total_pages ) {
				$next_url = $base_path;
				if ( $filter_procedure ) {
					$next_url .= '/' . $filter_procedure;
				}
				$next_url .= '?page=' . ( $current_page + 1 );
				?>
				<a href="<?php echo esc_url( $next_url ); ?>" class="next-page">
					<?php esc_html_e( 'Next &raquo;', 'brag-book-gallery' ); ?>
				</a>
				<?php
			}
			?>
		</div>
		<?php
		
		return ob_get_clean();
	}

	/**
	 * Clear cases cache
	 *
	 * Clears all cached cases data.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		wp_cache_flush_group( self::CACHE_GROUP );
	}
}