import * as customSelectors from './customSelectors.js'
import * as helper from '../helper.js'

export function extendMicroDom (MicroDom) {

  MicroDom.prototype.eq = function (index) {
    if (index < 0) {
      index = this.length + index
    }
    if (index < 0 || index >= this.length) {
      return new MicroDom()
    }
    return new MicroDom(this[index])
  }

  MicroDom.prototype.filter = function (mixed, notFilter = false) {
    return this.alter((el, i) => {
      let isMatch = false
      if (typeof mixed === 'string') {
        isMatch = customSelectors.matches(el, mixed)
      } else if (typeof mixed === 'function') {
        isMatch = mixed.call(el, el, i)
      } else {
        isMatch = helper.argsToElements([mixed]).includes(el)
      }
      if (notFilter) {
        isMatch = !isMatch
      }
      return isMatch
        ? el
        : []
    })
  }

  MicroDom.prototype.first = function () {
    return new MicroDom( this[0] )
  }

  /**
   * Return true if at least one of our elements matches the given arguments
   * @param {*} mixed
   * @return bool
   */
  MicroDom.prototype.is = function (mixed) {
    if (typeof mixed === 'string') {
      for (let el of this) {
        if (customSelectors.matches(el, mixed)) {
          return true
        }
      }
      return false
    }
    if (typeof mixed === 'function') {
      for (let i = 0, len = this.length; i < len; i++) {
        if (mixed.call(this[i], this[i], i)) {
          return true
        }
      }
      return false
    }
    const elements = helper.argsToElements([mixed])
    for (let el of this) {
      for (let i = 0, len = elements.length; i < len; i++) {
        if (elements[i] === el) {
          return true
        }
      }
    }
    return false
  }

  MicroDom.prototype.last = function () {
    return new MicroDom(this[this.length - 1])
  }

  MicroDom.prototype.not = function (filter) {
    return this.filter(filter, true)
  }
}
