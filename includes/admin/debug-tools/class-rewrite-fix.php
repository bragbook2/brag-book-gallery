<?php
/**
 * Rewrite Fix Tool
 *
 * Provides tools to fix rewrite rules issues on live sites
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
 * Rewrite Fix class
 *
 * @since 3.0.0
 */
class Rewrite_Fix {

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
	 * Render the fix tool interface
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="rewrite-fix-tool">
			<h2><?php esc_html_e( 'Live Site Rewrite Rules Fix', 'brag-book-gallery' ); ?></h2>

			<div class="tool-section">
				<h3><?php esc_html_e( '1. Server Environment', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_server_info(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( '2. .htaccess Status', 'brag-book-gallery' ); ?></h3>
				<button class="button" id="check-htaccess">
					<?php esc_html_e( 'Check .htaccess', 'brag-book-gallery' ); ?>
				</button>
				<div id="htaccess-status"></div>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( '3. Gallery Configuration', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_gallery_config(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( '4. Rewrite Rules Status', 'brag-book-gallery' ); ?></h3>
				<button class="button" id="check-rules-status">
					<?php esc_html_e( 'Check Rules Status', 'brag-book-gallery' ); ?>
				</button>
				<div id="rules-status"></div>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( '5. Test URLs', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_test_urls(); ?>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( '6. Fix Actions', 'brag-book-gallery' ); ?></h3>
				<button class="button button-primary" id="apply-fixes">
					<?php esc_html_e( 'Apply All Fixes', 'brag-book-gallery' ); ?>
				</button>
				<div id="fix-result"></div>
			</div>

			<div class="tool-section">
				<h3><?php esc_html_e( '7. Manual Fix Instructions', 'brag-book-gallery' ); ?></h3>
				<?php $this->render_manual_instructions(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render server information
	 *
	 * @return void
	 */
	private function render_server_info(): void {
		?>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Server Software', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( phpversion() ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Permalink Structure', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( get_option( 'permalink_structure' ) ?: 'Plain' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Home URL', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_url( home_url() ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Site URL', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_url( site_url() ); ?></td>
				</tr>
				<?php if ( defined( 'WPE_APIKEY' ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Hosting', 'brag-book-gallery' ); ?></th>
					<td style="color: orange;">WP Engine Detected - Some restrictions may apply</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render gallery configuration
	 *
	 * @return void
	 */
	private function render_gallery_config(): void {
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id' );
		?>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Combine Gallery Slug', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $brag_book_gallery_page_slug ?: '(not set)' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Combine Gallery Page ID', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $brag_book_gallery_page_id ?: '(not set)' ); ?></td>
				</tr>
				<?php if ( $brag_book_gallery_page_slug ) : ?>
				<?php 
				// Handle both string and array formats
				$slug_to_check = is_array( $brag_book_gallery_page_slug ) ? reset( $brag_book_gallery_page_slug ) : $brag_book_gallery_page_slug;
				$page = get_page_by_path( $slug_to_check ); 
				?>
				<tr>
					<th><?php esc_html_e( 'Page Status', 'brag-book-gallery' ); ?></th>
					<td>
						<?php if ( $page ) : ?>
							<span style="color: green;"><?php echo $this->get_check_icon( true ); ?>Page exists</span>
							<?php if ( strpos( $page->post_content, '[brag_book_gallery' ) !== false ) : ?>
								<span style="color: green;"><?php echo $this->get_check_icon( true ); ?>Contains shortcode</span>
							<?php else : ?>
								<span style="color: red;"><?php echo $this->get_check_icon( false ); ?>Missing shortcode</span>
							<?php endif; ?>
						<?php else : ?>
							<span style="color: red;"><?php echo $this->get_check_icon( false ); ?>Page not found</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render test URLs
	 *
	 * @return void
	 */
	private function render_test_urls(): void {
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();

		if ( ! $brag_book_gallery_page_slug ) {
			echo '<p>' . esc_html__( 'Please set the combine gallery slug first.', 'brag-book-gallery' ) . '</p>';
			return;
		}

		// Ensure slug is never null for PHP 8.2 compatibility
		$slug = $brag_book_gallery_page_slug ?? '';
		$test_urls = [
			home_url( '/' . $slug . '/' ),
			home_url( '/' . $slug . '/tummy-tuck/' ),
			home_url( '/' . $slug . '/tummy-tuck/12345/' ),
		];
		?>
		<p><?php esc_html_e( 'Click to test these URLs:', 'brag-book-gallery' ); ?></p>
		<ul>
			<?php foreach ( $test_urls as $url ) : ?>
			<li>
				<a href="<?php echo esc_url( $url ); ?>" target="_blank">
					<?php echo esc_html( $url ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render manual instructions
	 *
	 * @return void
	 */
	private function render_manual_instructions(): void {
		// Use Slug_Helper to properly handle array/string format
		$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id' );
		?>
		<div class="manual-instructions">
			<h4><?php esc_html_e( 'For Apache servers:', 'brag-book-gallery' ); ?></h4>
			<p><?php esc_html_e( 'Ensure mod_rewrite is enabled in your hosting control panel.', 'brag-book-gallery' ); ?></p>

			<h4><?php esc_html_e( 'For Nginx servers:', 'brag-book-gallery' ); ?></h4>
			<p><?php esc_html_e( 'Add these rules to your nginx.conf:', 'brag-book-gallery' ); ?></p>
			<?php if ( $brag_book_gallery_page_slug ) : ?>
			<pre style="background: #f5f5f5; padding: 10px;">location ~ ^/<?php echo esc_html( $brag_book_gallery_page_slug ); ?>/([^/]+)/([0-9]+)/?$ {
    try_files $uri $uri/ /index.php?page_id=<?php echo esc_html( $brag_book_gallery_page_id ?: '[PAGE_ID]' ); ?>&procedure_title=$1&case_id=$2;
}

location ~ ^/<?php echo esc_html( $brag_book_gallery_page_slug ); ?>/([^/]+)/?$ {
    try_files $uri $uri/ /index.php?page_id=<?php echo esc_html( $brag_book_gallery_page_id ?: '[PAGE_ID]' ); ?>&filter_procedure=$1;
}</pre>
			<?php else : ?>
			<p><?php esc_html_e( 'Set brag_book_gallery_page_slug option first', 'brag-book-gallery' ); ?></p>
			<?php endif; ?>

			<h4><?php esc_html_e( 'For WP Engine:', 'brag-book-gallery' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Contact WP Engine support to add custom rewrite rules', 'brag-book-gallery' ); ?></li>
				<li><?php esc_html_e( 'Clear all caches from WP Engine dashboard', 'brag-book-gallery' ); ?></li>
				<li><?php esc_html_e( 'Use the flush tool after rules are added', 'brag-book-gallery' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'General steps:', 'brag-book-gallery' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Clear all caches (CloudFlare, hosting cache, WordPress cache plugins)', 'brag-book-gallery' ); ?></li>
				<li><?php esc_html_e( 'Check hosting restrictions - some hosts block custom rewrite rules', 'brag-book-gallery' ); ?></li>
				<li><?php esc_html_e( 'Ensure permalink structure is not "Plain"', 'brag-book-gallery' ); ?></li>
			</ol>
		</div>
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
			case 'check_htaccess':
				return $this->check_htaccess();

			case 'check_rules':
				return $this->check_rules();

			case 'apply_fixes':
				return $this->apply_fixes();

			default:
				throw new \Exception( 'Invalid action' );
		}
	}

	/**
	 * Check .htaccess status
	 *
	 * @return string
	 */
	private function check_htaccess(): string {
		$output = '<div class="htaccess-check">';

		// WP Engine doesn't use .htaccess
		if ( defined( 'WPE_APIKEY' ) ) {
			$output .= '<p style="color: orange;">' . $this->get_warning_icon() . 'WP Engine detected - .htaccess is not used. Rewrite rules are handled differently.</p>';
			return $output . '</div>';
		}

		$htaccess_path = ABSPATH . '.htaccess';

		if ( file_exists( $htaccess_path ) ) {
			$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . '.htaccess file exists</p>';

			$htaccess_content = file_get_contents( $htaccess_path );
			if ( strpos( $htaccess_content, 'BEGIN WordPress' ) !== false ) {
				$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . 'WordPress rules found in .htaccess</p>';
			} else {
				$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . 'WordPress rules NOT found in .htaccess</p>';
			}

			if ( is_writable( $htaccess_path ) ) {
				$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . '.htaccess is writable</p>';
			} else {
				$output .= '<p style="color: orange;">' . $this->get_warning_icon() . '.htaccess is NOT writable</p>';
			}

			$output .= '<details>';
			$output .= '<summary>View .htaccess content (click to expand)</summary>';
			$output .= '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
			$output .= htmlspecialchars( $htaccess_content );
			$output .= '</pre>';
			$output .= '</details>';
		} else {
			$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . '.htaccess file does NOT exist!</p>';
			$output .= '<p>WordPress needs this file for custom URLs when using Apache.</p>';
		}

		// Check mod_rewrite
		if ( stripos( $_SERVER['SERVER_SOFTWARE'] ?? '', 'apache' ) !== false ) {
			$output .= '<h4>Apache Checks:</h4>';
			if ( function_exists( 'apache_get_modules' ) ) {
				$modules = apache_get_modules();
				if ( in_array( 'mod_rewrite', $modules, true ) ) {
					$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . 'mod_rewrite is enabled</p>';
				} else {
					$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . 'mod_rewrite is NOT enabled</p>';
				}
			} else {
				$output .= '<p style="color: orange;">' . $this->get_warning_icon() . 'Cannot check mod_rewrite status</p>';
			}
		}

		$output .= '</div>';
		return $output;
	}

	/**
	 * Check rewrite rules status
	 *
	 * @return string
	 */
	private function check_rules(): string {
		global $wp_rewrite;

		$output = '<div class="rules-check">';
		$rules  = $wp_rewrite->wp_rewrite_rules();

		if ( empty( $rules ) ) {
			$output .= '<p style="color: red;">' . $this->get_check_icon( false ) . 'No rewrite rules found! This is a problem.</p>';
		} else {
			$output .= '<p>Found ' . count( $rules ) . ' total rewrite rules.</p>';

			// Use Slug_Helper to properly handle array/string format
			$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
			$found_gallery_rules  = false;

			$output .= '<h4>Gallery-specific rules:</h4>';
			$output .= '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';

			foreach ( $rules as $pattern => $query ) {
				if ( $brag_book_gallery_page_slug && strpos( $pattern, $brag_book_gallery_page_slug ) !== false ) {
					$output .= htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
					$found_gallery_rules = true;
				}
			}

			if ( ! $found_gallery_rules ) {
				$output .= 'No rules found for slug: ' . esc_html( $brag_book_gallery_page_slug );
			}

			$output .= '</pre>';
		}

		$output .= '</div>';
		return $output;
	}

	/**
	 * Apply fixes
	 *
	 * @return string
	 */
	private function apply_fixes(): string {
		$results = [];

		// 1. Ensure query vars are registered
		global $wp;
		$query_vars = [ 'procedure_title', 'case_id', 'filter_procedure', 'filter_category' ];
		foreach ( $query_vars as $var ) {
			$wp->add_query_var( $var );
		}
		$results[] = $this->get_check_icon( true ) . 'Query vars registered';

		// 2. Force add rewrite rules
		if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
			\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
			$results[] = $this->get_check_icon( true ) . 'Custom rewrite rules added';
		}

		// 3. Flush rewrite rules
		flush_rewrite_rules( true );
		$results[] = $this->get_check_icon( true ) . 'Rewrite rules flushed';

		// 4. Update .htaccess if needed (not on WP Engine)
		if ( ! defined( 'WPE_APIKEY' ) ) {
			$htaccess_path = ABSPATH . '.htaccess';
			if ( ! file_exists( $htaccess_path ) || ! strpos( file_get_contents( $htaccess_path ), 'BEGIN WordPress' ) ) {
				if ( function_exists( 'save_mod_rewrite_rules' ) ) {
					save_mod_rewrite_rules();
					$results[] = $this->get_check_icon( true ) . '.htaccess updated';
				}
			}
		}

		return implode( '<br>', $results ) . '<br><br><strong>Fixes applied! Test your URLs now.</strong>';
	}
}
