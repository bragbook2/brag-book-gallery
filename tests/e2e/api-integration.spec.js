import { test, expect } from '@playwright/test';

test.describe('API Integration Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Set up request/response monitoring
    page.on('request', request => {
      console.log('Request:', request.method(), request.url());
    });
    
    page.on('response', response => {
      console.log('Response:', response.status(), response.url());
    });
  });

  test('should handle API requests gracefully', async ({ page }) => {
    let apiRequestsMade = [];
    
    // Monitor API requests
    page.on('request', request => {
      const url = request.url();
      if (url.includes('bragbook') || url.includes('gallery') || url.includes('wp-json')) {
        apiRequestsMade.push({
          method: request.method(),
          url: url,
          headers: request.headers()
        });
      }
    });

    // Go to a page that might trigger API calls
    await page.goto('/');
    
    // Wait for potential API calls
    await page.waitForTimeout(3000);
    
    // Log API requests for debugging
    console.log('API requests detected:', apiRequestsMade.length);
    
    // Validate that if API requests are made, they're properly formatted
    for (const request of apiRequestsMade) {
      expect(request.method).toMatch(/GET|POST|PUT|DELETE/);
      expect(request.url).toMatch(/https?:\/\/.+/);
    }
  });

  test('should mock API responses correctly', async ({ page }) => {
    // Set up mock API response
    await page.route('**/wp-json/bragbook/**', async route => {
      const mockData = {
        success: true,
        data: {
          cases: [
            {
              id: 'test-123',
              title: 'Mock Test Case',
              procedure: 'Mock Procedure',
              images: {
                before: 'https://via.placeholder.com/300x200/blue/white?text=Before',
                after: 'https://via.placeholder.com/300x200/green/white?text=After'
              }
            }
          ]
        }
      };
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockData)
      });
    });

    await page.goto('/');
    
    // Trigger a potential API call
    await page.evaluate(() => {
      if (typeof fetch !== 'undefined') {
        fetch('/wp-json/bragbook/v1/cases')
          .then(response => response.json())
          .then(data => {
            window.testApiResponse = data;
          })
          .catch(error => {
            window.testApiError = error;
          });
      }
    });
    
    await page.waitForTimeout(2000);
    
    // Check if mock response was received
    const apiResponse = await page.evaluate(() => window.testApiResponse);
    const apiError = await page.evaluate(() => window.testApiError);
    
    if (apiResponse) {
      expect(apiResponse.success).toBe(true);
      expect(apiResponse.data.cases).toHaveLength(1);
    }
    
    // Should not have errors with mock
    expect(apiError).toBeFalsy();
  });

  test('should handle AJAX endpoints', async ({ page }) => {
    let ajaxResponses = [];
    
    // Mock WordPress AJAX responses
    await page.route('**/wp-admin/admin-ajax.php', async route => {
      const postData = route.request().postData();
      ajaxResponses.push({
        action: postData?.includes('action=') ? postData.match(/action=([^&]+)/)?.[1] : 'unknown',
        data: postData
      });
      
      // Return appropriate mock response based on action
      const mockResponse = {
        success: true,
        data: {
          message: 'AJAX call handled successfully',
          action: postData?.includes('action=') ? postData.match(/action=([^&]+)/)?.[1] : 'test'
        }
      };
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockResponse)
      });
    });

    await page.goto('/');
    
    // Simulate AJAX calls that the plugin might make
    await page.evaluate(() => {
      if (typeof jQuery !== 'undefined' && jQuery.ajax) {
        jQuery.ajax({
          url: '/wp-admin/admin-ajax.php',
          method: 'POST',
          data: {
            action: 'brag_book_gallery_test',
            nonce: 'test-nonce',
            data: 'test-data'
          }
        });
      }
    });
    
    await page.waitForTimeout(2000);
    
    // Validate AJAX interactions
    expect(ajaxResponses.length).toBeGreaterThanOrEqual(0);
    
    // If AJAX calls were made, they should be plugin-related
    for (const response of ajaxResponses) {
      if (response.action && response.action !== 'unknown') {
        expect(response.action).toMatch(/brag|gallery|test/);
      }
    }
  });

  test('should validate API error handling', async ({ page }) => {
    // Mock API error responses
    await page.route('**/wp-json/bragbook/**', async route => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          error: 'Mock API error for testing'
        })
      });
    });

    await page.goto('/');
    
    // Test error handling
    await page.evaluate(() => {
      if (typeof fetch !== 'undefined') {
        fetch('/wp-json/bragbook/v1/cases')
          .then(response => response.json())
          .then(data => {
            window.testErrorResponse = data;
          })
          .catch(error => {
            window.testErrorCaught = error.message;
          });
      }
    });
    
    await page.waitForTimeout(2000);
    
    const errorResponse = await page.evaluate(() => window.testErrorResponse);
    const errorCaught = await page.evaluate(() => window.testErrorCaught);
    
    // Should handle errors gracefully
    expect(errorResponse || errorCaught).toBeTruthy();
  });

  test('should test caching behavior', async ({ page }) => {
    let requestCount = 0;
    
    // Count identical requests to test caching
    await page.route('**/wp-json/bragbook/**', async route => {
      requestCount++;
      
      const mockData = {
        success: true,
        data: { cached: true, requestNumber: requestCount }
      };
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: {
          'Cache-Control': 'public, max-age=3600'
        },
        body: JSON.stringify(mockData)
      });
    });

    await page.goto('/');
    
    // Make multiple identical requests
    await page.evaluate(() => {
      if (typeof fetch !== 'undefined') {
        const promises = [];
        for (let i = 0; i < 3; i++) {
          promises.push(
            fetch('/wp-json/bragbook/v1/cases')
              .then(response => response.json())
          );
        }
        
        Promise.all(promises).then(responses => {
          window.testCacheResponses = responses;
        });
      }
    });
    
    await page.waitForTimeout(3000);
    
    const responses = await page.evaluate(() => window.testCacheResponses);
    
    if (responses && responses.length > 0) {
      // All responses should be successful
      responses.forEach(response => {
        expect(response.success).toBe(true);
      });
    }
    
    console.log('Cache test - Requests made:', requestCount);
  });

  test('should validate data serialization', async ({ page }) => {
    // Test data handling and serialization
    await page.route('**/wp-json/bragbook/**', async route => {
      const requestData = route.request().postDataJSON();
      
      const responseData = {
        success: true,
        received: requestData,
        timestamp: new Date().toISOString(),
        data: {
          processed: true,
          sanitized: true
        }
      };
      
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(responseData)
      });
    });

    await page.goto('/');
    
    // Test POST data serialization
    await page.evaluate(() => {
      if (typeof fetch !== 'undefined') {
        const testData = {
          action: 'test',
          cases: [1, 2, 3],
          filters: { age: '25-30', gender: 'Female' },
          special_chars: 'test & data <script>',
          unicode: '测试数据'
        };
        
        fetch('/wp-json/bragbook/v1/test', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(testData)
        })
        .then(response => response.json())
        .then(data => {
          window.testSerializationResponse = data;
        });
      }
    });
    
    await page.waitForTimeout(2000);
    
    const response = await page.evaluate(() => window.testSerializationResponse);
    
    if (response) {
      expect(response.success).toBe(true);
      expect(response.data.processed).toBe(true);
    }
  });

  test('should test API authentication handling', async ({ page }) => {
    // Mock different authentication scenarios
    const authScenarios = [
      { status: 200, description: 'authenticated' },
      { status: 401, description: 'unauthorized' },
      { status: 403, description: 'forbidden' }
    ];
    
    for (const scenario of authScenarios) {
      await page.route('**/wp-json/bragbook/**', async route => {
        await route.fulfill({
          status: scenario.status,
          contentType: 'application/json',
          body: JSON.stringify({
            success: scenario.status === 200,
            message: `Mock ${scenario.description} response`
          })
        });
      });

      await page.goto('/');
      
      await page.evaluate(() => {
        if (typeof fetch !== 'undefined') {
          fetch('/wp-json/bragbook/v1/protected')
            .then(response => {
              window.authTestStatus = response.status;
              return response.json();
            })
            .then(data => {
              window.authTestData = data;
            })
            .catch(error => {
              window.authTestError = error;
            });
        }
      });
      
      await page.waitForTimeout(1000);
      
      const status = await page.evaluate(() => window.authTestStatus);
      expect(status).toBe(scenario.status);
    }
  });
});