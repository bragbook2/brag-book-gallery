<?php
/**
 * Self-check for Changelog_Page::markdown_to_html().
 *
 * Runs the private renderer against the real CHANGELOG.md plus a set of
 * injection payloads. No framework — run from the plugin root with:
 *
 *     PLUGIN_DIR="$PWD" php tests/unit/test-changelog-markdown.php
 */

namespace {
	define( 'WPINC', 1 );
	define( 'ABSPATH', __DIR__ . '/' );

	// Minimal WordPress stand-ins for the functions the renderer uses.
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
	function esc_html_e( $t, $d = '' ) { echo esc_html( $t ); }
	function __( $t, $d = '' ) { return $t; }
	function esc_url( $u ) {
		$u = trim( (string) $u );
		return preg_match( '#^(https?://|/|\#)#i', $u ) ? htmlspecialchars( $u, ENT_QUOTES, 'UTF-8' ) : '';
	}
	function wp_kses_post( $h ) { return $h; }
}

namespace BRAGBookGallery\Includes\Admin\Core { class Settings_Base { protected $page_slug; protected $page_title; protected $menu_title; protected function init(): void {} protected function render_header(): void {} protected function render_footer(): void {} } }
namespace BRAGBookGallery\Includes\Core { class Setup { public static function get_plugin_path(): string { return getenv( 'PLUGIN_DIR' ) . '/'; } } }

namespace {

require getenv( 'PLUGIN_DIR' ) . '/includes/admin/pages/class-changelog-page.php';

use BRAGBookGallery\Includes\Admin\Pages\Changelog_Page;

$render = function ( string $md ): string {
	$m = new ReflectionMethod( Changelog_Page::class, 'markdown_to_html' );
	$m->setAccessible( true );
	return $m->invoke( null, $md );
};

$failures = 0;
$check = function ( string $label, bool $ok ) use ( &$failures ): void {
	if ( ! $ok ) { $failures++; echo "FAIL: $label\n"; } else { echo "ok:   $label\n"; }
};

// --- Structure -----------------------------------------------------------
$out = $render( "## [4.9.2] - 2026-07-29\n\n### Fixed\n\n- First item\n- Second item\n" );
$check( 'h2 becomes h3 (page keeps the only h1)', str_contains( $out, '<h3>[4.9.2] - 2026-07-29</h3>' ) );
$check( 'h3 becomes h4', str_contains( $out, '<h4>Fixed</h4>' ) );
$check( 'list items render', substr_count( $out, '<li>' ) === 2 );
$check( 'list is closed', str_contains( $out, '</ul>' ) );

// --- Wrapped list items --------------------------------------------------
$out = $render( "- item that wraps\n  onto a second line\n" );
$check( 'wrapped line joins its item', str_contains( $out, '<li>item that wraps onto a second line</li>' ) );
$check( 'wrapped line makes no extra item', substr_count( $out, '<li>' ) === 1 );

// --- Inline --------------------------------------------------------------
$out = $render( "- **bold** and `code` here\n" );
$check( 'bold renders', str_contains( $out, '<strong>bold</strong>' ) );
$check( 'inline code renders', str_contains( $out, '<code>code</code>' ) );

$out = $render( "See [Keep a Changelog](https://keepachangelog.com/) here\n" );
$check( 'link renders with href', str_contains( $out, 'href="https://keepachangelog.com/"' ) );
$check( 'link opens safely', str_contains( $out, 'rel="noopener noreferrer"' ) );

// --- Injection -----------------------------------------------------------
$out = $render( "## <script>alert(1)</script>\n" );
$check( 'script tag in heading is escaped', ! str_contains( $out, '<script>' ) );

$out = $render( "- <img src=x onerror=alert(1)>\n" );
$check( 'img payload in list is escaped', ! str_contains( $out, '<img' ) );

$out = $render( "[click](javascript:alert(1))\n" );
$check( 'javascript: URL is dropped', ! str_contains( $out, 'javascript:' ) );

$out = $render( "[x](https://a.test/\" onmouseover=\"alert(1))\n" );
$check( 'quote cannot break out of href', ! preg_match( '/href="[^"]*"\s+onmouseover/', $out ) );

// --- Real file -----------------------------------------------------------
$real = file_get_contents( getenv( 'PLUGIN_DIR' ) . '/CHANGELOG.md' );
$out  = $render( $real );
$check( 'real CHANGELOG.md produces output', strlen( $out ) > 10000 );
$check( 'real file emits no raw script tag', ! str_contains( $out, '<script' ) );
$check( 'every <ul> is closed', substr_count( $out, '<ul>' ) === substr_count( $out, '</ul>' ) );
$check( 'every <li> is closed', substr_count( $out, '<li>' ) === substr_count( $out, '</li>' ) );
$check( 'every <p> is closed', substr_count( $out, '<p>' ) === substr_count( $out, '</p>' ) );
$check( 'no h1 emitted', ! str_contains( $out, '<h1>' ) );

echo $failures === 0 ? "\nAll checks passed.\n" : "\n$failures check(s) failed.\n";
exit( $failures === 0 ? 0 : 1 );

}
