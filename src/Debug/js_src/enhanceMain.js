/**
 * Add primary Ui elements
 */

import $ from 'jquery'
import * as drawer from './drawer.js'
import * as filter from './filter.js'
import * as optionsMenu from './optionsDropdown.js'
import * as sidebar from './sidebar.js'

var config
var $root

export function init ($debugRoot) {
  $root = $debugRoot
  config = $root.data('config').get()
  updateMenuBar()
  addChannelToggles()
  addExpandAll()
  addNoti($('body'))
  enhanceErrorSummary()
  drawer.init($root)
  filter.init($root)
  sidebar.init($root)
  optionsMenu.init($root)
  addErrorIcons()
  $root.find('.loading').hide()
  $root.addClass('enhanced')
}

function updateMenuBar () {
  var $menuBar = $root.find('.debug-menu-bar')
  var nav = $menuBar.find('nav').length
    ? $menuBar.find('nav')[0].outerHTML
    : ''
  $menuBar.html('<span><i class="fa fa-bug"></i> PHPDebugConsole</span>' +
    nav +
    '<div class="float-right"></div>'
  )
}

function addChannelToggles () {
  var channelKeyRoot = $root.data('channelKeyRoot')
  var $log = $root.find('> .tab-panes > .tab-primary')
  var channels = $root.data('channels') || {}
  var $ul
  var $toggles
  if (!channelKeyRoot) {
    return
  }
  if (!channels[channelKeyRoot]) {
    return
  }
  $ul = buildChannelList(channels[channelKeyRoot].channels, channelKeyRoot)
  if ($ul.html().length) {
    $toggles = $('<fieldset />', {
      class: 'channels',
    })
      .append('<legend>Channels</legend>')
      .append($ul)
    $log.find('> .tab-body').prepend($toggles)
  }
}

function addErrorIcons () {
  var channelKeyRoot = $root.data('channelKeyRoot')
  var counts = {
    error: $root.find('.m_error[data-channel="' + channelKeyRoot + '.phpError"]').length,
    warn: $root.find('.m_warn[data-channel="' + channelKeyRoot + '.phpError"]').length
  }
  var $icon
  var $icons = $('<span>', { class: 'debug-error-counts' })
  $.each(['error', 'warn'], function (i, what) {
    if (counts[what] === 0) {
      return
    }
    $icon = $(config.iconsMethods['.m_' + what]).removeClass('fa-lg').addClass('text-' + what)
    $icons.append($icon).append($('<span>', {
      class: 'badge',
      html: counts[what]
    }))
  })
  $root.find('.debug-pull-tab').append($icons[0].outerHTML)
  $root.find('.debug-menu-bar .float-right').prepend($icons)
}

function addExpandAll () {
  var $expandAll = $('<button>', {
    class: 'expand-all'
  }).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups')
  var $logBody = $root.find('> .tab-panes > .tab-primary > .tab-body')

  // this is currently invoked before entries are enhance / empty class not yet added
  if ($logBody.find('.m_group:not(.empty)').length > 1) {
    $logBody.find('.debug-log-summary').before($expandAll)
  }
  $root.on('click', '.expand-all', function () {
    $(this).closest('.debug').find('.m_group:not(.expanded)').debugEnhance('expand')
    return false
  })
}

function addNoti ($root) {
  if ($root.find('.debug-noti-wrap').length) {
    return
  }
  $root.append('<div class="debug-noti-wrap">' +
      '<div class="debug-noti-table">' +
        '<div class="debug-noti"></div>' +
      '</div>' +
    '</div>')
}

/**
 * @return {jQuery} $ul
 */
export function buildChannelList (channels, keyRoot, checkedChannels, prepend) {
  var $lis = []
  var $ul = $('<ul class="list-unstyled">')
  /*
  console.warn('buildChannelList', {
    channels: channels,
    keyRoot: keyRoot,
    prepend: prepend,
  })
  */
  prepend = prepend || ''
  if ($.isArray(channels)) {
    channels = channelsToTree(channels)
  } else if (prepend.length === 0 && Object.keys(channels).length) {
    // start with root.   Add if there are other channels
    // console.log('buildChannelLi key root', keyRoot)
    $ul.append(buildChannelLi(
      {
        name: keyRoot,
        options: {},
      },
      keyRoot, // value
      true, // isChecked
      true // isRoot
    ))
  }
  $lis = buildChannelLis(channels, keyRoot, checkedChannels, prepend)
  for (var i = 0, len = $lis.length; i < len; i++) {
    $ul.append($lis[i])
  }
  return $ul
}

function buildChannelValue (channelKey, prepend, keyRoot) {
  var value = channelKey
  if (prepend) {
    value = prepend + channelKey
  } else if (value !== keyRoot) {
    value = keyRoot + '.' + value
  }
  return value
}

function buildChannelLis (channels, keyRoot, checkedChannels, prepend) {
  var $lis = []
  var channel
  var channelKeys = Object.keys(channels).sort(function (a, b) {
    return a.localeCompare(b)
  })
  $.each(channelKeys, function (i, channelKey) {
    if (channelKey === 'phpError') {
      // phpError is a special channel
      return
    }
    channel = channels[channelKey]
    channel.key = channelKey
    $lis.push(buildChannelLisIterator(channel, keyRoot, checkedChannels, prepend))
  })
  return $lis
}

function buildChannelLisIterator (channel, keyRoot, checkedChannels, prepend) {
  var value = buildChannelValue(channel.key, prepend, keyRoot)
  var $li = buildChannelLi(
    channel,
    value,
    checkedChannels !== undefined
      ? checkedChannels.indexOf(value) > -1
      : channel.options.show,
    channel.key === keyRoot
  )
  if (Object.keys(channel.channels).length) {
    $li.append(buildChannelList(channel.channels, keyRoot, checkedChannels, value + '.'))
  }
  return $li
}

/**
 * Build a single LI element without any children
 */
function buildChannelLi (channel, value, isChecked, isRoot) {
  var $label
  var $li
  $label = $('<label>', {
    class: 'toggle'
  }).append($('<input>', {
    checked: isChecked,
    'data-is-root': isRoot,
    'data-toggle': 'channel',
    type: 'checkbox',
    value: value
  })).append(channel.name)
  $label.toggleClass('active', isChecked)
  $li = $('<li>').append($label)
  if (channel.options.icon) {
    $li.find('input').after($('<i>', { class: channel.options.icon }))
  }
  return $li
}

function channelsToTree (channels) {
  var channelTree = {}
  var channel
  var ref
  var i
  var path
  for (i = 0; i < channels.length; i++) {
    ref = channelTree
    channel = channels[i]
    path = channel.key.split('.')
    if (path.length > 1 && path[0] === channels[0].name) {
      path.shift()
    }
    channelsToTreeWalkPath(channel, path, ref)
  }
  return channelTree
}

function channelsToTreeWalkPath (channel, path, channelTreeRef) {
  var i
  var options
  for (i = 0; i < path.length; i++) {
    options = i === path.length - 1
      ? {
        icon: channel.icon,
        show: channel.show
      }
      : {
        icon: null,
        show: null
      }
    if (!channelTreeRef[path[i]]) {
      channelTreeRef[path[i]] = {
        name: channel.name,
        options: options,
        channels: {}
      }
    }
    channelTreeRef = channelTreeRef[path[i]].channels
  }
}

/**
 * ErrorSummary should be considered deprecated
 */
function enhanceErrorSummary () {
  var $errorSummary = $root.find('.m_alert.error-summary')
  $errorSummary.find('h3:first-child').prepend(config.iconsMethods['.m_error'])
  $errorSummary.find('.in-console li[class*=error-]').each(function () {
    var category = $(this).attr('class').replace('error-', '')
    var html = $(this).html()
    var htmlReplace = '<li><label>' +
      '<input type="checkbox" checked data-toggle="error" data-count="' + $(this).data('count') + '" value="' + category + '" /> ' +
      html +
      '</label></li>'
    $(this).replaceWith(htmlReplace)
  })
  $errorSummary.find('.m_trace').debugEnhance()
}
