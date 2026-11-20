<?php
/**
 * Self-check for Taxonomies::valid_profile_url().
 *
 * The API sends a provider's profile link as `profileUrl`, and sync writes
 * it to the Profile URL only when it is a real web link. No framework — run
 * from the plugin root with:
 *
 *     php tests/unit/test-provider-valid-profile-url.php
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
}

namespace BRAGBookGallery\Includes\Extend {
	require_once dirname( __DIR__, 2 ) . '/includes/extend/class-taxonomies.php';

	$cases = [
		'https://example.com/dr-smith'     => 'https://example.com/dr-smith',
		'  http://example.com/dr-smith/  ' => 'http://example.com/dr-smith/',
		'Dr. Smith is board certified.'    => '',
		'javascript:alert(1)'              => '',
		'ftp://example.com/file'           => '',
		'example.com/dr-smith'             => '',
		''                                 => '',
	];

	$failures = 0;

	foreach ( $cases as $input => $expected ) {
		$actual = Taxonomies::valid_profile_url( (string) $input );

		if ( $actual !== $expected ) {
			$failures++;
			printf( "FAIL  %-34s got %-30s want %s\n", $input, $actual, $expected );
			continue;
		}

		printf( "ok    %-34s %s\n", $input, $actual );
	}

	if ( $failures > 0 ) {
		printf( "\n%d failure(s)\n", $failures );
		exit( 1 );
	}

	echo "\nAll profile URL checks passed.\n";
}
