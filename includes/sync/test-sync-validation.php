<?php
/**
 * Sync Validation Test Script
 *
 * Simple validation script to test the two-stage sync functionality.
 * This can be used to validate the sync workflow during development.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Validation helper functions for sync testing
 */
class Sync_Validation_Test {

	/**
	 * Test procedure sync functionality
	 *
	 * @since 3.0.0
	 * @return array Test results
	 */
	public static function test_procedure_sync(): array {
		$results = [];

		try {
			// Test if Procedure_Sync class exists
			if ( ! class_exists( '\\BRAGBookGallery\\Includes\\Sync\\Procedure_Sync' ) ) {
				$results['class_exists'] = false;
				return $results;
			}

			$results['class_exists'] = true;

			// Test if API tokens are configured
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$results['api_configured'] = ! empty( $api_tokens ) && ! empty( $api_tokens[0] );

			// Test if custom post type is registered
			$results['post_type_exists'] = post_type_exists( 'brag_book_cases' );

			// Test if procedure taxonomy exists
			$results['taxonomy_exists'] = taxonomy_exists( 'procedures' );

			// Count existing procedures
			$procedures = get_terms( [
				'taxonomy' => 'procedures',
				'hide_empty' => false,
			] );
			$results['existing_procedures'] = is_array( $procedures ) ? count( $procedures ) : 0;

			// Count existing cases
			$cases = get_posts( [
				'post_type' => 'brag_book_cases',
				'numberposts' => -1,
				'post_status' => 'any',
			] );
			$results['existing_cases'] = count( $cases );

			// Test sync log table
			global $wpdb;
			$table_name = $wpdb->prefix . 'brag_book_sync_log';
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
			$results['sync_table_exists'] = $table_exists;

			if ( $table_exists ) {
				$log_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
				$results['sync_log_entries'] = (int) $log_count;
			} else {
				$results['sync_log_entries'] = 0;
			}

		} catch ( Exception $e ) {
			$results['error'] = $e->getMessage();
		}

		return $results;
	}

	/**
	 * Validate sync requirements
	 *
	 * @since 3.0.0
	 * @return array Validation results
	 */
	public static function validate_sync_requirements(): array {
		$validation = [];

		// Check PHP version
		$validation['php_version'] = [
			'required' => '8.2.0',
			'current' => PHP_VERSION,
			'valid' => version_compare( PHP_VERSION, '8.2.0', '>=' ),
		];

		// Check WordPress version
		$validation['wp_version'] = [
			'required' => '6.8.0',
			'current' => get_bloginfo( 'version' ),
			'valid' => version_compare( get_bloginfo( 'version' ), '6.8.0', '>=' ),
		];

		// Check required functions
		$required_functions = [
			'wp_remote_post',
			'wp_insert_post',
			'wp_insert_term',
			'update_post_meta',
			'update_term_meta',
		];

		foreach ( $required_functions as $function ) {
			$validation['functions'][ $function ] = function_exists( $function );
		}

		// Check memory limit
		$memory_limit = ini_get( 'memory_limit' );
		$memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
		$validation['memory_limit'] = [
			'current' => $memory_limit,
			'bytes' => $memory_bytes,
			'adequate' => $memory_bytes >= ( 256 * 1024 * 1024 ), // 256MB
		];

		// Check max execution time
		$max_execution_time = ini_get( 'max_execution_time' );
		$validation['execution_time'] = [
			'current' => $max_execution_time,
			'adequate' => $max_execution_time == 0 || $max_execution_time >= 300, // 5 minutes
		];

		return $validation;
	}

	/**
	 * Generate validation report
	 *
	 * @since 3.0.0
	 * @return string HTML report
	 */
	public static function generate_validation_report(): string {
		$test_results = self::test_procedure_sync();
		$requirements = self::validate_sync_requirements();

		$html = '<div class="sync-validation-report">';
		$html .= '<h3>Sync System Validation Report</h3>';

		// Requirements section
		$html .= '<h4>System Requirements</h4>';
		$html .= '<table class="widefat">';
		$html .= '<thead><tr><th>Requirement</th><th>Current</th><th>Status</th></tr></thead>';
		$html .= '<tbody>';

		// PHP Version
		$status = $requirements['php_version']['valid'] ? '✓ Valid' : '✗ Invalid';
		$class = $requirements['php_version']['valid'] ? 'success' : 'error';
		$html .= "<tr><td>PHP Version (>= {$requirements['php_version']['required']})</td><td>{$requirements['php_version']['current']}</td><td class='{$class}'>{$status}</td></tr>";

		// WordPress Version
		$status = $requirements['wp_version']['valid'] ? '✓ Valid' : '✗ Invalid';
		$class = $requirements['wp_version']['valid'] ? 'success' : 'error';
		$html .= "<tr><td>WordPress Version (>= {$requirements['wp_version']['required']})</td><td>{$requirements['wp_version']['current']}</td><td class='{$class}'>{$status}</td></tr>";

		// Memory Limit
		$status = $requirements['memory_limit']['adequate'] ? '✓ Adequate' : '✗ Too Low';
		$class = $requirements['memory_limit']['adequate'] ? 'success' : 'warning';
		$html .= "<tr><td>Memory Limit (>= 256MB)</td><td>{$requirements['memory_limit']['current']}</td><td class='{$class}'>{$status}</td></tr>";

		// Execution Time
		$current_time = $requirements['execution_time']['current'] == 0 ? 'Unlimited' : $requirements['execution_time']['current'] . 's';
		$status = $requirements['execution_time']['adequate'] ? '✓ Adequate' : '✗ Too Short';
		$class = $requirements['execution_time']['adequate'] ? 'success' : 'warning';
		$html .= "<tr><td>Max Execution Time (>= 300s)</td><td>{$current_time}</td><td class='{$class}'>{$status}</td></tr>";

		$html .= '</tbody></table>';

		// Sync System Status
		$html .= '<h4>Sync System Status</h4>';
		$html .= '<table class="widefat">';
		$html .= '<thead><tr><th>Component</th><th>Status</th><th>Details</th></tr></thead>';
		$html .= '<tbody>';

		// Class exists
		$status = $test_results['class_exists'] ? '✓ Available' : '✗ Missing';
		$class = $test_results['class_exists'] ? 'success' : 'error';
		$html .= "<tr><td>Procedure_Sync Class</td><td class='{$class}'>{$status}</td><td>Main sync class</td></tr>";

		// API configured
		$status = $test_results['api_configured'] ? '✓ Configured' : '✗ Not Configured';
		$class = $test_results['api_configured'] ? 'success' : 'error';
		$html .= "<tr><td>API Configuration</td><td class='{$class}'>{$status}</td><td>API tokens required for sync</td></tr>";

		// Post type exists
		$status = $test_results['post_type_exists'] ? '✓ Registered' : '✗ Missing';
		$class = $test_results['post_type_exists'] ? 'success' : 'error';
		$html .= "<tr><td>Cases Post Type</td><td class='{$class}'>{$status}</td><td>Required for case storage</td></tr>";

		// Taxonomy exists
		$status = $test_results['taxonomy_exists'] ? '✓ Registered' : '✗ Missing';
		$class = $test_results['taxonomy_exists'] ? 'success' : 'error';
		$html .= "<tr><td>Procedures Taxonomy</td><td class='{$class}'>{$status}</td><td>Required for procedure organization</td></tr>";

		// Sync table exists
		$status = $test_results['sync_table_exists'] ? '✓ Available' : '✗ Missing';
		$class = $test_results['sync_table_exists'] ? 'success' : 'error';
		$html .= "<tr><td>Sync Log Table</td><td class='{$class}'>{$status}</td><td>Required for sync history</td></tr>";

		$html .= '</tbody></table>';

		// Current Data Status
		$html .= '<h4>Current Data Status</h4>';
		$html .= '<table class="widefat">';
		$html .= '<thead><tr><th>Data Type</th><th>Count</th><th>Status</th></tr></thead>';
		$html .= '<tbody>';

		$html .= "<tr><td>Existing Procedures</td><td>{$test_results['existing_procedures']}</td><td>Current taxonomy terms</td></tr>";
		$html .= "<tr><td>Existing Cases</td><td>{$test_results['existing_cases']}</td><td>Current case posts</td></tr>";
		$html .= "<tr><td>Sync Log Entries</td><td>{$test_results['sync_log_entries']}</td><td>Historical sync operations</td></tr>";

		$html .= '</tbody></table>';

		// Overall status
		$all_requirements_met = $requirements['php_version']['valid'] &&
								$requirements['wp_version']['valid'] &&
								$requirements['memory_limit']['adequate'] &&
								$requirements['execution_time']['adequate'];

		$sync_ready = $test_results['class_exists'] &&
					  $test_results['api_configured'] &&
					  $test_results['post_type_exists'] &&
					  $test_results['taxonomy_exists'] &&
					  $test_results['sync_table_exists'];

		$html .= '<h4>Overall Status</h4>';
		if ( $all_requirements_met && $sync_ready ) {
			$html .= '<div class="notice notice-success"><p><strong>✓ System Ready</strong> - All requirements met. Sync functionality is ready to use.</p></div>';
		} elseif ( $sync_ready ) {
			$html .= '<div class="notice notice-warning"><p><strong>⚠ Partial Ready</strong> - Sync components are available but system requirements may cause issues.</p></div>';
		} else {
			$html .= '<div class="notice notice-error"><p><strong>✗ Not Ready</strong> - Required components are missing or not configured.</p></div>';
		}

		$html .= '</div>';

		return $html;
	}
}