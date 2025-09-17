# Post Meta Field Mapping

This document outlines the mapping between API data and WordPress post meta fields in the BRAGBook Gallery plugin.

## Overview

The plugin stores case data using two meta field formats:
- **New Format**: Prefixed with `brag_book_gallery_` (v3.0+)
- **Legacy Format**: Prefixed with `_case_` (backwards compatibility)

## API to Post Meta Field Mapping

### Basic Information

| API Field | New Meta Key | Legacy Meta Key | Data Type | Description |
|-----------|--------------|-----------------|-----------|-------------|
| `id` | `brag_book_gallery_api_id` | `_case_api_id` | int | Case ID from API |
| `patientId` | `brag_book_gallery_patient_id` | `_case_patient_id` | int | Patient identifier |
| `userId` | `brag_book_gallery_user_id` | `_case_user_id` | int | User/doctor ID |
| `orgId` | `brag_book_gallery_org_id` | `_case_org_id` | int | Organization ID |
| `emrId` | `brag_book_gallery_emr_id` | `_case_emr_id` | int | EMR system ID |

### Patient Demographics

| API Field | New Meta Key | Legacy Meta Key | Data Type | Description |
|-----------|--------------|-----------------|-----------|-------------|
| `age` | `brag_book_gallery_patient_age` | `_case_patient_age` | int | Patient age |
| `gender` | `brag_book_gallery_patient_gender` | `_case_patient_gender` | string | Patient gender |
| `ethnicity` | `brag_book_gallery_ethnicity` | `_case_ethnicity` | string | Patient ethnicity |
| `height` | `brag_book_gallery_height` | `_case_height` | int | Patient height value |
| `heightUnit` | `brag_book_gallery_height_unit` | `_case_height_unit` | string | Height unit (in/cm) |
| `weight` | `brag_book_gallery_weight` | `_case_weight` | int | Patient weight value |
| `weightUnit` | `brag_book_gallery_weight_unit` | `_case_weight_unit` | string | Weight unit (lbs/kg) |

### Procedure Information

| API Field | New Meta Key | Legacy Meta Key | Data Type | Description |
|-----------|--------------|-----------------|-----------|-------------|
| `technique` | `brag_book_gallery_technique` | `_case_technique` | string | Surgical technique used |
| `revisionSurgery` | `brag_book_gallery_revision_surgery` | `_case_revision_surgery` | string | Revision surgery details |
| `after1Timeframe` | `brag_book_gallery_after1_timeframe` | `_case_after1_timeframe` | int | First follow-up timeframe |
| `after1Unit` | `brag_book_gallery_after1_unit` | `_case_after1_unit` | string | First follow-up unit |
| `after2Timeframe` | `brag_book_gallery_after2_timeframe` | `_case_after2_timeframe` | int | Second follow-up timeframe |
| `after2Unit` | `brag_book_gallery_after2_unit` | `_case_after2_unit` | string | Second follow-up unit |
| `procedureIds` | `brag_book_gallery_procedure_ids` | `_case_procedure_ids` | string | Comma-separated procedure IDs |

### Case Settings & Flags

| API Field | New Meta Key | Legacy Meta Key | Data Type | Description |
|-----------|--------------|-----------------|-----------|-------------|
| `qualityScore` | `brag_book_gallery_quality_score` | `_case_quality_score` | int | Image quality rating |
| `approvedForSocial` | `brag_book_gallery_approved_for_social` | `_case_approved_for_social` | bool | Social media approval |
| `isForTablet` | `brag_book_gallery_is_for_tablet` | `_case_is_for_tablet` | bool | Tablet display approval |
| `isForWebsite` | `brag_book_gallery_is_for_website` | `_case_is_for_website` | bool | Website display approval |
| `draft` | `brag_book_gallery_draft` | `_case_draft` | bool | Draft status |
| `noWatermark` | `brag_book_gallery_no_watermark` | `_case_no_watermark` | bool | Watermark exemption |
| `details` | `brag_book_gallery_notes` | `_case_notes` | text | Case notes/details |

### SEO Data

| API Field | New Meta Key | Legacy Meta Key | Data Type | Description |
|-----------|--------------|-----------------|-----------|-------------|
| `caseDetails[].seoSuffixUrl` | `brag_book_gallery_seo_suffix_url` | `_case_seo_suffix_url` | string | URL suffix for SEO |
| `caseDetails[].seoHeadline` | `brag_book_gallery_seo_headline` | `_case_seo_headline` | string | SEO headline/title |
| `caseDetails[].seoPageTitle` | `brag_book_gallery_seo_page_title` | `_case_seo_page_title` | string | Page title for SEO |
| `caseDetails[].seoPageDescription` | `brag_book_gallery_seo_page_description` | `_case_seo_page_description` | text | Meta description for SEO |

### Image Data

| API Field | New Meta Key | Legacy Meta Key | Data Type | Description |
|-----------|--------------|-----------------|-----------|-------------|
| `photoSets[].beforeLocationUrl` | `brag_book_gallery_case_before_url` | Stored in `image_url_sets` | textarea | Before image URLs (one per line with semicolons) |
| `photoSets[].afterLocationUrl1` | `brag_book_gallery_case_after_url1` | Stored in `image_url_sets` | textarea | First after image URLs (one per line with semicolons) |
| `photoSets[].afterLocationUrl2` | `brag_book_gallery_case_after_url2` | Stored in `image_url_sets` | textarea | Second after image URLs (one per line with semicolons) |
| `photoSets[].afterLocationUrl3` | `brag_book_gallery_case_after_url3` | Stored in `image_url_sets` | textarea | Third after image URLs (one per line with semicolons) |
| `photoSets[].postProcessedImageLocation` | `brag_book_gallery_case_post_processed_url` | `_case_post_processed_image_url` | textarea | Post-processed image URLs (one per line with semicolons) |
| `photoSets[].highResPostProcessedImageLocation` | `brag_book_gallery_case_high_res_url` | `_case_high_res_post_processed_image_url` | textarea | High-res processed URLs (one per line with semicolons) |
| `photoSets[].seoAltText` | N/A | `_case_seo_alt_text` | string | Alt text for images |
| `photoSets[].isNude` | N/A | `_case_is_nude` | bool | Nudity flag for images |

### Image Collections

| Meta Key | Legacy Meta Key | Data Type | Description |
|----------|-----------------|-----------|-------------|
| `brag_book_gallery_image_url_sets` | `_case_image_url_sets` | array | Collection of all image URL sets |
| `brag_book_gallery_images` | `_case_gallery_images` | array | Downloaded gallery images |

## Data Sync Specific Fields

These fields are specific to the Data_Sync class and handle multi-procedure cases and sync tracking.

### Multi-Procedure Case Management

| Meta Key | Data Type | Description |
|----------|-----------|-------------|
| `_original_case_id` | int | Original case ID before procedure splitting |
| `_procedure_id` | int | Specific procedure ID for multi-procedure cases |
| `_procedure_index` | int | Index of procedure in multi-procedure case |
| `_case_synced_at` | datetime | Timestamp of last sync operation |

### Procedure Details

| Meta Key | Data Type | Description |
|----------|-----------|-------------|
| `_case_procedure_details` | json | Full procedure details from API |
| `_case_procedure_detail_{procedure_id}_{detail_key}` | mixed | Individual procedure detail fields |

### System Data

| Meta Key | Data Type | Description |
|----------|-----------|-------------|
| `_case_api_response` | array | Complete API response data |

## Attachment Meta Fields

For downloaded images, additional meta is stored on attachment posts:

| Meta Key | Data Type | Description |
|----------|-----------|-------------|
| `_case_post_id` | int | Links attachment to case post |
| `_case_source_url` | string | Original image URL from API |
| `_case_downloaded_date` | datetime | When image was downloaded |

## Data Flow Overview

1. **API Response** → Raw case data from BRAGBook API
2. **Field Mapping** → API fields mapped to post meta keys
3. **Dual Storage** → Both new and legacy formats saved for compatibility
4. **Multi-Procedure** → Cases with multiple procedures split into separate posts
5. **Image Processing** → Images downloaded and stored as attachments
6. **Taxonomy Assignment** → Procedures assigned as custom taxonomy terms

## Usage in Code

### Retrieving Case Data
```php
// New format (recommended)
$case_id = get_post_meta($post_id, 'brag_book_gallery_api_id', true);
$patient_age = get_post_meta($post_id, 'brag_book_gallery_patient_age', true);

// Legacy format (backwards compatibility)
$case_id = get_post_meta($post_id, '_case_api_id', true);
$patient_age = get_post_meta($post_id, '_case_patient_age', true);
```

### Image URL Sets
```php
// Legacy format (array of URL sets)
$image_sets = get_post_meta($post_id, 'brag_book_gallery_image_url_sets', true);
foreach ($image_sets as $set) {
    $before_url = $set['before_url'];
    $after_url1 = $set['after_url1'];
    // ... process images
}

// New format (textarea with semicolon-separated URLs)
$before_urls = get_post_meta($post_id, 'brag_book_gallery_case_before_url', true);
$url_list = explode("\n", $before_urls);
foreach ($url_list as $url_line) {
    $url = rtrim($url_line, ';'); // Remove semicolon
    if (!empty($url)) {
        // Process individual URL
    }
}
```

### Multi-Procedure Cases
```php
$original_case_id = get_post_meta($post_id, '_original_case_id', true);
$procedure_id = get_post_meta($post_id, '_procedure_id', true);

if ($original_case_id && $procedure_id) {
    // This is a split procedure case
    // Original case: $original_case_id
    // Specific procedure: $procedure_id
}
```

## Migration Notes

- All new installations use the `brag_book_gallery_` prefix
- Existing installations maintain both formats during updates
- Legacy `_case_` prefixed fields will be maintained indefinitely for backwards compatibility
- When querying, prefer the new format but fall back to legacy if needed