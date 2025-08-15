<?php
/**
 * Check gallery page setup
 */

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if user is logged in and has admin capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You do not have permission to access this page.' );
}

echo '<h2>Gallery Page Setup Check</h2>';

// Check combine_gallery_slug
$combine_gallery_slug = get_option( 'combine_gallery_slug' );
$combine_gallery_page_id = get_option( 'combine_gallery_page_id' );

echo '<h3>Current Settings:</h3>';
echo '<ul>';
echo '<li><strong>combine_gallery_slug:</strong> ' . esc_html( $combine_gallery_slug ?: '(not set)' ) . '</li>';
echo '<li><strong>combine_gallery_page_id:</strong> ' . esc_html( $combine_gallery_page_id ?: '(not set)' ) . '</li>';
echo '</ul>';

// Check if there's an actual page with slug "before-after"
if ( $combine_gallery_slug ) {
	$page = get_page_by_path( $combine_gallery_slug );
	if ( $page ) {
		echo '<p style="color: green;">✓ Found page with slug "' . esc_html( $combine_gallery_slug ) . '" (ID: ' . $page->ID . ', Title: ' . esc_html( $page->post_title ) . ')</p>';
		
		// Check if it has the shortcode
		if ( strpos( $page->post_content, '[brag_book_gallery' ) !== false ) {
			echo '<p style="color: green;">✓ Page contains [brag_book_gallery] shortcode</p>';
		} else {
			echo '<p style="color: orange;">⚠ Page does NOT contain [brag_book_gallery] shortcode</p>';
			echo '<p>You need to add the shortcode to this page for it to work.</p>';
		}
	} else {
		echo '<p style="color: red;">✗ No page found with slug "' . esc_html( $combine_gallery_slug ) . '"</p>';
		echo '<p><strong>This is the problem!</strong> You need to either:</p>';
		echo '<ol>';
		echo '<li>Create a page with the slug "' . esc_html( $combine_gallery_slug ) . '" and add [brag_book_gallery] shortcode to it</li>';
		echo '<li>OR use an existing page slug that has the [brag_book_gallery] shortcode</li>';
		echo '</ol>';
	}
}

// Find all pages with the shortcode
echo '<h3>Pages with [brag_book_gallery] shortcode:</h3>';
global $wpdb;
$pages_with_shortcode = $wpdb->get_results(
	"SELECT ID, post_name, post_title, post_status 
	FROM {$wpdb->posts} 
	WHERE post_content LIKE '%[brag_book_gallery%' 
	AND post_type = 'page'
	ORDER BY post_status DESC, post_title ASC"
);

if ( ! empty( $pages_with_shortcode ) ) {
	echo '<table style="border-collapse: collapse; width: 100%;">';
	echo '<tr><th style="border: 1px solid #ccc; padding: 8px;">ID</th><th style="border: 1px solid #ccc; padding: 8px;">Title</th><th style="border: 1px solid #ccc; padding: 8px;">Slug</th><th style="border: 1px solid #ccc; padding: 8px;">Status</th><th style="border: 1px solid #ccc; padding: 8px;">URL</th></tr>';
	foreach ( $pages_with_shortcode as $page ) {
		$url = get_permalink( $page->ID );
		echo '<tr>';
		echo '<td style="border: 1px solid #ccc; padding: 8px;">' . $page->ID . '</td>';
		echo '<td style="border: 1px solid #ccc; padding: 8px;">' . esc_html( $page->post_title ) . '</td>';
		echo '<td style="border: 1px solid #ccc; padding: 8px;"><code>' . esc_html( $page->post_name ) . '</code></td>';
		echo '<td style="border: 1px solid #ccc; padding: 8px;">' . $page->post_status . '</td>';
		echo '<td style="border: 1px solid #ccc; padding: 8px;"><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a></td>';
		echo '</tr>';
	}
	echo '</table>';
	
	echo '<h4>Recommendation:</h4>';
	if ( ! empty( $pages_with_shortcode ) ) {
		$first_page = $pages_with_shortcode[0];
		if ( $first_page->post_status === 'publish' ) {
			echo '<p>You should set <strong>combine_gallery_slug</strong> to: <code>' . esc_html( $first_page->post_name ) . '</code></p>';
			echo '<p>Or create a new page with slug <code>before-after</code> if you want to use that specific URL.</p>';
		}
	}
} else {
	echo '<p style="color: red;">No pages found with the [brag_book_gallery] shortcode!</p>';
	echo '<p>You need to create a page and add the [brag_book_gallery] shortcode to it.</p>';
}

// Show current rewrite rules
echo '<h3>Current Rewrite Rules for "' . esc_html( $combine_gallery_slug ?: 'gallery' ) . '":</h3>';
global $wp_rewrite;
$rules = $wp_rewrite->wp_rewrite_rules();
echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">';
$found = false;
foreach ( $rules as $pattern => $query ) {
	if ( $combine_gallery_slug && strpos( $pattern, $combine_gallery_slug ) === 0 ) {
		echo htmlspecialchars( $pattern ) . "\n    => " . htmlspecialchars( $query ) . "\n\n";
		$found = true;
	}
}
if ( ! $found ) {
	echo "No rewrite rules found for '" . esc_html( $combine_gallery_slug ) . "'";
}
echo '</pre>';

// Actions
echo '<h3>Quick Actions:</h3>';

// Create page form
if ( $combine_gallery_slug && ! get_page_by_path( $combine_gallery_slug ) ) {
	echo '<form method="post" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">';
	echo '<h4>Create Page with slug "' . esc_html( $combine_gallery_slug ) . '"</h4>';
	echo '<input type="hidden" name="create_page" value="1">';
	echo '<input type="hidden" name="page_slug" value="' . esc_attr( $combine_gallery_slug ) . '">';
	echo '<label>Page Title: <input type="text" name="page_title" value="Gallery" style="margin-left: 10px;"></label><br><br>';
	echo '<button type="submit" class="button button-primary">Create Page with [brag_book_gallery] shortcode</button>';
	echo '</form>';
	
	if ( isset( $_POST['create_page'] ) && $_POST['page_slug'] === $combine_gallery_slug ) {
		$page_title = sanitize_text_field( $_POST['page_title'] ?: 'Gallery' );
		$page_id = wp_insert_post( [
			'post_title' => $page_title,
			'post_name' => $combine_gallery_slug,
			'post_content' => '[brag_book_gallery]',
			'post_status' => 'publish',
			'post_type' => 'page',
		] );
		
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			// Update the combine_gallery_page_id option
			update_option( 'combine_gallery_page_id', $page_id );
			
			// Flush rewrite rules
			flush_rewrite_rules( true );
			
			echo '<p style="color: green; font-weight: bold;">✓ Page created successfully! <a href="' . get_permalink( $page_id ) . '" target="_blank">View Page</a></p>';
			echo '<script>setTimeout(function() { window.location.reload(); }, 2000);</script>';
		} else {
			echo '<p style="color: red;">Error creating page.</p>';
		}
	}
}

// Flush rewrite rules button
echo '<form method="post" style="margin: 20px 0;">';
echo '<input type="hidden" name="flush_rules" value="1">';
echo '<button type="submit" class="button">Flush Rewrite Rules</button>';
echo '</form>';

if ( isset( $_POST['flush_rules'] ) ) {
	flush_rewrite_rules( true );
	echo '<p style="color: green;">✓ Rewrite rules flushed!</p>';
}

echo '<hr>';
echo '<p><a href="' . admin_url( 'options-general.php?page=brag-book-gallery-settings' ) . '">Go to Plugin Settings</a> | ';
echo '<a href="' . admin_url( 'options-permalink.php' ) . '">Go to Permalinks</a> | ';
echo '<a href="' . home_url() . '">Go to Homepage</a></p>';