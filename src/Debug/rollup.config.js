import { nodeResolve } from '@rollup/plugin-node-resolve' // so can resolve tippy.js
import replace from '@rollup/plugin-replace'
import terser from '@rollup/plugin-terser'

var tasks = [
  {
    input: 'js_src/main.js',
    external: ['clipboardjs', 'microDom'],
    output: {
      file: 'js/Debug.js',
      format: 'iife', // immediately invoked function expression
      globals: {
        clipboardjs: 'window.ClipboardJS',
        microDom: 'window.microDom'
      },
      name: 'phpDebugConsole'
    },
    plugins: [
      nodeResolve(),
      replace({
        preventAssignment: true,
        'process.env.NODE_ENV': JSON.stringify('development')
      })
    ]
  },
  {
    input: 'js_src/microDom.js',
    output: {
      file: 'js/microDom.js',
      format: 'iife', // immediately invoked function expression
      globals: {},
      name: 'microDom'
    },
    plugins: [
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
    external: ['clipboardjs', 'microDom'],
    output: {
      file: 'js/Debug.min.js',
      format: 'iife', // immediately invoked function expression
      globals: {
        clipboardjs: 'window.ClipboardJS',
        microDom: 'window.microDom'
      },
      name: 'phpDebugConsole'
    },
    plugins: [
      nodeResolve(),
      replace({
        preventAssignment: true,
        'process.env.NODE_ENV': JSON.stringify('production')
      }),
      terser()
    ]
  })
  tasks.push({
    input: 'js_src/microDom.js',
    output: {
      file: 'js/microDom.min.js',
      format: 'iife', // immediately invoked function expression
      globals: {},
      name: 'microDom'
    },
    plugins: [
      nodeResolve(),
      replace({
        preventAssignment: true,
        'process.env.NODE_ENV': JSON.stringify('development')
      }),
      terser()
    ]
  })
}

export default tasks
