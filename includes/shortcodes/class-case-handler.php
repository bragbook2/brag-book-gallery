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

/**
 * Single Case Handler class
 *
 * Generates single case display using WordPress post data instead of API data.
 * Uses the same HTML structure as Cases_Handler for consistency.
 *
 * @since 3.0.0
 */
class Case_Handler {

	/**
	 * Missing data log
	 *
	 * @var array
	 */
	private $missing_data_log = [];

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
	}

	/**
	 * Check if case shortcode is present and enqueue assets
	 *
	 * Detects if the brag_book_gallery_case shortcode is present on the current page
	 * and enqueues the necessary CSS and JavaScript assets.
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

		// Enqueue the main gallery CSS and JS since our case uses the same styles
		$plugin_version = Setup::get_plugin_version();

		// Enqueue CSS using Setup class asset URL method
		wp_enqueue_style(
			'brag-book-gallery',
			Setup::get_asset_url( 'assets/css/brag-book-gallery.css' ),
			[],
			$plugin_version
		);

		// Enqueue JavaScript using Setup class asset URL method
		wp_enqueue_script(
			'brag-book-gallery',
			Setup::get_asset_url( 'assets/js/brag-book-gallery.js' ),
			[ 'jquery' ],
			$plugin_version,
			true
		);

		// Localize script with necessary data for AJAX and functionality
		wp_localize_script( 'brag-book-gallery', 'bragBookGalleryConfig', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'brag_book_gallery_nonce' ),
		] );
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

		// Get case by case_id or post_id
		$case_post = null;
		if ( ! empty( $atts['case_id'] ) ) {
			$case_post = $this->get_case_by_case_id( $atts['case_id'] );
		} elseif ( ! empty( $atts['post_id'] ) ) {
			$case_post = get_post( intval( $atts['post_id'] ) );
		}

		if ( ! $case_post || $case_post->post_type !== Post_Types::POST_TYPE_CASES ) {
			return '<p class="brag-book-gallery-error">Case not found.</p>';
		}

		// Generate case HTML directly
		$output = $this->generate_case_html( $case_post );

		// Prevent wpautop from adding unwanted <p> and <br> tags to shortcode output
		return \BRAGBookGallery\Includes\Core\Setup::clean_shortcode_text( $output );
	}

	/**
	 * Get case post by case_id meta value
	 *
	 * @since 3.0.0
	 * @param string $case_id The case ID to search for.
	 * @return \WP_Post|null The case post or null if not found.
	 */
	private function get_case_by_case_id( string $case_id ): ?\WP_Post {
		$query = new \WP_Query( [
			'post_type'  => Post_Types::POST_TYPE_CASES,
			'meta_query' => [
				[
					'key'   => 'case_id',
					'value' => $case_id,
					'compare' => '=',
				],
			],
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		] );

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Generate case HTML from post data
	 *
	 * Creates the complete case HTML using the same markup structure
	 * as Cases_Handler::render_ajax_gallery_case_card but from WordPress post data.
	 *
	 * @since 3.0.0
	 * @param \WP_Post $case_post The case post object.
	 * @return string Generated case HTML.
	 */
	private function generate_case_html( \WP_Post $case_post ): string {
		// Get post meta data
		$case_data = $this->get_case_meta_data( $case_post->ID );

		// Get procedure information
		$procedures = $this->get_case_procedures( $case_post->ID );

		// Build case data array similar to API format
		$case_array = [
			'id' => $case_data['case_id'] ?: $case_post->ID,
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

		// Generate the case card HTML
		return $this->render_case_card( $case_array, $case_post );
	}

	/**
	 * Get case meta data with missing data logging
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @return array Case meta data with defaults.
	 */
	private function get_case_meta_data( int $post_id ): array {
		$meta_fields = [
			'case_id' => '',
			'patient_age' => '',
			'patient_gender' => '',
			'patient_ethnicity' => '',
			'patient_height' => '',
			'patient_weight' => '',
			'procedure_date' => '',
			'case_notes' => '',
			'before_image' => '',
			'after_image' => '',
		];

		$case_data = [];
		foreach ( $meta_fields as $field => $default ) {
			$value = get_post_meta( $post_id, $field, true );
			if ( empty( $value ) && $value !== '0' ) {
				$this->log_missing_data( $post_id, $field );
				$value = $default;
			}
			$case_data[ $field ] = $value;
		}

		return $case_data;
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

		if ( is_wp_error( $procedure_terms ) || empty( $procedure_terms ) ) {
			$this->log_missing_data( $post_id, 'procedures_taxonomy' );
			return [];
		}

		$procedures = [];
		foreach ( $procedure_terms as $term ) {
			$procedure_meta = $this->get_procedure_meta( $term->term_id );
			$procedures[] = [
				'id' => $procedure_meta['procedure_id'] ?: $term->term_id,
				'procedure_id' => $procedure_meta['procedure_id'] ?: $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'seoSuffixUrl' => $term->slug,
				'nudity' => 'true' === $procedure_meta['nudity'],
			];
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

		// Build the complete case card HTML
		$html = sprintf(
			'<article class="brag-book-gallery-case-card" data-case-id="%s" data-age="%s" data-gender="%s" data-ethnicity="%s" data-procedure-ids="%s">',
			$case_id,
			$patient_age,
			$patient_gender,
			$patient_ethnicity,
			$procedure_ids
		);

		// Image section
		$html .= '<div class="brag-book-gallery-case-image-section">';
		$html .= '<div class="brag-book-gallery-case-image-container">';

		if ( $has_nudity ) {
			$html .= '<div class="brag-book-gallery-nudity-warning" style="display: block;">';
			$html .= '<div class="brag-book-gallery-nudity-content">';
			$html .= '<div class="brag-book-gallery-nudity-icon">🔞</div>';
			$html .= '<p class="brag-book-gallery-nudity-text">' . esc_html__( 'This content contains nudity', 'brag-book-gallery' ) . '</p>';
			$html .= '<button class="brag-book-gallery-nudity-proceed">' . esc_html__( 'Proceed', 'brag-book-gallery' ) . '</button>';
			$html .= '</div>';
			$html .= '</div>';
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
	 * Get missing data log
	 *
	 * @since 3.0.0
	 * @return array The missing data log.
	 */
	public function get_missing_data_log(): array {
		return $this->missing_data_log;
	}
}