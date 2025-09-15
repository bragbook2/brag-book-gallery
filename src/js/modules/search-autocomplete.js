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
		// Collect all procedures from the filter system
		const filterLinks = document.querySelectorAll('.brag-book-gallery-nav-link');
		const procedureMap = new Map();

		filterLinks.forEach(link => {
			const procedure = link.dataset.procedure;
			const category = link.dataset.category;
			const label = link.querySelector('.brag-book-gallery-filter-option-label')?.textContent || procedure;
			const count = parseInt(link.dataset.procedureCount || '0');

			if (procedure && category) {
				// Create unique key combining category and procedure
				const key = `${category}:${procedure}`;

				if (!procedureMap.has(key)) {
					procedureMap.set(key, {
						id: procedure,
						name: label.trim(),
						category: category,
						count: count,
						searchText: label.toLowerCase(),
						fullName: `${label} (${count})` // Keep full name with count for reference
					});
				}
			}
		});

		this.procedures = Array.from(procedureMap.values());
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
                     data-category="${proc.category}"
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

		// Trigger callback
		this.options.onSelect({ procedure, category, name });

		// Find the corresponding filter link and navigate to its URL
		const filterLink = document.querySelector(`.brag-book-gallery-nav-link[data-procedure="${procedure}"]`);
		if (filterLink && filterLink.href) {
			// Navigate to the taxonomy page
			window.location.href = filterLink.href;
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
}

export default SearchAutocomplete;
