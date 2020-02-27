/**
 * Add primary Ui elements
 */

import $ from 'jquery'
import * as drawer from './drawer.js'
import * as filter from './filter.js'
import * as optionsMenu from './optionsDropdown.js'
import * as sidebar from './sidebar.js'
// import { cookieGet, cookieRemove, cookieSet } from './http.js'

var config
var $root

export function init ($debugRoot) {
  $root = $debugRoot
  config = $root.data('config').get()
  $root.find('.debug-menu-bar').append($('<div />', { class: 'pull-right' }))
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
  var $log = $root.find('> .debug-tabs > .debug-tab-log')
  /*
  var channels = $.extend(
    {},
    {
      general: {
        options: $log.data('channels').general.options,
        channels: {}
      }
    },
    $log.data('channels').general.channels
  )
  */
  var channels = $log.data('channels')
  var $ul = buildChannelList(channels, $log.data('nameRoot'))
  var $toggles
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
  var counts = {
    error: $root.find('.m_error[data-channel=phpError]').length,
    warn: $root.find('.m_warn[data-channel=phpError]').length
  }
  var $icon
  var $icons = $('<span>', { class: 'debug-error-counts' })
  // var $badge = $('<span>', {class: 'badge'});
  if (counts.error) {
    $icon = $(config.iconsMethods['.m_error']).removeClass('fa-lg').addClass('text-error')
    // $root.find('.debug-pull-tab').append($icon);
    $icons.append($icon).append($('<span>', {
      class: 'badge',
      html: counts.error
    }))
  }
  if (counts.warn) {
    $icon = $(config.iconsMethods['.m_warn']).removeClass('fa-lg').addClass('text-warn')
    // $root.find('.debug-pull-tab').append($icon);
    $icons.append($icon).append($('<span>', {
      class: 'badge',
      html: counts.warn
    }))
  }
  $root.find('.debug-pull-tab').append($icons[0].outerHTML)
  $root.find('.debug-menu-bar .pull-right').prepend($icons)
}

function addExpandAll () {
  var $expandAll = $('<button>', {
    class: 'expand-all'
  }).html('<i class="fa fa-lg fa-plus"></i> Expand All Groups')
  var $logBody =  $root.find('> .debug-tabs > .debug-tab-log > .tab-body')

  // this is currently invoked before entries are enhance / empty class not yet added
  if ($logBody.find('.m_group:not(.empty)').length > 1) {
    $logBody.find('.debug-log-summary').before($expandAll)
  }
  $root.on('click', '.expand-all', function () {
    $(this).closest('.debug').find('.group-header').not('.expanded').each(function () {
      $(this).debugEnhance('expand')
    })
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

/*
function addPersistOption () {
  var $node;
  if (config.debugKey) {
    $node = $('<label class='debug-cookie' title='Add/remove debug cookie'><input type='checkbox'> Keep debug on</label>');
    if (cookieGet('debug') === options.debugKey) {
      $node.find('input').prop('checked', true);
    }
    $('input', $node).on('change', function () {
      var checked = $(this).is(':checked');
      if (checked) {
        cookieSet('debug', options.debugKey, 7);
      } else {
        cookieRemove('debug');
      }
    });
    $root.find('.debug-menu-bar').eq(0).prepend($node);
  }
}
*/

export function buildChannelList (channels, nameRoot, checkedChannels, prepend) {
  var $ul = $('<ul class="list-unstyled">')
  var $li
  var $label
  var channel
  var channelName = ''
  var isChecked = true
  prepend = prepend || ''
  if ($.isArray(channels)) {
    channels = channelsToTree(channels)
  }
  console.log('channels', channels);
  for (channelName in channels) {
    // console.log('channelName', channelName);
    if (channelName === 'phpError') {
      // phpError is a special channel
      continue
    }
    if (prepend.length === 0 && channelName !== nameRoot) {
      prepend = nameRoot + '.'
    }
    channel = channels[channelName]
    isChecked = checkedChannels !== undefined
      ? checkedChannels.indexOf(prepend + channelName) > -1
      : channel.options.show
    $label = $('<label>', {
      class: 'toggle'
    }).append($('<input>', {
      checked: isChecked,
      'data-is-root': channelName === nameRoot,
      'data-toggle': 'channel',
      type: 'checkbox',
      value: prepend + channelName
    })).append(channelName)
    $label.toggleClass('active', isChecked)
    $li = $('<li>').append($label)
    if (channel.options.icon) {
      $li.find('input').after($('<i>', { class: channel.options.icon }))
    }
    if (Object.keys(channel.channels).length) {
      $li.append(buildChannelList(channel.channels, nameRoot, checkedChannels, prepend + channelName + '.'))
    }
    $ul.append($li)
  }
  return $ul
}

function channelsToTree (channels) {
  var channelTree = {}
  var channel
  var ref
  var i
  var i2
  var path
  channels = channels.sort(function (a, b) {
    if (a.name < b.name) {
      return -1
    }
    if (a.name > b.name) {
      return 1
    }
    return 0
  })
  for (i = 0; i < channels.length; i++) {
    ref = channelTree
    channel = channels[i]
    path = channel.name.split('.')
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
