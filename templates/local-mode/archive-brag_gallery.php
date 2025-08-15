<?php
/**
 * Gallery Archive Template (Local Mode)
 *
 * Template for displaying gallery archive in Local mode.
 *
 * @package    BRAGBookGallery
 * @subpackage Templates\LocalMode
 * @since      3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div id="primary" class="content-area brag-gallery-archive">
	<main id="main" class="site-main">
		
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Gallery', 'brag-book-gallery' ); ?></h1>
			<?php
			$description = get_the_archive_description();
			if ( $description ) :
			?>
				<div class="archive-description"><?php echo wp_kses_post( $description ); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>
			
			<div class="brag-gallery-grid">
				
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'brag-gallery-item' ); ?>>
						
						<div class="brag-gallery-thumbnail">
							<?php if ( has_post_thumbnail() ) : ?>
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'medium', array( 'class' => 'brag-thumbnail-image' ) ); ?>
								</a>
							<?php else : ?>
								<?php
								// Fallback to first before/after image
								$before_images = get_post_meta( get_the_ID(), '_brag_before_image_ids', true );
								$after_images = get_post_meta( get_the_ID(), '_brag_after_image_ids', true );
								$fallback_image = null;
								
								if ( $before_images && is_array( $before_images ) && ! empty( $before_images[0] ) ) {
									$fallback_image = $before_images[0];
								} elseif ( $after_images && is_array( $after_images ) && ! empty( $after_images[0] ) ) {
									$fallback_image = $after_images[0];
								}
								
								if ( $fallback_image ) :
								?>
									<a href="<?php the_permalink(); ?>">
										<?php echo wp_get_attachment_image( $fallback_image, 'medium', false, array( 'class' => 'brag-thumbnail-image' ) ); ?>
									</a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
						
						<div class="brag-gallery-content">
							
							<h2 class="brag-gallery-title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							
							<?php
							// Display categories
							$categories = get_the_terms( get_the_ID(), 'brag_category' );
							if ( $categories && ! is_wp_error( $categories ) ) :
							?>
								<div class="brag-gallery-categories">
									<?php foreach ( $categories as $category ) : ?>
										<a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="brag-category-tag">
											<?php echo esc_html( $category->name ); ?>
										</a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							
							<?php
							// Display procedures
							$procedures = get_the_terms( get_the_ID(), 'brag_procedure' );
							if ( $procedures && ! is_wp_error( $procedures ) ) :
							?>
								<div class="brag-gallery-procedures">
									<?php foreach ( $procedures as $procedure ) : ?>
										<span class="brag-procedure-tag"><?php echo esc_html( $procedure->name ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							
							<?php if ( has_excerpt() ) : ?>
								<div class="brag-gallery-excerpt">
									<?php the_excerpt(); ?>
								</div>
							<?php endif; ?>
							
							<div class="brag-gallery-meta">
								<time class="published" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
									<?php echo esc_html( get_the_date() ); ?>
								</time>
							</div>
							
						</div>
						
					</article>
					
				<?php endwhile; ?>
				
			</div>
			
			<?php
			// Pagination
			the_posts_pagination( array(
				'mid_size'  => 2,
				'prev_text' => __( 'Previous', 'brag-book-gallery' ),
				'next_text' => __( 'Next', 'brag-book-gallery' ),
			) );
			?>
			
		<?php else : ?>
			
			<section class="no-results not-found">
				<header class="page-header">
					<h2 class="page-title"><?php esc_html_e( 'Nothing here', 'brag-book-gallery' ); ?></h2>
				</header>
				<div class="page-content">
					<p><?php esc_html_e( 'No gallery items found.', 'brag-book-gallery' ); ?></p>
				</div>
			</section>
			
		<?php endif; ?>

	</main>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>