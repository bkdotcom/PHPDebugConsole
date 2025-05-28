import * as helper from '../helper.js'

export function extendMicroDom (MicroDom) {

  /**
   * @param string|Array|fn mixed space separated class names, list of class names, or function that returns either
   *
   * @return MicroDom
   */
  function addClass (mixed) {
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i)
        this.eq(i).addClass(classes)
      })
    }
    if (typeof mixed === 'object' && !Array.isArray(mixed) && mixed !== null) {
      // object (not array) of classes
      // classname => boolean
      return this.toggleClass(mixed)
    }
    const classes = typeof mixed === 'string'
      ? mixed.split(' ').filter((val) => val !== '')
      : mixed
    return this.each((el) => {
      for (const className of classes) {
        el.classList.add(className)
      }
    })
  }

  function attr ( ...args ) {
    if (typeof args[0] === 'string' && args.length === 1) {
      return this[0]?.getAttribute(args[0])
    }
    if (typeof args[0] === 'string') {
      args[0] = {[args[0]]: args[1]}
    }
    return this.each((el, i) => {
      for (const [name, value] of Object.entries(args[0])) {
        if (typeof value === 'boolean' && name.startsWith('data-') === false) {
          if (value) {
            el.setAttribute(name, name)
          } else {
            el.removeAttribute(name)
          }
        } else if (value === undefined) {
          el.removeAttribute(name)
        } else if (value === null && name.startsWith('data-') === false) {
          el.removeAttribute(name)
        } else if (name === 'class') {
          this.eq(i).removeAttr('class').addClass(value)
        } else if (name === 'html') {
          this.eq(i).html(value)
        } else if (name === 'style') {
          this.eq(i).style(value)
        } else if (name === 'text') {
          el.textContent = value
        } else {
          el.setAttribute(name, value)
        }
      }
    })
  }

  function hasClass (className) {
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

  function prop ( ...args ) {
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
      for (let [name, value] of Object.entries(args[0])) {
        if (name === 'class') {
          name = 'className'
        }
        el[name] = value
      }
    })
  }

  function removeAttr (name) {
    return this.each((el) => {
      el.removeAttribute(name)
    })
  }

  function removeClass (mixed) {
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i)
        this.eq(i).removeClass(classes)
      })
    }
    const classes = typeof mixed === 'string'
      ? mixed.split(' ').filter((val) => val !== '')
      : mixed
    return this.each((el) => {
      for (const className of classes) {
        el.classList.remove(className)
      }
    })
  }

  function toggleClass (mixed, state) {
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i)
        this.eq(i).toggleClass(classes, state)
      })
    }
    const classes = Array.isArray(mixed) || typeof mixed === 'object'
      ? mixed
      : mixed.split(' ').filter((val) => val !== '')
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

  function val (value) {
    if (typeof value !== 'undefined') {
      // set value
      return this.each((el) => {
        if (el.type === 'checkbox' || el.type === 'radio') {
          el.checked = Boolean(value)
          return
        }
        if (el.tagName === 'SELECT' && el.multiple) {
          if (Array.isArray(value) === false) {
            value = [value]
          }
          Array.from(el.options).forEach((option) => {
            option.selected = value.includes(option.value)
          })
          return
        }
        el.value = value
      })
    }
    // get value
    if (typeof this[0] === 'undefined') {
      return undefined
    }
    let el = this[0]
    if (el.options && el.multiple) {
      return Array.from(el.options)
        .filter((option) => option.selected)
        .map((option) => option.value)
    }
    return el.value
  }

  Object.assign(MicroDom.prototype, {
    addClass,
    attr,
    hasClass,
    prop,
    removeAttr,
    removeClass,
    toggleClass,
    val,
  })

}
