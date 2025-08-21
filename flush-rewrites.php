<?php
/**
 * Flush rewrite rules for BRAGBook Gallery plugin
 * Access this file directly to flush WordPress rewrite rules
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if user is logged in and is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'You must be logged in as an administrator to flush rewrite rules.' );
}

// Trigger the flush
update_option( 'brag_book_gallery_flush_rewrite_rules', true );
flush_rewrite_rules();

echo '<h2>Success!</h2>';
echo '<p>Rewrite rules have been flushed successfully.</p>';
echo '<p><a href="' . home_url() . '/wp-admin/">Return to WordPress Admin</a></p>';
echo '<p>You can now access the My Favorites page at: <a href="' . home_url() . '/before-after/myfavorites">/before-after/myfavorites</a></p>';