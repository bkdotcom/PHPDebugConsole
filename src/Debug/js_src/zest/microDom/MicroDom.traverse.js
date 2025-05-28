import * as customSelectors from './customSelectors.js'
import * as helper from '../helper.js'

export function extendMicroDom (MicroDom) {

  function children(filter) {
    return this.alter((el) => el.children, filter)
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
    const elements = helper.argsToElements([mixed])
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

  function next (filter) {
    return this.alter((el) => el.nextElementSibling, filter)
  }

  function nextAll (filter) {
    return this.alter((el) => {
      const collected = []
      while (el = el.nextElementSibling) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  function nextUntil (selector, filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.nextElementSibling) && customSelectors.matches(el, selector) === false) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  function parent (filter) {
    return this.alter((el) => el.parentNode, filter)
  }

  function parents (filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.parentNode) && el !== document) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  function parentsUntil (selector, filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.parentNode) && customSelectors.matches(el, selector) === false) {
        collected.push(el)
        if (el.nodeName === 'BODY') {
          break
        }
      }
      return collected
    }, filter)
  }

  function prev (filter) {
    return this.alter((el) => el.previousElementSibling, filter)
  }

  function prevAll (filter) {
    return this.alter((el) => {
      const collected = []
      while (el = el.previousElementSibling) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  function prevUntil (selector, filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.previousElementSibling) && el.matches(selector) === false) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  function siblings (filter) {
    return this.alter((el) => {
      return Array.from(el.parentNode.children).filter((child) => child !== el)
    }, filter)
  }

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
