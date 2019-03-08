/*
	rollup -c
*/

export default {
	input: "js_src/main.js",
	external: ['jquery'],
	output: {
		file: "js/Debug.test.js",
		format: "iife", // immediately invoked function expression
		// sourcemap: "inline"
		// name: "PHPDebugConsole",
		globals: {
			jquery: 'window.jQuery'
		}
	}
}
