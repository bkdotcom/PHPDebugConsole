import $ from 'zest'

// config values
var config

var dict

export function init ($delegateNode) {
  config = $delegateNode.data('config').get()
  dict = $delegateNode.data('config').dict
  $delegateNode.on('click', '[data-toggle=vis]', function () {
    toggleVis(this)
    return false
  })
  $delegateNode.on('click', '[data-toggle=interface]', function () {
    toggleInterface(this)
    return false
  })
}

function addIcons ($node) {
  $.each(config.iconsObject, function (icon, selector) {
    var $found = addIconFind($node, selector)
    var matches = typeof icon === 'string'
      ? icon.match(/^([ap])\s*:(.+)$/)
      : null
    var prepend = !matches || matches[1] === 'p'
    if (matches) {
      icon = matches[2]
    }
    if (typeof icon === 'function') {
      // wrap function in a function that calls replaceTokens on the result
      var iconFunc = icon
      icon = function () {
        icon = iconFunc.apply(this, arguments)
        if (typeof icon === 'object') {
          icon = icon[0].outerHTML
        }
        return dict.replaceTokens(icon)
      }
    } else {
      icon = dict.replaceTokens(icon)
    }
    if (prepend) {
      addIconPrepend($found, icon)
      return
    }
    $found.append(icon)
  })
}

function addIconFind ($node, selector) {
  var sMatches = selector.match(/(?:parent(:\S+)\s)?(?:context(\S+)\s)?(.*)$/)
  if (sMatches === null) {
    return $node.find(selector)
  }
  if (sMatches[1] && $node.parent().filter(sMatches[1]).length === 0) {
    // no matches on parent selector.
    return $()
  }
  selector = sMatches[3]
  if (sMatches[2]) {
    // think of this as scss/sass's & selector
    return $node.filter(sMatches[2]).find(selector)
  }
  return $node.find(selector)
}

function addIconPrepend ($dest, icon) {
  // add icon to destinations having two icons
  var $existingIcon = $dest.find('> i:first-child + i').after(icon)
  $dest = $dest.not($existingIcon.parent())
  // add icon to destinations having one icon
  $existingIcon = $dest.find('> i:first-child').after(icon)
  $dest = $dest.not($existingIcon.parent())
  // add icon to destination that did not have icon
  $dest.prepend(icon)
}

/**
 * Adds toggle icon & hides target
 * Minimal DOM manipulation -> apply to all descendants
 */
export function enhance ($node) {
  var selectors = $node.find('> .t_identifier').length
    ? ['> .t_identifier']
    : ['> .classname', '> .t_const']
  $node.find(selectors.join(',')).each(function () {
    var $toggle = $(this)
    var $target = $toggle.next()
    var isEnhanced = $toggle.data('toggle') === 'object'
    if ($target.is('.t_maxDepth, .t_recursion, .excluded')) {
      $toggle.addClass('empty')
      return
    }
    if (isEnhanced) {
      return
    }
    if ($target.length === 0) {
      return
    }
    $toggle.wrap('<span data-toggle="object"></span>')
      .after(' <i class="fa ' + config.iconsExpand.expand + '"></i>')
    $target.hide()
  })
}

export function enhanceInner ($obj) {
  var $inner = $obj.find('> .object-inner')
  var accessible = $obj.data('accessible')
  var callPostToggle = null // or "local", or "allDesc"
  if ($obj.is('.enhanced')) {
    return
  }
  $inner.find('> .private, > .protected')
    .filter('.magic, .magic-read, .magic-write')
    .removeClass('private protected')
  if (accessible === 'public') {
    $inner.find('.private, .protected').hide()
    callPostToggle = 'allDesc'
  }
  enhanceInterfaces($obj)
  visToggles($inner, accessible)
  addIcons($inner)
  $inner.find('> .property.forceShow').show().find('> .t_array').debugEnhance('expand')
  if (callPostToggle) {
    postToggle($obj, callPostToggle === 'allDesc')
  }
  $obj.addClass('enhanced')
}

function enhanceInterfaces ($obj) {
  var $inner = $obj.find('> .object-inner')
  $inner.find('> dd.interface, > dd.implements .interface')
    .each(function () {
      var iface = $(this).text()
      if (findInterfaceMethods($obj, iface).length === 0) {
        return
      }
      $(this)
        .addClass('toggle-on')
        .prop('title', 'toggle interface methods')
        .attr('data-toggle', 'interface')
        .attr('data-interface', iface)
    })
    .filter('.toggle-off').removeClass('toggle-off').each(function () {
      // element may have toggle-off to begin with...
      toggleInterface(this)
    })
}

/**
 * Add toggles for protected, private excluded inherited
 */
function visToggles ($inner, accessible) {
  var flags = {
    hasProtected: $inner.children('.protected').not('.magic, .magic-read, .magic-write').length > 0,
    hasPrivate: $inner.children('.private').not('.magic, .magic-read, .magic-write').length > 0,
    hasExcluded: $inner.children('.debuginfo-excluded').hide().length > 0,
    hasInherited: $inner.children('dd[data-inherited-from]').length > 0
  }
  var $visToggles = visTogglesGet(flags, accessible)
  if ($inner.find('> dd[class*=t_modifier_]').length) {
    $inner.find('> dd[class*=t_modifier_]').last().after($visToggles)
    return
  }
  $inner.prepend($visToggles)
}

function visTogglesGet (flags, accessible) {
  var $visToggles = $('<div class="vis-toggles"></div>')
  var toggleClass = accessible === 'public'
    ? 'toggle-off'
    : 'toggle-on'
  var toggleVerb = accessible === 'public'
    ? 'show'
    : 'hide'
  var toggles = {
    hasProtected: '<span class="' + toggleClass + '" data-toggle="vis" data-vis="protected">' + toggleVerb + ' protected</span>',
    hasPrivate: '<span class="' + toggleClass + '" data-toggle="vis" data-vis="private">' + toggleVerb + ' private</span>',
    hasExcluded: '<span class="toggle-off" data-toggle="vis" data-vis="debuginfo-excluded">show excluded</span>',
    hasInherited: '<span class="toggle-on" data-toggle="vis" data-vis="inherited">hide inherited</span>',
  }
  $.each(flags, function (val, name) {
    if (val) {
      $visToggles.append(toggles[name])
    }
  })
  return $visToggles
}

function toggleInterface (toggle) {
  var $toggle = $(toggle)
  var $obj = $toggle.closest('.t_object')
  $toggle = $toggle.is('.toggle-off')
    ? $toggle.add($toggle.next().find('.toggle-off'))
    : $toggle.add($toggle.next().find('.toggle-on'))
  $toggle.each(function () {
    var $toggle = $(this)
    var iface = $toggle.data('interface')
    var $methods = findInterfaceMethods($obj, iface)
    if ($toggle.is('.toggle-off')) {
      $toggle.addClass('toggle-on').removeClass('toggle-off')
      $methods.show()
    } else {
      $toggle.addClass('toggle-off').removeClass('toggle-on')
      $methods.hide()
    }
  })
  postToggle($obj)
}

function findInterfaceMethods ($obj, iface) {
    var selector = '> .object-inner > dd[data-implements="' + CSS.escape(iface) + '"]'
    return $obj.find(selector)
}

/**
 * Toggle visibility for private/protected properties and methods
 */
function toggleVis (toggle) {
  // console.log('toggleVis', toggle)
  var $toggle = $(toggle)
  var vis = $toggle.data('vis')
  var $obj = $toggle.closest('.t_object')
  var $objInner = $obj.find('> .object-inner')
  var $toggles = $objInner.find('[data-toggle=vis][data-vis=' + vis + ']')
  var selector = vis === 'inherited'
    ? 'dd[data-inherited-from], .private-ancestor'
    : '.' + vis
  var $nodes = $objInner.find(selector)
  var show = $toggle.hasClass('toggle-off')
  $toggles
    .html($toggle.html().replace(
      show ? 'show ' : 'hide ',
      show ? 'hide ' : 'show '
    ))
    .addClass(show ? 'toggle-on' : 'toggle-off')
    .removeClass(show ? 'toggle-off' : 'toggle-on')
  show
    ? toggleVisNodes($nodes) // show for this and all descendants.. unless hidden by other toggle
    : $nodes.hide() // simply hide for this and all descendants
  postToggle($obj, true)
}

function toggleVisNodes ($nodes) {
  $nodes.each(function () {
    var $node = $(this)
    var $objInner = $node.closest('.object-inner')
    var show = true
    $objInner.find('> .vis-toggles [data-toggle]').each(function () {
      var $toggle = $(this)
      var isOn = $toggle.hasClass('toggle-on')
      var vis = $toggle.data('vis')
      var filter = vis === 'inherited'
        ? 'dd[data-inherited-from], .private-ancestor'
        : '.' + vis
      if (!isOn && $node.filter(filter).length === 1) {
        show = false
        return false // break
      }
    })
    if (show) {
      $node.show()
    }
  })
}

function postToggle ($obj, allDescendants) {
  var selector = allDescendants
    ? '.object-inner > dt'
    : '> .object-inner > dt'
  var selector2 = allDescendants
    ? '.object-inner > .heading'
    : '> .object-inner > .heading'
  $obj.find(selector).each(function (dt) {
    var $dds = $(dt).nextUntil('dt')
    var $ddsVis = $dds.not('.heading').filter(function (node) {
      return $(node).style('display') !== 'none'
    })
    var allHidden = $dds.length > 0 && $ddsVis.length === 0
    $(dt).toggleClass('text-muted', allHidden)
  })
  $obj.find(selector2).each(function (heading) {
    var $dds = $(heading).nextUntil('dt, .heading')
    var $ddsVis = $dds.filter(function (node) {
      return $(node).style('display') !== 'none'
    })
    var allHidden = $dds.length > 0 && $ddsVis.length === 0
    $(heading).toggleClass('text-muted', allHidden)
  })

  $obj.trigger('expanded.debug.object')
}
