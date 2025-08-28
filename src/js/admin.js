/**
 * BRAG book Admin JavaScript
 * 
 * Handles all WordPress admin interface functionality including:
 * - Dynamic settings table management
 * - Debug tools and logging
 * - API validation and testing
 * - Factory reset with confirmation dialogs
 * - System information export/copy
 * - HTML5 dialog modals with accessibility
 *
 * @package BRAGBook
 * @since   3.0.0
 */

'use strict';

/**
 * BRAG book Admin Controller Class
 * Manages all admin interface interactions and AJAX communications
 */
if (typeof window.BRAGbookAdmin === 'undefined') {
	window.BRAGbookAdmin = class {
		/**
		 * Initialize the admin interface controller
		 */
		constructor() {
			// Configure AJAX endpoints - check multiple possible localization objects
			this.ajaxUrl = (typeof brag_book_gallery_admin !== 'undefined' && brag_book_gallery_admin.ajaxurl) 
				? brag_book_gallery_admin.ajaxurl 
				: (typeof brag_book_gallery_ajax !== 'undefined' ? brag_book_gallery_ajax.ajaxurl : '/wp-admin/admin-ajax.php');
			
			// Configure security nonces - check multiple possible sources
			this.nonce = (typeof brag_book_gallery_admin !== 'undefined' && brag_book_gallery_admin.nonce) 
				? brag_book_gallery_admin.nonce 
				: (typeof brag_book_gallery_ajax !== 'undefined' ? brag_book_gallery_ajax.nonce : '');
			
			// Dialog instance for user feedback
			this.dialog = null;
			
			// Initialize all admin functionality
			this.init();
		}

		/**
		 * Initialize all admin functionality in sequence
		 */
		init() {
			// Create reusable dialog modal for user feedback
			this.createDialog();

			// Initialize admin components
			this.initDynamicTable();      // Multi-page gallery settings table
			this.initCombineGallery();    // Combined gallery creation UI
			this.initDebugPage();         // Debug tools and logging interface
			// this.initApiValidation(); // DISABLED - now handled by inline JS in settings page
		}

		/**
		 * Create HTML5 dialog element for user feedback messages
		 * Supports success, error, warning, and info message types
		 */
		createDialog() {
			// Avoid creating duplicate dialogs
			if (document.getElementById('brag-book-gallery-dialog')) {
				this.dialog = document.getElementById('brag-book-gallery-dialog');
				return;
			}

			// Create native HTML5 dialog element
			this.dialog = document.createElement('dialog');
			this.dialog.id = 'brag-book-gallery-dialog';
			this.dialog.className = 'brag-book-gallery-dialog';

			// Create accessible dialog structure with header, body, and footer
			this.dialog.innerHTML = `
            <div class="brag-book-gallery-dialog-content">
                <div class="brag-book-gallery-dialog-header">
                    <h3 class="brag-book-gallery-dialog-title"></h3>
                    <button type="button" class="brag-book-gallery-dialog-close" aria-label="Close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="brag-book-gallery-dialog-body">
                    <div class="brag-book-gallery-dialog-icon"></div>
                    <div class="brag-book-gallery-dialog-message"></div>
                </div>
                <div class="brag-book-gallery-dialog-footer">
                    <button type="button" class="button button-primary brag-book-gallery-dialog-ok">OK</button>
                </div>
            </div>
        `;

			// Add dialog to page DOM
			document.body.appendChild(this.dialog);

			// Set up dialog close handlers
			const closeBtn = this.dialog.querySelector('.brag-book-gallery-dialog-close');
			const okBtn = this.dialog.querySelector('.brag-book-gallery-dialog-ok');

			// Close button and OK button handlers
			closeBtn.addEventListener('click', () => this.closeDialog());
			okBtn.addEventListener('click', () => this.closeDialog());

			// Light dismiss - close when clicking backdrop
			this.dialog.addEventListener('click', (e) => {
				if (e.target === this.dialog) {
					this.closeDialog();
				}
			});

			// Close on Escape key press
			this.dialog.addEventListener('cancel', (e) => {
				e.preventDefault();
				this.closeDialog();
			});
		}

		/**
		 * Display a message dialog with appropriate styling and icons
		 * @param {string} message - HTML message content to display
		 * @param {string} type - Message type: 'success', 'error', 'warning', 'info'
		 * @param {string} title - Optional custom title (auto-generated based on type if empty)
		 */
		showDialog(message, type = 'info', title = '') {
			// Ensure dialog exists before showing
			if (!this.dialog) {
				this.createDialog();
			}

			// Get dialog UI elements for customization
			const titleEl = this.dialog.querySelector('.brag-book-gallery-dialog-title');
			const iconEl = this.dialog.querySelector('.brag-book-gallery-dialog-icon');
			const messageEl = this.dialog.querySelector('.brag-book-gallery-dialog-message');
			const contentEl = this.dialog.querySelector('.brag-book-gallery-dialog-content');

			// Set dialog title (auto-generate if not provided)
			if (!title) {
				switch (type) {
					case 'success':
						title = 'Success';
						break;
					case 'error':
						title = 'Error';
						break;
					case 'warning':
						title = 'Warning';
						break;
					default:
						title = 'Information';
				}
			}
			titleEl.textContent = title;

			// Apply type-specific styling and icons
			contentEl.className = `brag-book-gallery-dialog-content brag-book-gallery-dialog-${type}`;
			switch (type) {
				case 'success':
					// Green checkmark for success messages
					iconEl.innerHTML = '<span class="dashicons dashicons-yes-alt"></span>';
					break;
				case 'error':
					// Red X for error messages
					iconEl.innerHTML = '<span class="dashicons dashicons-dismiss"></span>';
					break;
				case 'warning':
					// Yellow warning triangle
					iconEl.innerHTML = '<span class="dashicons dashicons-warning"></span>';
					break;
				default:
					// Blue info icon for general messages
					iconEl.innerHTML = '<span class="dashicons dashicons-info"></span>';
			}

			// Set message content (allows HTML)
			messageEl.innerHTML = message;

			// Display dialog as modal (blocks background interaction)
			this.dialog.showModal();
		}

		/**
		 * Close the currently open dialog
		 */
		closeDialog() {
			if (this.dialog) {
				// Native dialog close method handles focus restoration
				this.dialog.close();
			}
		}

		/**
		 * Initialize dynamic settings table for multi-page gallery configuration
		 * Allows adding/removing rows with API tokens, property IDs, and SEO settings
		 */
		initDynamicTable() {
			const tableBody = document.querySelector("#dynamicTable tbody");
			if (!tableBody) return;

			// Helper function to determine next row number for unique field names
			const getLastRowNumber = () => {
				const rows = [...tableBody.querySelectorAll("tr")];
				return rows.reduce((max, row) => {
					// Find input with page number in data-key attribute
					const input = row.querySelector('input[data-key^="page_"]');
					if (input) {
						// Extract number from 'page_N' format
						const num = parseInt(input.dataset.key.split('_')[1], 10);
						return Math.max(max, num);
					}
					return max;
				}, 0);
			};

			// Create table cell with input field for settings data
			const createInputCell = (name, rowNumber) => {
				const td = document.createElement("td");
				const input = document.createElement("input");
				input.type = "text";
				// Set unique identifiers for WordPress settings array
				input.setAttribute("data-key", `page_${rowNumber}`);
				input.setAttribute("name", `${name}[page_${rowNumber}]`);
				input.required = true;
				td.appendChild(input);
				return td;
			};

			// Create table cell with add/remove buttons for row management
			const createButtonCell = () => {
				const td = document.createElement("td");

				// Remove row button
				const removeBtn = document.createElement("button");
				removeBtn.type = "button";
				removeBtn.className = "button removeRow";
				removeBtn.textContent = "Remove Row";

				// Add row button (only shown on last row)
				const addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "button addRow";
				addBtn.textContent = "Add Row";

				td.appendChild(removeBtn);
				td.appendChild(addBtn);
				return td;
			};

			// Add new settings row to the dynamic table
			const addRow = () => {
				const newRowNumber = getLastRowNumber() + 1;
				const row = document.createElement("tr");

				// Create cells for all required settings fields
				row.appendChild(createInputCell("brag_book_gallery_api_token", newRowNumber));
				row.appendChild(createInputCell("brag_book_gallery_website_property_id", newRowNumber));
				row.appendChild(createInputCell("brag_book_gallery_page_slug", newRowNumber));
				row.appendChild(createInputCell("brag_book_gallery_seo_page_title", newRowNumber));
				row.appendChild(createInputCell("brag_book_gallery_seo_page_description", newRowNumber));
				row.appendChild(createButtonCell());

				// Add to table and update button visibility
				tableBody.appendChild(row);
				updateButtonVisibility();
			};

			// Remove settings row from the dynamic table
			const removeRow = (row) => {
				// Prevent removing the last row (always need at least one)
				if (tableBody.rows.length <= 1) return;

				// Get the row identifier for server-side deletion
				const input = row.querySelector('input[data-key^="page_"]');
				const remove_id = input ? input.dataset.key : '';

				// Notify server to remove from database
				if (remove_id) {
					this.ajaxPost({
						action: 'brag_book_gallery_setting_remove_row',
						remove_id
					});
				}

				// Remove from DOM and update UI
				row.remove();
				updateButtonVisibility();
			};

			// Update add/remove button visibility based on table state
			const updateButtonVisibility = () => {
				const rows = tableBody.querySelectorAll("tr");
				rows.forEach((row, index) => {
					const addBtn = row.querySelector(".addRow");
					const removeBtn = row.querySelector(".removeRow");

					// Only show "Add" button on the last row
					if (addBtn) addBtn.style.display = (index === rows.length - 1) ? "inline-block" : "none";
					// Only show "Remove" button if more than one row exists
					if (removeBtn) removeBtn.style.display = (rows.length > 1) ? "inline-block" : "none";
				});
			};

			// Use event delegation to handle dynamically created buttons
			tableBody.addEventListener("click", (event) => {
				const row = event.target.closest("tr");
				// Handle add row button clicks
				if (event.target.classList.contains("addRow")) {
					addRow();
				}
				// Handle remove row button clicks
				if (event.target.classList.contains("removeRow") && row) {
					removeRow(row);
				}
			});

			updateButtonVisibility();
		}

		/**
		 * Initialize combined gallery creation UI
		 * Manages the show/hide logic for the gallery slug input field
		 */
		initCombineGallery() {
			const createBtn = document.getElementById("createCombineGallery");
			const slugContainer = document.getElementById("slugFieldContainer");
			const slugInput = document.querySelector(".combineGallerySlug");

			// Exit if required elements aren't found
			if (!createBtn || !slugContainer || !slugInput) return;

			// Configure UI based on whether gallery already exists
			if (slugInput.value === "") {
				// No existing gallery - show create button
				createBtn.style.display = "block";
				slugContainer.style.display = "none";

				// Toggle slug input field when create button is clicked
				createBtn.addEventListener("click", () => {
					slugContainer.style.display = (slugContainer.style.display === "none") ? "block" : "none";
				});
			} else {
				// Existing gallery - show input field directly
				createBtn.style.display = "none";
				slugContainer.style.display = "block";
			}
		}

		/**
		 * Initialize all debug page functionality including tabs, logs, and tools
		 */
		initDebugPage() {
			// Initialize debug interface components
			this.initDebugTabs();      // Tab navigation system
			this.initErrorLog();       // Error log viewer/management
			this.initApiLog();         // API request log viewer
			this.initSystemInfo();     // System information export
			this.initDebugSettings();  // Debug mode toggles
			this.initFactoryReset();   // Factory reset with confirmations
		}

		/**
		 * Initialize tab navigation for the debug page
		 * Handles switching between error logs, API logs, system info, etc.
		 */
		initDebugTabs() {
			const tabLinks = document.querySelectorAll('.brag-book-gallery-log-tab-nav a');
			const tabContents = document.querySelectorAll('.brag-book-gallery-log-tab-content');

			// Exit if no tabs found
			if (tabLinks.length === 0) return;

			// Set up click handlers for each tab link
			tabLinks.forEach(link => {
				link.addEventListener('click', (e) => {
					e.preventDefault();

					// Get target tab identifier from data attribute
					const targetTab = link.getAttribute('data-tab');

					// Clear all active states
					tabLinks.forEach(l => l.classList.remove('active'));
					tabContents.forEach(c => c.classList.remove('active'));

					// Activate clicked tab and its content
					link.classList.add('active');
					const targetContent = document.getElementById(targetTab + '-tab');
					if (targetContent) {
						targetContent.classList.add('active');
					}
				});
			});
		}

		/**
		 * Initialize error log management functionality
		 * Handles refresh, clear, and download operations for error logs
		 */
		initErrorLog() {
			const refreshBtn = document.getElementById('brag-book-gallery-refresh-error-log');
			const clearBtn = document.getElementById('brag-book-gallery-clear-error-log');
			const downloadBtn = document.getElementById('brag-book-gallery-download-error-log');
			const errorLogContent = document.getElementById('brag-book-gallery-error-log');

			// Set up refresh button functionality
			if (refreshBtn && errorLogContent) {
				refreshBtn.addEventListener('click', async () => {
					// Show loading state and disable button to prevent multiple clicks
					refreshBtn.disabled = true;
					const originalContent = errorLogContent.textContent;
					errorLogContent.innerHTML = '<span style="color: #666;">Loading...</span>';

					try {
						// Make AJAX request to fetch latest error log
						const response = await this.ajaxPost({
							action: 'brag_book_gallery_get_error_log',
							nonce: this.nonce
						});

						if (response.success) {
							// Update log content and show success message
							errorLogContent.textContent = response.data.log || 'No errors logged yet.';
							this.showDialog('Error log refreshed successfully.', 'success');
						} else {
							// Restore original content on failure
							errorLogContent.textContent = originalContent;
							this.showDialog(
								`Failed to load error log: ${response.data || 'Unknown error'}`,
								'error'
							);
						}
					} catch (error) {
						// Handle network or parsing errors
						errorLogContent.textContent = originalContent;
						this.showDialog(`Error loading log: ${error.message}`, 'error');
					} finally {
						// Always re-enable button when done
						refreshBtn.disabled = false;
					}
				});
			}

			if (clearBtn && errorLogContent) {
				clearBtn.addEventListener('click', async () => {
					// Create confirmation dialog
					const confirmDialog = document.createElement('dialog');
					confirmDialog.className = 'brag-book-gallery-dialog';
					confirmDialog.innerHTML = `
                    <div class="brag-book-gallery-dialog-content brag-book-gallery-dialog-warning">
                        <div class="brag-book-gallery-dialog-header">
                            <h3 class="brag-book-gallery-dialog-title">Confirm Clear Log</h3>
                        </div>
                        <div class="brag-book-gallery-dialog-body">
                            <div class="brag-book-gallery-dialog-icon">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <div class="brag-book-gallery-dialog-message">
                                Are you sure you want to clear the error log? This action cannot be undone.
                            </div>
                        </div>
                        <div class="brag-book-gallery-dialog-footer">
                            <button type="button" class="button button-secondary brag-book-gallery-dialog-cancel">Cancel</button>
                            <button type="button" class="button button-primary brag-book-gallery-dialog-confirm">Clear Log</button>
                        </div>
                    </div>
                `;

					document.body.appendChild(confirmDialog);
					confirmDialog.showModal();

					// Handle confirmation
					const confirmBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-confirm');
					const cancelBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-cancel');

					confirmBtn.addEventListener('click', async () => {
						confirmDialog.close();
						confirmDialog.remove();

						try {
							const response = await this.ajaxPost({
								action: 'brag_book_gallery_clear_error_log',
								nonce: this.nonce
							});

							if (response.success) {
								errorLogContent.textContent = 'No errors logged yet.';
								this.showDialog('Error log cleared successfully.', 'success');
							} else {
								this.showDialog(`Failed to clear log: ${response.data || 'Unknown error'}`, 'error');
							}
						} catch (error) {
							this.showDialog(`Error clearing log: ${error.message}`, 'error');
						}
					});

					cancelBtn.addEventListener('click', () => {
						confirmDialog.close();
						confirmDialog.remove();
					});

					confirmDialog.addEventListener('click', (e) => {
						if (e.target === confirmDialog) {
							confirmDialog.close();
							confirmDialog.remove();
						}
					});
				});
			}

			// Set up download button functionality
			if (downloadBtn && errorLogContent) {
				downloadBtn.addEventListener('click', () => {
					// Create downloadable text file from log content
					const logContent = errorLogContent.textContent;
					const blob = new Blob([logContent], { type: 'text/plain' });
					const url = window.URL.createObjectURL(blob);
					
					// Create temporary download link with date-stamped filename
					const a = document.createElement('a');
					a.href = url;
					a.download = `brag-book-gallery-error-log-${new Date().toISOString().split('T')[0]}.txt`;
					document.body.appendChild(a);
					a.click();
					
					// Clean up temporary elements
					document.body.removeChild(a);
					window.URL.revokeObjectURL(url);
				});
			}
		}

		/**
		 * Initialize API log functionality
		 */
		initApiLog() {
			const refreshBtn = document.getElementById('brag-book-gallery-refresh-api-log');
			const clearBtn = document.getElementById('brag-book-gallery-clear-api-log');
			const downloadBtn = document.getElementById('brag-book-gallery-download-api-log');
			const apiLogContent = document.getElementById('brag-book-gallery-api-log');

			if (refreshBtn && apiLogContent) {
				refreshBtn.addEventListener('click', async () => {
					refreshBtn.disabled = true;
					const originalContent = apiLogContent.textContent;
					apiLogContent.innerHTML = '<span style="color: #666;">Loading...</span>';

					try {
						const response = await this.ajaxPost({
							action: 'brag_book_gallery_get_api_log',
							nonce: this.nonce
						});

						if (response.success) {
							apiLogContent.textContent = response.data.log || 'No API requests logged yet.';
							this.showDialog('API log refreshed successfully.', 'success');
						} else {
							apiLogContent.textContent = originalContent;
							this.showDialog(
								`Failed to load API log: ${response.data || 'Unknown error'}`,
								'error'
							);
						}
					} catch (error) {
						apiLogContent.textContent = originalContent;
						this.showDialog(`Error loading log: ${error.message}`, 'error');
					} finally {
						refreshBtn.disabled = false;
					}
				});
			}

			if (clearBtn && apiLogContent) {
				clearBtn.addEventListener('click', async () => {
					const confirmDialog = document.createElement('dialog');
					confirmDialog.className = 'brag-book-gallery-dialog';
					confirmDialog.innerHTML = `
                    <div class="brag-book-gallery-dialog-content brag-book-gallery-dialog-warning">
                        <div class="brag-book-gallery-dialog-header">
                            <h3 class="brag-book-gallery-dialog-title">Confirm Clear API Log</h3>
                            <button type="button" class="brag-book-gallery-dialog-close" aria-label="Close">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="brag-book-gallery-dialog-body">
                            <div class="brag-book-gallery-dialog-icon">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <div class="brag-book-gallery-dialog-message">
                                Are you sure you want to clear the API log? This action cannot be undone.
                            </div>
                        </div>
                        <div class="brag-book-gallery-dialog-footer">
                            <button type="button" class="button button-secondary brag-book-gallery-dialog-cancel">Cancel</button>
                            <button type="button" class="button button-primary brag-book-gallery-dialog-confirm">Clear Log</button>
                        </div>
                    </div>
                `;

					document.body.appendChild(confirmDialog);
					confirmDialog.showModal();

					const confirmBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-confirm');
					const cancelBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-cancel');
					const closeBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-close');

					const closeDialog = () => {
						confirmDialog.close();
						confirmDialog.remove();
					};

					confirmBtn.addEventListener('click', async () => {
						closeDialog();
						clearBtn.disabled = true;

						try {
							const response = await this.ajaxPost({
								action: 'brag_book_gallery_clear_api_log',
								nonce: this.nonce
							});

							if (response.success) {
								apiLogContent.textContent = 'No API requests logged yet.';
								this.showDialog('API log cleared successfully.', 'success');
							} else {
								this.showDialog(
									`Failed to clear API log: ${response.data || 'Unknown error'}`,
									'error'
								);
							}
						} catch (error) {
							this.showDialog(`Error clearing log: ${error.message}`, 'error');
						} finally {
							clearBtn.disabled = false;
						}
					});

					cancelBtn.addEventListener('click', closeDialog);
					closeBtn.addEventListener('click', closeDialog);
				});
			}

			if (downloadBtn && apiLogContent) {
				downloadBtn.addEventListener('click', () => {
					const logContent = apiLogContent.textContent;
					const blob = new Blob([logContent], { type: 'text/plain' });
					const url = window.URL.createObjectURL(blob);
					const a = document.createElement('a');
					a.href = url;
					a.download = `brag-book-gallery-api-log-${new Date().toISOString().split('T')[0]}.txt`;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					window.URL.revokeObjectURL(url);
				});
			}
		}

		/**
		 * Initialize system info functionality
		 */
		initSystemInfo() {
			const copyBtn = document.getElementById('brag-book-gallery-copy-system-info');
			const exportBtn = document.getElementById('brag-book-gallery-export-system-info');
			const systemInfoContent = document.getElementById('brag-book-gallery-system-info'); // Fixed ID

			if (copyBtn && systemInfoContent) {
				copyBtn.addEventListener('click', async () => {
					const systemInfo = systemInfoContent.textContent;
					try {
						await navigator.clipboard.writeText(systemInfo);
						const feedback = document.getElementById('brag-book-gallery-copy-feedback');
						if (feedback) {
							feedback.classList.add('show');
							setTimeout(() => feedback.classList.remove('show'), 2000);
						}
						this.showDialog('System information copied to clipboard.', 'success');
					} catch (error) {
						// Fallback for older browsers
						const textArea = document.createElement("textarea");
						textArea.value = systemInfo;
						textArea.style.position = "fixed";
						textArea.style.left = "-999999px";
						document.body.appendChild(textArea);
						textArea.focus();
						textArea.select();
						try {
							document.execCommand('copy');
							const feedback = document.getElementById('brag-book-gallery-copy-feedback');
							if (feedback) {
								feedback.classList.add('show');
								setTimeout(() => feedback.classList.remove('show'), 2000);
							}
							this.showDialog('System information copied to clipboard.', 'success');
						} catch (error) {
							this.showDialog('Failed to copy to clipboard. Please try again.', 'error');
						}
						document.body.removeChild(textArea);
					}
				});
			}

			if (exportBtn && systemInfoContent) {
				exportBtn.addEventListener('click', () => {
					const systemInfo = systemInfoContent.textContent;
					const blob = new Blob([systemInfo], { type: 'text/plain' });
					const url = window.URL.createObjectURL(blob);
					const a = document.createElement('a');
					a.href = url;
					a.download = `brag-book-gallery-system-info-${new Date().toISOString().split('T')[0]}.txt`;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					window.URL.revokeObjectURL(url);
				});
			}
		}

		/**
		 * Initialize debug settings
		 */
		initDebugSettings() {
			const saveBtn = document.getElementById('brag-book-gallery-save-debug-settings');
			const debugModeCheckbox = document.getElementById('brag-book-gallery-debug-mode');
			const apiLoggingCheckbox = document.getElementById('brag-book-gallery-api-logging');
			const wpDebugCheckbox = document.getElementById('wp-debug-mode');
			const logLevelSelect = document.getElementById('brag-book-gallery-log-level');

			if (saveBtn) {
				saveBtn.addEventListener('click', async () => {
					const debugMode = debugModeCheckbox ? (debugModeCheckbox.checked ? '1' : '0') : '0';
					const apiLogging = apiLoggingCheckbox ? (apiLoggingCheckbox.checked ? '1' : '0') : '0';
					const wpDebug = wpDebugCheckbox ? (wpDebugCheckbox.checked ? '1' : '0') : '0';
					const logLevel = logLevelSelect ? logLevelSelect.value : 'error';

					try {
						const response = await this.ajaxPost({
							action: 'brag_book_gallery_save_debug_settings',
							nonce: this.nonce,
							debug_mode: debugMode,
							api_logging: apiLogging,
							wp_debug: wpDebug,
							log_level: logLevel
						});

						if (response.success) {
							// Show success message
							this.showDialog(
								response.data.message || 'Settings saved successfully.',
								'success'
							);

							// Update status display
							const debugStatus = document.querySelector('.brag-book-gallery-debug-status');
							if (debugStatus) {
								if (debugMode === '1') {
									debugStatus.classList.add('active');
									debugStatus.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> Active';
								} else {
									debugStatus.classList.remove('active');
									debugStatus.innerHTML = '<span class="dashicons dashicons-no-alt"></span> Inactive';
								}
							}
						} else {
							this.showDialog(
								`Failed to save debug settings: ${response.data || 'Unknown error'}`,
								'error'
							);
						}
					} catch (error) {
						this.showDialog(`Error saving settings: ${error.message}`, 'error');
					}
				});
			}
		}

		/**
		 * Initialize API validation
		 */
		initApiValidation() {
			const validateButtons = document.querySelectorAll('.bb-validate-api');

			validateButtons.forEach(button => {
				button.addEventListener('click', async (e) => {
					const row = e.target.closest('.bb-api-row');
					if (!row) return;

					const tokenInput = row.querySelector('input[name*="api_token"]');
					const propertyInput = row.querySelector('input[name*="website_property_id"]');
					const statusDiv = row.querySelector('.bb-api-status');

					if (!tokenInput || !propertyInput) return;

					const token = tokenInput.value.trim();
					const propertyId = propertyInput.value.trim();

					if (!token || !propertyId) {
						this.showDialog(
							'Please enter both API Token and Website Property ID',
							'warning'
						);
						return;
					}

					// Show loading
					if (statusDiv) {
						statusDiv.innerHTML = '<span class="spinner is-active"></span> Validating...';
					}

					try {
						const response = await this.ajaxPost({
							action: 'brag_book_gallery_validate_api',
							nonce: this.nonce,
							api_token: token,
							website_property_id: propertyId
						});

						if (statusDiv) {
							if (response.success && response.data.valid) {
								statusDiv.innerHTML = '<span class="dashicons dashicons-yes" style="color: #46b450;"></span> <span style="color: #46b450;">Connected</span>';
								this.showDialog('API credentials validated successfully!', 'success');
							} else {
								const message = response.data?.message || 'Invalid credentials';
								statusDiv.innerHTML = `<span class="dashicons dashicons-no" style="color: #dc3232;"></span> <span style="color: #dc3232;">${message}</span>`;
								this.showDialog(`API validation failed: ${message}`, 'error');
							}
						}
					} catch (error) {
						if (statusDiv) {
							statusDiv.innerHTML = `<span class="dashicons dashicons-warning" style="color: #ffb900;"></span> <span style="color: #ffb900;">Connection error</span>`;
						}
						this.showDialog(`Connection error: ${error.message}`, 'error');
					}
				});
			});
		}


		/**
		 * Make AJAX POST request to WordPress admin-ajax.php
		 * Handles JSON parsing, error handling, and WordPress-specific response format
		 * @param {Object} data - Data object to send (will be converted to FormData)
		 * @returns {Promise<Object>} Promise resolving to parsed JSON response
		 */
		async ajaxPost(data) {
			// Convert data object to FormData for WordPress compatibility
			const formData = new FormData();
			for (const key in data) {
				formData.append(key, data[key]);
			}

			// Make fetch request to WordPress AJAX endpoint
			const response = await fetch(this.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin', // Include WordPress authentication cookies
				body: formData
			});

			// Check for HTTP errors
			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			// Parse response based on content type
			const contentType = response.headers.get('content-type');
			if (contentType && contentType.includes('application/json')) {
				// Standard JSON response
				return await response.json();
			} else {
				// Fallback: try to parse text as JSON (WordPress sometimes sends JSON with wrong content-type)
				const text = await response.text();
				try {
					return JSON.parse(text);
				} catch (e) {
					// Handle common WordPress error responses
					if (text.includes('<div') || text.includes('<!DOCTYPE')) {
						console.error('Server returned HTML instead of JSON:', text);
						throw new Error('Server returned an HTML error page. Please check PHP error logs.');
					}
					console.error('Invalid JSON response:', text);
					throw new Error('Invalid server response format');
				}
			}
		}

		/**
		 * Initialize factory reset functionality
		 */
		initFactoryReset() {
			const resetButton = document.getElementById('brag-book-gallery-factory-reset');
			
			if (!resetButton) return;

			resetButton.addEventListener('click', async (e) => {
				e.preventDefault();

				// Create confirmation dialog
				const confirmDialog = document.createElement('dialog');
				confirmDialog.className = 'brag-book-gallery-dialog brag-book-gallery-factory-reset-dialog';
				confirmDialog.innerHTML = `
					<div class="brag-book-gallery-dialog-content brag-book-gallery-dialog-danger">
						<div class="brag-book-gallery-dialog-header">
							<h3 class="brag-book-gallery-dialog-title">⚠️ Factory Reset Warning</h3>
							<button type="button" class="brag-book-gallery-dialog-close" aria-label="Close">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
						<div class="brag-book-gallery-dialog-body">
							<div class="brag-book-gallery-dialog-icon">
								<span class="dashicons dashicons-warning"></span>
							</div>
							<div class="brag-book-gallery-dialog-message">
								<p><strong>This will PERMANENTLY delete:</strong></p>
								<ul style="text-align: left; margin: 10px 0;">
									<li>All plugin settings and configurations</li>
									<li>All pages containing the [brag_book_gallery] shortcode</li>
									<li>All cached data and transients</li>
									<li>All API tokens and credentials</li>
									<li>Custom database tables</li>
									<li>All log files</li>
								</ul>
								<p><strong style="color: #dc3232;">This action CANNOT be undone!</strong></p>
								<p>Are you absolutely sure you want to continue?</p>
							</div>
						</div>
						<div class="brag-book-gallery-dialog-footer">
							<button type="button" class="button button-secondary brag-book-gallery-dialog-cancel">Cancel</button>
							<button type="button" class="button button-danger brag-book-gallery-dialog-confirm">Continue to Final Warning</button>
						</div>
					</div>
				`;

				document.body.appendChild(confirmDialog);
				confirmDialog.showModal();

				// Handle first confirmation
				const firstConfirmBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-confirm');
				const firstCancelBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-cancel');
				const firstCloseBtn = confirmDialog.querySelector('.brag-book-gallery-dialog-close');

				const closeFirstDialog = () => {
					confirmDialog.close();
					confirmDialog.remove();
				};

				firstCancelBtn.addEventListener('click', closeFirstDialog);
				firstCloseBtn.addEventListener('click', closeFirstDialog);
				
				// Click outside to close
				confirmDialog.addEventListener('click', (e) => {
					if (e.target === confirmDialog) {
						closeFirstDialog();
					}
				});

				firstConfirmBtn.addEventListener('click', () => {
					closeFirstDialog();

					// Show final confirmation dialog
					const finalDialog = document.createElement('dialog');
					finalDialog.className = 'brag-book-gallery-dialog brag-book-gallery-factory-reset-dialog';
					finalDialog.innerHTML = `
						<div class="brag-book-gallery-dialog-content brag-book-gallery-dialog-danger">
							<div class="brag-book-gallery-dialog-header">
								<h3 class="brag-book-gallery-dialog-title">🛑 FINAL WARNING</h3>
								<button type="button" class="brag-book-gallery-dialog-close" aria-label="Close">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
							<div class="brag-book-gallery-dialog-body">
								<div class="brag-book-gallery-dialog-icon">
									<span class="dashicons dashicons-dismiss"></span>
								</div>
								<div class="brag-book-gallery-dialog-message">
									<p>You are about to <strong>completely reset</strong> the BRAG Book Gallery plugin.</p>
									<p>Please confirm one more time that you want to proceed with the factory reset.</p>
								</div>
							</div>
							<div class="brag-book-gallery-dialog-footer">
								<button type="button" class="button button-secondary brag-book-gallery-dialog-cancel">Cancel</button>
								<button type="button" class="button button-danger brag-book-gallery-dialog-reset">Yes, Factory Reset</button>
							</div>
						</div>
					`;

					document.body.appendChild(finalDialog);
					finalDialog.showModal();

					const finalResetBtn = finalDialog.querySelector('.brag-book-gallery-dialog-reset');
					const finalCancelBtn = finalDialog.querySelector('.brag-book-gallery-dialog-cancel');
					const finalCloseBtn = finalDialog.querySelector('.brag-book-gallery-dialog-close');

					const closeFinalDialog = () => {
						finalDialog.close();
						finalDialog.remove();
					};

					finalCancelBtn.addEventListener('click', closeFinalDialog);
					finalCloseBtn.addEventListener('click', closeFinalDialog);

					// Click outside to close
					finalDialog.addEventListener('click', (e) => {
						if (e.target === finalDialog) {
							closeFinalDialog();
						}
					});

					// Handle actual reset
					finalResetBtn.addEventListener('click', async () => {
						closeFinalDialog();

						// Disable button and show loading state
						resetButton.disabled = true;
						const originalText = resetButton.textContent;
						resetButton.textContent = 'Resetting... Please wait...';

						try {
							// Get nonce from button data attribute if main nonce isn't available
							const buttonNonce = resetButton.getAttribute('data-nonce');
							const nonceToUse = this.nonce || buttonNonce || '';
							
							if (!nonceToUse) {
								throw new Error('Security nonce not found. Please refresh the page and try again.');
							}
							
							const response = await this.ajaxPost({
								action: 'brag_book_gallery_factory_reset',
								nonce: nonceToUse,
								confirm: true
							});

							// Check if response is valid JSON
							if (!response || typeof response !== 'object') {
								throw new Error('Invalid server response. Please check error logs.');
							}

							if (response.success) {
								// Show success dialog
								this.showDialog(
									response.data.message || 'Plugin has been successfully reset to factory defaults.',
									'success',
									'Factory Reset Complete'
								);
								
								// Wait a moment then redirect
								setTimeout(() => {
									if (response.data.redirect) {
										window.location.href = response.data.redirect;
									} else {
										window.location.reload();
									}
								}, 2000);
							} else {
								throw new Error(response.data?.message || response.data || 'Factory reset failed');
							}
						} catch (error) {
							console.error('Factory reset error:', error);
							
							// Show error dialog
							this.showDialog(
								`Failed to reset plugin: ${error.message}`,
								'error',
								'Factory Reset Failed'
							);
							
							// Re-enable button
							resetButton.disabled = false;
							resetButton.textContent = originalText;
						}
					});
				});
			});
		}
	}

}

/**
 * Auto-initialize admin interface when DOM is ready
 * Prevents multiple instances and handles both loading states
 */
if (typeof window.bragBookAdminInstance === 'undefined') {
	if (document.readyState === 'loading') {
		// DOM still loading - wait for DOMContentLoaded event
		document.addEventListener('DOMContentLoaded', () => {
			window.bragBookAdminInstance = new window.BRAGbookAdmin();
		});
	} else {
		// DOM already loaded - initialize immediately
		window.bragBookAdminInstance = new window.BRAGbookAdmin();
	}
}
