<?php
/**
 * HTML Renderer for BRAGBookGallery plugin.
 *
 * Handles all HTML generation and rendering for gallery components.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare( strict_types=1 );

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
	 * @param string $procedure_name The procedure name to format.
	 *
	 * @return string Formatted procedure name.
	 * @since 3.0.0
	 */
	public static function format_procedure_display_name( string $procedure_name ): string {
		// Handle known special cases
		$special_cases = [
			'ipl bbl laser'           => 'IPL / BBL Laser',
			'ipl/bbl laser'           => 'IPL / BBL Laser',
			'ipl-bbl-laser'           => 'IPL / BBL Laser',
			'co2 laser'               => 'CO2 Laser',
			'co2-laser'               => 'CO2 Laser',
			'halo laser'              => 'HALO Laser',
			'halo-laser'              => 'HALO Laser',
			'bbl laser'               => 'BBL Laser',
			'bbl-laser'               => 'BBL Laser',
			'ipl laser'               => 'IPL Laser',
			'ipl-laser'               => 'IPL Laser',
			'rf microneedling'        => 'RF Microneedling',
			'rf-microneedling'        => 'RF Microneedling',
			'prp therapy'             => 'PRP Therapy',
			'prp-therapy'             => 'PRP Therapy',
			'pdo threads'             => 'PDO Threads',
			'pdo-threads'             => 'PDO Threads',
			'lower lid canthoplasty'  => 'Lower Lid (Canthoplasty)',
			'lower-lid-canthoplasty'  => 'Lower Lid (Canthoplasty)',
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
			$main_part   = ucwords( strtolower( trim( $matches[1] ) ) );
			$parens_part = ucwords( strtolower( trim( $matches[2] ) ) );

			return $main_part . ' (' . $parens_part . ')';
		}

		// For slug format (with hyphens), convert to title case
		if ( strpos( $procedure_name, '-' ) !== false ) {
			// Check if it might be a procedure that should have parentheses
			if ( preg_match( '/^(lower|upper)[-\s]lid[-\s](.+)$/i', $procedure_name, $matches ) ) {
				$lid_part       = ucwords( strtolower( $matches[1] ) ) . ' Lid';
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
	 * @param mixed $text Text to limit.
	 * @param int $word_limit Maximum word count.
	 *
	 * @return string Limited text.
	 * @since 3.0.0
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
		$words = preg_split( '/\s+/', $text, - 1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $words ) || count( $words ) <= $word_limit ) {
			return $text;
		}

		return implode( ' ', array_slice( $words, 0, $word_limit ) );
	}

	/**
	 * Generate filters HTML from sidebar data.
	 *
	 * @param array $sidebar_data Sidebar data from API.
	 *
	 * @return string Filters HTML.
	 * @since 3.0.0
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
			$procedures    = $category_data['procedures'];
			$total_cases   = absint( $category_data['totalCase'] ?? 0 );

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
				$procedure_name     = self::format_procedure_display_name( $raw_procedure_name );
				$procedure_slug     = sanitize_title( $procedure['slugName'] ?? $raw_procedure_name );
				$case_count         = absint( $procedure['totalCase'] ?? 0 );
				$procedure_ids      = $procedure['ids'] ?? array();

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
					$procedure_ids    = array_map( 'absint', $procedure_ids );
					$procedure_id_str = implode( ',', $procedure_ids );
				} else {
					$procedure_id_str = '';
				}

				if ( empty( $procedure_name ) ) {
					continue;
				}

				// Get current page URL and append filter path (procedure only, no category)
				$current_url = get_permalink();
				$base_path   = parse_url( $current_url, PHP_URL_PATH ) ?: '';
				$filter_url  = rtrim( $base_path, '/' ) . '/' . $procedure_slug;

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
	 * @return string Generated HTML for default filters.
	 * @since 3.0.0
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
	 * @param int $start Starting index.
	 * @param int $limit Number of items to generate.
	 * @param string $procedure_slug Procedure slug for filtering.
	 *
	 * @return string Placeholder carousel HTML.
	 * @since 3.0.0
	 */
	private static function generate_placeholder_carousel_items(
		int $start = self::DEFAULT_START_INDEX,
		int $limit = self::DEFAULT_CAROUSEL_LIMIT,
		string $procedure_slug = ''
	): string {
		$items_html = '';
		$plugin_url = Setup::get_plugin_url();

		for ( $i = $start; $i < ( $start + $limit ); $i ++ ) {
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
	 * @param array $case Case data.
	 * @param int $index Item index.
	 * @param string $base_path Base URL path.
	 * @param bool $has_nudity Whether content has nudity.
	 *
	 * @return string Carousel item HTML.
	 * @since 3.0.0
	 */
	public static function generate_single_carousel_item(
		array $case,
		int $index,
		string $base_path,
		bool $has_nudity = false
	): string {
		$case_id         = $case['id'] ?? '';
		$procedure_title = $case['procedureTitle'] ?? __( 'Unknown Procedure', 'brag-book-gallery' );
		$procedure_slug  = sanitize_title( $procedure_title );
		$image_url       = $case['mainImageUrl'] ?? '';

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
				   class="brag-book-gallery-case-card-link"
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
	 * @param array $items Carousel items data.
	 *
	 * @return string Carousel items HTML.
	 * @since 3.0.0
	 */
	public static function generate_carousel_items_from_data( array $items ): string {
		$items_html  = '';
		$slide_index = 0;

		foreach ( $items as $case ) {
			if ( ! empty( $case['photos'] ) && is_array( $case['photos'] ) ) {
				foreach ( $case['photos'] as $photo ) {
					$items_html .= self::generate_carousel_slide_from_photo( $photo, $case, $slide_index );
					$slide_index ++;
				}
			}
		}

		return $items_html;
	}

	/**
	 * Generate carousel slide from photo data.
	 *
	 * @param array $photo Photo data.
	 * @param array $case Case data.
	 * @param int $slide_index Slide index.
	 *
	 * @return string Slide HTML.
	 * @since 3.0.0
	 */
	public static function generate_carousel_slide_from_photo( array $photo, array $case, int $slide_index, string $procedure_slug = '' ): string {
		$photo_id = $photo['id'] ?? '';
		// Handle different image URL field names based on API response format
		$image_url = $photo['postProcessedImageLocation'] ?? $photo['url'] ?? $photo['originalBeforeLocation'] ?? '';
		$case_id   = $case['id'] ?? '';

		// Build case URL
		$case_url = '';
		if ( ! empty( $case_id ) ) {
			// Get gallery page slug from settings (correct option name)
			$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', [] );

			// Handle both array and string formats
			if ( is_array( $gallery_slug_option ) ) {
				$gallery_slug = ! empty( $gallery_slug_option[0] ) ? $gallery_slug_option[0] : 'before-after';
			} else {
				$gallery_slug = ! empty( $gallery_slug_option ) ? $gallery_slug_option : 'before-after';
			}

			// Use the procedure slug passed from the shortcode if available
			// Otherwise try to determine it from the case data
			if ( empty( $procedure_slug ) ) {
				// Try to get procedure information from the case
				if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
					// Get the first procedure
					$first_procedure = reset( $case['procedures'] );
					if ( ! empty( $first_procedure['slugName'] ) ) {
						$procedure_slug = $first_procedure['slugName'];
					} elseif ( ! empty( $first_procedure['name'] ) ) {
						$procedure_slug = sanitize_title( $first_procedure['name'] );
					}
				}

				// Final fallback
				if ( empty( $procedure_slug ) ) {
					$procedure_slug = 'case';
				}
			}

			// Get SEO suffix URL from caseDetails array (based on actual API response structure)
			$seo_suffix = '';
			if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
				$first_detail = reset( $case['caseDetails'] );
				$seo_suffix = ! empty( $first_detail['seoSuffixUrl'] ) ? $first_detail['seoSuffixUrl'] : '';
			}

			// Use SEO suffix if available, otherwise use case ID
			$case_identifier = ! empty( $seo_suffix ) ? $seo_suffix : $case_id;

			// Build the URL
			$case_url = home_url( '/' . $gallery_slug . '/' . $procedure_slug . '/' . $case_identifier );
		}

		// Default alt text
		$alt_text = 'Before and after procedure result';

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
			<?php if ( ! empty( $case_url ) ) : ?>
				<a href="<?php echo esc_url( $case_url ); ?>"
				   class="brag-book-gallery-carousel-link"
				   aria-label="<?php echo esc_attr( 'View case details for ' . $alt_text ); ?>">
			<?php endif; ?>
			<?php if ( $has_nudity ) : ?>
				<div class="brag-book-gallery-nudity-warning">
					<div class="brag-book-gallery-nudity-warning-content">
						<h4 class="brag-book-gallery-nudity-warning-title"><?php esc_html_e( 'WARNING: Contains Nudity', 'brag-book-gallery' ); ?></h4>
						<p class="brag-book-gallery-nudity-warning-caption"><?php esc_html_e( 'If you are offended by such material or are under 18 years of age. Please do not proceed.', 'brag-book-gallery' ); ?></p>
						<button class="brag-book-gallery-nudity-warning-button"
								type="button"><?php esc_html_e( 'Proceed', 'brag-book-gallery' ); ?></button>
					</div>
				</div>
			<?php endif; ?>
			<picture class="brag-book-gallery-carousel-image">
				<source srcset="<?php echo esc_url( $image_url ); ?>"
						type="image/jpeg">
				<img src="<?php echo esc_url( $image_url ); ?>"
					 alt="<?php echo esc_attr( $alt_text ); ?>"
					 loading="lazy"
					 <?php if ( $has_nudity ) : ?>class="brag-book-gallery-nudity-blur"<?php endif; ?>>
			</picture>
			<?php if ( ! empty( $case_url ) ) : ?>
				</a>
			<?php endif; ?>
			<div class="brag-book-gallery-item-actions">
				<button class="brag-book-gallery-favorite-button"
						data-favorited="false"
						data-item-id="<?php echo esc_attr( 'case-' . $case_id ); ?>"
						aria-label="<?php esc_attr_e( 'Add to favorites', 'brag-book-gallery' ); ?>">
					<svg fill="rgba(255, 255, 255, 0.5)" stroke="white"
						 stroke-width="2" viewBox="0 0 24 24">
						<path
							d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
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
	 * @param string $image_url Image URL.
	 * @param bool $has_nudity Whether image has nudity.
	 * @param string $alt_text Alt text for image.
	 *
	 * @return string Image HTML.
	 * @since 3.0.0
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
	 * @return string Generated HTML for nudity warning.
	 * @since 3.0.0
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
	 * @param string $image_url Image URL.
	 * @param bool $has_nudity Whether image contains nudity.
	 *
	 * @return string Generated HTML for image.
	 * @since 3.0.0
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
	 * @param string $item_id Item identifier.
	 *
	 * @return string Generated HTML for action buttons.
	 * @since 3.0.0
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
	 * @param array $case_data Case data array.
	 *
	 * @return array Array with 'html' and 'seo' keys.
	 * @since 3.0.0
	 */
	public static function render_case_details_html( array $case_data ): array {
		$html = '<div class="brag-book-gallery-case-detail-view">';

		// Debug: Log the case data structure
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Case data keys: ' . implode( ', ', array_keys( $case_data ) ) );
			if ( isset( $case_data['procedures'] ) ) {
				error_log( 'Procedures data: ' . print_r( $case_data['procedures'], true ) );
			}
			if ( isset( $case_data['procedureIds'] ) ) {
				error_log( 'Procedure IDs: ' . print_r( $case_data['procedureIds'], true ) );
			}
		}

		// Get procedure name and case ID
		$procedure_name = '';
		$procedure_slug = '';

		// Check if we have procedures data (might be 'procedures' or 'procedureIds')
		$procedure_ids = [];

		// First check for 'procedures' array with objects
		if ( ! empty( $case_data['procedures'] ) && is_array( $case_data['procedures'] ) ) {
			$first_procedure = reset( $case_data['procedures'] );

			// Check if we have a name directly (shouldn't happen with case detail endpoint)
			if ( ! empty( $first_procedure['name'] ) ) {
				$raw_procedure_name = $first_procedure['name'];
				$procedure_name     = self::format_procedure_display_name( $raw_procedure_name );
				$procedure_slug     = sanitize_title( $raw_procedure_name );
			}
			// Otherwise, we have procedure IDs and need to look them up
			elseif ( ! empty( $first_procedure['id'] ) ) {
				$procedure_ids[] = intval( $first_procedure['id'] );
			}
		}
		// Also check for 'procedureIds' array (API might return this format)
		elseif ( ! empty( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			$procedure_ids = array_map( 'intval', $case_data['procedureIds'] );
		}

		// If we have procedure IDs but no name yet, look them up
		if ( empty( $procedure_name ) && ! empty( $procedure_ids ) ) {
			// Get API configuration
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$api_token  = ! empty( $api_tokens[0] ) ? $api_tokens[0] : '';

			if ( ! empty( $api_token ) ) {
				// Get sidebar data which contains procedure mappings
				$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Looking for procedure ID: ' . $procedure_ids[0] );
					error_log( 'Sidebar data available: ' . ( ! empty( $sidebar_data['data'] ) ? 'Yes' : 'No' ) );
				}

				// Search for the procedure name by ID
				if ( ! empty( $sidebar_data['data'] ) ) {
					$procedure_id = $procedure_ids[0]; // Use first procedure ID
					$found = false;

					foreach ( $sidebar_data['data'] as $category ) {
						if ( ! empty( $category['procedures'] ) ) {
							foreach ( $category['procedures'] as $proc ) {
								// Debug each procedure
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $proc['ids'] ) ) {
									error_log( 'Checking procedure "' . ( $proc['name'] ?? 'Unknown' ) . '" with IDs: ' . implode( ', ', $proc['ids'] ) );
								}

								// Check if this procedure's IDs array contains our ID
								if ( ! empty( $proc['ids'] ) && in_array( $procedure_id, $proc['ids'] ) ) {
									$raw_procedure_name = $proc['name'] ?? '';
									$procedure_name     = self::format_procedure_display_name( $raw_procedure_name );
									$procedure_slug     = sanitize_title( $proc['slugName'] ?? $raw_procedure_name );
									$found = true;

									if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
										error_log( 'Found procedure! Name: ' . $raw_procedure_name );
									}

									break 2; // Break out of both loops
								}
							}
						}
					}

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! $found ) {
						error_log( 'Procedure ID ' . $procedure_id . ' not found in sidebar data' );
					}
				}
			}

			// If we still don't have a name, use a fallback
			if ( empty( $procedure_name ) && ! empty( $procedure_ids[0] ) ) {
				$procedure_name = 'Procedure #' . $procedure_ids[0];
				$procedure_slug = 'procedure-' . $procedure_ids[0];
			}
		}

		$case_id = $case_data['id'] ?? '';

		// Extract SEO fields from caseDetails array if available
		$seo_headline = '';
		$seo_page_title = '';
		$seo_page_description = '';
		$seo_suffix_url = '';

		if ( ! empty( $case_data['caseDetails'] ) && is_array( $case_data['caseDetails'] ) ) {
			$first_detail = reset( $case_data['caseDetails'] );

			// If main ID is empty, fall back to caseDetails ID
			if ( empty( $case_id ) ) {
				$case_id = $first_detail['caseId'] ?? '';
			}

			// Extract SEO fields
			$seo_headline = ! empty( $first_detail['seoHeadline'] ) ? $first_detail['seoHeadline'] : '';
			$seo_page_title = ! empty( $first_detail['seoPageTitle'] ) ? $first_detail['seoPageTitle'] : '';
			$seo_page_description = ! empty( $first_detail['seoPageDescription'] ) ? $first_detail['seoPageDescription'] : '';
			$seo_suffix_url = ! empty( $first_detail['seoSuffixUrl'] ) ? $first_detail['seoSuffixUrl'] : '';
		}

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

		// Case header with title - use seoHeadline if available, otherwise use procedure name
		$html .= '<div class="brag-book-gallery-brag-book-gallery-case-header">';
		$html .= '<h2 class="brag-book-gallery-content-title">';
		if ( ! empty( $seo_headline ) ) {
			$html .= esc_html( $seo_headline );
		} else {
			$html .= '<strong>' . esc_html( $procedure_name ) . '</strong>';
			if ( ! empty( $case_id ) ) {
				$html .= ' <span class="case-id">#' . esc_html( $case_id ) . '</span>';
			}
		}
		$html .= '</h2>';
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
			$first_photo    = reset( $case_data['photoSets'] );
			$main_image_url = ! empty( $first_photo['postProcessedImageLocation'] ) ? $first_photo['postProcessedImageLocation'] : '';

			// Get seoAltText if available
			$seo_alt_text = null;
			if ( isset( $first_photo['seoAltText'] ) && $first_photo['seoAltText'] !== null ) {
				$seo_alt_text = $first_photo['seoAltText'];
			}

			// Use seoAltText if available, otherwise use procedure name and case number
			$main_alt_text = $seo_alt_text !== null ? $seo_alt_text : $procedure_name . ' - Case ' . $case_id;

			// Main image display - single processed image
			$html .= '<div class="brag-book-gallery-main-image-container" data-image-index="0">';

			if ( $main_image_url ) {
				$html .= '<div class="brag-book-gallery-main-single">';
				$html .= '<img src="' . esc_url( $main_image_url ) . '" alt="' . esc_attr( $main_alt_text ) . '" loading="eager">';

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
		$html .= '<div class="brag-book-gallery-case-card-details-section">';
		$html .= '<div class="brag-book-gallery-case-card-details-grid">';

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

		$html .= '</div>'; // End brag-book-gallery-case-card-details-grid
		$html .= '</div>'; // End case-details-section
		$html .= '</div>'; // End brag-book-gallery-case-content
		$html .= '</div>'; // End main container

		// Return both HTML and SEO data
		return [
			'html' => $html,
			'seo' => [
				'title' => ! empty( $seo_page_title ) ? $seo_page_title : $procedure_name . ' - Case #' . $case_id,
				'description' => ! empty( $seo_page_description ) ? $seo_page_description : 'View before and after photos for ' . $procedure_name . ' case #' . $case_id,
			],
		];
	}

	/**
	 * Render favorites view with case cards.
	 *
	 * @since 3.0.0
	 * @param array $cases Array of case data from the API.
	 * @return string HTML output.
	 */
	public static function render_favorites_view( array $cases ): string {
		// Build the HTML using the same structure as ajax_load_filtered_gallery
		$html = '<div class="brag-book-filtered-results">';

		// Add SVG logo for favorites
		$html .= '<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
			<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"></path>
			<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"></path>
			<path fill="#121827" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"></path>
			<path fill="#121827" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"></path>
			<path fill="#121827" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"></path>
			<path fill="#121827" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"></path>
			<path fill="#121827" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"></path>
			<path fill="#121827" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"></path>
			<path fill="#121827" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"></path>
			<path fill="#121827" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"></path>
			<path fill="#121827" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"></path>
			<path fill="#121827" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"></path>
			<path fill="#121827" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"></path>
			<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"></path>
		</svg>';

		// Add user email info if available
		$html .= '<p class="brag-book-gallery-favorites-user"></p>';

		// Add controls container for grid selector
		$html .= '<div class="brag-book-gallery-controls">';

		// Left side container (empty for favorites, but maintains layout consistency)
		$html .= '<div class="brag-book-gallery-controls-left">';

		// Add count display
		$count = count( $cases );
		$html .= '<div class="brag-book-gallery-active-filters">';
		$html .= '<span class="brag-book-gallery-favorite-count">' . sprintf( _n( '%d favorite', '%d favorites', $count, 'brag-book-gallery' ), $count ) . '</span>';
		$html .= '</div>';

		$html .= '</div>'; // Close left controls

		// Get default columns from settings
		$default_columns = get_option( 'brag_book_gallery_columns', '3' );
		$column2_active = ( $default_columns === '2' ) ? ' active' : '';
		$column3_active = ( $default_columns === '3' ) ? ' active' : '';

		// Grid layout selector (on the right)
		$html .= sprintf(
			'<div class="brag-book-gallery-grid-selector">
				<span class="brag-book-gallery-grid-label">%s</span>
				<div class="brag-book-gallery-grid-buttons">
					<button class="brag-book-gallery-grid-btn%s" data-columns="2" onclick="updateGridLayout(2)" aria-label="%s">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="6" height="6"/><rect x="9" y="1" width="6" height="6"/><rect x="1" y="9" width="6" height="6"/><rect x="9" y="9" width="6" height="6"/></svg>
						<span class="sr-only">%s</span>
					</button>
					<button class="brag-book-gallery-grid-btn%s" data-columns="3" onclick="updateGridLayout(3)" aria-label="%s">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="4" height="4"/><rect x="6" y="1" width="4" height="4"/><rect x="11" y="1" width="4" height="4"/><rect x="1" y="6" width="4" height="4"/><rect x="6" y="6" width="4" height="4"/><rect x="11" y="6" width="4" height="4"/><rect x="1" y="11" width="4" height="4"/><rect x="6" y="11" width="4" height="4"/><rect x="11" y="11" width="4" height="4"/></svg>
						<span class="sr-only">%s</span>
					</button>
				</div>
			</div>',
			esc_html__( 'View:', 'brag-book-gallery' ),
			esc_attr( $column2_active ),
			esc_attr__( 'View in 2 columns', 'brag-book-gallery' ),
			esc_html__( '2 Columns', 'brag-book-gallery' ),
			esc_attr( $column3_active ),
			esc_attr__( 'View in 3 columns', 'brag-book-gallery' ),
			esc_html__( '3 Columns', 'brag-book-gallery' )
		);

		$html .= '</div>'; // Close controls container

		// Start cases grid with masonry layout for variable heights
		$html .= sprintf( '<div class="brag-book-gallery-case-grid masonry-layout" data-columns="%s">', esc_attr( $default_columns ) );

		if ( empty( $cases ) ) {
			// Show message when no favorites found
			$html .= '<div class="brag-book-gallery-no-results">';
			$html .= '<p>' . esc_html__( 'No favorites found. Start adding your favorite cases to see them here!', 'brag-book-gallery' ) . '</p>';
			$html .= '</div>';
		} else {
			// Get image display mode
			$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

			// Transform and render each case using the same method as filtered gallery
			foreach ( $cases as $case ) {
				// Transform case data to match what render_ajax_gallery_case_card expects
				$transformed_case = $case;

				// Extract main image from photoSets
				$transformed_case['mainImageUrl'] = '';
				if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
					$first_photoset = reset( $case['photoSets'] );
					$transformed_case['mainImageUrl'] = $first_photoset['postProcessedImageLocation'] ??
														 $first_photoset['beforeLocationUrl'] ??
														 $first_photoset['afterLocationUrl1'] ?? '';
				}

				// Extract procedure title
				$transformed_case['procedureTitle'] = __( 'Unknown Procedure', 'brag-book-gallery' );

				// Check if we have procedures array with names
				if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
					$first_procedure = reset( $case['procedures'] );
					$transformed_case['procedureTitle'] = $first_procedure['name'] ?? __( 'Unknown Procedure', 'brag-book-gallery' );
				}
				// Check if we have procedureIds array and look up the names
				elseif ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
					// Get API token for sidebar data lookup
					$api_token = get_option( 'brag_book_gallery_api_token' );
					if ( is_array( $api_token ) && ! empty( $api_token[0] ) ) {
						$api_token = $api_token[0];
					}

					// Get sidebar data to look up procedure names
					$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );

					if ( ! empty( $sidebar_data['data'] ) && ! empty( $case['procedureIds'][0] ) ) {
						$procedure_info = Data_Fetcher::find_procedure_by_id( $sidebar_data['data'], intval( $case['procedureIds'][0] ) );
						if ( $procedure_info && ! empty( $procedure_info['name'] ) ) {
							$transformed_case['procedureTitle'] = $procedure_info['name'];

							// Also add the procedures array for consistency
							$procedures = [];
							foreach ( $case['procedureIds'] as $procedure_id ) {
								$proc_info = Data_Fetcher::find_procedure_by_id( $sidebar_data['data'], intval( $procedure_id ) );
								if ( $proc_info && ! empty( $proc_info['name'] ) ) {
									$procedures[] = ['name' => $proc_info['name'], 'id' => $procedure_id];
								}
							}
							if ( ! empty( $procedures ) ) {
								$transformed_case['procedures'] = $procedures;
							}
						}
					}
				}

				// Use reflection to access the private render_ajax_gallery_case_card method from Shortcodes class
				try {
					$shortcodes_class = 'BRAGBookGallery\Includes\Extend\Shortcodes';
					$method = new \ReflectionMethod( $shortcodes_class, 'render_ajax_gallery_case_card' );
					$method->setAccessible( true );

					// Render the case card without procedure context to allow proper procedure name lookup
					$html .= $method->invoke( null, $transformed_case, $image_display_mode, false, '' );
				} catch ( \Exception $render_error ) {
					// Fallback to simple rendering if reflection fails
					error_log( 'Error rendering favorite case card: ' . $render_error->getMessage() );

					// Simple fallback rendering
					$case_id = $case['id'] ?? $case['caseId'] ?? '';
					if ( ! empty( $case_id ) ) {
						$html .= '<article class="brag-book-gallery-case-card" data-case-id="' . esc_attr( $case_id ) . '">';
						$html .= '<div class="brag-book-gallery-case-images">';

						if ( ! empty( $transformed_case['mainImageUrl'] ) ) {
							$html .= '<img src="' . esc_url( $transformed_case['mainImageUrl'] ) . '" alt="Case" loading="lazy">';
						}

						$html .= '</div>';
						$html .= '<div class="brag-book-gallery-case-info">';
						$html .= '<h3>' . esc_html( $transformed_case['procedureTitle'] ) . '</h3>';
						$html .= '</div>';
						$html .= '</article>';
					}
				}
			}
		}

		$html .= '</div>'; // Close cases grid
		$html .= '</div>'; // Close filtered-results

		return $html;
	}
}
