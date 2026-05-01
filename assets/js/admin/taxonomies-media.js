/**
 * Taxonomy media uploader bindings.
 *
 * Wires generic "choose media" / "remove media" button pairs to the WordPress
 * media frame without depending on jQuery. Button markup uses these attributes:
 *
 *   <button data-bb-media-upload
 *           data-target-input="#field_id"
 *           data-target-preview="#preview_id"
 *           data-remove-button="#remove_btn"
 *           data-frame-title="..."
 *           data-frame-button-text="..."
 *           data-preview-style="...">
 *
 *   <button data-bb-media-remove
 *           data-target-input="#field_id"
 *           data-target-preview="#preview_id">
 */
(function () {
	'use strict';

	function escapeHtml(value) {
		var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
		return String(value).replace(/[&<>"']/g, function (ch) { return map[ch]; });
	}

	function openFrame(button) {
		if (!window.wp || !window.wp.media) {
			return;
		}

		var inputSelector = button.getAttribute('data-target-input');
		var previewSelector = button.getAttribute('data-target-preview');
		var removeSelector = button.getAttribute('data-remove-button');
		var previewStyle = button.getAttribute('data-preview-style') || 'max-width:200px;height:auto;';
		var title = button.getAttribute('data-frame-title') || 'Choose Image';
		var buttonText = button.getAttribute('data-frame-button-text') || 'Choose';

		var input = inputSelector ? document.querySelector(inputSelector) : null;
		var preview = previewSelector ? document.querySelector(previewSelector) : null;
		var removeButton = removeSelector ? document.querySelector(removeSelector) : null;

		var frame = window.wp.media({
			title: title,
			button: { text: buttonText },
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			if (input) {
				input.value = attachment.id;
			}
			if (preview) {
				preview.innerHTML = '<img src="' + escapeHtml(attachment.url) + '" style="' + escapeHtml(previewStyle) + '" alt="" />';
			}
			if (removeButton) {
				removeButton.style.display = '';
			}
		});

		frame.open();
	}

	function clearField(button) {
		var input = document.querySelector(button.getAttribute('data-target-input'));
		var preview = document.querySelector(button.getAttribute('data-target-preview'));
		if (input) {
			input.value = '';
		}
		if (preview) {
			preview.innerHTML = '';
		}
		button.style.display = 'none';
	}

	function onClick(event) {
		var target = event.target.closest('[data-bb-media-upload], [data-bb-media-remove]');
		if (!target) {
			return;
		}
		event.preventDefault();
		if (target.hasAttribute('data-bb-media-upload')) {
			openFrame(target);
		} else {
			clearField(target);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			document.addEventListener('click', onClick);
		});
	} else {
		document.addEventListener('click', onClick);
	}
}());
