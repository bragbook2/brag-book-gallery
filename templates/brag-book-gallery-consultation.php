<?php
/**
 * Template Name: Consultation Page Template
 *
 * Renders the consultation request form for the BRAG Book gallery plugin.
 * Allows visitors to submit consultation requests with contact information.
 *
 * @package BRAGBook
 * @since   1.0.0
 */

declare( strict_types=1 );

use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access.
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the sidebar image URL.
 */
$caret_image_url = Setup::get_asset_url( asset_path: 'assets/images/caret-right-sm.svg' );
$logo_image_url  = Setup::get_asset_url( asset_path:  'assets/images/fav-logo.svg' );

?>

<div class="brag-book-gallery-container-main">
	<main class="brag-book-gallery-main">
		<?php
		/**
		 * Include the sidebar template.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'sidebar-template.php';
		?>

		<div class="brag-book-gallery-content-area">
			<div class="brag-book-gallery-filter-attic">
				<button type="button" class="brag-book-gallery-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle sidebar', 'brag-book-gallery' ); ?>">
					<img
						src="<?php echo esc_url( $caret_image_url ); ?>"
						alt="<?php esc_attr_e( 'Toggle sidebar', 'brag-book-gallery' ); ?>"
						width="16"
						height="16">
				</button>
				<h2>
					<span><?php esc_html_e( 'Consultation Request', 'brag-book-gallery' ); ?></span>
				</h2>
			</div>

			<form
				class="brag-book-gallery-form brag-book-gallery-consultation-form"
				id="brag-book-gallery-consultation-form"
				method="post"
				action=""
				novalidate>

				<?php
				/**
				 * Add nonce field for security.
				 */
				wp_nonce_field( 'bb_consultation_submit', 'bb_consultation_nonce' );
				?>

				<div class="brag-book-gallery-form-field">
					<input
						class="brag-book-gallery-is-required"
						name="name"
						type="text"
						placeholder="<?php esc_attr_e( 'Name*', 'brag-book-gallery' ); ?>"
						required
						aria-required="true"
						aria-describedby="name-error">
					<span class="brag-book-gallery-is-required-msg" id="name-error" role="alert">
						<?php esc_html_e( 'Name is required', 'brag-book-gallery' ); ?>
					</span>
				</div>

				<div class="brag-book-gallery-form-field">
					<input
						class="brag-book-gallery-is-required"
						name="email"
						type="email"
						placeholder="<?php esc_attr_e( 'Email*', 'brag-book-gallery' ); ?>"
						required
						aria-required="true"
						aria-describedby="email-error">
					<span class="brag-book-gallery-is-required-msg" id="email-error" role="alert">
						<?php esc_html_e( 'Email is required', 'brag-book-gallery' ); ?>
					</span>
				</div>

				<div class="brag-book-gallery-form-field">
					<input
						class="brag-book-gallery-is-required"
						name="phone"
						type="tel"
						placeholder="<?php esc_attr_e( 'Phone*', 'brag-book-gallery' ); ?>"
						required
						aria-required="true"
						aria-describedby="phone-error">
					<span class="brag-book-gallery-is-required-msg" id="phone-error" role="alert">
						<?php esc_html_e( 'Phone number is required', 'brag-book-gallery' ); ?>
					</span>
				</div>

				<div class="brag-book-gallery-form-field">
					<textarea
						rows="6"
						name="description"
						placeholder="<?php esc_attr_e( 'How can we help?', 'brag-book-gallery' ); ?>"
						aria-label="<?php esc_attr_e( 'Description of consultation request', 'brag-book-gallery' ); ?>"></textarea>
				</div>

				<button
					type="submit"
					id="brag-book-gallery-consultation-form-submit"
					name="submit"
					class="brag-book-gallery-button brag-book-gallery-button-primary">
					<?php esc_html_e( 'Submit', 'brag-book-gallery' ); ?>
				</button>
			</form>

			<div class="brag-book-gallery-is-required-success" role="status" aria-live="polite"></div>
		</div>

		<div class="brag-book-gallery-bottom-bar">
			<img
				src="<?php echo esc_url( $logo_image_url ); ?>"
				alt="<?php esc_attr_e( 'BRAG Book Logo', 'brag-book-gallery' ); ?>"
				width="40"
				height="40">
			<p>
				<span><?php esc_html_e( 'Use the MyFavorites tool', 'brag-book-gallery' ); ?></span>
				<?php esc_html_e( 'to help communicate your specific goals. If a result speaks to you, tap the heart.', 'brag-book-gallery' ); ?>
			</p>
		</div>
	</main>
</div>

<?php
get_footer();
