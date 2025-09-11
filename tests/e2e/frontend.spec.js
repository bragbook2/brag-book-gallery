import { test, expect } from '@playwright/test';

test.describe.skip('BRAGBook Gallery Frontend (Requires Auth)', () => {
  test.beforeEach(async ({ page }) => {
    // Mock API responses to avoid external dependencies
    await page.route('**/bragbook**', async route => {
      const mockResponse = {
        success: true,
        data: {
          cases: [
            {
              id: '12345',
              title: 'Test Case 1',
              procedure: 'Test Procedure',
              images: {
                before: 'https://example.com/before1.jpg',
                after: 'https://example.com/after1.jpg'
              },
              details: {
                age: '25-30',
                gender: 'Female',
                ethnicity: 'Caucasian'
              }
            },
            {
              id: '67890',
              title: 'Test Case 2',
              procedure: 'Test Procedure 2',
              images: {
                before: 'https://example.com/before2.jpg',
                after: 'https://example.com/after2.jpg'
              },
              details: {
                age: '30-35',
                gender: 'Male',
                ethnicity: 'Hispanic'
              }
            }
          ],
          sidebar: {
            procedures: [
              { id: 1, name: 'Test Procedure', slug: 'test-procedure' },
              { id: 2, name: 'Test Procedure 2', slug: 'test-procedure-2' }
            ],
            filters: {
              age: ['25-30', '30-35'],
              gender: ['Female', 'Male'],
              ethnicity: ['Caucasian', 'Hispanic']
            }
          }
        }
      };
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockResponse)
      });
    });
  });

  test('should display main gallery shortcode', async ({ page }) => {
    // Create a test page with gallery shortcode
    await page.goto('/wp-admin/post-new.php?post_type=page');
    
    // Login if needed
    const loginForm = await page.$('#loginform');
    if (loginForm) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      await page.waitForSelector('#wpadminbar');
      await page.goto('/wp-admin/post-new.php?post_type=page');
    }
    
    // Add page content with shortcode
    await page.fill('#title', 'Test Gallery Page');
    
    // Switch to text editor and add shortcode
    const textTab = page.locator('#content-html');
    if (await textTab.count() > 0) {
      await textTab.click();
      await page.fill('#content', '[brag_book_gallery]');
    }
    
    // Publish page
    await page.click('#publish');
    await page.waitForSelector('.notice-success', { timeout: 10000 });
    
    // View the page
    const viewLink = page.locator('a:has-text("View page")');
    await viewLink.click();
    
    // Check for gallery elements
    await expect(page.locator('.brag-book-gallery, .brag-book-gallery-main')).toBeVisible({ timeout: 10000 });
  });

  test('should display carousel shortcode', async ({ page }) => {
    // Create a test page with carousel shortcode
    await page.goto('/wp-admin/post-new.php?post_type=page');
    
    // Login if needed
    const loginForm = await page.$('#loginform');
    if (loginForm) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      await page.waitForSelector('#wpadminbar');
      await page.goto('/wp-admin/post-new.php?post_type=page');
    }
    
    // Add page content
    await page.fill('#title', 'Test Carousel Page');
    
    const textTab = page.locator('#content-html');
    if (await textTab.count() > 0) {
      await textTab.click();
      await page.fill('#content', '[brag_book_carousel procedure="test-procedure" limit="5"]');
    }
    
    // Publish and view
    await page.click('#publish');
    await page.waitForSelector('.notice-success', { timeout: 10000 });
    
    const viewLink = page.locator('a:has-text("View page")');
    await viewLink.click();
    
    // Check for carousel elements
    await expect(page.locator('.brag-book-carousel, .carousel-container')).toBeVisible({ timeout: 10000 });
  });

  test('should test gallery filtering', async ({ page }) => {
    // Assuming we have a gallery page already created
    await page.goto('/gallery/'); // Adjust URL as needed
    
    // Wait for gallery to load
    await page.waitForSelector('.brag-book-gallery', { timeout: 10000 });
    
    // Check for filter sidebar
    const filterSidebar = page.locator('.brag-book-gallery-sidebar, .gallery-filters');
    if (await filterSidebar.count() > 0) {
      await expect(filterSidebar).toBeVisible();
      
      // Test procedure filter
      const procedureFilter = filterSidebar.locator('input[type="checkbox"], button, a').first();
      if (await procedureFilter.count() > 0) {
        await procedureFilter.click();
        
        // Wait for filtered results
        await page.waitForTimeout(2000);
        
        // Check that filtering occurred (cases should update)
        const cases = page.locator('.gallery-case, .case-item');
        await expect(cases.first()).toBeVisible();
      }
    }
  });

  test('should test responsive design', async ({ page }) => {
    await page.goto('/gallery/');
    
    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.waitForSelector('.brag-book-gallery', { timeout: 10000 });
    
    // Check for mobile menu or responsive elements
    const mobileMenu = page.locator('.mobile-menu, .hamburger-menu, .nav-toggle');
    if (await mobileMenu.count() > 0) {
      await expect(mobileMenu).toBeVisible();
      await mobileMenu.click();
    }
    
    // Test tablet viewport
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(1000);
    
    // Test desktop viewport
    await page.setViewportSize({ width: 1200, height: 800 });
    await page.waitForTimeout(1000);
    
    // Ensure gallery is still functional
    await expect(page.locator('.brag-book-gallery')).toBeVisible();
  });

  test('should test case details modal', async ({ page }) => {
    await page.goto('/gallery/');
    await page.waitForSelector('.brag-book-gallery', { timeout: 10000 });
    
    // Click on first case to open details
    const firstCase = page.locator('.gallery-case, .case-item').first();
    if (await firstCase.count() > 0) {
      await firstCase.click();
      
      // Check for modal or details view
      const modal = page.locator('.modal, .case-modal, .dialog, .case-details');
      if (await modal.count() > 0) {
        await expect(modal).toBeVisible();
        
        // Test close modal
        const closeButton = modal.locator('.close, .modal-close, button:has-text("Close")');
        if (await closeButton.count() > 0) {
          await closeButton.click();
          await expect(modal).not.toBeVisible();
        }
      }
    }
  });

  test('should test favorites functionality', async ({ page }) => {
    await page.goto('/gallery/');
    await page.waitForSelector('.brag-book-gallery', { timeout: 10000 });
    
    // Look for favorite buttons
    const favoriteButton = page.locator('.favorite-btn, .heart-btn, button:has-text("♡")').first();
    if (await favoriteButton.count() > 0) {
      // Add to favorites
      await favoriteButton.click();
      
      // Check if button state changed
      await expect(favoriteButton).toHaveClass(/active|favorited/);
      
      // Test favorites page if it exists
      const favoritesLink = page.locator('a[href*="favorites"], a:has-text("Favorites")');
      if (await favoritesLink.count() > 0) {
        await favoritesLink.click();
        
        // Should show favorited items
        await expect(page.locator('.favorite-item, .favorited-case')).toBeVisible();
      }
    }
  });

  test('should test search functionality', async ({ page }) => {
    await page.goto('/gallery/');
    await page.waitForSelector('.brag-book-gallery', { timeout: 10000 });
    
    // Look for search input
    const searchInput = page.locator('input[type="search"], .search-input');
    if (await searchInput.count() > 0) {
      await searchInput.fill('test');
      
      // Trigger search (enter or search button)
      await page.keyboard.press('Enter');
      
      // Wait for search results
      await page.waitForTimeout(2000);
      
      // Verify search results
      const results = page.locator('.search-results, .gallery-case');
      if (await results.count() > 0) {
        await expect(results.first()).toBeVisible();
      }
    }
  });

  test('should test load more functionality', async ({ page }) => {
    await page.goto('/gallery/');
    await page.waitForSelector('.brag-book-gallery', { timeout: 10000 });
    
    // Count initial cases
    const initialCases = await page.locator('.gallery-case, .case-item').count();
    
    // Look for load more button
    const loadMoreButton = page.locator('button:has-text("Load More"), .load-more-btn');
    if (await loadMoreButton.count() > 0) {
      await loadMoreButton.click();
      
      // Wait for new cases to load
      await page.waitForTimeout(3000);
      
      // Count cases after loading
      const newCaseCount = await page.locator('.gallery-case, .case-item').count();
      expect(newCaseCount).toBeGreaterThan(initialCases);
    }
  });

  test('should test accessibility', async ({ page }) => {
    await page.goto('/gallery/');
    await page.waitForSelector('.brag-book-gallery', { timeout: 10000 });
    
    // Test keyboard navigation
    await page.keyboard.press('Tab');
    const focusedElement = page.locator(':focus');
    await expect(focusedElement).toBeVisible();
    
    // Test alt text on images
    const images = page.locator('img');
    if (await images.count() > 0) {
      const firstImage = images.first();
      const altText = await firstImage.getAttribute('alt');
      expect(altText).toBeTruthy();
    }
    
    // Test ARIA labels
    const interactiveElements = page.locator('button, a, input');
    const elementCount = await interactiveElements.count();
    
    if (elementCount > 0) {
      for (let i = 0; i < Math.min(elementCount, 5); i++) {
        const element = interactiveElements.nth(i);
        const ariaLabel = await element.getAttribute('aria-label');
        const text = await element.textContent();
        
        // Should have either aria-label or visible text
        expect(ariaLabel || text).toBeTruthy();
      }
    }
  });
});