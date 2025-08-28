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
 */
window.initializeProcedureFilters = function() {
	const details = document.getElementById('procedure-filters-details');
	console.log('Initializing procedure filters, details element:', details);
	console.log('Complete dataset available:', window.bragBookGalleryConfig?.completeDataset?.length || 0, 'cases');
	if (details && !details.dataset.initialized) {
		// Generate filter options which will handle showing/hiding
		generateProcedureFilterOptions();
		details.dataset.initialized = 'true';
	}
};

/**
 * Generate filter options HTML based on available case data
 * Uses either complete dataset from config or falls back to DOM scanning
 */
window.generateProcedureFilterOptions = function() {
	const container = document.getElementById('brag-book-gallery-filters');
	if (!container) {
		console.warn('Filter container not found');
		return;
	}

	// Initialize the complete dataset from config if not already set
	if (!window.bragBookCompleteDataset && window.bragBookGalleryConfig && window.bragBookGalleryConfig.completeDataset) {
		window.bragBookCompleteDataset = window.bragBookGalleryConfig.completeDataset;
		console.log('Initialized complete dataset with', window.bragBookCompleteDataset.length, 'cases');
	} else if (!window.bragBookCompleteDataset) {
		console.warn('Complete dataset not available from config');
		console.log('Config object:', window.bragBookGalleryConfig);
	}

	// First try to use the complete dataset if available
	let cards;
	if (window.bragBookCompleteDataset && window.bragBookCompleteDataset.length > 0) {
		console.log('Using complete dataset for filters:', window.bragBookCompleteDataset.length, 'cases');
		// Use the complete dataset for filter generation
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

		window.bragBookCompleteDataset.forEach(caseData => {
			// Handle both data structures: mapped (age) and raw API (patientAge)
			const age = caseData.age || caseData.patientAge;
			const gender = caseData.gender || caseData.patientGender;
			const ethnicity = caseData.ethnicity || caseData.patientEthnicity;
			const height = caseData.height || caseData.patientHeight;
			const weight = caseData.weight || caseData.patientWeight;

			// Collect raw values
			if (age) ageValues.push(parseInt(age));
			if (gender) filterData.gender.add(gender);
			if (ethnicity) filterData.ethnicity.add(ethnicity);
			if (height) heightValues.push(parseInt(height));
			if (weight) weightValues.push(parseInt(weight));
		});

		// Only add age ranges that have actual data
		if (ageValues.some(age => age >= 18 && age < 25)) filterData.age.add('18-24');
		if (ageValues.some(age => age >= 25 && age < 35)) filterData.age.add('25-34');
		if (ageValues.some(age => age >= 35 && age < 45)) filterData.age.add('35-44');
		if (ageValues.some(age => age >= 45 && age < 55)) filterData.age.add('45-54');
		if (ageValues.some(age => age >= 55 && age < 65)) filterData.age.add('55-64');
		if (ageValues.some(age => age >= 65)) filterData.age.add('65+');

		// Only add height ranges that have actual data
		if (heightValues.some(h => h < 60)) filterData.height.add('Under 5\'0"');
		if (heightValues.some(h => h >= 60 && h < 64)) filterData.height.add('5\'0" - 5\'3"');
		if (heightValues.some(h => h >= 64 && h < 68)) filterData.height.add('5\'4" - 5\'7"');
		if (heightValues.some(h => h >= 68 && h < 72)) filterData.height.add('5\'8" - 5\'11"');
		if (heightValues.some(h => h >= 72)) filterData.height.add('6\'0" and above');

		// Only add weight ranges that have actual data
		if (weightValues.some(w => w < 120)) filterData.weight.add('Under 120 lbs');
		if (weightValues.some(w => w >= 120 && w < 150)) filterData.weight.add('120-149 lbs');
		if (weightValues.some(w => w >= 150 && w < 180)) filterData.weight.add('150-179 lbs');
		if (weightValues.some(w => w >= 180 && w < 210)) filterData.weight.add('180-209 lbs');
		if (weightValues.some(w => w >= 210)) filterData.weight.add('210+ lbs');

		// Now generate the filter HTML using the complete dataset
		generateFilterHTML(container, filterData);
		return;
	}

	// Fallback to reading from visible cards if no complete dataset
	console.log('Falling back to DOM cards for filters');
	cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	console.log('Found', cards.length, 'cards in DOM');
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
		if (age) ageValues.push(parseInt(age));

		// Gender
		if (card.dataset.gender) {
			filterData.gender.add(card.dataset.gender);
		}

		// Ethnicity
		if (card.dataset.ethnicity) {
			filterData.ethnicity.add(card.dataset.ethnicity);
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
	console.log('generateFilterHTML called with:', filterData);

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

	console.log('Generated HTML length:', html.length);
	console.log('HTML preview:', html.substring(0, 200) + (html.length > 200 ? '...' : ''));

	container.innerHTML = html || '<p>No filters available</p>';

	// Add event listeners to all checkboxes
	const filterCheckboxes = container.querySelectorAll('input[type="checkbox"]');
	filterCheckboxes.forEach(checkbox => {
		checkbox.addEventListener('change', function() {
			console.log('Filter changed:', this.dataset.filterType, '=', this.value, 'checked:', this.checked);
			applyProcedureFilters();
		});
	});

	// Show/hide the details element based on whether filters exist
	const details = document.getElementById('procedure-filters-details');
	if (details) {
		const hasFilters = html && html !== '<p>No filters available</p>';
		console.log('Has filters:', hasFilters);
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
	console.log('Applying procedure filters...');
	const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input:checked');
	console.log('Checked filters:', checkboxes.length);

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
		console.log('Filter:', filterType, '=', value);
		if (window.bragBookProcedureFilters[filterType]) {
			window.bragBookProcedureFilters[filterType].push(value);
		}
	});

	console.log('Active filters:', window.bragBookProcedureFilters);

	// Check if any filters are selected
	const hasActiveFilters = Object.values(window.bragBookProcedureFilters).some(arr => arr.length > 0);

	if (!hasActiveFilters) {
		// No filters selected, show all currently loaded cards
		const cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
		cards.forEach(card => {
			card.style.display = '';
		});

		// Update count
		updateFilteredCount(cards.length, cards.length);

		// Show Load More button if it exists since no filters are active
		const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') || 
						   document.querySelector('.brag-book-gallery-load-more button');
		const loadMoreContainer = loadMoreBtn ? (loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement) : null;
		if (loadMoreContainer && loadMoreBtn.hasAttribute('data-start-page')) {
			loadMoreContainer.style.display = '';
		}

		return;
	}

	// If we have the complete dataset, we need to filter and potentially load missing cards
	if (window.bragBookCompleteDataset && window.bragBookCompleteDataset.length > 0) {
		// Find all matching cases from the complete dataset
		const matchingCaseIds = [];

		window.bragBookCompleteDataset.forEach(caseData => {
			let matches = true;

			// Handle both data structures: mapped (age) and raw API (patientAge)
			const age = caseData.age || caseData.patientAge;
			const gender = caseData.gender || caseData.patientGender;
			const ethnicity = caseData.ethnicity || caseData.patientEthnicity;
			const height = caseData.height || caseData.patientHeight;
			const weight = caseData.weight || caseData.patientWeight;

			// Check age filter
			if (window.bragBookProcedureFilters.age.length > 0) {
				const ageNum = parseInt(age);
				let ageMatch = false;
				window.bragBookProcedureFilters.age.forEach(range => {
					if (range === '18-24' && ageNum >= 18 && ageNum <= 24) ageMatch = true;
					else if (range === '25-34' && ageNum >= 25 && ageNum <= 34) ageMatch = true;
					else if (range === '35-44' && ageNum >= 35 && ageNum <= 44) ageMatch = true;
					else if (range === '45-54' && ageNum >= 45 && ageNum <= 54) ageMatch = true;
					else if (range === '55-64' && ageNum >= 55 && ageNum <= 64) ageMatch = true;
					else if (range === '65+' && ageNum >= 65) ageMatch = true;
				});
				if (!ageMatch) matches = false;
			}

			// Check gender filter
			if (matches && window.bragBookProcedureFilters.gender.length > 0) {
				if (!window.bragBookProcedureFilters.gender.includes(gender)) {
					matches = false;
				}
			}

			// Check ethnicity filter
			if (matches && window.bragBookProcedureFilters.ethnicity.length > 0) {
				if (!window.bragBookProcedureFilters.ethnicity.includes(ethnicity)) {
					matches = false;
				}
			}

			// Check height filter
			if (matches && window.bragBookProcedureFilters.height.length > 0) {
				const heightNum = parseInt(height);
				let heightMatch = false;
				window.bragBookProcedureFilters.height.forEach(range => {
					if (range === 'Under 5\'0"' && heightNum < 60) heightMatch = true;
					else if (range === '5\'0" - 5\'3"' && heightNum >= 60 && heightNum < 64) heightMatch = true;
					else if (range === '5\'4" - 5\'7"' && heightNum >= 64 && heightNum < 68) heightMatch = true;
					else if (range === '5\'8" - 5\'11"' && heightNum >= 68 && heightNum < 72) heightMatch = true;
					else if (range === '6\'0" and above' && heightNum >= 72) heightMatch = true;
				});
				if (!heightMatch) matches = false;
			}

			// Check weight filter
			if (matches && window.bragBookProcedureFilters.weight.length > 0) {
				const weightNum = parseInt(weight);
				let weightMatch = false;
				window.bragBookProcedureFilters.weight.forEach(range => {
					if (range === 'Under 120 lbs' && weightNum < 120) weightMatch = true;
					else if (range === '120-149 lbs' && weightNum >= 120 && weightNum < 150) weightMatch = true;
					else if (range === '150-179 lbs' && weightNum >= 150 && weightNum < 180) weightMatch = true;
					else if (range === '180-209 lbs' && weightNum >= 180 && weightNum < 210) weightMatch = true;
					else if (range === '210+ lbs' && weightNum >= 210) weightMatch = true;
				});
				if (!weightMatch) matches = false;
			}

			if (matches) {
				matchingCaseIds.push(caseData.id);
			}
		});

		// Now we need to load ALL matching cases if they're not already visible
		loadFilteredCases(matchingCaseIds);

	} else {
		// Fallback: just filter visible cards if no complete dataset
		// Try multiple selectors to find the cards
		let cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
		if (cards.length === 0) {
			cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
		}
		if (cards.length === 0) {
			cards = document.querySelectorAll('.brag-book-gallery-case-card, .brag-book-gallery-case-card');
		}

		console.log('Found cards for filtering:', cards.length);

		let visibleCount = 0;
		cards.forEach((card, index) => {
			let show = true;

			// Debug card data
			if (index === 0) {
				console.log('First card data attributes:', {
					age: card.dataset.age,
					gender: card.dataset.gender,
					ethnicity: card.dataset.ethnicity,
					height: card.dataset.height,
					weight: card.dataset.weight,
					card: card.dataset.card
				});
			}

			// Check age filter
			if (window.bragBookProcedureFilters.age.length > 0) {
				const cardAge = parseInt(card.dataset.age);
				let ageMatch = false;
				console.log('Age check for card:', cardAge, 'against filters:', window.bragBookProcedureFilters.age);
				window.bragBookProcedureFilters.age.forEach(range => {
					if (range === '18-24' && cardAge >= 18 && cardAge < 25) ageMatch = true;
					else if (range === '25-34' && cardAge >= 25 && cardAge < 35) ageMatch = true;
					else if (range === '35-44' && cardAge >= 35 && cardAge < 45) ageMatch = true;
					else if (range === '45-54' && cardAge >= 45 && cardAge < 55) ageMatch = true;
					else if (range === '55-64' && cardAge >= 55 && cardAge < 65) ageMatch = true;
					else if (range === '65+' && cardAge >= 65) ageMatch = true;
				});
				if (!ageMatch) {
					show = false;
					console.log('Age filter failed for card with age:', cardAge);
				}
			}

			// Check gender filter
			if (show && window.bragBookProcedureFilters.gender.length > 0) {
				const cardGender = (card.dataset.gender || '').toLowerCase();
				const filterGenders = window.bragBookProcedureFilters.gender.map(g => g.toLowerCase());

				console.log('Gender check:', {
					cardGender: cardGender,
					filterGenders: filterGenders,
					matches: filterGenders.includes(cardGender)
				});

				if (!filterGenders.includes(cardGender)) {
					show = false;
				}
			}

			// Check ethnicity filter
			if (show && window.bragBookProcedureFilters.ethnicity.length > 0) {
				const cardEthnicity = (card.dataset.ethnicity || '').toLowerCase();
				const filterEthnicities = window.bragBookProcedureFilters.ethnicity.map(e => e.toLowerCase());

				console.log('Ethnicity check:', {
					cardEthnicity: cardEthnicity,
					filterEthnicities: filterEthnicities,
					matches: filterEthnicities.includes(cardEthnicity)
				});

				if (!filterEthnicities.includes(cardEthnicity)) {
					show = false;
				}
			}

			// Check height filter
			if (show && window.bragBookProcedureFilters.height.length > 0) {
				const cardHeight = parseInt(card.dataset.height);
				const unit = card.dataset.heightUnit || 'cm';
				let heightMatch = false;

				window.bragBookProcedureFilters.height.forEach(range => {
					if (unit === 'cm') {
						if (range === 'Under 160cm' && cardHeight < 160) heightMatch = true;
						else if (range === '160-169cm' && cardHeight >= 160 && cardHeight < 170) heightMatch = true;
						else if (range === '170-179cm' && cardHeight >= 170 && cardHeight < 180) heightMatch = true;
						else if (range === '180cm+' && cardHeight >= 180) heightMatch = true;
					} else {
						if (range === card.dataset.heightFull) heightMatch = true;
					}
				});
				if (!heightMatch) show = false;
			}

			// Check weight filter
			if (show && window.bragBookProcedureFilters.weight.length > 0) {
				const cardWeight = parseInt(card.dataset.weight);
				const unit = card.dataset.weightUnit || 'lbs';
				let weightMatch = false;

				window.bragBookProcedureFilters.weight.forEach(range => {
					if (unit === 'lbs' || unit === 'lb') {
						if (range === 'Under 120 lbs' && cardWeight < 120) weightMatch = true;
						else if (range === '120-149 lbs' && cardWeight >= 120 && cardWeight < 150) weightMatch = true;
						else if (range === '150-179 lbs' && cardWeight >= 150 && cardWeight < 180) weightMatch = true;
						else if (range === '180-209 lbs' && cardWeight >= 180 && cardWeight < 210) weightMatch = true;
						else if (range === '210+ lbs' && cardWeight >= 210) weightMatch = true;
					} else if (unit === 'kg') {
						if (range === 'Under 55kg' && cardWeight < 55) weightMatch = true;
						else if (range === '55-69kg' && cardWeight >= 55 && cardWeight < 70) weightMatch = true;
						else if (range === '70-84kg' && cardWeight >= 70 && cardWeight < 85) weightMatch = true;
						else if (range === '85kg+' && cardWeight >= 85) weightMatch = true;
					} else {
						if (range === card.dataset.weightFull) weightMatch = true;
					}
				});
				if (!weightMatch) show = false;
			}

			// Show/hide card
			card.style.display = show ? '' : 'none';
			if (show) visibleCount++;
		});

		// Update results count
		const hasActiveFilters = checkboxes.length > 0;
		const resultsMessage = visibleCount === 0 ? 'No procedures match the selected filters' :
			`Showing ${visibleCount} of ${cards.length} procedures`;

		// Add/update results message if needed
		let resultsEl = document.querySelector('.brag-book-gallery-filter-results');
		if (!resultsEl) {
			resultsEl = document.createElement('div');
			resultsEl.className = 'brag-book-gallery-filter-results';
			const grid = document.querySelector('.brag-book-gallery-case-grid') ||
			             document.querySelector('.brag-book-gallery-case-grid');
			if (grid && grid.parentNode) {
				grid.parentNode.insertBefore(resultsEl, grid);
			}
		}
		resultsEl.textContent = resultsMessage;
		resultsEl.style.display = hasActiveFilters ? 'block' : 'none';

		// Close the details after applying filters
		const details = document.getElementById('procedure-filters-details');
		if (details) {
			details.open = false;
			// Add visual indicator if filters are active
			const toggle = details.querySelector('.brag-book-gallery-filter-dropdown__toggle');
			if (toggle) {
				toggle.classList.toggle('has-active-filters', hasActiveFilters);
			}
		}

		// Hide Load More button when filters are active or no results found
		const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') || 
						   document.querySelector('.brag-book-gallery-load-more button');
		const loadMoreContainer = loadMoreBtn ? (loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement) : null;
		if (loadMoreContainer) {
			if (hasActiveFilters || visibleCount === 0) {
				loadMoreContainer.style.display = 'none';
			} else {
				// Show it back if filters are cleared and there are more results to load
				if (loadMoreBtn.hasAttribute('data-start-page')) {
					loadMoreContainer.style.display = '';
				}
			}
		}
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

	// If we need to load more cases, make an AJAX request
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

			// Make AJAX request to load the specific cases
			const formData = new FormData();
			formData.append('action', 'brag_book_load_filtered_cases');
			formData.append('case_ids', needToLoad.join(','));
			formData.append('nonce', typeof bragBookAjax !== 'undefined' ? bragBookAjax.nonce : '');

			fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData
			})
				.then(response => response.json())
				.then(data => {
					// Remove loading message
					const loadingMsg = container.querySelector('.filter-loading-message');
					if (loadingMsg) loadingMsg.remove();

					if (data.success && data.data.html) {
						// Add the new cards to the container
						const tempDiv = document.createElement('div');
						tempDiv.innerHTML = data.data.html;

						// Append each new card
						const newCards = tempDiv.querySelectorAll('.brag-book-gallery-case-card');
						newCards.forEach(card => {
							container.appendChild(card);
							visibleCount++;
						});
					}

					// Update the count
					updateFilteredCount(visibleCount, window.bragBookCompleteDataset.length);
				})
				.catch(error => {
					console.error('Error loading filtered cases:', error);
					const loadingMsg = container.querySelector('.filter-loading-message');
					if (loadingMsg) {
						loadingMsg.textContent = 'Failed to load additional cases. Showing only currently loaded matches.';
						setTimeout(() => loadingMsg.remove(), 3000);
					}
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

	// Also update or add a filter results message
	let resultsEl = document.querySelector('.brag-book-gallery-filter-results');
	if (!resultsEl) {
		resultsEl = document.createElement('div');
		resultsEl.className = 'brag-book-gallery-filter-results';
		const grid = document.querySelector('.brag-book-gallery-case-grid') ||
		             document.querySelector('.brag-book-gallery-case-grid');
		if (grid && grid.parentNode) {
			grid.parentNode.insertBefore(resultsEl, grid);
		}
	}

	if (shown === 0) {
		resultsEl.textContent = 'No procedures match the selected filters';
		resultsEl.style.display = 'block';
	} else if (window.bragBookProcedureFilters && Object.values(window.bragBookProcedureFilters).some(arr => arr.length > 0)) {
		resultsEl.textContent = `Filter applied: Showing ${shown} matching procedures`;
		resultsEl.style.display = 'block';
	} else {
		resultsEl.style.display = 'none';
	}
};

/**
 * Clear all active demographic filters and show all cases
 */
window.clearProcedureFilters = function() {
	console.log('Clearing all procedure filters');

	const checkboxes = document.querySelectorAll('.brag-book-gallery-filter-option input');
	checkboxes.forEach(checkbox => {
		checkbox.checked = false;
	});

	// Show all cards
	const cards = document.querySelectorAll('.brag-book-gallery-case-card[data-card="true"]');
	cards.forEach(card => {
		card.style.display = '';
	});

	// Hide results message
	const resultsEl = document.querySelector('.brag-book-gallery-filter-results');
	if (resultsEl) {
		resultsEl.style.display = 'none';
	}

	// Reset filter state
	window.bragBookProcedureFilters = {
		age: [],
		gender: [],
		ethnicity: [],
		height: [],
		weight: []
	};

	// Update toggle button state
	const toggle = document.querySelector('.brag-book-gallery-filter-dropdown__toggle');
	if (toggle) {
		toggle.classList.remove('has-active-filters');
	}

	// Clear all filter badges from the DOM
	const badgesContainer = document.querySelector('[data-action="filter-badges"]');
	if (badgesContainer) {
		console.log('Clearing filter badges container');
		badgesContainer.innerHTML = '';
	}

	// Also remove any individual badges that might exist elsewhere
	const allBadges = document.querySelectorAll('.brag-book-gallery-filter-badge');
	allBadges.forEach(badge => {
		console.log('Removing badge:', badge);
		badge.remove();
	});

	// Hide the Clear All button since no filters are active
	const clearAllButton = document.querySelector('[data-action="clear-filters"]');
	if (clearAllButton) {
		clearAllButton.style.display = 'none';
	}

	// Trigger any app-level badge updates if the app instance exists
	if (window.bragBookGalleryApp && typeof window.bragBookGalleryApp.updateDemographicBadges === 'function') {
		console.log('Calling app updateDemographicBadges with empty filters');
		window.bragBookGalleryApp.updateDemographicBadges({
			age: [],
			gender: [],
			ethnicity: [],
			height: [],
			weight: []
		});
	}

	// Also call the global update function if it exists
	if (typeof window.updateDemographicFilterBadges === 'function') {
		console.log('Calling global updateDemographicFilterBadges with empty filters');
		window.updateDemographicFilterBadges({
			age: [],
			gender: [],
			ethnicity: [],
			height: [],
			weight: []
		});
	}

	// Show Load More button again if it exists and has more pages
	const loadMoreBtn = document.querySelector('button[onclick*="loadMoreCases"]') || 
					   document.querySelector('.brag-book-gallery-load-more button');
	const loadMoreContainer = loadMoreBtn ? (loadMoreBtn.closest('.brag-book-gallery-load-more-container') || loadMoreBtn.parentElement) : null;
	if (loadMoreContainer && loadMoreBtn && loadMoreBtn.hasAttribute('data-start-page')) {
		loadMoreContainer.style.display = '';
	}
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
			console.log('Got procedure IDs from case card:', procedureIds);
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
		action: 'load_case_details',
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
				galleryContent.innerHTML = '<div class="brag-book-gallery-error">Failed to load case details: ' + (data.data || 'Unknown error') + '</div>';
			}
		})
		.catch(error => {
			console.error('Error loading case details:', error);
			galleryContent.innerHTML = '<div class="brag-book-gallery-error">Error loading case details. Please try again.</div>';
		});
};

/**
 * Load more cases via AJAX
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
	console.log('Currently loaded case IDs:', loadedIds.length, 'cases');

	// Get the nonce from the localized script data
	const nonce = window.bragBookGalleryConfig?.nonce || '';
	const ajaxUrl = window.bragBookGalleryConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';

	// Prepare AJAX data
	const formData = new FormData();
	formData.append('action', 'brag_book_load_more_cases');
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
			console.log('Load More Response:', data);
			if (data.success) {
				// Find the cases grid container
				let container = document.querySelector('.brag-book-gallery-case-grid');
				if (!container) {
					container = document.querySelector('.brag-book-gallery-cases-container');
				}
				if (!container) {
					// In AJAX filtered view, container might be nested
					container = document.querySelector('.brag-book-gallery-case-grid .brag-book-gallery-cases-container');
				}

				if (container) {
					console.log('Adding HTML to container. HTML length:', data.data.html ? data.data.html.length : 0);
					console.log('Debug info:', data.data.debug);
					if (data.data.html) {
						// Find the last case card in the grid
						const lastCard = container.querySelector('.brag-book-gallery-case-card:last-child');
						if (lastCard) {
							// Insert after the last card
							lastCard.insertAdjacentHTML('afterend', data.data.html);
						} else {
							// If no cards exist, add to the container
							container.insertAdjacentHTML('beforeend', data.data.html);
						}
						console.log('Cases in container after insert:', container.querySelectorAll('[data-case-id]').length);
					} else {
						console.error('No HTML received from server');
					}
				} else {
					console.error('Container not found (.brag-book-gallery-case-grid, .brag-book-gallery-cases-container, or .brag-book-gallery-case-grid .brag-book-gallery-cases-container)');
				}

				// Update button for next load
				// Check if we actually loaded any new cases or if there are more to load
				const newCasesLoaded = data.data.casesLoaded || 0;

				if (data.data.hasMore && newCasesLoaded > 0) {
					// Increment page by 1 since we load 1 page at a time
					button.setAttribute('data-start-page', parseInt(startPage) + 1);
					button.disabled = false;
					button.textContent = originalText;
				} else {
					// No more cases or no new cases loaded (all were duplicates), hide the button
					console.log('Hiding load more button - hasMore:', data.data.hasMore, 'newCasesLoaded:', newCasesLoaded);
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
			} else {
				console.error('Failed to load more cases:', data.data ? data.data.message : 'Unknown error');
				button.disabled = false;
				button.textContent = originalText;
				alert('Failed to load more cases. Please try again.');
			}
		})
		.catch(error => {
			console.error('Error loading more cases:', error);
			button.disabled = false;
			button.textContent = originalText;
			alert('Error loading more cases. Please check your connection and try again.');
		});
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
 * Global instances for utility managers
 */
let nudityManager; // Nudity warning manager instance
let phoneFormatter; // Phone number formatter instance

document.addEventListener('DOMContentLoaded', () => {
	new BRAGbookGalleryApp();
	nudityManager = new NudityWarningManager();
	phoneFormatter = new PhoneFormatter();

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
			console.log('Initialized bragBookCompleteDataset with', window.bragBookCompleteDataset.length, 'cases');
		}
		initializeProcedureFilters();

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

				console.log('Auto-loading case on page load:', caseId, 'for procedure:', procedureSlug, 'with name:', procedureName, 'and IDs:', procedureIds);
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
