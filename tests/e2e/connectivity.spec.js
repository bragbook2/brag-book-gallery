import { test, expect } from '@playwright/test';

test.describe('Site Connectivity', () => {
  test('should be able to access the site', async ({ page }) => {
    await page.goto('/');
    
    // Check that we can reach the site (site title is "bragbook")
    await expect(page).toHaveTitle(/bragbook|BRAGBook|WordPress/);
    
    // Check if WordPress is running
    const wpContent = page.locator('body');
    await expect(wpContent).toBeVisible();
  });

  test('should be able to access wp-admin', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    // Should see login page or admin dashboard
    const isLoginPage = await page.locator('#loginform').count() > 0;
    const isAdminPage = await page.locator('#wpadminbar').count() > 0;
    
    expect(isLoginPage || isAdminPage).toBe(true);
  });

  test('should detect if plugin assets exist', async ({ page }) => {
    // This test will check if the plugin files are accessible
    const jsResponse = await page.goto('/wp-content/plugins/brag-book-gallery/assets/js/brag-book-gallery.js');
    
    // JS file should be accessible
    expect(jsResponse.status()).toBe(200);
    
    // Check if the JS file contains expected plugin code
    const jsContent = await jsResponse.text();
    expect(jsContent).toContain('brag'); // Should contain plugin-related code
  });
});