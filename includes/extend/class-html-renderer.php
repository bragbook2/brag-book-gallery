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
	private static function generate_filters_from_sidebar( array $sidebar_data ): string {
		if ( empty( $sidebar_data['data'] ) || ! is_array( $sidebar_data['data'] ) ) {
			return self::generate_default_filters();
		}

		$categories = $sidebar_data['data'];
		$all_procedures = [];

		// Collect all procedures from all categories
		foreach ( $categories as $category ) {
			if ( ! empty( $category['procedures'] ) && is_array( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					if ( ! empty( $procedure['slug'] ) && ! empty( $procedure['title'] ) ) {
						$all_procedures[] = $procedure;
					}
				}
			}
		}

		// Sort procedures alphabetically by title
		usort( $all_procedures, fn( $a, $b ) => strcasecmp( $a['title'], $b['title'] ) );

		ob_start();
		?>
		<div class="brag-book-gallery-nav" role="navigation" aria-label="<?php esc_attr_e( 'Gallery Filters', 'brag-book-gallery' ); ?>">
			<div class="brag-book-gallery-nav-button">
				<h3 class="brag-book-gallery-filter-title">
					<?php esc_html_e( 'Filter by Procedure', 'brag-book-gallery' ); ?>
				</h3>
				<button
					type="button"
					class="brag-book-gallery-nav-button__toggle"
					aria-expanded="false"
					aria-controls="filter-content"
					aria-label="<?php esc_attr_e( 'Toggle filters', 'brag-book-gallery' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor" class="toggle-icon" aria-hidden="true">
						<path d="M480-357.85 253.85-584 296-626.15l184 184 184-184L706.15-584 480-357.85Z"/>
					</svg>
				</button>
			</div>
			<div id="filter-content" class="brag-book-gallery-nav-list-submenu" aria-hidden="true">
				<div class="brag-book-gallery-nav-list__item all-procedures">
					<ul class="brag-book-gallery-filter-list" role="list">
						<li class="brag-book-gallery-nav-list-submenu__item" role="listitem">
							<a href="#"
							   class="brag-book-gallery-nav-link active"
							   data-filter="all"
							   role="button"
							   aria-pressed="true"
							   aria-label="<?php esc_attr_e( 'Show all procedures', 'brag-book-gallery' ); ?>">
								<?php esc_html_e( 'All Procedures', 'brag-book-gallery' ); ?>
								<span class="brag-book-gallery-filter-count" aria-label="<?php esc_attr_e( 'Total cases', 'brag-book-gallery' ); ?>">
									(0)
								</span>
							</a>
						</li>
						<?php foreach ( $all_procedures as $procedure ) : ?>
							<li class="brag-book-gallery-nav-list-submenu__item" role="listitem">
								<a href="#"
								   class="brag-book-gallery-nav-link"
								   data-filter="<?php echo esc_attr( $procedure['slug'] ); ?>"
								   data-procedure-id="<?php echo esc_attr( $procedure['id'] ?? '' ); ?>"
								   role="button"
								   aria-pressed="false"
								   aria-label="<?php printf( esc_attr__( 'Filter by %s', 'brag-book-gallery' ), esc_attr( $procedure['title'] ) ); ?>">
									<?php echo esc_html( $procedure['title'] ); ?>
									<span class="brag-book-gallery-filter-count"
										  data-procedure="<?php echo esc_attr( $procedure['slug'] ); ?>"
										  aria-label="<?php printf( esc_attr__( 'Cases for %s', 'brag-book-gallery' ), esc_attr( $procedure['title'] ) ); ?>">
										(0)
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate default filters when no sidebar data is available.
	 *
	 * @since 3.0.0
	 * @return string Default filters HTML.
	 */
	private static function generate_default_filters(): string {
		ob_start();
		?>
		<div class="brag-book-gallery-nav" role="navigation" aria-label="<?php esc_attr_e( 'Gallery Filters', 'brag-book-gallery' ); ?>">
			<div class="brag-book-gallery-nav-button">
				<h3 class="brag-book-gallery-filter-title">
					<?php esc_html_e( 'Filter by Procedure', 'brag-book-gallery' ); ?>
				</h3>
				<button
					type="button"
					class="brag-book-gallery-nav-button__toggle"
					aria-expanded="false"
					aria-controls="filter-content"
					aria-label="<?php esc_attr_e( 'Toggle filters', 'brag-book-gallery' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor" class="toggle-icon" aria-hidden="true">
						<path d="M480-357.85 253.85-584 296-626.15l184 184 184-184L706.15-584 480-357.85Z"/>
					</svg>
				</button>
			</div>
			<div id="filter-content" class="brag-book-gallery-nav-list-submenu" aria-hidden="true">
				<div class="brag-book-gallery-nav-list__item">
					<ul class="brag-book-gallery-filter-list" role="list">
						<li class="brag-book-gallery-nav-list-submenu__item" role="listitem">
							<a href="#"
							   class="brag-book-gallery-nav-link active"
							   data-filter="all"
							   role="button"
							   aria-pressed="true"
							   aria-label="<?php esc_attr_e( 'Show all procedures', 'brag-book-gallery' ); ?>">
								<?php esc_html_e( 'All Procedures', 'brag-book-gallery' ); ?>
								<span class="brag-book-gallery-filter-count" aria-label="<?php esc_attr_e( 'Total cases', 'brag-book-gallery' ); ?>">
									(0)
								</span>
							</a>
						</li>
					</ul>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
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
	private static function generate_single_carousel_item(
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
			<a href="<?php echo esc_url( "{$base_path}/{$procedure_slug}/{$case_id}/" ); ?>"
			   class="brag-book-gallery-case-link"
			   aria-label="<?php printf( esc_attr__( 'View case %s for %s', 'brag-book-gallery' ), esc_attr( $case_id ), esc_attr( $procedure_title ) ); ?>">
				<div class="brag-book-gallery-image-container">
					<?php if ( $has_nudity ) : ?>
						<?php echo self::generate_nudity_warning(); ?>
					<?php endif; ?>
					<?php echo self::generate_carousel_image( $image_url, $has_nudity ); ?>
				</div>
			</a>
			<?php echo self::generate_item_actions( $case_id ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate nudity warning HTML.
	 *
	 * @since 3.0.0
	 * @return string Nudity warning HTML.
	 */
	private static function generate_nudity_warning(): string {
		return sprintf(
			'<div class="brag-book-gallery-nudity-warning" aria-label="%s">
				<p>%s</p>
			</div>',
			esc_attr__( 'Content warning', 'brag-book-gallery' ),
			esc_html__( 'This content contains nudity', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate carousel image HTML.
	 *
	 * @since 3.0.0
	 * @param string $image_url Image URL.
	 * @param bool   $has_nudity Whether image has nudity.
	 * @return string Image HTML.
	 */
	private static function generate_carousel_image( string $image_url, bool $has_nudity ): string {
		$blur_class = $has_nudity ? ' brag-book-gallery-nudity-blur' : '';
		return sprintf(
			'<picture class="brag-book-gallery-image%s">
				<img src="%s" alt="%s" loading="lazy" />
			</picture>',
			esc_attr( $blur_class ),
			esc_url( $image_url ),
			esc_attr__( 'Gallery image', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate item action buttons HTML.
	 *
	 * @since 3.0.0
	 * @param string $item_id Item ID.
	 * @return string Actions HTML.
	 */
	private static function generate_item_actions( string $item_id ): string {
		ob_start();
		?>
		<div class="brag-book-gallery-item-actions">
			<button type="button"
					class="brag-book-gallery-heart-btn"
					data-case-id="<?php echo esc_attr( $item_id ); ?>"
					aria-label="<?php esc_attr_e( 'Add to favorites', 'brag-book-gallery' ); ?>"
					title="<?php esc_attr_e( 'Add to favorites', 'brag-book-gallery' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
				</svg>
			</button>
			<button type="button"
					class="brag-book-gallery-share-btn"
					data-case-id="<?php echo esc_attr( $item_id ); ?>"
					aria-label="<?php esc_attr_e( 'Share this case', 'brag-book-gallery' ); ?>"
					title="<?php esc_attr_e( 'Share', 'brag-book-gallery' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
					<polyline points="16 6 12 2 8 6"/>
					<line x1="12" y1="2" x2="12" y2="15"/>
				</svg>
			</button>
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
	private static function generate_carousel_slide_from_photo( array $photo, array $case, int $slide_index ): string {
		$photo_id = $photo['id'] ?? '';
		$image_url = $photo['url'] ?? '';
		$case_id = $case['id'] ?? '';
		$procedure_title = $case['procedureTitle'] ?? '';
		$patient_age = $case['patientAge'] ?? '';
		$patient_gender = $case['patientGender'] ?? '';
		$patient_height = $case['patientHeight'] ?? '';
		$patient_weight = $case['patientWeight'] ?? '';
		$patient_ethnicity = $case['patientEthnicity'] ?? '';
		$description = $case['description'] ?? '';

		// Check for nudity based on photo ID (example logic, adapt as needed)
		$has_nudity = ! empty( $photo['hasNudity'] );

		// Build alt text
		$alt_text = sprintf(
			__( 'Case %s: %s', 'brag-book-gallery' ),
			esc_attr( $case_id ),
			esc_attr( $procedure_title )
		);

		// Add patient details to alt text if available
		if ( $patient_age || $patient_gender ) {
			$details = [];
			if ( $patient_age ) {
				$details[] = sprintf( __( '%s years old', 'brag-book-gallery' ), $patient_age );
			}
			if ( $patient_gender ) {
				$details[] = $patient_gender;
			}
			$alt_text .= ' - ' . implode( ', ', $details );
		}

		ob_start();
		?>
		<div class="brag-book-carousel-item"
			 data-index="<?php echo esc_attr( (string) $slide_index ); ?>"
			 data-case-id="<?php echo esc_attr( $case_id ); ?>"
			 data-photo-id="<?php echo esc_attr( $photo_id ); ?>">
			<div class="brag-book-carousel-image-container">
				<?php if ( $has_nudity ) : ?>
					<?php echo self::generate_nudity_warning(); ?>
				<?php endif; ?>
				<?php echo self::generate_carousel_image_with_alt( $image_url, $has_nudity, $alt_text ); ?>
			</div>
			<div class="brag-book-carousel-details">
				<h3 class="brag-book-carousel-title"><?php echo esc_html( $procedure_title ); ?></h3>
				<?php if ( $description ) : ?>
					<p class="brag-book-carousel-description">
						<?php echo esc_html( self::limit_words( $description, 30 ) ); ?>
					</p>
				<?php endif; ?>
				<div class="brag-book-carousel-demographics">
					<?php if ( $patient_age ) : ?>
						<span class="demographic-item">
							<strong><?php esc_html_e( 'Age:', 'brag-book-gallery' ); ?></strong>
							<?php echo esc_html( $patient_age ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $patient_gender ) : ?>
						<span class="demographic-item">
							<strong><?php esc_html_e( 'Gender:', 'brag-book-gallery' ); ?></strong>
							<?php echo esc_html( $patient_gender ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $patient_height ) : ?>
						<span class="demographic-item">
							<strong><?php esc_html_e( 'Height:', 'brag-book-gallery' ); ?></strong>
							<?php echo esc_html( $patient_height ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $patient_weight ) : ?>
						<span class="demographic-item">
							<strong><?php esc_html_e( 'Weight:', 'brag-book-gallery' ); ?></strong>
							<?php echo esc_html( $patient_weight ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $patient_ethnicity ) : ?>
						<span class="demographic-item">
							<strong><?php esc_html_e( 'Ethnicity:', 'brag-book-gallery' ); ?></strong>
							<?php echo esc_html( $patient_ethnicity ); ?>
						</span>
					<?php endif; ?>
				</div>
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
}
