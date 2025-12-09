import * as customSelectors from './customSelectors.js'
import * as helper from '../helper.js'

function getArgs (args) {
  const argsObj = {
    filter: null,
    inclTextNodes: false,
  }
  for (const val of args) {
    // console.log(val)  // Access the value directly
    if (typeof val === 'boolean') {
      argsObj.inclTextNodes = val
    } else {
      argsObj.filter = val
    }
  }
  return argsObj
}

function collectWhile (el, predicate) {
  const collected = []
  while (el = predicate(el)) {
    collected.push(el)
  }
  return collected
}

/**
 * @param {*} filter
 * @param {bool} inclTextNodes (false)
 */
function children(...args) {
  args = getArgs(args)
  return args.inclTextNodes
    ? this.alter((el) => el.childNodes, args.filter)
    : this.alter((el) => el.children, args.filter)
}

function closest (selector) {
  return this.alter((el) => {
    return [9, 11].includes(el.nodeType)
      ? []
      : el.closest(selector)
  })
}

function find(mixed) {
  if (typeof mixed === 'string') {
    return this.alter((el) => customSelectors.querySelectorAll(mixed, el))
  }
  const elements = helper.argsToElements([mixed], true)
  return this.alter((el) => {
    const collected = []
    for (const el2 of elements) {
      if (el.contains(el2)) {
        collected.push(el2)
      }
    }
    return collected
  })
}

/**
 * @param {*} filter
 * @param {bool} inclTextNodes (false)
 */
function next (...args) {
  args = getArgs(args)
  return  args.inclTextNodes
    ? this.alter((el) => el.nextSibling, args.filter)
    : this.alter((el) => el.nextElementSibling, args.filter)
}

/**
 * @param {*} filter
 * @param {bool} inclTextNodes (false)
 */
function nextAll (...args) {
  args = getArgs(args)
  return this.alter((el) => {
    return collectWhile(el, (currentEl) => {
      return args.inclTextNodes
        ? currentEl.nextSibling
        : currentEl.nextElementSibling
    })
  }, args.filter)
}

function nextUntil (selector, ...args) {
  args = getArgs(args)
  return this.alter((el) => {
    return collectWhile(el, (currentEl) => {
      const sibling = args.inclTextNodes
        ? currentEl.nextSibling
        : currentEl.nextElementSibling
      return sibling && (
        sibling.nodeType !== Node.ELEMENT_NODE
        || customSelectors.matches(sibling, selector) === false
      )
        ? sibling
        : null
    })
  }, args.filter)
}

function parent (filter) {
  return this.alter((el) => el.parentNode, filter)
}

function parents (filter) {
  return this.alter((el) => {
    return collectWhile(el, (currentEl) => {
      const parent = currentEl.parentNode
      return parent && parent.nodeType !== Node.DOCUMENT_NODE
        ? parent
        : null
    })
  }, filter)
}

function parentsUntil (selector, filter) {
  return this.alter((el) => {
    return collectWhile(el, (currentEl) => {
      const parent = currentEl.parentNode
      return parent && !(currentEl instanceof HTMLHtmlElement) && customSelectors.matches(parent, selector) === false
        ? parent
        : null
    })
  }, filter)
}

/**
 * @param {*} filter
 * @param {bool} inclTextNodes (false)
 */
function prev (...args) {
  args = getArgs(args)
  return args.inclTextNodes
    ? this.alter((el) => el.previousSibling, args.filter)
    : this.alter((el) => el.previousElementSibling, args.filter)
}

/**
 * @param {*} filter
 * @param {bool} inclTextNodes (false)
 */
function prevAll (...args) {
  args = getArgs(args)
  return this.alter((el) => {
    return collectWhile(el, (currentEl) => {
      return args.inclTextNodes
        ? currentEl.previousSibling
        : currentEl.previousElementSibling
    })
  }, args.filter)
}

/**
 * @param {*} filter
 * @param {bool} inclTextNodes (false)
 */
function prevUntil (selector, ...args) {
  args = getArgs(args)
  return this.alter((el) => {
    return collectWhile(el, (currentEl) => {
      const sibling = args.inclTextNodes
        ? currentEl.previousSibling
        : currentEl.previousElementSibling
      return sibling && (
        sibling.nodeType !== Node.ELEMENT_NODE
        || customSelectors.matches(sibling, selector) === false
      )
        ? sibling
        : null
    })
  }, args.filter)
}

/**
 * @param {*} filter
 * @param {bool} inclTextNodes (false)
 */
function siblings (...args) {
  args = getArgs(args)
  return this.alter((el) => {
    const childNodes = args.inclTextNodes
      ? el.parentNode.childNodes
      : el.parentNode.children
    return Array.from(childNodes).filter((child) => child !== el)
  }, args.filter)
}

export function extendMicroDom (MicroDom) {

  Object.assign(MicroDom.prototype, {
    children,
    closest,
    find,
    next,
    nextAll,
    nextUntil,
    parent,
    parents,
    parentsUntil,
    prev,
    prevAll,
    prevUntil,
    siblings,
  })

}
