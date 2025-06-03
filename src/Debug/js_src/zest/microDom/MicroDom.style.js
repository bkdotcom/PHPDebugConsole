import * as helper from '../helper.js'

export function extendMicroDom (MicroDom) {

  const addPx = function (value) {
    return helper.isNumeric(value)
      ? value + 'px'
      : value
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

  // "helper" methods
  // height = as defined by the CSS height propert
  // clientHeight = includes padding / excludes borders, margins, and scrollbars
  // offsetHeight = includes padding and border / excludes margins
  // getBoundingClientRect() = includes padding, border, and (for most browsers) the scrollbar's height if it's rendered

  function height (value) {
    if (typeof value === 'undefined') {
      // return "content" height excluding padding, border, and margin.

      // also works on inline elements
      const el = this[0]
      const cs = window.getComputedStyle(el)
      const paddingY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom)
      const borderY = parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth)
      return el.offsetHeight - paddingY - borderY
    }
    return this.style('height', addPx(value))
  }

  function innerHeight (value) {
    if (typeof value === 'undefined') {
      // return content height plus padding (excludes border and margin)
      return this[0]?.clientHeight
    }
    return this.style('height', addPx(value))
  }

  function innerWidth (value) {
    if (typeof value === 'undefined') {
      return this[0]?.clientWidth
    }
    return this.style('width', addPx(value))
  }

  function outerHeight (value, includeMargin = false) {
    if (typeof value === 'undefined') {
      // content height plus padding and border
      if (!includeMargin) {
        return this[0]?.offsetHeight
      }
      // content height plus padding, border & margin
      const el = this[0]
      const cs = getComputedStyle(el)
      return el.getBoundingClientRect().height
        + parseFloat(cs.marginTop)
        + parseFloat(cs.marginBottom)
    }
    return this.style('height', addPx(value))
  }

  Object.assign(MicroDom.prototype, {
    style,
    height,
    innerHeight,
    innerWidth,
    outerHeight,
  })

}
