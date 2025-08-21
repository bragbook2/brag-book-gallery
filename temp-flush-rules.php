<?php
/**
 * Flush rewrite rules for BRAGBook Gallery plugin
 * 
 * Run this file to flush WordPress rewrite rules
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) && php_sapi_name() !== 'cli' ) {
    die( 'Unauthorized' );
}

// Flush rewrite rules
flush_rewrite_rules();

echo "Rewrite rules have been flushed successfully!\n";
echo "The plugin should now handle URLs like /facelift/16480 correctly.\n";
echo "\nRegistered rewrite rules:\n";

// Show current rewrite rules for debugging
global $wp_rewrite;
$rules = $wp_rewrite->rewrite_rules();

// Filter to show only brag-book related rules
foreach ( $rules as $pattern => $query ) {
    if ( strpos( $pattern, 'facelift' ) !== false || 
         strpos( $pattern, 'gallery' ) !== false || 
         strpos( $query, 'procedure_title' ) !== false ||
         strpos( $query, 'case_id' ) !== false ) {
        echo "Pattern: $pattern\n";
        echo "Query: $query\n\n";
    }
}