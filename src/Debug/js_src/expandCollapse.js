/**
 * handle expanding/collapsing arrays, groups, & objects
 */

import $ from 'jquery'

var config

export function init ($delegateNode) {
  config = $delegateNode.data('config').get()
  $delegateNode.on('click', '[data-toggle=array]', function () {
    toggle(this)
    return false
  })
  $delegateNode.on('click', '[data-toggle=group]', function () {
    toggle(this)
    return false
  })
  $delegateNode.on('click', '[data-toggle=object]', function () {
    toggle(this)
    return false
  })
  $delegateNode.on('click', '[data-toggle=next]', function (e) {
    if ($(e.target).closest('a,button').length) {
      return
    }
    toggle(this)
    return false
  })
  $delegateNode.on('collapsed.debug.group', function (e) {
    groupErrorIconUpdate($(e.target).prev())
  })
}

/**
 * Collapse an array, group, or object
 *
 * @param jQueryObj $toggle   the toggle node
 * @param immediate immediate no annimation
 *
 * @return void
 */
export function collapse ($toggle, immediate) {
  var $target = $toggle.next()
  var $groupEndValue
  var what = 'array'
  var icon = config.iconsExpand.expand
  if ($toggle.is('[data-toggle=array]')) {
    $target = $toggle.closest('.t_array')
    $target.removeClass('expanded')
  } else {
    if ($toggle.is('[data-toggle=group]')) {
      $groupEndValue = $target.find('> .m_groupEndValue > :last-child')
      if ($groupEndValue.length && $toggle.find('.group-label').last().nextAll().length === 0) {
        $toggle.find('.group-label').last().after('<span class="t_operator"> : </span>' + $groupEndValue[0].outerHTML)
      }
      what = 'group'
    } else {
      what = 'object'
    }
    $toggle.removeClass('expanded')
    if (immediate) {
      $target.hide()
      iconUpdate($toggle, icon)
    } else {
      $target.slideUp('fast', function () {
        iconUpdate($toggle, icon)
      })
    }
  }
  $target.trigger('collapsed.debug.' + what)
}

export function expand ($toggleOrTarget) {
  // console.warn('expand', $toggleOrTarget)
  var isToggle = $toggleOrTarget.is('[data-toggle]')
  var $toggle
  var $target
  var what
  var eventNameExpanded
  if ($toggleOrTarget.hasClass('t_array')) {
    what = 'array'
    $target = $toggleOrTarget
    // don't need toggle
  } else if (isToggle) {
    what = $toggleOrTarget.data('toggle')
    $toggle = $toggleOrTarget
    $target = what === 'array'
      ? $toggle.closest('.t_array')
      : $toggle.next()
  } else {
    $target = $toggleOrTarget
    $toggle = $toggleOrTarget.prev()
    what = $toggle.data('toggle')
  }
  eventNameExpanded = 'expanded.debug.' + what
  // trigger while still hidden!
  //    no redraws
  $target.trigger('expand.debug.' + what)
  if (what === 'array') {
    $target.addClass('expanded').trigger(eventNameExpanded)
  } else {
    $target.slideDown('fast', function () {
      var $groupEndValue = $target.find('> .m_groupEndValue')
      $toggle.addClass('expanded')
      iconUpdate($toggle, config.iconsExpand.collapse)
      if ($groupEndValue.length) {
        // remove value from label
        $toggle.find('.group-label').last().nextAll().remove()
      }
      // setTimeout for reasons?...
      setTimeout(function () {
        $target.trigger(eventNameExpanded)
      })
    })
  }
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

function groupErrorIconUpdate ($toggle) {
  var selector = '.fa-times-circle, .fa-warning'
  var $group = $toggle.parent()
  var $target = $toggle.next()
  var icon = groupErrorIconGet($group)
  var isExpanded = $toggle.is('.expanded')
  $group.removeClass('empty') // 'empty' class just affects cursor
  if (icon) {
    if ($toggle.find(selector).length) {
      $toggle.find(selector).replaceWith(icon)
    } else {
      $toggle.append(icon)
    }
    iconUpdate($toggle, isExpanded
      ? config.iconsExpand.collapse
      : config.iconsExpand.expand
    )
  } else {
    $toggle.find(selector).remove()
    if ($target.children().not('.m_warn, .m_error').length < 1) {
      // group only contains errors & they're now hidden
      $group.addClass('empty')
      iconUpdate($toggle, config.iconsExpand.empty)
    }
  }
}

function iconUpdate ($toggle, classNameNew) {
  var $icon = $toggle.children('i').eq(0)
  if ($toggle.is('.group-header') && $toggle.parent().is('.empty')) {
    classNameNew = config.iconsExpand.empty
  }
  $.each(config.iconsExpand, function (i, className) {
    $icon.toggleClass(className, className === classNameNew)
  })
}

export function toggle (toggle) {
  var $toggle = $(toggle)
  var isExpanded = $toggle.hasClass('expanded')
  if ($toggle.is('.group-header') && $toggle.parent().is('.empty')) {
    return
  }
  if ($toggle.parent().hasClass('t_array')) {
    isExpanded = $toggle.parent().hasClass('expanded')
  }
  if (isExpanded) {
    collapse($toggle)
  } else {
    expand($toggle)
  }
}
