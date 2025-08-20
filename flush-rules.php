<?php
/**
 * Flush Rewrite Rules Script
 * 
 * This script flushes WordPress rewrite rules to fix 404 errors.
 * Run this after making changes to rewrite rules.
 * 
 * Usage: Navigate to /wp-content/plugins/brag-book-gallery/flush-rules.php
 */

// Load WordPress
require_once dirname( __DIR__, 3 ) . '/wp-load.php';

// Check if user is logged in and is admin
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	die( 'You must be logged in as an administrator to run this script.' );
}

// Ensure our rewrite rules are registered first
if ( class_exists( '\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler' ) ) {
	\BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler::custom_rewrite_rules();
}

// Flush the rewrite rules
flush_rewrite_rules( true );

// Clear any rewrite notice transients
delete_transient( 'bragbook_show_rewrite_notice' );

// Output success message
?>
<!DOCTYPE html>
<html>
<head>
	<title>Rewrite Rules Flushed</title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			padding: 40px;
			max-width: 600px;
			margin: 0 auto;
		}
		.success {
			background: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
			padding: 15px;
			border-radius: 4px;
			margin-bottom: 20px;
		}
		.info {
			background: #d1ecf1;
			border: 1px solid #bee5eb;
			color: #0c5460;
			padding: 15px;
			border-radius: 4px;
			margin-bottom: 20px;
		}
		h1 { color: #333; }
		ul { line-height: 1.8; }
		a {
			color: #007cba;
			text-decoration: none;
		}
		a:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body>
	<h1>BRAGBook Gallery - Rewrite Rules Flushed</h1>
	
	<div class="success">
		✓ Rewrite rules have been successfully flushed!
	</div>
	
	<div class="info">
		<strong>Registered Query Variables:</strong>
		<ul>
			<li><code>procedure_title</code> - Used for case detail URLs</li>
			<li><code>case_id</code> - Used for individual case pages</li>
			<li><code>filter_procedure</code> - Used for procedure filtering</li>
			<li><code>filter_category</code> - Used for category filtering</li>
			<li><code>favorites_section</code> - Used for favorites pages</li>
		</ul>
	</div>
	
	<div class="info">
		<strong>Gallery Page Slugs Found:</strong>
		<ul>
			<?php
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
			if ( ! is_array( $gallery_slugs ) ) {
				$gallery_slugs = [ $gallery_slugs ];
			}
			
			if ( empty( $gallery_slugs ) || empty( $gallery_slugs[0] ) ) {
				echo '<li><em>No gallery page slugs configured</em></li>';
			} else {
				foreach ( $gallery_slugs as $slug ) {
					if ( ! empty( $slug ) ) {
						echo '<li><code>' . esc_html( $slug ) . '</code></li>';
					}
				}
			}
			?>
		</ul>
	</div>
	
	<div class="info">
		<strong>Sample Rewrite Rules (for first gallery slug):</strong>
		<ul>
			<?php
			$first_slug = ! empty( $gallery_slugs[0] ) ? $gallery_slugs[0] : 'gallery';
			?>
			<li>Procedure Filter: <code>/<?php echo esc_html( $first_slug ); ?>/procedure-name/</code></li>
			<li>Case Details: <code>/<?php echo esc_html( $first_slug ); ?>/procedure-name/123/</code></li>
		</ul>
	</div>
	
	<p>
		<strong>Next Steps:</strong>
	</p>
	<ul>
		<li>Test your gallery URLs to ensure they're working properly</li>
		<li>If you still see 404 errors, check your permalink settings</li>
		<li>Make sure your gallery page exists and has the [brag_book_gallery] shortcode</li>
	</ul>
	
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ); ?>">← Back to Settings</a> |
		<a href="<?php echo esc_url( home_url( '/' . $first_slug ) ); ?>">View Gallery →</a>
	</p>
</body>
</html>