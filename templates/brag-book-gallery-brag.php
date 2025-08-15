<?php
/**
 * Template Name: Brag Page Template
 *
 * Main routing template for BragBook gallery pages.
 * Handles routing to appropriate sub-templates based on URL structure.
 *
 * @package BRAGBook
 * @since   1.0.0
 */

declare( strict_types=1 );

use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\REST\Endpoints;

// Prevent direct access.
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

get_header();

// Initialize the request URI and sanitize it.
$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

// Remove query parameters from the request URI.
$case_url = strtok( $request_uri, '?' );

// Ensure the URL starts with a slash.
$case_url = trim( $case_url, '/' );

// If the URL is empty, redirect to the home page.
$url_parts = explode( '/', $case_url );

// Check if the URL is empty after trimming.
$base_slug = isset( $url_parts[0] ) ? $url_parts[0] : '';

// If the base slug is empty, redirect to the home page.
$page      = get_page_by_path( $base_slug );

// If the page does not exist, redirect to the home page.
$page_id   = $page instanceof WP_Post ? $page->ID : null;

/**
 * Handle 404 errors early for invalid pages.
 */
if ( is_404() || ! $page_id ) {
	get_template_part( slug: '404' );
	exit;
}

/**
 * Initialize route variables.
 */
$procedure_title = '';
$procedure_id    = '';
$case_id         = '';
$is_favorites_page        = str_contains( $case_url, '/favorites/' );

/**
 * Parse URL segments for favorites pages.
 */
if ( $is_favorites_page ) {

	if ( count( $url_parts ) >= 3 ) {
		$procedure_title = sanitize_title( $url_parts[2] );
		$procedure_id    = get_option(
			option: $procedure_title . '_id',
			default_value: ''
		);
	}

	if ( count( $url_parts ) >= 4 ) {
		$case_id = sanitize_text_field( $url_parts[3] );
	}

} else {
	/**
	 * Parse URL segments for regular gallery pages.
	 */
	if ( count( $url_parts ) >= 2 ) {
		$procedure_title = sanitize_title( $url_parts[1] );
		$procedure_id    = get_option(
			option: $procedure_title . '_id',
			default_value: ''
		);
	}

	if ( count( $url_parts ) >= 3 ) {
		$case_id = sanitize_text_field( $url_parts[2] );
	}
}

/**
 * Decode and sanitize procedure title if set.
 */
if ( ! empty( $procedure_title ) ) {
	$procedure_title = sanitize_text_field(
		urldecode( $procedure_title )
	);
}

/**
 * Route to consultation page template.
 */
if ( $case_url === '/' . $base_slug . '/consultation/' ) {
	require_once plugin_dir_path( file: __FILE__ ) . 'brag-book-gallery-consultation.php';
	return;
}

/**
 * Route to favorites page template.
 */
$is_favorites_list   = $case_url === '/' . $base_slug . '/favorites/';
$is_favorites_detail = ! empty( $case_id ) && $case_url === '/' . $base_slug . '/favorites/' . $procedure_title . '/' . $case_id . '/';

if ( $is_favorites_list || $is_favorites_detail ) {
	require_once plugin_dir_path( file: __FILE__ ) . 'brag-book-gallery-favorites.php';
	return;
}

/**
 * Route to case listing page template.
 */
if (
	! empty( $procedure_title ) &&
	$case_url === '/' . $base_slug . '/' . $procedure_title . '/'
) {
	require_once plugin_dir_path( file: __FILE__ ) . 'case-list-template.php';
	return;
}

/**
 * Route to case detail page template.
 */
if ( ! empty( $procedure_title ) &&
     ! empty( $case_id ) &&
     $case_url === '/' . $base_slug . '/' . $procedure_title . '/' . $case_id . '/'
) {

	/**
	 * Verify the case exists for this page.
	 */
	$case_option_key = $case_id . '_brag_book_gallery_procedure_id_' . $page_id;
	$case_exists     = get_option(
		option: $case_option_key,
		default_value: ''
	) !== '';

	if ( $case_exists ) {


		// Send view tracking request to API for the case.
		$api_tokens           = get_option(
			option: 'brag_book_gallery_api_token',
			default_value:  array()
		);

		// Get website property IDs and gallery slugs.
		$website_property_ids = get_option(
			option: 'brag_book_gallery_website_property_id',
			default_value: array()
		);

		// Get gallery slugs from options.
		$gallery_slugs = get_option(
			option: 'brag_book_gallery_page_slug',
			default_value: array()
		);

		foreach ( $api_tokens as $index => $api_token ) {
			/**
			 * Skip if required fields are missing.
			 */
			if ( empty( $api_token ) ) {
				continue;
			}

			$website_property_id = $website_property_ids[ $index ] ?? '';
			$page_slug          = $gallery_slugs[ $index ] ?? '';

			if ( empty( $website_property_id ) || $page_slug !== $base_slug ) {
				continue;
			}

			/**
			 * Send view tracking request to API using the centralized Endpoints class.
			 */
			$endpoints = new Endpoints();
			$endpoints->track_case_view( 
				$api_token, 
				(int) $case_id,
				array() // No additional metadata needed
			);
		}

		require_once plugin_dir_path( file: __FILE__ ) . 'case-details-template.php';
		return;
	}
}

/**
 * Default to carousel page template for all other routes.
 */
require_once plugin_dir_path( file: __FILE__ ) . 'carousel-page-template.php';
