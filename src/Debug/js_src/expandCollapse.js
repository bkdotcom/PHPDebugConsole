/**
 * handle expanding/collapsing arrays, groups, & objects
 */

import $ from 'zest'
import { getNodeType } from './nodeType.js'

var config

export function init ($delegateNode) {
  config = $delegateNode.data('config').get()
  $delegateNode.on('click', '[data-toggle=array], [data-toggle=group], [data-toggle=object]', onClickToggle)
  $delegateNode.on('click', '[data-toggle=next]', function (e) {
    if ($(e.target).closest('a, button').length) {
      return
    }
    return onClickToggle.call(this)
  })
  $delegateNode.on('collapsed.debug.group updated.debug.group', function (e) {
    groupUpdate($(e.target))
  })
  $delegateNode.on('expanded.debug.group', function (e) {
    $(e.target).find('> .group-header > i:last-child').remove()
  })
}

/**
 * Collapse an array, group, or object
 *
 * @param jQueryObj $toggle   the toggle node
 * @param immediate immediate no animation
 *
 * @return void
 */
export function collapse ($node, immediate) {
  var info = getNodeInfo($node)
  var eventNameDone = 'collapsed.debug.' + info.what
  if (info.what === 'array') {
    info.$classTarget.removeClass('expanded')
  } else if (['group', 'object'].indexOf(info.what) > -1) {
    collapseGroupObject(info, immediate, eventNameDone)
  } else if (info.what === 'next') {
    collapseNext(info.$toggle, immediate, eventNameDone)
  }
}

export function expand ($node) {
  var icon = config.iconsExpand.collapse
  var info = getNodeInfo($node)
  var eventNameDone = 'expanded.debug.' + info.what
  // trigger while still hidden!
  //    no redraws
  info.$evtTarget.trigger('expand.debug.' + info.what)
  if (info.what === 'array') {
    info.$classTarget.addClass('expanded')
    info.$evtTarget.trigger(eventNameDone)
    return
  }
  // group, object, & next
  expandGroupObjNext(info, icon, eventNameDone)
}

export function toggle (node) {
  var $node = $(node)
  var info = getNodeInfo($node)
  var isExpanded = info.$classTarget.hasClass('expanded')
  if (info.what === 'group' && info.$wrap.hasClass('.empty')) {
    return
  }
  isExpanded
    ? collapse($node)
    : expand($node)
}

/**
 * Build the value displayed when group is collapsed
 */
function buildReturnVal ($return) {
  var type = getNodeType($return)
  var typeMore = type[1]
  type = type[0]
  if (['bool', 'callable', 'const', 'float', 'identifier', 'int', 'null', 'resource', 'unknown'].indexOf(type) > -1 || ['numeric', 'timestamp'].indexOf(typeMore) > -1) {
    return $return[0].outerHTML
  }
  if (type === 'string') {
    return buildReturnValString($return, typeMore)
  }
  if (type === 'object') {
    return buildReturnValObject($return)
  }
  if (type === 'array' && $return[0].textContent === 'array()') {
    return $return[0].outerHTML.replace('t_array', 't_array expanded')
  }
  return '<span class="t_keyword">' + type + '</span>'
}

function buildReturnValObject ($return) {
  var selectors = [
    '> .t_identifier', // initially collapsed and before enhanced with data-toggle
    '> [data-toggle] > .t_identifier',
    '> .classname',
    '> .t_const',
    '> [data-toggle] > .classname',
    '> [data-toggle] > .t_const',
  ]
  return $return.find(selectors.join(','))[0].outerHTML
}

function buildReturnValString ($return, typeMore) {
  if (typeMore === 'classname') {
    return $return[0].outerHTML
  }
  return typeMore
    ? '<span><span class="t_keyword">string</span><span class="text-muted">(' + typeMore + ')</span></span>'
    : ($return[0].innerHTML.indexOf('\n') < 0
      ? $return[0].outerHTML
      : '<span class="t_keyword">string</span>')
}

/**
 * Collapse group or object
 */
function collapseGroupObject (nodes, immediate, eventNameDone) {
  var $groupEndValue = nodes.$wrap.find('> .group-body > .m_groupEndValue > :last-child')
  var $afterLabel = nodes.$toggle.find('.group-label').last().nextAll().not('i')
  if ($groupEndValue.length && $afterLabel.length === 0) {
    nodes.$toggle.find('.group-label').last()
      .after('<span class="t_operator"> : </span>' + buildReturnVal($groupEndValue))
  }
  if (immediate) {
    return collapseDone(nodes, eventNameDone)
  }
  nodes.$target.slideUp('fast', function () {
    collapseDone(nodes, eventNameDone)
  })
}

function collapseNext (nodes, immediate, eventNameDone) {
  if (immediate) {
    nodes.$target.hide()
    return collapseDone(nodes, eventNameDone)
  }
  nodes.$target.slideUp('fast', function () {
    collapseDone(nodes, eventNameDone)
  })
}

function collapseDone (nodes, eventNameDone) {
  var icon = config.iconsExpand.expand
  nodes.$classTarget.removeClass('expanded')
  iconUpdate(nodes.$toggle, icon)
  nodes.$evtTarget.trigger(eventNameDone)
}

/**
 * @param {*} icon          toggle, classTarget, & evtTarget
 * @param {*} icon          the icon to update toggle with
 * @param {*} eventNameDone the event name
 */
function expandGroupObjNext (nodes, icon, eventNameDone) {
  nodes.$target.slideDown('fast', function () {
    var $groupEndValue = $(this).find('> .m_groupEndValue')
    if ($groupEndValue.length) {
      // remove value from label
      nodes.$toggle.find('.group-label').last().nextAll().remove()
    }
    nodes.$classTarget.addClass('expanded')
    iconUpdate(nodes.$toggle, icon)
    nodes.$evtTarget.trigger(eventNameDone)
  })
}

function groupErrorIconGet ($group) {
  var icon = ''
  var channel = $group.data('channel')
  var filter = function (node) {
    var $node = $(node)
    if ($node.hasClass('filter-hidden')) {
      // only collect hidden errors if of the same channel & channel also hidden
      return $group.hasClass('filter-hidden') && $node.data('channel') === channel
    }
    return true
  }
  if ($group.find('.m_error').filter(filter).length) {
    icon = config.iconsMethods['.m_error']
  } else if ($group.find('.m_warn').filter(filter).length) {
    icon = config.iconsMethods['.m_warn']
  }
  return icon
}

/**
 * Get node info for collapse/expand methods
 */
function getNodeInfo ($node) {
  var isToggle = $node.is('[data-toggle]')
  var what = isToggle
    ? $node.data('toggle')
    : ($node.find('> *[data-toggle]').data('toggle') || ($node.attr('class').match(/\bt_(\w+)/) || []).pop())
  var $wrap = isToggle
    ? $node.parent()
    : $node
  var $toggle = isToggle
    ? $node
    : $wrap.find('> *[data-toggle]')
  return {
    what: what,
    $wrap: $wrap,
    $toggle: $toggle,
    $classTarget: what === 'next' // node that get's "expanded" class
      ? $toggle
      : $wrap,
    $evtTarget: what === 'next' // node we trigger events on
      ? $toggle.next()
      : $wrap,
    $target: what === 'object'
      ? $wrap.find('> .object-inner')
      : $toggle.next()
  }
}

/**
 * Does group have any visible children
 *
 * @param $group .m_group jQuery obj
 *
 * @return bool
 */
function groupHasVis ($group) {
  var $children = $group.find('> .group-body > *')
  var count
  var i
  for (i = 0, count = $children.length; i < count; i++) {
    if (groupHasVisTestChild($children.eq(i))) {
      return true
    }
  }
  return false
}

function groupHasVisTestChild ($child) {
  if ($child.hasClass('filter-hidden')) {
    return $child.hasClass('m_group')
      ? groupHasVis($child)
      : false
  }
  if ($child.is('.m_group.hide-if-empty.empty')) {
    return false
  }
  return true
}

/**
 * Update expand/collapse icon and nested error/warn icon
 */
function groupUpdate ($group) {
  var selector = '> i:last-child'
  var $toggle = $group.find('> .group-header')
  var haveVis = groupHasVis($group)
  var icon = groupErrorIconGet($group)
  var isExpanded = $group.hasClass('expanded')
  // console.log('groupUpdate', $toggle.text(), icon, haveVis)
  $group.toggleClass('empty', !haveVis) // 'empty' class just affects cursor
  iconUpdate($toggle, config.iconsExpand[isExpanded ? 'collapse' : 'expand'])
  if (!icon || isExpanded) {
    $toggle.find(selector).remove()
    return
  }
  if ($toggle.find(selector).length) {
    $toggle.find(selector).replaceWith(icon)
    return
  }
  $toggle.append(icon)
}

function iconUpdate ($toggle, classNameNew) {
  var $icon = $toggle.children('i').eq(0)
  if ($toggle.hasClass('group-header') && $toggle.parent().hasClass('empty')) {
    classNameNew = config.iconsExpand.empty
  }
  if ($toggle.is('.t_object-expand, .t_object-collapse')) {
    // prop-only object has two separate toggles (like array)... correct one shown via css
    return
  }
  $.each(config.iconsExpand, function (className) {
    $icon.toggleClass(className, className === classNameNew)
  })
}

function onClickToggle () {
  toggle(this)
  return false
}
