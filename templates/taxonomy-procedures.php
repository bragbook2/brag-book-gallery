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

get_header(); ?>

<div class="brag-book-taxonomy-procedures-wrapper">
	<div class="container">
		<main id="main" class="site-main" role="main">

			<?php if ( have_posts() ) : ?>

				<?php
				/**
				 * Display gallery content - automatically detects context
				 * On taxonomy pages: Shows full gallery with sidebar, filtered to current procedure
				 * On single case pages: Shows single case view
				 * On regular pages: Shows full gallery with sidebar
				 */
				echo do_shortcode( '[brag_book_gallery]' );
				?>

			<?php else : ?>

				<section class="no-results not-found">
					<header class="page-header">
						<h1 class="page-title"><?php esc_html_e( 'Nothing Found', 'brag-book-gallery' ); ?></h1>
					</header><!-- .page-header -->

					<div class="page-content">
						<p><?php esc_html_e( 'No cases were found for this procedure.', 'brag-book-gallery' ); ?></p>
					</div><!-- .page-content -->
				</section><!-- .no-results -->

			<?php endif; ?>

		</main><!-- #main -->
	</div><!-- .container -->
</div><!-- .brag-book-taxonomy-procedures-wrapper -->

<?php get_footer(); ?>
