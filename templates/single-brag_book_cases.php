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
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header>

			<div class="entry-content">
				<?php
				// Display featured image (before image)
				if ( has_post_thumbnail() ) {
					echo '<div class="case-before-image">';
					echo '<h2>' . esc_html__( 'Before', 'brag-book-gallery' ) . '</h2>';
					the_post_thumbnail( 'large', array( 'class' => 'before-image' ) );
					echo '</div>';
				}

				// Display content
				the_content();

				// Display case details via shortcode if case ID is available
				$case_api_id = get_post_meta( get_the_ID(), '_brag_book_api_case_id', true );
				if ( $case_api_id ) {
					echo '<div class="case-gallery-display">';
					echo do_shortcode( '[brag_book_gallery_case case_id="' . esc_attr( $case_api_id ) . '"]' );
					echo '</div>';
				}

				// Display custom case details
				$case_details = get_post_meta( get_the_ID(), '_brag_book_case_details', true );
				if ( $case_details ) {
					echo '<div class="case-additional-details">';
					echo '<h2>' . esc_html__( 'Case Details', 'brag-book-gallery' ) . '</h2>';
					echo wpautop( wp_kses_post( $case_details ) );
					echo '</div>';
				}

				// Display procedures taxonomy
				$procedures = get_the_terms( get_the_ID(), 'procedures' );
				if ( $procedures && ! is_wp_error( $procedures ) ) {
					echo '<div class="case-procedures">';
					echo '<h3>' . esc_html__( 'Procedures', 'brag-book-gallery' ) . '</h3>';
					echo '<ul class="procedures-list">';
					foreach ( $procedures as $procedure ) {
						echo '<li><a href="' . esc_url( get_term_link( $procedure ) ) . '">' . esc_html( $procedure->name ) . '</a></li>';
					}
					echo '</ul>';
					echo '</div>';
				}
				?>
			</div>

			<footer class="entry-footer">
				<?php
				// Navigation to next/previous cases
				$next_post = get_next_post();
				$prev_post = get_previous_post();

				if ( $next_post || $prev_post ) {
					echo '<nav class="case-navigation">';
					echo '<h2 class="screen-reader-text">' . esc_html__( 'Case navigation', 'brag-book-gallery' ) . '</h2>';
					echo '<div class="nav-links">';

					if ( $prev_post ) {
						echo '<div class="nav-previous">';
						echo '<a href="' . esc_url( get_permalink( $prev_post->ID ) ) . '" rel="prev">';
						echo '<span class="nav-subtitle">' . esc_html__( 'Previous Case', 'brag-book-gallery' ) . '</span>';
						echo '<span class="nav-title">' . esc_html( get_the_title( $prev_post->ID ) ) . '</span>';
						echo '</a>';
						echo '</div>';
					}

					if ( $next_post ) {
						echo '<div class="nav-next">';
						echo '<a href="' . esc_url( get_permalink( $next_post->ID ) ) . '" rel="next">';
						echo '<span class="nav-subtitle">' . esc_html__( 'Next Case', 'brag-book-gallery' ) . '</span>';
						echo '<span class="nav-title">' . esc_html( get_the_title( $next_post->ID ) ) . '</span>';
						echo '</a>';
						echo '</div>';
					}

					echo '</div>';
					echo '</nav>';
				}
				?>
			</footer>
		</article>
		<?php
	endwhile;
	?>
</main>

<?php
get_sidebar();
get_footer();