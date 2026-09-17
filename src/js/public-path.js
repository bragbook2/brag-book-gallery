/**
 * Pin webpack's chunk base URL to the plugin's assets folder.
 *
 * The main bundle loads its filter, favorites, search and share modules as
 * separate chunks. With publicPath 'auto', webpack derives the chunk URL from
 * document.currentScript.src, which is wrong the moment an optimizer such as
 * SiteGround Speed Optimizer or WP Rocket serves the bundle from its own
 * combined-assets folder: chunks 404 there with ChunkLoadError.
 *
 * Must be the first import of the entry so it runs before any chunk request.
 */
/* global __webpack_public_path__:writable */
const pluginUrl = window.bragBookGalleryConfig && window.bragBookGalleryConfig.pluginUrl;

if (pluginUrl) {
	__webpack_public_path__ = pluginUrl.replace(/\/+$/, '') + '/assets/js/';
}
