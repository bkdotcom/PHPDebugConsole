import * as helper from '../helper.js'

export function extendMicroDom (MicroDom) {

  const durationNorm = function (duration) {
    if (duration === 'fast') {
      duration = 200
    } else if (duration === 'slow') {
      duration = 600
    }
    return duration
  }

  function animate (properties, duration = 400, easing = 'swing', complete) {
    duration = durationNorm(duration)
    const startTime = performance.now()

    return this.each((el) => {
      const propInfo = {
        // for each animated property, store the start value, end value, and unit
      }

      const easingFunctions = {
        easeIn: (p) => p * p,
        easeOut: (p) => 1 - Math.cos(p * Math.PI / 2),
        linear: (p) => p,
        swing: (p) => 0.5 - Math.cos(p * Math.PI) / 2,
      }

      const animateStep = (currentTime) => {
        const elapsedTime = currentTime - startTime
        const progress = Math.min(elapsedTime / duration, 1)
        const easingFunction = typeof easing === 'function'
          ? easing
          : easingFunctions[easing] || easingFunctions.swing
        const easedProgress = easingFunction(progress)

        for (const property in properties) {
          const startValue = propInfo[property].start
          const endValue = propInfo[property].end
          const currentValue = startValue + (endValue - startValue) * easedProgress
          el.style[property] = currentValue + (isNaN(endValue) ? '' : propInfo[property].unit)
        }

        if (progress < 1) {
          requestAnimationFrame(animateStep)
        } else if (typeof complete === 'function') {
          complete.call(el, el)
        }
      }

      // populate propInfo with start and end values
      const computedStyles = getComputedStyle(el)
      for (const property in properties) {
        const regex = /^(-=|\+=)?([\d\.]+)(\D+)?$/
        const matchesUser = properties[property].toString().match(regex) || []
        const matchesComputed = computedStyles[property].toString().match(regex) || []
        propInfo[property] = {
          end: parseFloat(properties[property]),
          start: parseFloat(computedStyles[property]) || 0,
          unit: matchesUser[3] || matchesComputed[3] || '',
        }
      }

      requestAnimationFrame(animateStep)
    })
  }

  function fadeIn (duration = 400, onComplete) {
    duration = durationNorm(duration)

    return this.each((el) => {
      el.style.transition = `opacity ${duration}ms`
      el.style.opacity = 1

      setTimeout(() => {
        el.style.display = el[helper.rand]?.display
          ? el[helper.rand].display
          : ''
        el.style.transition = ''; // Reset transition
        if (typeof onComplete === 'function') {
          onComplete.call(el, el)
        }
      }, duration)
    })
  }

  function fadeOut (duration = 400, onComplete) {
    duration = durationNorm(duration)

    return this.each((el) => {
      el.style.transition = `opacity ${duration}ms`
      el.style.opacity = 0

      setTimeout(() => {
        helper.elInitMicroDomInfo(el)
        el.style.display = 'none'
        el.style.transition = ''; // Reset transition
        if (typeof onComplete === 'function') {
          onComplete.call(el, el)
        }
      }, duration)
    })
  }

  function hide () {
    return this.each( (el) => {
      helper.elInitMicroDomInfo(el)
      el.style.display = 'none'
    })
  }

  function show () {
    return this.each((el) => {
      el.style.display = helper.elInitMicroDomInfo(el).display
    })
  }

  function slideDown (duration = 400, onComplete) {
    duration = durationNorm(duration)
    return this.each((el) => {
      el.style.transitionProperty = 'height, margin, padding'
      el.style.transitionDuration = duration + 'ms'
      el.style.boxSizing = 'border-box'
      el.style.overflow = 'hidden'
      el.style.display = helper.elInitMicroDomInfo(el).display
      el.style.height = el.scrollHeight + 'px'
      el.style.removeProperty('padding-top')
      el.style.removeProperty('padding-bottom')
      el.style.removeProperty('margin-top')
      el.style.removeProperty('margin-bottom')
      window.setTimeout( () => {
        const propsRemove = [
          'box-sizing', 'height',
          'overflow',
          'transition-duration', 'transition-property',
        ]
        propsRemove.forEach((prop) => {
          el.style.removeProperty(prop)
        })
        if (typeof onComplete === 'function') {
          onComplete.call(el, el)
        }
      }, duration)
    })
  }

  function slideUp (duration = 400, onComplete) {
    duration = durationNorm(duration)
    return this.each((el) => {
      el.style.transitionProperty = 'height, margin, padding'
      el.style.transitionDuration = duration + 'ms'
      el.style.boxSizing = 'border-box'
      el.style.overflow = 'hidden'
      el.style.height = 0
      el.style.paddingTop = 0
      el.style.paddingBottom = 0
      el.style.marginTop = 0
      el.style.marginBottom = 0
      window.setTimeout( () => {
        const propsRemove = [
          'box-sizing', 'height',
          'margin-bottom', 'margin-top',
          'overflow',
          'padding-bottom', 'padding-top',
          'transition-duration', 'transition-property',
        ]
        el.style.display = 'none'
        propsRemove.forEach((prop) => {
          el.style.removeProperty(prop)
        })
        if (typeof onComplete === 'function') {
          onComplete.call(el, el)
        }
      }, duration)
    })
  }

  function toggle ( ...args ) {
    if (typeof args[0] == 'boolean') {
      return this[args[0] ? 'show' : 'hide']()
    }
    return this.each((el) => {
      let display = 'none'
      if (el.style.display === 'none') {
        display = el[helper.rand]?.display
          ? el[helper.rand].display
          : ''
      }
      el.style.display = display
    })
  }

  Object.assign(MicroDom.prototype, {
    animate,
    fadeIn,
    fadeOut,
    hide,
    show,
    slideDown,
    slideUp,
    toggle,
  })

}
