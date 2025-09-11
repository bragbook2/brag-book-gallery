import { chromium } from '@playwright/test';

async function globalSetup() {
  console.log('Starting global setup...');
  
  // Launch browser for setup
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  try {
    // Navigate to WordPress admin
    await page.goto('http://bragbook.local/wp-admin/');
    
    // Login if needed (adjust selectors based on your setup)
    const loginForm = await page.$('#loginform');
    if (loginForm) {
      console.log('Logging into WordPress...');
      
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      
      // Wait for dashboard
      await page.waitForSelector('#wpadminbar', { timeout: 10000 });
      console.log('Successfully logged into WordPress');
    }
    
    // Ensure plugin is active
    await page.goto('http://bragbook.local/wp-admin/plugins.php');
    const pluginRow = await page.$('tr[data-slug="brag-book-gallery"]');
    
    if (pluginRow) {
      const isActive = await pluginRow.$('.active');
      if (!isActive) {
        console.log('Activating BRAGBook Gallery plugin...');
        const activateLink = await pluginRow.$('.activate a');
        if (activateLink) {
          await activateLink.click();
          await page.waitForSelector('.notice-success', { timeout: 5000 });
          console.log('Plugin activated successfully');
        }
      } else {
        console.log('BRAGBook Gallery plugin is already active');
      }
    }
    
    // Configure plugin settings if needed
    await page.goto('http://bragbook.local/wp-admin/admin.php?page=brag-book-gallery-settings');
    
    // Set API token if not set
    const apiTokenField = await page.$('input[name="api_token"]');
    if (apiTokenField) {
      const tokenValue = await apiTokenField.inputValue();
      if (!tokenValue) {
        console.log('Setting test API token...');
        await apiTokenField.fill('test-api-token-for-e2e-tests');
        
        const websitePropertyField = await page.$('input[name="website_property_id"]');
        if (websitePropertyField) {
          await websitePropertyField.fill('123');
        }
        
        const saveButton = await page.$('input[type="submit"]');
        if (saveButton) {
          await saveButton.click();
          await page.waitForSelector('.notice-success', { timeout: 5000 });
          console.log('Plugin settings saved');
        }
      }
    }
    
  } catch (error) {
    console.error('Global setup failed:', error);
    throw error;
  } finally {
    await browser.close();
  }
  
  console.log('Global setup completed successfully');
}

export default globalSetup;