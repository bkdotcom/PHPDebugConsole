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
      const combined = parts[i] + ' ' + parts[i + 1]
      containsCustomPseudoSelectors(rejoinedParts[rejoinedParts.length - 1]) === false
        ? rejoinedParts[rejoinedParts.length - 1] += ' ' + combined
        : rejoinedParts.push(combined)
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
    var pseudosStandard = []
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
    baseElements = Array.from(element.querySelectorAll(part.baseSelector))
  } else if (queryOrMatch === 'match' && part.baseSelector) {
    baseElements = element.matches(part.baseSelector)
      ? [element]
      : []
  }
  return baseElements.filter(el => {
    return part.pseudoParts.every(pseudo => {
      return pseudoSelectors[pseudo](el);
    });
  });
}

// register custom pseudo-selectors
// This function allows you to register a custom pseudo-selector with a filter function
// or a string that will be used to replace the pseudo-selector in the selector string.
export function registerPseudoSelector(name, mixed) {
  if (typeof mixed !== 'function' && typeof mixed !== 'string') {
    throw new Error('Filter must be a function or a string');
  }
  pseudoSelectors[name] = mixed;
}

// element.querySelectorAll() with custom pseudo-selector support
export function querySelectorAll(selector, context = document) {
  if (!containsCustomPseudoSelectors(selector)) {
    selector = selector.replace(/(^|,)\s*>/g, '$1 :scope >').trimStart()
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
export function matches(element, selector) {
  if (!containsCustomPseudoSelectors(selector)) {
    return element.matches(selector);
  }
  const parts = parseSelector(selector);
  const elements = parts.length === 1
    ? queryOrMatchPart(element, parts[0], 'match')
    : querySelectorAll(selector)
  return elements.includes(element);
}
