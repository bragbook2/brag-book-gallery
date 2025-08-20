<?php
/**
 * Gallery Page Checker Tool
 *
 * Checks gallery page setup and configuration
 *
 * @package BragBookGallery
 * @since 3.0.0
 */

namespace BragBookGallery\Admin\Debug_Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Checker class
 *
 * @since 3.0.0
 */
class Gallery_Checker {

	/**
	 * Get checkmark SVG icon
	 *
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML.
	 */
	private function get_check_icon( bool $success = true ): string {
		if ( $success ) {
			// Green checkmark circle for success
			return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#4caf50" style="vertical-align: middle; margin-right: 5px;"><path d="m423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z"/></svg>';
		} else {
			// Red X for error/not set
			return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#f44336" style="vertical-align: middle; margin-right: 5px;"><path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/></svg>';
		}
	}

	/**
	 * Get warning SVG icon
	 *
	 * @return string SVG HTML.
	 */
	private function get_warning_icon(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ff9800" style="vertical-align: middle; margin-right: 5px;"><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
	}

	/**
	 * Render the checker tool interface
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="gallery-checker-tool">
			<h2><?php esc_html_e( 'Gallery Page Setup Check', 'brag-book-gallery' ); ?></h2>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Current Settings', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_current_settings(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Page Status', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_page_status(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Available Gallery Pages', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_available_pages(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></h3>
				<button class="button button-primary" id="create-gallery-page">
					<?php esc_html_e( 'Create Gallery Page', 'brag-book-gallery' ); ?>
				</button>
				<button class="button" id="update-gallery-slug">
					<?php esc_html_e( 'Update Gallery Slug', 'brag-book-gallery' ); ?>
				</button>
				<div id="gallery-action-result"></div>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( 'Active Rewrite Rules', 'brag-book-gallery' ); ?></h3>
				<button class="button" id="show-gallery-rules">
					<?php esc_html_e( 'Show Gallery Rules', 'brag-book-gallery' ); ?>
				</button>
				<div id="gallery-rules-display"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render current settings
	 *
	 * @return void
	 */
	private function render_current_settings(): void {
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id' );
		?>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Gallery Slug', 'brag-book-gallery' ); ?></th>
					<td>
						<code><?php echo esc_html( $brag_book_gallery_page_slug ?: '(not set)' ); ?></code>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Gallery Page ID', 'brag-book-gallery' ); ?></th>
					<td>
						<?php echo esc_html( $brag_book_gallery_page_id ?: '(not set)' ); ?>
						<?php if ( $brag_book_gallery_page_id ) : ?>
							<?php $edit_link = get_edit_post_link( $brag_book_gallery_page_id ); ?>
							<?php if ( $edit_link ) : ?>
								<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank">
									<?php esc_html_e( 'Edit', 'brag-book-gallery' ); ?>
								</a>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render page status
	 *
	 * @return void
	 */
	private function render_page_status(): void {
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();

		if ( ! $brag_book_gallery_page_slug ) {
			echo '<p style="color: orange;">' . $this->get_warning_icon() . esc_html__( 'No gallery slug configured', 'brag-book-gallery' ) . '</p>';
			return;
		}

		$page = get_page_by_path( $brag_book_gallery_page_slug );

		if ( $page ) {
			echo '<p style="color: green;">' . $this->get_check_icon( true ) . sprintf(
				/* translators: %1$s: page slug, %2$d: page ID, %3$s: page title */
				esc_html__( 'Found page with slug "%1$s" (ID: %2$d, Title: %3$s)', 'brag-book-gallery' ),
				esc_html( $brag_book_gallery_page_slug ),
				$page->ID,
				esc_html( $page->post_title )
			) . '</p>';

			// Check for shortcode
			if ( strpos( $page->post_content, '[brag_book_gallery' ) !== false ) {
				echo '<p style="color: green;">' . $this->get_check_icon( true ) . esc_html__( 'Page contains [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</p>';
			} else {
				echo '<p style="color: orange;">' . $this->get_warning_icon() . esc_html__( 'Page does NOT contain [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</p>';
				echo '<p>' . esc_html__( 'You need to add the shortcode to this page for it to work.', 'brag-book-gallery' ) . '</p>';
			}

			// Show page URL
			$page_url = get_permalink( $page->ID );
			if ( $page_url ) {
				echo '<p>' . esc_html__( 'Page URL:', 'brag-book-gallery' ) . ' <a href="' . esc_url( $page_url ) . '" target="_blank">' . esc_url( $page_url ) . '</a></p>';
			}
		} else {
			echo '<p style="color: red;">' . $this->get_check_icon( false ) . sprintf(
				/* translators: %s: gallery slug */
				esc_html__( 'No page found with slug "%s"', 'brag-book-gallery' ),
				esc_html( $brag_book_gallery_page_slug )
			) . '</p>';

			echo '<p><strong>' . esc_html__( 'This is the problem!', 'brag-book-gallery' ) . '</strong> ' . esc_html__( 'You need to either:', 'brag-book-gallery' ) . '</p>';
			echo '<ol>';
			echo '<li>' . sprintf(
				/* translators: %s: gallery slug */
				esc_html__( 'Create a page with the slug "%s" and add [brag_book_gallery] shortcode to it', 'brag-book-gallery' ),
				esc_html( $brag_book_gallery_page_slug )
			) . '</li>';
			echo '<li>' . esc_html__( 'OR use an existing page slug that has the [brag_book_gallery] shortcode', 'brag-book-gallery' ) . '</li>';
			echo '</ol>';
		}
	}

	/**
	 * Render available pages with shortcode
	 *
	 * @return void
	 */
	private function render_available_pages(): void {
		global $wpdb;

		$pages = $wpdb->get_results(
			"SELECT ID, post_name, post_title, post_status
			FROM {$wpdb->posts}
			WHERE post_content LIKE '%[brag_book_gallery%'
			AND post_type = 'page'
			ORDER BY post_status DESC, post_title ASC"
		);

		if ( empty( $pages ) ) {
			echo '<p style="color: red;">' . esc_html__( 'No pages found with the [brag_book_gallery] shortcode!', 'brag-book-gallery' ) . '</p>';
			echo '<p>' . esc_html__( 'You need to create a page and add the [brag_book_gallery] shortcode to it.', 'brag-book-gallery' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Title', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $page ) : ?>
				<tr>
					<td><?php echo esc_html( $page->ID ); ?></td>
					<td><?php echo esc_html( $page->post_title ); ?></td>
					<td><code><?php echo esc_html( $page->post_name ); ?></code></td>
					<td><?php echo esc_html( $page->post_status ); ?></td>
					<td>
						<?php $view_link = get_permalink( $page->ID ); ?>
						<?php $edit_link = get_edit_post_link( $page->ID ); ?>
						<?php if ( $view_link ) : ?>
							<a href="<?php echo esc_url( $view_link ); ?>" target="_blank">
								<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
							</a>
							<?php if ( $edit_link ) : ?>
								|
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( $edit_link ) : ?>
							<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank">
								<?php esc_html_e( 'Edit', 'brag-book-gallery' ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $pages ) ) : ?>
			<?php $first_page = $pages[0]; ?>
			<?php if ( $first_page->post_status === 'publish' ) : ?>
				<h4><?php esc_html_e( 'Recommendation:', 'brag-book-gallery' ); ?></h4>
				<p>
					<?php
					printf(
						/* translators: %s: page slug */
						esc_html__( 'You should set the gallery slug to: %s', 'brag-book-gallery' ),
						'<code>' . esc_html( $first_page->post_name ) . '</code>'
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Execute tool actions via AJAX
	 *
	 * @param string $action Action to execute.
	 * @param array  $data   Request data.
	 * @return mixed
	 */
	public function execute( string $action, array $data ) {
		switch ( $action ) {
			case 'create_page':
				return $this->create_gallery_page();

			case 'update_slug':
				return $this->update_gallery_slug( $data['gallery_slug'] ?? '' );

			case 'show_rules':
				return $this->show_gallery_rules();

			default:
				throw new \Exception( 'Invalid action' );
		}
	}

	/**
	 * Create a new gallery page
	 *
	 * @return string
	 */
	private function create_gallery_page(): string {
		$page_title = 'Before & After Gallery';
		$page_slug  = 'before-after';

		// Check if page already exists
		$existing = get_page_by_path( $page_slug );
		if ( $existing ) {
			return __( 'A page with this slug already exists!', 'brag-book-gallery' );
		}

		// Create the page
		$page_id = wp_insert_post( [
			'post_title'   => $page_title,
			'post_name'    => $page_slug,
			'post_content' => '[brag_book_gallery]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		] );

		if ( is_wp_error( $page_id ) ) {
			return $page_id->get_error_message();
		}

		// Update options using Slug_Helper
		\BRAGBookGallery\Includes\Core\Slug_Helper::set_primary_slug( $page_slug );
		update_option( 'brag_book_gallery_page_id', $page_id );

		// Flush rewrite rules
		flush_rewrite_rules( true );

		return sprintf(
			/* translators: %1$s: page title, %2$d: page ID */
			__( 'Gallery page "%1$s" created successfully (ID: %2$d). Rewrite rules have been flushed.', 'brag-book-gallery' ),
			$page_title,
			$page_id
		);
	}

	/**
	 * Update gallery slug
	 *
	 * @param string $slug New gallery slug.
	 * @return string
	 */
	private function update_gallery_slug( string $slug ): string {
		$slug = sanitize_title( $slug );

		if ( empty( $slug ) ) {
			return __( 'Invalid slug provided', 'brag-book-gallery' );
		}

		// Check if page exists
		$page = get_page_by_path( $slug );
		if ( ! $page ) {
			return sprintf(
				/* translators: %s: page slug */
				__( 'No page found with slug "%s"', 'brag-book-gallery' ),
				$slug
			);
		}

		// Check for shortcode
		if ( strpos( $page->post_content, '[brag_book_gallery' ) === false ) {
			return __( 'The selected page does not contain the [brag_book_gallery] shortcode', 'brag-book-gallery' );
		}

		// Update options using Slug_Helper
		\BRAGBookGallery\Includes\Core\Slug_Helper::set_primary_slug( $slug );
		update_option( 'brag_book_gallery_page_id', $page->ID );

		// Flush rewrite rules
		flush_rewrite_rules( true );

		return sprintf(
			/* translators: %s: page slug */
			__( 'Gallery slug updated to "%s" and rewrite rules flushed.', 'brag-book-gallery' ),
			$slug
		);
	}

	/**
	 * Show gallery-related rewrite rules
	 *
	 * @return string
	 */
	private function show_gallery_rules(): string {
		global $wp_rewrite;

		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$rules = $wp_rewrite->wp_rewrite_rules();

		$output = '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';

		$found = false;
		// Ensure $rules is an array before iterating
		if ( is_array( $rules ) ) {
			foreach ( $rules as $pattern => $query ) {
				if ( $brag_book_gallery_page_slug && strpos( $pattern, $brag_book_gallery_page_slug ) === 0 ) {
					$output .= htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
					$found = true;
				}
			}
		}

		if ( ! $found ) {
			$output .= __( 'No rewrite rules found for the current gallery slug.', 'brag-book-gallery' );
			if ( ! $brag_book_gallery_page_slug ) {
				$output .= "\n" . __( 'Gallery slug is not configured.', 'brag-book-gallery' );
			}
		}

		$output .= '</pre>';

		return $output;
	}
}
