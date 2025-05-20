var zest = (function () {
  'use strict';

  var rand = "zest" + Math.random().toString().replace(/\D/g, '');

  var camelCase = function (str) {
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

  var createElements = function (html) {
    const element = document.createElement('template');
    element.innerHTML = html; // do not trim
    return element.content.childNodes;  // vs element.content.children
  };

  var each = function (mixed, callback) {
    if (Array.isArray(mixed)) {
      return mixed.forEach((value, index) => callback.call(value, value, index))
    }
    for (const [key, value] of Object.entries(mixed)) {
      callback.call(value, value, key);
    }
  };

  var elInitMicroDomInfo = function (el) {
    if (typeof el[rand] === 'undefined') {
      el[rand] = {
        data: {},
        // display
        eventHandlers: [],
      };
    }
    if (el !== window && typeof el[rand].display === 'undefined') {
      const displayVal = window.getComputedStyle(el).display;
      if (displayVal !== 'none') {
        el[rand].display = displayVal;
      }
    }
  };

  /*
  export var elMatches = function (el, selector) {
    selector = modifySelector(selector)
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
  }
  */

  var extend = function ( ...args ) {
    const isDeep = typeof args[0] === 'boolean' ? args.shift() : false;
    const target = args.shift();
    args.forEach((source) => {
      var curTargetIsObject = false;
      var curSourceIsObject = false;
      for (const [key, value] of Object.entries(source)) {
        curSourceIsObject = typeof value === 'object' && value !== null;
        curTargetIsObject = typeof target[key] === 'object' && target[key] !== null;
        if (curSourceIsObject && curTargetIsObject && isDeep) {
          extend(true, target[key], value);
        } else {
          target[key] = value;
        }
      }
    });
    return target
  };

  var findDeepest = function (el) {
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

  /*
  function hash (str) {
    let hash = 0
    for (let i = 0, len = str.length; i < len; i++) {
        let chr = str.charCodeAt(i)
        hash = (hash << 5) - hash + chr
        hash |= 0; // Convert to 32bit integer
    }
    return hash.toString(16); // convert to hex
  }
  */

  var isNumeric = function (val) {
    // parseFloat NaNs numeric-cast false positives ("")
    // ...but misinterprets leading-number strings, particularly hex literals ("0x...")
    // subtraction forces infinities to NaN
    var valType = type(val);
    return (valType === 'number' || valType === 'string')
      && !isNaN(val - parseFloat(val))
  };

  /*
  function isPlainObject (val) {
    return typeof val === 'object'
      && val !== null
      && val.constructor === Object
  }
  */

  /*
  export var modifySelector = function (selector) {
    selector = selector.replace(/(^|,)\s*>/g, '$1 :scope >').trimStart()
    selector = selector.replace(/:input/g, 'input')
    selector = selector.replace(/:(button|checkbox|file|radio|password|reset|submit|text)\b/g, '[type="$1"]')
    return selector
  }
  */

  var type = function (val) {
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

  // This script provides utility methods for handling custom pseudo-selectors in DOM queries.
  // It includes functions to register custom pseudo-selectors, query elements using these selectors,
  // and check if elements match selectors with custom pseudo-selectors.

  // Registry for custom pseudo-selectors
  // Pre-define custom pseudo-selectors found in the jQuery library
  const pseudoSelectors = {
    hidden: el => !(el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0),
    visible: el => el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0,

    header: ':is(h1, h2, h3, h4, h5, h6)',

    // input type related
    button: el => (nodeNameIs(el, 'input') && el.type === 'button') || nodeNameIs(el, 'button'),
    checkbox: '[type="checkbox"]',
    file: '[type="file"]',
    input: ':is(button, input, select, textarea)',
    password: '[type="password"]',
    radio: '[type="radio"]',
    reset: '[type="reset"]:is(button, input)',
    search: '[type="search"]',
    selected: 'option:checked', // jquery's :selected only works for <option> elements
    submit: el => (nodeNameIs(el, 'button') || nodeNameIs(el, 'input')) && el.type === 'submit', // can't use [type="button"] because <button>'s default type is "submit"
    text: el => nodeNameIs(el, 'input') && el.type === 'text', // can't use [type="text"] because the attribute may not be present
  };

  // Define shared regular expressions as global constants
  const REGEX_UNESCAPED_COMMAS = /,(?![^"\[]*\])/; // Matches unescaped commas, ignoring those inside quotes or attribute selectors
  const REGEX_SPACES_OUTSIDE_ATTRIBUTES = /\s+(?![^\[]*\])(?=[^>+~]*(?:$|[>+~]))(?![^:]*\))/; // Matches spaces, ignoring those inside attribute selectors and pseudo-selectors
  const REGEX_PSEUDO_SELECTORS = /:(?![^\[]*\])/; // Matches pseudo-selectors, ignoring those inside attribute selectors

  // Utility function to check if a selector contains custom pseudo-selectors
  function containsCustomPseudoSelectors (selector) {
    const customPseudoRegex = new RegExp(`:(${Object.keys(pseudoSelectors).join('|')})\\b`);
    return customPseudoRegex.test(selector);
  }

  // Utility function to test element's nodeName
  function nodeNameIs(el, name) {
    return el.nodeName && el.nodeName.toLowerCase() === name.toLowerCase();
  }

  // parse/split selector into chunks
  // chunks contains any custom pseudo-selectors at the end of the chunk
  function parseSelector (selector) {

    // Replace string-based pseudo-selectors in the selector
    Object.entries(pseudoSelectors).forEach(([name, mixed]) => {
      if (typeof mixed === 'string') {
        const regex = new RegExp(`:${name}\\b`, 'g');
        selector = selector.replace(regex, mixed);
      }
    });

    const parts = selector.split(REGEX_SPACES_OUTSIDE_ATTRIBUTES);

    // Rejoin parts such that parts end with custom pseudo-selectors
    const rejoinedParts = [];
    for (let i = 0; i < parts.length; i++) {
      if (i > 0 && /^[>+~]$/.test(parts[i])) {
        const combined = parts[i] + ' ' + parts[i + 1];
        containsCustomPseudoSelectors(rejoinedParts[rejoinedParts.length - 1]) === false
          ? rejoinedParts[rejoinedParts.length - 1] += ' ' + combined
          : rejoinedParts.push(combined);
        i++; // Skip the next part as it has been joined
      } else {
        rejoinedParts.push(parts[i]);
      }
    }

    return rejoinedParts.map(part => {
      const parts = part.split(REGEX_PSEUDO_SELECTORS);
      const customPseudoSelectors = Object.keys(pseudoSelectors);
      var baseSelector = parts.shift();
      var pseudosCustom = [];
      var pseudosStandard = [];
      for (let i = 0; i < parts.length; i++) {
        const part = parts[i];
        customPseudoSelectors.includes(part)
          ? pseudosCustom.push(part)
          : pseudosStandard.push(part);
      }
      if (pseudosStandard.length > 0) {
        // Add standard pseudo-selectors to the baseSelector
        baseSelector += pseudosStandard.map(p => `:${p}`).join('');
      } else if (baseSelector && /[>+~]\s*$/.test(baseSelector)) {
        // baseSelector ends with a CSS combinator -> append "*"
        baseSelector += ' *';
      } else if (baseSelector && /^\s*[>+~]/.test(baseSelector)) {
        // baseSelector starts with a CSS combinator -> prepend ":scope"
        baseSelector = ':scope ' + baseSelector;
      } else if (baseSelector === '') {
        baseSelector = '*';
      }
      return {
        baseSelector: baseSelector.trim(),
        pseudoParts: pseudosCustom,
      }
    });
  }

  // for the given element, perform a query or match
  // return array of elements
  function queryOrMatchPart (element, part, queryOrMatch = 'query') {
    var baseElements = [element];
    if (queryOrMatch === 'query' && part.baseSelector) {
      baseElements = Array.from(element.querySelectorAll(part.baseSelector));
    } else if (queryOrMatch === 'match' && part.baseSelector) {
      baseElements = element.matches(part.baseSelector)
        ? [element]
        : [];
    }
    return baseElements.filter(el => {
      return part.pseudoParts.every(pseudo => {
        return pseudoSelectors[pseudo](el);
      });
    });
  }

  // element.querySelectorAll() with custom pseudo-selector support
  function querySelectorAll(selector, context = document) {
    if (!containsCustomPseudoSelectors(selector)) {
      selector = selector.replace(/(^|,)\s*>/g, '$1 :scope >').trimStart();
      return Array.from(context.querySelectorAll(selector));
    }

    let elements = [];
    const selectors = selector.split(REGEX_UNESCAPED_COMMAS).map(s => s.trim());
    selectors.forEach(sel => {
      let currentContext = [context];

      parseSelector(sel).forEach(part => {
        let matchedElements = [];
        currentContext.forEach(ctx => {
          const filteredElements = queryOrMatchPart(ctx, part, 'query');
          matchedElements = matchedElements.concat(filteredElements);
        });

        currentContext = matchedElements;
      });

      elements = elements.concat(currentContext);
    });

    return [...new Set(elements)]; // Remove duplicates
  }

  // element.matches() with custom pseudo-selector support
  function matches(element, selector) {
    if (!containsCustomPseudoSelectors(selector)) {
      return element.matches(selector);
    }
    const parts = parseSelector(selector);
    const elements = parts.length === 1
      ? queryOrMatchPart(element, parts[0], 'match')
      : querySelectorAll(selector);
    return elements.includes(element);
  }

  const findEventHandlerInfo = function (el, events, selector, handler) {
    return (el[rand]?.eventHandlers || []).filter((handlerInfo) => {
      return handlerInfo.selector === selector
        && (events.length === 0 || events.indexOf(handlerInfo.event) > -1)
        && (!handler || handlerInfo.handler === handler)
    })
  };

  function extendMicroDom$3 (MicroDom) {
    MicroDom.prototype.off = function ( ...args ) {
      var events = args.length ? args.shift().split(' ') : [];
      var selector = typeof args[0] === 'string' ? args.shift() : null;
      var handler = args.length ? args.shift() : null;
      return this.each((el) => {
        const eventHandlers = findEventHandlerInfo(el, events, selector, handler);
        for (let i = 0, len = eventHandlers.length; i < len; i++) {
          el.removeEventListener(eventHandlers[i]['event'], eventHandlers[i]['wrappedHandler']);
        }
      })
    };

    MicroDom.prototype.on = function ( ...args ) {
      const events = args.shift().split(' ');
      const selector = typeof args[0] === 'string' ? args.shift() : null;
      const handler = args.shift();
      const captureEvents = ['mouseenter', 'mouseleave', 'pointerenter', 'pointerleave'];
      return this.each((el) => {
        const wrappedHandler = (e) => {
          const target = selector
            ? (captureEvents.includes(e.type)
              ? (e.target.matches(selector) ? e.target : null)
              : e.target?.closest(selector))
            : el;
          return target && (target instanceof Window || document.body.contains(target))
            ? handler.apply(target, [e, ...(e.extraParams || [])])
            : true
        };
        elInitMicroDomInfo(el);
        for (let i = 0, len = events.length; i < len; i++) {
          const eventName = events[i];
          const capture = selector && captureEvents.includes(eventName);
          el[rand].eventHandlers.push({
            event: eventName,
            handler: handler,
            selector: selector,
            wrappedHandler: wrappedHandler,
          });
          el.addEventListener(eventName, wrappedHandler, capture);
        }
      })
    };

    MicroDom.prototype.one = function ( ...args ) {
      return this.on( ...args, true )
    };

    MicroDom.prototype.trigger = function (eventName, extraParams) {
      return this.each((el) => {
        if (typeof eventName === 'string' && typeof el[eventName] === 'function') {
          el[eventType]();
          return
        }
        const event = typeof eventName === 'string'
          ? new Event(eventName, {bubbles: true})
          : eventName;
        if (typeof extraParams !== 'undefined') {
          event.extraParams = Array.isArray(extraParams)
            ? extraParams
            : [extraParams];
        }
        el.dispatchEvent(event);
      })
    };
  }

  function extendMicroDom$2 (MicroDom) {

    MicroDom.prototype.eq = function (index) {
      if (index < 0) {
        index = this.length + index;
      }
      if (index < 0 || index >= this.length) {
        return new MicroDom()
      }
      return new MicroDom(this[index])
    };

    MicroDom.prototype.filter = function (mixed, notFilter = false) {
      return this.alter((el, i) => {
        let isMatch = false;
        if (typeof mixed === 'string') {
          isMatch = matches(el, mixed);
        } else if (typeof mixed === 'function') {
          isMatch = mixed.call(el, el, i);
        } else {
          var elements = [];
          if (mixed instanceof MicroDom) {
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
    };

    MicroDom.prototype.first = function () {
      return new MicroDom( this[0] )
    };

    /**
     * Return true if at least one of our elements matches the given arguments
     * @param {*} mixed
     * @return bool
     */
    MicroDom.prototype.is = function (mixed) {
      if (typeof mixed === 'string') {
        for (let el of this) {
          if (matches(el, mixed)) {
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
    };

    MicroDom.prototype.last = function () {
      return new MicroDom(this[this.length - 1])
    };

    MicroDom.prototype.not = function (filter) {
      return this.filter(filter, true)
    };
  }

  function extendMicroDom$1 (MicroDom) {
    MicroDom.prototype.children = function (filter) {
      return this.alter((el) => el.children, filter)
    };

    MicroDom.prototype.closest = function (selector) {
      return this.alter((el) => {
        if (typeof el.closest !== 'function') {
          // console.warn('el.closest is not a function', el)
          return []
        }
        return el.closest(selector)
      })
    };

    MicroDom.prototype.find = function (mixed) {
      if (typeof mixed === 'string') {
        // const selector = helper.modifySelector(mixed)
        // return this.alter((el) => el.querySelectorAll(selector))
        return this.alter((el) => querySelectorAll(mixed, el))
      }
      var elements = [];
      if (mixed instanceof MicroDom) {
        elements = Array.from(mixed);
      } else if (mixed instanceof Node) {
        elements = [mixed];
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
    };

    MicroDom.prototype.next = function (filter) {
      return this.alter((el) => el.nextElementSibling, filter)
    };

    MicroDom.prototype.nextAll = function (filter) {
      return this.alter((el) => {
        const collected = [];
        while (el = el.nextElementSibling) {
          collected.push(el);
        }
        return collected
      }, filter)
    };

    MicroDom.prototype.nextUntil = function (selector, filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.nextElementSibling) && matches(el, selector) === false) {
          collected.push(el);
        }
        return collected
      }, filter)
    };

    MicroDom.prototype.parent = function (filter) {
      return this.alter((el) => el.parentNode, filter)
    };

    MicroDom.prototype.parents = function (filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.parentNode) && el !== document) {
          collected.push(el);
        }
        return collected
      }, filter)
    };

    MicroDom.prototype.parentsUntil = function (selector, filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.parentNode) && el.nodeName !== 'BODY' && matches(el, selector) === false) {
          collected.push(el);
        }
        return collected
      }, filter)
    };

    MicroDom.prototype.prev = function (filter) {
      return this.alter((el) => el.previousElementSibling, filter)
    };

    MicroDom.prototype.prevAll = function (filter) {
      return this.alter((el) => {
        const collected = [];
        while (el = el.previousElementSibling) {
          collected.push(el);
        }
        return collected
      }, filter)
    };

    MicroDom.prototype.prevUntil = function (selector, filter) {
      return this.alter((el) => {
        const collected = [];
        while ((el = el.previousElementSibling) && el.matches(selector) === false) {
          collected.push(el);
        }
        return collected
      }, filter)
    };

    MicroDom.prototype.siblings = function (filter) {
      return this.alter((el) => {
        return Array.from(el.parentNode.children).filter((child) => child !== el)
      }, filter)
    };
  }

  function extendMicroDom (MicroDom) {

    const durationNorm = function (duration) {
      if (duration === 'fast') {
        duration = 200;
      } else if (duration === 'slow') {
        duration = 600;
      }
      return duration
    };

    MicroDom.prototype.animate = function (properties, duration = 400, easing = 'swing', complete) {
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
          const matchesUser = properties[property].toString().match(regex);
          const matchesComputed = computedStyles[property].toString().match(regex);
          propInfo[property] = {
            end: parseFloat(properties[property]),
            start: parseFloat(computedStyles[property]) || 0,
            unit: matchesUser[3] || matchesComputed[3] || '',
          };
        }

        requestAnimationFrame(animateStep);
      })
    };

    MicroDom.prototype.fadeIn = function (duration = 400, onComplete) {
      duration = durationNorm(duration);

      return this.each((el) => {
        el.style.transition = `opacity ${duration}ms`;
        el.style.opacity = 1;

        setTimeout(() => {
          el.style.display = el[rand]?.display
            ? el[rand].display
            : '';
          el.style.transition = ''; // Reset transition
          if (typeof onComplete === 'function') {
            onComplete.call(el, el);
          }
        }, duration);
      })
    };

    MicroDom.prototype.fadeOut = function (duration = 400, onComplete) {
      duration = durationNorm(duration);

      return this.each((el) => {
        el.style.transition = `opacity ${duration}ms`;
        el.style.opacity = 0;

        setTimeout(() => {
          elInitMicroDomInfo(el);
          el.style.display = 'none';
          el.style.transition = ''; // Reset transition
          if (typeof onComplete === 'function') {
            onComplete.call(el, el);
          }
        }, duration);
      })
    };

    MicroDom.prototype.hide = function () {
      return this.each( (el) => {
        elInitMicroDomInfo(el);
        el.style.display = 'none';
      })
    };

    MicroDom.prototype.show = function () {
      return this.each((el) => {
        el.style.display = el[rand]?.display
          ? el[rand].display
          : '';
      })
    };

    MicroDom.prototype.slideDown = function (duration = 400, onComplete) {
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
    };

    MicroDom.prototype.slideUp = function (duration = 400, onComplete) {
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
    };

    MicroDom.prototype.toggle = function ( ...args ) {
      if (typeof args[0] == 'boolean') {
        return this[args[0] ? 'show' : 'hide']()
      }
      return this.each((el) => {
        let display = 'none';
        if (el.style.display === 'none') {
          display = el[rand]?.display
            ? el[rand].display
            : '';
        }
        el.style.display = display;
      })
    };
  }

  const argsToElements = function (args, el, index) {
    const elements = [];
    while (args.length) {
      const arg = args.shift();
      if (typeof arg === 'string') {
        // we assume this is HTML
        const elementsAppend = createElements(arg);
        for (let j = 0, jlen = elementsAppend.length; j < jlen; j++) {
          elements.push(elementsAppend[j]);
        }
      } else if (typeof args === 'function') {
        args.unshift(arg.call(el, el, index));
      } else if (arg instanceof MicroDom) {
        for (let j = 0, jlen = arg.length; j < jlen; j++) {
          elements.push(arg[j]);
        }
      } else if (arg instanceof Node) {
        elements.push(arg);
      } else if (Array.isArray(arg)) {
        args.unshift(...arg);
      }
    }
    return elements
  };

  /**
   * MicroDom - a fluent collection of DOM elements
   */
  class MicroDom extends Array {

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
      var elements = Array.from(this);
      var elementsNew = argsToElements([mixed]);
      for (let i = 0, len = elementsNew.length; i < len; i++) {
        elements.push(elementsNew[i]);
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
     * returns a new MicroDom instance
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
      const ret = new MicroDom( ...elementsNew );
      return filter
        ? ret.filter(filter)
        : ret
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
      if (typeof args[0] === 'string' && args.length === 1) {
        return this[0]?.getAttribute(args[0])
      }
      if (typeof args[0] === 'string') {
        args[0] = {[args[0]]: args[1]};
      }
      return this.each((el, i) => {
        for (const [name, value] of Object.entries(args[0])) {
          if ([null, undefined].includes(value)) {
            el.removeAttribute(name);
          } else if (name === 'class') {
            this.eq(i).addClass(value);
          } else if (name === 'html') {
            this.eq(i).html(value);
          } else if (name === 'style') {
            this.eq(i).style(value);
          } else if (name === 'text') {
            el.textContent = value;
          } else if (typeof value === 'boolean' && name.startsWith('data-') === false) {
            if (value) {
              el.setAttribute(name, name);
            } else {
              el.removeAttribute(name);
            }
          } else {
            el.setAttribute(name, value);
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
    clone() {
      return this.alter((el) => el.cloneNode(true))
    }
    data(name, value) {
      if (typeof name === 'undefined') {
        // return all data
        if (typeof this[0] === 'undefined') {
          return {}
        }
        const data = {};
        const nonSerializable = this[0][rand]?.data || {};
        for (const key in this[0].dataset) {
          value = this[0].dataset[key];
          try {
            value = JSON.parse(value);
          } catch (e) {
            // do nothing
          }
          data[key] = value;
        }
        return extend(data, nonSerializable)
      }
      if (typeof name !== 'object' && typeof value !== 'undefined') {
        // we're setting a single value -> convert to object
        name = {[name]: value};
      }
      if (typeof name === 'object') {
        // setting value(s)
        return this.each((el) => {
          for (let [key, value] of Object.entries(name)) {
            var isStringable = true;
            if (typeof value === 'function') {
              isStringable = false;
            } else if (typeof value === 'object' && value !== null) {
              isStringable = value === JSON.parse(JSON.stringify(value));
            }
            key = camelCase(key);
            if (isStringable === false) {
              // store in non-serializable value in special element property
              elInitMicroDomInfo(el);
              el[rand].data[key] = value;
              continue
            }
            el.dataset[key] = typeof value === 'string'
              ? value
              : JSON.stringify(value);
          }
        })
      }
      // return value
      name = camelCase(name);
      if (typeof this[0] === 'undefined') {
        return undefined
      }
      value = this[0][rand]?.data?.[name]
        ? this[0][rand].data[name]
        : this[0].dataset[name];
      try {
        return JSON.parse(value)
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
    empty() {
      return this.each((el) => {
        el.replaceChildren();
      })
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
        // return this[0]?.clientHeight = inner height, including padding but excluding borders and margins
        // return this[0]?.getBoundingClientRect().height
        return this[0]?.offsetHeight // total height, including padding and borders
      }
      return this.each((el) => {
        el.style.height = value + 'px';
      })
    }
    html(mixed) {
      if (typeof mixed === 'undefined') {
        return this[0]?.innerHTML
      }
      return this.each((el, i) => {
        const oldHtml = el.innerHTML;
        el.replaceChildren(); // empty
        if (typeof mixed === 'function') {
          mixed = mixed.call(el, el, i, oldHtml);
        }
        if (mixed instanceof MicroDom) {
          el.replaceChildren(...Array.from(mixed));
        } else if (mixed instanceof Node) {
          el.replaceChildren(mixed);
        } else {
          el.innerHTML = mixed;
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
        const elements = argsToElements([mixed]);
        return elements.length > 0
          ? this.indexOf(elements[0])
          : -1
      }
      if (typeof mixed === 'string') {
        // return the index of the first element that matches the selector
        for (let i = 0, len = this.length; i < len; i++) {
          if (matches(this[i], mixed)) {
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
    outerHeight(value) {
      if (typeof value === 'undefined') {
        return this[0]?.offsetHeight
      }
      return this.each((el) => {
        el.style.height = value + 'px';
      })
    }
    prepend( ...args ) {
      return this.each((el, i) => {
        const elements = argsToElements(args, el, i);
        for (let j = 0, len = elements.length; j < len; j++) {
          el.prepend(elements[j]);
        }
      })
    }
    prop( ...args ) {
      if (typeof args[0] === 'string' && args.length === 1) {
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
          const name = camelCase(mixed[i]);
          if (el[rand]) {
            delete el[rand].data[name];
          }
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
    style( ...args ) {
      if (args.length === 0) {
        return this[0]?.style; // return the style object
      }
      if (typeof args[0] === 'string' && args.length === 1) {
        if (args[0].includes(':')) {
          // if the property contains a colon, we're setting the style attribute
          return this.each((el) => {
            el.setAttribute('style', args[0]);
          })
        }
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
        each(classes, (value, key) => {
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
        // mixed can be a string, MicroDom instance, or a DOM element
        const elements = argsToElements([mixed]);
        const wrapperElement = elements[0].cloneNode(true);
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
        // mixed can be a string, MicroDom instance, or a DOM element
        const elements = argsToElements([mixed]);
        const wrapperElement = elements[0].cloneNode(true);
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

  extendMicroDom$3(MicroDom);
  extendMicroDom$2(MicroDom);
  extendMicroDom$1(MicroDom);
  extendMicroDom(MicroDom);

  /**
   * zest - vanilla javascript jquery replacement
   *
   * key differences:
   *    each() callback receives value as first argument
   *    filter() callback receives value as first argument
   */
  function Zest (mixed, more) {
    if (mixed instanceof MicroDom) {
      // this is already a MicroDom instance
      return mixed
    }
    if (mixed instanceof Node || mixed instanceof Window) {
      return new MicroDom(mixed)
    }
    if (typeof mixed === 'undefined') {
      return new MicroDom()
    }
    if (typeof mixed === 'function') {
      if (document.readyState !== 'loading') {
        mixed();
        return
      }
      document.addEventListener('DOMContentLoaded', mixed);
      return
    }
    if (typeof mixed !== 'string') {
      console.warn('what is this?', typeof mixed, mixed);
    }
    if (mixed.substr(0, 1) === '<') {
      const elements = createElements(mixed);
      const ret =  new MicroDom( ...elements );
      if (typeof more === 'object') {
        ret.attr(more);
      }
      return ret
    }
    // we are assuming this is a selector
    return new MicroDom(document).find(mixed)
  }

  Zest.fn = MicroDom.prototype;

  // used as a key to store internal data on elements
  Zest.rand = rand;

  Zest.camelCase = camelCase;

  Zest.each = each;

  Zest.extend = extend;

  Zest.isNumeric = isNumeric;

  Zest.type = type;

  return Zest;

})();
