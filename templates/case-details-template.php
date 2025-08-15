<?php
/**
 * Template Name: Case Detail Page Template
 *
 * Displays individual case details with before/after images and patient information.
 * Includes a modal for favorites functionality with modern PHP 8.2 features.
 *
 * @package BRAGBook
 * @since   3.0.0
 */

declare(strict_types=1);

use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

get_header();

/**
 * Get case variables from the main template
 * These should be set by brag-book-gallery-brag.php before including this template
 */
$case_id         = $case_id ?? '';
$procedure_title = $procedure_title ?? '';
$procedure_id    = $procedure_id ?? '';
$page_id         = $page_id_via_slug ?? 0;

// Sanitize all variables
$case_id = sanitize_text_field( $case_id );
$procedure_title = sanitize_text_field( $procedure_title );
$procedure_id = sanitize_text_field( $procedure_id );
$page_id = absint( $page_id );

?>
<div class="brag-book-gallery-container-main">
	<main class="brag-book-gallery-main">
		<?php
		// Include sidebar template
		include plugin_dir_path( __FILE__ ) . 'sidebar-template.php';
		?>

		<div class="brag-book-gallery-content-area">
			<div class="brag-book-gallery-filter-attic brag-book-gallery-filter-attic-borderless">
				<button type="button" class="brag-book-gallery-sidebar-toggle" aria-label="Toggle sidebar">
					<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/menu-icon.svg' ) ); ?>"
					     style="padding:3px;"
					     alt="toggle sidebar">
				</button>

				<div class="brag-book-gallery-search-container-outer">
					<form class="search-container mobile-search-container">
						<label for="mobile-search-bar" class="screen-reader-text">
							<?php esc_html_e( 'Search Procedures', 'brag-book-gallery' ); ?>
						</label>
						<input type="text"
						       id="mobile-search-bar"
						       placeholder="<?php esc_attr_e( 'Search Procedures', 'brag-book-gallery' ); ?>">
						<img src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/search-svgrepo-com.svg' ) ); ?>"
						     class="brag-book-gallery-search-icon"
						     alt="search">
						<ul id="mobile-search-suggestions" class="search-suggestions"></ul>
					</form>
				</div>
			</div>

			<div class="brag-book-gallery-patient-box">
				<div class="brag-book-gallery-patient-left" id="patient-images">
					<?php
					/**
					 * Images will be loaded dynamically via JavaScript
					 * based on the case data
					 */
					?>
					<div class="brag-book-gallery-loading-message">
						<p><?php esc_html_e( 'Loading case details...', 'brag-book-gallery' ); ?></p>
					</div>
				</div>

				<div class="brag-book-gallery-patient-right" id="patient-details">
					<?php
					/**
					 * Patient details will be loaded dynamically via JavaScript
					 * based on the case data
					 */
					?>
					<div class="brag-book-gallery-loading-message">
						<p><?php esc_html_e( 'Loading patient information...', 'brag-book-gallery' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<?php
		/**
		 * Favorites Modal
		 * This modal appears when users click the heart icon to save a case
		 */
		?>
		<div class="brag-book-gallery-fav-modal" style="display: none;" aria-hidden="true" role="dialog">
			<div class="brag-book-gallery-fav-modal-inner">
				<button type="button"
				        class="brag-book-gallery-fav-modal-close-button"
				        aria-label="<?php esc_attr_e( 'Close modal', 'brag-book-gallery' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>

				<img class="brag-book-gallery-thumbnail"
				     src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/myfavs-logo.svg' ) ); ?>"
				     alt="<?php esc_attr_e( 'MyFavorites Logo', 'brag-book-gallery' ); ?>">

				<h3><?php esc_html_e( 'You are Loving these!', 'brag-book-gallery' ); ?></h3>

				<p>
					<?php esc_html_e( 'To improve the communication between us, keep hearting cases that speak to you. During our consultation, we\'ll review this collection together so we can discuss your specific goals and concerns.', 'brag-book-gallery' ); ?>
				</p>

				<form method="post" id="favorites-form" novalidate>
					<?php wp_nonce_field( 'save_favorite', 'favorite_nonce' ); ?>

					<div class="brag-book-gallery-input-group">
						<label for="fav-name">
							<?php esc_html_e( 'Name', 'brag-book-gallery' ); ?>
							<span class="required" aria-label="required">*</span>
						</label>
						<input type="text"
						       id="fav-name"
						       name="name"
						       class="brag-book-gallery-is-required"
						       placeholder="<?php esc_attr_e( 'Your Name', 'brag-book-gallery' ); ?>"
						       required
						       aria-required="true">
						<span class="brag-book-gallery-is-required-msg" role="alert">
							<?php esc_html_e( 'Name is required', 'brag-book-gallery' ); ?>
						</span>
					</div>

					<div class="brag-book-gallery-input-group">
						<label for="fav-email">
							<?php esc_html_e( 'Email Address', 'brag-book-gallery' ); ?>
							<span class="required" aria-label="required">*</span>
						</label>
						<input type="email"
						       id="fav-email"
						       name="email"
						       class="brag-book-gallery-is-required"
						       placeholder="<?php esc_attr_e( 'your.email@example.com', 'brag-book-gallery' ); ?>"
						       required
						       aria-required="true">
						<span class="brag-book-gallery-is-required-msg" role="alert">
							<?php esc_html_e( 'Valid email is required', 'brag-book-gallery' ); ?>
						</span>
					</div>

					<div class="brag-book-gallery-input-group">
						<label for="fav-phone">
							<?php esc_html_e( 'Phone', 'brag-book-gallery' ); ?>
							<span class="required" aria-label="required">*</span>
						</label>
						<input type="tel"
						       id="fav-phone"
						       name="phone"
						       class="brag-book-gallery-is-required"
						       placeholder="<?php esc_attr_e( '(555) 123-4567', 'brag-book-gallery' ); ?>"
						       required
						       aria-required="true">
						<span class="brag-book-gallery-is-required-msg" role="alert">
							<?php esc_html_e( 'Phone number is required', 'brag-book-gallery' ); ?>
						</span>
					</div>

					<input type="hidden" name="case_id" value="<?php echo esc_attr( $case_id ); ?>">
					<input type="hidden" name="api_token" value="">
					<input type="hidden" name="website_id" value="">

					<button type="submit" class="brag-book-gallery-submit-btn">
						<?php esc_html_e( 'Submit', 'brag-book-gallery' ); ?>
					</button>
				</form>
			</div>
		</div>
	</main>
</div>

<script>
/**
 * Case details page initialization
 */
document.addEventListener( 'DOMContentLoaded', function() {
	// Get case data attributes
	const caseId = <?php echo wp_json_encode( $case_id ); ?>;
	const procedureTitle = <?php echo wp_json_encode( $procedure_title ); ?>;
	const procedureId = <?php echo wp_json_encode( $procedure_id ); ?>;
	const pageId = <?php echo wp_json_encode( $page_id ); ?>;

	// Modal handling
	const modal = document.querySelector( '.brag-book-gallery-fav-modal' );
	const closeBtn = document.querySelector( '.brag-book-gallery-fav-modal-close-button' );
	const form = document.getElementById( 'favorites-form' );

	// Close modal functionality
	if ( closeBtn ) {
		closeBtn.addEventListener( 'click', function() {
			if ( modal ) {
				modal.style.display = 'none';
				modal.setAttribute( 'aria-hidden', 'true' );
			}
		});
	}

	// Close modal when clicking outside
	if ( modal ) {
		modal.addEventListener( 'click', function( e ) {
			if ( e.target === modal ) {
				modal.style.display = 'none';
				modal.setAttribute( 'aria-hidden', 'true' );
			}
		});
	}

	// Form validation
	if ( form ) {
		form.addEventListener( 'submit', function( e ) {
			e.preventDefault();

			let isValid = true;
			const requiredFields = form.querySelectorAll( '.brag-book-gallery-is-required' );

			requiredFields.forEach( field => {
				const errorMsg = field.parentElement.querySelector( '.brag-book-gallery-is-required-msg' );

				if ( ! field.value.trim() ) {
					isValid = false;
					field.classList.add( 'error' );
					if ( errorMsg ) {
						errorMsg.style.display = 'block';
					}
				} else {
					field.classList.remove( 'error' );
					if ( errorMsg ) {
						errorMsg.style.display = 'none';
					}
				}

				// Email validation
				if ( field.type === 'email' && field.value.trim() ) {
					const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
					if ( ! emailRegex.test( field.value ) ) {
						isValid = false;
						field.classList.add( 'error' );
						if ( errorMsg ) {
							errorMsg.textContent = '<?php echo esc_js( __( 'Please enter a valid email address', 'brag-book-gallery' ) ); ?>';
							errorMsg.style.display = 'block';
						}
					}
				}
			});

			if ( isValid ) {
				// Submit form via AJAX or standard submission
				form.submit();
			}
		});
	}

	// Clear error states on input
	const requiredFields = document.querySelectorAll( '.brag-book-gallery-is-required' );
	requiredFields.forEach( field => {
		field.addEventListener( 'input', function() {
			if ( this.value.trim() ) {
				this.classList.remove( 'error' );
				const errorMsg = this.parentElement.querySelector( '.brag-book-gallery-is-required-msg' );
				if ( errorMsg ) {
					errorMsg.style.display = 'none';
				}
			}
		});
	});
});
</script>

<?php
get_footer();
