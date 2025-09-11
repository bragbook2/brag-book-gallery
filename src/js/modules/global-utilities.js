import BRAGbookGalleryApp from './main-app.js';
import { NudityWarningManager, PhoneFormatter } from './utilities.js';

/**
 * Global utility functions for the BRAG book Gallery
 * Contains grid layout management, procedure filtering, case loading, and image handling
 */

/**
 * Update the gallery grid column layout and save preference
 * @param {number} columns - Number of columns to display (1-4)
 */
window.updateGridLayout = function(columns) {
	const grid = document.querySelector('.brag-book-gallery-case-grid');
	if (!grid) return;

	// Only allow grid changes on desktop devices
	const isDesktop = window.innerWidth >= 1024;
	if (!isDesktop) return; // Mobile/tablet use responsive grid

	// Mark grid as initialized to prevent animation conflicts
	grid.classList.add('grid-initialized');

	// Update grid columns data attribute for CSS grid changes
	grid.setAttribute('data-columns', columns);

	// Update button active states to reflect current selection
	const buttons = document.querySelectorAll('.brag-book-gallery-grid-btn');
	buttons.forEach(btn => {
		const btnCols = parseInt(btn.dataset.columns);
		if (btnCols === columns) {
			btn.classList.add('active');
		} else {
			btn.classList.remove('active');
		}
	});

	// Persist user preference across sessions
	localStorage.setItem('bragbook-grid-columns', columns);
};

/**
 * Global state for demographic procedure filters
 * Stores arrays of selected filter values for each category
 */
window.bragBookProcedureFilters = {
	age: [],      // Age ranges like '18-24', '25-34', etc.
	gender: [],   // Gender values
	ethnicity: [], // Ethnicity values
	height: [],   // Height ranges
	weight: []    // Weight ranges
};

/**
 * Initialize demographic procedure filters
 * Called on page load and after AJAX content updates
 * Always regenerates filters based on current page content
 */
window.initializeProcedureFilters = function() {
	const details = document.getElementById('procedure-filters-details');
	if (details) {
		console.log('Initializing procedure filters...');
		// Clean up any server-generated procedure badges first
		cleanupProcedureBadges();
		// Always regenerate filter options based on current page cards
		generateProcedureFilterOptions();
		// Mark as initialized but don't prevent regeneration
		details.dataset.initialized = 'true';
	}
};

/**
 * Force regenerate procedure filters (useful after AJAX content updates)
 * Clears the initialized flag and regenerates filters
 */
window.regenerateProcedureFilters = function() {
	console.log('Force regenerating procedure filters...');
	const details = document.getElementById('procedure-filters-details');
	if (details) {
		// Clear the initialized flag to force regeneration
		details.dataset.initialized = 'false';
		// Clear any existing filter state
		window.bragBookProcedureFilters = {
			age: [],
			gender: [],
			ethnicity: [],
			height: [],
			weight: []
		};
		// Clean up any existing badges first
		cleanupActiveFiltersSection();
		// Regenerate filters
		initializeProcedureFilters();
	}
};

/**
 * Clean up the active filters section - remove all badges and hide section
 */
function cleanupActiveFiltersSection() {
	const activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
	if (activeFiltersSection) {
		// Clear all badges
		const badgesContainers = activeFiltersSection.querySelectorAll('.brag-book-gallery-filter-badges');
		badgesContainers.forEach(container => {
			container.innerHTML = '';
		});
		
		// Hide the entire section
		activeFiltersSection.style.display = 'none';
		
		// Also remove any standalone badges
		const standaloneBadges = activeFiltersSection.querySelectorAll('.brag-book-gallery-filter-badge');
		standaloneBadges.forEach(badge => badge.remove());
	}
}

/**
 * Clean up any server-generated procedure badges
 * Removes badges that contain procedure names or data-filter-key attributes
 */
function cleanupProcedureBadges() {
	console.log('Cleaning up procedure badges...');
	
	// Find all active filters sections
	const activeFiltersSections = document.querySelectorAll('.brag-book-gallery-active-filters');
	
	activeFiltersSections.forEach(section => {
		// Remove badges with procedure names (like "Non Surgical Skin Tightening")
		const procedureBadges = section.querySelectorAll('.brag-book-gallery-filter-badge');
		
		procedureBadges.forEach(badge => {
			// Check if it's a procedure badge by looking for data-filter-key or remove-filter onclick
			const hasFilterKey = badge.hasAttribute('data-filter-key');
			const hasRemoveFilter = badge.querySelector('button[onclick*="clearProcedureFilter"]');
			const hasSpanWithProcedureName = badge.querySelector('span') && 
				!badge.querySelector('[data-filter-type]'); // Not a demographic filter
			
			if (hasFilterKey || hasRemoveFilter || hasSpanWithProcedureName) {
				console.log('Removing procedure badge:', badge.textContent.trim());
				badge.remove();
			}
		});
		
		// Hide the section if it has no remaining content
		const remainingBadges = section.querySelectorAll('.brag-book-gallery-filter-badge');
		if (remainingBadges.length === 0) {
			section.style.display = 'none';
		}
	});
	
	// Also clean up any Clear All buttons that might be showing
	const clearAllButtons = document.querySelectorAll('.brag-book-gallery-clear-all-filters');
	clearAllButtons.forEach(button => {
		// Only hide if there are no demographic filters active
		const activeCheckboxes = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]:checked');
		if (activeCheckboxes.length === 0) {
			button.style.display = 'none';
		}
	});
}

/**
 * Generate filter options HTML based on available case data
 * Uses either complete dataset from config or falls back to DOM scanning
 */
window.generateProcedureFilterOptions = function() {
	// Try multiple possible filter container IDs/classes
	const container = document.getElementById('brag-book-gallery-filters') ||
					  document.querySelector('.brag-book-gallery-filter-content') ||
					  document.querySelector('.brag-book-gallery-filters');
	if (!container) {
		// On procedure/case pages, filters might not be needed, so don't warn
		console.log('Filter container not found - likely on a procedure or case page');
		return;
	}

	// Always generate filters based on visible case cards on the page
	const cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	console.log('Filter generation - Scanning case cards on page, found:', cards.length);
	const filterData = {
		age: new Set(),
		gender: new Set(),
		ethnicity: new Set(),
		height: new Set(),
		weight: new Set()
	};

	// First pass: collect actual values to determine which ranges have data
	const ageValues = [];
	const heightValues = [];
	const weightValues = [];

	cards.forEach(card => {
		// Collect raw values
		const age = card.dataset.age;
		if (age) {
			ageValues.push(parseInt(age));
			console.log('Found case with age:', age);
		}

		// Gender
		if (card.dataset.gender) {
			filterData.gender.add(card.dataset.gender);
			console.log('Found case with gender:', card.dataset.gender);
		}

		// Ethnicity
		if (card.dataset.ethnicity) {
			filterData.ethnicity.add(card.dataset.ethnicity);
			console.log('Found case with ethnicity:', card.dataset.ethnicity);
		}

		// Height
		const height = card.dataset.height;
		if (height) {
			const heightNum = parseInt(height);
			const unit = card.dataset.heightUnit || 'in';
			// Convert to inches for consistent comparison
			if (unit === 'cm') {
				heightValues.push(Math.round(heightNum / 2.54));
			} else {
				heightValues.push(heightNum);
			}
		}

		// Weight
		const weight = card.dataset.weight;
		if (weight) {
			const weightNum = parseInt(weight);
			const unit = card.dataset.weightUnit || 'lbs';
			// Convert to lbs for consistent comparison
			if (unit === 'kg') {
				weightValues.push(Math.round(weightNum * 2.205));
			} else {
				weightValues.push(weightNum);
			}
		}
	});

	// Only add age ranges that have actual data
	if (ageValues.some(age => age >= 18 && age < 25)) filterData.age.add('18-24');
	if (ageValues.some(age => age >= 25 && age < 35)) filterData.age.add('25-34');
	if (ageValues.some(age => age >= 35 && age < 45)) filterData.age.add('35-44');
	if (ageValues.some(age => age >= 45 && age < 55)) filterData.age.add('45-54');
	if (ageValues.some(age => age >= 55 && age < 65)) filterData.age.add('55-64');
	if (ageValues.some(age => age >= 65)) filterData.age.add('65+');

	// Only add height ranges that have actual data (in inches)
	if (heightValues.some(h => h < 60)) filterData.height.add('Under 5\'0"');
	if (heightValues.some(h => h >= 60 && h < 64)) filterData.height.add('5\'0" - 5\'3"');
	if (heightValues.some(h => h >= 64 && h < 68)) filterData.height.add('5\'4" - 5\'7"');
	if (heightValues.some(h => h >= 68 && h < 72)) filterData.height.add('5\'8" - 5\'11"');
	if (heightValues.some(h => h >= 72)) filterData.height.add('6\'0" and above');

	// Only add weight ranges that have actual data (in lbs)
	if (weightValues.some(w => w < 120)) filterData.weight.add('Under 120 lbs');
	if (weightValues.some(w => w >= 120 && w < 150)) filterData.weight.add('120-149 lbs');
	if (weightValues.some(w => w >= 150 && w < 180)) filterData.weight.add('150-179 lbs');
	if (weightValues.some(w => w >= 180 && w < 210)) filterData.weight.add('180-209 lbs');
	if (weightValues.some(w => w >= 210)) filterData.weight.add('210+ lbs');

	// Generate the filter HTML
	generateFilterHTML(container, filterData);
};

/**
 * Generate the filter interface HTML from collected filter data
 * @param {HTMLElement} container - Container to insert filter HTML
 * @param {Object} filterData - Categorized filter options
 */
window.generateFilterHTML = function(container, filterData) {

	// Build filter HTML
	let html = '';

	// Age filter
	if (filterData.age.size > 0) {
		html += '<details class="brag-book-gallery-filter">';
		html += '<summary class="brag-book-gallery-filter-label">';
		html += '<span class="brag-book-gallery-filter-label__name">Age</span>';
		html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-filter-options">';
		Array.from(filterData.age).sort().forEach(value => {
			const id = `procedure-filter-age-${value.replace(/\s+/g, '-')}`;
			html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="age">
				<label for="${id}">${value}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Gender filter
	if (filterData.gender.size > 0) {
		html += '<details class="brag-book-gallery-filter">';
		html += '<summary class="brag-book-gallery-filter-label">';
		html += '<span class="brag-book-gallery-filter-label__name">Gender</span>';
		html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-filter-options">';
		Array.from(filterData.gender).sort().forEach(value => {
			const id = `procedure-filter-gender-${value}`;
			const displayValue = value.charAt(0).toUpperCase() + value.slice(1);
			html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="gender">
				<label for="${id}">${displayValue}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Ethnicity filter
	if (filterData.ethnicity.size > 0) {
		html += '<details class="brag-book-gallery-filter">';
		html += '<summary class="brag-book-gallery-filter-label">';
		html += '<span class="brag-book-gallery-filter-label__name">Ethnicity</span>';
		html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-filter-options">';
		Array.from(filterData.ethnicity).sort().forEach(value => {
			const id = `procedure-filter-ethnicity-${value.replace(/\s+/g, '-')}`;
			const displayValue = value.charAt(0).toUpperCase() + value.slice(1);
			html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="ethnicity">
				<label for="${id}">${displayValue}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Height filter
	if (filterData.height.size > 0) {
		html += '<details class="brag-book-gallery-filter">';
		html += '<summary class="brag-book-gallery-filter-label">';
		html += '<span class="brag-book-gallery-filter-label__name">Height</span>';
		html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-filter-options">';
		Array.from(filterData.height).sort().forEach(value => {
			const id = `procedure-filter-height-${value.replace(/\s+/g, '-')}`;
			html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="height">
				<label for="${id}">${value}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	// Weight filter
	if (filterData.weight.size > 0) {
		html += '<details class="brag-book-gallery-filter">';
		html += '<summary class="brag-book-gallery-filter-label">';
		html += '<span class="brag-book-gallery-filter-label__name">Weight</span>';
		html += '<svg class="brag-book-gallery-filter-label__arrow" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">';
		html += '<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
		html += '</svg>';
		html += '</summary>';
		html += '<ul class="brag-book-gallery-filter-options">';
		Array.from(filterData.weight).sort().forEach(value => {
			const id = `procedure-filter-weight-${value.replace(/\s+/g, '-')}`;
			html += `<li class="brag-book-gallery-filter-option">
				<input type="checkbox" id="${id}" value="${value}" data-filter-type="weight">
				<label for="${id}">${value}</label>
			</li>`;
		});
		html += '</ul>';
		html += '</details>';
	}

	container.innerHTML = html || '<p>No filters available</p>';

	// Add event listeners to all checkboxes
	const filterCheckboxes = container.querySelectorAll('input[type="checkbox"]');
	console.log('Setting up event listeners for', filterCheckboxes.length, 'filter checkboxes');
	filterCheckboxes.forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			console.log('Checkbox changed:', this.dataset.filterType, '=', this.value, 'checked:', this.checked);
			console.log('About to call applyProcedureFilters...');
			try {
				if (typeof window.applyProcedureFilters === 'function') {
					console.log('Calling window.applyProcedureFilters()');
					window.applyProcedureFilters();
					console.log('Successfully called applyProcedureFilters');
				} else {
					console.error('window.applyProcedureFilters function not found, typeof:', typeof window.applyProcedureFilters);
				}
			} catch (error) {
				console.error('Error calling applyProcedureFilters:', error);
			}
		});
	});

	// Show/hide the details element based on whether filters exist
	const details = document.getElementById('procedure-filters-details');
	if (details) {
		const hasFilters = html && html !== '<p>No filters available</p>';
		if (hasFilters) {
			details.style.display = '';
		} else {
			details.style.display = 'none';
		}
	}
};

/**
 * Apply active demographic filters to case cards
 * Handles both complete dataset filtering and DOM-based filtering
 */
window.applyProcedureFilters = function() {
	console.log('applyProcedureFilters called');
	const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input:checked');
	console.log('Found checked filters:', checkboxes.length);

	// Reset filter state
	window.bragBookProcedureFilters = {
		age: [],
		gender: [],
		ethnicity: [],
		height: [],
		weight: []
	};

	// Collect selected filters
	checkboxes.forEach(checkbox => {
		const filterType = checkbox.dataset.filterType;
		const value = checkbox.value;
		console.log('Adding filter:', filterType, '=', value);
		if (window.bragBookProcedureFilters[filterType]) {
			window.bragBookProcedureFilters[filterType].push(value);
		}
	});

	console.log('Active filters:', window.bragBookProcedureFilters);

	// Check if any filters are selected
	const hasActiveFilters = Object.values(window.bragBookProcedureFilters).some(arr => arr.length > 0);

	// Update filter badges
	updateFilterBadges();

	// Always filter only the visible cards on the current page
	let cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	
	// Fallback selectors if the primary selector doesn't find cards
	if (cards.length === 0) {
		cards = document.querySelectorAll('.brag-book-gallery-case-grid .brag-book-gallery-case-card');
	}
	if (cards.length === 0) {
		cards = document.querySelectorAll('.brag-book-gallery-case-card');
	}
	
	console.log('Filtering cards, found:', cards.length, 'cards');
	
	// Debug: log the first card's data attributes
	if (cards.length > 0) {
		const firstCard = cards[0];
		console.log('First card data attributes:', {
			age: firstCard.dataset.age,
			gender: firstCard.dataset.gender,
			ethnicity: firstCard.dataset.ethnicity,
			caseId: firstCard.dataset.caseId
		});
	}

	if (!hasActiveFilters) {
		// No filters selected, show all currently loaded cards
		cards.forEach(card => {
			card.style.display = '';
		});

		// Hide filter results message when no filters are active
		const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
		if (resultsEl) {
			resultsEl.style.display = 'none';
		}

		// Show Load More button if it exists since no filters are active
		const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') ||
						   document.querySelector('.brag-book-gallery-load-more button');
		const loadMoreContainer = loadMoreBtn ? (loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement) : null;
		if (loadMoreContainer && loadMoreBtn.hasAttribute('data-start-page')) {
			loadMoreContainer.style.display = '';
		}

		return;
	}

	// Filter the visible cards based on their data attributes
	let visibleCount = 0;
	cards.forEach((card, index) => {
		let show = true;
		const caseId = card.dataset.caseId;

		// Check age filter
		if (window.bragBookProcedureFilters.age.length > 0) {
			const cardAge = parseInt(card.dataset.age);
			console.log('Case', caseId, 'has age:', cardAge, '(type:', typeof cardAge, ') checking against filters:', window.bragBookProcedureFilters.age);
			let ageMatch = false;
			window.bragBookProcedureFilters.age.forEach(range => {
				console.log('Checking age range:', range, 'against card age:', cardAge);
				if (range === '18-24' && cardAge >= 18 && cardAge < 25) {
					console.log('Matched 18-24 range');
					ageMatch = true;
				}
				else if (range === '25-34' && cardAge >= 25 && cardAge < 35) {
					console.log('Matched 25-34 range');
					ageMatch = true;
				}
				else if (range === '35-44' && cardAge >= 35 && cardAge < 45) {
					console.log('Matched 35-44 range');
					ageMatch = true;
				}
				else if (range === '45-54' && cardAge >= 45 && cardAge < 55) {
					console.log('Matched 45-54 range');
					ageMatch = true;
				}
				else if (range === '55-64' && cardAge >= 55 && cardAge < 65) {
					console.log('Matched 55-64 range');
					ageMatch = true;
				}
				else if (range === '65+' && cardAge >= 65) {
					console.log('Matched 65+ range');
					ageMatch = true;
				}
			});
			console.log('Case', caseId, 'age match result:', ageMatch);
			if (!ageMatch) {
				show = false;
			}
		}

		// Check gender filter
		if (show && window.bragBookProcedureFilters.gender.length > 0) {
			const cardGender = (card.dataset.gender || '').toLowerCase();
			const filterGenders = window.bragBookProcedureFilters.gender.map(g => g.toLowerCase());
			console.log('Case', caseId, 'has gender:', cardGender, 'checking against filters:', filterGenders);

			if (!filterGenders.includes(cardGender)) {
				console.log('Case', caseId, 'gender does not match');
				show = false;
			}
		}

		// Check ethnicity filter
		if (show && window.bragBookProcedureFilters.ethnicity.length > 0) {
			const cardEthnicity = (card.dataset.ethnicity || '').toLowerCase();
			const filterEthnicities = window.bragBookProcedureFilters.ethnicity.map(e => e.toLowerCase());
			console.log('Case', caseId, 'has ethnicity:', cardEthnicity, 'checking against filters:', filterEthnicities);

			if (!filterEthnicities.includes(cardEthnicity)) {
				console.log('Case', caseId, 'ethnicity does not match');
				show = false;
			}
		}

		// Check height filter
		if (show && window.bragBookProcedureFilters.height.length > 0) {
			const cardHeight = parseInt(card.dataset.height);
			let heightMatch = false;

			window.bragBookProcedureFilters.height.forEach(range => {
				// Assume height is in inches (matching the ranges we generate)
				if (range === 'Under 5\'0"' && cardHeight < 60) heightMatch = true;
				else if (range === '5\'0" - 5\'3"' && cardHeight >= 60 && cardHeight < 64) heightMatch = true;
				else if (range === '5\'4" - 5\'7"' && cardHeight >= 64 && cardHeight < 68) heightMatch = true;
				else if (range === '5\'8" - 5\'11"' && cardHeight >= 68 && cardHeight < 72) heightMatch = true;
				else if (range === '6\'0" and above' && cardHeight >= 72) heightMatch = true;
			});
			
			console.log('Case', caseId, 'height match:', heightMatch);
			if (!heightMatch) show = false;
		}

		// Check weight filter
		if (show && window.bragBookProcedureFilters.weight.length > 0) {
			const cardWeight = parseInt(card.dataset.weight);
			let weightMatch = false;

			window.bragBookProcedureFilters.weight.forEach(range => {
				// Assume weight is in lbs (matching the ranges we generate)
				if (range === 'Under 120 lbs' && cardWeight < 120) weightMatch = true;
				else if (range === '120-149 lbs' && cardWeight >= 120 && cardWeight < 150) weightMatch = true;
				else if (range === '150-179 lbs' && cardWeight >= 150 && cardWeight < 180) weightMatch = true;
				else if (range === '180-209 lbs' && cardWeight >= 180 && cardWeight < 210) weightMatch = true;
				else if (range === '210+ lbs' && cardWeight >= 210) weightMatch = true;
			});
			
			console.log('Case', caseId, 'weight match:', weightMatch);
			if (!weightMatch) show = false;
		}

		// Show/hide card
		console.log('Case', caseId, 'final show decision:', show);
		card.style.display = show ? '' : 'none';
		if (show) visibleCount++;
	});

	// Only show filter results message if there are active filters
	if (hasActiveFilters) {
		const resultsMessage = visibleCount === 0 ? 'No cases match the selected filters' :
			`Filter applied: Showing ${visibleCount} matching case${visibleCount !== 1 ? 's' : ''}`;

		// Add/update results message if needed
		const resultsEl = getFilterResultsElement();
		resultsEl.textContent = resultsMessage;
		resultsEl.style.display = 'block';
	} else {
		// Hide filter results message when no filters are active
		const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
		if (resultsEl) {
			resultsEl.style.display = 'none';
		}
	}

	// Close the details after applying filters
	const details = document.getElementById('procedure-filters-details');
	if (details) {
		details.open = false;
		// Add visual indicator if filters are active
		const toggle = details.querySelector('.brag-book-gallery-filter-dropdown__toggle');
		if (toggle) {
			toggle.classList.add('has-active-filters');
		}
	}

	// Hide Load More button when filters are active
	const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') ||
					   document.querySelector('.brag-book-gallery-load-more button');
	const loadMoreContainer = loadMoreBtn ? (loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement) : null;
	if (loadMoreContainer) {
		loadMoreContainer.style.display = 'none';
	}
};

/**
 * Load specific cases from server when filtering with complete dataset
 * @param {Array<string>} matchingCaseIds - Array of case IDs that match current filters
 */
window.loadFilteredCases = function(matchingCaseIds) {
	// First, hide all current cards
	const allCards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	allCards.forEach(card => {
		card.style.display = 'none';
	});

	// Show matching cards that are already loaded
	let visibleCount = 0;
	let needToLoad = [];

	matchingCaseIds.forEach(caseId => {
		const existingCard = document.querySelector(`.brag-book-gallery-case-card[data-case-id="${caseId}"]`);
		if (existingCard) {
			existingCard.style.display = '';
			visibleCount++;
		} else {
			// This case needs to be loaded
			needToLoad.push(caseId);
		}
	});

	// If we need to load more cases, try optimized loading first
	if (needToLoad.length > 0) {
		// Show loading indicator
		const container = document.querySelector('.brag-book-gallery-case-grid') ||
		                  document.querySelector('.brag-book-gallery-case-grid');

		if (container) {
			// Add loading message
			const loadingMsg = document.createElement('div');
			loadingMsg.className = 'filter-loading-message';
			loadingMsg.textContent = `Loading ${needToLoad.length} additional matching cases...`;
			loadingMsg.style.cssText = 'padding: 20px; text-align: center; font-style: italic;';
			container.appendChild(loadingMsg);

			// Try optimized frontend cache first, then fallback to AJAX
			loadFilteredCasesOptimized(needToLoad)
				.then(result => {
					// Remove loading message
					const loadingMsg = container.querySelector('.filter-loading-message');
					if (loadingMsg) loadingMsg.remove();

					if (result.success && result.html) {
						// Add the new cards to the container
						const tempDiv = document.createElement('div');
						tempDiv.innerHTML = result.html;

						// Append each new card
						const newCards = tempDiv.querySelectorAll('.brag-book-gallery-case-card');
						newCards.forEach(card => {
							container.appendChild(card);
							visibleCount++;
						});

						// Update the count
						updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
					} else {
						// No cases found or error occurred
						updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
					}
				})
				.catch(error => {
					console.error('Error loading filtered cases:', error);
					const loadingMsg = container.querySelector('.filter-loading-message');
					if (loadingMsg) {
						loadingMsg.textContent = 'Failed to load additional cases. Showing only currently loaded matches.';
						setTimeout(() => loadingMsg.remove(), 3000);
					}
					// Still update the count with what we have
					updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
				});
		}
	} else {
		// All matching cases are already loaded, just update the count
		updateFilteredCount(visibleCount, window.bragBookCompleteDataset ? window.bragBookCompleteDataset.length : allCards.length);
	}

	// Hide Load More button when filters are active since we're showing all matching results
	const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') ||
					   document.querySelector('.brag-book-gallery-load-more button');
	const loadMoreContainer = loadMoreBtn ? (loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement) : null;
	if (loadMoreContainer) {
		loadMoreContainer.style.display = 'none';
	}
};

/**
 * Optimized loading for filtered cases with frontend cache and AJAX fallback
 * @param {Array<string>} caseIds - Array of case IDs to load
 * @returns {Promise} Promise that resolves with result object
 */
async function loadFilteredCasesOptimized(caseIds) {
	try {
		// First, try to find cases in frontend cache/dataset
		let foundCases = [];
		let missingCaseIds = [];

		// Check if we have the complete dataset available
		if (window.bragBookCompleteDataset && Array.isArray(window.bragBookCompleteDataset)) {
			caseIds.forEach(caseId => {
				const foundCase = window.bragBookCompleteDataset.find(caseData => 
					String(caseData.id) === String(caseId)
				);
				if (foundCase) {
					foundCases.push(foundCase);
				} else {
					missingCaseIds.push(caseId);
				}
			});
		} else {
			// No frontend cache, need to load all via AJAX
			missingCaseIds = [...caseIds];
		}

		let html = '';

		// Generate HTML for cases found in frontend cache
		if (foundCases.length > 0) {
			foundCases.forEach(caseData => {
				html += generateFilteredCaseHTML(caseData);
			});
		}

		// If we have missing cases, load them via AJAX fallback
		if (missingCaseIds.length > 0) {
			try {
				const ajaxResult = await loadFilteredCasesViaAjax(missingCaseIds);
				if (ajaxResult.success && ajaxResult.html) {
					html += ajaxResult.html;
				}
			} catch (ajaxError) {
				console.warn('AJAX fallback failed for filtered cases:', ajaxError);
				// Continue with what we have from frontend cache
			}
		}

		return {
			success: html.length > 0,
			html: html,
			casesFound: foundCases.length + (missingCaseIds.length > 0 ? 1 : 0), // Approximate
			fromCache: foundCases.length,
			fromAjax: missingCaseIds.length
		};

	} catch (error) {
		console.error('Error in optimized filtered cases loading:', error);
		// Fallback to AJAX for all cases
		return await loadFilteredCasesViaAjax(caseIds);
	}
}

/**
 * Fallback method using original AJAX approach for filtered cases
 * @param {Array<string>} caseIds - Array of case IDs to load
 * @returns {Promise} Promise that resolves with result object
 */
async function loadFilteredCasesViaAjax(caseIds) {
	try {
		const formData = new FormData();
		formData.append('action', 'brag_book_gallery_load_filtered_cases');
		formData.append('case_ids', caseIds.join(','));
		formData.append('nonce', typeof bragBookAjax !== 'undefined' ? bragBookAjax.nonce : '');

		const response = await fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData
		});

		const data = await response.json();

		if (data.success && data.data) {
			return {
				success: true,
				html: data.data.html || '',
				casesFound: data.data.casesFound || 0,
				totalCount: data.data.totalCount || caseIds.length
			};
		} else {
			return {
				success: false,
				error: data.data?.message || 'Failed to load filtered cases'
			};
		}

	} catch (error) {
		console.error('AJAX error loading filtered cases:', error);
		return {
			success: false,
			error: error.message
		};
	}
}

/**
 * Generate HTML for a filtered case from frontend data
 * @param {Object} caseData - Case data object
 * @returns {string} Generated HTML for the case
 */
function generateFilteredCaseHTML(caseData) {
	// Transform case data to match expected format
	const caseId = caseData.id || '';
	
	// Extract main image from photoSets
	let mainImageUrl = '';
	if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
		const firstPhotoset = caseData.photoSets[0];
		mainImageUrl = firstPhotoset.postProcessedImageLocation || 
					   firstPhotoset.beforeLocationUrl || 
					   firstPhotoset.afterLocationUrl1 || '';
	}

	// Extract procedure title
	let procedureTitle = 'Unknown Procedure';
	if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
		procedureTitle = caseData.procedures[0].name || procedureTitle;
	}

	// Generate case HTML (reusing the helper function from load more)
	let html = `<article class="brag-book-gallery-case-card" data-case-id="${escapeHtml(String(caseId))}" data-card="true">`;
	html += `<div class="brag-book-gallery-case-image-container" onclick="loadCaseDetails('${caseId}')">`;
	
	if (mainImageUrl) {
		html += `<img src="${escapeHtml(mainImageUrl)}" alt="${escapeHtml(procedureTitle)} case" loading="lazy">`;
	}
	
	// Add nudity warning only if the active procedure has data-nudity="true"
	const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
	const shouldAddNudityWarning = activeLink && activeLink.dataset.nudity === 'true';
	
	if (shouldAddNudityWarning) {
		html += '<div class="brag-book-gallery-nudity-overlay">';
		html += '<div class="brag-book-gallery-nudity-warning">';
		html += '<div class="brag-book-gallery-nudity-warning-content">';
		html += '<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>';
		html += '<p class="brag-book-gallery-nudity-warning-caption">This procedure may contain nudity or sensitive content. Click to proceed if you wish to view.</p>';
		html += '<button class="brag-book-gallery-nudity-warning-button" type="button">Proceed</button>';
		html += '</div>';
		html += '</div>';
		html += '</div>';
	}
	
	html += '<div class="brag-book-gallery-case-overlay">';
	html += '<span class="brag-book-gallery-case-view-text">View Case</span>';
	html += '</div>';
	html += '</div>';
	
	html += '<div class="brag-book-gallery-case-info">';
	html += `<h3>${escapeHtml(procedureTitle)}</h3>`;
	html += '</div>';
	
	html += '</article>';
	
	return html;
}

/**
 * Update the display of filtered results count
 * @param {number} shown - Number of cases currently visible
 * @param {number} total - Total number of cases available
 */
window.updateFilteredCount = function(shown, total) {
	// Update the count label
	const countLabel = document.querySelector('.brag-book-gallery-favorite-count-label') ||
	                   document.querySelector('.cases-count');
	if (countLabel) {
		countLabel.textContent = `Showing ${shown} of ${total}`;
	}

	// Note: Filter results message is handled by applyProcedureFilters function
	// This function only updates the count label, not the filter results message
};

/**
 * Clear all active demographic filters and show all cases
 */
window.clearProcedureFilters = function() {
	console.log('clearProcedureFilters called');
	
	// 1. Uncheck all filter checkboxes
	const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]');
	console.log('Unchecking', checkboxes.length, 'filter checkboxes');
	checkboxes.forEach(checkbox => {
		checkbox.checked = false;
	});
	
	// 2. Reset global filter state to empty arrays
	if (window.bragBookProcedureFilters) {
		window.bragBookProcedureFilters.age = [];
		window.bragBookProcedureFilters.gender = [];
		window.bragBookProcedureFilters.ethnicity = [];
		window.bragBookProcedureFilters.height = [];
		window.bragBookProcedureFilters.weight = [];
		console.log('Reset global filter state');
	}
	
	// 3. Show all case cards
	const cards = document.querySelectorAll('.brag-book-gallery-case-card');
	console.log('Showing', cards.length, 'case cards');
	cards.forEach(card => {
		card.style.display = '';
		card.style.visibility = '';
	});
	
	// 4. Remove has-active-filters class from wrapper
	const galleryWrapper = document.querySelector('.brag-book-gallery-wrapper');
	if (galleryWrapper && galleryWrapper.classList.contains('has-active-filters')) {
		console.log('Removing has-active-filters class from wrapper');
		galleryWrapper.classList.remove('has-active-filters');
	}
	
	// 5. Hide the active filters section and clear badges
	const activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
	if (activeFiltersSection) {
		console.log('Hiding active filters section');
		activeFiltersSection.style.display = 'none';
		activeFiltersSection.innerHTML = '';
	}
	
	// 6. Hide filter results message
	const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
	if (resultsEl) {
		console.log('Hiding filter results message');
		resultsEl.style.display = 'none';
	}
	
	// 7. Show Load More button if it exists
	const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') ||
					   document.querySelector('.brag-book-gallery-load-more button');
	if (loadMoreBtn) {
		const loadMoreContainer = loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement;
		if (loadMoreContainer) {
			console.log('Showing Load More button');
			loadMoreContainer.style.display = '';
		}
	}
	
	// 8. Hide the Clear All button
	const clearAllButton = document.querySelector('.brag-book-gallery-clear-all-filters');
	if (clearAllButton) {
		console.log('Hiding Clear All button');
		clearAllButton.style.display = 'none';
	}
	
	// 9. Remove has-active-filters class from filter dropdown toggle
	const filterDropdown = document.getElementById('procedure-filters-details');
	if (filterDropdown) {
		filterDropdown.open = false;
		const summary = filterDropdown.querySelector('summary');
		if (summary && summary.classList.contains('has-active-filters')) {
			console.log('Removing has-active-filters class from filter dropdown summary');
			summary.classList.remove('has-active-filters');
		}
	}
	
	console.log('clearProcedureFilters completed');
};

/**
 * Synchronize image heights within a case card for consistent display
 * @param {HTMLImageElement} img - The image element that just loaded
 */
window.syncImageHeights = function(img) {
	// Hide skeleton loader and show image
	img.style.opacity = '1';
	// Find the skeleton loader in the image container (new structure with anchor wrapping picture)
	const container = img.closest('.brag-book-gallery-image-container');
	if (container) {
		const loader = container.querySelector('.brag-book-gallery-skeleton-loader');
		if (loader) loader.style.display = 'none';
	}

	// Get the parent case images container
	const caseContainer = img.closest('.brag-book-gallery-case-images');
	if (!caseContainer) return;

	// Get both images in this case
	const images = caseContainer.querySelectorAll('img');

	// Check if both images are loaded
	let allLoaded = true;
	images.forEach(image => {
		if (!image.complete || !image.naturalHeight) {
			allLoaded = false;
		}
	});

	if (!allLoaded) return; // Wait for both images to load

	// Find the maximum aspect ratio (height/width)
	let maxAspectRatio = 0;
	images.forEach(image => {
		const aspectRatio = image.naturalHeight / image.naturalWidth;
		if (aspectRatio > maxAspectRatio) {
			maxAspectRatio = aspectRatio;
		}
	});

	// Set the padding-bottom based on the tallest image's aspect ratio
	const imageContainers = caseContainer.querySelectorAll('.brag-book-gallery-image-container');
	imageContainers.forEach(container => {
		container.style.paddingBottom = (maxAspectRatio * 100) + '%';
	});
};

/**
 * Load case details into gallery content (backward compatibility wrapper)
 * @param {string} caseId - The case ID to load
 * @param {string} procedureId - The procedure ID
 * @param {string} procedureSlug - The procedure URL slug
 * @param {string} procedureIds - Comma-separated procedure IDs
 */
window.loadCaseDetails = function(caseId, procedureId, procedureSlug, procedureIds) {
	// For backwards compatibility, call the new function without procedure name
	window.loadCaseDetailsWithName(caseId, procedureId, procedureSlug, '', procedureIds);
};

/**
 * Load case details with full context including procedure name
 * @param {string} caseId - The case ID to load
 * @param {string} procedureId - The procedure ID
 * @param {string} procedureSlug - The procedure URL slug
 * @param {string} procedureName - Display name for the procedure
 * @param {string} procedureIds - Comma-separated procedure IDs
 */
window.loadCaseDetailsWithName = function(caseId, procedureId, procedureSlug, procedureName, procedureIds) {
	const galleryContent = document.getElementById('gallery-content');
	if (!galleryContent) {
		console.error('Gallery content container not found');
		return;
	}

	// If procedureIds not provided, try to get from the case card
	if (!procedureIds) {
		const caseCard = document.querySelector(`.brag-book-gallery-case-card[data-case-id="${caseId}"]`);
		if (caseCard && caseCard.dataset.procedureIds) {
			procedureIds = caseCard.dataset.procedureIds;
		}
	}

	// Show loading state
	galleryContent.innerHTML = '<div class="brag-book-gallery-loading">Loading case details...</div>';

	// Update URL to reflect the case being viewed
	if (procedureSlug && window.history && window.history.pushState) {
		// Get the base gallery URL
		const galleryWrapper = document.querySelector('.brag-book-gallery-wrapper');
		let basePath = galleryWrapper?.dataset.baseUrl || window.location.pathname;

		// Clean up the base path - remove any existing procedure/case segments
		basePath = basePath.replace(/\/[^\/]+\/\d+\/?$/, ''); // Remove /procedure/123
		basePath = basePath.replace(/\/[^\/]+\/?$/, ''); // Remove /procedure if present
		basePath = basePath.replace(/\/$/, ''); // Remove trailing slash

		// If we still don't have a proper base, use the first segment
		if (!basePath || basePath === '') {
			const pathSegments = window.location.pathname.split('/').filter(s => s);
			basePath = pathSegments.length > 0 ? '/' + pathSegments[0] : '';
		}

		// Build the new URL: /gallery-page/procedure-slug/case-id
		const newUrl = `${basePath}/${procedureSlug}/${caseId}`;

		// Update browser URL without page reload
		window.history.pushState(
			{ caseId: caseId, procedureId: procedureId, procedureSlug: procedureSlug },
			'',
			newUrl
		);
	}

	// Prepare request parameters
	const requestParams = {
		action: 'brag_book_gallery_load_case_details',
		case_id: caseId,
		nonce: bragBookGalleryConfig.nonce
	};

	// Add procedure ID if provided
	if (procedureId) {
		requestParams.procedure_id = procedureId;
	}

	// Add procedure slug if provided (for display context)
	if (procedureSlug) {
		requestParams.procedure_slug = procedureSlug;
	}

	// Add procedure name if provided (for display)
	if (procedureName) {
		requestParams.procedure_name = procedureName;
	}

	// Add procedure IDs if provided (for API request)
	if (procedureIds) {
		requestParams.procedure_ids = procedureIds;
	}

	// Make AJAX request to load case details
	fetch(bragBookGalleryConfig.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams(requestParams)
	})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				galleryContent.innerHTML = data.data.html;

				// Scroll to top of gallery content area smoothly after content loads
				const wrapper = document.querySelector('.brag-book-gallery-wrapper');
				if (wrapper) {
					wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
				} else {
					// Fallback to scrolling to gallery content
					galleryContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			} else {
				let errorMessage = 'Unknown error';
				if (data.data) {
					if (typeof data.data === 'string') {
						errorMessage = data.data;
					} else if (data.data.message) {
						errorMessage = data.data.message;
					} else {
						errorMessage = JSON.stringify(data.data);
					}
				}
				galleryContent.innerHTML = '<div class="brag-book-gallery-error">Failed to load case details: ' + errorMessage + '</div>';
			}
		})
		.catch(error => {
			console.error('Error loading case details:', error);
			galleryContent.innerHTML = '<div class="brag-book-gallery-error">Error loading case details. Please try again.</div>';
		});
};

/**
 * Load more cases via direct API (optimized) with AJAX fallback
 * @param {HTMLElement} button - The Load More button element
 */
window.loadMoreCases = function(button) {
	// Disable button and show loading state
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent = 'Loading...';

	// Get data from button attributes
	const startPage = button.getAttribute('data-start-page');
	const procedureIds = button.getAttribute('data-procedure-ids');
	const procedureName = button.getAttribute('data-procedure-name') || '';

	// Check if there's an active procedure filter with nudity
	let hasNudity = false;
	const activeFilterLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
	if (activeFilterLink && activeFilterLink.dataset.nudity === 'true') {
		hasNudity = true;
	}

	// Get all currently loaded case IDs to prevent duplicates
	const loadedCases = document.querySelectorAll('[data-case-id]');
	const loadedIds = Array.from(loadedCases).map(el => el.getAttribute('data-case-id')).filter(Boolean);

	// Try direct API first (optimized), then fallback to AJAX
	loadMoreCasesDirectly(startPage, procedureIds, procedureName, hasNudity, loadedIds)
		.then(result => {
			if (result.success) {
				// Direct API succeeded, process the result
				processLoadMoreResult(result, button, originalText, startPage);
			} else {
				// Direct API failed, fallback to AJAX
				console.log('Direct API failed for load more, falling back to AJAX');
				loadMoreCasesViaAjax(button, startPage, procedureIds, procedureName, hasNudity, loadedIds, originalText);
			}
		})
		.catch(error => {
			console.log('Direct API error for load more, falling back to AJAX:', error);
			loadMoreCasesViaAjax(button, startPage, procedureIds, procedureName, hasNudity, loadedIds, originalText);
		});
};

/**
 * Load more cases directly from API (optimized method)
 */
async function loadMoreCasesDirectly(startPage, procedureIds, procedureName, hasNudity, loadedIds) {
	try {
		// Get API configuration
		const apiToken = window.bragBookGalleryConfig?.apiToken || '';
		const websitePropertyId = window.bragBookGalleryConfig?.websitePropertyId || '';
		const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
		const nonce = window.bragBookGalleryConfig?.nonce || '';

		if (!apiToken || !websitePropertyId || !ajaxUrl || !nonce) {
			return { success: false, error: 'Missing API configuration' };
		}

		// Build API request body similar to PHP implementation
		const requestBody = {
			apiTokens: [apiToken],
			websitePropertyIds: [parseInt(websitePropertyId)],
			count: parseInt(startPage)
		};

		// Add procedure IDs if provided
		if (procedureIds) {
			const procedureIdsArray = procedureIds.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
			if (procedureIdsArray.length > 0) {
				requestBody.procedureIds = procedureIdsArray;
			}
		}

		// Use WordPress AJAX proxy to avoid CORS issues
		const formData = new FormData();
		formData.append('action', 'brag_book_api_proxy');
		formData.append('nonce', nonce);
		formData.append('endpoint', '/api/plugin/combine/cases');
		formData.append('method', 'POST');
		formData.append('body', JSON.stringify(requestBody));
		formData.append('timeout', '8');

		const controller = new AbortController();
		const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

		const response = await fetch(ajaxUrl, {
			method: 'POST',
			body: formData,
			signal: controller.signal
		});

		clearTimeout(timeoutId);

		if (!response.ok) {
			throw new Error(`HTTP error! status: ${response.status}`);
		}

		const result = await response.json();

		if (!result.success || !result.data || !result.data.data) {
			throw new Error(result.data?.message || 'API proxy request failed');
		}

		const pageData = result.data.data;

		if (!pageData || !pageData.data || !Array.isArray(pageData.data)) {
			return { success: false, error: 'Invalid API response' };
		}

		// Filter out already loaded cases to prevent duplicates
		const newCases = [];
		pageData.data.forEach(caseData => {
			const caseId = caseData.id ? String(caseData.id) : '';
			if (caseId && !loadedIds.includes(caseId)) {
				newCases.push(caseData);
			}
		});

		// Check if there are more pages
		let hasMore = false;
		const casesPerPage = 10; // Default from PHP implementation
		if (pageData.data.length >= casesPerPage) {
			// Check next page to see if it has data using proxy
			const nextRequestBody = { ...requestBody, count: parseInt(startPage) + 1 };
			try {
				const nextFormData = new FormData();
				nextFormData.append('action', 'brag_book_api_proxy');
				nextFormData.append('nonce', nonce);
				nextFormData.append('endpoint', '/api/plugin/combine/cases');
				nextFormData.append('method', 'POST');
				nextFormData.append('body', JSON.stringify(nextRequestBody));
				nextFormData.append('timeout', '5');

				const nextResponse = await fetch(ajaxUrl, {
					method: 'POST',
					body: nextFormData,
					signal: AbortSignal.timeout(5000) // Shorter timeout for next page check
				});
				
				if (nextResponse.ok) {
					const nextResult = await nextResponse.json();
					if (nextResult.success && nextResult.data && nextResult.data.data && 
						nextResult.data.data.data && Array.isArray(nextResult.data.data.data) && 
						nextResult.data.data.data.length > 0) {
						hasMore = true;
					}
				}
			} catch (nextError) {
				// If checking next page fails, assume there might be more
				hasMore = pageData.data.length >= casesPerPage;
			}
		}

		// Generate HTML for the new cases
		const html = generateLoadMoreCasesHTML(newCases, procedureName, hasNudity);

		return {
			success: true,
			html: html,
			casesLoaded: newCases.length,
			hasMore: hasMore,
			nextPage: hasMore ? (parseInt(startPage) + 1) : null
		};

	} catch (error) {
		console.error('Direct API error for load more:', error);
		return { success: false, error: error.message };
	}
}

/**
 * Fallback method using original AJAX approach for load more
 */
function loadMoreCasesViaAjax(button, startPage, procedureIds, procedureName, hasNudity, loadedIds, originalText) {
	// Get AJAX configuration
	const nonce = window.bragBookGalleryConfig?.nonce || '';
	const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';

	// Prepare AJAX data
	const formData = new FormData();
	formData.append('action', 'brag_book_gallery_load_more_cases');
	formData.append('nonce', nonce);
	formData.append('start_page', startPage);
	formData.append('procedure_ids', procedureIds);
	formData.append('procedure_name', procedureName);
	formData.append('has_nudity', hasNudity ? '1' : '0');
	formData.append('loaded_ids', loadedIds.join(','));

	// Make AJAX request
	fetch(ajaxUrl, {
		method: 'POST',
		body: formData
	})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				processLoadMoreResult(data, button, originalText, startPage);
			} else {
				console.error('Failed to load more cases:', data.data ? data.data.message : 'Unknown error');
				button.disabled = false;
				button.textContent = originalText;
				alert('Failed to load more cases. Please try again.');
			}
		})
		.catch(error => {
			console.error('AJAX fallback error loading more cases:', error);
			button.disabled = false;
			button.textContent = originalText;
			alert('Error loading more cases. Please check your connection and try again.');
		});
}

/**
 * Scroll to gallery wrapper for better user experience
 * Accounts for websites with hero sections that may hide the gallery
 */
function scrollToGalleryWrapper() {
	const wrapper = document.querySelector('.brag-book-gallery-wrapper');
	if (wrapper) {
		// Use smooth scrolling with some offset for better UX
		const offsetTop = wrapper.getBoundingClientRect().top + window.pageYOffset - 20;
		window.scrollTo({
			top: offsetTop,
			behavior: 'smooth'
		});
	}
}

/**
 * Process the result from either direct API or AJAX for load more cases
 */
function processLoadMoreResult(result, button, originalText, startPage) {
	const data = result.data || result; // Handle both AJAX response format and direct API format

	// Find the cases grid container (try multiple possible selectors)
	let container = document.querySelector('.brag-book-gallery-case-grid.masonry-layout'); // PHP standard (new)
	if (!container) {
		container = document.querySelector('.brag-book-gallery-cases-grid'); // Previous JS version
	}
	if (!container) {
		container = document.querySelector('.brag-book-gallery-case-grid'); // Legacy JS
	}
	if (!container) {
		container = document.querySelector('.brag-book-gallery-cases-container'); // Outer container
	}
	if (!container) {
		// Try nested structures
		container = document.querySelector('.brag-book-gallery-cases-container .brag-book-gallery-cases-grid');
	}
	if (!container) {
		container = document.querySelector('.brag-book-gallery-grid'); // Fallback
	}

	if (container) {
		if (data.html) {
			// Find the last case card in the grid
			const lastCard = container.querySelector('.brag-book-gallery-case-card:last-child');
			if (lastCard) {
				// Insert after the last card
				lastCard.insertAdjacentHTML('afterend', data.html);
			} else {
				// If no cards exist, add to the container
				container.insertAdjacentHTML('beforeend', data.html);
			}
			
			// Scroll to gallery wrapper after loading items
			scrollToGalleryWrapper();
		} else {
			console.error('No HTML received from server');
		}
	} else {
		console.error('Container not found - tried .brag-book-gallery-case-grid.masonry-layout, .brag-book-gallery-cases-grid, .brag-book-gallery-case-grid, .brag-book-gallery-cases-container, and .brag-book-gallery-grid');
	}

	// Update button for next load
	const newCasesLoaded = data.casesLoaded || 0;

	if (data.hasMore && newCasesLoaded > 0) {
		// Increment page by 1 since we load 1 page at a time
		button.setAttribute('data-start-page', parseInt(startPage) + 1);
		button.disabled = false;
		button.textContent = originalText;
	} else {
		const loadMoreContainer = button.closest('.brag-book-gallery-load-more-container') || button.parentElement;
		if (loadMoreContainer) {
			loadMoreContainer.style.display = 'none';
		}
	}

	// Update the count display - only if we found the container
	if (container) {
		// Try multiple possible selectors for the count label
		const countLabel = document.querySelector('.brag-book-gallery-favorite-count-label') ||
		                   document.querySelector('.cases-count') ||
		                   document.querySelector('[class*="count-label"]');
		if (countLabel) {
			// Count only the gallery case cards, not all elements with data-case-id
			const currentShown = container.querySelectorAll('.brag-book-gallery-case-card').length;
			const match = countLabel.textContent.match(/(\d+) of (\d+)/);
			if (match) {
				const total = match[2];
				countLabel.textContent = 'Showing ' + currentShown + ' of ' + total;
			}
		}
	}
	
	// Regenerate filters since new cases were loaded
	if (newCasesLoaded > 0) {
		setTimeout(() => {
			regenerateProcedureFilters();
		}, 100);
	}

	// Note: Nudity warnings are now handled at render time based on data-nudity attribute
}

/**
 * Generate HTML for load more cases (frontend version of PHP implementation)
 */
function generateLoadMoreCasesHTML(cases, procedureName, hasNudity) {
	let html = '';
	
	cases.forEach(caseData => {
		// Transform case data to match expected format
		const transformedCase = { ...caseData };
		
		// Extract main image from photoSets
		transformedCase.mainImageUrl = '';
		if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
			const firstPhotoset = caseData.photoSets[0];
			transformedCase.mainImageUrl = firstPhotoset.postProcessedImageLocation || 
										   firstPhotoset.beforeLocationUrl || 
										   firstPhotoset.afterLocationUrl1 || '';
		}

		// Extract procedure title
		transformedCase.procedureTitle = procedureName || 'Unknown Procedure';
		if (caseData.procedures && Array.isArray(caseData.procedures) && caseData.procedures.length > 0) {
			transformedCase.procedureTitle = caseData.procedures[0].name || transformedCase.procedureTitle;
		}

		// Determine if case has nudity (simplified check)
		const caseHasNudity = hasNudity || false;

		// Generate case HTML
		html += generateLoadMoreCaseHTML(transformedCase, caseHasNudity);
	});
	
	return html;
}

/**
 * Generate HTML for a single load more case (matches PHP render_case_card structure)
 */
function generateLoadMoreCaseHTML(caseData, hasNudity) {
	const caseId = caseData.id || '';
	const procedureTitle = caseData.procedureTitle || 'Unknown Procedure';
	
	// Prepare data attributes for filtering (matching PHP prepare_case_data_attributes)
	let dataAttrs = 'data-card="true"';
	if (caseData.age) {
		dataAttrs += ` data-age="${escapeHtml(caseData.age)}"`;
	}
	if (caseData.gender) {
		dataAttrs += ` data-gender="${escapeHtml(caseData.gender.toLowerCase())}"`;
	}
	if (caseData.ethnicity) {
		dataAttrs += ` data-ethnicity="${escapeHtml(caseData.ethnicity.toLowerCase())}"`;
	}
	
	// Get procedure IDs
	const procedureIds = caseData.procedureIds ? caseData.procedureIds.join(',') : '';
	
	// Build case URL (matching PHP get_case_url structure)
	const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';
	const procedureSlug = extractProcedureSlugFromUrl() || 'case';
	const seoSuffix = (caseData.caseDetails && caseData.caseDetails[0] && caseData.caseDetails[0].seoSuffixUrl) || caseId;
	const caseUrl = `/${gallerySlug}/${procedureSlug}/${seoSuffix}/`;
	
	// Start building HTML (matching PHP render_case_card structure)
	let html = `<article class="brag-book-gallery-case-card" ${dataAttrs} data-case-id="${escapeHtml(caseId)}" data-procedure-ids="${escapeHtml(procedureIds)}">`;
	
	// Add case link (matching PHP structure)
	html += `<a href="${escapeHtml(caseUrl)}" class="case-link" data-case-id="${escapeHtml(caseId)}" data-procedure-ids="${escapeHtml(procedureIds)}">`;
	
	// Add images (matching PHP image display logic)
	const imageDisplayMode = window.bragBookGalleryConfig?.imageDisplayMode || 'single';
	if (caseData.photoSets && Array.isArray(caseData.photoSets) && caseData.photoSets.length > 0) {
		const firstPhoto = caseData.photoSets[0];
		
		if (imageDisplayMode === 'before_after') {
			// Show both before and after images
			html += `<div class="brag-book-gallery-case-images before-after">`;
			if (firstPhoto.beforePhoto) {
				html += `<img src="${escapeHtml(firstPhoto.beforePhoto)}" alt="Before" class="before-image" />`;
			}
			if (firstPhoto.afterPhoto) {
				html += `<img src="${escapeHtml(firstPhoto.afterPhoto)}" alt="After" class="after-image" />`;
			}
			html += `</div>`;
		} else {
			// Show single image (after preferred, fallback to before)
			const imageUrl = firstPhoto.afterPhoto || firstPhoto.beforePhoto || '';
			if (imageUrl) {
				html += `<div class="brag-book-gallery-case-images"><img src="${escapeHtml(imageUrl)}" alt="Case Image" /></div>`;
			}
		}
	}
	
	// Add nudity warning only if the active procedure has data-nudity="true"
	const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
	const shouldAddNudityWarning = activeLink && activeLink.dataset.nudity === 'true';
	
	if (shouldAddNudityWarning) {
		html += `<div class="brag-book-gallery-nudity-warning">`;
		html += `<div class="brag-book-gallery-nudity-warning-content">`;
		html += `<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>`;
		html += `<p class="brag-book-gallery-nudity-warning-caption">This procedure may contain nudity or sensitive content. Click to proceed if you wish to view.</p>`;
		html += `<button class="brag-book-gallery-nudity-warning-button" type="button">Proceed</button>`;
		html += `</div>`;
		html += `</div>`;
	}
	
	// Add case title if available (matching PHP seo_headline)
	const seoHeadline = (caseData.caseDetails && caseData.caseDetails[0] && caseData.caseDetails[0].seoHeadline) || procedureTitle;
	if (seoHeadline) {
		html += `<h3 class="case-title">${escapeHtml(seoHeadline)}</h3>`;
	}
	
	html += `</a>`; // Close case link
	html += `</article>`; // Close article
	
	return html;
}

/**
 * Extract procedure slug from current URL
 */
function extractProcedureSlugFromUrl() {
	const pathSegments = window.location.pathname.split('/').filter(s => s);
	const gallerySlug = window.bragBookGalleryConfig?.gallerySlug || 'gallery';
	
	// Find gallery slug position and get the next segment as procedure slug
	const galleryIndex = pathSegments.indexOf(gallerySlug.replace(/^\/+/, ''));
	if (galleryIndex >= 0 && galleryIndex + 1 < pathSegments.length) {
		const procedureSlug = pathSegments[galleryIndex + 1];
		// Make sure it's not a case ID (numeric)
		if (!/^\d+$/.test(procedureSlug)) {
			return procedureSlug;
		}
	}
	
	return null;
}

/**
 * Clear procedure filter and reload gallery
 */
window.clearProcedureFilter = function() {
	// Reload the gallery to show all cases
	window.location.href = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/') + '/';
};


/**
 * Get or create the filter results element (centralized management)
 * Always starts hidden and only shows when filters are applied
 */
function getFilterResultsElement() {
	let resultsEl = document.querySelector('.brag-book-gallery-filter-results');
	if (!resultsEl) {
		resultsEl = document.createElement('div');
		resultsEl.className = 'brag-book-gallery-filter-results';
		resultsEl.style.display = 'none'; // Start hidden by default
		
		// Insert before the case grid
		const grid = document.querySelector('.brag-book-gallery-case-grid') ||
		             document.querySelector('.brag-book-gallery-cases-grid');
		if (grid && grid.parentNode) {
			grid.parentNode.insertBefore(resultsEl, grid);
		}
	}
	return resultsEl;
}

/**
 * Update filter badges based on active demographic filters only
 */
function updateFilterBadges() {
	// Remove any old/legacy filter badges container
	const oldBadgesContainer = document.querySelector('.brag-book-gallery-filter-badges');
	if (oldBadgesContainer) {
		oldBadgesContainer.remove();
	}
	
	// Use the existing active filters section instead of creating a separate container
	let activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
	if (!activeFiltersSection) {
		// If it doesn't exist, create it and place it correctly
		activeFiltersSection = document.createElement('div');
		activeFiltersSection.className = 'brag-book-gallery-active-filters';
		
		// Insert after filter results message or before case grid
		const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
		const caseGrid = document.querySelector('.brag-book-gallery-case-grid') || 
						 document.querySelector('.brag-book-gallery-cases-grid');
		
		if (resultsEl && resultsEl.parentNode) {
			resultsEl.parentNode.insertBefore(activeFiltersSection, resultsEl.nextSibling);
		} else if (caseGrid && caseGrid.parentNode) {
			caseGrid.parentNode.insertBefore(activeFiltersSection, caseGrid);
		}
	}
	
	// Always clear all existing badges first
	activeFiltersSection.innerHTML = '';
	
	// Get checked demographic filter checkboxes directly from the DOM
	const checkedFilters = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]:checked');
	
	if (checkedFilters.length === 0) {
		// Hide the active filters section when no filters are applied
		activeFiltersSection.style.display = 'none';
		return;
	}
	
	// Show the active filters section when filters are applied
	activeFiltersSection.style.display = 'block';
	
	// Create badges for each checked demographic filter
	checkedFilters.forEach(checkbox => {
		const filterType = checkbox.dataset.filterType;
		const filterValue = checkbox.value;
		const label = checkbox.parentNode.querySelector('label');
		const displayValue = label ? label.textContent.trim() : filterValue;
		
		const badge = document.createElement('div');
		badge.className = 'brag-book-gallery-filter-badge';
		badge.setAttribute('data-filter-key', `${filterType}:${filterValue}`);
		badge.setAttribute('data-filter-type', filterType);
		badge.setAttribute('data-filter-value', filterValue);
		
		// Capitalize filter type for display
		const displayType = filterType.charAt(0).toUpperCase() + filterType.slice(1);
		
		badge.innerHTML = `
			<span class="brag-book-gallery-badge-text">${displayType}: ${escapeHtml(displayValue)}</span>
			<button class="brag-book-gallery-badge-remove" aria-label="Remove ${displayType}: ${escapeHtml(displayValue)} filter" onclick="removeFilterBadge('${filterType}', '${escapeHtml(filterValue)}')">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
					<path d="M13 1L1 13M1 1l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
				</svg>
			</button>
		`;
		
		activeFiltersSection.appendChild(badge);
	});
	
	// Add Clear All button after the active filters section if there are any filters
	if (checkedFilters.length > 0) {
		// Remove any existing Clear All button first
		const existingClearAllButton = document.querySelector('.brag-book-gallery-clear-all-filters');
		if (existingClearAllButton) {
			existingClearAllButton.remove();
		}
		
		const clearAllButton = document.createElement('button');
		clearAllButton.className = 'brag-book-gallery-clear-all-filters';
		clearAllButton.setAttribute('data-action', 'clear-filters');
		clearAllButton.textContent = 'Clear All';
		clearAllButton.onclick = function(event) {
			event.preventDefault();
			console.log('Clear All button clicked!');
			
			// Try multiple approaches to call the function
			try {
				// Method 1: Direct call
				if (typeof window.clearProcedureFilters === 'function') {
					console.log('Method 1: Calling window.clearProcedureFilters...');
					console.log('Function definition:', window.clearProcedureFilters.toString().substring(0, 100));
					window.clearProcedureFilters();
					console.log('Function call completed');
					return;
				}
				
				// Method 2: Try global scope
				if (typeof clearProcedureFilters === 'function') {
					console.log('Method 2: Calling clearProcedureFilters...');
					clearProcedureFilters();
					return;
				}
				
				// Method 3: Manual clear if function not found
				console.log('Method 3: Manual clear - function not found');
				
				// Uncheck all filter checkboxes
				const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input[type="checkbox"]');
				console.log('Manually unchecking', checkboxes.length, 'checkboxes');
				checkboxes.forEach(checkbox => {
					checkbox.checked = false;
				});
				
				// Show all cards
				const cards = document.querySelectorAll('.brag-book-gallery-case-card');
				console.log('Manually showing', cards.length, 'cards');
				cards.forEach(card => {
					card.style.display = '';
				});
				
				// Hide active filters section
				const activeFiltersSection = document.querySelector('.brag-book-gallery-active-filters');
				if (activeFiltersSection) {
					activeFiltersSection.style.display = 'none';
					activeFiltersSection.innerHTML = '';
				}
				
				// Hide filter results message
				const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
				if (resultsEl) {
					resultsEl.style.display = 'none';
				}
				
				// Remove has-active-filters class
				const toggle = document.querySelector('.brag-book-gallery-filter-dropdown__toggle');
				if (toggle) {
					toggle.classList.remove('has-active-filters');
				}
				
				console.log('Manual clear completed');
				
			} catch (error) {
				console.error('Error in Clear All button:', error);
			}
		};
		
		console.log('Created Clear All button');
		
		// Insert the Clear All button after the active filters section
		if (activeFiltersSection.parentNode) {
			activeFiltersSection.parentNode.insertBefore(clearAllButton, activeFiltersSection.nextSibling);
		}
	} else {
		// Remove Clear All button if no filters are active
		const existingClearAllButton = document.querySelector('.brag-book-gallery-clear-all-filters');
		if (existingClearAllButton) {
			existingClearAllButton.remove();
		}
	}
}

/**
 * Remove a specific filter badge and update the filtering
 * @param {string} filterType - The type of filter (age, gender, etc.)
 * @param {string} filterValue - The value of the filter to remove
 */
window.removeFilterBadge = function(filterType, filterValue) {
	// Find and uncheck the corresponding checkbox
	const checkbox = document.querySelector(`#brag-book-gallery-filters input[data-filter-type="${filterType}"][value="${filterValue}"]`);
	if (checkbox) {
		checkbox.checked = false;
	}
	
	// Re-apply filters to update the display
	applyProcedureFilters();
};


/**
 * Escape HTML characters for safe output (helper function)
 */
function escapeHtml(text) {
	if (!text) return '';
	
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
};

/**
 * Calculate and apply image aspect ratio dynamically
 * @param {HTMLImageElement} img - The image element that has loaded
 */
window.bragBookSetImageAspectRatio = function(img) {
	// Get the natural dimensions of the image
	const naturalWidth = img.naturalWidth;
	const naturalHeight = img.naturalHeight;

	// Only proceed if we have valid dimensions
	if (naturalWidth && naturalHeight) {
		// Calculate aspect ratio as width/height
		const aspectRatio = naturalWidth + '/' + naturalHeight;

		// Find the parent container (either .brag-book-gallery-image-container or .brag-book-gallery-thumb-image)
		const container = img.closest('.brag-book-gallery-image-container, .brag-book-gallery-thumb-image');

		if (container) {
			// Apply the aspect ratio using CSS aspect-ratio property
			container.style.aspectRatio = aspectRatio;
			container.style.width = '100%';
			container.style.height = 'auto';
			container.style.minHeight = 'auto';
			container.style.maxHeight = 'none';

			// Ensure the image fills the container properly
			img.style.width = '100%';
			img.style.height = '100%';
			img.style.objectFit = 'contain';

			// Remove the loading indicator
			container.removeAttribute('data-image-loading');
		}
	}
};

/**
 * Initialize infinite scroll functionality for automatic content loading
 * Triggers load more when user scrolls near bottom of page
 */
window.initInfiniteScroll = function() {
	// Check if infinite scroll is enabled
	const infiniteScrollEnabled = window.bragBookGalleryConfig?.infiniteScroll === 'yes';
	if (!infiniteScrollEnabled) {
		return;
	}

	let isLoading = false;
	let scrollTimeout;

	const handleScroll = () => {
		// Clear previous timeout
		clearTimeout(scrollTimeout);

		// Debounce scroll events
		scrollTimeout = setTimeout(() => {
			// Don't trigger if already loading
			if (isLoading) return;

			// Find the Load More button - look for button with onclick="loadMoreCases(this)"
			const loadMoreButton = document.querySelector('button[onclick*="loadMoreCases"]') ||
								   document.querySelector('.brag-book-gallery-load-more button');
			if (!loadMoreButton || loadMoreButton.disabled || loadMoreButton.style.display === 'none') {
				return;
			}

			// Check if button's container is hidden
			const buttonContainer = loadMoreButton.closest('.brag-book-gallery-load-more-container');
			if (buttonContainer && buttonContainer.style.display === 'none') {
				return;
			}

			// Calculate scroll position
			const scrollPosition = window.innerHeight + window.scrollY;
			const documentHeight = document.documentElement.offsetHeight;
			const triggerPoint = documentHeight - 800; // Trigger 800px before bottom

			// Check if we've scrolled far enough
			if (scrollPosition >= triggerPoint) {
				isLoading = true;

				// Trigger the load more function
				loadMoreButton.click();

				// Reset loading flag after a delay
				setTimeout(() => {
					isLoading = false;
				}, 1000);
			}
		}, 100); // 100ms debounce
	};

	// Add scroll event listener
	window.addEventListener('scroll', handleScroll, { passive: true });

	// Also check on resize
	window.addEventListener('resize', handleScroll, { passive: true });

	// Store reference for cleanup if needed
	window.infiniteScrollHandler = handleScroll;
};

/**
 * Immediately hide filter results on page load (before DOMContentLoaded)
 * This runs as soon as the script loads to prevent flash of content
 */
(function() {
	// Hide filter results immediately if they exist
	const hideFilterResults = () => {
		const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
		if (resultsEl) {
			resultsEl.style.display = 'none !important';
		}
	};
	
	// Run immediately
	hideFilterResults();
	
	// Also run when DOM is ready (in case element loads later)
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', hideFilterResults);
	} else {
		hideFilterResults();
	}
	
	// Watch for the element being added to the DOM
	const observer = new MutationObserver((mutations) => {
		mutations.forEach((mutation) => {
			if (mutation.type === 'childList') {
				mutation.addedNodes.forEach((node) => {
					if (node.nodeType === 1 && node.classList && node.classList.contains('brag-book-gallery-filter-results')) {
						node.style.display = 'none !important';
					}
				});
			}
		});
	});
	
	// Start observing
	observer.observe(document.body || document.documentElement, {
		childList: true,
		subtree: true
	});
})();

/**
 * Global instances for utility managers
 */
let nudityManager; // Nudity warning manager instance
let phoneFormatter; // Phone number formatter instance
let allCasesData = []; // Cache for all loaded cases data
let currentDisplayedCases = 0; // Track how many cases are currently displayed

/**
 * Initialize case pagination system - load all data via XHR and manage display
 */
function initializeCasePagination() {
	console.log('initializeCasePagination called');
	
	// Check if we're on a cases page that should use pagination
	const caseGrid = document.querySelector('.brag-book-gallery-case-grid, .brag-book-gallery-cases-grid');
	console.log('caseGrid found:', !!caseGrid);
	
	if (!caseGrid) return;

	// Check if there are already cases rendered server-side
	const existingCases = caseGrid.querySelectorAll('.brag-book-gallery-case-card');
	currentDisplayedCases = existingCases.length;
	console.log('currentDisplayedCases:', currentDisplayedCases);

	// Load all cases via AJAX for the current procedure
	loadAllCasesForPagination();
}

/**
 * Load all cases for the current procedure via AJAX
 */
async function loadAllCasesForPagination() {
	const activeLink = document.querySelector('.brag-book-gallery-nav-link.brag-book-gallery-active');
	if (!activeLink) return;

	const procedureIds = activeLink.dataset.procedureIds;
	const procedureName = activeLink.textContent.trim().replace(/\(\d+\)$/, '').trim();
	
	if (!procedureIds) return;

	try {
		// Get AJAX configuration
		const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
		const nonce = window.bragBookGalleryConfig?.nonce || '';

		// Prepare request data to load ALL cases for this procedure
		const formData = new FormData();
		formData.append('action', 'brag_book_gallery_load_more_cases');
		formData.append('nonce', nonce);
		formData.append('procedure_ids', procedureIds);
		formData.append('procedure_name', procedureName);
		formData.append('load_all', '1'); // Signal to load all cases
		formData.append('has_nudity', activeLink.dataset.nudity === 'true' ? '1' : '0');

		const response = await fetch(ajaxUrl, {
			method: 'POST',
			body: formData
		});

		const result = await response.json();

		if (result.success && result.data) {
			// Store all cases data
			allCasesData = result.data;
			console.log('Loaded', allCasesData.length, 'cases for pagination');
			console.log('allCasesData sample:', allCasesData[0]);

			// Update load more button visibility
			updateLoadMoreButton();
		} else {
			console.error('Failed to load cases data:', result);
		}
	} catch (error) {
		console.error('Failed to load cases for pagination:', error);
	}
}

/**
 * Simplified load more function that uses server-side pagination
 */
window.loadMoreCasesFromCache = function(button) {
	console.log('loadMoreCasesFromCache called');
	
	// Disable button and show loading state
	button.disabled = true;
	const originalText = button.textContent;
	button.textContent = 'Loading...';

	// Get data from button attributes
	const startPage = button.getAttribute('data-start-page') || '2';
	const procedureIds = button.getAttribute('data-procedure-ids') || '';
	const procedureName = button.getAttribute('data-procedure-name') || '';

	console.log('Loading page:', startPage, 'for procedure:', procedureName);

	// Get AJAX configuration
	const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
	const nonce = window.bragBookGalleryConfig?.nonce || '';

	// Prepare request data for server-side pagination
	const formData = new FormData();
	formData.append('action', 'brag_book_gallery_load_more_cases');
	formData.append('nonce', nonce);
	formData.append('start_page', startPage);
	formData.append('procedure_ids', procedureIds);
	formData.append('procedure_name', procedureName);
	formData.append('loaded_ids', ''); // Will be populated by server

	// Make AJAX request
	fetch(ajaxUrl, {
		method: 'POST',
		body: formData
	})
	.then(response => response.json())
	.then(result => {
		console.log('Load more result:', result);
		
		if (result.success && result.data && result.data.html) {
			// Find the cases grid container
			let container = document.querySelector('.brag-book-gallery-case-grid');
			if (!container) {
				container = document.querySelector('.brag-book-gallery-cases-grid');
			}

			if (container) {
				// Find the last case card and insert after it
				const lastCard = container.querySelector('.brag-book-gallery-case-card:last-child');
				if (lastCard) {
					lastCard.insertAdjacentHTML('afterend', result.data.html);
				} else {
					container.insertAdjacentHTML('beforeend', result.data.html);
				}

				// Scroll to gallery wrapper after loading items
				scrollToGalleryWrapper();

				// Update button for next page
				const nextPage = parseInt(startPage) + 1;
				button.setAttribute('data-start-page', nextPage.toString());
				
				// Check if there are more pages
				if (result.data.hasMore === false) {
					button.style.display = 'none';
				} else {
					button.disabled = false;
					button.textContent = originalText;
				}
			}
		} else {
			console.error('Load more failed:', result);
			button.disabled = false;
			button.textContent = originalText;
		}
	})
	.catch(error => {
		console.error('Load more error:', error);
		button.disabled = false;
		button.textContent = originalText;
	});
};

/**
 * Update load more button visibility and state
 */
function updateLoadMoreButton() {
	const loadMoreBtn = document.querySelector('.brag-book-gallery-button--load-more');
	if (!loadMoreBtn) return;

	if (allCasesData.length > currentDisplayedCases) {
		loadMoreBtn.style.display = '';
		// Update the onclick to use our new cached function
		loadMoreBtn.setAttribute('onclick', 'loadMoreCasesFromCache(this)');
	} else {
		loadMoreBtn.style.display = 'none';
	}
}

document.addEventListener('DOMContentLoaded', () => {
	// Clean up any server-generated procedure badges first
	cleanupProcedureBadges();
	
	// Always hide filter results message on page load - should only show when filters are applied
	const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
	if (resultsEl) {
		resultsEl.style.display = 'none';
	}
	
	// Remove old/legacy filter badges container
	const oldBadgesContainer = document.querySelector('.brag-book-gallery-filter-badges');
	if (oldBadgesContainer) {
		oldBadgesContainer.remove();
	}
	
	new BRAGbookGalleryApp();
	nudityManager = new NudityWarningManager();
	phoneFormatter = new PhoneFormatter();
	
	// Initialize case pagination system (simplified approach)
	console.log('Simplified pagination system initialized');

	// Mark grid as initialized after initial load animations
	const grid = document.querySelector('.brag-book-gallery-case-grid');
	if (grid) {
		setTimeout(() => {
			grid.classList.add('grid-initialized');
		}, 1000); // Wait for initial animations to complete

		// Apply saved grid preference if available and on desktop
		const isDesktop = window.innerWidth >= 1024;
		if (isDesktop) {
			const savedColumns = localStorage.getItem('bragbook-grid-columns');
			if (savedColumns) {
				const columns = parseInt(savedColumns);
				grid.setAttribute('data-columns', columns);

				// Update button states
				const buttons = document.querySelectorAll('.brag-book-gallery-grid-btn');
				buttons.forEach(btn => {
					const btnCols = parseInt(btn.dataset.columns);
					if (btnCols === columns) {
						btn.classList.add('active');
					} else {
						btn.classList.remove('active');
					}
				});
			} else {
				// Default to 3 columns on desktop
				grid.setAttribute('data-columns', '3');
			}
		}
	}

	// Initialize infinite scroll if enabled
	initInfiniteScroll();
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
	// Initialize filters after a brief delay to ensure DOM is ready
	setTimeout(function() {
		// Ensure the complete dataset is available
		if (!window.bragBookCompleteDataset && window.bragBookGalleryConfig && window.bragBookGalleryConfig.completeDataset) {
			window.bragBookCompleteDataset = window.bragBookGalleryConfig.completeDataset;
		}
		initializeProcedureFilters();

		// Case navigation is now handled with anchor links, no JavaScript needed

		// Check if we need to load a case on initial page load
		const wrapper = document.querySelector('.brag-book-gallery-wrapper');
		if (wrapper && wrapper.dataset.initialCaseId) {
			const caseId = wrapper.dataset.initialCaseId;
			// Extract procedure slug from URL - it's the segment before the case ID
			// URL format: /gallery/procedure-slug/case-id
			const pathSegments = window.location.pathname.split('/').filter(s => s);
			// Find the segment before the last one (case ID)
			const procedureSlug = pathSegments.length > 2 ? pathSegments[pathSegments.length - 2] : '';

			// Try to get the procedure name from the sidebar data
			let procedureName = '';

			// First try to get from active sidebar link (if it exists and is already marked active)
			const activeLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedureSlug}"]`);
			if (activeLink) {
				const label = activeLink.querySelector('.brag-book-gallery-filter-option-label');
				if (label) {
					procedureName = label.textContent.trim();
				}
			}

			// If not found in DOM, lookup in sidebar data
			if (!procedureName && window.bragBookGalleryConfig && window.bragBookGalleryConfig.sidebarData) {
				const sidebarData = window.bragBookGalleryConfig.sidebarData;
				// Search through categories for the procedure
				for (const category of Object.values(sidebarData)) {
					if (category.procedures) {
						for (const procedure of category.procedures) {
							if (procedure.slug === procedureSlug) {
								procedureName = procedure.name;
								break;
							}
						}
					}
					if (procedureName) break;
				}
			}

			// Try to find the case card to get procedure IDs
			let procedureIds = '';
			setTimeout(() => {
				const caseCard = document.querySelector(`.brag-book-gallery-case-card[data-case-id="${caseId}"]`);
				if (caseCard && caseCard.dataset.procedureIds) {
					procedureIds = caseCard.dataset.procedureIds;
				}

				window.loadCaseDetailsWithName(caseId, '', procedureSlug, procedureName, procedureIds);
			}, 200);
		}
	}, 100);
});

// Handle details toggle event
document.addEventListener('toggle', function(e) {
	if (e.target.id === 'procedure-filters-details') {
		const details = e.target;
		if (details.open && !details.dataset.initialized) {
			generateProcedureFilterOptions();
			details.dataset.initialized = 'true';
		}
	}
});

// Close details when clicking outside
document.addEventListener('click', function(e) {
	const details = document.getElementById('procedure-filters-details');
	const panel = document.querySelector('.brag-book-gallery-filter-dropdown__panel');

	if (details && details.open && panel) {
		if (!details.contains(e.target) && !panel.contains(e.target)) {
			details.open = false;
		}
	}
});
