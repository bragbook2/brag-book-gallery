<?php
/**
 * Template Name: Favorites Page Template
 *
 * Displays user's favorite gallery cases with modern PHP 8.2 features
 * and WP VIP coding standards.
 *
 * @package BRAGBook
 * @since   3.0.0
 */

declare( strict_types=1 );

use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

get_header();

/**
 * Parse and sanitize the current request URI
 */
$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$brag_case_url = strtok( $request_uri, '?' );
$case_url = trim( $brag_case_url, '/' );
$url_parts = explode( '/', $case_url );

/**
 * Get page information
 */
$base_slug = $url_parts[0] ?? '';
$page = get_page_by_path( $base_slug );

if ( ! $page instanceof WP_Post ) {
	get_template_part( '404' );
	exit;
}

$page_id = $page->ID;

/**
 * Initialize route variables
 */
$procedure_title = '';
$procedure_id = '';
$case_id = '';

/**
 * Parse URL segments for favorites detail page
 */
if ( count( $url_parts ) >= 3 ) {
	$procedure_title = sanitize_title( $url_parts[2] );
	$procedure_id    = get_option( $procedure_title . '_id', '' );
}

if ( count( $url_parts ) >= 4 ) {
	$case_id = sanitize_text_field( $url_parts[3] );
}

/**
 * Get favorites data
 */
$favorite_case_ids = get_option( 'favorite_caseIds_ajax', [] );
if ( ! is_array( $favorite_case_ids ) ) {
	$favorite_case_ids = array();
}

/**
 * Check if this is a detail page
 */
$is_detail_page = ! empty( $procedure_title ) &&
				  ! empty( $case_id ) &&
				  $brag_case_url === "/{$base_slug}/favorites/{$procedure_title}/{$case_id}/";

$case_exists = false;
if ( $is_detail_page ) {
	$case_option_key = "{$case_id}_brag_book_gallery_procedure_id_f_{$page_id}";
	$case_exists     = get_option( $case_option_key, '' ) !== '';
}

?>
<div class="brag-book-gallery-container-main">
	<main class="brag-book-gallery-main">
		<?php
		// Include sidebar template
		include plugin_dir_path( __FILE__ ) . 'sidebar-template.php';
		?>

		<?php if ( $is_detail_page && $case_exists ) : ?>
		<?php
		/**
		 * Display favorite case detail
		 */

		// Initialize data variables with proper typing
		$matching_data = [];
		$patient_detail = '';
		$height = '';
		$weight = '';
		$race = '';
		$gender = '';
		$age = '';
		$timeframe = '';
		$timeframe2 = '';
		$revision_surgery = '';
		$seo_detail = [];

		// This would normally come from your API or database
		// For now using placeholder structure
		foreach ( $matching_data as $procedure_data ) {
			$patient_detail = $procedure_data['details'] ?? '';
			$height         = ! empty( $procedure_data['height'] )
				? sprintf( '<li>HEIGHT: %s</li>', esc_html( strtolower( $procedure_data['height'] ) ) )
				: '';
			$weight         = ! empty( $procedure_data['weight'] )
				? sprintf( '<li>WEIGHT: %s</li>', esc_html( strtolower( $procedure_data['weight'] ) ) )
				: '';
			$race           = ! empty( $procedure_data['ethnicity'] )
				? sprintf( '<li>RACE: %s</li>', esc_html( strtolower( $procedure_data['ethnicity'] ) ) )
				: '';
			$gender         = ! empty( $procedure_data['gender'] )
				? sprintf( '<li>GENDER: %s</li>', esc_html( strtolower( $procedure_data['gender'] ) ) )
				: '';
			$age            = ! empty( $procedure_data['age'] )
				? sprintf( '<li>AGE: %s</li>', esc_html( strtolower( $procedure_data['age'] ) ) )
				: '';

			if ( ! empty( $procedure_data['after1Timeframe'] ) && ! empty( $procedure_data['after1Unit'] ) ) {
				$timeframe = sprintf(
					'<li>POST-OP PERIOD: %s %s</li>',
					esc_html( strtolower( $procedure_data['after1Timeframe'] ) ),
					esc_html( strtolower( $procedure_data['after1Unit'] ) )
				);
			}

			if ( ! empty( $procedure_data['after2Timeframe'] ) && ! empty( $procedure_data['after2Unit'] ) ) {
				$timeframe2 = sprintf(
					'<li>2nd AFTER: %s %s</li>',
					esc_html( strtolower( $procedure_data['after2Timeframe'] ) ),
					esc_html( strtolower( $procedure_data['after2Unit'] ) )
				);
			}

			$revision_surgery = ! empty( $procedure_data['revisionSurgery'] )
				? '<li>This case is a revision of a previous procedure.</li>'
				: '';

			$seo_detail = $procedure_data['caseDetails'][0] ?? [];
		}
		?>

		<div class="brag-book-gallery-content-area">
			<div
				class="brag-book-gallery-filter-attic brag-book-gallery-filter-attic-borderless">
				<button type="button"
						class="brag-book-gallery-sidebar-toggle"
						aria-label="Toggle sidebar">
					<img
						src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/caret-right-sm.svg' ) ); ?>"
						alt="toggle sidebar">
				</button>
				<div class="brag-book-gallery-search-container-outer">
					<form
						class="search-container mobile-search-container">
						<input type="text"
							   id="mobile-search-bar"
							   placeholder="<?php esc_attr_e( 'Search...', 'brag-book' ); ?>">
						<img
							src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/search-svgrepo-com.svg' ) ); ?>"
							class="brag-book-gallery-search-icon"
							alt="search">
						<ul id="mobile-search-suggestions"
							class="search-suggestions"></ul>
					</form>
				</div>
			</div>

			<div class="brag-book-gallery-patient-box">
				<div class="brag-book-gallery-patient-left">
					<div class="brag-book-gallery-patient-row">
						<?php
						$headline = ! empty( $seo_detail['seoHeadline'] )
							? esc_html( $seo_detail['seoHeadline'] )
							: sprintf( '%s Patient', esc_html( $procedure_title ) );
						?>
						<h2><?php echo $headline; ?></h2>

						<?php
						// Display heart icon based on favorite status.
						$heart_icon = in_array( $case_id, $favorite_case_ids, true )
							? 'red-heart.svg'
							: 'red-heart-outline.svg';
						?>
						<img
							class="brag-book-gallery-heart-icon brag-book-gallery-open-fav-modal"
							data-case-id="<?php echo esc_attr( $case_id ); ?>"
							src="<?php echo esc_url( Setup::get_asset_url( "assets/images/{$heart_icon}" ) ); ?>"
							alt="favorite">
					</div>

					<?php
					// Display photo sets
					if ( ! empty( $matching_data ) ) :
					foreach ( $matching_data as $procedure_data ) :
					if ( ! empty( $procedure_data['photoSets'] ) ) :
					foreach ( $procedure_data['photoSets'] as $photo_set ) :
					$image_url = $photo_set['highResPostProcessedImageLocation']
								 ?? $photo_set['postProcessedImageLocation']
									?? $photo_set['originalBeforeLocation']
									   ?? '';

					if ( ! empty( $image_url ) ) :
					?>
					<img
						class="brag-book-gallery-image"
						src="<?php echo esc_url( $image_url ); ?>"
						alt="<?php echo esc_attr( $photo_set['seoAltText'] ?? 'Gallery image' ); ?>"
						loading="lazy">
					<?php
					endif;
					endforeach;
					endif;
					endforeach;
					endif;
					?>
				</div>

				<div class="brag-book-gallery-patient-right">
					<div class="brag-book-gallery-patient-row">
						<h2><?php echo $headline; ?></h2>
						<img
							class="brag-book-gallery-heart-icon brag-book-gallery-open-fav-modal"
							data-case-id="<?php echo esc_attr( $case_id ); ?>"
							src="<?php echo esc_url( Setup::get_asset_url( "assets/images/{$heart_icon}" ) ); ?>"
							alt="favorite">
					</div>

					<?php if ( $height || $weight || $race || $gender || $age || $timeframe || $timeframe2 || $revision_surgery ) : ?>
					<ul class="brag-book-gallery-patient-features">
						<?php echo $height . $weight . $race . $gender . $age . $timeframe . $timeframe2 . $revision_surgery; ?>
					</ul>
					<?php endif; ?>

					<?php if ( ! empty( $patient_detail ) ) : ?>
					<div class="brag-book-gallery-patient-detail">
						<?php echo wp_kses_post( $patient_detail ); ?>
					</div>
					<?php endif; ?>

					<a href="<?php echo esc_url( "/{$base_slug}/consultation/" ); ?>"
					   class="brag-book-gallery-sidebar-btn">
						<?php esc_html_e( 'REQUEST A CONSULTATION', 'brag-book' ); ?>
					</a>

					<div class="brag-book-gallery-patient-slides"
						 data-page-id="<?php echo esc_attr( $page_id ); ?>"
						 data-page-url="<?php echo esc_attr( $brag_case_url ); ?>">
					</div>

					<script>
						document.addEventListener( 'DOMContentLoaded', function () {
							const slidesContainer = document.querySelector( '.brag-book-gallery-patient-slides' );
							if ( !slidesContainer ) {
								return;
							}

							const pageId = slidesContainer.dataset.pageId;
							const pageUrl = slidesContainer.dataset.pageUrl;

							// Load pagination via AJAX
							const formData = new FormData();
							formData.append( 'action', 'brag_book_gallery_generate_pagination' );
							formData.append( 'page_id_via_slug', pageId );
							formData.append( 'page_url', pageUrl );

							fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
								method: 'POST',
								body: formData,
								credentials: 'same-origin'
							} )
								.then( response => response.text() )
								.then( html => {
									slidesContainer.innerHTML = html;
								} )
								.catch( error => {
									console.error( 'Error loading pagination:', error );
								} );
						} );
					</script>
				</div>
			</div>
		</div>

		<?php else : ?>
		<?php
		/**
		 * Display favorites list page
		 */
		?>
		<div class="brag-book-gallery-content-area">
			<div class="brag-book-gallery-filter-attic">
				<button type="button"
						class="brag-book-gallery-sidebar-toggle"
						aria-label="Toggle sidebar">
					<img
						src="<?php echo esc_url( Setup::get_asset_url( 'assets/images/caret-right-sm.svg' ) ); ?>"
						alt="toggle sidebar">
				</button>
				<h2>
					<span><?php esc_html_e( 'My Favorites', 'brag-book' ); ?></span>
				</h2>
			</div>

			<div class="brag-book-gallery-content-boxes-sm">
				<div class="brag-book-gallery-content-boxes"
					 id="brag-book-gallery-content-boxes-ajax">
					<?php if ( empty( $favorite_case_ids ) ) : ?>
					<div class="brag-book-gallery-no-favorites">
						<p><?php esc_html_e( 'You have not added any cases to your favorites yet.', 'brag-book' ); ?></p>
						<p><?php esc_html_e( 'Browse the gallery and click the heart icon to add cases to your favorites.', 'brag-book' ); ?></p>
					</div>
					<?php else : ?>
					<div
						class="brag-book-gallery-loading-favorites">
						<p><?php esc_html_e( 'Loading your favorite cases...', 'brag-book' ); ?></p>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

	</main>
</div>

<?php
// Set SEO page title if available
if ( ! empty( $seo_detail['seoPageTitle'] ) ) :
$seo_page_title = esc_js( $seo_detail['seoPageTitle'] );
?>
<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		document.title = '<?php echo $seo_page_title; ?>';
	} );
</script>
<?php endif; ?>

	<?php
	get_footer();
