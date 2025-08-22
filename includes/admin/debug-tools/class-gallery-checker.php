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
		
		<script>
		document.addEventListener('DOMContentLoaded', () => {
			// Helper function for AJAX requests using async/await
			const ajaxPost = async (data) => {
				const formData = new FormData();
				Object.entries(data).forEach(([key, value]) => {
					formData.append(key, value);
				});
				
				try {
					const response = await fetch(ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					});
					return await response.json();
				} catch (error) {
					console.error('AJAX error:', error);
					return { success: false, data: 'Network error' };
				}
			};
			
			// Create gallery page
			document.getElementById('create-gallery-page')?.addEventListener('click', async function() {
				if (!confirm('<?php esc_html_e( 'This will create a new page with the [brag_book_gallery] shortcode. Continue?', 'brag-book-gallery' ); ?>')) {
					return;
				}
				
				this.disabled = true;
				const resultDiv = document.getElementById('gallery-action-result');
				if (resultDiv) {
					resultDiv.innerHTML = '<p><?php esc_html_e( 'Creating gallery page...', 'brag-book-gallery' ); ?></p>';
				}
				
				const response = await ajaxPost({
					action: 'brag_book_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_debug_tools' ); ?>',
					tool: 'gallery-checker',
					tool_action: 'create_page'
				});
				
				if (resultDiv) {
					if (response.success) {
						resultDiv.innerHTML = `<div class="notice notice-success"><p>${response.data}</p></div>`;
						setTimeout(() => location.reload(), 2000);
					} else {
						resultDiv.innerHTML = `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`;
					}
				}
				this.disabled = false;
			});
			
			// Update gallery slug
			document.getElementById('update-gallery-slug')?.addEventListener('click', async function() {
				const newSlug = prompt('<?php esc_html_e( 'Enter the slug of an existing page that contains [brag_book_gallery]:', 'brag-book-gallery' ); ?>');
				if (!newSlug) return;
				
				this.disabled = true;
				const resultDiv = document.getElementById('gallery-action-result');
				if (resultDiv) {
					resultDiv.innerHTML = '<p><?php esc_html_e( 'Updating gallery slug...', 'brag-book-gallery' ); ?></p>';
				}
				
				const response = await ajaxPost({
					action: 'brag_book_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_debug_tools' ); ?>',
					tool: 'gallery-checker',
					tool_action: 'update_slug',
					gallery_slug: newSlug
				});
				
				if (resultDiv) {
					if (response.success) {
						resultDiv.innerHTML = `<div class="notice notice-success"><p>${response.data}</p></div>`;
						setTimeout(() => location.reload(), 2000);
					} else {
						resultDiv.innerHTML = `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`;
					}
				}
				this.disabled = false;
			});
			
			// Show gallery rules
			document.getElementById('show-gallery-rules')?.addEventListener('click', async function() {
				this.disabled = true;
				const displayDiv = document.getElementById('gallery-rules-display');
				if (displayDiv) {
					displayDiv.innerHTML = '<p><?php esc_html_e( 'Loading rules...', 'brag-book-gallery' ); ?></p>';
				}
				
				const response = await ajaxPost({
					action: 'brag_book_debug_tool',
					nonce: '<?php echo wp_create_nonce( 'brag_book_debug_tools' ); ?>',
					tool: 'gallery-checker',
					tool_action: 'show_rules'
				});
				
				if (displayDiv) {
					if (response.success) {
						displayDiv.innerHTML = response.data;
					} else {
						displayDiv.innerHTML = `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`;
					}
				}
				this.disabled = false;
			});
		});
		</script>
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
		$page = $brag_book_gallery_page_id ? get_post( $brag_book_gallery_page_id ) : null;
		$page_url = $page ? get_permalink( $page->ID ) : null;
		$edit_link = $page ? get_edit_post_link( $page->ID ) : null;
		?>
		<div class="gallery-config-cards">
			<!-- Gallery Page Card -->
			<div class="config-card">
				<div class="config-card-header">
					<div class="config-card-icon <?php echo $brag_book_gallery_page_slug ? 'icon-active' : 'icon-inactive'; ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
							<polyline points="14 2 14 8 20 8"></polyline>
						</svg>
					</div>
					<h4 class="config-card-title"><?php esc_html_e( 'Gallery Page', 'brag-book-gallery' ); ?></h4>
				</div>
				<div class="config-card-content">
					<?php if ( $brag_book_gallery_page_slug ) : ?>
						<div class="config-item">
							<span class="config-label"><?php esc_html_e( 'Slug:', 'brag-book-gallery' ); ?></span>
							<code class="config-value">/<?php echo esc_html( $brag_book_gallery_page_slug ); ?>/</code>
						</div>
						<?php if ( $page ) : ?>
							<div class="config-item">
								<span class="config-label"><?php esc_html_e( 'Title:', 'brag-book-gallery' ); ?></span>
								<span class="config-value"><?php echo esc_html( $page->post_title ); ?></span>
							</div>
							<div class="config-item">
								<span class="config-label"><?php esc_html_e( 'ID:', 'brag-book-gallery' ); ?></span>
								<span class="config-value-badge">#<?php echo esc_html( $page->ID ); ?></span>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="config-empty">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<circle cx="12" cy="12" r="10"></circle>
								<line x1="12" y1="8" x2="12" y2="12"></line>
								<line x1="12" y1="16" x2="12.01" y2="16"></line>
							</svg>
							<span><?php esc_html_e( 'No gallery page configured', 'brag-book-gallery' ); ?></span>
						</div>
					<?php endif; ?>
					
					<?php if ( $page_url || $edit_link ) : ?>
						<div class="config-actions">
							<?php if ( $page_url ) : ?>
								<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" class="config-link">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
										<polyline points="15 3 21 3 21 9"></polyline>
										<line x1="10" y1="14" x2="21" y2="3"></line>
									</svg>
									<?php esc_html_e( 'View', 'brag-book-gallery' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $edit_link ) : ?>
								<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank" class="config-link">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
										<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
									</svg>
									<?php esc_html_e( 'Edit', 'brag-book-gallery' ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Shortcode Status Card -->
			<div class="config-card">
				<div class="config-card-header">
					<div class="config-card-icon <?php echo ( $page && strpos( $page->post_content, '[brag_book_gallery' ) !== false ) ? 'icon-success' : 'icon-warning'; ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="16 18 22 12 16 6"></polyline>
							<polyline points="8 6 2 12 8 18"></polyline>
						</svg>
					</div>
					<h4 class="config-card-title"><?php esc_html_e( 'Shortcode Status', 'brag-book-gallery' ); ?></h4>
				</div>
				<div class="config-card-content">
					<?php if ( $page ) : ?>
						<?php if ( strpos( $page->post_content, '[brag_book_gallery' ) !== false ) : ?>
							<div class="config-status-success">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
									<polyline points="22 4 12 14.01 9 11.01"></polyline>
								</svg>
								<span><?php esc_html_e( 'Shortcode found', 'brag-book-gallery' ); ?></span>
							</div>
							<div class="config-code">
								<code>[brag_book_gallery]</code>
							</div>
						<?php else : ?>
							<div class="config-status-warning">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
									<line x1="12" y1="9" x2="12" y2="13"></line>
									<line x1="12" y1="17" x2="12.01" y2="17"></line>
								</svg>
								<span><?php esc_html_e( 'Shortcode missing', 'brag-book-gallery' ); ?></span>
							</div>
							<p class="config-hint"><?php esc_html_e( 'Add [brag_book_gallery] to the page content', 'brag-book-gallery' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<div class="config-empty">
							<span><?php esc_html_e( 'No page to check', 'brag-book-gallery' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Page Status Card -->
			<div class="config-card">
				<div class="config-card-header">
					<div class="config-card-icon <?php echo ( $page && $page->post_status === 'publish' ) ? 'icon-success' : 'icon-warning'; ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="10"></circle>
							<polyline points="12 6 12 12 16 14"></polyline>
						</svg>
					</div>
					<h4 class="config-card-title"><?php esc_html_e( 'Page Status', 'brag-book-gallery' ); ?></h4>
				</div>
				<div class="config-card-content">
					<?php if ( $page ) : ?>
						<div class="config-status-badge status-<?php echo esc_attr( $page->post_status ); ?>">
							<?php echo esc_html( ucfirst( $page->post_status ) ); ?>
						</div>
						<?php if ( $page->post_status !== 'publish' ) : ?>
							<p class="config-hint"><?php esc_html_e( 'Page must be published to work', 'brag-book-gallery' ); ?></p>
						<?php else : ?>
							<div class="config-item">
								<span class="config-label"><?php esc_html_e( 'Modified:', 'brag-book-gallery' ); ?></span>
								<span class="config-value"><?php echo esc_html( human_time_diff( strtotime( $page->post_modified ), current_time( 'timestamp' ) ) . ' ago' ); ?></span>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="config-empty">
							<span><?php esc_html_e( 'No page configured', 'brag-book-gallery' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
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
		<div class="rewrite-table-wrapper">
			<table class="rewrite-table pages-table striped">
				<thead>
					<tr class="header-row">
						<th class="header-cell">
							<span class="header-text"><?php esc_html_e( 'ID', 'brag-book-gallery' ); ?></span>
						</th>
						<th class="header-cell">
							<span class="header-text"><?php esc_html_e( 'Title', 'brag-book-gallery' ); ?></span>
						</th>
						<th class="header-cell">
							<span class="header-text"><?php esc_html_e( 'Slug', 'brag-book-gallery' ); ?></span>
						</th>
						<th class="header-cell">
							<span class="header-text"><?php esc_html_e( 'Status', 'brag-book-gallery' ); ?></span>
						</th>
						<th class="header-cell">
							<span class="header-text"><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pages as $page ) : ?>
					<tr class="table-row">
						<td class="data-cell">
							<span class="data-text"><?php echo esc_html( $page->ID ); ?></span>
						</td>
						<td class="data-cell">
							<span class="data-text"><?php echo esc_html( $page->post_title ); ?></span>
						</td>
						<td class="data-cell">
							<span class="data-text"><code><?php echo esc_html( $page->post_name ); ?></code></span>
						</td>
						<td class="data-cell">
							<span class="data-text"><?php echo esc_html( $page->post_status ); ?></span>
						</td>
						<td class="data-cell">
							<span class="data-text">
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
							</span>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

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
	 * Execute tool actions via AJAX.
	 *
	 * Handles all AJAX requests for gallery checker operations.
	 *
	 * @since 3.0.0
	 * @param string $action Action to execute.
	 * @param array  $data   Request data from AJAX.
	 * @return mixed Response data for AJAX.
	 * @throws \Exception If action is invalid.
	 */
	public function execute( string $action, array $data ): mixed {
		return match ( $action ) {
			'create_page' => $this->create_gallery_page(),
			'update_slug' => $this->update_gallery_slug( $data['gallery_slug'] ?? '' ),
			'show_rules' => $this->show_gallery_rules(),
			default => throw new \Exception( 'Invalid action: ' . $action ),
		};
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
