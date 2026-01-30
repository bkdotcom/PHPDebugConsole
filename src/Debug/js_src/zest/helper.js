import * as customSelectors from './microDom/customSelectors.js'

export var rand = "zest" + Math.random().toString().replace(/\D/g, '')

const computedDisplayValues = {
  'div': 'block',
  'table': 'table',
  'tr': 'table-row',
  'td': 'table-cell',
  'th': 'table-cell',
}

const getDisplayValue = function (el) {
  if (el === window) {
    return undefined
  }

  let displayVal = window.getComputedStyle(el).display

  if (displayVal !== 'none') {
    return displayVal
  }

  // display is "none" - Lets clone the element and check its display value
  const clone = el.cloneNode()
  clone.innerHTML = ''
  clone.removeAttribute('style')
  clone.style.width = '0px'
  clone.style.height = '0px'
  el.after(clone)
  displayVal = window.getComputedStyle(clone).display
  clone.remove()

  if (displayVal !== 'none') {
    return displayVal
  }

  // we're still "none" so lets check the display value of a temporary element
  const tagName = el.tagName.toLowerCase()
  if (computedDisplayValues[tagName]) {
    return computedDisplayValues[tagName]
  }

  const elTemp = document.createElement(tagName)
  document.body.appendChild(elTemp)
  displayVal = window.getComputedStyle(elTemp).display
  document.body.removeChild(elTemp)
  computedDisplayValues[tagName] = displayVal
  return displayVal
}

/**
 * convert arguments to elements
 *
 * accepts text/html/css-selector, Node, NodeList, function, iterable
 */
export const argsToElements = function (args, acceptSelector, el, index) {
  const elements = []
  args = Array.from(args) // shallow copy so not affecting original
  while (args.length) {
    const arg = args.shift()
    if (typeof arg === 'string') {
      let elementsAppend = false
      if (acceptSelector) {
        elementsAppend = isCssSelector(arg)
      }
      if (elementsAppend === false) {
        elementsAppend = createElements(arg)
      }
      args.unshift(...elementsAppend)
    } else if (arg instanceof Node) {
      elements.push(arg)
    } else if (arg instanceof NodeList) {
      elements.push(...arg)
    } else if (typeof arg === 'object' && arg !== null && typeof arg[Symbol.iterator] === 'function') {
      args.unshift(...arg)
    } else if (typeof arg === 'function') {
      args.unshift(arg.call(el, el, index))
    }
  }
  return elements
}

export const camelCase = function (str) {
  const regex = /[-_\s]+/
  if (str.match(regex) === null) {
    return str
  }
  return str.toLowerCase().split(regex).map((word, index) => {
    return index === 0
      ? word
      : word.charAt(0).toUpperCase() + word.slice(1)
  }).join('')
}

export const createElements = function (html) {
  const element = document.createElement('template')
  element.innerHTML = html  // do not trim
  return element.content.childNodes  // vs element.content.children
}

export const each = function (mixed, callback) {
  if (Array.isArray(mixed)) {
    return mixed.forEach((value, index) => callback.call(value, value, index))
  }
  for (const [key, value] of Object.entries(mixed)) {
    callback.call(value, value, key)
  }
}

export const elInitMicroDomInfo = function (el) {
  if (typeof el[rand] === 'undefined') {
    el[rand] = {
      data: {},
      display: getDisplayValue(el),
      eventHandlers: [],
    }
  }
  return el[rand]
}

export const extend = function ( ...args ) {
  const isDeep = typeof args[0] === 'boolean' ? args.shift() : false
  const target = args.shift()
  args.forEach((source) => {
    var curTargetIsObject = false
    var curSourceIsObject = false
    if (source === undefined || source === null) {
      // silently skip over undefined/null sources
      return // continue
    }
    if (typeof source !== 'object') {
      throw new Error('extend: object or array expected, ' + type(source) + ' given')
    }
    if (Array.isArray(target) && Array.isArray(source)) {
      // append source array values to target array
      Array.prototype.push.apply(target, source)
      // Now replace target contents with unique values only
      const uniqueValues = [...new Set(target)]
      target.length = 0
      Array.prototype.push.apply(target, uniqueValues)
      return // continue
    }
    for (const [key, value] of Object.entries(source)) {
      curSourceIsObject = typeof value === 'object' && value !== null
      curTargetIsObject = typeof target[key] === 'object' && target[key] !== null
      if (curSourceIsObject && curTargetIsObject && isDeep) {
        extend(true, target[key], value)
      } else {
        target[key] = value
      }
    }
  })
  return target
}

export const findDeepest = function (el) {
  var children = el.children
  var depth = arguments[1] || 0
  var deepestEl = [el, depth]
  for (const child of children) {
    let found = findDeepest(child, depth + 1)
    if (found[1] > deepestEl[1]) {
      deepestEl = found
    }
  }
  return depth === 0
    ? deepestEl[0]
    : deepestEl
}

/*
export const intersectObjects = function ( ...objects ) {
  var target = objects.shift()
  objects.forEach((obj) => {
    const intersectingKeys = Object.keys(target).filter(key => Object.keys(obj).includes(key))
    target = intersectingKeys.reduce((result, key) => {
      result[key] = target[key]
      return result
    }, {})
  })
  return target
}
*/

/*
function hash (str) {
  let hash = 0
  for (let i = 0, len = str.length; i < len; i++) {
      let chr = str.charCodeAt(i)
      hash = (hash << 5) - hash + chr
      hash |= 0  // Convert to 32bit integer
  }
  return hash.toString(16)  // convert to hex
}
*/

// return found elements or false
const isCssSelector = function (val)
{
  if (val.includes('<')) {
    return false
  }
  try {
    let isCssSelector = true
    const elements = customSelectors.querySelectorAll(val)
    if (elements.length === 0 && val.match(/^([a-z][\w\-]*[\s,]*)+$/i)) {
      // we didn't error, but we didn't find any elements and we have a string that looks like words
      // "hello world" is a valid selector
      const words = val.toLowerCase().split(/[\s,]+/)
      // "i" and "a" omitted
      const tags = 'div span form label input select option textarea button section nav img b p u em strong table tr td th ul ol dl li dt dd h1 h2 h3 h4 h5 h6'.split(' ')
      isCssSelector = words.filter(x => tags.includes(x)).length > 0
    }
    if (isCssSelector) {
      return elements
    }
  } catch {
    // we got an error, so val is not a valid selector
  }
  return false
}

export const isNumeric = function (val) {
  // parseFloat NaNs numeric-cast false positives ("")
  // ...but misinterprets leading-number strings, particularly hex literals ("0x...")
  // subtraction forces infinities to NaN
  var valType = type(val)
  return (valType === 'number' || valType === 'string')
    && !isNaN(val - parseFloat(val))
}

/*
function isPlainObject (val) {
  return typeof val === 'object'
    && val !== null
    && val.constructor === Object
}
*/

/*
export var modifySelector = function (selector) {
  selector = selector.replace(/(^|,)\s*>/g, '$1 :scope >').trimStart()
  selector = selector.replace(/:input/g, 'input')
  selector = selector.replace(/:(button|checkbox|file|radio|password|reset|submit|text)\b/g, '[type="$1"]')
  return selector
}
*/

export const type = function (val) {
  if (val === null || val === undefined) {
    return val + ''
  }
  if (typeof val !== 'object' && typeof val !== 'function') {
    return typeof val
  }
  if (val instanceof Element) {
    return 'element'
  }
  if (val instanceof Node) {
    return 'node'
  }
  return toString.call(val).match(/^\[object (\w+)\]$/)[1].toLowerCase()
}
