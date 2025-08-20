<?php
/**
 * Quick script to create gallery page and set options
 * Run this from WordPress admin or via browser
 */

// Ensure we're in WordPress environment
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../wp-config.php';
}

// Create the gallery page
$page_data = array(
    'post_title'    => 'Before & After Gallery',
    'post_name'     => 'gallery',
    'post_content'  => '[brag_book_gallery]',
    'post_status'   => 'publish',
    'post_type'     => 'page',
    'post_author'   => 1,
    'comment_status' => 'closed',
    'ping_status'   => 'closed',
);

// Check if page already exists
$existing_page = get_page_by_path('gallery');
if ($existing_page) {
    echo "Gallery page already exists (ID: {$existing_page->ID})<br>";
    $page_id = $existing_page->ID;
} else {
    $page_id = wp_insert_post($page_data);
    if (is_wp_error($page_id)) {
        echo "Error creating page: " . $page_id->get_error_message() . "<br>";
        exit;
    }
    echo "Gallery page created successfully (ID: $page_id)<br>";
}

// Set the plugin options using the helper class
if (class_exists('BRAGBookGallery\Includes\Core\Slug_Helper')) {
    BRAGBookGallery\Includes\Core\Slug_Helper::set_primary_slug('gallery');
    echo "Gallery slug set using Slug_Helper<br>";
} else {
    // Fallback to direct option setting
    update_option('brag_book_gallery_page_slug', array('gallery'));
    echo "Gallery slug set directly<br>";
}

update_option('brag_book_gallery_page_id', $page_id);
echo "Gallery page ID option set<br>";

// Flush rewrite rules
flush_rewrite_rules(true);
echo "Rewrite rules flushed<br>";

echo "<br><strong>Setup complete!</strong><br>";
echo "Gallery page URL: " . get_permalink($page_id) . "<br>";
echo "Test URL: " . home_url('/gallery/brazilian-butt-lift/') . "<br>";