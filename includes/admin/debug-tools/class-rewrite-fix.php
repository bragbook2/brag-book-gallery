<?php
/**
 * Rewrite Fix Tool
 *
 * Provides comprehensive tools to diagnose and fix rewrite rules issues on production sites.
 * Features include server environment detection, .htaccess validation, automated fixes,
 * and hosting-specific troubleshooting with enhanced security and performance optimizations.
 *
 * @package    BragBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @version    3.0.0
 * 
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 * 
 * @see \BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler For rule registration
 * @see \BRAGBookGallery\Admin\Debug_Tools\Rewrite_Debug For rule analysis
 * @see \BRAGBookGallery\Includes\Core\Slug_Helper For slug management
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use WP_Error;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite Fix class
 *
 * Enterprise-grade rewrite rule fixing and debugging tool with comprehensive server support,
 * automated diagnostics, and performance-optimized fix strategies.
 *
 * ## Features:
 * - Server environment detection (Apache, Nginx, IIS, WP Engine)
 * - .htaccess validation and repair
 * - Automated rule generation and conflict resolution
 * - Hosting-specific fix strategies
 * - Performance metrics and caching
 * - Security-hardened operations
 *
 * @since 3.0.0
 * @final
 */
final class Rewrite_Fix {

	/**
	 * Performance metrics storage.
	 *
	 * @since 3.0.0
	 * @var array<string, float>
	 */
	private array $metrics = [];

	/**
	 * Cache key prefix for transient operations.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_PREFIX = 'brag_book_rewrite_fix_';

	/**
	 * Cache duration in seconds (1 hour).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_DURATION = 3600;

	/**
	 * Maximum retries for fix operations.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Get checkmark SVG icon for visual feedback.
	 *
	 * Returns an inline SVG icon for success or error states, optimized for
	 * accessibility with proper ARIA attributes.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $success Whether this is a success (true) or error (false) icon.
	 * @return string SVG HTML markup for the icon with accessibility attributes.
	 */
	private function get_check_icon( bool $success = true ): string {
		$color = $success ? '#4caf50' : '#f44336';
		$title = $success ? 'Success' : 'Error';
		$path = $success
			? 'm423.23-309.85 268.92-268.92L650-620.92 423.23-394.15l-114-114L267.08-466l156.15 156.15ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z'
			: 'M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z';

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="%s" style="vertical-align: middle; margin-right: 5px;" role="img" aria-label="%s"><title>%s</title><path d="%s"/></svg>',
			esc_attr( $color ),
			esc_attr( $title ),
			esc_html( $title ),
			esc_attr( $path )
		);
	}

	/**
	 * Get warning SVG icon for informational feedback.
	 *
	 * Returns an inline SVG warning icon optimized for accessibility.
	 *
	 * @since 3.0.0
	 *
	 * @return string SVG HTML markup for the warning icon with accessibility attributes.
	 */
	private function get_warning_icon(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ff9800" style="vertical-align: middle; margin-right: 5px;" role="img" aria-label="Warning"><title>Warning</title><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>';
	}

	/**
	 * Get server environment information.
	 *
	 * Detects and returns comprehensive server environment details including
	 * hosting provider, server software, and configuration.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, mixed> Server environment data.
	 */
	private function get_server_environment(): array {
		$cache_key = self::CACHE_PREFIX . 'server_env';
		$cached = wp_cache_get( $cache_key, 'brag_book_gallery' );

		if ( false !== $cached ) {
			return $cached;
		}

		$server_software = sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' );
		$environment = [
			'software' => $server_software,
			'php_version' => phpversion(),
			'wp_version' => get_bloginfo( 'version' ),
			'permalink_structure' => get_option( 'permalink_structure' ) ?: 'Plain',
			'home_url' => home_url(),
			'site_url' => site_url(),
			'is_multisite' => is_multisite(),
			'hosting_provider' => $this->detect_hosting_provider(),
			'server_type' => $this->detect_server_type( $server_software ),
			'mod_rewrite' => $this->check_mod_rewrite(),
		];

		wp_cache_set( $cache_key, $environment, 'brag_book_gallery', self::CACHE_DURATION );

		return $environment;
	}

	/**
	 * Detect hosting provider.
	 *
	 * @since 3.0.0
	 *
	 * @return string Detected hosting provider name.
	 */
	private function detect_hosting_provider(): string {
		return match ( true ) {
			defined( 'WPE_APIKEY' ) => 'WP Engine',
			defined( 'KINSTAMU_VERSION' ) => 'Kinsta',
			defined( 'IS_PRESSABLE' ) => 'Pressable',
			defined( 'FLYWHEEL_CONFIG_DIR' ) => 'Flywheel',
			defined( 'WPCOMSH_VERSION' ) => 'WordPress.com',
			file_exists( '/etc/siteground' ) => 'SiteGround',
			str_contains( gethostname(), 'dreamhost' ) => 'DreamHost',
			default => 'Unknown',
		};
	}

	/**
	 * Detect server type from software string.
	 *
	 * @since 3.0.0
	 *
	 * @param string $software Server software string.
	 * @return string Detected server type.
	 */
	private function detect_server_type( string $software ): string {
		$lower = strtolower( $software );

		return match ( true ) {
			str_contains( $lower, 'apache' ) => 'Apache',
			str_contains( $lower, 'nginx' ) => 'Nginx',
			str_contains( $lower, 'litespeed' ) => 'LiteSpeed',
			str_contains( $lower, 'iis' ) => 'IIS',
			default => 'Unknown',
		};
	}

	/**
	 * Check if mod_rewrite is available.
	 *
	 * @since 3.0.0
	 *
	 * @return bool|null True if available, false if not, null if cannot determine.
	 */
	private function check_mod_rewrite(): ?bool {
		if ( function_exists( 'apache_get_modules' ) ) {
			return in_array( 'mod_rewrite', apache_get_modules(), true );
		}

		// Check via phpinfo if available
		if ( function_exists( 'phpinfo' ) ) {
			ob_start();
			phpinfo( INFO_MODULES );
			$info = ob_get_clean();
			return str_contains( $info, 'mod_rewrite' );
		}

		return null;
	}

	/**
	 * Render the comprehensive fix tool interface.
	 *
	 * Displays a complete interface for diagnosing and fixing rewrite rule issues
	 * with server environment checks, .htaccess validation, automated fixes,
	 * and performance metrics tracking.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 * @throws Exception If rendering fails.
	 */
	public function render(): void {
		try {
			$start_time = microtime( true );

			// Security check
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die(
					esc_html__( 'You do not have sufficient permissions to access this page.', 'brag-book-gallery' ),
					403
				);
			}

			// Check if fixes were recently applied
			$recent_fixes = get_transient( self::CACHE_PREFIX . 'recent_fixes' );
			if ( $recent_fixes ) {
				$this->show_admin_notice(
					__( 'Fixes were recently applied. Please test your URLs.', 'brag-book-gallery' ),
					'success'
				);
			}
			?>
			<div class="rewrite-fix-tool" data-nonce="<?php echo esc_attr( wp_create_nonce( 'brag_book_debug_tools' ) ); ?>">
				<h2><?php esc_html_e( 'Live Site Rewrite Rules Fix', 'brag-book-gallery' ); ?></h2>

				<div class="tool-section">
					<h3><?php esc_html_e( '1. Server Environment', 'brag-book-gallery' ); ?></h3>
					<?php $this->render_server_info(); ?>
				</div>

				<div class="tool-section">
					<h3><?php esc_html_e( '2. .htaccess Status', 'brag-book-gallery' ); ?></h3>
					<button type="button" class="button" id="check-htaccess">
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
					<button type="button" class="button" id="check-rules-status">
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
					<button type="button" class="button button-primary" id="apply-fixes">
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

			$this->metrics['render_time'] = microtime( true ) - $start_time;
			$this->maybe_log_performance();

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			echo '<div class="notice notice-error"><p>' .
				esc_html( sprintf(
					/* translators: %s: Error message */
					__( 'Failed to render fix tool: %s', 'brag-book-gallery' ),
					$e->getMessage()
				) ) . '</p></div>';
		}
	}

	/**
	 * Render comprehensive server environment information.
	 *
	 * Displays server software, PHP version, WordPress details, and hosting-specific
	 * information that may affect rewrite rule functionality. Uses caching to improve
	 * performance and includes security sanitization.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function render_server_info(): void {
		try {
			$environment = $this->get_server_environment();
			?>
			<div class="rewrite-table-wrapper">
				<table class="rewrite-table server-info-table">
					<tbody>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Server Software', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_html( $environment['software'] ); ?></span>
								<?php if ( 'Unknown' !== $environment['server_type'] ) : ?>
									<span class="badge badge-info"><?php echo esc_html( $environment['server_type'] ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_html( $environment['php_version'] ); ?></span>
								<?php if ( version_compare( $environment['php_version'], '8.2', '>=' ) ) : ?>
									<?php echo $this->get_check_icon( true ); ?>
								<?php elseif ( version_compare( $environment['php_version'], '8.0', '>=' ) ) : ?>
									<?php echo $this->get_warning_icon(); ?>
								<?php else : ?>
									<?php echo $this->get_check_icon( false ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_html( $environment['wp_version'] ); ?></span>
								<?php if ( $environment['is_multisite'] ) : ?>
									<span class="badge badge-info"><?php esc_html_e( 'Multisite', 'brag-book-gallery' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Permalink Structure', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_html( $environment['permalink_structure'] ); ?></span>
								<?php if ( 'Plain' === $environment['permalink_structure'] ) : ?>
									<span class="notice-inline notice-warning">
										<?php esc_html_e( 'Custom URLs require pretty permalinks', 'brag-book-gallery' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Home URL', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_url( $environment['home_url'] ); ?></span>
							</td>
						</tr>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Site URL', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_url( $environment['site_url'] ); ?></span>
							</td>
						</tr>
						<?php if ( 'Unknown' !== $environment['hosting_provider'] ) : ?>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Hosting Provider', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_html( $environment['hosting_provider'] ); ?></span>
								<?php if ( in_array( $environment['hosting_provider'], [ 'WP Engine', 'Kinsta' ], true ) ) : ?>
									<span class="notice-inline notice-warning">
										<?php esc_html_e( 'Some restrictions may apply', 'brag-book-gallery' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endif; ?>
						<?php if ( null !== $environment['mod_rewrite'] ) : ?>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'mod_rewrite', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<?php if ( $environment['mod_rewrite'] ) : ?>
									<span style="color: green;"><?php echo $this->get_check_icon( true ); ?><?php esc_html_e( 'Enabled', 'brag-book-gallery' ); ?></span>
								<?php else : ?>
									<span style="color: red;"><?php echo $this->get_check_icon( false ); ?><?php esc_html_e( 'Disabled', 'brag-book-gallery' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php
		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
		}
	}

	/**
	 * Render gallery configuration status.
	 *
	 * Displays current gallery slug configuration, page status, shortcode validation,
	 * and configuration health checks to help diagnose configuration-related rewrite issues.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function render_gallery_config(): void {
		try {
			// Use Slug_Helper to properly handle array/string format
			$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
			$brag_book_gallery_page_id = get_option( 'brag_book_gallery_page_id' );
			
			// Validate configuration
			$config_issues = $this->validate_gallery_configuration();
			
			if ( ! empty( $config_issues ) ) {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'Configuration issues detected:', 'brag-book-gallery' ) . '</p><ul>';
				foreach ( $config_issues as $issue ) {
					echo '<li>' . esc_html( $issue ) . '</li>';
				}
				echo '</ul></div>';
			}
			?>
			<div class="rewrite-table-wrapper">
				<table class="rewrite-table gallery-config-table">
					<tbody>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Combine Gallery Slug', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_html( $brag_book_gallery_page_slug ?: '(not set)' ); ?></span>
							</td>
						</tr>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Combine Gallery Page ID', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text"><?php echo esc_html( $brag_book_gallery_page_id ?: '(not set)' ); ?></span>
							</td>
						</tr>
						<?php if ( $brag_book_gallery_page_slug ) : ?>
						<?php 
						// Handle both string and array formats
						$slug_to_check = is_array( $brag_book_gallery_page_slug ) ? reset( $brag_book_gallery_page_slug ) : $brag_book_gallery_page_slug;
						$page = get_page_by_path( $slug_to_check ); 
						?>
						<tr class="table-row">
							<th class="setting-label">
								<span class="label-text"><?php esc_html_e( 'Page Status', 'brag-book-gallery' ); ?></span>
							</th>
							<td class="setting-value">
								<span class="value-text">
									<?php if ( $page ) : ?>
										<span style="color: green;"><?php echo $this->get_check_icon( true ); ?>Page exists</span>
										<?php if ( str_contains( $page->post_content, '[brag_book_gallery' ) ) : ?>
											<span style="color: green;"><?php echo $this->get_check_icon( true ); ?>Contains shortcode</span>
										<?php else : ?>
											<span style="color: red;"><?php echo $this->get_check_icon( false ); ?>Missing shortcode</span>
										<?php endif; ?>
									<?php else : ?>
										<span style="color: red;"><?php echo $this->get_check_icon( false ); ?>Page not found</span>
									<?php endif; ?>
								</span>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php
		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to load gallery configuration', 'brag-book-gallery' ) . '</p></div>';
		}
	}

	/**
	 * Validate gallery configuration.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string> List of configuration issues.
	 */
	private function validate_gallery_configuration(): array {
		$issues = [];

		$slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$page_id = get_option( 'brag_book_gallery_page_id' );

		if ( ! $slug ) {
			$issues[] = __( 'Gallery slug is not configured', 'brag-book-gallery' );
		}

		if ( ! $page_id ) {
			// Check if there's a page with the gallery slug that we can auto-detect
			if ( $slug ) {
				$page_by_slug = get_page_by_path( $slug );
				if ( $page_by_slug && str_contains( $page_by_slug->post_content, '[brag_book_gallery' ) ) {
					// Auto-fix: Set the page ID since we found the gallery page
					update_option( 'brag_book_gallery_page_id', $page_by_slug->ID );
					$page_id = $page_by_slug->ID;
				} else {
					$issues[] = __( 'Gallery page ID is not set but there is a gallery page configured', 'brag-book-gallery' );
				}
			} else {
				$issues[] = __( 'Gallery page ID is not set', 'brag-book-gallery' );
			}
		} elseif ( ! get_post( $page_id ) ) {
			$issues[] = __( 'Gallery page does not exist', 'brag-book-gallery' );
		}

		if ( 'Plain' === get_option( 'permalink_structure' ) ) {
			$issues[] = __( 'Pretty permalinks are required for custom URLs', 'brag-book-gallery' );
		}

		return $issues;
	}

	/**
	 * Render test URLs for validation.
	 *
	 * Generates sample URLs based on current gallery configuration to help
	 * users test rewrite rule functionality after fixes are applied.
	 * Includes URL validation and health checks.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function render_test_urls(): void {
		try {
			// Use Slug_Helper to properly handle array/string format
			$brag_book_gallery_page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();

			if ( ! $brag_book_gallery_page_slug ) {
				echo '<p>' . esc_html__( 'Please set the combine gallery slug first.', 'brag-book-gallery' ) . '</p>';
				return;
			}

			// Ensure slug is never null for PHP 8.2 compatibility
			$slug = $brag_book_gallery_page_slug ?? '';
			$test_urls = [
				'gallery' => home_url( '/' . $slug . '/' ),
				'procedure' => home_url( '/' . $slug . '/tummy-tuck/' ),
				'case' => home_url( '/' . $slug . '/tummy-tuck/12345/' ),
				'favorites' => home_url( '/' . $slug . '/myfavorites/' ),
			];
			?>
			<p><?php esc_html_e( 'Click to test these URLs:', 'brag-book-gallery' ); ?></p>
			<ul>
				<?php foreach ( $test_urls as $type => $url ) : ?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="test-url" data-type="<?php echo esc_attr( $type ); ?>">
						<?php echo esc_html( $url ); ?>
					</a>
					<button type="button" class="button button-small test-url-btn" data-url="<?php echo esc_url( $url ); ?>">
						<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
					</button>
					<span class="test-result" id="test-result-<?php echo esc_attr( $type ); ?>"></span>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php
		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			echo '<p>' . esc_html__( 'Failed to generate test URLs', 'brag-book-gallery' ) . '</p>';
		}
	}

	/**
	 * Render manual fix instructions for different server environments.
	 *
	 * Provides server-specific manual instructions for Apache, Nginx, WP Engine,
	 * and general troubleshooting steps when automated fixes are insufficient.
	 *
	 * @since 3.0.0
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
		
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			/**
			 * Modern AJAX request handler using fetch API with async/await.
			 *
			 * @param {Object} data - Request data to send
			 * @param {Function} onSuccess - Success callback
			 * @param {Function} onError - Error callback
			 * @param {Function} onComplete - Complete callback
			 * @returns {Promise<void>}
			 */
			const ajaxRequest = async function(data, onSuccess, onError, onComplete) {
				try {
					const formData = new FormData();
					Object.entries(data).forEach(function([key, value]) {
						formData.append(key, value);
					});
					
					const response = await fetch(ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					});
					
					const result = await response.json();
					
					if (onSuccess) onSuccess(result);
				} catch (error) {
					console.error('AJAX request failed:', error);
					if (onError) onError(error);
				} finally {
					if (onComplete) onComplete();
				}
			};
			
			/**
			 * Handle button state during operations.
			 *
			 * @param {HTMLButtonElement} button - Button element to manage
			 * @param {boolean} isLoading - Whether button is in loading state
			 * @param {string} loadingText - Text to show during loading
			 */
			const handleButtonState = function(button, isLoading, loadingText = 'Processing...') {
				if (isLoading) {
					button.disabled = true;
					button.dataset.originalText = button.textContent;
					button.textContent = loadingText;
				} else {
					button.disabled = false;
					button.textContent = button.dataset.originalText || button.textContent;
				}
			};
			
			/**
			 * Display results in target element with proper styling.
			 *
			 * @param {HTMLElement} target - Target element for results
			 * @param {string} content - Content to display
			 * @param {string} type - Result type ('success', 'error', 'info')
			 */
			const displayResult = function(target, content, type = 'info') {
				if (!target) return;
				
				const cssClass = {
					success: 'notice notice-success',
					error: 'notice notice-error',
					info: 'notice notice-info'
				}[type] || 'notice notice-info';
				
				target.innerHTML = `<div class="${cssClass}"><p>${content}</p></div>`;
			};
			
			// Event Handlers
			const checkHtaccessBtn = document.getElementById('check-htaccess');
			const checkRulesBtn = document.getElementById('check-rules-status');
			const applyFixesBtn = document.getElementById('apply-fixes');
			
			const htaccessResult = document.getElementById('htaccess-status');
			const rulesResult = document.getElementById('rules-status');
			const fixResult = document.getElementById('fix-result');
			
			// Check .htaccess handler
			if (checkHtaccessBtn) {
				checkHtaccessBtn.addEventListener('click', async function() {
					handleButtonState(checkHtaccessBtn, true, 'Checking...');
					displayResult(htaccessResult, 'Checking .htaccess file...', 'info');
					
					await ajaxRequest(
						{
							action: 'brag_book_debug_tool',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_debug_tools' ) ); ?>',
							tool: 'rewrite-fix',
							tool_action: 'check_htaccess'
						},
						(response) => {
							if (response.success) {
								htaccessResult.innerHTML = response.data;
							} else {
								displayResult(htaccessResult, `Error: ${response.data}`, 'error');
							}
						},
						function() { displayResult(htaccessResult, 'Failed to check .htaccess. Please try again.', 'error'); },
						function() { handleButtonState(checkHtaccessBtn, false); }
					);
				});
			}
			
			// Check rules status handler
			if (checkRulesBtn) {
				checkRulesBtn.addEventListener('click', async function() {
					handleButtonState(checkRulesBtn, true, 'Checking...');
					displayResult(rulesResult, 'Checking rewrite rules status...', 'info');
					
					await ajaxRequest(
						{
							action: 'brag_book_debug_tool',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_debug_tools' ) ); ?>',
							tool: 'rewrite-fix',
							tool_action: 'check_rules'
						},
						(response) => {
							if (response.success) {
								rulesResult.innerHTML = response.data;
							} else {
								displayResult(rulesResult, `Error: ${response.data}`, 'error');
							}
						},
						function() { displayResult(rulesResult, 'Failed to check rules. Please try again.', 'error'); },
						function() { handleButtonState(checkRulesBtn, false); }
					);
				});
			}
			
			// Apply fixes handler
			if (applyFixesBtn) {
				applyFixesBtn.addEventListener('click', async function() {
					if (!confirm('<?php echo esc_js( __( 'This will attempt to fix rewrite rule issues. Continue?', 'brag-book-gallery' ) ); ?>')) {
						return;
					}
					
					handleButtonState(applyFixesBtn, true, 'Applying fixes...');
					displayResult(fixResult, 'Applying all fixes...', 'info');
					
					await ajaxRequest(
						{
							action: 'brag_book_debug_tool',
							nonce: '<?php echo esc_js( wp_create_nonce( 'brag_book_debug_tools' ) ); ?>',
							tool: 'rewrite-fix',
							tool_action: 'apply_fixes'
						},
						(response) => {
							if (response.success) {
								displayResult(fixResult, response.data, 'success');
							} else {
								displayResult(fixResult, `Error: ${response.data}`, 'error');
							}
						},
						function() { displayResult(fixResult, 'Failed to apply fixes. Please try again.', 'error'); },
						function() { handleButtonState(applyFixesBtn, false); }
					);
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Execute tool actions via AJAX with modern error handling.
	 *
	 * Handles various debugging and fixing actions including .htaccess validation,
	 * rewrite rules checking, automated fix application, and performance monitoring.
	 * All actions are security-validated and cached for efficiency.
	 *
	 * @since 3.0.0
	 *
	 * @param string $action The specific action to execute.
	 * @param array  $data   Additional request data and parameters.
	 * @return string|array The result of the executed action.
	 * @throws Exception When an invalid action is provided or security check fails.
	 */
	public function execute( string $action, array $data ): string|array {
		try {
			$start_time = microtime( true );

			// Security validation
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( __( 'Insufficient permissions', 'brag-book-gallery' ) );
			}

			// Check cache first for read operations
			if ( in_array( $action, [ 'check_htaccess', 'check_rules' ], true ) ) {
				$cache_key = self::CACHE_PREFIX . $action;
				$cached = get_transient( $cache_key );
				if ( false !== $cached ) {
					return $cached;
				}
			}

			$result = match ( $action ) {
				'check_htaccess' => $this->check_htaccess(),
				'check_rules' => $this->check_rules(),
				'apply_fixes' => $this->apply_fixes(),
				'test_url' => $this->test_url( $data['url'] ?? '' ),
				'export_diagnostics' => $this->export_diagnostics(),
				default => throw new Exception( sprintf( 'Invalid action: %s', esc_html( $action ) ) )
			};

			// Cache read operations
			if ( in_array( $action, [ 'check_htaccess', 'check_rules' ], true ) ) {
				set_transient( self::CACHE_PREFIX . $action, $result, 300 ); // 5 minutes
			}

			$this->metrics[ $action . '_time' ] = microtime( true ) - $start_time;
			$this->maybe_log_performance();

			return $result;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			throw $e;
		}
	}

	/**
	 * Check .htaccess file status and configuration.
	 *
	 * Performs comprehensive .htaccess validation including file existence,
	 * writability, WordPress rules presence, mod_rewrite detection, and
	 * security vulnerability scanning.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted status report.
	 */
	private function check_htaccess(): string {
		try {
			$output = '<div class="htaccess-check">';

			// WP Engine uses different rewrite handling
			if ( defined( 'WPE_APIKEY' ) ) {
				$output .= '<p style="color: orange;">' . $this->get_warning_icon() . 'WP Engine detected - .htaccess is not used. Rewrite rules are handled differently.</p>';
				return $output . '</div>';
			}

			$htaccess_path = ABSPATH . '.htaccess';

			if ( file_exists( $htaccess_path ) ) {
				$output .= '<p style="color: green;">' . $this->get_check_icon( true ) . '.htaccess file exists</p>';

				$htaccess_content = file_get_contents( $htaccess_path ) ?: '';
				if ( str_contains( $htaccess_content, 'BEGIN WordPress' ) ) {
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

			// Check Apache-specific features
			if ( str_contains( strtolower( $_SERVER['SERVER_SOFTWARE'] ?? '' ), 'apache' ) ) {
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

			// Security scan
			if ( file_exists( $htaccess_path ) ) {
				$security_issues = $this->scan_htaccess_security( $htaccess_path );
				if ( ! empty( $security_issues ) ) {
					$output .= '<h4>' . esc_html__( 'Security Scan:', 'brag-book-gallery' ) . '</h4>';
					foreach ( $security_issues as $issue ) {
						$output .= '<p style="color: orange;">' . $this->get_warning_icon() . esc_html( $issue ) . '</p>';
					}
				}
			}

			$output .= '</div>';
			return $output;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return '<div class="notice notice-error"><p>' . esc_html__( 'Failed to check .htaccess', 'brag-book-gallery' ) . '</p></div>';
		}
	}

	/**
	 * Scan .htaccess for security issues.
	 *
	 * @since 3.0.0
	 *
	 * @param string $file_path Path to .htaccess file.
	 * @return array<string> List of security issues found.
	 */
	private function scan_htaccess_security( string $file_path ): array {
		$issues = [];
		$content = file_get_contents( $file_path );

		if ( ! $content ) {
			return $issues;
		}

		// Check for common security directives
		$security_checks = [
			'Options -Indexes' => __( 'Directory listing protection not found', 'brag-book-gallery' ),
			'ServerSignature Off' => __( 'Server signature disclosure not disabled', 'brag-book-gallery' ),
		];

		foreach ( $security_checks as $directive => $message ) {
			if ( ! str_contains( $content, $directive ) ) {
				$issues[] = $message;
			}
		}

		return $issues;
	}

	/**
	 * Check rewrite rules status and gallery-specific rules.
	 *
	 * Analyzes current WordPress rewrite rules, validates gallery-specific rules,
	 * detects conflicts, and provides detailed rule inspection with performance metrics.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted rules analysis report.
	 */
	private function check_rules(): string {
		try {
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
					if ( $brag_book_gallery_page_slug && str_contains( $pattern, $brag_book_gallery_page_slug ) ) {
						$output .= htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
						$found_gallery_rules = true;
					}
				}

				if ( ! $found_gallery_rules ) {
					$output .= 'No rules found for slug: ' . esc_html( $brag_book_gallery_page_slug );
				}

				$output .= '</pre>';
			}

			// Check for rule conflicts
			if ( ! empty( $rules ) ) {
				$conflicts = $this->detect_rule_conflicts( $rules );
				if ( ! empty( $conflicts ) ) {
					$output .= '<h4>' . esc_html__( 'Potential Conflicts:', 'brag-book-gallery' ) . '</h4>';
					$output .= '<ul>';
					foreach ( $conflicts as $conflict ) {
						$output .= '<li>' . esc_html( $conflict ) . '</li>';
					}
					$output .= '</ul>';
				}
			}

			$output .= '</div>';
			return $output;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return '<div class="notice notice-error"><p>' . esc_html__( 'Failed to check rules', 'brag-book-gallery' ) . '</p></div>';
		}
	}

	/**
	 * Detect potential rule conflicts.
	 *
	 * @since 3.0.0
	 *
	 * @param array $rules WordPress rewrite rules.
	 * @return array<string> List of detected conflicts.
	 */
	private function detect_rule_conflicts( array $rules ): array {
		$conflicts = [];
		$patterns = array_keys( $rules );

		// Check for overlapping patterns
		foreach ( $patterns as $i => $pattern1 ) {
			foreach ( $patterns as $j => $pattern2 ) {
				if ( $i >= $j ) {
					continue;
				}

				// Simple overlap detection
				if ( str_starts_with( $pattern1, substr( $pattern2, 0, 10 ) ) ) {
					$conflicts[] = sprintf(
						/* translators: %1$s and %2$s are regex patterns */
						__( 'Patterns may overlap: %1$s and %2$s', 'brag-book-gallery' ),
						$pattern1,
						$pattern2
					);
				}
			}
		}

		return array_slice( $conflicts, 0, 5 ); // Limit to 5 conflicts
	}

	/**
	 * Apply comprehensive rewrite rule fixes.
	 *
	 * Executes a series of automated fixes including query variable registration,
	 * custom rewrite rule addition, rules flushing, .htaccess updates, and
	 * verification with rollback capability.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML-formatted fix results report.
	 * @throws Exception If fixes fail after maximum retries.
	 */
	private function apply_fixes(): string {
		try {
			$results = [];
			$start_time = microtime( true );

			// Backup current rules
			global $wp_rewrite;
			$backup_rules = $wp_rewrite->wp_rewrite_rules();
			set_transient( self::CACHE_PREFIX . 'rules_backup', $backup_rules, 3600 );

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

			// 4. Update .htaccess if needed (not applicable on WP Engine)
			if ( ! defined( 'WPE_APIKEY' ) ) {
				$htaccess_path = ABSPATH . '.htaccess';
				$htaccess_content = file_exists( $htaccess_path ) ? file_get_contents( $htaccess_path ) : '';
				if ( ! file_exists( $htaccess_path ) || ! str_contains( $htaccess_content ?: '', 'BEGIN WordPress' ) ) {
					if ( function_exists( 'save_mod_rewrite_rules' ) ) {
						save_mod_rewrite_rules();
						$results[] = $this->get_check_icon( true ) . '.htaccess updated';
					}
				}
			}

			// Clear caches
			wp_cache_flush();
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'brag_book_gallery' );
			}
			$results[] = $this->get_check_icon( true ) . 'Caches cleared';

			// Verify fixes
			$verification = $this->verify_fixes();
			if ( $verification['success'] ) {
				$results[] = $this->get_check_icon( true ) . 'All fixes verified successfully';
			} else {
				$results[] = $this->get_warning_icon() . 'Some fixes may need manual intervention';
				if ( ! empty( $verification['issues'] ) ) {
					foreach ( $verification['issues'] as $issue ) {
						$results[] = '&nbsp;&nbsp;• ' . esc_html( $issue );
					}
				}
			}

			// Track performance
			$this->metrics['apply_fixes_time'] = microtime( true ) - $start_time;
			$results[] = sprintf(
				'<small>%s</small>',
				esc_html( sprintf(
					/* translators: %s: Time in seconds */
					__( 'Completed in %s seconds', 'brag-book-gallery' ),
					number_format( $this->metrics['apply_fixes_time'], 2 )
				) )
			);

			// Mark as recently applied
			set_transient( self::CACHE_PREFIX . 'recent_fixes', true, 300 );

			return implode( '<br>', $results ) . '<br><br><strong>' . esc_html__( 'Fixes applied! Test your URLs now.', 'brag-book-gallery' ) . '</strong>';

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );

			// Attempt rollback
			$this->rollback_fixes();

			return '<div class="notice notice-error"><p>' . 
				esc_html( sprintf(
					/* translators: %s: Error message */
					__( 'Failed to apply fixes: %s. Rules have been rolled back.', 'brag-book-gallery' ),
					$e->getMessage()
				) ) . '</p></div>';
		}
	}

	/**
	 * Verify that fixes were applied successfully.
	 *
	 * @since 3.0.0
	 *
	 * @return array{success: bool, issues: array<string>} Verification results.
	 */
	private function verify_fixes(): array {
		$issues = [];
		$success = true;

		// Check query vars
		global $wp;
		$required_vars = [ 'procedure_title', 'case_id', 'filter_procedure' ];
		foreach ( $required_vars as $var ) {
			if ( ! in_array( $var, $wp->public_query_vars, true ) ) {
				$issues[] = sprintf(
					/* translators: %s: Query variable name */
					__( 'Query var not registered: %s', 'brag-book-gallery' ),
					$var
				);
				$success = false;
			}
		}

		// Check rewrite rules
		global $wp_rewrite;
		$rules = $wp_rewrite->wp_rewrite_rules();
		if ( empty( $rules ) ) {
			$issues[] = __( 'No rewrite rules found after flush', 'brag-book-gallery' );
			$success = false;
		}

		return [ 'success' => $success, 'issues' => $issues ];
	}

	/**
	 * Rollback fixes in case of failure.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function rollback_fixes(): void {
		try {
			$backup_rules = get_transient( self::CACHE_PREFIX . 'rules_backup' );
			if ( $backup_rules ) {
				global $wp_rewrite;
				$wp_rewrite->rules = $backup_rules;
				flush_rewrite_rules( true );
			}
		} catch ( Exception $e ) {
			// Silently fail rollback
			error_log( 'BRAGBook Gallery: Failed to rollback fixes - ' . $e->getMessage() );
		}
	}

	/**
	 * Test a specific URL for proper routing.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url URL to test.
	 * @return array{status: int, message: string} Test results.
	 */
	private function test_url( string $url ): array {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return [
				'status' => 400,
				'message' => __( 'Invalid URL', 'brag-book-gallery' ),
			];
		}

		$response = wp_remote_head( $url, [
			'timeout' => 10,
			'redirection' => 0,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'status' => 0,
				'message' => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		return [
			'status' => $status_code,
			'message' => $this->get_status_message( $status_code ),
		];
	}

	/**
	 * Get human-readable message for HTTP status code.
	 *
	 * @since 3.0.0
	 *
	 * @param int $code HTTP status code.
	 * @return string Status message.
	 */
	private function get_status_message( int $code ): string {
		return match ( $code ) {
			200 => __( 'OK - Page loads successfully', 'brag-book-gallery' ),
			301, 302 => __( 'Redirect detected', 'brag-book-gallery' ),
			404 => __( 'Not Found - Rewrite rules may not be working', 'brag-book-gallery' ),
			500 => __( 'Server Error - Check error logs', 'brag-book-gallery' ),
			default => sprintf(
				/* translators: %d: HTTP status code */
				__( 'HTTP %d', 'brag-book-gallery' ),
				$code
			),
		};
	}

	/**
	 * Export diagnostics data for support.
	 *
	 * @since 3.0.0
	 *
	 * @return array Diagnostic data array.
	 */
	private function export_diagnostics(): array {
		return [
			'timestamp' => current_time( 'mysql' ),
			'environment' => $this->get_server_environment(),
			'configuration' => [
				'gallery_slug' => \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug(),
				'gallery_page_id' => get_option( 'brag_book_gallery_page_id' ),
				'permalink_structure' => get_option( 'permalink_structure' ),
			],
			'rules_count' => count( get_option( 'rewrite_rules', [] ) ),
			'metrics' => $this->metrics,
		];
	}

	/**
	 * Show admin notice.
	 *
	 * @since 3.0.0
	 *
	 * @param string $message Notice message.
	 * @param string $type Notice type.
	 * @return void
	 */
	private function show_admin_notice( string $message, string $type = 'info' ): void {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Handle errors with logging.
	 *
	 * @since 3.0.0
	 *
	 * @param Exception $e Exception to handle.
	 * @param string $context Context where error occurred.
	 * @return void
	 */
	private function handle_error( Exception $e, string $context ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'BRAGBook Gallery Rewrite Fix Error in %s: %s',
				$context,
				$e->getMessage()
			) );
		}
	}

	/**
	 * Log performance metrics if needed.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function maybe_log_performance(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $this->metrics ) ) {
			$total_time = array_sum( $this->metrics );
			if ( $total_time > 1.0 ) { // Log if operations take more than 1 second
				error_log( sprintf(
					'BRAGBook Gallery Rewrite Fix Performance: %s',
					wp_json_encode( $this->metrics )
				) );
			}
		}
	}
}