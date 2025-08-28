/**
 * WordPress BRAGBook Gallery - Webpack Configuration
 */

const path = require('path');

module.exports = {
	entry: {
		frontend: './src/js/frontend.js',
		admin: './src/js/admin.js'
	},
	output: {
		path: path.resolve(__dirname, 'assets/js'),
		filename: (pathData) => {
			// Map entry names to desired output filenames
			const nameMap = {
				frontend: 'brag-book-gallery.js',
				admin: 'brag-book-gallery-admin.js'
			};
			return nameMap[pathData.chunk.name] || '[name].js';
		}
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['@babel/preset-env']
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
	externals: {
		jquery: 'jQuery'
	}
};
