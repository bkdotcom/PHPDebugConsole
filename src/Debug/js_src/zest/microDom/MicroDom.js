import * as customSelectors from './customSelectors.js'
import * as helper from '../helper.js'

import * as attrPropStyle from './MicroDom.attrClassProp.js'
import * as data from './MicroDom.data.js'
import * as events from './MicroDom.events.js'
import * as filter from './MicroDom.filter.js'
import * as style from './MicroDom.style.js'
import * as traverse from './MicroDom.traverse.js'
import * as vis from './MicroDom.vis.js'

/**
 * MicroDom - a fluent collection of DOM elements
 */
export default class MicroDom extends Array {

  constructor( ...elements ) {
    super()
    elements = elements.filter((el) => {
      return el !== null && el !== undefined
    })
    elements.forEach((el, key) => {
      this[key] = el
    })
    this.length = elements.length
  }

  add(mixed) {
    var elements = Array.from(this)
    var elementsNew = helper.argsToElements([mixed])
    for (const elNew of elementsNew) {
      elements.push(elNew)
    }
    elements = [ ...new Set(elements) ]  // remove duplicates
    return new MicroDom( ...elements )
  }
  /**
   * jQuery's `map()` method
   *
   * callback should return an array of new elements (or null, or single element)
   * returns a new MicroDom instance
   *
   * used by most traversal methods:
   * prev(), prevAll(), next(), siblings(), parent(), parents(), parentsUntil(), children(), closest(), etc
   */
  alter(callback, filter) {
    var elementsNew = []
    this.each((el, i) => {
      let ret = callback.call(el, el, i)
      if (ret === null) {
        return
      }
      ret = ret instanceof Node
        ? [ret]
        : Array.from(ret)
      elementsNew = elementsNew.concat(ret)
    })
    elementsNew = [ ...new Set(elementsNew) ]  // remove duplicates
    const ret = new MicroDom( ...elementsNew )
    return filter
      ? ret.filter(filter)
      : ret
  }
  clone() {
    return this.alter((el) => el.cloneNode(true))
  }
  each(callback) {
    for (let i = 0, len = this.length; i < len; i++) {
      callback.call(this[i], this[i], i)
    }
    return this
  }

  after( ...args ) {
    return this.each((el, i) => {
      const elementsNew = helper.argsToElements(args, false, el, i)
      elementsNew.reverse()
      for (const elNew of elementsNew) {
        el.after(elNew)
      }
    })
  }
  append( ...args ) {
    return this.each((el, i) => {
      const elementsNew = helper.argsToElements(args, false, el, i)
      for (const elNew of elementsNew) {
        el.append(elNew)
      }
    })
  }
  before( ...args ) {
    return this.each((el, i) => {
      const elementsNew = helper.argsToElements(args, false, el, i)
      for (const elNew of elementsNew) {
        el.before(elNew)
      }
    })
  }
  empty() {
    return this.each((el) => {
      el.replaceChildren()
    })
  }
  html(mixed) {
    if (typeof mixed === 'undefined') {
      return this[0]?.innerHTML
    }
    return this.each((el, i) => {
      const oldHtml = el.innerHTML
      el.replaceChildren() // empty
      if (typeof mixed === 'function') {
        mixed = mixed.call(el, el, i, oldHtml)
      }
      if (mixed instanceof MicroDom || mixed instanceof NodeList) {
        el.replaceChildren(...Array.from(mixed))
      } else if (mixed instanceof Node) {
        el.replaceChildren(mixed)
      } else {
        el.innerHTML = mixed
      }
    })
  }
  index(mixed) {
    if (typeof mixed === 'undefined') {
      // return position of the first element within our collection
      // relative to its sibling elements
      return this.length > 0
        ? [...this[0].parentNode.children ].indexOf(this[0])
        : -1
    }
    if (mixed instanceof MicroDom || mixed instanceof NodeList) {
      // return the index of the passed element within collection.
      const elements = helper.argsToElements([mixed])
      return elements.length > 0
        ? this.indexOf(elements[0])
        : -1
    }
    if (typeof mixed === 'string') {
      // return the index of the first element that matches the selector
      for (let i = 0, len = this.length; i < len; i++) {
        if (customSelectors.matches(this[i], mixed)) {
          return i
        }
      }
    }
    return -1
  }
  prepend( ...args ) {
    return this.each((el, i) => {
      const elementsNew = helper.argsToElements(args, false, el, i)
      elementsNew.reverse()
      for (const elNew of elementsNew) {
        el.prepend(elNew)
      }
    })
  }
  remove(filter) {
    if (typeof filter !== 'undefined') {
      const $remove = this.filter(filter)
      const remove = Array.from($remove)
      $remove.remove()
      return this.filter(el => !remove.includes(el))
    }
    this.each((el) => el.remove())
    return new MicroDom()
  }
  replaceWith( ...args ) {
    return this.each((el, i) => {
      const elementsNew = helper.argsToElements(args, false, el, i)
      el.replaceWith(...elementsNew)
    })
  }
  scrollTop(value) {
    var win
    var el
    for (let i = 0, len = this.length; i < len; i++) {
      el = this[i]
      win = null

      if (el.window === el) {
        win = el
      } else if (el.nodeType === 9) {
        // document_node
        win = el.defaultView
      }

      if (value === undefined) {
        // return scrollTop for first matching element
        return win ? win.pageYOffset : el.scrollTop
      }

      if (win) {
        win.scrollTo(win.pageXOffset, value)
      } else {
        el.scrollTop = value
      }
    }
    return this
  }
  serialize() {
    if (this.length === 0) {
      return ''
    }
    const searchParams = new URLSearchParams(new FormData(this[0]))
    return searchParams.toString()
  }
  text(text) {
    if (typeof text === 'undefined') {
      return this.length > 0
        ? this[0].textContent
        : ''  // return empty string vs undefined
    }
    return this.each((el) => {
      el.textContent = text
    })
  }
  wrap(mixed) {
    return this.each((el, i) => {
      if (typeof mixed === 'function') {
        mixed = mixed.call(el, el, i)
      }
      // mixed can be a string (but not css selector), MicroDom instance, or a DOM element
      const elements = helper.argsToElements([mixed], false)
      const wrapperElement = elements[0].cloneNode(true)
      const wrapperElementDeepest = helper.findDeepest(wrapperElement)
      el.replaceWith(wrapperElement)
      wrapperElementDeepest.appendChild(el)
    })
  }
  wrapInner(mixed) {
    return this.each((el, i) => {
      if (typeof mixed === 'function') {
        mixed = mixed.call(el, el, i)
      }
      // mixed can be a string (but not css selector), MicroDom instance, or a DOM element
      const elements = helper.argsToElements([mixed], false)
      const wrapperElement = elements[0].cloneNode(true)
      const wrapperElementDeepest = helper.findDeepest(wrapperElement)
      // Move the element's children (incl text/comment nodes) into the wrapper
      while (el.firstChild) {
        wrapperElementDeepest.appendChild(el.firstChild)
      }
      // Append the wrapper to the element
      el.appendChild(wrapperElement)
    })
  }

}

attrPropStyle.extendMicroDom(MicroDom)
data.extendMicroDom(MicroDom)
events.extendMicroDom(MicroDom)
filter.extendMicroDom(MicroDom)
style.extendMicroDom(MicroDom)
traverse.extendMicroDom(MicroDom)
vis.extendMicroDom(MicroDom)
