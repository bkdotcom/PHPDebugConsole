import * as helper from '../helper.js'

// height = as defined by the CSS height propert
// clientHeight = includes padding / excludes borders, margins, and scrollbars
// offsetHeight = includes padding and border / excludes margins
// getBoundingClientRect() = includes padding, border, and (for most browsers) the scrollbar's height if it's rendered

export function extendMicroDom (MicroDom) {

  const addPx = function (value) {
    return helper.isNumeric(value)
      ? value + 'px'
      : value
  }

  const queryHelper = function (microDom, windowProp, elementQuery) {
    if (microDom.length === 0) {
      return undefined
    }
    const el = microDom[0]
    if (helper.type(el) === 'window') {
      return el[windowProp]
    }
    return elementQuery(el)
  }

  function style ( ...args ) {
    if (args.length === 0) {
      return this[0]?.style; // return the style object
    }
    if (typeof args[0] === 'string' && args.length === 1) {
      if (args[0].trim() === '') {
        return this.removeAttr('style')
      }
      if (args[0].includes(':')) {
        // if the string contains a colon, we're setting the style attribute
        return this.each((el) => {
          el.setAttribute('style', args[0])
        })
      }
      // return the computed style on the first element for the specified property
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

  function height (value) {
    if (typeof value === 'undefined') {
      return queryHelper(this, 'innerHeight', function (el) {
        // return "content" height excluding padding, border, and margin.
        // also works on inline elements
        const cs = window.getComputedStyle(el)
        const paddingY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom)
        const borderY = parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth)
        return el.offsetHeight - paddingY - borderY
      })
    }
    return this.style('height', addPx(value))
  }

  function width (value) {
    if (typeof value === 'undefined') {
      return queryHelper(this, 'innerWidth', function (el) {
        // return "content" height excluding padding, border, and margin.
        // also works on inline elements
        const cs = window.getComputedStyle(el)
        const paddingX = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight)
        const borderX = parseFloat(cs.borderLeftWidth) + parseFloat(cs.borderRightWidth)
        return el.offsetWidth - paddingX - borderX
      })
    }
    return this.style('width', addPx(value))
  }

  function innerHeight (value) {
    if (typeof value === 'undefined') {
      return queryHelper(this, 'innerHeight', function (el) {
        // return content height plus padding (excludes border and margin)
        return el.clientHeight
      })
    }
    return this.style('height', addPx(value))
  }

  function innerWidth (value) {
    if (typeof value === 'undefined') {
      return queryHelper(this, 'innerWidth', function (el) {
        // return content width plus padding (excludes border and margin)
        return el.clientWidth
      })
    }
    return this.style('width', addPx(value))
  }

  function outerHeight (value, includeMargin = false) {
    if (typeof value === 'undefined') {
      return queryHelper(this, 'innerHeight', function (el) {
        if (!includeMargin) {
          // content height plus padding and border
          return el.offsetHeight
        }
        // content height plus padding, border & margin
        const cs = getComputedStyle(el)
        return el.getBoundingClientRect().height
          + parseFloat(cs.marginTop)
          + parseFloat(cs.marginBottom)
      })
    }
    return this.style('height', addPx(value))
  }

  Object.assign(MicroDom.prototype, {
    style,
    height,
    width,
    innerHeight,
    innerWidth,
    outerHeight,
  })

}
