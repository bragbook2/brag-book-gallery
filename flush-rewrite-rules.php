<?php
/**
 * Utility to manually flush rewrite rules
 * 
 * Access this file directly in browser to flush rules:
 * http://yoursite.com/wp-content/plugins/brag-book-gallery/flush-rewrite-rules.php
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if user is logged in and has admin capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You do not have permission to access this page.' );
}

// Force registration of rewrite rules
if ( class_exists( 'BRAGBookGallery\Includes\Extend\Shortcodes' ) ) {
	BRAGBookGallery\Includes\Extend\Shortcodes::custom_rewrite_rules();
}

// Flush rewrite rules
flush_rewrite_rules( true );

// Output success message
echo '<h2>Rewrite Rules Flushed Successfully!</h2>';
echo '<p>The rewrite rules have been regenerated.</p>';
echo '<p><a href="' . admin_url( 'options-permalink.php' ) . '">Go to Permalinks Settings</a></p>';
echo '<p><a href="' . home_url() . '">Go to Homepage</a></p>';

// Display current rewrite rules for debugging
global $wp_rewrite;
echo '<h3>Current Rewrite Rules:</h3>';
echo '<pre>';
$rules = $wp_rewrite->wp_rewrite_rules();
foreach ( $rules as $pattern => $query ) {
	if ( strpos( $pattern, 'gallery' ) !== false || strpos( $pattern, 'procedure' ) !== false || strpos( $pattern, 'case' ) !== false ) {
		echo htmlspecialchars( $pattern ) . ' => ' . htmlspecialchars( $query ) . "\n";
	}
}
echo '</pre>';