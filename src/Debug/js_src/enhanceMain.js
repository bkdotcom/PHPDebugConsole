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
  $root.find('.debug-menu-bar').append($('<div />', { class: 'float-right' }))
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

function addChannelToggles () {
  var channelNameRoot = $root.data('channelNameRoot')
  var $log = $root.find('> .tab-panes > .tab-primary')
  var channels = $root.data('channels') || {}
  var $ul
  var $toggles
  if (!channelNameRoot) {
    return
  }
  if (!channels[channelNameRoot]) {
    return
  }
  $ul = buildChannelList(channels[channelNameRoot].channels, channelNameRoot)
  if ($ul.html().length) {
    $toggles = $('<fieldset />', {
      class: 'channels'
    })
      .append('<legend>Channels</legend>')
      .append($ul)
    $log.find('> .tab-body').prepend($toggles)
  }
}

function addErrorIcons () {
  var channelNameRoot = $root.data('channelNameRoot')
  var counts = {
    error: $root.find('.m_error[data-channel="' + channelNameRoot + '.phpError"]').length,
    warn: $root.find('.m_warn[data-channel="' + channelNameRoot + '.phpError"]').length
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

export function buildChannelList (channels, nameRoot, checkedChannels, prepend) {
  var $li
  var $lis = []
  var $ul = $('<ul class="list-unstyled">')
  /*
  console.log('buildChannelList', {
    nameRoot: nameRoot,
    prepend: prepend,
    channels: channels
  })
  */
  prepend = prepend || ''
  if ($.isArray(channels)) {
    channels = channelsToTree(channels)
  } else if (prepend.length === 0 && Object.keys(channels).length) {
    // start with (add) if there are other channels
    // console.log('buildChannelLi name root', nameRoot)
    $li = buildChannelLi(
      nameRoot,
      nameRoot,
      true,
      true,
      {}
    )
    $ul.append($li)
  }
  $lis = buildChannelLis(channels, nameRoot, checkedChannels, prepend)
  for (var i = 0, len = $lis.length; i < len; i++) {
    $ul.append($lis[i])
  }
  return $ul
}

function buildChannelValue (channelName, prepend, nameRoot) {
  var value = channelName
  if (prepend) {
    value = prepend + channelName
  } else if (value !== nameRoot) {
    value = nameRoot + '.' + value
  }
  return value
}

function buildChannelLis (channels, nameRoot, checkedChannels, prepend) {
  var $li
  var $lis = []
  var channel
  var channelName = ''
  var channelNames = Object.keys(channels).sort(function (a, b) {
    return a.localeCompare(b)
  })
  var isChecked = true
  var value
  for (var i = 0, len = channelNames.length; i < len; i++) {
    channelName = channelNames[i]
    if (channelName === 'phpError') {
      // phpError is a special channel
      continue
    }
    channel = channels[channelName]
    value = buildChannelValue(channelName, prepend, nameRoot)
    isChecked = checkedChannels !== undefined
      ? checkedChannels.indexOf(value) > -1
      : channel.options.show
    $li = buildChannelLi(
      channelName,
      value,
      isChecked,
      false,
      channel.options
    )
    if (Object.keys(channel.channels).length) {
      $li.append(buildChannelList(channel.channels, nameRoot, checkedChannels, value + '.'))
    }
    $lis.push($li)
  }
  return $lis
}

function buildChannelLi (channelName, value, isChecked, isRoot, options) {
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
  })).append(channelName)
  $label.toggleClass('active', isChecked)
  $li = $('<li>').append($label)
  if (options.icon) {
    $li.find('input').after($('<i>', { class: options.icon }))
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
    path = channel.name.split('.')
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
        options: options,
        channels: {}
      }
    }
    channelTreeRef = channelTreeRef[path[i]].channels
  }
}

function enhanceErrorSummary () {
  var $errorSummary = $root.find('.m_alert.error-summary')
  $errorSummary.find('h3:first-child').prepend(config.iconsMethods['.m_error'])
  $errorSummary.find('li[class*=error-]').each(function () {
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
