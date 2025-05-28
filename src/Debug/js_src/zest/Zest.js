import * as helper from './helper.js'
import MicroDom from './microDom/MicroDom.js'

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
      mixed()
      return
    }
    document.addEventListener('DOMContentLoaded', mixed)
    return
  }
  // we're a string... are we html/text or a selector?
  if (mixed.includes('<')) {
    const elements = helper.createElements(mixed)
    const ret =  new MicroDom( ...elements )
    if (typeof more === 'object') {
      ret.attr(more)
    }
    return ret
  }
  // we are assuming this is a selector
  return new MicroDom(document).find(mixed)
}

Zest.fn = MicroDom.prototype

// used as a key to store internal data on elements
Zest.rand = helper.rand

Zest.camelCase = helper.camelCase

Zest.each = helper.each

Zest.extend = helper.extend

Zest.isNumeric = helper.isNumeric

Zest.type = helper.type

export default Zest
