/**
 * WordPress BRAGBook Gallery - Webpack Configuration
 */

const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
	const isProduction = argv.mode === 'production';

	return {
		cache: false,
		entry: {
			frontend: './src/js/frontend.js',
			'carousel-frontend': './src/js/carousel-frontend.js',
			admin: './src/js/admin.js',
			'sync-admin': './src/js/sync-admin.js',
			'stage-sync': './src/js/stage-sync.js'
		},
		output: {
			path: path.resolve(__dirname, 'assets/js'),
			filename: (pathData) => {
				// Map entry names to desired output filenames
				const nameMap = {
					frontend: 'brag-book-gallery.js',
					'carousel-frontend': 'brag-book-gallery-carousel.js',
					admin: 'brag-book-gallery-admin.js',
					'sync-admin': 'brag-book-gallery-sync-admin.js',
					'stage-sync': 'brag-book-gallery-stage-sync.js'
				};
				const baseName = nameMap[pathData.chunk.name] || '[name].js';
				// Add .min suffix for production builds
				return isProduction ? baseName.replace('.js', '.min.js') : baseName;
			},
			// Async chunks (dynamic imports in main-app.js) get stable names so
			// they cache well across deploys. Names come from the
			// webpackChunkName magic comment at each import() site.
			chunkFilename: (pathData) => {
				const name = pathData.chunk.name || `chunk-${pathData.chunk.id}`;
				return isProduction ? `${name}.min.js` : `${name}.js`;
			},
			// 'auto' lets webpack derive the chunk URL from document.currentScript
			// at runtime, so chunks load correctly regardless of where the plugin
			// is installed.
			publicPath: 'auto',
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					exclude: /node_modules/,
					use: {
						loader: 'babel-loader',
						options: {
							presets: [
								'@babel/preset-env',
								['@babel/preset-react', { pragma: 'wp.element.createElement' }]
							]
						}
					}
				}
			]
		},
		resolve: {
			alias: {
				'@utils': path.resolve(__dirname, 'src/js/utils'),
				'@components': path.resolve(__dirname, 'src/js/components'),
				'@filters': path.resolve(__dirname, 'src/js/filters'),
				'@gallery': path.resolve(__dirname, 'src/js/gallery')
			}
		},
		optimization: {
			minimize: isProduction,
			minimizer: [
				new TerserPlugin({
					terserOptions: {
						compress: {
							drop_console: true,
						},
						format: {
							comments: false,
						},
					},
					extractComments: false,
				}),
			],
		},
		devtool: isProduction ? false : 'source-map',
		externals: {
			jquery: 'jQuery',
			'@wordpress/plugins': ['wp', 'plugins'],
			'@wordpress/editor': ['wp', 'editor'],
			'@wordpress/element': ['wp', 'element'],
			'@wordpress/components': ['wp', 'components'],
			'@wordpress/data': ['wp', 'data'],
			'@wordpress/i18n': ['wp', 'i18n'],
			'wp': 'wp'
		}
	};
};
