<?php
/**
 * Case Report Generator
 *
 * Generates a detailed report of all imported cases with their actual data.
 * This will help verify sync accuracy and identify any discrepancies.
 */

// WordPress bootstrap
require_once('/Users/karladams/Local Sites/bragbook/app/public/wp-config.php');

global $wpdb;

echo "=== BRAG Book Gallery Case Report ===\n\n";

// Get actual case count from database
$case_count_query = $wpdb->prepare("
    SELECT COUNT(*) as total_cases
    FROM {$wpdb->posts}
    WHERE post_type = %s
    AND post_status = 'publish'
", 'brag_book_case');

$actual_case_count = $wpdb->get_var($case_count_query);
echo "ACTUAL CASES IN DATABASE: {$actual_case_count}\n\n";

// Get all cases with their metadata
$cases_query = $wpdb->prepare("
    SELECT
        p.ID as wordpress_id,
        p.post_title,
        p.post_status,
        p.post_date,
        m1.meta_value as case_id,
        m2.meta_value as procedure_id
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'brag_book_gallery_case_id'
    LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = 'brag_book_gallery_procedure_id'
    WHERE p.post_type = %s
    AND p.post_status = 'publish'
    ORDER BY p.ID
", 'brag_book_case');

$cases = $wpdb->get_results($cases_query);

echo "=== DETAILED CASE REPORT ===\n";
echo "WordPress ID | Case ID | Procedure ID | Title | Created Date\n";
echo "-------------|---------|--------------|-------|-------------\n";

foreach ($cases as $case) {
    printf(
        "%12s | %7s | %12s | %-30s | %s\n",
        $case->wordpress_id,
        $case->case_id ?: 'N/A',
        $case->procedure_id ?: 'N/A',
        substr($case->post_title, 0, 30),
        $case->post_date
    );
}

// Get sync history for comparison
echo "\n\n=== SYNC HISTORY COMPARISON ===\n";

$sync_logs = $wpdb->get_results("
    SELECT
        id,
        sync_type,
        status,
        processed,
        details,
        created_at
    FROM {$wpdb->prefix}brag_book_sync_log
    ORDER BY created_at DESC
    LIMIT 5
");

foreach ($sync_logs as $log) {
    $details = json_decode($log->details, true);
    $reported_cases = $details['total_cases_processed'] ?? 0;

    echo "Sync ID: {$log->id} | Type: {$log->sync_type} | Status: {$log->status}\n";
    echo "Date: {$log->created_at}\n";
    echo "Reported Cases: {$reported_cases} | Processed: {$log->processed}\n";
    echo "---\n";
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Actual cases in database: {$actual_case_count}\n";
if (!empty($sync_logs)) {
    $latest_sync = $sync_logs[0];
    $latest_details = json_decode($latest_sync->details, true);
    $latest_reported = $latest_details['total_cases_processed'] ?? 0;
    echo "Latest sync reported: {$latest_reported}\n";
    echo "Difference: " . ($latest_reported - $actual_case_count) . "\n";
}

echo "\nReport completed.\n";