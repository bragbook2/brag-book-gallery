/**
 * BRAGBook Gallery — Taxonomy media selection.
 *
 * Wires up the WordPress media library for image fields rendered on the
 * procedure and provider taxonomy admin screens. Buttons declare their
 * behaviour through data attributes so a single handler serves every field:
 *
 *   [data-bb-media-upload]
 *     data-target-input    Hidden input that stores the attachment ID.
 *     data-target-preview  Container the selected image is rendered into.
 *     data-remove-button   Remove button to reveal once an image is chosen.
 *     data-frame-title     Media frame title.
 *     data-frame-button-text  Media frame select-button label.
 *     data-preview-style   Optional inline style applied to the preview <img>.
 *
 *   [data-bb-media-remove]
 *     data-target-input    Hidden input to clear.
 *     data-target-preview  Preview container to empty.
 *
 * @since 4.4.0
 */
( function () {
	'use strict';

	/**
	 * Resolve the best preview URL from a media attachment.
	 *
	 * @param {Object} attachment Attachment model attributes from wp.media.
	 * @return {string} Image URL, preferring a medium/thumbnail size.
	 */
	function getPreviewUrl( attachment ) {
		const sizes = attachment.sizes || {};
		if ( sizes.medium ) {
			return sizes.medium.url;
		}
		if ( sizes.thumbnail ) {
			return sizes.thumbnail.url;
		}
		return attachment.url;
	}

	/**
	 * Render the chosen attachment into its preview container.
	 *
	 * @param {HTMLElement} preview      Preview container element.
	 * @param {Object}      attachment   Attachment attributes from wp.media.
	 * @param {string}      previewStyle Optional inline style for the image.
	 * @return {void}
	 */
	function renderPreview( preview, attachment, previewStyle ) {
		if ( ! preview ) {
			return;
		}

		const image = document.createElement( 'img' );
		image.src = getPreviewUrl( attachment );
		image.alt = attachment.alt || '';

		if ( previewStyle ) {
			image.setAttribute( 'style', previewStyle );
		} else {
			image.style.maxWidth = '150px';
			image.style.height = 'auto';
		}

		preview.replaceChildren( image );
	}

	/**
	 * Open the media frame for an upload button.
	 *
	 * @param {HTMLElement} button Upload button that was activated.
	 * @return {void}
	 */
	function openMediaFrame( button ) {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		const input = document.querySelector( button.dataset.targetInput );
		const preview = document.querySelector( button.dataset.targetPreview );
		const removeButton = button.dataset.removeButton
			? document.querySelector( button.dataset.removeButton )
			: null;

		if ( ! input ) {
			return;
		}

		// Reuse the frame across clicks on the same button.
		if ( ! button._bbMediaFrame ) {
			button._bbMediaFrame = wp.media( {
				title: button.dataset.frameTitle || 'Select Image',
				button: { text: button.dataset.frameButtonText || 'Use Image' },
				library: { type: 'image' },
				multiple: false,
			} );

			button._bbMediaFrame.on( 'select', function () {
				const attachment = button._bbMediaFrame
					.state()
					.get( 'selection' )
					.first()
					.toJSON();

				input.value = attachment.id;
				renderPreview( preview, attachment, button.dataset.previewStyle );

				if ( removeButton ) {
					removeButton.style.display = '';
				}
			} );
		}

		button._bbMediaFrame.open();
	}

	/**
	 * Clear an image field via its remove button.
	 *
	 * @param {HTMLElement} button Remove button that was activated.
	 * @return {void}
	 */
	function clearMediaField( button ) {
		const input = document.querySelector( button.dataset.targetInput );
		const preview = document.querySelector( button.dataset.targetPreview );

		if ( input ) {
			input.value = '';
		}
		if ( preview ) {
			preview.replaceChildren();
		}

		button.style.display = 'none';
	}

	/**
	 * Delegate clicks so dynamically rendered fields (e.g. the inline
	 * "add term" form that resets after submission) keep working.
	 */
	document.addEventListener( 'click', function ( event ) {
		const uploadButton = event.target.closest( '[data-bb-media-upload]' );
		if ( uploadButton ) {
			event.preventDefault();
			openMediaFrame( uploadButton );
			return;
		}

		const removeButton = event.target.closest( '[data-bb-media-remove]' );
		if ( removeButton ) {
			event.preventDefault();
			clearMediaField( removeButton );
		}
	} );
} )();
