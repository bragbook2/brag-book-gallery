<?php
/**
 * Self-check for the gallery shortcode's `tag` attribute.
 *
 * The attribute picks the element wrapping .brag-book-gallery-content-title, so
 * this guards the allowlist and the rendered markup. No framework — run from
 * the plugin root with:
 *
 *     php tests/unit/test-content-title-tag.php
 */

namespace BRAGBookGallery\Includes\Core {
	trait Trait_Api {}
}

namespace BRAGBookGallery\Includes\Shortcodes\Traits {
	trait Trait_Provider_Query {}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	// Minimal stand-ins for the WordPress functions the parsing touches.
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) );
	}

	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}

	function sanitize_title( $value ) {
		return strtolower( (string) $value );
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function add_shortcode( ...$args ) {}

	function add_action( ...$args ) {}

	require_once dirname( __DIR__, 2 ) . '/includes/shortcodes/class-gallery-handler.php';

	$handler   = 'BRAGBookGallery\Includes\Shortcodes\Gallery_Handler';
	$sanitizer = new ReflectionMethod( $handler, 'sanitize_title_tag' );
	$parser    = new ReflectionMethod( $handler, 'validate_and_sanitize_shortcode_attributes' );
	$sanitizer->setAccessible( true );
	$parser->setAccessible( true );

	$tags = [
		'p'      => 'p',
		'H2'     => 'h2',
		' span ' => 'span',
		''       => 'h1',
		'script' => 'h1',
		'h7'     => 'h1',
	];

	foreach ( $tags as $given => $expected ) {
		$actual = $sanitizer->invoke( null, $given );
		assert( $actual === $expected, sprintf( 'tag "%s" gave "%s", expected "%s"', $given, $actual, $expected ) );
	}

	// An explicit tag reaches the rendered title.
	$parsed = $parser->invoke( null, [ 'tag' => 'p' ] );
	assert( 'p' === $parsed['tag'] );
	assert( '<p class="brag-book-gallery-content-title"><strong>X</strong></p>' === $handler::render_content_title( '<strong>X</strong>' ) );

	// Omitting it goes back to the heading, even after a previous shortcode set one.
	$parser->invoke( null, [] );
	assert( '<h1 class="brag-book-gallery-content-title"><strong>X</strong></h1>' === $handler::render_content_title( '<strong>X</strong>' ) );

	echo "OK\n";
}
