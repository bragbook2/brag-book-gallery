<?php
/**
 * Single Case Template
 *
 * Template for displaying individual case posts from the Cases custom post type.
 * This template shows case details including before/after images, procedure information,
 * and case-specific content.
 *
 * @package BRAGBookGallery
 * @subpackage Templates
 * @since 3.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

get_header(); ?>

<main id="main" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>

<?php
get_sidebar();
get_footer();
