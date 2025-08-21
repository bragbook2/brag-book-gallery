<?php
/**
 * HTML Renderer for BRAGBookGallery plugin.
 *
 * Handles all HTML generation and rendering for gallery components.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Setup;

/**
 * Class HTML_Renderer
 *
 * Manages all HTML rendering and generation for the plugin.
 *
 * @since 3.0.0
 */
final class HTML_Renderer {

	/**
	 * Default word limit for descriptions.
	 *
	 * @var int
	 */
	private const DEFAULT_WORD_LIMIT = 50;

	/**
	 * Default carousel item limit.
	 *
	 * @var int
	 */
	private const DEFAULT_CAROUSEL_LIMIT = 10;

	/**
	 * Default start index for carousel.
	 *
	 * @var int
	 */
	private const DEFAULT_START_INDEX = 0;

	/**
	 * Format procedure display name with proper casing and formatting.
	 *
	 * @since 3.0.0
	 * @param string $procedure_name The procedure name to format.
	 * @return string Formatted procedure name.
	 */
	public static function format_procedure_display_name( string $procedure_name ): string {
		// Handle known special cases
		$special_cases = [
			'ipl bbl laser' => 'IPL / BBL Laser',
			'ipl/bbl laser' => 'IPL / BBL Laser',
			'ipl-bbl-laser' => 'IPL / BBL Laser',
			'co2 laser' => 'CO2 Laser',
			'co2-laser' => 'CO2 Laser',
			'halo laser' => 'HALO Laser',
			'halo-laser' => 'HALO Laser',
			'bbl laser' => 'BBL Laser',
			'bbl-laser' => 'BBL Laser',
			'ipl laser' => 'IPL Laser',
			'ipl-laser' => 'IPL Laser',
			'rf microneedling' => 'RF Microneedling',
			'rf-microneedling' => 'RF Microneedling',
			'prp therapy' => 'PRP Therapy',
			'prp-therapy' => 'PRP Therapy',
			'pdo threads' => 'PDO Threads',
			'pdo-threads' => 'PDO Threads',
			'lower lid canthoplasty' => 'Lower Lid (Canthoplasty)',
			'lower-lid-canthoplasty' => 'Lower Lid (Canthoplasty)',
			'upper lid ptosis repair' => 'Upper Lid (Ptosis Repair)',
			'upper-lid-ptosis-repair' => 'Upper Lid (Ptosis Repair)',
		];

		// Check for special cases (case-insensitive)
		$lower_name = strtolower( trim( $procedure_name ) );
		if ( isset( $special_cases[ $lower_name ] ) ) {
			return $special_cases[ $lower_name ];
		}

		// Check if the name already contains parentheses and preserve them
		if ( preg_match( '/^(.+?)\s*\((.+?)\)/', $procedure_name, $matches ) ) {
			// Already has parentheses, ensure proper capitalization
			$main_part = ucwords( strtolower( trim( $matches[1] ) ) );
			$parens_part = ucwords( strtolower( trim( $matches[2] ) ) );
			return $main_part . ' (' . $parens_part . ')';
		}

		// For slug format (with hyphens), convert to title case
		if ( strpos( $procedure_name, '-' ) !== false ) {
			// Check if it might be a procedure that should have parentheses
			if ( preg_match( '/^(lower|upper)[-\s]lid[-\s](.+)$/i', $procedure_name, $matches ) ) {
				$lid_part = ucwords( strtolower( $matches[1] ) ) . ' Lid';
				$procedure_part = ucwords( str_replace( '-', ' ', $matches[2] ) );
				return $lid_part . ' (' . $procedure_part . ')';
			}
			return ucwords( str_replace( '-', ' ', $procedure_name ) );
		}

		// If it already looks properly formatted (has uppercase letters), keep it
		if ( preg_match( '/[A-Z]/', $procedure_name ) ) {
			return $procedure_name;
		}

		// Default: convert to title case
		return ucwords( strtolower( $procedure_name ) );
	}

	/**
	 * Limit words in a text string.
	 *
	 * @since 3.0.0
	 * @param mixed $text       Text to limit.
	 * @param int   $word_limit Maximum word count.
	 * @return string Limited text.
	 */
	public static function limit_words( mixed $text, int $word_limit = self::DEFAULT_WORD_LIMIT ): string {
		// Convert to string and sanitize
		$text = match ( true ) {
			is_string( $text ) => trim( $text ),
			is_numeric( $text ) => (string) $text,
			is_object( $text ) && method_exists( $text, '__toString' ) => (string) $text,
			default => '',
		};

		// Return empty string if no valid text
		if ( empty( $text ) ) {
			return '';
		}

		// Split words and limit count
		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $words ) || count( $words ) <= $word_limit ) {
			return $text;
		}

		return implode( ' ', array_slice( $words, 0, $word_limit ) );
	}

	/**
	 * Generate filters HTML from sidebar data.
	 *
	 * @since 3.0.0
	 * @param array $sidebar_data Sidebar data from API.
	 * @return string Filters HTML.
	 */
	public static function generate_filters_from_sidebar( array $sidebar_data ): string {
		$html = '';

		// Check if we have valid sidebar data
		if ( empty( $sidebar_data ) || ! isset( $sidebar_data['data'] ) || ! is_array( $sidebar_data['data'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAG book Gallery: No sidebar data, using default filters' );
			}
			return self::generate_default_filters();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BRAG book Gallery: Generating filters for ' . count( $sidebar_data['data'] ) . ' categories' );
		}

		// Process each category from the sidebar data
		foreach ( $sidebar_data['data'] as $category_data ) {
			if ( ! isset( $category_data['name'] ) || ! isset( $category_data['procedures'] ) ) {
				continue;
			}

			$category_name = sanitize_text_field( $category_data['name'] );
			$procedures = $category_data['procedures'];
			$total_cases = absint( $category_data['totalCase'] ?? 0 );

			// Skip if no procedures
			if ( empty( $procedures ) || ! is_array( $procedures ) ) {
				continue;
			}

			// Generate category slug for data attributes
			$category_slug = sanitize_title( $category_name );

			// Build the filter group HTML using sprintf for better readability
			$html .= sprintf(
				'<div class="brag-book-gallery-nav-list__item" data-category="%s" data-expanded="false">',
				esc_attr( $category_slug )
			);

			$html .= sprintf(
				'<button class="brag-book-gallery-nav-button" data-category="%1$s" data-expanded="false" aria-label="%2$s">',
				esc_attr( $category_slug ),
				/* translators: %s: Category name */
				esc_attr( sprintf( __( '%s category filter', 'brag-book-gallery' ), $category_name ) )
			);

			$html .= '<div class="brag-book-gallery-nav-button__label">';
			$html .= sprintf(
				'<span>%s</span>',
				esc_html( $category_name )
			);
			$html .= sprintf(
				'<span class="brag-book-gallery-filter-count">(%d)</span>',
				$total_cases
			);
			$html .= '</div>';
			$html .= '<svg class="brag-book-gallery-nav-button__toggle" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z"/></svg>';
			$html .= '</button>';
			$html .= '<ul class="brag-book-gallery-nav-list-submenu" data-expanded="false">';

			// Add procedures as filter options
			foreach ( $procedures as $procedure ) {
				$raw_procedure_name = sanitize_text_field( $procedure['name'] ?? '' );
				$procedure_name = self::format_procedure_display_name( $raw_procedure_name );
				$procedure_slug = sanitize_title( $procedure['slugName'] ?? $raw_procedure_name );
				$case_count = absint( $procedure['totalCase'] ?? 0 );
				$procedure_ids = $procedure['ids'] ?? array();

				// Debug: Log procedure details for HALO Laser and Liposuction
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG &&
					( stripos( $procedure_name, 'liposuction' ) !== false ||
					  stripos( $procedure_name, 'halo' ) !== false ) ) {
					error_log( 'Sidebar procedure debug - ' . $procedure_name . ':' );
					error_log( '  Procedure IDs: ' . print_r( $procedure_ids, true ) );
					error_log( '  Case count: ' . $case_count );
					error_log( '  Full procedure data: ' . print_r( $procedure, true ) );
				}

				// Ensure procedure IDs are properly sanitized
				if ( is_array( $procedure_ids ) ) {
					$procedure_ids = array_map( 'absint', $procedure_ids );
					$procedure_id_str = implode( ',', $procedure_ids );
				} else {
					$procedure_id_str = '';
				}

				if ( empty( $procedure_name ) ) {
					continue;
				}

				// Get current page URL and append filter path (procedure only, no category)
				$current_url = get_permalink();
				$base_path = parse_url( $current_url, PHP_URL_PATH ) ?: '';
				$filter_url = rtrim( $base_path, '/' ) . '/' . $procedure_slug;

				// Wrap in a li for semantic list
				$html .= '<li class="brag-book-gallery-nav-list-submenu__item">';

				// Check if procedure has nudity
				$has_nudity = ! empty( $procedure['nudity'] ) ? 'true' : 'false';

				$html .= sprintf(
					'<a href="%1$s" class="brag-book-gallery-nav-link" data-category="%2$s" data-procedure="%3$s" data-procedure-ids="%4$s" data-procedure-count="%5$d" data-nudity="%6$s">',
					esc_url( $filter_url ),
					esc_attr( $category_slug ),
					esc_attr( $procedure_slug ),
					esc_attr( $procedure_id_str ),
					$case_count,
					esc_attr( $has_nudity )
				);

				$html .= sprintf(
					'<span class="brag-book-gallery-filter-option-label">%1$s</span>',
					esc_html( $procedure_name )
				);

				$html .= sprintf(
					'<span class="brag-book-gallery-filter-count">(%d)</span>',
					$case_count
				);

				$html .= '</a>';
				$html .= '</li>';
			}

			$html .= '</ul>';
			$html .= '</div>';
		}

		// Removed the favorites filter from the navigation list
		// The favorites functionality is now handled by a separate button

		return $html;
	}

	/**
	 * Generate default filters when no sidebar data is available.
	 *
	 * @since 3.0.0
	 * @return string Generated HTML for default filters.
	 */
	public static function generate_default_filters(): string {
		$html = '';

		// Add default body filter
		$html .= sprintf(
			'<div class="brag-book-gallery-nav-list__item" data-category="%s" data-expanded="false">',
			'body'
		);

		$html .= sprintf(
			'<button class="brag-book-gallery-nav-button" data-category="%1$s" data-expanded="false" aria-label="%2$s">',
			'body',
			esc_attr__( 'Body category filter', 'brag-book-gallery' )
		);

		$html .= '<div class="brag-book-gallery-nav-button__label">';
		$html .= sprintf(
			'<span>%s</span>',
			esc_html__( 'Body', 'brag-book-gallery' )
		);
		$html .= '<span class="brag-book-gallery-filter-count">(0)</span>';
		$html .= '</div>';
		$html .= '<svg class="brag-book-gallery-nav-button__toggle" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z"/></svg>';
		$html .= '</button>';
		$html .= '<div class="brag-book-gallery-nav-list-submenu" data-expanded="false">';
		$html .= sprintf(
			'<p class="no-procedures">%s</p>',
			esc_html__( 'No procedures available', 'brag-book-gallery' )
		);
		$html .= '</div>';
		$html .= '</div>';

		// Removed the favorites filter from the default filters
		// The favorites functionality is now handled by a separate button

		return $html;
	}

	/**
	 * Generate placeholder carousel items.
	 *
	 * @since 3.0.0
	 * @param int    $start Starting index.
	 * @param int    $limit Number of items to generate.
	 * @param string $procedure_slug Procedure slug for filtering.
	 * @return string Placeholder carousel HTML.
	 */
	private static function generate_placeholder_carousel_items(
		int $start = self::DEFAULT_START_INDEX,
		int $limit = self::DEFAULT_CAROUSEL_LIMIT,
		string $procedure_slug = ''
	): string {
		$items_html = '';
		$plugin_url = Setup::get_plugin_url();

		for ( $i = $start; $i < ( $start + $limit ); $i++ ) {
			$items_html .= sprintf(
				'<div class="brag-book-gallery-item placeholder-item" data-index="%d" data-procedure="%s">
					<div class="brag-book-gallery-image-container">
						<div class="brag-book-gallery-placeholder-container">
							<div class="skeleton-loader"></div>
						</div>
					</div>
				</div>',
				$i,
				esc_attr( $procedure_slug )
			);
		}

		return $items_html;
	}

	/**
	 * Generate single carousel item HTML.
	 *
	 * @since 3.0.0
	 * @param array  $case Case data.
	 * @param int    $index Item index.
	 * @param string $base_path Base URL path.
	 * @param bool   $has_nudity Whether content has nudity.
	 * @return string Carousel item HTML.
	 */
	public static function generate_single_carousel_item(
		array $case,
		int $index,
		string $base_path,
		bool $has_nudity = false
	): string {
		$case_id = $case['id'] ?? '';
		$procedure_title = $case['procedureTitle'] ?? __( 'Unknown Procedure', 'brag-book-gallery' );
		$procedure_slug = sanitize_title( $procedure_title );
		$image_url = $case['mainImageUrl'] ?? '';

		ob_start();
		?>
		<div class="brag-book-gallery-item"
			 data-index="<?php echo esc_attr( (string) $index ); ?>"
			 data-case-id="<?php echo esc_attr( $case_id ); ?>"
			 data-procedure="<?php echo esc_attr( $procedure_slug ); ?>">
			<div class="brag-book-gallery-image-container">
				<?php if ( $has_nudity ) : ?>
					<?php echo self::generate_nudity_warning(); ?>
				<?php endif; ?>
				<a href="<?php echo esc_url( "{$base_path}/{$procedure_slug}/{$case_id}/" ); ?>"
				   class="brag-book-gallery-case-link"
				   aria-label="<?php printf( esc_attr__( 'View case %s for %s', 'brag-book-gallery' ), esc_attr( $case_id ), esc_attr( $procedure_title ) ); ?>">
					<?php echo self::generate_carousel_image( $image_url, $has_nudity ); ?>
				</a>
			</div>
			<?php echo self::generate_item_actions( $case_id ); ?>
		</div>
		<?php
		return ob_get_clean();
	}




	/**
	 * Generate carousel items from data.
	 *
	 * @since 3.0.0
	 * @param array $items Carousel items data.
	 * @return string Carousel items HTML.
	 */
	public static function generate_carousel_items_from_data( array $items ): string {
		$items_html = '';
		$slide_index = 0;

		foreach ( $items as $case ) {
			if ( ! empty( $case['photos'] ) && is_array( $case['photos'] ) ) {
				foreach ( $case['photos'] as $photo ) {
					$items_html .= self::generate_carousel_slide_from_photo( $photo, $case, $slide_index );
					$slide_index++;
				}
			}
		}

		return $items_html;
	}

	/**
	 * Generate carousel slide from photo data.
	 *
	 * @since 3.0.0
	 * @param array $photo Photo data.
	 * @param array $case Case data.
	 * @param int   $slide_index Slide index.
	 * @return string Slide HTML.
	 */
	public static function generate_carousel_slide_from_photo( array $photo, array $case, int $slide_index ): string {
		$photo_id = $photo['id'] ?? '';
		// Handle different image URL field names based on API response format
		$image_url = $photo['postProcessedImageLocation'] ?? $photo['url'] ?? $photo['originalBeforeLocation'] ?? '';
		$case_id = $case['id'] ?? '';
		
		// Extract procedure title from details HTML or use direct field
		$procedure_title = '';
		if ( ! empty( $case['procedureTitle'] ) ) {
			$procedure_title = $case['procedureTitle'];
		} elseif ( ! empty( $case['details'] ) ) {
			// Try to extract from details HTML
			if ( preg_match( '/<p>([^<]+)<\/p>/', $case['details'], $matches ) ) {
				$procedure_title = trim( $matches[1] );
			}
		}
		
		// Default alt text based on procedure or generic
		$alt_text = ! empty( $procedure_title ) 
			? $procedure_title . ' before and after result'
			: 'Body procedure before and after result';
		
		// Check if SEO alt text is provided
		if ( ! empty( $photo['seoAltText'] ) ) {
			$alt_text = $photo['seoAltText'];
		}

		// Check for nudity flag in photo data
		$has_nudity = ! empty( $photo['hasNudity'] ) || ! empty( $photo['nudity'] );
		
		// Generate unique slide ID using case and photo IDs
		$slide_id = 'bd-' . $slide_index;
		if ( ! empty( $case_id ) && ! empty( $photo_id ) ) {
			$slide_id = $case_id . '-' . $photo_id;
		}
		
		// Count total slides from photos array
		$total_slides = count( $case['photos'] ?? [] );

		ob_start();
		?>
		<div class="brag-book-gallery-carousel-item" 
			 data-slide="<?php echo esc_attr( $slide_id ); ?>" 
			 role="group" 
			 aria-roledescription="slide" 
			 aria-label="<?php echo esc_attr( sprintf( 'Slide %d of %d', $slide_index + 1, $total_slides ) ); ?>">
			<?php if ( $has_nudity ) : ?>
				<div class="brag-book-gallery-nudity-warning">
					<div class="brag-book-gallery-nudity-warning-content">
						<h4 class="brag-book-gallery-nudity-warning-title"><?php esc_html_e( 'WARNING: Contains Nudity', 'brag-book-gallery' ); ?></h4>
						<p class="brag-book-gallery-nudity-warning-caption"><?php esc_html_e( 'If you are offended by such material or are under 18 years of age. Please do not proceed.', 'brag-book-gallery' ); ?></p>
						<button class="brag-book-gallery-nudity-warning-button" type="button"><?php esc_html_e( 'Proceed', 'brag-book-gallery' ); ?></button>
					</div>
				</div>
			<?php endif; ?>
			<picture class="brag-book-gallery-carousel-image">
				<source srcset="<?php echo esc_url( $image_url ); ?>" type="image/jpeg">
				<img src="<?php echo esc_url( $image_url ); ?>" 
					 alt="<?php echo esc_attr( $alt_text ); ?>" 
					 loading="lazy"
					 <?php if ( $has_nudity ) : ?>class="brag-book-gallery-nudity-blur"<?php endif; ?>
					 width="400"
					 height="300">
			</picture>
			<div class="brag-book-gallery-item-actions">
				<button class="brag-book-gallery-favorite-button" 
						data-favorited="false" 
						data-item-id="<?php echo esc_attr( 'case-' . $case_id ); ?>"
						aria-label="<?php esc_attr_e( 'Add to favorites', 'brag-book-gallery' ); ?>">
					<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate carousel image with custom alt text.
	 *
	 * @since 3.0.0
	 * @param string $image_url Image URL.
	 * @param bool   $has_nudity Whether image has nudity.
	 * @param string $alt_text Alt text for image.
	 * @return string Image HTML.
	 */
	private static function generate_carousel_image_with_alt( string $image_url, bool $has_nudity, string $alt_text ): string {
		$blur_class = $has_nudity ? ' brag-book-carousel-nudity-blur' : '';
		return sprintf(
			'<picture class="brag-book-carousel-image%s">
				<img src="%s" alt="%s" loading="lazy" />
			</picture>',
			esc_attr( $blur_class ),
			esc_url( $image_url ),
			esc_attr( $alt_text )
		);
	}

	/**
	 * Generate nudity warning overlay.
	 *
	 * @since 3.0.0
	 * @return string Generated HTML for nudity warning.
	 */
	public static function generate_nudity_warning(): string {
		return sprintf(
			'<div class="brag-book-carousel-nudity-warning" aria-label="%s">
				<div class="nudity-warning-content">
					<span class="nudity-warning-text">%s</span>
				</div>
			</div>',
			esc_attr__( 'This image contains nudity', 'brag-book-gallery' ),
			esc_html__( 'Nudity Warning', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate carousel image element.
	 *
	 * @since 3.0.0
	 * @param string $image_url Image URL.
	 * @param bool   $has_nudity Whether image contains nudity.
	 * @return string Generated HTML for image.
	 */
	public static function generate_carousel_image( string $image_url, bool $has_nudity ): string {
		$blur_class = $has_nudity ? ' brag-book-carousel-nudity-blur' : '';
		return sprintf(
			'<picture class="brag-book-carousel-image%s">
				<img src="%s" alt="%s" loading="lazy" />
			</picture>',
			esc_attr( $blur_class ),
			esc_url( $image_url ),
			esc_attr__( 'Before and after procedure photo', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate item action buttons.
	 *
	 * @since 3.0.0
	 * @param string $item_id Item identifier.
	 * @return string Generated HTML for action buttons.
	 */
	public static function generate_item_actions( string $item_id ): string {
		return sprintf(
			'<div class="brag-book-carousel-actions">
				<button class="brag-book-gallery-favorite-btn" data-case-id="%1$s" aria-label="%2$s" title="%3$s">
					<svg class="heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
					</svg>
				</button>
				<button class="brag-book-gallery-share-button" data-case-id="%1$s" aria-label="%4$s" title="%5$s">
					<svg class="share-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="18" cy="5" r="3"></circle>
						<circle cx="6" cy="12" r="3"></circle>
						<circle cx="18" cy="19" r="3"></circle>
						<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
						<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
					</svg>
				</button>
			</div>',
			esc_attr( $item_id ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' ),
			esc_attr__( 'Share this image', 'brag-book-gallery' ),
			esc_attr__( 'Share this image', 'brag-book-gallery' )
		);
	}

	/**
	 * Render case details HTML.
	 *
	 * @since 3.0.0
	 * @param array $case_data Case data array.
	 * @return string Rendered HTML.
	 */
	public static function render_case_details_html( array $case_data ): string {
		$html = '<div class="brag-book-gallery-case-detail-view">';

		// Get procedure name and case ID
		$procedure_name = '';
		$procedure_slug = '';
		if ( ! empty( $case_data['procedures'] ) && is_array( $case_data['procedures'] ) ) {
			$first_procedure = reset( $case_data['procedures'] );
			$procedure_name = $first_procedure['name'] ?? '';
			$procedure_slug = sanitize_title( $procedure_name );
		}
		$case_id = $case_data['id'] ?? '';

		// Get gallery page slug from settings
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'before-after' );
		// If it's an array (for multiple slugs), use the first one
		if ( is_array( $gallery_slug ) ) {
			$gallery_slug = ! empty( $gallery_slug[0] ) ? $gallery_slug[0] : 'before-after';
		}
		$base_path = '/' . ltrim( $gallery_slug, '/' );

		// Header section with navigation and title
		$html .= '<div class="brag-book-gallery-brag-book-gallery-case-header-section">';

		// Back to gallery link
		$html .= '<div class="brag-book-gallery-case-navigation">';
		$html .= '<a href="' . esc_url( $base_path ) . '" class="brag-book-gallery-back-link">← Back to Gallery</a>';
		$html .= '</div>';

		// Case header with title
		$html .= '<div class="brag-book-gallery-brag-book-gallery-case-header">';
		$html .= '<h1 class="brag-book-gallery-case-title">';
		$html .= esc_html( $procedure_name );
		if ( ! empty( $case_id ) ) {
			$html .= ' <span class="case-id">#' . esc_html( $case_id ) . '</span>';
		}
		$html .= '</h1>';
		$html .= '</div>';
		$html .= '</div>';

		// Main content container
		$html .= '<div class="brag-book-gallery-brag-book-gallery-case-content">';

		// Images section with main viewer and thumbnails
		$html .= '<div class="brag-book-gallery-case-images-section">';

		if ( ! empty( $case_data['photoSets'] ) && is_array( $case_data['photoSets'] ) ) {
			$html .= '<div class="brag-book-gallery-case-images-layout">';

			// Main image viewer (left side)
			$html .= '<div class="brag-book-gallery-case-main-viewer">';

			// Display first image as main by default
			$first_photo = reset( $case_data['photoSets'] );
			$main_image_url = ! empty( $first_photo['postProcessedImageLocation'] ) ? $first_photo['postProcessedImageLocation'] : '';

			// Main image display - single processed image
			$html .= '<div class="brag-book-gallery-main-image-container" data-image-index="0">';

			if ( $main_image_url ) {
				$html .= '<div class="brag-book-gallery-main-single">';
				$html .= '<img src="' . esc_url( $main_image_url ) . '" alt="' . esc_attr( $procedure_name . ' - Case ' . $case_id ) . '" loading="eager">';

				// Action buttons on main image
				$html .= '<div class="brag-book-gallery-item-actions">';
				$html .= '<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case_' . esc_attr( $case_id ) . '_main" aria-label="Add to favorites">';
				$html .= '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
				$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
				$html .= '</svg>';
				$html .= '</button>';

				$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
				if ( $enable_sharing === 'yes' ) {
					$html .= '<button class="brag-book-gallery-share-button" data-item-id="case_' . esc_attr( $case_id ) . '_main" aria-label="Share this image">';
					$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
					$html .= '<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>';
					$html .= '</svg>';
					$html .= '</button>';
				}
				$html .= '</div>';

				$html .= '</div>';
			}

			$html .= '</div>'; // Close main-image-container
			$html .= '</div>'; // Close main-viewer

			// Thumbnail sidebar (right side)
			if ( count( $case_data['photoSets'] ) > 1 ) {
				$html .= '<div class="brag-book-gallery-case-thumbnails">';
				$html .= '<div class="brag-book-gallery-thumbnails-grid">';

				foreach ( $case_data['photoSets'] as $index => $photo ) {
					$processed_thumb = ! empty( $photo['postProcessedImageLocation'] ) ? $photo['postProcessedImageLocation'] : '';

					if ( $processed_thumb ) {
						$active_class = $index === 0 ? ' active' : '';

						$html .= '<div class="brag-book-gallery-thumbnail-item' . $active_class . '" data-image-index="' . esc_attr( $index ) . '" ';
						$html .= 'data-processed-url="' . esc_attr( $processed_thumb ) . '">';
						$html .= '<img src="' . esc_url( $processed_thumb ) . '" alt="Thumbnail" loading="lazy">';
						$html .= '</div>';
					}
				}

				$html .= '</div>'; // Close thumbnails-grid
				$html .= '</div>'; // Close thumbnails
			}

			$html .= '</div>'; // Close images-layout
		} else {
			$html .= '<div class="brag-book-gallery-no-images-container">';
			$html .= '<p class="brag-book-gallery-no-images">No images available for this case.</p>';
			$html .= '</div>';
		}

		$html .= '</div>'; // Close images-section

		// Details section - now below images in a card layout
		$html .= '<div class="brag-book-gallery-case-details-section">';
		$html .= '<div class="brag-book-gallery-case-details-grid">';

		// Procedures performed card
		if ( ! empty( $case_data['procedures'] ) && is_array( $case_data['procedures'] ) ) {
			$html .= '<div class="case-detail-card procedures-performed-card">';
			$html .= '<div class="card-header">';
			$html .= '<h3 class="card-title">Procedures Performed</h3>';
			$html .= '</div>';
			$html .= '<div class="card-content">';
			$html .= '<div class="brag-book-gallery-procedure-badges-list">';
			foreach ( $case_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$html .= '<span class="procedure-badge">' . esc_html( $procedure['name'] ) . '</span>';
				}
			}
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		// Patient details card
		$html .= '<div class="case-detail-card patient-details-card">';
		$html .= '<div class="card-header">';
		$html .= '<h3 class="card-title">Patient Information</h3>';
		$html .= '</div>';
		$html .= '<div class="card-content">';
		$html .= '<div class="patient-info-grid">';

		// Ethnicity
		if ( ! empty( $case_data['ethnicity'] ) ) {
			$html .= '<div class="brag-book-gallery-info-item">';
			$html .= '<span class="brag-book-gallery-info-label">Ethnicity</span>';
			$html .= '<span class="brag-book-gallery-info-value">' . esc_html( $case_data['ethnicity'] ) . '</span>';
			$html .= '</div>';
		}

		// Gender
		if ( ! empty( $case_data['gender'] ) ) {
			$html .= '<div class="brag-book-gallery-info-item">';
			$html .= '<span class="brag-book-gallery-info-label">Gender</span>';
			$html .= '<span class="brag-book-gallery-info-value">' . esc_html( ucfirst( $case_data['gender'] ) ) . '</span>';
			$html .= '</div>';
		}

		// Age
		if ( ! empty( $case_data['age'] ) ) {
			$html .= '<div class="brag-book-gallery-info-item">';
			$html .= '<span class="brag-book-gallery-info-label">Age</span>';
			$html .= '<span class="brag-book-gallery-info-value">' . esc_html( $case_data['age'] ) . ' years</span>';
			$html .= '</div>';
		}

		// Height
		if ( ! empty( $case_data['height'] ) ) {
			$html .= '<div class="brag-book-gallery-info-item">';
			$html .= '<span class="brag-book-gallery-info-label">Height</span>';
			$html .= '<span class="brag-book-gallery-info-value">' . esc_html( $case_data['height'] ) . '</span>';
			$html .= '</div>';
		}

		// Weight
		if ( ! empty( $case_data['weight'] ) ) {
			$html .= '<div class="brag-book-gallery-info-item">';
			$html .= '<span class="brag-book-gallery-info-label">Weight</span>';
			$html .= '<span class="brag-book-gallery-info-value">' . esc_html( $case_data['weight'] ) . ' lbs</span>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		// Procedure details card
		if ( ! empty( $case_data['procedureDetails'] ) && is_array( $case_data['procedureDetails'] ) ) {
			$html .= '<div class="case-detail-card procedure-details-card">';
			$html .= '<div class="card-header">';
			$html .= '<h3 class="card-title">Procedure Details</h3>';
			$html .= '</div>';
			$html .= '<div class="card-content">';

			// Iterate through each procedure's details
			foreach ( $case_data['procedureDetails'] as $procedure_id => $details ) {
				if ( is_array( $details ) && ! empty( $details ) ) {
					$html .= '<div class="procedure-details-grid">';
					foreach ( $details as $label => $value ) {
						if ( ! empty( $value ) ) {
							$html .= '<div class="brag-book-gallery-info-item">';
							$html .= '<span class="brag-book-gallery-info-label">' . esc_html( $label ) . '</span>';
							$html .= '<span class="brag-book-gallery-info-value">' . esc_html( $value ) . '</span>';
							$html .= '</div>';
						}
					}
					$html .= '</div>';
				}
			}

			$html .= '</div>';
			$html .= '</div>';
		}

		// Case details card
		if ( ! empty( $case_data['details'] ) ) {
			$html .= '<div class="case-detail-card case-notes-card">';
			$html .= '<div class="card-header">';
			$html .= '<h3 class="card-title">Case Notes</h3>';
			$html .= '</div>';
			$html .= '<div class="card-content">';
			$html .= '<div class="case-details-content">';
			$html .= wp_kses_post( $case_data['details'] );
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>'; // End brag-book-gallery-case-details-grid
		$html .= '</div>'; // End case-details-section
		$html .= '</div>'; // End brag-book-gallery-case-content
		$html .= '</div>'; // End main container

		return $html;
	}
}
