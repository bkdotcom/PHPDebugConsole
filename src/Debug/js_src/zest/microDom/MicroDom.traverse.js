import * as customSelectors from './customSelectors.js'

export function extendMicroDom (MicroDom) {
  MicroDom.prototype.children = function (filter) {
    return this.alter((el) => el.children, filter)
  }

  MicroDom.prototype.closest = function (selector) {
    return this.alter((el) => {
      if (typeof el.closest !== 'function') {
        // console.warn('el.closest is not a function', el)
        return []
      }
      return el.closest(selector)
    })
  }

  MicroDom.prototype.find = function (mixed) {
    if (typeof mixed === 'string') {
      // const selector = helper.modifySelector(mixed)
      // return this.alter((el) => el.querySelectorAll(selector))
      return this.alter((el) => customSelectors.querySelectorAll(mixed, el))
    }
    var elements = []
    if (mixed instanceof MicroDom) {
      elements = Array.from(mixed)
    } else if (mixed instanceof Node) {
      elements = [mixed]
    }
    return this.alter((el) => {
      const collected = []
      for (let i = 0, len = elements.length; i < len; i++) {
        if (el.contains(elements[i])) {
          collected.push(elements[i])
        }
      }
      return collected
    })
  }

  MicroDom.prototype.next = function (filter) {
    return this.alter((el) => el.nextElementSibling, filter)
  }

  MicroDom.prototype.nextAll = function (filter) {
    return this.alter((el) => {
      const collected = []
      while (el = el.nextElementSibling) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  MicroDom.prototype.nextUntil = function (selector, filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.nextElementSibling) && customSelectors.matches(el, selector) === false) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  MicroDom.prototype.parent = function (filter) {
    return this.alter((el) => el.parentNode, filter)
  }

  MicroDom.prototype.parents = function (filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.parentNode) && el !== document) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  MicroDom.prototype.parentsUntil = function (selector, filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.parentNode) && el.nodeName !== 'BODY' && customSelectors.matches(el, selector) === false) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  MicroDom.prototype.prev = function (filter) {
    return this.alter((el) => el.previousElementSibling, filter)
  }

  MicroDom.prototype.prevAll = function (filter) {
    return this.alter((el) => {
      const collected = []
      while (el = el.previousElementSibling) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  MicroDom.prototype.prevUntil = function (selector, filter) {
    return this.alter((el) => {
      const collected = []
      while ((el = el.previousElementSibling) && el.matches(selector) === false) {
        collected.push(el)
      }
      return collected
    }, filter)
  }

  MicroDom.prototype.siblings = function (filter) {
    return this.alter((el) => {
      return Array.from(el.parentNode.children).filter((child) => child !== el)
    }, filter)
  }
}
