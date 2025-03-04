/**
 * see https://github.com/mishoo/UglifyJS2/blob/master/README.md#minify-options
 */

// import uglify from '@lopatnov/rollup-plugin-uglify'
import { nodeResolve } from '@rollup/plugin-node-resolve' // so can resolve tippy.js
// import css from 'rollup-plugin-import-css'
import replace from '@rollup/plugin-replace'
import terser from '@rollup/plugin-terser'

var tasks = [
  {
    input: 'js_src/main.js',
    external: ['clipboardjs', 'jquery'],
    output: {
      file: 'js/Debug.jquery.js',
      format: 'iife', // immediately invoked function expression
      globals: {
        clipboardjs: 'window.ClipboardJS',
        jquery: 'window.jQuery'
      },
      name: 'phpDebugConsole'
    },
    plugins: [
      /*
      css({
        output: 'css/bktippy.css',
        alwaysOutput: true
        // output: null
      }),
      */
      nodeResolve(),
      replace({
        preventAssignment: true,
        'process.env.NODE_ENV': JSON.stringify('development')
      })
    ]
  }
]

if (process.env.NODE_ENV !== 'watch') {
  tasks.push({
    input: 'js_src/main.js',
    external: ['clipboardjs', 'jquery'],
    output: {
      file: 'js/Debug.jquery.min.js',
      format: 'iife', // immediately invoked function expression
      globals: {
        clipboardjs: 'window.ClipboardJS',
        jquery: 'window.jQuery'
      }
    },
    plugins: [
      /*
      css({
        output: 'css/bktippy.css',
        alwaysOutput: true
        // output: null
      }),
      */
      nodeResolve(),
      replace({
        preventAssignment: true,
        'process.env.NODE_ENV': JSON.stringify('production')
      }),
      /*
      uglify({
        compress: {
          drop_console: true
        }
      })
      */
      terser()
    ]
  })
}

export default tasks
