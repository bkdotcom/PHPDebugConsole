/**
 * see https://github.com/mishoo/UglifyJS2/blob/master/README.md#minify-options
 */

import { uglify } from 'rollup-plugin-uglify';

export default [
	{
		input: "js_src/main.js",
		external: ['jquery'],
		output: {
			file: "js/Debug.test.js",
			format: "iife", // immediately invoked function expression
			globals: {
				jquery: 'window.jQuery'
			}
		}
	},
	{
		input: "js_src/main.js",
		external: ['jquery'],
		output: {
			file: "js/Debug.test.min.js",
			format: "iife", // immediately invoked function expression
			globals: {
				jquery: 'window.jQuery'
			}
		},
		plugins: [
			uglify({
				compress: {
					drop_console: true
				}
			})
		]
	}
]
