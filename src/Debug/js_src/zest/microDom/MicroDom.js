import * as customSelectors from './customSelectors.js'
import * as helper from '../helper.js' // cookie & query utils
import * as events from './MicroDom.events.js'
import * as filter from './MicroDom.filter.js'
import * as traverse from './MicroDom.traverse.js'
import * as vis from './MicroDom.vis.js'

const argsToElements = function (args, el, index) {
  const elements = []
  while (args.length) {
    const arg = args.shift()
    if (typeof arg === 'string') {
      // we assume this is HTML
      const elementsAppend = helper.createElements(arg)
      for (let j = 0, jlen = elementsAppend.length; j < jlen; j++) {
        elements.push(elementsAppend[j])
      }
    } else if (typeof args === 'function') {
      args.unshift(arg.call(el, el, index))
    } else if (arg instanceof MicroDom) {
      for (let j = 0, jlen = arg.length; j < jlen; j++) {
        elements.push(arg[j])
      }
    } else if (arg instanceof Node) {
      elements.push(arg)
    } else if (Array.isArray(arg)) {
      args.unshift(...arg)
    }
  }
  return elements
}

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
    var elementsNew = argsToElements([mixed])
    for (let i = 0, len = elementsNew.length; i < len; i++) {
      elements.push(elementsNew[i])
    }
    elements = [...new Set(elements) ]; // remove duplicates
    return new MicroDom( ...elements )
  }
  /**
   * @param string|Array|fn mixed space separated class names, list of class names, or function that returns either
   *
   * @return MicroDom
   */
  addClass(mixed) {
    var classes = []
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i)
        this.eq(i).addClass(classes)
      })
    }
    if (typeof mixed === 'string') {
      classes = mixed.split(' ').filter((val) => val !== '')
    } else if (Array.isArray(mixed)) {
      classes = mixed
    } else if (typeof mixed === 'object') {
      return this.toggleClass(mixed)
    }
    return this.each((el) => {
      for (let i = 0, len = classes.length; i < len; i++) {
        el.classList.add(classes[i])
      }
    })
  }
  after( ...args ) {
    return this.each((el, i) => {
      const elements = argsToElements(args, el, i)
      for (let j = elements.length - 1; j >= 0; j--) {
        el.after(elements[j])
      }
    })
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
    elementsNew = [...new Set(elementsNew) ]; // remove duplicates
    const ret = new MicroDom( ...elementsNew )
    return filter
      ? ret.filter(filter)
      : ret
  }
  append( ...args ) {
    return this.each((el, i) => {
      const elements = argsToElements(args, el, i)
      for (let j = 0, len = elements.length; j < len; j++) {
        el.append(elements[j])
      }
    })
  }
  attr( ...args ) {
    if (typeof args[0] === 'string' && args.length === 1) {
      return this[0]?.getAttribute(args[0])
    }
    if (typeof args[0] === 'string') {
      args[0] = {[args[0]]: args[1]}
    }
    return this.each((el, i) => {
      for (const [name, value] of Object.entries(args[0])) {
        if ([null, undefined].includes(value)) {
          el.removeAttribute(name)
        } else if (name === 'class') {
          this.eq(i).addClass(value)
        } else if (name === 'html') {
          this.eq(i).html(value)
        } else if (name === 'style') {
          this.eq(i).style(value)
        } else if (name === 'text') {
          el.textContent = value
        } else if (typeof value === 'boolean' && name.startsWith('data-') === false) {
          if (value) {
            el.setAttribute(name, name)
          } else {
            el.removeAttribute(name)
          }
        } else {
          el.setAttribute(name, value)
        }
      }
    })
  }
  before( ...args ) {
    return this.each((el, i) => {
      const elements = argsToElements(args, el, i)
      for (let j = 0, len = elements.length; j < len; j++) {
        el.before(elements[j])
      }
    })
  }
  clone() {
    return this.alter((el) => el.cloneNode(true))
  }
  data(name, value) {
    if (typeof name === 'undefined') {
      // return all data
      if (typeof this[0] === 'undefined') {
        return {}
      }
      const data = {}
      const nonSerializable = this[0][helper.rand]?.data || {}
      for (const key in this[0].dataset) {
        value = this[0].dataset[key]
        try {
          value = JSON.parse(value)
        } catch (e) {
          // do nothing
        }
        data[key] = value
      }
      return helper.extend(data, nonSerializable)
    }
    if (typeof name !== 'object' && typeof value !== 'undefined') {
      // we're setting a single value -> convert to object
      name = {[name]: value}
    }
    if (typeof name === 'object') {
      // setting value(s)
      return this.each((el) => {
        for (let [key, value] of Object.entries(name)) {
          var isStringable = true
          if (typeof value === 'function') {
            isStringable = false
          } else if (typeof value === 'object' && value !== null) {
            isStringable = value === JSON.parse(JSON.stringify(value))
          }
          key = helper.camelCase(key)
          if (isStringable === false) {
            // store in non-serializable value in special element property
            helper.elInitMicroDomInfo(el)
            el[helper.rand].data[key] = value
            continue
          }
          el.dataset[key] = typeof value === 'string'
            ? value
            : JSON.stringify(value)
        }
      })
    }
    // return value
    name = helper.camelCase(name)
    if (typeof this[0] === 'undefined') {
      return undefined
    }
    value = this[0][helper.rand]?.data?.[name]
      ? this[0][helper.rand].data[name]
      : this[0].dataset[name]
    try {
      return JSON.parse(value)
    } catch (e) {
      // do nothing
    }
    return value
  }
  each(callback) {
    for (let i = 0, len = this.length; i < len; i++) {
      callback.call(this[i], this[i], i)
    }
    return this
  }
  empty() {
    return this.each((el) => {
      el.replaceChildren()
    })
  }
  hasClass(className) {
    let classes = typeof className === 'string'
      ? className.split(' ')
      : className
    for (let el of this) {
      let classesMatch = classes.filter((className) => el.classList.contains(className))
      if (classesMatch.length === classes.length) {
        return true
      }
    }
    return false
  }
  height(value) {
    if (typeof value === 'undefined') {
      // return this[0]?.clientHeight = inner height, including padding but excluding borders and margins
      // return this[0]?.getBoundingClientRect().height
      return this[0]?.offsetHeight // total height, including padding and borders
    }
    return this.each((el) => {
      el.style.height = value + 'px'
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
      if (mixed instanceof MicroDom) {
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
      const elements = argsToElements([mixed])
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
  innerHeight(value) {
    if (typeof value === 'undefined') {
      return this[0]?.clientHeight
    }
    return this.each((el) => {
      el.style.height = value
    })
  }
  innerWidth(value) {
    if (typeof value === 'undefined') {
      return this[0]?.clientWidth
    }
    return this.each((el) => {
      el.style.width = value
    })
  }
  outerHeight(value) {
    if (typeof value === 'undefined') {
      return this[0]?.offsetHeight
    }
    return this.each((el) => {
      el.style.height = value + 'px'
    })
  }
  prepend( ...args ) {
    return this.each((el, i) => {
      const elements = argsToElements(args, el, i)
      for (let j = 0, len = elements.length; j < len; j++) {
        el.prepend(elements[j])
      }
    })
  }
  prop( ...args ) {
    if (typeof args[0] === 'string' && args.length === 1) {
      let name = args[0]
      if (name === 'class') {
        name = 'className'
      }
      return this[0]?.[name]
    }
    // set one or more properties
    if (typeof args[0] === 'string') {
      args[0] = {[args[0]]: args[1]}
    }
    return this.each((el) => {
      for (const [name, value] of Object.entries(args[0])) {
        el[name] = value
      }
    })
  }
  remove(selector) {
    if (typeof selector === 'string') {
      this.find(selector).remove()
      return this
    }
    return this.each((el) => {
      el.remove()
    })
  }
  removeAttr(name) {
    return this.each((el) => {
      el.removeAttribute(name)
    })
  }
  removeClass(mixed) {
    var classes = []
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i)
        $(el).removeClass(classes)
      })
    }
    if (typeof mixed === 'string') {
      classes = mixed.split(' ')
    } else if (Array.isArray(mixed)) {
      classes = mixed
    }
    return this.each((el) => {
      for (let i = 0, len = classes.length; i < len; i++) {
        el.classList.remove(classes[i])
      }
    })
  }
  removeData(mixed) {
    return this.each((el) => {
      if (typeof mixed === 'string') {
        mixed = [mixed]
      }
      for (let i = 0, len = mixed.length; i < len; i++) {
        const name = helper.camelCase(mixed[i])
        if (el[helper.rand]) {
          delete el[helper.rand].data[name]
        }
        delete el.dataset[name]
      }
    })
  }
  replaceWith( ...args ) {
    return this.each((el, i) => {
      const elements = argsToElements(args, el, i)
      for (let j = 0, len = elements.length; j < len; j++) {
        el.replaceWith(elements[j])
      }
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
  style( ...args ) {
    if (args.length === 0) {
      return this[0]?.style; // return the style object
    }
    if (typeof args[0] === 'string' && args.length === 1) {
      if (args[0].includes(':')) {
        // if the property contains a colon, we're setting the style attribute
        return this.each((el) => {
          el.setAttribute('style', args[0])
        })
      }
      return typeof this[0] !== 'undefined'
        ? getComputedStyle(this[0])[args[0]]
        : undefined
    }
    if (typeof args[0] === 'string') {
      args[0] = {[args[0]]: args[1]}
    }
    return this.each((el) => {
      for (const [name, value] of Object.entries(args[0])) {
        el.style[name] = value
      }
    })
  }
  text(text) {
    if (typeof text === 'undefined') {
      return this.length > 0
        ? this[0].textContent
        : ''; // return empty string vs undefined
    }
    return this.each((el) => {
      el.textContent = text
    })
  }
  toggleClass(mixed, state) {
    var classes = []
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i)
        $(el).toggleClass(classes, state)
      })
    }
    if (typeof mixed === 'string') {
      classes = mixed.split(' ')
    } else if (Array.isArray(mixed) || typeof mixed === 'object') {
      classes = mixed
    }
    return this.each((el) => {
      helper.each(classes, (value, key) => {
        var className = value
        var classState = typeof state !== 'undefined'
          ? state
          : el.classList.contains(className) === false
        if (typeof value === 'boolean' && typeof key === 'string') {
          className = key
          classState = value
        }
        classState
          ? el.classList.add(className)
          : el.classList.remove(className)
      })
    })
  }
  val(value) {
    if (typeof value === 'undefined') {
      if (typeof this[0] === 'undefined') {
        return undefined
      }
      let el = this[0]
      if (el.options && el.multiple) {
        return el.options
          .filter((option) => option.selected)
          .map((option) => option.value)
      } else {
        return el.value
      }
    }
    return this.each((el) => {
      el.value = value
    })
  }
  wrap(mixed) {
    return this.each((el, i) => {
      if (typeof mixed === 'function') {
        mixed = mixed.call(el, el, i)
      }
      // mixed can be a string, MicroDom instance, or a DOM element
      const elements = argsToElements([mixed])
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
      // mixed can be a string, MicroDom instance, or a DOM element
      const elements = argsToElements([mixed])
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

events.extendMicroDom(MicroDom)
filter.extendMicroDom(MicroDom)
traverse.extendMicroDom(MicroDom)
vis.extendMicroDom(MicroDom)
