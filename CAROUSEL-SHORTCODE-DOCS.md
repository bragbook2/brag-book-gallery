# BRAGBook Gallery Carousel Shortcode Documentation

## Current Shortcode: `[brag_book_carousel]`

### Basic Usage
```
[brag_book_carousel]
```

### Full Parameters Example
```
[brag_book_carousel 
    api_token="your-api-token" 
    website_property_id="89" 
    procedure="nonsurgical-facelift" 
    limit="10" 
    start="1" 
    show_controls="true" 
    show_pagination="true" 
    auto_play="false"
    class="custom-carousel-class"]
```

### Parameters

| Parameter | Description | Default | Required |
|-----------|-------------|---------|----------|
| `api_token` | API authentication token | First token from settings | No (uses settings) |
| `website_property_id` | Property ID for the website | First ID from settings | No (uses settings) |
| `procedure` or `procedure_id` | Filter by specific procedure (slug or ID) | None (all procedures) | No |
| `limit` | Number of cases to display | 10 | No |
| `start` | Starting index (1-based) | 1 | No |
| `member_id` | Filter by specific member | None | No |
| `show_controls` | Show navigation arrows | "true" | No |
| `show_pagination` | Show dot pagination | "true" | No |
| `auto_play` | Enable auto-play | "false" | No |
| `class` | Additional CSS classes | "" | No |

### Common Examples

#### Display 5 cases from a specific procedure
```
[brag_book_carousel procedure="facelift" limit="5"]
```

#### Carousel without controls
```
[brag_book_carousel show_controls="false" show_pagination="false"]
```

#### Auto-playing carousel
```
[brag_book_carousel auto_play="true" limit="8"]
```

---

## Legacy Shortcode Support: `[bragbook_carousel_shortcode]`

The plugin maintains backwards compatibility with the old shortcode format.

### Legacy Format
```
[bragbook_carousel_shortcode 
    procedure="nonsurgical-facelift" 
    start="1" 
    limit="10" 
    title="0" 
    details="0" 
    website_property_id="89"]
```

### Legacy Parameters Mapping

| Legacy Parameter | Description | Maps To |
|-----------------|-------------|---------|
| `procedure` | Procedure slug | `procedure_id` |
| `start` | Starting index | `start` |
| `limit` | Number of items | `limit` |
| `title` | Show title (0=hide, 1=show) | `show_controls` |
| `details` | Show details (0=hide, 1=show) | `show_pagination` |
| `website_property_id` | Property ID | `website_property_id` |

### Legacy Examples

#### Old format (still works)
```
[bragbook_carousel_shortcode procedure="breast-augmentation" limit="6" title="1" details="1"]
```

#### Equivalent new format
```
[brag_book_carousel procedure="breast-augmentation" limit="6" show_controls="true" show_pagination="true"]
```

---

## Configuration Requirements

### Required Settings
Before using the carousel shortcode, ensure these are configured in the plugin settings:

1. **API Token** - Set in JavaScript Settings
2. **Website Property ID** - Set in JavaScript Settings
3. **Gallery Page** - Must exist with the main gallery shortcode

### Optimal Settings for Carousel

For best carousel performance:
- Enable carousel assets in JavaScript Settings
- Set appropriate cache duration (recommended: 1 hour)
- Enable lazy loading for images

### Troubleshooting

#### Carousel not displaying
1. Check API token is configured
2. Verify website property ID is set
3. Ensure procedure slug is correct (if filtering)
4. Check browser console for JavaScript errors

#### No data showing
1. Verify API endpoint is accessible
2. Check cache settings - try clearing cache
3. Ensure proper permissions for API access

#### Styling issues
1. Check for theme CSS conflicts
2. Verify carousel CSS is loading
3. Use browser inspector to check for style overrides

---

## JavaScript API

The carousel exposes these methods via JavaScript:

```javascript
// Access carousel instance
const carousel = document.querySelector('.brag-book-carousel');

// Available methods (if carousel is initialized)
carousel.bragBookCarousel.next();     // Go to next slide
carousel.bragBookCarousel.prev();     // Go to previous slide
carousel.bragBookCarousel.goTo(3);    // Go to specific slide (0-indexed)
carousel.bragBookCarousel.play();     // Start auto-play
carousel.bragBookCarousel.pause();    // Pause auto-play
```

---

## CSS Customization

Target these classes for custom styling:

```css
/* Main carousel container */
.brag-book-carousel { }

/* Carousel track */
.brag-book-carousel-track { }

/* Individual items */
.brag-book-carousel-item { }

/* Navigation controls */
.brag-book-carousel-prev { }
.brag-book-carousel-next { }

/* Pagination dots */
.brag-book-carousel-pagination { }
.brag-book-carousel-dot { }
.brag-book-carousel-dot.active { }

/* Custom class if provided */
.your-custom-class { }
```

---

## Performance Tips

1. **Limit items**: Keep `limit` under 20 for optimal performance
2. **Use caching**: Enable transient caching in settings
3. **Optimize images**: Ensure source images are web-optimized
4. **Lazy loading**: Enable for better initial load times
5. **Procedure filtering**: Filter by procedure to reduce data transfer

---

## Migration Guide

### From Old Plugin Version

If upgrading from version 2.x to 3.x:

1. Old shortcodes will continue to work
2. Consider updating to new format for better features
3. New parameters available: `auto_play`, `class`, `member_id`
4. Better caching and performance in new version

### Updating Shortcodes

Find and replace in your content:
- Find: `[bragbook_carousel_shortcode`
- Replace: `[brag_book_carousel`

Then update parameters as needed.