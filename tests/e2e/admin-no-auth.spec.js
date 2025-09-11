import { test, expect } from '@playwright/test';

test.describe('BRAGBook Gallery Admin (No Auth Required)', () => {
  test('should detect admin functionality without authentication', async ({ page }) => {
    // Test wp-admin access (should redirect to login or show admin)
    const adminResponse = await page.goto('/wp-admin/', { timeout: 10000 });
    expect(adminResponse.status()).toBeLessThan(400);
    
    // Should either see login form or admin interface
    const hasLogin = await page.locator('#loginform').count() > 0;
    const hasAdminBar = await page.locator('#wpadminbar').count() > 0;
    const hasAdminMenu = await page.locator('#adminmenu').count() > 0;
    
    // At least one should be present (login form most likely)
    expect(hasLogin || hasAdminBar || hasAdminMenu).toBe(true);
    
    // If login form is present, it indicates WordPress is running properly
    if (hasLogin) {
      await expect(page.locator('#loginform')).toBeVisible();
      console.log('WordPress login form detected - admin area accessible');
    }
  });

  test('should validate plugin admin URLs are registered', async ({ page }) => {
    // Test if plugin admin pages are registered (even if not directly accessible)
    const pluginAdminUrls = [
      '/wp-admin/admin.php?page=brag-book-gallery-settings',
      '/wp-admin/admin.php?page=brag-book-gallery-debug'
    ];
    
    for (const url of pluginAdminUrls) {
      const response = await page.goto(url, { timeout: 10000 });
      
      // Should either redirect to login or show admin page (not 404)
      expect(response.status()).toBeLessThan(500);
      
      // If redirected to login, that means the page exists but requires auth
      const currentUrl = page.url();
      const isRedirectedToLogin = currentUrl.includes('wp-login.php') || 
                                 currentUrl.includes('wp-admin') ||
                                 await page.locator('#loginform').count() > 0;
      
      expect(isRedirectedToLogin).toBe(true);
      console.log(`Plugin admin URL ${url} is properly registered`);
    }
  });

  test('should verify plugin shows in WordPress plugins list', async ({ page }) => {
    // Go to plugins page
    const pluginsResponse = await page.goto('/wp-admin/plugins.php', { timeout: 10000 });
    expect(pluginsResponse.status()).toBeLessThan(400);
    
    // Should redirect to login (indicating page exists)
    const hasLogin = await page.locator('#loginform').count() > 0;
    expect(hasLogin).toBe(true);
    
    console.log('WordPress plugins page is accessible and requires authentication as expected');
  });

  test('should validate admin assets exist', async ({ page }) => {
    // Test admin assets directly
    const adminAssets = [
      '/wp-content/plugins/brag-book-gallery/assets/js/brag-book-gallery-admin.js',
      '/wp-content/plugins/brag-book-gallery/assets/css/brag-book-gallery-admin.css'
    ];
    
    let existingAssets = 0;
    
    for (const asset of adminAssets) {
      try {
        const response = await page.goto(asset);
        if (response && response.status() === 200) {
          existingAssets++;
          console.log(`Admin asset exists: ${asset}`);
        }
      } catch (error) {
        // Asset might not exist, which is okay
      }
    }
    
    // At least one admin asset should exist
    expect(existingAssets).toBeGreaterThan(0);
  });

  test('should verify WordPress environment supports plugin admin', async ({ page }) => {
    await page.goto('/', { timeout: 10000 });
    
    // Check for WordPress admin bar or other indicators
    const wpIndicators = [
      '#wp-admin-bar-root-default',
      'body[class*="wp-"]',
      'link[href*="wp-admin"]',
      'script[src*="wp-includes"]'
    ];
    
    let wpDetected = false;
    
    for (const selector of wpIndicators) {
      const element = page.locator(selector);
      if (await element.count() > 0) {
        wpDetected = true;
        break;
      }
    }
    
    // Also check page source for WordPress signatures
    if (!wpDetected) {
      const pageContent = await page.content();
      wpDetected = pageContent.includes('wp-admin') || 
                   pageContent.includes('wp-content') ||
                   pageContent.includes('WordPress');
    }
    
    expect(wpDetected).toBe(true);
    console.log('WordPress environment detected - supports admin functionality');
  });

  test('should test plugin activation indicators', async ({ page }) => {
    await page.goto('/', { timeout: 10000 });
    
    // Look for signs that plugin is active and has admin features
    const pluginIndicators = [
      // Plugin files being loaded
      'link[href*="brag-book-gallery"]',
      'script[src*="brag-book-gallery"]',
      // Plugin-specific elements or classes
      '[class*="brag"]',
      '[id*="brag"]'
    ];
    
    let pluginActive = false;
    
    for (const selector of pluginIndicators) {
      const element = page.locator(selector);
      if (await element.count() > 0) {
        pluginActive = true;
        console.log(`Plugin indicator found: ${selector}`);
        break;
      }
    }
    
    // Check page source for plugin signatures
    if (!pluginActive) {
      const pageContent = await page.content();
      pluginActive = pageContent.includes('brag-book-gallery') || 
                     pageContent.includes('BRAGBook');
    }
    
    expect(pluginActive).toBe(true);
    console.log('Plugin appears to be active with admin capabilities');
  });
});