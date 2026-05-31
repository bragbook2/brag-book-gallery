<?php
/**
 * Uninstall handler for BRAG book Gallery.
 *
 * Removes all plugin data when the plugin is deleted through the WordPress admin.
 * This file is called automatically by WordPress during uninstallation.
 *
 * @package    BRAGBookGallery
 * @since      4.4.6
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

// Abort if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all plugin options from the database.
 */
function brag_book_gallery_delete_options() {
	$options = array(
		'brag_book_gallery_api_token',
		'brag_book_gallery_website_property_id',
		'brag_book_gallery_page_slug',
		'brag_book_gallery_page_id',
		'brag_book_gallery_mode',
		'brag_book_gallery_debug_mode',
		'brag_book_gallery_javascript_enabled',
		'brag_book_gallery_log_api_calls',
		'brag_book_gallery_log_errors',
		'brag_book_gallery_log_verbosity',
		'brag_book_gallery_cache_duration',
		'brag_book_gallery_items_per_page',
		'brag_book_gallery_columns',
		'brag_book_gallery_enable_favorites',
		'brag_book_gallery_enable_powered_by',
		'brag_book_gallery_enable_sharing',
		'brag_book_gallery_enable_nudity_warning',
		'brag_book_gallery_enable_lightbox',
		'brag_book_gallery_enable_filtering',
		'brag_book_gallery_consultation_form_url',
		'brag_book_gallery_consultation_form_type',
		'brag_book_gallery_consultation_custom_html',
		'brag_book_gallery_consultation_enabled',
		'brag_book_gallery_db_version',
		'brag_book_gallery_version',
		'brag_book_gallery_activation_time',
		'brag_book_gallery_last_sync',
		'brag_book_gallery_api_endpoint',
		'brag_book_gallery_api_timeout',
		'brag_book_gallery_ajax_timeout',
		'brag_book_gallery_minify_assets',
		'brag_book_gallery_lazy_load',
		'brag_book_gallery_use_custom_font',
		'brag_book_gallery_custom_css',
		'brag_book_gallery_image_display_mode',
		'brag_book_gallery_infinite_scroll',
		'brag_book_gallery_show_doctor',
		'brag_book_gallery_show_filter_counts',
		'brag_book_gallery_expand_nav_menus',
		'brag_book_gallery_case_card_type',
		'brag_book_gallery_case_image_carousel',
		'brag_book_gallery_enable_logs',
		'brag_book_gallery_log_level',
		'brag_book_gallery_stored_pages',
		'brag_book_gallery_seo_page_title',
		'brag_book_gallery_seo_page_description',
		'brag_book_gallery_seo_procedure_titles',
		'brag_book_gallery_seo_procedure_descriptions',
		'brag_book_gallery_account_info',
		'brag_book_gallery_release_channel',
		'brag_book_gallery_update_available',
		'brag_book_gallery_plugin_state',
		'brag_book_gallery_taxonomy_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Remove all plugin transients from the database.
 */
function brag_book_gallery_delete_transients() {
	global $wpdb;

	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_brag_book_gallery_%'
		OR option_name LIKE '_transient_timeout_brag_book_gallery_%'"
	);
}

/**
 * Remove custom database tables.
 */
function brag_book_gallery_drop_tables() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'brag_sync_log',
		$wpdb->prefix . 'brag_sync_registry',
		$wpdb->prefix . 'brag_case_map', // Legacy table
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}

/**
 * Remove custom post type data.
 */
function brag_book_gallery_delete_posts() {
	global $wpdb;

	// Delete all posts of the custom post type and their meta.
	$post_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'brag_book_cases'"
	);

	if ( ! empty( $post_ids ) ) {
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}

/**
 * Remove custom taxonomy terms.
 */
function brag_book_gallery_delete_terms() {
	global $wpdb;

	$terms = $wpdb->get_results(
		"SELECT t.term_id
		FROM {$wpdb->terms} t
		INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
		WHERE tt.taxonomy = 'brag_book_procedures'"
	);

	if ( ! empty( $terms ) ) {
		foreach ( $terms as $term ) {
			wp_delete_term( (int) $term->term_id, 'brag_book_procedures' );
		}
	}
}

/**
 * Remove scheduled cron events.
 */
function brag_book_gallery_clear_cron() {
	wp_clear_scheduled_hook( 'brag_book_gallery_db_cleanup' );
	wp_clear_scheduled_hook( 'brag_book_gallery_auto_sync' );
}

/**
 * Remove the plugin's custom permalink rewrite rules.
 *
 * The plugin injects rewrite rules built on the gallery slug — the
 * `brag_book_cases` post type and `brag_book_procedures` taxonomy both use
 * it — plus a `/myfavorites` endpoint and the XML sitemap rule. WordPress
 * persists the resolved rules in the `rewrite_rules` option.
 *
 * During uninstall the plugin is not loaded, so its post types, taxonomies
 * and custom `add_rewrite_rule()` calls are never registered. A
 * `flush_rewrite_rules()` call in this context cannot dependably regenerate
 * a clean rule set, which left the plugin's rules baked into the option and
 * caused sitewide 404s after the plugin was deleted.
 *
 * Deleting the `rewrite_rules` option forces WordPress to rebuild the rules
 * from scratch — without the plugin's rules — on the next front-end request.
 * The rewrite-flush flag options are removed alongside it so no stale state
 * survives a later reinstall.
 *
 * @since 4.6.0
 */
function brag_book_gallery_clean_rewrite_rules() {

	// Drop the cached rewrite rules so WordPress rebuilds them cleanly.
	delete_option( 'rewrite_rules' );

	// Remove the flags that gate the plugin's rewrite-rule flushing.
	$rewrite_flags = array(
		'brag_book_gallery_flush_rewrite_rules',
		'brag_book_gallery_case_url_structure_updated',
		'brag_book_gallery_favorites_rewrite_fixed',
		'brag_book_taxonomy_version',
	);

	foreach ( $rewrite_flags as $flag ) {
		delete_option( $flag );
	}
}

// Execute cleanup.
brag_book_gallery_delete_options();
brag_book_gallery_delete_transients();
brag_book_gallery_delete_posts();
brag_book_gallery_delete_terms();
brag_book_gallery_drop_tables();
brag_book_gallery_clear_cron();

// Remove the plugin's custom rewrite rules so WordPress rebuilds a clean
// rule set, preventing stale gallery rules from causing sitewide 404s.
brag_book_gallery_clean_rewrite_rules();
