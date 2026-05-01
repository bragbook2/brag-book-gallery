/**
 * Case meta box tab switching.
 */
(function () {
	'use strict';

	function init() {
		var navTabs = document.querySelectorAll('.brag-book-api-data-tabs .nav-tab');
		if (!navTabs.length) {
			return;
		}

		navTabs.forEach(function (tab) {
			tab.addEventListener('click', function (event) {
				event.preventDefault();
				var target = tab.getAttribute('href');

				document.querySelectorAll('.brag-book-api-data-tabs .nav-tab').forEach(function (t) {
					t.classList.remove('nav-tab-active');
				});
				document.querySelectorAll('.brag-book-api-data-tabs .tab-content').forEach(function (c) {
					c.classList.remove('active');
				});

				tab.classList.add('nav-tab-active');
				if (target) {
					var panel = document.querySelector(target);
					if (panel) {
						panel.classList.add('active');
					}
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
