<?php
/**
 * Template for Procedures Taxonomy Archive
 *
 * This template displays the taxonomy archive page for a specific procedure.
 * It shows all cases associated with the current procedure term.
 *
 * @package BRAGBookGallery
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Get current term
$term = get_queried_object();

if ( $term && ! is_wp_error( $term ) ) {
	?>
	<div class="brag-book-procedures-archive">
		<header class="page-header">
			<h2 class="page-title"><?php echo esc_html( $term->name ); ?></h2>
			<?php if ( ! empty( $term->description ) ) : ?>
				<div class="taxonomy-description">
					<?php
					// Process shortcodes in the description
					echo do_shortcode( wp_kses_post( wpautop( $term->description ) ) );
					?>
				</div>
			<?php endif; ?>
		</header>
	</div>
	<?php
} else {
	?>
	<section class="no-results not-found">
		<header class="page-header">
			<h2 class="page-title"><?php esc_html_e( 'Nothing Found', 'brag-book-gallery' ); ?></h2>
		</header><!-- .page-header -->
		<div class="page-content">
			<p><?php esc_html_e( 'No cases were found for this procedure.', 'brag-book-gallery' ); ?></p>
		</div>
	</section>
	<?php
}

get_footer();
