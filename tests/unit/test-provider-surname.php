<?php
/**
 * Self-check for Taxonomies::provider_surname().
 *
 * The provider filter can sort by surname, so this guards how a surname is
 * pulled from a provider name. No framework — run from the plugin root with:
 *
 *     php tests/unit/test-provider-surname.php
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
}

namespace BRAGBookGallery\Includes\Extend {
	require_once dirname( __DIR__, 2 ) . '/includes/extend/class-taxonomies.php';

	$cases = [
		'John A. Smith'       => 'Smith',
		'Dr. J. R. Smith'     => 'Smith',
		'John A. Smith, MD'   => 'Smith',
		'John Smith Jr.'      => 'Smith',
		'John Smith III, DDS' => 'Smith',
		'Maria de la Cruz'    => 'Cruz',
		'Cher'                => 'Cher',
		'  Maria  Lopez  '    => 'Lopez',
		''                    => '',
	];

	$failures = 0;

	foreach ( $cases as $input => $expected ) {
		$actual = Taxonomies::provider_surname( (string) $input );

		if ( $actual !== $expected ) {
			$failures++;
			printf( "FAIL  %-22s got %-10s want %s\n", $input, $actual, $expected );
			continue;
		}

		printf( "ok    %-22s %s\n", $input, $actual );
	}

	if ( $failures > 0 ) {
		printf( "\n%d failure(s)\n", $failures );
		exit( 1 );
	}

	echo "\nAll provider surname checks passed.\n";
}
