/**
 * BRAGBook Gallery Gutenberg Sidebar
 *
 * Provides sidebar panels for case data management in the block editor
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { Panel, PanelBody, PanelRow, TextControl, SelectControl, TextareaControl, CheckboxControl, Button, Flex, FlexItem, BaseControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
// Using string icons instead of @wordpress/icons for better compatibility

const BragBookGallerySidebar = () => {
	const { editPost } = useDispatch('core/editor');

	const { meta, postType } = useSelect((select) => {
		const { getEditedPostAttribute } = select('core/editor');
		const { getCurrentPostType } = select('core/editor');

		return {
			meta: getEditedPostAttribute('meta') || {},
			postType: getCurrentPostType()
		};
	});

	// Only show for brag_book_cases post type
	if (postType !== 'brag_book_cases') {
		return null;
	}

	const updateMeta = (key, value) => {
		editPost({
			meta: {
				...meta,
				[key]: value
			}
		});
	};

	// Case Details Panel
	const CaseDetailsPanel = () => (
		<PanelBody title={__('Case Details', 'brag-book-gallery')} initialOpen={true}>
			<BaseControl __nextHasNoMarginBottom={true}>
				<TextControl
					label={__('Patient Age', 'brag-book-gallery')}
					type="number"
					min={18}
					max={100}
					value={meta.brag_book_gallery_patient_age || ''}
					onChange={(value) => updateMeta('brag_book_gallery_patient_age', value)}
					help={__('Patient age at time of procedure', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</BaseControl>
			<BaseControl __nextHasNoMarginBottom={true}>
				<SelectControl
					label={__('Patient Gender', 'brag-book-gallery')}
					value={meta.brag_book_gallery_patient_gender || ''}
					onChange={(value) => updateMeta('brag_book_gallery_patient_gender', value)}
					options={[
						{ label: __('— Select Gender —', 'brag-book-gallery'), value: '' },
						{ label: __('Male', 'brag-book-gallery'), value: 'Male' },
						{ label: __('Female', 'brag-book-gallery'), value: 'Female' },
						{ label: __('Non-binary', 'brag-book-gallery'), value: 'Non-binary' },
						{ label: __('Other', 'brag-book-gallery'), value: 'Other' },
						{ label: __('Prefer not to say', 'brag-book-gallery'), value: 'Prefer not to say' }
					]}
					help={__('Patient gender identity for demographic filtering', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</BaseControl>
			<BaseControl __nextHasNoMarginBottom={true}>
				<TextControl
					label={__('Procedure Date', 'brag-book-gallery')}
					type="date"
					value={meta.brag_book_gallery_procedure_date || ''}
					onChange={(value) => updateMeta('brag_book_gallery_procedure_date', value)}
					help={__('Date when the procedure was performed', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</BaseControl>
			<BaseControl __nextHasNoMarginBottom={true}>
				<TextareaControl
					label={__('Case Notes', 'brag-book-gallery')}
					value={meta.brag_book_gallery_notes || ''}
					onChange={(value) => updateMeta('brag_book_gallery_notes', value)}
					rows={4}
					help={__('Additional notes about this case (optional)', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
				/>
			</BaseControl>
		</PanelBody>
	);

	// API Data Panel
	const APIDataPanel = () => (
		<PanelBody title={__('API Data', 'brag-book-gallery')} initialOpen={false}>
			<PanelRow>
				<TextControl
					label={__('API Case ID', 'brag-book-gallery')}
					type="number"
					value={meta.brag_book_gallery_api_id || ''}
					onChange={(value) => updateMeta('brag_book_gallery_api_id', value)}
					help={__('Unique identifier from the BRAGBook API', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('Patient ID', 'brag-book-gallery')}
					value={meta.brag_book_gallery_patient_id || ''}
					onChange={(value) => updateMeta('brag_book_gallery_patient_id', value)}
					help={__('Patient identifier from the API', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('Organization ID', 'brag-book-gallery')}
					value={meta.brag_book_gallery_org_id || ''}
					onChange={(value) => updateMeta('brag_book_gallery_org_id', value)}
					help={__('Organization identifier', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('Quality Score', 'brag-book-gallery')}
					type="number"
					min={1}
					max={10}
					value={meta.brag_book_gallery_quality_score || ''}
					onChange={(value) => updateMeta('brag_book_gallery_quality_score', value)}
					help={__('Quality rating from 1-10', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
		</PanelBody>
	);

	// Patient Information Panel
	const PatientInfoPanel = () => (
		<PanelBody title={__('Patient Information', 'brag-book-gallery')} initialOpen={false}>
			<PanelRow>
				<TextControl
					label={__('Ethnicity', 'brag-book-gallery')}
					value={meta.brag_book_gallery_ethnicity || ''}
					onChange={(value) => updateMeta('brag_book_gallery_ethnicity', value)}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('Height', 'brag-book-gallery')}
					type="number"
					value={meta.brag_book_gallery_height || ''}
					onChange={(value) => updateMeta('brag_book_gallery_height', value)}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('Height Unit', 'brag-book-gallery')}
					value={meta.brag_book_gallery_height_unit || ''}
					onChange={(value) => updateMeta('brag_book_gallery_height_unit', value)}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('Weight', 'brag-book-gallery')}
					type="number"
					value={meta.brag_book_gallery_weight || ''}
					onChange={(value) => updateMeta('brag_book_gallery_weight', value)}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('Weight Unit', 'brag-book-gallery')}
					value={meta.brag_book_gallery_weight_unit || ''}
					onChange={(value) => updateMeta('brag_book_gallery_weight_unit', value)}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
		</PanelBody>
	);

	// Settings Panel
	const SettingsPanel = () => (
		<PanelBody title={__('Settings', 'brag-book-gallery')} initialOpen={false}>
			<PanelRow>
				<CheckboxControl
					label={__('Revision Surgery', 'brag-book-gallery')}
					checked={meta.brag_book_gallery_revision_surgery === '1'}
					onChange={(checked) => updateMeta('brag_book_gallery_revision_surgery', checked ? '1' : '0')}
				/>
			</PanelRow>
			<PanelRow>
				<CheckboxControl
					label={__('Approved for Social Media', 'brag-book-gallery')}
					checked={meta.brag_book_gallery_approved_for_social === '1'}
					onChange={(checked) => updateMeta('brag_book_gallery_approved_for_social', checked ? '1' : '0')}
				/>
			</PanelRow>
			<PanelRow>
				<CheckboxControl
					label={__('For Tablet Display', 'brag-book-gallery')}
					checked={meta.brag_book_gallery_is_for_tablet === '1'}
					onChange={(checked) => updateMeta('brag_book_gallery_is_for_tablet', checked ? '1' : '0')}
				/>
			</PanelRow>
			<PanelRow>
				<CheckboxControl
					label={__('For Website Display', 'brag-book-gallery')}
					checked={meta.brag_book_gallery_is_for_website === '1'}
					onChange={(checked) => updateMeta('brag_book_gallery_is_for_website', checked ? '1' : '0')}
				/>
			</PanelRow>
			<PanelRow>
				<CheckboxControl
					label={__('Draft', 'brag-book-gallery')}
					checked={meta.brag_book_gallery_draft === '1'}
					onChange={(checked) => updateMeta('brag_book_gallery_draft', checked ? '1' : '0')}
				/>
			</PanelRow>
			<PanelRow>
				<CheckboxControl
					label={__('No Watermark', 'brag-book-gallery')}
					checked={meta.brag_book_gallery_no_watermark === '1'}
					onChange={(checked) => updateMeta('brag_book_gallery_no_watermark', checked ? '1' : '0')}
				/>
			</PanelRow>
			<PanelRow>
				<CheckboxControl
					label={__('Contains Nudity', 'brag-book-gallery')}
					checked={meta.brag_book_gallery_is_nude === '1'}
					onChange={(checked) => updateMeta('brag_book_gallery_is_nude', checked ? '1' : '0')}
				/>
			</PanelRow>
		</PanelBody>
	);

	// SEO Panel
	const SEOPanel = () => (
		<PanelBody title={__('SEO & Marketing', 'brag-book-gallery')} initialOpen={false}>
			<PanelRow>
				<TextControl
					label={__('SEO Suffix URL', 'brag-book-gallery')}
					value={meta.brag_book_gallery_seo_suffix_url || ''}
					onChange={(value) => updateMeta('brag_book_gallery_seo_suffix_url', value)}
					help={__('URL suffix for SEO purposes', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('SEO Headline', 'brag-book-gallery')}
					value={meta.brag_book_gallery_seo_headline || ''}
					onChange={(value) => updateMeta('brag_book_gallery_seo_headline', value)}
					help={__('SEO-optimized headline', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('SEO Page Title', 'brag-book-gallery')}
					value={meta.brag_book_gallery_seo_page_title || ''}
					onChange={(value) => updateMeta('brag_book_gallery_seo_page_title', value)}
					help={__('Page title for search engines', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('SEO Page Description', 'brag-book-gallery')}
					value={meta.brag_book_gallery_seo_page_description || ''}
					onChange={(value) => updateMeta('brag_book_gallery_seo_page_description', value)}
					help={__('Meta description for search engines', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
			<PanelRow>
				<TextControl
					label={__('SEO Alt Text', 'brag-book-gallery')}
					value={meta.brag_book_gallery_seo_alt_text || ''}
					onChange={(value) => updateMeta('brag_book_gallery_seo_alt_text', value)}
					help={__('Alternative text for images', 'brag-book-gallery')}
					__nextHasNoMarginBottom={true}
					__next40pxDefaultSize={true}
				/>
			</PanelRow>
		</PanelBody>
	);

	// Image URLs Panel
	const ImageURLsPanel = () => {
		// Convert stored image URL sets to separate textarea values
		const imageUrlsByType = useMemo(() => {
			try {
				const rawData = meta.brag_book_gallery_image_url_sets || '[]';
				const sets = JSON.parse(rawData);

				const urlsByType = {
					before_url: [],
					after_url1: [],
					after_url2: [],
					after_url3: [],
					post_processed_url: [],
					high_res_url: []
				};

				sets.forEach((set) => {
					if (set.before_url) urlsByType.before_url.push(set.before_url);
					if (set.after_url1) urlsByType.after_url1.push(set.after_url1);
					if (set.after_url2) urlsByType.after_url2.push(set.after_url2);
					if (set.after_url3) urlsByType.after_url3.push(set.after_url3);
					if (set.post_processed_url) urlsByType.post_processed_url.push(set.post_processed_url);
					if (set.high_res_url) urlsByType.high_res_url.push(set.high_res_url);
				});

				return {
					before_url: urlsByType.before_url.join('\n'),
					after_url1: urlsByType.after_url1.join('\n'),
					after_url2: urlsByType.after_url2.join('\n'),
					after_url3: urlsByType.after_url3.join('\n'),
					post_processed_url: urlsByType.post_processed_url.join('\n'),
					high_res_url: urlsByType.high_res_url.join('\n')
				};
			} catch (e) {
				console.error('Error parsing image sets:', e);
				return {
					before_url: '',
					after_url1: '',
					after_url2: '',
					after_url3: '',
					post_processed_url: '',
					high_res_url: ''
				};
			}
		}, [meta.brag_book_gallery_image_url_sets]);

		const updateImageUrlType = (type, value) => {
			// Get current data
			const currentData = { ...imageUrlsByType };
			currentData[type] = value;

			// Convert back to URL sets format
			const maxLines = Math.max(
				...Object.values(currentData).map(text => text.split('\n').filter(url => url.trim()).length),
				0
			);

			const urlSets = [];
			for (let i = 0; i < maxLines; i++) {
				const set = {
					before_url: currentData.before_url.split('\n')[i]?.trim() || '',
					after_url1: currentData.after_url1.split('\n')[i]?.trim() || '',
					after_url2: currentData.after_url2.split('\n')[i]?.trim() || '',
					after_url3: currentData.after_url3.split('\n')[i]?.trim() || '',
					post_processed_url: currentData.post_processed_url.split('\n')[i]?.trim() || '',
					high_res_url: currentData.high_res_url.split('\n')[i]?.trim() || ''
				};

				// Only add sets that have at least one URL
				if (Object.values(set).some(url => url.length > 0)) {
					urlSets.push(set);
				}
			}

			updateMeta('brag_book_gallery_image_url_sets', JSON.stringify(urlSets));
		};

		return (
			<PanelBody title={__('Image URLs', 'brag-book-gallery')} initialOpen={false}>
				<TextareaControl
					label={__('Before Image URLs', 'brag-book-gallery')}
					help={__('Enter one before image URL per line', 'brag-book-gallery')}
					value={imageUrlsByType.before_url}
					onChange={(value) => updateImageUrlType('before_url', value)}
					rows={4}
					__nextHasNoMarginBottom={true}
				/>
				<TextareaControl
					label={__('After Image URLs 1', 'brag-book-gallery')}
					help={__('Enter one after image URL per line', 'brag-book-gallery')}
					value={imageUrlsByType.after_url1}
					onChange={(value) => updateImageUrlType('after_url1', value)}
					rows={4}
					__nextHasNoMarginBottom={true}
				/>
				<TextareaControl
					label={__('After Image URLs 2', 'brag-book-gallery')}
					help={__('Enter one after image URL per line', 'brag-book-gallery')}
					value={imageUrlsByType.after_url2}
					onChange={(value) => updateImageUrlType('after_url2', value)}
					rows={4}
					__nextHasNoMarginBottom={true}
				/>
				<TextareaControl
					label={__('After Image URLs 3', 'brag-book-gallery')}
					help={__('Enter one after image URL per line', 'brag-book-gallery')}
					value={imageUrlsByType.after_url3}
					onChange={(value) => updateImageUrlType('after_url3', value)}
					rows={4}
					__nextHasNoMarginBottom={true}
				/>
				<TextareaControl
					label={__('Post-Processed Image URLs', 'brag-book-gallery')}
					help={__('Enter one post-processed image URL per line', 'brag-book-gallery')}
					value={imageUrlsByType.post_processed_url}
					onChange={(value) => updateImageUrlType('post_processed_url', value)}
					rows={4}
					__nextHasNoMarginBottom={true}
				/>
				<TextareaControl
					label={__('High-Res Post-Processed Image URLs', 'brag-book-gallery')}
					help={__('Enter one high-res image URL per line', 'brag-book-gallery')}
					value={imageUrlsByType.high_res_url}
					onChange={(value) => updateImageUrlType('high_res_url', value)}
					rows={4}
					__nextHasNoMarginBottom={true}
				/>
			</PanelBody>
		);
	};

	return (
		<>
			<PluginSidebarMoreMenuItem target="brag-book-gallery-sidebar">
				{__('BRAGBook Gallery', 'brag-book-gallery')}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="brag-book-gallery-sidebar"
				title={__('BRAGBook Gallery', 'brag-book-gallery')}
				icon="camera"
			>
				<Panel>
					<CaseDetailsPanel />
					<APIDataPanel />
					<PatientInfoPanel />
					<SettingsPanel />
					<SEOPanel />
					<ImageURLsPanel />
				</Panel>
			</PluginSidebar>
		</>
	);
};

registerPlugin('brag-book-gallery-sidebar', {
	render: BragBookGallerySidebar,
});