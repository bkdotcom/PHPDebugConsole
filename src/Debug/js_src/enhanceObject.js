import $ from 'jquery'

var config

export function init ($delegateNode) {
  config = $delegateNode.data('config').get()
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
  // console.warn('addIcons', $node)
  $.each(config.iconsObject, function (selector, v) {
    var prepend = true
    var matches = v.match(/^([ap])\s*:(.+)$/)
    if (matches) {
      prepend = matches[1] === 'p'
      v = matches[2]
    }
    if (prepend) {
      $node.find(selector).prepend(v)
    } else {
      $node.find(selector).append(v)
    }
  })
}

/**
 * Adds toggle icon & hides target
 * Minimal DOM manipulation -> apply to all descendants
 */
export function enhance ($node) {
  $node.find('> .classname').each(function () {
    var $classname = $(this)
    var $target = $classname.next()
    var isEnhanced = $classname.data('toggle') === 'object'
    if ($target.is('.t_recursion, .excluded')) {
      $classname.addClass('empty')
      return
    }
    if (isEnhanced) {
      return
    }
    $classname.wrap('<span data-toggle="object"></span>')
      .after(' <i class="fa ' + config.iconsExpand.expand + '"></i>')
    $target.hide()
  })
}

export function enhanceInner ($nodeObj) {
  var $inner = $nodeObj.find('> .object-inner')
  var accessible = $nodeObj.data('accessible')
  var hiddenInterfaces = []
  if ($nodeObj.is('.enhanced')) {
    return
  }
  if ($inner.find('> .method[data-implements]').hide().length) {
    // linkify visibility
    $inner.find('> .method[data-implements]').each(function () {
      var iface = $(this).data('implements')
      if (hiddenInterfaces.indexOf(iface) < 0) {
        hiddenInterfaces.push(iface)
      }
    })
    $.each(hiddenInterfaces, function (i, iface) {
      $inner.find('> .interface').each(function () {
        var html = '<span class="toggle-off" data-toggle="interface" data-interface="' + iface + '" title="toggle methods">' +
            '<i class="fa fa-eye-slash"></i>' + iface + '</span>'
        if ($(this).text() === iface) {
          $(this).html(html)
        }
      })
    })
  }
  $inner.find('> .private, > .protected')
    .filter('.magic, .magic-read, .magic-write')
    .removeClass('private protected')
  if (accessible === 'public') {
    $inner.find('.private, .protected').hide()
  }
  visToggles($inner, accessible)
  addIcons($inner)
  $inner.find('> .property.forceShow').show().find('> .t_array').debugEnhance('expand')
  $nodeObj.addClass('enhanced')
}

function visToggles ($inner, accessible) {
  var flags = {
    hasProtected: $inner.children('.protected').not('.magic, .magic-read, .magic-write').length > 0,
    hasPrivate: $inner.children('.private').not('.magic, .magic-read, .magic-write').length > 0,
    hasExcluded: $inner.children('.debuginfo-excluded').hide().length > 0,
    hasInherited: $inner.children('.inherited').length > 0
  }
  var toggleClass = accessible === 'public'
    ? 'toggle-off'
    : 'toggle-on'
  var toggleVerb = accessible === 'public'
    ? 'show'
    : 'hide'
  var $visToggles = $('<div class="vis-toggles"></div>')
  if (flags.hasProtected) {
    $visToggles.append('<span class="' + toggleClass + '" data-toggle="vis" data-vis="protected">' + toggleVerb + ' protected</span>')
  }
  if (flags.hasPrivate) {
    $visToggles.append('<span class="' + toggleClass + '" data-toggle="vis" data-vis="private">' + toggleVerb + ' private</span>')
  }
  if (flags.hasExcluded) {
    $visToggles.append('<span class="toggle-off" data-toggle="vis" data-vis="debuginfo-excluded">show excluded</span>')
  }
  if (flags.hasInherited) {
    $visToggles.append('<span class="toggle-on" data-toggle="vis" data-vis="inherited">hide inherited methods</span>')
  }
  if ($inner.find('> dt.t_modifier_final').length) {
    $inner.find('> dt.t_modifier_final').after($visToggles)
    return
  }
  $inner.prepend($visToggles)
}

function toggleInterface (toggle) {
  var $toggle = $(toggle)
  var iface = $toggle.data('interface')
  var $methods = $toggle.closest('.t_object').find('> .object-inner > dd[data-implements=' + iface + ']')
  if ($toggle.is('.toggle-off')) {
    $toggle.addClass('toggle-on').removeClass('toggle-off')
    $methods.show()
  } else {
    $toggle.addClass('toggle-off').removeClass('toggle-on')
    $methods.hide()
  }
}

/**
 * Toggle visibility for private/protected properties and methods
 */
function toggleVis (toggle) {
  // console.log('toggleVis', toggle)
  var $toggle = $(toggle)
  var vis = $toggle.data('vis')
  var $objInner = $toggle.closest('.object-inner')
  var $toggles = $objInner.find('[data-toggle=vis][data-vis=' + vis + ']')
  var $nodes = $objInner.find('.' + vis)
  if ($toggle.is('.toggle-off')) {
    // show for this and all descendants
    $toggles
      .html($toggle.html().replace('show ', 'hide '))
      .addClass('toggle-on')
      .removeClass('toggle-off')
    $nodes.each(function () {
      var $node = $(this)
      var $objInner = $node.closest('.object-inner')
      var show = true
      $objInner.find('> .vis-toggles [data-toggle]').each(function () {
        var $toggle = $(this)
        var vis = $toggle.data('vis')
        var isOn = $toggle.is('.toggle-on')
        // if any applicable test is false, don't show it
        if (!isOn && $node.hasClass(vis)) {
          show = false
          return false // break
        }
      })
      if (show) {
        $node.show()
      }
    })
  } else {
    // hide for this and all descendants
    $toggles
      .html($toggle.html().replace('hide ', 'show '))
      .addClass('toggle-off')
      .removeClass('toggle-on')
    $nodes.hide()
  }
}
