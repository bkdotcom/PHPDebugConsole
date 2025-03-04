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

export function setCfg (cfg) {
  config.set(cfg)
}

$.fn.debugEnhance = function (method, arg1, arg2) {
  if (method === 'buildChannelList') {
    // buildChannelList is a utility function that can be called without a jQuery object
    return enhanceMain.buildChannelList(arg1, arg2, arguments[3])
  }
  this.each(function () {
    var $node = $(this)
    switch (method) {
      case 'sidebar':
        return debugEnhanceSidebar($node, arg1)
      case 'collapse':
        return expandCollapse.collapse($node, arg1)
      case 'expand':
        return expandCollapse.expand($node)
      case 'init':
        return debugEnhanceInit($node, arg1)
      case 'setConfig':
        return debugEnhanceSetConfig($node, arg1)
      default:
        return debugEnhanceDefault($node)
    }
  })
  return this
}

$(function () {
  $('.debug').debugEnhance('init')
  window.matchMedia('(prefers-color-scheme: dark)').onchange = function (e) {
    $('.debug.debug-drawer').attr('data-theme', config.themeGet())
  }
})

function debugEnhanceInit ($node, arg1) {
  $node.data('config', config)
  config.set($node.eq(0).data('options') || {})
  if (typeof arg1 === 'object') {
    config.set(arg1)
  }
  tabs.init($node)
  if (config.get('tooltip')) {
    tooltip.init($node)
  }
  enhanceEntries.init($node)
  expandCollapse.init($node)
  registerListeners($node)
  enhanceMain.init($node)
  if (!config.get('drawer')) {
    $node.debugEnhance()
  }
  if ($node.hasClass('debug-drawer')) {
    $node.attr('data-theme', config.themeGet())
  }
  $node.trigger('init.debug')
}

function debugEnhanceDefault ($node) {
  var $parentLis = {}
  if ($node.hasClass('debug')) {
    // console.warn('debugEnhance() : .debug')
    $node.find('.debug-menu-bar > nav, .tab-panes').show()
    $node.find('.tab-pane.active')
      .find('.m_alert, .debug-log-summary, .debug-log')
      .debugEnhance()
    $node.trigger('refresh.debug')
    return
  }
  if ($node.hasClass('filter-hidden') && $node.hasClass('m_group') === false) {
    return
  }
  if ($node.hasClass('group-body')) {
    enhanceEntries.enhanceEntries($node)
  } else if ($node.is('li, div') && $node.prop('class').match(/\bm_/) !== null) {
    // logEntry  (alerts use <div>)
    enhanceEntries.enhanceEntry($node)
  } else if ($node.prop('class').match(/\bt_/)) {
    // value
    $parentLis = $node.parents('li').filter(function () {
      return $(this).prop('class').match(/\bm_/) !== null
    })
    enhanceEntries.enhanceValue($node, $parentLis)
  }
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
    .add($node)
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
  return new window.ClipboardJS('.debug .t_float, .debug .t_identifier, .debug .t_int, .debug .t_key, .debug .t_string', {
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
