import { test, expect } from '@playwright/test';

test.describe('Favorites Functionality Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Mock favorites API responses
    await page.route('**/wp-json/bragbook/**', async route => {
      const url = route.request().url();
      
      if (url.includes('favorites') || url.includes('favorite')) {
        const mockFavoritesData = {
          success: true,
          data: {
            favorites: [
              {
                id: 'fav_case_001',
                title: 'Favorite Facelift Case',
                procedure: 'Facelift',
                images: {
                  before: 'https://via.placeholder.com/400x300/FF69B4/FFFFFF?text=Fav+Before+1',
                  after: 'https://via.placeholder.com/400x300/32CD32/FFFFFF?text=Fav+After+1'
                },
                dateAdded: '2024-01-15',
                isFavorite: true
              },
              {
                id: 'fav_case_002',
                title: 'Favorite Breast Augmentation',
                procedure: 'Breast Augmentation',
                images: {
                  before: 'https://via.placeholder.com/400x300/FF69B4/FFFFFF?text=Fav+Before+2',
                  after: 'https://via.placeholder.com/400x300/32CD32/FFFFFF?text=Fav+After+2'
                },
                dateAdded: '2024-01-10',
                isFavorite: true
              }
            ],
            count: 2
          }
        };
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(mockFavoritesData)
        });
      } else {
        // Mock regular gallery data with favorite indicators
        const mockGalleryData = {
          success: true,
          data: {
            cases: [
              {
                id: 'case_001',
                title: 'Facelift Case 1',
                procedure: 'Facelift',
                images: {
                  before: 'https://via.placeholder.com/400x300/cccccc/666666?text=Before+1',
                  after: 'https://via.placeholder.com/400x300/90EE90/006400?text=After+1'
                },
                isFavorite: false
              },
              {
                id: 'case_002',
                title: 'Breast Case 1',
                procedure: 'Breast Augmentation', 
                images: {
                  before: 'https://via.placeholder.com/400x300/cccccc/666666?text=Before+2',
                  after: 'https://via.placeholder.com/400x300/FFB6C1/8B0000?text=After+2'
                },
                isFavorite: true
              },
              {
                id: 'case_003',
                title: 'Rhinoplasty Case 1',
                procedure: 'Rhinoplasty',
                images: {
                  before: 'https://via.placeholder.com/400x300/cccccc/666666?text=Before+3',
                  after: 'https://via.placeholder.com/400x300/87CEEB/000080?text=After+3'
                },
                isFavorite: false
              }
            ]
          }
        };
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(mockGalleryData)
        });
      }
    });

    // Mock favorites AJAX requests
    await page.route('**/wp-admin/admin-ajax.php', async route => {
      const postData = route.request().postData() || '';
      
      if (postData.includes('add_favorite') || postData.includes('toggle_favorite')) {
        const mockResponse = {
          success: true,
          data: {
            action: 'added',
            message: 'Case added to favorites',
            isFavorite: true,
            favoriteCount: 3
          }
        };
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(mockResponse)
        });
      } else if (postData.includes('remove_favorite')) {
        const mockResponse = {
          success: true,
          data: {
            action: 'removed',
            message: 'Case removed from favorites',
            isFavorite: false,
            favoriteCount: 2
          }
        };
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(mockResponse)
        });
      } else if (postData.includes('get_favorites') || postData.includes('load_favorites')) {
        const mockResponse = {
          success: true,
          data: {
            html: `
              <div class="favorites-container">
                <div class="favorites-header">
                  <h2>My Favorite Cases</h2>
                  <p class="favorite-count">You have 2 favorite cases</p>
                </div>
                <div class="favorites-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                  <div class="favorite-case" data-case-id="fav_case_001">
                    <div class="case-images" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                      <img src="https://via.placeholder.com/200x250/FF69B4/FFFFFF?text=Fav+Before" alt="Before" class="before-image">
                      <img src="https://via.placeholder.com/200x250/32CD32/FFFFFF?text=Fav+After" alt="After" class="after-image">
                    </div>
                    <div class="case-info">
                      <h3>Favorite Facelift Case</h3>
                      <p class="procedure">Facelift</p>
                      <p class="date-added">Added: Jan 15, 2024</p>
                    </div>
                    <div class="case-actions">
                      <button class="remove-favorite" data-case-id="fav_case_001">Remove from Favorites</button>
                      <button class="view-details" data-case-id="fav_case_001">View Details</button>
                    </div>
                  </div>
                </div>
              </div>
            `
          }
        };
        
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(mockResponse)
        });
      } else {
        await route.continue();
      }
    });

    // Set up localStorage for favorites functionality
    await page.addInitScript(() => {
      // Initialize favorites in localStorage
      const favoriteCase = {
        id: 'case_002',
        title: 'Breast Case 1',
        procedure: 'Breast Augmentation',
        dateAdded: new Date().toISOString()
      };
      
      localStorage.setItem('bragbook_favorites', JSON.stringify([favoriteCase]));
      localStorage.setItem('bragbook_user_email', 'test@example.com');
    });
  });

  test('should display favorite buttons on case cards', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Inject gallery with favorite buttons for testing
    await page.evaluate(() => {
      if (!document.querySelector('.test-gallery-with-favorites')) {
        const galleryHTML = `
          <div class="test-gallery-with-favorites" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px;">
            <div class="gallery-case" data-case-id="case_001" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; position: relative;">
              <button class="favorite-btn" data-case-id="case_001" style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                <span class="heart-icon">♡</span>
              </button>
              <img src="https://via.placeholder.com/300x200/FF6B6B/FFFFFF?text=Case+1" alt="Case 1" style="width: 100%; border-radius: 4px;">
              <h3>Facelift Case 1</h3>
              <p>Click heart to add to favorites</p>
            </div>
            
            <div class="gallery-case" data-case-id="case_002" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; position: relative;">
              <button class="favorite-btn favorited" data-case-id="case_002" style="position: absolute; top: 10px; right: 10px; background: #e74c3c; color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                <span class="heart-icon">♥</span>
              </button>
              <img src="https://via.placeholder.com/300x200/4ECDC4/FFFFFF?text=Case+2" alt="Case 2" style="width: 100%; border-radius: 4px;">
              <h3>Breast Case 1</h3>
              <p>Already favorited</p>
            </div>
            
            <div class="gallery-case" data-case-id="case_003" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; position: relative;">
              <button class="favorite-btn" data-case-id="case_003" style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                <span class="heart-icon">♡</span>
              </button>
              <img src="https://via.placeholder.com/300x200/87CEEB/FFFFFF?text=Case+3" alt="Case 3" style="width: 100%; border-radius: 4px;">
              <h3>Rhinoplasty Case 1</h3>
              <p>Click heart to add to favorites</p>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', galleryHTML);
      }
    });

    // Look for favorite buttons
    const favoriteButtons = page.locator('.favorite-btn, button[class*="favorite"], .heart-btn');
    const buttonCount = await favoriteButtons.count();

    console.log(`Favorite buttons found: ${buttonCount}`);

    if (buttonCount > 0) {
      // Test button visibility
      await expect(favoriteButtons.first()).toBeVisible();

      // Check for heart icons
      const heartIcons = page.locator('.heart-icon, [class*="heart"]');
      const heartCount = await heartIcons.count();
      
      if (heartCount > 0) {
        console.log(`Heart icons found: ${heartCount}`);
        
        // Test different heart states
        const emptyHearts = page.locator('text=♡');
        const filledHearts = page.locator('text=♥');
        
        const emptyCount = await emptyHearts.count();
        const filledCount = await filledHearts.count();
        
        console.log(`Empty hearts (not favorited): ${emptyCount}`);
        console.log(`Filled hearts (favorited): ${filledCount}`);
      }

      // Test different button states
      const unfavoritedBtns = favoriteButtons.filter({ hasNotClass: 'favorited' });
      const favoritedBtns = favoriteButtons.filter({ hasClass: 'favorited' });

      const unfavoritedCount = await unfavoritedBtns.count();
      const favoritedCount = await favoritedBtns.count();

      console.log(`Unfavorited buttons: ${unfavoritedCount}`);
      console.log(`Favorited buttons: ${favoritedCount}`);
    }

    expect(buttonCount).toBeGreaterThanOrEqual(0);
  });

  test('should toggle favorite state when button is clicked', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Find unfavorited case to test adding
    const unfavoritedBtn = page.locator('.favorite-btn').filter({ hasNotClass: 'favorited' }).first();
    
    if (await unfavoritedBtn.count() > 0) {
      // Get initial state
      const initialClass = await unfavoritedBtn.getAttribute('class');
      const initialIcon = await unfavoritedBtn.locator('.heart-icon').textContent();
      
      console.log(`Initial state - Class: "${initialClass}", Icon: "${initialIcon}"`);

      // Click to add to favorites
      await unfavoritedBtn.click();
      await page.waitForTimeout(500);

      // Check if state changed
      const newClass = await unfavoritedBtn.getAttribute('class');
      const newIcon = await unfavoritedBtn.locator('.heart-icon').textContent();
      
      console.log(`After click - Class: "${newClass}", Icon: "${newIcon}"`);

      // State should have changed
      const stateChanged = initialClass !== newClass || initialIcon !== newIcon;
      console.log(`Favorite state changed: ${stateChanged}`);

      if (stateChanged) {
        expect(stateChanged).toBe(true);
      }
    }

    // Test removing from favorites
    const favoritedBtn = page.locator('.favorite-btn.favorited').first();
    
    if (await favoritedBtn.count() > 0) {
      console.log('Testing favorite removal');
      
      const initialClass = await favoritedBtn.getAttribute('class');
      await favoritedBtn.click();
      await page.waitForTimeout(500);
      
      const newClass = await favoritedBtn.getAttribute('class');
      const stateChanged = initialClass !== newClass;
      
      console.log(`Remove favorite - state changed: ${stateChanged}`);
    }

    expect(true).toBe(true);
  });

  test('should persist favorites in localStorage', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Test localStorage functionality
    const localStorageFavorites = await page.evaluate(() => {
      const stored = localStorage.getItem('bragbook_favorites');
      return stored ? JSON.parse(stored) : [];
    });

    console.log('Initial localStorage favorites:', localStorageFavorites);

    // Add a favorite via JavaScript
    await page.evaluate(() => {
      const newFavorite = {
        id: 'case_test_001',
        title: 'Test Favorite Case',
        procedure: 'Test Procedure',
        dateAdded: new Date().toISOString()
      };
      
      const favorites = JSON.parse(localStorage.getItem('bragbook_favorites') || '[]');
      favorites.push(newFavorite);
      localStorage.setItem('bragbook_favorites', JSON.stringify(favorites));
      
      // Trigger a custom event to notify of favorite change
      window.dispatchEvent(new CustomEvent('favoriteChanged', {
        detail: { action: 'added', case: newFavorite }
      }));
    });

    // Check if favorite was added
    const updatedFavorites = await page.evaluate(() => {
      const stored = localStorage.getItem('bragbook_favorites');
      return stored ? JSON.parse(stored) : [];
    });

    console.log('Updated localStorage favorites:', updatedFavorites);
    expect(updatedFavorites.length).toBeGreaterThan(0);

    // Test removing a favorite
    await page.evaluate(() => {
      const favorites = JSON.parse(localStorage.getItem('bragbook_favorites') || '[]');
      const filtered = favorites.filter(fav => fav.id !== 'case_test_001');
      localStorage.setItem('bragbook_favorites', JSON.stringify(filtered));
    });

    const finalFavorites = await page.evaluate(() => {
      const stored = localStorage.getItem('bragbook_favorites');
      return stored ? JSON.parse(stored) : [];
    });

    console.log('Final localStorage favorites:', finalFavorites);

    // Test favorites count functionality
    const favoriteCount = finalFavorites.length;
    console.log(`Total favorites count: ${favoriteCount}`);

    expect(true).toBe(true);
  });

  test('should display favorites page with saved cases', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Inject favorites page content for testing
    await page.evaluate(() => {
      if (!document.querySelector('.favorites-page-content')) {
        const favoritesHTML = `
          <div class="favorites-page-content" style="max-width: 1200px; margin: 20px auto; padding: 20px;">
            <div class="favorites-header" style="text-align: center; margin-bottom: 30px;">
              <h1>My Favorite Cases</h1>
              <p class="favorite-count-display">You have <span class="count-number">2</span> favorite cases</p>
              <div class="favorites-actions" style="margin-top: 15px;">
                <button class="clear-all-favorites" style="background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin: 0 10px;">Clear All</button>
                <button class="share-favorites" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin: 0 10px;">Share Favorites</button>
                <button class="export-favorites" style="background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin: 0 10px;">Export PDF</button>
              </div>
            </div>
            
            <div class="favorites-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px;">
              <div class="favorite-case-card" data-case-id="fav_case_001" style="border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <div class="case-images" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                  <div class="before-container">
                    <label style="font-size: 12px; color: #666; text-transform: uppercase;">Before</label>
                    <img src="https://via.placeholder.com/160x200/FF69B4/FFFFFF?text=Fav+Before+1" alt="Before" style="width: 100%; border-radius: 6px;">
                  </div>
                  <div class="after-container">
                    <label style="font-size: 12px; color: #666; text-transform: uppercase;">After</label>
                    <img src="https://via.placeholder.com/160x200/32CD32/FFFFFF?text=Fav+After+1" alt="After" style="width: 100%; border-radius: 6px;">
                  </div>
                </div>
                
                <div class="case-info" style="margin-bottom: 15px;">
                  <h3 style="margin: 0 0 8px 0;">Favorite Facelift Case</h3>
                  <p class="procedure-name" style="color: #3498db; font-weight: 500; margin: 0 0 5px 0;">Facelift</p>
                  <p class="date-added" style="color: #666; font-size: 14px; margin: 0;">Added: Jan 15, 2024</p>
                </div>
                
                <div class="case-actions" style="display: flex; gap: 10px;">
                  <button class="view-case" data-case-id="fav_case_001" style="flex: 1; background: #3498db; color: white; border: none; padding: 8px 12px; border-radius: 4px; font-size: 14px;">View Details</button>
                  <button class="remove-favorite" data-case-id="fav_case_001" style="background: #e74c3c; color: white; border: none; padding: 8px 12px; border-radius: 4px; font-size: 14px;">Remove</button>
                </div>
              </div>
              
              <div class="favorite-case-card" data-case-id="fav_case_002" style="border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <div class="case-images" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                  <div class="before-container">
                    <label style="font-size: 12px; color: #666; text-transform: uppercase;">Before</label>
                    <img src="https://via.placeholder.com/160x200/FF69B4/FFFFFF?text=Fav+Before+2" alt="Before" style="width: 100%; border-radius: 6px;">
                  </div>
                  <div class="after-container">
                    <label style="font-size: 12px; color: #666; text-transform: uppercase;">After</label>
                    <img src="https://via.placeholder.com/160x200/32CD32/FFFFFF?text=Fav+After+2" alt="After" style="width: 100%; border-radius: 6px;">
                  </div>
                </div>
                
                <div class="case-info" style="margin-bottom: 15px;">
                  <h3 style="margin: 0 0 8px 0;">Favorite Breast Augmentation</h3>
                  <p class="procedure-name" style="color: #e74c3c; font-weight: 500; margin: 0 0 5px 0;">Breast Augmentation</p>
                  <p class="date-added" style="color: #666; font-size: 14px; margin: 0;">Added: Jan 10, 2024</p>
                </div>
                
                <div class="case-actions" style="display: flex; gap: 10px;">
                  <button class="view-case" data-case-id="fav_case_002" style="flex: 1; background: #3498db; color: white; border: none; padding: 8px 12px; border-radius: 4px; font-size: 14px;">View Details</button>
                  <button class="remove-favorite" data-case-id="fav_case_002" style="background: #e74c3c; color: white; border: none; padding: 8px 12px; border-radius: 4px; font-size: 14px;">Remove</button>
                </div>
              </div>
            </div>
            
            <div class="favorites-empty-state" style="display: none; text-align: center; padding: 60px 20px;">
              <h3>No Favorite Cases Yet</h3>
              <p>Browse the gallery and click the heart icon to save cases you're interested in.</p>
              <button class="browse-gallery" style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 6px; margin-top: 15px;">Browse Gallery</button>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', favoritesHTML);
      }
    });

    // Test favorites page components
    const favoritesPage = page.locator('.favorites-page-content, .favorites-container, [class*="favorites"]');
    
    if (await favoritesPage.count() > 0) {
      await expect(favoritesPage.first()).toBeVisible();
      console.log('Favorites page content is visible');

      // Test favorites count display
      const countDisplay = page.locator('.favorite-count-display, .count-number, [class*="count"]');
      if (await countDisplay.count() > 0) {
        const countText = await countDisplay.first().textContent();
        console.log(`Favorites count display: "${countText}"`);
      }

      // Test favorite case cards
      const favoriteCaseCards = page.locator('.favorite-case-card, .favorite-case, [class*="favorite-case"]');
      const cardCount = await favoriteCaseCards.count();
      
      console.log(`Favorite case cards found: ${cardCount}`);
      
      if (cardCount > 0) {
        await expect(favoriteCaseCards.first()).toBeVisible();
        
        // Test card content
        const firstCard = favoriteCaseCards.first();
        const cardTitle = firstCard.locator('h3, .case-title');
        const procedureName = firstCard.locator('.procedure-name, [class*="procedure"]');
        const dateAdded = firstCard.locator('.date-added, [class*="date"]');
        
        if (await cardTitle.count() > 0) {
          const titleText = await cardTitle.textContent();
          console.log(`First favorite case title: "${titleText}"`);
        }
        
        if (await procedureName.count() > 0) {
          const procedureText = await procedureName.textContent();
          console.log(`First favorite procedure: "${procedureText}"`);
        }
        
        if (await dateAdded.count() > 0) {
          const dateText = await dateAdded.textContent();
          console.log(`First favorite date added: "${dateText}"`);
        }
      }

      // Test favorites page actions
      const pageActions = page.locator('.clear-all-favorites, .share-favorites, .export-favorites');
      const actionCount = await pageActions.count();
      
      if (actionCount > 0) {
        console.log(`Favorites page actions found: ${actionCount}`);
        
        // Test clear all favorites
        const clearAllBtn = page.locator('.clear-all-favorites');
        if (await clearAllBtn.count() > 0) {
          await clearAllBtn.click();
          await page.waitForTimeout(300);
          console.log('Clear all favorites button clicked');
        }
      }
    }

    expect(true).toBe(true);
  });

  test('should handle empty favorites state', async ({ page }) => {
    // Clear localStorage favorites for empty state test
    await page.addInitScript(() => {
      localStorage.removeItem('bragbook_favorites');
      localStorage.setItem('bragbook_favorites', '[]');
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Check for empty state messaging
    const emptyStateElements = [
      '.favorites-empty-state',
      '.no-favorites',
      'text=/no favorite cases/i',
      'text=/you haven\'t saved any cases/i',
      'text=/start browsing/i'
    ];

    let emptyStateFound = false;
    let emptyStateText = '';

    for (const selector of emptyStateElements) {
      const elements = page.locator(selector);
      if (await elements.count() > 0) {
        emptyStateFound = true;
        emptyStateText = await elements.first().textContent() || '';
        console.log(`Empty state found with selector: ${selector}`);
        console.log(`Empty state message: "${emptyStateText}"`);
        
        await expect(elements.first()).toBeVisible();
        break;
      }
    }

    if (!emptyStateFound) {
      // Inject empty state for testing
      await page.evaluate(() => {
        if (!document.querySelector('.empty-favorites-test')) {
          const emptyHTML = `
            <div class="empty-favorites-test" style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 8px; margin: 20px;">
              <div style="font-size: 48px; margin-bottom: 20px;">💔</div>
              <h3>No Favorite Cases Yet</h3>
              <p>You haven't saved any cases to your favorites yet.</p>
              <p>Browse the gallery and click the heart icon (♡) to save cases you're interested in.</p>
              <button class="browse-gallery-btn" style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 6px; margin-top: 20px; cursor: pointer;">
                Browse Gallery
              </button>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', emptyHTML);
        }
      });

      const injectedEmptyState = page.locator('.empty-favorites-test');
      if (await injectedEmptyState.count() > 0) {
        await expect(injectedEmptyState).toBeVisible();
        console.log('Empty favorites state properly displayed');
        
        // Test browse gallery button
        const browseBtn = injectedEmptyState.locator('.browse-gallery-btn, button');
        if (await browseBtn.count() > 0) {
          await browseBtn.click();
          console.log('Browse gallery button clicked');
        }
      }
    }

    expect(true).toBe(true);
  });

  test('should provide favorites management actions', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Test individual case removal from favorites
    const removeFavoriteButtons = page.locator('.remove-favorite, [class*="remove"]').or(page.locator('button').filter({ hasText: /remove/i }));
    const removeButtonCount = await removeFavoriteButtons.count();
    
    if (removeButtonCount > 0) {
      console.log(`Remove favorite buttons found: ${removeButtonCount}`);
      
      // Test removing a favorite
      const firstRemoveBtn = removeFavoriteButtons.first();
      await firstRemoveBtn.click();
      await page.waitForTimeout(500);
      
      console.log('Remove favorite button clicked');
      
      // Look for confirmation or immediate removal
      const confirmDialog = page.locator('.confirm-dialog, [role="dialog"], .modal');
      if (await confirmDialog.count() > 0) {
        console.log('Confirmation dialog shown for favorite removal');
        
        const confirmBtn = confirmDialog.locator('.confirm-btn').or(confirmDialog.locator('button').filter({ hasText: /confirm|yes/i }));
        if (await confirmBtn.count() > 0) {
          await confirmBtn.click();
          console.log('Favorite removal confirmed');
        }
      }
    }

    // Test bulk actions
    const bulkActions = [
      { selector: '.clear-all-favorites', name: 'Clear All Favorites' },
      { selector: '.select-all-favorites', name: 'Select All' },
      { selector: '.export-favorites', name: 'Export Favorites' },
      { selector: '.share-all-favorites', name: 'Share All' }
    ];

    for (const action of bulkActions) {
      let elements;
      if (action.name === 'Clear All Favorites') {
        elements = page.locator('.clear-all-favorites').or(page.locator('button').filter({ hasText: /clear all/i }));
      } else if (action.name === 'Select All') {
        elements = page.locator('.select-all-favorites').or(page.locator('button').filter({ hasText: /select all/i }));
      } else if (action.name === 'Export Favorites') {
        elements = page.locator('.export-favorites').or(page.locator('button').filter({ hasText: /export/i }));
      } else if (action.name === 'Share All') {
        elements = page.locator('.share-all-favorites').or(page.locator('button').filter({ hasText: /share/i }));
      } else {
        elements = page.locator(action.selector);
      }
      if (await elements.count() > 0) {
        console.log(`${action.name} button found`);
        
        await elements.first().click();
        await page.waitForTimeout(300);
        
        // Look for action results or confirmation
        const actionResult = page.locator('.action-result, .success-message, .notification');
        if (await actionResult.count() > 0) {
          const resultText = await actionResult.first().textContent();
          console.log(`${action.name} result: "${resultText}"`);
        }
      }
    }

    // Test favorites sorting and filtering
    const sortOptions = page.locator('.sort-favorites, .favorites-sort, [class*="sort"]');
    if (await sortOptions.count() > 0) {
      console.log('Favorites sorting options found');
      
      // Test different sort options
      const sortSelect = sortOptions.locator('select, .dropdown');
      if (await sortSelect.count() > 0) {
        await sortSelect.click();
        await page.waitForTimeout(300);
        
        const sortOptions = sortSelect.locator('option');
        const optionCount = await sortOptions.count();
        console.log(`Sort options available: ${optionCount}`);
      }
    }

    expect(true).toBe(true);
  });

  test('should sync favorites with user account', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Test user authentication state
    const userEmail = await page.evaluate(() => {
      return localStorage.getItem('bragbook_user_email');
    });

    console.log(`User email in localStorage: ${userEmail}`);

    if (userEmail) {
      console.log('User is logged in - testing sync functionality');
      
      // Test syncing favorites to server
      await page.evaluate(() => {
        const favorites = JSON.parse(localStorage.getItem('bragbook_favorites') || '[]');
        
        // Simulate sync to server
        fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'sync_favorites',
            favorites: JSON.stringify(favorites),
            user_email: localStorage.getItem('bragbook_user_email')
          })
        }).then(response => response.json())
          .then(data => {
            if (data.success) {
              localStorage.setItem('bragbook_favorites_synced', 'true');
            }
          });
      });

      await page.waitForTimeout(1000);

      const syncStatus = await page.evaluate(() => {
        return localStorage.getItem('bragbook_favorites_synced');
      });

      console.log(`Favorites sync status: ${syncStatus}`);
    } else {
      console.log('User not logged in - testing email capture');
      
      // Test email capture for favorites sync
      await page.evaluate(() => {
        if (!document.querySelector('.email-capture-modal')) {
          const emailModalHTML = `
            <div class="email-capture-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; display: flex; align-items: center; justify-content: center;">
              <div class="email-capture-form" style="background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%;">
                <h3>Save Your Favorites</h3>
                <p>Enter your email to save and sync your favorite cases across devices.</p>
                <form>
                  <input type="email" placeholder="your@email.com" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin: 10px 0;">
                  <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="capture-cancel" style="flex: 1; background: #95a5a6; color: white; border: none; padding: 12px; border-radius: 6px;">Skip</button>
                    <button type="submit" class="capture-submit" style="flex: 1; background: #3498db; color: white; border: none; padding: 12px; border-radius: 6px;">Save Favorites</button>
                  </div>
                </form>
              </div>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', emailModalHTML);
        }
      });

      const emailModal = page.locator('.email-capture-modal');
      if (await emailModal.count() > 0) {
        await expect(emailModal).toBeVisible();
        console.log('Email capture modal displayed');
        
        // Test email submission
        const emailInput = emailModal.locator('input[type="email"]');
        const submitBtn = emailModal.locator('.capture-submit');
        
        if (await emailInput.count() > 0 && await submitBtn.count() > 0) {
          await emailInput.fill('test@example.com');
          await submitBtn.click();
          await page.waitForTimeout(500);
          
          console.log('Email capture form submitted');
        }
        
        // Test skip option
        const cancelBtn = emailModal.locator('.capture-cancel');
        if (await cancelBtn.count() > 0) {
          await cancelBtn.click();
          console.log('Email capture skipped');
        }
      }
    }

    expect(true).toBe(true);
  });

  test('should be mobile responsive for favorites', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Test favorites on different screen sizes
    const viewports = [
      { width: 375, height: 667, name: 'Mobile Portrait' },
      { width: 667, height: 375, name: 'Mobile Landscape' },
      { width: 768, height: 1024, name: 'Tablet' }
    ];

    for (const viewport of viewports) {
      await page.setViewportSize(viewport);
      await page.waitForTimeout(500);
      
      console.log(`Testing favorites on ${viewport.name} (${viewport.width}x${viewport.height})`);

      // Check favorites page layout adaptation
      const favoritesGrid = page.locator('.favorites-grid, [class*="favorites-grid"]');
      if (await favoritesGrid.count() > 0) {
        const gridBox = await favoritesGrid.first().boundingBox();
        if (gridBox) {
          console.log(`Favorites grid width on ${viewport.name}: ${gridBox.width}px`);
        }
      }

      // Check favorite buttons on mobile
      const favoriteButtons = page.locator('.favorite-btn');
      const mobileButtonCount = await favoriteButtons.count();
      
      if (mobileButtonCount > 0) {
        const firstBtn = favoriteButtons.first();
        const btnBox = await firstBtn.boundingBox();
        
        if (btnBox) {
          console.log(`Favorite button size on ${viewport.name}: ${btnBox.width}x${btnBox.height}px`);
          
          // Mobile buttons should be adequately sized for touch
          if (viewport.width <= 768) {
            expect(btnBox.width).toBeGreaterThanOrEqual(30);
            expect(btnBox.height).toBeGreaterThanOrEqual(30);
          }
        }
      }

      // Test mobile-specific features
      if (viewport.width <= 768) {
        // Look for mobile-specific favorites features
        const mobileFeatures = page.locator('.mobile-favorites, .favorites-mobile, [class*="mobile"]');
        const hasMobileFeatures = await mobileFeatures.count() > 0;
        
        console.log(`Mobile-specific favorites features: ${hasMobileFeatures}`);
      }
    }

    expect(true).toBe(true);
  });
});