// Test localStorage detection for debugging
// Run this in browser console on the myfavorites page

console.log('=== TESTING LOCALSTORAGE DETECTION ===');

// Check what's in localStorage
const userInfo = localStorage.getItem('brag-book-user-info');
const favorites = localStorage.getItem('brag-book-favorites');

console.log('Raw userInfo from localStorage:', userInfo);
console.log('Raw favorites from localStorage:', favorites);

if (userInfo) {
    try {
        const parsed = JSON.parse(userInfo);
        console.log('Parsed userInfo:', parsed);
        console.log('Has email?', !!(parsed && parsed.email));
        console.log('Email value:', parsed.email);
    } catch (e) {
        console.error('Error parsing userInfo:', e);
    }
}

if (favorites) {
    try {
        const parsed = JSON.parse(favorites);
        console.log('Parsed favorites:', parsed);
        console.log('Favorites count:', parsed.length);
    } catch (e) {
        console.error('Error parsing favorites:', e);
    }
}

// Check if the main app exists
console.log('window.bragBookGalleryApp exists:', !!window.bragBookGalleryApp);

// Check DOM elements
const favoritesPage = document.getElementById('brag-book-gallery-favorites');
const emailCapture = document.getElementById('favoritesEmailCapture');
const gridContainer = document.getElementById('favoritesGridContainer');
const loadingEl = document.getElementById('favoritesLoading');

console.log('DOM elements found:');
console.log('- favoritesPage:', !!favoritesPage);
console.log('- emailCapture:', !!emailCapture);
console.log('- gridContainer:', !!gridContainer);
console.log('- loadingEl:', !!loadingEl);

// Check current visibility
if (emailCapture) console.log('emailCapture display:', emailCapture.style.display);
if (gridContainer) console.log('gridContainer display:', gridContainer.style.display);
if (loadingEl) console.log('loadingEl display:', loadingEl.style.display);