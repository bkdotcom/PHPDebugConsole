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
import * as http from './http.js' // cookie & query utils
import * as sidebar from './sidebar.js'
import * as tabs from './tabs.js'
import * as tooltip from './Tooltip.js'
import { Config } from './config.js'
import loadDeps from './loadDeps.js'

var listenersRegistered = false
var config = new Config({
  fontAwesomeCss: '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
  clipboardSrc: '//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js',
  iconsExpand: {
    expand: 'fa-plus-square-o',
    collapse: 'fa-minus-square-o',
    empty: 'fa-square-o'
  },
  iconsMisc: {
    '.string-encoded': '<i class="fa fa-barcode"></i>',
    '.timestamp': '<i class="fa fa-calendar"></i>'
  },
  iconsArray: {
    '> .array-inner > li > .exclude-count': '<i class="fa fa-eye-slash"></i>'
  },
  iconsObject: {
    '> .t_modifier_final': '<i class="fa fa-hand-stop-o"></i>',
    '> .t_modifier_readonly': '<span class="fa-stack">' +
      '<i class="fa fa-pencil fa-stack-1x"></i>' +
      '<i class="fa fa-ban fa-flip-horizontal fa-stack-2x text-muted"></i>' +
      '</span>',
    '> .info.magic': '<i class="fa fa-fw fa-magic"></i>',
    'parent:not(.groupByInheritance) > dd[data-inherited-from]:not(.private-ancestor)': '<i class="fa fa-fw fa-clone" title="Inherited"></i>',
    'parent:not(.groupByInheritance) > dd.private-ancestor': '<i class="fa fa-lock" title="Private ancestor"></i>',
    '> dd[data-attributes]': '<i class="fa fa-hashtag" title="Attributes"></i>',
    '> dd[data-declared-prev]': '<i class="fa fa-fw fa-repeat" title="Overrides"></i>',
    '> .method.isDeprecated': '<i class="fa fa-fw fa-arrow-down" title="Deprecated"></i>',
    '> .method > .t_modifier_magic': '<i class="fa fa-magic" title="magic method"></i>',
    '> .method > .t_modifier_final': '<i class="fa fa-hand-stop-o"></i>',
    '> .method > .parameter.isPromoted': '<i class="fa fa-arrow-up" title="Promoted"></i>',
    '> .method > .parameter[data-attributes]': '<i class="fa fa-hashtag" title="Attributes"></i>',
    '> .method[data-implements]': '<i class="fa fa-handshake-o" title="Implements"></i>',
    '> .method[data-throws]': '<i class="fa fa-flag" title="Throws"></i>',
    '> .property.debuginfo-value': '<i class="fa fa-eye" title="via __debugInfo()"></i>',
    '> .property.debuginfo-excluded': '<i class="fa fa-eye-slash" title="not included in __debugInfo"></i>',
    '> .property.isDynamic': '<i class="fa fa-warning" title="Dynamic"></i>',
    '> .property.isPromoted': '<i class="fa fa-arrow-up" title="Promoted"></i>',
    '> .property > .t_modifier_magic': '<i class="fa fa-magic" title="magic property"></i>',
    '> .property > .t_modifier_magic-read': '<i class="fa fa-magic" title="magic property"></i>',
    '> .property > .t_modifier_magic-write': '<i class="fa fa-magic" title="magic property"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=private]': '<i class="fa fa-user-secret"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=protected]': '<i class="fa fa-shield"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=debuginfo-excluded]': '<i class="fa fa-eye-slash"></i>',
    '> .vis-toggles > span[data-toggle=vis][data-vis=inherited]': '<i class="fa fa-clone"></i>'
  },
  // debug methods (not object methods)
  iconsMethods: {
    '.m_assert': '<i class="fa-lg"><b>&ne;</b></i>',
    '.m_clear': '<i class="fa fa-lg fa-ban"></i>',
    '.m_count': '<i class="fa fa-lg fa-plus-circle"></i>',
    '.m_countReset': '<i class="fa fa-lg fa-plus-circle"></i>',
    '.m_error': '<i class="fa fa-lg fa-times-circle"></i>',
    '.m_group.expanded': '<i class="fa fa-lg fa-minus-square-o"></i>',
    '.m_group': '<i class="fa fa-lg fa-plus-square-o"></i>',
    '.m_info': '<i class="fa fa-lg fa-info-circle"></i>',
    '.m_profile': '<i class="fa fa-lg fa-pie-chart"></i>',
    '.m_profileEnd': '<i class="fa fa-lg fa-pie-chart"></i>',
    '.m_time': '<i class="fa fa-lg fa-clock-o"></i>',
    '.m_timeLog': '<i class="fa fa-lg fa-clock-o"></i>',
    '.m_trace': '<i class="fa fa-list"></i>',
    '.m_warn': '<i class="fa fa-lg fa-warning"></i>'
  },
  debugKey: getDebugKey(),
  drawer: false,
  persistDrawer: false,
  linkFiles: false,
  linkFilesTemplate: 'subl://open?url=file://%file&line=%line',
  useLocalStorage: true,
  tooltip: true,
  cssFontAwesome5: '' +
    '.debug .fa-bell-o:before { content:"\\f0f3"; font-weight:400; }' +
    '.debug .fa-calendar:before { content:"\\f073"; }' +
    '.debug .fa-clock-o:before { content:"\\f017"; font-weight:400; }' +
    '.debug .fa-clone:before { content:"\\f24d"; font-weight:400; }' +
    '.debug .fa-envelope-o:before { content:"\\f0e0"; font-weight:400; }' +
    '.debug .fa-exchange:before { content:"\\f362"; }' +
    '.debug .fa-external-link:before { content:"\\f35d"; }' +
    '.debug .fa-eye-slash:before { content:"\\f070"; font-weight:400; }' +
    '.debug .fa-file-code-o:before { content:"\\f1c9"; font-weight:400; }' +
    '.debug .fa-file-text-o:before { content:"\\f15c"; font-weight:400; }' +
    '.debug .fa-files-o:before { content:"\\f0c5"; font-weight:400; }' +
    '.debug .fa-hand-stop-o:before { content:"\\f256"; font-weight:400; }' +
    '.debug .fa-minus-square-o:before { content:"\\f146"; font-weight:400; }' +
    '.debug .fa-pencil:before { content:"\\f303" }' +
    '.debug .fa-pie-chart:before { content:"\\f200"; }' +
    '.debug .fa-plus-square-o:before { content:"\\f0fe"; font-weight:400; }' +
    '.debug .fa-shield:before { content:"\\f3ed"; }' +
    '.debug .fa-square-o:before { content:"\\f0c8"; font-weight:400; }' +
    '.debug .fa-user-o:before { content:"\\f007"; }' +
    '.debug .fa-warning:before { content:"\\f071"; }' +
    '.debug .fa.fa-github { font-family: "Font Awesome 5 Brands"; }'
}, 'phpDebugConsole')

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
        $self.parents('li').filter(function () {
          return $(this).prop('class').match(/\bm_/) !== null
        }),
        $self
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

function getDebugKey () {
  var key = null
  var queryParams = http.queryDecode()
  var cookieValue = http.cookieGet('debug')
  if (typeof queryParams.debug !== 'undefined') {
    key = queryParams.debug
  } else if (cookieValue) {
    key = cookieValue
  }
  return key
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
