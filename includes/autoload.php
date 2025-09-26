<?php
/**
 * Autoloader.
 *
 * @author     Candace Crowe <info@bragbook.com>
 * @copyright  Copyright (c) 2025, Candace Crowe LLC
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @link       https://candacecrowe.com/
 * @package    BRAGBookGallery
 * @since      3.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

spl_autoload_register(
	function ( $file ) {

		$file_path = explode( '\\', $file );
		$file_name = '';

		if ( isset( $file_path[ count( $file_path ) - 1 ] ) ) {
			$file_name       = strtolower( $file_path[ count( $file_path ) - 1 ] );
			$file_name       = str_ireplace( '_', '-', $file_name );
			$file_name_parts = explode( '-', $file_name );

			$index = $file_name_parts[0];

			if ( 'interface' === $index || 'trait' === $index ) {
				unset( $file_name_parts[ $index ] );
				$file_name = implode( '-', $file_name_parts );
				$file_name = $file_name . '.php';
			} else {
				$file_name = 'class-' . $file_name . '.php';
			}
		}

		$fully_qualified_path = trailingslashit( dirname( __DIR__, 1 ) );

		$count = count( $file_path );

		for ( $i = 1; $i < $count - 1; $i++ ) {
			$dir                   = strtolower( $file_path[ $i ] );
			$fully_qualified_path .= trailingslashit( $dir );
		}

		$fully_qualified_path .= $file_name;

		if ( stream_resolve_include_path( $fully_qualified_path ) ) {
			require_once $fully_qualified_path;
		}
	}
);
