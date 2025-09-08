import * as helper from '../helper.js'

const findEventHandlerInfo = function (el, events, selector, handler) {
  return (el[helper.rand]?.eventHandlers || []).filter((handlerInfo) => {
    return handlerInfo.selector === selector
      && (events.length === 0 || events.indexOf(handlerInfo.event) > -1)
      && (!handler || handlerInfo.handler === handler)
  })
}

const createEvent = function (eventOrName) {
  const eventClasses = {
    'PointerEvent': ['click', 'dblclick', 'mousedown', 'mousemove', 'mouseout', 'mouseover', 'mouseup'],
    'SubmitEvent': ['submit'],
    'FocusEvent': ['blur', 'focus'],
    'KeyboardEvent': ['keydown', 'keypress', 'keyup'],
    'WindowEvent': ['load', 'resize', 'scroll', 'unload'],
  }
  if (eventOrName instanceof Event) {
    return eventOrName
  }
  for (const [eventClass, eventNames] of Object.entries(eventClasses)) {
    if (eventNames.includes(eventOrName)) {
      return new window[eventClass](eventOrName, {bubbles: true})
    }
  }
  return new Event(eventOrName, {bubbles: true})
}

function off ( ...args ) {
  var events = args.length ? args.shift().split(' ') : []
  var selector = typeof args[0] === 'string' ? args.shift() : null
  var handler = args.length ? args.shift() : null
  return this.each((el) => {
    const eventHandlers = findEventHandlerInfo(el, events, selector, handler)
    for (let i = 0, len = eventHandlers.length; i < len; i++) {
      el.removeEventListener(eventHandlers[i]['event'], eventHandlers[i]['wrappedHandler'])
    }
  })
}

function on ( ...args ) {
  const events = args.shift().split(' ')
  const selector = typeof args[0] === 'string' ? args.shift() : null
  const handler = args.shift()
  const captureEvents = ['mouseenter', 'mouseleave', 'pointerenter', 'pointerleave']
  return this.each((el) => {
    const wrappedHandler = (e) => {
      const target = selector
        ? (captureEvents.includes(e.type)
          ? (e.target.matches(selector) ? e.target : null)
          : e.target?.closest(selector))
        : el
      return target && (target instanceof Window || document.body.contains(target))
        ? handler.apply(target, [e, ...(e.extraParams || [])])
        : true
    }
    helper.elInitMicroDomInfo(el)
    for (let i = 0, len = events.length; i < len; i++) {
      const eventName = events[i]
      const capture = selector && captureEvents.includes(eventName)
      el[helper.rand].eventHandlers.push({
        event: eventName,
        handler: handler,
        selector: selector,
        wrappedHandler: wrappedHandler,
      })
      el.addEventListener(eventName, wrappedHandler, capture)
    }
  })
}

function one ( ...args ) {
  return this.on( ...args, true )
}

function trigger (eventName, extraParams) {
  if (typeof extraParams === 'undefined') {
    extraParams = []
  }
  return this.each((el) => {
    const event = createEvent(eventName)
    event.extraParams = Array.isArray(extraParams)
      ? extraParams
      : [extraParams]
    el.dispatchEvent(event)
  })
}

export function extendMicroDom (MicroDom) {

  Object.assign(MicroDom.prototype, {
    off,
    on,
    one,
    trigger,
  })
}
