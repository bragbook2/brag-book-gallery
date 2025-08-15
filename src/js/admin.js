/**
 * BRAG Book Admin JavaScript
 *
 * @package BRAGBook
 * @since   3.0.0
 */

'use strict';

/**
 * BRAG Book Admin Class
 */
if (typeof window.BRAG BookAdmin === 'undefined') {
	window.BRAG BookAdmin = class {
		constructor() {
			this.ajaxUrl = typeof brag_book_gallery_ajax !== 'undefined' ? brag_book_gallery_ajax.ajaxurl : '/wp-admin/admin-ajax.php';
			this.nonce = typeof brag_book_gallery_ajax !== 'undefined' ? brag_book_gallery_ajax.nonce : '';
			this.dialog = null;
			this.init();
		}

		/**
		 * Initialize all admin functionality
		 */
		init() {
			// Create dialog element
			this.createDialog();

			// Initialize different components
			this.initDynamicTable();
			this.initCombineGallery();
			this.initDebugPage();
			this.initApiValidation();
		}

		/**
		 * Create dialog element for messages
		 */
		createDialog() {
			// Check if dialog already exists
			if (document.getElementById('brag-book-gallery-dialog')) {
				this.dialog = document.getElementById('brag-book-gallery-dialog');
				return;
			}

			// Create dialog element
			this.dialog = document.createElement('dialog');
			this.dialog.id = 'brag-book-gallery-dialog';
			this.dialog.className = 'brag-book-gallery-dialog';

			// Create dialog content structure
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

			// Add to body
			document.body.appendChild(this.dialog);

			// Add event listeners
			const closeBtn = this.dialog.querySelector('.brag-book-gallery-dialog-close');
			const okBtn = this.dialog.querySelector('.brag-book-gallery-dialog-ok');

			closeBtn.addEventListener('click', () => this.closeDialog());
			okBtn.addEventListener('click', () => this.closeDialog());

			// Close on backdrop click
			this.dialog.addEventListener('click', (e) => {
				if (e.target === this.dialog) {
					this.closeDialog();
				}
			});

			// Close on Escape key
			this.dialog.addEventListener('cancel', (e) => {
				e.preventDefault();
				this.closeDialog();
			});
		}

		/**
		 * Show dialog with message
		 * @param {string} message - Message to display
		 * @param {string} type - Message type (success, error, warning, info)
		 * @param {string} title - Optional title
		 */
		showDialog(message, type = 'info', title = '') {
			if (!this.dialog) {
				this.createDialog();
			}

			const titleEl = this.dialog.querySelector('.brag-book-gallery-dialog-title');
			const iconEl = this.dialog.querySelector('.brag-book-gallery-dialog-icon');
			const messageEl = this.dialog.querySelector('.brag-book-gallery-dialog-message');
			const contentEl = this.dialog.querySelector('.brag-book-gallery-dialog-content');

			// Set title
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

			// Set icon and color based on type
			contentEl.className = `brag-book-gallery-dialog-content brag-book-gallery-dialog-${type}`;
			switch (type) {
				case 'success':
					iconEl.innerHTML = '<span class="dashicons dashicons-yes-alt"></span>';
					break;
				case 'error':
					iconEl.innerHTML = '<span class="dashicons dashicons-dismiss"></span>';
					break;
				case 'warning':
					iconEl.innerHTML = '<span class="dashicons dashicons-warning"></span>';
					break;
				default:
					iconEl.innerHTML = '<span class="dashicons dashicons-info"></span>';
			}

			// Set message
			messageEl.innerHTML = message;

			// Show dialog
			this.dialog.showModal();
		}

		/**
		 * Close dialog
		 */
		closeDialog() {
			if (this.dialog) {
				this.dialog.close();
			}
		}

		/**
		 * Initialize dynamic table functionality
		 */
		initDynamicTable() {
			const tableBody = document.querySelector("#dynamicTable tbody");
			if (!tableBody) return;

			// Helper functions
			const getLastRowNumber = () => {
				const rows = [...tableBody.querySelectorAll("tr")];
				return rows.reduce((max, row) => {
					const input = row.querySelector('input[data-key^="page_"]');
					if (input) {
						const num = parseInt(input.dataset.key.split('_')[1], 10);
						return Math.max(max, num);
					}
					return max;
				}, 0);
			};

			const createInputCell = (name, rowNumber) => {
				const td = document.createElement("td");
				const input = document.createElement("input");
				input.type = "text";
				input.setAttribute("data-key", `page_${rowNumber}`);
				input.setAttribute("name", `${name}[page_${rowNumber}]`);
				input.required = true;
				td.appendChild(input);
				return td;
			};

			const createButtonCell = () => {
				const td = document.createElement("td");

				const removeBtn = document.createElement("button");
				removeBtn.type = "button";
				removeBtn.className = "button removeRow";
				removeBtn.textContent = "Remove Row";

				const addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "button addRow";
				addBtn.textContent = "Add Row";

				td.appendChild(removeBtn);
				td.appendChild(addBtn);
				return td;
			};

			const addRow = () => {
				const newRowNumber = getLastRowNumber() + 1;
				const row = document.createElement("tr");

				row.appendChild(createInputCell("brag_book_gallery_api_token", newRowNumber));
				row.appendChild(createInputCell("brag_book_gallery_website_property_id", newRowNumber));
				row.appendChild(createInputCell("bb_gallery_page_slug", newRowNumber));
				row.appendChild(createInputCell("bb_seo_page_title", newRowNumber));
				row.appendChild(createInputCell("bb_seo_page_description", newRowNumber));
				row.appendChild(createButtonCell());

				tableBody.appendChild(row);
				updateButtonVisibility();
			};

			const removeRow = (row) => {
				if (tableBody.rows.length <= 1) return;

				const input = row.querySelector('input[data-key^="page_"]');
				const bb_remove_id = input ? input.dataset.key : '';

				if (bb_remove_id) {
					this.ajaxPost({
						action: 'bb_setting_remove_row',
						bb_remove_id
					});
				}

				row.remove();
				updateButtonVisibility();
			};

			const updateButtonVisibility = () => {
				const rows = tableBody.querySelectorAll("tr");
				rows.forEach((row, index) => {
					const addBtn = row.querySelector(".addRow");
					const removeBtn = row.querySelector(".removeRow");

					if (addBtn) addBtn.style.display = (index === rows.length - 1) ? "inline-block" : "none";
					if (removeBtn) removeBtn.style.display = (rows.length > 1) ? "inline-block" : "none";
				});
			};

			// Event delegation for dynamic table
			tableBody.addEventListener("click", (event) => {
				const row = event.target.closest("tr");
				if (event.target.classList.contains("addRow")) {
					addRow();
				}
				if (event.target.classList.contains("removeRow") && row) {
					removeRow(row);
				}
			});

			updateButtonVisibility();
		}

		/**
		 * Initialize combine gallery functionality
		 */
		initCombineGallery() {
			const createBtn = document.getElementById("createCombineGallery");
			const slugContainer = document.getElementById("slugFieldContainer");
			const slugInput = document.querySelector(".combineGallerySlug");

			if (!createBtn || !slugContainer || !slugInput) return;

			if (slugInput.value === "") {
				createBtn.style.display = "block";
				slugContainer.style.display = "none";

				createBtn.addEventListener("click", () => {
					slugContainer.style.display = (slugContainer.style.display === "none") ? "block" : "none";
				});
			} else {
				createBtn.style.display = "none";
				slugContainer.style.display = "block";
			}
		}

		/**
		 * Initialize debug page functionality
		 */
		initDebugPage() {
			this.initDebugTabs();
			this.initErrorLog();
			this.initApiLog();
			this.initSystemInfo();
			this.initDebugSettings();
		}

		/**
		 * Initialize debug page tabs
		 */
		initDebugTabs() {
			const tabLinks = document.querySelectorAll('.brag-book-gallery-log-tab-nav a');
			const tabContents = document.querySelectorAll('.brag-book-gallery-log-tab-content');

			if (tabLinks.length === 0) return;

			tabLinks.forEach(link => {
				link.addEventListener('click', (e) => {
					e.preventDefault();

					// Get target tab
					const targetTab = link.getAttribute('data-tab');

					// Remove active classes
					tabLinks.forEach(l => l.classList.remove('active'));
					tabContents.forEach(c => c.classList.remove('active'));

					// Add active class to clicked tab
					link.classList.add('active');
					const targetContent = document.getElementById(targetTab + '-tab');
					if (targetContent) {
						targetContent.classList.add('active');
					}
				});
			});
		}

		/**
		 * Initialize error log functionality
		 */
		initErrorLog() {
			const refreshBtn = document.getElementById('bb-refresh-error-log');
			const clearBtn = document.getElementById('bb-clear-error-log');
			const downloadBtn = document.getElementById('bb-download-error-log');
			const errorLogContent = document.getElementById('brag-book-gallery-error-log'); // Fixed ID

			if (refreshBtn && errorLogContent) {
				refreshBtn.addEventListener('click', async () => {
					// Disable button and show loading
					refreshBtn.disabled = true;
					const originalContent = errorLogContent.textContent;
					errorLogContent.innerHTML = '<span style="color: #666;">Loading...</span>';

					try {
						const response = await this.ajaxPost({
							action: 'bb_get_error_log',
							nonce: this.nonce
						});

						if (response.success) {
							errorLogContent.textContent = response.data.log || 'No errors logged yet.';
							// Show success feedback
							this.showDialog('Error log refreshed successfully.', 'success');
						} else {
							// Restore original content on error
							errorLogContent.textContent = originalContent;
							this.showDialog(
								`Failed to load error log: ${response.data || 'Unknown error'}`,
								'error'
							);
						}
					} catch (error) {
						// Restore original content on error
						errorLogContent.textContent = originalContent;
						this.showDialog(`Error loading log: ${error.message}`, 'error');
					} finally {
						// Re-enable button
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
								action: 'bb_clear_error_log',
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

			if (downloadBtn && errorLogContent) {
				downloadBtn.addEventListener('click', () => {
					const logContent = errorLogContent.textContent;
					const blob = new Blob([logContent], { type: 'text/plain' });
					const url = window.URL.createObjectURL(blob);
					const a = document.createElement('a');
					a.href = url;
					a.download = `brag-book-gallery-error-log-${new Date().toISOString().split('T')[0]}.txt`;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					window.URL.revokeObjectURL(url);
				});
			}
		}

		/**
		 * Initialize API log functionality
		 */
		initApiLog() {
			const refreshBtn = document.getElementById('bb-refresh-api-log');
			const clearBtn = document.getElementById('bb-clear-api-log');
			const downloadBtn = document.getElementById('bb-download-api-log');
			const apiLogContent = document.getElementById('brag-book-gallery-api-log');

			if (refreshBtn && apiLogContent) {
				refreshBtn.addEventListener('click', async () => {
					refreshBtn.disabled = true;
					const originalContent = apiLogContent.textContent;
					apiLogContent.innerHTML = '<span style="color: #666;">Loading...</span>';

					try {
						const response = await this.ajaxPost({
							action: 'bb_get_api_log',
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
								action: 'bb_clear_api_log',
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
			const copyBtn = document.getElementById('bb-copy-system-info');
			const exportBtn = document.getElementById('bb-export-system-info');
			const systemInfoContent = document.getElementById('brag-book-gallery-system-info'); // Fixed ID

			if (copyBtn && systemInfoContent) {
				copyBtn.addEventListener('click', async () => {
					const systemInfo = systemInfoContent.textContent;
					try {
						await navigator.clipboard.writeText(systemInfo);
						const feedback = document.getElementById('bb-copy-feedback');
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
							const feedback = document.getElementById('bb-copy-feedback');
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
			const saveBtn = document.getElementById('bb-save-debug-settings');
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
							action: 'bb_save_debug_settings',
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
					const propertyInput = row.querySelector('input[name*="websiteproperty_id"]');
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
							action: 'bb_validate_api',
							nonce: this.nonce,
							api_token: token,
							websiteproperty_id: propertyId
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
		 * Make AJAX POST request
		 * @param {Object} data - Data to send
		 * @returns {Promise} - Promise resolving to response
		 */
		async ajaxPost(data) {
			const formData = new FormData();
			for (const key in data) {
				formData.append(key, data[key]);
			}

			const response = await fetch(this.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			});

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			return await response.json();
		}
	}

}

// Initialize when DOM is ready (only once)
if (typeof window.bragBookAdminInstance === 'undefined') {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => {
			window.bragBookAdminInstance = new window.BRAG BookAdmin();
		});
	} else {
		window.bragBookAdminInstance = new window.BRAG BookAdmin();
	}
}
