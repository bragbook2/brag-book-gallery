import { test, expect } from '@playwright/test';

test.describe.skip('BRAGBook Gallery Admin (Requires Auth)', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to WordPress admin and login
    await page.goto('/wp-admin/');
    
    // Check if login is needed
    const loginForm = await page.$('#loginform');
    if (loginForm) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      await page.waitForSelector('#wpadminbar');
    }
  });

  test('should access plugin settings page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=brag-book-gallery-settings');
    
    // Check for main settings page elements
    await expect(page).toHaveTitle(/BRAGBook Gallery/);
    await expect(page.locator('.brag-book-gallery-settings')).toBeVisible();
  });

  test('should display admin menu', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    // Check for BRAGBook Gallery menu item
    const menuItem = page.locator('#adminmenu a[href*="brag-book-gallery"]');
    await expect(menuItem).toBeVisible();
    
    // Click menu item
    await menuItem.click();
    await expect(page).toHaveURL(/.*brag-book-gallery.*/);
  });

  test('should save API settings', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=brag-book-gallery-settings');
    
    // Fill in API settings
    const apiTokenField = page.locator('input[name*="api_token"]');
    const websitePropertyField = page.locator('input[name*="website_property_id"]');
    
    if (await apiTokenField.count() > 0) {
      await apiTokenField.fill('test-token-123');
    }
    
    if (await websitePropertyField.count() > 0) {
      await websitePropertyField.fill('456');
    }
    
    // Save settings
    const saveButton = page.locator('input[type="submit"], button[type="submit"]');
    if (await saveButton.count() > 0) {
      await saveButton.first().click();
      
      // Check for success message
      await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 10000 });
    }
  });

  test('should access debug tools', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=brag-book-gallery-debug');
    
    // Check for debug tools page
    await expect(page.locator('.brag-book-debug-tools, .debug-tools')).toBeVisible();
    
    // Check for cache management
    const cacheManagement = page.locator('text=Cache Management, #cache-management');
    if (await cacheManagement.count() > 0) {
      await expect(cacheManagement.first()).toBeVisible();
    }
  });

  test('should display settings tabs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=brag-book-gallery-settings');
    
    // Check for tab navigation
    const tabs = page.locator('.nav-tab, .brag-book-gallery-tab-link');
    if (await tabs.count() > 0) {
      await expect(tabs.first()).toBeVisible();
      
      // Test tab switching
      if (await tabs.count() > 1) {
        await tabs.nth(1).click();
        await expect(tabs.nth(1)).toHaveClass(/nav-tab-active|active/);
      }
    }
  });

  test('should handle factory reset', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=brag-book-gallery-debug');
    
    // Look for factory reset button
    const factoryResetButton = page.locator('button:has-text("Factory Reset"), input[value*="Factory Reset"]');
    
    if (await factoryResetButton.count() > 0) {
      // Click factory reset
      await factoryResetButton.click();
      
      // Handle confirmation dialog
      page.on('dialog', async dialog => {
        expect(dialog.type()).toBe('confirm');
        await dialog.accept();
      });
      
      // Check for success message or redirect
      await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 10000 });
    }
  });

  test('should validate cache management', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=brag-book-gallery-debug');
    
    // Navigate to cache management if it's in tabs
    const cacheTab = page.locator('a[href*="cache-management"], a:has-text("Cache Management")');
    if (await cacheTab.count() > 0) {
      await cacheTab.click();
    }
    
    // Check for cache management interface
    const cacheTable = page.locator('.cache-items-table, table:has-text("Cache")');
    const cacheButtons = page.locator('button:has-text("Clear"), button:has-text("Refresh")');
    
    if (await cacheTable.count() > 0) {
      await expect(cacheTable).toBeVisible();
    }
    
    if (await cacheButtons.count() > 0) {
      await expect(cacheButtons.first()).toBeVisible();
    }
  });

  test('should test API connection', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=brag-book-gallery-settings');
    
    // Look for API test button
    const testButton = page.locator('button:has-text("Test"), input[value*="Test"]');
    
    if (await testButton.count() > 0) {
      await testButton.click();
      
      // Wait for test result
      await page.waitForTimeout(3000);
      
      // Check for test result message
      const resultMessage = page.locator('.notice, .api-test-result, .test-result');
      if (await resultMessage.count() > 0) {
        await expect(resultMessage.first()).toBeVisible();
      }
    }
  });
});