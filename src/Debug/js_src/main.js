/**
 * Enhance debug output
 *    Add expand/collapse functionality to groups, arrays, & objects
 *    Add FontAwesome icons
 */

import $ from 'jquery' // external global
import * as enhanceEntries from './enhanceEntries.js'
import * as enhanceMain from './enhanceMain.js'
import * as expandCollapse from './expandCollapse.js'
import * as http from './http.js' // cookie & query utils
import * as sidebar from './sidebar.js'
import * as tabs from './tabs.js'
import { Config } from './config.js'
import loadDeps from './loadDeps.js'

var listenersRegistered = false
var config = new Config({
  fontAwesomeCss: '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
  // jQuerySrc: '//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
  clipboardSrc: '//cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.4/clipboard.min.js',
  iconsExpand: {
    expand: 'fa-plus-square-o',
    collapse: 'fa-minus-square-o',
    empty: 'fa-square-o'
  },
  iconsMisc: {
    '.timestamp': '<i class="fa fa-calendar"></i>'
  },
  iconsArray: {
    '> .array-inner > li > .exclude-count': '<i class="fa fa-eye-slash"></i>'
  },
  iconsObject: {
    '> .info.magic': '<i class="fa fa-fw fa-magic"></i>',
    '> .method.magic': '<i class="fa fa-fw fa-magic" title="magic method"></i>',
    '> .method.deprecated': '<i class="fa fa-fw fa-arrow-down" title="Deprecated"></i>',
    '> .method.inherited': '<i class="fa fa-fw fa-clone" title="Inherited"></i>',
    '> .property.debuginfo-value': '<i class="fa fa-eye" title="via __debugInfo()"></i>',
    '> .property.debuginfo-excluded': '<i class="fa fa-eye-slash" title="not included in __debugInfo"></i>',
    '> .property.private-ancestor': '<i class="fa fa-lock" title="private ancestor"></i>',
    '> .property > .t_modifier_magic': '<i class="fa fa-magic" title="magic property"></i>',
    '> .property > .t_modifier_magic-read': '<i class="fa fa-magic" title="magic property"></i>',
    '> .property > .t_modifier_magic-write': '<i class="fa fa-magic" title="magic property"></i>',
    '[data-toggle=vis][data-vis=private]': '<i class="fa fa-user-secret"></i>',
    '[data-toggle=vis][data-vis=protected]': '<i class="fa fa-shield"></i>',
    '[data-toggle=vis][data-vis=debuginfo-excluded]': '<i class="fa fa-eye-slash"></i>',
    '[data-toggle=vis][data-vis=inherited]': '<i class="fa fa-clone"></i>'
  },
  // debug methods (not object methods)
  iconsMethods: {
    '.group-header': '<i class="fa fa-lg fa-minus-square-o"></i>',
    '.m_assert': '<i class="fa-lg"><b>&ne;</b></i>',
    '.m_clear': '<i class="fa fa-lg fa-ban"></i>',
    '.m_count': '<i class="fa fa-lg fa-plus-circle"></i>',
    '.m_countReset': '<i class="fa fa-lg fa-plus-circle"></i>',
    '.m_error': '<i class="fa fa-lg fa-times-circle"></i>',
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
  cssFontAwesome5: '' +
    '.debug .fa-bell-o:before { content:"\\f0f3"; font-weight:400; }' +
    '.debug .fa-calendar:before { content:"\\f073"; }' +
    '.debug .fa-clock-o:before { content:"\\f017"; font-weight:400; }' +
    '.debug .fa-clone:before { content:"\\f24d"; font-weight:400; }' +
    '.debug .fa-envelope-o:before { content:"\\f0e0"; font-weight:400; }' +
    '.debug .fa-external-link:before { content:"\\f35d"; }' +
    '.debug .fa-exchange:before { content:"\\f362"; }' +
    '.debug .fa-eye-slash:before { content:"\\f070"; font-weight:400; }' +
    '.debug .fa-files-o:before { content:"\\f0c5"; font-weight:400; }' +
    '.debug .fa-file-text-o:before { content:"\\f15c"; font-weight:400; }' +
    '.debug .fa-minus-square-o:before { content:"\\f146"; font-weight:400; }' +
    '.debug .fa-pie-chart:before { content:"\\f200"; }' +
    '.debug .fa-plus-square-o:before { content:"\\f0fe"; font-weight:400; }' +
    '.debug .fa-shield:before { content:"\\f3ed"; }' +
    '.debug .fa-square-o:before { content:"\\f0c8"; font-weight:400; }' +
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
  /*
  {
    src: options.jQuerySrc,
    onLoaded: start,
    check: function () {
      return typeof window.jQuery !== 'undefined'
    }
  },
  */
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

/*
function getSelectedText () {
  var text = ''
  if (typeof window.getSelection !== 'undefined') {
    text = window.getSelection().toString()
  } else if (typeof document.selection !== 'undefined' && document.selection.type === 'Text') {
    text = document.selection.createRange().text
  }
  return text
}
*/

$.fn.debugEnhance = function (method, arg1, arg2) {
  // console.warn('debugEnhance', method, this)
  var $self = this
  // var dataOptions = {}
  // var lsOptions = {} // localStorage options
  // var options = {}
  if (method === 'sidebar') {
    if (arg1 === 'add') {
      sidebar.addMarkup($self)
    } else if (arg1 === 'open') {
      sidebar.open($self)
    } else if (arg1 === 'close') {
      sidebar.close($self)
    }
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
    var conf = new Config(config.get(), 'phpDebugConsole')
    $self.data('config', conf)
    conf.set($self.eq(0).data('options') || {})
    if (typeof arg1 === 'object') {
      conf.set(arg1)
    }
    tabs.init($self)
    enhanceEntries.init($self)
    expandCollapse.init($self)
    registerListeners($self)
    enhanceMain.init($self)
    if (!conf.get('drawer')) {
      $self.debugEnhance()
    }
  } else if (method === 'setConfig') {
    if (typeof arg1 === 'object') {
      config.set(arg1)
      // update logs that have already been enhanced
      $(this)
        .find('.debug-log.enhanced')
        .closest('.debug')
        .trigger('config.debug.updated', 'linkFilesTemplate')
    }
  } else {
    this.each(function () {
      var $self = $(this)
      // console.log('debugEnhance', this, $self.is('.enhanced'))
      if ($self.is('.debug')) {
        // console.warn('debugEnhance() : .debug')
        $self.find('.debug-menu-bar > nav, .debug-tabs').show()
        $self.find('.m_alert, .debug-log-summary, .debug-log').debugEnhance()
      } else if (!$self.is('.enhanced')) {
        console.group('debugEnhance')
        console.warn('log', this)
        if ($self.is('.group-body')) {
          // console.warn('debugEnhance() : .group-body', $self)
          enhanceEntries.enhanceEntries($self)
        } else {
          // log entry assumed
          // console.warn('debugEnhance() : entry')
          enhanceEntries.enhanceEntry($self) // true
        }
        console.groupEnd()
      }
    })
  }
  return this
}

$(function () {
  $('.debug').each(function () {
    $(this).debugEnhance('init')
    // $(this).find('.m_alert, .debug-log-summary, .debug-log').debugEnhance()
  })
})

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
