# BRAG Book Gallery API Overview

## Table of Contents
1. [API Base Information](#api-base-information)
2. [Authentication](#authentication)
3. [Endpoints Summary](#endpoints-summary)
4. [Detailed Endpoint Documentation](#detailed-endpoint-documentation)
5. [Data Models](#data-models)
6. [Common Patterns](#common-patterns)
7. [Error Handling](#error-handling)

## API Base Information

- **Base URL**: `https://app.bragbookgallery.com`
- **Protocol**: HTTPS
- **Format**: JSON
- **API Version**: Plugin Combine APIs v2.1.0

## Authentication

The API uses token-based authentication with the following methods:

1. **API Token in Query Parameters**: `apiToken=YOUR_TOKEN`
2. **API Token in Request Body**: `"apiTokens": ["YOUR_TOKEN"]`
3. **Headers for Image Optimization**:
   - `x-api-token: YOUR_TOKEN`
   - `x-plugin-version: VERSION`

## Endpoints Summary

| Endpoint | Method | Purpose | Authentication |
|----------|--------|---------|----------------|
| `/api/plugin/carousel` | GET | Get carousel data for featured cases | Query param |
| `/api/plugin/optimize-image` | GET | Optimize and transform images | Headers |
| `/api/plugin/combine/favorites/add` | POST | Add case to user favorites | Body |
| `/api/plugin/combine/favorites/list` | POST | Get user's favorite cases | Body |
| `/api/plugin/combine/sidebar` | POST | Get navigation menu structure | Body |
| `/api/plugin/combine/cases` | POST | Get paginated case listings | Body |
| `/api/plugin/combine/cases/{id}` | POST | Get specific case details | Body |
| `/api/plugin/combine/filters` | POST | Get available filter options | Body |
| `/api/plugin/tracker` | POST | Track plugin usage analytics | Body |
| `/api/plugin/sitemap` | POST | Generate sitemap data | Body |
| `/api/plugin/consultations` | POST | Submit consultation request | Query param |

## Detailed Endpoint Documentation

### 1. Carousel Endpoint
**GET** `/api/plugin/carousel`

Retrieves featured cases for carousel display.

**Query Parameters:**
- `websitePropertyId` (required): Property identifier
- `apiToken` (required): Authentication token
- `procedureId` (optional): Filter by procedure
- `memberId` (optional): Filter by member
- `start` (optional): Pagination start (default: 1)
- `limit` (optional): Results per page (default: 10)

**Response Structure:**
```json
{
  "success": true,
  "data": [
    {
      "id": "case_id",
      "details": "HTML description",
      "photoSets": [
        {
          "originalBeforeLocation": "URL",
          "postProcessedImageLocation": "URL",
          "highResPostProcessedImageLocation": "URL",
          "seoAltText": "string or null"
        }
      ],
      "caseDetails": []
    }
  ]
}
```

### 2. Image Optimization Endpoint
**GET** `/api/plugin/optimize-image`

Optimizes and transforms images on-the-fly.

**Headers:**
- `x-api-token`: Authentication token
- `x-plugin-version`: Plugin version

**Query Parameters:**
- `url` (required): Source image URL
- `quality` (optional): Image quality (small/medium/large)
- `format` (optional): Output format (png/jpg/webp)

### 3. Favorites Management

#### Add to Favorites
**POST** `/api/plugin/combine/favorites/add`

**Request Body:**
```json
{
  "apiTokens": ["token"],
  "websitePropertyIds": [111],
  "email": "user@example.com",    // Required
  "phone": "1234567890",           // Required
  "name": "User Name",             // Required
  "caseId": 17662                  // Required
}
```

**Response:**
```json
{
  "success": true,
  "totalFavorites": 5
}
```

#### List Favorites
**POST** `/api/plugin/combine/favorites/list`

**Request Body:**
```json
{
  "apiTokens": ["token"],
  "websitePropertyIds": [111],
  "email": "user@example.com"  // Optional: filter by email
}
```

### 4. Navigation Sidebar
**POST** `/api/plugin/combine/sidebar`

Returns hierarchical navigation structure organized by procedure categories.

**Request Body:**
```json
{
  "apiTokens": ["token"]
}
```

**Response Structure:**
```json
{
  "success": true,
  "data": [
    {
      "name": "Category Name",
      "procedures": [
        {
          "ids": [123],
          "name": "Procedure Name",
          "description": "SEO description",
          "nudity": false,
          "slugName": "procedure-slug",
          "totalCase": 45
        }
      ],
      "totalCase": 150
    }
  ]
}
```

### 5. Cases Endpoints

#### Get Cases (Paginated)
**POST** `/api/plugin/combine/cases`

**Request Body:**
```json
{
  "apiTokens": ["token"],
  "websitePropertyIds": [111],
  "count": 1,                      // Page number
  "procedureIds": [6851],          // Optional: filter by procedures
  "memberId": 133,                 // Optional: filter by member
  "gender": "male",                // Optional: static filter
  "age": 1,                        // Optional: age range ID
  "height": 2,                     // Optional: height range ID
  "weight": 3,                     // Optional: weight range ID
  "ethnicity": "White",            // Optional: ethnicity filter
  "filters": {                     // Optional: dynamic filters
    "Implant Type": "Silicone Gel",
    "Implant Base": "Classic"
  }
}
```

#### Get Case by ID
**POST** `/api/plugin/combine/cases/{caseId}`

**URL Parameters:**
- `caseId`: The case identifier

**Query Parameters:**
- `seoSuffixUrl` (optional): SEO suffix for URL

**Request Body:**
```json
{
  "apiTokens": ["token"],
  "procedureIds": [6851],
  "websitePropertyIds": [111],
  "memberId": 133              // Optional
}
```

### 6. Filters Endpoint
**POST** `/api/plugin/combine/filters`

Returns available filter options for case browsing.

**Request Body:**
```json
{
  "apiTokens": ["token"],
  "procedureIds": [6851],
  "websitePropertyIds": [111]
}
```

**Response Structure:**
```json
{
  "success": true,
  "data": {
    "staticFilter": {
      "height": [
        {"label": "5'0\" - 5'2\"", "value": 1}
      ],
      "weight": [
        {"label": "100-120 lbs", "value": 1}
      ],
      "gender": [
        {"label": "Female", "value": "female"}
      ],
      "ethnicity": [
        {"label": "White", "value": "White"}
      ],
      "age": [
        {"label": "18-25", "value": 1}
      ]
    },
    "dynamicFilter": {}
  }
}
```

### 7. Analytics Tracker
**POST** `/api/plugin/tracker`

Tracks plugin usage and analytics.

**Request Body:**
```json
{
  "data": [
    {
      "version": "2.5.0",                              // Required
      "domain": "https://example.com",                 // Required
      "galleryPage": "prod-page",                      // Required
      "apiToken": "token",
      "websitePropertyId": 111
    }
  ]
}
```

### 8. Sitemap Generation
**POST** `/api/plugin/sitemap`

Generates sitemap data for SEO purposes.

**Request Body:**
```json
{
  "apiTokens": ["token"],
  "websitePropertyIds": [111]
}
```

**Response Structure:**
```json
{
  "success": true,
  "data": [
    [
      {
        "url": "/category-page",
        "updatedAt": null
      },
      {
        "url": "/case/12345",
        "updatedAt": "2024-01-15"
      }
    ]
  ]
}
```

### 9. Consultations
**POST** `/api/plugin/consultations`

Submit a consultation request.

**Query Parameters:**
- `apiToken` (required): Authentication token
- `websitepropertyId` (required): Property ID

**Request Body:**
```json
{
  "email": "user@example.com",
  "phone": "1234567890",
  "name": "User Name",
  "details": "Consultation details"
}
```

## Data Models

### Case Object
```typescript
interface Case {
  id: number;
  userId: number;
  orgId: number;
  details: string;
  ethnicity: string;
  gender: string;
  height: number;
  weight: number;
  age: number;
  procedureIds: number[];
  technique: string;
  after1Timeframe: string;
  uploadUrls: string[];
  draft: boolean;
  approvedForSocial: boolean;
  isForWebsite: boolean;
  photoSets: PhotoSet[];
  caseDetails: CaseDetail[];
}
```

### PhotoSet Object
```typescript
interface PhotoSet {
  id: number;
  originalBeforeLocation: string;
  originalAfterLocation: string;
  postProcessedImageLocation: string;
  highResPostProcessedImageLocation: string;
  watermarkedStatus: boolean;
  watermarkSettings: object;
  seoAltText: string | null;
  optimizedImages: object;
}
```

### Procedure Object
```typescript
interface Procedure {
  ids: number[];
  name: string;
  description: string;
  nudity: boolean;
  slugName: string;
  totalCase: number;
}
```

## Common Patterns

### 1. Authentication Pattern
- Most endpoints accept multiple API tokens: `"apiTokens": ["token1", "token2"]`
- Support for multiple website properties: `"websitePropertyIds": [111, 222]`

### 2. Response Pattern
All responses follow a consistent structure:
```json
{
  "success": boolean,
  "data": object | array,
  "message": string (on error)
}
```

### 3. Image URL Structure
Images follow a consistent URL pattern:
- Original: `https://domain/assets/gallery/{orgId}/original-image.jpg`
- Processed: `https://domain/assets/gallery/{orgId}/processed-image.webp`
- High-res: `https://domain/assets/gallery/{orgId}/highres-image.webp`

### 4. SEO Optimization
- All cases include SEO-friendly URLs with slugs
- Alt text support for images
- Structured data for search engines
- Sitemap generation support

### 5. Pagination Pattern
Pagination uses `count` (page number) and `limit` (items per page):
```json
{
  "count": 1,      // Page number (1-based)
  "limit": 10      // Items per page
}
```

## Error Handling

### Common Error Responses

#### Authentication Error
```json
{
  "success": false,
  "message": "Invalid API token"
}
```

#### Validation Error
```json
{
  "success": false,
  "message": "Required field 'email' is missing"
}
```

#### Not Found Error
```json
{
  "success": false,
  "message": "Case not found"
}
```

### HTTP Status Codes
- `200 OK`: Successful request
- `400 Bad Request`: Invalid parameters
- `401 Unauthorized`: Authentication failed
- `404 Not Found`: Resource not found
- `500 Internal Server Error`: Server error

## Rate Limiting

The API implements rate limiting to prevent abuse:
- Default timeout: 30 seconds per request
- Caching: 5-30 minute cache duration for responses
- Retry mechanism: Exponential backoff on failures

## Best Practices

1. **Cache Responses**: Implement client-side caching for frequently accessed data
2. **Batch Requests**: Use array parameters to request multiple items in one call
3. **Handle Errors Gracefully**: Always check the `success` field before processing data
4. **Use Pagination**: For large datasets, use pagination to improve performance
5. **Optimize Images**: Use the image optimization endpoint for better performance
6. **SEO URLs**: Always use SEO-friendly URLs when available
7. **Monitor Usage**: Track API usage with the tracker endpoint

## Plugin Integration

The BRAG Book Gallery WordPress plugin uses these endpoints to:
- Display before/after photo galleries
- Manage user favorites
- Generate navigation menus
- Handle consultation requests
- Optimize images for web display
- Generate SEO sitemaps
- Track usage analytics

## Version History

- **Current Version**: Plugin Combine APIs v2.1.0
- **Plugin Compatibility**: BRAG Book Gallery v3.0.0+
- **Minimum PHP Version**: 8.2
- **WordPress Compatibility**: 6.8+

## Support

For API support and documentation:
- Documentation: https://www.bragbookgallery.com/docs/
- Support: https://www.bragbookgallery.com/support/
- GitHub: https://github.com/bragbook2/brag-book-gallery
