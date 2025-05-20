export var rand = "zest" + Math.random().toString().replace(/\D/g, '')

export var camelCase = function (str) {
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

export var createElements = function (html) {
  const element = document.createElement('template')
  element.innerHTML = html; // do not trim
  return element.content.childNodes;  // vs element.content.children
}

export var each = function (mixed, callback) {
  if (Array.isArray(mixed)) {
    return mixed.forEach((value, index) => callback.call(value, value, index))
  }
  for (const [key, value] of Object.entries(mixed)) {
    callback.call(value, value, key)
  }
}

export var elInitMicroDomInfo = function (el) {
  if (typeof el[rand] === 'undefined') {
    el[rand] = {
      data: {},
      // display
      eventHandlers: [],
    }
  }
  if (el !== window && typeof el[rand].display === 'undefined') {
    const displayVal = window.getComputedStyle(el).display
    if (displayVal !== 'none') {
      el[rand].display = displayVal
    }
  }
}

/*
export var elMatches = function (el, selector) {
  selector = modifySelector(selector)
  if (selector === ':visible') {
    return Boolean(el.offsetWidth || el.offsetHeight || el.getClientRects().length)
  }
  if (selector === ':hidden') {
    return !Boolean(el.offsetWidth || el.offsetHeight || el.getClientRects().length)
  }
  if (el.matches(selector)) {
    return true
  }
  return false
}
*/

export var extend = function ( ...args ) {
  const isDeep = typeof args[0] === 'boolean' ? args.shift() : false
  const target = args.shift()
  args.forEach((source) => {
    var curTargetIsObject = false
    var curSourceIsObject = false
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

export var findDeepest = function (el) {
  var children = el.children
  var depth = arguments[1] || 0
  var deepestEl = [el, depth]
  for (let i = 0; i < children.length; i++) {
    let found = findDeepest(children[i], depth + 1)
    if (found[1] > deepestEl[1]) {
      deepestEl = found
    }
  }
  return depth === 0
    ? deepestEl[0]
    : deepestEl
}

/*
function hash (str) {
  let hash = 0
  for (let i = 0, len = str.length; i < len; i++) {
      let chr = str.charCodeAt(i)
      hash = (hash << 5) - hash + chr
      hash |= 0; // Convert to 32bit integer
  }
  return hash.toString(16); // convert to hex
}
*/

export var isNumeric = function (val) {
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

export var type = function (val) {
  if (val === null || val === undefined) {
    return val + ''
  }
  const class2type = {}
  if (typeof val !== 'object' && typeof val !== 'function') {
    return typeof val
  }
  'Boolean Number String Function Array Date RegExp Object Error Symbol'
    .split(' ')
    .forEach((name) => {
      class2type[`[object ${name}]`] = name.toLowerCase()
    })
  return class2type[ toString.call(val) ] || 'object'
}
