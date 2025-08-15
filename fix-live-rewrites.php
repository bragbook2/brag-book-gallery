<?php
/**
 * Fix rewrite rules for live site
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if user is logged in and has admin capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You do not have permission to access this page.' );
}

echo '<h1>Live Site Rewrite Rules Fix</h1>';

// Server environment check
echo '<h2>1. Server Environment</h2>';
echo '<ul>';
echo '<li><strong>Server Software:</strong> ' . esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) . '</li>';
echo '<li><strong>PHP Version:</strong> ' . phpversion() . '</li>';
echo '<li><strong>WordPress Version:</strong> ' . get_bloginfo( 'version' ) . '</li>';
echo '<li><strong>Permalink Structure:</strong> ' . get_option( 'permalink_structure' ) . '</li>';
echo '<li><strong>Home URL:</strong> ' . home_url() . '</li>';
echo '<li><strong>Site URL:</strong> ' . site_url() . '</li>';
echo '</ul>';

// Check if mod_rewrite is enabled (Apache)
if ( stripos( $_SERVER['SERVER_SOFTWARE'] ?? '', 'apache' ) !== false ) {
	echo '<h3>Apache Checks:</h3>';
	if ( function_exists( 'apache_get_modules' ) ) {
		$modules = apache_get_modules();
		if ( in_array( 'mod_rewrite', $modules ) ) {
			echo '<p style="color: green;">✓ mod_rewrite is enabled</p>';
		} else {
			echo '<p style="color: red;">✗ mod_rewrite is NOT enabled - this is required for custom URLs!</p>';
		}
	} else {
		echo '<p style="color: orange;">⚠ Cannot check mod_rewrite status (apache_get_modules not available)</p>';
	}
}

// Check .htaccess
echo '<h2>2. .htaccess File</h2>';
$htaccess_path = ABSPATH . '.htaccess';
if ( file_exists( $htaccess_path ) ) {
	echo '<p style="color: green;">✓ .htaccess file exists</p>';
	
	$htaccess_content = file_get_contents( $htaccess_path );
	if ( strpos( $htaccess_content, 'BEGIN WordPress' ) !== false ) {
		echo '<p style="color: green;">✓ WordPress rules found in .htaccess</p>';
	} else {
		echo '<p style="color: red;">✗ WordPress rules NOT found in .htaccess</p>';
	}
	
	// Check if it's writable
	if ( is_writable( $htaccess_path ) ) {
		echo '<p style="color: green;">✓ .htaccess is writable</p>';
	} else {
		echo '<p style="color: orange;">⚠ .htaccess is NOT writable - may need to update manually</p>';
	}
	
	echo '<details>';
	echo '<summary>View .htaccess content (click to expand)</summary>';
	echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
	echo htmlspecialchars( $htaccess_content );
	echo '</pre>';
	echo '</details>';
} else {
	echo '<p style="color: red;">✗ .htaccess file does NOT exist!</p>';
	echo '<p>WordPress needs this file for custom URLs. Creating it...</p>';
	
	// Try to create .htaccess
	if ( isset( $_POST['create_htaccess'] ) ) {
		$htaccess_rules = '# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress';
		
		if ( file_put_contents( $htaccess_path, $htaccess_rules ) ) {
			echo '<p style="color: green;">✓ .htaccess file created successfully!</p>';
		} else {
			echo '<p style="color: red;">✗ Could not create .htaccess file. Please create it manually with these contents:</p>';
			echo '<pre>' . htmlspecialchars( $htaccess_rules ) . '</pre>';
		}
	} else {
		echo '<form method="post">';
		echo '<input type="hidden" name="create_htaccess" value="1">';
		echo '<button type="submit" class="button button-primary">Create .htaccess file</button>';
		echo '</form>';
	}
}

// Gallery configuration
echo '<h2>3. Gallery Configuration</h2>';
$combine_gallery_slug = get_option( 'combine_gallery_slug' );
$combine_gallery_page_id = get_option( 'combine_gallery_page_id' );

echo '<ul>';
echo '<li><strong>combine_gallery_slug:</strong> ' . esc_html( $combine_gallery_slug ?: '(not set)' ) . '</li>';
echo '<li><strong>combine_gallery_page_id:</strong> ' . esc_html( $combine_gallery_page_id ?: '(not set)' ) . '</li>';
echo '</ul>';

// Check if page exists
if ( $combine_gallery_slug ) {
	$page = get_page_by_path( $combine_gallery_slug );
	if ( $page ) {
		echo '<p style="color: green;">✓ Page exists with slug: ' . esc_html( $combine_gallery_slug ) . '</p>';
		if ( strpos( $page->post_content, '[brag_book_gallery' ) !== false ) {
			echo '<p style="color: green;">✓ Page contains [brag_book_gallery] shortcode</p>';
		}
	} else {
		echo '<p style="color: red;">✗ No page found with slug: ' . esc_html( $combine_gallery_slug ) . '</p>';
	}
}

// Current rewrite rules
echo '<h2>4. Current Rewrite Rules</h2>';
global $wp_rewrite;
$rules = $wp_rewrite->wp_rewrite_rules();

if ( empty( $rules ) ) {
	echo '<p style="color: red;">✗ No rewrite rules found! This is a problem.</p>';
} else {
	echo '<p>Found ' . count( $rules ) . ' total rewrite rules.</p>';
	
	// Check for our custom rules
	$found_gallery_rules = false;
	echo '<h3>Gallery-specific rules:</h3>';
	echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
	foreach ( $rules as $pattern => $query ) {
		if ( $combine_gallery_slug && strpos( $pattern, $combine_gallery_slug ) !== false ) {
			echo htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
			$found_gallery_rules = true;
		}
	}
	
	if ( ! $found_gallery_rules ) {
		echo 'No rules found for slug: ' . esc_html( $combine_gallery_slug );
	}
	echo '</pre>';
}

// Test URLs
echo '<h2>5. Test URLs</h2>';
if ( $combine_gallery_slug ) {
	$test_urls = [
		home_url( '/' . $combine_gallery_slug . '/' ),
		home_url( '/' . $combine_gallery_slug . '/tummy-tuck/' ),
		home_url( '/' . $combine_gallery_slug . '/tummy-tuck/12345/' ),
	];
	
	echo '<ul>';
	foreach ( $test_urls as $url ) {
		echo '<li><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a></li>';
	}
	echo '</ul>';
}

// Actions
echo '<h2>6. Fix Actions</h2>';

// Force regenerate with proper rules
if ( isset( $_POST['force_fix'] ) ) {
	echo '<h3>Applying fixes...</h3>';
	
	// 1. Ensure query vars are registered
	global $wp;
	$query_vars = [ 'procedure_title', 'case_id', 'filter_procedure', 'filter_category' ];
	foreach ( $query_vars as $var ) {
		$wp->add_query_var( $var );
	}
	echo '<p>✓ Query vars registered</p>';
	
	// 2. Force add rewrite rules
	if ( class_exists( 'BRAGBookGallery\Includes\Extend\Shortcodes' ) ) {
		BRAGBookGallery\Includes\Extend\Shortcodes::custom_rewrite_rules();
		echo '<p>✓ Custom rewrite rules added</p>';
	}
	
	// 3. Flush rewrite rules
	flush_rewrite_rules( true );
	echo '<p>✓ Rewrite rules flushed</p>';
	
	// 4. Update .htaccess if needed
	if ( ! file_exists( $htaccess_path ) || ! strpos( file_get_contents( $htaccess_path ), 'BEGIN WordPress' ) ) {
		save_mod_rewrite_rules();
		echo '<p>✓ .htaccess updated</p>';
	}
	
	echo '<p style="color: green; font-weight: bold;">Fixes applied! Test your URLs now.</p>';
}

echo '<form method="post">';
echo '<input type="hidden" name="force_fix" value="1">';
echo '<button type="submit" class="button button-primary" style="font-size: 16px; padding: 10px 20px;">Apply All Fixes</button>';
echo '</form>';

// Manual fix instructions
echo '<h2>7. Manual Fix Instructions (if automatic fix doesn\'t work)</h2>';
echo '<ol>';
echo '<li><strong>For Apache servers:</strong> Ensure mod_rewrite is enabled in your hosting control panel</li>';
echo '<li><strong>For Nginx servers:</strong> Add these rules to your nginx.conf:';
echo '<pre style="background: #f5f5f5; padding: 10px;">';
if ( $combine_gallery_slug ) {
	echo 'location ~ ^/' . $combine_gallery_slug . '/([^/]+)/([0-9]+)/?$ {
    try_files $uri $uri/ /index.php?page_id=' . ( $combine_gallery_page_id ?: '[PAGE_ID]' ) . '&procedure_title=$1&case_id=$2;
}

location ~ ^/' . $combine_gallery_slug . '/([^/]+)/?$ {
    try_files $uri $uri/ /index.php?page_id=' . ( $combine_gallery_page_id ?: '[PAGE_ID]' ) . '&filter_procedure=$1;
}';
} else {
	echo '# Set combine_gallery_slug option first';
}
echo '</pre>';
echo '</li>';
echo '<li><strong>Clear all caches:</strong> CloudFlare, hosting cache, WordPress cache plugins</li>';
echo '<li><strong>Check hosting restrictions:</strong> Some hosts block custom rewrite rules</li>';
echo '</ol>';

// Additional debugging
echo '<h2>8. Additional Debug Info</h2>';
echo '<details>';
echo '<summary>WordPress Rewrite Object (click to expand)</summary>';
echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
echo 'use_trailing_slashes: ' . ( $wp_rewrite->use_trailing_slashes ? 'true' : 'false' ) . "\n";
echo 'permalink_structure: ' . $wp_rewrite->permalink_structure . "\n";
echo 'rewrite_base: ' . $wp_rewrite->root . "\n";
echo 'index: ' . $wp_rewrite->index . "\n";
echo '</pre>';
echo '</details>';

echo '<hr>';
echo '<p><a href="' . admin_url( 'options-permalink.php' ) . '">Go to Permalinks Settings</a> | ';
echo '<a href="' . admin_url( 'options-general.php?page=brag-book-gallery-settings' ) . '">Plugin Settings</a></p>';