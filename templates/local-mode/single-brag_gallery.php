<?php
/**
 * Single Gallery Template (Local Mode)
 *
 * Template for displaying individual gallery cases in Local mode.
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

<div id="primary" class="content-area brag-gallery-single">
	<main id="main" class="site-main">
		
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'brag-gallery-case' ); ?>>
				
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
					
					<?php
					// Display categories
					$categories = get_the_terms( get_the_ID(), 'brag_category' );
					if ( $categories && ! is_wp_error( $categories ) ) :
					?>
						<div class="brag-gallery-categories">
							<?php foreach ( $categories as $category ) : ?>
								<a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="brag-category-link">
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
							<strong><?php esc_html_e( 'Procedures:', 'brag-book-gallery' ); ?></strong>
							<?php foreach ( $procedures as $procedure ) : ?>
								<a href="<?php echo esc_url( get_term_link( $procedure ) ); ?>" class="brag-procedure-link">
									<?php echo esc_html( $procedure->name ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</header>

				<div class="entry-content">
					
					<?php
					// Display before/after images
					$before_images = get_post_meta( get_the_ID(), '_brag_before_image_ids', true );
					$after_images = get_post_meta( get_the_ID(), '_brag_after_image_ids', true );
					
					if ( $before_images || $after_images ) :
					?>
						<div class="brag-gallery-images">
							
							<?php if ( $before_images && is_array( $before_images ) ) : ?>
								<div class="brag-before-images">
									<h3><?php esc_html_e( 'Before', 'brag-book-gallery' ); ?></h3>
									<div class="brag-image-grid">
										<?php foreach ( $before_images as $image_id ) : ?>
											<?php if ( wp_get_attachment_url( $image_id ) ) : ?>
												<div class="brag-image-item">
													<?php echo wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'brag-gallery-image' ) ); ?>
												</div>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
							
							<?php if ( $after_images && is_array( $after_images ) ) : ?>
								<div class="brag-after-images">
									<h3><?php esc_html_e( 'After', 'brag-book-gallery' ); ?></h3>
									<div class="brag-image-grid">
										<?php foreach ( $after_images as $image_id ) : ?>
											<?php if ( wp_get_attachment_url( $image_id ) ) : ?>
												<div class="brag-image-item">
													<?php echo wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'brag-gallery-image' ) ); ?>
												</div>
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
							
						</div>
					<?php endif; ?>
					
					<?php the_content(); ?>
					
					<?php
					// Display patient information if available
					$patient_info = get_post_meta( get_the_ID(), '_brag_patient_info', true );
					if ( $patient_info ) {
						$patient_data = json_decode( $patient_info, true );
						if ( $patient_data && is_array( $patient_data ) ) :
					?>
						<div class="brag-patient-info">
							<h3><?php esc_html_e( 'Patient Information', 'brag-book-gallery' ); ?></h3>
							<ul class="brag-patient-details">
								<?php if ( ! empty( $patient_data['age'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Age:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( $patient_data['age'] ); ?></li>
								<?php endif; ?>
								<?php if ( ! empty( $patient_data['gender'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Gender:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( ucfirst( $patient_data['gender'] ) ); ?></li>
								<?php endif; ?>
								<?php if ( ! empty( $patient_data['height'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Height:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( $patient_data['height'] ); ?></li>
								<?php endif; ?>
								<?php if ( ! empty( $patient_data['weight'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Weight:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( $patient_data['weight'] ); ?></li>
								<?php endif; ?>
								<?php if ( ! empty( $patient_data['ethnicity'] ) ) : ?>
									<li><strong><?php esc_html_e( 'Ethnicity:', 'brag-book-gallery' ); ?></strong> <?php echo esc_html( $patient_data['ethnicity'] ); ?></li>
								<?php endif; ?>
							</ul>
						</div>
					<?php
						endif;
					}
					?>
					
				</div>

				<footer class="entry-footer">
					<?php
					// Display edit link for administrators
					if ( current_user_can( 'edit_posts' ) ) {
						edit_post_link(
							sprintf(
								/* translators: %s: Post title */
								__( 'Edit %s', 'brag-book-gallery' ),
								get_the_title()
							),
							'<span class="edit-link">',
							'</span>'
						);
					}
					?>
				</footer>

			</article>
			
		<?php endwhile; ?>

	</main>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>