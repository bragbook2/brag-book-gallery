<?php
/**
 * Self-check for Taxonomies::provider_display_name().
 *
 * Provider names are punctuated for display only, so this guards the one piece
 * of logic that does it. No framework — run from the plugin root with:
 *
 *     php tests/unit/test-provider-display-name.php
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
}

namespace BRAGBookGallery\Includes\Extend {
	require_once dirname( __DIR__, 2 ) . '/includes/extend/class-taxonomies.php';

	$cases = [
		'John A Smith'      => 'John A. Smith',
		'John A. Smith'     => 'John A. Smith',
		'Dr. J R Smith'     => 'Dr. J. R. Smith',
		'John A Smith, MD'  => 'John A. Smith, MD',
		'Maria Lopez'       => 'Maria Lopez',
		'Provider 42'       => 'Provider 42',
		''                  => '',
	];

	$failures = 0;

	foreach ( $cases as $input => $expected ) {
		$actual = Taxonomies::provider_display_name( (string) $input );

		if ( $actual !== $expected ) {
			$failures++;
			printf( "FAIL  %-20s got %-22s want %s\n", $input, $actual, $expected );
			continue;
		}

		printf( "ok    %-20s %s\n", $input, $actual );
	}

	if ( $failures > 0 ) {
		printf( "\n%d failure(s)\n", $failures );
		exit( 1 );
	}

	echo "\nAll provider name checks passed.\n";
}
