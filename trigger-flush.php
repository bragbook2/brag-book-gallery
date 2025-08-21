<?php
/**
 * Trigger rewrite rules flush for BRAGBook Gallery plugin
 * 
 * Access this file in your browser to trigger a rewrite rules flush on next page load
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You must be logged in as an administrator to flush rewrite rules.' );
}

// Set the option to trigger flush on next init
update_option( 'brag_book_gallery_flush_rewrite_rules', true );

// Also flush immediately
flush_rewrite_rules();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Rewrite Rules Flushed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
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
    <div class="container">
        <h1>✅ Rewrite Rules Flushed Successfully!</h1>
        
        <div class="success">
            The rewrite rules have been flushed. The plugin should now handle case URLs correctly.
        </div>
        
        <div class="info">
            <strong>Test it out:</strong><br>
            Try accessing a case URL like: <a href="<?php echo home_url('/facelift/16480'); ?>" target="_blank"><?php echo home_url('/facelift/16480'); ?></a>
        </div>
        
        <p><a href="<?php echo admin_url(); ?>">← Back to WordPress Admin</a></p>
        
        <h2>Registered Gallery Rewrite Rules:</h2>
        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
<?php
global $wp_rewrite;
$rules = $wp_rewrite->rewrite_rules();

// Show gallery-related rules
foreach ( $rules as $pattern => $query ) {
    if ( strpos( $query, 'procedure_title' ) !== false ||
         strpos( $query, 'case_id' ) !== false ||
         strpos( $pattern, 'gallery' ) !== false ||
         strpos( $pattern, 'facelift' ) !== false ) {
        echo "Pattern: $pattern\n";
        echo "Query:   $query\n\n";
    }
}
?>
        </pre>
    </div>
</body>
</html>