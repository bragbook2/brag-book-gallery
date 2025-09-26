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

if ( have_posts() ) {
	the_archive_description();
} else {
	?>
	<section class="no-results not-found">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Nothing Found', 'brag-book-gallery' ); ?></h1>
		</header><!-- .page-header -->
		<div class="page-content">
			<p><?php esc_html_e( 'No cases were found for this procedure.', 'brag-book-gallery' ); ?></p>
		</div>
	</section>
	<?php
}

get_footer();
