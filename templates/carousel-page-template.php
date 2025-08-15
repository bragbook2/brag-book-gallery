<?php
/**
 * Template Name: Carousel Page Template
 *
 * Displays the main gallery carousel page with landing content.
 * Handles shortcode parsing and content rendering with modern PHP 8.2 features.
 *
 * @package BRAGBook
 * @since   3.0.0
 */

declare(strict_types=1);

use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

get_header();

/**
 * Get page configuration
 */
$landing_page_text = get_option( 'brag_book_gallery_landing_page_text', '' );
$parts = explode( '/', trim( $_SERVER['REQUEST_URI'] ?? '', '/' ) );
$base_slug = sanitize_text_field( $parts[0] ?? '' );

/**
 * Parse carousel configuration from landing page text
 */
$carousel_category = '';
$content_parts = [];

if ( ! empty( $landing_page_text ) ) {
	// Split content by shortcode delimiter
	$content_parts = explode( '[', $landing_page_text );

	// Extract category from shortcode if present
	if ( isset( $content_parts[1] ) ) {
		preg_match( '/category="([^"]*)"/', $content_parts[1], $matches );
		$carousel_category = $matches[1] ?? '';
	}
}

/**
 * Process procedure title if category exists
 */
$procedure_title = '';
if ( ! empty( $carousel_category ) ) {
	$procedure_slug = sanitize_title( $carousel_category );
	$procedure_title = get_option( $procedure_slug, '' );
}

?>
<div class="brag-book-gallery-container-main">
	<main class="brag-book-gallery-main">
		<?php
		// Include sidebar template
		include plugin_dir_path( __FILE__ ) . 'sidebar-template.php';
		?>

		<div class="brag-book-gallery-content-area">
			<div class="brag-book-gallery-filter-attic brag-book-gallery-filter-attic-borderless">
				<button type="button" class="brag-book-gallery-sidebar-toggle" aria-label="Toggle sidebar">
					<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/menu-icon.svg' ) ); ?>"
					     style="padding:3px;"
					     alt="toggle sidebar">
				</button>

				<div class="brag-book-gallery-search-container-outer">
					<form class="search-container mobile-search-container">
						<input type="text"
						       id="mobile-search-bar"
						       placeholder="<?php esc_attr_e( 'Search Procedures', 'brag-book-gallery' ); ?>">
						<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/search-svgrepo-com.svg' ) ); ?>"
						     class="brag-book-gallery-search-icon"
						     alt="search">
						<ul id="mobile-search-suggestions" class="search-suggestions"></ul>
					</form>
				</div>
			</div>

			<?php
			/**
			 * Render content before shortcode
			 */
			if ( ! empty( $content_parts[0] ) ) {
				echo render_content_with_paragraphs( $content_parts[0] );
			}

			/**
			 * Process and render shortcodes
			 */
			if ( count( $content_parts ) > 1 ) {
				// Reconstruct shortcodes and render them
				for ( $i = 1; $i < count( $content_parts ); $i++ ) {
					$shortcode_content = '[' . $content_parts[$i];

					// Find the end of the shortcode
					$closing_bracket_pos = strpos( $shortcode_content, ']' );

					if ( $closing_bracket_pos !== false ) {
						// Extract shortcode and any content after it
						$shortcode = substr( $shortcode_content, 0, $closing_bracket_pos + 1 );
						$after_content = substr( $shortcode_content, $closing_bracket_pos + 1 );

						// Render the shortcode
						echo do_shortcode( $shortcode );

						// Render any content after the shortcode
						if ( ! empty( trim( $after_content ) ) ) {
							echo render_content_with_paragraphs( $after_content );
						}
					}
				}
			}
			?>

			<a href="<?php echo esc_url( "/{$base_slug}/consultation/" ); ?>"
			   class="brag-book-gallery-sidebar-btn mobile-footer">
				<?php esc_html_e( 'REQUEST A CONSULTATION', 'brag-book-gallery' ); ?>
			</a>

			<div class="brag-book-gallery-bottom-bar">
				<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/myfavs-logo.png' ) ); ?>"
				     alt="<?php esc_attr_e( 'MyFavorites', 'brag-book-gallery' ); ?>">
				<p>
					<span><?php esc_html_e( 'Use the MyFavorites tool', 'brag-book-gallery' ); ?></span>
					<?php esc_html_e( ' to help communicate your specific goals. If a result speaks to you, tap the heart.', 'brag-book-gallery' ); ?>
				</p>
			</div>
		</div>
	</main>
</div>

<?php
/**
 * Helper function to render content with automatic paragraph wrapping
 *
 * @param string $content The content to process
 * @return string Processed content with paragraphs
 */
function render_content_with_paragraphs( string $content ): string {
	// Split content into lines
	$lines = preg_split( "/\r\n|\n|\r/", $content );
	$output = '';

	foreach ( $lines as $line ) {
		$trimmed_line = trim( $line );

		// Skip empty lines
		if ( empty( $trimmed_line ) ) {
			continue;
		}

		// If line contains HTML tags, output as-is; otherwise wrap in paragraph
		if ( $trimmed_line === strip_tags( $trimmed_line ) ) {
			$output .= '<p>' . esc_html( $trimmed_line ) . '</p>';
		} else {
			// Allow safe HTML through wp_kses_post
			$output .= wp_kses_post( $trimmed_line );
		}
	}

	return $output;
}

get_footer();
