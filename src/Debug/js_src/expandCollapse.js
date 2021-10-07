/**
 * handle expanding/collapsing arrays, groups, & objects
 */

import $ from 'jquery'

var config

export function init ($delegateNode) {
  config = $delegateNode.data('config').get()
  $delegateNode.on('click', '[data-toggle=array]', onClickToggle)
  $delegateNode.on('click', '[data-toggle=group]', onClickToggle)
  $delegateNode.on('click', '[data-toggle=next]', function (e) {
    if ($(e.target).closest('a,button').length) {
      return
    }
    return onClickToggle.call(this)
  })
  $delegateNode.on('click', '[data-toggle=object]', onClickToggle)
  $delegateNode.on('collapsed.debug.group updated.debug.group', function (e) {
    groupUpdate($(e.target))
  })
  $delegateNode.on('expanded.debug.group', function (e) {
    var $target = $(e.target)
    $target.find('> .group-header > i:last-child').remove()
  })
}

function onClickToggle () {
  toggle(this)
  return false
}

/**
 * Collapse an array, group, or object
 *
 * @param jQueryObj $toggle   the toggle node
 * @param immediate immediate no annimation
 *
 * @return void
 */
export function collapse ($node, immediate) {
  var isToggle = $node.is('[data-toggle]')
  var what = isToggle
    ? $node.data('toggle')
    : $node.find('> *[data-toggle]').data('toggle')
  var $wrap = isToggle
    ? $node.parent()
    : $node
  var $toggle = isToggle
    ? $node
    : $wrap.find('> *[data-toggle]')
  var eventNameDone = 'collapsed.debug.' + what
  if (what === 'array') {
    $wrap.removeClass('expanded')
  } else if (['group', 'object'].indexOf(what) > -1) {
    collapseGroupObject($wrap, $toggle, immediate, eventNameDone)
  } else if (what === 'next') {
    collapseNext($toggle, immediate, eventNameDone)
  }
}

function collapseGroupObject ($wrap, $toggle, immediate, eventNameDone) {
  var $groupEndValue = $wrap.find('> .group-body > .m_groupEndValue > :last-child')
  if ($groupEndValue.length && $toggle.find('.group-label').last().nextAll().length === 0) {
    $toggle.find('.group-label').last()
      .after('<span class="t_operator"> : </span>' + $groupEndValue[0].outerHTML)
  }
  if (immediate) {
    collapseGroupObjectDone($wrap, $toggle, eventNameDone)
    return
  }
  $toggle.next().slideUp('fast', function () {
    collapseGroupObjectDone($wrap, $toggle, eventNameDone)
  })
}

function collapseGroupObjectDone ($wrap, $toggle, eventNameDone) {
  var icon = config.iconsExpand.expand
  $wrap.removeClass('expanded')
  iconUpdate($toggle, icon)
  $wrap.trigger(eventNameDone)
}

function collapseNext ($toggle, immediate, eventNameDone) {
  if (immediate) {
    $toggle.next().hide()
    collapseNextDone($toggle, eventNameDone)
    return
  }
  $toggle.next().slideUp('fast', function () {
    collapseNextDone($toggle, eventNameDone)
  })
}

function collapseNextDone ($toggle, eventNameDone) {
  var icon = config.iconsExpand.expand
  $toggle.removeClass('expanded')
  iconUpdate($toggle, icon)
  $toggle.next().trigger(eventNameDone)
}

export function expand ($node) {
  var icon = config.iconsExpand.collapse
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
  var $classTarget = what === 'next' // node that get's "expanded" class
    ? $toggle
    : $wrap
  var $evtTarget = what === 'next' // node we trigger events on
    ? $toggle.next()
    : $wrap
  var eventNameDone = 'expanded.debug.' + what
  // trigger while still hidden!
  //    no redraws
  $evtTarget.trigger('expand.debug.' + what)
  if (what === 'array') {
    $classTarget.addClass('expanded')
    $evtTarget.trigger(eventNameDone)
    return
  }
  // group, object, & next
  $toggle.next().slideDown('fast', function () {
    var $groupEndValue = $(this).find('> .m_groupEndValue')
    if ($groupEndValue.length) {
      // remove value from label
      $toggle.find('.group-label').last().nextAll().remove()
    }
    $classTarget.addClass('expanded')
    iconUpdate($toggle, icon)
    $evtTarget.trigger(eventNameDone)
  })
}

function groupErrorIconGet ($group) {
  var icon = ''
  var channel = $group.data('channel')
  var filter = function (i, node) {
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
 * Update expand/collapse icon and nested error/warn icon
 */
function groupUpdate ($group) {
  var selector = '> i:last-child'
  var $toggle = $group.find('> .group-header')
  var haveVis = $group.find('> .group-body > *').filter(function () {
    /*
      We return true only if visible
    */
    var $this = $(this)
    if ($this.hasClass('filter-hidden')) {
      return false
    }
    if ($this.is('.m_group.hide-if-empty.empty')) {
      return false
    }
    return true
  }).length > 0
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
  $.each(config.iconsExpand, function (i, className) {
    $icon.toggleClass(className, className === classNameNew)
  })
}

export function toggle (node) {
  var $node = $(node)
  var isToggle = $node.is('[data-toggle]')
  var what = isToggle
    ? $node.data('toggle')
    : $node.find('> *[data-toggle]').data('toggle')
  var $wrap = isToggle
    ? $node.parent()
    : $node
  var isExpanded = what === 'next'
    ? $node.hasClass('expanded')
    : $wrap.hasClass('expanded')
  if (what === 'group' && $wrap.hasClass('.empty')) {
    return
  }
  isExpanded
    ? collapse($node)
    : expand($node)
}
