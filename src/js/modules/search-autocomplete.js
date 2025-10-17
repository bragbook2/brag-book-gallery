/**
 * Search Autocomplete Component
 * Provides searchable dropdown for procedures
 */
class SearchAutocomplete {
	constructor(wrapper, options = {}) {
		this.wrapper = wrapper;
		this.input = wrapper.querySelector('.brag-book-gallery-search-input');
		this.dropdown = wrapper.querySelector('.brag-book-gallery-search-dropdown');
		this.searchIcon = wrapper.querySelector('.brag-book-gallery-search-icon');

		this.options = {
			minChars: options.minChars || 1,
			debounceDelay: options.debounceDelay || 200,
			maxResults: options.maxResults || 10,
			onSelect: options.onSelect || (() => {}),
			...options
		};

		this.procedures = [];
		this.filteredResults = [];
		this.selectedIndex = -1;
		this.isOpen = false;
		this.debounceTimer = null;

		if (this.input && this.dropdown) {
			this.init();
		}
	}

	init() {
		this.collectProcedures();
		this.setupEventListeners();
	}

	collectProcedures() {
		// Collect all procedures from rendered sidebar DOM or tiles view
		const procedureMap = new Map();
		const sidebarNav = document.querySelector('.brag-book-gallery-nav');
		const tilesNav = document.querySelector('.brag-book-gallery-tiles-view');

		// Check if we have either sidebar nav or tiles view
		if (!sidebarNav && !tilesNav) {
			console.warn('BRAGBook Gallery: No navigation found for search autocomplete');
			this.procedures = [];
			return;
		}

		// Extract procedures from sidebar navigation
		if (sidebarNav) {
			const procedureLinks = sidebarNav.querySelectorAll('.brag-book-gallery-nav-link[data-procedure-slug]');

			procedureLinks.forEach(link => {
				const procedureSlug = link.getAttribute('data-procedure-slug');
				const procedureId = link.getAttribute('data-procedure-id');
				const category = link.getAttribute('data-category');
				const nudity = link.getAttribute('data-nudity') === 'true';
				const url = link.href;

				// Extract name and count from link text
				const linkText = link.textContent.trim();
				const countMatch = linkText.match(/\((\d+)\)$/);
				const count = countMatch ? parseInt(countMatch[1], 10) : 0;
				const name = linkText.replace(/\s*\(\d+\)$/, '').trim();

				// Get category name from the parent details element
				const categoryDetails = link.closest('details[data-category]');
				const categoryName = categoryDetails ?
					categoryDetails.querySelector('.brag-book-gallery-nav-button__label span')?.textContent?.trim() || category :
					category;

				if (procedureSlug && name) {
					// Create unique key combining category and procedure slug
					const key = `${category}:${procedureSlug}`;

					if (!procedureMap.has(key)) {
						procedureMap.set(key, {
							id: procedureSlug,
							procedureId: procedureId,
							name: name,
							category: categoryName,
							categorySlug: category,
							count: count,
							searchText: name.toLowerCase(),
							url: url,
							nudity: nudity,
							fullName: `${name} (${count})`
						});
					}
				}
			});
		}

		// Extract procedures from tiles view navigation
		if (tilesNav) {
			const procedureLinks = tilesNav.querySelectorAll('.brag-book-gallery-procedure-link');

			procedureLinks.forEach(link => {
				const url = link.href;
				// Extract procedure slug from URL
				const urlParts = url.split('/');
				const procedureSlug = urlParts[urlParts.length - 2] || '';

				// Extract name and count from link text
				const nameSpan = link.querySelector('.brag-book-gallery-procedure-name');
				const countSpan = link.querySelector('.brag-book-gallery-procedure-count');

				if (!nameSpan) return;

				const name = nameSpan.textContent.trim();
				const countText = countSpan ? countSpan.textContent.trim() : '';
				const countMatch = countText.match(/\((\d+)\)/);
				const count = countMatch ? parseInt(countMatch[1], 10) : 0;

				// Get category from the parent panel
				const panel = link.closest('.brag-book-gallery-subcategory-panel');
				const categoryId = panel ? panel.getAttribute('data-category-id') : '';

				// Find the category button to get the category name
				const categoryButton = categoryId ?
					document.querySelector(`.brag-book-gallery-category-link[data-category-id="${categoryId}"]`) : null;
				const categoryName = categoryButton ?
					categoryButton.querySelector('.brag-book-gallery-category-name')?.textContent?.replace(/\s*\(\d+\)$/, '').trim() || '' : '';

				if (procedureSlug && name) {
					// Create unique key
					const key = `${categoryId}:${procedureSlug}`;

					if (!procedureMap.has(key)) {
						procedureMap.set(key, {
							id: procedureSlug,
							procedureId: '',
							name: name,
							category: categoryName,
							categorySlug: categoryId,
							count: count,
							searchText: name.toLowerCase(),
							url: url,
							nudity: false,
							fullName: `${name} (${count})`
						});
					}
				}
			});
		}

		this.procedures = Array.from(procedureMap.values());

		// Debug: Log first few procedures to verify data structure
		if (this.procedures.length > 0) {
			console.log('BRAGBook Gallery: First 3 procedures:', this.procedures.slice(0, 3));
		} else {
			console.warn('BRAGBook Gallery: No procedures loaded from sidebar DOM');
		}
	}

	setupEventListeners() {
		// Input events
		this.input.addEventListener('input', (e) => this.handleInput(e));
		this.input.addEventListener('focus', () => this.handleFocus());
		this.input.addEventListener('blur', (e) => this.handleBlur(e));

		// Keyboard navigation
		this.input.addEventListener('keydown', (e) => this.handleKeydown(e));

		// Click outside to close
		document.addEventListener('click', (e) => {
			if (!this.wrapper.contains(e.target)) {
				this.close();
			}
		});

		// Dropdown item clicks
		this.dropdown.addEventListener('click', (e) => {
			const item = e.target.closest('.brag-book-gallery-search-item');
			if (item) {
				this.selectItem(item);
			}
		});
	}

	handleInput(e) {
		const query = e.target.value.trim();

		// Clear previous timer
		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer);
		}

		// Debounce the search
		this.debounceTimer = setTimeout(() => {
			if (query.length >= this.options.minChars) {
				this.search(query);
			} else {
				this.close();
			}
		}, this.options.debounceDelay);
	}

	handleFocus() {
		const query = this.input.value.trim();
		if (query.length >= this.options.minChars) {
			this.search(query);
		}
	}

	handleBlur(e) {
		// Delay close to allow click events on dropdown items
		setTimeout(() => {
			if (!this.wrapper.contains(document.activeElement)) {
				this.close();
			}
		}, 200);
	}

	handleKeydown(e) {
		if (!this.isOpen) {
			if (e.key === 'ArrowDown' && this.input.value.trim().length >= this.options.minChars) {
				e.preventDefault();
				this.search(this.input.value.trim());
			}
			return;
		}

		switch(e.key) {
			case 'ArrowDown':
				e.preventDefault();
				this.moveSelection(1);
				break;
			case 'ArrowUp':
				e.preventDefault();
				this.moveSelection(-1);
				break;
			case 'Enter':
				e.preventDefault();
				if (this.selectedIndex >= 0) {
					const selectedItem = this.dropdown.querySelector(`[data-index="${this.selectedIndex}"]`);
					if (selectedItem) {
						this.selectItem(selectedItem);
					}
				}
				break;
			case 'Escape':
				this.close();
				this.input.blur();
				break;
		}
	}

	search(query) {
		const normalizedQuery = query.toLowerCase();

		// Filter procedures
		this.filteredResults = this.procedures
		                           .filter(proc => {
			                           return proc.searchText.includes(normalizedQuery) ||
			                                  proc.category.includes(normalizedQuery);
		                           })
		                           .slice(0, this.options.maxResults);

		// Sort by relevance (exact match first, then starts with, then contains)
		this.filteredResults.sort((a, b) => {
			const aExact = a.searchText === normalizedQuery;
			const bExact = b.searchText === normalizedQuery;
			if (aExact && !bExact) return -1;
			if (!aExact && bExact) return 1;

			const aStarts = a.searchText.startsWith(normalizedQuery);
			const bStarts = b.searchText.startsWith(normalizedQuery);
			if (aStarts && !bStarts) return -1;
			if (!aStarts && bStarts) return 1;

			return 0;
		});

		this.renderResults(query);
		this.open();
	}

	renderResults(query) {
		if (this.filteredResults.length === 0) {
			this.dropdown.innerHTML = `
                <div class="brag-book-gallery-search-no-results">
                    No procedures found for "${this.escapeHtml(query)}"
                </div>
            `;
			return;
		}

		const html = this.filteredResults.map((proc, index) => {
			const highlightedName = this.highlightMatch(proc.name, query);
			const caseText = proc.count === 1 ? 'case' : 'cases';

			return `
                <div class="brag-book-gallery-search-item"
                     role="option"
                     data-index="${index}"
                     data-procedure="${proc.id}"
                     data-category="${proc.categorySlug}"
                     aria-selected="${index === this.selectedIndex}">
                    <div class="brag-book-gallery-search-item-content">
                        <span class="brag-book-gallery-search-item-name">${highlightedName}</span>
                        <span class="brag-book-gallery-search-item-count">${proc.count} ${caseText}</span>
                    </div>
                    <span class="brag-book-gallery-search-item-category">${proc.category}</span>
                </div>
            `;
		}).join('');

		this.dropdown.innerHTML = html;
		this.selectedIndex = -1;
	}

	highlightMatch(text, query) {
		const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		const regex = new RegExp(`(${escapedQuery})`, 'gi');
		return text.replace(regex, '<mark>$1</mark>');
	}

	escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	moveSelection(direction) {
		const items = this.dropdown.querySelectorAll('.brag-book-gallery-search-item');
		if (items.length === 0) return;

		// Update selected index
		this.selectedIndex += direction;

		// Wrap around
		if (this.selectedIndex < 0) {
			this.selectedIndex = items.length - 1;
		} else if (this.selectedIndex >= items.length) {
			this.selectedIndex = 0;
		}

		// Update aria-selected and scroll into view
		items.forEach((item, index) => {
			const isSelected = index === this.selectedIndex;
			item.setAttribute('aria-selected', isSelected);

			if (isSelected) {
				item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
			}
		});
	}

	selectItem(item) {
		const procedure = item.dataset.procedure;
		const category = item.dataset.category;
		const nameElement = item.querySelector('.brag-book-gallery-search-item-name');
		const name = nameElement ? nameElement.textContent.replace(/<mark>/g, '').replace(/<\/mark>/g, '') : '';

		// Update input value
		this.input.value = name;

		// Close dropdown
		this.close();

		// Find the corresponding procedure data from our loaded procedures
		const procedureData = this.procedures.find(proc =>
			proc.id === procedure && (proc.categorySlug === category || proc.category === category)
		);

		// Trigger callback with full procedure data
		this.options.onSelect({
			procedure,
			category,
			name,
			url: procedureData?.url || '',
			data: procedureData
		});

		// Navigate using the URL from sidebar data
		if (procedureData && procedureData.url) {
			window.location.href = procedureData.url;
		} else {
			// Fallback: try to find corresponding filter link
			const filterLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`);
			if (filterLink && filterLink.href) {
				window.location.href = filterLink.href;
			} else {
				console.warn(`BRAGBook Gallery: No URL found for procedure ${procedure} in category ${category}`);
			}
		}
	}

	open() {
		if (this.isOpen) return;

		this.isOpen = true;
		this.dropdown.classList.add('active');
		this.wrapper.classList.add('active');
		this.input.setAttribute('aria-expanded', 'true');
	}

	close() {
		if (!this.isOpen) return;

		this.isOpen = false;
		this.dropdown.classList.remove('active');
		this.wrapper.classList.remove('active');
		this.input.setAttribute('aria-expanded', 'false');
		this.selectedIndex = -1;
	}

	/**
	 * Refresh procedures data from sidebar
	 * Call this if sidebar data is updated dynamically
	 */
	refresh() {
		this.collectProcedures();
	}
}

export default SearchAutocomplete;
