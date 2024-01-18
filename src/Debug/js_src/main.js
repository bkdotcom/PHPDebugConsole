/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups, arrays, & objects
 *    Add FontAwesome icons
 */

import $ from 'jquery' // external global
import './prototypeMethods.js'
import * as enhanceEntries from './enhanceEntries.js'
import * as enhanceMain from './enhanceMain.js'
import * as expandCollapse from './expandCollapse.js'
import * as sidebar from './sidebar.js'
import * as tabs from './tabs.js'
import * as tooltip from './Tooltip.js'
import { Config } from './config.js'
import loadDeps from './loadDeps.js'

var listenersRegistered = false
var config = new Config()

if (typeof $ === 'undefined') {
  throw new TypeError('PHPDebugConsole\'s JavaScript requires jQuery.')
}

/*
  Load 'optional' dependencies
*/
loadDeps([
  {
    src: config.get('fontAwesomeCss'),
    type: 'stylesheet',
    check: function () {
      var fontFamily = getFontFamily('fa')
      var haveFa = fontFamily === 'FontAwesome' || fontFamily.indexOf('Font Awesome') > -1
      return haveFa
    },
    onLoaded: function () {
      var fontFamily = getFontFamily('fa')
      var matches = fontFamily.match(/Font\s?Awesome.+(\d+)/)
      if (matches && matches[1] >= 5) {
        addStyle(config.get('cssFontAwesome5'))
      }
    }
  },
  {
    src: config.get('clipboardSrc'),
    check: function () {
      return typeof window.ClipboardJS !== 'undefined'
    },
    onLoaded: function () {
      initClipboardJs()
    }
  }
])

$.fn.debugEnhance = function (method, arg1, arg2) {
  // console.warn('debugEnhance', method, this)
  if (method === 'sidebar') {
    debugEnhanceSidebar(this, arg1)
  } else if (method === 'buildChannelList') {
    return enhanceMain.buildChannelList(arg1, arg2, arguments[3])
  } else if (method === 'collapse') {
    this.each(function () {
      expandCollapse.collapse($(this), arg1)
    })
  } else if (method === 'expand') {
    this.each(function () {
      expandCollapse.expand($(this))
    })
  } else if (method === 'init') {
    debugEnhanceInit(this, arg1)
  } else if (method === 'setConfig') {
    debugEnhanceSetConfig(this, arg1)
  } else {
    debugEnhanceDefault(this)
  }
  return this
}

$(function () {
  $('.debug').each(function () {
    $(this).debugEnhance('init')
  })
})

function debugEnhanceInit ($node, arg1) {
  var conf = new Config(config.get(), 'phpDebugConsole')
  $node.data('config', conf)
  conf.set($node.eq(0).data('options') || {})
  if (typeof arg1 === 'object') {
    conf.set(arg1)
  }
  tabs.init($node)
  if (conf.get('tooltip')) {
    tooltip.init($node)
  }
  enhanceEntries.init($node)
  expandCollapse.init($node)
  registerListeners($node)
  enhanceMain.init($node)
  if (!conf.get('drawer')) {
    $node.debugEnhance()
  }
}

function debugEnhanceDefault ($node) {
  $node.each(function () {
    var $self = $(this)
    if ($self.hasClass('debug')) {
      // console.warn('debugEnhance() : .debug')
      $self.find('.debug-menu-bar > nav, .tab-panes').show()
      $self.find('.tab-pane.active')
        .find('.m_alert, .debug-log-summary, .debug-log')
        .debugEnhance()
      $self.trigger('refresh.debug')
      return
    }
    if ($self.hasClass('filter-hidden') && $self.hasClass('m_group') === false) {
      return
    }
    // console.group('debugEnhance')
    if ($self.hasClass('group-body')) {
      enhanceEntries.enhanceEntries($self)
    } else if ($self.is('li, div') && $self.prop('class').match(/\bm_/) !== null) {
      // logEntry  (alerts use <div>)
      enhanceEntries.enhanceEntry($self)
    } else if ($self.prop('class').match(/\bt_/)) {
      // value
      enhanceEntries.enhanceValue(
        $self,
        $self.parents('li').filter(function () {
          return $(this).prop('class').match(/\bm_/) !== null
        })
      )
    }
    // console.groupEnd()
  })
}

function debugEnhanceSetConfig ($node, arg1) {
  if (typeof arg1 !== 'object') {
    return
  }
  config.set(arg1)
  // update log entries that have already been enhanced
  $node
    .find('.debug-log.enhanced')
    .closest('.debug')
    .trigger('config.debug.updated', 'linkFilesTemplate')
}

function debugEnhanceSidebar ($node, arg1) {
  if (arg1 === 'add') {
    sidebar.addMarkup($node)
  } else if (arg1 === 'open') {
    sidebar.open($node)
  } else if (arg1 === 'close') {
    sidebar.close($node)
  }
}

/**
 * Add <style> tag to head of document
 */
function addStyle (css) {
  var head = document.head || document.getElementsByTagName('head')[0]
  var style = document.createElement('style')
  style.type = 'text/css'
  head.appendChild(style)
  if (style.styleSheet) {
    // This is required for IE8 and below.
    style.styleSheet.cssText = css
    return
  }
  style.appendChild(document.createTextNode(css))
}

/**
 * For given css class, what is its font-family
 */
function getFontFamily (cssClass) {
  var span = document.createElement('span')
  var fontFamily = null
  span.className = 'fa'
  span.style.display = 'none'
  document.body.appendChild(span)
  fontFamily = window.getComputedStyle(span, null).getPropertyValue('font-family')
  document.body.removeChild(span)
  return fontFamily
}

function initClipboardJs () {
  /*
    Copy strings/floats/ints to clipboard when clicking
  */
  return new window.ClipboardJS('.debug .t_string, .debug .t_int, .debug .t_float, .debug .t_key', {
    target: function (trigger) {
      var range
      if ($(trigger).is('a')) {
        return $('<div>')[0]
      }
      if (window.getSelection().toString().length) {
        // text was being selected vs a click
        range = window.getSelection().getRangeAt(0)
        setTimeout(function () {
          // re-select
          window.getSelection().addRange(range)
        })
        return $('<div>')[0]
      }
      notify('Copied to clipboard')
      return trigger
    }
  })
}

function registerListeners ($root) {
  if (listenersRegistered) {
    return
  }
  $('body').on('animationend', '.debug-noti', function () {
    $(this).removeClass('animate').closest('.debug-noti-wrap').hide()
  })
  $('.debug').on('mousedown', '.debug a', function () {
    var beforeunload = window.onbeforeunload
    window.onbeforeunload = null
    window.setTimeout(function () {
      window.onbeforeunload = beforeunload
    }, 500)
  })
  listenersRegistered = true
}

function notify (html) {
  $('.debug-noti').html(html).addClass('animate').closest('.debug-noti-wrap').show()
}
