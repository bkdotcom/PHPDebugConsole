/**
 * see https://github.com/mishoo/UglifyJS2/blob/master/README.md#minify-options
 */

import { uglify } from 'rollup-plugin-uglify';

var tasks = [
	{
		input: "js_src/main.js",
		external: ['jquery'],
		output: {
			file: "js/Debug.jquery.js",
			format: "iife", // immediately invoked function expression
			globals: {
				jquery: 'window.jQuery'
			}
		}
	}
];

if (process.env.NODE_ENV !== 'watch') {
	tasks.push({
		input: "js_src/main.js",
		external: ['jquery'],
		output: {
			file: "js/Debug.jquery.min.js",
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
	});
}

export default tasks
