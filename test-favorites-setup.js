// Test localStorage setup for favorites debugging
// Run this in browser console to set up test data

// Set up test user info
const testUserInfo = {
    email: 'test@example.com',
    name: 'Test User',
    first_name: 'Test',
    last_name: 'User',
    phone: '(555) 123-4567',
    id: '12345'
};

// Set up test favorites (stored as array in localStorage, converted to Set by favorites manager)
const testFavorites = ['123', '456', '789'];

// Store in localStorage
localStorage.setItem('brag-book-user-info', JSON.stringify(testUserInfo));
localStorage.setItem('brag-book-favorites', JSON.stringify(testFavorites));

console.log('Test localStorage data set up:');
console.log('User info:', JSON.parse(localStorage.getItem('brag-book-user-info')));
console.log('Favorites:', JSON.parse(localStorage.getItem('brag-book-favorites')));
console.log('Now visit: http://bragbook.local/gallery/myfavorites/');