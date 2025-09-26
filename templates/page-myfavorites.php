<?php
/**
 * My Favorites Page Template
 *
 * Template for displaying the My Favorites page, which shows user's saved
 * before/after cases. This template provides the structure for the favorites
 * functionality and email capture form.
 *
 * @package BRAGBookGallery
 * @subpackage Templates
 * @since 3.0.0
 * @author Candace Crowe Design <bragbook@candacecrowe.com>
 */

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

get_header(); ?>

	<main id="main" class="site-main">
		<?php
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		?>
	</main>

<?php
get_footer();
