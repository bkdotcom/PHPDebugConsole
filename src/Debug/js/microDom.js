var microDom = (function () {
  'use strict';

  /**
   * microDom - a jquery replacement
   *
   * key differences:
   *    each() callback receives value as first argument
   *    filter() callback receives value as first argument
   */

  // used by append, prepend, before, after, replaceWith
  const argsToElements = function (args, el, index) {
    const elements = [];
    while (args.length) {
      const arg = args.shift();
      if (typeof arg === 'string') {
        const elementsAppend = createElements(arg);
        for (let j = 0, jlen = elementsAppend.length; j < jlen; j++) {
          elements.push(elementsAppend[j]);
        }
      } else if (typeof args === 'function') {
        args.unshift(arg.call(el, el, index));
      } else if (arg instanceof Micro) {
        for (let j = 0, jlen = arg.length; j < jlen; j++) {
          elements.push(arg[j]);
        }
      } else if (arg instanceof Node) {
        elements.push(arg);
      }
    }
    return elements
  };

  const createElements = function (html) {
    const element = document.createElement('template');
    element.innerHTML = html; // do not trim
    return element.content.childNodes;  // vs element.content.children
  };

  const durationNorm = function (duration) {
    if (duration === 'fast') {
      duration = 200;
    } else if (duration === 'slow') {
      duration = 600;
    }
    return duration
  };

  const elInitMicro = function (el) {
    if (typeof el[MicroDom.rand] === 'undefined') {
      el[MicroDom.rand] = {
        data: {},
        // display
        eventHandlers: [],
      };
    }
    if (el !== window && typeof el[MicroDom.rand].display === 'undefined') {
      const displayVal = window.getComputedStyle(el).display;
      if (displayVal !== 'none') {
        el[MicroDom.rand].display = displayVal;
      }
    }
  };

  const elMatches = function (el, selector) {
    selector = modifySelector(selector);
    if (selector === ':visible') {
      return Boolean(el.offsetWidth || el.offsetHeight || el.getClientRects().length)
    }
    if (selector === ':hidden') {
      return !Boolean(el.offsetWidth || el.offsetHeight || el.getClientRects().length)
    }
    if (el.matches(selector)) {
      return true
    }
    return false
  };

  const findDeepest = function (el) {
    var children = el.children;
    var depth = arguments[1] || 0;
    var deepestEl = [el, depth];
    for (let i = 0; i < children.length; i++) {
      let found = findDeepest(children[i], depth + 1);
      if (found[1] > deepestEl[1]) {
        deepestEl = found;
      }
    }
    return depth === 0
      ? deepestEl[0]
      : deepestEl
  };

  const findEventHandlerInfo = function (el, events, selector, handler) {
    return (el[MicroDom.rand]?.eventHandlers || []).filter((handlerInfo) => {
      return handlerInfo.selector === selector
        && (events.length === 0 || events.indexOf(handlerInfo.event) > -1)
        && (!handler || handlerInfo.handler === handler)
    })
  };

  /*
  function isPlainObject (val) {
    return typeof val === 'object'
      && val !== null
      && val.constructor === Object
  }
  */

  const modifySelector = function (selector) {
    selector = selector.replace(/(^|,)\s*>/g, '$1 :scope >').trimStart();
    selector = selector.replace(/:input/g, 'input');
    selector = selector.replace(/:(button|checkbox|file|radio|password|reset|submit|text)\b/g, '[type="$1"]');
    return selector
  };

  /**
   * Micro is our fluent collection of DOM elements
   */
  class Micro extends Array {

    constructor( ...elements ) {
      super();
      elements = elements.filter((el) => {
        return el !== null && el !== undefined
      });
      elements.forEach((el, key) => {
        this[key] = el;
      });
      this.length = elements.length;
    }

    add(mixed) {
      var elements = [];
      for (let i = 0, len = this.length; i < len; i++) {
        elements.push(this[i]);
      }
      mixed = MicroDom(mixed);
      for (let i = 0, len = mixed.length; i < len; i++) {
        elements.push(mixed[i]);
      }
      elements = [...new Set(elements) ]; // remove duplicates
      return new Micro( ...elements )
    }
    /**
     * @param string|Array|fn mixed space separated class names, list of class names, or function that returns either
     *
     * @return Micro
     */
    addClass(mixed) {
      var classes = [];
      if (typeof mixed === 'function') {
        return this.each((el, i) => {
          const classes = mixed.call(el, el, i);
          this.eq(i).addClass(classes);
        })
      }
      if (typeof mixed === 'string') {
        classes = mixed.split(' ').filter((val) => val !== '');
      } else if (Array.isArray(mixed)) {
        classes = mixed;
      } else if (typeof mixed === 'object') {
        return this.toggleClass(mixed)
      }
      return this.each((el) => {
        for (let i = 0, len = classes.length; i < len; i++) {
          el.classList.add(classes[i]);
        }
      })
    }
    after( ...args ) {
      return this.each((el, i) => {
        const elements = argsToElements(args, el, i);
        for (let j = elements.length - 1; j >= 0; j--) {
          el.after(elements[j]);
        }
      })
    }
    /**
     * jQuery's `map()` method
     *
     * callback should return an array of new elements (or null, or single element)
     * returns a new Micro instance
     *
     * used by most traversal methods:
     * prev(), prevAll(), next(), siblings(), parent(), parents(), parentsUntil(), children(), closest(), etc
     */
    alter(callback, filter) {
      var elementsNew = [];
      this.each((el, i) => {
        let ret = callback.call(el, el, i);
        if (ret === null) {
          return
        }
        ret = ret instanceof Node
          ? [ret]
          : Array.from(ret);
        elementsNew = elementsNew.concat(ret);
      });
      elementsNew = [...new Set(elementsNew) ]; // remove duplicates
      const ret = new Micro( ...elementsNew );
      return filter
        ? ret.filter(filter)
        : ret
    }
    animate(properties, duration = 400, easing = 'swing', complete) {
      duration = durationNorm(duration);
      const startTime = performance.now();

      return this.each((el) => {
        const propInfo = {
          // for each animated property, store the start value, end value, and unit
        };

        const easingFunctions = {
          easeIn: (p) => p * p,
          easeOut: (p) => 1 - Math.cos(p * Math.PI / 2),
          linear: (p) => p,
          swing: (p) => 0.5 - Math.cos(p * Math.PI) / 2,
        };

        const animateStep = (currentTime) => {
          const elapsedTime = currentTime - startTime;
          const progress = Math.min(elapsedTime / duration, 1);
          const easingFunction = typeof easing === 'function'
            ? easing
            : easingFunctions[easing] || easingFunctions.swing;
          const easedProgress = easingFunction(progress);

          for (const property in properties) {
            const startValue = propInfo[property].start;
            const endValue = propInfo[property].end;
            const currentValue = startValue + (endValue - startValue) * easedProgress;
            el.style[property] = currentValue + (isNaN(endValue) ? '' : propInfo[property].unit);
          }

          if (progress < 1) {
            requestAnimationFrame(animateStep);
          } else if (typeof complete === 'function') {
            complete.call(el, el);
          }
        };

        // populate propInfo with start and end values
        const computedStyles = getComputedStyle(el);
        for (const property in properties) {
          const regex = /^(-=|\+=)?([\d\.]+)(\D+)?$/;
          const matchesUser = properties[property].match(regex);
          const matchesComputed = computedStyles[property].match(regex);
          propInfo[property] = {
            end: parseFloat(properties[property]),
            start: parseFloat(computedStyles[property]) || 0,
            unit: matchesUser[3] || matchesComputed[3] || '',
          };
        }

        requestAnimationFrame(animateStep);
      })
    }
    append( ...args ) {
      return this.each((el, i) => {
        const elements = argsToElements(args, el, i);
        for (let j = 0, len = elements.length; j < len; j++) {
          el.append(elements[j]);
        }
      })
    }
    attr( ...args ) {
      if (typeof args[0] === 'string' && typeof args[1] === 'undefined') {
        return this[0]?.getAttribute(args[0])
      }
      if (typeof args[0] === 'string') {
        args[0] = {[args[0]]: args[1]};
      }
      return this.each((el, i) => {
        for (const [name, value] of Object.entries(args[0])) {
          if (name === 'class') {
            this.eq(i).addClass(value);
          } else if (name === 'html') {
            el.innerHTML = value;
          } else if (name === 'style') {
            this.eq(i).style(value);
          } else if (name === 'text') {
            el.textContent = value;
          } else {
            value !== null
              ? el.setAttribute(name, value)
              : el.removeAttribute(name);
          }
        }
      })
    }
    before( ...args ) {
      return this.each((el, i) => {
        const elements = argsToElements(args, el, i);
        for (let j = 0, len = elements.length; j < len; j++) {
          el.before(elements[j]);
        }
      })
    }
    children(filter) {
      return this.alter((el) => el.children, filter)
    }
    closest(selector) {
      return this.alter((el) => el.closest(selector))
    }
    data(name, value) {
      if (typeof name === 'undefined') {
        // return all data
        if (typeof this[0] === 'undefined') {
          return {}
        }
        const data = {};
        const nonSerializable = this[0][MicroDom.rand]?.data || {};
        for (const key in this[0].dataset) {
          value = this[0].dataset[key];
          try {
            value = JSON.parse(value);
          } catch (e) {
            // do nothing
          }
          data[key] = value;
        }
        return MicroDom.extend(data, nonSerializable)
      }
      if (typeof name !== 'object' && typeof value !== 'undefined') {
        // we're setting a single value -> convert to object
        name = {[name]: value};
      }
      if (typeof name === 'object') {
        // setting value(s)
        return this.each((el) => {
          for (let [key, value] of Object.entries(name)) {
            key = MicroDom.camelCase(key);
            if ((typeof value === 'object' && value !== null) || typeof value === 'function') {
              let isStringable = value === JSON.parse(JSON.stringify(value));
              if (isStringable === false) {
                elInitMicro(el);
                this[0][MicroDom.rand].data[key] = value;
                return
              }
            }
            el.dataset[key] = typeof value === 'string'
              ? value
              : JSON.stringify(value);
          }
        })
      }
      // return value
      name = MicroDom.camelCase(name);
      if (typeof this[0] === 'undefined') {
        return undefined
      }
      value = this[0][MicroDom.rand]?.data?.[name]
        ? this[0][MicroDom.rand].data[name]
        : this[0].dataset[name];
      try {
        value = JSON.parse(value);
      } catch (e) {
        // do nothing
      }
      return value
    }
    each(callback) {
      for (let i = 0, len = this.length; i < len; i++) {
        callback.call(this[i], this[i], i);
      }
      return this
    }
    eq(index) {
      if (index < 0) {
        index = this.length + index;
      }
      if (index < 0 || index >= this.length) {
        return new Micro()
      }
      return new Micro(this[index])
    }
    fadeIn(duration = 400, onComplete) {
      duration = durationNorm(duration);

      return this.each((el) => {
        el.style.transition = `opacity ${duration}ms`;
        el.style.opacity = 1;

        setTimeout(() => {
          el.style.display = el[MicroDom.rand]?.display
            ? el[MicroDom.rand].display
            : '';
          el.style.transition = ''; // Reset transition
          if (typeof onComplete === 'function') {
            onComplete.call(el, el);
          }
        }, duration);

      })
    }
    fadeOut(duration = 400, onComplete) {
      duration = durationNorm(duration);

      return this.each((el) => {
        el.style.transition = `opacity ${duration}ms`;
        el.style.opacity = 0;

        setTimeout(() => {
          elInitMicro(el);
          el.style.display = 'none';
          el.style.transition = ''; // Reset transition
          if (typeof onComplete === 'function') {
            onComplete.call(el, el);
          }
        }, duration);

      })
    }
    // override the default filter method
    filter(mixed, notFilter = false) {
      return this.alter((el, i) => {
        let isMatch = false;
        if (typeof mixed === 'string') {
          isMatch = elMatches(el, mixed);
        } else if (typeof mixed === 'function') {
          isMatch = mixed.call(el, el, i);
        } else {
          var elements = [];
          if (mixed instanceof Micro) {
            elements = Array.from(mixed);
          } else if (mixed instanceof Node) {
            elements = [mixed];
          }
          isMatch = elements.includes(el);
        }
        if (notFilter) {
          isMatch = !isMatch;
        }
        return isMatch
          ? el
          : []
      })
    }
    find(selector) {
      if (typeof selector === 'string') {
        selector = modifySelector(selector);
        return this.alter((el) => el.querySelectorAll(selector))
      }
      var elements = [];
      if (selector instanceof Micro) {
        elements = Array.from(selector);
      } else if (selector instanceof Node) {
        elements = [selector];
      }
      return this.alter((el) => {
        const collected = [];
        for (let i = 0, len = elements.length; i < len; i++) {
          if (el.contains(elements[i])) {
            collected.push(elements[i]);
          }
        }
        return collected
      })
    }
    first() {
      return new Micro( this[0] )
    }
    hasClass(className) {
      let classes = typeof className === 'string'
        ? className.split(' ')
        : className;
      for (let el of this) {
        let classesMatch = classes.filter((className) => el.classList.contains(className));
        if (classesMatch.length === classes.length) {
          return true
        }
      }
      return false
    }
    height(value) {
      if (typeof value === 'undefined') {
        // return this[0]?.clientHeight
        // return this[0]?.getBoundingClientRect().height
        return this[0]?.offsetHeight
      }
      return this.each((el) => {
        el.style.height = value + 'px';
      })
    }
    hide() {
      return this.each( (el) => {
        elInitMicro(el);
        el.style.display = 'none';
      })
    }
    html(html) {
      if (typeof html === 'undefined') {
        return this[0]?.innerHTML
      }
      return this.each((el) => {
        el.innerHTML = html;
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
      if (mixed instanceof Micro || mixed instanceof NodeList) {
        // return the index of the passed element within collection.
        const elements = argsToElements([mixed]);
        return elements.length > 0
          ? this.indexOf(elements[0])
          : -1
      }
      if (typeof mixed === 'string') {
        // return the index of the first element that matches the selector
        for (let i = 0, len = this.length; i < len; i++) {
          if (elMatches(this[i], mixed)) {
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
        el.style.height = value;
      })
    }
    innerWidth(value) {
      if (typeof value === 'undefined') {
        return this[0]?.clientWidth
      }
      return this.each((el) => {
        el.style.width = value;
      })
    }
    /**
     * Return true if at least one of our elements matches the given arguments
     * @param {*} mixed
     * @return bool
     */
    is(mixed) {
      if (typeof mixed === 'string') {
        for (let el of this) {
          if (elMatches(el, mixed)) {
            return true
          }
        }
        return false
      }
      if (typeof mixed === 'function') {
        for (let i = 0, len = this.length; i < len; i++) {
          if (callback.call(this[i], this[i], i)) {
            return true
          }
        }
        return false
      }
      var elements = [];
      if (selector instanceof Micro) {
        elements = Array.from(selector);
      } else if (selector instanceof Node) {
        elements = [selector];
      }
      for (let el of this) {
        for (let i = 0, len = elements.length; i < len; i++) {
          if (elements[i] === el) {
            return true
          }
        }
      }
      return false
    }
    last() {
      return new Micro(this[this.length - 1])
    }
    next(filter) {
      return this.alter((el) => el.nextElementSibling, filter)
    }
    nextAll(filter) {
      return this.alter((el) => {
        const collected = [];
        while (el = el.nextElementSibling) {
          collected.push(el);
        }
        return collected
      }, filter)
    }
    nextUntil(selector, filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.nextElementSibling) && el.matches(selector) === false) {
          collected.push(el);
        }
        return collected
      }, filter)
    }
    not(filter) {
      return this.filter(filter, true)
    }
    off( ...args ) {
      var events = args.length ? args.shift().split(' ') : [];
      var selector = typeof args[0] === 'string' ? args.shift() : null;
      var handler = args.length ? args.shift() : null;
      return this.each((el) => {
        const eventHandlers = findEventHandlerInfo(el, events, selector, handler);
        for (let i = 0, len = eventHandlers.length; i < len; i++) {
          el.removeEventListener(eventHandlers[i]['event'], eventHandlers[i]['wrappedHandler']);
        }
      })
    }
    on( ...args ) {
      var events = args.shift().split(' ');
      var selector = typeof args[0] === 'string' ? args.shift() : null;
      var handler = args.shift();
      return this.each((el) => {
        const wrappedHandler = !selector
          ? handler
          : (e) => {
            const el = e.target?.closest(selector);
            if (el) {
              handler.call(el, e);
            }
          };
        elInitMicro(el);
        for (let i = 0, len = events.length; i < len; i++) {
          el[MicroDom.rand].eventHandlers.push({
            event: events[i],
            handler: handler,
            selector: selector,
            wrappedHandler: wrappedHandler,
          });
          el.addEventListener(events[i], wrappedHandler);
        }
      })
    }
    one( ...args ) {
      return this.on( ...args, true )
    }
    outerHeight(value) {
      if (typeof value === 'undefined') {
        return this[0]?.offsetHeight
      }
      return this.each((el) => {
        el.style.height = value + 'px';
      })
    }
    parent(filter) {
      return this.alter((el) => el.parentNode, filter)
    }
    parents(filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.parentNode) && el !== document) {
          collected.push(el);
        }
        return collected
      }, filter)
    }
    parentsUntil(selector, filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.parentNode) && el.nodeName !== 'BODY' && el.matches(selector) === false) {
          collected.push(el);
        }
        return collected
      }, filter)
    }
    prepend( ...args ) {
      return this.each((el, i) => {
        const elements = argsToElements(args, el, i);
        for (let j = 0, len = elements.length; j < len; j++) {
          el.prepend(elements[j]);
        }
      })
    }
    prev(filter) {
      return this.alter((el) => el.previousElementSibling, filter)
    }
    prevAll(filter) {
      return this.alter((el) => {
        const collected = [];
        while (el = el.previousElementSibling) {
          collected.push(el);
        }
        return collected
      }, filter)
    }
    prevUntil(selector, filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.previousElementSibling) && el.matches(selector) === false) {
          collected.push(el);
        }
        return collected
      }, filter)
    }
    prop( ...args ) {
      if (typeof args[0] === 'string' && typeof args[1] === 'undefined') {
        let name = args[0];
        if (name === 'class') {
          name = 'className';
        }
        return this[0]?.[name]
      }
      // set one or more properties
      if (typeof args[0] === 'string') {
        args[0] = {[args[0]]: args[1]};
      }
      return this.each((el) => {
        for (const [name, value] of Object.entries(args[0])) {
          el[name] = value;
        }
      })
    }
    remove(selector) {
      if (typeof selector === 'string') {
        this.find(selector).remove();
        return this
      }
      return this.each((el) => {
        el.remove();
      })
    }
    removeAttr(name) {
      return this.each((el) => {
        el.removeAttribute(name);
      })
    }
    removeClass(mixed) {
      var classes = [];
      if (typeof mixed === 'function') {
        return this.each((el, i) => {
          const classes = mixed.call(el, el, i);
          $(el).removeClass(classes);
        })
      }
      if (typeof mixed === 'string') {
        classes = mixed.split(' ');
      } else if (Array.isArray(mixed)) {
        classes = mixed;
      }
      return this.each((el) => {
        for (let i = 0, len = classes.length; i < len; i++) {
          el.classList.remove(classes[i]);
        }
      })
    }
    removeData(mixed) {
      return this.each((el) => {
        if (typeof mixed === 'string') {
          mixed = [mixed];
        }
        for (let i = 0, len = mixed.length; i < len; i++) {
          const name = MicroDom.camelCase(mixed[i]);
          delete el[MicroDom.rand].data[name];
          delete el.dataset[name];
        }
      })
    }
    replaceWith( ...args ) {
      return this.each((el, i) => {
        const elements = argsToElements(args, el, i);
        for (let j = 0, len = elements.length; j < len; j++) {
          el.replaceWith(elements[j]);
        }
      })
    }
    scrollTop(value) {
      var win;
      var el;
      for (let i = 0, len = this.length; i < len; i++) {
        el = this[i];
        win = null;

        if (el.window === el) {
          win = el;
        } else if (el.nodeType === 9) {
          // document_node
          win = el.defaultView;
        }

        if (value === undefined) {
          // return scrollTop for first matching element
          return win ? win.pageYOffset : el.scrollTop
        }

        if (win) {
          win.scrollTo(win.pageXOffset, value);
        } else {
          el.scrollTop = value;
        }

      }
      return this
    }
    show() {
      return this.each((el) => {
        el.style.display = el[MicroDom.rand]?.display
          ? el[MicroDom.rand].display
          : '';
      })
    }
    siblings(filter) {
      return this.alter((el) => {
        return Array.from(el.parentNode.children).filter((child) => child !== el)
      }, filter)
    }
    slideDown(duration = 400, onComplete) {
      duration = durationNorm(duration);

      return this.each((el) => {
        var display = window.getComputedStyle(el).display;
        const height = el.offsetHeight;

        el.style.removeProperty('display');

        if (display === 'none') {
          display = 'block';
        }

        el.style.display = display;
        el.style.overflow = 'hidden';
        el.style.height = 0;
        // el.style.paddingTop = 0
        // el.style.paddingBottom = 0
        // el.style.marginTop = 0
        // el.style.marginBottom = 0
        // el.offsetHeight
        el.style.boxSizing = 'border-box';
        el.style.transitionProperty = "height, margin, padding";
        el.style.transitionDuration = duration + 'ms';
        el.style.height = height + 'px';
        el.style.removeProperty('padding-top');
        el.style.removeProperty('padding-bottom');
        el.style.removeProperty('margin-top');
        el.style.removeProperty('margin-bottom');
        window.setTimeout( () => {
          el.style.removeProperty('height');
          el.style.removeProperty('overflow');
          el.style.removeProperty('transition-duration');
          el.style.removeProperty('transition-property');
          if (typeof onComplete === 'function') {
            onComplete.call(el, el);
          }
        }, duration);
      })
    }
    slideUp(duration = 400, onComplete) {
      duration = durationNorm(duration);

      return this.each((el) => {
        el.style.transitionProperty = 'height, margin, padding';
        el.style.transitionDuration = duration + 'ms';
        el.style.boxSizing = 'border-box';
        el.style.height = el.offsetHeight + 'px';
        // el.offsetHeight
        el.style.overflow = 'hidden';
        el.style.height = 0;
        el.style.paddingTop = 0;
        el.style.paddingBottom = 0;
        el.style.marginTop = 0;
        el.style.marginBottom = 0;
        window.setTimeout( () => {
          el.style.display = 'none';
          el.style.removeProperty('height');
          el.style.removeProperty('padding-top');
          el.style.removeProperty('padding-bottom');
          el.style.removeProperty('margin-top');
          el.style.removeProperty('margin-bottom');
          el.style.removeProperty('overflow');
          el.style.removeProperty('transition-duration');
          el.style.removeProperty('transition-property');
          if (typeof onComplete === 'function') {
            onComplete.call(el, el);
          }
        }, duration);
      })
    }
    style( ...args ) {
      if (args.length === 0) {
        return this[0]?.style; // return the style object
      }
      if (typeof args[0] === 'string' && typeof args[1] === 'undefined') {
        return typeof this[0] !== 'undefined'
          ? getComputedStyle(this[0])[args[0]]
          : undefined
      }
      if (typeof args[0] === 'string') {
        args[0] = {[args[0]]: args[1]};
      }
      return this.each((el) => {
        for (const [name, value] of Object.entries(args[0])) {
          el.style[name] = value;
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
        el.textContent = text;
      })
    }
    toggle( ...args ) {
      if (typeof args[0] == 'boolean') {
        return this[args[0] ? 'show' : 'hide']()
      }
      return this.each((el) => {
        let display = 'none';
        if (el.style.display === 'none') {
          display = el[MicroDom.rand]?.display
            ? el[MicroDom.rand].display
            : '';
        }
        el.style.display = display;
      })
    }
    toggleClass(mixed, state) {
      var classes = [];
      if (typeof mixed === 'function') {
        return this.each((el, i) => {
          const classes = mixed.call(el, el, i);
          $(el).toggleClass(classes, state);
        })
      }
      if (typeof mixed === 'string') {
        classes = mixed.split(' ');
      } else if (Array.isArray(mixed) || typeof mixed === 'object') {
        classes = mixed;
      }
      return this.each((el) => {
        MicroDom.each(classes, (value, key) => {
          var className = value;
          var classState = typeof state !== 'undefined'
            ? state
            : el.classList.contains(className) === false;
          if (typeof value === 'boolean' && typeof key === 'string') {
            className = key;
            classState = value;
          }
          classState
            ? el.classList.add(className)
            : el.classList.remove(className);
        });
      })
    }
    trigger(eventName) {
      return this.each((el) => {
        if (typeof eventName === 'string' && typeof el[eventName] === 'function') {
          el[eventType]();
          return
        }
        const event = typeof eventName === 'string'
          ? new Event(eventName, {bubbles: true})
          : eventName;
        el.dispatchEvent(event);
      })
    }
    val(value) {
      if (typeof value === 'undefined') {
        if (typeof this[0] === 'undefined') {
          return undefined
        }
        let el = this[0];
        if (el.options && el.multiple) {
          return el.options
            .filter((option) => option.selected)
            .map((option) => option.value)
        } else {
          return el.value
        }
      }
      return this.each((el) => {
        el.value = value;
      })
    }
    wrap(mixed) {
      return this.each((el, i) => {
        if (typeof mixed === 'function') {
          mixed = mixed.call(el, el, i);
        }
        const wrapperElement = new MicroDom(mixed)[0].cloneNode(true);
        const wrapperElementDeepest = findDeepest(wrapperElement);
        el.replaceWith(wrapperElement);
        wrapperElementDeepest.appendChild(el);
      })
    }
    wrapInner(mixed) {
      return this.each((el, i) => {
        if (typeof mixed === 'function') {
          mixed = mixed.call(el, el, i);
        }
        const wrapperElement = new MicroDom(mixed)[0].cloneNode(true);
        const wrapperElementDeepest = findDeepest(wrapperElement);
        // Move the element's children (incl text/comment nodes) into the wrapper
        while (el.firstChild) {
          wrapperElementDeepest.appendChild(el.firstChild);
        }
        // Append the wrapper to the element
        el.appendChild(wrapperElement);
      })
    }

  }

  function MicroDom (mixed, more) {
    if (mixed instanceof Micro) {
      // this is already a Micro instance
      return mixed
    }
    if (typeof mixed === 'object') {
      // we are assuming this is a single element
      return new Micro(mixed)
    }
    if (typeof mixed === 'function') {
      if (document.readyState !== 'loading') {
        mixed();
      } else {
        document.addEventListener('DOMContentLoaded', mixed);
      }
      return
    }
    if (typeof mixed === 'undefined') {
      return new Micro()
    }
    if (mixed.substr(0, 1) === '<') {
      const elements = createElements(mixed);
      const ret =  new Micro( ...elements );
      if (typeof more === 'object') {
        ret.attr(more);
      }
      return ret
    }
    // we are assuming this is a selector
    return new Micro( ...document.querySelectorAll(mixed) )
  }

  MicroDom.fn = Micro.prototype;

  // used as a key to store internal data on elements
  MicroDom.rand = "microDom" + Math.random().toString().replace(/\D/g, '');

  MicroDom.camelCase = function (str) {
    const regex = /[-_\s]+/;
    if (str.match(regex) === null) {
      return str
    }
    return str.toLowerCase().split(regex).map((word, index) => {
      return index === 0
        ? word
        : word.charAt(0).toUpperCase() + word.slice(1)
    }).join('')
  };

  MicroDom.each = function (mixed, callback) {
    if (Array.isArray(mixed)) {
      return mixed.forEach((value, index) => callback.call(value, value, index))
    }
    for (const [key, value] of Object.entries(mixed)) {
      callback.call(value, value, key);
    }
  };

  MicroDom.extend = function ( ...args ) {
    const isDeep = typeof args[0] === 'boolean' ? args.shift() : false;
    const target = args.shift();
    args.forEach((source) => {
      var curTargetIsObject = false;
      var curSourceIsObject = false;
      for (const [key, value] of Object.entries(source)) {
        curSourceIsObject = typeof value === 'object' && value !== null;
        curTargetIsObject = typeof target[key] === 'object' && target[key] !== null;
        if (curSourceIsObject && curTargetIsObject && isDeep) {
          MicroDom.extend(true, target[key], value);
        } else {
          target[key] = value;
        }
      }
    });
    return target
  };

  /*
  MicroDom.hash = function (str) {
    let hash = 0
    for (let i = 0, len = str.length; i < len; i++) {
        let chr = str.charCodeAt(i)
        hash = (hash << 5) - hash + chr
        hash |= 0; // Convert to 32bit integer
    }
    return hash.toString(16); // convert to hex
  }
  */

  MicroDom.isNumeric = function (val) {
    // parseFloat NaNs numeric-cast false positives ("")
    // ...but misinterprets leading-number strings, particularly hex literals ("0x...")
    // subtraction forces infinities to NaN
    var type = MicroDom.type(val);
    return (type === 'number' || type === 'string')
      && !isNaN(val - parseFloat(val))
  };

  MicroDom.type = function (val) {
    if (val === null || val === undefined) {
      return val + ''
    }
    const class2type = {};
    if (typeof val !== 'object' && typeof val !== 'function') {
      return typeof val
    }
    'Boolean Number String Function Array Date RegExp Object Error Symbol'
      .split(' ')
      .forEach((name) => {
        class2type[`[object ${name}]`] = name.toLowerCase();
      });
    return class2type[ toString.call(val) ] || 'object'
  };

  return MicroDom;

})();
