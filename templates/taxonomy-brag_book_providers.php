<?php
/**
 * Template for Providers Taxonomy Archive
 *
 * Displays the term archive for a single provider: the provider name, the term
 * description (shortcodes allowed) and the provider's cases grid. The grid
 * shortcode reads the queried provider, so no attributes are needed here.
 *
 * @package BRAGBookGallery
 * @subpackage Templates
 * @since 4.9.3
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term = get_queried_object();

if ( $term instanceof WP_Term ) {
	?>
	<main id="main" class="site-main brag-book-gallery-provider-view">
		<header class="page-header">
			<h1 class="page-title"><?php echo esc_html( $term->name ); ?></h1>
			<?php if ( ! empty( $term->description ) ) : ?>
				<div class="taxonomy-description">
					<?php echo do_shortcode( wp_kses_post( wpautop( $term->description ) ) ); ?>
				</div>
			<?php endif; ?>
		</header>
		<?php
		// The gallery shortcode auto-detects the provider archive and renders the
		// same structure as a procedure page, scoped to this provider.
		echo do_shortcode( '[brag_book_gallery]' );
		?>
	</main>
	<?php
} else {
	?>
	<section class="no-results not-found">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Nothing Found', 'brag-book-gallery' ); ?></h1>
		</header>
		<div class="page-content">
			<p><?php esc_html_e( 'No cases were found for this provider.', 'brag-book-gallery' ); ?></p>
		</div>
	</section>
	<?php
}

get_footer();
