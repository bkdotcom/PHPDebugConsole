var zest = (function () {
  'use strict';

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
  const REGEX_UNESCAPED_COMMAS = /,(?![^"\[]*\])/;   // Matches unescaped commas, ignoring those inside quotes or attribute selectors
  const REGEX_SPACES_OUTSIDE_ATTRIBUTES = /\s+(?![^\[]*\])(?=[^>+~]*(?:$|[>+~]))(?![^:]*\))/; // Matches spaces, ignoring those inside attribute selectors and pseudo-selectors
  const REGEX_PSEUDO_SELECTORS = /:(?![^\[]*\])/;    // Matches pseudo-selectors, ignoring those inside attribute selectors

  // Utility function to check if a selector contains custom pseudo-selectors
  function containsCustomPseudoSelectors (selector) {
    const customPseudoRegex = new RegExp(`:(${Object.keys(pseudoSelectors).join('|')})\\b`);
    return customPseudoRegex.test(selector)
  }

  // Utility function to test element's nodeName
  function nodeNameIs(el, name) {
    return el.nodeName && el.nodeName.toLowerCase() === name.toLowerCase()
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
        i++;  // Skip the next part as it has been joined
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
      for (const part of parts) {
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
    })
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
        return pseudoSelectors[pseudo](el)
      })
    })
  }

  // element.querySelectorAll() with custom pseudo-selector support
  function querySelectorAll(selector, context = document) {
    if (!containsCustomPseudoSelectors(selector)) {
      selector = selector.replace(/(^|,)\s*>/g, '$1 :scope >').trimStart();
      return Array.from(context.querySelectorAll(selector))
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

    return [ ...new Set(elements) ]  // Remove duplicates
  }

  // element.matches() with custom pseudo-selector support
  function matches(element, selector) {
    if (!containsCustomPseudoSelectors(selector)) {
      return element.matches(selector)
    }
    const parts = parseSelector(selector);
    const elements = parts.length === 1
      ? queryOrMatchPart(element, parts[0], 'match')
      : querySelectorAll(selector);
    return elements.includes(element)
  }

  var rand = "zest" + Math.random().toString().replace(/\D/g, '');

  const computedDisplayValues = {
    'div': 'block',
    'table': 'table',
    'tr': 'table-row',
    'td': 'table-cell',
    'th': 'table-cell',
  };

  const getDisplayValue = function (el) {
    if (el === window) {
      return undefined
    }

    let displayVal = window.getComputedStyle(el).display;

    if (displayVal !== 'none') {
      return displayVal
    }

    // display is "none" - Lets clone the element and check its display value
    const clone = el.cloneNode();
    clone.innerHTML = '';
    clone.removeAttribute('style');
    clone.style.width = '0px';
    clone.style.height = '0px';
    el.after(clone);
    displayVal = window.getComputedStyle(clone).display;
    clone.remove();

    if (displayVal !== 'none') {
      return displayVal
    }

    // we're still "none" so lets check the display value of a temporary element
    const tagName = el.tagName.toLowerCase();
    if (computedDisplayValues[tagName]) {
      return computedDisplayValues[tagName]
    }

    const elTemp = document.createElement(tagName);
    document.body.appendChild(elTemp);
    displayVal = window.getComputedStyle(elTemp).display;
    document.body.removeChild(elTemp);
    computedDisplayValues[tagName] = displayVal;
    return displayVal
  };

  /**
   * convert arguments to elements
   *
   * accepts text/html/css-selector, Node, NodeList, function, iterable
   */
  const argsToElements = function (args, el, index) {
    const elements = [];
    args = Array.from(args); // shallow copy so not affecting original
    while (args.length) {
      const arg = args.shift();
      if (typeof arg === 'string') {
        let elementsAppend = isCssSelector(arg);
        if (elementsAppend === false) {
          elementsAppend = createElements(arg);
        }
        args.unshift(...elementsAppend);
      } else if (arg instanceof Node) {
        elements.push(arg);
      } else if (arg instanceof NodeList) {
        elements.push(...arg);
      } else if (typeof arg === 'object' && typeof arg[Symbol.iterator] === 'function') {
        args.unshift(...arg);
      } else if (typeof arg === 'function') {
        args.unshift(arg.call(el, el, index));
      }
    }
    return elements
  };

  const camelCase = function (str) {
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

  const createElements = function (html) {
    const element = document.createElement('template');
    element.innerHTML = html;  // do not trim
    return element.content.childNodes  // vs element.content.children
  };

  const each = function (mixed, callback) {
    if (Array.isArray(mixed)) {
      return mixed.forEach((value, index) => callback.call(value, value, index))
    }
    for (const [key, value] of Object.entries(mixed)) {
      callback.call(value, value, key);
    }
  };

  const elInitMicroDomInfo = function (el) {
    if (typeof el[rand] === 'undefined') {
      el[rand] = {
        data: {},
        display: getDisplayValue(el),
        eventHandlers: [],
      };
    }
    return el[rand]
  };

  const extend = function ( ...args ) {
    const isDeep = typeof args[0] === 'boolean' ? args.shift() : false;
    const target = args.shift();
    args.forEach((source) => {
      var curTargetIsObject = false;
      var curSourceIsObject = false;
      if (source === undefined || source === null) {
        // silently skip over undefined/null sources
        return
      }
      if (typeof source !== 'object') {
        throw new Error('extend: object or array expected, ' + type(source) + ' given')
      }
      if (Array.isArray(target) && Array.isArray(source)) {
        // append arrays
        Array.prototype.push.apply(target, source);
        return [...new Set(target)] // unique values
      }
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

  const findDeepest = function (el) {
    var children = el.children;
    var depth = arguments[1] || 0;
    var deepestEl = [el, depth];
    for (const child of children) {
      let found = findDeepest(child, depth + 1);
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
        hash |= 0  // Convert to 32bit integer
    }
    return hash.toString(16)  // convert to hex
  }
  */

  const isCssSelector = function (val)
  {
    if (val.includes('<')) {
      return false
    }
    try {
      let isCssSelector = true;
      const elements = querySelectorAll(val);
      if (elements.length === 0 && val.match(/^([a-z][\w\-]*[\s,]*)+$/i)) {
        // we didn't error, but we didn't find any elements and we have a string that looks like words
        // "hello world" is a valid selector
        const words = val.toLowerCase().split(/[\s,]+/);
        // "i" and "a" omitted
        const tags = 'div span form label input select option textarea button section nav img b p u em strong table tr td th ul ol dl li dt dd h1 h2 h3 h4 h5 h6'.split(' ');
        isCssSelector = words.filter(x => tags.includes(x)).length > 0;
      }
      if (isCssSelector) {
        return elements
      }
    } catch {
      // we got an error, so val is not a valid selector
    }
    return false
  };

  const isNumeric = function (val) {
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

  const type = function (val) {
    if (val === null || val === undefined) {
      return val + ''
    }
    if (typeof val !== 'object' && typeof val !== 'function') {
      return typeof val
    }
    if (val instanceof Element) {
      return 'element'
    }
    if (val instanceof Node) {
      return 'node'
    }
    return toString.call(val).match(/^\[object (\w+)\]$/)[1].toLowerCase()
  };

  /**
   * @param string|Array|fn mixed space separated class names, list of class names, or function that returns either
   *
   * @return MicroDom
   */
  function addClass (mixed) {
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i);
        this.eq(i).addClass(classes);
      })
    }
    if (typeof mixed === 'object' && !Array.isArray(mixed) && mixed !== null) {
      // object (not array) of classes
      // classname => boolean
      return this.toggleClass(mixed)
    }
    const classes = typeof mixed === 'string'
      ? mixed.split(' ').filter((val) => val !== '')
      : mixed;
    return this.each((el) => {
      for (const className of classes) {
        el.classList.add(className);
      }
    })
  }

  function attr ( ...args ) {
    if (typeof args[0] === 'string' && args.length === 1) {
      return this[0]?.getAttribute(args[0])
    }
    if (typeof args[0] === 'string') {
      args[0] = {[args[0]]: args[1]};
    }
    return this.each((el, i) => {
      for (const [name, value] of Object.entries(args[0])) {
        if (typeof value === 'boolean' && name.startsWith('data-') === false) {
          if (value) {
            el.setAttribute(name, name);
          } else {
            el.removeAttribute(name);
          }
        } else if (value === undefined) {
          el.removeAttribute(name);
        } else if (value === null && name.startsWith('data-') === false) {
          el.removeAttribute(name);
        } else if (name === 'class') {
          this.eq(i).removeAttr('class').addClass(value);
        } else if (name === 'html') {
          this.eq(i).html(value);
        } else if (name === 'style') {
          this.eq(i).style(value);
        } else if (name === 'text') {
          el.textContent = value;
        } else {
          el.setAttribute(name, value);
        }
      }
    })
  }

  function hasClass (className) {
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

  function prop ( ...args ) {
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
      for (let [name, value] of Object.entries(args[0])) {
        let propName = name === 'class'
          ? 'className'
          : name;
        el[propName] = value;
      }
    })
  }

  function removeAttr (name) {
    return this.each((el) => {
      el.removeAttribute(name);
    })
  }

  function removeClass (mixed) {
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i);
        this.eq(i).removeClass(classes);
      })
    }
    const classes = typeof mixed === 'string'
      ? mixed.split(' ').filter((val) => val !== '')
      : mixed;
    return this.each((el) => {
      for (const className of classes) {
        el.classList.remove(className);
      }
    })
  }

  function toggleClass (mixed, state) {
    if (typeof mixed === 'function') {
      return this.each((el, i) => {
        const classes = mixed.call(el, el, i);
        this.eq(i).toggleClass(classes, state);
      })
    }
    const classes = Array.isArray(mixed) || typeof mixed === 'object'
      ? mixed
      : mixed.split(' ').filter((val) => val !== '');
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

  function val (value) {
    if (typeof value !== 'undefined') {
      // set value
      return this.each((el) => {
        if (el.type === 'checkbox' || el.type === 'radio') {
          el.checked = Boolean(value);
          return
        }
        if (el.tagName === 'SELECT' && el.multiple) {
          if (Array.isArray(value) === false) {
            value = [value];
          }
          Array.from(el.options).forEach((option) => {
            option.selected = value.includes(option.value);
          });
          return
        }
        el.value = value;
      })
    }
    // get value
    if (typeof this[0] === 'undefined') {
      return undefined
    }
    let el = this[0];
    if (el.options && el.multiple) {
      return Array.from(el.options)
        .filter((option) => option.selected)
        .map((option) => option.value)
    }
    return el.value
  }

  function extendMicroDom$6 (MicroDom) {

    Object.assign(MicroDom.prototype, {
      addClass,
      attr,
      hasClass,
      prop,
      removeAttr,
      removeClass,
      toggleClass,
      val,
    });

  }

  const setValue = function (el, key, value) {
    let isStringable = true;
    let stringified = null;
    removeDataHelper(el, key); // remove existing key (whether dataset or special property)
    if (['function', 'undefined'].includes(typeof value)) {
      isStringable = false;
    } else if (typeof value === 'object' && value !== null) {
      // object (or array) value
      // store object & array values in special element property (regardless of serializability)
      // this allows user to maintain a reference to the stored value
      isStringable = false;
    }
    if (isStringable) {
      try {
        stringified = typeof value === 'string'
          ? value
          : JSON.stringify(value);
      } catch (e) {
      }
      isStringable = typeof stringified === 'string';
    }
    key = camelCase(key);
    if (isStringable === false) {
      // store non-serializable value in special element property
      elInitMicroDomInfo(el).data[key] = value;
      return
    }
    el.dataset[key] = stringified;
  };

  const getValue = function (el, name) {
    name = camelCase(name);
    if (typeof el === 'undefined') {
      return undefined
    }
    const value = el[rand]?.data?.[name]
      ? el[rand].data[name]
      : el.dataset[name];
    return safeJsonParse(value)
  };

  const removeDataHelper = function (el, name) {
    name = camelCase(name);
    if (el[rand]) {
      delete el[rand].data[name];
    }
    delete el.dataset[name];
  };

  const safeJsonParse = function (value) {
    try {
      value = JSON.parse(value);
    } catch (e) {
      // do nothing
    }
    return value
  };

  function data (name, value) {
    if (typeof name === 'undefined') {
      // return all data
      if (typeof this[0] === 'undefined') {
        return {}
      }
      const data = {};
      const nonSerializable = this[0][rand]?.data || {};
      for (const key in this[0].dataset) {
        data[key] = safeJsonParse(this[0].dataset[key]);
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
          setValue(el, key, value);
        }
      })
    }
    return getValue(this[0], name)
  }

  function removeData (mixed) {
    mixed = typeof mixed === 'string'
      ? mixed.split(' ').filter((val) => val !== '')
      : mixed;
    return this.each((el) => {
      for (let name of mixed) {
        removeDataHelper(el, name);
      }
    })
  }

  function extendMicroDom$5 (MicroDom) {

    Object.assign(MicroDom.prototype, {
      data,
      removeData,
    });

  }

  const findEventHandlerInfo = function (el, events, selector, handler) {
    return (el[rand]?.eventHandlers || []).filter((handlerInfo) => {
      return handlerInfo.selector === selector
        && (events.length === 0 || events.indexOf(handlerInfo.event) > -1)
        && (!handler || handlerInfo.handler === handler)
    })
  };

  const createEvent = function (eventOrName) {
    const eventClasses = {
      'PointerEvent': ['click', 'dblclick', 'mousedown', 'mousemove', 'mouseout', 'mouseover', 'mouseup'],
      'SubmitEvent': ['submit'],
      'FocusEvent': ['blur', 'focus'],
      'KeyboardEvent': ['keydown', 'keypress', 'keyup'],
      'WindowEvent': ['load', 'resize', 'scroll', 'unload'],
    };
    if (eventOrName instanceof Event) {
      return eventOrName
    }
    for (const [eventClass, eventNames] of Object.entries(eventClasses)) {
      if (eventNames.includes(eventOrName)) {
        return new window[eventClass](eventOrName, {bubbles: true})
      }
    }
    return new Event(eventOrName, {bubbles: true})
  };

  function off ( ...args ) {
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

  function on ( ...args ) {
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
  }

  function one ( ...args ) {
    return this.on( ...args, true )
  }

  function trigger (eventName, extraParams) {
    if (typeof extraParams === 'undefined') {
      extraParams = [];
    }
    return this.each((el) => {
      const event = createEvent(eventName);
      event.extraParams = Array.isArray(extraParams)
        ? extraParams
        : [extraParams];
      el.dispatchEvent(event);
    })
  }

  function extendMicroDom$4 (MicroDom) {

    Object.assign(MicroDom.prototype, {
      off,
      on,
      one,
      trigger,
    });
  }

  function filter (mixed, notFilter = false) {
    return this.alter((el, i) => {
      let isMatch = false;
      if (typeof mixed === 'string') {
        isMatch = matches(el, mixed);
      } else if (typeof mixed === 'function') {
        isMatch = mixed.call(el, el, i);
      } else {
        isMatch = argsToElements([mixed]).includes(el);
      }
      if (notFilter) {
        isMatch = !isMatch;
      }
      return isMatch
        ? el
        : []
    })
  }

  function first () {
    return this.eq(0)
  }

  /**
   * Return true if at least one of our elements matches the given arguments
   * @param {*} mixed
   * @return bool
   */
  function is (mixed) {
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
        if (mixed.call(this[i], this[i], i)) {
          return true
        }
      }
      return false
    }
    const elements = argsToElements([mixed]);
    for (let el of this) {
      for (let i = 0, len = elements.length; i < len; i++) {
        if (elements[i] === el) {
          return true
        }
      }
    }
    return false
  }

  function last () {
    return this.eq(-1)
  }

  function not (filter) {
    return this.filter(filter, true)
  }

  function extendMicroDom$3 (MicroDom) {

    function eq (index) {
      if (index < 0) {
        index = this.length + index;
      }
      return index < 0 || index >= this.length
        ? new MicroDom()
        : new MicroDom(this[index])
    }

    Object.assign(MicroDom.prototype, {
      eq,
      filter,
      first,
      is,
      last,
      not,
    });

  }

  // height = as defined by the CSS height propert
  // clientHeight = includes padding / excludes borders, margins, and scrollbars
  // offsetHeight = includes padding and border / excludes margins
  // getBoundingClientRect() = includes padding, border, and (for most browsers) the scrollbar's height if it's rendered

  function addPx (value) {
    return isNumeric(value)
      ? value + 'px'
      : value
  }

  function queryHelper (microDom, windowProp, elementQuery) {
    if (microDom.length === 0) {
      return undefined
    }
    const el = microDom[0];
    if (type(el) === 'window') {
      return el[windowProp]
    }
    return elementQuery(el)
  }

  function style ( ...args ) {
    if (args.length === 0) {
      return this[0]?.style  // return the style object
    }
    if (typeof args[0] === 'string' && args.length === 1) {
      if (args[0].trim() === '') {
        return this.removeAttr('style')
      }
      if (args[0].includes(':')) {
        // if the string contains a colon, we're setting the style attribute
        return this.each((el) => {
          el.setAttribute('style', args[0]);
        })
      }
      // return the computed style on the first element for the specified property
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

  function height (value) {
    if (typeof value === 'undefined') {
      return queryHelper(this, 'innerHeight', function (el) {
        // return "content" height excluding padding, border, and margin.
        // also works on inline elements
        const cs = window.getComputedStyle(el);
        const paddingY = parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom);
        const borderY = parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth);
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
        const cs = window.getComputedStyle(el);
        const paddingX = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
        const borderX = parseFloat(cs.borderLeftWidth) + parseFloat(cs.borderRightWidth);
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
        const cs = getComputedStyle(el);
        return el.getBoundingClientRect().height
          + parseFloat(cs.marginTop)
          + parseFloat(cs.marginBottom)
      })
    }
    return this.style('height', addPx(value))
  }

  function extendMicroDom$2 (MicroDom) {

    Object.assign(MicroDom.prototype, {
      style,
      height,
      width,
      innerHeight,
      innerWidth,
      outerHeight,
    });

  }

  function getArgs (args) {
    const argsObj = {
      filter: null,
      inclTextNodes: false,
    };
    for (const val of args) {
      // console.log(val)  // Access the value directly
      if (typeof val === 'boolean') {
        argsObj.inclTextNodes = val;
      } else {
        argsObj.filter = val;
      }
    }
    return argsObj
  }

  function collectWhile (el, predicate) {
    const collected = [];
    while (el = predicate(el)) {
      collected.push(el);
    }
    return collected
  }

  /**
   * @param {*} filter
   * @param {bool} inclTextNodes (false)
   */
  function children(...args) {
    args = getArgs(args);
    return args.inclTextNodes
      ? this.alter((el) => el.childNodes, args.filter)
      : this.alter((el) => el.children, args.filter)
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
      return this.alter((el) => querySelectorAll(mixed, el))
    }
    const elements = argsToElements([mixed]);
    return this.alter((el) => {
      const collected = [];
      for (const el2 of elements) {
        if (el.contains(el2)) {
          collected.push(el2);
        }
      }
      return collected
    })
  }

  /**
   * @param {*} filter
   * @param {bool} inclTextNodes (false)
   */
  function next (...args) {
    args = getArgs(args);
    return  args.inclTextNodes
      ? this.alter((el) => el.nextSibling, args.filter)
      : this.alter((el) => el.nextElementSibling, args.filter)
  }

  /**
   * @param {*} filter
   * @param {bool} inclTextNodes (false)
   */
  function nextAll (...args) {
    args = getArgs(args);
    return this.alter((el) => {
      return collectWhile(el, (currentEl) => {
        return args.inclTextNodes
          ? currentEl.nextSibling
          : currentEl.nextElementSibling
      })
    }, args.filter)
  }

  function nextUntil (selector, ...args) {
    args = getArgs(args);
    return this.alter((el) => {
      return collectWhile(el, (currentEl) => {
        const sibling = args.inclTextNodes
          ? currentEl.nextSibling
          : currentEl.nextElementSibling;
        return sibling && (
          sibling.nodeType !== Node.ELEMENT_NODE
          || matches(sibling, selector) === false
        )
          ? sibling
          : null
      })
    }, args.filter)
  }

  function parent (filter) {
    return this.alter((el) => el.parentNode, filter)
  }

  function parents (filter) {
    return this.alter((el) => {
      return collectWhile(el, (currentEl) => {
        const parent = currentEl.parentNode;
        return parent && parent.nodeType !== Node.DOCUMENT_NODE
          ? parent
          : null
      })
    }, filter)
  }

  function parentsUntil (selector, filter) {
    return this.alter((el) => {
      return collectWhile(el, (currentEl) => {
        const parent = currentEl.parentNode;
        return parent && !(currentEl instanceof HTMLHtmlElement) && matches(parent, selector) === false
          ? parent
          : null
      })
    }, filter)
  }

  /**
   * @param {*} filter
   * @param {bool} inclTextNodes (false)
   */
  function prev (...args) {
    args = getArgs(args);
    return args.inclTextNodes
      ? this.alter((el) => el.previousSibling, args.filter)
      : this.alter((el) => el.previousElementSibling, args.filter)
  }

  /**
   * @param {*} filter
   * @param {bool} inclTextNodes (false)
   */
  function prevAll (...args) {
    args = getArgs(args);
    return this.alter((el) => {
      return collectWhile(el, (currentEl) => {
        return args.inclTextNodes
          ? currentEl.previousSibling
          : currentEl.previousElementSibling
      })
    }, args.filter)
  }

  /**
   * @param {*} filter
   * @param {bool} inclTextNodes (false)
   */
  function prevUntil (selector, ...args) {
    args = getArgs(args);
    return this.alter((el) => {
      return collectWhile(el, (currentEl) => {
        const sibling = args.inclTextNodes
          ? currentEl.previousSibling
          : currentEl.previousElementSibling;
        return sibling && (
          sibling.nodeType !== Node.ELEMENT_NODE
          || matches(sibling, selector) === false
        )
          ? sibling
          : null
      })
    }, args.filter)
  }

  /**
   * @param {*} filter
   * @param {bool} inclTextNodes (false)
   */
  function siblings (...args) {
    args = getArgs(args);
    return this.alter((el) => {
      const childNodes = args.inclTextNodes
        ? el.parentNode.childNodes
        : el.parentNode.children;
      return Array.from(childNodes).filter((child) => child !== el)
    }, args.filter)
  }

  function extendMicroDom$1 (MicroDom) {

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
    });

  }

  function durationNorm (duration) {
    if (duration === 'fast') {
      duration = 200;
    } else if (duration === 'slow') {
      duration = 600;
    }
    return duration
  }

  function animate (properties, duration = 400, easing = 'swing', complete) {
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
        const matchesUser = properties[property].toString().match(regex) || [];
        const matchesComputed = computedStyles[property].toString().match(regex) || [];
        propInfo[property] = {
          end: parseFloat(properties[property]),
          start: parseFloat(computedStyles[property]) || 0,
          unit: matchesUser[3] || matchesComputed[3] || '',
        };
      }

      requestAnimationFrame(animateStep);
    })
  }

  function fadeIn (duration = 400, onComplete) {
    duration = durationNorm(duration);

    return this.each((el) => {
      el.style.transition = `opacity ${duration}ms`;
      el.style.opacity = 1;

      setTimeout(() => {
        el.style.display = el[rand]?.display
          ? el[rand].display
          : '';
        el.style.transition = '';  // Reset transition
        if (typeof onComplete === 'function') {
          onComplete.call(el, el);
        }
      }, duration);
    })
  }

  function fadeOut (duration = 400, onComplete) {
    duration = durationNorm(duration);

    return this.each((el) => {
      el.style.transition = `opacity ${duration}ms`;
      el.style.opacity = 0;

      setTimeout(() => {
        elInitMicroDomInfo(el);
        el.style.display = 'none';
        el.style.transition = '';  // Reset transition
        if (typeof onComplete === 'function') {
          onComplete.call(el, el);
        }
      }, duration);
    })
  }

  function hide () {
    return this.each( (el) => {
      elInitMicroDomInfo(el);
      el.style.display = 'none';
    })
  }

  function show () {
    return this.each((el) => {
      el.style.display = elInitMicroDomInfo(el).display;
    })
  }

  function slideDown (duration = 400, onComplete) {
    duration = durationNorm(duration);
    return this.each((el) => {
      el.style.transitionProperty = 'height, margin, padding';
      el.style.transitionDuration = duration + 'ms';
      el.style.boxSizing = 'border-box';
      el.style.overflow = 'hidden';
      el.style.display = elInitMicroDomInfo(el).display;
      el.style.height = el.scrollHeight + 'px';
      el.style.removeProperty('padding-top');
      el.style.removeProperty('padding-bottom');
      el.style.removeProperty('margin-top');
      el.style.removeProperty('margin-bottom');
      window.setTimeout( () => {
        const propsRemove = [
          'box-sizing', 'height',
          'overflow',
          'transition-duration', 'transition-property',
        ];
        propsRemove.forEach((prop) => {
          el.style.removeProperty(prop);
        });
        if (typeof onComplete === 'function') {
          onComplete.call(el, el);
        }
      }, duration);
    })
  }

  function slideUp (duration = 400, onComplete) {
    duration = durationNorm(duration);
    return this.each((el) => {
      el.style.transitionProperty = 'height, margin, padding';
      el.style.transitionDuration = duration + 'ms';
      el.style.boxSizing = 'border-box';
      el.style.overflow = 'hidden';
      el.style.height = 0;
      el.style.paddingTop = 0;
      el.style.paddingBottom = 0;
      el.style.marginTop = 0;
      el.style.marginBottom = 0;
      window.setTimeout( () => {
        const propsRemove = [
          'box-sizing', 'height',
          'margin-bottom', 'margin-top',
          'overflow',
          'padding-bottom', 'padding-top',
          'transition-duration', 'transition-property',
        ];
        el.style.display = 'none';
        propsRemove.forEach((prop) => {
          el.style.removeProperty(prop);
        });
        if (typeof onComplete === 'function') {
          onComplete.call(el, el);
        }
      }, duration);
    })
  }

  function toggle ( ...args ) {
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
  }

  function extendMicroDom (MicroDom) {

    Object.assign(MicroDom.prototype, {
      animate,
      fadeIn,
      fadeOut,
      hide,
      show,
      slideDown,
      slideUp,
      toggle,
    });

  }

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
      for (const elNew of elementsNew) {
        elements.push(elNew);
      }
      elements = [ ...new Set(elements) ];  // remove duplicates
      return new MicroDom( ...elements )
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
      elementsNew = [ ...new Set(elementsNew) ];  // remove duplicates
      const ret = new MicroDom( ...elementsNew );
      return filter
        ? ret.filter(filter)
        : ret
    }
    clone() {
      return this.alter((el) => el.cloneNode(true))
    }
    each(callback) {
      for (let i = 0, len = this.length; i < len; i++) {
        callback.call(this[i], this[i], i);
      }
      return this
    }

    after( ...args ) {
      return this.each((el, i) => {
        const elementsNew = argsToElements(args, el, i);
        elementsNew.reverse();
        for (const elNew of elementsNew) {
          el.after(elNew);
        }
      })
    }
    append( ...args ) {
      return this.each((el, i) => {
        const elementsNew = argsToElements(args, el, i);
        for (const elNew of elementsNew) {
          el.append(elNew);
        }
      })
    }
    before( ...args ) {
      return this.each((el, i) => {
        const elementsNew = argsToElements(args, el, i);
        for (const elNew of elementsNew) {
          el.before(elNew);
        }
      })
    }
    empty() {
      return this.each((el) => {
        el.replaceChildren();
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
        if (mixed instanceof MicroDom || mixed instanceof NodeList) {
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
    prepend( ...args ) {
      return this.each((el, i) => {
        const elementsNew = argsToElements(args, el, i);
        elementsNew.reverse();
        for (const elNew of elementsNew) {
          el.prepend(elNew);
        }
      })
    }
    remove(filter) {
      if (typeof filter !== 'undefined') {
        const $remove = this.filter(filter);
        const remove = Array.from($remove);
        $remove.remove();
        return this.filter(el => !remove.includes(el))
      }
      this.each((el) => el.remove());
      return new MicroDom()
    }
    replaceWith( ...args ) {
      return this.each((el, i) => {
        const elementsNew = argsToElements(args, el, i);
        el.replaceWith(...elementsNew);
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
    serialize() {
      if (this.length === 0) {
        return ''
      }
      const searchParams = new URLSearchParams(new FormData(this[0]));
      return searchParams.toString()
    }
    text(text) {
      if (typeof text === 'undefined') {
        return this.length > 0
          ? this[0].textContent
          : ''  // return empty string vs undefined
      }
      return this.each((el) => {
        el.textContent = text;
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

  extendMicroDom$6(MicroDom);
  extendMicroDom$5(MicroDom);
  extendMicroDom$4(MicroDom);
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
    if ( mixed instanceof Node || mixed === window) {
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
    // we're a string... are we html/text or a selector?
    if (mixed.includes('<')) {
      const elements = createElements(mixed);
      const ret = new MicroDom( ...elements );
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
