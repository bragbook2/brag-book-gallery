<?php
/**
 * Single Case Handler for WordPress-based case display
 *
 * Handles the [brag_book_gallery_case] shortcode that displays
 * a single case using WP_Query with the brag_book_cases post type.
 *
 * @package BRAGBookGallery
 * @subpackage Shortcodes
 * @since 3.0.0
 */

namespace BRAGBookGallery\Includes\Shortcodes;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Core\Trait_Api;

/**
 * Single Case Handler class
 *
 * Generates single case display using WordPress post data instead of API data.
 * Uses the same HTML structure as Cases_Handler for consistency.
 *
 * @since 3.0.0
 */
class Case_Handler {
	use Trait_Api;

	/**
	 * Missing data log
	 *
	 * @var array
	 */
	private array $missing_data_log = [];

	/**
	 * Initialize the case handler
	 *
	 * Sets up shortcode registration and hooks.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function __construct() {
		add_shortcode( 'brag_book_gallery_case', [ $this, 'render_case_shortcode' ] );

		// Hook into wp_enqueue_scripts to check for our shortcode and enqueue assets
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ], 5 );

		// Register AJAX handlers for case details loading with tracking
		add_action( 'wp_ajax_brag_book_gallery_load_case_details_html', [ $this, 'ajax_load_case_details_html' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_case_details_html', [ $this, 'ajax_load_case_details_html' ] );
	}

	/**
	 * Check if case shortcode is present and enqueue assets
	 *
	 * Detects if the brag_book_gallery_case shortcode is present on the current page
	 * and enqueues only the necessary CSS. JavaScript is not needed for WordPress
	 * post-based case pages since they don't use API functionality.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function maybe_enqueue_assets(): void {
		global $post;

		// Check if we have a post and if it contains our shortcode
		if ( ! $post || ! has_shortcode( $post->post_content, 'brag_book_gallery_case' ) ) {
			return;
		}

		// Only enqueue CSS for styling - no JavaScript needed for WordPress post-based cases
		$plugin_version = Setup::get_plugin_version();

		// Enqueue CSS using Setup class asset URL method
		wp_enqueue_style(
			'brag-book-gallery',
			Setup::get_asset_url( 'assets/css/brag-book-gallery.css' ),
			[],
			$plugin_version
		);

		// Note: JavaScript is intentionally not enqueued for WordPress post-based case pages
		// to prevent unnecessary API calls. The case data is already rendered server-side.
	}

	/**
	 * Render case content only (for Gallery_Handler delegation)
	 *
	 * Renders only the case detail content without the full wrapper,
	 * used when Gallery_Handler delegates main content area rendering.
	 *
	 * @since 3.0.0
	 * @param string $case_id The case ID to render.
	 * @return string Generated case content HTML.
	 */
	public function render_case_content_only( string $case_id ): string {

		// Get case post by case_id
		$case_post = $this->get_case_by_case_id( $case_id );

		if ( ! $case_post ) {
			return '<div class="brag-book-gallery-error">Case not found.</div>';
		}

		// Get case data
		$case_data = $this->get_case_meta_data( $case_post->ID );
		$procedures = $this->get_case_procedures( $case_post->ID );

		// Build case data array
		$case_array = [
			'id' => $case_post->ID, // Use WordPress post ID for routing consistency
			'api_case_id' => $case_data['case_id'], // Keep API case ID available for API calls
			'patientAge' => $case_data['patient_age'],
			'patientGender' => $case_data['patient_gender'],
			'patientEthnicity' => $case_data['patient_ethnicity'] ?: '',
			'patientHeight' => $case_data['patient_height'] ?: '',
			'patientWeight' => $case_data['patient_weight'] ?: '',
			'procedureDate' => $case_data['procedure_date'],
			'caseNotes' => $case_data['case_notes'],
			'beforeImage' => $case_data['before_image'],
			'afterImage' => $case_data['after_image'],
			'procedureIds' => wp_list_pluck( $procedures, 'procedure_id' ),
			'procedures' => $procedures,
		];

		// Return only the case detail view content
		return $this->render_case_detail_view( $case_array, $case_post );
	}

	/**
	 * Render the case shortcode
	 *
	 * Processes the [brag_book_gallery_case] shortcode and returns
	 * the generated case HTML using WordPress post data.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Generated case HTML.
	 */
	public function render_case_shortcode( $atts = [] ): string {
		// Parse shortcode attributes with defaults
		$atts = shortcode_atts(
			[
				'case_id' => '',
				'post_id' => '',
			],
			$atts,
			'brag_book_gallery_case'
		);

		// Get case by case_id or post_id, or use current post as fallback
		$case_post = null;
		if ( ! empty( $atts['case_id'] ) ) {
			$case_post = $this->get_case_by_case_id( $atts['case_id'] );
		} elseif ( ! empty( $atts['post_id'] ) ) {
			$case_post = get_post( intval( $atts['post_id'] ) );
		} else {
			// Fallback to current post if no parameters provided
			global $post;
			if ( $post && $post->post_type === Post_Types::POST_TYPE_CASES ) {
				$case_post = $post;
			}
		}

		// Ensure we have a valid case post
		if ( ! $case_post ) {
			return '<p>Case not found.</p>';
		}

		// Generate case HTML directly
		$output = $this->generate_case_html( $case_post );

		// Prevent wpautop from adding unwanted <p> and <br> tags to shortcode output
		return $output;
	}

	/**
	 * Get case post by case_id meta value
	 *
	 * @since 3.0.0
	 * @param string $case_id The case ID to search for.
	 * @return \WP_Post|null The case post or null if not found.
	 */
	private function get_case_by_case_id( string $case_id ): ?\WP_Post {
		// Treat case_id as WordPress post ID
		$post_id = get_the_ID();

		if ( $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );

		// Verify it's a case post type and published
		if ( ! $post || $post->post_type !== Post_Types::POST_TYPE_CASES || $post->post_status !== 'publish' ) {
			return null;
		}

		return $post;
	}

	/**
	 * Generate case HTML with full gallery wrapper structure
	 *
	 * Creates the complete gallery wrapper with sidebar and case detail view.
	 *
	 * @since 3.0.0
	 * @param \WP_Post $case_post The case post object.
	 * @return string Generated case HTML with full wrapper.
	 */
	private function generate_case_html( \WP_Post $case_post ): string {
		// Get post meta data
		$case_data = $this->get_case_meta_data( $case_post->ID );

		// Get procedure information
		$procedures = $this->get_case_procedures( $case_post->ID );

		// Build case data array similar to API format
		$case_array = [
			'id' => $case_post->ID, // Use WordPress post ID for routing consistency
			'api_case_id' => $case_data['case_id'], // Keep API case ID available for API calls
			'patientAge' => $case_data['patient_age'],
			'patientGender' => $case_data['patient_gender'],
			'patientEthnicity' => $case_data['patient_ethnicity'] ?: '',
			'patientHeight' => $case_data['patient_height'] ?: '',
			'patientWeight' => $case_data['patient_weight'] ?: '',
			'procedureDate' => $case_data['procedure_date'],
			'caseNotes' => $case_data['case_notes'],
			'beforeImage' => $case_data['before_image'],
			'afterImage' => $case_data['after_image'],
			'procedureIds' => wp_list_pluck( $procedures, 'procedure_id' ),
			'procedures' => $procedures,
		];

		// Generate the complete gallery wrapper with sidebar and case detail view
		return $this->render_full_gallery_wrapper( $case_array, $case_post );
	}

	/**
	 * Get case meta data with missing data logging
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @return array Case meta data with defaults.
	 */
	private function get_case_meta_data( int $post_id ): array {
		// Try new format first, fall back to legacy format
		$meta_fields = [
			'case_id' => ['brag_book_gallery_case_id'],
			'patient_age' => ['brag_book_gallery_patient_age'],
			'patient_gender' => ['brag_book_gallery_patient_gender'],
			'patient_ethnicity' => ['brag_book_gallery_ethnicity'],
			'patient_height' => ['brag_book_gallery_height'],
			'patient_weight' => ['brag_book_gallery_weight'],
			'procedure_date' => ['brag_book_gallery_procedure_date'],
			'case_notes' => ['brag_book_gallery_notes'],
			'before_image' => ['brag_book_gallery_before_image'],
			'after_image' => ['brag_book_gallery_after_image'],
		];

		$case_data = [];
		foreach ( $meta_fields as $field => $meta_keys ) {
			$value = '';
			// Try each meta key until we find a value
			foreach ( $meta_keys as $meta_key ) {
				$value = get_post_meta( $post_id, $meta_key, true );
				if ( ! empty( $value ) || $value === '0' ) {
					break;
				}
			}

			if ( empty( $value ) && $value !== '0' ) {
				$this->log_missing_data( $post_id, $field );
			}
			$case_data[ $field ] = $value;
		}

		// Get image URLs from new meta fields
		$case_data['post_processed_urls'] = $this->get_urls_from_meta( $post_id, 'brag_book_gallery_case_post_processed_url' );
		$case_data['before_urls'] = $this->get_urls_from_meta( $post_id, 'brag_book_gallery_case_before_url' );
		$case_data['after_urls1'] = $this->get_urls_from_meta( $post_id, 'brag_book_gallery_case_after_url1' );
		$case_data['after_urls2'] = $this->get_urls_from_meta( $post_id, 'brag_book_gallery_case_after_url2' );
		$case_data['after_urls3'] = $this->get_urls_from_meta( $post_id, 'brag_book_gallery_case_after_url3' );

		return $case_data;
	}

	/**
	 * Get URLs from semicolon-separated meta field
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @param string $meta_key The meta key to retrieve.
	 * @return array Array of URLs.
	 */
	private function get_urls_from_meta( int $post_id, string $meta_key ): array {
		$urls_string = get_post_meta( $post_id, $meta_key, true );

		// Debug: Log what we're getting from meta
		error_log( "Case Handler Debug: Getting URLs from meta key '{$meta_key}' for post {$post_id}" );
		error_log( "Case Handler Debug: Raw meta value: " . var_export( $urls_string, true ) );
		error_log( "Case Handler Debug: String length: " . strlen( $urls_string ) );

		if ( empty( $urls_string ) ) {
			error_log( "Case Handler Debug: Empty URLs string for meta key '{$meta_key}'" );
			return [];
		}

		// Normalize line endings and split by various delimiters
		$normalized = str_replace( [ "\r\n", "\r" ], "\n", $urls_string );
		$lines = preg_split( '/[\n;]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$urls = [];

		foreach ( $lines as $line ) {
			$clean_url = trim( $line );

			// Debug each individual URL attempt
			error_log( "Case Handler Debug: Processing line: '{$clean_url}'" );

			if ( ! empty( $clean_url ) ) {
				if ( filter_var( $clean_url, FILTER_VALIDATE_URL ) ) {
					$urls[] = $clean_url;
					error_log( "Case Handler Debug: Valid URL added: '{$clean_url}'" );
				} else {
					error_log( "Case Handler Debug: Invalid URL rejected: '{$clean_url}'" );
				}
			}
		}

		error_log( "Case Handler Debug: Extracted " . count( $urls ) . " valid URLs from meta key '{$meta_key}'" );
		if ( ! empty( $urls ) ) {
			error_log( "Case Handler Debug: Final URLs: " . wp_json_encode( $urls ) );
		}

		return $urls;
	}

	/**
	 * Get case procedures from taxonomy
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @return array Array of procedure data.
	 */
	private function get_case_procedures( int $post_id ): array {
		$procedure_terms = wp_get_post_terms( $post_id, Taxonomies::TAXONOMY_PROCEDURES );

		$procedures = [];
		$added_procedure_ids = [];

		// First, add procedures from taxonomy terms
		if ( ! is_wp_error( $procedure_terms ) && ! empty( $procedure_terms ) ) {
			foreach ( $procedure_terms as $term ) {
				$procedure_meta = $this->get_procedure_meta( $term->term_id );
				$proc_id = $procedure_meta['procedure_id'] ?: $term->term_id;

				$procedures[] = [
					'id' => $proc_id,
					'procedure_id' => $proc_id,
					'name' => $term->name,
					'slug' => $term->slug,
					'seoSuffixUrl' => $term->slug,
					'nudity' => 'true' === $procedure_meta['nudity'],
				];

				$added_procedure_ids[] = $proc_id;
			}
		}

		// Second, add procedures from post meta that aren't already included
		$meta_procedure_ids = get_post_meta( $post_id, 'brag_book_gallery_procedure_ids', true );

		if ( ! empty( $meta_procedure_ids ) ) {
			// Handle both comma-separated string and array formats
			$procedure_ids_array = is_array( $meta_procedure_ids )
				? $meta_procedure_ids
				: array_filter( array_map( 'trim', explode( ',', $meta_procedure_ids ) ) );

			foreach ( $procedure_ids_array as $procedure_id ) {
				// Skip if already added from taxonomy
				if ( in_array( $procedure_id, $added_procedure_ids, true ) ) {
					continue;
				}

				// Find the taxonomy term with this procedure_id in term meta
				$term = $this->get_term_by_procedure_id( $procedure_id );

				if ( $term ) {
					$procedure_meta = $this->get_procedure_meta( $term->term_id );
					$procedures[] = [
						'id' => $procedure_id,
						'procedure_id' => $procedure_id,
						'name' => $term->name,
						'slug' => $term->slug,
						'seoSuffixUrl' => $term->slug,
						'nudity' => 'true' === $procedure_meta['nudity'],
					];

					$added_procedure_ids[] = $procedure_id;
				}
			}
		}

		// Log if no procedures found at all
		if ( empty( $procedures ) ) {
			$this->log_missing_data( $post_id, 'procedures_taxonomy' );
		}

		return $procedures;
	}

	/**
	 * Get procedure metadata
	 *
	 * @since 3.0.0
	 * @param int $term_id The term ID.
	 * @return array Procedure meta data with defaults.
	 */
	private function get_procedure_meta( int $term_id ): array {
		return [
			'procedure_id' => get_term_meta( $term_id, 'procedure_id', true ) ?: '',
			'member_id'    => get_term_meta( $term_id, 'member_id', true ) ?: '',
			'nudity'       => get_term_meta( $term_id, 'nudity', true ) ?: 'false',
			'banner_image' => get_term_meta( $term_id, 'banner_image', true ) ?: '',
		];
	}

	/**
	 * Find taxonomy term by procedure ID stored in term meta
	 *
	 * @since 3.3.1
	 * @param string|int $procedure_id The procedure ID to search for.
	 * @return \WP_Term|null The term object if found, null otherwise.
	 */
	private function get_term_by_procedure_id( $procedure_id ): ?\WP_Term {
		$terms = get_terms(
			[
				'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
				'hide_empty' => false,
				'meta_query' => [
					[
						'key'     => 'procedure_id',
						'value'   => $procedure_id,
						'compare' => '=',
					],
				],
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0];
	}

	/**
	 * Render full gallery wrapper with sidebar and case detail view
	 *
	 * Creates the complete gallery structure including sidebar navigation
	 * and case detail view in the main content area.
	 *
	 * @since 3.0.0
	 * @param array $case_data The case data array.
	 * @param \WP_Post $case_post The case post object.
	 * @return string Complete gallery wrapper HTML.
	 */
	private function render_full_gallery_wrapper( array $case_data, \WP_Post $case_post ): string {
		$case_id = esc_attr( $case_data['id'] );
		$procedure_ids = implode( ',', $case_data['procedureIds'] );
		$procedure_slug = ! empty( $case_data['procedures'] ) ? $case_data['procedures'][0]['slug'] : '';

		// Build the main content wrapper matching the desired structure
		$html = '<div class="brag-book-gallery-main-content" role="region" aria-label="Gallery content" id="gallery-content">';
		$html .= '<div class="brag-book-gallery-case-detail-view" data-case-id="' . $case_id . '" data-procedure-ids="' . esc_attr( $procedure_ids ) . '" data-procedure="' . esc_attr( $procedure_slug ) . '">';

		// Generate the detailed case view
		$html .= $this->render_case_detail_view( $case_data, $case_post );

		$html .= '</div>'; // Close case-detail-view
		$html .= '</div>'; // Close main-content

		return $html;
	}

	/**
	 * Render case detail view
	 *
	 * Creates the detailed case view with all case information,
	 * before/after images, and patient details.
	 *
	 * @since 3.0.0
	 * @param array $case_data The case data array.
	 * @param \WP_Post $case_post The case post object.
	 * @return string Case detail view HTML.
	 */
	private function render_case_detail_view( array $case_data, \WP_Post $case_post ): string {
		$case_id = esc_attr( $case_data['id'] );
		$procedure_name = ! empty( $case_data['procedures'] ) ? $case_data['procedures'][0]['name'] : __( 'Case', 'brag-book-gallery' );
		$procedure_slug = ! empty( $case_data['procedures'] ) ? $case_data['procedures'][0]['slug'] : '';

		$html = '';

		// Header section with navigation and title
		$html .= '<div class="brag-book-gallery-brag-book-gallery-case-header-section">';
		$html .= '<div class="brag-book-gallery-case-navigation">';
		// Get the gallery page URL dynamically
		$gallery_page_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		$back_url = sprintf( '/%s/%s/', $gallery_page_slug, esc_attr( $procedure_slug ) );
		$html .= sprintf(
			'<a href="%s" class="brag-book-gallery-back-link">%s</a>',
			esc_url( $back_url ),
			esc_html__( '← Back to gallery', 'brag-book-gallery' )
		);
		$html .= '</div>';
		$html .= '<div class="brag-book-gallery-brag-book-gallery-case-header">';
		// Get the full title and parse it to wrap case number in span
		$full_title = get_the_title( $case_post->ID );

		// Check if SEO headline is set
		$seo_headline = get_post_meta( $case_post->ID, 'brag_book_gallery_seo_headline', true );

		if ( ! empty( $seo_headline ) ) {
			// Use SEO headline for the strong tag, extract case ID from title
			if ( preg_match('/ (#\d+)$/', $full_title, $matches) ) {
				$case_id = $matches[1];
				$formatted_title = '<strong>' . esc_html( $seo_headline ) . '</strong> <span class="case-id">' . esc_html( $case_id ) . '</span>';
			} else {
				// Fallback if no case ID found
				$formatted_title = '<strong>' . esc_html( $seo_headline ) . '</strong>';
			}
		} else {
			// Use default title formatting (procedure name + case ID)
			// Replace #123 pattern with wrapped version
			$formatted_title = preg_replace('/ (#\d+)$/', ' <span class="case-id">$1</span>', $full_title);
			// Wrap the procedure name part in strong tag
			$formatted_title = preg_replace('/^(.+?)( <span)/', '<strong>$1</strong>$2', $formatted_title);
		}

		$html .= '<h1 class="brag-book-gallery-content-title">' . $formatted_title . '</h1>';
		$html .= '<div class="brag-book-gallery-case-nav-buttons">';
		$html .= $this->render_case_navigation_buttons( $case_post, $case_data );
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		// Main content section
		$html .= '<div class="brag-book-gallery-brag-book-gallery-case-content">';

		// Images section
		$html .= $this->render_case_images_section( $case_data );

		// Case details cards section
		$html .= $this->render_case_details_cards( $case_data );

		$html .= '</div>'; // Close case content

		return $html;
	}

	/**
	 * Render case images section with main viewer and thumbnails
	 *
	 * @since 3.0.0
	 * @param array $case_data The case data array.
	 * @return string Images section HTML.
	 */
	private function render_case_images_section( array $case_data ): string {
		// Get the images directly from post meta
		$images_string = get_post_meta( get_the_ID(), 'brag_book_gallery_case_post_processed_url', true );

		// Convert string to array by splitting on semicolons
		$images = [];
		if ( ! empty( $images_string ) ) {
			// Split by semicolons and clean up URLs
			$url_parts = explode( ';', $images_string );
			foreach ( $url_parts as $url_part ) {
				$clean_url = trim( $url_part );
				if ( ! empty( $clean_url ) && filter_var( $clean_url, FILTER_VALIDATE_URL ) ) {
					$images[] = $clean_url;
				}
			}
		}

		if ( empty( $images ) ) {
			return '<div class="brag-book-gallery-case-images-section"><p>No images available for this case.</p></div>';
		}

		$case_id = esc_attr( $case_data['id'] );
		$procedure_name = ! empty( $case_data['procedures'] ) ? $case_data['procedures'][0]['name'] : 'Case';

		// Generate schema markup for the image gallery
		$schema_markup = $this->generate_gallery_schema( $images, $case_data );

		$html = $schema_markup;
		$html .= '<div class="brag-book-gallery-case-images-section" itemscope itemtype="https://schema.org/ImageGallery">';

		// Add hidden meta elements for schema.org microdata
		$gallery_name = sprintf( '%s - Before & After Case #%s', $procedure_name, $case_id );
		$gallery_description = sprintf( 'Before and after photos of %s procedure', $procedure_name );
		$html .= '<meta itemprop="name" content="' . esc_attr( $gallery_name ) . '">';
		$html .= '<meta itemprop="description" content="' . esc_attr( $gallery_description ) . '">';
		$html .= '<meta itemprop="contentUrl" content="' . esc_url( get_permalink() ) . '">';

		$html .= '<div class="brag-book-gallery-case-images-layout">';
		$html .= '<div class="brag-book-gallery-case-main-viewer">';
		$html .= '<div class="brag-book-gallery-main-image-container" data-image-index="0">';
		$html .= '<div class="brag-book-gallery-main-single">';

		// Main image (first image) with microdata
		if ( ! empty( $images[0] ) ) {
			$html .= '<img src="' . esc_url( $images[0] ) . '" alt="' . esc_attr( $procedure_name . ' - Case ' . $case_id ) . '" loading="eager" itemprop="image">';
			$html .= '<div class="brag-book-gallery-item-actions">';
			$html .= '<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case_' . $case_id . '_main" aria-label="Add to favorites">';
			$html .= '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
			$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>';
			$html .= '</svg>';
			$html .= '</button>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		// Thumbnails (only show if there are 2 or more images)
		if ( count( $images ) >= 2 ) {
			$html .= '<div class="brag-book-gallery-case-thumbnails">';
			$html .= '<div class="brag-book-gallery-thumbnails-grid">';

			foreach ( $images as $index => $image_url ) {
				$active_class = $index === 0 ? ' active' : '';
				$thumbnail_alt = sprintf( '%s - Thumbnail %d', $procedure_name, $index + 1 );
				$html .= '<div class="brag-book-gallery-thumbnail-item' . $active_class . '" data-image-index="' . $index . '" data-processed-url="' . esc_attr( $image_url ) . '">';
				$html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $thumbnail_alt ) . '" loading="lazy" itemprop="thumbnail">';
				$html .= '</div>';
			}

			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate schema.org ImageGallery markup for SEO
	 *
	 * @since 3.3.0
	 * @param array $images Array of image URLs.
	 * @param array $case_data The case data array.
	 * @return string Schema markup HTML.
	 */
	private function generate_gallery_schema( array $images, array $case_data ): string {
		if ( empty( $images ) ) {
			return '';
		}

		// Get site and practice information
		$site_name = get_bloginfo( 'name' );
		$site_url = home_url();

		// Build procedure information
		$procedure_name = ! empty( $case_data['procedures'] ) ? $case_data['procedures'][0]['name'] : 'Cosmetic Procedure';
		$all_procedures = ! empty( $case_data['procedures'] ) ? array_map( function( $proc ) {
			return $proc['name'];
		}, $case_data['procedures'] ) : array( $procedure_name );

		// Get patient information for description
		$patient_info_parts = array();
		if ( ! empty( $case_data['patient']['age'] ) ) {
			$patient_info_parts[] = $case_data['patient']['age'] . ' year old';
		}
		if ( ! empty( $case_data['patient']['gender'] ) ) {
			$patient_info_parts[] = $case_data['patient']['gender'];
		}
		$patient_info = ! empty( $patient_info_parts ) ? implode( ' ', $patient_info_parts ) : 'Patient';

		// Build description
		$description = sprintf(
			'Before and after photos of %s - %s performed at %s',
			$patient_info,
			implode( ', ', $all_procedures ),
			$site_name
		);

		// Build the schema data
		$schema_data = array(
			'@context' => 'https://schema.org',
			'@type' => 'ImageGallery',
			'name' => sprintf( '%s - Before & After Case #%s', $procedure_name, $case_data['id'] ),
			'description' => $description,
			'url' => get_permalink(),
			'provider' => array(
				'@type' => 'MedicalBusiness',
				'name' => $site_name,
				'url' => $site_url,
			),
			'about' => array(
				'@type' => 'MedicalProcedure',
				'name' => implode( ', ', $all_procedures ),
				'procedureType' => 'CosmeticProcedure',
			),
			'associatedMedia' => array(),
		);

		// Add each image to the schema
		foreach ( $images as $index => $image_url ) {
			$image_position = $index + 1;
			$image_caption = sprintf(
				'%s - Before and After Photo %d',
				$procedure_name,
				$image_position
			);

			$schema_data['associatedMedia'][] = array(
				'@type' => 'ImageObject',
				'contentUrl' => $image_url,
				'url' => $image_url,
				'name' => $image_caption,
				'caption' => $image_caption,
				'description' => sprintf(
					'%s result photo %d showing patient transformation',
					$procedure_name,
					$image_position
				),
				'position' => $image_position,
				'copyrightHolder' => array(
					'@type' => 'Organization',
					'name' => $site_name,
				),
			);
		}

		// Add aggregateRating if available
		if ( ! empty( $case_data['rating'] ) ) {
			$schema_data['aggregateRating'] = array(
				'@type' => 'AggregateRating',
				'ratingValue' => $case_data['rating'],
				'bestRating' => '5',
				'worstRating' => '1',
			);
		}

		// Add date information if available
		if ( ! empty( $case_data['datePublished'] ) ) {
			$schema_data['datePublished'] = $case_data['datePublished'];
		}
		if ( ! empty( $case_data['dateModified'] ) ) {
			$schema_data['dateModified'] = $case_data['dateModified'];
		}

		// Generate the JSON-LD script tag
		$schema_json = wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		return sprintf(
			'<script type="application/ld+json">%s</script>',
			$schema_json
		);
	}

	/**
	 * Render case details cards section
	 *
	 * @since 3.0.0
	 * @param array $case_data The case data array.
	 * @return string Case details cards HTML.
	 */
	private function render_case_details_cards( array $case_data ): string {
		$html = '<div class="brag-book-gallery-case-card-details-section">';
		$html .= '<div class="brag-book-gallery-case-card-details-grid">';

		// Procedures performed card
		if ( ! empty( $case_data['procedures'] ) ) {
			$procedures_html = '';

			foreach ( $case_data['procedures'] as $procedure ) {
				$procedures_html .= sprintf(
					'<span class="procedure-badge">%s</span>',
					esc_html( $procedure['name'] )
				);
			}

			$html .= sprintf(
				'<div class="case-detail-card procedures-performed-card">' .
					'<div class="card-header">' .
						'<h3 class="card-title">' . esc_html__( 'Procedures Performed', 'brag-book-gallery' ) . '</h3>' .
					'</div>' .
					'<div class="card-content">' .
						'<div class="brag-book-gallery-procedure-badges-list">%s</div>' .
					'</div>' .
					'</div>',
				$procedures_html
			);
		}

		// Patient information card
		$patient_info = $this->get_patient_info_for_card( $case_data );

		if ( ! empty( $patient_info ) ) {
			$html .= sprintf(
				'<div class="case-detail-card patient-details-card">' .
					'<div class="card-header">' .
						'<h3 class="card-title">' . esc_html__( 'Patient Information', 'brag-book-gallery') . '</h3>' .
					'</div>' .
					'<div class="card-content">' .
						'<div class="patient-info-grid">%s</div>' .
					'</div>' .
				'</div>',
				$patient_info
			);
		}

		// PostOp information card
		$postop_info = $this->get_postop_info_for_card( $case_data );

		if ( ! empty( $postop_info ) ) {
			$html .= sprintf(
				'<div class="case-detail-card postop-info-card">' .
					'<div class="card-header">' .
						'<h3 class="card-title">' . esc_html__( 'Post-Operative Information', 'brag-book-gallery' ) . '</h3>' .
					'</div>' .
					'<div class="card-content">' .
						'<div class="postop-info-grid">%s</div>' .
					'</div>' .
				'</div>',
				$postop_info
			);
		}

		// Procedure details card (if available).
		if ( ! empty( $case_data['procedures'] ) ) {

			$procedure_details = $this->get_procedure_details_for_card( $case_data );

			if ( ! empty( $procedure_details ) ) {
				$html .= sprintf(
					'<div class="case-detail-card procedure-details-card">' .
						'<div class="card-header">' .
							'<h3 class="card-title">%s</h3>' .
						'</div>' .
						'<div class="card-content">' .
							'<div class="procedure-details-grid">%s</div>' .
						'</div>' .
						'</div>',
						esc_html__( 'Procedure Details', 'brag-book-gallery' ),
					$procedure_details
				);
			}
		}

		// Close grid
		$html .= '</div>';

		// Case notes card - outside the grid, full width
		if ( ! empty( $case_data['caseNotes'] ) ) {
			$html .= sprintf(
				'<div class="case-detail-card case-notes-card">' .
					'<div class="card-header">' .
						'<h3 class="card-title">' . esc_html__( 'Case Notes', 'brag-book-gallery' ) . '</h3>' .
					'</div>' .
					'<div class="card-content">' .
						'<div class="case-details-content">%s</div>' .
					'</div>' .
				'</div>',
				wp_kses_post( wpautop( $case_data['caseNotes'] ) )
			);
		}

		// Close section
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate patient information HTML for a case card.
	 *
	 * @param array $case_data Case data from API or database.
	 * @return string HTML markup for patient info display.
	 */
	private function get_patient_info_for_card( array $case_data ): string {
		$info_html = '';

		// Use API case ID (caseId) instead of WordPress post ID.
		$case_id = $case_data['api_case_id'] ?? $case_data['id'];

		// Info item template for sprintf.
		$item_template = '<div class="brag-book-gallery-info-item"><span class="brag-book-gallery-info-label">%s</span><span class="brag-book-gallery-info-value">%s</span></div>';

		// Display ethnicity if available.
		if ( ! empty( $case_data['patientEthnicity'] ) ) {
			$info_html .= sprintf(
				$item_template,
				esc_html__( 'Ethnicity', 'brag-book-gallery' ),
				esc_html( $case_data['patientEthnicity'] )
			);
		}

		// Display gender if available.
		if ( ! empty( $case_data['patientGender'] ) ) {
			$info_html .= sprintf(
				$item_template,
				esc_html__( 'Gender', 'brag-book-gallery' ),
				esc_html( $case_data['patientGender'] )
			);
		}

		// Display age with "years" suffix if available.
		if ( ! empty( $case_data['patientAge'] ) ) {
			$info_html .= sprintf(
				$item_template,
				esc_html__( 'Age', 'brag-book-gallery' ),
				sprintf(
				/* translators: %s: patient age in years */
					esc_html__( '%s years', 'brag-book-gallery' ),
					esc_html( $case_data['patientAge'] )
				)
			);
		}

		return $info_html;
	}

	/**
	 * Get procedure details for card display
	 *
	 * @since 3.0.0
	 * @param array $case_data The case data array.
	 * @return string Procedure details HTML.
	 */
	private function get_procedure_details_for_card( array $case_data ): string {
		$html = '';

		// Get the case post ID from case data (it's already the WordPress post ID)
		if ( empty( $case_data['id'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: Missing case ID in get_procedure_details_for_card' );
			}
			return $html;
		}

		$post_id = $case_data['id'];

		// Get procedure details JSON from post meta
		$procedure_details_json = get_post_meta( $post_id, 'brag_book_gallery_procedure_details', true );

		if ( empty( $procedure_details_json ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "BRAGBook Gallery: No procedure details found for post {$post_id}" );
			}
			return $html;
		}

		// Decode JSON
		$procedure_details = json_decode( $procedure_details_json, true );
		if ( ! is_array( $procedure_details ) || empty( $procedure_details ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "BRAGBook Gallery: Invalid procedure details JSON for post {$post_id}: " . $procedure_details_json );
			}
			return $html;
		}

		// Build a map of procedure IDs to names from the case data
		$procedure_names = [];
		if ( ! empty( $case_data['procedures'] ) ) {
			foreach ( $case_data['procedures'] as $proc ) {
				$procedure_names[ $proc['procedure_id'] ] = $proc['name'];
			}
		}

		// Build HTML for each procedure's details
		foreach ( $procedure_details as $procedure_id => $details ) {
			if ( ! is_array( $details ) || empty( $details ) ) {
				continue;
			}

			// If we have multiple procedures with details, show which procedure these details belong to
			if ( count( $procedure_details ) > 1 && isset( $procedure_names[ $procedure_id ] ) ) {
				$html .= '<div class="procedure-details-group">';
				$html .= '<p class="procedure-details-subheading">' . esc_html( $procedure_names[ $procedure_id ] ) . '</p>';
			}

			foreach ( $details as $detail_label => $detail_value ) {
				$html .= '<div class="brag-book-gallery-info-item">';
				$html .= '<span class="brag-book-gallery-info-label">' . esc_html( $detail_label ) . '</span>';

				// Handle array values (e.g., ["Upper", "Lower"])
				if ( is_array( $detail_value ) ) {
					$html .= '<span class="brag-book-gallery-info-value">' . esc_html( implode( ', ', $detail_value ) ) . '</span>';
				} else {
					$html .= '<span class="brag-book-gallery-info-value">' . esc_html( $detail_value ) . '</span>';
				}

				$html .= '</div>';
			}

			if ( count( $procedure_details ) > 1 && isset( $procedure_names[ $procedure_id ] ) ) {
				$html .= '</div>';
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $html ) ) {
			error_log( "BRAGBook Gallery: Successfully generated procedure details for post {$post_id}" );
		}

		return $html;
	}

	/**
	 * Get PostOp information for card display
	 *
	 * @since 3.3.2
	 * @param array $case_data The case data array.
	 * @return string PostOp info HTML.
	 */
	private function get_postop_info_for_card( array $case_data ): string {
		$html = '';

		// Get the case post ID from case data
		if ( empty( $case_data['id'] ) ) {
			return $html;
		}

		$post_id = $case_data['id'];

		// Info item template for sprintf
		$item_template = '<div class="brag-book-gallery-info-item"><span class="brag-book-gallery-info-label">%s</span><span class="brag-book-gallery-info-value">%s</span></div>';

		// Get PostOp meta fields
		$technique          = get_post_meta( $post_id, 'brag_book_gallery_postop_technique', true );
		$revision_surgery   = get_post_meta( $post_id, 'brag_book_gallery_postop_revision_surgery', true );
		$after1_timeframe   = get_post_meta( $post_id, 'brag_book_gallery_postop_after1_timeframe', true );
		$after1_unit        = get_post_meta( $post_id, 'brag_book_gallery_postop_after1_unit', true );

		// Display technique if available
		if ( ! empty( $technique ) ) {
			$html .= sprintf(
				$item_template,
				esc_html__( 'Technique', 'brag-book-gallery' ),
				esc_html( $technique )
			);
		}

		// Display revision surgery if available
		if ( ! empty( $revision_surgery ) && $revision_surgery === '1' ) {
			$html .= sprintf(
				$item_template,
				esc_html__( 'Revision Surgery', 'brag-book-gallery' ),
				esc_html__( 'Yes', 'brag-book-gallery' )
			);
		}

		// Display after timeframe if available
		if ( ! empty( $after1_timeframe ) && ! empty( $after1_unit ) ) {
			$html .= sprintf(
				$item_template,
				esc_html__( 'Photo Taken', 'brag-book-gallery' ),
				sprintf(
					/* translators: 1: timeframe number, 2: time unit (e.g., months, weeks) */
					esc_html__( '%1$s %2$s post-op', 'brag-book-gallery' ),
					esc_html( $after1_timeframe ),
					esc_html( $after1_unit )
				)
			);
		}

		return $html;
	}

	/**
	 * Render case card HTML
	 *
	 * Creates HTML for one case card using the same structure
	 * as Cases_Handler::render_ajax_gallery_case_card().
	 *
	 * @since 3.0.0
	 * @param array $case_data The case data array.
	 * @param \WP_Post $case_post The case post object.
	 * @return string Case card HTML.
	 */
	private function render_case_card( array $case_data, \WP_Post $case_post ): string {
		$id = $case_post->ID;
		$case_id = esc_attr( $case_data['id'] );
		$patient_age = esc_attr( $case_data['patientAge'] );
		$patient_gender = esc_attr( $case_data['patientGender'] );
		$patient_ethnicity = esc_attr( $case_data['patientEthnicity'] );
		$procedure_ids = ! empty( $case_data['procedureIds'] ) ? esc_attr( implode( ',', $case_data['procedureIds'] ) ) : '';

		// Check for nudity
		$has_nudity = false;
		if ( ! empty( $case_data['procedures'] ) ) {
			foreach ( $case_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['nudity'] ) ) {
					$has_nudity = true;
					break;
				}
			}
		}

		// Generate case URL (link to the post)
		$case_url = get_permalink( $case_post->ID );

		// Determine primary image
		$primary_image = $this->get_primary_image( $case_data );

		// Generate procedures list for details
		$procedures_list = $this->generate_procedures_list( $case_data['procedures'] );

		// Get current procedure context if on taxonomy page
		$current_procedure_id = '';
		$current_term_id      = '';
		if ( is_tax( 'brag_book_procedures' ) ) {
			$current_term = get_queried_object();
			if ( $current_term && ! is_wp_error( $current_term ) ) {
				$current_term_id      = (string) $current_term->term_id;
				$current_procedure_id = get_term_meta( $current_term->term_id, 'procedure_id', true ) ?: $current_term_id;
			}
		}

		// Get procedure details data attributes
		$procedure_details_attrs = $this->get_procedure_details_attributes( $case_post->ID );

		// Build the complete case card HTML
		$html = sprintf(
			'<article class="brag-book-gallery-case-card" data-test="testing" data-post-id="%s" data-case-id="%s" data-age="%s" data-gender="%s" data-ethnicity="%s" data-procedure-ids="%s" data-current-procedure-id="%s" data-current-term-id="%s"%s>',
			$id,
			$case_id,
			$patient_age,
			$patient_gender,
			$patient_ethnicity,
			$procedure_ids,
			esc_attr( $current_procedure_id ),
			esc_attr( $current_term_id ),
			$procedure_details_attrs
		);

		// Image section
		$html .= '<div class="brag-book-gallery-case-image-section">';
		$html .= '<div class="brag-book-gallery-case-image-container">';

		if ( $has_nudity ) {
			$html .= sprintf(
				'<div class="brag-book-gallery-nudity-warning" style="display: block;">
		            <div class="brag-book-gallery-nudity-content">
		                <p class="brag-book-gallery-nudity-text">%s</p>
		                <button class="brag-book-gallery-nudity-proceed">%s</button>
		            </div>
		        </div>',
				esc_html__( 'This content contains nudity', 'brag-book-gallery' ),
				esc_html__( 'Proceed', 'brag-book-gallery' )
			);
		}

		// Skeleton loader
		$html .= '<div class="brag-book-gallery-case-skeleton" style="display: none;"></div>';

		// Image link
		if ( $primary_image ) {
			$html .= sprintf(
				'<a href="%s" class="brag-book-gallery-case-link" data-case-id="%s">',
				esc_url( $case_url ),
				$case_id
			);

			$html .= sprintf(
				'<img src="%s" alt="%s" class="brag-book-gallery-case-image" loading="lazy">',
				esc_url( $primary_image ),
				esc_attr( sprintf( __( 'Case %s', 'brag-book-gallery' ), $case_id ) )
			);

			$html .= '</a>';
		}

		// Favorites button
		$html .= sprintf(
			'<button class="brag-book-gallery-favorites-btn" data-case-id="%s" aria-label="%s">',
			$case_id,
			esc_attr__( 'Add to favorites', 'brag-book-gallery' )
		);
		$html .= '<span class="brag-book-gallery-favorites-icon">♡</span>';
		$html .= '</button>';

		$html .= '</div>'; // Close image-container
		$html .= '</div>'; // Close image-section

		// Details section
		$html .= '<div class="brag-book-gallery-case-details">';
		$html .= '<details class="brag-book-gallery-case-summary">';

		// Summary (always visible)
		$summary_text = ! empty( $case_data['procedures'] ) ? $case_data['procedures'][0]['name'] : __( 'Case', 'brag-book-gallery' );
		$html .= sprintf(
			'<summary class="brag-book-gallery-case-summary-header">%s<span class="brag-book-gallery-case-number">%s</span></summary>',
			esc_html( $summary_text ),
			esc_html( sprintf( __( 'Case #%s', 'brag-book-gallery' ), $case_id ) )
		);

		// Expandable content
		$html .= '<div class="brag-book-gallery-case-expanded-content">';
		$html .= '<div class="brag-book-gallery-case-more-details">' . esc_html__( 'More Details', 'brag-book-gallery' ) . '</div>';

		if ( ! empty( $procedures_list ) ) {
			$html .= '<div class="brag-book-gallery-case-procedures">' . $procedures_list . '</div>';
		}

		$html .= '</div>'; // Close expanded-content
		$html .= '</details>'; // Close summary
		$html .= '</div>'; // Close details

		$html .= '</article>'; // Close case-card

		return $html;
	}

	/**
	 * Get primary image for case
	 *
	 * @since 3.0.0
	 * @param array $case_data The case data array.
	 * @return string Image URL or empty string.
	 */
	private function get_primary_image( array $case_data ): string {
		// Try after image first, then before image
		if ( ! empty( $case_data['afterImage'] ) ) {
			return $case_data['afterImage'];
		}

		if ( ! empty( $case_data['beforeImage'] ) ) {
			return $case_data['beforeImage'];
		}

		return '';
	}

	/**
	 * Generate procedures list HTML
	 *
	 * @since 3.0.0
	 * @param array $procedures Array of procedure data.
	 * @return string Procedures list HTML.
	 */
	private function generate_procedures_list( array $procedures ): string {
		if ( empty( $procedures ) ) {
			return '';
		}

		$html = '<ul class="brag-book-gallery-procedure-list">';
		foreach ( $procedures as $procedure ) {
			$html .= sprintf(
				'<li class="brag-book-gallery-procedure-item">%s</li>',
				esc_html( $procedure['name'] )
			);
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Render case navigation buttons
	 *
	 * Uses the Case Ordering system to display prev/next buttons only for cases
	 * within the same procedure, respecting the custom ordering defined in the
	 * brag_book_procedures taxonomy.
	 *
	 * @since 3.0.0
	 * @param \WP_Post $case_post The current case post.
	 * @param array $case_data The case data array.
	 * @return string Navigation buttons HTML.
	 */
	private function render_case_navigation_buttons( \WP_Post $case_post, array $case_data ): string {
		// Get the primary procedure for this case
		if ( empty( $case_data['procedures'] ) ) {
			return '';
		}

		$primary_procedure_id = $case_data['procedures'][0]['procedure_id'];

		// Get all cases for this procedure in the correct order (from Case Ordering)
		$procedure_cases = $this->get_cases_for_procedure( $primary_procedure_id );

		if ( count( $procedure_cases ) <= 1 ) {
			return ''; // No navigation needed for single case
		}

		// Find current case position in the procedure cases
		$current_position = null;
		foreach ( $procedure_cases as $index => $case ) {
			if ( $case->ID === $case_post->ID ) {
				$current_position = $index;
				break;
			}
		}

		if ( $current_position === null ) {
			return ''; // Current case not found in procedure
		}

		$html = '';

		// Previous case button
		if ( $current_position > 0 ) {
			$prev_case = $procedure_cases[ $current_position - 1 ];
			// Force absolute URL by prepending home_url to the path
			$prev_case_path = wp_make_link_relative( get_permalink( $prev_case->ID ) );
			$prev_case_url = home_url( $prev_case_path );

			$html .= sprintf(
				'<a href="%s" class="brag-book-gallery-nav-button brag-book-gallery-nav-button--prev" aria-label="Previous case">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M400-240 160-480l240-240 56 58-142 142h486v80H314l142 142-56 58Z"/></svg>
					%s
				</a>',
				esc_url( $prev_case_url ),
				sprintf(
					'<span class="sr-only">%s</span>',
					esc_html__( 'Previous case', 'brag-book-gallery' )
				)
			);
		}

		// Next case button
		if ( $current_position < count( $procedure_cases ) - 1 ) {
			$next_case = $procedure_cases[ $current_position + 1 ];
			// Force absolute URL by prepending home_url to the path
			$next_case_path = wp_make_link_relative( get_permalink( $next_case->ID ) );
			$next_case_url = home_url( $next_case_path );

			$html .= sprintf(
				'<a href="%s" class="brag-book-gallery-nav-button brag-book-gallery-nav-button--next" aria-label="Next case">
					%s
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="m560-240-56-58 142-142H160v-80h486L504-662l56-58 240 240-240 240Z"/></svg>
				</a>',
				esc_url( $next_case_url ),
				sprintf(
					'<span class="sr-only">%s</span>',
					esc_html__( 'Next case', 'brag-book-gallery' )
				)
			);
		}

		return $html;
	}

	/**
	 * Ensure a URL is absolute with domain
	 *
	 * Converts relative URLs to absolute URLs by prepending the site URL.
	 * Handles cases where plugins filter permalinks to be relative.
	 *
	 * @since 3.3.0
	 * @param string $url The URL to check and convert.
	 * @return string Absolute URL with domain.
	 */
	private function ensure_absolute_url( string $url ): string {
		// Check if URL is already absolute (starts with http:// or https://)
		if ( preg_match( '/^https?:\/\//', $url ) ) {
			return $url;
		}

		// Get the site URL with proper scheme
		$site_url = home_url( '/', is_ssl() ? 'https' : 'http' );

		// If URL is relative, prepend the site URL
		// Ensure no double slashes by removing leading slash from URL
		$url = ltrim( $url, '/' );

		return rtrim( $site_url, '/' ) . '/' . $url;
	}

	/**
	 * Get all cases for a specific procedure
	 *
	 * @since 3.0.0
	 * @param int $procedure_id The procedure ID.
	 * @return array Array of WP_Post objects for cases in this procedure.
	 */
	private function get_cases_for_procedure( int $procedure_id ): array {
		// Try to get ordered cases using the case ordering metadata
		$ordered_post_ids = \BRAGBookGallery\Includes\Sync\Data_Sync::get_ordered_cases_for_procedure( $procedure_id );

		if ( ! empty( $ordered_post_ids ) ) {
			// Use ordered IDs with post__in to preserve order
			$query_args = [
				'post_type' => Post_Types::POST_TYPE_CASES,
				'post_status' => 'publish',
				'post__in' => $ordered_post_ids,
				'orderby' => 'post__in', // Preserve the order from post__in
				'posts_per_page' => -1, // Get all posts
			];
		} else {
			// Fallback to regular query if no ordering data exists
			// First, find the taxonomy term for this procedure_id
			$terms = get_terms( [
				'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
				'meta_query' => [
					[
						'key' => 'procedure_id',
						'value' => $procedure_id,
						'compare' => '='
					]
				],
				'hide_empty' => false
			] );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				return [];
			}

			$procedure_term = $terms[0];

			// Get all posts with this procedure term, ordered by date
			$query_args = [
				'post_type' => Post_Types::POST_TYPE_CASES,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'orderby' => [
					'modified' => 'DESC',
					'date' => 'DESC'
				],
				'tax_query' => [
					[
						'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
						'field' => 'term_id',
						'terms' => $procedure_term->term_id
					]
				]
			];
		}

		$cases_query = new \WP_Query( $query_args );

		return $cases_query->posts;
	}

	/**
	 * Log missing data
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @param string $field The missing field name.
	 * @return void
	 */
	private function log_missing_data( int $post_id, string $field ): void {
		$this->missing_data_log[] = [
			'post_id' => $post_id,
			'field'   => $field,
			'time'    => current_time( 'mysql' ),
		];

		// Log to WordPress debug log if enabled
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'BRAGBook Gallery: Missing data for post %d, field: %s',
				$post_id,
				$field
			) );
		}
	}

	/**
	 * Get procedure details as data attributes
	 *
	 * Extracts procedure details from post meta and formats them as HTML data attributes
	 * for use in filtering.
	 *
	 * @since 3.3.0
	 * @param int $post_id The post ID.
	 * @return string Space-prepended data attributes string (e.g., ' data-procedure-detail-implant-type="silicone"').
	 */
	private function get_procedure_details_attributes( int $post_id ): string {
		$attrs = '';

		// Get procedure details JSON from post meta
		$procedure_details_json = get_post_meta( $post_id, 'brag_book_gallery_procedure_details', true );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "BRAGBook Gallery Debug: Getting procedure details for post {$post_id}" );
			error_log( "BRAGBook Gallery Debug: Raw JSON: " . var_export( $procedure_details_json, true ) );
		}

		if ( empty( $procedure_details_json ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "BRAGBook Gallery Debug: No procedure details JSON found for post {$post_id}" );
			}
			return $attrs;
		}

		// Decode JSON
		$procedure_details = json_decode( $procedure_details_json, true );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "BRAGBook Gallery Debug: Decoded JSON: " . var_export( $procedure_details, true ) );
		}

		if ( ! is_array( $procedure_details ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "BRAGBook Gallery: Invalid procedure details JSON for post {$post_id}: " . $procedure_details_json );
			}
			return $attrs;
		}

		// Build data attributes from procedure details
		foreach ( $procedure_details as $procedure_id => $details ) {
			if ( ! is_array( $details ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "BRAGBook Gallery Debug: Procedure ID {$procedure_id} details is not an array" );
				}
				continue;
			}

			foreach ( $details as $detail_label => $detail_value ) {
				// Create a sanitized attribute name from the label (use dashes for dataset compatibility)
				$attr_name = sanitize_title_with_dashes( $detail_label );

				// Handle array values (e.g., ["Upper", "Lower"])
				if ( is_array( $detail_value ) ) {
					$attr_value = implode( ',', array_map( function( $val ) {
						return strtolower( (string) $val );
					}, $detail_value ) );
				} else {
					$attr_value = strtolower( (string) $detail_value );
				}

				$attrs .= ' data-procedure-detail-' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "BRAGBook Gallery Debug: Added attribute data-procedure-detail-{$attr_name}=\"{$attr_value}\"" );
				}
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "BRAGBook Gallery Debug: Final attributes for post {$post_id}: " . $attrs );
		}

		return $attrs;
	}

	/**
	 * AJAX handler for loading case details HTML with view tracking
	 *
	 * Handles the AJAX request to load case details and tracks the view in the API.
	 * This method is called when cases are loaded dynamically via JavaScript.
	 *
	 * @since 3.0.0
	 * @return void Outputs JSON response and exits
	 */
	public function ajax_load_case_details_html(): void {
		// Verify nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( __( 'Security verification failed.', 'brag-book-gallery' ) );
			return;
		}

		// Get and validate case ID
		$case_id = isset( $_POST['case_id'] ) ? sanitize_text_field( wp_unslash( $_POST['case_id'] ) ) : '';
		if ( empty( $case_id ) ) {
			wp_send_json_error( __( 'Case ID is required.', 'brag-book-gallery' ) );
			return;
		}

		// Get additional parameters
		$procedure_slug = isset( $_POST['procedure_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_slug'] ) ) : '';
		$procedure_name = isset( $_POST['procedure_name'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_name'] ) ) : '';

		// Track the view in the API
		$view_tracked = $this->track_case_view( $case_id );

		// Generate the case HTML using WordPress data
		$case_html = $this->generate_case_detail_html( $case_id );

		if ( empty( $case_html ) ) {
			wp_send_json_error( __( 'Case not found or unable to generate content.', 'brag-book-gallery' ) );
			return;
		}

		// Prepare response data
		$response_data = array(
			'html'         => $case_html,
			'case_id'      => $case_id,
			'view_tracked' => $view_tracked,
			'seo'          => array(
				'title'       => $procedure_name ? "{$procedure_name} #{$case_id}" : "Case #{$case_id}",
				'description' => $procedure_name ? "View {$procedure_name} case #{$case_id} before and after results." : "View case #{$case_id} before and after results.",
			),
		);

		// Add debug information if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response_data['debug'] = array(
				'view_tracking_attempted' => true,
				'view_tracked'           => $view_tracked,
				'tracking_error'         => $view_tracked ? null : 'API tracking failed or not configured',
			);
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * Track case view in the BRAGBook API
	 *
	 * Sends a tracking request to the API to record that this case was viewed.
	 *
	 * @since 3.0.0
	 * @param string $case_id The case ID to track
	 * @return bool True if tracking was successful, false otherwise
	 */
	private function track_case_view( string $case_id ): bool {
		try {
			// Get API configuration
			$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

			if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook Gallery: API configuration missing for view tracking' );
				}
				return false;
			}

			// Use the first configured API token and property ID
			$api_token = is_array( $api_tokens ) ? $api_tokens[0] : $api_tokens;
			$website_property_id = is_array( $website_property_ids ) ? $website_property_ids[0] : $website_property_ids;

			// Get base API URL
			$api_endpoint = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );

			// Build the tracking URL
			$tracking_url = sprintf(
				'%s/api/plugin/tracker?apiToken=%s&websitepropertyId=%s',
				$api_endpoint,
				urlencode( $api_token ),
				urlencode( $website_property_id )
			);

			// Prepare tracking data
			$tracking_data = array(
				'case_id' => $case_id,
				'action'  => 'view',
				'source'  => 'wordpress_plugin',
			);

			// Make the API request
			$response = wp_remote_post( $tracking_url, array(
				'body'    => wp_json_encode( $tracking_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 10,
			) );

			// Check for errors
			if ( is_wp_error( $response ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook Gallery: View tracking API error - ' . $response->get_error_message() );
				}
				return false;
			}

			// Check response code
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code < 200 || $response_code >= 300 ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "BRAGBook Gallery: View tracking API returned status {$response_code}" );
				}
				return false;
			}

			// Parse response
			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );

			// Check if tracking was successful
			if ( isset( $response_data['success'] ) && $response_data['success'] ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "BRAGBook Gallery: Successfully tracked view for case {$case_id}" );
				}
				return true;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "BRAGBook Gallery: View tracking failed for case {$case_id} - " . wp_json_encode( $response_data ) );
			}
			return false;

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: View tracking exception - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Generate case detail HTML for AJAX response
	 *
	 * Creates the HTML content for a case detail view.
	 *
	 * @since 3.0.0
	 * @param string $case_id The case ID (WordPress post ID)
	 * @return string The generated HTML or empty string if case not found
	 */
	private function generate_case_detail_html( string $case_id ): string {
		// Treat case_id as WordPress post ID
		$case_post = get_post( intval( $case_id ) );

		// Verify it's a case post type and published
		if ( ! $case_post || $case_post->post_type !== Post_Types::POST_TYPE_CASES || $case_post->post_status !== 'publish' ) {
			return '';
		}

		// Generate case HTML using the existing method
		return $this->generate_case_html( $case_post );
	}
}
