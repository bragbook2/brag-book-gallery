<?php
/**
 * Debug rewrite rules
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if user is logged in and has admin capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You do not have permission to access this page.' );
}

echo '<h2>Rewrite Rules Debug</h2>';

// Get the combine_gallery_slug
$combine_gallery_slug = get_option( 'combine_gallery_slug' );
echo '<h3>Settings:</h3>';
echo '<p><strong>combine_gallery_slug:</strong> ' . esc_html( $combine_gallery_slug ?: '(not set)' ) . '</p>';

// Find pages with shortcode
global $wpdb;
$pages_with_shortcode = $wpdb->get_results(
	"SELECT ID, post_name, post_title, post_content 
	FROM {$wpdb->posts} 
	WHERE post_content LIKE '%[brag_book_gallery%' 
	AND post_status = 'publish' 
	AND post_type = 'page'"
);

echo '<h3>Pages with [brag_book_gallery] shortcode:</h3>';
if ( ! empty( $pages_with_shortcode ) ) {
	echo '<ul>';
	foreach ( $pages_with_shortcode as $page ) {
		echo '<li>ID: ' . $page->ID . ', Slug: ' . esc_html( $page->post_name ) . ', Title: ' . esc_html( $page->post_title ) . '</li>';
	}
	echo '</ul>';
} else {
	echo '<p>No pages found with the shortcode.</p>';
}

// Get current rewrite rules
global $wp_rewrite;
$rules = $wp_rewrite->wp_rewrite_rules();

echo '<h3>Gallery-related Rewrite Rules:</h3>';
echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">';

$found_rules = false;
foreach ( $rules as $pattern => $query ) {
	// Look for rules containing our slug or gallery-related patterns
	if ( 
		( $combine_gallery_slug && strpos( $pattern, $combine_gallery_slug ) !== false ) ||
		strpos( $pattern, 'gallery' ) !== false ||
		strpos( $pattern, 'before-after' ) !== false ||
		strpos( $query, 'filter_procedure' ) !== false ||
		strpos( $query, 'procedure_title' ) !== false ||
		strpos( $query, 'case_id' ) !== false
	) {
		echo htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
		$found_rules = true;
	}
}

if ( ! $found_rules ) {
	echo "No gallery-related rewrite rules found!\n";
}

echo '</pre>';

// Test specific URLs
echo '<h3>Test URL Parsing:</h3>';
$test_urls = [
	'/before-after/tummy-tuck',
	'/before-after/arm-lift',
	'/before-after/brow-lift/15268',
];

foreach ( $test_urls as $test_url ) {
	$test_url = ltrim( $test_url, '/' );
	echo '<h4>Testing: ' . esc_html( $test_url ) . '</h4>';
	
	$matched = false;
	foreach ( $rules as $pattern => $query ) {
		if ( preg_match( '#' . $pattern . '#', $test_url, $matches ) ) {
			echo '<p style="color: green;">✓ Matches pattern: <code>' . esc_html( $pattern ) . '</code></p>';
			echo '<p>Query: <code>' . esc_html( $query ) . '</code></p>';
			
			// Show what the query vars would be
			$query_with_matches = $query;
			foreach ( $matches as $i => $match ) {
				$query_with_matches = str_replace( '$matches[' . $i . ']', $match, $query_with_matches );
			}
			echo '<p>Resolved query: <code>' . esc_html( $query_with_matches ) . '</code></p>';
			
			$matched = true;
			break;
		}
	}
	
	if ( ! $matched ) {
		echo '<p style="color: red;">✗ No matching rewrite rule found!</p>';
	}
}

// Show registered query vars
echo '<h3>Registered Query Vars:</h3>';
global $wp;
echo '<pre>';
$gallery_vars = ['procedure_title', 'case_id', 'filter_procedure', 'filter_category', 'favorites_section'];
foreach ( $gallery_vars as $var ) {
	$is_registered = in_array( $var, $wp->public_query_vars ) || in_array( $var, $wp->private_query_vars );
	echo $var . ': ' . ( $is_registered ? '✓ Registered' : '✗ Not registered' ) . "\n";
}
echo '</pre>';

// Force regenerate rules button
echo '<h3>Actions:</h3>';
echo '<form method="post">';
echo '<input type="hidden" name="force_regenerate" value="1">';
echo '<button type="submit" class="button button-primary">Force Regenerate Rewrite Rules</button>';
echo '</form>';

if ( isset( $_POST['force_regenerate'] ) ) {
	// Register the rules
	if ( class_exists( 'BRAGBookGallery\Includes\Extend\Shortcodes' ) ) {
		BRAGBookGallery\Includes\Extend\Shortcodes::custom_rewrite_rules();
	}
	flush_rewrite_rules( true );
	echo '<p style="color: green; font-weight: bold;">✓ Rewrite rules regenerated! Refresh this page to see updated rules.</p>';
}

echo '<hr>';
echo '<p><a href="' . admin_url( 'options-permalink.php' ) . '">Go to Permalinks Settings</a> | ';
echo '<a href="' . home_url() . '">Go to Homepage</a></p>';