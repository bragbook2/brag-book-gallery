<?php
/**
 * Sidebar Handler for WordPress taxonomy-based sidebar
 *
 * Handles the [brag_book_gallery_sidebar] shortcode that displays
 * procedures taxonomy in the same markup format as the API-based sidebar.
 *
 * @package BRAGBookGallery
 * @subpackage Shortcodes
 * @since 3.0.0
 */

namespace BRAGBookGallery\Includes\Shortcodes;

use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Core\Setup;

/**
 * Sidebar Handler class
 *
 * Generates sidebar markup using WordPress taxonomy data instead of API data.
 * Maintains the same HTML structure as the main gallery sidebar for consistency.
 *
 * @since 3.0.0
 */
class Sidebar_Handler {

	/**
	 * Initialize the sidebar handler
	 *
	 * Sets up shortcode registration and hooks.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {

		add_shortcode(
			'brag_book_gallery_sidebar',
			[ $this, 'render_sidebar_shortcode' ]
		);

		// Hook into wp_enqueue_scripts to check for our shortcode and enqueue assets
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'maybe_enqueue_assets' ),
			5
		);
	}

	/**
	 * Check if sidebar shortcode is present and enqueue assets
	 *
	 * Detects if the brag_book_gallery_sidebar shortcode is present on the current page
	 * and enqueues the necessary CSS and JavaScript assets.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function maybe_enqueue_assets(): void {
		global $post;

		// Check if we have a post and if it contains our shortcode
		if ( ! $post || ! has_shortcode( $post->post_content, 'brag_book_gallery_sidebar' ) ) {
			return;
		}

		// Enqueue the main gallery CSS and JS since our sidebar uses the same styles
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

		// Get sidebar data for search functionality
		$sidebar_data = $this->get_taxonomy_sidebar_data();

		// Format sidebar data for JavaScript consumption (match the expected structure)
		$formatted_sidebar_data = [];
		if ( ! empty( $sidebar_data['data'] ) ) {
			foreach ( $sidebar_data['data'] as $category ) {
				$procedures = [];
				if ( ! empty( $category['procedures'] ) ) {
					foreach ( $category['procedures'] as $procedure ) {
						$procedures[] = [
							'slug' => $procedure['slug'],
							'name' => $procedure['name'],
							'count' => $procedure['caseCount'],
							'url' => get_term_link( get_term_by( 'slug', $procedure['slug'], Taxonomies::TAXONOMY_PROCEDURES ) ) ?: '',
						];
					}
				}

				$formatted_sidebar_data[ $category['slug'] ] = [
					'name' => $category['name'],
					'procedures' => $procedures,
				];
			}
		}

		// Localize script with necessary data for AJAX and functionality
		wp_localize_script( 'brag-book-gallery', 'bragBookGalleryConfig', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'brag_book_gallery_nonce' ),
			'sidebarData' => $formatted_sidebar_data,
		] );
	}

	/**
	 * Render the sidebar shortcode
	 *
	 * Processes the [brag_book_gallery_sidebar] shortcode and returns
	 * the generated sidebar HTML using WordPress taxonomy data.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Generated sidebar HTML.
	 * @since 3.0.0
	 */
	public function render_sidebar_shortcode( $atts = [] ): string {
		// Parse shortcode attributes with defaults
		$atts = shortcode_atts(
			array(
				'show_counts' => 'true',
				'hide_empty'  => 'true',
			),
			$atts,
			'brag_book_gallery_sidebar'
		);

		// Convert string attributes to booleans
		$show_counts = 'true' === $atts['show_counts'];
		$hide_empty  = 'true' === $atts['hide_empty'];

		// Generate sidebar HTML directly
		$output = $this->generate_sidebar_html(
			$show_counts,
			$hide_empty
		);

		// Clean up whitespace but maintain formatting (wpautop prevention handled by block filter)
		$output = trim( $output );
		return $output;
	}

	/**
	 * Generate sidebar HTML from taxonomy data
	 *
	 * Creates the complete sidebar HTML using the same markup structure
	 * as HTML_Renderer but from WordPress taxonomy data.
	 *
	 * @param bool $show_counts Whether to include case counts.
	 * @param bool $hide_empty Whether to hide empty categories.
	 *
	 * @return string Generated sidebar HTML.
	 * @since 3.0.0
	 */
	public function generate_sidebar_html( bool $show_counts = true, bool $hide_empty = true ): string {

		// Get all parent procedures (categories).
		$parent_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'parent'     => 0,
			'hide_empty' => $hide_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );


		if ( is_wp_error( $parent_terms ) || empty( $parent_terms ) ) {
			return '';
		}

		$filters = [];
		foreach ( $parent_terms as $parent_term ) {
			$filter = $this->generate_category_filter( $parent_term, $show_counts, $hide_empty );
			if ( ! empty( $filter ) ) {
				$filters[] = $filter;
			}
		}

		return implode( '', $filters );
	}

	/**
	 * Generate category filter HTML
	 *
	 * Creates HTML for one category filter section using the same structure
	 * as HTML_Renderer::generate_category_filter().
	 *
	 * @param \WP_Term $parent_term The parent category term.
	 * @param bool $show_counts Whether to include case counts.
	 * @param bool $hide_empty Whether to hide empty categories.
	 *
	 * @return string Category filter HTML.
	 * @since 3.0.0
	 */
	private function generate_category_filter( \WP_Term $parent_term, bool $show_counts, bool $hide_empty ): string {
		// Get child procedures for this category
		$child_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'parent'     => $parent_term->term_id,
			'hide_empty' => $hide_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $child_terms ) ) {
			$child_terms = [];
		}

		// Calculate total case count
		$total_cases = 0;
		if ( $show_counts ) {
			foreach ( $child_terms as $child_term ) {
				$total_cases += $child_term->count;
			}
		}

		// Generate category button
		$filter_button = $this->render_category_button( $parent_term->slug, $parent_term->name, $total_cases );

		// Generate procedures list
		$procedures_list = $this->render_procedures_list( $child_terms, $parent_term->slug );

		// Check if navigation menus should be expanded by default
		$expand_nav_menus = get_option( 'brag_book_gallery_expand_nav_menus', false );
		$open_attribute   = $expand_nav_menus ? ' open' : '';

		// Return the complete category filter HTML
		return sprintf(
			'<details class="brag-book-gallery-nav-list__item" data-category="%s"%s>%s%s</details>',
			esc_attr( $parent_term->slug ),
			$open_attribute,
			$filter_button,
			$procedures_list
		);
	}

	/**
	 * Render category button
	 *
	 * Creates category toggle button HTML using the same structure
	 * as HTML_Renderer::render_category_button().
	 *
	 * @param string $category_slug Category slug.
	 * @param string $category_name Category name.
	 * @param int $total_cases Total case count.
	 *
	 * @return string Button HTML.
	 * @since 3.0.0
	 */
	private function render_category_button( string $category_slug, string $category_name, int $total_cases ): string {
		// Check if filter counts should be displayed
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );

		$count = sprintf(
			'<span class="brag-book-gallery-nav-button__label">%s %s</span>',
			sprintf( '<span>%s</span>',  esc_html( $category_name ) ),
			( $show_filter_counts && $total_cases > 0 ) ? sprintf( '<span class="brag-book-gallery-nav-count">(%d)</span>', $total_cases ) : ''
		);

		$icon = '<svg class="brag-book-gallery-nav-button__icon" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-357.85 253.85-584 296-626.15l184 184 184-184L706.15-584 480-357.85Z"/></svg>';

		return sprintf(
			'<summary class="brag-book-gallery-nav-button" data-category="%s" aria-label="%s">%s %s</summary>',
			esc_attr( $category_slug ),
			/* translators: %s: category name */
			esc_attr( sprintf( __( '%s category filter', 'brag-book-gallery' ), $category_name ) ),
			$count,
			$icon
		);
	}

	/**
	 * Render procedures list
	 *
	 * Creates procedures submenu HTML using the same structure
	 * as HTML_Renderer::render_procedures_list().
	 *
	 * @param array $procedures Array of child procedure terms.
	 * @param string $category_slug Category slug.
	 *
	 * @return string Procedures list HTML.
	 * @since 3.0.0
	 */
	private function render_procedures_list( array $procedures, string $category_slug ): string {
		$procedure_items = [];

		foreach ( $procedures as $procedure ) {
			$procedure_item = $this->render_procedure_item( $procedure, $category_slug );
			if ( $procedure_item ) {
				$procedure_items[] = $procedure_item;
			}
		}

		return sprintf(
			'<ul class="brag-book-gallery-nav-list-submenu">%s</ul>',
			implode( '', $procedure_items )
		);
	}

	/**
	 * Render a single procedure item
	 *
	 * Creates HTML for one procedure filter link using the same structure
	 * as HTML_Renderer::render_procedure_item().
	 *
	 * @param \WP_Term $procedure Procedure term.
	 * @param string $category_slug Category slug.
	 *
	 * @return string Item HTML.
	 * @since 3.0.0
	 */
	private function render_procedure_item( \WP_Term $procedure, string $category_slug ): string {
		$procedure_meta = $this->get_procedure_meta( $procedure->term_id );
		$procedure_id   = $procedure_meta['procedure_id'] ?: $procedure->term_id;
		$nudity         = 'true' === $procedure_meta['nudity'];

		// Generate procedure permalink
		$procedure_url = get_term_link( $procedure );
		if ( is_wp_error( $procedure_url ) ) {
			$procedure_url = '#';
		}

		// Generate procedure link
		$procedure_name = esc_html( $procedure->name );
		$case_count     = $procedure->count;

		// Check if filter counts should be displayed
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );
		$count_display  = ( $show_filter_counts && $case_count > 0 ) ? sprintf( ' <span class="brag-book-gallery-nav-count">(%d)</span>', $case_count ) : '';

		return sprintf(
			'<li class="brag-book-gallery-nav-list-submenu-item">
				<a href="%s" class="brag-book-gallery-nav-link" data-procedure-id="%s" data-term-id="%s" data-procedure-slug="%s" data-category="%s" data-nudity="%s">%s%s</a>
			</li>',
			esc_url( $procedure_url ),
			esc_attr( $procedure_id ),
			esc_attr( $procedure->term_id ),
			esc_attr( $procedure->slug ),
			esc_attr( $category_slug ),
			$nudity ? 'true' : 'false',
			$procedure_name,
			$count_display
		);
	}

	/**
	 * Get taxonomy data formatted for sidebar generation
	 *
	 * Fetches procedures taxonomy data and formats it to match the API structure
	 * expected by HTML_Renderer::generate_filters_from_sidebar().
	 *
	 * @param bool $show_counts Whether to include case counts.
	 * @param bool $hide_empty Whether to hide empty categories.
	 *
	 * @return array Formatted sidebar data structure.
	 * @since 3.0.0
	 */
	public function get_taxonomy_sidebar_data( bool $show_counts = true, bool $hide_empty = true ): array {
		// Get all parent procedures (categories)
		$parent_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'parent'     => 0,
			'hide_empty' => $hide_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );


		if ( is_wp_error( $parent_terms ) || empty( $parent_terms ) ) {
			return [ 'data' => [] ];
		}

		$sidebar_data = [ 'data' => [] ];

		foreach ( $parent_terms as $parent_term ) {
			// Get child procedures for this category
			$child_terms = get_terms( [
				'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
				'parent'     => $parent_term->term_id,
				'hide_empty' => $hide_empty,
				'orderby'    => 'name',
				'order'      => 'ASC',
			] );

			if ( is_wp_error( $child_terms ) ) {
				$child_terms = [];
			}

			// Format procedures data
			$procedures  = [];
			$total_cases = 0;

			foreach ( $child_terms as $child_term ) {
				$procedure_meta = $this->get_procedure_meta( $child_term->term_id );
				$case_count     = $show_counts ? $child_term->count : 0;
				$total_cases    += $case_count;

				$procedures[] = [
					'id'           => $procedure_meta['procedure_id'] ?: $child_term->term_id,
					'name'         => $child_term->name,
					'slug'         => $child_term->slug,
					'seoSuffixUrl' => $child_term->slug,
					'caseCount'    => $case_count,
					'nudity'       => 'true' === $procedure_meta['nudity'],
					'memberId'     => $procedure_meta['member_id'] ?: '',
				];
			}

			// Format category data to match API structure
			$sidebar_data['data'][] = [
				'id'         => $parent_term->term_id,
				'name'       => $parent_term->name,
				'slug'       => $parent_term->slug,
				'caseCount'  => $show_counts ? $total_cases : 0,
				'procedures' => $procedures,
			];
		}

		return $sidebar_data;
	}

	/**
	 * Get procedure metadata
	 *
	 * Retrieves stored meta data for a procedure term.
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return array Procedure meta data with defaults.
	 * @since 3.0.0
	 */
	private function get_procedure_meta( int $term_id ): array {
		return [
			'procedure_id' => get_term_meta( $term_id, 'procedure_id', true ) ?: '',
			'member_id'    => get_term_meta( $term_id, 'member_id', true ) ?: '',
			'nudity'       => get_term_meta( $term_id, 'nudity', true ) ?: 'false',
			'banner_image' => get_term_meta( $term_id, 'banner_image', true ) ?: '',
		];
	}
}
