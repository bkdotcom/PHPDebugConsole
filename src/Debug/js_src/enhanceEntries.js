import $ from 'microDom'
import * as enhanceArray from './enhanceArray.js'
import * as enhanceObject from './enhanceObject.js'
import * as enhanceSubscribers from './enhanceSubscribers.js'
import * as tableSort from './tableSort.js'
import * as fileLinks from './FileLinks.js'

var config
var toExpandQueue = []
var processingQueue = false

export function init ($root) {
  config = $root.data('config').get()
  enhanceArray.init($root)
  enhanceObject.init($root)
  enhanceSubscribers.init($root, enhanceValue, enhanceObject)
  fileLinks.init($root)
}

/**
 * Enhance log entries inside .group-body
 */
export function enhanceEntries ($node) {
  // console.log('enhanceEntries', $node[0])
  var $parent = $node.parent()
  var show = !$parent.hasClass('m_group') || $parent.hasClass('expanded')
  // temporarily hide when enhancing... minimize redraws
  $node.hide()
  $node.children().each(function () {
    enhanceEntry($(this))
  })
  if (show) {
    $node.show().trigger('expanded.debug.group')
  }
  processExpandQueue()
  if ($node.parent().hasClass('m_group') === false) {
    // only add .enhanced to root .group-body
    $node.addClass('enhanced')
  }
}

/**
 * Enhance a single log entry
 * we don't enhance strings by default (add showmore).. needs to be visible to calc height
 */
export function enhanceEntry ($entry) {
  // console.log('enhanceEntry', $entry[0])
  if ($entry.hasClass('enhanced')) {
    return
  } else if ($entry.hasClass('m_group')) {
    enhanceGroup($entry)
  } else if ($entry.hasClass('filter-hidden')) {
    return
  } else if ($entry.is('.m_table, .m_trace')) {
    enhanceEntryTabular($entry)
  } else {
    enhanceEntryDefault($entry)
  }
  $entry.addClass('enhanced')
  $entry.trigger('enhanced.debug')
}

export function enhanceValue (node, $entry) {
  var $node = $(node)
  if ($node.is('.t_array')) {
    enhanceArray.enhance($node)
  } else if ($node.is('.t_object')) {
    enhanceObject.enhance($node)
  } else if ($node.is('table')) {
    tableSort.makeSortable($node)
  } else if ($node.is('.t_string')) {
    fileLinks.create($entry, $node)
  } else if ($node.is('.string-encoded.tabs-container')) {
    // console.warn('enhanceStringEncoded', $node)
    enhanceValue($node.find('> .tab-pane.active > *'), $entry)
  }
}

/**
 * add font-awesome icons
 */
function addIcons ($node) {
  var $icon = determineIcon($node)
  addIconsMisc($node)
  if (!$icon) {
    return
  }
  if ($node.hasClass('m_group')) {
    // custom icon..   add to .group-label
    $node = $node.find('> .group-header .group-label').eq(0)
  } else if ($node.find('> table').length) {
    $node = addIconsTableNode($node)
  }
  if ($node.find('> i:first-child').hasClass($icon.attr('class'))) {
    // already have icon
    return
  }
  $node.prepend($icon)
}

function addIconsMisc ($node) {
  var $icon
  var $node2
  var selector
  for (selector in config.iconsMisc) {
    $node2 = $node.find(selector)
    if ($node2.length === 0) {
      continue
    }
    $icon = $(config.iconsMisc[selector])
    if ($node2.find('> i:first-child').hasClass($icon.attr('class'))) {
      // already have icon
      $icon = null
      continue
    }
    $node2.prepend($icon)
    $icon = null
  }
}

/**
 * table... we'll prepend icon to caption
 *
 * @return jQuery caption node
 */
function addIconsTableNode ($node) {
  var isNested = $node.parent('.no-indent').length > 0
  var $caption = $node.find('> table > caption')
  if ($caption.length === 0 && isNested === false) {
    // add caption
    $caption = $('<caption>')
    $node.find('> table').prepend($caption)
  }
  return $caption
}

function determineIcon ($node) {
  var $icon
  var $node2
  if ($node.data('icon')) {
    return $node.data('icon').match('<')
      ? $($node.data('icon'))
      : $('<i>').addClass($node.data('icon'))
  }
  if ($node.hasClass('m_group')) {
    return $icon // undefined / groupIcon will be added separately
  }
  $node2 = $node.hasClass('group-header')
    ? $node.parent()
    : $node
  return determineIconFromConfig($node2)
}

function determineIconFromConfig ($node) {
  var $icon
  var selector
  for (selector in config.iconsMethods) {
    if ($node.is(selector)) {
      $icon = $(config.iconsMethods[selector])
      break
    }
  }
  return $icon
}

function enhanceEntryDefault ($entry) {
  // regular log-type entry
  var title
  if ($entry.data('file')) {
    if (!$entry.attr('title')) {
      title = $entry.data('file') + ': line ' + $entry.data('line')
      if ($entry.data('evalline')) {
        title += ' (eval\'d line ' + $entry.data('evalline') + ')'
      }
      $entry.attr('title', title)
    }
    fileLinks.create($entry)
  }
  addIcons($entry)
  $entry.children().each(function () {
    enhanceValue(this, $entry)
  })
}

function enhanceEntryTabular ($entry) {
  fileLinks.create($entry)
  addIcons($entry)
  if ($entry.hasClass('m_table')) {
    $entry.find('> table > tbody > tr > td').each(function () {
      enhanceValue(this, $entry)
    })
  }
  // table may have a expand collapse row that's initially expanded
  //   trigger expanded event  (so, trace context args are enhanced, etc)
  $entry.find('tbody > tr.expanded').next().trigger('expanded.debug.next')
  tableSort.makeSortable($entry.find('> table'))
}

function enhanceGroup ($group) {
  // console.log('enhanceGroup', $group[0])
  var $toggle = $group.find('> .group-header')
  var $target = $toggle.next()
  addIcons($group) // custom data-icon
  addIcons($toggle) // expand/collapse
  $toggle.attr('data-toggle', 'group')
  $toggle.find('.t_array, .t_object').each(function () {
    $(this).data('expand', false)
    enhanceValue(this, $group)
  })
  /*
  $.each(['level-error', 'level-info', 'level-warn'], function (i, classname) {
    var $toggleIcon
    if ($group.hasClass(classname)) {
      $toggleIcon = $toggle.children('i').eq(0)
      $toggle.wrapInner('<span class="' + classname + '"></span>')
      $toggle.prepend($toggleIcon) // move icon
    }
  })
  */
  if (
    $group.hasClass('expanded') ||
    $target.find('.m_error, .m_warn').not('.filter-hidden').not('[data-uncollapse=false]').length
  ) {
    toExpandQueue.push($toggle)
    return
  }
  $toggle.debugEnhance('collapse', true)
}

function processExpandQueue () {
  if (processingQueue) {
    return
  }
  processingQueue = true
  while (toExpandQueue.length) {
    toExpandQueue.shift().debugEnhance('expand')
  }
  processingQueue = false
}
