/**
 * Debug Tools JavaScript
 *
 * Handles AJAX interactions for debug tools in the admin area
 *
 * @package BragBookGallery
 * @since 3.0.0
 */

class DebugTools {
    constructor() {
        // Ensure the global object exists for backward compatibility
        window.bragBookDebugTools = window.bragBookDebugTools || {
            ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: ''
        };

        this.ajaxUrl = window.bragBookDebugTools.ajaxUrl;
        this.nonce = window.bragBookDebugTools.nonce;
        
        // Prevent jQuery UI tabs from interfering with our tabs
        this.preventTabsInterference();
        
        this.init();
    }

    preventTabsInterference() {
        // Only prevent jQuery UI tabs from initializing on our specific elements
        if (typeof jQuery !== 'undefined' && jQuery.fn.tabs) {
            const originalTabs = jQuery.fn.tabs;
            jQuery.fn.tabs = function() {
                // Check if this is within our debug tools area
                if (this.hasClass('brag-book-gallery-tabs') || 
                    this.hasClass('brag-book-debug-tabs') ||
                    this.closest('.brag-book-debug-tools').length > 0) {
                    // Don't initialize jQuery UI tabs on our elements
                    return this;
                }
                return originalTabs.apply(this, arguments);
            };
        }
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupEventListeners());
        } else {
            this.setupEventListeners();
        }
    }

    setupEventListeners() {
        // Set System Info as default tab on page load
        this.setDefaultTab();
        
        // Prevent WordPress or other scripts from handling our tabs
        // Use capture phase to intercept before jQuery handlers
        const tabContainer = document.querySelector('.brag-book-debug-tools');
        if (tabContainer) {
            tabContainer.addEventListener('click', (e) => {
                // Only handle clicks on debug tab links within the debug tools container
                const link = e.target.closest('.brag-book-debug-tab-link');
                if (link) {
                    e.stopImmediatePropagation(); // Stop other handlers
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleTabSwitch(e, link);
                }
            }, true); // Use capture phase
        }
        
        // Don't interfere with main navigation tabs - let them work normally
        const mainNavTabs = document.querySelectorAll('.brag-book-gallery-tabs .brag-book-gallery-tab-link');
        mainNavTabs.forEach(tab => {
            // Remove any event handlers that might have been added by debug tools
            const newTab = tab.cloneNode(true);
            tab.parentNode.replaceChild(newTab, tab);
        });

        // Setup tool-specific handlers
        this.setupGalleryChecker();
        this.setupRewriteDebug();
        this.setupRewriteFix();
        this.setupRewriteFlush();
    }
    
    setDefaultTab() {
        // Ensure System Info tab is active by default
        const systemInfoTab = document.querySelector('.brag-book-debug-tab-link[data-tab-target="system-info"]');
        const systemInfoPanel = document.getElementById('system-info');
        
        if (systemInfoTab && systemInfoPanel) {
            // Set tab as active
            const parentLi = systemInfoTab.closest('.brag-book-debug-tab-item');
            if (parentLi) {
                // Remove active from all debug tabs only
                document.querySelectorAll('.brag-book-debug-tools .brag-book-debug-tab-item').forEach(item => {
                    item.classList.remove('active');
                });
                parentLi.classList.add('active');
            }
            
            // Show panel
            document.querySelectorAll('.brag-book-debug-tools .tool-panel').forEach(panel => {
                panel.classList.remove('active');
                panel.style.display = 'none';
            });
            systemInfoPanel.classList.add('active');
            systemInfoPanel.style.display = 'block';
        }
    }

    handleTabSwitch(e, link) {
        // This method now only handles debug tool tabs, not main navigation
        
        // First try data-tab-target attribute, then fall back to href
        let targetId = link.getAttribute('data-tab-target');
        
        if (!targetId) {
            const href = link.getAttribute('href');
            // Extract target ID from href - handle both #id and full URLs
            if (href && href.startsWith('#')) {
                targetId = href.substring(1);
            } else if (href && href.includes('#')) {
                targetId = href.split('#')[1];
            } else {
                console.error('Invalid tab href:', href);
                return false;
            }
        }
        
        // Only update debug tool tabs (not main navigation)
        document.querySelectorAll('.brag-book-debug-tools .brag-book-debug-tab-item').forEach(item => {
            item.classList.remove('active');
        });
        const parentLi = link.closest('.brag-book-debug-tab-item');
        if (parentLi) {
            parentLi.classList.add('active');
        }
        
        // Update active panel - only within debug tools
        document.querySelectorAll('.brag-book-debug-tools .tool-panel').forEach(panel => {
            panel.classList.remove('active');
            panel.style.display = 'none';
        });
        const targetPanel = document.getElementById(targetId);
        if (targetPanel) {
            targetPanel.classList.add('active');
            targetPanel.style.display = 'block';
        }
        
        // Update URL hash without triggering navigation
        if (window.history && window.history.replaceState) {
            const newUrl = window.location.pathname + window.location.search + '#' + targetId;
            window.history.replaceState(null, '', newUrl);
        }
        
        return false; // Extra prevention
    }

    setupGalleryChecker() {
        // Create gallery page
        const createBtn = document.getElementById('create-gallery-page');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.createGalleryPage());
        }

        // Update gallery slug
        const updateBtn = document.getElementById('update-gallery-slug');
        if (updateBtn) {
            updateBtn.addEventListener('click', () => this.updateGallerySlug());
        }

        // Show gallery rules
        const showRulesBtn = document.getElementById('show-gallery-rules');
        if (showRulesBtn) {
            showRulesBtn.addEventListener('click', () => this.showGalleryRules());
        }
    }

    setupRewriteDebug() {
        // Load rewrite rules
        const loadRulesBtn = document.getElementById('load-rewrite-rules');
        if (loadRulesBtn) {
            loadRulesBtn.addEventListener('click', () => this.loadRewriteRules());
        }

        // Test URL
        const testUrlBtn = document.getElementById('test-url-button');
        if (testUrlBtn) {
            testUrlBtn.addEventListener('click', () => this.testUrl());
        }

        // Regenerate rules
        const regenerateBtn = document.getElementById('regenerate-rules');
        if (regenerateBtn) {
            regenerateBtn.addEventListener('click', () => this.regenerateRules());
        }
    }

    setupRewriteFix() {
        // Check .htaccess
        const checkHtaccessBtn = document.getElementById('check-htaccess');
        if (checkHtaccessBtn) {
            checkHtaccessBtn.addEventListener('click', () => this.checkHtaccess());
        }

        // Check rules status
        const checkRulesBtn = document.getElementById('check-rules-status');
        if (checkRulesBtn) {
            checkRulesBtn.addEventListener('click', () => this.checkRulesStatus());
        }

        // Apply fixes
        const applyFixesBtn = document.getElementById('apply-fixes');
        if (applyFixesBtn) {
            applyFixesBtn.addEventListener('click', () => this.applyFixes());
        }
    }

    setupRewriteFlush() {
        // Standard flush
        const standardFlushBtn = document.getElementById('flush-rules-standard');
        if (standardFlushBtn) {
            standardFlushBtn.addEventListener('click', () => this.flushRules('standard'));
        }

        // Hard flush
        const hardFlushBtn = document.getElementById('flush-rules-hard');
        if (hardFlushBtn) {
            hardFlushBtn.addEventListener('click', () => {
                if (confirm('This will force a complete regeneration. Continue?')) {
                    this.flushRules('hard');
                }
            });
        }

        // Flush with registration
        const flushWithRegBtn = document.getElementById('flush-with-registration');
        if (flushWithRegBtn) {
            flushWithRegBtn.addEventListener('click', () => this.flushRules('with_registration'));
        }

        // Verify rules
        const verifyBtn = document.getElementById('verify-rules');
        if (verifyBtn) {
            verifyBtn.addEventListener('click', () => this.verifyRules());
        }
    }

    // Gallery Checker Methods
    async createGalleryPage() {
        if (!confirm('Create a new gallery page with the shortcode?')) {
            return;
        }

        const button = document.getElementById('create-gallery-page');
        const result = document.getElementById('gallery-action-result');
        
        button.disabled = true;
        button.textContent = 'Creating...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'gallery-checker',
                tool_action: 'create_page'
            });

            if (response.success) {
                result.innerHTML = `<div class="notice notice-success"><p>${response.data}</p></div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                result.innerHTML = `<div class="notice notice-error"><p>${response.data}</p></div>`;
            }
        } catch (error) {
            result.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Create Gallery Page';
        }
    }

    async updateGallerySlug() {
        const slug = prompt('Enter the page slug to use for the gallery:');
        if (!slug) return;

        const button = document.getElementById('update-gallery-slug');
        const result = document.getElementById('gallery-action-result');
        
        button.disabled = true;
        button.textContent = 'Updating...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'gallery-checker',
                tool_action: 'update_slug',
                gallery_slug: slug
            });

            if (response.success) {
                result.innerHTML = `<div class="notice notice-success"><p>${response.data}</p></div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                result.innerHTML = `<div class="notice notice-error"><p>${response.data}</p></div>`;
            }
        } catch (error) {
            result.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Update Gallery Slug';
        }
    }

    async showGalleryRules() {
        const button = document.getElementById('show-gallery-rules');
        const display = document.getElementById('gallery-rules-display');
        
        button.disabled = true;
        button.textContent = 'Loading...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'gallery-checker',
                tool_action: 'show_rules'
            });

            display.innerHTML = response.success ? response.data : 
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            display.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Show Gallery Rules';
        }
    }

    // Rewrite Debug Methods
    async loadRewriteRules() {
        const button = document.getElementById('load-rewrite-rules');
        const content = document.getElementById('rewrite-rules-content');
        
        button.disabled = true;
        button.textContent = 'Loading...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-debug',
                tool_action: 'get_rules'
            });

            content.innerHTML = response.success ? response.data : 
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            content.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Load Rewrite Rules';
        }
    }

    async testUrl() {
        const button = document.getElementById('test-url-button');
        const input = document.getElementById('test-url-input');
        const result = document.getElementById('test-url-result');
        
        const url = input.value;
        if (!url) {
            alert('Please enter a URL to test');
            return;
        }

        button.disabled = true;

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-debug',
                tool_action: 'test_url',
                test_url: url
            });

            result.innerHTML = response.success ? response.data : 
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            result.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
        }
    }

    async regenerateRules() {
        if (!confirm('Are you sure you want to regenerate rewrite rules?')) {
            return;
        }

        const button = document.getElementById('regenerate-rules');
        const result = document.getElementById('regenerate-result');
        
        button.disabled = true;
        button.textContent = 'Regenerating...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-debug',
                tool_action: 'regenerate'
            });

            result.innerHTML = response.success ? 
                `<div class="notice notice-success"><p>${response.data}</p></div>` :
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            result.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Force Regenerate Rewrite Rules';
        }
    }

    // Rewrite Fix Methods
    async checkHtaccess() {
        const button = document.getElementById('check-htaccess');
        const status = document.getElementById('htaccess-status');
        
        button.disabled = true;
        button.textContent = 'Checking...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-fix',
                tool_action: 'check_htaccess'
            });

            status.innerHTML = response.success ? response.data : 
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            status.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Check .htaccess';
        }
    }

    async checkRulesStatus() {
        const button = document.getElementById('check-rules-status');
        const status = document.getElementById('rules-status');
        
        button.disabled = true;
        button.textContent = 'Checking...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-fix',
                tool_action: 'check_rules'
            });

            status.innerHTML = response.success ? response.data : 
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            status.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Check Rules Status';
        }
    }

    async applyFixes() {
        if (!confirm('This will apply all fixes to your rewrite rules. Continue?')) {
            return;
        }

        const button = document.getElementById('apply-fixes');
        const result = document.getElementById('fix-result');
        
        button.disabled = true;
        button.textContent = 'Applying fixes...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-fix',
                tool_action: 'apply_fixes'
            });

            result.innerHTML = response.success ? 
                `<div class="notice notice-success"><p>${response.data}</p></div>` :
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            result.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Apply All Fixes';
        }
    }

    // Rewrite Flush Methods
    async flushRules(type) {
        const buttonId = type === 'with_registration' ? 'flush-with-registration' : `flush-rules-${type}`;
        const button = document.getElementById(buttonId);
        const result = document.getElementById('flush-result');
        
        if (!button) {
            console.error(`Button not found: ${buttonId}`);
            if (result) {
                result.innerHTML = `<div class="notice notice-error"><p>Error: Button element not found</p></div>`;
            }
            return;
        }
        
        const originalText = button.textContent;
        
        button.disabled = true;
        button.textContent = 'Flushing...';
        if (result) {
            result.innerHTML = '';
        }

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-flush',
                tool_action: 'flush',
                flush_type: type
            });

            if (result) {
                result.innerHTML = response.success ? 
                    `<div class="notice notice-success"><p>${response.data}</p></div>` :
                    `<div class="notice notice-error"><p>${response.data}</p></div>`;
            }
        } catch (error) {
            if (result) {
                result.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
            }
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    }

    async verifyRules() {
        const button = document.getElementById('verify-rules');
        const result = document.getElementById('verify-result');
        
        button.disabled = true;
        button.textContent = 'Verifying...';

        try {
            const response = await this.makeAjaxRequest({
                tool: 'rewrite-flush',
                tool_action: 'verify'
            });

            result.innerHTML = response.success ? response.data : 
                `<div class="notice notice-error"><p>${response.data}</p></div>`;
        } catch (error) {
            result.innerHTML = `<div class="notice notice-error"><p>Error: ${error.message}</p></div>`;
        } finally {
            button.disabled = false;
            button.textContent = 'Verify Rules';
        }
    }

    // Utility method for AJAX requests
    async makeAjaxRequest(data) {
        const formData = new FormData();
        formData.append('action', 'brag_book_debug_tool');
        formData.append('nonce', this.nonce);
        
        // Add all data properties to formData
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

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

// Initialize when DOM is ready
const debugTools = new DebugTools();