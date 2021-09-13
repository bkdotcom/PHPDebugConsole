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
  if (counts.error) {
    $icon = $(config.iconsMethods['.m_error']).removeClass('fa-lg').addClass('text-error')
    $icons.append($icon).append($('<span>', {
      class: 'badge',
      html: counts.error
    }))
  }
  if (counts.warn) {
    $icon = $(config.iconsMethods['.m_warn']).removeClass('fa-lg').addClass('text-warn')
    $icons.append($icon).append($('<span>', {
      class: 'badge',
      html: counts.warn
    }))
  }
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
  var $ul = $('<ul class="list-unstyled">')
  var channel
  var channelName = ''
  var channelNames = []
  var isChecked = true
  var value = ''
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
  channelNames = Object.keys(channels).sort(function (a, b) {
    return a.localeCompare(b)
  })
  for (var i = 0, len = channelNames.length; i < len; i++) {
    channelName = channelNames[i]
    if (channelName === 'phpError') {
      // phpError is a special channel
      continue
    }
    channel = channels[channelName]
    value = channelName
    if (prepend) {
      value = prepend + channelName
    } else if (value !== nameRoot) {
      value = nameRoot + '.' + value
    }
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
    $ul.append($li)
  }
  return $ul
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
  var i2
  var path
  for (i = 0; i < channels.length; i++) {
    ref = channelTree
    channel = channels[i]
    path = channel.name.split('.')
    if (path.length > 1 && path[0] === channels[0].name) {
      path.shift()
    }
    for (i2 = 0; i2 < path.length; i2++) {
      if (!ref[path[i2]]) {
        ref[path[i2]] = {
          options: {
            icon: i2 === path.length - 1 ? channel.icon : null,
            show: i2 === path.length - 1 ? channel.show : null
          },
          channels: {}
        }
      }
      ref = ref[path[i2]].channels
    }
  }
  return channelTree
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
