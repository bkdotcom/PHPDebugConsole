import { nodeResolve } from '@rollup/plugin-node-resolve' // so can resolve tippy.js
import replace from '@rollup/plugin-replace'
import terser from '@rollup/plugin-terser'

var tasks = [
  {
    input: 'js_src/main.js',
    external: ['clipboardjs', 'zest'],
    output: {
      file: 'js/Debug.js',
      format: 'iife', // immediately invoked function expression
      globals: {
        clipboardjs: 'window.ClipboardJS',
        zest: 'window.zest'
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
    input: 'js_src/zest/Zest.js',
    output: {
      file: 'js/zest.js',
      format: 'iife', // immediately invoked function expression
      globals: {},
      name: 'zest'
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
    external: ['clipboardjs', 'zest'],
    output: {
      file: 'js/Debug.min.js',
      format: 'iife', // immediately invoked function expression
      globals: {
        clipboardjs: 'window.ClipboardJS',
        zest: 'window.zest'
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
    input: 'js_src/zest/Zest.js',
    output: {
      file: 'js/zest.min.js',
      format: 'iife', // immediately invoked function expression
      globals: {},
      name: 'zest'
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
