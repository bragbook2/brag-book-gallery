<?php
/**
 * Changelog Settings Class
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin
 * @since      3.2.4
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Pages;

use BRAGBookGallery\Includes\Admin\Core\Settings_Base;
use BRAGBookGallery\Includes\Core\Setup;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Changelog Settings Class
 *
 * Renders CHANGELOG.md, which is the single source of truth for the version
 * history. This page previously carried its own hand-maintained copy of the
 * same list, which drifted from the file every release.
 *
 * @since 3.2.4
 */
class Changelog_Page extends Settings_Base {

	/**
	 * Changelog file name, relative to the plugin root.
	 *
	 * @since 4.9.2
	 * @var string
	 */
	private const CHANGELOG_FILE = 'CHANGELOG.md';

	/**
	 * Initialize the settings page
	 *
	 * @since 3.2.4
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug = 'brag-book-gallery-changelog';
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.2.4
	 * @return void
	 */
	public function render(): void {
		// Set translated strings when rendering
		$this->page_title = __( 'Changelog & Version History', 'brag-book-gallery' );
		$this->menu_title = __( 'Changelog', 'brag-book-gallery' );

		$this->render_header();
		?>

		<div class="brag-book-gallery-changelog-content">
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-card">
					<p>
						<strong><?php esc_html_e( 'Current Version:', 'brag-book-gallery' ); ?></strong>
						<?php echo esc_html( $this->get_plugin_version() ); ?>
					</p>
					<p>
						<a href="https://github.com/bragbook2/brag-book-gallery/releases" target="_blank" rel="noopener noreferrer" class="button button-primary">
							<?php esc_html_e( 'View GitHub Releases', 'brag-book-gallery' ); ?>
						</a>
					</p>
				</div>
			</div>

			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-card">
					<?php echo wp_kses_post( $this->render_changelog() ); ?>
				</div>
			</div>
		</div>

		<?php
		$this->render_footer();
	}

	/**
	 * Read the plugin version from its own header.
	 *
	 * @since 4.9.2
	 * @return string Version string, or an empty string if unreadable.
	 */
	private function get_plugin_version(): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = Setup::get_plugin_path() . 'brag-book-gallery.php';

		if ( ! is_readable( $plugin_file ) ) {
			return '';
		}

		$plugin_data = get_plugin_data( $plugin_file, false, false );

		return (string) ( $plugin_data['Version'] ?? '' );
	}

	/**
	 * Convert CHANGELOG.md to the HTML subset this page displays.
	 *
	 * @since 4.9.2
	 * @return string HTML markup, already escaped.
	 */
	private function render_changelog(): string {
		$path = Setup::get_plugin_path() . self::CHANGELOG_FILE;

		if ( ! is_readable( $path ) ) {
			return '<p>' . esc_html__( 'The changelog file could not be read. See the GitHub Releases page for the full version history.', 'brag-book-gallery' ) . '</p>';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file, not a remote request.
		$markdown = file_get_contents( $path );

		if ( false === $markdown ) {
			return '<p>' . esc_html__( 'The changelog file could not be read. See the GitHub Releases page for the full version history.', 'brag-book-gallery' ) . '</p>';
		}

		return self::markdown_to_html( $markdown );
	}

	/**
	 * Minimal Markdown renderer covering what CHANGELOG.md actually uses.
	 *
	 * Handles ATX headings, unordered lists, bold, inline code and links. Input
	 * is escaped before any markup is added, so nothing in the file can inject
	 * HTML.
	 *
	 * ponytail: deliberately not a Markdown parser — no tables, block quotes,
	 * fenced code or nested lists. Pull in a real parser if the file starts
	 * using them; do not grow this function.
	 *
	 * @since 4.9.2
	 *
	 * @param string $markdown Raw Markdown source.
	 *
	 * @return string HTML markup.
	 */
	private static function markdown_to_html( string $markdown ): string {
		$html      = '';
		$in_list   = false;
		$paragraph = '';

		foreach ( explode( "\n", $markdown ) as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				$html     .= self::close_blocks( $paragraph, $in_list );
				$paragraph = '';
				$in_list   = false;
				continue;
			}

			// Headings: # through ####.
			if ( preg_match( '/^(#{1,4})\s+(.*)$/', $trimmed, $matches ) ) {
				$html     .= self::close_blocks( $paragraph, $in_list );
				$paragraph = '';
				$in_list   = false;

				// Offset by one so the page's own <h1> stays the only h1.
				$level = min( 6, strlen( $matches[1] ) + 1 );
				$html .= sprintf( '<h%1$d>%2$s</h%1$d>', $level, self::inline_markdown( $matches[2] ) );
				continue;
			}

			// Unordered list items: - or *.
			if ( preg_match( '/^[-*]\s+(.*)$/', $trimmed, $matches ) ) {
				if ( '' !== $paragraph ) {
					$html     .= '<p>' . $paragraph . '</p>';
					$paragraph = '';
				}

				if ( ! $in_list ) {
					$html   .= '<ul>';
					$in_list = true;
				}

				$html .= '<li>' . self::inline_markdown( $matches[1] ) . '</li>';
				continue;
			}

			// A non-blank line under an open list continues the previous item.
			if ( $in_list ) {
				$continued = preg_replace(
					'/<\/li>$/',
					' ' . self::inline_markdown( $trimmed ) . '</li>',
					$html,
					1
				);
				$html      = $continued ?? $html;
				continue;
			}

			$paragraph .= ( '' === $paragraph ? '' : ' ' ) . self::inline_markdown( $trimmed );
		}

		return $html . self::close_blocks( $paragraph, $in_list );
	}

	/**
	 * Close whichever blocks are currently open.
	 *
	 * @since 4.9.2
	 *
	 * @param string $paragraph Pending paragraph text, or an empty string.
	 * @param bool   $in_list   Whether a list is currently open.
	 *
	 * @return string Closing markup.
	 */
	private static function close_blocks( string $paragraph, bool $in_list ): string {
		$html = '' !== $paragraph ? '<p>' . $paragraph . '</p>' : '';

		return $in_list ? $html . '</ul>' : $html;
	}

	/**
	 * Apply inline Markdown to a single line of untrusted text.
	 *
	 * @since 4.9.2
	 *
	 * @param string $text Raw line content.
	 *
	 * @return string Escaped HTML with inline formatting applied.
	 */
	private static function inline_markdown( string $text ): string {
		// Escape first — everything added after this point is our own markup.
		$escaped = esc_html( $text );

		// Inline code before emphasis, so backticked text is left alone.
		$escaped = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $escaped ) ?? $escaped;

		$escaped = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped ) ?? $escaped;

		// Links: [label](url). esc_url() drops anything that is not a safe scheme.
		$linked = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)\)/',
			static function ( array $matches ): string {
				$url = esc_url( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) );

				if ( '' === $url ) {
					return $matches[1];
				}

				return sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					$url,
					$matches[1]
				);
			},
			$escaped
		);

		return $linked ?? $escaped;
	}
}
